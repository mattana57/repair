<?php
require 'db_connect.php';
require 'env.php';

// ========================================================
// 1. ตั้งค่า API Keys และข้อมูลกลุ่มช่าง
// ========================================================
// (ดึง $channelAccessToken มาจากไฟล์ env.php)
$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; // ไอดีกลุ่มช่างที่ให้บอทส่งงานไปให้

// ========================================================
// ฟังก์ชันสกัดคำ (Rule-based) ทำงานแทน AI
// ========================================================
function extract_repair_info($text) {
    $equipment = "ไม่ระบุอุปกรณ์ (รอช่างตรวจสอบ)";
    $location = "ไม่ระบุสถานที่";
    
    // คลังคำศัพท์ที่พบบ่อย (เพิ่มลดได้ตามต้องการ)
    $equipments = ['แอร์', 'ไฟ', 'น้ำ', 'พัดลม', 'คอม', 'ปริ้น', 'เน็ต', 'ประตู', 'หน้าต่าง', 'โปรเจคเตอร์', 'ท่อ'];
    foreach ($equipments as $keyword) {
        if (mb_strpos($text, $keyword) !== false) {
            $equipment = $keyword;
            break;
        }
    }
    
    // ดักจับสถานที่ เช่น ห้อง 101, ตึก A
    preg_match('/(ห้อง\s*[a-zA-Z0-9ก-๙]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+)/i', $text, $matches);
    if (!empty($matches[0])) {
        $location = trim($matches[0]);
    }
    
    return [$equipment, $location];
}

