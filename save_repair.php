<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // รับค่า หมวดหมู่ (เช็คว่าถ้าเลือก "อื่นๆ" ให้ดึงค่าจากช่องพิมพ์เองมาใช้)
    $equipment_type = $_POST['equipment_type'];
    if ($equipment_type === 'อื่นๆ' && !empty($_POST['other_equipment'])) {
        $equipment_type = $_POST['other_equipment'];
    }

    // รับค่า สถานที่ (เช็คว่าถ้าเลือกให้ระบุเอง ให้ดึงค่าจากช่องพิมพ์เองมาใช้)
    $location = $_POST['location'];
    if (in_array($location, ['ห้องพักอาจารย์', 'สำนักงานคณะ', 'อื่นๆ']) && !empty($_POST['other_location'])) {
        $location = $_POST['other_location'];
    }

    $phone_number = $_POST['phone_number'];
    $problem_desc = $_POST['problem_desc'];
    
    // รับค่า UID จาก LIFF (ถ้ายังไม่ได้ต่อ LIFF ค่าจะเป็น GUEST_...)
    $reporter_uid = isset($_POST['reporter_uid']) && !empty($_POST['reporter_uid']) ? $_POST['reporter_uid'] : "GUEST_" . rand(1000, 9999); 
    
    // สร้างเลขที่ใบงาน (เช่น MR-2026-0001)
    $ticket_no = "MR-" . date("Y") . "-" . sprintf("%04d", rand(1, 9999));

    // SQL สำหรับเพิ่มข้อมูล
    $sql = "INSERT INTO repairs (ticket_no, reporter_uid, equipment_type, location, phone_number, problem_desc, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'รอรับเรื่อง')";
    
    $stmt = $conn->prepare($sql);
    // ssssss คือบอกว่ามีข้อมูลเป็น String 6 ตัว
    $stmt->bind_param("ssssss", $ticket_no, $reporter_uid, $equipment_type, $location, $phone_number, $problem_desc);

    if ($stmt->execute()) {
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