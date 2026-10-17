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
    
    $text_lower = mb_strtolower($text, 'UTF-8');
    
    $text_for_category = str_replace(['ดำเนินการ', 'พนักงาน', 'หนัก'], ['ทำงาน', 'พนง', 'ห_นัก'], $text_lower);
    
    // คำเฉพาะจากการแจ้งจริง วางก่อนคำกว้าง เช่น ไมค์ จอ ไฟ
    // ไม่เพิ่มคำตอบรับหรือคำทั่วไป เช่น ครับ ขอบคุณ ด่วน เป็นคีย์เวิร์ดอุปกรณ์
    $keywords = [
        'ลิฟต์', 'ลิฟท์',
        'เครื่องปรับอากาศ',
        'เครื่องเสียง', 'ไมโครโฟน', 'ไมค์ไร้สาย', 'ไมค์มีสาย', 'ไมค์ลอย', 'ไมค์สาย',
        'เครื่องพิมพ์', 'เครื่องปริ้น', 'เครื่องปริ๊น', 'เครื่องปริน', 'เครื่องพริน',
        'ปริ้นเตอร์', 'ปริ๊นเตอร์', 'ปรินเตอร์', 'พรินเตอร์', 'ปริ้น', 'ปริ๊น',
        'โปรเจ็คเตอร์', 'จอภาพ', 'hdmi', 'ไอแพด', 'ipad',
        'ฟิวส์', 'ฟิวไฟฟ้า', 'ฟิวไฟ', 'ฟิวขาด',
        'รถตู้', 'รถบัส', 'รถกระบะ', 'รถคณะ', 'รถสิบล้อ', 'รถขนของ', 'รถยนต์', 'รถไฟฟ้า', 'รถเก๋ง', 'รถสองแถว', 'รถรับส่ง', 'รถ',
        'จอดขวาง', 'ขวางทาง', 'อุบัติเหตุ', 'ชาร์จแบต', 'ยางรั่ว', 'ยางแบน', 'สตาร์ทไม่ติด', 'เครื่องดับ', 'รถเสีย',
        'โต๊ะคอม', 'เก้าอี้คอม', 'สายแลน', 'สายไฟ', 'หลอดไฟ', 'ไฟดับ', 'น้ำรั่ว', 'printer', 'projector', 'โปรเจคเตอร์', 'โปรเจกเตอร์', 'เครื่องฉาย', 
        'แอร์', 'คอม', 'จอ', 'ทีวี', 'ไมค์', 'ปลั๊ก', 'ไฟ', 'หลอด', 'พัดลม', 'เน็ต', 'wifi', 'วายฟาย','ไว้ฟาย','ไวฟาย', 'อินเทอร์เน็ต',
        'เว็บคณะ', 'มคอ', 'ประตู', 'สแกนหน้า', 'ท่อ', 'ห้องน้ำ', 'ก๊อก', 'ชักโครก',
        'ตู้กดน้ำ', 'จิ้งจก', 'นก', 'ตุ๊กแก', 'หนู', 'กลิ่นเหม็น', 
        'งู', 'หมา', 'แมว', 'หน้าต่าง', 'กระจก', 'โต๊ะ', 'เก้าอี้', 'เพดาน', 'หลังคา'
    ];

    foreach ($keywords as $keyword) {
        if (mb_strpos($text_for_category, $keyword) !== false) {
            if ($keyword === 'หนู' && (mb_strpos($text_for_category, 'หนูชื่อ') !== false || mb_strpos($text_for_category, 'หนูขอ') !== false || mb_strpos($text_for_category, 'หนูอยู่') !== false)) {
                continue;
            }
            $category = $keyword;
            break;
        }
    }
    
    // เมื่อไม่มีชื่ออุปกรณ์ ให้รับวลีขอรับบริการเป็นหัวข้องานได้
    // ใช้วลีร่วมกันทุกห้อง ไม่ต้องเพิ่มประโยคซ้ำตามหมายเลขห้อง
    if ($category === "ไม่ระบุปัญหา") {
        $service_keywords = [
            'ขอความช่วยเหลือ', 'ขอให้ช่วย', 'ช่วยตรวจสอบ', 'ช่วยเช็ค', 'ช่วยเช็ก',
            'ช่วยดู', 'รบกวนดู', 'รบกวนช่วย', 'รบกวนตรวจสอบ',
            'ขอเปิดห้อง', 'ช่วยเปิดห้อง', 'รบกวนเปิดห้อง',
            'ขออุปกรณ์', 'ขอยืมอุปกรณ์', 'ขอรับบริการ'
        ];
        foreach ($service_keywords as $service_keyword) {
            if (mb_strpos($text_lower, $service_keyword) !== false) {
                $category = "ขอความช่วยเหลือ/บริการ";
                break;
            }
        }
    }

    // รูปแบบอาคารและห้อง เช่น SBB603, SBB-401, Acc.Biz 204
    if (preg_match('/(?<![a-z0-9])(?:(sbb)|(acc[.\s-]*biz))[\s-]*([0-9]{3,4})(?![a-z0-9])/iu', $text, $matches)) {
        $building = !empty($matches[1]) ? 'SBB' : 'Acc.Biz';
        $location = $building . ' ' . $matches[3];
    }
    // ห้องที่ระบุชัดเจน เก็บเฉพาะเลข เช่น ห้อง701 หรือ ห้องแลป 302
    elseif (preg_match('/ห้อง\s*(?:เรียน|แลป|แล็บ|แลบ|lab)?\s*([0-9]{3,4})(?![0-9])/iu', $text, $matches)) {
        $location = $matches[1];
    }
    // เลขห้องนำหน้าคำขอ เช่น 507 ขอความช่วยเหลือจอไม่ติด
    // ไม่ใช้เลขทั่วไป เช่น 11 โมง, 2 ตัว หรือ print_44 เป็นห้อง
    elseif (preg_match('/^\s*([0-9]{3,4})\s*(?=ขอความช่วยเหลือ|ขอให้ช่วย|รบกวน|ช่วยดู|แจ้ง)/u', $text, $matches)) {
        $location = $matches[1];
    }
    else {
        // คงรูปแบบสถานที่เดิมที่ไม่ใช่เลขห้อง เช่น ตึก ชั้น และบริเวณคณะ
        preg_match('/(หน้า|หลัง|ข้าง|ใน|นอก|บริเวณ)?\s*(ห้อง\s*[a-zA-Z0-9]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+|ชั้น\s*[0-9]+|ลานจอดรถ|โรงรถ|ทางเข้า|ทางออก|หน้าคณะ|หลังคณะ|ข้างคณะ|ในคณะ)/iu', $text, $matches);
        if (!empty($matches[0])) {
            $location = trim($matches[0]);
        }
    }

    return [$category, $location];
}


