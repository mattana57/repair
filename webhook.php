<?php
require 'db_connect.php';
require 'env.php';

// ฟังก์ชันแยกหมวดหมู่จากคำศัพท์ (Rule-based Extraction แทน AI)
function extract_repair_info($text) {
    $equipment = "แจ้งซ่อมทั่วไป";
    $location = "ไม่ระบุสถานที่";
    
    // คลังคำศัพท์อุปกรณ์
    $equipments = [
        'แอร์' => 'เครื่องปรับอากาศ', 'ไฟ' => 'ระบบไฟฟ้า', 'น้ำ' => 'ระบบประปา',
        'พัดลม' => 'พัดลม', 'คอม' => 'คอมพิวเตอร์', 'ปริ้น' => 'เครื่องปริ้นเตอร์',
        'เน็ต' => 'เครือข่ายอินเทอร์เน็ต', 'ประตู' => 'ประตู/หน้าต่าง'
    ];
    
    foreach ($equipments as $keyword => $category) {
        if (mb_strpos($text, $keyword) !== false) {
            $equipment = $category;
            break;
        }
    }
    
    // ค้นหาสถานที่ (ดักจับคำว่า ห้อง... หรือ ตึก...)
    preg_match('/(ห้อง\s*[a-zA-Z0-9ก-๙]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+)/i', $text, $matches);
    if (!empty($matches[0])) {
        $location = trim($matches[0]);
    }
    
    return [$equipment, $location];
}

$content = file_get_contents('php://input');
$events = json_decode($content, true);

