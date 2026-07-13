<?php
$servername = "localhost";
$username = "root";
$password = "Project_02"; 
$dbname = "repair_db";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตั้งค่าให้รองรับภาษาไทย
$conn->set_charset("utf8mb4");

// เช็คการเชื่อมต่อ
if ($conn->connect_error) {
  die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}
?>