<?php
require 'db_connect.php';
require 'env.php';

$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

function extract_repair_info($text) {
    $equipment = "ไม่ระบุอุปกรณ์";
    $location = "ไม่ระบุสถานที่";
    
    $keywords = [
        'แอร์', 'คอม', 'เครื่องปริ้น', 'printer', 'projector', 'เครื่องฉาย', 
        'จอ', 'ทีวี', 'ไมค์', 'หลอดไฟ', 'ไฟดับ', 'สายไฟ', 'ปลั๊ก', 'ไฟ', 'หลอด', 'พัดลม', 'เน็ต', 
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
    
    preg_match('/(ห้อง\s*[a-zA-Z0-9]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+|ชั้น\s*[0-9]+)/iu', $text, $matches);
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

            if (mb_strpos($text, 'ลงทะเบียนช่าง') === 0) {
                $raw_data = trim(mb_substr($text, 13)); 
                
                preg_match('/[0-9]{9,10}$/', $raw_data, $phone_matches);
                $phone = !empty($phone_matches[0]) ? $phone_matches[0] : '';
                $full_name = trim(str_replace($phone, '', $raw_data));

                if(empty($full_name) || empty($phone)) {
                    send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ รูปแบบไม่ถูกต้องค่ะ\nกรุณาพิมพ์: ลงทะเบียนช่าง [ชื่อ-นามสกุล] [เบอร์โทร]\nเช่น: ลงทะเบียนช่าง สมชาย ใจงาม 0812345678"], $channelAccessToken);
                } else {
                    $stmt_check = $conn->prepare("SELECT approval_status FROM technicians WHERE line_user_id = ?");
                    $stmt_check->bind_param("s", $userId);
                    $stmt_check->execute();
                    $res = $stmt_check->get_result()->fetch_assoc();

                    if($res) {
                        if($res['approval_status'] == 'รออนุมัติ') {
                            $msg = "⏳ ข้อมูลของคุณอยู่ในระบบแล้ว กรุณารอแอดมินตรวจสอบและอนุมัติค่ะ";
                        } else {
                            $msg = "✅ บัญชีนี้ได้รับการอนุมัติเรียบร้อยแล้ว คุณสามารถรับงานได้เลยค่ะ";
                        }
                    } else {
                        $stmt_insert = $conn->prepare("INSERT INTO technicians (line_user_id, full_name, phone) VALUES (?, ?, ?)");
                        $stmt_insert->bind_param("sss", $userId, $full_name, $phone);
                        if($stmt_insert->execute()) {
                            $msg = "📝 ส่งข้อมูลลงทะเบียนเรียบร้อย!\nชื่อ: $full_name\nเบอร์: $phone\n\nกรุณารอแอดมินตรวจสอบและอนุมัติในระบบหลังบ้านสักครู่นะคะ ⏳";
                        } else {
                            $msg = "🚨 เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_insert->error;
                        }
                    }
                    send_reply($replyToken, ['type' => 'text', 'text' => $msg], $channelAccessToken);
                }
                continue; 
            }

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

            if ($equipment !== "ไม่ระบุอุปกรณ์") {
                
                $user_name = get_line_profile($userId, null, $channelAccessToken);
                $ticket_no = "MR-" . rand(1000, 9999);
                $status = "รอรับเรื่อง"; 
                $phone_number = "ไม่ระบุ";
                
                $words_to_remove = [
                    'แอร์', 'คอม', 'เครื่องปริ้น', 'printer', 'projector', 'เครื่องฉาย', 
                    'จอ', 'ทีวี', 'ไมค์', 'หลอดไฟ', 'ไฟดับ', 'สายไฟ', 'ปลั๊ก', 'ไฟ', 'หลอด', 'พัดลม', 'เน็ต', 
                    'เว็บคณะ', 'มคอ', 'ประตู', 'สแกนหน้า', 'ท่อ', 'ห้องน้ำ', 'ก๊อก', 
                    'ตู้กดน้ำ', 'จิ้งจก', 'นก', 'ตุ๊กแก', 'หนู', 'กลิ่นเหม็น',
                    $location, 'ค่ะ', 'ครับ', 'คะ', 'คับ', 'มัน', 'รบกวน', 'ด่วน', 'แจ้งซ่อม', 'นึง'
                ];
                
                $problem = str_replace($words_to_remove, '', $text);
                $problem = trim($problem); 
                
                if (empty($problem)) {
                    $problem = "มีความผิดปกติ (รอตรวจสอบ)";
                }

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
                $stmt_check_review = $conn->prepare("SELECT ticket_no, review_comment FROM repairs WHERE line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว' ORDER BY ticket_no DESC LIMIT 1");
                
                if ($stmt_check_review) {
                    $stmt_check_review->bind_param("s", $userId);
                    $stmt_check_review->execute();
                    $recent_job = $stmt_check_review->get_result()->fetch_assoc();

                    if ($recent_job) {
                        $current_rev = (string)$recent_job['review_comment'];
                        $new_rev = trim($current_rev . " " . $text);
                        
                        $stmt_update_review = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE ticket_no = ?");
                        $stmt_update_review->bind_param("ss", $new_rev, $recent_job['ticket_no']);
                        $stmt_update_review->execute();
                        
                        send_reply($replyToken, ['type' => 'text', 'text' => "บันทึกรีวิวเพิ่มเติมเรียบร้อยค่ะ ขอบคุณมากนะคะ 🙏✨"], $channelAccessToken);
                    }
                }
            }
        }
        elseif ($event['type'] == 'postback') {
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                if ($postbackData['action'] == 'accept') {
                    $stmt_check = $conn->prepare("SELECT status, line_user_id, technician_name, equipment_type, location FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job) {
                        if ($job['status'] == 'รอรับเรื่อง') {
                            $stmt_tech = $conn->prepare("SELECT full_name FROM technicians WHERE line_user_id = ? AND approval_status = 'อนุมัติแล้ว'");
                            $stmt_tech->bind_param("s", $userId);
                            $stmt_tech->execute();
                            $tech_result = $stmt_tech->get_result()->fetch_assoc();

                            if ($tech_result) {
                                $tech_name = $tech_result['full_name'];
                            } else {
                                send_push($userId, ['type' => 'text', 'text' => "⚠️ คุณยังไม่มีสิทธิ์รับงานค่ะ กรุณาลงทะเบียนช่าง หรือรอแอดมินอนุมัติบัญชีของคุณก่อนนะคะ"], $channelAccessToken);
                                continue;
                            }

                            $stmt = $conn->prepare("UPDATE repairs SET status = 'ช่างรับเรื่องแจ้งซ่อมแล้ว', technician_name = ? WHERE ticket_no = ?");
                            $stmt->bind_param("ss", $tech_name, $ticket_no);
                            $stmt->execute();

                            $replyMsg = [
                                'type' => 'flex',
                                'altText' => 'อัปเดตสถานะงาน',
                                'contents' => [
                                    'type' => 'bubble',
                                    'body' => [
                                        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                        'contents' => [
                                            ['type' => 'text', 'text' => "✅ ช่าง $tech_name รับงานแล้ว", 'weight' => 'bold', 'color' => '#10b981', 'size' => 'md'],
                                            ['type' => 'text', 'text' => "ใบงาน: $ticket_no", 'size' => 'sm', 'color' => '#666666'],
                                            ['type' => 'text', 'text' => "อุปกรณ์: ".$job['equipment_type']." | สถานที่: ".$job['location'], 'size' => 'sm', 'color' => '#888888'],
                                            ['type' => 'text', 'text' => "สถานะ: ช่างรับเรื่องแจ้งซ่อมแล้ว", 'weight' => 'bold', 'color' => '#3b82f6', 'size' => 'sm']
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
                            
                            send_push($line_group_id, $replyMsg, $channelAccessToken);
                            
                            $pushMsgToUser = ['type' => 'text', 'text' => "👨‍🔧 ช่าง $tech_name รับงานซ่อมของคุณแล้วนะคะ!\nช่างกำลังเตรียมตัวเข้าไปดำเนินการแก้ไขให้ค่ะ รบกวนรอสักครู่นะคะ 🛠️"];
                            send_push($job['line_user_id'], $pushMsgToUser, $channelAccessToken);
                        } else {
                            $taken_by = !empty($job['technician_name']) ? $job['technician_name'] : "ช่างท่านอื่น";
                            send_push($userId, ['type' => 'text', 'text' => "⚠️ ไม่สามารถรับงานได้ค่ะ เนื่องจากงานนี้ถูกรับไปแล้วโดยช่าง $taken_by"], $channelAccessToken);
                        }
                    }
                }
                elseif ($postbackData['action'] == 'close') {
                    $stmt_check = $conn->prepare("SELECT status, line_user_id, technician_name FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job) {
                        if ($job['status'] == 'ช่างรับเรื่องแจ้งซ่อมแล้ว') {
                            
                            $stmt_tech = $conn->prepare("SELECT full_name FROM technicians WHERE line_user_id = ? AND approval_status = 'อนุมัติแล้ว'");
                            $stmt_tech->bind_param("s", $userId);
                            $stmt_tech->execute();
                            $tech_result = $stmt_tech->get_result()->fetch_assoc();

                            if ($tech_result) {
                                $clicker_name = $tech_result['full_name'];
                                
                                if ($clicker_name === $job['technician_name']) {
                                    
                                    $stmt = $conn->prepare("UPDATE repairs SET status = 'ซ่อมเสร็จแล้ว' WHERE ticket_no = ?");
                                    $stmt->bind_param("s", $ticket_no);
                                    $stmt->execute();

                                    send_push($line_group_id, ['type' => 'text', 'text' => "🎉 ใบงาน $ticket_no : ช่าง $clicker_name บันทึกการซ่อมเสร็จสิ้น ระบบส่งแบบประเมินให้ผู้แจ้งแล้วค่ะ"], $channelAccessToken);

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
                                                    ['type' => 'separator', 'margin' => 'md'],
                                                    ['type' => 'text', 'text' => '1️⃣ ให้คะแนนดาว (กดเปลี่ยนได้)', 'size' => 'sm', 'color' => '#aaaaaa', 'margin' => 'md'],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'primary', 'color' => '#fbbf24', 'action' => ['type' => 'postback', 'label' => '5 ดาว', 'data' => "action=rate&score=5&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'action' => ['type' => 'postback', 'label' => '4 ดาว', 'data' => "action=rate&score=4&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'action' => ['type' => 'postback', 'label' => '3 ดาว', 'data' => "action=rate&score=3&ticket=$ticket_no"]]
                                                        ]
                                                    ],
                                                    ['type' => 'separator', 'margin' => 'md'],
                                                    ['type' => 'text', 'text' => '2️⃣ เลือกคำชม (กดได้หลายข้อ)', 'size' => 'sm', 'color' => '#aaaaaa', 'margin' => 'md'],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm', 'margin' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '👍 บริการดีเยี่ยม', 'data' => "action=add_tag&tag=บริการดีเยี่ยม&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '⏱️ ตรงต่อเวลา', 'data' => "action=add_tag&tag=ตรงต่อเวลา&ticket=$ticket_no"]]
                                                        ]
                                                    ],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm', 'margin' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '✨ สะอาดเรียบร้อย', 'data' => "action=add_tag&tag=สะอาดเรียบร้อย&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '🗣️ พูดจาสุภาพ', 'data' => "action=add_tag&tag=พูดจาสุภาพ&ticket=$ticket_no"]]
                                                        ]
                                                    ],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm', 'margin' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '🛠️ ซ่อมเร็วทันใจ', 'data' => "action=add_tag&tag=ซ่อมเร็วทันใจ&ticket=$ticket_no"]]
                                                        ]
                                                    ],
                                                    ['type' => 'text', 'text' => '*หรือพิมพ์ข้อความรีวิวส่งมาในแชทได้เลยค่ะ', 'size' => 'xs', 'color' => '#bbbbbb', 'margin' => 'md', 'wrap' => true]
                                                ]
                                            ]
                                        ]
                                    ];
                                    send_push($job['line_user_id'], $review_msg, $channelAccessToken);
                                    
                                } else {
                                    send_push($userId, ['type' => 'text', 'text' => "⚠️ คุณไม่สามารถแจ้งปิดงานนี้ได้ค่ะ เนื่องจากช่าง ".$job['technician_name']." เป็นผู้รับผิดชอบ"], $channelAccessToken);
                                }
                            } else {
                                send_push($userId, ['type' => 'text', 'text' => "⚠️ คุณไม่มีสิทธิ์ทำรายการนี้ค่ะ"], $channelAccessToken);
                            }
                            
                        } else if ($job['status'] == 'ซ่อมเสร็จแล้ว') {
                             send_push($userId, ['type' => 'text', 'text' => "✅ ใบงาน $ticket_no นี้ถูกแจ้งปิดงานไปเรียบร้อยแล้วค่ะ"], $channelAccessToken);
                        }
                    }
                }
                elseif ($postbackData['action'] == 'rate') {
                    $score = $postbackData['score'];
                    $stmt = $conn->prepare("UPDATE repairs SET rating = ? WHERE ticket_no = ?");
                    $stmt->bind_param("is", $score, $ticket_no);
                    
                    if($stmt->execute()){
                        send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกคะแนน $score ดาว เรียบร้อยแล้วค่ะ"], $channelAccessToken);
                    }
                }
                elseif ($postbackData['action'] == 'add_tag') {
                    $tag = $postbackData['tag'];
                    
                    $stmt_check = $conn->prepare("SELECT review_comment FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job_rev = $stmt_check->get_result()->fetch_assoc();
                    $current_rev = $job_rev ? (string)$job_rev['review_comment'] : "";

                    if (mb_strpos($current_rev, $tag) === false) {
                        $new_rev = trim($current_rev . " [" . $tag . "]");
                        $stmt_upd = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE ticket_no = ?");
                        $stmt_upd->bind_param("ss", $new_rev, $ticket_no);
                        
                        if($stmt_upd->execute()){
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ เพิ่มคำชม: $tag"], $channelAccessToken);
                        }
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "คุณได้เลือกคำชม '$tag' ไปแล้วค่ะ 💖"], $channelAccessToken);
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