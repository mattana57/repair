<?php
// ตรวจ OTP เท่านั้น ไม่ตั้งรหัสผ่านและไม่ออกสิทธิ์ล็อกอิน
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit();
}
require_once __DIR__ . '/otp_request_service.php';

function otpVerificationResult($message, $status = 'error')
{
    return ['status' => $status, 'message' => $message];
}

function otpVerificationEmail($user, $technician)
{
    $role = strtolower((string) $user['role']);
    if ($role === 'technician') {
        if (!$technician || (int) $user['technician_id'] < 1
            || (string) $user['technician_id'] !== (string) $technician['id']) {
            return null;
        }
        $email = $technician['email'];
    } elseif (in_array($role, ['admin', 'executive'], true)
        && $user['technician_id'] === null) {
        $email = $user['email'];
    } else {
        return null;
    }
    return is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL)
        ? trim($email) : null;
}

function otpVerificationCommit($conn, &$started)
{
    if (!$conn->commit()) {
        throw new RuntimeException('OTP verification commit failed');
    }
    $started = false;
}

function otpVerificationCheck(
    $conn, $purpose, $token, $otp,
    $session_user_id = null, $session_version = null,
    $session_role = null, $session_technician_id = null
) {
    $started = false;
    $generic = 'คำขอใช้ไม่ได้ หมดอายุ หรือข้อมูลบัญชีเปลี่ยนไป กรุณาขอรหัสใหม่';
    if (!in_array($purpose, ['password_reset', 'email_verification'], true)
        || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)
        || !is_string($otp) || !preg_match('/\A[0-9]{6}\z/', $otp)) {
        return otpVerificationResult('กรุณาใช้คำขอล่าสุดและกรอก OTP เป็นตัวเลข 6 หลัก');
    }

    try {
        $config = otpRequestConfig();
        foreach (['otp_max_failed_attempts', 'reset_grant_lifetime_seconds'] as $key) {
            if (!isset($config[$key]) || !is_int($config[$key])
                || $config[$key] < 1 || $config[$key] > 2147483647) {
                throw new RuntimeException('Invalid OTP verification configuration');
            }
        }
        $limit = $config['otp_max_failed_attempts'];
        otpRequestCheckEngines($conn);

        // อ่านเพื่อหารหัสบัญชีเท่านั้น ต้องอ่านคำขอล่าสุดซ้ำภายใต้ล็อก
        $lookup = otpRequestRow(otpRequestStatement(
            $conn,
            'SELECT user_id FROM auth_requests WHERE request_token = ? AND purpose = ?',
            'ss', [$token, $purpose]
        ));
        if (!$lookup) {
            return otpVerificationResult($generic);
        }
        $user_id = (int) $lookup['user_id'];
        $context = $_SESSION['otp_request_context'][$purpose] ?? null;
        if (!is_array($context)
            || !is_string($context['token_hash'] ?? null)
            || !hash_equals($context['token_hash'], hash('sha256', $token))
            || (string) ($context['user_id'] ?? '') !== (string) $user_id) {
            return otpVerificationResult($generic);
        }
        if ($purpose === 'email_verification'
            && ((string) $session_user_id !== (string) $user_id
                || $session_version === null || !is_string($session_role))) {
            return otpVerificationResult($generic);
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('OTP verification transaction failed');
        }
        $started = true;

        // ใช้ลำดับล็อกบัญชี -> ช่าง -> คำขอ เช่นเดียวกับการขอ OTP
        $user = otpRequestRow(otpRequestStatement(
            $conn,
            'SELECT id, role, is_active, technician_id, email,
                    verified_email, email_verified_at, auth_version
             FROM users WHERE id = ? FOR UPDATE',
            'i', [$user_id]
        ));
        $technician = null;
        if ($user && strtolower((string) $user['role']) === 'technician'
            && (int) $user['technician_id'] > 0) {
            $technician = otpRequestRow(otpRequestStatement(
                $conn, 'SELECT id, email FROM technicians WHERE id = ? FOR UPDATE',
                'i', [(int) $user['technician_id']]
            ));
        }
        $request = otpRequestRow(otpRequestStatement(
            $conn,
            'SELECT id, user_id, purpose, target_email, otp_hash, failed_attempts,
                    auth_version, verified_at, used_at, is_canceled,
                    (expires_at > NOW()) AS is_live,
                    UNIX_TIMESTAMP(NOW()) AS database_now,
                    UNIX_TIMESTAMP(expires_at) AS expiry_epoch
             FROM auth_requests
             WHERE request_token = ? AND purpose = ? AND user_id = ? FOR UPDATE',
            'ssi', [$token, $purpose, $user_id]
        ));
        if (!$request || (int) $request['is_canceled'] !== 0
            || $request['used_at'] !== null || $request['verified_at'] !== null) {
            otpVerificationCommit($conn, $started);
            return otpVerificationResult($generic);
        }

        $email = $user ? otpVerificationEmail($user, $technician) : null;
        $eligible = $user && (int) $user['is_active'] === 1
            && (string) $user['auth_version'] === (string) $request['auth_version']
            && (string) $request['auth_version'] === (string) $context['auth_version']
            && (string) $user['role'] === (string) $context['role']
            && $user['technician_id'] === $context['technician_id']
            && (string) $request['id'] === (string) $context['request_id']
            && $email !== null && $email === $request['target_email']
            && $email === $context['target_email']
            && (int) $request['is_live'] === 1
            && (int) $request['failed_attempts'] < $limit;
        if ($eligible && $purpose === 'password_reset') {
            $eligible = !empty($user['email_verified_at'])
                && is_string($user['verified_email'])
                && $user['verified_email'] === $email;
        }
        if ($eligible && $purpose === 'email_verification') {
            $eligible = (string) $session_version === (string) $user['auth_version']
                && strtolower($session_role) === strtolower((string) $user['role'])
                && (strtolower((string) $user['role']) !== 'technician'
                    || (string) $session_technician_id === (string) $user['technician_id']);
        }
        $request_id = (int) $request['id'];
        if (!$eligible) {
            $stmt = otpRequestStatement($conn,
                'UPDATE auth_requests SET is_canceled = 1 WHERE id = ? AND used_at IS NULL',
                'i', [$request_id]);
            $stmt->close();
            otpVerificationCommit($conn, $started);
            return otpVerificationResult($generic);
        }

        if (!password_verify($otp, $request['otp_hash'])) {
            // อ่านภายใต้ล็อก: เพิ่มตัวนับและยกเลิกในคำสั่งเดียว
            $failures = (int) $request['failed_attempts'] + 1;
            $cancel = $failures >= $limit ? 1 : 0;
            $stmt = otpRequestStatement($conn,
                'UPDATE auth_requests SET failed_attempts = ?, is_canceled = ?
                 WHERE id = ? AND used_at IS NULL AND verified_at IS NULL AND is_canceled = 0',
                'iii', [$failures, $cancel, $request_id]);
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('OTP failure update failed');
            }
            $stmt->close();
            otpVerificationCommit($conn, $started);
            return otpVerificationResult($cancel
                ? 'กรอก OTP ผิดครบจำนวนที่กำหนด คำขอนี้ถูกยกเลิก กรุณาขอใหม่'
                : 'OTP ไม่ถูกต้อง เหลือโอกาส ' . ($limit - $failures) . ' ครั้ง');
        }

        $grant = null;
        if ($purpose === 'password_reset') {
            $grant_token = bin2hex(random_bytes(32));
            // สิทธิ์ไม่ยืดอายุคำขอเดิม และหมดอายุไม่เกินเวลาที่กำหนด
            $grant = [
                'token_hash' => hash('sha256', $grant_token),
                'request_id' => $request_id,
                'user_id' => $user_id,
                'purpose' => 'password_reset',
                'auth_version' => (string) $user['auth_version'],
                'role' => (string) $user['role'],
                'technician_id' => $user['technician_id'],
                'target_email' => $email,
                'expires_at' => min((int) $request['expiry_epoch'],
                    (int) $request['database_now'] + $config['reset_grant_lifetime_seconds']),
            ];
        }
        $stmt = otpRequestStatement($conn,
            "UPDATE auth_requests SET verified_at = NOW(),
                    used_at = IF(purpose = 'email_verification', NOW(), NULL)
             WHERE id = ? AND verified_at IS NULL AND used_at IS NULL
               AND is_canceled = 0 AND expires_at > NOW() AND failed_attempts < ?",
            'ii', [$request_id, $limit]);
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('OTP consume failed');
        }
        $stmt->close();
        if ($purpose === 'email_verification') {
            $stmt = otpRequestStatement($conn,
                'UPDATE users SET verified_email = ?, email_verified_at = NOW()
                 WHERE id = ? AND is_active = 1 AND auth_version = ?',
                'sii', [$email, $user_id, (int) $user['auth_version']]);
            $stmt->close();
        }
        otpVerificationCommit($conn, $started);

        if ($grant !== null) {
            // เก็บเฉพาะสิทธิ์รีเซ็ต ไม่เขียนคีย์สิทธิ์ล็อกอินใด ๆ
            $_SESSION['password_reset_grant'] = $grant;
            return [
                'status' => 'success', 'message' => 'ตรวจ OTP สำเร็จแล้ว',
                'reset_grant' => $grant_token,
                'expires_in' => $grant['expires_at'] - (int) $request['database_now'],
            ];
        }
        return otpVerificationResult('ยืนยันอีเมลสำเร็จแล้ว', 'success');
    } catch (Throwable $e) {
        if ($started) {
            try { $conn->rollback(); } catch (Throwable $rollback_error) {}
        }
        // ไม่บันทึก OTP, โทเคน, อีเมล หรือข้อความฐานข้อมูล
        error_log('OTP verification failed');
        return otpVerificationResult('ไม่สามารถตรวจ OTP ได้ กรุณาลองใหม่ภายหลัง');
    }
}
