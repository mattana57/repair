<?php
session_start();
require_once 'db_connect.php'; // ตรวจสอบว่าไฟล์นี้มีอยู่แล้วในโฟลเดอร์นะ

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม login
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ตรวจสอบข้อมูลในฐานข้อมูล
    $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // 1. เก็บ Session ที่จำเป็น (สำคัญมาก ห้ามลบ user_id)
        $_SESSION['user_id'] = $user['id']; 
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role']; 
        
        // แปลง Role เป็นตัวพิมพ์เล็กทั้งหมดเพื่อป้องกันปัญหาตัวพิมพ์เล็ก-ใหญ่
        $role_lower = strtolower($user['role']);
        
        // 2. แยกหน้าตามสิทธิ์
        if ($role_lower === 'admin' || $role_lower === 'technician') {
            header("Location: dashboard.php");
            exit();
        } elseif ($role_lower === 'executive') {
            // เปลี่ยนชื่อไฟล์ให้ตรงกับที่เราสร้างไว้
            header("Location: executive_dashboard.php");
            exit();
        } else {
            // เผื่อสิทธิ์อื่นๆ (ถ้ามี) ให้วิ่งไปหน้า dashboard ปกติ
            header("Location: dashboard.php");
            exit();
        }
    } else {
        // ถ้ารหัสผิด ให้มี Alert แจ้งเตือน และเด้งกลับหน้าเดิม
        echo "<script>
                alert('Username หรือ Password ไม่ถูกต้อง!');
                window.location.href = 'login.php';
              </script>";
    }
}
?>