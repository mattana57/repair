<?php
// บันทึกรหัสผ่านหลังตรวจ OTP เท่านั้น ไม่ออกสิทธิ์ล็อกอิน
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit();
}
require_once __DIR__ . '/otp_verification_service.php';
require_once __DIR__ . '/password_policy.php';

function passwordResetSave($conn, $token, $password, $confirmation)
{
    $started = false;
    $invalid = 'สิทธิ์ตั้งรหัสผ่านใช้ไม่ได้หรือหมดอายุ กรุณาขอ OTP ใหม่';
    // บัญชีเป้าหมายมาจากสิทธิ์ใน Session เท่านั้น
    $grant = $_SESSION['password_reset_grant'] ?? null;
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)
        || !is_array($grant)
        || !is_string($grant['token_hash'] ?? null)
        || !hash_equals($grant['token_hash'], hash('sha256', $token))
        || ($grant['purpose'] ?? null) !== 'password_reset') {
        return otpVerificationResult($invalid);
    }
    $user_id = filter_var($grant['user_id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    $request_id = filter_var($grant['request_id'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    $grant_expiry = filter_var($grant['expires_at'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    if ($user_id === false || $request_id === false || $grant_expiry === false
        || !is_string($grant['auth_version'] ?? null)
        || !is_string($grant['role'] ?? null)
        || !array_key_exists('technician_id', $grant)
        || !is_string($grant['target_email'] ?? null)) {
        return otpVerificationResult($invalid);
    }
        $password_error = passwordPolicyError($password);
    if ($password_error !== null) {
        return otpVerificationResult($password_error);
    }
    if (!is_string($confirmation)) {
        return otpVerificationResult('กรุณากรอกการยืนยันรหัสผ่านเป็นข้อความ');
    }

    if ($password !== $confirmation) {
        return otpVerificationResult('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
    }
    try {
        $config = otpRequestConfig();
        $limit = $config['otp_max_failed_attempts'] ?? null;
        if (!is_int($limit) || $limit < 1 || $limit > 2147483647) {
            throw new RuntimeException('Invalid reset configuration');
        }
        otpRequestCheckEngines($conn);
        // ทำ hash ก่อนล็อก เพื่อลดเวลาที่บัญชีต้องรอ
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hashed)) {
            throw new RuntimeException('Password hashing failed');
        }
        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Password reset transaction failed');
        }
        $started = true;
        // ลำดับล็อกเดียวกับการขอและตรวจ OTP
        $user = otpRequestRow(otpRequestStatement($conn,
            'SELECT id, role, is_active, technician_id, email,
                    verified_email, email_verified_at, auth_version
             FROM users WHERE id = ? FOR UPDATE', 'i', [$user_id]));
        $technician = null;
        if ($user && strtolower((string) $user['role']) === 'technician'
            && (int) $user['technician_id'] > 0) {
            $technician = otpRequestRow(otpRequestStatement($conn,
                'SELECT id, email FROM technicians WHERE id = ? FOR UPDATE',
                'i', [(int) $user['technician_id']]));
        }
        $request = otpRequestRow(otpRequestStatement($conn,
            "SELECT id, user_id, target_email, auth_version, verified_at,
                    used_at, is_canceled, failed_attempts,
                    (expires_at > NOW()) AS is_live,
                    UNIX_TIMESTAMP(NOW()) AS database_now,
                    UNIX_TIMESTAMP(expires_at) AS expiry_epoch
             FROM auth_requests
             WHERE id = ? AND user_id = ? AND purpose = 'password_reset'
             FOR UPDATE", 'ii', [$request_id, $user_id]));
        $email = $user ? otpVerificationEmail($user, $technician) : null;
        $eligible = $user && $request
            && (int) $user['is_active'] === 1
            && (string) $user['auth_version'] === $grant['auth_version']
            && (string) $request['auth_version'] === $grant['auth_version']
            && (string) $user['role'] === $grant['role']
            && $user['technician_id'] === $grant['technician_id']
            && $email !== null && $email === $grant['target_email']
            && $email === $request['target_email']
            && is_string($user['verified_email'])
            && $email === $user['verified_email']
            && !empty($user['email_verified_at'])
            && $request['verified_at'] !== null
            && $request['used_at'] === null
            && (int) $request['is_canceled'] === 0
            && (int) $request['failed_attempts'] < $limit
            && (int) $request['is_live'] === 1
            && $grant_expiry > (int) $request['database_now']
            && $grant_expiry <= (int) $request['expiry_epoch'];
        if (!$eligible) {
            if ($request && $request['used_at'] === null) {
                $stmt = otpRequestStatement($conn,
                    'UPDATE auth_requests SET is_canceled = 1 WHERE id = ? AND used_at IS NULL',
                    'i', [$request_id]);
                $stmt->close();
            }
            otpVerificationCommit($conn, $started);
            unset($_SESSION['password_reset_grant']);
            return otpVerificationResult($invalid);
        }
        // รองรับชนิด INT เดิม โดยไม่เปลี่ยนโครงสร้างฐานข้อมูล
        $version = filter_var($user['auth_version'], FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483646]]);
        if ($version === false) {
            throw new RuntimeException('Invalid account version');
        }
        // เงื่อนไขวันหมดอายุถูกตรวจอีกครั้งในคำสั่งใช้สิทธิ์
        $stmt = otpRequestStatement($conn,
            "UPDATE auth_requests SET used_at = NOW()
             WHERE id = ? AND user_id = ? AND purpose = 'password_reset'
               AND verified_at IS NOT NULL AND used_at IS NULL
               AND is_canceled = 0 AND expires_at > NOW()
               AND UNIX_TIMESTAMP(NOW()) < ?
               AND failed_attempts < ? AND auth_version = ?",
            'iiiii', [$request_id, $user_id, $grant_expiry, $limit, $version]);
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Password reset grant consume failed');
        }
        $stmt->close();
        // เพิ่มรุ่นสิทธิ์เพื่อให้ Session รุ่นเดิมถูกปฏิเสธเมื่อมีการตรวจ
        // รหัสส่วนตัวใหม่ไม่ต้องผ่านขั้นตอนรหัสผ่านชั่วคราวอีก
        $stmt = otpRequestStatement($conn,
            'UPDATE users SET password = ?, auth_version = auth_version + 1,
                    must_change_password = 0
             WHERE id = ? AND is_active = 1 AND auth_version = ?',
            'sii', [$hashed, $user_id, $version]);
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Password reset account update failed');
        }
        $stmt->close();
        // ยกเลิกคำขอรีเซ็ตอื่นของบัญชีเดียวกัน
        $stmt = otpRequestStatement($conn,
            "UPDATE auth_requests SET is_canceled = 1
             WHERE user_id = ? AND purpose = 'password_reset'
               AND id <> ? AND is_canceled = 0",
            'ii', [$user_id, $request_id]);
        $stmt->close();
        otpVerificationCommit($conn, $started);
        // ล้างสิทธิ์และ Session ของเบราว์เซอร์นี้ ไม่ล็อกอินอัตโนมัติ
        // Session ของอุปกรณ์อื่นถูกปฏิเสธด้วย auth_version ในหน้าที่ตรวจรุ่นสิทธิ์
        $_SESSION = [];
        return ['status' => 'success',
            'message' => 'ตั้งรหัสผ่านใหม่สำเร็จ กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่',
            'redirect' => 'login.php'];
    } catch (Throwable $e) {
        if ($started) {
            try { $conn->rollback(); } catch (Throwable $rollback_error) {}
        }
        error_log('Password reset failed');
        return otpVerificationResult('ไม่สามารถบันทึกรหัสผ่านได้ กรุณาลองใหม่ภายหลัง');
    }
}
