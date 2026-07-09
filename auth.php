<?php
session_start();
require_once 'db_connect.php'; // ตรวจสอบว่าไฟล์นี้มีอยู่แล้วในโฟลเดอร์นะ

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
    $_SESSION['role'] = $user['role']; // เก็บสิทธิ์การใช้งานไว้ใน Session
    
    // แยกหน้าตามสิทธิ์
    if ($user['role'] == 'admin') {
        header("Location: dashboard.php");
    } elseif ($user['role'] == 'executive') {
        header("Location: executive.php");
    }
} else {
    echo "Username หรือ Password ไม่ถูกต้อง <br> <a href='login.php'>กลับไปหน้า Login</a>";
}
?>