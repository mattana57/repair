<?php
require 'db_connect.php';
require 'env.php';

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $userId = $event['source']['userId'];
            $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;
            
            $message_id = $event['message']['id'];
            $quoted_msg_id = isset($event['message']['quotedMessageId']) ? $event['message']['quotedMessageId'] : null;
            
            // ========================================================
            // เคสที่ 1: ช่างกด Reply ข้อความเพื่อ "รับงาน"
            // ========================================================
            if ($quoted_msg_id) {
                $text_clean = mb_strtolower(str_replace(' ', '', $text), 'UTF-8');
                $accept_words = ['ครับ', 'ค่ะ', 'รับงาน', 'รับทราบ', 'โอเค', 'จัดไป', 'รับเรื่อง', 'กำลังไป', 'ok'];
                $is_accept = false;
                
                foreach ($accept_words as $w) {
                    if (mb_strpos($text_clean, $w) !== false) {
                        $is_accept = true; break;
                    }
                }

                if ($is_accept) {
                    $stmt = $conn->prepare("SELECT ticket_no, status FROM repairs WHERE line_message_id = ?");
                    $stmt->bind_param("s", $quoted_msg_id);
                    $stmt->execute();
                    $job = $stmt->get_result()->fetch_assoc();

                    if ($job && $job['status'] == 'รอรับเรื่อง') {
                        // ดึงชื่อเฉพาะตอนที่จะบันทึกช่าง (ประหยัดเวลา)
                        $user_name = get_line_profile($userId, $groupId, $channelAccessToken);
                        
                        $stmt_up = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ', technician_name = ? WHERE ticket_no = ?");
                        $stmt_up->bind_param("ss", $user_name, $job['ticket_no']);
                        $stmt_up->execute();
                    }
                }
            } 
            // ========================================================
            // เคสที่ 2: คนพิมพ์แจ้งซ่อมใหม่
            // ========================================================
            else {
                if (mb_strpos($text, '@') !== false || mb_strpos($text, 'แจ้งซ่อม') !== false || mb_strpos($text, 'พัง') !== false || mb_strpos($text, 'เสีย') !== false || mb_strpos($text, 'แปลก') !== false) {
                    
                    $gemini_prompt = "ทำหน้าที่เป็นผู้ช่วยรับแจ้งซ่อม สกัดข้อมูลจากข้อความต่อไปนี้:\nข้อความ: '$text'\nส่งกลับมาเป็น JSON format ห้ามมีข้อความอื่น โดยมี key:\n- equipment: ชื่ออุปกรณ์\n- building: ชื่อตึก\n- room: เลขห้อง\n- problem: อาการ";

                    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";
                    $gemini_data = [
                        "contents" => [["parts" => [["text" => $gemini_prompt]]]],
                        "generationConfig" => ["temperature" => 0.1, "responseMimeType" => "application/json"]
                    ];

                    $ch = curl_init($gemini_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $gemini_api_key]);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gemini_data));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // จำกัดเวลาให้ AI ไม่เกิน 10 วินาที เพื่อไม่ให้ระบบค้าง
                    
                    $gemini_response = curl_exec($ch);
                    curl_close($ch);

                    $gemini_result = json_decode($gemini_response, true);
                    
                    if(isset($gemini_result['candidates'][0]['content']['parts'][0]['text'])) {
                        $ai_data = json_decode($gemini_result['candidates'][0]['content']['parts'][0]['text'], true);

                        $equipment = !empty($ai_data['equipment']) ? $ai_data['equipment'] : 'ไม่ระบุอุปกรณ์';
                        $building = !empty($ai_data['building']) ? $ai_data['building'] : '';
                        $room = !empty($ai_data['room']) ? $ai_data['room'] : '';
                        $location = trim($building . ' ' . $room) ?: 'ไม่ระบุสถานที่';
                        $problem = !empty($ai_data['problem']) ? $ai_data['problem'] : 'ไม่ระบุอาการ';
                        
                        if ($equipment != 'ไม่ระบุอุปกรณ์' && $problem != 'ไม่ระบุอาการ') {
                            // ดึงชื่อเฉพาะตอนสร้างใบงานสำเร็จ (ประหยัดเวลา)
                            $user_name = get_line_profile($userId, $groupId, $channelAccessToken);
                            
                            $ticket_no = "MR-" . date("Ymd-His");
                            $status = "รอรับเรื่อง"; 
                            $phone_number = "ไม่ระบุ";

                            $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id, line_message_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssssss", $ticket_no, $equipment, $location, $problem, $status, $user_name, $phone_number, $userId, $message_id);
                            $stmt->execute();
                        }
                    }
                }
            }
        }
    }
}
echo "OK";

function get_line_profile($userId, $groupId, $accessToken) {
    $url = $groupId ? "https://api.line.me/v2/bot/group/$groupId/member/$userId" : "https://api.line.me/v2/bot/profile/$userId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return isset($data['displayName']) ? $data['displayName'] : 'ผู้ใช้งาน';
}
?>