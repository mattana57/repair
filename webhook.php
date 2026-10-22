<?php
require 'db_connect.php';
require 'env.php';
require_once __DIR__ . '/repair_media.php';

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


// แปลงเฉพาะข้อความสถานที่ ไม่แก้ข้อความรายละเอียดของผู้แจ้ง
function mbsThaiLocationNumber($word) {
    $digits = ['ศูนย์'=>'0','หนึ่ง'=>'1','เอ็ด'=>'1','สอง'=>'2','ยี่'=>'2','สาม'=>'3','สี่'=>'4','ห้า'=>'5','หก'=>'6','เจ็ด'=>'7','แปด'=>'8','เก้า'=>'9'];
    preg_match_all('/ศูนย์|หนึ่ง|เอ็ด|สอง|ยี่|สาม|สี่|ห้า|หก|เจ็ด|แปด|เก้า|สิบ|ร้อย|พัน/u', $word, $m);
    if (!$m[0]) { return ''; }
    if (!preg_match('/สิบ|ร้อย|พัน/u', $word)) {
        $out = ''; foreach ($m[0] as $token) { $out .= $digits[$token]; }
        return $out;
    }
    $total = 0; $digit = 0;
    foreach ($m[0] as $token) {
        if (isset($digits[$token])) { $digit = (int)$digits[$token]; }
        else {
            $unit = ['สิบ'=>10,'ร้อย'=>100,'พัน'=>1000][$token];
            $total += ($digit ?: 1) * $unit; $digit = 0;
        }
    }
    return (string)($total + $digit);
}

function mbsNormalizeLocationText($text) {
    $text = strtr($text, ['๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4','๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9']);
    $number = '(?:ศูนย์|หนึ่ง|เอ็ด|สอง|ยี่|สาม|สี่|ห้า|หก|เจ็ด|แปด|เก้า|สิบ|ร้อย|พัน)';
    return preg_replace_callback('/(ชั้น\s*(?:ที่\s*)?|ห้อง\s*(?:(?:หมายเลข|เลขที่|เรียน|แลป|แล็บ|แลบ|lab)\s*)?)(' . $number . '(?:\s*' . $number . ')*)/iu', function($m) {
        return $m[1] . mbsThaiLocationNumber($m[2]);
    }, $text);
}