// ========================================================
// 2. รับข้อมูลที่ LINE ส่งมา
// ========================================================
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
        $userId = $event['source']['userId'];
        $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;

        // ========================================================
        // ส่วนที่ A: จัดการเมื่อผู้ใช้ "พิมพ์ข้อความ" แจ้งซ่อม
        // ========================================================
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);

            if (mb_strpos($text, 'แจ้งซ่อม') !== false) {
                
                $user_name = get_line_profile($userId, null, $channelAccessToken);
                $ticket_no = "MR-" . date("Ymd-His");
                $status = "รอรับเรื่อง"; 
                $phone_number = "ไม่ระบุ";

                // ใช้ฟังก์ชันดักคำแทน AI
                list($equipment, $location) = extract_repair_info($text);
                $problem = $text; // เก็บประโยคเต็มๆ เป็นอาการเสียไปเลย

                // 1. บันทึกลงฐานข้อมูล
                $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $ticket_no, $equipment, $location, $problem, $status, $user_name, $phone_number, $userId);
                
                if($stmt->execute()) {
                    // 2. ตอบกลับผู้ใช้ว่ารับเรื่องแล้ว
                    $replyMsg = ['type' => 'text', 'text' => "📝 รับเรื่องแจ้งซ่อมเรียบร้อยค่ะ\nอุปกรณ์: $equipment\nสถานที่: $location\n\nระบบกำลังประสานงานช่างให้ รอสักครู่นะคะ 🔎"];
                    send_reply($replyToken, $replyMsg, $channelAccessToken);

                    // 3. ส่ง Flex Message ไปที่กลุ่มช่าง ให้กดรับงาน
                    $pushMsg = [
                        'type' => 'flex',
                        'altText' => '🚨 มีงานแจ้งซ่อมใหม่',
                        'contents' => [
                            'type' => 'bubble',
                            'body' => [
                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => '🚨 มีงานแจ้งซ่อมใหม่!', 'weight' => 'bold', 'color' => '#ef4444', 'size' => 'md'],
                                    ['type' => 'text', 'text' => "อุปกรณ์: $equipment", 'wrap' => true],
                                    ['type' => 'text', 'text' => "สถานที่: $location", 'wrap' => true],
                                    ['type' => 'text', 'text' => "อาการ: $problem", 'wrap' => true, 'color' => '#666666']
                                ]
                            ],
                            'footer' => [
                                'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'button', 'style' => 'primary', 'color' => '#3b82f6', 'height' => 'sm',
                                        'action' => ['type' => 'postback', 'label' => '🛠️ กดรับงาน', 'data' => "action=accept&ticket=$ticket_no"]
                                    ]
                                ]
                            ]
                        ]
                    ];
                    send_push($line_group_id, $pushMsg, $channelAccessToken);
                }
            }
        }
        
        // ========================================================
        // ส่วนที่ B: จัดการเมื่อกดปุ่ม (Postback Event) - ช่างรับงาน / ปิดงาน / ประเมิน
        // ========================================================
        elseif ($event['type'] == 'postback') {
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                // ----------------------------------------------------
                // 1. ช่างกดรับงาน
                // ----------------------------------------------------
                if ($postbackData['action'] == 'accept') {
                    $stmt_check = $conn->prepare("SELECT status, line_user_id, equipment_type FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job && $job['status'] == 'รอรับเรื่อง') {
                        // ดึงชื่อช่างจาก ID ของคนที่กดปุ่ม
                        $tech_name = get_line_profile($userId, null, $channelAccessToken);

                        $stmt = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ', technician_name = ? WHERE ticket_no = ?");
                        $stmt->bind_param("ss", $tech_name, $ticket_no);
                        $stmt->execute();

                        // ตอบกลับในกลุ่มช่างว่ารับงานแล้ว + ขึ้นปุ่มให้กดปิดงาน
                        $replyMsg = [
                            'type' => 'flex',
                            'altText' => 'อัปเดตสถานะงาน',
                            'contents' => [
                                'type' => 'bubble',
                                'body' => [
                                    'type' => 'box', 'layout' => 'vertical',
                                    'contents' => [
                                        ['type' => 'text', 'text' => "✅ ช่าง $tech_name รับงานแล้ว", 'weight' => 'bold', 'color' => '#10b981'],
                                        ['type' => 'text', 'text' => "ใบงาน: $ticket_no"]
                                    ]
                                ],
                                'footer' => [
                                    'type' => 'box', 'layout' => 'horizontal',
                                    'contents' => [
                                        ['type' => 'button', 'style' => 'primary', 'color' => '#ef4444',
                                            'action' => ['type' => 'postback', 'label' => '🏁 แจ้งปิดงาน', 'data' => "action=close&ticket=$ticket_no"]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                        send_reply($replyToken, $replyMsg, $channelAccessToken);

                        // แจ้งเตือนผู้แจ้งซ่อม
                        send_push($job['line_user_id'], ['type' => 'text', 'text' => "👨‍🔧 ช่าง $tech_name รับงานแล้ว\nและกำลังเดินทางไปดำเนินการค่ะ 🛵"], $channelAccessToken);
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ งานนี้มีผู้รับไปแล้ว หรือถูกปิดไปแล้วค่ะ"], $channelAccessToken);
                    }
                }
                
                // ----------------------------------------------------
                // 2. ช่างกดปิดงาน
                // ----------------------------------------------------
                elseif ($postbackData['action'] == 'close') {
                    $stmt_check = $conn->prepare("SELECT status, line_user_id FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job && $job['status'] == 'กำลังดำเนินการ') {
                        $stmt = $conn->prepare("UPDATE repairs SET status = 'ปิดงาน' WHERE ticket_no = ?");
                        $stmt->bind_param("s", $ticket_no);
                        $stmt->execute();

                        send_reply($replyToken, ['type' => 'text', 'text' => "🎉 ปิดงาน $ticket_no สำเร็จ ระบบได้ส่งแบบประเมินให้ผู้แจ้งซ่อมแล้วค่ะ"], $channelAccessToken);

                        // ส่ง Quick Reply รูปดาวไปให้ผู้แจ้งซ่อม
                        $review_msg = [
                            'type' => 'text',
                            'text' => "🎉 งานแจ้งซ่อม $ticket_no เสร็จสิ้นแล้ว!\nรบกวนให้คะแนนความพึงพอใจ เพื่อการพัฒนาบริการด้วยนะคะ 👇",
                            'quickReply' => [
                                'items' => [
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '⭐️⭐️⭐️⭐️⭐️', 'data' => "action=rate&score=5&ticket=$ticket_no", 'displayText' => 'ให้ 5 ดาว']],
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '⭐️⭐️⭐️⭐️', 'data' => "action=rate&score=4&ticket=$ticket_no", 'displayText' => 'ให้ 4 ดาว']],
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '⭐️⭐️⭐️', 'data' => "action=rate&score=3&ticket=$ticket_no", 'displayText' => 'ให้ 3 ดาว']]
                                ]
                            ]
                        ];
                        send_push($job['line_user_id'], $review_msg, $channelAccessToken);
                    }
                }

                // ----------------------------------------------------
                // 3. ผู้ใช้กดให้คะแนนดาว
                // ----------------------------------------------------
                elseif ($postbackData['action'] == 'rate') {
                    $score = $postbackData['score'];
                    
                    // 💡 หมายเหตุ: ในฐานข้อมูลตาราง repairs ต้องมีคอลัมน์ชื่อ rating (ชนิด INT) ด้วยนะคะ
                    // ถ้ายังไม่มี ต้องไปเพิ่มใน phpMyAdmin ก่อนค่ะ
                    $stmt = $conn->prepare("UPDATE repairs SET rating = ? WHERE ticket_no = ?");
                    $stmt->bind_param("is", $score, $ticket_no);
                    
                    if($stmt->execute()){
                        send_reply($replyToken, ['type' => 'text', 'text' => "💖 ขอบคุณสำหรับคะแนน $score ดาว ค่ะ!\nหากมีปัญหาเพิ่มเติม แจ้งซ่อมได้ตลอดเลยนะคะ"], $channelAccessToken);
                    }
                }
            }
        }
    }
}
echo "OK";

// ========================================================
// ฟังก์ชันช่วยเหลือต่างๆ
// ========================================================
function get_line_profile($userId, $groupId, $accessToken) {
    $url = $groupId ? "https://api.line.me/v2/bot/group/$groupId/member/$userId" : "https://api.line.me/v2/bot/profile/$userId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return isset($data['displayName']) ? $data['displayName'] : 'เจ้าหน้าที่ช่าง';
}

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