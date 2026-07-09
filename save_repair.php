<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. เก็บข้อมูลลง Database
    $ticket_no = 'MR-' . date('Ymd-His');
    $equipment = $_POST['equipment_type'];
    $location = $_POST['location'];
    $phone = $_POST['phone_number'];
    $desc = $_POST['problem_desc'];

    $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, phone_number, problem_desc, status) VALUES (?, ?, ?, ?, ?, 'รอรับเรื่อง')");
    $stmt->bind_param("sssss", $ticket_no, $equipment, $location, $phone, $desc);

    if ($stmt->execute()) {
        // 2. ส่งข้อความเข้า LINE Notify หลังจากบันทึก DB สำเร็จ
        $token = "ใส่_LINE_NOTIFY_TOKEN_ของคุณที่นี่"; // <--- ใส่ Token ตรงนี้ครับ
        $message = "\n🔔 มีรายการแจ้งซ่อมใหม่!\n" .
                   "เลขที่: $ticket_no\n" .
                   "อุปกรณ์: $equipment\n" .
                   "สถานที่: $location\n" .
                   "เบอร์โทร: $phone\n" .
                   "อาการ: $desc";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . $message);
        $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $token . '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);

        echo "<script>alert('แจ้งซ่อมสำเร็จและส่ง LINE แล้ว!'); window.location='report_form.html';</script>";
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึก: " . $conn->error;
    }
}
?>