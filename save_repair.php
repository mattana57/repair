<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าและเช็ค "อื่นๆ"
    $ticket_no = 'MR-' . date('Ymd-His');
    $equipment = ($_POST['equipment_type'] === 'อื่นๆ') ? $_POST['other_equipment'] : $_POST['equipment_type'];
    $location = ($_POST['location'] === 'อื่นๆ') ? $_POST['other_location'] : $_POST['location'];
    $phone = $_POST['phone_number'];
    $desc = $_POST['problem_desc'];

    // ใช้คำสั่ง SQL ที่บันทึกเฉพาะคอลัมน์ที่จำเป็น (ไม่ต้องยุ่งกับ reporter_uid)
    $sql = "INSERT INTO repairs (ticket_no, equipment_type, location, phone_number, problem_desc, status) 
            VALUES (?, ?, ?, ?, ?, 'รอรับเรื่อง')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $ticket_no, $equipment, $location, $phone, $desc);
    
    if ($stmt->execute()) {
        // ส่ง LINE
        $accessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU='; 
        $userId = 'Ub6fddb83458d09ae70b3a4c7ad430b28';

        $message = [
            'to' => $userId,
            'messages' => [['type' => 'text', 'text' => "🔔 แจ้งซ่อมใหม่!\nเลขที่: $ticket_no\nอุปกรณ์: $equipment\nสถานที่: $location\nอาการ: $desc"]]
        ];

        $ch = curl_init("https://api.line.me/v2/bot/message/push");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        echo "<script>alert('แจ้งซ่อมสำเร็จ!'); window.location='report_form.html';</script>";
    } else {
        echo "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>