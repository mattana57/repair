<?php

// แสดงข้อความแจ้งเตือนให้อ่านได้บนมือถือ โดยคงสถานะ HTTP เดิม

// รูปแบบ Flex ใช้สีน้ำเงินเข้ม สีเทา และพื้นขาว ไม่เปลี่ยนข้อมูลหรือคำสั่ง
// จัดข้อความทั่วไปเป็น Flex โดยคงข้อความและปุ่ม quick reply เดิม
function mbsUpdateCompactNoticeFlex($message) {
    if (!is_array($message) || ($message['type'] ?? '') !== 'text' || !isset($message['text']) || !is_string($message['text'])) return $message;
    $text = $message['text'];
    // ถ้าข้อความยาวมาก ให้ส่งรูปแบบเดิม เพื่อไม่ตัดข้อมูลหรือทำให้ Flex เกินขนาด
    if (trim($text) === '' || mb_strlen($text, 'UTF-8') > 1800 || strlen($text) > 9000) return $message;
    $parts = preg_split('/\R/u', $text, 2);
    $alt = trim($parts[0] ?? '');
    if ($alt === '') $alt = 'ข้อความจากระบบแจ้งบริการ';
    $alt = mb_substr($alt, 0, 150, 'UTF-8');
    $flex = [
        'type' => 'flex', 'altText' => $alt,
        'contents' => ['type' => 'bubble', 'size' => 'mega',
            'body' => ['type' => 'box', 'layout' => 'vertical', 'paddingAll' => '16px',
                'contents' => [['type' => 'text', 'text' => $text, 'size' => 'md', 'color' => '#263B50', 'wrap' => true]]]]
    ];
    if (isset($message['quickReply'])) $flex['quickReply'] = $message['quickReply'];
    return $flex;
}

function mbsReadableFlexNode($node, $inside_flex = false) {
    if (!is_array($node)) { return $node; }
    // แบบประเมินคงรูปแบบเดิม ไม่ผ่านการปรับขนาดหรือสีของใบงาน
    if (($node['type'] ?? '') === 'flex' && ($node['altText'] ?? '') === 'ประเมินผลการซ่อม') {
        return $node;
    }
    $type = $node['type'] ?? null;
    $inside_flex = $inside_flex || $type === 'flex' || $type === 'bubble';
    if ($inside_flex && $type === 'bubble') {
        $node['size'] = 'mega';
    }
    if ($inside_flex && $type === 'text') {
        if (!isset($node['size']) || in_array($node['size'], ['xxs', 'xs', 'sm'], true)) {
            $node['size'] = 'md';
        }
        $node['wrap'] = true;
        $node['color'] = ($node['weight'] ?? '') === 'bold' ? '#1E3A5F' : '#222222';
    }
    if ($inside_flex && $type === 'button') {
        $node['height'] = 'md';
        if (($node['style'] ?? '') === 'primary') {
            $node['color'] = '#1E3A5F';
        }
    }
    foreach ($node as $key => $value) {
        if (is_array($value)) {
            $node[$key] = mbsReadableFlexNode($value, $inside_flex);
        }
    }
    return $node;
}

function mbsUpdateRepairNotice($message) {
    $status = http_response_code();
    if (!$status || $status < 400) {
        $status = 400;
    }
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    $back = $role === 'admin' ? 'dashboard.php?tab=repairs'
        : ($role === 'executive' ? 'executive_dashboard.php' : 'technician_home.php');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>แจ้งเตือนการทำรายการ</title><style>'
        . '*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#1e293b;'
        . 'font-family:Tahoma,Arial,sans-serif;font-size:20px;line-height:1.7;'
        . 'padding:32px 20px}main{max-width:560px;margin:8vh auto;background:#fff;'
        . 'padding:28px 24px;border:1px solid #e2e8f0;border-radius:16px}'
        . 'h1{font-size:25px;line-height:1.5;margin:0 0 16px}'
        . 'p{margin:0 0 24px;overflow-wrap:anywhere}'
        . 'a{display:inline-block;font-size:18px;font-weight:bold;padding:12px 20px;'
        . 'background:#0369a1;color:white;text-decoration:none;border-radius:10px}'
        . 'small{display:block;margin-top:20px;font-size:16px;color:#64748b}'
        . '</style></head><body><main><h1>ไม่สามารถทำรายการได้</h1><p>'
        . $safe_message . '</p><a href="' . $back . '">กลับหน้ารายการ</a>'
        . '<small>หากบัญชีไม่ถูกต้อง กรุณาออกจากระบบแล้วเข้าสู่ระบบใหม่</small>'
        . '</main></body></html>';
    exit;
}

// เรียกใช้ระบบตรวจสิทธิ์ส่วนกลางแทนการเช็คแบบแยกไฟล์
require_once 'auth_guard.php';
require_once __DIR__ . '/activity_log.php';

$back_url = 'dashboard.php?tab=repairs';
$query_params = [];
// ... (ส่วน GET source คงเดิม) ...
if (isset($_GET['source'])) {
    $source = $_GET['source'];
    $query_params['source'] = $source;
    
    if ($source === 'tech_history' && !empty($_GET['tech'])) {
        $back_url = 'dashboard.php?tab=technicians&open_history=' . urlencode($_GET['tech']);
        $query_params['tech'] = $_GET['tech'];
    } elseif ($source === 'reporter_history' && !empty($_GET['reporter'])) {
        $back_url = 'dashboard.php?tab=users&open_reporter=' . urlencode($_GET['reporter']);
        $query_params['reporter'] = $_GET['reporter'];
    } elseif ($source === 'overview' || $source === 'tech_reviews') {
        $back_url = 'dashboard.php?tab=dash';
    }
}

date_default_timezone_set('Asia/Bangkok');

$is_admin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
$is_tech = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'technician';

if (!$is_admin && !$is_tech) {
    http_response_code(403);
    mbsUpdateRepairNotice('ไม่อนุญาตให้แก้ไขใบงานนี้');
}
if (!isset($_SESSION['repair_csrf']) || !is_string($_SESSION['repair_csrf'])) {
    $_SESSION['repair_csrf'] = bin2hex(random_bytes(32));
}
$validated_repair_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
if ($validated_repair_id === false || $validated_repair_id === null) {
    http_response_code(400);
    mbsUpdateRepairNotice('เลขใบงานไม่ถูกต้อง');
}


function splitThaiEngName($fullName, $engName) {
    $th = trim((string)$fullName);
    $en = trim((string)$engName);
    if (empty($en) && !empty($th)) {
        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $th, $matches)) {
            $th = trim($matches[1]);
            $en = trim($matches[2]);
        } elseif (preg_match('/^(.*?)\s+(Mr\.|Mrs\.|Miss|Ms\.)\s*(.*)$/i', $th, $matches)) {
            $th = trim($matches[1]);
            $en = trim($matches[2]) . ' ' . trim($matches[3]);
        }
    }
    return array($th, $en);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 0) $diff = 0; 
    if ($diff < 60) return "เมื่อสักครู่";
    if ($diff < 3600) return floor($diff / 60) . " นาทีที่แล้ว";
    if ($diff < 86400) return floor($diff / 3600) . " ชั่วโมงที่แล้ว";
    if ($diff < 604800) return floor($diff / 86400) . " วันที่แล้ว";
    $thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $m = date('n', $time) - 1;
    $y = date('Y', $time) + 543;
    $d = date('j', $time);
    return "$d " . $thaiMonths[$m] . " $y";
}

