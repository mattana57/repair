<?php
require_once __DIR__ . '/auth_guard.php';

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('อนุญาตเฉพาะแอดมิน');
}

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    $saved_token = $_SESSION['session_expiry_test_csrf'] ?? null;

    if (!is_string($token) || !is_string($saved_token)
        || !hash_equals($saved_token, $token)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณาเปิดหน้าทดสอบใหม่');
    }

    unset($_SESSION['session_expiry_test_csrf']);
    $_SESSION['LAST_ACTIVITY'] = time() - MBS_SESSION_IDLE_TIMEOUT - 1;

    if (!session_write_close()) {
        http_response_code(503);
        exit('บันทึก Session ทดสอบไม่สำเร็จ');
    }

    header('Location: dashboard.php', true, 303);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('คำขอไม่ถูกต้อง');
}

$_SESSION['session_expiry_test_csrf'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ทดสอบ Session หมดอายุ</title>
</head>
<body>
    <h1>ทดสอบ Session หมดอายุ</h1>
    <p>ปุ่มนี้จำลองการไม่ใช้งานเกิน 8 ชั่วโมง เฉพาะ Session ในเบราว์เซอร์นี้</p>
    <p>หลังทดสอบ ระบบควรกลับหน้า Login และคุณต้องล็อกอินใหม่</p>

    <form method="POST" action="session_expiry_test.php">
        <input type="hidden" name="csrf_token" value="<?php
            echo htmlspecialchars(
                $_SESSION['session_expiry_test_csrf'],
                ENT_QUOTES,
                'UTF-8'
            );
        ?>">
        <button type="submit">ทดสอบ Session ของฉันหมดอายุ</button>
    </form>
</body>
</html>