<?php
// ใส่ Channel Access Token ของคุณน้ำฝน
$channelAccessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU=';

// 🚨 Group ID ของกลุ่มช่าง/แอดมิน (เอาไว้สะกิดเรียกแอดมิน)
$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

// รับข้อมูลที่ LINE ส่งมาให้
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        // ตรวจสอบว่าเป็นเหตุการณ์ "ส่งข้อความ" และเป็น "ข้อความตัวอักษร"
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $replyToken = $event['replyToken'];

            // ----------------------------------------------------
            // กรณีที่ 1: พิมพ์คำว่า "ขอไอดีกลุ่ม"
            // ----------------------------------------------------
            if ($text == 'ขอไอดีกลุ่ม') {
                $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : 'นี่ไม่ใช่กลุ่ม (เป็นแชทส่วนตัว)';
                
                $messageData = [
                    'type' => 'text',
                    'text' => "Group ID ของกลุ่มนี้คือ:\n" . $groupId
                ];
                send_reply($replyToken, $messageData, $channelAccessToken);
            } 
            
            // ----------------------------------------------------
            // กรณีที่ 2: ผู้ใช้กดปุ่ม "ติดต่อแอดมิน" จาก Rich Menu
            // ----------------------------------------------------
            elseif ($text == 'ติดต่อแอดมิน') {
                
                // 2.1 ตอบกลับผู้ใช้ให้สบายใจ
                $replyMsg = [
                    'type' => 'text', 
                    'text' => "แอดมินรับทราบแล้วค่ะ 🙋‍♀️\n\nรบกวนพิมพ์รายละเอียดปัญหา หรือข้อสงสัยทิ้งไว้ได้เลยนะคะ เจ้าหน้าที่จะรีบเข้ามาตรวจสอบและตอบกลับให้เร็วที่สุดค่ะ"
                ];
                send_reply($replyToken, $replyMsg, $channelAccessToken);

                // 2.2 ยิงข้อความไปสะกิดแอดมินใน "กลุ่มช่าง"
                if (!empty($line_group_id)) {
                    $pushMsg = [
                        'type' => 'text',
                        'text' => "🚨 แจ้งเตือน: มีผู้ใช้ต้องการติดต่อเจ้าหน้าที่!\n\nรบกวนแอดมินเข้าไปตรวจสอบและตอบแชทผู้ใช้ในแอป LINE Official Account ด้วยนะคะ 💬\n\n💻 สำหรับแอดมินที่ใช้คอมพิวเตอร์ สามารถคลิกลิงก์นี้เพื่อเปิดหน้าแชทได้เลยค่ะ:\nhttps://chat.line.biz/"
                    ];
                    send_push($line_group_id, $pushMsg, $channelAccessToken);
                }
            }
        }
    }
}
echo "OK";

// ========================================================
// ฟังก์ชันสำหรับส่งข้อความตอบกลับ (Reply)
// ========================================================
function send_reply($replyToken, $messageData, $accessToken) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    $data = [
        'replyToken' => $replyToken,
        'messages' => [$messageData],
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', 
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

// ========================================================
// ฟังก์ชันสำหรับส่งข้อความแจ้งเตือน (Push) ไปยังกลุ่ม
// ========================================================
function send_push($to, $messageData, $accessToken) {
    $url = 'https://api.line.me/v2/bot/message/push';
    $data = [
        'to' => $to,
        'messages' => [$messageData],
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', 
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
?>