// ชื่อที่ผู้แจ้งเลือกใช้ ไม่บังคับชื่อจริงและไม่ตัดเหลือเพียงคำแรก
function reporterChosenName($text, $phone) {
    $name = (string) $text;
    if (is_string($phone) && $phone !== '') {
        $pos = mb_strpos($name, $phone, 0, 'UTF-8');
        if ($pos !== false) {
            $name = mb_substr($name, 0, $pos, 'UTF-8')
                . mb_substr($name, $pos + mb_strlen($phone, 'UTF-8'), null, 'UTF-8');
        }
    }
    $name = preg_replace('/^\s*(?:เปลี่ยนชื่อ|แก้ไขข้อมูล|แก้ชื่อ|เปลี่ยนเบอร์|แก้เบอร์)\s*[:：]?\s*/u', '', $name);
    $name = preg_replace('/^\s*(?:ชื่อเล่น|ชื่อที่ใช้ติดต่อ|ชื่อ[\s-]*นามสกุล|ชื่อ[\s-]*สกุล|ชื่อ)\s*[:：]?\s*/u', '', $name);
    $name = preg_replace('/(?:\s|^)(?:เบอร์โทรศัพท์|เบอร์โทร|เบอร์|โทรศัพท์|tel)\s*[:：]?\s*$/iu', '', $name);
    $name = preg_replace('/\s+(?:นะครับ|นะคะ|ครับ|ค่ะ|คะ|คับ)\s*$/u', '', $name);
    return trim($name);
}

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
        $userId = $event['source']['userId'];
        $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;

        $line_name = get_line_profile($userId, null, $channelAccessToken);
        $words_to_remove = ['เปลี่ยนชื่อ', 'เปลี่ยนเบอร์', 'แก้ไขข้อมูล', 'แก้ชื่อ', 'เปี่ยนชื่อ', 'เปลียนชื่อ', 'เปลี่ชื่อ', 'เปี่ยนเบอร์', 'แก้เบอร์', 'จาก', 'เดิม', 'ด้วย', 'เป็น', 'ใหม่', 'ชื่อ-สกุล', 'ชื่อ-นามสกุล', 'ชื่อ', 'นามสกุล', 'เบอร์โทรศัพท์', 'เบอร์โทร','เบอโทร', 'เบอร์', 'โทรศัพท์', 'โทร', 'tel', 'นะคะ', 'นะครับ', 'ค่ะ', 'ครับ', 'คับ', 'จ้า', 'จ๊ะ', 'นะ', 'หน่อย', 'และ', 'หรือ', 'ทั้ง', 'ได้ไหม', 'ได้มั้ย', 'คะ', 'ข้อมูล', 'กับ', 'ปรับ', 'ปรุง', 'แก้', 'นี้', 'เบอ', 'จ้ะ', 'ค้าบ', 'อยาก', 'ต้องการ', 'ช่วย', 'ให้', 'ค่า', 'ต้อง', 'การ', 'ครัช', 'ได้', 'มั้ย', 'ไหม', 'โท', 'สกุล', 'เอา', 'ออก', 'แล้ว', 'ถ้า', 'หาก', 'สอง', 'อย่าง', '2', ':', '-', ','];
        $prefixes_to_strip = ['นางสาว', 'น.ส.', 'น.ส', 'น.', 'นส.', 'นส', 'น', 'ด.ช.', 'ด.ญ.', 'นาย', 'นาง', 'คุณ'];

        // ✨ บล็อกข้อความในกลุ่มเพื่อกันบอทก่อกวน "ยกเว้น" คำสั่ง "ผูกบัญชี"
        if ($event['type'] == 'message' && !empty($groupId)) {
            $is_bind_cmd = false;
            if ($event['message']['type'] == 'text') {
                if (mb_strpos(trim($event['message']['text']), 'ผูกบัญชี') === 0) {
                    $is_bind_cmd = true;
                }
            }
            if (!$is_bind_cmd) {
                continue;
            }
        }

        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            
            $stmt_chk_user = $conn->prepare("SELECT id FROM line_users WHERE line_user_id = ?");
            $stmt_chk_user->bind_param("s", $userId);
            $stmt_chk_user->execute();
            if ($stmt_chk_user->get_result()->num_rows === 0) {
                send_reply($replyToken, ['type' => 'text', 'text' => "กรุณาส่งชื่อที่ต้องการใช้และเบอร์โทรค่ะ
ใช้ชื่อเล่น ชื่อทั่วไป หรือชื่อเต็มได้ตามสะดวก

ตัวอย่าง: แพรว 0812345678"], $channelAccessToken);
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
                if ($latest_job && $latest_job['status'] !== 'ซ่อมเสร็จแล้ว' && empty($latest_job['image_path'])) {
                    if ((time() - strtotime($latest_job['created_at'])) <= 300) {
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
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ ยืนยันตัวตนสำเร็จ\n\nยินดีต้อนรับ เจ้าหน้าที่ $tech_name\n($tech_dept)\n\nคุณสามารถเริ่มรับงานซ่อมได้เลยค่ะ 🛠️"], $channelAccessToken);
                        }
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ รหัสลับไม่ถูกต้อง หรือรหัสนี้ถูกใช้งานไปแล้วค่ะ"], $channelAccessToken);
                    }
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ รูปแบบไม่ถูกต้อง\nกรุณาพิมพ์: ผูกบัญชี [รหัส 4 หลัก]"], $channelAccessToken);
                }
                continue; 
            }

            $is_edit_cmd = false;
            foreach ($edit_keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    $is_edit_cmd = true;
                    break;
                }
            }

            if ($is_edit_cmd) {
                preg_match('/(0[0-9]{8,9})/', $text, $matches);
                $phone = !empty($matches[1]) ? $matches[1] : null;

                $real_name = reporterChosenName($text, $phone);

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
                    send_reply($replyToken, ['type' => 'text', 'text' => "✅ อัปเดตข้อมูลของคุณเรียบร้อยแล้วค่ะ!\n\nชื่อ: $real_name\nเบอร์โทร: $phone\n\nระบบจะใช้ข้อมูลนี้สำหรับคำขอครั้งต่อไปค่ะ"], $channelAccessToken);
                } else {
                    send_reply($replyToken, ['type' => 'text', 'text' => "🚨 ระบบเกิดข้อผิดพลาดในการอัปเดตข้อมูลค่ะ"], $channelAccessToken);
                }
                continue;
            }

            $stmt_chk_user = $conn->prepare("SELECT real_name, phone_number FROM line_users WHERE line_user_id = ?");
            $stmt_chk_user->bind_param("s", $userId);
            $stmt_chk_user->execute();
            $user_reg = $stmt_chk_user->get_result()->fetch_assoc();

            if (!$user_reg) {
                
                if (preg_match('/(0[0-9]{8,9})/', $text, $matches)) {
                    $phone = $matches[1];
                    $real_name = reporterChosenName($text, $phone);
                    
                    if ($real_name === '') {
                        send_reply($replyToken, ['type' => 'text', 'text' => "กรุณาส่งชื่อที่ต้องการใช้และเบอร์โทรค่ะ ใช้ชื่อเล่นได้ เช่น แพรว 0812345678"], $channelAccessToken);
                        continue;
                    }
                    


                    if (!empty($real_name)) {
                        $stmt = $conn->prepare("INSERT INTO line_users (line_user_id, line_display_name, real_name, phone_number) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE line_display_name=?, real_name=?, phone_number=?");
                        $stmt->bind_param("sssssss", $userId, $line_name, $real_name, $phone, $line_name, $real_name, $phone);
                        
                        if ($stmt->execute()) {
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกข้อมูลแล้วค่ะ\nชื่อที่ใช้ติดต่อ: $real_name\nเบอร์โทร: $phone\n\nส่งคำขอพร้อมสถานที่ได้เลยค่ะ เช่น แอร์ไม่เย็น ห้อง 901"], $channelAccessToken);
                        } else {
                            send_reply($replyToken, ['type' => 'text', 'text' => "🚨 ระบบเกิดข้อผิดพลาดในการบันทึกข้อมูลค่ะ"], $channelAccessToken);
                        }
                        continue;
                    }
                }

                $stmt_check_review = $conn->prepare("SELECT id, ticket_no, review_comment FROM repairs WHERE line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว' ORDER BY id DESC LIMIT 1");
                if ($stmt_check_review) {
                    $stmt_check_review->bind_param("s", $userId);
                    $stmt_check_review->execute();
                    $recent_job = $stmt_check_review->get_result()->fetch_assoc();

                    if ($recent_job && mb_strlen($text, 'UTF-8') <= 255 && !preg_match('/(ห้อง|อาคาร|ตึก|ชั้น)/', $text)) {
                        $current_rev = (string)$recent_job['review_comment'];
                        $new_rev = trim($current_rev . " " . $text);
                        $stmt_upd = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE id = ?");
                        $stmt_upd->bind_param("si", $new_rev, $recent_job['id']);
                        $stmt_upd->execute();
                        
                        $text_clean_rev = mb_strtolower(str_replace([' ', "\n", 'ค่ะ', 'ครับ', 'จ้า', 'นะ', 'พี่'], '', $text), 'UTF-8');
                        $greetings_rev = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'รับทราบ', 'เยี่ยม', 'แต้ง'];
                        $is_greeting_rev = false;
                        foreach ($greetings_rev as $g) {
                            if (mb_strpos($text_clean_rev, $g) !== false) {
                                $is_greeting_rev = true; break;
                            }
                        }
                        if ($is_greeting_rev && mb_strlen($text_clean_rev, 'UTF-8') < 40) {
                            send_reply($replyToken, ['type' => 'text', 'text' => "ด้วยความยินดีค่ะ 💖 หากมีปัญหาเพิ่มเติมแจ้งได้ตลอดเลยนะคะ"], $channelAccessToken);
                        } else {
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกรีวิวเพิ่มเติมให้ใบงาน {$recent_job['ticket_no']} เรียบร้อยค่ะ ขอบคุณมากนะคะ 🙏✨"], $channelAccessToken);
                        }
                        continue;
                    }
                }

                send_reply($replyToken, ['type' => 'text', 'text' => "กรุณาส่งชื่อที่ต้องการใช้และเบอร์โทรค่ะ
ใช้ชื่อเล่น ชื่อทั่วไป หรือชื่อเต็มได้ตามสะดวก

ตัวอย่าง: แพรว 0812345678"], $channelAccessToken);
                continue;
            }

            // ✨ เพิ่มการดักจับการสอบถามข้อมูลรถ (ไม่เปิดใบงาน)
            $text_clean_car = str_replace(' ', '', $text);
            if (mb_strpos($text_clean_car, 'รถว่าง') !== false || 
                mb_strpos($text_clean_car, 'จองรถ') !== false || 
                mb_strpos($text_clean_car, 'คิวรถ') !== false ||
                mb_strpos($text_clean_car, 'ขอใช้รถ') !== false) {
                
                $car_reply = "🚐 สำหรับการติดต่อสอบถามคิวรถ หรือจองรถคณะ\n\nรบกวนติดต่อ 'ฝ่ายยานยนต์' โดยตรงนะคะ 💖";
                send_reply($replyToken, ['type' => 'text', 'text' => $car_reply], $channelAccessToken);
                continue;
            }

            list($category, $location) = extract_repair_info($text);

            if ($category !== "ไม่ระบุปัญหา" || $location !== "ไม่ระบุสถานที่") {
                
                $ticket_no = "MR-" . rand(1000, 9999);
                $status = "รอรับเรื่อง"; 
                $image_path = null; 
                
                // เก็บคำพูดผู้แจ้งครบทั้งประโยค ไม่ตัดสถานที่ คำขอ หรือคำว่าด่วน
                $problem = $text;

                if ($category === "ไม่ระบุปัญหา") {
                    $category = "ขอความช่วยเหลือ/บริการ";
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
                    $replyText = "✅ รับคำขอเรียบร้อยแล้วค่ะ\n\n📌 เลขที่ใบงาน: $ticket_no\n📌 เรื่องที่แจ้ง: $category\n📍 สถานที่: $location\n📝 ข้อความจากผู้แจ้ง: $problem\n\nระบบจะแจ้งเมื่อเจ้าหน้าที่รับงานค่ะ";
                    if ($image_path) {
                        $replyText .= "\n📸 (ระบบได้รับและแนบรูปภาพของคุณเข้าไปในใบงานเรียบร้อยแล้วค่ะ)";
                    }
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

                    $short_reporter_name = trim($user_reg['real_name']);

                    $flex_details = [
                        ['type' => 'text', 'text' => "ปัญหา: $category", 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                        ['type' => 'text', 'text' => "สถานที่: $location", 'size' => 'xs', 'color' => '#333333', 'wrap' => true],
                        ['type' => 'text', 'text' => "ผู้แจ้ง: ".$short_reporter_name." (".$user_reg['phone_number'].")", 'size' => 'xs', 'color' => '#666666', 'wrap' => true],
                        ['type' => 'text', 'text' => "รายละเอียด: $problem", 'size' => 'xs', 'color' => '#ef4444', 'wrap' => true]
                    ];
                    if ($image_path) {
                        $flex_details[] = ['type' => 'text', 'text' => "📸 (มีรูปภาพแนบในระบบ)", 'size' => 'xs', 'color' => '#3b82f6', 'weight' => 'bold', 'margin' => 'sm'];
                    }

                    $pushMsg = [
                        'type' => 'flex',
                        'altText' => 'คำขอรับบริการใหม่: '.$ticket_no,
                        'contents' => [
                            'type' => 'bubble',
                            'size' => 'kilo',
                            'body' => [
                                'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '15px',
                                'contents' => [
                                    ['type' => 'text', 'text' => '🔔 คำขอรับบริการใหม่', 'weight' => 'bold', 'color' => '#ef4444', 'size' => 'xs'],
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
                    $real_name = reporterChosenName($text, $phone);
                    
                    if (mb_strlen($text, 'UTF-8') < 50) {
                        if (empty($real_name)) {
                            $real_name = $user_reg['real_name']; 
                        }
    

                        $stmt = $conn->prepare("UPDATE line_users SET line_display_name=?, real_name=?, phone_number=? WHERE line_user_id=?");
                        $stmt->bind_param("ssss", $line_name, $real_name, $phone, $userId);
                        $stmt->execute();
                        
                        send_reply($replyToken, ['type' => 'text', 'text' => "✅ อัปเดตข้อมูลของคุณเรียบร้อยแล้วค่ะ!\n\nชื่อ: $real_name\nเบอร์โทร: $phone\n\nระบบจะใช้ข้อมูลนี้สำหรับคำขอครั้งต่อไปค่ะ"], $channelAccessToken);
                        continue;
                    }
                }

                $stmt_check_review = $conn->prepare("SELECT id, ticket_no, review_comment FROM repairs WHERE line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว' ORDER BY id DESC LIMIT 1");
                if ($stmt_check_review) {
                    $stmt_check_review->bind_param("s", $userId);
                    $stmt_check_review->execute();
                    $recent_job = $stmt_check_review->get_result()->fetch_assoc();

                    if ($recent_job && mb_strlen($text, 'UTF-8') <= 255 && !preg_match('/(ห้อง|อาคาร|ตึก|ชั้น)/', $text)) {
                        $current_rev = (string)$recent_job['review_comment'];
                        $new_rev = trim($current_rev . " " . $text);
                        
                        $stmt_update_review = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE id = ?");
                        $stmt_update_review->bind_param("si", $new_rev, $recent_job['id']);
                        $stmt_update_review->execute();
                        
                        $text_clean_rev = mb_strtolower(str_replace([' ', "\n", 'ค่ะ', 'ครับ', 'จ้า', 'นะ', 'พี่'], '', $text), 'UTF-8');
                        $greetings_rev = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'รับทราบ', 'เยี่ยม', 'แต้ง'];
                        $is_greeting_rev = false;
                        foreach ($greetings_rev as $g) {
                            if (mb_strpos($text_clean_rev, $g) !== false) {
                                $is_greeting_rev = true; break;
                            }
                        }
                        
                        if ($is_greeting_rev && mb_strlen($text_clean_rev, 'UTF-8') < 40) {
                            send_reply($replyToken, ['type' => 'text', 'text' => "ด้วยความยินดีค่ะ 💖 หากมีปัญหาเพิ่มเติมแจ้งได้ตลอดเลยนะคะ"], $channelAccessToken);
                        } else {
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ บันทึกรีวิวเพิ่มเติมให้ใบงาน {$recent_job['ticket_no']} เรียบร้อยค่ะ ขอบคุณมากนะคะ 🙏✨"], $channelAccessToken);
                        }
                        continue;
                    }
                }
                
                $text_clean = mb_strtolower(str_replace([' ', "\n", 'ค่ะ', 'ครับ', 'จ้า', 'นะ', 'พี่'], '', $text), 'UTF-8');
                $greetings = ['ขอบคุณ', 'ขอบคุน', 'ขอบใจ', 'ok', 'โอเค', 'รับทราบ', 'เยี่ยม', 'แต้ง'];
                $is_greeting = false;
                foreach ($greetings as $g) {
                    if (mb_strpos($text_clean, $g) !== false) {
                        $is_greeting = true; break;
                    }
                }
                if ($is_greeting && mb_strlen($text_clean, 'UTF-8') < 40) {
                    send_reply($replyToken, ['type' => 'text', 'text' => "ด้วยความยินดีค่ะ 💖 หากมีปัญหาเพิ่มเติมแจ้งได้ตลอดเลยนะคะ"], $channelAccessToken);
                    continue; 
                }
            }
        }
        elseif ($event['type'] == 'postback') {
            parse_str($event['postback']['data'], $postbackData);

            if (isset($postbackData['action']) && isset($postbackData['ticket'])) {
                $ticket_no = $postbackData['ticket'];

                if ($postbackData['action'] == 'accept') {
                    $stmt_check = $conn->prepare("SELECT id, status, line_user_id, technician_id, technician_name, equipment_type, location, reporter_name, phone_number, line_display_name FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job) {
                        if ($job['status'] == 'รอรับเรื่อง' && empty($job['technician_id'])) {
                            
                            $stmt_tech = $conn->prepare("SELECT * FROM technicians WHERE line_user_id = ? AND approval_status = 'อนุมัติแล้ว'");
                            $stmt_tech->bind_param("s", $userId);
                            $stmt_tech->execute();
                            $tech_result = $stmt_tech->get_result()->fetch_assoc();

                            if ($tech_result) {
                                $tech_name = $tech_result['full_name'];
                                $tech_phone = !empty($tech_result['phone']) ? $tech_result['phone'] : "-"; 
                                $tech_dept = isset($tech_result['department']) && !empty($tech_result['department']) ? $tech_result['department'] : "ทีมเจ้าหน้าที่";
                            } else {
                                send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ระบบปฏิเสธ: คุณยังไม่ได้ผูกบัญชีเจ้าหน้าที่เทคนิคค่ะ\nกรุณาพิมพ์ 'ผูกบัญชี [รหัส 4 หลัก]' เพื่อใช้งานนะคะ"], $channelAccessToken);
                                continue;
                            }

                            $tech_id = (int) $tech_result['id'];
                            $stmt = $conn->prepare("UPDATE repairs SET status = 'กำลังดำเนินการ', technician_id = ?, technician_name = ?, received_at = COALESCE(received_at, CURRENT_TIMESTAMP) WHERE ticket_no = ? AND status = 'รอรับเรื่อง' AND (technician_id IS NULL OR technician_id = 0)");
                            $stmt->bind_param("iss", $tech_id, $tech_name, $ticket_no);
                            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                                send_reply($replyToken, ['type' => 'text', 'text' => "ไม่สามารถรับงานได้ ใบงานอาจมีผู้รับแล้ว กรุณาตรวจสอบอีกครั้งค่ะ"], $channelAccessToken);
                                continue;
                            }

                            $disp_name = trim($job['reporter_name']);

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
                                            ['type' => 'text', 'text' => "เจ้าหน้าที่ $tech_name", 'weight' => 'bold', 'size' => 'md', 'margin' => 'xs'],
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
                                                'action' => ['type' => 'uri', 'label' => '📝 จัดการ/เพิ่มหมายเหตุ', 'uri' => "https://autism-unfreeze-snooze.ngrok-free.dev/repair/update_repair.php?id=" . $job['id']]
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                            
                            send_reply($replyToken, $replyMsg, $channelAccessToken);
                            
                            $pushMsgToUser = ['type' => 'text', 'text' => "👨‍🔧 เจ้าหน้าที่ $tech_name รับงานของคุณแล้วค่ะ\n📞 เบอร์ติดต่อ: $tech_phone\n\nเจ้าหน้าที่จะตรวจสอบและประสานงานค่ะ"];
                            send_push($job['line_user_id'], $pushMsgToUser, $channelAccessToken);

                            if(!empty($line_group_id)) {
                                $groupMessage = "📢 มีเจ้าหน้าที่รับงานแล้วจ้า!\n" .
                                                "👨‍🔧 เจ้าหน้าที่: " . $tech_name . "\n" .
                                                "💻 งาน: " . $job['equipment_type'] . " (" . $job['location'] . ")";
                                
                                $postDataGroup = [
                                    'to' => $line_group_id,
                                    'messages' => [['type' => 'text', 'text' => $groupMessage]]
                                ];
                                send_push($line_group_id, $postDataGroup, $channelAccessToken);
                            }

                        } else {
                            $taken_by = !empty($job['technician_name']) ? $job['technician_name'] : "เจ้าหน้าที่ท่านอื่น";
                            send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ใบงาน $ticket_no ถูกรับไปแล้วโดยเจ้าหน้าที่ $taken_by"], $channelAccessToken);
                        }
                    }
                }
                elseif ($postbackData['action'] == 'close') {
                    $stmt_check = $conn->prepare("SELECT id, status, line_user_id, technician_id, technician_name, reporter_name FROM repairs WHERE ticket_no = ?");
                    $stmt_check->bind_param("s", $ticket_no);
                    $stmt_check->execute();
                    $job = $stmt_check->get_result()->fetch_assoc();

                    if ($job) {
                        if ($job['status'] == 'กำลังดำเนินการ' || $job['status'] == 'ช่างรับเรื่องแจ้งซ่อมแล้ว') {
                            
                            $stmt_tech = $conn->prepare("SELECT id, full_name FROM technicians WHERE line_user_id = ? AND approval_status = 'อนุมัติแล้ว'");
                            $stmt_tech->bind_param("s", $userId);
                            $stmt_tech->execute();
                            $tech_result = $stmt_tech->get_result()->fetch_assoc();

                            if ($tech_result) {
                                $clicker_name = $tech_result['full_name'];
                                
                                if ((int) ($job['technician_id'] ?? 0) > 0 && (int) $tech_result['id'] === (int) $job['technician_id']) {
                                    
                                    $clicker_id = (int) $tech_result['id'];
                                    $stmt = $conn->prepare("UPDATE repairs SET status = 'ซ่อมเสร็จแล้ว', completed_at = CURRENT_TIMESTAMP WHERE ticket_no = ? AND technician_id = ? AND status IN ('กำลังดำเนินการ', 'ช่างรับเรื่องแจ้งซ่อมแล้ว')");
                                    $stmt->bind_param("si", $ticket_no, $clicker_id);
                                    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                                        send_reply($replyToken, ['type' => 'text', 'text' => "ไม่สามารถปิดงานได้ สิทธิ์หรือสถานะใบงานเปลี่ยนแล้วค่ะ"], $channelAccessToken);
                                        continue;
                                    }

                                    $close_reply = "🎉 บันทึกปิดงานใบงาน $ticket_no เรียบร้อยค่ะ ระบบได้ส่งแบบประเมินให้ผู้แจ้งแล้ว";
                                    send_reply($replyToken, ['type' => 'text', 'text' => $close_reply], $channelAccessToken);

                                    $short_reporter_name_close = trim($job['reporter_name']);

                                    $review_msg = [
                                        'type' => 'flex',
                                        'altText' => 'ประเมินผลการซ่อม',
                                        'contents' => [
                                            'type' => 'bubble',
                                            'body' => [
                                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                                'contents' => [
                                                    ['type' => 'text', 'text' => '⭐ ประเมินผลการซ่อม', 'weight' => 'bold', 'color' => '#ffb700', 'size' => 'md'],
                                                    ['type' => 'text', 'text' => "ถึงคุณ ".$short_reporter_name_close, 'weight' => 'bold', 'color' => '#3b82f6', 'size' => 'xs'],
                                                    ['type' => 'text', 'text' => 'เจ้าหน้าที่ '.$clicker_name.' ดำเนินการซ่อมเสร็จเรียบร้อยแล้ว!', 'weight' => 'bold', 'size' => 'xs', 'wrap' => true],
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
                                    send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ ระบบปฏิเสธ: เฉพาะเจ้าหน้าที่ ".$job['technician_name']." ที่สามารถแจ้งปิดงานใบงาน $ticket_no ได้ค่ะ"], $channelAccessToken);
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