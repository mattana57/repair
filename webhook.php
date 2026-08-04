<?php
require 'db_connect.php';
require 'env.php';

// ========================================================
// 1. ตั้งค่า API Keys และข้อมูลกลุ่มช่าง
// ========================================================
$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

// ========================================================
// ฟังก์ชันสกัดคำ (Rule-based) ทำงานแทน AI
// ========================================================
function extract_repair_info($text) {
    $equipment = "ไม่ระบุอุปกรณ์ (รอช่างตรวจสอบ)";
    $location = "ไม่ระบุสถานที่";
    
    // คลังคำศัพท์ที่จัดหมวดหมู่แล้ว
    $keyword_map = [
        'แอร์' => 'เครื่องปรับอากาศ',
        'คอม' => 'คอมพิวเตอร์',
        'เครื่องปริ้น' => 'ปริ้นเตอร์',
        'printer' => 'ปริ้นเตอร์',
        'projector' => 'โปรเจคเตอร์',
        'เครื่องฉาย' => 'โปรเจคเตอร์',
        'จอ' => 'จอมอนิเตอร์/ทีวี',
        'ทีวี' => 'จอมอนิเตอร์/ทีวี',
        'ไมค์' => 'ระบบเสียง/ไมโครโฟน',
        'หลอดไฟ' => 'ระบบไฟฟ้า (หลอดไฟ)',
        'ไฟดับ' => 'ระบบไฟฟ้า (ไฟดับ)',
        'สายไฟ' => 'ระบบไฟฟ้า (สายไฟ)',
        'ปลั๊ก' => 'ระบบไฟฟ้า (ปลั๊ก)',
        'เน็ต' => 'ระบบเครือข่ายอินเทอร์เน็ต',
        'เว็บคณะ' => 'ระบบสารสนเทศ (เว็บคณะ)',
        'มคอ' => 'ระบบสารสนเทศ (มคอ.)',
        'ประตู' => 'อาคารสถานที่ (ประตู)',
        'สแกนหน้า' => 'เครื่องสแกนหน้า',
        'ท่อ' => 'ระบบประปา (ท่อ)',
        'ห้องน้ำ' => 'สุขภัณฑ์/ห้องน้ำ',
        'ก๊อก' => 'ระบบประปา (ก๊อกน้ำ)',
        'ตู้กดน้ำ' => 'ตู้กดน้ำดื่ม',
        'จิ้งจก' => 'งานแม่บ้าน/กำจัดสัตว์รบกวน',
        'นก' => 'งานแม่บ้าน/กำจัดสัตว์รบกวน',
        'ตุ๊กแก' => 'งานแม่บ้าน/กำจัดสัตว์รบกวน',
        'หนู' => 'งานแม่บ้าน/กำจัดสัตว์รบกวน',
        'กลิ่นเหม็น' => 'งานแม่บ้าน (ทำความสะอาด)'
    ];

    $text_lower = mb_strtolower($text, 'UTF-8');
    
    foreach ($keyword_map as $keyword => $category) {
        if (mb_strpos($text_lower, $keyword) !== false) {
            $equipment = $category;
            break;
        }
    }
    
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
            $message_id = $event['message']['id']; // 🛠️ ดึง message_id กลับมาใช้งาน

            list($equipment, $location) = extract_repair_info($text);

            // ถ้าเจอคำว่าแจ้งซ่อม หรือ พบคำศัพท์อุปกรณ์ ให้ทำงาน
            if (mb_strpos($text, 'แจ้งซ่อม') !== false || $equipment !== "ไม่ระบุอุปกรณ์ (รอช่างตรวจสอบ)") {
                
                $user_name = get_line_profile($userId, null, $channelAccessToken);
                $ticket_no = "MR-" . date("Ymd-His");
                $status = "รอรับเรื่อง"; 
                $phone_number = "ไม่ระบุ";
                $problem = $text; 

                // 🛠️ บันทึกลงฐานข้อมูล (ใส่ line_message_id กลับเข้าไปแล้ว)
                $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id, line_message_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssss", $ticket_no, $equipment, $location, $problem, $status, $user_name, $phone_number, $userId, $message_id);
                
                if($stmt->execute()) {
                    // ตอบกลับผู้ใช้
                    $replyMsg = ['type' => 'text', 'text' => "📝 รับเรื่องแจ้งซ่อมเรียบร้อยค่ะ\nหมวดหมู่: $equipment\nสถานที่: $location\n\nระบบกำลังประสานงานให้ รอสักครู่นะคะ 🔎"];
                    send_reply($replyToken, $replyMsg, $channelAccessToken);

                    // ส่ง Flex ไปกลุ่มช่าง
                    $pushMsg = [
                        'type' => 'flex',
                        'altText' => '🚨 มีงานแจ้งซ่อมใหม่',
                        'contents' => [
                            'type' => 'bubble',
                            'body' => [
                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => '🚨 มีงานแจ้งซ่อมใหม่!', 'weight' => 'bold', 'color' => '#ef4444', 'size' => 'md'],
                                    ['type' => 'text', 'text' => "หมวดหมู่: $equipment", 'wrap' => true],
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
                    // 🚨 ไม้ตายดักจับ Error: ถ้าฐานข้อมูลพัง บอทจะพิมพ์บอกทันที
                    $error_msg = "🚨 ข้อมูลไม่เข้าฐานข้อมูลค่ะ (SQL Error): " . $stmt->error;
                    send_reply($replyToken, ['type' => 'text', 'text' => $error_msg], $channelAccessToken);
                }
            }
            // ระบบรอรับข้อความรีวิวแบบพิมพ์
            else {
                // 🛠️ เปลี่ยนการเรียงลำดับให้ใช้ ticket_no แทน เพื่อป้องกัน Error หากไม่มีคอลัมน์ created_at
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
        
        // ========================================================
        // ส่วนที่ B: Postback Event (ช่างรับงาน / ปิดงาน / รีวิว)
        // ========================================================
        elseif ($event['type'] == 'postback') {
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                // 1. ช่างกดรับงาน
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
                        send_push($job['line_user_id'], ['type' => 'text', 'text' => "👨‍🔧 ช่าง $tech_name รับงานแล้ว\nและกำลังเดินทางไปดำเนินการค่ะ 🛵"], $channelAccessToken);
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ งานนี้มีผู้รับไปแล้ว หรือถูกปิดไปแล้วค่ะ"], $channelAccessToken);
                    }
                }
                
                // 2. ช่างกดปิดงาน
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

                // 3. ผู้ใช้กดให้คะแนนดาว
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