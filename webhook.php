<?php
require 'db_connect.php';
require 'env.php';

$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

function extract_repair_info($text) {
    $equipment = "ไม่ระบุอุปกรณ์";
    $location = "ไม่ระบุสถานที่";
    
    $keywords = [
        'แอร์', 'คอม', 'เครื่องปริ้น', 'printer', 'projector', 'เครื่องฉาย', 
        'จอ', 'ทีวี', 'ไมค์', 'หลอดไฟ', 'ไฟดับ', 'สายไฟ', 'ปลั๊ก', 'เน็ต', 
        'เว็บคณะ', 'มคอ', 'ประตู', 'สแกนหน้า', 'ท่อ', 'ห้องน้ำ', 'ก๊อก', 
        'ตู้กดน้ำ', 'จิ้งจก', 'นก', 'ตุ๊กแก', 'หนู', 'กลิ่นเหม็น'
    ];

    $text_lower = mb_strtolower($text, 'UTF-8');
    foreach ($keywords as $keyword) {
        if (mb_strpos($text_lower, $keyword) !== false) {
            $equipment = $keyword;
            break;
        }
    }
    
    preg_match('/(ห้อง\s*[a-zA-Z0-9ก-๙]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+)/iu', $text, $matches);
    if (!empty($matches[0])) {
        $location = trim($matches[0]);
    }
    
    return [$equipment, $location];
}

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
        $userId = $event['source']['userId'];
        $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;

        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $message_id = $event['message']['id']; 

            $text_clean = mb_strtolower(str_replace([' ', "\n", 'ค่ะ', 'ครับ', 'จ้า', 'นะ', 'พี่'], '', $text), 'UTF-8');
            $greetings = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'รับทราบ', 'เยี่ยม', 'แต้ง'];
            $is_greeting = false;
            
            foreach ($greetings as $g) {
                if (mb_strpos($text_clean, $g) !== false) {
                    $is_greeting = true;
                    break;
                }
            }
            
            if ($is_greeting && mb_strlen($text_clean) < 40) {
                $replyMsg = ['type' => 'text', 'text' => "ด้วยความยินดีค่ะ 💖 หากมีปัญหาเพิ่มเติมแจ้งบอทได้ตลอดเลยนะคะ"];
                send_reply($replyToken, $replyMsg, $channelAccessToken);
                continue; 
            }

            list($equipment, $location) = extract_repair_info($text);

            if (mb_strpos($text, 'แจ้งซ่อม') !== false || $equipment !== "ไม่ระบุอุปกรณ์") {
                
                $user_name = get_line_profile($userId, null, $channelAccessToken);
                $ticket_no = "MR-" . rand(1000, 9999);
                $status = "รอรับเรื่อง"; 
                $phone_number = "ไม่ระบุ";
                
                // ==========================================
                // 💡 กรองคำฟุ่มเฟือยออกจากประโยคอาการเสีย
                // ==========================================
                $words_to_remove = [
                    'แอร์', 'คอม', 'เครื่องปริ้น', 'printer', 'projector', 'เครื่องฉาย', 
                    'จอ', 'ทีวี', 'ไมค์', 'หลอดไฟ', 'ไฟดับ', 'สายไฟ', 'ปลั๊ก', 'เน็ต', 
                    'เว็บคณะ', 'มคอ', 'ประตู', 'สแกนหน้า', 'ท่อ', 'ห้องน้ำ', 'ก๊อก', 
                    'ตู้กดน้ำ', 'จิ้งจก', 'นก', 'ตุ๊กแก', 'หนู', 'กลิ่นเหม็น',
                    $location, 'ค่ะ', 'ครับ', 'คะ', 'คับ', 'มัน', 'รบกวน', 'ด่วน', 'แจ้งซ่อม'
                ];
                
                // ลบคำทั้งหมดที่อยู่ใน Array ออกจากข้อความที่ผู้ใช้พิมพ์
                $problem = str_replace($words_to_remove, '', $text);
                $problem = trim($problem); // ลบช่องว่างหัวท้าย
                
                // ถ้าลบหมดแล้วไม่เหลืออะไรเลย ให้ใส่ค่าเริ่มต้นแทน
                if (empty($problem)) {
                    $problem = "มีความผิดปกติ (รอตรวจสอบ)";
                }
                // ==========================================

                $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id, line_message_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssss", $ticket_no, $equipment, $location, $problem, $status, $user_name, $phone_number, $userId, $message_id);
                
                if($stmt->execute()) {
                    $replyText = "🤖 ขอรับเรื่องแจ้งซ่อมเรียบร้อยแล้วค่ะ!\n\n📌 เลขที่ใบงาน:\n$ticket_no\n💻 อุปกรณ์: $equipment\n📍 สถานที่: $location\n⚠️ ปัญหา: $problem\n\nระบบจะแจ้งเตือนให้ทราบเมื่อช่างเริ่มดำเนินการนะคะ";
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

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
                                    ['type' => 'text', 'text' => "ผู้แจ้ง: $user_name", 'wrap' => true, 'color' => '#888888'],
                                    ['type' => 'text', 'text' => "รายละเอียด: $problem", 'wrap' => true, 'color' => '#666666']
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
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "🚨 ข้อมูลไม่เข้าฐานข้อมูลค่ะ (SQL Error): " . $stmt->error], $channelAccessToken);
                }
            }
            else {
                $stmt_check_review = $conn->prepare("SELECT ticket_no FROM repairs WHERE line_user_id = ? AND status = 'ปิดงาน' AND rating IS NOT NULL AND review_comment IS NULL ORDER BY ticket_no DESC LIMIT 1");
                
                if ($stmt_check_review) {
                    $stmt_check_review->bind_param("s", $userId);
                    $stmt_check_review->execute();
                    $recent_job = $stmt_check_review->get_result()->fetch_assoc();

                    if ($recent_job) {
                        $stmt_update_review = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE ticket_no = ?");
                        $stmt_update_review->bind_param("ss", $text, $recent_job['ticket_no']);
                        $stmt_update_review->execute();
                        
                        send_reply($replyToken, ['type' => 'text', 'text' => "ขอบคุณสำหรับข้อเสนอแนะค่ะ 🙏 ระบบบันทึกข้อมูลเรียบร้อยแล้ว"], $channelAccessToken);
                    }
                }
            }
        }
        elseif ($event['type'] == 'postback') {
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                if ($postbackData['action'] == 'accept') {
                    $stmt_check = $conn->prepare("SELECT status, line_user_id FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job && $job['status'] == 'รอรับเรื่อง') {
                        $tech_name = get_line_profile($userId, null, $channelAccessToken);

                        $stmt = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ', technician_name = ? WHERE ticket_no = ?");
                        $stmt->bind_param("ss", $tech_name, $ticket_no);
                        $stmt->execute();

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
                        
                        $pushMsgToUser = ['type' => 'text', 'text' => "👨‍🔧 ช่าง $tech_name รับงานซ่อมของคุณแล้วนะคะ!\nช่างกำลังเตรียมตัวเข้าไปดำเนินการแก้ไขให้ค่ะ รบกวนรอสักครู่นะคะ 🛠️"];
                        send_push($job['line_user_id'], $pushMsgToUser, $channelAccessToken);
                    }
                }
                elseif ($postbackData['action'] == 'close') {
                    $stmt_check = $conn->prepare("SELECT status, line_user_id FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job && $job['status'] == 'กำลังดำเนินการ') {
                        $stmt = $conn->prepare("UPDATE repairs SET status = 'ปิดงาน' WHERE ticket_no = ?");
                        $stmt->bind_param("s", $ticket_no);
                        $stmt->execute();

                        send_reply($replyToken, ['type' => 'text', 'text' => "🎉 ปิดงาน $ticket_no สำเร็จ ส่งแบบประเมินให้ผู้แจ้งแล้วค่ะ"], $channelAccessToken);

                        $review_msg = [
                            'type' => 'flex',
                            'altText' => 'ประเมินผลการซ่อม',
                            'contents' => [
                                'type' => 'bubble',
                                'body' => [
                                    'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
                                    'contents' => [
                                        ['type' => 'text', 'text' => '⭐ ประเมินผลการซ่อม', 'weight' => 'bold', 'color' => '#ffb700', 'size' => 'lg'],
                                        ['type' => 'text', 'text' => 'งานซ่อมของคุณเสร็จเรียบร้อยแล้ว!', 'weight' => 'bold', 'wrap' => true],
                                        ['type' => 'text', 'text' => 'รบกวนให้คะแนนช่างเพื่อเป็นกำลังใจด้วยนะคะ', 'color' => '#aaaaaa', 'size' => 'sm', 'wrap' => true]
                                    ]
                                ]
                            ],
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
                elseif ($postbackData['action'] == 'rate') {
                    $score = $postbackData['score'];
                    $stmt = $conn->prepare("UPDATE repairs SET rating = ? WHERE ticket_no = ?");
                    $stmt->bind_param("is", $score, $ticket_no);
                    
                    if($stmt->execute()){
                        send_reply($replyToken, ['type' => 'text', 'text' => "💖 ขอบคุณสำหรับคะแนน $score ดาว ค่ะ!\nหากมีข้อเสนอแนะเพิ่มเติม สามารถพิมพ์ตอบกลับมาในแชทนี้ได้เลยนะคะ (ถ้าไม่มี ปิดแชทได้เลยค่ะ)"], $channelAccessToken);
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
    return isset($data['displayName']) ? $data['displayName'] : 'เจ้าหน้าที่/ผู้ใช้งาน';
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