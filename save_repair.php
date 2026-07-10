<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ticket_no = 'MR-' . date('Ymd-His');
    $equipment = ($_POST['equipment_type'] === 'อื่นๆ') ? $_POST['other_equipment'] : $_POST['equipment_type'];
    $location = ($_POST['location'] === 'อื่นๆ') ? $_POST['other_location'] : $_POST['location'];
    $phone = $_POST['phone_number'];
    $desc = $_POST['problem_desc'];

    $sql = "INSERT INTO repairs (ticket_no, equipment_type, location, phone_number, problem_desc, status) 
            VALUES (?, ?, ?, ?, ?, 'รอรับเรื่อง')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $ticket_no, $equipment, $location, $phone, $desc);
    
    if ($stmt->execute()) {
        // ใช้ Messaging API แบบ Broadcast (ส่งหาเพื่อนทุกคนที่เป็นเพื่อนกับบอท)
        $accessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU='; 

        $message = [
            'messages' => [['type' => 'text', 'text' => "🔔 แจ้งซ่อมใหม่!\nเลขที่: $ticket_no\nอุปกรณ์: $equipment\nสถานที่: $location\nเบอร์โทร: $phone\nอาการ: $desc"]]
        ];

        $ch = curl_init("https://api.line.me/v2/bot/message/broadcast");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // ถ้า HTTP Code เป็น 200 คือส่งสำเร็จ ถ้าไม่ใช่จะแสดง Error ให้คุณน้ำฝนเห็นครับ
        if ($httpCode == 200) {
            echo "<script>alert('แจ้งซ่อมสำเร็จ!'); window.location='report_form.html';</script>";
        } else {
            echo "แจ้งซ่อมสำเร็จใน DB แต่ส่ง LINE ไม่ได้! (Error Code: $httpCode) - รายละเอียด: " . $result;
        }
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
    }
}
?>