<?php
require_once 'auth_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ยืนยันอีเมลได้เฉพาะบัญชีของผู้ที่ล็อกอินอยู่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'คำขอไม่ถูกต้อง'
    ]);
    exit();
}

$user_id = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($user_id === false
    || !isset($_SESSION['auth_version'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ'
    ]);
    exit();
}

$csrf_received = $_POST['csrf_token'] ?? null;
$csrf_expected = $_SESSION['csrf_token'] ?? null;

if (!is_string($csrf_received)
    || !is_string($csrf_expected)
    || $csrf_expected === ''
    || !hash_equals($csrf_expected, $csrf_received)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่'
    ]);
    exit();
}

$action = $_POST['action'] ?? null;

if (!is_string($action)
    || !in_array($action, ['request_otp', 'verify_otp'], true)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'คำสั่งไม่ถูกต้อง'
    ]);
    exit();
}

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.role,
        u.is_active,
        u.auth_version,
        u.technician_id,
        u.email AS account_email,
        u.verified_email,
        u.email_verified_at,
        t.id AS found_technician_id,
        t.email AS tech_email
    FROM users u
    LEFT JOIN technicians t ON u.technician_id = t.id
    WHERE u.id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถตรวจข้อมูลบัญชีได้'
    ]);
    exit();
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถตรวจข้อมูลบัญชีได้'
    ]);
    exit();
}

$user_result = $stmt->get_result();
$user = $user_result && $user_result->num_rows === 1
    ? $user_result->fetch_assoc()
    : null;

$stmt->close();

if (!$user
    || (int) $user['is_active'] !== 1
    || (string) $user['auth_version']
        !== (string) $_SESSION['auth_version']) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'บัญชีหรือสิทธิ์การใช้งานเปลี่ยนไป กรุณาเข้าสู่ระบบใหม่'
    ]);
    exit();
}

$role = strtolower((string) $user['role']);

if ($role === 'technician') {
    $technician_id = filter_var(
        $user['technician_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $found_technician_id = filter_var(
        $user['found_technician_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($technician_id === false
        || $found_technician_id === false
        || $technician_id !== $found_technician_id) {
        echo json_encode([
            'status' => 'error',
            'message' => 'บัญชีไม่ได้เชื่อมกับช่างที่มีอยู่จริง กรุณาติดต่อแอดมิน'
        ]);
        exit();
    }

    $email_source = $user['tech_email'];

} elseif (in_array($role, ['admin', 'executive'], true)) {
    if ($user['technician_id'] !== null) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ข้อมูลการเชื่อมบัญชีไม่ถูกต้อง กรุณาติดต่อแอดมิน'
        ]);
        exit();
    }

    $email_source = $user['account_email'];

} else {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'บัญชีนี้ไม่มีสิทธิ์ใช้การยืนยันอีเมล'
    ]);
    exit();
}

$target_email = is_string($email_source)
    ? trim($email_source)
    : '';

if ($target_email === ''
    || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่มีอีเมลหรือรูปแบบอีเมลไม่ถูกต้อง กรุณาติดต่อแอดมินเพื่อแก้ไขข้อมูลก่อน'
    ]);
    exit();
}

