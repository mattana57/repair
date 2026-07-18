<?php
// ตั้งค่าโซนเวลาเป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reporter_name = $_POST['reporter_name'];
    $line_user_id = isset($_POST['line_user_id']) ? $_POST['line_user_id'] : null; // รับค่า LINE ID
    
    $equipment = ($_POST['equipment_type'] == 'other') ? $_POST['other_equip'] : $_POST['equipment_type'];
    $location = $_POST['building'] . " ห้อง " . $_POST['room_no'];
    $phone_number = $_POST['phone_number'];
    $problem_desc = $_POST['problem_desc'];
    $ticket_no = "MR-" . date("Ymd-His");

    // ดึงเวลาปัจจุบันเพื่อแสดงใน LINE
    $current_time = date("d/m/Y H:i น.");

    $target_dir = "uploads/";
    $image_name = null;
    if (!empty($_FILES["image_before"]["name"])) {
        $image_name = $ticket_no . "_" . basename($_FILES["image_before"]["name"]);
        move_uploaded_file($_FILES["image_before"]["tmp_name"], $target_dir . $image_name);
    }

    $sql = "INSERT INTO repairs (ticket_no, reporter_name, line_user_id, equipment_type, location, phone_number, problem_desc, image_before, status, building, room_no) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'รอรับเรื่อง', ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $ticket_no, $reporter_name, $line_user_id, $equipment, $location, $phone_number, $problem_desc, $image_name, $_POST['building'], $_POST['room_no']);

    if ($stmt->execute()) {
        
        $channelAccessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU=';
        
        // ==========================================
        // 1. ส่งข้อความแจ้งเตือนกลับหา "ผู้แจ้งซ่อม" แบบส่วนตัว
        // ==========================================
        if(!empty($line_user_id)) {
            $messageText = "✅ ได้รับเรื่องแจ้งซ่อมเรียบร้อยแล้วค่ะ\n\n" .
                           "📋 เลขที่ใบงาน: " . $ticket_no . "\n" .
                           "🕒 เวลาที่แจ้ง: " . $current_time . "\n" .
                           "👤 ผู้แจ้ง: " . $reporter_name . "\n" .
                           "📞 เบอร์ติดต่อ: " . $phone_number . "\n" .
                           "💻 อุปกรณ์: " . $equipment . "\n" .
                           "⚠️ อาการ: " . $problem_desc . "\n\n" .
                           "📌 สถานะ: รอรับเรื่อง\n" .
                           "👨‍🔧 ช่างผู้ดูแล: - ยังไม่ระบุ -\n\n" .
                           "ระบบจะแจ้งเตือนความคืบหน้าให้ทราบที่นี่ค่ะ";

            $postData = [
                'to' => $line_user_id, 
                'messages' => [['type' => 'text', 'text' => $messageText]]
            ];

            $ch = curl_init('https://api.line.me/v2/bot/message/push');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $channelAccessToken));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
        }

        // ==========================================
        // 2. ส่งข้อความแจ้งเตือนเข้า "กลุ่มช่าง" (Admin & Technician)
        // ==========================================
        // 🚨 นำ Group ID (ที่ขึ้นต้นด้วยตัว C) มาใส่ในเครื่องหมายฝนทองตรงนี้ค่ะ 👇
        $line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 
        
        if(!empty($line_group_id) && $line_group_id !== 'Caed57e09981787d718ce11abb3b2db15') {
            $groupMessage = "🚨 มีงานแจ้งซ่อมใหม่เข้ามาจ้า!\n\n" .
                            "📋 ใบงาน: " . $ticket_no . "\n" .
                            "🕒 เวลา: " . $current_time . "\n" .
                            "👤 ผู้แจ้ง: " . $reporter_name . "\n" .
                            "💻 อุปกรณ์: " . $equipment . "\n" .
                            "📍 สถานที่: " . $location . "\n" .
                            "⚠️ อาการ: " . $problem_desc . "\n\n" .
                            "👇 ช่างคนไหนว่าง กดลิงก์เข้าระบบไปรับงานได้เลยค่ะ!\n" .
                            "http://103.99.11.147/repair/dashboard.php";

            $postDataGroup = [
                'to' => $line_group_id,
                'messages' => [['type' => 'text', 'text' => $groupMessage]]
            ];

            $ch2 = curl_init('https://api.line.me/v2/bot/message/push');
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $channelAccessToken));
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($postDataGroup));
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch2);
            curl_close($ch2);
        }

        header("Location: form_repair.php?status=success&ticket=" . urlencode($ticket_no)); 
    } else {
        header("Location: form_repair.php?status=error&msg=" . urlencode($stmt->error));
    }
    $stmt->close();
    exit();
}
?>