// ⚠️ ตั้งค่า ID ของกลุ่มไลน์ช่างที่นี่ เพื่อให้ระบบยิงแจ้งเตือนงานใหม่เข้าไป
$tech_group_id = "ใส่_LINE_GROUP_ID_ของกลุ่มช่างที่นี่"; 

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
        $userId = $event['source']['userId'];
        $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;
        
        // ========================================================
        // ส่วนที่ 1: จัดการข้อความที่พิมพ์เข้ามา (แจ้งซ่อมใหม่)
        // ========================================================
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            
            if (mb_strpos($text, '@repair-แจ้งซ่อม') !== false || mb_strpos($text, 'แจ้งซ่อม') !== false) {
                
                $user_name = get_line_profile($userId, $groupId, $channelAccessToken);
                $ticket_no = "MR-" . date("Ymd-His");
                $status = "รอรับเรื่อง";
                
                // ใช้ฟังก์ชันดักจับคำแทน AI
                list($equipment, $location) = extract_repair_info($text);
                
                // บันทึกลงฐานข้อมูล
                $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, line_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $ticket_no, $equipment, $location, $text, $status, $user_name, $userId);
                $stmt->execute();
                
                // 1. ตอบกลับผู้ใช้ว่ารับเรื่องแล้ว
                $reply_msg = [
                    'type' => 'text',
                    'text' => "📝 รับเรื่องแจ้งซ่อมเรียบร้อยค่ะ\nหมวดหมู่: $equipment\nสถานที่: $location\n\nระบบกำลังประสานงานช่างให้ รอสักครู่นะคะ 🔎"
                ];
                send_reply($replyToken, $reply_msg, $channelAccessToken);
                
                // 2. ยิง Push Message ไปที่กลุ่มช่าง ให้กดปุ่มรับงาน (Flex Message แบบปุ่ม)
                $push_msg = [
                    'type' => 'template',
                    'altText' => 'มีงานแจ้งซ่อมใหม่เข้า!',
                    'template' => [
                        'type' => 'buttons',
                        'title' => '🚨 มีงานแจ้งซ่อมใหม่',
                        'text' => "สถานที่: $location\nอาการ: $text",
                        'actions' => [
                            [
                                'type' => 'postback',
                                'label' => '🛠️ กดเพื่อรับงานนี้',
                                'data' => "action=accept&ticket_no=$ticket_no",
                                'displayText' => "รับงาน: $ticket_no"
                            ]
                        ]
                    ]
                ];
                send_push($tech_group_id, $push_msg, $channelAccessToken);
            }
        }
        
        // ========================================================
        // ส่วนที่ 2: จัดการเมื่อมีการกดปุ่ม (Postback Events) 
        // ========================================================
        elseif ($event['type'] == 'postback') {
            // แกะข้อมูลที่ซ่อนมาในปุ่ม (เช่น action=accept&ticket_no=MR-...)
            parse_str($event['postback']['data'], $postback_data);
            $action = $postback_data['action'];
            $ticket_no = $postback_data['ticket_no'];
            
            // 2.1 ช่างกดปุ่ม "รับงาน"
            if ($action == 'accept') {
                $stmt = $conn->prepare("SELECT status, line_user_id FROM repairs WHERE ticket_no = ?");
                $stmt->bind_param("s", $ticket_no);
                $stmt->execute();
                $job = $stmt->get_result()->fetch_assoc();
                
                if ($job && $job['status'] == 'รอรับเรื่อง') {
                    // ดึงชื่อช่างจากคนที่กดปุ่ม
                    $tech_name = get_line_profile($userId, $groupId, $channelAccessToken);
                    
                    // อัปเดตฐานข้อมูล
                    $stmt_up = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ', technician_name = ? WHERE ticket_no = ?");
                    $stmt_up->bind_param("ss", $tech_name, $ticket_no);
                    $stmt_up->execute();
                    
                    // ตอบในกลุ่มช่างว่ารับงานสำเร็จ พร้อมปุ่มกดปิดงาน
                    $reply_msg = [
                        'type' => 'template',
                        'altText' => 'รับงานสำเร็จ',
                        'template' => [
                            'type' => 'buttons',
                            'text' => "✅ ช่าง $tech_name รับงาน $ticket_no แล้ว\n(เมื่อซ่อมเสร็จ สามารถกดปุ่มด้านล่างเพื่อปิดงาน)",
                            'actions' => [
                                [
                                    'type' => 'postback',
                                    'label' => '🏁 กดเพื่อปิดงาน',
                                    'data' => "action=close&ticket_no=$ticket_no",
                                    'displayText' => "ปิดงาน: $ticket_no"
                                ]
                            ]
                        ]
                    ];
                    send_reply($replyToken, $reply_msg, $channelAccessToken);
                    
                    // ยิงแจ้งเตือนกลับไปหาผู้แจ้งซ่อมแบบ 1 ต่อ 1
                    send_push($job['line_user_id'], ['type' => 'text', 'text' => "🔔 อัปเดตสถานะ: ช่าง $tech_name รับงานแล้ว และกำลังเดินทางไปดำเนินการค่ะ 🛵"], $channelAccessToken);
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "❌ งานนี้มีช่างรับไปแล้ว หรือถูกปิดงานไปแล้วค่ะ"], $channelAccessToken);
                }
            }
            
            // 2.2 ช่างกดปุ่ม "ปิดงาน"
            elseif ($action == 'close') {
                $stmt = $conn->prepare("SELECT status, line_user_id FROM repairs WHERE ticket_no = ?");
                $stmt->bind_param("s", $ticket_no);
                $stmt->execute();
                $job = $stmt->get_result()->fetch_assoc();
                
                if ($job && $job['status'] == 'กำลังดำเนินการ') {
                    $stmt_up = $conn->prepare("UPDATE repairs SET status = 'ปิดงาน' WHERE ticket_no = ?");
                    $stmt_up->bind_param("s", $ticket_no);
                    $stmt_up->execute();
                    
                    send_reply($replyToken, ['type' => 'text', 'text' => "🎉 ปิดงาน $ticket_no เรียบร้อยแล้ว ระบบได้ส่งแบบประเมินให้ผู้แจ้งซ่อมแล้วค่ะ"], $channelAccessToken);
                    
                    // ส่งแบบประเมินดาว (Quick Reply) ไปหาผู้แจ้งซ่อม
                    $review_msg = [
                        'type' => 'text',
                        'text' => "🎉 การแจ้งซ่อม $ticket_no ดำเนินการเสร็จสิ้นแล้ว!\nรบกวนให้คะแนนความพึงพอใจ เพื่อการพัฒนาบริการของเรานะคะ 👇",
                        'quickReply' => [
                            'items' => [
                                ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '⭐️⭐️⭐️⭐️⭐️', 'data' => "action=rate&score=5&ticket_no=$ticket_no", 'displayText' => 'ให้ 5 ดาว']],
                                ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '⭐️⭐️⭐️⭐️', 'data' => "action=rate&score=4&ticket_no=$ticket_no", 'displayText' => 'ให้ 4 ดาว']],
                                ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '⭐️⭐️⭐️', 'data' => "action=rate&score=3&ticket_no=$ticket_no", 'displayText' => 'ให้ 3 ดาว']]
                            ]
                        ]
                    ];
                    send_push($job['line_user_id'], $review_msg, $channelAccessToken);
                }
            }
            
            // 2.3 ผู้แจ้งกดให้คะแนนดาว (Rate)
            elseif ($action == 'rate') {
                $score = $postback_data['score'];
                
                // สมมติว่ามีคอลัมน์ rating ในตาราง (ถ้าไม่มีให้สร้างเพิ่มนะคะ)
                // $stmt = $conn->prepare("UPDATE repairs SET rating = ? WHERE ticket_no = ?");
                // $stmt->bind_param("is", $score, $ticket_no);
                // $stmt->execute();
                
                send_reply($replyToken, ['type' => 'text', 'text' => "💖 ขอบคุณสำหรับคะแนน $score ดาว ค่ะ! หากมีปัญหาเพิ่มเติม แจ้งซ่อมเข้ามาใหม่ได้ตลอดเลยนะคะ"], $channelAccessToken);
            }
        }
    }
}
echo "OK";

// ฟังก์ชันดึงชื่อผู้ใช้
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

// ฟังก์ชันตอบกลับข้อความ (Reply)
function send_reply($replyToken, $messageData, $accessToken) {
    if (!$replyToken) return;
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

// ฟังก์ชันส่งข้อความแบบตรงตัว (Push)
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