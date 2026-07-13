<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reporter_name = $_POST['reporter_name'];
    $equipment = ($_POST['equipment_type'] == 'other') ? $_POST['other_equip'] : $_POST['equipment_type'];
    $building = $_POST['building'];
    $room_no = $_POST['room_no'];
    $phone_number = $_POST['phone_number'];
    $problem_desc = $_POST['problem_desc'];
    $ticket_no = "MR-" . date("Ymd-His");

    // จัดการอัปโหลดรูปภาพ
    $target_dir = "uploads/"; // อย่าลืมสร้างโฟลเดอร์ชื่อ uploads ไว้ใน Server
    $image_name = null;
    if (!empty($_FILES["image_before"]["name"])) {
        $image_name = $ticket_no . "_" . basename($_FILES["image_before"]["name"]);
        move_uploaded_file($_FILES["image_before"]["tmp_name"], $target_dir . $image_name);
    }

    // บันทึกลงฐานข้อมูล
    $sql = "INSERT INTO repairs (ticket_no, reporter_name, equipment_type, building, room_no, phone_number, problem_desc, image_before, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'รอรับเรื่อง')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $ticket_no, $reporter_name, $equipment, $building, $room_no, $phone_number, $problem_desc, $image_name);

    if ($stmt->execute()) {
        echo "<script>alert('แจ้งซ่อมสำเร็จ! เลขที่ใบงาน: $ticket_no'); window.location='index.php';</script>";
    } else {
        echo "เกิดข้อผิดพลาด: " . $stmt->error;
    }
}
?>