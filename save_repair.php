<?php
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. รับค่าจากฟอร์ม (รวมถึงชื่อผู้แจ้ง)
    $reporter = $_POST['reporter_name']; // รับชื่อที่กรอกจากฟอร์ม
    $ticket_no = 'MR-' . date('Ymd-His');
    $equipment = ($_POST['equipment_type'] === 'อื่นๆ') ? $_POST['other_equipment'] : $_POST['equipment_type'];
    $location = ($_POST['location'] === 'อื่นๆ') ? $_POST['other_location'] : $_POST['location'];
    $phone = $_POST['phone_number'];
    $desc = $_POST['problem_desc'];

    // 2. บันทึกลงฐานข้อมูล (เพิ่มคอลัมน์ชื่อผู้แจ้งด้วย ถ้าตารางมีคอลัมน์ชื่อ reporter_name)
    // หมายเหตุ: หากใน DB ยังไม่มีคอลัมน์ชื่อ reporter_name ให้ลองเพิ่มดูนะครับ จะได้เก็บประวัติได้
    $sql = "INSERT INTO repairs (ticket_no, reporter_uid, equipment_type, location, phone_number, problem_desc, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'รอรับเรื่อง')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $ticket_no, $reporter, $equipment, $location, $phone, $desc);
    
    if ($stmt->execute()) {
        // 3. จัดรูปแบบข้อความแจ้งเตือนให้ "อ่านง่าย" สไตล์ใบจดงาน
        $message_text = "📌 แจ้งซ่อมโดย: $reporter\n" .
                        "📅 วันที่: " . date('d/m/y') . " เวลา: " . date('H:i') . "\n" .
                        "🏢 ห้อง: $location\n" .
                        "🛠 อุปกรณ์: $equipment\n" .
                        "📝 อาการ: $desc\n" .
                        "📞 เบอร์โทร: $phone\n" .
                        "🎫 เลขที่คิวงาน: $ticket_no";

        // 4. ส่ง LINE (Broadcast)
        $accessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU='; 

        $message = [
            'messages' => [['type' => 'text', 'text' => $message_text]]
        ];

        $ch = curl_init("https://api.line.me/v2/bot/message/broadcast");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            echo "<script>alert('แจ้งซ่อมสำเร็จ!'); window.location='report_form.html';</script>";
        } else {
            echo "แจ้งซ่อมสำเร็จในระบบ แต่ส่ง LINE ไม่ได้ (Error: $httpCode)";
        }
    } else {
        echo "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>