function mbsNamedRoomAndFloor($text) {
    $parts = [];
    if (preg_match('/ห้อง(?:พักอาจารย์|น้ำ(?:\s*(?:สำหรับ\s*)?(?:อาจารย์|บุคลากร|เจ้าหน้าที่|ผู้พิการ|ชาย|หญิง))?|ประชุม|สำนักงาน|ธุรการ|คอมพิวเตอร์|สมุด|พยาบาล|เก็บของ|พักเจ้าหน้าที่|พักครู|ครัว|อาหาร|ปฏิบัติการ)/u', $text, $m)) {
        $parts[] = trim($m[0]);
    }
    if (preg_match('/ชั้น\s*(?:ที่\s*)?([0-9]{1,3})(?![0-9])/u', $text, $m)) {
        $parts[] = 'ชั้น ' . $m[1];
    } elseif (preg_match('/ชั้น\s*(ใต้ดิน|ล่าง|บน|ดาดฟ้า)/u', $text, $m)) {
        $parts[] = 'ชั้น' . $m[1];
    }
    return implode(' ', $parts);
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

    $location_text = mbsNormalizeLocationText($text);
    // รูปแบบอาคารและห้อง เช่น SBB603, SBB-401, Acc.Biz 204
    if (preg_match('/(?<![a-z0-9])(?:(sbb)|(acc[.\s-]*biz))[\s-]*([0-9]{3,4})(?![a-z0-9])/iu', $location_text, $matches)) {
        $building = !empty($matches[1]) ? 'SBB' : 'Acc.Biz';
        $location = $building . ' ' . $matches[3];
    }
    // ห้องที่ระบุชัดเจน เก็บเฉพาะเลข เช่น ห้อง701 หรือ ห้องแลป 302
    elseif (preg_match('/ห้อง\s*(?:เรียน|แลป|แล็บ|แลบ|lab)?\s*([0-9]{3,4})(?![0-9])/iu', $location_text, $matches)) {
        $location = $matches[1];
    }
    // เลขห้องนำหน้าคำขอ เช่น 507 ขอความช่วยเหลือจอไม่ติด
    // ไม่ใช้เลขทั่วไป เช่น 11 โมง, 2 ตัว หรือ print_44 เป็นห้อง
    elseif (preg_match('/^\s*([0-9]{3,4})\s*(?=ขอความช่วยเหลือ|ขอให้ช่วย|รบกวน|ช่วยดู|แจ้ง)/u', $location_text, $matches)) {
        $location = $matches[1];
    }
    elseif (($named_location = mbsNamedRoomAndFloor($location_text)) !== '') {
        $location = $named_location;
    }
    else {
        // คงรูปแบบสถานที่เดิมที่ไม่ใช่เลขห้อง เช่น ตึก ชั้น และบริเวณคณะ
        preg_match('/(หน้า|หลัง|ข้าง|ใน|นอก|บริเวณ)?\s*(ห้อง\s*[a-zA-Z0-9]+|ตึก\s*[a-zA-Z0-9ก-๙]+|อาคาร\s*[a-zA-Z0-9ก-๙]+|ชั้น\s*[0-9]+|ลานจอดรถ|โรงรถ|ทางเข้า|ทางออก|หน้าคณะ|หลังคณะ|ข้างคณะ|ในคณะ)/iu', $location_text, $matches);
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


// รูปแบบ Flex ใช้สีน้ำเงินเข้ม สีเทา และพื้นขาว ไม่เปลี่ยนข้อมูลหรือคำสั่ง
require_once __DIR__.'/line_job_sync.php';

// เลขแสดงผลยาว 19 ตัว รองรับ ticket_no VARCHAR(20)
function mbsNewTicketNumber() {
    return 'MR-' . strtoupper(bin2hex(random_bytes(8)));
}

// ตรวจเลขก่อนบันทึก และลองใหม่เมื่อ UNIQUE ปฏิเสธเลขชนกัน
function mbsInsertRepair($conn, $stmt, &$ticket_no) {
    $lock = $conn->query("SELECT GET_LOCK('repair_ticket_number_v1', 5) AS acquired");
    $row = $lock ? $lock->fetch_assoc() : null;
    if (!$row || (int)$row['acquired'] !== 1) { return false; }
    $daily_transaction = false;
    try {
        if (!$conn->begin_transaction()) { throw new RuntimeException('Cannot start request transaction'); } $daily_transaction=true;
        $check = $conn->prepare('SELECT id FROM repairs WHERE ticket_no = ? LIMIT 1');
        if (!$check) { return false; }
        $check->bind_param('s', $ticket_no);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $ticket_no = mbsNewTicketNumber();
                continue;
            }
            try {
                if ($stmt->execute()) {
                    $created_id=(int)$stmt->insert_id;
                    $created_job=mbsSyncRow($conn,'SELECT * FROM repairs WHERE id=?','i',$created_id);
                    mbsSyncLabel($conn,$created_job);
                    if (!$conn->commit()) { throw new RuntimeException('Cannot commit request'); }
                    $daily_transaction=false; return true;
                }
                if ((int)$stmt->errno !== 1062) { return false; }
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() !== 1062) {
                    error_log('LINE repair insert failed; code=' . (int)$e->getCode());
                    return false;
                }
            }
            $ticket_no = mbsNewTicketNumber();
        }
        return false;
    } catch (Throwable $e) {
        error_log('LINE request numbering failed: '.get_class($e)); return false;
    } finally {
        if ($daily_transaction) { $conn->rollback(); }
        $conn->query("SELECT RELEASE_LOCK('repair_ticket_number_v1')");
    }
}

