<?php
// ข้อ 2.7: กระบวนการขอและสร้าง OTP ร่วมกัน
// ไม่สร้างตาราง ไม่เปลี่ยนรหัสผ่าน และไม่ออก Session ล็อกอิน

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit();
}

function otpRequestConfig()
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $loaded = require __DIR__ . '/otp_config.php';

    $required = [
        'otp_lifetime_seconds',
        'account_cooldown_seconds',
        'account_window_seconds',
        'account_request_limit',
        'source_short_window_seconds',
        'source_short_request_limit',
        'source_hour_window_seconds',
        'source_hour_request_limit',
    ];

    if (!is_array($loaded)) {
        throw new RuntimeException('Invalid OTP configuration');
    }

    foreach ($required as $key) {
        if (!isset($loaded[$key])
            || !is_int($loaded[$key])
            || $loaded[$key] < 1
            || $loaded[$key] > 2147483647) {
            throw new RuntimeException('Invalid OTP configuration');
        }
    }

    $config = $loaded;
    return $config;
}

function otpRequestStatement($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('OTP database operation failed');
    }

    try {
        if ($types !== ''
            && !$stmt->bind_param($types, ...$params)) {
            throw new RuntimeException('OTP database operation failed');
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('OTP database operation failed');
        }

        return $stmt;

    } catch (Throwable $e) {
        $stmt->close();
        throw $e;
    }
}

function otpRequestRow($stmt)
{
    try {
        $result = $stmt->get_result();

        if (!$result) {
            throw new RuntimeException('OTP database operation failed');
        }

        $row = $result->num_rows === 1
            ? $result->fetch_assoc()
            : null;

        $result->free();
        return $row;

    } finally {
        $stmt->close();
    }
}

function otpRequestCheckEngines($conn)
{
    $result = $conn->query(
        "SELECT TABLE_NAME, ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN (
               'users',
               'technicians',
               'auth_requests',
               'otp_source_limits'
           )"
    );

    if (!$result) {
        throw new RuntimeException('OTP table check failed');
    }

    try {
        if ($result->num_rows !== 4) {
            throw new RuntimeException('OTP table check failed');
        }

        while ($row = $result->fetch_assoc()) {
            if (strtoupper((string) $row['ENGINE']) !== 'INNODB') {
                throw new RuntimeException('OTP tables require InnoDB');
            }
        }

    } finally {
        $result->free();
    }
}

// ใช้ IP ที่เซิร์ฟเวอร์เห็นจริง
// ไม่เชื่อ X-Forwarded-For หรือ header ที่ผู้เรียกปลอมได้
function otpRequestSourceIp()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!is_string($ip)
        || !filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new RuntimeException('Invalid OTP request source');
    }

    $packed = inet_pton($ip);

    if ($packed === false) {
        throw new RuntimeException('Invalid OTP request source');
    }

    // ทำ IPv4-mapped IPv6 ให้ใช้ตัวนับเดียวกับ IPv4
    if (strlen($packed) === 16
        && substr($packed, 0, 12)
            === str_repeat("\0", 10) . "\xff\xff") {
        $packed = substr($packed, 12);
    }

    return $packed;
}