$repair = null;
$repair_line_id = "";
$repair_real_name = "";

if (isset($_GET['id'])) {
    $id = $validated_repair_id;
    $sql = "SELECT * FROM repairs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $repair = $result->fetch_assoc();
    
    if (!$repair) {
        mbsUpdateRepairNotice("ไม่พบข้อมูลใบงาน");
    }

    // เจ้าหน้าที่เปิดได้เฉพาะใบงานที่ผูกกับรหัสของบัญชีตนเอง
    if ($is_tech) {
        $owner_tech_id = filter_var(
            $_SESSION['technician_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $repair_owner_id = filter_var(
            $repair['technician_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        // เปิดได้เฉพาะใบงานที่รับแล้วและมีรหัสเจ้าของตรงกับบัญชี
        if ($owner_tech_id === false
            || $repair_owner_id === false
            || $repair_owner_id !== $owner_tech_id) {
            http_response_code(403);
            mbsUpdateRepairNotice('ไม่อนุญาตให้เข้าถึงใบงานนี้');
        }
    }

    // แยกชื่อบัญชี LINE กับชื่อที่ผู้แจ้งกรอกตามข้อมูลในใบงาน
    $repair_line_id = trim((string)($repair['line_display_name'] ?? ''));
    $repair_real_name = trim((string)($repair['reporter_name'] ?? ''));

    if ($repair_line_id === '') $repair_line_id = '-';
    if ($repair_real_name === '') $repair_real_name = '-';
}

$form_action = "update_repair.php?id=" . ($repair['id'] ?? '');
if (!empty($query_params)) {
    $form_action .= "&" . http_build_query($query_params);
}
$view_url = "view_repair.php?id=" . ($repair['id'] ?? '');
if (!empty($query_params)) {
    $view_url .= "&" . http_build_query($query_params);
}

$techs_by_dept = [];
// ดึงรหัสเจ้าหน้าที่มาด้วยเพื่อแยกคนชื่อซ้ำและใช้บันทึกข้อมูล
$tech_res = $conn->query("SELECT id, full_name, department FROM technicians WHERE full_name IS NOT NULL AND full_name != ''");
if($tech_res && $tech_res->num_rows > 0){
    while($t = $tech_res->fetch_assoc()) {
        $dept = !empty($t['department']) ? $t['department'] : 'ฝ่ายงานทั่วไป';
        if (!isset($techs_by_dept[$dept])) {
            $techs_by_dept[$dept] = [];
        }
        // เก็บเป็น Array ที่มีทั้ง id และ name
        $techs_by_dept[$dept][] = ['id' => $t['id'], 'name' => $t['full_name']];
    }
}

// แก้ไขฟังก์ชันการจัดเรียงภายในกลุ่มให้รองรับโครงสร้าง Array แบบใหม่
foreach ($techs_by_dept as &$tList) { 
    usort($tList, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    }); 
}
unset($tList);

$custom_dept_order = [
    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล',
    'ฝ่ายงานโสตทัศนูปกรณ์',
    'ฝ่ายงานยานยนต์',
    'แม่บ้าน',
    'ฝ่ายงานทั่วไป',
    'อื่นๆ'
];

uksort($techs_by_dept, function($a, $b) use ($custom_dept_order) {
    $pos_a = array_search($a, $custom_dept_order);
    $pos_b = array_search($b, $custom_dept_order);
    $pos_a = ($pos_a === false) ? 999 : $pos_a;
    $pos_b = ($pos_b === false) ? 999 : $pos_b;
    if ($pos_a == $pos_b) return strcmp($a, $b);
    return $pos_a - $pos_b;
});

foreach ($techs_by_dept as &$tList) { sort($tList); }
unset($tList);

$assets_list = [];
$assets_res = $conn->query("SELECT asset_code, asset_name FROM assets ORDER BY asset_code ASC");
if($assets_res && $assets_res->num_rows > 0){
    while($a = $assets_res->fetch_assoc()){
        $assets_list[] = $a;
    }
}

$asset_categories = ['IT Support', 'ไฟฟ้า/แอร์', 'อาคารสถานที่'];
$cat_res = $conn->query("SELECT DISTINCT category FROM assets WHERE category IS NOT NULL AND category != ''");
if($cat_res && $cat_res->num_rows > 0){
    while($c = $cat_res->fetch_assoc()){
        if(!in_array($c['category'], $asset_categories) && $c['category'] !== 'อื่นๆ') {
            $asset_categories[] = $c['category'];
        }
    }
}

$check_asset_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'asset_code'");
if($check_asset_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN asset_code VARCHAR(50) NULL AFTER equipment_type");
}

$check_root_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'root_cause'");
if($check_root_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN root_cause TEXT NULL");
}

$check_completed_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'completed_at'");
if($check_completed_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN completed_at DATETIME NULL");
}

// ✨ ตรวจสอบและสร้างคอลัมน์ received_at เผื่อไว้ ป้องกันระบบพัง ✨
$check_received_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'received_at'");
if($check_received_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN received_at DATETIME NULL AFTER root_cause");
}

$show_alert = false;
$show_asset_alert = false; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $csrf = $_POST['repair_csrf'] ?? null;
    if (!is_string($csrf) || !hash_equals($_SESSION['repair_csrf'], $csrf)) {
        http_response_code(403);
        mbsUpdateRepairNotice('คำขอไม่ถูกต้อง กรุณาเปิดใบงานใหม่แล้วลองอีกครั้ง');
    }
    if (!isset($_POST['save_asset_only'])) {
        $posted_id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if ($posted_id === false || $posted_id !== $validated_repair_id) {
            http_response_code(400);
            mbsUpdateRepairNotice('เลขใบงานไม่ตรงกับหน้าที่เปิด');
        }
    }

    if ($is_tech) {
        if (!is_array($repair)
            || !isset($owner_tech_id)
            || $owner_tech_id === false
            || (int) ($repair['technician_id'] ?? 0) < 1
            || (int) $repair['technician_id'] !== $owner_tech_id) {
            http_response_code(403);
            mbsUpdateRepairNotice('ไม่อนุญาตให้บันทึกใบงานนี้');
        }

        // ฟอร์มเพิ่มอุปกรณ์เดิมไม่มีช่อง id ของใบงาน
        // ตรวจเลขใบงานจาก POST เฉพาะคำสั่งบันทึกใบงาน
        if (!isset($_POST['save_asset_only'])) {
            $posted_repair_id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($posted_repair_id === false
                || $posted_repair_id !== (int) $repair['id']) {
                http_response_code(403);
                mbsUpdateRepairNotice('เลขใบงานไม่ตรงกับใบงานที่ได้รับอนุญาต');
            }
        }
    }

    if (isset($_POST['save_asset_only'])) {
        $acode = trim($_POST['modal_asset_code']);
        $aname = trim($_POST['modal_asset_name']);
        
        $acat = $_POST['modal_category'];
        if ($acat === 'อื่นๆ' && !empty($_POST['modal_category_custom'])) {
            $acat = trim($_POST['modal_category_custom']);
        }
        
        $astat = 'ใช้งานปกติ';

        $chk = $conn->prepare("SELECT id FROM assets WHERE asset_code = ?");
        $chk->bind_param("s", $acode);
        $chk->execute();
        $res_chk = $chk->get_result();
        
        if ($res_chk->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $acode, $aname, $acat, $astat);
            $stmt_insert->execute();
            $stmt_insert->close();
            $show_asset_alert = true;
            $repair['asset_code'] = $acode; 
        }
        $chk->close();
        
    } else {
        $old_status = isset($repair['status']) ? $repair['status'] : '';
        $old_note = isset($repair['repair_note']) ? trim($repair['repair_note']) : '';

        $status = $_POST['status'] ?? null;
        if (!is_string($status) || !in_array($status,
            ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'], true)) {
            http_response_code(400);
            mbsUpdateRepairNotice('สถานะใบงานไม่ถูกต้อง');
        }
        $repair_note = trim($_POST['repair_note']);
        
                if ($is_tech) {
            $technician_id = $owner_tech_id;
            $technician_name = null;
        } else {
            // ใช้เจ้าของใบงานเดิม ไม่รับการเปลี่ยนผู้รับผิดชอบจาก POST
            $technician_id = $repair['technician_id'] ?? null;
            $technician_name = $repair['technician_name'] ?? null;
        }
        
        // ฝั่งเจ้าหน้าที่ใช้รหัสจาก Session และค้นชื่อจากฐานข้อมูล
        if ($is_tech && $technician_id) {
            $chk_t = $conn->prepare("SELECT full_name FROM technicians WHERE id = ?");
            $chk_t->bind_param("i", $technician_id);
            $chk_t->execute();
            $res_t = $chk_t->get_result();
            if ($res_t->num_rows > 0) {
                $technician_name = $res_t->fetch_assoc()['full_name'];
            } else {
                http_response_code(403);
                mbsUpdateRepairNotice('ไม่พบข้อมูลเจ้าหน้าที่ กรุณาเข้าสู่ระบบใหม่');
            }
            $chk_t->close();
        } elseif ($is_tech) {
            $technician_name = null;
        }

        $asset_code = isset($_POST['asset_code']) && $_POST['asset_code'] !== '' ? trim($_POST['asset_code']) : null;
        $asset_status = isset($_POST['asset_status']) ? $_POST['asset_status'] : null;
        $update_id = $_POST['id'];

        // ✨ บันทึกเวลาจริงลงฐานข้อมูลแบบเรียลไทม์ (ดักจับค่า 0000-00-00 ด้วย PHP) ✨
        $now = date('Y-m-d H:i:s');
        
        // ดึงค่าเวลาเดิมที่เคยบันทึกไว้มาตรวจสอบก่อน
        $old_received = (!empty($repair['received_at']) && $repair['received_at'] !== '0000-00-00 00:00:00') ? $repair['received_at'] : null;
        $old_completed = (!empty($repair['completed_at']) && $repair['completed_at'] !== '0000-00-00 00:00:00') ? $repair['completed_at'] : null;

        if ($status === 'ซ่อมเสร็จแล้ว') {
            // ถ้าข้ามมากดเสร็จเลย โดยที่เวลายังว่าง ให้ยัดเวลาปัจจุบันลงไปทั้ง 2 ช่อง
            $final_received = $old_received ? $old_received : $now;
            $final_completed = $old_completed ? $old_completed : $now;
        } elseif ($status === 'กำลังดำเนินการ') {
            // ถ้ารับงาน ให้ยัดเวลาปัจจุบันลงไปช่องรับงาน (ถ้ายังไม่มี) และล้างเวลาเสร็จทิ้ง
            $final_received = $old_received ? $old_received : $now;
            $final_completed = null; 
        } else {
            // ถ้ากลับไปรอรับเรื่อง ให้ล้างเวลาทิ้งให้หมด
            $final_received = null;
            $final_completed = null;
        }
        
        // ตรวจเจ้าของงานซ้ำและล็อกแถวเฉพาะการบันทึกของเจ้าหน้าที่
        $owner_transaction_started = false;
        $owner_lock_stmt = null;

        try {
            if ($is_tech) {
                if (!$conn->begin_transaction()) {
                    throw new RuntimeException('Cannot start owner check');
                }
                $owner_transaction_started = true;

                $owner_lock_stmt = $conn->prepare(
                    "SELECT id
                     FROM repairs
                     WHERE id = ?
                       AND technician_id = ?
                     FOR UPDATE"
                );

                if (!$owner_lock_stmt
                    || !$owner_lock_stmt->bind_param(
                        "ii",
                        $update_id,
                        $owner_tech_id
                    )
                    || !$owner_lock_stmt->execute()) {
                    throw new RuntimeException('Cannot check repair owner');
                }

                $owner_result = $owner_lock_stmt->get_result();

                if (!$owner_result) {
                    throw new RuntimeException('Cannot read repair owner');
                }

                $owner_matches = $owner_result->num_rows === 1;
                $owner_lock_stmt->close();
                $owner_lock_stmt = null;

                if (!$owner_matches) {
                    if (!$conn->rollback()) {
                        throw new RuntimeException('Cannot release owner check');
                    }
                    $owner_transaction_started = false;

                    http_response_code(403);
                    mbsUpdateRepairNotice('ผู้รับผิดชอบใบงานเปลี่ยนแล้ว ไม่อนุญาตให้บันทึก');
                }
            }

            if ($is_tech) {
                $update_sql = "UPDATE repairs
                    SET status = ?, repair_note = ?, root_cause = ?,
                        technician_id = ?, technician_name = ?,
                        asset_code = ?, received_at = ?, completed_at = ?
                    WHERE id = ?
                      AND technician_id = ?";
            } else {
                // คงผู้รับผิดชอบที่อยู่ในฐานข้อมูล แม้มี POST ปลอมส่งมา
                $update_sql = "UPDATE repairs
                    SET status = ?, repair_note = ?, root_cause = ?,
                        asset_code = ?, received_at = ?, completed_at = ?
                    WHERE id = ?";
            }

            $update_stmt = $conn->prepare($update_sql);

            if (!$update_stmt) {
                throw new RuntimeException('Cannot prepare repair update');
            }

            if ($is_tech) {
                $bound = $update_stmt->bind_param(
                    "sssissssii",
                    $status,
                    $repair_note,
                    $repair_note,
                    $technician_id,
                    $technician_name,
                    $asset_code,
                    $final_received,
                    $final_completed,
                    $update_id,
                    $owner_tech_id
                );
                
            } else {
                $bound = $update_stmt->bind_param(
                    "ssssssi",
                    $status,
                    $repair_note,
                    $repair_note,
                    $asset_code,
                    $final_received,
                    $final_completed,
                    $update_id
                );
            }

            if (!$bound) {
                throw new RuntimeException('Cannot bind repair update');
            }

            $update_saved = $update_stmt->execute();

            if ($is_tech) {
                if (!$update_saved) {
                    throw new RuntimeException('Cannot save repair');
                }

                if (!$conn->commit()) {
                    throw new RuntimeException('Cannot confirm repair update');
                }

                $owner_transaction_started = false;
            }
        } catch (Throwable $error) {
            if ($owner_lock_stmt instanceof mysqli_stmt) {
                $owner_lock_stmt->close();
            }

            if ($owner_transaction_started) {
                try {
                    $conn->rollback();
                } catch (Throwable $rollback_error) {
                    error_log('Repair owner transaction rollback failed');
                }
            }

            error_log('Repair owner check or update failed');
            http_response_code(500);
            mbsUpdateRepairNotice('ไม่สามารถยืนยันผลการบันทึกได้ กรุณาเปิดใบงานตรวจสอบก่อนทำรายการซ้ำ');
        }

        if ($update_saved) {
            $activity_action =
                $status === 'ซ่อมเสร็จแล้ว'
                && $old_status !== 'ซ่อมเสร็จแล้ว'
                    ? 'CLOSE_REPAIR'
                    : 'SAVE_REPAIR';

            mbsActivityLog(
                $conn,
                $activity_action,
                'repair',
                (int) $update_id,
                'บันทึกใบงาน #' . (int) $update_id
                    . ' — สถานะ: ' . $status
            );

            $show_alert = true;

            if (!empty($asset_code) && !empty($asset_status)) {
                $check_asset = $conn->prepare("SELECT id FROM assets WHERE asset_code = ?");
                $check_asset->bind_param("s", $asset_code);
                $check_asset->execute();
                $res_check = $check_asset->get_result();

                if ($res_check->num_rows > 0) {
                    $stmt_asset = $conn->prepare("UPDATE assets SET status = ? WHERE asset_code = ?");
                    $stmt_asset->bind_param("ss", $asset_status, $asset_code);
                    $stmt_asset->execute();
                    $stmt_asset->close();
                } else {
                    $new_asset_name = isset($_POST['new_asset_name']) && trim($_POST['new_asset_name']) !== '' ? trim($_POST['new_asset_name']) : "ครุภัณฑ์จากใบงาน " . (!empty($repair['ticket_no']) ? $repair['ticket_no'] : "ล่าสุด");
                    
                    $new_asset_category = isset($_POST['new_asset_category']) ? $_POST['new_asset_category'] : "อื่นๆ";
                    if ($new_asset_category === 'อื่นๆ' && !empty($_POST['new_asset_category_custom'])) {
                        $new_asset_category = trim($_POST['new_asset_category_custom']);
                    }
                    
                    $stmt_asset_insert = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
                    $stmt_asset_insert->bind_param("ssss", $asset_code, $new_asset_name, $new_asset_category, $asset_status);
                    $stmt_asset_insert->execute();
                    $stmt_asset_insert->close();
                }
                $check_asset->close();
            }

            $stmt->execute();
            $repair = $stmt->get_result()->fetch_assoc();

            $channelAccessToken = 'NfTQOY3ht2g8OfkT1qHgiJuagAjF7ELlwVm022PejbQhCLKDrwqq14uvnicLPRv/hzCiIXwucK9LwHbz5qsYq7Bi9KgidS5XycIX1SERQzX0OYDXU7K8RPLuGMb26v2ZFhBmHTWTZ/mg6Mjll3OFEAdB04t89/1O/w1cDnyilFU=';

            $tech_display = !empty($technician_name) ? $technician_name : "- ไม่ระบุ -";
            
            if(!empty($repair['line_user_id'])) {
                $messages = [];
                
                if ($status === 'ซ่อมเสร็จแล้ว' && $old_status !== 'ซ่อมเสร็จแล้ว') {
                    $textMsg = "🎉 ดำเนินการใบงาน {$repair['ticket_no']} เสร็จสิ้นแล้วค่ะ\n\n" .
                               "📝 หมายเหตุ: " . (!empty($repair_note) ? $repair_note : "- ไม่มีหมายเหตุ -");
                               
                    $review_msg = [
                        'type' => 'flex',
                        'altText' => 'ประเมินผลการซ่อม',
                        'contents' => [
                            'type' => 'bubble',
                            'body' => [
                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => '⭐ ประเมินผลการซ่อม', 'weight' => 'bold', 'color' => '#ffb700', 'size' => 'md'],
                                    ['type' => 'text', 'text' => "ถึงคุณ ".$repair['reporter_name'], 'weight' => 'bold', 'color' => '#3b82f6', 'size' => 'xs'],
                                    ['type' => 'text', 'text' => 'เจ้าหน้าที่ '.$tech_display.' ดำเนินการเรียบร้อยแล้วค่ะ', 'weight' => 'bold', 'size' => 'xs', 'wrap' => true],
                                    ['type' => 'separator', 'margin' => 'sm'],
                                    ['type' => 'text', 'text' => '1️⃣ ให้คะแนนดาว (กดเปลี่ยนได้)', 'size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'sm'],
                                    [
                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                        'contents' => [
                                            ['type' => 'button', 'style' => 'primary', 'color' => '#fbbf24', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '5 ดาว', 'data' => "action=rate&score=5&id={$repair['id']}"]],
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '4 ดาว', 'data' => "action=rate&score=4&id={$repair['id']}"]],
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '3 ดาว', 'data' => "action=rate&score=3&id={$repair['id']}"]]
                                        ]
                                    ],
                                    [
                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm', 'margin' => 'sm',
                                        'contents' => [
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '2 ดาว', 'data' => "action=rate&score=2&id={$repair['id']}"]],
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '1 ดาว', 'data' => "action=rate&score=1&id={$repair['id']}"]],
                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '0 ดาว', 'data' => "action=rate&score=0&id={$repair['id']}"]]
                                        ]
                                    ],
                                    ['type' => 'separator', 'margin' => 'sm'],
                                    ['type' => 'text', 'text' => '2️⃣ เลือกรีวิว (กดได้หลายข้อ)', 'size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'sm'],
                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '⏱️ ดำเนินการเร็ว', 'data' => "action=add_tag&tag=ดำเนินการรวดเร็วทันใจ&id={$repair['id']}"]],
                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🎯 แก้ปัญหาตรงจุด', 'data' => "action=add_tag&tag=แก้ไขปัญหาได้อย่างตรงจุด&id={$repair['id']}"]],
                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '💡 ให้คำแนะนำดี', 'data' => "action=add_tag&tag=อธิบายและให้คำแนะนำชัดเจน&id={$repair['id']}"]],
                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🗣️ สุภาพเรียบร้อย', 'data' => "action=add_tag&tag=สุภาพเรียบร้อย บริการเต็มใจ&id={$repair['id']}"]],
                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '✨ เก็บงานเรียบร้อย', 'data' => "action=add_tag&tag=ซ่อมแซมและเก็บงานเรียบร้อย&id={$repair['id']}"]],
                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🙏 ช่วยเหลือดีเยี่ยม', 'data' => "action=add_tag&tag=ช่วยอำนวยความสะดวกได้ดีเยี่ยม&id={$repair['id']}"]],
                                    ['type' => 'text', 'text' => '*หรือพิมพ์ข้อความรีวิวเพิ่มเติมส่งมาในแชทได้เลยค่ะ', 'size' => 'xxs', 'color' => '#bbbbbb', 'margin' => 'sm', 'wrap' => true]
                                ]
                            ]
                        ]
                    ];
                    
                    $messages = [
                        mbsUpdateCompactNoticeFlex(['type' => 'text', 'text' => $textMsg]),
                        $review_msg
                    ];
                } 
                else if ($repair_note !== $old_note && $repair_note !== '') {
                    $textMsg = "📝 อัปเดตความคืบหน้าใบงาน\n\n" .
                               "📋 ใบงาน: {$repair['ticket_no']}\n" .
                               "💻 ปัญหา: {$repair['equipment_type']} ({$repair['location']})\n" .
                               "👨‍🔧 ผู้รับผิดชอบ: {$tech_display}\n" .
                               "💬 หมายเหตุ: {$repair_note}";
                               
                    $messages = [mbsUpdateCompactNoticeFlex(['type' => 'text', 'text' => $textMsg])];
                }
                
                if (!empty($messages)) {
                    $postData = [
                        'to' => $repair['line_user_id'],
                        'messages' => $messages
                    ];

                    $ch = curl_init('https://api.line.me/v2/bot/message/push');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $channelAccessToken));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(mbsReadableFlexNode($postData)));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดตสถานะงานซ่อม | MBS MAINT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); }
        
        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20320%20512%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M31.3%20192h257.3c17.8%200%2026.7%2021.5%2014.1%2034.1L174.1%20354.8c-7.8%207.8-20.5%207.8-28.3%200L17.2%20226.1C4.6%20213.5%2013.5%20192%2031.3%20192z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 0.75em auto;
            padding-right: 2.5rem;
        }

        input[list]::-webkit-calendar-picker-indicator {
            opacity: 0 !important;
            cursor: pointer;
            width: 2rem;
            height: 100%;
        }

        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
    </style>
    <link rel="stylesheet" href="repair-detail-ui.css?v=20261019-1">
