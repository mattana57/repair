<?php
// เริ่มต้น Session เพื่อให้สามารถทำลายได้
session_start();

// ล้างตัวแปร Session ทั้งหมด
$_SESSION = array();

// ทำลาย Cookie ของ Session ในเบราว์เซอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย Session ฝั่งเซิร์ฟเวอร์
session_destroy();

// ส่งกลับหน้าเข้าสู่ระบบ
header("Location: login.php");
exit();
?>