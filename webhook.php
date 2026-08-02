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
        
        // ========================================================
        // ส่วนที่ A: จัดการเมื่อผู้ใช้ "พิมพ์ข้อความ"
        // ========================================================
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $replyToken = $event['replyToken'];
            $userId = $event['source']['userId'];

            if ($text == 'ขอไอดีกลุ่ม') {
                $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : 'นี่ไม่ใช่กลุ่ม (เป็นแชทส่วนตัว)';
                $messageData = ['type' => 'text', 'text' => "Group ID ของกลุ่มนี้คือ:\n" . $groupId];
                send_reply($replyToken, $messageData, $channelAccessToken);
            } 
            elseif ($text == 'ติดต่อแอดมิน') {
                $replyMsg = ['type' => 'text', 'text' => "แอดมินรับทราบแล้วค่ะ 🙋‍♀️\nรบกวนพิมพ์รายละเอียดปัญหาทิ้งไว้ได้เลยนะคะ"];
                send_reply($replyToken, $replyMsg, $channelAccessToken);
            }
            else {
                // ดักคำขอบคุณ/รับทราบ
                $text_clean = mb_strtolower(str_replace([' ', "\n", 'ค่ะ', 'ครับ', 'จ้า', 'นะ', 'พี่'], '', $text), 'UTF-8');
                $greetings = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'เค', 'รับทราบ', 'เยี่ยม', 'แต้ง'];
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
                } 
                else {
                    $gemini_prompt = "ทำหน้าที่เป็นผู้ช่วยรับแจ้งซ่อม สกัดข้อมูลจากข้อความต่อไปนี้:\nข้อความ: '$text'\nให้ส่งกลับมาเป็น JSON format อย่างเดียว ห้ามมีข้อความอื่น โดยมี key ดังนี้:\n- equipment: ชื่ออุปกรณ์\n- building: ชื่อตึก\n- room: เลขห้อง\n- problem: อาการที่เสีย";

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
                        
                        if ($equipment == 'ไม่ระบุอุปกรณ์' && $problem == 'ไม่ระบุอาการ') {
                            // ไม่ทำอะไร
                        } else {
                            $ticket_no = "MR-" . date("Ymd-His");
                            $status = "รอยืนยัน"; 
                            $reporter_name = "แจ้งผ่านแชทบอท AI"; 
                            $phone_number = "ไม่ระบุ";

                            $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("ssssssss", $ticket_no, $equipment, $location, $problem, $status, $reporter_name, $phone_number, $userId);
                            
                            if($stmt->execute()) {
                                $messageData = [
                                    'type' => 'flex',
                                    'altText' => 'กรุณายืนยันข้อมูลแจ้งซ่อม',
                                    'contents' => [
                                        'type' => 'bubble',
                                        'header' => [
                                            'type' => 'box', 'layout' => 'vertical',
                                            'contents' => [['type' => 'text', 'text' => '📋 ตรวจสอบความถูกต้อง', 'weight' => 'bold', 'size' => 'lg', 'color' => '#1DB446']]
                                        ],
                                        'body' => [
                                            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
                                            'contents' => [
                                                ['type' => 'box', 'layout' => 'horizontal', 'contents' => [
                                                    ['type' => 'text', 'text' => 'อุปกรณ์:', 'color' => '#aaaaaa', 'size' => 'sm', 'flex' => 1],
                                                    ['type' => 'text', 'text' => $equipment, 'wrap' => true, 'color' => '#333333', 'size' => 'sm', 'flex' => 3]
                                                ]],
                                                ['type' => 'box', 'layout' => 'horizontal', 'contents' => [
                                                    ['type' => 'text', 'text' => 'สถานที่:', 'color' => '#aaaaaa', 'size' => 'sm', 'flex' => 1],
                                                    ['type' => 'text', 'text' => $location, 'wrap' => true, 'color' => '#333333', 'size' => 'sm', 'flex' => 3]
                                                ]],
                                                ['type' => 'box', 'layout' => 'horizontal', 'contents' => [
                                                    ['type' => 'text', 'text' => 'ปัญหา:', 'color' => '#aaaaaa', 'size' => 'sm', 'flex' => 1],
                                                    ['type' => 'text', 'text' => $problem, 'wrap' => true, 'color' => '#ef4444', 'size' => 'sm', 'flex' => 3]
                                                ]]
                                            ]
                                        ],
                                        'footer' => [
                                            'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                            'contents' => [
                                                ['type' => 'button', 'style' => 'primary', 'height' => 'sm', 'color' => '#1DB446',
                                                    'action' => ['type' => 'postback', 'label' => '✅ ถูกต้อง', 'data' => "action=confirm&ticket=$ticket_no", 'displayText' => '✅ ยืนยันข้อมูล']
                                                ],
                                                ['type' => 'button', 'style' => 'secondary', 'height' => 'sm',
                                                    'action' => ['type' => 'postback', 'label' => '❌ พิมพ์ใหม่', 'data' => "action=cancel&ticket=$ticket_no", 'displayText' => '❌ ยกเลิก (พิมพ์ใหม่)']
                                                ]
                                            ]
                                        ]
                                    ]
                                ];
                                send_reply($replyToken, $messageData, $channelAccessToken);
                            }
                        }
                    }
                }
            }
        }
        
        // ========================================================
        // ส่วนที่ B: จัดการเมื่อกดปุ่ม (Postback Event)
        // ========================================================
        elseif ($event['type'] == 'postback') {
            $replyToken = $event['replyToken'];
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                // 1. ผู้ใช้กดยืนยันข้อมูล
                if ($postbackData['action'] == 'confirm') {
                    $stmt = $conn->prepare("UPDATE repairs SET status = 'รอรับเรื่อง' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    if ($stmt->execute()) {
                        $replyText = "✅ ส่งเรื่องให้ช่างเรียบร้อยแล้วค่ะ";
                        send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

                        $stmt_info = $conn->prepare("SELECT equipment_type, location, problem_desc FROM repairs WHERE ticket_no = ?");
                        $stmt_info->bind_param("s", $ticket_no);
                        $stmt_info->execute();
                        $job = $stmt_info->get_result()->fetch_assoc();

                        if($job && !empty($line_group_id)) {
                            $pushMsg = [
                                'type' => 'flex',
                                'altText' => '🚨 งานซ่อมใหม่',
                                'contents' => [
                                    'type' => 'bubble',
                                    'body' => [
                                        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                        'contents' => [
                                            ['type' => 'text', 'text' => '🚨 มีงานแจ้งซ่อมใหม่!', 'weight' => 'bold', 'color' => '#ef4444', 'size' => 'md'],
                                            ['type' => 'text', 'text' => 'ซ่อม: ' . $job['equipment_type'] . ' (' . $job['location'] . ')', 'wrap' => true],
                                            ['type' => 'text', 'text' => 'อาการ: ' . $job['problem_desc'], 'wrap' => true, 'color' => '#ef4444']
                                        ]
                                    ],
                                    'footer' => [
                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                        'contents' => [
                                            ['type' => 'button', 'style' => 'primary', 'color' => '#3b82f6', 'height' => 'sm',
                                                'action' => ['type' => 'postback', 'label' => 'ช่างต้อม รับ', 'data' => "action=accept&ticket=$ticket_no&tech=ช่างต้อม"]
                                            ],
                                            ['type' => 'button', 'style' => 'primary', 'color' => '#10b981', 'height' => 'sm',
                                                'action' => ['type' => 'postback', 'label' => 'ช่างเอ รับ', 'data' => "action=accept&ticket=$ticket_no&tech=ช่างเอ"]
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                            send_push($line_group_id, $pushMsg, $channelAccessToken);
                        }
                    }
                } 
                // 2. ผู้ใช้กดยกเลิก/พิมพ์ใหม่
                elseif ($postbackData['action'] == 'cancel') {
                    $stmt = $conn->prepare("UPDATE repairs SET status = 'ยกเลิก' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    $stmt->execute();
                    $replyText = "🗑️ ยกเลิกข้อมูลเดิมแล้วค่ะ รบกวนพิมพ์แจ้งซ่อมใหม่ได้เลยค่ะ";
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);
                }
                // 3. ช่างกดปุ่มรับงานในกลุ่ม -> ตอบกลับผู้ใช้พร้อมแนบ Quick Reply ให้ดาว
                elseif ($postbackData['action'] == 'accept') {
                    $tech_name = isset($postbackData['tech']) ? $postbackData['tech'] : 'ช่าง';
                    
                    $stmt_info = $conn->prepare("SELECT equipment_type, location, line_user_id FROM repairs WHERE ticket_no = ?");
                    $stmt_info->bind_param("s", $ticket_no);
                    $stmt_info->execute();
                    $job = $stmt_info->get_result()->fetch_assoc();
                    
                    $equip = $job ? $job['equipment_type'] : 'อุปกรณ์';
                    $loc = $job ? $job['location'] : 'สถานที่';

                    $stmt = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    $stmt->execute();

                    // ตอบกลับในกลุ่มช่าง
                    $replyText = "✅ $tech_name รับงานแล้ว\nซ่อม: $equip ($loc)";
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

                    // แจ้งเตือนกลับไปหาผู้แจ้งซ่อม พร้อมปุ่ม Quick Reply ให้ดาว
                    if($job && !empty($job['line_user_id'])) {
                        $pushMsgToUser = [
                            'type' => 'text',
                            'text' => "👨‍🔧 $tech_name รับงานซ่อมของคุณแล้วนะคะ\n\n(หากดำเนินการเสร็จสิ้น รบกวนประเมินให้คะแนนช่างหน่อยนะคะ ⭐️)",
                            'quickReply' => [
                                'items' => [
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '5 ดาว ⭐️', 'data' => "action=rate&ticket=$ticket_no&score=5&tech=$tech_name", 'displayText' => 'ให้ 5 ดาว']],
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '4 ดาว ⭐️', 'data' => "action=rate&ticket=$ticket_no&score=4&tech=$tech_name", 'displayText' => 'ให้ 4 ดาว']],
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '3 ดาว ⭐️', 'data' => "action=rate&ticket=$ticket_no&score=3&tech=$tech_name", 'displayText' => 'ให้ 3 ดาว']],
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '2 ดาว ⭐️', 'data' => "action=rate&ticket=$ticket_no&score=2&tech=$tech_name", 'displayText' => 'ให้ 2 ดาว']],
                                    ['type' => 'action', 'action' => ['type' => 'postback', 'label' => '1 ดาว ⭐️', 'data' => "action=rate&ticket=$ticket_no&score=1&tech=$tech_name", 'displayText' => 'ให้ 1 ดาว']]
                                ]
                            ]
                        ];
                        send_push($job['line_user_id'], $pushMsgToUser, $channelAccessToken);
                    }
                }
                // 4. ผู้ใช้กดให้ดาว
                elseif ($postbackData['action'] == 'rate') {
                    $score = $postbackData['score'];
                    $tech_name = isset($postbackData['tech']) ? $postbackData['tech'] : 'ช่าง';
                    $new_status = "เสร็จสิ้น ($score ดาว)"; 
                    
                    $stmt = $conn->prepare("UPDATE repairs SET status = ? WHERE ticket_no = ?");
                    $stmt->bind_param("ss", $new_status, $ticket_no);
                    $stmt->execute();
                    
                    send_reply($replyToken, ['type' => 'text', 'text' => "💖 ขอบคุณสำหรับคะแนน $score ดาวนะคะ!"], $channelAccessToken);
                    
                    // แจ้งให้ช่างทราบในกลุ่ม
                    if (!empty($line_group_id)) {
                        send_push($line_group_id, ['type' => 'text', 'text' => "🎉 $tech_name ได้รับรีวิว $score ดาวจากงานซ่อม $ticket_no ครับ! 👏"], $channelAccessToken);
                    }
                }
            }
        }
    }
}
echo "OK";

// (ฟังก์ชัน send_reply และ send_push คงเดิม)
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