// ปุ่มใหม่ระบุ id; ปุ่มเก่าใช้ได้เฉพาะเลขที่ไม่กำกวม
function mbsResolveRepair($conn, $data) {
    if (array_key_exists('id', $data)) {
        $id = is_scalar($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { return null; }
        $stmt = $conn->prepare('SELECT id, ticket_no, line_user_id, status FROM repairs WHERE id = ?');
        $stmt->bind_param('i', $id);
    } else {
        $ticket = $data['ticket'] ?? null;
        // เลขนี้เคยซ้ำ: ห้ามปุ่มเก่าแม้ภายหลังเปลี่ยนเลขหนึ่งรายการแล้ว
        if (!is_string($ticket) || $ticket === '' || $ticket === 'MR-5208') { return null; }
        $stmt = $conn->prepare('SELECT id, ticket_no, line_user_id, status FROM repairs WHERE ticket_no = ? LIMIT 2');
        $stmt->bind_param('s', $ticket);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $job;
}

// อ่านสถานะเว็บทุกครั้งที่เจ้าหน้าที่ทำรายการผ่าน LINE ไม่จำสถานะไว้ใน Session
function mbsLineStaffAllowed($account) {
    return is_array($account)
        && (int)($account['web_account_id'] ?? 0) > 0
        && strtolower((string)($account['web_role'] ?? '')) === 'technician'
        && (int)($account['web_is_active'] ?? 0) === 1
        && empty($account['web_deleted_at'])
        && ($account['approval_status'] ?? '') === 'อนุมัติแล้ว';
}
function mbsLineStaffAccount($conn, $line_user_id) {
    $stmt=$conn->prepare("SELECT t.*, u.id AS web_account_id, u.role AS web_role, u.is_active AS web_is_active, u.deleted_at AS web_deleted_at FROM technicians t LEFT JOIN users u ON u.technician_id=t.id WHERE t.line_user_id=? AND t.deleted_at IS NULL");
    if (!$stmt) { throw new RuntimeException('Cannot check LINE staff account'); }
    $stmt->bind_param('s',$line_user_id);
    if (!$stmt->execute()) { throw new RuntimeException('Cannot read LINE staff account'); }
    $res=$stmt->get_result();
    $row=$res->num_rows===1 ? $res->fetch_assoc() : null;
    $stmt->close();return $row;
}

// เมนูผู้แจ้ง: อ่านอย่างเดียว ตรวจเจ้าของด้วย LINE user ID ทุกครั้ง
function mbsRequesterQuery($db, $sql, $types = '', ...$params) {
    $stmt = $db->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Requester query prepare failed'); }
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    if (!$stmt->execute()) { throw new RuntimeException('Requester query failed'); }
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    $stmt->close();
    return $rows;
}
function mbsRequesterVisibleSql($db) {
    static $filter = null;
    if ($filter !== null) { return $filter; }
    $columns = mbsRequesterQuery($db, 'SHOW COLUMNS FROM repairs');
    $names = array_column($columns, 'Field');
    $filter = '';
    // รองรับถังขยะที่มีอยู่ โดยไม่เพิ่มหรือแก้โครงสร้างฐานข้อมูล
    if (in_array('deleted_at', $names, true)) { $filter .= ' AND deleted_at IS NULL'; }
    if (in_array('is_deleted', $names, true)) { $filter .= ' AND COALESCE(is_deleted,0)=0'; }
    return $filter;
}
function mbsRequesterText($text, $limit = 160) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8').'…' : ($text !== '' ? $text : 'ไม่ระบุ');
}
function mbsRequesterStatus($status) {
    $labels = ['ซ่อมเสร็จแล้ว'=>'ดำเนินการเสร็จแล้ว', 'ช่างรับเรื่องแจ้งซ่อมแล้ว'=>'รับเรื่องแล้ว'];
    return $labels[$status] ?? mbsRequesterText($status, 100);
}
function mbsRequesterButton($label, $data) {
    return ['type'=>'button','style'=>'secondary','height'=>'sm',
        'action'=>['type'=>'postback','label'=>$label,'data'=>$data]];
}
function mbsRequesterMenu() {
    return ['type'=>'flex','altText'=>'รายการแจ้งของฉัน: เลือกดูรายการ', 'contents'=>[
        'type'=>'bubble','body'=>['type'=>'box','layout'=>'vertical','spacing'=>'md','contents'=>[
            ['type'=>'text','text'=>'รายการแจ้งของฉัน','weight'=>'bold','size'=>'lg','wrap'=>true],
            ['type'=>'text','text'=>'ต้องการดูรายการแบบไหนคะ?','wrap'=>true]]],
        'footer'=>['type'=>'box','layout'=>'vertical','spacing'=>'sm','contents'=>[
            mbsRequesterButton('รายการที่ยังไม่เสร็จ','action=my_requests&scope=open'),
            mbsRequesterButton('ประวัติการแจ้งทั้งหมด','action=my_requests&scope=all')]]]];
}
function mbsRequesterList($db, $userId, $scope, $before = 0) {
    $sql = 'SELECT id,ticket_no,equipment_type,location,problem_desc,status,created_at FROM repairs WHERE line_user_id=?'.mbsRequesterVisibleSql($db);
    $params = [$userId]; $types = 's';
    if ($scope === 'open') {
        $sql .= " AND COALESCE(status,'') NOT IN ('ซ่อมเสร็จแล้ว','ดำเนินการเสร็จแล้ว','ปิดงานแล้ว','ยกเลิก','ยกเลิกแล้ว')";
    }
    if ($before > 0) { $sql .= ' AND id<?'; $params[] = $before; $types .= 'i'; }
    $rows = mbsRequesterQuery($db, $sql.' ORDER BY id DESC LIMIT 6', $types, ...$params);
    $more = count($rows) > 5; $rows = array_slice($rows, 0, 5);
    $title = $scope === 'open' ? 'รายการที่ยังไม่เสร็จ' : 'ประวัติการแจ้งทั้งหมด';
    if (!$rows) { return ['type'=>'text','text'=>$before > 0 ? 'ไม่มีรายการเพิ่มเติมแล้วค่ะ' : ($scope === 'open' ? 'คุณไม่มีรายการที่ยังไม่เสร็จค่ะ' : 'ยังไม่พบประวัติการแจ้งของคุณค่ะ')]; }
    $contents = [['type'=>'text','text'=>$title,'size'=>'lg','weight'=>'bold','wrap'=>true]];
    foreach ($rows as $row) {
        $contents[] = ['type'=>'separator','margin'=>'md'];
        $contents[] = ['type'=>'box','layout'=>'vertical','spacing'=>'xs','margin'=>'md','contents'=>[
            ['type'=>'text','text'=>mbsRequesterText($row['ticket_no'],40),'weight'=>'bold','wrap'=>true],
            ['type'=>'text','text'=>'สถานที่: '.mbsRequesterText($row['location'],100),'wrap'=>true,'size'=>'sm'],
            ['type'=>'text','text'=>mbsRequesterText($row['problem_desc'],100),'wrap'=>true,'size'=>'sm'],
            ['type'=>'text','text'=>'สถานะ: '.mbsRequesterStatus($row['status']),'wrap'=>true,'size'=>'sm'],
            mbsRequesterButton('ดูรายละเอียด','action=my_request_detail&id='.(int)$row['id'])]];
    }
    $footer = [];
    if ($more) { $footer[] = mbsRequesterButton('ดูเพิ่มเติม','action=my_requests&scope='.$scope.'&before='.(int)end($rows)['id']); }
    $footer[] = mbsRequesterButton('กลับไปเลือกประเภท','action=my_requests_menu');
    return ['type'=>'flex','altText'=>$title.' ('.count($rows).' รายการ)','contents'=>[
        'type'=>'bubble','size'=>'mega','body'=>['type'=>'box','layout'=>'vertical','contents'=>$contents],
        'footer'=>['type'=>'box','layout'=>'vertical','spacing'=>'sm','contents'=>$footer]]];
}
function mbsRequesterDetail($db, $userId, $id, $part = 0) {
    $rows = mbsRequesterQuery($db,'SELECT id,ticket_no,equipment_type,location,problem_desc,repair_note,status,technician_name,created_at FROM repairs WHERE id=? AND line_user_id=?'.mbsRequesterVisibleSql($db).' LIMIT 1','is',$id,$userId);
    if (!$rows) { return ['type'=>'text','text'=>'ไม่พบรายการนี้ในบัญชี LINE ของคุณค่ะ']; }
    $row = $rows[0];
    // แสดงหมายเหตุใบงาน repair_note ตามคำขอ ไม่ดึง root_cause หรือข้อมูลผู้แจ้งอื่น
    $note = trim((string)($row['repair_note'] ?? ''));
    $detail = "รายละเอียดที่แจ้ง:\n".(trim((string)$row['problem_desc']) !== '' ? (string)$row['problem_desc'] : 'ไม่ระบุ')
        ."\n\nหมายเหตุจากเจ้าหน้าที่:\n".($note !== '' ? $note : 'ยังไม่มีหมายเหตุ');
    $pages = max(1,(int)ceil(mb_strlen($detail,'UTF-8')/1500));
    if ($part >= $pages) { return ['type'=>'text','text'=>'ไม่มีรายละเอียดเพิ่มเติมแล้วค่ะ']; }
    $text = 'รายการ: '.mbsRequesterText($row['ticket_no'],40)."\nสถานที่: ".mbsRequesterText($row['location'],150)
        ."\nเรื่อง: ".mbsRequesterText($row['equipment_type'],100)."\nสถานะ: ".mbsRequesterStatus($row['status'])
        ."\nผู้รับผิดชอบ: ".(trim((string)$row['technician_name']) !== '' ? mbsRequesterText($row['technician_name'],100) : 'รอเจ้าหน้าที่รับเรื่อง')
        ."\nแจ้งเมื่อ: ".mbsRequesterText($row['created_at'],40)."\n\nข้อมูลรายการ".($pages > 1 ? ' ('.($part+1).'/'.$pages.')' : '').":\n"
        .(trim($detail) !== '' ? mb_substr($detail,$part*1500,1500,'UTF-8') : 'ไม่ระบุ');
    $items = [];
    if ($part+1 < $pages) { $items[] = ['type'=>'action','action'=>['type'=>'postback','label'=>'อ่านรายละเอียดต่อ','data'=>'action=my_request_detail&id='.$id.'&part='.($part+1)]]; }
    $items[] = ['type'=>'action','action'=>['type'=>'postback','label'=>'รายการแจ้งของฉัน','data'=>'action=my_requests_menu']];
    return ['type'=>'text','text'=>$text,'quickReply'=>['items'=>$items]];
}
function mbsRequesterHelp() {
    return ['type'=>'text','text'=>"📖 วิธีใช้งานสำหรับผู้แจ้ง\n\n1. เริ่มใช้งานครั้งแรก\nส่งชื่อที่ต้องการใช้พร้อมเบอร์โทร ใช้ชื่อเล่นได้ ไม่บังคับชื่อจริง เช่น แพรว 0812345678\n\n2. แจ้งซ่อมหรือขอรับบริการ\nพิมพ์สถานที่และสิ่งที่ต้องการในแชต เช่น ‘แอร์ห้อง 901 ไม่เย็น’ หรือ ‘ขอไมค์ลอย 2 ตัว ห้อง SBB603’ หากไม่ทราบอาการแน่ชัด บอกตามที่พบได้เลยค่ะ\n\n3. แนบรูปหรือวิดีโอ\nส่งก่อนข้อความแจ้งเรื่องได้ หรือส่งตามหลังภายใน 5 นาทีหลังสร้างรายการ ส่งไฟล์ของรายการเดิมให้ครบก่อนแจ้งเรื่องใหม่ เพื่อไม่ให้แนบผิดรายการ\nรองรับไม่เกิน 10 รูปและ 2 วิดีโอต่อรายการ รูปไม่เกิน 10 MB วิดีโอไม่เกิน 50 MB ต่อไฟล์\n\n4. ติดตามรายการ\nกด ‘รายการแจ้งของฉัน’ เลือกรายการที่ยังไม่เสร็จหรือประวัติทั้งหมด ดูครั้งละ 5 รายการ กด ‘ดูรายละเอียด’ หรือ ‘ดูเพิ่มเติม’ ได้ ระบบแสดงเฉพาะรายการของบัญชี LINE นี้\n\n5. รับแจ้งสถานะและประเมินบริการ\nระบบแจ้งเมื่อเจ้าหน้าที่รับเรื่องและดำเนินการเสร็จ ประเมินจากข้อความของรายการนั้นได้\n\n6. แก้ไขข้อมูลติดต่อ\nพิมพ์ ‘เปลี่ยนชื่อ แพรว’ หรือ ‘เปลี่ยนเบอร์ 0812345678’\n\n7. ติดต่อสอบถาม\nกด ‘ช่องทางติดต่อ’ เพื่อดูเบอร์โทร อีเมล และเวลาติดต่อค่ะ"];
}
function mbsRequesterContact() {
    return ['type'=>'text','text'=>"☎️ ช่องทางติดต่อ\n\nโทร: 0XX-XXX-XXXX\nอีเมล: support@example.com\nเวลาติดต่อ: จันทร์–ศุกร์ 08.30–16.30 น.\n\nข้อมูลสมมุติสำหรับทดสอบระบบ\nปุ่มนี้แสดงข้อมูลติดต่อ ไม่ได้เริ่มสนทนากับเจ้าหน้าที่โดยตรงค่ะ"];
}
function mbsHandleRequesterMenu($db, $event, $userId, $replyToken, $accessToken) {
    $type = $event['type'] ?? '';
    $command = null; $data = [];
    if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
        $text = trim($event['message']['text']);
        if (in_array($text,['รายการแจ้งของฉัน','งานของฉัน','ติดตามงาน','ประวัติการแจ้ง'],true)) { $command = 'my_requests_menu'; }
        elseif ($text === 'วิธีใช้งาน') { $command = 'help'; }
        elseif ($text === 'ช่องทางติดต่อ') { $command = 'contact'; }
    } elseif ($type === 'postback') {
        parse_str($event['postback']['data'] ?? '',$data);
        if (isset($data['action']) && is_string($data['action']) && in_array($data['action'],['my_requests_menu','my_requests','my_request_detail'],true)) { $command = $data['action']; }
    }
    if ($command === null) { return false; }
    // ประวัติผู้แจ้งแสดงเฉพาะแชตส่วนตัว ไม่เผยแพร่ลงกลุ่มหรือห้องรวม
    if (($event['source']['type'] ?? '') !== 'user' || !is_string($userId) || $userId === '') { return true; }
    try {
        if ($command === 'my_requests_menu') { $message = mbsRequesterMenu(); }
        elseif ($command === 'help') { $message = mbsRequesterHelp(); }
        elseif ($command === 'contact') { $message = mbsRequesterContact(); }
        elseif ($command === 'my_requests') {
            $scope = $data['scope'] ?? 'open';
            $before = filter_var($data['before'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if (!is_string($scope) || !in_array($scope,['open','all'],true) || $before === false) { throw new InvalidArgumentException('Invalid list parameters'); }
            $message = mbsRequesterList($db,$userId,$scope,$before);
        } else {
            $id = filter_var($data['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $part = filter_var($data['part'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
            if ($id === false || $id === null || $part === false) { throw new InvalidArgumentException('Invalid detail parameters'); }
            $message = mbsRequesterDetail($db,$userId,$id,$part);
        }
    } catch (InvalidArgumentException $e) {
        $message = ['type'=>'text','text'=>'คำสั่งไม่ถูกต้องค่ะ กรุณากดรายการแจ้งของฉันใหม่'];
    } catch (Throwable $e) {
        error_log('LINE requester menu failed: '.get_class($e));
        $message = ['type'=>'text','text'=>'ยังเปิดรายการไม่ได้ค่ะ กรุณาลองใหม่ภายหลัง'];
    }
    send_reply($replyToken,$message,$accessToken);
    return true;
}


$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
        $userId = $event['source']['userId'];
        $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;

        if (mbsHandleRequesterMenu($conn, $event, $userId, $replyToken, $channelAccessToken)) { continue; }

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

        if ($event['type'] == 'message' && in_array($event['message']['type'], ['image','video'], true)) {
            try {
                $registered=rmQuery($conn,'SELECT id FROM line_users WHERE line_user_id=?','s',$userId)->get_result()->fetch_assoc();
                if(!$registered){
                    send_reply($replyToken,['type'=>'text','text'=>'กรุณาส่งชื่อที่ต้องการใช้และเบอร์โทรก่อนค่ะ ใช้ชื่อเล่นได้ เช่น แพรว 0812345678'],$channelAccessToken);
                    continue;
                }
                if(($event['message']['contentProvider']['type'] ?? 'line') !== 'line'){
                    send_reply($replyToken,['type'=>'text','text'=>'กรุณาส่งรูปหรือวิดีโอจากเครื่องผ่าน LINE โดยตรงค่ะ'],$channelAccessToken);continue;
                }
                $kind=$event['message']['type'];
                $sent=isset($event['timestamp'])?(int)floor($event['timestamp']/1000):time();
                $registeredMedia=rmRegister($conn,$userId,(string)$event['message']['id'],$kind,$sent);
                if(!empty($registeredMedia['duplicate'])){continue;}
                if(isset($registeredMedia['error'])){send_reply($replyToken,['type'=>'text','text'=>$registeredMedia['error']],$channelAccessToken);continue;}
                $mediaState=rmDownload($conn,$registeredMedia['row'],$channelAccessToken);
                if($mediaState==='too_large'){
                    $notice=$kind==='image'?'รูปนี้เกิน 10 MB ค่ะ กรุณาลดขนาดแล้วส่งใหม่ ไฟล์ก่อนหน้ายังอยู่ครบ':'วิดีโอนี้เกิน 50 MB ค่ะ กรุณาลดขนาดแล้วส่งใหม่ ไฟล์ก่อนหน้ายังอยู่ครบ';
                } elseif($mediaState==='failed') {
                    $notice='รับไฟล์นี้ไม่สำเร็จค่ะ กรุณาส่งเป็นรูปภาพหรือวิดีโออีกครั้ง ไฟล์ก่อนหน้ายังอยู่ครบ';
                } else {
                    $label=$kind==='image'?'รูป':'วิดีโอ';
                    $notice=!empty($registeredMedia['ticket'])?'รับ'.$label.'สำหรับใบงาน '.$registeredMedia['ticket'].' แล้วค่ะ':'รับ'.$label.'แล้วค่ะ ส่งรายละเอียดและสถานที่เพื่อแจ้งงานได้เลย';
                    if($mediaState==='queued'){$notice.=' กำลังรอรับไฟล์ให้ครบค่ะ';}
                }
                send_reply($replyToken,['type'=>'text','text'=>$notice],$channelAccessToken);
            } catch(Throwable $e) {
                error_log('LINE media intake failed: '.get_class($e).' code='.(int)$e->getCode().' '.$e->getMessage());
                send_reply($replyToken,['type'=>'text','text'=>'ยังรับไฟล์ไม่ได้ค่ะ กรุณาแจ้งผู้ดูแลตรวจการตั้งค่าไฟล์แนบ แล้วส่งไฟล์ใหม่'],$channelAccessToken);
            }
            continue;
        }

        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $message_id = $event['message']['id']; 

            if (mb_strpos($text, 'ผูกบัญชี') === 0) {
                $code = trim(str_replace('ผูกบัญชี', '', $text));
                if (preg_match('/^[0-9]{4}$/', $code)) {
                    $stmt_check = $conn->prepare("SELECT id, full_name, department FROM technicians WHERE secret_code = ? AND approval_status = 'รอผูกบัญชี' AND deleted_at IS NULL AND EXISTS (SELECT 1 FROM users u WHERE u.technician_id=technicians.id AND LOWER(u.role)='technician' AND u.is_active=1 AND u.deleted_at IS NULL)");
                    $stmt_check->bind_param("s", $code);
                    $stmt_check->execute();
                    $res = $stmt_check->get_result()->fetch_assoc();

                    if ($res) {
                        $tech_id = $res['id'];
                        $tech_name = $res['full_name'];
                        $tech_dept = !empty($res['department']) ? $res['department'] : 'ฝ่ายงานทั่วไป';

                        $stmt_update = $conn->prepare("UPDATE technicians SET line_user_id = ?, approval_status = 'อนุมัติแล้ว', secret_code = NULL WHERE id = ? AND secret_code = ? AND approval_status = 'รอผูกบัญชี' AND deleted_at IS NULL AND (line_user_id IS NULL OR line_user_id='') AND EXISTS (SELECT 1 FROM users u WHERE u.technician_id=technicians.id AND LOWER(u.role)='technician' AND u.is_active=1 AND u.deleted_at IS NULL)");
                        $stmt_update->bind_param("sis", $userId, $tech_id, $code);
                        
                        if ($stmt_update->execute() && $stmt_update->affected_rows === 1) {
                            send_reply($replyToken, ['type' => 'text', 'text' => "✅ ยืนยันตัวตนสำเร็จ\n\nยินดีต้อนรับ เจ้าหน้าที่ $tech_name\n($tech_dept)\n\nคุณสามารถเริ่มรับงานซ่อมได้เลยค่ะ 🛠️"], $channelAccessToken);
                        } else {
                            send_reply($replyToken,['type'=>'text','text'=>'ยังผูกบัญชีไม่ได้ค่ะ รหัสหรือสถานะบัญชีอาจเปลี่ยน กรุณาติดต่อผู้ดูแล'],$channelAccessToken);
                        }
                    } else {
                        send_reply($replyToken, ['type' => 'text', 'text' => "⚠️ รหัสไม่ถูกต้อง ถูกใช้แล้ว หรือบัญชียังไม่เปิดใช้งานค่ะ กรุณาติดต่อผู้ดูแล"], $channelAccessToken);
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
                        $stmt_upd = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE id = ? AND line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว'");
                        $stmt_upd->bind_param("sis", $new_rev, $recent_job['id'], $userId);
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
                
                $ticket_no = mbsNewTicketNumber();
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

                $stmt = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, line_display_name, phone_number, line_user_id, line_message_id, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR))");
                $stmt->bind_param("sssssssssss", $ticket_no, $category, $location, $problem, $status, $user_reg['real_name'], $line_name, $user_reg['phone_number'], $userId, $message_id, $image_path);
                
                if(mbsInsertRepair($conn, $stmt, $ticket_no)) {
                    $repair_id = (int)$stmt->insert_id;
                    $created_job=mbsSyncRow($conn,'SELECT * FROM repairs WHERE id=?','i',$repair_id);
                    $daily_label=mbsSyncLabel($conn,$created_job);
                    $media_count=0;
                    try {$media_count=rmClaimPending($conn,$userId,$repair_id);}
                    catch(Throwable $e){error_log('LINE pending media claim failed: '.get_class($e).' code='.(int)$e->getCode().' '.$e->getMessage());}

                    $replyText = "✅ รับคำขอเรียบร้อยแล้วค่ะ\n$daily_label\n\n📌 เลขที่ใบงาน: $ticket_no\n📌 เรื่องที่แจ้ง: $category\n📍 สถานที่: $location\n📝 ข้อความจากผู้แจ้ง: $problem\n\nระบบจะแจ้งเมื่อเจ้าหน้าที่รับงานค่ะ";
                    if ($image_path || $media_count > 0) {
                        $replyText .= "\n📎 มีไฟล์แนบสำหรับใบงานนี้ค่ะ";
                    }
                    send_reply($replyToken, ['type' => 'text', 'text' => $replyText], $channelAccessToken);

                    $short_reporter_name = trim($user_reg['real_name']);

                    $pushMsg = mbsServiceJobCard(
                        $ticket_no, $location, $category, $problem,
                        $short_reporter_name, $user_reg['phone_number'], 'คำขอใหม่ · รอรับงาน',
                        [[
                            'type' => 'button', 'style' => 'primary', 'height' => 'md',
                            'action' => ['type' => 'postback', 'label' => 'รับงาน', 'data' => "action=accept&id=$repair_id"]
                        ]], '', '', (!empty($image_path) || $media_count > 0), false, $daily_label
                    );
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
                        
                        $stmt_update_review = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE id = ? AND line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว'");
                        $stmt_update_review->bind_param("sis", $new_rev, $recent_job['id'], $userId);
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

            if (isset($postbackData['action']) && (isset($postbackData['id']) || isset($postbackData['ticket']))) {
                if (in_array($postbackData['action'], ['accept','close','confirm_close'], true)) {
                    try { $line_staff=mbsLineStaffAccount($conn,$userId); }
                    catch (Throwable $e) {
                        error_log('LINE staff account status check failed');
                        send_reply($replyToken,['type'=>'text','text'=>'ยังตรวจสอบสถานะบัญชีไม่ได้ค่ะ กรุณาลองอีกครั้ง'],$channelAccessToken);
                        continue;
                    }
                    if (!$line_staff) {
                        send_reply($replyToken,['type'=>'text','text'=>"ยังไม่ได้ผูกบัญชีเจ้าหน้าที่ค่ะ กรุณาใช้รหัส 4 หลักที่ได้รับจากผู้ดูแลเพื่อผูกบัญชีก่อน"],$channelAccessToken);
                        continue;
                    }
                    if (!mbsLineStaffAllowed($line_staff)) {
                        send_reply($replyToken,['type'=>'text','text'=>'บัญชีเจ้าหน้าที่ถูกระงับหรือยังไม่พร้อมใช้งานค่ะ กรุณาติดต่อผู้ดูแล ระบบยังไม่อนุญาตให้รับหรือปิดงาน'],$channelAccessToken);
                        continue;
                    }
                }

                $resolved_job = mbsResolveRepair($conn, $postbackData);
                if (!$resolved_job) {
                    send_reply($replyToken, ['type' => 'text', 'text' => 'ไม่สามารถระบุใบงานจากปุ่มนี้ได้ค่ะ กรุณาใช้การ์ดใบงานล่าสุดที่ระบบส่งใหม่ หรือติดต่อผู้ดูแลให้ส่งการ์ดใหม่ค่ะ'], $channelAccessToken);
                    continue;
                }
                $repair_id = (int)$resolved_job['id'];
                $ticket_no = $resolved_job['ticket_no'];
                if (in_array($postbackData['action'], ['rate', 'add_tag'], true)
                    && ($resolved_job['line_user_id'] !== $userId || $resolved_job['status'] !== 'ซ่อมเสร็จแล้ว')) {
                    send_reply($replyToken, ['type' => 'text', 'text' => 'เฉพาะผู้แจ้งของใบงานที่ดำเนินการเสร็จแล้วเท่านั้นที่ประเมินได้ค่ะ'], $channelAccessToken);
                    continue;
                }

                if (in_array($postbackData['action'], ['accept','close','confirm_close'], true)) {
                    try {
                        $action=$postbackData['action']==='accept'?'accept':'close';
                        $result=mbsSyncTransition($conn,$repair_id,(int)$line_staff['id'],$action,$userId);
                        mbsSyncDispatchSafe($conn,$repair_id);
                        // One compact card is pushed to the group for either channel.
                        // No extra success message in the group; private commands get an acknowledgement.
                        if (($event['source']['type'] ?? '')==='user') {
                            send_reply($replyToken,['type'=>'text','text'=>$result['label'].($action==='accept'?' รับงานเรียบร้อยค่ะ':' บันทึกปิดงานเรียบร้อยค่ะ')],$channelAccessToken);
                        }
                    } catch(Throwable $e) {
                        error_log('LINE job action failed: '.get_class($e));
                        send_reply($replyToken,['type'=>'text','text'=>'ยังทำรายการไม่ได้ค่ะ รายการอาจมีผู้รับแล้ว ปิดงานแล้ว หรือสิทธิ์เปลี่ยน กรุณาเปิดรายการล่าสุด หากยังไม่ได้ให้ติดต่อผู้ดูแล'],$channelAccessToken);
                    }
                }
                elseif ($postbackData['action'] == 'rate') {
                    $score = isset($postbackData['score']) && is_scalar($postbackData['score'])
                        ? filter_var($postbackData['score'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 5]]) : false;
                    if ($score === false) { continue; }
                    $stmt = $conn->prepare("UPDATE repairs SET rating = ? WHERE id = ? AND line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว'");
                    $stmt->bind_param("iis", $score, $repair_id, $userId);
                    
                    if($stmt->execute()){
                        $thankYouMsg = [
                            'type' => 'text', 
                            'text' => "✅ บันทึกคะแนน $score ดาว สำหรับใบงาน $ticket_no เรียบร้อยค่ะ\n\n(ประทับใจส่วนไหน เลือกรีวิวด้านบน 👆 หรือพิมพ์ข้อความส่งมาในแชทได้เลยนะคะ 💬)"
                        ];
                        send_reply($replyToken, $thankYouMsg, $channelAccessToken);
                    }
                }
                elseif ($postbackData['action'] == 'add_tag') {
                    $tag = $postbackData['tag'] ?? '';
                    if (!in_array($tag, ['ดำเนินการรวดเร็วทันใจ', 'แก้ไขปัญหาได้อย่างตรงจุด', 'อธิบายและให้คำแนะนำชัดเจน', 'สุภาพเรียบร้อย บริการเต็มใจ', 'ซ่อมแซมและเก็บงานเรียบร้อย', 'ช่วยอำนวยความสะดวกได้ดีเยี่ยม'], true)) { continue; }
                    
                    $stmt_check = $conn->prepare("SELECT review_comment FROM repairs WHERE id = ?");
                    $stmt_check->bind_param("i", $repair_id);
                    $stmt_check->execute();
                    $job_rev = $stmt_check->get_result()->fetch_assoc();
                    $current_rev = $job_rev ? (string)$job_rev['review_comment'] : "";

                    if (mb_strpos($current_rev, $tag) === false) {
                        $new_rev = trim($current_rev . " [" . $tag . "]");
                        $stmt_upd = $conn->prepare("UPDATE repairs SET review_comment = ? WHERE id = ? AND line_user_id = ? AND status = 'ซ่อมเสร็จแล้ว'");
                        $stmt_upd->bind_param("sis", $new_rev, $repair_id, $userId);
                        
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
    $data = ['replyToken' => $replyToken, 'messages' => [mbsReadableFlexNode($messageData)]];
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
    $data = ['to' => $to, 'messages' => [mbsReadableFlexNode($messageData)]];
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