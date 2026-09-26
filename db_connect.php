<?php
// อันนี้ดึงตัวแปรจากไฟล์ env.php
require_once 'env.php';

// เชื่อมต่อโดยใช้ตัวแปรจาก env.php 
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// ตั้งค่าให้รองรับภาษาไทยและ Emoji
$conn->set_charset("utf8mb4");

// อันนี้เช็คการเชื่อมต่อ
if ($conn->connect_error) {
  die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}
?>