<?php
// ดึงไฟล์เชื่อมต่อฐานข้อมูลมาใช้
require_once 'db_connect.php';

// เช็คว่ามีการกดปุ่ม Submit ฟอร์มมาจริงๆ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // รับค่าจากฟอร์ม
    $equipment_type = $_POST['equipment_type'];
    $location = $_POST['location'];
    $problem_desc = $_POST['problem_desc'];
    
    // จำลอง LINE UID ของผู้แจ้งไปก่อน (เดี๋ยวเราค่อยเอาของจริงจาก LIFF มาใส่ทีหลัง)
    $reporter_uid = "U_DUMMY_12345"; 
    
    // สร้างเลขที่ใบงานอัตโนมัติ (เช่น MR-2026-0001)
    // สำหรับระบบจริงอาจจะต้อง query หาเลขล่าสุดบวก 1 แต่อันนี้เราสุ่มเลขไปก่อนครับ
    $ticket_no = "MR-" . date("Y") . "-" . sprintf("%04d", rand(1, 9999));

    // คำสั่ง SQL สำหรับเพิ่มข้อมูล
    $sql = "INSERT INTO repairs (ticket_no, reporter_uid, equipment_type, location, problem_desc, status) 
            VALUES (?, ?, ?, ?, ?, 'รอรับเรื่อง')";
    
    // ใช้ Prepared Statement เพื่อความปลอดภัย (ป้องกัน SQL Injection)
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $ticket_no, $reporter_uid, $equipment_type, $location, $problem_desc);

    if ($stmt->execute()) {
        // ถ้าบันทึกสำเร็จ ให้เด้งแจ้งเตือนและกลับไปหน้าฟอร์ม
        echo "<script>
                alert('แจ้งซ่อมสำเร็จ! เลขที่ใบงานของคุณคือ: " . $ticket_no . "');
                window.location.href = 'report_form.html';
              </script>";
    } else {
        echo "เกิดข้อผิดพลาด: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>