// ---------------------------------------------------------
// Action 1: ขอรับรหัส OTP (Request OTP)
// ---------------------------------------------------------
if ($action === 'request_otp') {
    try {
        require_once __DIR__ . '/otp_request_service.php';

        $result = otpRequestCreateAndSend(
            $conn,
            'email_verification',
            $user_id,
            $_SESSION['auth_version'] ?? null
        );

        $result_status = $result['status'] ?? 'error';

        if ($result_status === 'sent'
            && isset($result['token'])
            && is_string($result['token'])
            && preg_match('/\A[a-f0-9]{64}\z/', $result['token'])) {
            echo json_encode([
                'status' => 'success',
                'message' => 'ระบบได้ส่งรหัส OTP ไปยังอีเมลที่บันทึกไว้แล้ว'
                    . ' (รหัสมีอายุ '
                    . (int) $result['expires_in']
                    . ' วินาที)',
                'token' => $result['token'],
            ], JSON_UNESCAPED_UNICODE);

            exit();
        }

        $messages = [
            'source_limit' =>
                'มีการขอรหัสจากเครือข่ายนี้มากเกินไป กรุณารอแล้วลองใหม่',
            'account_limit' =>
                'บัญชีนี้ขอรหัสครบจำนวนที่กำหนดแล้ว กรุณารอแล้วลองใหม่',
            'cooldown' =>
                'ยังไม่ครบระยะเว้นการส่งรหัส กรุณารอแล้วลองใหม่',
            'already_verified' =>
                'อีเมลนี้ได้รับการยืนยันเรียบร้อยแล้ว',
            'not_eligible' =>
                'ข้อมูลบัญชีหรืออีเมลเปลี่ยนไป กรุณารีเฟรชหน้า'
                . ' หากยังทำรายการไม่ได้ให้ติดต่อแอดมิน',
            'error' =>
                'ไม่สามารถสร้างคำขอหรือส่งอีเมลได้ กรุณาลองใหม่ภายหลัง',
        ];

        echo json_encode([
            'status' => 'error',
            'message' => $messages[$result_status] ?? $messages['error'],
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        error_log('Email verification OTP request failed');

        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถทำรายการได้ กรุณาลองใหม่ภายหลัง',
        ], JSON_UNESCAPED_UNICODE);
    }

    exit();
}

