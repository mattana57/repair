<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. รับค่าจากฟอร์ม
    $ticket_no = 'MR-' . date('Ymd-His');
    // ถ้าเลือก "อื่นๆ" ให้ใช้ค่าจากช่อง input แทน
    $equipment = ($_POST['equipment_type'] === 'อื่นๆ') ? $_POST['other_equipment'] : $_POST['equipment_type'];
    $location = ($_POST['location'] === 'อื่นๆ') ? $_POST['other_location'] : $_POST['location'];
    $phone = $_POST['phone_number'];
    $desc = $_POST['problem_desc'];

    // 2. บันทึกลงฐานข้อมูล
    $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, phone_number, problem_desc, status) VALUES (?, ?, ?, ?, ?, 'รอรับเรื่อง')");
    $stmt->bind_param("sssss", $ticket_no, $equipment, $location, $phone, $desc);
    
    if ($stmt->execute()) {
        // 3. ส่งข้อความเข้า LINE (Messaging API)
        $accessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU='; 
        $userId = 'Ub6fddb83458d09ae70b3a4c7ad430b28';

        $message = [
            'to' => $userId,
            'messages' => [
                [
                    'type' => 'text', 
                    'text' => "🔔 มีรายการแจ้งซ่อมใหม่!\nเลขที่: $ticket_no\nอุปกรณ์: $equipment\nสถานที่: $location\nเบอร์โทร: $phone\nอาการ: $desc"
                ]
            ]
        ];

        $ch = curl_init("https://api.line.me/v2/bot/message/push");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 4. แสดงผลลัพธ์ให้ผู้ใช้ทราบ
        if ($httpCode == 200) {
            echo "<script>alert('แจ้งซ่อมสำเร็จและส่งข้อมูลเข้า LINE แล้ว!'); window.location='report_form.html';</script>";
        } else {
            // หากเกิด Error จะแสดงให้เห็นแทนการหน้าจอขาว
            echo "แจ้งซ่อมสำเร็จในระบบ แต่ส่งเข้า LINE ไม่สำเร็จ (Error Code: $httpCode)";
            echo "<br><a href='report_form.html'>กลับหน้าหลัก</a>";
        }
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
    }
}
?>