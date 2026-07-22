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
        
        // แยกหน้าตามสิทธิ์
        if ($role_lower === 'technician') {
            // ถ้าเป็นช่าง ให้เด้งไปหน้า "รายการแจ้งซ่อม" เลย
            header("Location: dashboard.php?tab=repairs");
            exit();
        } elseif ($role_lower === 'admin') {
            // ถ้าเป็น Admin เข้าหน้าภาพรวมปกติ
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