// ---------------------------------------------------------
// Action 2: ตรวจสอบ OTP (Verify OTP)
// ---------------------------------------------------------
elseif ($action === 'verify_otp') {
        $token = $_POST['token'] ?? null;
    $otp_input = $_POST['otp'] ?? null;

    if (!is_string($token)
        || !preg_match('/\A[a-f0-9]{64}\z/', $token)
        || !is_string($otp_input)
        || !preg_match('/\A[0-9]{6}\z/', $otp_input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกรหัส OTP เป็นตัวเลข 6 หลักจากคำขอล่าสุด'
        ]);
        exit();
    }

    // ค้นหาคำขอที่ยังไม่ถูกยกเลิก ยังไม่หมดอายุ และจุดประสงค์ถูกต้อง พร้อมเช็ค auth_version ปัจจุบัน
    $stmt_req = $conn->prepare("
        SELECT a.id, a.otp_hash, a.target_email, a.failed_attempts, a.expires_at, a.auth_version AS req_auth_version, u.auth_version AS current_auth_version 
        FROM auth_requests a
        JOIN users u ON a.user_id = u.id
        WHERE a.request_token = ? AND a.user_id = ? AND a.purpose = 'email_verification' AND a.is_canceled = 0 AND a.used_at IS NULL
    ");
    $stmt_req->bind_param("si", $token, $user_id);
    $stmt_req->execute();
    $request = $stmt_req->get_result()->fetch_assoc();
    $stmt_req->close();

    if (!$request) {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง หรือถูกใช้งานไปแล้ว']);
        exit();
    }

    // ตรวจสอบวันหมดอายุ (Double check ฝั่ง PHP)
    if (strtotime($request['expires_at']) < time()) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณาขอใหม่']);
        exit();
    }

    // ตรวจสอบการเปลี่ยนแปลงสิทธิ์
    if ($request['req_auth_version'] != $request['current_auth_version']) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลบัญชีถูกเปลี่ยนแปลงระหว่างดำเนินการ คำขอถูกยกเลิก']);
        exit();
    }

    // ตรวจสอบจำนวนครั้งที่กรอกผิด
    if ($request['failed_attempts'] >= 5) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'คุณกรอกรหัสผิดเกินจำนวนที่กำหนด คำขอนี้ถูกยกเลิกแล้ว']);
        exit();
    }

    // ตรวจสอบว่าอีเมลยังตรงกับตอนที่ขอ OTP ไหม
    if ($request['target_email'] !== $target_email) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'อีเมลของคุณถูกเปลี่ยนแปลงระหว่างดำเนินการ กรุณาขอรหัสใหม่']);
        exit();
    }

    // ตรวจ OTP
        if (password_verify($otp_input, $request['otp_hash'])) {
        $verification_transaction_started = false;

        try {
            // ต้องใช้ตารางที่รองรับ Transaction เพื่อไม่บันทึกเพียงบางส่วน
            $engine_result = $conn->query(
                "SELECT TABLE_NAME, ENGINE
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('users', 'technicians', 'auth_requests')"
            );

            if (!$engine_result || $engine_result->num_rows !== 3) {
                throw new Exception('ไม่สามารถตรวจตารางสำหรับยืนยันอีเมลได้');
            }

            while ($engine_row = $engine_result->fetch_assoc()) {
                if (strtoupper((string) $engine_row['ENGINE']) !== 'INNODB') {
                    throw new Exception('ตารางสำหรับยืนยันอีเมลต้องรองรับ Transaction');
                }
            }

            $engine_result->free();

            if (!$conn->begin_transaction()) {
                throw new Exception('ไม่สามารถเริ่มบันทึกการยืนยันอีเมลได้');
            }

            $verification_transaction_started = true;

            $fresh_stmt = $conn->prepare(
                "SELECT u.role, u.is_active, u.auth_version,
                        u.technician_id, u.email AS account_email,
                        t.id AS found_technician_id, t.email AS tech_email
                 FROM users u
                 LEFT JOIN technicians t ON t.id = u.technician_id
                 WHERE u.id = ?
                 FOR UPDATE"
            );

            if (!$fresh_stmt) {
                throw new Exception('ไม่สามารถตรวจบัญชีล่าสุดได้');
            }

            $fresh_stmt->bind_param('i', $user_id);

            if (!$fresh_stmt->execute()) {
                throw new Exception('ไม่สามารถตรวจบัญชีล่าสุดได้');
            }

            $fresh_result = $fresh_stmt->get_result();
            $fresh_user = $fresh_result && $fresh_result->num_rows === 1
                ? $fresh_result->fetch_assoc()
                : null;

            $fresh_stmt->close();

            if (!$fresh_user
                || (int) $fresh_user['is_active'] !== 1
                || (string) $fresh_user['auth_version']
                    !== (string) $_SESSION['auth_version']
                || (string) $fresh_user['auth_version']
                    !== (string) $request['req_auth_version']) {
                throw new Exception('บัญชีหรือสิทธิ์เปลี่ยนไป กรุณาเข้าสู่ระบบใหม่');
            }

            $fresh_role = strtolower((string) $fresh_user['role']);
            $fresh_email = null;

            if ($fresh_role === 'technician') {
                if ($fresh_user['technician_id'] === null
                    || (int) $fresh_user['technician_id'] < 1
                    || (string) $fresh_user['technician_id']
                        !== (string) $fresh_user['found_technician_id']
                    || (string) $fresh_user['technician_id']
                        !== (string) $user['technician_id']) {
                    throw new Exception('ข้อมูลการเชื่อมช่างเปลี่ยนไป กรุณาขอรหัสใหม่');
                }

                $fresh_email = $fresh_user['tech_email'];

            } elseif (in_array($fresh_role, ['admin', 'executive'], true)
                && $fresh_user['technician_id'] === null) {
                $fresh_email = $fresh_user['account_email'];

            } else {
                throw new Exception('บัญชีนี้ไม่มีสิทธิ์ยืนยันอีเมล');
            }

            if ($fresh_role !== $role
                || !is_string($fresh_email)
                || !filter_var(trim($fresh_email), FILTER_VALIDATE_EMAIL)
                || trim($fresh_email) !== $request['target_email']) {
                throw new Exception('อีเมลหรือข้อมูลบัญชีเปลี่ยนไป กรุณาขอรหัสใหม่');
            }

            $request_id = (int) $request['id'];
            $request_version = (int) $request['req_auth_version'];
            $verified_target = $request['target_email'];

            // ใช้คำขอได้ครั้งเดียว และตรวจเงื่อนไขอีกครั้งในคำสั่งบันทึก
            $consume_stmt = $conn->prepare(
                "UPDATE auth_requests
                 SET verified_at = NOW(), used_at = NOW()
                 WHERE id = ?
                   AND request_token = ?
                   AND user_id = ?
                   AND purpose = 'email_verification'
                   AND auth_version = ?
                   AND BINARY target_email = BINARY ?
                   AND is_canceled = 0
                   AND used_at IS NULL
                   AND expires_at > NOW()
                   AND failed_attempts < 5"
            );

            if (!$consume_stmt) {
                throw new Exception('ไม่สามารถตรวจคำขอยืนยันได้');
            }

            $consume_stmt->bind_param(
                'isiis',
                $request_id,
                $token,
                $user_id,
                $request_version,
                $verified_target
            );

            if (!$consume_stmt->execute()
                || $consume_stmt->affected_rows !== 1) {
                throw new Exception('คำขอหมดอายุ ถูกยกเลิก หรือถูกใช้แล้ว กรุณาขอรหัสใหม่');
            }

            $consume_stmt->close();

            $stmt_update_user = $conn->prepare(
                "UPDATE users
                 SET verified_email = ?, email_verified_at = NOW()
                 WHERE id = ?
                   AND is_active = 1
                   AND auth_version = ?"
            );

            if (!$stmt_update_user) {
                throw new Exception('ไม่สามารถบันทึกการยืนยันอีเมลได้');
            }

            $stmt_update_user->bind_param(
                'sii',
                $verified_target,
                $user_id,
                $request_version
            );

            if (!$stmt_update_user->execute()) {
                throw new Exception('ไม่สามารถบันทึกการยืนยันอีเมลได้');
            }

            $stmt_update_user->close();

            if (!$conn->commit()) {
                throw new Exception('ไม่สามารถบันทึกการยืนยันอีเมลได้');
            }

            $verification_transaction_started = false;

            echo json_encode([
                'status' => 'success',
                'message' => 'ยืนยันอีเมลสำเร็จแล้ว'
            ]);

        } catch (Throwable $e) {
            if ($verification_transaction_started) {
                $conn->rollback();
            }

            echo json_encode([
                'status' => 'error',
                'message' => 'ยืนยันอีเมลไม่สำเร็จ คำขออาจหมดอายุหรือข้อมูลบัญชีเปลี่ยนไป กรุณารีเฟรชหน้าแล้วขอรหัสใหม่ หากยังเกิดปัญหาให้ติดต่อแอดมิน'
            ]);
        }
    } else {
        // อัปเดตจำนวนครั้งที่ผิดแบบ Atomic พร้อมดึงค่าล่าสุดมาตรวจสอบ
        $conn->query("UPDATE auth_requests SET failed_attempts = failed_attempts + 1 WHERE id = " . $request['id']);
        $res_fail = $conn->query("SELECT failed_attempts FROM auth_requests WHERE id = " . $request['id']);
        $current_fail = $res_fail->fetch_assoc()['failed_attempts'];
        
        if ($current_fail >= 5) {
            $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
            echo json_encode(['status' => 'error', 'message' => 'คุณกรอกรหัสผิดเกินจำนวนที่กำหนด คำขอนี้ถูกยกเลิกแล้ว']);
        } else {
            $remain = 5 - $current_fail;
            echo json_encode(['status' => 'error', 'message' => "รหัส OTP ไม่ถูกต้อง (เหลือโอกาส $remain ครั้ง)"]);
        }
    }
}
?>