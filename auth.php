<?php
session_start();
require_once 'db_connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $_SESSION['user_id'] = $user['id']; 
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role']; 
        
        $role_lower = strtolower($user['role']);
        
        // ==========================================
        // ตรวจสอบว่าระบบมีการฝากจำ URL ไว้ก่อนล็อกอินหรือไม่
        // ==========================================
        if (isset($_SESSION['redirect_url']) && !empty($_SESSION['redirect_url'])) {
            $redirect = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']); // ล้างค่าทิ้งเพื่อไม่ให้จำซ้ำค้างไว้
            
            // พุ่งตรงไปยัง URL ที่จำไว้ (เช่น เด้งไปหน้ารายการซ่อม)
            header("Location: " . $redirect);
            exit();
        }

        // ==========================================
        // กรณีเข้าผ่านหน้าเว็บตรงๆ (ไม่ได้คลิกผ่าน LINE)
        // ==========================================
        if ($role_lower === 'technician') {
            header("Location: dashboard.php?tab=repairs");
            exit();
        } elseif ($role_lower === 'admin') {
            header("Location: dashboard.php");
            exit();
        } elseif ($role_lower === 'executive') {
            header("Location: executive_dashboard.php");
            exit();
        } else {
            header("Location: dashboard.php");
            exit();
        }
    } else {
        echo "<script>
                alert('Username หรือ Password ไม่ถูกต้อง!');
                window.location.href = 'login.php';
              </script>";
    }
}
?>