<?php
// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("localhost", "username", "password", "mbs_repair_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// รับค่าจากฟอร์ม (ตัวอย่าง)
$reporter_uid = $_POST['reporter_uid']; // ได้มาจาก LIFF
$equipment_type = $_POST['equipment_type'];
$location = $_POST['location'];
$phone_number = $_POST['phone_number'];
$problem_desc = $_POST['problem_desc'];

// สร้างเลขที่ใบงานอัตโนมัติ (Ticket No)
$ticket_no = "MR-" . date("Ymd-His");

// คำสั่ง SQL สำหรับบันทึกข้อมูล
$sql = "INSERT INTO repairs (ticket_no, reporter_uid, equipment_type, location, phone_number, problem_desc, status) 
        VALUES ('$ticket_no', '$reporter_uid', '$equipment_type', '$location', '$phone_number', '$problem_desc', 'รอรับเรื่อง')";

if ($conn->query($sql) === TRUE) {
    echo "แจ้งซ่อมสำเร็จ! เลขที่ใบงานของคุณคือ: " . $ticket_no;
} else {
    echo "เกิดข้อผิดพลาด: " . $conn->error;
}

$conn->close();
?>