// นับตาม IP ก่อนค้นบัญชี เพื่อรวม Username ที่ไม่มีอยู่จริง
// Transaction นี้แยกจากการสร้าง OTP:
// แม้บัญชีไม่เข้าเกณฑ์ ตัวนับ IP ที่รับไว้แล้วจะไม่ถูกย้อนกลับ
function otpRequestAdmitSource($conn, $config)
{
    $source_ip = otpRequestSourceIp();
    $transaction_started = false;

    try {
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('OTP source transaction failed');
        }

        $transaction_started = true;

        // สร้างรายการเมื่อพบ IP ครั้งแรก
        // หากมีอยู่แล้ว การ UPDATE จะล็อกรายการเดิม
        $stmt = otpRequestStatement(
            $conn,
            "INSERT INTO otp_source_limits (
                source_ip,
                short_window_started_at,
                short_request_count,
                hour_window_started_at,
                hour_request_count,
                updated_at
             ) VALUES (?, NOW(), 0, NOW(), 0, NOW())
             ON DUPLICATE KEY UPDATE source_ip = source_ip",
            's',
            [$source_ip]
        );
        $stmt->close();

        $row = otpRequestRow(
            otpRequestStatement(
                $conn,
                "SELECT
                    short_request_count,
                    hour_request_count,
                    TIMESTAMPDIFF(
                        SECOND, short_window_started_at, NOW()
                    ) AS short_age,
                    TIMESTAMPDIFF(
                        SECOND, hour_window_started_at, NOW()
                    ) AS hour_age
                 FROM otp_source_limits
                 WHERE source_ip = ?
                 FOR UPDATE",
                's',
                [$source_ip]
            )
        );

        if (!$row) {
            throw new RuntimeException('OTP source record missing');
        }

        $reset_short = (int) $row['short_age']
            >= $config['source_short_window_seconds'];
        $reset_hour = (int) $row['hour_age']
            >= $config['source_hour_window_seconds'];

        $short_count = $reset_short
            ? 0
            : (int) $row['short_request_count'];

        $hour_count = $reset_hour
            ? 0
            : (int) $row['hour_request_count'];

        if ($short_count >= $config['source_short_request_limit']
            || $hour_count >= $config['source_hour_request_limit']) {
            if (!$conn->rollback()) {
                throw new RuntimeException('OTP source rollback failed');
            }

            $transaction_started = false;
            return false;
        }

        $short_count++;
        $hour_count++;

        $reset_short_flag = $reset_short ? 1 : 0;
        $reset_hour_flag = $reset_hour ? 1 : 0;

        $stmt = otpRequestStatement(
            $conn,
            "UPDATE otp_source_limits
             SET short_window_started_at =
                    IF(? = 1, NOW(), short_window_started_at),
                 short_request_count = ?,
                 hour_window_started_at =
                    IF(? = 1, NOW(), hour_window_started_at),
                 hour_request_count = ?,
                 updated_at = NOW()
             WHERE source_ip = ?",
            'iiiis',
            [
                $reset_short_flag,
                $short_count,
                $reset_hour_flag,
                $hour_count,
                $source_ip,
            ]
        );
        $stmt->close();

        if (!$conn->commit()) {
            throw new RuntimeException('OTP source commit failed');
        }

        $transaction_started = false;
        return true;

    } catch (Throwable $e) {
        if ($transaction_started) {
            $conn->rollback();
        }

        throw $e;
    }
}

function otpRequestCancel($conn, $request_id)
{
    $stmt = otpRequestStatement(
        $conn,
        "UPDATE auth_requests
         SET is_canceled = 1
         WHERE id = ? AND used_at IS NULL",
        'i',
        [$request_id]
    );
    $stmt->close();
}

/*
 * ต้องเรียกหลัง Endpoint ตรวจ CSRF แล้วเท่านั้น
 *
 * password_reset:
 *   $identifier = Username
 *
 * email_verification:
 *   $identifier = user_id จาก Session
 *   $expected_auth_version = auth_version จาก Session
 *
 * ผลลัพธ์:
 *   sent             ส่งเมลสำเร็จ คืนเฉพาะ token และอายุ
 *   source_limit     เกินโควตา IP
 *   account_limit    เกินโควตาบัญชี
 *   cooldown         ยังไม่ครบระยะเว้นส่ง
 *   already_verified อีเมลยืนยันแล้ว
 *   not_eligible     บัญชีไม่เข้าเงื่อนไข
 *   error            ทำรายการหรือส่งอีเมลไม่สำเร็จ
 *
 * ไม่คืน OTP อีเมล หรือข้อมูลบัญชีให้ผู้เรียก
 */
