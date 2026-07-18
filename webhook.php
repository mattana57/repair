<?php
// ใส่ Channel Access Token ของคุณน้ำฝน
$channelAccessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU=';

// รับข้อมูลที่ LINE ส่งมาให้
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        // ตรวจสอบว่าเป็นเหตุการณ์ "ส่งข้อความ" และเป็น "ข้อความตัวอักษร"
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = $event['message']['text'];
            $replyToken = $event['replyToken'];

            // ถ้าพิมพ์คำว่า "ขอไอดีกลุ่ม"
            if ($text == 'ขอไอดีกลุ่ม') {
                // ดึงค่า Group ID ออกมา
                $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : 'นี่ไม่ใช่กลุ่ม (เป็นแชทส่วนตัว)';
                
                // เตรียมข้อความตอบกลับ
                $messages = [
                    'type' => 'text',
                    'text' => "Group ID ของกลุ่มนี้คือ:\n" . $groupId
                ];

                $url = 'https://api.line.me/v2/bot/message/reply';
                $data = [
                    'replyToken' => $replyToken,
                    'messages' => [$messages],
                ];
                
                $post = json_encode($data);
                $headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $channelAccessToken);

                // ส่งคำสั่งตอบกลับไปยัง LINE
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }
}
echo "OK";
?>