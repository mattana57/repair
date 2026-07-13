<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reporter_name = $_POST['reporter_name'];
    $equipment = ($_POST['equipment_type'] == 'other') ? $_POST['other_equip'] : $_POST['equipment_type'];
    
    // รวมตึกและห้องเข้าด้วยกันเพื่อให้ลงฟิลด์ 'location' ในฐานข้อมูล
    $location = $_POST['building'] . " ห้อง " . $_POST['room_no'];
    
    $phone_number = $_POST['phone_number'];
    $problem_desc = $_POST['problem_desc'];
    $ticket_no = "MR-" . date("Ymd-His");

    // จัดการอัปโหลดรูปภาพ
    $target_dir = "uploads/";
    $image_name = null;
    if (!empty($_FILES["image_before"]["name"])) {
        $image_name = $ticket_no . "_" . basename($_FILES["image_before"]["name"]);
        move_uploaded_file($_FILES["image_before"]["tmp_name"], $target_dir . $image_name);
    }

    // บันทึกลงฐานข้อมูล
    $sql = "INSERT INTO repairs (ticket_no, reporter_name, equipment_type, location, phone_number, problem_desc, image_before, status, building, room_no) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'รอรับเรื่อง', ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $ticket_no, $reporter_name, $equipment, $location, $phone_number, $problem_desc, $image_name, $_POST['building'], $_POST['room_no']);

    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

    if ($stmt->execute()) {
        // ป๊อบอัพสวยๆ เมื่อสำเร็จ
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'แจ้งซ่อมสำเร็จ!',
                text: 'เลขที่ใบงานของคุณคือ: $ticket_no',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#0284c7',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                window.location='index.php';
            });
        </script>";
    } else {
        // ป๊อบอัพเมื่อเกิดข้อผิดพลาด
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถบันทึกข้อมูลได้: " . addslashes($stmt->error) . "',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#ef4444'
            });
        </script>";
    }
    
    $stmt->close();
}
?>