function otpRequestCreateAndSend(
    $conn,
    $purpose,
    $identifier,
    $expected_auth_version = null
) {
    $transaction_started = false;
    $request_id = null;

    try {
        if (!in_array(
            $purpose,
            ['password_reset', 'email_verification'],
            true
        )) {
            throw new RuntimeException('Invalid OTP purpose');
        }

        $config = otpRequestConfig();
        otpRequestCheckEngines($conn);

        if (!otpRequestAdmitSource($conn, $config)) {
            return ['status' => 'source_limit'];
        }

        if ($purpose === 'password_reset') {
            if (!is_string($identifier)
                || trim($identifier) === ''
                || strlen($identifier) > 1024) {
                return ['status' => 'not_eligible'];
            }

            $identifier = trim($identifier);

        } else {
            $identifier = filter_var(
                $identifier,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $expected_auth_version = filter_var(
                $expected_auth_version,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($identifier === false
                || $expected_auth_version === false) {
                return ['status' => 'not_eligible'];
            }
        }

        if (!$conn->begin_transaction()) {
            throw new RuntimeException('OTP account transaction failed');
        }

        $transaction_started = true;

        // ล็อกบัญชีก่อนตรวจจำนวนคำขอ
        // ผู้ขอพร้อมกันสำหรับบัญชีเดียวกันต้องรอกัน
        $where = $purpose === 'password_reset'
            ? 'username = ?'
            : 'id = ?';

        $types = $purpose === 'password_reset' ? 's' : 'i';

        $user = otpRequestRow(
            otpRequestStatement(
                $conn,
                "SELECT id, role, is_active, technician_id,
                        email, verified_email, email_verified_at,
                        auth_version
                 FROM users
                 WHERE $where
                 FOR UPDATE",
                $types,
                [$identifier]
            )
        );

        $stop = null;
        $target_email = null;

        if (!$user || (int) $user['is_active'] !== 1) {
            $stop = 'not_eligible';

        } elseif ($purpose === 'email_verification'
            && (string) $user['auth_version']
                !== (string) $expected_auth_version) {
            $stop = 'not_eligible';
        }

        if ($stop === null) {
            $role = strtolower((string) $user['role']);

            if ($role === 'technician') {
                $tech_id = filter_var(
                    $user['technician_id'],
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($tech_id === false) {
                    $stop = 'not_eligible';

                } else {
                    $technician = otpRequestRow(
                        otpRequestStatement(
                            $conn,
                            "SELECT id, email
                             FROM technicians
                             WHERE id = ?
                             FOR UPDATE",
                            'i',
                            [$tech_id]
                        )
                    );

                    if (!$technician) {
                        $stop = 'not_eligible';
                    } else {
                        $target_email = $technician['email'];
                    }
                }

            } elseif (in_array(
                $role,
                ['admin', 'executive'],
                true
            ) && $user['technician_id'] === null) {
                $target_email = $user['email'];

            } else {
                $stop = 'not_eligible';
            }
        }

        if ($stop === null) {
            if (!is_string($target_email)) {
                $stop = 'not_eligible';

            } else {
                $target_email = trim($target_email);

                if ($target_email === ''
                    || strlen($target_email) > 255
                    || !filter_var(
                        $target_email,
                        FILTER_VALIDATE_EMAIL
                    )) {
                    $stop = 'not_eligible';
                }
            }
        }

        if ($stop === null) {
            $email_verified =
                is_string($user['verified_email'])
                && $user['verified_email'] === $target_email
                && !empty($user['email_verified_at']);

            if ($purpose === 'password_reset' && !$email_verified) {
                $stop = 'not_eligible';

            } elseif ($purpose === 'email_verification'
                && $email_verified) {
                $stop = 'already_verified';
            }
        }

        if ($stop !== null) {
            if (!$conn->rollback()) {
                throw new RuntimeException('OTP account rollback failed');
            }

            $transaction_started = false;
            return ['status' => $stop];
        }

        $user_id = (int) $user['id'];

        // นับรวมคำขอที่ยกเลิก ใช้แล้ว และส่งอีเมลล้มเหลว
        // ใช้ locking read เพื่ออ่านข้อมูลล่าสุดหลังรอล็อกบัญชี
        $history_stmt = otpRequestStatement(
            $conn,
            "SELECT
                TIMESTAMPDIFF(SECOND, created_at, NOW())
                    AS created_age,
                TIMESTAMPDIFF(SECOND, last_sent_at, NOW())
                    AS sent_age
             FROM auth_requests
             WHERE user_id = ?
               AND purpose = ?
               AND (
                   created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                   OR last_sent_at >=
                        DATE_SUB(NOW(), INTERVAL ? SECOND)
               )
             FOR UPDATE",
            'isii',
            [
                $user_id,
                $purpose,
                $config['account_window_seconds'],
                $config['account_cooldown_seconds'],
            ]
        );

        $history = $history_stmt->get_result();

        if (!$history) {
            $history_stmt->close();
            throw new RuntimeException('OTP history check failed');
        }

        $request_count = 0;
        $in_cooldown = false;

        while ($row = $history->fetch_assoc()) {
            if ((int) $row['created_age']
                <= $config['account_window_seconds']) {
                $request_count++;
            }

            if ((int) $row['sent_age']
                < $config['account_cooldown_seconds']) {
                $in_cooldown = true;
            }
        }

        $history->free();
        $history_stmt->close();

        if ($in_cooldown
            || $request_count >= $config['account_request_limit']) {
            if (!$conn->rollback()) {
                throw new RuntimeException('OTP account rollback failed');
            }

            $transaction_started = false;

            return [
                'status' => $in_cooldown
                    ? 'cooldown'
                    : 'account_limit',
            ];
        }

        $otp_code = sprintf('%06d', random_int(0, 999999));
        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);

        if (!is_string($otp_hash)) {
            throw new RuntimeException('OTP hash failed');
        }

        $request_token = bin2hex(random_bytes(32));

        // ยกเลิกคำขอเดิมของวัตถุประสงค์เดียวกัน
        // รวมคำขอที่เคยตรวจ OTP ผ่านแต่ยังมีสิทธิ์รีเซ็ตค้างอยู่
        $stmt = otpRequestStatement(
            $conn,
            "UPDATE auth_requests
             SET is_canceled = 1
             WHERE user_id = ?
               AND purpose = ?
               AND is_canceled = 0",
            'is',
            [$user_id, $purpose]
        );
        $stmt->close();

        $auth_version = (int) $user['auth_version'];

        $stmt = otpRequestStatement(
            $conn,
            "INSERT INTO auth_requests (
                request_token,
                user_id,
                purpose,
                target_email,
                otp_hash,
                failed_attempts,
                created_at,
                last_sent_at,
                expires_at,
                verified_at,
                used_at,
                is_canceled,
                auth_version
             ) VALUES (
                ?, ?, ?, ?, ?, 0,
                NOW(), NOW(),
                DATE_ADD(NOW(), INTERVAL ? SECOND),
                NULL, NULL, 0, ?
             )",
            'sisssii',
            [
                $request_token,
                $user_id,
                $purpose,
                $target_email,
                $otp_hash,
                $config['otp_lifetime_seconds'],
                $auth_version,
            ]
        );

        $request_id = (int) $conn->insert_id;
        $stmt->close();

        if ($request_id < 1) {
            throw new RuntimeException('OTP request insert failed');
        }

        if (!$conn->commit()) {
            throw new RuntimeException('OTP account commit failed');
        }

        $transaction_started = false;

        // ส่งหลัง Commit เพื่อไม่ล็อกบัญชีระหว่างรอ SMTP
        require_once __DIR__ . '/mailer.php';

        $mail_purpose = $purpose === 'password_reset'
            ? 'รีเซ็ตรหัสผ่าน'
            : 'ยืนยันบัญชีอีเมล';

        try {
            $is_sent = sendOtpEmail(
                $target_email,
                $otp_code,
                $mail_purpose
            );
        } finally {
            unset($otp_code);
        }

        if (!$is_sent) {
            otpRequestCancel($conn, $request_id);
            return ['status' => 'error'];
        }

        // ระหว่างรอ SMTP บัญชีหรือคำขออาจถูกเปลี่ยน
        // ไม่คืนโทเคนเป็นผลสำเร็จหากคำขอใช้ต่อไม่ได้แล้ว
        $live_request = otpRequestRow(
            otpRequestStatement(
                $conn,
                "SELECT a.id
                 FROM auth_requests a
                 JOIN users u ON u.id = a.user_id
                 WHERE a.id = ?
                   AND a.is_canceled = 0
                   AND a.used_at IS NULL
                   AND a.expires_at > NOW()
                   AND u.is_active = 1
                   AND u.auth_version = a.auth_version",
                'i',
                [$request_id]
            )
        );

        if (!$live_request) {
            otpRequestCancel($conn, $request_id);
            return ['status' => 'error'];
        }

        return [
            'status' => 'sent',
            'token' => $request_token,
            'expires_in' => $config['otp_lifetime_seconds'],
        ];

    } catch (Throwable $e) {
        if ($transaction_started) {
            try {
                $conn->rollback();
            } catch (Throwable $rollback_error) {
                // ไม่แสดงรายละเอียดฐานข้อมูลออกหน้าเว็บ
            }

        } elseif ($request_id !== null && $request_id > 0) {
            try {
                otpRequestCancel($conn, $request_id);
            } catch (Throwable $cancel_error) {
                error_log('OTP request cancellation failed');
            }
        }

        // ไม่บันทึก OTP โทเคน อีเมล หรือรหัสผ่านลง Log
        error_log('OTP request processing failed');

        return ['status' => 'error'];
    }
}