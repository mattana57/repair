<?php
require 'db_connect.php';
require 'env.php';

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $replyToken = $event['replyToken']; 
            $userId = $event['source']['userId'];
            $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;
            
            $message_id = $event['message']['id'];
            $quoted_msg_id = isset($event['message']['quotedMessageId']) ? $event['message']['quotedMessageId'] : null;
            
            // ========================================================
            // สเตปที่ 2: ช่างกด Reply "รับงาน" -> เปลี่ยนสถานะเป็น "ช่างรับงาน" และบันทึกชื่อช่าง
            // ========================================================
            if ($quoted_msg_id) {
                $text_clean = mb_strtolower(str_replace(' ', '', $text), 'UTF-8');
                $accept_words = ['ครับ', 'ค่ะ', 'รับงาน', 'รับทราบ', 'โอเค', 'จัดไป', 'รับเรื่อง', 'กำลังไป', 'ok', 'ได้ครับ', 'ได้ค่ะ', 'ได้ครับผม'];
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

                    // ถ้าสถานะเป็นรอรับเรื่อง ช่างตอบปุ๊บ ให้ถือว่ารับงาน
                    if ($job && $job['status'] == 'รอรับเรื่อง') {
                        $user_name = get_line_profile($userId, $groupId, $channelAccessToken);
                        
                        // 🛠️ เปลี่ยนสถานะตรงนี้เป็น "ช่างรับงาน" ตามที่ออกแบบไว้เลยค่ะ
                        $stmt_up = $conn->prepare("UPDATE repairs SET status = 'ช่างรับงาน', technician_name = ? WHERE ticket_no = ?");
                        $stmt_up->bind_param("ss", $user_name, $job['ticket_no']);
                        $stmt_up->execute();
                    }
                }
            } 
            // ========================================================
            // สเตปที่ 1: คนพิมพ์แจ้งซ่อมใหม่ -> บันทึกสถานะ "รอรับเรื่อง"
            // ========================================================
            else {
                if (mb_strpos($text, '@') !== false || mb_strpos($text, 'แจ้งซ่อม') !== false || mb_strpos($text, 'พัง') !== false || mb_strpos($text, 'เสีย') !== false || mb_strpos($text, 'แปลก') !== false || mb_strpos($text, 'ดู') !== false) {
                    
                    $gemini_prompt = "ดึงข้อมูลจากประโยค: '$text' ตอบแค่ JSON โครงสร้างนี้เท่านั้น {\"equipment\":\"\",\"building\":\"\",\"room\":\"\",\"problem\":\"\"} ถ้าไม่มีให้ใส่ ไม่ระบุ";

                    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";
                    $gemini_data = [
                        "contents" => [["parts" => [["text" => $gemini_prompt]]]],
                        "generationConfig" => ["temperature" => 0.0, "responseMimeType" => "application/json"]
                    ];

                    $ch = curl_init($gemini_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $gemini_api_key]);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gemini_data));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
                    
                    $gemini_response = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($curl_error) {
                        send_reply($replyToken, ['type' => 'text', 'text' => "🚨 [เครือข่ายขัดข้อง]: " . $curl_error], $channelAccessToken);
                        continue;
                    }
                    if ($http_code != 200) {
                        send_reply($replyToken, ['type' => 'text', 'text' => "🚨 [Google API Error (รหัส $http_code)]: " . $gemini_response], $channelAccessToken);
                        continue;
                    }

                    $gemini_result = json_decode($gemini_response, true);
                    
                    if(isset($gemini_result['candidates'][0]['content']['parts'][0]['text'])) {
                        $ai_data = json_decode($gemini_result['candidates'][0]['content']['parts'][0]['text'], true);

                        $equipment = !empty($ai_data['equipment']) ? $ai_data['equipment'] : 'ไม่ระบุ';
                        $building = !empty($ai_data['building']) ? $ai_data['building'] : '';
                        $room = !empty($ai_data['room']) ? $ai_data['room'] : '';
                        $location = trim($building . ' ' . $room) ?: 'ไม่ระบุสถานที่';
                        
                        $problem = !empty($ai_data['problem']) ? $ai_data['problem'] : 'ไม่ระบุ';
                        if ($problem == 'ไม่ระบุ' || $problem == 'ไม่ระบุอาการ' || $problem == 'null') {
                            $problem = 'มีความผิดปกติ (รอช่างตรวจสอบ)';
                        }
                        
                        if ($equipment != 'ไม่ระบุ' && $equipment != 'ไม่ระบุอุปกรณ์') {
                            $user_name = get_line_profile($userId, $groupId, $channelAccessToken);
                            
                            $ticket_no = "MR-" . date("Ymd-His");
                            $status = "รอรับเรื่อง"; 
                            $phone_number = "ไม่ระบุ";

                            $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id, line_message_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssssss", $ticket_no, $equipment, $location, $problem, $status, $user_name, $phone_number, $userId, $message_id);
                            
                            if (!$stmt->execute()) {
                                send_reply($replyToken, ['type' => 'text', 'text' => "🚨 [ฐานข้อมูลขัดข้อง]: บันทึกไม่สำเร็จ " . $stmt->error], $channelAccessToken);
                            }
                        } else {
                            send_reply($replyToken, ['type' => 'text', 'text' => "🚨 [บอทงง]: หาชื่อ 'อุปกรณ์' จากประโยคไม่เจอค่ะ ช่วยระบุชื่ออุปกรณ์ให้หน่อยนะคะ 🙏"], $channelAccessToken);
                        }
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "🚨 [AI สับสน]: AI ตอบข้อมูลมาผิดรูปแบบค่ะ ลองพิมพ์ใหม่อีกครั้งนะคะ"], $channelAccessToken);
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
?>