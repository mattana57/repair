<?php
require 'db_connect.php';
require 'env.php';

$line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

$conn->query("CREATE TABLE IF NOT EXISTS line_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id VARCHAR(255) UNIQUE NOT NULL,
    line_display_name VARCHAR(255),
    real_name VARCHAR(255),
    phone_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$chk_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'line_display_name'");
if($chk_col && $chk_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN line_display_name VARCHAR(255) NULL AFTER reporter_name");
}

function extract_repair_info($text) {
    $category = "ไม่ระบุปัญหา";
    $location = "ไม่ระบุสถานที่";
    
    $keywords = [
        'แอร์', 'คอม', 'เครื่องปริ้น', 'printer', 'projector', 'โปรเจคเตอร์', 'โปรเจกเตอร์', 'เครื่องฉาย', 
        'จอ', 'ทีวี', 'ไมค์', 'หลอดไฟ', 'ไฟดับ', 'สายไฟ', 'ปลั๊ก', 'ไฟ', 'หลอด', 'พัดลม', 'เน็ต', 'wifi', 'วายฟาย','ไว้ฟาย','ไวฟาย', 'อินเทอร์เน็ต',
        'เว็บคณะ', 'มคอ', 'ประตู', 'สแกนหน้า', 'ท่อ', 'ห้องน้ำ', 'ก๊อก', 'ชักโครก',
        'ตู้กดน้ำ', 'จิ้งจก', 'นก', 'ตุ๊กแก', 'หนู', 'กลิ่นเหม็น', 
        'งู', 'หมา', 'แมว', 'น้ำรั่ว', 'หน้าต่าง', 'กระจก', 'โต๊ะ', 'เก้าอี้', 'เพดาน', 'หลังคา'
    ];

    $text_lower = mb_strtolower($text, 'UTF-8');
    foreach ($keywords as $keyword) {
        if (mb_strpos($text_lower, $keyword) !== false) {
            $category = $keyword;
            break;
        }
    }
    
    preg_match('/(หน้า|หลัง|ข้าง|ใน|นอก)?\s*(ห้อง\s*[a-zA-Z0-9]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+|ชั้น\s*[0-9]+)/iu', $text, $matches);
    if (!empty($matches[0])) {
        $location = trim($matches[0]);
    }
    
    return [$category, $location];
}

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
        $userId = $event['source']['userId'];
        $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;

        $line_name = get_line_profile($userId, null, $channelAccessToken);

        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            
            $stmt_chk_user = $conn->prepare("SELECT id FROM line_users WHERE line_user_id = ?");
            $stmt_chk_user->bind_param("s", $userId);
            $stmt_chk_user->execute();
            if ($stmt_chk_user->get_result()->num_rows === 0) {
                send_reply($replyToken, ['type' => 'text', 'text' => "🛑 คุณยังไม่ได้ให้ข้อมูลติดต่อค่ะ\n\nกรุณาพิมพ์ ชื่อ และ เบอร์โทรศัพท์ ส่งมาได้เลยนะคะ (ไม่ต้องมีคำสั่งใดๆ)\n\nตัวอย่าง:\nดวงดาว 098009809"], $channelAccessToken);
                continue;
            }

            $message_id = $event['message']['id'];
            $image_url = "https://api-data.line.me/v2/bot/message/$message_id/content";
            
            $ch = curl_init($image_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $channelAccessToken]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $image_data = curl_exec($ch);
            curl_close($ch);
            
            if ($image_data) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                $stmt_check = $conn->prepare("SELECT id, ticket_no, created_at, status, image_path FROM repairs WHERE line_user_id = ? ORDER BY id DESC LIMIT 1");
                $stmt_check->bind_param("s", $userId);
                $stmt_check->execute();
                $latest_job = $stmt_check->get_result()->fetch_assoc();
                
                $attached = false;
                if ($latest_job && $latest_job['status'] === 'รอรับเรื่อง' && empty($latest_job['image_path'])) {
                    if ((time() - strtotime($latest_job['created_at'])) <= 3600) {
                        $new_img_name = $latest_job['ticket_no'] . "_" . time() . ".jpg";
                        file_put_contents("uploads/" . $new_img_name, $image_data);
                        
                        $chk_img_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'image_path'");
                        if($chk_img_col && $chk_img_col->num_rows == 0) {
                            $conn->query("ALTER TABLE repairs ADD COLUMN image_path VARCHAR(255) NULL");
                        }
                        
                        $stmt_upd = $conn->prepare("UPDATE repairs SET image_path = ? WHERE id = ?");
                        $stmt_upd->bind_param("si", $new_img_name, $latest_job['id']);
                        $stmt_upd->execute();
                        
                        $replyText = "📸 แนบรูปภาพเข้ากับใบงาน {$latest_job['ticket_no']} เรียบร้อยแล้วค่ะ";
                        send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);
                        $attached = true;
                    }
                }
                
                if (!$attached) {
                    file_put_contents("uploads/temp_{$userId}.jpg", $image_data);
                    $replyText = "📸 รับรูปภาพเรียบร้อยค่ะ\nกรุณาพิมพ์ข้อความแจ้งรายละเอียดปัญหาและสถานที่ (เช่น 'แอร์ไม่เย็น ห้อง 502') เพื่อให้ระบบบันทึกเข้าระบบแจ้งซ่อมได้เลยค่ะ";
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);
                }
            }
            continue;
        }

        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $message_id = $event['message']['id']; 

            if (mb_strpos($text, 'ผูกบัญชี') === 0) {
                $code = trim(str_replace('ผูกบัญชี', '', $text));
                if (preg_match('/^[0-9]{4}$/', $code)) {
                    $stmt_check = $conn->prepare("SELECT id, full_name, department FROM technicians WHERE secret_code = ? AND approval_status = 'รอผูกบัญชี'");
                    $stmt_check->bind_param("s", $code);
                    $stmt_check->execute();
                    $res = $stmt_check->get_result()->fetch_assoc();

                    if ($res) {
                        $tech_id = $res['id'];
                        $tech_name = $res['full_name'];
                        $tech_dept = !empty($res['department']) ? $res['department'] : 'ฝ่ายงานทั่วไป';

                        $stmt_update = $conn->prepare("UPDATE technicians SET line_user_id = ?, approval_status = 'อนุมัติแล้ว', secret_code = NULL WHERE id = ?");
                        $stmt_update->bind_param("si", $userId, $tech_id);
                        
                        if ($stmt_update->execute()) {
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ ยืนยันตัวตนสำเร็จ\n\nยินดีต้อนรับ ช่าง$tech_name\n($tech_dept)\n\nคุณสามารถเริ่มรับงานซ่อมได้เลยค่ะ 🛠️"], $channelAccessToken);
                        }
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ รหัสลับไม่ถูกต้อง หรือรหัสนี้ถูกใช้งานไปแล้วค่ะ"], $channelAccessToken);
                    }
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ รูปแบบไม่ถูกต้อง\nกรุณาพิมพ์: ผูกบัญชี [รหัส 4 หลัก]"], $channelAccessToken);
                }
                continue; 
            }

            $edit_keywords = ['เปลี่ยนชื่อ', 'เปลี่ยนเบอร์', 'แก้ไขข้อมูล', 'แก้ชื่อ', 'เปี่ยนชื่อ', 'เปลียนชื่อ', 'เปลี่ชื่อ', 'เปี่ยนเบอร์', 'แก้เบอร์','เปลี่ยนแปลง','อยากแก้','อยากเปลี่ยน','ต้องการแก้','ต้องการเปลี่ยน','ปรับปรุง','ปรับแก้', 'แก้ไข'];
            $is_edit_cmd = false;
            foreach ($edit_keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    $is_edit_cmd = true;
                    break;
                }
            }

            $words_to_remove = ['เปลี่ยนชื่อ', 'เปลี่ยนเบอร์', 'แก้ไขข้อมูล', 'แก้ชื่อ', 'เปี่ยนชื่อ', 'เปลียนชื่อ', 'เปลี่ชื่อ', 'เปี่ยนเบอร์', 'แก้เบอร์', 'จาก', 'เดิม', 'ด้วย', 'เป็น', 'ใหม่', 'ชื่อ-สกุล', 'ชื่อ-นามสกุล', 'ชื่อ', 'นามสกุล', 'เบอร์โทรศัพท์', 'เบอร์โทร','เบอโทร', 'เบอร์', 'โทรศัพท์', 'โทร', 'tel', 'นะคะ', 'นะครับ', 'ค่ะ', 'ครับ', 'คับ', 'จ้า', 'จ๊ะ', 'นะ', 'หน่อย', 'และ', 'หรือ', 'ทั้ง', 'ได้ไหม', 'ได้มั้ย', 'คะ', 'ข้อมูล', 'กับ', 'ปรับ', 'ปรุง', 'แก้', 'นี้', 'เบอ', 'จ้ะ', 'ค้าบ', 'อยาก', 'ต้องการ', 'ช่วย', 'ให้', 'ค่า', 'ต้อง', 'การ', 'ครัช', 'ได้', 'มั้ย', 'ไหม', 'โท', 'สกุล', 'เอา', 'ออก', 'แล้ว', 'ถ้า', 'หาก', 'สอง', 'อย่าง', '2', ':', '-', ','];

            if ($is_edit_cmd) {
                preg_match('/(0[0-9]{8,9})/', $text, $matches);
                $phone = !empty($matches[1]) ? $matches[1] : null;

                $name_part = str_replace($phone, '', $text);
                $real_name = str_replace($words_to_remove, ' ', $name_part);
                $real_name = preg_replace('/\s+/', ' ', $real_name);
                $real_name = trim($real_name);

                $stmt_old = $conn->prepare("SELECT real_name, phone_number FROM line_users WHERE line_user_id = ?");
                $stmt_old->bind_param("s", $userId);
                $stmt_old->execute();
                $res_old = $stmt_old->get_result()->fetch_assoc();

                if (empty($real_name)) {
                    $real_name = ($res_old && !empty($res_old['real_name'])) ? $res_old['real_name'] : $line_name;
                }
                if (empty($phone)) {
                    $phone = ($res_old && !empty($res_old['phone_number'])) ? $res_old['phone_number'] : "-";
                }

                $stmt = $conn->prepare("UPDATE line_users SET line_display_name=?, real_name=?, phone_number=? WHERE line_user_id=?");
                $stmt->bind_param("ssss", $line_name, $real_name, $phone, $userId);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows == 0) {
                        $stmt_chk = $conn->prepare("SELECT id FROM line_users WHERE line_user_id=?");
                        $stmt_chk->bind_param("s", $userId);
                        $stmt_chk->execute();
                        if ($stmt_chk->get_result()->num_rows == 0) {
                            $stmt2 = $conn->prepare("INSERT INTO line_users (line_user_id, line_display_name, real_name, phone_number) VALUES (?, ?, ?, ?)");
                            $stmt2->bind_param("ssss", $userId, $line_name, $real_name, $phone);
                            $stmt2->execute();
                        }
                    }
                    send_reply($replyToken, ['type' => 'text', 'text' => "✅ อัปเดตข้อมูลของคุณเรียบร้อยแล้วค่ะ!\n\nชื่อ: $real_name\nเบอร์โทร: $phone\n\nครั้งต่อไปที่แจ้งซ่อม ระบบจะใช้ข้อมูลใหม่นี้ทันทีค่ะ ✨"], $channelAccessToken);
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "🚨 ระบบเกิดข้อผิดพลาดในการอัปเดตข้อมูลค่ะ"], $channelAccessToken);
                }
                continue;
            }

            $text_clean = mb_strtolower(str_replace([' ', "\n", 'ค่ะ', 'ครับ', 'จ้า', 'นะ', 'พี่'], '', $text), 'UTF-8');
            $greetings = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'รับทราบ', 'เยี่ยม', 'แต้ง'];
            $is_greeting = false;
            foreach ($greetings as $g) {
                if (mb_strpos($text_clean, $g) !== false) {
                    $is_greeting = true; break;
                }
            }
            if ($is_greeting && mb_strlen($text_clean) < 40) {
                send_reply($replyToken, ['type' => 'text', 'text' => "ด้วยความยินดีค่ะ 💖 หากมีปัญหาเพิ่มเติมแจ้งได้ตลอดเลยนะคะ"], $channelAccessToken);
                continue; 
            }

            $stmt_chk_user = $conn->prepare("SELECT real_name, phone_number FROM line_users WHERE line_user_id = ?");
            $stmt_chk_user->bind_param("s", $userId);
            $stmt_chk_user->execute();
            $user_reg = $stmt_chk_user->get_result()->fetch_assoc();

            if (!$user_reg) {
                
                if (preg_match('/(0[0-9]{8,9})/', $text, $matches)) {
                    $phone = $matches[1];
                    $name_part = str_replace($phone, '', $text);
                    $real_name = str_replace($words_to_remove, ' ', $name_part);
                    $real_name = preg_replace('/\s+/', ' ', $real_name);
                    $real_name = trim($real_name);
                    
                    if (empty($real_name)) {
                        $real_name = $line_name; 
                    }
                    
                    if (!empty($real_name)) {
                        $stmt = $conn->prepare("INSERT INTO line_users (line_user_id, line_display_name, real_name, phone_number) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE line_display_name=?, real_name=?, phone_number=?");
                        $stmt->bind_param("sssssss", $userId, $line_name, $real_name, $phone, $line_name, $real_name, $phone);
                        
                        if ($stmt->execute()) {
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกข้อมูลสำเร็จ!\nยินดีต้อนรับคุณ $real_name\n\nจากนี้สามารถพิมพ์แจ้งซ่อมเข้ามาได้เลยค่ะ เช่น 'แอร์น้ำหยด ห้อง 901' 🛠️"], $channelAccessToken);
                        } else {
                            send_reply($replyToken, ['type' => 'text', 'text' => "🚨 ระบบเกิดข้อผิดพลาดในการบันทึกข้อมูลค่ะ"], $channelAccessToken);
                        }
                        continue;
                    }
                }

                $stmt_check_review = $conn->prepare("SELECT ticket_no, review_comment FROM repairs WHERE line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว' ORDER BY ticket_no DESC LIMIT 1");
                if ($stmt_check_review) {
                    $stmt_check_review->bind_param("s", $userId);
                    $stmt_check_review->execute();
                    $recent_job = $stmt_check_review->get_result()->fetch_assoc();

                    if ($recent_job && mb_strlen($text) < 100 && !preg_match('/(ห้อง|อาคาร|ชั้น)/', $text)) {
                        $current_rev = (string)$recent_job['review_comment'];
                        $new_rev = trim($current_rev . " " . $text);
                        $stmt_upd = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE ticket_no = ?");
                        $stmt_upd->bind_param("ss", $new_rev, $recent_job['ticket_no']);
                        $stmt_upd->execute();
                        send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกรีวิวเพิ่มเติมให้ใบงาน {$recent_job['ticket_no']} เรียบร้อยค่ะ ขอบคุณมากนะคะ 🙏✨"], $channelAccessToken);
                        continue;
                    }
                }

                send_reply($replyToken, ['type' => 'text', 'text' => "🛑 คุณยังไม่ได้ให้ข้อมูลติดต่อค่ะ\n\nเพื่อให้ช่างติดต่อกลับได้สะดวก กรุณาพิมพ์ ชื่อ และ เบอร์โทรศัพท์ ส่งมาให้ระบบได้เลยนะคะ\n\nตัวอย่าง:\nดวงดาว 098009809"], $channelAccessToken);
                continue;
            }

            list($category, $location) = extract_repair_info($text);

            if ($category !== "ไม่ระบุปัญหา" || $location !== "ไม่ระบุสถานที่") {
                
                $ticket_no = "MR-" . rand(1000, 9999);
                $status = "รอรับเรื่อง"; 
                $image_path = null; 
                
                $words_to_remove_repair = [$location, 'ค่ะ', 'ครับ', 'คะ', 'คับ', 'รบกวน', 'ด่วน', 'แจ้งซ่อม', 'นึง', 'หน่อย'];
                $problem = str_replace($words_to_remove_repair, '', $text);
                $problem = trim($problem); 
                
                if (empty($problem)) {
                    $problem = "มีความผิดปกติ (รอตรวจสอบ)";
                }

                if ($category === "ไม่ระบุปัญหา") {
                    $category = mb_substr($problem, 0, 50, 'UTF-8');
                }

                $temp_img_path = "uploads/temp_{$userId}.jpg";
                if (file_exists($temp_img_path)) {
                    $new_img_name = $ticket_no . "_" . time() . ".jpg";
                    rename($temp_img_path, "uploads/" . $new_img_name); 
                    $image_path = $new_img_name;
                }

                $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, line_display_name, phone_number, line_user_id, line_message_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssss", $ticket_no, $category, $location, $problem, $status, $user_reg['real_name'], $line_name, $user_reg['phone_number'], $userId, $message_id, $image_path);
                
                if($stmt->execute()) {
                    $replyText = "✅ รับเรื่องแจ้งซ่อมเรียบร้อยค่ะ\n\n📌 เลขที่ใบงาน: $ticket_no\n⚠️ ปัญหา: $category\n📍 สถานที่: $location\n📝 รายละเอียด: $problem\n\nระบบจะแจ้งเตือนให้ทราบเมื่อช่างเริ่มดำเนินการนะคะ";
                    if ($image_path) {
                        $replyText .= "\n📸 (ระบบได้รับและแนบรูปภาพของคุณเข้าไปในใบงานเรียบร้อยแล้วค่ะ)";
                    }
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

                    $flex_details = [
                        ['type' => 'text', 'text' => "ปัญหา: $category", 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                        ['type' => 'text', 'text' => "สถานที่: $location", 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                        ['type' => 'text', 'text' => "ผู้แจ้ง: ".$line_name." (".$user_reg['phone_number'].")", 'size' => 'xs', 'color' => '#666666', 'wrap' => true],
                        ['type' => 'text', 'text' => "รายละเอียด: $problem", 'size' => 'xs', 'color' => '#ef4444', 'wrap' => true]
                    ];
                    if ($image_path) {
                        $flex_details[] = ['type' => 'text', 'text' => "📸 (มีรูปภาพแนบในระบบ)", 'size' => 'xs', 'color' => '#3b82f6', 'weight' => 'bold', 'margin' => 'sm'];
                    }

                    $pushMsg = [
                        'type' => 'flex',
                        'altText' => 'แจ้งงานซ่อมใหม่: '.$ticket_no,
                        'contents' => [
                            'type' => 'bubble',
                            'size' => 'kilo',
                            'body' => [
                                'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '15px',
                                'contents' => [
                                    ['type' => 'text', 'text' => '🔔 งานแจ้งซ่อมใหม่', 'weight' => 'bold', 'color' => '#ef4444', 'size' => 'xs'],
                                    ['type' => 'text', 'text' => $ticket_no, 'weight' => 'bold', 'size' => 'lg', 'margin' => 'xs'],
                                    ['type' => 'separator', 'margin' => 'sm'],
                                    [
                                        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs', 'margin' => 'sm',
                                        'contents' => $flex_details
                                    ]
                                ]
                            ],
                            'footer' => [
                                'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '15px', 'paddingTop' => '0px',
                                'contents' => [
                                    ['type' => 'button', 'style' => 'primary', 'color' => '#3b82f6', 'height' => 'sm',
                                        'action' => ['type' => 'postback', 'label' => 'กดรับงาน', 'data' => "action=accept&ticket=$ticket_no"]
                                    ]
                                ]
                            ]
                        ]
                    ];
                    send_push($line_group_id, $pushMsg, $channelAccessToken);
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "🚨 เกิดข้อผิดพลาด ไม่สามารถบันทึกข้อมูลได้ค่ะ"], $channelAccessToken);
                }
            }
            else {
                if (preg_match('/(0[0-9]{8,9})/', $text, $matches)) {
                    $phone = $matches[1];
                    $name_part = str_replace($phone, '', $text);
                    $real_name = str_replace($words_to_remove, ' ', $name_part);
                    $real_name = preg_replace('/\s+/', ' ', $real_name);
                    $real_name = trim($real_name);
                    
                    if (mb_strlen($text, 'UTF-8') < 50) {
                        
                        if (empty($real_name)) {
                            $real_name = $user_reg['real_name']; 
                        }

                        $stmt = $conn->prepare("UPDATE line_users SET line_display_name=?, real_name=?, phone_number=? WHERE line_user_id=?");
                        $stmt->bind_param("ssss", $line_name, $real_name, $phone, $userId);
                        $stmt->execute();
                        
                        send_reply($replyToken, ['type' => 'text', 'text' => "✅ อัปเดตข้อมูลของคุณเรียบร้อยแล้วค่ะ!\n\nชื่อ: $real_name\nเบอร์โทร: $phone\n\nครั้งต่อไปที่แจ้งซ่อม ระบบจะใช้ข้อมูลใหม่นี้ทันทีค่ะ ✨"], $channelAccessToken);
                        continue;
                    }
                }

                $stmt_check_review = $conn->prepare("SELECT ticket_no, review_comment FROM repairs WHERE line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว' ORDER BY ticket_no DESC LIMIT 1");
                if ($stmt_check_review) {
                    $stmt_check_review->bind_param("s", $userId);
                    $stmt_check_review->execute();
                    $recent_job = $stmt_check_review->get_result()->fetch_assoc();

                    if ($recent_job && mb_strlen($text) < 100 && !preg_match('/(ห้อง|อาคาร|ชั้น)/', $text)) {
                        $current_rev = (string)$recent_job['review_comment'];
                        $new_rev = trim($current_rev . " " . $text);
                        
                        $stmt_update_review = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE ticket_no = ?");
                        $stmt_update_review->bind_param("ss", $new_rev, $recent_job['ticket_no']);
                        $stmt_update_review->execute();
                        
                        send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกรีวิวเพิ่มเติมให้ใบงาน {$recent_job['ticket_no']} เรียบร้อยค่ะ ขอบคุณมากนะคะ 🙏✨"], $channelAccessToken);
                    }
                }
            }
        }
        elseif ($event['type'] == 'postback') {
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                if ($postbackData['action'] == 'accept') {
                    $stmt_check = $conn->prepare("SELECT id, status, line_user_id, technician_name, equipment_type, location, reporter_name, phone_number, line_display_name FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job) {
                        if ($job['status'] == 'รอรับเรื่อง') {
                            
                            $stmt_tech = $conn->prepare("SELECT * FROM technicians WHERE line_user_id = ? AND approval_status = 'อนุมัติแล้ว'");
                            $stmt_tech->bind_param("s", $userId);
                            $stmt_tech->execute();
                            $tech_result = $stmt_tech->get_result()->fetch_assoc();

                            if ($tech_result) {
                                $tech_name = $tech_result['full_name'];
                                $tech_phone = !empty($tech_result['phone']) ? $tech_result['phone'] : "-"; 
                                $tech_dept = isset($tech_result['department']) && !empty($tech_result['department']) ? $tech_result['department'] : "ทีมช่าง";
                            } else {
                                send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ระบบปฏิเสธ: คุณยังไม่ได้ผูกบัญชีช่างเทคนิคค่ะ\nกรุณาพิมพ์ 'ผูกบัญชี [รหัส 4 หลัก]' เพื่อใช้งานนะคะ"], $channelAccessToken);
                                continue;
                            }

                            $stmt = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ', technician_name = ? WHERE ticket_no = ?");
                            $stmt->bind_param("ss", $tech_name, $ticket_no);
                            $stmt->execute();

                            $disp_name = !empty($job['line_display_name']) ? $job['line_display_name'] : $job['reporter_name'];

                            $replyMsg = [
                                'type' => 'flex',
                                'altText' => 'รับงานซ่อม: '.$ticket_no,
                                'contents' => [
                                    'type' => 'bubble',
                                    'size' => 'kilo',
                                    'body' => [
                                        'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '15px',
                                        'contents' => [
                                            ['type' => 'text', 'text' => '✅ รับงานเรียบร้อย', 'weight' => 'bold', 'color' => '#10b981', 'size' => 'xs'],
                                            ['type' => 'text', 'text' => "ช่าง $tech_name", 'weight' => 'bold', 'size' => 'md', 'margin' => 'xs'],
                                            ['type' => 'text', 'text' => "($tech_dept)", 'size' => 'xxs', 'color' => '#888888', 'margin' => 'none'],
                                            ['type' => 'separator', 'margin' => 'sm'],
                                            [
                                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'xs', 'margin' => 'sm',
                                                'contents' => [
                                                    ['type' => 'text', 'text' => "ใบงาน: $ticket_no", 'size' => 'xs', 'color' => '#333333'],
                                                    ['type' => 'text', 'text' => "ปัญหา: ".$job['equipment_type'], 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                                                    ['type' => 'text', 'text' => "สถานที่: ".$job['location'], 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                                                    ['type' => 'text', 'text' => "ผู้แจ้ง: ".$disp_name." (".$job['phone_number'].")", 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                                                    ['type' => 'text', 'text' => "สถานะ: กำลังดำเนินการ", 'size' => 'xs', 'color' => '#3b82f6', 'weight' => 'bold', 'wrap' => true]
                                                ]
                                            ]
                                        ]
                                    ],
                                    'footer' => [
                                        'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '15px', 'paddingTop' => '0px',
                                        'contents' => [
                                            ['type' => 'button', 'style' => 'primary', 'color' => '#ef4444', 'height' => 'sm',
                                                'action' => ['type' => 'postback', 'label' => 'แจ้งปิดงาน', 'data' => "action=close&ticket=$ticket_no"]
                                            ],
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm',
                                                'action' => ['type' => 'uri', 'label' => '📝 จัดการ/เพิ่มหมายเหตุ', 'uri' => "http://103.99.11.147/repair/update_repair.php?id=" . $job['id']]
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                            
                            send_reply($replyToken, $replyMsg, $channelAccessToken);
                            
                            $pushMsgToUser = ['type' => 'text', 'text' => "👨‍🔧 ช่าง $tech_name รับงานซ่อมของคุณแล้วนะคะ\n📞 เบอร์ติดต่อ: $tech_phone\n\nช่างกำลังเตรียมตัวเข้าไปดำเนินการแก้ไขให้ค่ะ 🛠️"];
                            send_push($job['line_user_id'], $pushMsgToUser, $channelAccessToken);

                            if(!empty($line_group_id)) {
                                $groupMessage = "📢 มีช่างรับงานแล้วจ้า!\n" .
                                                "👨‍🔧 ช่าง: " . $tech_name . "\n" .
                                                "💻 งาน: " . $job['equipment_type'] . " (" . $job['location'] . ")";
                                
                                $postDataGroup = [
                                    'to' => $line_group_id,
                                    'messages' => [['type' => 'text', 'text' => $groupMessage]]
                                ];
                                send_push($line_group_id, $postDataGroup, $channelAccessToken);
                            }

                        } else {
                            $taken_by = !empty($job['technician_name']) ? $job['technician_name'] : "ช่างท่านอื่น";
                            send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ใบงาน $ticket_no ถูกรับไปแล้วโดยช่าง $taken_by"], $channelAccessToken);
                        }
                    }
                }
                elseif ($postbackData['action'] == 'close') {
                    $stmt_check = $conn->prepare("SELECT id, status, line_user_id, technician_name, reporter_name FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job) {
                        if ($job['status'] == 'กำลังดำเนินการ' || $job['status'] == 'ช่างรับเรื่องแจ้งซ่อมแล้ว') {
                            
                            $stmt_tech = $conn->prepare("SELECT full_name FROM technicians WHERE line_user_id = ? AND approval_status = 'อนุมัติแล้ว'");
                            $stmt_tech->bind_param("s", $userId);
                            $stmt_tech->execute();
                            $tech_result = $stmt_tech->get_result()->fetch_assoc();

                            if ($tech_result) {
                                $clicker_name = $tech_result['full_name'];
                                
                                if ($clicker_name === $job['technician_name']) {
                                    
                                    $stmt = $conn->prepare("UPDATE repairs SET status = 'ซ่อมเสร็จแล้ว', completed_at = CURRENT_TIMESTAMP WHERE ticket_no = ?");
                                    $stmt->bind_param("s", $ticket_no);
                                    $stmt->execute();

                                    $close_reply = "🎉 บันทึกปิดงานใบงาน $ticket_no เรียบร้อยค่ะ ระบบได้ส่งแบบประเมินให้ผู้แจ้งแล้ว";
                                    send_reply($replyToken, ['type' => 'text', 'text' => $close_reply], $channelAccessToken);

                                    $review_msg = [
                                        'type' => 'flex',
                                        'altText' => 'ประเมินผลการซ่อม',
                                        'contents' => [
                                            'type' => 'bubble',
                                            'body' => [
                                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                                'contents' => [
                                                    ['type' => 'text', 'text' => '⭐ ประเมินผลการซ่อม', 'weight' => 'bold', 'color' => '#ffb700', 'size' => 'md'],
                                                    ['type' => 'text', 'text' => "ถึงคุณ ".$job['reporter_name'], 'weight' => 'bold', 'color' => '#3b82f6', 'size' => 'xs'],
                                                    ['type' => 'text', 'text' => 'ช่าง '.$clicker_name.' ดำเนินการซ่อมเสร็จเรียบร้อยแล้ว!', 'weight' => 'bold', 'size' => 'xs', 'wrap' => true],
                                                    ['type' => 'separator', 'margin' => 'sm'],
                                                    ['type' => 'text', 'text' => '1️⃣ ให้คะแนนดาว (กดเปลี่ยนได้)', 'size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'sm'],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'primary', 'color' => '#fbbf24', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '5 ดาว', 'data' => "action=rate&score=5&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '4 ดาว', 'data' => "action=rate&score=4&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '3 ดาว', 'data' => "action=rate&score=3&ticket=$ticket_no"]]
                                                        ]
                                                    ],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm', 'margin' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '2 ดาว', 'data' => "action=rate&score=2&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '1 ดาว', 'data' => "action=rate&score=1&ticket=$ticket_no"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '0 ดาว', 'data' => "action=rate&score=0&ticket=$ticket_no"]]
                                                        ]
                                                    ],
                                                    ['type' => 'separator', 'margin' => 'sm'],
                                                    ['type' => 'text', 'text' => '2️⃣ เลือกรีวิว (กดได้หลายข้อ)', 'size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'sm'],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '⏱️ ดำเนินการเร็ว', 'data' => "action=add_tag&tag=ดำเนินการรวดเร็วทันใจ&ticket=$ticket_no"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🎯 แก้ปัญหาตรงจุด', 'data' => "action=add_tag&tag=แก้ไขปัญหาได้อย่างตรงจุด&ticket=$ticket_no"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '💡 ให้คำแนะนำดี', 'data' => "action=add_tag&tag=อธิบายและให้คำแนะนำชัดเจน&ticket=$ticket_no"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🗣️ สุภาพเรียบร้อย', 'data' => "action=add_tag&tag=สุภาพเรียบร้อย บริการเต็มใจ&ticket=$ticket_no"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '✨ เก็บงานเรียบร้อย', 'data' => "action=add_tag&tag=ซ่อมแซมและเก็บงานเรียบร้อย&ticket=$ticket_no"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🙏 ช่วยเหลือดีเยี่ยม', 'data' => "action=add_tag&tag=ช่วยอำนวยความสะดวกได้ดีเยี่ยม&ticket=$ticket_no"]],
                                                    ['type' => 'text', 'text' => '*หรือพิมพ์ข้อความรีวิวเพิ่มเติมส่งมาในแชทได้เลยค่ะ', 'size' => 'xxs', 'color' => '#bbbbbb', 'margin' => 'sm', 'wrap' => true]
                                                ]
                                            ]
                                        ]
                                    ];
                                    
                                    send_push($job['line_user_id'], $review_msg, $channelAccessToken);
                                    
                                } else {
                                    send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ระบบปฏิเสธ: เฉพาะช่าง ".$job['technician_name']." ที่สามารถแจ้งปิดงานใบงาน $ticket_no ได้ค่ะ"], $channelAccessToken);
                                }
                            } else {
                                send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ระบบปฏิเสธ: ผู้กดไม่มีสิทธิ์ทำรายการนี้ค่ะ"], $channelAccessToken);
                            }
                            
                        } else if ($job['status'] == 'ซ่อมเสร็จแล้ว') {
                             send_reply($replyToken, ['type' => 'text', 'text' => "✅ ใบงาน $ticket_no ถูกแจ้งปิดงานไปเรียบร้อยแล้วค่ะ"], $channelAccessToken);
                        }
                    }
                }
                elseif ($postbackData['action'] == 'rate') {
                    $score = $postbackData['score'];
                    $stmt = $conn->prepare("UPDATE repairs SET rating = ? WHERE ticket_no = ?");
                    $stmt->bind_param("is", $score, $ticket_no);
                    
                    if($stmt->execute()){
                        $thankYouMsg = [
                            'type' => 'text', 
                            'text' => "✅ บันทึกคะแนน $score ดาว สำหรับใบงาน $ticket_no เรียบร้อยค่ะ\n\n(ประทับใจส่วนไหน เลือกรีวิวด้านบน 👆 หรือพิมพ์ข้อความส่งมาในแชทได้เลยนะคะ 💬)"
                        ];
                        send_reply($replyToken, $thankYouMsg, $channelAccessToken);
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
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ เพิ่มรีวิวให้ใบงาน $ticket_no: $tag"], $channelAccessToken);
                        }
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "คุณได้เลือกรีวิว '$tag' ให้ใบงาน $ticket_no ไปแล้วค่ะ 💖"], $channelAccessToken);
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