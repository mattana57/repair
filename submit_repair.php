<?php
// 1. เรียกใช้ไฟล์การเชื่อมต่อฐานข้อมูล
include 'db_connect.php'; 

// 2. ตรวจสอบว่ามีการส่งข้อมูลมาจากฟอร์มหรือไม่ (ป้องกัน Error กรณีเข้าไฟล์ตรงๆ)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // รับค่าจากฟอร์ม
    $reporter_uid = $_POST['reporter_uid'] ?? null;
    $equipment_type = $_POST['equipment_type'];
    $location = $_POST['location'];
    $phone_number = $_POST['phone_number'];
    $problem_desc = $_POST['problem_desc'];

    // 3. สร้างเลขที่ใบงานอัตโนมัติ (Ticket No)
    $ticket_no = "MR-" . date("Ymd-His");

    // 4. ใช้ Prepared Statement เพื่อความปลอดภัย
    $sql = "INSERT INTO repairs (ticket_no, reporter_uid, equipment_type, location, phone_number, problem_desc, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'รอรับเรื่อง')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $ticket_no, $reporter_uid, $equipment_type, $location, $phone_number, $problem_desc);

    if ($stmt->execute()) {
        echo "แจ้งซ่อมสำเร็จ! เลขที่ใบงานของคุณคือ: " . $ticket_no;
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "กรุณาส่งข้อมูลผ่านฟอร์มเท่านั้น";
}

// 5. ปิดการเชื่อมต่อ
$conn->close();
?>