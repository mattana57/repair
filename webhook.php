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
                $replyMsg = ['type' => 'text', 'text' => "แอดมินรับทราบแล้วค่ะ 🙋‍♀️\n\nรบกวนพิมพ์รายละเอียดปัญหา หรือข้อสงสัยทิ้งไว้ได้เลยนะคะ เจ้าหน้าที่จะรีบเข้ามาตรวจสอบและตอบกลับให้เร็วที่สุดค่ะ"];
                send_reply($replyToken, $replyMsg, $channelAccessToken);
            }
            else {
                // ----------------------------------------------------
                // *** เพิ่มใหม่: ดักคำขอบคุณ/รับทราบ เพื่อป้องกัน AI สร้างใบงานขยะ ***
                // ----------------------------------------------------
                // ตัดคำลงท้ายและช่องว่างทิ้ง เพื่อให้เช็คคำหลักได้แม่นยำขึ้น
                $clean_text = str_replace([' ', 'ครับ', 'ค่ะ', 'คับ', 'จ้า', 'นะคะ', 'นะ', 'พี่'], '', mb_strtolower($text, 'UTF-8'));
                $greeting_words = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'เค', 'รับทราบ', 'รับแซ่บ', 'เยี่ยม', 'ดีมาก', 'thankyou', 'thanks'];
                
                // ถ้าข้อความที่พิมพ์มา เป็นแค่คำขอบคุณ บอทจะตอบกลับและจบการทำงานทันที
                if (in_array($clean_text, $greeting_words)) {
                    $replyMsg = ['type' => 'text', 'text' => "ด้วยความยินดีค่ะ 💖 ทีมงานพร้อมดูแลเสมอ หากมีปัญหาเพิ่มเติมแจ้งบอทเข้ามาได้ตลอดเลยนะคะ 🛠️"];
                    send_reply($replyToken, $replyMsg, $channelAccessToken);
                } 
                // ----------------------------------------------------
                // แต่ถ้าไม่ใช่คำขอบคุณ -> ส่งให้ AI ตีความแจ้งซ่อมตามปกติ
                // ----------------------------------------------------
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
                                                'action' => ['type' => 'postback', 'label' => '✅ ถูกต้อง', 'data' => "action=confirm&ticket=$ticket_no", 'displayText' => '✅ ข้อมูลถูกต้อง ดำเนินการต่อได้เลย']
                                            ],
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm',
                                                'action' => ['type' => 'postback', 'label' => '❌ พิมพ์ใหม่', 'data' => "action=cancel&ticket=$ticket_no", 'displayText' => '❌ ข้อมูลผิด (ต้องการพิมพ์แจ้งใหม่)']
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                        }
                    }
                    send_reply($replyToken, $messageData, $channelAccessToken);
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
                        $replyText = "✅ ยืนยันข้อมูลเรียบร้อย รอช่างรับงานนะคะ 🛠️";
                        send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

                        $stmt_info = $conn->prepare("SELECT equipment_type, location, problem_desc FROM repairs WHERE ticket_no = ?");
                        $stmt_info->bind_param("s", $ticket_no);
                        $stmt_info->execute();
                        $job = $stmt_info->get_result()->fetch_assoc();

                        if($job && !empty($line_group_id)) {
                            $pushMsg = [
                                'type' => 'flex',
                                'altText' => '🚨 มีงานแจ้งซ่อมใหม่!',
                                'contents' => [
                                    'type' => 'bubble',
                                    'body' => [
                                        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                        'contents' => [
                                            ['type' => 'text', 'text' => '🚨 มีงานแจ้งซ่อมใหม่!', 'weight' => 'bold', 'color' => '#ef4444', 'size' => 'lg'],
                                            ['type' => 'text', 'text' => 'ใบงาน: ' . $ticket_no, 'size' => 'sm', 'color' => '#aaaaaa'],
                                            ['type' => 'text', 'text' => 'ซ่อม: ' . $job['equipment_type'] . ' (' . $job['location'] . ')', 'wrap' => true],
                                            ['type' => 'text', 'text' => 'อาการ: ' . $job['problem_desc'], 'wrap' => true, 'color' => '#ef4444']
                                        ]
                                    ],
                                    'footer' => [
                                        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                        'contents' => [
                                            ['type' => 'button', 'style' => 'primary', 'color' => '#3b82f6',
                                                'action' => ['type' => 'postback', 'label' => '👨‍🔧 ช่างต้อม รับงาน', 'data' => "action=accept&ticket=$ticket_no&tech=ช่างต้อม"]
                                            ],
                                            ['type' => 'button', 'style' => 'primary', 'color' => '#10b981',
                                                'action' => ['type' => 'postback', 'label' => '👨‍🔧 ช่างเอ รับงาน', 'data' => "action=accept&ticket=$ticket_no&tech=ช่างเอ"]
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
                    $replyText = "🗑️ ยกเลิกข้อมูลเดิมเรียบร้อยแล้วค่ะ\n\nรบกวนพิมพ์แจ้งอุปกรณ์และปัญหาเข้ามาใหม่อีกครั้งได้เลยนะคะ 🛠️";
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);
                }
                // 3. ช่างกดปุ่มรับงานในกลุ่ม
                elseif ($postbackData['action'] == 'accept') {
                    $tech_name = isset($postbackData['tech']) ? $postbackData['tech'] : 'ช่าง';
                    
                    $stmt = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    $stmt->execute();

                    // ตอบกลับในกลุ่มช่างพร้อมปุ่มปิดงาน
                    $messageData = [
                        'type' => 'flex',
                        'altText' => "$tech_name รับงานแล้ว",
                        'contents' => [
                            'type' => 'bubble',
                            'body' => [
                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => "✅ $tech_name รับงานแล้ว!", 'weight' => 'bold', 'color' => '#10b981'],
                                    ['type' => 'text', 'text' => "ใบงาน: $ticket_no\nเมื่อดำเนินการเสร็จสิ้น รบกวนกดปุ่มด้านล่างเพื่อปิดงานและส่งแบบประเมินให้ลูกค้านะคะ", 'wrap' => true, 'size' => 'sm']
                                ]
                            ],
                            'footer' => [
                                'type' => 'box', 'layout' => 'vertical',
                                'contents' => [
                                    ['type' => 'button', 'style' => 'primary', 'color' => '#ef4444', 'action' => ['type' => 'postback', 'label' => '🔒 ปิดงานซ่อม', 'data' => "action=close&ticket=$ticket_no"]]
                                ]
                            ]
                        ]
                    ];
                    send_reply($replyToken, $messageData, $channelAccessToken);

                    // แจ้งเตือนกลับไปหาผู้แจ้งซ่อม
                    $stmt_user = $conn->prepare("SELECT line_user_id, equipment_type FROM repairs WHERE ticket_no = ?");
                    $stmt_user->bind_param("s", $ticket_no);
                    $stmt_user->execute();
                    $user_result = $stmt_user->get_result()->fetch_assoc();

                    if($user_result && !empty($user_result['line_user_id'])) {
                        $pushMsgToUser = ['type' => 'text', 'text' => "👨‍🔧 $tech_name รับงานซ่อมของคุณแล้วนะคะ!\nช่างกำลังเตรียมตัวเข้าไปดำเนินการแก้ไขให้ค่ะ รบกวนรอสักครู่นะคะ 🛠️"];
                        send_push($user_result['line_user_id'], $pushMsgToUser, $channelAccessToken);
                    }
                }
                // 4. ช่างกดปุ่ม "ปิดงานซ่อม"
                elseif ($postbackData['action'] == 'close') {
                    $stmt = $conn->prepare("UPDATE repairs SET status = 'รอการรีวิว' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    $stmt->execute();

                    // แจ้งในกลุ่มว่าปิดงานแล้ว
                    send_reply($replyToken, ['type' => 'text', 'text' => "🔒 ปิดงาน $ticket_no เรียบร้อย! ระบบกำลังส่งแบบประเมินให้ลูกค้าครับ"], $channelAccessToken);

                    // ส่ง Flex ประเมินให้ลูกค้า
                    $stmt_user = $conn->prepare("SELECT line_user_id FROM repairs WHERE ticket_no = ?");
                    $stmt_user->bind_param("s", $ticket_no);
                    $stmt_user->execute();
                    $user_result = $stmt_user->get_result()->fetch_assoc();

                    if($user_result && !empty($user_result['line_user_id'])) {
                        $pushMsg = [
                            'type' => 'flex',
                            'altText' => 'รบกวนประเมินความพึงพอใจ',
                            'contents' => [
                                'type' => 'bubble',
                                'header' => [
                                    'type' => 'box', 'layout' => 'vertical',
                                    'contents' => [['type' => 'text', 'text' => '⭐️ ประเมินผลการซ่อม', 'weight' => 'bold', 'color' => '#f59e0b', 'size' => 'lg']]
                                ],
                                'body' => [
                                    'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                    'contents' => [
                                        ['type' => 'text', 'text' => 'งานซ่อมของคุณเสร็จเรียบร้อยแล้ว!', 'wrap' => true],
                                        ['type' => 'text', 'text' => 'รบกวนให้คะแนนช่างเพื่อเป็นกำลังใจด้วยนะคะ', 'wrap' => true, 'size' => 'sm', 'color' => '#aaaaaa']
                                    ]
                                ],
                                'footer' => [
                                    'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                    'contents' => [
                                        ['type' => 'button', 'style' => 'primary', 'color' => '#f59e0b', 'action' => ['type' => 'postback', 'label' => '⭐⭐⭐⭐⭐ ดีมาก', 'data' => "action=rate&ticket=$ticket_no&score=5", 'displayText' => 'ให้ 5 ดาว ⭐⭐⭐⭐⭐']],
                                        ['type' => 'button', 'style' => 'secondary', 'action' => ['type' => 'postback', 'label' => '⭐⭐⭐⭐ ดี', 'data' => "action=rate&ticket=$ticket_no&score=4", 'displayText' => 'ให้ 4 ดาว ⭐⭐⭐⭐']],
                                        ['type' => 'button', 'style' => 'secondary', 'action' => ['type' => 'postback', 'label' => '⭐⭐⭐ ปานกลาง', 'data' => "action=rate&ticket=$ticket_no&score=3", 'displayText' => 'ให้ 3 ดาว ⭐⭐⭐']]
                                    ]
                                ]
                            ]
                        ];
                        send_push($user_result['line_user_id'], $pushMsg, $channelAccessToken);
                    }
                }
                // 5. ลูกค้ากดให้คะแนนดาว
                elseif ($postbackData['action'] == 'rate') {
                    $score = $postbackData['score'];
                    $new_status = "เสร็จสิ้น ($score ดาว)"; 
                    
                    $stmt = $conn->prepare("UPDATE repairs SET status = ? WHERE ticket_no = ?");
                    $stmt->bind_param("ss", $new_status, $ticket_no);
                    $stmt->execute();
                    
                    $replyText = "💖 ขอบคุณสำหรับคะแนน $score ดาวนะคะ!\n\nหวังว่าคุณลูกค้าจะประทับใจในบริการของเราค่ะ หากมีปัญหาเพิ่มเติมแจ้งบอทได้ตลอดเลยนะคะ";
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);
                    
                    if (!empty($line_group_id)) {
                        send_push($line_group_id, ['type' => 'text', 'text' => "🎉 ใบงาน $ticket_no ได้รับคะแนนรีวิว $score ดาวจากลูกค้าครับ! เก่งมากๆ 👏"], $channelAccessToken);
                    }
                }
            }
        }
    }
}
echo "OK";

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