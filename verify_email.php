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
    try {
        require_once __DIR__ . '/otp_verification_service.php';
        $result = otpVerificationCheck(
            $conn, 'email_verification',
            $_POST['token'] ?? null, $_POST['otp'] ?? null,
            $user_id, $_SESSION['auth_version'] ?? null,
            $_SESSION['role'] ?? null, $_SESSION['technician_id'] ?? null
        );
    } catch (Throwable $e) {
        error_log('Email OTP verification failed');
        $result = ['status' => 'error', 'message' => 'ไม่สามารถตรวจ OTP ได้ กรุณาลองใหม่ภายหลัง'];
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}
