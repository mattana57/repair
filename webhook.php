<?php
require 'db_connect.php';
require 'env.php';

// ========================================================
// 1. ตั้งค่า API Keys และข้อมูลต่างๆ
// ========================================================
$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

// ========================================================
// 2. รับข้อมูลที่ LINE ส่งมา
// ========================================================
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $replyToken = $event['replyToken'];
            $userId = $event['source']['userId'];

            // ----------------------------------------------------
            // กรณีที่ 1: พิมพ์คำว่า "ขอไอดีกลุ่ม" (ฟีเจอร์เดิม)
            // ----------------------------------------------------
            if ($text == 'ขอไอดีกลุ่ม') {
                $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : 'นี่ไม่ใช่กลุ่ม (เป็นแชทส่วนตัว)';
                $messageData = ['type' => 'text', 'text' => "Group ID ของกลุ่มนี้คือ:\n" . $groupId];
                send_reply($replyToken, $messageData, $channelAccessToken);
            } 
            
            // ----------------------------------------------------
            // กรณีที่ 2: กดปุ่ม "ติดต่อแอดมิน" (ฟีเจอร์เดิม)
            // ----------------------------------------------------
            elseif ($text == 'ติดต่อแอดมิน') {
                $replyMsg = ['type' => 'text', 'text' => "แอดมินรับทราบแล้วค่ะ 🙋‍♀️\n\nรบกวนพิมพ์รายละเอียดปัญหา หรือข้อสงสัยทิ้งไว้ได้เลยนะคะ เจ้าหน้าที่จะรีบเข้ามาตรวจสอบและตอบกลับให้เร็วที่สุดค่ะ"];
                send_reply($replyToken, $replyMsg, $channelAccessToken);

                if (!empty($line_group_id)) {
                    $pushMsg = ['type' => 'text', 'text' => "🚨 แจ้งเตือน: มีผู้ใช้ต้องการติดต่อเจ้าหน้าที่!\n\nรบกวนแอดมินเข้าไปตรวจสอบและตอบแชทผู้ใช้ในแอป LINE Official Account ด้วยนะคะ 💬\n\n💻 สำหรับแอดมินที่ใช้คอมพิวเตอร์ สามารถคลิกลิงก์นี้เพื่อเปิดหน้าแชทได้เลยค่ะ:\nhttps://chat.line.biz/"];
                    send_push($line_group_id, $pushMsg, $channelAccessToken);
                }
            }

            // ----------------------------------------------------
            // กรณีที่ 3: พิมพ์ข้อความอื่นๆ -> ส่งให้ AI ตีความแจ้งซ่อม (ฟีเจอร์ใหม่)
            // ----------------------------------------------------
            else {
                // ส่งประโยคให้ Gemini
                $gemini_prompt = "ทำหน้าที่เป็นผู้ช่วยรับแจ้งซ่อม สกัดข้อมูลจากข้อความต่อไปนี้:\n" .
                                 "ข้อความ: '$text'\n" .
                                 "ให้ส่งกลับมาเป็น JSON format อย่างเดียว ห้ามมีข้อความอื่น โดยมี key ดังนี้:\n" .
                                 "- equipment: ชื่ออุปกรณ์ (เช่น แอร์, คอมพิวเตอร์, เครื่องปริ้น)\n" .
                                 "- building: ชื่อตึก (เช่น SBB, ACC.BIZ)\n" .
                                 "- room: เลขห้อง\n" .
                                 "- problem: อาการที่เสีย";

                // *** แก้ไข 1: เปลี่ยน URL ให้ตรงกับที่ Google ระบุ ***
                $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";

                $gemini_data = [
                    "contents" => [["parts" => [["text" => $gemini_prompt]]]],
                    "generationConfig" => [
                        "temperature" => 0.1, 
                        "responseMimeType" => "application/json"
                    ]
                ];

                $ch = curl_init($gemini_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                // *** แก้ไข 2: เพิ่ม x-goog-api-key ใน Header ***
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $gemini_api_key
                ]);

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gemini_data));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                $gemini_response = curl_exec($ch);
                $curl_err = curl_error($ch);
                curl_close($ch);

                $gemini_result = json_decode($gemini_response, true);
                
                // เช็คว่า AI ตอบกลับมาสำเร็จหรือไม่
                if(isset($gemini_result['candidates'][0]['content']['parts'][0]['text'])) {
                    $extracted_text = $gemini_result['candidates'][0]['content']['parts'][0]['text'];
                    $ai_data = json_decode($extracted_text, true);

                    $equipment = !empty($ai_data['equipment']) ? $ai_data['equipment'] : 'ไม่ระบุอุปกรณ์';
                    $building = !empty($ai_data['building']) ? $ai_data['building'] : '';
                    $room = !empty($ai_data['room']) ? $ai_data['room'] : '';
                    $location = trim($building . ' ' . $room);
                    if(empty($location)) $location = 'ไม่ระบุสถานที่';
                    $problem = !empty($ai_data['problem']) ? $ai_data['problem'] : 'ไม่ระบุอาการ';
                    
                    $ticket_no = "MR-" . date("Ymd-His");
                    $status = "รอรับเรื่อง";
                    $reporter_name = "แจ้งผ่านแชทบอท AI"; 

                    // บันทึกลงฐานข้อมูล
                    $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, line_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $ticket_no, $equipment, $location, $problem, $status, $reporter_name, $userId);
                    
                    // ส่วนที่เติมกลับเข้ามาให้ครบสมบูรณ์
                    if($stmt->execute()) {
                        $replyText = "🤖 บอทรับเรื่องแจ้งซ่อมเรียบร้อยแล้วค่ะ!\n\n📌 เลขที่ใบงาน: $ticket_no\n💻 อุปกรณ์: $equipment\n📍 สถานที่: $location\n⚠️ ปัญหา: $problem\n\nระบบจะแจ้งเตือนให้ทราบเมื่อช่างเริ่มดำเนินการนะคะ";
                    } else {
                        $replyText = "🚨 เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: " . $stmt->error;
                    }
                } else {
                    $replyText = "🚨 พบข้อผิดพลาดจากระบบ:\n" . $gemini_response . "\nCURL Error: " . $curl_err;
                }

                $messageData = ['type' => 'text', 'text' => $replyText];
                send_reply($replyToken, $messageData, $channelAccessToken);
            }
        }
    }
}
echo "OK";

// ========================================================
// ฟังก์ชันเสริมสำหรับ LINE API
// ========================================================
function send_reply($replyToken, $messageData, $accessToken) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    $data = ['replyToken' => $replyToken, 'messages' => [$messageData]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

function send_push($to, $messageData, $accessToken) {
    $url = 'https://api.line.me/v2/bot/message/push';
    $data = ['to' => $to, 'messages' => [$messageData]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
?>