</head>
<body class="p-4 md:p-10 selection:bg-sky-200">

    <div class="max-w-5xl mx-auto">
        <?php if($is_admin): ?>
        <!-- ✨ โครงสร้างสำหรับฝั่งแอดมิน: ใช้ CSS Media Query ล็อกเป้าหมายมือถือแนวตั้ง 100% ✨ -->
        <style>
            @media (max-width: 639px) and (orientation: portrait) {
                .admin-header-group { flex-direction: column-reverse !important; }
                .admin-btn-item { width: auto !important; align-self: flex-end !important; }
            }
        </style>
        <div class="admin-header-group flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-slate-800"><i class="fas fa-clipboard-check text-sky-500 mr-2"></i> ระบบจัดการใบงานแจ้งซ่อม</h1>
                <p class="text-sm md:text-base text-slate-500 mt-1">ตรวจสอบรายละเอียดและอัปเดตสถานะให้ผู้แจ้ง</p>
            </div>
            <button type="button" onclick="goBack();" class="admin-btn-item bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-md inline-flex items-center justify-center text-sm w-full sm:w-auto cursor-pointer">
               <i class="fas fa-arrow-left mr-2"></i> กลับหน้ารายการ
            </button>
        </div>
        <?php else: ?>
            
        <!-- ส่วนหัวใบงานของเจ้าหน้าที่ -->
        <style>
            @media (max-width: 639px) and (orientation: portrait) {
                .tech-repair-header {
                    flex-direction: column-reverse;
                }

                .tech-repair-header > .tech-repair-back {
                    width: auto;
                    align-self: flex-end;
                    flex-shrink: 0;
                    white-space: nowrap;
                }
            }
        </style>

        <div class="tech-repair-header flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-slate-800"><i class="fas fa-clipboard-check text-sky-500 mr-2"></i> ระบบจัดการใบงานแจ้งซ่อม</h1>
                <p class="text-sm md:text-base text-slate-500 mt-1">ตรวจสอบรายละเอียดและอัปเดตสถานะให้ผู้แจ้ง</p>
            </div>
            <a href="technician_home.php?tab=history" onclick="if (window.name === 'mbsTechnicianRepair') { window.close(); setTimeout(() => window.location.replace(this.href), 300); return false; }" class="tech-repair-back bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-md inline-flex items-center justify-center text-sm w-full sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i> กลับหน้ารายการ
            </a>
        </div>
        <?php endif; ?>

        <?php if($repair): ?>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <div class="lg:col-span-2 space-y-6">
                <div class="modern-card p-6 border-t-4 border-sky-500">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-slate-800">ข้อมูลใบงาน</h2>
                        <span class="bg-slate-100 border border-slate-200 px-3 py-1 rounded-lg text-lg font-extrabold text-sky-600 tracking-tight">
                            <?php echo htmlspecialchars($repair['ticket_no']); ?>
                        </span>
                    </div>

                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="text-slate-400 text-[11px] font-medium md:text-xs md:font-normal uppercase tracking-wide">วัน/เวลาที่แจ้ง</p>
                            <p class="font-medium text-slate-700 mt-0.5"><i class="far fa-clock text-slate-400 mr-1"></i> <?php echo date("d/m/Y H:i", strtotime($repair['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[11px] font-medium md:text-xs md:font-normal uppercase tracking-wide">ผู้แจ้ง</p>
                            <p class="font-bold text-indigo-600 mt-1 flex items-center"><i class="fab fa-line text-[#06C755] text-[16px] mr-1.5"></i> <?php echo htmlspecialchars($repair_line_id); ?></p>
                            <p class="font-medium text-slate-700 mt-0.5"><i class="far fa-user text-slate-400 mr-1"></i> <?php echo htmlspecialchars($repair_real_name); ?></p>
                            <?php 
                                // ✨ ตรวจสอบและแสดงคำว่า "ไม่ระบุ" สีแดงหากไม่มีเบอร์โทร (แอดมิน) ✨
                                $admin_raw_phone = trim((string)$repair['phone_number']);
                                if (empty($admin_raw_phone) || $admin_raw_phone === '-' || $admin_raw_phone === 'ไม่ระบุ') {
                                    echo '<p class="font-bold text-rose-500 mt-0.5"><i class="fas fa-phone-slash text-rose-400 mr-1"></i> ไม่ระบุ</p>';
                                } else {
                                    echo '<p class="text-slate-500 mt-0.5"><i class="fas fa-phone-alt text-slate-400 mr-1"></i> ' . htmlspecialchars($admin_raw_phone) . '</p>';
                                }
                            ?>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[11px] font-medium md:text-xs md:font-normal uppercase tracking-wide">สถานที่</p>
                            <p class="font-medium text-slate-700 mt-0.5"><i class="fas fa-map-marker-alt text-rose-400 mr-1"></i> <?php echo htmlspecialchars($repair['location']); ?></p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <p class="text-slate-400 text-[11px] font-medium md:text-xs md:font-normal uppercase tracking-wide mb-1">อุปกรณ์และอาการเสีย</p>
                            <p class="font-bold text-sky-700"><?php echo htmlspecialchars($repair['equipment_type']); ?></p>
                            <p class="text-slate-600 mt-1"><?php echo htmlspecialchars($repair['problem_desc']); ?></p>
                        </div>

                        <div>
                            <p class="text-slate-400 text-[11px] font-medium md:text-xs md:font-normal uppercase tracking-wide mb-2">ไฟล์แนบประกอบใบงาน</p>
                            <div class="repair-media-summary">
                            <?php require_once __DIR__ . '/repair_media.php'; rmPanel($conn, (int)$repair['id']); ?>

                            </div>
                            <?php 
                                $image_file = !empty($repair['image_before']) ? $repair['image_before'] : (!empty($repair['image_path']) ? $repair['image_path'] : null);
                                if($image_file): 
                            ?>
                                <a href="uploads/<?php echo htmlspecialchars($image_file); ?>" target="_blank" class="block w-full h-48 rounded-xl border border-slate-200 overflow-hidden group relative">
                                    <img src="uploads/<?php echo htmlspecialchars($image_file); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-white font-medium text-sm"><i class="fas fa-expand mr-1"></i> ดูรูปภาพเต็ม</span>
                                    </div>
                                </a>
                            <?php else: ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <div class="modern-card overflow-hidden border-t-4 border-amber-400">
                    <div class="bg-slate-50 p-4 border-b border-slate-100 flex items-center">
                        <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-500 flex items-center justify-center mr-3">
                            <i class="fas fa-star text-sm"></i>
                        </div>
                        <h3 class="font-bold text-slate-800">ผลการประเมิน (รีวิว)</h3>
                    </div>
                    <div class="p-5">
                        <?php 
                        $has_rating = !empty($repair['rating']) && (int)$repair['rating'] > 0;
                        $has_comment = !empty($repair['review_comment']) && trim($repair['review_comment']) !== '' && trim($repair['review_comment']) !== '-';
                        if ($has_rating || $has_comment): 
                        ?>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-start mb-2 gap-1.5 sm:gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 shadow-sm">
                                                <i class="fas fa-user text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-800 flex items-center flex-wrap gap-2">
                                                    <span><?php echo htmlspecialchars($repair['reporter_name']); ?></span>
                                                    <!-- ✨ ย้ายดาวมาต่อท้ายชื่อตรงนี้ ✨ -->
                                                    <div class="flex gap-0.5 pt-0.5 sm:pt-0">
                                                        <?php 
                                                        $rating = (int)($repair['rating'] ?? 0);
                                                        if ($rating > 0) {
                                                            for($i=1; $i<=5; $i++) {
                                                                if($i <= $rating) echo '<i class="fas fa-star text-amber-400 text-[13px] drop-shadow-sm"></i>';
                                                                else echo '<i class="fas fa-star text-slate-200 text-[13px]"></i>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="text-[11px] text-slate-400 font-medium">
                                                    <?php 
                                                    require_once __DIR__ . '/repair_review_datetime.php';
                                                    echo mbs_repair_review_datetime($repair);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            <?php if($has_comment): ?>
                                <p class="text-sm text-slate-600 font-medium pl-[52px] leading-relaxed mt-1"><?php echo nl2br(htmlspecialchars(trim($repair['review_comment']))); ?></p>
                            <?php endif; ?>

                            <div class="pl-[52px] mt-2.5">
                                <div class="text-[11px] text-indigo-600 font-bold inline-block bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                    <i class="fas fa-tools mr-1.5 opacity-70"></i>ให้คะแนนเจ้าหน้าที่: <?php echo !empty($repair['technician_name']) && $repair['technician_name'] !== '-' ? htmlspecialchars($repair['technician_name']) : 'ไม่ระบุเจ้าหน้าที่'; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-star text-slate-300 text-lg"></i>
                                </div>
                                <p class="text-slate-500 text-xs font-medium">ยังไม่มีการประเมินผลการซ่อม</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="lg:col-span-3">
                <div class="modern-card p-6 md:p-8 h-full flex flex-col">
                    <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-6">บันทึกการปฏิบัติงาน</h2>

                    <form id="updateForm" action="<?php echo htmlspecialchars($form_action); ?>" method="POST" class="space-y-6 flex-1 flex flex-col">
                        <input type="hidden" name="repair_csrf" value="<?php echo htmlspecialchars($_SESSION['repair_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo $repair['id']; ?>">

                        <?php
                            $current_tech_full = $repair['technician_name'] ?? '';
                            $current_tech_display = '';
                            if ($current_tech_full) {
                                list($cth, $cen) = splitThaiEngName($current_tech_full, '');
                                $current_tech_display = $cth;
                            }
                        ?>
                        <div class="mb-4 relative" id="techDropdownContainer">
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-user-cog text-sky-500 mr-2"></i> เจ้าหน้าที่ผู้รับผิดชอบ</label>
                            
                            <?php if ($is_tech): ?>
                                <!-- ล็อกสิทธิ์ผู้รับผิดชอบตามรหัสเจ้าหน้าที่ของ Account ที่กำลังล็อกอิน (ต้องรับงานก่อนเปิดหน้านี้) -->
                                <input type="hidden" name="technician_id" id="technician_id" value="<?php echo $_SESSION['technician_id']; ?>">
                                <input type="hidden" name="technician_name" id="technician_name" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>">
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center shadow-sm">
                                    <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center mr-4 text-sky-600 shrink-0">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                                        <p class="text-[11px] text-slate-500 mt-0.5">รับผิดชอบใบงานนี้ (ล็อกสิทธิ์ตามบัญชี)</p>
                                    </div>
                                </div>
                                                        <?php else: ?>
                                <?php
                                    $assigned_name = trim(
                                        (string) ($repair['technician_name'] ?? '')
                                    );

                                    $has_assigned_name = $assigned_name !== ''
                                        && $assigned_name !== '-';

                                    $assigned_display = $has_assigned_name
                                        ? $assigned_name
                                        : 'ยังไม่มีเจ้าหน้าที่รับงาน';
                                ?>

                                <input type="hidden"
                                       id="technician_name"
                                       value="<?php echo htmlspecialchars(
                                           $has_assigned_name ? $assigned_name : '',
                                           ENT_QUOTES,
                                           'UTF-8'
                                       ); ?>">

                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center shadow-sm">
                                    <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center mr-4 text-sky-600 shrink-0">
                                        <i class="fas fa-user-check"></i>
                                    </div>

                                    <div>
                                        <p class="text-sm font-bold text-slate-800">
                                            <?php echo htmlspecialchars(
                                                $assigned_display,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </p>

                                        <p class="text-[11px] text-slate-500 mt-0.5">
                                            ผู้รับผิดชอบตามข้อมูลใบงาน
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4 p-5 border border-slate-200 bg-slate-50/50 rounded-2xl shadow-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="block text-sm font-semibold text-slate-700"><i class="fas fa-barcode text-sky-500 mr-1.5"></i> รหัสครุภัณฑ์ (ที่ซ่อม)</label>
                                        <button type="button" onclick="openAssetModal()" class="text-[11px] bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg font-bold transition-all shadow-sm flex items-center shrink-0">
                                            <i class="fas fa-plus mr-1"></i> เพิ่มใหม่
                                        </button>
                                    </div>
                                    <input type="text" name="asset_code" id="asset_code" list="asset_code_list" 
                                           class="custom-select w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100 transition-all shadow-sm relative" 
                                           placeholder="-- เลือกรหัส หรือพิมพ์ค้นหา --" 
                                           value="<?php echo isset($repair['asset_code']) ? htmlspecialchars($repair['asset_code']) : ''; ?>">
                                    
                                    <datalist id="asset_code_list">
                                        <?php foreach($assets_list as $asset): ?>
                                            <option value="<?php echo htmlspecialchars($asset['asset_code']); ?>">
                                                <?php echo htmlspecialchars($asset['asset_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <p class="text-[11px] text-slate-500 mt-2 font-medium">กด <span class="text-indigo-600 font-bold">"เพิ่มใหม่"</span> หากไม่มีรหัสในระบบ</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-heartbeat text-sky-500 mr-2"></i> สถานะครุภัณฑ์ (ปัจจุบัน)</label>
                                    <select name="asset_status" id="asset_status" class="custom-select w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100 transition-all cursor-pointer shadow-sm mt-0.5 md:mt-0">
                                        <option value="ใช้งานปกติ">🟢 ใช้งานปกติ</option>
                                        <option value="ชำรุด/ส่งซ่อม">🟠 ชำรุด/ส่งซ่อม</option>
                                        <option value="แทงจำหน่าย">🔴 แทงจำหน่าย</option>
                                    </select>
                                </div>
                            </div>

                            <div id="new_asset_section" class="hidden mt-4 pt-4 border-t border-slate-200">
                                <div class="flex items-center mb-3">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 mr-2"><i class="fas fa-plus text-xs"></i></span>
                                    <p class="text-sm font-bold text-indigo-700">พบรหัสครุภัณฑ์ใหม่! กรุณาระบุข้อมูลเพื่อเพิ่มลงระบบ</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">ชื่อครุภัณฑ์ <span class="text-red-500">*</span></label>
                                        <input type="text" name="new_asset_name" id="new_asset_name" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="เช่น คอมพิวเตอร์ DELL">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">หมวดหมู่</label>
                                        <select name="new_asset_category" id="new_asset_category" onchange="toggleCustomInput(this, 'new_asset_category_custom')" class="custom-select w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm cursor-pointer">
                                            <?php foreach($asset_categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                            <?php endforeach; ?>
                                            <option value="อื่นๆ">อื่นๆ (พิมพ์ระบุเอง)</option>
                                        </select>
                                        <input type="text" name="new_asset_category_custom" id="new_asset_category_custom" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 hidden mt-2 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="ระบุหมวดหมู่ใหม่">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-tasks text-sky-500 mr-2"></i> อัปเดตสถานะงานแจ้งซ่อม <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="รอรับเรื่อง" class="peer sr-only" <?php echo ($repair['status'] == 'รอรับเรื่อง') ? 'checked' : ''; ?> required>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-amber-50 peer-checked:border-amber-300 peer-checked:text-amber-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-clock mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">รอรับเรื่อง</div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="กำลังดำเนินการ" class="peer sr-only" <?php echo ($repair['status'] == 'กำลังดำเนินการ' || $repair['status'] == 'ช่างรับเรื่องแจ้งซ่อมแล้ว') ? 'checked' : ''; ?>>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-sky-50 peer-checked:border-sky-300 peer-checked:text-sky-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-tools mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">กำลังดำเนินการ</div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="ซ่อมเสร็จแล้ว" class="peer sr-only" <?php echo ($repair['status'] == 'ซ่อมเสร็จแล้ว' || $repair['status'] == 'เสร็จสิ้น') ? 'checked' : ''; ?>>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-emerald-50 peer-checked:border-emerald-300 peer-checked:text-emerald-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-check-circle mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">ซ่อมเสร็จแล้ว</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="w-full">
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-edit text-sky-500 mr-2"></i> บันทึกผลการดำเนินการ / หมายเหตุเจ้าหน้าที่</label>
                            <textarea name="repair_note" rows="4" placeholder="ระบุสาเหตุที่เสีย, อะไหล่ที่เปลี่ยน, หรือคำแนะนำ..." class="w-full h-32 bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all resize-none"><?php echo isset($repair['repair_note']) ? htmlspecialchars($repair['repair_note']) : ''; ?></textarea>
                        </div>

                            <?php if ($is_tech && (int)($repair['technician_id'] ?? 0) === (int)($_SESSION['technician_id'] ?? 0)): ?>
                            <div style="margin:12px 0;padding:14px;border:1px solid #cbd5e1;border-radius:10px">
                                <input form="staffPhotoForm" type="hidden" name="id" value="<?php echo (int)$repair['id']; ?>">
                                <input form="staffPhotoForm" type="hidden" name="repair_csrf" value="<?php echo htmlspecialchars($_SESSION['repair_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                <label for="staff_photo" style="display:block;font-weight:bold;margin-bottom:8px">เพิ่มรูปหรือวิดีโอผลการดำเนินงาน</label>
                                <input form="staffPhotoForm" id="staff_photo" type="file" name="media[]" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" multiple required style="display:block;max-width:100%;margin-bottom:10px">
                                <button form="staffPhotoForm" type="submit" style="background:#1e3a5f;color:white;padding:10px 16px;border:0;border-radius:8px">บันทึกไฟล์</button>
                                <p style="font-size:14px;margin-top:8px">รูปไม่เกิน 10 MB · วิดีโอไม่เกิน 50 MB ต่อไฟล์ · เลือกได้หลายไฟล์</p>
                            </div>
                            <?php endif; ?>

                        <div class="flex flex-col md:flex-row justify-end gap-3 mt-2">
                            <button type="submit" class="w-full md:w-auto bg-sky-600 hover:bg-sky-500 text-white px-8 py-3 rounded-xl font-bold transition-colors shadow-lg shadow-sky-600/20 flex justify-center items-center">
                                <i class="fas fa-save mr-2"></i> บันทึกข้อมูลและแจ้งเตือน
                            </button>
                        </div>
                    </form>
                    <?php if ($is_tech && (int)($repair['technician_id'] ?? 0) === (int)($_SESSION['technician_id'] ?? 0)): ?>
                    <form id="staffPhotoForm" action="staff_photo_upload.php" method="post" enctype="multipart/form-data"></form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php else: ?>
            <div class="modern-card p-12 text-center">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-3xl text-slate-400"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-700 mb-2">ไม่พบข้อมูลใบงาน</h2>
                <?php if($is_admin): ?>
                <button type="button" onclick="window.close();" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-2.5 rounded-xl font-medium transition-colors inline-block mt-4 cursor-pointer">กลับหน้ารายการ</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                <p class="text-lg font-extrabold text-slate-800" id="assetModalTitle">Add Asset</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <form action="update_repair.php?id=<?php echo $id; ?>" method="POST" class="p-6">
                <input type="hidden" name="repair_csrf" value="<?php echo htmlspecialchars($_SESSION['repair_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="save_asset_only" value="1"><input type="hidden" name="asset_id" id="asset_id" value="">
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Code <span class="text-red-500">*</span></label>
                        <input type="text" name="modal_asset_code" id="modal_asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Name <span class="text-red-500">*</span></label>
                        <input type="text" name="modal_asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Category</label>
                        <select name="modal_category" id="modal_category" onchange="toggleCustomInput(this, 'modal_category_custom')" required class="custom-select w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium cursor-pointer">
                            <?php foreach($asset_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="อื่นๆ">อื่นๆ (พิมพ์ระบุเอง)</option>
                        </select>
                        <input type="text" name="modal_category_custom" id="modal_category_custom" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 hidden mt-2 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="ระบุหมวดหมู่ใหม่">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('assetModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors shadow-sm">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Save Asset</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php if ($is_admin): ?>
        let currentTechDisplay = '<?php echo addslashes($current_tech_display); ?>';
        let currentTechValue = '<?php echo addslashes($current_tech_full); ?>';

        function focusTechSearch(e) {
            e.target.value = ''; 
            filterTechDropdown(); 
            toggleTechDropdown(e, true);
        }

        function blurTechSearch(e) {
            setTimeout(() => {
                if (document.getElementById('techSearchInput').value === '') {
                    document.getElementById('techSearchInput').value = currentTechDisplay || '-- ยังไม่ระบุผู้รับผิดชอบ --';
                }
            }, 200);
        }

        function toggleTechDropdown(e, forceOpen = false) {
            if(e) e.stopPropagation();
            const list = document.getElementById('techDropdownList');
            if(forceOpen) {
                list.classList.remove('hidden');
                list.classList.add('flex');
            } else {
                list.classList.toggle('hidden');
                list.classList.toggle('flex');
            }
            updateTechCheckmarks();
        }

        function filterTechDropdown() {
            toggleTechDropdown(null, true);
            const searchVal = document.getElementById('techSearchInput').value.toLowerCase().replace(/\s+/g, '');
            
            let deptVisibility = {};
            const items = document.querySelectorAll('.tech-dropdown-item');
            
            items.forEach(item => {
                const val = item.getAttribute('data-value');
                if (val === '') {
                    const displayData = '--ยังไม่ระบุผู้รับผิดชอบ--';
                    if(displayData.includes(searchVal) || searchVal === '') {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                    return;
                }
                
                const searchData = item.getAttribute('data-search') || '';
                const displayData = (item.getAttribute('data-display') || '').toLowerCase().replace(/\s+/g, '');
                const dept = item.getAttribute('data-dept');
                
                if (!deptVisibility[dept]) deptVisibility[dept] = 0;
                
                if(searchData.includes(searchVal) || displayData.includes(searchVal)) {
                    item.style.display = '';
                    deptVisibility[dept]++;
                } else {
                    item.style.display = 'none';
                }
            });

            const deptHeaders = document.querySelectorAll('.tech-dept-header');
            deptHeaders.forEach(header => {
                const dept = header.getAttribute('data-dept');
                if (deptVisibility[dept] && deptVisibility[dept] > 0) {
                    header.style.display = '';
                } else {
                    header.style.display = 'none';
                }
            });
        }

        function selectTech(id, fullName, displayText) {
            currentTechValue = fullName;
            currentTechDisplay = displayText;
            document.getElementById('technician_id').value = id;
            document.getElementById('technician_name').value = fullName;
            document.getElementById('techSearchInput').value = displayText;
            document.getElementById('techDropdownList').classList.add('hidden');
            document.getElementById('techDropdownList').classList.remove('flex');
            updateTechCheckmarks();
        }

        function updateTechCheckmarks() {
            document.querySelectorAll('.tech-dropdown-item').forEach(item => {
                const icon = item.querySelector('.check-icon');
                if(icon) {
                    if(item.getAttribute('data-value') === currentTechValue && currentTechValue !== '') {
                        icon.classList.remove('opacity-0');
                        icon.classList.add('opacity-100');
                    } else {
                        icon.classList.add('opacity-0');
                        icon.classList.remove('opacity-100');
                    }
                }
            });
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('techDropdownContainer');
            if (container && !container.contains(e.target)) {
                const list = document.getElementById('techDropdownList');
                if(list) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                    document.getElementById('techSearchInput').value = currentTechDisplay || '-- ยังไม่ระบุผู้รับผิดชอบ --';
                }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const techSearchInput = document.getElementById('techSearchInput');

            if (techSearchInput) {
                techSearchInput.value = currentTechDisplay || '-- ยังไม่ระบุผู้รับผิดชอบ --';
                updateTechCheckmarks();
            }
        });
        <?php endif; ?>

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        function openAssetModal() {
            let typedVal = document.getElementById('asset_code').value;
            document.getElementById('modal_asset_code').value = typedVal;
            toggleModal('assetModal');
        }

        function toggleCustomInput(selectElement, customInputId) {
            const customInput = document.getElementById(customInputId);
            if(selectElement.value === 'อื่นๆ') { 
                customInput.classList.remove('hidden'); customInput.required = true;
            } else { 
                customInput.classList.add('hidden'); customInput.required = false; 
            }
        }

        const existingAssets = <?php echo json_encode(array_column($assets_list, 'asset_code')); ?>;
        const assetCodeInput = document.getElementById('asset_code');
        const newAssetSection = document.getElementById('new_asset_section');
        const newAssetNameInput = document.getElementById('new_asset_name');

        function checkNewAsset() {
            const val = assetCodeInput.value.trim();
            if (val !== '' && !existingAssets.includes(val)) {
                newAssetSection.classList.remove('hidden');
                newAssetSection.classList.add('block');
                newAssetNameInput.required = true;
            } else {
                newAssetSection.classList.add('hidden');
                newAssetSection.classList.remove('block');
                newAssetNameInput.required = false;
            }
        }

        assetCodeInput.addEventListener('input', checkNewAsset);
        checkNewAsset();

        document.getElementById('updateForm').addEventListener('submit', function(e) {
            const statusChecked = document.querySelector('input[name="status"]:checked');
            const techName = document.getElementById('technician_name').value;

            if (statusChecked) {
                const status = statusChecked.value;
                if ((status === 'กำลังดำเนินการ' || status === 'ซ่อมเสร็จแล้ว' || status === 'ช่างรับเรื่องแจ้งซ่อมแล้ว') && techName === '') {
                    e.preventDefault(); 
                    Swal.fire({
                        icon: 'warning',
                        title: 'ยังไม่มีข้อมูลผู้รับผิดชอบ',
                        text: 'ใบงานนี้ยังไม่มีชื่อเจ้าหน้าที่ผู้รับผิดชอบ กรุณาตรวจสอบข้อมูลการรับงานก่อนบันทึกสถานะนี้',
                        confirmButtonColor: '#0284c7',
                        confirmButtonText: 'ตกลงเข้าใจแล้ว'
                    });
                }
            }
        }); // ✅ ปิด Form ให้เรียบร้อยตรงนี้ก่อน

        // ✅ วาง goBack ไว้ด้านนอกสุดแบบนี้ ปุ่มถึงจะมองเห็นและกดได้
        function goBack() {
            const source = <?php echo json_encode(is_string($_GET['source'] ?? null) ? $_GET['source'] : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            if (source === 'tech_reviews') {
                // 🚨 ถ้าเปิดมาจากหน้ารีวิวเจ้าหน้าที่ (แท็บใหม่) พอกดกลับ ให้ "ปิดแท็บนี้ลง" ทันที!
                window.close();
            } else if (source !== '') {
                // หากเปิดมาจากจุดอื่น ให้พากลับไปหน้าเดิม
                window.location.href = '<?php echo $back_url; ?>';
            } else {
                // หากเปิดเป็นแท็บใหม่มาจากตารางปกติ ให้ปิดแท็บ
                window.close();
                // สำรองไว้กรณีไม่ได้เป็น popup
                setTimeout(() => { window.location.href = '<?php echo $back_url; ?>'; }, 300);
            }    
        }
    </script>

    <?php if($show_asset_alert): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'เพิ่มครุภัณฑ์ใหม่สำเร็จ!',
            text: 'รหัสครุภัณฑ์นี้ถูกเพิ่มเข้าสู่ระบบ และพร้อมเชื่อมโยงกับใบงานแล้ว',
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'ตกลง'
        });
    </script>
    <?php endif; ?>

    <?php if($show_alert): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกข้อมูลใบงานสำเร็จ!',
            text: 'อัปเดตระบบเรียบร้อยแล้ว',
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            const isTechnician = <?php echo json_encode($is_tech); ?>;

            if (result.isConfirmed && !isTechnician) {
                goBack();
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>