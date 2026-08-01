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

                if (!empty($line_group_id)) {
                    $pushMsg = ['type' => 'text', 'text' => "🚨 แจ้งเตือน: มีผู้ใช้ต้องการติดต่อเจ้าหน้าที่!\n\nรบกวนแอดมินเข้าไปตรวจสอบและตอบแชทผู้ใช้ในแอป LINE Official Account ด้วยนะคะ 💬"];
                    send_push($line_group_id, $pushMsg, $channelAccessToken);
                }
            }
            else {
                // ให้ AI สกัดข้อมูล
                $gemini_prompt = "ทำหน้าที่เป็นผู้ช่วยรับแจ้งซ่อม สกัดข้อมูลจากข้อความต่อไปนี้:\nข้อความ: '$text'\nให้ส่งกลับมาเป็น JSON format อย่างเดียว ห้ามมีข้อความอื่น โดยมี key ดังนี้:\n- equipment: ชื่ออุปกรณ์ (เช่น แอร์, คอมพิวเตอร์)\n- building: ชื่อตึก (เช่น SBB)\n- room: เลขห้อง\n- problem: อาการที่เสีย";

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
                $curl_err = curl_error($ch);
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
                    $status = "รอยืนยัน"; // <--- ตั้งเป็นรอยืนยัน
                    $reporter_name = "แจ้งผ่านแชทบอท AI"; 
                    $phone_number = "ไม่ระบุ";

                    $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssss", $ticket_no, $equipment, $location, $problem, $status, $reporter_name, $phone_number, $userId);
                    
                    if($stmt->execute()) {
                        // สร้างรูปแบบการ์ด Flex Message
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
                                            ['type' => 'text', 'text' => 'อุปกณ์:', 'color' => '#aaaaaa', 'size' => 'sm', 'flex' => 1],
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
                    } else {
                        $messageData = ['type' => 'text', 'text' => "🚨 เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: " . $stmt->error];
                    }
                } else {
                    $messageData = ['type' => 'text', 'text' => "🚨 พบข้อผิดพลาดจากระบบ:\n" . $gemini_response . "\nCURL Error: " . $curl_err];
                }
                send_reply($replyToken, $messageData, $channelAccessToken);
            }
        }
        
        // ========================================================
        // ส่วนที่ B: จัดการเมื่อผู้ใช้กดปุ่ม (Postback Event)
        // ========================================================
        elseif ($event['type'] == 'postback') {
            $replyToken = $event['replyToken'];
            parse_str($event['postback']['data'], $postbackData); // แยกข้อมูลที่ส่งมาจากปุ่ม

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                if ($postbackData['action'] == 'confirm') {
                    // อัปเดตสถานะเป็น "รอรับเรื่อง"
                    $stmt = $conn->prepare("UPDATE repairs SET status = 'รอรับเรื่อง' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    if ($stmt->execute()) {
                        $replyText = "✅ ยืนยันข้อมูลเรียบร้อยแล้วค่ะ!\n\n📌 เลขที่ใบงาน: $ticket_no\n\nระบบส่งเรื่องให้เจ้าหน้าที่แล้ว จะมีการแจ้งเตือนอีกครั้งเมื่อช่างเริ่มดำเนินการนะคะ";
                    } else {
                        $replyText = "🚨 เกิดข้อผิดพลาด: " . $stmt->error;
                    }
                } elseif ($postbackData['action'] == 'cancel') {
                    // เปลี่ยนสถานะเป็น "ยกเลิก"
                    $stmt = $conn->prepare("UPDATE repairs SET status = 'ยกเลิก' WHERE ticket_no = ?");
                    $stmt->bind_param("s", $ticket_no);
                    $stmt->execute();
                    $replyText = "🗑️ ยกเลิกข้อมูลเดิมเรียบร้อยแล้วค่ะ\n\nรบกวนคุณลูกค้าพิมพ์อธิบายอุปกรณ์และปัญหาที่ต้องการแจ้งซ่อมเข้ามาใหม่อีกครั้งได้เลยนะคะ 🛠️";
                }

                $messageData = ['type' => 'text', 'text' => $replyText];
                send_reply($replyToken, $messageData, $channelAccessToken);
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