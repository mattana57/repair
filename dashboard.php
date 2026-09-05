<?php 
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

if (strtolower($_SESSION['role']) === 'executive') {
    header("Location: executive_dashboard.php");
    exit();
}

include 'db_connect.php';

$conn->set_charset("utf8mb4");

// ✨ ฟังก์ชันจัดการค่าว่าง (-) และ (ไม่ระบุ) ให้เป็นสีแดง ✨
function formatEmptyOrDash($val) {
    $val = trim((string)$val);
    if (empty($val) || $val === '-') return "<span class='text-rose-500 font-bold'>-</span>";
    if ($val === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
    return htmlspecialchars($val);
}

function thaiNum($num) {
    return str_replace(
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        array('๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'),
        $num
    );
}

// ✨ ฟังก์ชันคำนวณเวลาที่ผ่านไป (Time Ago) สำหรับ PHP ✨
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

// ฟังก์ชันแยกชื่อไทย-อังกฤษ อัตโนมัติ
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

// ฟังก์ชันกำหนดตำแหน่งงานอัตโนมัติ
function getAutoPosition($th_name) {
    $map = [
        'สมพร วงษ์จำปา' => 'นักวิชาการคอมพิวเตอร์',
        'ปริญญา จันทรภา' => 'นักวิชาการคอมพิวเตอร์',
        'ทองสน พลมีศักดิ์' => 'นักวิชาการคอมพิวเตอร์',
        'ธีรศักดิ์ พาโคกทม' => 'นักวิชาการคอมพิวเตอร์',
        
        'จิตรณรงค์ นาใจคง' => 'นักวิชาการโสตทัศนศึกษา',
        'ลำไพร ทองบ่อ' => 'นักวิชาการโสตทัศนศึกษา',
        'รักชาติ แดงเทโพธิ์' => 'นักวิชาการโสตทัศนศึกษา',
        'ปิยะสันต์ บุญพระ' => 'นักวิชาการโสตทัศนศึกษา',
        'จตุพล ฤทธิสิงห์' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'อาทิตย์ บรรเทา' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        
        'ธวัชชัย รัสสมบัติ' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'ทรงภพ จันทร์ลอย' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'รนภักดี ลิงลม' => 'พนักงานขับรถยนต์',
        'กิตติภณ รัดถา' => 'พนักงานขับรถยนต์',
        'ทิวา เนื่องทะบาล' => 'พนักงานขับรถยนต์',
        'นิรุตติ์ กองเงิน' => 'พนักงานขับรถยนต์',
        'อุทัย หาหอม' => 'พนักงานขับรถยนต์'
    ];
    foreach($map as $key => $val) {
        if(mb_strpos($th_name, $key) !== false) return $val;
    }
    return '';
}

// ฟังก์ชันจัดฟอร์แมตเบอร์โทร
function formatPhoneHtml($phone_str) {
    $val = trim((string)$phone_str);
    if (empty($val) || $val === '-') return "<span class='text-rose-500 font-bold'>-</span>";
    if ($val === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
    $phones = array_values(array_filter(array_map('trim', explode(',', $val))));
    $html = '<div class="space-y-1">';
    $count = count($phones);
    foreach($phones as $index => $p) {
        $comma = ($index < $count - 1) ? ',' : '';
        $html .= "<div class='whitespace-nowrap'>".htmlspecialchars($p).$comma."</div>";
    }
    $html .= '</div>';
    return $html;
}

// =====================================================================
// 🛠️ ระบบซ่อมแซมและปรับปรุงฐานข้อมูลอัตโนมัติ (AUTO-FIX DB STRUCTURE)
// =====================================================================

$conn->query("CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ใช้งานปกติ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$tech_cols = [
    'line_user_id' => 'VARCHAR(100) NULL',
    'department' => 'VARCHAR(255) NULL',
    'position' => 'VARCHAR(255) NULL',
    'phone' => 'VARCHAR(100) NULL',
    'avatar_url' => 'VARCHAR(255) NULL',
    'secret_code' => 'VARCHAR(10) NULL',
    'approval_status' => "VARCHAR(50) DEFAULT 'รอผูกบัญชี'",
    'status' => "VARCHAR(50) DEFAULT 'ว่าง'",
    'english_name' => 'VARCHAR(255) NULL'
];
foreach ($tech_cols as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM technicians LIKE '$col'");
    if($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE technicians ADD COLUMN $col $def");
    }
}

$conn->query("ALTER TABLE technicians CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE technicians MODIFY COLUMN department VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
$conn->query("ALTER TABLE technicians MODIFY COLUMN english_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
$conn->query("ALTER TABLE technicians MODIFY COLUMN position VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");

$res_fix = $conn->query("SELECT id FROM technicians WHERE approval_status IN ('รออนุมัติ', 'รอผูกบัญชี') AND (secret_code IS NULL OR secret_code = '')");
if ($res_fix && $res_fix->num_rows > 0) {
    while($rf = $res_fix->fetch_assoc()) {
        $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $conn->query("UPDATE technicians SET secret_code = '$code', approval_status = 'รอผูกบัญชี' WHERE id = " . $rf['id']);
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    role VARCHAR(50) DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$users_cols = [
    'password' => 'VARCHAR(255) NULL',
    'full_name' => 'VARCHAR(255) NULL',
    'english_name' => 'VARCHAR(255) NULL',
    'position' => 'VARCHAR(255) NULL',
    'phone' => 'VARCHAR(100) NULL',
    'department' => 'VARCHAR(255) NULL'
];
foreach ($users_cols as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $def");
    }
}

$conn->query("ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE users MODIFY COLUMN department VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
$conn->query("ALTER TABLE users MODIFY COLUMN full_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
$conn->query("ALTER TABLE users MODIFY COLUMN english_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
$conn->query("ALTER TABLE users MODIFY COLUMN position VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");

$check_repairs_table = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs_table && $check_repairs_table->num_rows > 0) {
    $conn->query("ALTER TABLE repairs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $check_tech_name = $conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'");
    if($check_tech_name && $check_tech_name->num_rows == 0) {
        $conn->query("ALTER TABLE repairs ADD COLUMN technician_name VARCHAR(100) NULL");
    }

    $check_root_cause = $conn->query("SHOW COLUMNS FROM repairs LIKE 'root_cause'");
    if($check_root_cause && $check_root_cause->num_rows == 0) {
        $conn->query("ALTER TABLE repairs ADD COLUMN root_cause TEXT NULL");
    }

    $check_rating = $conn->query("SHOW COLUMNS FROM repairs LIKE 'rating'");
    if($check_rating && $check_rating->num_rows == 0) {
        $conn->query("ALTER TABLE repairs ADD COLUMN rating INT DEFAULT 0");
    }
    $check_review = $conn->query("SHOW COLUMNS FROM repairs LIKE 'review_comment'");
    if($check_review && $check_review->num_rows == 0) {
        $conn->query("ALTER TABLE repairs ADD COLUMN review_comment TEXT NULL");
    }

    $conn->query("INSERT INTO users (username, full_name, phone, department, role) 
                  SELECT CONCAT('U', REPLACE(phone_number, '-', '')), reporter_name, phone_number, 'บุคลากรทั่วไป', 'User' 
                  FROM repairs 
                  WHERE reporter_name IS NOT NULL AND reporter_name != '' AND reporter_name NOT IN (SELECT full_name FROM users WHERE full_name IS NOT NULL) 
                  GROUP BY reporter_name, phone_number");
}

// =====================================================================
// ดึงข้อมูลเตรียมแสดงผล
// =====================================================================

$all_repairs_json = "[]";
$check_repairs_list = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs_list && $check_repairs_list->num_rows > 0) {
    $select_query = "SELECT * FROM repairs ORDER BY created_at DESC";
    $rep_res = $conn->query($select_query);
    $reps = [];
    if($rep_res) {
        while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
        $encoded = json_encode($reps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            $all_repairs_json = $encoded;
        }
    }
}

$tech_dept_map = [];
$tech_info_map = [];
$td_res = $conn->query("SELECT full_name, department, english_name, position FROM technicians");
if($td_res) {
    while($tr = $td_res->fetch_assoc()) {
        $tech_dept_map[$tr['full_name']] = !empty($tr['department']) ? $tr['department'] : 'ฝ่ายงานทั่วไป';
        
        list($th_name, $en_name) = splitThaiEngName($tr['full_name'], $tr['english_name']);
        $pos = !empty($tr['position']) ? $tr['position'] : getAutoPosition($th_name);
        
        $tech_info_map[$tr['full_name']] = [
            'th' => $th_name,
            'eng' => $en_name,
            'pos' => $pos
        ];
    }
}
$tech_dept_map_json = json_encode($tech_dept_map, JSON_UNESCAPED_UNICODE);
$tech_info_map_json = json_encode($tech_info_map, JSON_UNESCAPED_UNICODE);

// ✨ ดึงข้อมูลตาราง line_users เพื่อนำมาแมปกับชื่อไลน์ (reporter_name) ✨
$line_users_map = [];
$check_lu = $conn->query("SHOW TABLES LIKE 'line_users'");
if($check_lu && $check_lu->num_rows > 0) {
    $lu_res = $conn->query("SELECT line_display_name, real_name, phone_number FROM line_users");
    if($lu_res) {
        while($lu = $lu_res->fetch_assoc()) {
            $user_info = [
                'line_display_name' => $lu['line_display_name'],
                'real_name' => $lu['real_name'],
                'phone_number' => $lu['phone_number']
            ];
            if(!empty($lu['line_display_name'])) {
                $line_users_map[$lu['line_display_name']] = $user_info;
            }
            if(!empty($lu['real_name'])) {
                $line_users_map[$lu['real_name']] = $user_info;
            }
        }
    }
}
$line_users_map_json = json_encode($line_users_map, JSON_UNESCAPED_UNICODE);

// ดึงรายการตำแหน่งทั้งหมดสำหรับ Dropdown (Custom UI)
$db_positions = [];
$pos_query = $conn->query("SELECT DISTINCT position FROM technicians WHERE position IS NOT NULL AND position != '' UNION SELECT DISTINCT position FROM users WHERE position IS NOT NULL AND position != ''");
if($pos_query) {
    while($r = $pos_query->fetch_assoc()) {
        $db_positions[] = trim($r['position']);
    }
}
$default_positions = ['นักวิชาการคอมพิวเตอร์', 'นักวิชาการโสตทัศนศึกษา', 'เจ้าหน้าที่บริหารงานทั่วไป', 'พนักงานขับรถยนต์'];
$all_positions = array_values(array_unique(array_merge($default_positions, $db_positions)));
$available_positions_json = json_encode($all_positions, JSON_UNESCAPED_UNICODE);

$years_query = $conn->query("SELECT DISTINCT YEAR(created_at) as y FROM repairs WHERE created_at IS NOT NULL ORDER BY y DESC");
$available_years = [];
if($years_query && $years_query->num_rows > 0) {
    while($y_row = $years_query->fetch_assoc()) {
        if(!empty($y_row['y'])) $available_years[] = $y_row['y'];
    }
} else {
    $available_years[] = date('Y');
}
$thai_months = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];

// =====================================================================
// ระบบบันทึกและลบข้อมูล
// =====================================================================

if (isset($_GET['delete_asset'])) {
    $del_id = intval($_GET['delete_asset']);
    $conn->query("DELETE FROM assets WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=assets';</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_asset'])) {
    $asset_id = $_POST['asset_id'];
    $asset_code = $_POST['asset_code'];
    $asset_name = $_POST['asset_name'];
    $category = $_POST['category'];
    $status = $_POST['status'];

    if (empty($asset_id)) {
        $stmt = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $asset_code, $asset_name, $category, $status);
    } else {
        $stmt = $conn->prepare("UPDATE assets SET asset_code=?, asset_name=?, category=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $asset_code, $asset_name, $category, $status, $asset_id);
    }
    $stmt->execute();
    echo "<script>window.location.href='dashboard.php?tab=assets';</script>";
}

if (isset($_GET['delete_tech'])) {
    $del_id = intval($_GET['delete_tech']);
    $conn->query("DELETE FROM technicians WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if (isset($_GET['unlink_tech'])) {
    $unlink_id = intval($_GET['unlink_tech']);
    $new_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $conn->query("UPDATE technicians SET line_user_id = NULL, approval_status = 'รอผูกบัญชี', secret_code = '$new_code' WHERE id = $unlink_id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if (isset($_GET['delete_user'])) {
    $del_id = intval($_GET['delete_user']);
    $conn->query("DELETE FROM users WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $user_id = $_POST['user_id'];
    $role = $_POST['role']; 
    
    $full_name = !empty($_POST['full_name']) ? $_POST['full_name'] : NULL;
    $english_name = !empty($_POST['english_name']) ? $_POST['english_name'] : NULL;
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : NULL;
    
    $position = !empty($_POST['position']) ? $_POST['position'] : NULL;
    if (isset($_POST['position_select'])) {
        $pos_val = $_POST['position_select'];
        if ($pos_val === 'อื่นๆ' && !empty($_POST['position_custom'])) {
            $position = $_POST['position_custom'];
        } elseif (!empty($pos_val)) {
            $position = $pos_val;
        }
    }
    
    if ($role === 'Technician') {
        $department = isset($_POST['department_select']) ? $_POST['department_select'] : NULL;
        if ($department === 'อื่นๆ' && !empty($_POST['department_custom'])) {
            $department = $_POST['department_custom'];
        }

        $avatar_url = NULL;
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
            $allowed_extensions = array("jpg", "jpeg", "png", "webp", "gif");
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
                    $avatar_url = $target_path;
                }
            }
        }
        
        if (empty($user_id)) {
            $secret_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO technicians (full_name, english_name, position, phone, department, avatar_url, secret_code, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'รอผูกบัญชี')");
            if ($stmt) {
                $stmt->bind_param("sssssss", $full_name, $english_name, $position, $phone, $department, $avatar_url, $secret_code);
                if ($stmt->execute()) {
                    $msg = "เพิ่มข้อมูลช่างสำเร็จ<br>รหัสผูกบัญชีไลน์คือ: <b style='font-size:24px; color:#4f46e5; margin-top:10px; display:block;'>$secret_code</b>";
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'สำเร็จ!', html: \"$msg\", confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=technicians'; }); });</script>";
                } else {
                    $err = addslashes($stmt->error);
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการบันทึก', text: '$err', confirmButtonColor: '#ef4444' }); });</script>";
                }
            } else {
                $err = addslashes($conn->error);
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'error', title: 'ฐานข้อมูลมีปัญหา', text: '$err', confirmButtonColor: '#ef4444' }); });</script>";
            }
        } else {
            if ($avatar_url) {
                $stmt = $conn->prepare("UPDATE technicians SET full_name=?, english_name=?, position=?, phone=?, department=?, avatar_url=? WHERE id=?");
                if ($stmt) $stmt->bind_param("ssssssi", $full_name, $english_name, $position, $phone, $department, $avatar_url, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE technicians SET full_name=?, english_name=?, position=?, phone=?, department=? WHERE id=?");
                if ($stmt) $stmt->bind_param("sssssi", $full_name, $english_name, $position, $phone, $department, $user_id);
            }
            
            if ($stmt && $stmt->execute()) {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อัปเดตข้อมูลสำเร็จ!', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=technicians'; }); });</script>";
            } else {
                $err = addslashes($stmt ? $stmt->error : $conn->error);
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการอัปเดต', text: '$err', confirmButtonColor: '#ef4444' }); });</script>";
            }
        }
        
    } else {
        $username = $_POST['username'];
        $password = $_POST['password']; 
        if (isset($_POST['admin_level'])) {
            $role = $_POST['admin_level'];
        }
        
        $department = isset($_POST['department_select']) ? $_POST['department_select'] : NULL;
        if ($department === 'อื่นๆ' && !empty($_POST['department_custom'])) {
            $department = $_POST['department_custom'];
        }

        if (empty($user_id)) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, english_name, position, phone, department, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssssss", $username, $password, $full_name, $english_name, $position, $phone, $department, $role);
                if ($stmt->execute()) {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'เพิ่มข้อมูลผู้ดูแลระบบสำเร็จ!', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=technicians'; }); });</script>";
                } else {
                    $err = addslashes($stmt->error);
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการบันทึก', text: '$err', confirmButtonColor: '#ef4444' }); });</script>";
                }
            }
        } else {
            if (!empty($password)) {
                $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=?, english_name=?, position=?, phone=?, department=?, role=? WHERE id=?");
                if ($stmt) $stmt->bind_param("ssssssssi", $username, $password, $full_name, $english_name, $position, $phone, $department, $role, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, english_name=?, position=?, phone=?, department=?, role=? WHERE id=?");
                if ($stmt) $stmt->bind_param("sssssssi", $username, $full_name, $english_name, $position, $phone, $department, $role, $user_id);
            }
            if ($stmt && $stmt->execute()) {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อัปเดตข้อมูลสำเร็จ!', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=technicians'; }); });</script>";
            } else {
                $err = addslashes($stmt ? $stmt->error : $conn->error);
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: '$err', confirmButtonColor: '#ef4444' }); });</script>";
            }
        }
    }
}

if (isset($_GET['delete_reporter'])) {
    $del_name = $_GET['delete_reporter'];
    $stmt = $conn->prepare("DELETE FROM repairs WHERE reporter_name = ?");
    $stmt->bind_param("s", $del_name);
    $stmt->execute();
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'ลบประวัติสำเร็จ!', showConfirmButton: false, timer: 1500 }).then(() => { window.location.href='dashboard.php?tab=users'; }); });</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_reporter'])) {
    $old_name = $_POST['old_name'];
    $new_name = $_POST['new_name'];
    $new_phone = $_POST['new_phone'];
    
    // อัปเดตข้อมูลลงตาราง line_users โดยใช้ line_display_name เป็นตัวอ้างอิง
    $stmt = $conn->prepare("UPDATE line_users SET real_name = ?, phone_number = ? WHERE line_display_name = ?");
    $stmt->bind_param("sss", $new_name, $new_phone, $old_name);
    $stmt->execute();
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อัปเดตข้อมูลผู้แจ้งสำเร็จ!', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=users'; }); });</script>";
}

$tech_options = [];
$tech_list_res = $conn->query("SELECT DISTINCT full_name FROM technicians WHERE approval_status = 'อนุมัติแล้ว' AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
if($tech_list_res){
    while($t = $tech_list_res->fetch_assoc()){
        $tech_options[] = $t['full_name'];
    }
}

$custom_dept_order = [
    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล',
    'ฝ่ายงานโสตทัศนูปกรณ์',
    'ฝ่ายงานยานยนต์',
    'แม่บ้าน',
    'ฝ่ายงานทั่วไป',
    'อื่นๆ'
];

$dept_icons = [
    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล' => 'fas fa-laptop-code',
    'ฝ่ายงานยานยนต์' => 'fas fa-car',
    'ฝ่ายงานโสตทัศนูปกรณ์' => 'fas fa-video',
    'แม่บ้าน' => 'fas fa-broom'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS Repair Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .modern-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); border: 1px solid #f1f5f9; }
        #sidebar { width: 240px !important; min-width: 240px !important; max-width: 240px !important; }
        .sidebar-logo-box { height: 88px !important; padding: 0 24px !important; }
        .top-header { height: 88px !important; padding: 0 32px !important; }
        .nav-btn { width: calc(100% - 32px) !important; display: flex !important; align-items: center !important; padding: 0.65rem 1rem !important; margin: 2px 16px !important; border-radius: 12px !important; color: #64748b !important; font-weight: 600 !important; font-size: 0.875rem !important; transition: all 0.2s ease !important; cursor: pointer !important; }
        .nav-btn i { width: 1.5rem !important; text-align: center !important; font-size: 1rem !important; margin-right: 0.75rem !important; color: #94a3b8 !important; }
        .nav-btn:hover { background-color: #f8fafc !important; color: #4f46e5 !important; }
        .nav-btn:hover i { color: #4f46e5 !important; }
        .active-btn { background-color: #eef2ff !important; color: #4f46e5 !important; font-weight: 700 !important; }
        .active-btn i { color: #4f46e5 !important; }
        
        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20320%20512%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M31.3%20192h257.3c17.8%200%2026.7%2021.5%2014.1%2034.1L174.1%20354.8c-7.8%207.8-20.5%207.8-28.3%200L17.2%20226.1C4.6%20213.5%2013.5%20192%2031.3%20192z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 0.75rem center; 
            background-size: 0.65em auto;
            padding-right: 2.25rem !important; 
        }
        .dark .custom-select {
            background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20320%20512%22%3E%3Cpath%20fill%3D%22%23cbd5e1%22%20d%3D%22M31.3%20192h257.3c17.8%200%2026.7%2021.5%2014.1%2034.1L174.1%20354.8c-7.8%207.8-20.5%207.8-28.3%200L17.2%20226.1C4.6%20213.5%2013.5%20192%2031.3%20192z%22%2F%3E%3C%2Fsvg%3E');
        }
        
        ::-webkit-scrollbar { width: 8px; height: 12px; } 
        ::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 3px solid #f8fafc; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; margin: 0 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { border: 2px solid #f1f5f9; }
        
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        .badge-pending { background-color: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-progress { background-color: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background-color: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        @media print { aside, header, .no-print, #sidebarOverlay, #dash, #repairs, #technicians, #team_cards, #assets, #users, #reports { display: none !important; } }

        /* ✨ สไตล์สำหรับ Keyboard Navigation (หน้า Official Report) ✨ */
        .report-dropdown-item.kb-active-item {
            background-color: #eef2ff !important;
            color: #4f46e5 !important;
        }
        .report-dropdown-item.kb-active-item .bg-slate-100 {
            background-color: #e0e7ff !important;
            color: #6366f1 !important;
        }
        .report-dropdown-item.kb-active-item .fa-check {
            opacity: 1 !important;
        }

        /* ✨ สไตล์สำหรับ Custom Dropdown บนกราฟ ✨ */
        .chart-dropdown-item.kb-active-item {
            background-color: #eef2ff !important;
            color: #4f46e5 !important;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-indigo-100">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 z-40 hidden md:hidden backdrop-blur-sm transition-opacity" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="bg-white flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 border-r border-slate-100 no-print">
        <div class="sidebar-logo-box flex items-center border-b border-slate-50">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/30 mr-3.5 shrink-0">
                <i class="fas fa-tools text-white text-xl"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">MBS<span class="text-indigo-600">Repair</span></h1>
            </div>
        </div>
        
        <?php 
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dash';
        $pageTitlesArr = [
            'dash' => 'Dashboard Overview',
            'repairs' => 'All Repairs List',
            'technicians' => 'Team Management',
            'team_cards' => 'Team Management',
            'assets' => 'Assets Database',
            'users' => 'Reporter History',
            'reports' => 'Official Report'
        ];
        $currentTitle = isset($pageTitlesArr[$active_tab]) ? $pageTitlesArr[$active_tab] : 'Dashboard Overview';
        ?>
        <nav class="flex-1 py-6 flex flex-col overflow-y-auto">
            <p class="px-6 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Dashboard</p>
            <button onclick="show('dash')" class="nav-btn <?php echo $active_tab === 'dash' ? 'active-btn' : ''; ?>" id="btn-dash"><i class="fas fa-chart-pie"></i> Overview</button>
            <button onclick="show('repairs')" class="nav-btn <?php echo $active_tab === 'repairs' ? 'active-btn' : ''; ?>" id="btn-repairs"><i class="fas fa-list-ul"></i> Transactions</button>
            <button onclick="show('technicians')" class="nav-btn <?php echo $active_tab === 'technicians' ? 'active-btn' : ''; ?>" id="btn-technicians"><i class="fas fa-user-friends"></i> Team</button>
            <button onclick="show('team_cards')" class="nav-btn <?php echo $active_tab === 'team_cards' ? 'active-btn' : ''; ?>" id="btn-team_cards"><i class="fas fa-id-badge"></i> Technician</button>
            
            <p class="px-6 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 mt-6">Management</p>
            <button onclick="show('assets')" class="nav-btn <?php echo $active_tab === 'assets' ? 'active-btn' : ''; ?>" id="btn-assets"><i class="fas fa-box-open"></i> Assets</button>
            <button onclick="show('users')" class="nav-btn <?php echo $active_tab === 'users' ? 'active-btn' : ''; ?>" id="btn-users"><i class="fas fa-address-book"></i> Contacts</button>
            <button onclick="show('reports')" class="nav-btn <?php echo $active_tab === 'reports' ? 'active-btn' : ''; ?>" id="btn-reports"><i class="fas fa-file-export"></i> Reports</button>
            
            <div class="mt-auto pt-4 border-t border-slate-50">
                <a href="logout.php" class="nav-btn text-slate-500 hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-[#f8fafc]">
        
        <header class="top-header bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 flex items-center justify-between z-10 sticky top-0 no-print shadow-md shadow-indigo-200/50">
            <div class="flex items-center">
                <button onclick="toggleSidebar()" class="md:hidden mr-4 text-white hover:text-indigo-100 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h3 class="textxl md:text-3xl font-extrabold text-slate-900 font-bold text-white tracking-tight drop-shadow-sm" id="headerTitle"><?php echo $currentTitle; ?></h3>
            </div>
            
            <div class="flex items-center">
                <div class="flex items-center gap-3 cursor-pointer group">
                    <div class="text-right hidden sm:block">
                        <span class="block text-sm font-bold text-white drop-shadow-sm leading-none mb-1 group-hover:text-indigo-100 transition-colors">
                            <?php echo isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'); ?>
                        </span>
                        <span class="block text-[11px] text-indigo-100 font-semibold">Administrator</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white overflow-hidden border border-white/30 shadow-inner backdrop-blur-sm">
                        <img src="https://api.dicebear.com/7.x/notionists/svg?seed=<?php echo $_SESSION['username'] ?? 'admin'; ?>&backgroundColor=e2e8f0" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            
            <div id="dash" class="section <?php echo $active_tab === 'dash' ? '' : 'hidden'; ?> space-y-6 animate-fade-in no-print">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                        $resTotal = $conn->query("SELECT count(*) as c FROM repairs");
                        $cTotal = $resTotal ? $resTotal->fetch_assoc()['c'] : 0;
                        $resPend = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอรับเรื่อง'");
                        $cPend = $resPend ? $resPend->fetch_assoc()['c'] : 0;
                        $resProg = $conn->query("SELECT count(*) as c FROM repairs WHERE status='กำลังดำเนินการ'");
                        $cProg = $resProg ? $resProg->fetch_assoc()['c'] : 0;
                        $resComp = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว'");
                        $cComp = $resComp ? $resComp->fetch_assoc()['c'] : 0;
                    ?>
                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow cursor-pointer" onclick="filterRepairs('all')">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 text-xl"><i class="fas fa-layer-group"></i></div>
                            <span class="text-xs font-bold text-slate-400">TOTAL</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cTotal; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Total Repairs</p>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow cursor-pointer" onclick="filterRepairs('รอรับเรื่อง')">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500 text-xl"><i class="fas fa-clock"></i></div>
                            <span class="text-xs font-bold text-slate-400">WAITING</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cPend; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Pending</p>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow cursor-pointer" onclick="filterRepairs('กำลังดำเนินการ')">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-sky-50 flex items-center justify-center text-sky-500 text-xl"><i class="fas fa-spinner"></i></div>
                            <span class="text-xs font-bold text-slate-400">ACTIVE</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cProg; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">In Progress</p>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow cursor-pointer" onclick="filterRepairs('ซ่อมเสร็จแล้ว')">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl"><i class="fas fa-check-circle"></i></div>
                            <span class="text-xs font-bold text-slate-400">DONE</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cComp; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Completed</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Equipment Analytics</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="equip-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'equip-Month', renderEquipChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'equip-Month')">
                                        <span id="equip-MonthText" class="truncate">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="equip-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('equip-Month', 'all', 'เดือน', renderEquipChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('equip-Month', '{$num_pad}', '{$name}', renderEquipChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="equipMonth" value="all">
                                </div>
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="equip-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'equip-Year', renderEquipChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'equip-Year')">
                                        <span id="equip-YearText" class="truncate">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="equip-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('equip-Year', 'all', 'ปี (พ.ศ.)', renderEquipChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('equip-Year', '{$y}', 'พ.ศ. {$thai_y}', renderEquipChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="equipYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 relative w-full h-[280px]">
                            <canvas id="mainEquipChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Work Status</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">สัดส่วนสถานะการดำเนินงาน</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="status-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'status-Month', renderStatusChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'status-Month')">
                                        <span id="status-MonthText" class="truncate">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="status-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('status-Month', 'all', 'เดือน', renderStatusChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('status-Month', '{$num_pad}', '{$name}', renderStatusChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="statusMonth" value="all">
                                </div>
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="status-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'status-Year', renderStatusChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'status-Year')">
                                        <span id="status-YearText" class="truncate">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="status-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('status-Year', 'all', 'ปี (พ.ศ.)', renderStatusChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('status-Year', '{$y}', 'พ.ศ. {$thai_y}', renderStatusChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="statusYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 relative w-full h-[280px] flex justify-center items-center">
                            <canvas id="mainStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Top Locations</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">ห้อง/สถานที่ ที่เกิดปัญหาบ่อยที่สุด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="loc-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'loc-Month', renderLocChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'loc-Month')">
                                        <span id="loc-MonthText" class="truncate">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="loc-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('loc-Month', 'all', 'เดือน', renderLocChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('loc-Month', '{$num_pad}', '{$name}', renderLocChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="locMonth" value="all">
                                </div>
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="loc-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'loc-Year', renderLocChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'loc-Year')">
                                        <span id="loc-YearText" class="truncate">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="loc-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('loc-Year', 'all', 'ปี (พ.ศ.)', renderLocChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('loc-Year', '{$y}', 'พ.ศ. {$thai_y}', renderLocChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="locYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="relative w-full h-[250px]"> 
                            <canvas id="mainLocChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Technician Workload</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">ปริมาณงานที่รับผิดชอบรายบุคคล</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="tech-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'tech-Month', renderTechChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'tech-Month')">
                                        <span id="tech-MonthText" class="truncate">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="tech-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('tech-Month', 'all', 'เดือน', renderTechChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('tech-Month', '{$num_pad}', '{$name}', renderTechChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="techMonth" value="all">
                                </div>
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="tech-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'tech-Year', renderTechChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'tech-Year')">
                                        <span id="tech-YearText" class="truncate">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="tech-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('tech-Year', 'all', 'ปี (พ.ศ.)', renderTechChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('tech-Year', '{$y}', 'พ.ศ. {$thai_y}', renderTechChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="techYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="relative w-full h-[250px]"> 
                            <canvas id="mainTechChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
                    
                    <div class="modern-card p-6 flex flex-col lg:col-span-7 justify-between">
                        <div class="flex flex-col sm:flex-row justify-between items-start mb-4 gap-4">
                            <div class="flex flex-col">
                                <h3 class="font-extrabold text-slate-800 text-lg">Customer Satisfaction</h3>
                                <span class="text-sm font-medium text-slate-400 mt-0.5">คะแนนความพึงพอใจการให้บริการ</span>
                                <span class="text-[12px] text-indigo-500 font-bold mt-1"><i class="fas fa-hand-pointer mr-1"></i>คลิกที่แท่งกราฟเพื่อดูรีวิวช่าง</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="rating-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'rating-Month', renderRatingChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'rating-Month')">
                                        <span id="rating-MonthText" class="truncate">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="rating-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('rating-Month', 'all', 'เดือน', renderRatingChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('rating-Month', '{$num_pad}', '{$name}', renderRatingChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="ratingMonth" value="all">
                                </div>
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="rating-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'rating-Year', renderRatingChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'rating-Year')">
                                        <span id="rating-YearText" class="truncate">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="rating-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('rating-Year', 'all', 'ปี (พ.ศ.)', renderRatingChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('rating-Year', '{$y}', 'พ.ศ. {$thai_y}', renderRatingChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="ratingYear" value="all">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center w-full mt-2 flex-1">
                            <div class="relative w-full h-[380px]">
                                <canvas id="mainRatingChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="modern-card overflow-hidden flex flex-col lg:col-span-5 h-full">
                        <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col xl:flex-row justify-between items-start xl:items-center shrink-0 gap-3">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Top Reporters</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="reporter-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'reporter-Month', renderTopReporters)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'reporter-Month')">
                                        <span id="reporter-MonthText" class="truncate">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="reporter-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('reporter-Month', 'all', 'เดือน', renderTopReporters)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('reporter-Month', '{$num_pad}', '{$name}', renderTopReporters)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="reporterMonth" value="all">
                                </div>
                                <div class="relative w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="reporter-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'reporter-Year', renderTopReporters)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'reporter-Year')">
                                        <span id="reporter-YearText" class="truncate">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="reporter-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('reporter-Year', 'all', 'ปี (พ.ศ.)', renderTopReporters)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('reporter-Year', '{$y}', 'พ.ศ. {$thai_y}', renderTopReporters)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="reporterYear" value="all">
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-4 md:px-5 py-3 bg-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center shrink-0 z-10 shadow-sm gap-3">
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mr-1">จัดอันดับ:</span>
                                <div class="flex items-center gap-1.5" id="topReportersFilterContainer">
                                    <button id="btnFilterTop3" onclick="setTopReportersFilter(3)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 3</button>
                                    <button id="btnFilterTop5" onclick="setTopReportersFilter(5)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700">Top 5</button>
                                    <button id="btnFilterTop10" onclick="setTopReportersFilter(10)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 10</button>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button id="btnFilterTopAll" onclick="setTopReportersFilter('all')" class="px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">
                                    ทั้งหมด
                                </button>
                            </div>
                        </div>

                        <div class="p-0 overflow-y-auto flex-1 bg-white custom-scrollbar max-h-[380px]">
                            <div class="divide-y divide-slate-100" id="topReportersList">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 mt-6">
                    <div class="modern-card overflow-hidden flex flex-col col-span-full">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Recent Transactions</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">Latest 5 repairs in system</p>
                            </div>
                            <button onclick="show('repairs')" class="flex items-center text-sm text-slate-600 font-bold hover:text-indigo-600 transition-colors group">
                                See All <i class="fas fa-arrow-right ml-2 text-xs text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto pb-4 custom-scrollbar">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-[#fef9c3] text-[#854d0e] text-xs uppercase tracking-widest font-bold border-b border-[#fef08a]">
                                    <tr>
                                        <th class="px-6 py-4">Date / Time</th>
                                        <th class="px-6 py-4">Ticket No.</th>
                                        <th class="px-6 py-4">Reporter</th>
                                        <th class="px-6 py-4">Equipment</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100">
                                    <?php
                                    $recent_dash = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                    if($recent_dash && $recent_dash->num_rows > 0){
                                        while($rd = $recent_dash->fetch_assoc()) {
                                            $stClass = ($rd['status'] == 'รอรับเรื่อง') ? 'badge-pending' : (($rd['status'] == 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-success');
                                            $statusText = htmlspecialchars($rd['status']);
                                            $ticket_no = formatEmptyOrDash($rd['ticket_no']);
                                            
                                            // ✨ ดึงชื่อจริงและเบอร์โทรมาแสดง ✨
                                            $raw_rep = trim((string)$rd['reporter_name']);
                                            $disp_name = $raw_rep;
                                            if (isset($line_users_map[$raw_rep]) && !empty($line_users_map[$raw_rep]['real_name'])) {
                                                $disp_name = $line_users_map[$raw_rep]['real_name'];
                                            }
                                            $reporter_name = formatEmptyOrDash($disp_name);
                                            $phone_number = formatEmptyOrDash($rd['phone_number']);
                                            
                                            $equipment_type = formatEmptyOrDash($rd['equipment_type']);
                                            
                                            $has_created = (!empty($rd['created_at']) && $rd['created_at'] != '0000-00-00 00:00:00');
                                            $date_fmt = $has_created ? date("Y-m-d", strtotime($rd['created_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                            $time_fmt = $has_created ? date("H:i", strtotime($rd['created_at'])) : '';
                                            $time_html = $time_fmt ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$time_fmt}</div>" : "";
                                            
                                            $imageIcon = "";
                                            if(isset($rd['image_path']) && !empty($rd['image_path'])) {
                                                $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                                            }
                                            
                                            echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                    <div class='font-medium text-slate-700'>{$date_fmt}</div>
                                                    {$time_html}
                                                </td>
                                                <td class='px-6 py-4 align-top text-slate-500 font-mono font-semibold'>{$ticket_no}</td>
                                                <td class='px-6 py-4 align-top'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 mr-3 text-xs shrink-0'><i class='fas fa-user'></i></div>
                                                        <div>
                                                            <div class='text-slate-800 font-bold'>{$reporter_name}</div>
                                                            <div class='text-slate-500 text-[11px] font-medium mt-0.5'>{$phone_number}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 align-top text-slate-600 font-medium'>{$equipment_type} {$imageIcon}</td>
                                                <td class='px-6 py-4 align-middle text-center'><span class='{$stClass}'>{$statusText}</span></td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>No transactions found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
             </div>

            <!-- ✨ เริ่มต้นส่วนหน้า Transactions ที่หายไป (นำกลับมาให้แล้วครับ!) ✨ -->
            <div id="repairs" class="section <?php echo $active_tab === 'repairs' ? '' : 'hidden'; ?> space-y-6 no-print">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Repairs List</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">All repair transactions</p>
                        </div>
                        <div class="w-full md:w-auto relative">
                            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchInput" placeholder="ค้นหาเลขที่ใบงาน หรือสถานะ..." class="w-full md:w-64 bg-slate-50 border border-slate-200 text-sm rounded-xl pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium">
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full max-h-[70vh] overflow-y-auto custom-scrollbar relative">
                        <table class="w-full text-left whitespace-nowrap min-w-[1200px]" id="repairsTable">
                            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4">Date / Time</th>
                                    <th class="px-6 py-4">Ticket No.</th>
                                    <th class="px-6 py-4">Reporter</th>
                                    <th class="px-6 py-4">Equipment</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4">Technician</th>
                                    <th class="px-6 py-4">Received At</th>
                                    <th class="px-6 py-4">Root Cause</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4">Completed At</th>
                                    <th class="px-6 py-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $select_query = "SELECT * FROM repairs ORDER BY created_at DESC";
                                $res = $conn->query($select_query);

                                if($res && $res->num_rows > 0){
                                    while($row = $res->fetch_assoc()) {
                                        $stClass = ($row['status'] == 'รอรับเรื่อง') ? 'badge-pending' : (($row['status'] == 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-success');
                                        
                                        $ticket_no = formatEmptyOrDash($row['ticket_no']);
                                        
                                        // ✨ ดึงชื่อจริงและเบอร์โทร ✨
                                        $raw_rep = trim((string)$row['reporter_name']);
                                        $disp_name = $raw_rep;
                                        if (isset($line_users_map[$raw_rep]) && !empty($line_users_map[$raw_rep]['real_name'])) {
                                            $disp_name = $line_users_map[$raw_rep]['real_name'];
                                        }
                                        
                                        $reporter_name = formatEmptyOrDash($disp_name);
                                        $phone_number = formatEmptyOrDash($row['phone_number']);
                                        
                                        $equipment_type = formatEmptyOrDash($row['equipment_type']);
                                        $problem_desc = formatEmptyOrDash($row['problem_desc']);
                                        
                                        $t_pos = ''; 
                                        $t_eng = '';
                                        $t_th = '';
                                        
                                        if (!empty($row['technician_name']) && $row['technician_name'] !== '-') {
                                            $t_raw = $row['technician_name'];
                                            if (isset($tech_info_map[$t_raw])) {
                                                $t_th = htmlspecialchars($tech_info_map[$t_raw]['th']);
                                                $t_eng = htmlspecialchars($tech_info_map[$t_raw]['eng']);
                                                $t_pos = htmlspecialchars($tech_info_map[$t_raw]['pos']);
                                            } else {
                                                list($th_name, $en_name) = splitThaiEngName($t_raw, '');
                                                $t_th = htmlspecialchars($th_name);
                                                $t_eng = htmlspecialchars($en_name);
                                                $t_pos = htmlspecialchars(getAutoPosition($th_name));
                                            }
                                            
                                            $techHtml = "<div class='text-blue-600 font-bold hover:text-blue-500 transition-colors cursor-default'>{$t_th}</div>";
                                            if (!empty($t_eng)) {
                                                $techHtml .= "<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>{$t_eng}</div>";
                                            }
                                            $techName = $techHtml;
                                        } else {
                                            $techName = "<span class='text-rose-500 font-bold'>-</span>";
                                        }

                                        $dept_str = isset($tech_dept_map[$row['technician_name']]) ? $tech_dept_map[$row['technician_name']] : 'General';
                                        if (empty($row['technician_name']) || $row['technician_name'] === '-') {
                                            $deptEng = "<span class='text-rose-500 font-bold'>-</span>";
                                        } else {
                                            $deptEng = "<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold mb-1 shadow-sm'>{$dept_str}</div>";
                                            if (!empty($t_pos)) {
                                                $deptEng .= "<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>{$t_pos}</div>";
                                            }
                                        }

                                        $has_created = (!empty($row['created_at']) && $row['created_at'] != '0000-00-00 00:00:00');
                                        $created_date = $has_created ? date('Y-m-d', strtotime($row['created_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $created_time = $has_created ? date('H:i', strtotime($row['created_at'])) : '';
                                        $created_time_html = $created_time ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$created_time}</div>" : "";

                                        $has_received = (!empty($row['created_at']) && $row['created_at'] != '0000-00-00 00:00:00');
                                        $received_date = $has_received ? date('Y-m-d', strtotime($row['created_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $received_time = $has_received ? date('H:i', strtotime($row['created_at'])) : '';
                                        $received_time_html = $received_time ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$received_time}</div>" : "";

                                        $has_completed = (!empty($row['completed_at']) && $row['completed_at'] != '0000-00-00 00:00:00');
                                        $completed_date = $has_completed ? date('Y-m-d', strtotime($row['completed_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $completed_time = $has_completed ? date('H:i', strtotime($row['completed_at'])) : '';
                                        $completed_time_html = $completed_time ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$completed_time}</div>" : "";

                                        $rootCause = !empty($row['root_cause']) && $row['root_cause'] !== '-' ? "<span class='text-slate-700 font-medium'>".htmlspecialchars($row['root_cause'])."</span>" : "<span class='text-rose-500 font-bold'>-</span>";

                                        $imageIcon = "";
                                        if(isset($row['image_path']) && !empty($row['image_path'])) {
                                            $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                                        }

                                        echo "<tr class='hover:bg-slate-50/50 transition-colors search-row'>
                                            <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                <div class='font-medium text-slate-700'>{$created_date}</div>
                                                {$created_time_html}
                                            </td>
                                            <td class='px-6 py-4 align-top font-mono font-semibold text-slate-600'>{$ticket_no}</td>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='text-slate-800 font-bold'>{$reporter_name}</div>
                                                <div class='text-slate-500 text-[11px] font-medium mt-0.5'>{$phone_number}</div>
                                            </td>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='text-slate-800 font-bold'>{$equipment_type} {$imageIcon}</div>
                                                <div class='text-slate-500 text-[11px] font-medium mt-0.5 max-w-[150px] truncate' title='".strip_tags($problem_desc)."'>{$problem_desc}</div>
                                            </td>
                                            <td class='px-6 py-4 align-top'>{$deptEng}</td>
                                            <td class='px-6 py-4 align-top'>{$techName}</td>
                                            <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                <div class='font-medium text-slate-700'>{$received_date}</div>
                                                {$received_time_html}
                                            </td>
                                            <td class='px-6 py-4 align-top'>{$rootCause}</td>
                                            <td class='px-6 py-4 align-middle text-center'><span class='{$stClass}'>{$row['status']}</span></td>
                                            <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                <div class='font-medium text-emerald-700'>{$completed_date}</div>
                                                {$completed_time_html}
                                            </td>
                                            <td class='px-6 py-4 align-middle text-center'>
                                                <div class='flex items-center justify-center'>
                                                    <a target='_blank' href='update_repair.php?id={$row['id']}' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100 shadow-2xs' title='Edit'><i class='fas fa-pen-to-square'></i></a>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='11' class='px-6 py-16 text-center text-slate-400 font-medium'>No records found</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- ✨ สิ้นสุดส่วนหน้า Transactions ✨ -->

            <div id="technicians" class="section <?php echo $active_tab === 'technicians' ? '' : 'hidden'; ?> space-y-6 no-print">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
                    <div>
                        
                    </div>
                    <div class="flex flex-col md:flex-row w-full md:w-auto gap-3">
                        <button onclick="openTechAdminModal('Admin')" class="flex-1 md:flex-none bg-white border border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:border-indigo-300 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition-all flex items-center justify-center"><i class="fas fa-user-shield mr-2"></i> Add Admin</button>
                        <button onclick="openTechAdminModal('Technician')" class="flex-1 md:flex-none bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-indigo-200 flex items-center justify-center transition-all"><i class="fas fa-plus mr-2"></i> Add Technician</button>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg md:text-xl font-extrabold text-slate-800 flex items-center">Administrators</h3>
                    <p class="text-sm font-medium text-slate-500 mt-1 mb-5">Manage administrators</p>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto w-full pb-4 custom-scrollbar">
                            <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                                <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">Username</th>
                                        <th class="px-6 py-4">Name</th>
                                        <th class="px-6 py-4">Contact</th>
                                        <th class="px-6 py-4 text-center">Role</th>
                                        <th class="px-6 py-4 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $admin_res = $conn->query("SELECT * FROM users WHERE LOWER(role) IN ('admin', 'executive') ORDER BY id DESC");
                                    if($admin_res && $admin_res->num_rows > 0){
                                        while($u = $admin_res->fetch_assoc()) {
                                            $r_lower = strtolower($u['role']);
                                            $roleDisplay = ($r_lower == 'executive') ? 'Executive' : 'Admin';
                                            $roleClass = ($r_lower == 'executive') ? "bg-amber-100 text-amber-700" : "bg-purple-100 text-purple-700";
                                            $icon = ($r_lower == 'executive') ? "fa-user-tie text-amber-500" : "fa-shield-alt text-purple-500";
                                            
                                            $js_raw_fname = htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES);
                                            list($th_name, $en_name) = splitThaiEngName($u['full_name'], $u['english_name']);
                                            $js_fname = htmlspecialchars($th_name, ENT_QUOTES); 
                                            $js_ename = htmlspecialchars($en_name, ENT_QUOTES);

                                            $js_uid = $u['id']; 
                                            $js_uname = htmlspecialchars($u['username'], ENT_QUOTES); 
                                            $js_phone = htmlspecialchars($u['phone'] ?? '', ENT_QUOTES); 
                                            $js_dept = htmlspecialchars($u['department'] ?? '', ENT_QUOTES); 
                                            $js_role = htmlspecialchars($u['role'], ENT_QUOTES);
                                            
                                            $u_username = formatEmptyOrDash($u['username']);
                                            $th_name_html = (!empty($th_name) && $th_name !== '-') ? htmlspecialchars($th_name) : "<span class='text-rose-500 font-bold'>-</span>";
                                            $en_name_html = (!empty($en_name) && $en_name !== '-') ? "<div class='text-slate-400 font-medium text-[11px] mt-0.5'>".htmlspecialchars($en_name)."</div>" : "";

                                            echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                <td class='px-6 py-4 align-top font-bold text-slate-700'>{$u_username}</td>
                                                <td class='px-6 py-4 align-top'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 shrink-0'><i class='fas {$icon} text-xs'></i></div>
                                                        <div>
                                                            <div class='text-slate-800 font-bold'>{$th_name_html}</div>
                                                            {$en_name_html}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 align-top text-slate-500 font-medium'>".formatPhoneHtml($u['phone'])."</td>
                                                <td class='px-6 py-4 align-middle text-center'><span class='px-3 py-1 rounded-full text-[10px] font-bold {$roleClass}'>{$roleDisplay}</span></td>
                                                <td class='px-6 py-4 align-middle text-center'>
                                                    <div class='flex items-center justify-center space-x-2'>
                                                        <button onclick=\"openTechAdminModal('{$js_role}', '$js_uid', '$js_uname', '$js_fname', '$js_ename', '', '$js_phone', '$js_dept', '')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$u['id']})\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>No admins found</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-24 space-y-6">
                    <div class="flex flex-col lg:flex-row justify-between items-start gap-4">
                        <div class="pt-1.5">
                            <h3 class="text-lg md:text-xl font-extrabold text-slate-800 flex items-center leading-none">Technicians</h3>
                            <p class="text-sm font-medium text-slate-500 mt-1.5">Manage technicians</p>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2.5 items-center w-full lg:w-auto">
                            <!-- ช่องค้นหา แบบแยกการทำงานสำหรับหน้า Team -->
                            <div class="relative w-full sm:w-48 lg:w-56 mb-2 sm:mb-0">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="text" id="search-tech-table" oninput="searchTeamTable()" placeholder="ค้นหาชื่อช่างทั้งหมด..." class="w-full bg-white border border-slate-200 text-sm rounded-full pl-9 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium shadow-sm">
                            </div>
                            
                            <div class="flex flex-wrap gap-2.5">
                                <button onclick="filterDeptTable('all')" id="btn-filter-all" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-indigo-600 text-white border border-indigo-600 shadow-md shadow-indigo-200 cursor-pointer">ทั้งหมด</button>
                                <button onclick="filterDeptTable('ฝ่ายงานบริการเทคโนโลยีดิจิทัล')" id="btn-filter-digital" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm cursor-pointer">บริการเทคโนโลยีดิจิทัล</button>
                                <button onclick="filterDeptTable('ฝ่ายงานโสตทัศนูปกรณ์')" id="btn-filter-av" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm cursor-pointer">โสตทัศนูปกรณ์</button>
                                <button onclick="filterDeptTable('ฝ่ายงานยานยนต์')" id="btn-filter-auto" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm cursor-pointer">ยานยนต์</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="techniciansTableContainer" class="w-full overflow-x-auto pb-4 custom-scrollbar">
                        <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                            <tbody class="text-sm" id="techniciansTableBody">
                            <?php 
                            $techs_by_dept = [];
                            $tech_res = $conn->query("SELECT * FROM technicians ORDER BY department ASC, id DESC");
                            
                            if($tech_res && $tech_res->num_rows > 0){
                                while($t = $tech_res->fetch_assoc()) {
                                    $dept = !empty($t['department']) ? $t['department'] : 'ฝ่ายงานทั่วไป';
                                    $techs_by_dept[$dept][] = $t;
                                }
                            }
                            
                            uksort($techs_by_dept, function($a, $b) use ($custom_dept_order) {
                                $pos_a = array_search($a, $custom_dept_order);
                                $pos_b = array_search($b, $custom_dept_order);
                                $pos_a = ($pos_a === false) ? 999 : $pos_a;
                                $pos_b = ($pos_b === false) ? 999 : $pos_b;
                                if ($pos_a == $pos_b) return strcmp($a, $b);
                                return $pos_a - $pos_b;
                            });
                            
                            if (empty($techs_by_dept)) {
                                echo "<tr><td colspan='6' class='px-6 py-12 text-center text-slate-400 font-medium'>No technicians found</td></tr>";
                            } else {
                                $tbl_dept_icons = [
                                    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล' => 'fas fa-laptop-code',
                                    'ฝ่ายงานยานยนต์' => 'fas fa-car',
                                    'ฝ่ายงานโสตทัศนูปกรณ์' => 'fas fa-video',
                                    'แม่บ้าน' => 'fas fa-broom'
                                ];

                                foreach ($techs_by_dept as $dept => $techs) {
                                    $tbl_icon = isset($tbl_dept_icons[$dept]) ? $tbl_dept_icons[$dept] : 'fas fa-users';
                                    
                                    echo "<tr class='tech-dept-header' data-dept='".htmlspecialchars($dept)."'>
                                            <td colspan='6' class='p-0 border-0 bg-transparent'>
                                                <div class='relative overflow-hidden flex items-center justify-between bg-blue-500 p-4 rounded-t-xl mb-[2px] mt-6 shadow-sm'>
                                                    <div class='absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl pointer-events-none'></div>
                                                    <div class='absolute bottom-0 right-1/4 w-20 h-20 bg-white opacity-10 rounded-full blur-xl pointer-events-none'></div>
                                                    
                                                    <div class='flex items-center relative z-10 pl-1'>
                                                        <div class='w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-white/20 backdrop-blur-md text-white flex items-center justify-center mr-3 sm:mr-4 border border-white/30 shadow-inner shrink-0'>
                                                            <i class='{$tbl_icon} text-base sm:text-lg'></i>
                                                        </div>
                                                        <div>
                                                            <h3 class='font-extrabold text-sm sm:text-base text-white tracking-wide drop-shadow-md leading-tight'>
                                                                ".htmlspecialchars($dept)."
                                                            </h3>
                                                            <!-- ✨ ปรับฟอนต์ให้ใหญ่ขึ้นเพื่อความชัดเจน ✨ -->
                                                            <p class='text-blue-100 text-[11px] sm:text-xs font-medium mt-0.5 opacity-90 tracking-wider'>ทีมช่างผู้รับผิดชอบประจำฝ่าย</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class='hidden sm:flex items-center pr-1 relative z-10'>
                                                        <span class='bg-white/20 backdrop-blur-md text-white text-[10px] sm:text-[11px] font-bold px-3 py-1.5 rounded-full shadow-sm flex items-center'>
                                                            <i class='fas fa-user-check mr-1.5 opacity-80'></i> ".count($techs)." คน
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                          </tr>";

                                    echo "<tr class='bg-[#fef9c3] text-[#854d0e] text-[11px] uppercase tracking-widest font-extrabold tech-col-header' data-dept='".htmlspecialchars($dept)."'>
                                            <th class='px-6 py-4 border-0'>Name</th>
                                            <th class='px-6 py-4 border-0'>Department</th>
                                            <th class='px-6 py-4 border-0'>Contact</th> 
                                            <th class='px-6 py-4 text-center border-0'>Status / Code</th>
                                            <th class='px-6 py-4 text-center border-0'>Jobs</th>
                                            <th class='py-4 pl-6 pr-[80px] text-right border-0'>Action</th>
                                        </tr>";

                                    foreach($techs as $t) {
                                        $js_raw_fname = htmlspecialchars($t['full_name'] ?? '', ENT_QUOTES);
                                        list($th_name, $en_name) = splitThaiEngName($t['full_name'], $t['english_name']);
                                        $js_fname = htmlspecialchars($th_name, ENT_QUOTES); 
                                        $js_ename = htmlspecialchars($en_name, ENT_QUOTES);
                                        
                                        $search_name = preg_replace('/\s+/', '', strtolower($t['full_name'] . $en_name . $th_name));
                                        $js_search_name = htmlspecialchars($search_name, ENT_QUOTES);
                                        
                                        $pos = !empty($t['position']) ? $t['position'] : getAutoPosition($th_name);
                                        $js_pos = htmlspecialchars($pos, ENT_QUOTES);

                                        $js_uid = $t['id']; $js_phone = htmlspecialchars($t['phone'] ?? '', ENT_QUOTES); $js_dept = htmlspecialchars($t['department'] ?? '', ENT_QUOTES); $js_role = 'Technician';
                                        
                                        $total_jobs = 0;
                                        if(!empty($t['full_name'])) {
                                            $safe_tech_name = $conn->real_escape_string($t['full_name']);
                                            $job_res = $conn->query("SELECT COUNT(id) as c FROM repairs WHERE technician_name = '{$safe_tech_name}'");
                                            if($job_res) $total_jobs = $job_res->fetch_assoc()['c'];
                                        }

                                        $statusBadge = ($t['approval_status'] == 'อนุมัติแล้ว') 
                                            ? "<span class='px-3 py-1.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100'><i class='fas fa-check-circle mr-1'></i> ผูกบัญชีแล้ว</span>" 
                                            : "<span class='px-3 py-1.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 tracking-widest border border-amber-200'>รหัส: " . ($t['secret_code'] ? $t['secret_code'] : 'N/A') . "</span>";

                                        $unlinkBtn = ($t['approval_status'] == 'อนุมัติแล้ว') 
                                            ? "<button onclick=\"confirmUnlink({$t['id']})\" class='w-8 h-8 rounded-lg bg-orange-50 text-orange-500 hover:text-white hover:bg-orange-500 transition-all flex items-center justify-center shadow-xs' title='ยกเลิกผูกบัญชี LINE'><i class='fas fa-unlink'></i></button>" 
                                            : "";

                                        $img_src = !empty($t['avatar_url']) ? htmlspecialchars($t['avatar_url']) : "https://api.dicebear.com/7.x/notionists/svg?seed=".urlencode($t['full_name'])."&backgroundColor=e2e8f0";
                                        
                                        $th_name_html = (!empty($th_name) && $th_name !== '-') ? htmlspecialchars($th_name) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $en_name_html = (!empty($en_name) && $en_name !== '-') ? "<div class='text-slate-400 font-medium text-[11px] mt-0.5'>".htmlspecialchars($en_name)."</div>" : "";
                                        
                                        $pos_html = (!empty($pos) && $pos !== '-') ? "<div class='text-[11px] text-slate-500 font-medium mt-0.5'>".htmlspecialchars($pos)."</div>" : "";

                                        echo "<tr class='bg-white hover:bg-slate-50/50 transition-colors border-b border-slate-100 tech-dept-row' data-dept='".htmlspecialchars($dept)."' data-tech-name='{$js_search_name}'>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='flex items-center'>
                                                    <img src='{$img_src}' onerror=\"this.onerror=null; this.src='https://api.dicebear.com/7.x/notionists/svg?seed=".urlencode($t['full_name'])."&backgroundColor=e2e8f0'\" onclick=\"openImageModal(this.src)\" class='w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-sm mr-4 shrink-0 cursor-pointer hover:scale-105 transition-all hover:ring-2 hover:ring-indigo-400' alt='avatar' title='คลิกเพื่อดูรูปขยาย'>
                                                    <div>
                                                        <div class='text-slate-800 font-bold'>{$th_name_html}</div>
                                                        {$en_name_html}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='text-slate-700 font-bold'>{$dept}</div>
                                                {$pos_html}
                                            </td>
                                            <td class='px-6 py-4 align-top text-slate-500 font-medium'>".formatPhoneHtml($t['phone'])."</td> 
                                            <td class='px-6 py-4 align-middle text-center'>{$statusBadge}</td>
                                            <td class='px-6 py-4 align-middle text-center'><span class='px-3 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600'>{$total_jobs}</span></td>
                                            <td class='px-6 py-4 align-middle text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    {$unlinkBtn}
                                                    <button onclick=\"viewHistory('{$js_raw_fname}', 'technician')\" class='bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>View</span></button>
                                                    <button onclick=\"openTechAdminModal('{$js_role}', '$js_uid', '', '$js_fname', '$js_ename', '$js_pos', '$js_phone', '$js_dept', '{$img_src}')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDelete('tech', {$t['id']})\" class='w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:text-white hover:bg-rose-500 transition-all flex items-center justify-center shadow-xs'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                    
                                    echo "<tr class='tech-dept-spacer' data-dept='".htmlspecialchars($dept)."'><td colspan='6' class='h-6 border-0 bg-transparent'></td></tr>";
                                }
                            } 
                            ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="tech-empty-state hidden w-full modern-card p-12 flex-col items-center justify-center mt-2 border-2 border-dashed border-rose-100 bg-rose-50/30 text-center rounded-3xl">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-5 mx-auto shadow-sm border border-rose-50">
                            <i class="fas fa-user-times text-3xl text-rose-300"></i>
                        </div>
                        <h3 class="font-extrabold text-xl mb-2 text-rose-500">ไม่พบรายชื่อช่างในระบบ</h3>
                        <p class="text-slate-500 text-sm font-medium max-w-md mx-auto leading-relaxed">
                            ไม่มีช่างชื่อนี้อยู่ในระบบ ลองตรวจสอบตัวสะกด ทั้งภาษาไทยและภาษาอังกฤษ<br>ดูอีกครั้งนะครับ<br>
                            <span class="text-xs text-slate-400 mt-2 block">ถ้าเป็นช่างใหม่ ต้องทำการ <span onclick="openTechAdminModal('Technician')" class="text-indigo-600 font-bold cursor-pointer hover:text-indigo-800 hover:underline transition-colors">"Add Technician"</span> เพิ่มเข้าสู่ระบบก่อน</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ===================================================================================
                 ✨ ส่วนการแสดงผล Team Management (ทำเนียบช่าง - Card View) ✨
                 =================================================================================== -->
            <div id="team_cards" class="section <?php echo $active_tab === 'team_cards' ? '' : 'hidden'; ?> animate-fade-in no-print">
                
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
                    <div>
                        <h3 class="text-lg md:text-xl font-extrabold text-slate-800 flex items-center">Technicians</h3>
                        <p class="text-sm font-medium text-slate-500 mt-1">ทำเนียบรายชื่อทีมช่างผู้ดูแลระบบ (แยกตามฝ่ายงาน)</p>
                    </div>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2.5 items-center w-full lg:w-auto">
                        <!-- ช่องค้นหา -->
                        <div class="relative w-full sm:w-48 lg:w-56 mb-2 sm:mb-0">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="search-tech-card" oninput="searchTechCards()" placeholder="ค้นหาช่างที่ผูกบัญชี..." class="w-full bg-white border border-slate-200 text-sm rounded-full pl-9 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium shadow-sm">
                        </div>

                        <div class="flex flex-wrap gap-2.5">
                            <button onclick="filterDeptCard('all')" id="btn-filter-all-2" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-indigo-600 text-white border border-indigo-600 shadow-md shadow-indigo-200 cursor-pointer">ทั้งหมด</button>
                            <button onclick="filterDeptCard('ฝ่ายงานบริการเทคโนโลยีดิจิทัล')" id="btn-filter-digital-2" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm cursor-pointer">บริการเทคโนโลยีดิจิทัล</button>
                            <button onclick="filterDeptCard('ฝ่ายงานโสตทัศนูปกรณ์')" id="btn-filter-av-2" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm cursor-pointer">โสตทัศนูปกรณ์</button>
                            <button onclick="filterDeptCard('ฝ่ายงานยานยนต์')" id="btn-filter-auto-2" class="dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm cursor-pointer">ยานยนต์</button>
                        </div>
                    </div>
                </div>

                <?php 
                $departments_data = [];
                $res_techs = $conn->query("SELECT * FROM technicians WHERE approval_status = 'อนุมัติแล้ว'");
                if($res_techs && $res_techs->num_rows > 0) {
                    while($row = $res_techs->fetch_assoc()) {
                        $dept = !empty($row['department']) ? $row['department'] : 'ฝ่ายงานทั่วไป';
                        if(!isset($departments_data[$dept])) $departments_data[$dept] = [];
                        
                        $img = !empty($row['avatar_url']) ? $row['avatar_url'] : '';
                        
                        list($th_name, $en_name) = splitThaiEngName($row['full_name'], $row['english_name']);

                        $departments_data[$dept][] = [
                            'th' => $th_name,
                            'eng' => $en_name, 
                            'pos' => !empty($row['position']) ? $row['position'] : getAutoPosition($th_name),
                            'phone' => $row['phone'],
                            'img' => $img,
                            'raw_name' => $row['full_name']
                        ];
                    }
                }

                $dept_icons = [
                    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล' => 'fas fa-laptop-code',
                    'ฝ่ายงานยานยนต์' => 'fas fa-car',
                    'ฝ่ายงานโสตทัศนูปกรณ์' => 'fas fa-video',
                    'แม่บ้าน' => 'fas fa-broom'
                ];
                
                uksort($departments_data, function($a, $b) use ($custom_dept_order) {
                    $pos_a = array_search($a, $custom_dept_order);
                    $pos_b = array_search($b, $custom_dept_order);
                    $pos_a = ($pos_a === false) ? 999 : $pos_a;
                    $pos_b = ($pos_b === false) ? 999 : $pos_b;
                    if ($pos_a == $pos_b) return strcmp($a, $b);
                    return $pos_a - $pos_b;
                });

                if(empty($departments_data)) {
                    echo "<div class='modern-card p-12 text-center flex flex-col items-center justify-center'><i class='fas fa-user-slash text-4xl text-slate-300 mb-4'></i><p class='text-slate-500 font-bold'>ยังไม่มีช่างในระบบ หรือยังไม่มีช่างที่ผูกบัญชีสำเร็จ</p></div>";
                }

                foreach ($departments_data as $dept_name => $techs):
                    $icon_class = $dept_icons[$dept_name] ?? 'fas fa-users';
                ?>
                <div class="mb-10 tech-dept-section" data-dept="<?php echo htmlspecialchars($dept_name); ?>">
                    
                    <div class="relative overflow-hidden flex items-center justify-between mb-5 bg-blue-500 p-3 rounded-2xl shadow-md shadow-blue-200/50 tech-dept-header">
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="absolute bottom-0 right-1/4 w-20 h-20 bg-white opacity-10 rounded-full blur-xl pointer-events-none"></div>
                        
                        <div class="flex items-center relative z-10 pl-1">
                            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white mr-4 border border-white/30 shadow-inner shrink-0">
                                <i class="<?php echo $icon_class; ?> text-lg drop-shadow-md"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-base text-white tracking-wide drop-shadow-md leading-tight">
                                    <?php echo htmlspecialchars($dept_name); ?>
                                </h3>
                                <!-- ✨ ปรับฟอนต์ให้ใหญ่ขึ้นเพื่อความชัดเจน ✨ -->
                                <p class="text-blue-100 text-[11px] sm:text-xs font-medium mt-0.5 opacity-90 tracking-wider">ทีมช่างผู้รับผิดชอบประจำฝ่าย</p>
                            </div>
                        </div>
                        
                        <div class="relative z-10 hidden sm:flex items-center pr-1">
                            <span class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-[11px] font-bold px-3 py-1.5 rounded-full shadow-sm flex items-center">
                                <i class="fas fa-user-check mr-1.5 opacity-80"></i> <?php echo count($techs); ?> คน
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-6 items-start justify-center sm:justify-start">
                        <?php foreach ($techs as $tech): 
                            $search_name = preg_replace('/\s+/', '', strtolower($tech['raw_name'] . $tech['eng'] . $tech['th']));
                        ?>
                        <div class="bg-white rounded-[24px] overflow-hidden border border-slate-200/70 shadow-sm hover:shadow-[0_8px_30px_rgba(56,189,248,0.25)] hover:border-sky-300 hover:-translate-y-1 transition-all duration-300 flex flex-col group relative min-w-[260px] w-full sm:w-[260px] tech-card-item" data-tech-name="<?php echo htmlspecialchars($search_name, ENT_QUOTES); ?>">
                            
                            <div class="relative w-full aspect-square bg-slate-50 overflow-hidden">
                                <img src="<?php echo htmlspecialchars($tech['img']); ?>" 
                                     onerror="this.onerror=null; this.src='https://api.dicebear.com/7.x/notionists/svg?seed=<?php echo urlencode($tech['th']); ?>&backgroundColor=e2e8f0'" 
                                     onclick="openImageModal(this.src)"
                                     alt="<?php echo htmlspecialchars($tech['th']); ?>" 
                                     class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-700 ease-out cursor-pointer" title="คลิกเพื่อดูรูปขยาย">
                                
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                            </div>

                            <div class="p-5 flex-1 flex flex-col relative z-10 bg-white text-left">
                                
                                <h5 class="font-extrabold text-slate-800 text-[17px] leading-tight group-hover:text-sky-500 transition-colors duration-300">
                                    <?php echo htmlspecialchars($tech['th']); ?>
                                </h5>
                                
                                <?php if (!empty($tech['eng'])): ?>
                                <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest truncate"><?php echo htmlspecialchars($tech['eng']); ?></p>
                                <?php else: ?>
                                <div class="mt-1 h-[15px]"></div>
                                <?php endif; ?>

                                <?php if (!empty($tech['pos'])): ?>
                                <div class="mt-3 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-indigo-50 border border-indigo-100 text-[10px] font-bold text-indigo-600">
                                        <i class="fas fa-id-badge mr-1.5 opacity-70"></i> <?php echo htmlspecialchars($tech['pos']); ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                <div class="mt-3 mb-2 h-[24px]"></div>
                                <?php endif; ?>
                                
                                <div class="mt-3 w-full pt-4 border-t border-slate-100 flex flex-col gap-2.5">
                                <?php 
                                if (!empty($tech['phone']) && $tech['phone'] !== '-'): 
                                    $phone_parts = array_values(array_filter(array_map('trim', explode(',', $tech['phone']))));
                                    foreach($phone_parts as $p): ?>
                                        <div class="flex items-center text-slate-600">
                                            <div class="w-7 h-7 rounded-full bg-emerald-50 flex items-center justify-center mr-3 shrink-0">
                                                <i class="fas fa-phone-alt text-[10px] text-emerald-500"></i>
                                            </div>
                                            <span class="text-[13px] font-bold tracking-wide text-slate-700"><?php echo htmlspecialchars(trim($p)); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="flex items-center text-rose-400">
                                        <div class="w-7 h-7 rounded-full bg-rose-50 flex items-center justify-center mr-3 shrink-0">
                                            <i class="fas fa-phone-slash text-[10px] text-rose-500"></i>
                                        </div>
                                        <span class="text-[12px] font-bold text-rose-500">ไม่มีเบอร์ติดต่อ</span>
                                    </div>
                                <?php endif; ?>
                                </div>

                                <div class="mt-auto pt-4">
                                    <button onclick="viewHistory('<?php echo htmlspecialchars($tech['raw_name'], ENT_QUOTES); ?>', 'technician')" 
                                            class="w-full text-[11px] font-bold text-sky-600 bg-white border border-sky-100 hover:bg-sky-500 hover:text-white hover:border-sky-500 py-2.5 rounded-xl transition-all duration-300 shadow-sm flex items-center justify-center group/btn">
                                        <i class="fas fa-history mr-1.5 text-sky-400 group-hover/btn:text-white transition-colors"></i> 
                                        ดูประวัติงาน
                                    </button>
                                </div>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="tech-empty-state hidden w-full modern-card p-10 flex-col items-center justify-center mt-6 border-2 border-dashed border-indigo-100 bg-indigo-50/30 text-center rounded-3xl">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-5 mx-auto shadow-sm border border-indigo-50">
                        <i class="fas fa-user-lock text-3xl text-indigo-300"></i>
                    </div>
                    <h3 class="font-extrabold text-xl mb-2"><span class="text-rose-500">ไม่พบรายชื่อช่าง</span> <span class="text-emerald-500">(ที่ผูกบัญชีแล้ว)</span></h3>
                    <p class="text-slate-500 text-sm font-medium max-w-md mx-auto leading-relaxed">
                        ไม่มีช่างชื่อนี้อยู่ในระบบ หรือ <strong class="text-indigo-600">ช่างท่านนี้ยังไม่ได้ทำการ <span class="text-emerald-500">"ผูกบัญชี LINE"</span></strong><br>
                        ลองตรวจสอบตัวสะกด ทั้งภาษาไทยและภาษาอังกฤษ<br>ดูอีกครั้งนะครับ<br>
                        <span class="text-xs text-slate-400 mt-2 block">ถ้าเป็นช่างใหม่ ต้องไปที่หน้าเมนู <strong>"Team"</strong> เพื่อทำการ <span onclick="show('technicians'); setTimeout(() => openTechAdminModal('Technician'), 200);" class="text-indigo-600 font-bold cursor-pointer hover:text-indigo-800 hover:underline transition-colors">"Add Technician"</span> เพิ่มเข้าสู่ระบบก่อน</span>
                    </p>
                </div>
            </div>

            <div id="assets" class="section <?php echo $active_tab === 'assets' ? '' : 'hidden'; ?> space-y-6 no-print">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Assets Database</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">Manage all registered equipments</p>
                        </div>
                        <button onclick="openAddAssetModal()" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-indigo-200 flex items-center justify-center transition-all"><i class="fas fa-plus mr-2"></i> Add Asset</button>
                    </div>
                    <div class="overflow-x-auto w-full pb-4 custom-scrollbar">
                        <table class="w-full text-left whitespace-nowrap min-w-[600px]">
                            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4">Code</th>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Category</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $asset_res = $conn->query("SELECT * FROM assets ORDER BY created_at DESC");
                                if($asset_res && $asset_res->num_rows > 0){
                                    while($a = $asset_res->fetch_assoc()) {
                                        $a_statusClass = ($a['status'] == 'ใช้งานปกติ') ? 'badge-success' : 'bg-rose-100 text-rose-600 px-3 py-1 rounded-full text-[11px] font-bold';
                                        
                                        $a_code = formatEmptyOrDash($a['asset_code']);
                                        $a_name = formatEmptyOrDash($a['asset_name']);
                                        $a_cat = formatEmptyOrDash($a['category']);
                                        
                                        $js_id = $a['id']; $js_code = htmlspecialchars($a['asset_code'], ENT_QUOTES); $js_name = htmlspecialchars($a['asset_name'], ENT_QUOTES); $js_cat = htmlspecialchars($a['category'], ENT_QUOTES); $js_status = htmlspecialchars($a['status'], ENT_QUOTES);

                                        echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                            <td class='px-6 py-4 align-top font-mono font-semibold text-slate-500'>{$a_code}</td>
                                            <td class='px-6 py-4 align-top text-slate-800 font-bold'>{$a_name}</td>
                                            <td class='px-6 py-4 align-top text-slate-500 font-medium'>{$a_cat}</td>
                                            <td class='px-6 py-4 align-middle text-center'><span class='{$a_statusClass}'>{$a['status']}</span></td>
                                            <td class='px-6 py-4 align-middle text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"openEditAssetModal('$js_id', '$js_code', '$js_name', '$js_cat', '$js_status')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDelete('asset', {$a['id']})\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='5' class='px-6 py-12 text-center text-slate-400'>No assets found</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="users" class="section <?php echo $active_tab === 'users' ? '' : 'hidden'; ?> space-y-6 no-print">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Reporter History</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">Database of personnel who reported issues</p>
                        </div>
                        <div class="w-full md:w-auto relative">
                            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchHistoryInput" oninput="searchHistoryTable()" placeholder="ค้นหาชื่อผู้แจ้ง..." class="w-full md:w-64 bg-slate-50 border border-slate-200 text-sm rounded-xl pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium">
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full pb-4 custom-scrollbar">
                        <table class="w-full text-left whitespace-nowrap min-w-[700px]" id="usersTable">
                            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Contact</th>
                                    <th class="px-6 py-4 text-center">Reports</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $reporter_res = $conn->query("SELECT reporter_name, MAX(phone_number) as fallback_phone, COUNT(id) as total_repairs FROM repairs WHERE reporter_name IS NOT NULL AND reporter_name != '' GROUP BY reporter_name ORDER BY MAX(created_at) DESC");
                                
                                if($reporter_res && $reporter_res->num_rows > 0){
                                    while($r = $reporter_res->fetch_assoc()) {
                                        $raw_name = trim((string)$r['reporter_name']);
                                        
                                        $line_id = $raw_name;
                                        $real_name = "";
                                        $display_phone = trim((string)$r['fallback_phone']);
                                        
                                        if (isset($line_users_map[$raw_name])) {
                                            if (!empty($line_users_map[$raw_name]['line_display_name'])) {
                                                $line_id = $line_users_map[$raw_name]['line_display_name']; 
                                            }
                                            if (!empty($line_users_map[$raw_name]['real_name'])) {
                                                $real_name = $line_users_map[$raw_name]['real_name']; 
                                            }
                                            if (!empty($line_users_map[$raw_name]['phone_number'])) {
                                                $display_phone = $line_users_map[$raw_name]['phone_number']; 
                                            }
                                        }
                                        
                                        $main_name_html = formatEmptyOrDash($line_id);
                                        // ✨ เปลี่ยนสี text-slate-400 เป็น text-slate-500 ให้เข้มขึ้น ✨
                                        $sub_name_html = ($real_name !== '' && $real_name !== $line_id) ? "<div class='text-slate-500 font-medium mt-0.5'>" . htmlspecialchars($real_name) . "</div>" : "";
                                        
                                        $js_view_name = htmlspecialchars($raw_name, ENT_QUOTES); 
                                        $js_edit_line_id = htmlspecialchars($line_id, ENT_QUOTES);
                                        $js_edit_real_name = htmlspecialchars($real_name !== '' ? $real_name : $line_id, ENT_QUOTES);
                                        $js_old_phone = htmlspecialchars($display_phone, ENT_QUOTES);
                                        
                                        $rep_phone_html = formatEmptyOrDash($display_phone);
                                        
                                        echo "<tr class='hover:bg-slate-50/50 transition-colors user-row'>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='flex items-center'>
                                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3 shrink-0'><i class='fas fa-user text-xs'></i></div>
                                                    <div>
                                                        <div class='text-slate-800 font-bold'>{$main_name_html}</div>
                                                        {$sub_name_html}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class='px-6 py-4 align-top text-slate-500 font-medium'>{$rep_phone_html}</td>
                                            <td class='px-6 py-4 align-middle text-center'>
                                                <span class='px-3 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600'>{$r['total_repairs']}</span>
                                            </td>
                                            <td class='px-6 py-4 align-middle text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"viewHistory('{$js_view_name}', 'reporter')\" class='bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>View</span></button>
                                                    <button onclick=\"openEditReporterModal('{$js_edit_line_id}', '{$js_edit_real_name}', '{$js_old_phone}')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDeleteReporter('{$js_view_name}')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='4' class='px-6 py-12 text-center text-slate-400'>No history found</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="reports" class="section <?php echo $active_tab === 'reports' ? '' : 'hidden'; ?> space-y-6 no-print">
                <div class="modern-card p-6 md:p-8">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                        <div>
                            <h2 class="text-2xl font-extrabold text-slate-800">Official Report</h2>
                            <p class="text-sm font-medium text-slate-500 mt-1">Generate official print document or export to Excel.</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                            <a href="export_excel.php" id="exportExcelBtn" target="_blank" class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-emerald-200 flex items-center justify-center transition-all">
                                <i class="fas fa-file-excel mr-2 text-lg"></i> Export Excel
                            </a>
                            <button onclick="printOfficialReport()" class="w-full sm:w-auto bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-indigo-600 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm flex items-center justify-center transition-all">
                                <i class="fas fa-print mr-2 text-lg"></i> Print Document
                            </button>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col md:flex-row items-start md:items-center gap-4 relative">
                        <label class="font-bold text-slate-700 text-sm flex items-center shrink-0"><i class="fas fa-filter text-indigo-500 mr-2"></i> Filter Data by Technician:</label>
                        
                        <div class="relative w-full md:w-[450px]" id="reportDropdownContainer">
                            <div class="flex items-center w-full bg-white border-2 border-slate-200 rounded-xl overflow-hidden focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-100 transition-all cursor-text shadow-sm" onclick="toggleReportDropdown(event, true)">
                                <i class="fas fa-search text-slate-400 pl-4"></i>
                                <input type="text" id="reportSearchInput" oninput="filterReportDropdown()" onfocus="focusReportSearch(event)" onblur="blurReportSearch(event)" autocomplete="off" class="w-full bg-transparent px-3 py-3 text-sm text-slate-700 focus:outline-none font-bold placeholder-slate-400" placeholder="พิมพ์ค้นหาชื่อช่าง, แผนก...">
                                <button type="button" class="px-4 py-3 text-slate-400 hover:text-indigo-600 focus:outline-none" onclick="toggleReportDropdown(event)">
                                    <i class="fas fa-caret-down text-lg"></i>
                                </button>
                            </div>
                            
                            <div id="reportDropdownList" class="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-xl max-h-80 overflow-y-auto hidden flex-col py-3 custom-scrollbar">
                                <div class="report-dropdown-item px-4 py-2 mx-2 rounded-xl text-sm font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 cursor-pointer transition-colors flex items-center" data-value="all" data-search="overallsystemalltechniciansทั้งหมดทุกแผนก" onmousedown="selectReportTech('all', 'รวมทุกฝ่ายงาน (ทั้งหมด)')">
                                    <div class="w-8 h-8 rounded-full bg-indigo-200/50 flex items-center justify-center mr-3 text-indigo-600">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    รวมทุกฝ่ายงาน (ทั้งหมด)
                                </div>
                                <?php 
                                    foreach ($techs_by_dept as $dept => $techs) {
                                        $tech_count = count($techs);
                                        echo "<div class='dropdown-dept-header flex justify-between items-center px-4 py-2.5 mt-2 mb-1 bg-indigo-50/60 border-y border-indigo-100' data-dept=\"".htmlspecialchars($dept)."\">
                                                <span class='text-sm font-extrabold text-indigo-700 tracking-wide'>{$dept}</span>
                                                <span class='text-[10px] font-bold text-indigo-600 bg-white border border-indigo-200 shadow-sm px-2 py-0.5 rounded-md flex items-center'>
                                                    <i class='fas fa-users mr-1 opacity-70 text-[9px]'></i> {$tech_count} คน
                                                </span>
                                              </div>";
                                        foreach($techs as $t) {
                                            list($th_name, $en_name) = splitThaiEngName($t['full_name'], $t['english_name']);
                                            $tNameOnly = htmlspecialchars($th_name);
                                            $tValue = htmlspecialchars($t['full_name'], ENT_QUOTES);
                                            $searchStr = preg_replace('/\s+/', '', strtolower($t['full_name'] . $en_name . $dept));
                                            
                                            echo "<div class='report-dropdown-item px-4 py-2 mx-2 mb-1 rounded-xl text-sm text-slate-700 font-bold hover:bg-indigo-50 hover:text-indigo-600 cursor-pointer flex justify-between items-center transition-all group' data-value=\"{$tValue}\" data-search=\"{$searchStr}\" data-dept=\"".htmlspecialchars($dept)."\" onmousedown=\"selectReportTech('{$tValue}', '{$tNameOnly}')\">
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 text-slate-400 group-hover:bg-indigo-100 group-hover:text-indigo-500 transition-colors'>
                                                            <i class='fas fa-user text-xs'></i>
                                                        </div>
                                                        <span>{$tNameOnly}</span>
                                                    </div>
                                                    <i class='fas fa-check text-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity'></i>
                                                  </div>";
                                        }
                                    }
                                ?>
                            </div>
                            <input type="hidden" id="techFilter" value="all">
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ================== MODALS ================== -->
    
    <div id="imagePreviewModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm cursor-pointer" onclick="toggleModal('imagePreviewModal')"></div>
        <button onclick="toggleModal('imagePreviewModal')" class="absolute top-4 right-4 md:top-6 md:right-6 w-10 h-10 bg-white/10 hover:bg-rose-500 text-white rounded-full flex items-center justify-center shadow-lg transition-all z-20 cursor-pointer backdrop-blur-md border border-white/20">
            <i class="fas fa-times text-xl"></i>
        </button>
        <img id="fullSizeImage" src="" class="relative z-10 max-h-[85vh] max-w-full rounded-xl shadow-2xl object-contain bg-slate-50 border-4 border-white" alt="Full Preview">
    </div>

    <!-- ✨ Modal สำหรับเพิ่มครุภัณฑ์ใหม่ ✨ -->
    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                <p class="text-lg font-extrabold text-slate-800" id="assetModalTitle">Add Asset</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <form action="dashboard.php?tab=assets" method="POST" class="p-6">
                <input type="hidden" name="save_asset" value="1"><input type="hidden" name="asset_id" id="asset_id" value="">
                <div class="space-y-5">
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Code</label><input type="text" name="asset_code" id="asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Name</label><input type="text" name="asset_name" id="asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"></div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Category</label>
                        <select name="category" id="asset_category" onchange="toggleCustomInput(this, 'asset_category_custom')" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium cursor-pointer">
                            <?php 
                            // สร้างตัวแปรดึงหมวดหมู่ (เหมือนใน update_repair)
                            $dash_categories = ['IT Support', 'ไฟฟ้า/แอร์', 'อาคารสถานที่'];
                            $d_cat_res = $conn->query("SELECT DISTINCT category FROM assets WHERE category IS NOT NULL AND category != ''");
                            if($d_cat_res && $d_cat_res->num_rows > 0){
                                while($dc = $d_cat_res->fetch_assoc()){
                                    if(!in_array($dc['category'], $dash_categories) && $dc['category'] !== 'อื่นๆ') {
                                        $dash_categories[] = $dc['category'];
                                    }
                                }
                            }
                            ?>
                            <?php foreach($dash_categories as $dcat): ?>
                                <option value="<?php echo htmlspecialchars($dcat); ?>"><?php echo htmlspecialchars($dcat); ?></option>
                            <?php endforeach; ?>
                            <option value="อื่นๆ">อื่นๆ (พิมพ์ระบุเอง)</option>
                        </select>
                        <input type="text" name="category_custom" id="asset_category_custom" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 hidden mt-2 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="ระบุหมวดหมู่ใหม่">
                    </div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Status</label><select name="status" id="asset_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"><option value="ใช้งานปกติ">ใช้งานปกติ</option><option value="ชำรุด/ส่งซ่อม">ชำรุด/ส่งซ่อม</option><option value="แทงจำหน่าย">แทงจำหน่าย</option></select></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('assetModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Save Asset</button></div>
            </form>
        </div>
    </div>

    <div id="techAdminModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('techAdminModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 flex flex-col max-h-[90vh] transform transition-all overflow-hidden">
            
            <div class="px-6 py-5 flex justify-between items-center bg-white rounded-t-3xl border-b border-slate-100 shrink-0">
                <h2 class="text-xl font-bold text-slate-800" id="techAdminModalTitle">Manage Technician</h2>
                <button type="button" onclick="toggleModal('techAdminModal')" class="text-slate-400 hover:text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors rounded-full w-8 h-8 flex items-center justify-center"><i class="fas fa-times text-sm"></i></button>
            </div>

            <form action="dashboard.php?tab=technicians" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden min-h-0">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="techAdmin_id" value="">
                <input type="hidden" name="role" id="techAdmin_role" value="">
                
                <!-- ✨ เปลี่ยนมาใช้ pt-4 และ flex-col gap-5 เพื่อแก้ปัญหาช่องว่างโล่งๆ ด้านบน ✨ -->
                <div class="px-6 pt-2 pb-6 overflow-y-auto custom-scrollbar flex-1 flex flex-col gap-5">
                    
                    <div id="loginCredsDiv" class="flex flex-col gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Username</label>
                            <input type="text" name="username" id="techAdmin_username" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password <span class="text-slate-400 font-normal normal-case" id="pwdHint"></span></label>
                            <div class="relative">
                                <input type="password" name="password" id="techAdmin_password" class="w-full bg-white border border-slate-200 rounded-xl pl-4 pr-10 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="••••••••">
                                <button type="button" class="absolute inset-y-0 right-0 px-4 flex items-center text-slate-400 hover:text-indigo-600 focus:outline-none" onclick="togglePasswordVisibility('techAdmin_password', 'eyeIcon')">
                                    <i id="eyeIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="adminLevelDiv" class="hidden">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Role Level</label>
                        <select name="admin_level" id="techAdmin_level" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm">
                            <option value="Admin">Admin</option>
                            <option value="Executive">Executive</option>
                        </select>
                    </div>

                    <div id="avatarDiv" class="hidden space-y-3">
                        <div id="avatarLabelWrapper">
                             <label id="avatarLabel" class="block text-sm font-extrabold text-indigo-600 uppercase tracking-wider">PROFILE PICTURE (รูปประจำตัว)</label>
                        </div>
                        
                        <div id="avatarPositionWrapper" class="hidden w-max relative z-30">
                             <div id="positionDisplayGroup" class="flex items-center gap-2">
                                 <div class="flex items-center text-sm font-extrabold text-indigo-600 uppercase tracking-wider cursor-pointer hover:text-indigo-500 transition-colors" onclick="toggleCustomPositionDropdown(event)" title="คลิกเพื่อเลือกตำแหน่งจากรายการ">
                                     <span id="displayPositionLabel">ตำแหน่งงาน</span>
                                     <i class="fas fa-caret-down ml-1.5 text-indigo-600 text-xs hover:text-indigo-400 transition-colors"></i>
                                 </div>
                                 <button type="button" onclick="openCustomPositionPrompt()" class="w-6 h-6 flex items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-indigo-100 hover:text-indigo-600 transition-colors shadow-sm" title="พิมพ์ระบุตำแหน่งเอง">
                                     <i class="fas fa-pencil-alt text-[10px]"></i>
                                 </button>
                             </div>
                             
                             <div id="customPositionDropdown" class="absolute left-0 top-full mt-2 w-64 bg-white border border-slate-100 rounded-2xl shadow-xl z-50 hidden flex-col py-2 custom-scrollbar max-h-60 overflow-y-auto">
                             </div>
                        </div>
                             
                        <select id="avatarPositionSelect" class="hidden w-full mt-1 text-sm font-extrabold text-indigo-600 uppercase tracking-wider bg-transparent border-b-2 border-indigo-400 focus:border-indigo-600 outline-none pb-1 transition-colors cursor-pointer appearance-none pr-6" onchange="handleDropdownChange(this)" onblur="cancelDropdownEdit()" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234f46e5%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right top 50%; background-size: 0.65rem auto;">
                            <option value="" disabled selected>-- เลือกตำแหน่ง --</option>
                            <option value="นักวิชาการคอมพิวเตอร์">นักวิชาการคอมพิวเตอร์</option>
                            <option value="นักวิชาการโสตทัศนศึกษา">นักวิชาการโสตทัศนศึกษา</option>
                            <option value="เจ้าหน้าที่บริหารงานทั่วไป">เจ้าหน้าที่บริหารงานทั่วไป</option>
                            <option value="พนักงานขับรถยนต์">พนักงานขับรถยนต์</option>
                            <option value="custom">อื่นๆ (พิมพ์ระบุเอง)</option>
                        </select>
                        
                        <div class="flex items-center gap-5 p-4 rounded-2xl border border-slate-100 bg-slate-50/50 shadow-sm">
                            <div class="w-[100px] h-[100px] rounded-2xl bg-white border-2 border-slate-100 shadow-sm overflow-hidden shrink-0 flex items-center justify-center">
                                <img id="avatarPreviewImg" src="https://api.dicebear.com/7.x/notionists/svg?seed=admin&backgroundColor=e2e8f0" alt="Preview" class="w-full h-full object-cover cursor-pointer hover:opacity-80 transition-opacity hover:scale-105" onclick="openImageModal(this.src)" title="คลิกเพื่อดูรูปขยาย">
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-2">
                                    <label for="techAdmin_avatar" class="cursor-pointer inline-flex items-center justify-center px-4 py-2 bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-xl text-sm font-bold transition-colors whitespace-nowrap">
                                        เลือกไฟล์รูปภาพ
                                    </label>
                                    <span id="fileNameDisplay" class="text-sm text-slate-500 truncate">ไม่ได้เลือกไฟล์ใด</span>
                                </div>
                                <input type="file" name="avatar" id="techAdmin_avatar" accept="image/*" class="hidden" onchange="previewAvatar(event)">
                                <p class="text-[11px] text-slate-400 mt-2">แนะนำรูปภาพขนาด 1:1 หรือ 4:5 (JPG, PNG)</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">FULL NAME</label>
                        <input type="text" name="full_name" id="techAdmin_fullname" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="เช่น นาย สมพร วงษ์จำปา">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">ENGLISH NAME</label>
                        <input type="text" name="english_name" id="techAdmin_englishname" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="เช่น Mr. Somporn Wongchampa">
                    </div>

                    <div id="positionDiv">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">POSITION</label>
                        <select name="position_select" id="techAdmin_position_select" onchange="toggleCustomInput(this, 'techAdmin_position_custom')" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm mb-2 appearance-none" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto;">
                            <option value="" disabled selected>-- Select Position --</option>
                            <option value="นักวิชาการคอมพิวเตอร์">นักวิชาการคอมพิวเตอร์</option>
                            <option value="นักวิชาการโสตทัศนศึกษา">นักวิชาการโสตทัศนศึกษา</option>
                            <option value="เจ้าหน้าที่บริหารงานทั่วไป">เจ้าหน้าที่บริหารงานทั่วไป</option>
                            <option value="พนักงานขับรถยนต์">พนักงานขับรถยนต์</option>
                            <option value="อื่นๆ">อื่นๆ (Custom)</option>
                        </select>
                        <input type="text" name="position_custom" id="techAdmin_position_custom" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 hidden focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="Specify position">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">PHONE</label>
                        <input type="text" name="phone" id="techAdmin_phone" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm">
                    </div>
                    
                    <div id="deptDiv">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">DEPARTMENT</label>
                        <select name="department_select" id="techAdmin_department_select" onchange="toggleCustomInput(this, 'techAdmin_department_custom')" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm mb-2 appearance-none" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto;">
                            <option value="" disabled selected>-- Select Department --</option>
                            <option value="ฝ่ายงานบริการเทคโนโลยีดิจิทัล">ฝ่ายงานบริการเทคโนโลยีดิจิทัล</option>
                            <option value="ฝ่ายงานยานยนต์">ฝ่ายงานยานยนต์</option>
                            <option value="ฝ่ายงานโสตทัศนูปกรณ์">ฝ่ายงานโสตทัศนูปกรณ์</option>
                            <option value="แม่บ้าน">แม่บ้าน</option>
                            <option value="อื่นๆ">อื่นๆ (Custom)</option>
                        </select>
                        <input type="text" name="department_custom" id="techAdmin_department_custom" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 hidden focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="Specify department">
                    </div>
                </div>

                <div class="p-6 pt-4 border-t border-slate-100 flex justify-end gap-3 shrink-0 bg-white rounded-b-3xl">
                    <button type="button" onclick="toggleModal('techAdminModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors shadow-sm">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Save Data</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editReporterModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('editReporterModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                <p class="text-lg font-extrabold text-slate-800">Edit Reporter</p>
                <button onclick="toggleModal('editReporterModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <form action="dashboard.php?tab=users" method="POST" class="p-6">
                <input type="hidden" name="edit_reporter" value="1">
                <input type="hidden" name="old_name" id="edit_rep_old_name" value="">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">LINE ID (Cannot be changed)</label>
                        <input type="text" id="display_old_name_ui" disabled class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-500 cursor-not-allowed font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name</label>
                        <input type="text" name="new_name" id="edit_rep_new_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Phone Number</label>
                        <input type="text" name="new_phone" id="edit_rep_new_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('editReporterModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Update</button></div>
            </form>
        </div>
    </div>

    <div id="historyModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('historyModal')"></div>
        <div class="modal-container bg-white w-full max-w-[95%] xl:max-w-6xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[85vh] max-h-[850px]">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl shrink-0">
                <p class="text-lg md:text-xl font-extrabold text-slate-800 truncate pr-4" id="historyModalTitle">History</p>
                <div class="flex items-center gap-4 md:gap-6 shrink-0">
                    <button id="historyModalLinkBtn" class="text-[11px] md:text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 px-4 md:px-5 py-2 md:py-2.5 rounded-xl shadow-md shadow-indigo-200 transition-all flex items-center justify-center cursor-pointer">
                        <i class="fas fa-address-book md:mr-2"></i> <span class="hidden md:inline">Contacts</span>
                    </button>
                    <button onclick="toggleModal('historyModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 rounded-full w-9 h-9 md:w-10 md:h-10 flex items-center justify-center shadow-sm shrink-0 hover:bg-rose-50"><i class="fas fa-times text-base md:text-lg"></i></button>
                </div>
            </div>
            <div class="p-0 md:p-6 overflow-hidden flex-1 bg-[#f8fafc]">
                <div class="w-full h-full overflow-x-auto md:rounded-2xl md:border border-slate-200 shadow-sm relative custom-scrollbar bg-white">
                    <table class="w-full text-left whitespace-nowrap min-w-[1200px]">
                        <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                            <tr>
                                <th class="px-6 py-4">Date / Time</th>
                                <th class="px-6 py-4">Ticket No.</th>
                                <th class="px-6 py-4">Reporter</th>
                                <th class="px-6 py-4">Equipment</th>
                                <th class="px-6 py-4">Department</th>
                                <th class="px-6 py-4">Technician</th>
                                <th class="px-6 py-4">Received At</th>
                                <th class="px-6 py-4">Root Cause</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4">Completed At</th>
                                <th class="px-6 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-100 bg-white" id="historyTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ✨ Modal สำหรับแสดงรีวิวของช่างรายบุคคล ✨ -->
    <div id="techReviewsModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('techReviewsModal')"></div>
        <div class="modal-container bg-white w-full max-w-lg mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[80vh] max-h-[800px]">
            
            <!-- Header -->
            <div class="px-5 py-5 border-b border-slate-100 flex justify-between items-start bg-gradient-to-b from-slate-50 to-white shrink-0 relative">
                <div class="flex gap-4 relative z-10 flex-1 min-w-0">
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-500 flex items-center justify-center text-2xl shrink-0 shadow-sm border border-amber-200 mt-1"><i class="fas fa-star"></i></div>
                    <div class="flex flex-col min-w-0 w-full">
                        <p class="text-lg md:text-xl font-extrabold text-slate-800 truncate" id="techReviewsModalTitle">รีวิวของช่าง: ...</p>
                        <div class="flex items-center mt-1">
                            <p class="text-[13px] font-bold text-indigo-600 truncate" id="techReviewsModalDept">ฝ่ายงาน...</p>
                            <p class="text-[11px] font-medium text-slate-500 truncate ml-1.5" id="techReviewsModalPos">(...)</p>
                        </div>
                        
                        <!-- ✨ Dropdown สำหรับเลือกดูช่างในฝ่ายงาน ✨ -->
                        <div class="mt-2.5">
                            <select id="modalTechSelector" onchange="changeModalTech(this.value)" style="font-family: 'Sarabun', sans-serif;" class="custom-select w-full max-w-[280px] bg-white border border-indigo-200 text-[13px] text-indigo-700 rounded-lg pl-3 pr-7 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-indigo-50 shadow-sm">
                                <!-- Options จะถูกสร้างผ่าน JS -->
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end gap-3 shrink-0 ml-3 relative z-10">
                    <button onclick="toggleModal('techReviewsModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 rounded-full w-8 h-8 flex items-center justify-center shadow-sm shrink-0"><i class="fas fa-times"></i></button>
                    <span id="techReviewsModalCount" class="text-xs font-extrabold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full shadow-sm border border-amber-100 whitespace-nowrap mt-1">0 รีวิว</span>
                </div>
            </div>

            <!-- ✨ Filter Sub-header (แบบรูปดาว) ✨ -->
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center shrink-0 z-10 shadow-sm gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mr-1">ระดับคะแนน:</span>
                    <div id="starFilterContainer" class="flex gap-1.5">
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(1)" title="1 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(2)" title="2 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(3)" title="3 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(4)" title="4 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(5)" title="5 ดาว"></i>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button id="btnFilterZeroReviews" onclick="setReviewFilter(0)" class="px-3 py-1.5 text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">
                        เฉพาะคอมเมนต์
                    </button>
                    <button id="btnFilterAllReviews" onclick="setReviewFilter('all')" class="px-4 py-1.5 text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700">
                        ทั้งหมด
                    </button>
                </div>
            </div>

            <!-- List -->
            <div class="p-0 overflow-y-auto flex-1 bg-white custom-scrollbar">
                <div class="divide-y divide-slate-100" id="techReviewsList">
                    <!-- Injected via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- ================== JAVASCRIPT ================== -->
    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;
        const techDeptMap = <?php echo $tech_dept_map_json; ?>;
        const techInfoMap = <?php echo $tech_info_map_json; ?>; 
        const lineUsersMap = <?php echo $line_users_map_json; ?>; // ✨ แมปข้อมูลผู้ใช้งาน LINE
        
        let chartEquipInstance = null;
        let chartStatusInstance = null;
        let chartLocInstance = null;
        let chartTechInstance = null;
        let chartRatingInstance = null;
        
        let currentTechReviewsData = [];
        let currentDeptReviewsData = []; // ✨ เก็บข้อมูลรีวิวของทั้งแผนก
        let currentReviewFilter = 'all'; // ✨ เติมตัวแปรนี้ เพื่อให้ Modal ทำงานได้ ✨
        // ✨ ตัวแปรเก็บค่าเริ่มต้น ให้โชว์ Top 5 ✨
        let currentTopReportersLimit = 5;
        
        const pageTitles = {
            'dash': 'Dashboard Overview',
            'repairs': 'All Repairs List',
            'technicians': 'Team Management',
            'team_cards': 'Team Management',
            'assets': 'Assets Database',
            'users': 'Reporter History',
            'reports': 'Official Report'
        };

        // ✨ ฟังก์ชันแสดงผลรายการรีวิวใน Modal ✨
        function renderTechReviewsList() {
            const container = document.getElementById('techReviewsList');
            if(!container) return;
            container.innerHTML = '';
            
            let filteredReviews = [...currentTechReviewsData];
            
            if (currentReviewFilter !== 'all') {
                filteredReviews = filteredReviews.filter(r => parseInt(r.rating || 0) === parseInt(currentReviewFilter));
            }
            
            filteredReviews.sort((a,b) => new Date(b.completed_at) - new Date(a.completed_at));

            if(filteredReviews.length === 0) {
                container.innerHTML = `<div class='p-10 flex flex-col items-center justify-center text-center h-full'>
                                            <i class='fas fa-star text-4xl text-slate-200 mb-3'></i>
                                            <p class='text-slate-400 font-medium text-sm mt-2'>ไม่พบข้อมูลรีวิวในระดับคะแนนนี้</p>
                                          </div>`;
            } else {
                filteredReviews.forEach(rev => {
                    let r_name = formatValJS(rev.reporter_name);
                    let r_rating = parseInt(rev.rating || 0);
                    let r_comment = (rev.review_comment && rev.review_comment.trim() !== '' && rev.review_comment !== '-') 
                                    ? rev.review_comment.trim() 
                                    : "<span class='text-slate-300 italic'>- ไม่มีข้อความรีวิว -</span>";
                    
                    let stars_html = '';
                    if (r_rating === 0) {
                        stars_html = '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                    } else {
                        for(let i=1; i<=5; i++) {
                            if(i <= r_rating) stars_html += '<i class="fas fa-star text-amber-400 text-[11px] drop-shadow-sm"></i>';
                            else stars_html += '<i class="fas fa-star text-slate-200 text-[11px]"></i>';
                        }
                    }

                    let date_str = "-";
                    if(rev.completed_at && rev.completed_at !== '0000-00-00 00:00:00') {
                        date_str = timeAgoJS(rev.completed_at);
                    }

                    let tName = rev.technician_name && rev.technician_name !== '-' ? rev.technician_name : 'ไม่ระบุช่าง';
                    let techInfoHtml = `<div class="text-[10px] text-indigo-500 font-bold mt-1.5 inline-block bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100"><i class="fas fa-tools mr-1 opacity-70"></i>ช่าง: ${tName}</div>`;

                    container.innerHTML += `<div onclick="openReviewTab(${rev.id})" class='p-5 hover:bg-slate-50 transition-colors group border-b border-slate-50 last:border-0 cursor-pointer relative'>
                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity text-indigo-400">
                                <i class="fas fa-external-link-alt text-xs" title="คลิกเพื่อดูใบงานนี้"></i>
                            </div>
                            <div class='flex justify-between items-start mb-2.5 pr-6'>
                                <div class='flex items-center gap-3'>
                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-indigo-100 group-hover:text-indigo-500 transition-colors'><i class='fas fa-user text-xs'></i></div>
                                    <div>
                                        <div class='text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors'>${r_name}</div>
                                        <div class='text-[10px] text-slate-400 font-medium'>${date_str}</div>
                                    </div>
                                </div>
                                <div class='flex gap-0.5 pt-1'>${stars_html}</div>
                            </div>
                            <p class='text-xs text-slate-600 font-medium pl-11 leading-relaxed'>${r_comment}</p>
                            <div class='pl-11'>${techInfoHtml}</div>
                          </div>`;
                });
            }
        }

        // ✨ เปิดแท็บใหม่แบบไร้ร่องรอย: ไม่ส่งค่า source เพื่อให้ตอนกดกลับ แท็บมันปิดตัวเองลงทันที และหน้า Dashboard เดิมจะหยุดนิ่งไม่รีเฟรช 100% ✨
        function openReviewTab(id) {
            window.open('update_repair.php?id=' + id, '_blank');
        }

        function formatValJS(val) {
            if (!val || String(val).trim() === '-' || String(val).trim() === '') return "<span class='text-rose-500 font-bold'>-</span>";
            if (String(val).trim() === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
            return val;
        }

        function timeAgoJS(dateString) {
            if (!dateString || dateString === '0000-00-00 00:00:00') return "-";
            const date = new Date(dateString.replace(/-/g, '/'));
            const now = new Date();
            let diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 0) diffInSeconds = 0;
            
            if (diffInSeconds < 60) return "เมื่อสักครู่";
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + " นาทีที่แล้ว";
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + " ชั่วโมงที่แล้ว";
            if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + " วันที่แล้ว"; 
            
            const thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
            return `${date.getDate()} ${thaiMonths[date.getMonth()]} ${date.getFullYear() + 543}`;
        }
        
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById('fileNameDisplay').textContent = file.name;
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreviewImg').src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                document.getElementById('fileNameDisplay').textContent = 'ไม่ได้เลือกไฟล์ใด';
            }
        }

        function openImageModal(imgSrc) {
            document.getElementById('fullSizeImage').src = imgSrc;
            toggleModal('imagePreviewModal');
        }

        // ระบบ Dropdown ตำแหน่งงานอัจฉริยะ
        let availablePositions = <?php echo $available_positions_json; ?>;
        
        function renderPositionDropdown() {
            const dropdown = document.getElementById('customPositionDropdown');
            if(!dropdown) return;
            dropdown.innerHTML = '';
            
            availablePositions.forEach((pos, index) => {
                dropdown.innerHTML += `
                    <div class="px-4 py-2 mx-2 mb-1 rounded-xl text-sm font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 flex justify-between items-center transition-colors">
                        <span onclick="selectCustomPosition('${pos}')" class="flex-1 cursor-pointer truncate mr-2">${pos}</span>
                        <i class="fas fa-times-circle text-rose-500 hover:text-rose-700 transition-colors p-1 cursor-pointer" onclick="deletePositionOption(${index}, event)" title="ลบออกจากรายการ"></i>
                    </div>
                `;
            });
            
            dropdown.innerHTML += `
                <div class="px-4 py-2 mx-2 mt-1 rounded-xl text-sm font-bold text-indigo-500 bg-indigo-50/50 hover:bg-indigo-100 cursor-pointer flex items-center transition-colors" onclick="openCustomPositionPrompt()">
                    <i class="fas fa-plus-circle mr-2"></i> อื่นๆ (พิมพ์ระบุเอง)
                </div>
            `;
        }

        function toggleCustomPositionDropdown(e) {
            if (e) e.stopPropagation();
            const dropdown = document.getElementById('customPositionDropdown');
            if(!dropdown) return;
            if (dropdown.classList.contains('hidden')) {
                renderPositionDropdown();
                dropdown.classList.remove('hidden');
                dropdown.classList.add('flex');
            } else {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
            }
        }

        function selectCustomPosition(pos) {
            savePositionValue(pos);
            const dropdown = document.getElementById('customPositionDropdown');
            if(dropdown){
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
            }
        }

        function deletePositionOption(index, e) {
            e.stopPropagation();
            Swal.fire({
                title: 'ลบออกจากตัวเลือก?',
                text: `ต้องการลบตำแหน่งงานนี้ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    availablePositions.splice(index, 1);
                    renderPositionDropdown();
                }
            });
        }

        function openCustomPositionPrompt() {
            let currentPos = document.getElementById('displayPositionLabel').innerText;
            if (currentPos === 'ระบุตำแหน่งงาน' || currentPos === 'ตำแหน่งงาน') currentPos = '';
            
            const dropdown = document.getElementById('customPositionDropdown');
            if (dropdown) {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('flex');
            }
            
            Swal.fire({
                title: 'ระบุตำแหน่งงาน',
                input: 'text',
                inputValue: currentPos,
                inputPlaceholder: 'พิมพ์ตำแหน่งงานที่นี่...',
                showCancelButton: true,
                confirmButtonText: 'ตกลง',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#4f46e5',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'กรุณาระบุตำแหน่งงาน!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value.trim() !== '') {
                    const newVal = result.value.trim();
                    savePositionValue(newVal);
                    if (!availablePositions.includes(newVal)) {
                        availablePositions.push(newVal);
                        renderPositionDropdown();
                    }
                }
            });
        }

        document.addEventListener('click', function(e) {
            const wrapper = document.getElementById('avatarPositionWrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                const dropdown = document.getElementById('customPositionDropdown');
                if (dropdown && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                    dropdown.classList.remove('flex');
                }
            }
        });

        function savePositionValue(val) {
            const label = document.getElementById('displayPositionLabel');
            if(label) label.innerText = val;
            let hiddenInput = document.getElementById('final_avatar_position');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'final_avatar_position';
                hiddenInput.name = 'position';
                const form = document.querySelector('form[action="dashboard.php?tab=technicians"]');
                if(form) form.appendChild(hiddenInput);
            }
            hiddenInput.value = val;
        }

        function updateTechVisibility() {
            ['technicians', 'team_cards'].forEach(containerId => {
                let container = document.getElementById(containerId);
                if (!container) return;
                
                let hasAnyVisible = false;
                container.querySelectorAll('.tech-dept-section').forEach(sec => {
                    if (sec.style.display !== 'none') {
                        hasAnyVisible = true;
                    }
                });
                
                let emptyState = container.querySelector('.tech-empty-state');
                if (emptyState) {
                    if (!hasAnyVisible) {
                        emptyState.classList.remove('hidden');
                        emptyState.classList.add('flex');
                    } else {
                        emptyState.classList.add('hidden');
                        emptyState.classList.remove('flex');
                    }
                }
            });
        }

        let activeDeptTable = 'all';
        function filterDeptTable(dept) {
            activeDeptTable = dept;
            const defaultStyle = "dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm";
            const activeStyle = "dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-indigo-600 text-white shadow-md shadow-indigo-200 ring-2 ring-indigo-100";

            let container = document.getElementById('technicians');
            if (!container) return;

            container.querySelectorAll('.dept-filter-btn').forEach(btn => {
                btn.className = defaultStyle;
                let onClickAttr = btn.getAttribute('onclick');
                if (onClickAttr && (onClickAttr.includes(`'${dept}'`) || onClickAttr.includes(`"${dept}"`))) {
                    btn.className = activeStyle;
                }
            });

            let searchInput = document.getElementById('search-tech-table');
            let currentSearchValue = searchInput ? searchInput.value.toLowerCase().replace(/\s+/g, '').trim() : '';
            let hasAnyVisibleTable = false;

            document.querySelectorAll('#technicians .tech-dept-header').forEach(header => {
                let secDept = header.getAttribute('data-dept');
                let deptMatch = (dept === 'all' || secDept === dept);
                let hasVisibleRow = false;
                
                document.querySelectorAll(`#technicians .tech-dept-row[data-dept="${secDept}"]`).forEach(row => {
                    if (deptMatch) {
                        let name = (row.getAttribute('data-tech-name') || '').toLowerCase().replace(/\s+/g, '');
                        if (name.includes(currentSearchValue)) {
                            row.style.display = '';
                            hasVisibleRow = true;
                            hasAnyVisibleTable = true;
                        } else {
                            row.style.display = 'none';
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                header.style.display = hasVisibleRow ? '' : 'none';
                
                let colHeader = document.querySelector(`#technicians .tech-col-header[data-dept="${secDept}"]`);
                if (colHeader) {
                    colHeader.style.display = hasVisibleRow ? '' : 'none';
                }

                let spacer = document.querySelector(`#technicians .tech-dept-spacer[data-dept="${secDept}"]`);
                if (spacer) {
                    spacer.style.display = hasVisibleRow ? '' : 'none';
                }
            });

            let tableContainer = document.querySelector('#techniciansTableContainer');
            let emptyState = document.querySelector('#technicians .tech-empty-state');
            if (hasAnyVisibleTable) {
                if(tableContainer) tableContainer.style.display = '';
                if(emptyState) { emptyState.classList.add('hidden'); emptyState.classList.remove('flex'); }
            } else {
                if(tableContainer) tableContainer.style.display = 'none';
                if(emptyState) { emptyState.classList.remove('hidden'); emptyState.classList.add('flex'); }
            }
        }

        function searchTeamTable() {
            filterDeptTable(activeDeptTable);
        }

        let activeDeptCard = 'all';
        function filterDeptCard(dept) {
            activeDeptCard = dept;
            const defaultStyle = "dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 shadow-sm";
            const activeStyle = "dept-filter-btn px-4 py-2 rounded-full text-sm font-bold transition-all duration-200 bg-indigo-600 text-white shadow-md shadow-indigo-200 ring-2 ring-indigo-100";

            let container = document.getElementById('team_cards');
            if (!container) return;

            container.querySelectorAll('.dept-filter-btn').forEach(btn => {
                btn.className = defaultStyle;
                let onClickAttr = btn.getAttribute('onclick');
                if (onClickAttr && (onClickAttr.includes(`'${dept}'`) || onClickAttr.includes(`"${dept}"`))) {
                    btn.className = activeStyle;
                }
            });

            let searchInput = document.getElementById('search-tech-card');
            let currentSearchValue = searchInput ? searchInput.value.toLowerCase().replace(/\s+/g, '').trim() : '';
            let hasAnyVisibleCard = false;

            document.querySelectorAll('#team_cards .tech-dept-section').forEach(sec => {
                let secDept = sec.getAttribute('data-dept');
                let deptMatch = (dept === 'all' || secDept === dept);
                let hasVisibleCard = false;
                
                if (deptMatch) {
                    sec.querySelectorAll('.tech-card-item').forEach(item => {
                        let name = (item.getAttribute('data-tech-name') || '').toLowerCase().replace(/\s+/g, '');
                        if (name.includes(currentSearchValue)) {
                            item.style.display = '';
                            hasVisibleCard = true;
                            hasAnyVisibleCard = true;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    let header = sec.querySelector('.tech-dept-header');
                    if (header) {
                        header.style.display = hasVisibleCard ? '' : 'none';
                    }
                    
                    sec.style.display = hasVisibleCard ? '' : 'none';
                } else {
                    sec.style.display = 'none';
                }
            });

            let emptyState = document.querySelector('#team_cards .tech-empty-state');
            if (hasAnyVisibleCard) {
                if(emptyState) { emptyState.classList.add('hidden'); emptyState.classList.remove('flex'); }
            } else {
                if(emptyState) { emptyState.classList.remove('hidden'); emptyState.classList.add('flex'); }
            }
        }

        function searchTechCards() {
            filterDeptCard(activeDeptCard);
        }

        function filterDept(dept) {
            filterDeptTable(dept);
            filterDeptCard(dept);
        }

        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'System Management';
            
            // ✨ อัปเดต URL เงียบๆ ให้ตรงกับแท็บที่กดเสมอ เวลา Refresh จะได้กลับมาหน้าเดิมเป๊ะๆ
            history.replaceState(null, '', '?tab=' + id);
            
            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
            }

            let searchInput = document.getElementById('searchInput');
            if(searchInput) {
                searchInput.value = '';
                let activeSection = document.getElementById(id);
                if(activeSection && id === 'repairs') {
                    activeSection.querySelectorAll('table tbody tr:not(.tech-dept-header)').forEach(row => row.style.display = '');
                }
            }

            if(id === 'dash' && !window.chartsRendered) {
                renderAllCharts();
                window.chartsRendered = true;
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        // ✨ ฟังก์ชันจัดการปุ่มกดเลือกอันดับ ✨
        function setTopReportersFilter(limit) {
            currentTopReportersLimit = limit;
            
            const btn3 = document.getElementById('btnFilterTop3');
            const btn5 = document.getElementById('btnFilterTop5');
            const btn10 = document.getElementById('btnFilterTop10');
            const btnAll = document.getElementById('btnFilterTopAll');
            
            const activeClass = "px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            const inactiveClass = "px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            const activeAllClass = "px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            const inactiveAllClass = "px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            
            if(btn3) btn3.className = inactiveClass;
            if(btn5) btn5.className = inactiveClass;
            if(btn10) btn10.className = inactiveClass;
            if(btnAll) btnAll.className = inactiveAllClass;
            
            if (limit === 3 && btn3) btn3.className = activeClass;
            else if (limit === 5 && btn5) btn5.className = activeClass;
            else if (limit === 10 && btn10) btn10.className = activeClass;
            else if (limit === 'all' && btnAll) btnAll.className = activeAllClass;
            
            renderTopReporters();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            window.chartsRendered = false;
            
            if(tab) { show(tab); } else { show('dash'); }

            const inputElement = document.getElementById('searchInput');
            if(inputElement) {
                inputElement.addEventListener('input', function() {
                    let filter = this.value.toLowerCase();
                    let activeSection = document.querySelector('.section:not(.hidden)');
                    if (!activeSection) return;
                    
                    let rows = activeSection.querySelectorAll('table tbody tr:not(.tech-dept-header)');
                    rows.forEach(row => {
                        if (row.innerText.toLowerCase().includes(filter)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            const reportInput = document.getElementById('reportSearchInput');
            if(reportInput) reportInput.value = 'รวมทุกฝ่ายงาน (ทั้งหมด)';
            
            if(document.getElementById('topReportersList')) {
                renderTopReporters();
            }

            // ✨ คืนค่าตำแหน่ง Scroll หลังจาก Auto-Refresh กลับมา ✨
            setTimeout(() => {
                const savedScrollY = sessionStorage.getItem('pageScrollY');
                if (savedScrollY !== null) {
                    window.scrollTo(0, parseInt(savedScrollY));
                    sessionStorage.removeItem('pageScrollY');
                }

                const savedRepairsScrollX = sessionStorage.getItem('repairsScrollX');
                if (savedRepairsScrollX !== null) {
                    const repairsTableWrap = document.getElementById('repairsTable').parentElement;
                    if (repairsTableWrap) repairsTableWrap.scrollLeft = parseInt(savedRepairsScrollX);
                    sessionStorage.removeItem('repairsScrollX');
                }

                const savedHistoryScrollX = sessionStorage.getItem('historyScrollX');
                if (savedHistoryScrollX !== null) {
                    const historyTableBody = document.getElementById('historyTableBody');
                    if (historyTableBody && historyTableBody.parentElement.parentElement) {
                        historyTableBody.parentElement.parentElement.scrollLeft = parseInt(savedHistoryScrollX);
                    }
                    sessionStorage.removeItem('historyScrollX');
                }

                // ถ้าก่อนหน้านี้เปิด Modal ประวัติค้างไว้ ให้เปิดขึ้นมาเหมือนเดิม
                const openModal = sessionStorage.getItem('modalOpen');
                if (openModal === 'historyModal') {
                    const titleStr = sessionStorage.getItem('historyModalTitle');
                    if (titleStr) {
                        let fullName = titleStr.replace('ประวัติงานช่าง: ', '').replace('ประวัติการแจ้งซ่อม: ', '').trim();
                        let type = titleStr.includes('ช่าง') ? 'technician' : 'reporter';
                        viewHistory(fullName, type);
                        
                        // คืนค่า Scroll แนวนอนของ Modal อีกครั้งหลังจากเปิด
                        setTimeout(() => {
                            const historyWrap = document.getElementById('historyTableBody').parentElement.parentElement;
                            if (historyWrap && savedHistoryScrollX !== null) {
                                historyWrap.scrollLeft = parseInt(savedHistoryScrollX);
                            }
                        }, 50);
                    }
                    sessionStorage.removeItem('modalOpen');
                    sessionStorage.removeItem('historyModalTitle');
                }

                // ✨ โค้ดสำหรับดึง Pop-up รีวิวช่าง กลับมาแสดงอัตโนมัติ
                const reopenReviews = sessionStorage.getItem('reopenTechReviewsModal');
                if (reopenReviews === 'true') {
                    sessionStorage.removeItem('reopenTechReviewsModal');
                    const trDept = sessionStorage.getItem('tr_dept');
                    const trTech = sessionStorage.getItem('tr_tech');
                    const trMonth = sessionStorage.getItem('tr_month');
                    const trYear = sessionStorage.getItem('tr_year');
                    
                    if(trMonth) document.getElementById('ratingMonth').value = trMonth;
                    if(trYear) document.getElementById('ratingYear').value = trYear;
                    
                    setTimeout(() => {
                        openTechReviewsModal(trDept, trMonth, trYear);
                        setTimeout(() => {
                            if (trTech) {
                                document.getElementById('modalTechSelector').value = trTech;
                                changeModalTech(trTech);
                            }
                        }, 200);
                    }, 300);
                }

            }, 150); // ดีเลย์นิดนึงให้ข้อมูลเรนเดอร์เสร็จก่อน
        });

        // ✨ ตรวจจับการคลิกเปิดหน้า Edit เพื่อสั่งให้ระบบเตรียม Refresh ✨
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a[href*="update_repair.php"], div[onclick*="update_repair.php"]');
            if (target) {
                sessionStorage.setItem('needsRefresh', 'true');
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            // ✨ คืนค่า Scroll ทันทีเมื่อโหลดหน้าเว็บเสร็จ
            setTimeout(() => {
                const savedScrollY = sessionStorage.getItem('pageScrollY');
                if (savedScrollY !== null) {
                    window.scrollTo(0, parseInt(savedScrollY));
                    sessionStorage.removeItem('pageScrollY');
                }

                const savedRepairsScrollX = sessionStorage.getItem('repairsScrollX');
                if (savedRepairsScrollX !== null) {
                    const repairsTableWrap = document.getElementById('repairsTable').parentElement;
                    if (repairsTableWrap) repairsTableWrap.scrollLeft = parseInt(savedRepairsScrollX);
                    sessionStorage.removeItem('repairsScrollX');
                }

                const openModal = sessionStorage.getItem('modalOpen');
                if (openModal === 'historyModal') {
                    const titleStr = sessionStorage.getItem('historyModalTitle');
                    if (titleStr) {
                        let fullName = titleStr.replace('ประวัติงานช่าง: ', '').replace('ประวัติการแจ้งซ่อม: ', '').trim();
                        let type = titleStr.includes('ช่าง') ? 'technician' : 'reporter';
                        viewHistory(fullName, type);
                        
                        setTimeout(() => {
                            const savedHistoryScrollX = sessionStorage.getItem('historyScrollX');
                            const historyTableBody = document.getElementById('historyTableBody');
                            if (historyTableBody && savedHistoryScrollX !== null) {
                                historyTableBody.parentElement.parentElement.scrollLeft = parseInt(savedHistoryScrollX);
                            }
                            sessionStorage.removeItem('historyScrollX');
                        }, 50);
                    }
                    sessionStorage.removeItem('modalOpen');
                    sessionStorage.removeItem('historyModalTitle');
                }

                // ✨ จำลองเปิด Pop-up รีวิวช่างคืนให้อัตโนมัติ ✨
                const reopenReviews = sessionStorage.getItem('reopenTechReviewsModal');
                if (reopenReviews === 'true') {
                    sessionStorage.removeItem('reopenTechReviewsModal');
                    const trDept = sessionStorage.getItem('tr_dept');
                    const trTech = sessionStorage.getItem('tr_tech');
                    const trMonth = sessionStorage.getItem('tr_month');
                    const trYear = sessionStorage.getItem('tr_year');
                    
                    if(trMonth) document.getElementById('ratingMonth').value = trMonth;
                    if(trYear) document.getElementById('ratingYear').value = trYear;
                    
                    setTimeout(() => {
                        openTechReviewsModal(trDept, trMonth, trYear);
                        setTimeout(() => {
                            if (trTech) {
                                document.getElementById('modalTechSelector').value = trTech;
                                changeModalTech(trTech);
                            }
                        }, 200);
                    }, 300);
                }
            }, 100);

            // เรนเดอร์กราฟเฉพาะถ้าอยู่หน้า dash
            const urlParams = new URLSearchParams(window.location.search);
            let tab = urlParams.get('tab') || 'dash';
            window.chartsRendered = false;
            
            if(tab === 'dash') {
                renderAllCharts();
                window.chartsRendered = true;
            }

            const inputElement = document.getElementById('searchInput');
            if(inputElement) {
                inputElement.addEventListener('input', function() {
                    let filter = this.value.toLowerCase();
                    let activeSection = document.querySelector('.section:not(.hidden)');
                    if (!activeSection) return;
                    let rows = activeSection.querySelectorAll('table tbody tr:not(.tech-dept-header)');
                    rows.forEach(row => {
                        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
                    });
                });
            }
            
            const reportInput = document.getElementById('reportSearchInput');
            if(reportInput) reportInput.value = 'รวมทุกฝ่ายงาน (ทั้งหมด)';
            
            if(document.getElementById('topReportersList')) renderTopReporters();
        });

        // ✨ ตรวจจับการคลิกเปิดหน้า Edit หรือ View เพื่อสั่งเตรียม Refresh
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a[href*="update_repair.php"], div[onclick*="update_repair.php"], a[href*="view_repair.php"]');
            if (target) {
                // 🚫 ตรวจสอบว่าคลิกมาจากใน Pop-up หรือไม่ ถ้าใช่ให้ข้ามการรีเฟรชไปเลย (รักษาหน้าจอเดิมไว้เป๊ะๆ 100% ไม่มีกระพริบ)
                if (!target.closest('.modal-container')) {
                    sessionStorage.setItem('needsRefresh', 'true');
                }
            }
        });

        // โหลดข้อมูลเนียนๆ เมื่อสลับแท็บกลับมา
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible") {
                if (sessionStorage.getItem('needsRefresh') === 'true') {
                    sessionStorage.removeItem('needsRefresh');
                    
                    // บันทึกตำแหน่งและแท็บไว้
                    const activeSection = document.querySelector('.section:not(.hidden)');
                    if (activeSection) {
                        sessionStorage.setItem('activeTabBeforeRefresh', activeSection.id);
                    }
                    
                    sessionStorage.setItem('pageScrollY', window.scrollY);
                    
                    const repairsTableWrap = document.getElementById('repairsTable')?.parentElement;
                    if (repairsTableWrap) {
                        sessionStorage.setItem('repairsScrollX', repairsTableWrap.scrollLeft);
                    }

                    const historyTableBody = document.getElementById('historyTableBody');
                    if (historyTableBody && !document.getElementById('historyModal').classList.contains('opacity-0')) {
                        sessionStorage.setItem('historyScrollX', historyTableBody.parentElement.parentElement.scrollLeft);
                        sessionStorage.setItem('modalOpen', 'historyModal');
                        sessionStorage.setItem('historyModalTitle', document.getElementById('historyModalTitle').innerText);
                    }

                    // ✨ ตรวจสอบว่า Pop-up รีวิวช่างเปิดอยู่หรือไม่ ถ้าเปิดอยู่ให้จำค่าไว้ ✨
                    const techReviewsModal = document.getElementById('techReviewsModal');
                    if (techReviewsModal && !techReviewsModal.classList.contains('opacity-0')) {
                        sessionStorage.setItem('reopenTechReviewsModal', 'true');
                        sessionStorage.setItem('tr_dept', document.getElementById('techReviewsModalDept').innerText);
                        sessionStorage.setItem('tr_tech', document.getElementById('modalTechSelector').value);
                        sessionStorage.setItem('tr_month', document.getElementById('ratingMonth').value);
                        sessionStorage.setItem('tr_year', document.getElementById('ratingYear').value);
                    }

                    // สั่งรีเฟรชตรงๆ (ข้อมูลแท็บและ Scroll ถูกจำไว้ใน Session Storage แล้ว)
                    window.location.reload();
                }
            }
        });
        
        function searchHistoryTable() {
            let input = document.getElementById('searchHistoryInput');
            if(!input) return;
            let filter = input.value.toLowerCase().replace(/\s+/g, '');
            let table = document.getElementById('usersTable');
            if(!table) return;
            let rows = table.querySelectorAll('tbody .user-row');
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase().replace(/\s+/g, '');
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function safeString(val) { return val ? String(val) : ''; }

        function getFilteredRepairsByMonthYear(m, y) {
            if (m === 'all' && y === 'all') return allRepairs;
            return allRepairs.filter(r => {
                if (!r.created_at || r.created_at === '0000-00-00 00:00:00') return false;
                let datePart = r.created_at.split(' ')[0]; // YYYY-MM-DD
                if (!datePart) return false;
                let parts = datePart.split('-');
                if (parts.length < 3) return false;
                let rYear = parts[0];
                let rMonth = parts[1];
                
                let matchMonth = (m === 'all' || rMonth === m);
                let matchYear = (y === 'all' || rYear === y);
                return matchMonth && matchYear;
            });
        }

        function renderAllCharts() {
            renderEquipChart();
            renderStatusChart();
            renderLocChart();
            renderTechChart();
            renderRatingChart();
        }

        function renderEquipChart() {
            let m = document.getElementById('equipMonth').value;
            let y = document.getElementById('equipYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let map = {};
            data.forEach(r => {
                if(r.equipment_type) map[r.equipment_type] = (map[r.equipment_type] || 0) + 1;
            });
            let sorted = Object.keys(map).map(k => ({ name: k, count: map[k] })).sort((a,b) => b.count - a.count).slice(0, 7);
            
            const ctx = document.getElementById('mainEquipChart').getContext('2d');
            if(chartEquipInstance) chartEquipInstance.destroy();
            
            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            chartEquipInstance = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: sorted.length ? sorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{ 
                        label: 'จำนวน (ครั้ง)', 
                        data: sorted.length ? sorted.map(e => e.count) : [0], 
                        borderColor: '#8b5cf6', 
                        backgroundColor: gradient, 
                        borderWidth: 3, 
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#8b5cf6',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
        }

        function renderStatusChart() {
            let m = document.getElementById('statusMonth').value;
            let y = document.getElementById('statusYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let pending = 0, progress = 0, completed = 0;
            data.forEach(r => {
                if(r.status === 'รอรับเรื่อง') pending++;
                else if(r.status === 'กำลังดำเนินการ') progress++;
                else if(r.status === 'ซ่อมเสร็จแล้ว') completed++;
            });

            const ctx = document.getElementById('mainStatusChart').getContext('2d');
            if(chartStatusInstance) chartStatusInstance.destroy();
            
            let isEmpty = (pending+progress+completed === 0);
            
            chartStatusInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['รอดำเนินการ', 'กำลังแก้ไข', 'เสร็จสิ้น'],
                    datasets: [{ 
                        data: isEmpty ? [1] : [pending, progress, completed], 
                        backgroundColor: isEmpty ? ['#f1f5f9'] : ['#f59e0b', '#38bdf8', '#10b981'],
                        borderWidth: 0, 
                        hoverOffset: 4 
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { 
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif", weight: '600' } } },
                        tooltip: { callbacks: { label: function(context) { return isEmpty ? ' ไม่มีข้อมูล' : ' ' + context.formattedValue + ' งาน'; } } }
                    }, 
                    cutout: '75%' 
                }
            });
        }

        function renderLocChart() {
            let m = document.getElementById('locMonth').value;
            let y = document.getElementById('locYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let map = {};
            data.forEach(r => {
                if(r.location && r.location !== 'ไม่ระบุสถานที่') map[r.location] = (map[r.location] || 0) + 1;
            });
            let sorted = Object.keys(map).map(k => ({ name: k, count: map[k] })).sort((a,b) => b.count - a.count).slice(0, 5);

            const ctx = document.getElementById('mainLocChart').getContext('2d');
            if(chartLocInstance) chartLocInstance.destroy();
            
            chartLocInstance = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: sorted.length ? sorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{ 
                        label: 'แจ้งซ่อม (ครั้ง)', 
                        data: sorted.length ? sorted.map(e => e.count) : [0], 
                        backgroundColor: '#f43f5e', 
                        borderRadius: 6
                    }]
                },
                options: { 
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        y: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
        }

        function renderTechChart() {
            let m = document.getElementById('techMonth').value;
            let y = document.getElementById('techYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let map = {};
            data.forEach(r => {
                let tName = r.technician_name ? r.technician_name : 'ไม่ระบุช่าง';
                map[tName] = (map[tName] || 0) + 1;
            });
            let sorted = Object.keys(map).map(k => ({ name: k, count: map[k] })).sort((a,b) => b.count - a.count).slice(0, 5);

            const ctx = document.getElementById('mainTechChart').getContext('2d');
            if(chartTechInstance) chartTechInstance.destroy();
            
            chartTechInstance = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: sorted.length ? sorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{ 
                        label: 'รับผิดชอบ (งาน)', 
                        data: sorted.length ? sorted.map(e => e.count) : [0], 
                        backgroundColor: '#6366f1', 
                        borderRadius: 6
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
        }

      // ✨ 1. ฟังก์ชันกราฟเรตติ้ง (ชื่อฝ่ายสีน้ำเงิน + ดาวไล่สีตามเปอร์เซ็นต์จริง) ✨
        function renderRatingChart() {
            let m = document.getElementById('ratingMonth').value;
            let y = document.getElementById('ratingYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let deptMap = {}; 
            let techMap = {}; 

            data.forEach(r => {
                let rating = parseFloat(r.rating);
                if (!isNaN(rating) && rating > 0) {
                    let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                    
                    let dName = techDeptMap[tName] ? techDeptMap[tName] : 'ไม่มีสังกัด';
                    if (dName !== 'ไม่มีสังกัด' && !dName.startsWith('ฝ่ายงาน') && dName !== 'แม่บ้าน' && dName !== 'อื่นๆ') {
                        dName = 'ฝ่ายงาน' + dName;
                    }

                    if (!techMap[tName]) techMap[tName] = { sum: 0, count: 0, dept: dName };
                    techMap[tName].sum += rating;
                    techMap[tName].count++;

                    if (!deptMap[dName]) deptMap[dName] = { sum: 0, count: 0, techs: new Set() };
                    deptMap[dName].sum += rating;
                    deptMap[dName].count++;
                    deptMap[dName].techs.add(tName);
                }
            });

            let deptArr = Object.keys(deptMap).map(dName => {
                let dAvg = (deptMap[dName].sum / deptMap[dName].count).toFixed(1);
                
                let topTechName = '-';
                let topTechAvg = 0;
                
                deptMap[dName].techs.forEach(t => {
                    let tAvg = (techMap[t].sum / techMap[t].count);
                    if (tAvg > topTechAvg) {
                        topTechAvg = tAvg;
                        topTechName = t;
                    }
                });

                return {
                    name: dName,
                    avg: dAvg,
                    count: deptMap[dName].count,
                    topTech: topTechName,
                    topTechAvg: topTechAvg.toFixed(1)
                };
            });
            
            deptArr.sort((a, b) => b.avg - a.avg || b.count - a.count);

            const getRatingColor = (score) => {
                if (score >= 4.5) return '#22c55e'; 
                if (score >= 3.5) return '#84cc16'; 
                if (score >= 2.5) return '#eab308'; 
                if (score >= 1.5) return '#f97316'; 
                return '#ef4444'; 
            };

            const ctx = document.getElementById('mainRatingChart').getContext('2d');
            if(chartRatingInstance) chartRatingInstance.destroy();
            
            chartRatingInstance = new Chart(ctx, {
                type: 'bar', 
                plugins: [{
                    id: 'custom_star_gradient',
                    afterDraw: (chart) => {
                        const ctx = chart.ctx;
                        const yAxis = chart.scales.y;
                        
                        ctx.save();
                        ctx.textAlign = 'right';
                        ctx.textBaseline = 'middle';
                        
                        yAxis.ticks.forEach((tick, index) => {
                            const y = yAxis.getPixelForTick(index);
                            const labelArray = chart.data.labels[index];
                            if (!labelArray) return;
                            
                            if (Array.isArray(labelArray) && labelArray.length === 3) {
                                const scoreStr = labelArray[0].trim();
                                const scoreVal = parseFloat(scoreStr) || 0;
                                const tName = labelArray[1];
                                const dName = labelArray[2];
                                
                                // 1. วาดชื่อฝ่ายงาน (บรรทัดล่าง) - สีน้ำเงิน
                                ctx.font = '800 14px "Sarabun", sans-serif';
                                ctx.fillStyle = '#4f46e5';
                                ctx.fillText(dName, yAxis.right - 10, y + 18);

                                // 2. วาดชื่อช่าง (บรรทัดกลาง) - สีเทาเข้ม
                                ctx.font = 'bold 13px "Sarabun", sans-serif';
                                ctx.fillStyle = '#475569';
                                ctx.fillText(tName, yAxis.right - 10, y);

                                // 3. วาดคะแนนตัวเลข (บรรทัดบน)
                                const textY = y - 18; 
                                ctx.font = 'bold 12px "Sarabun", sans-serif';
                                ctx.fillStyle = '#64748b';
                                ctx.fillText(scoreStr, yAxis.right - 10, textY);
                                
                                // 4. วาดดาวไล่สี
                                const scoreWidth = ctx.measureText(scoreStr).width;
                                const starX = yAxis.right - 10 - scoreWidth - 4; 
                                
                                ctx.font = '900 13px "Font Awesome 6 Free"';
                                const starIcon = '\uf005'; 
                                const starWidth = ctx.measureText(starIcon).width;
                                const startX = starX - starWidth;
                                
                                // ดาวพื้นหลัง (สีเทา)
                                ctx.fillStyle = '#e2e8f0';
                                ctx.fillText(starIcon, starX, textY);
                                
                                // ดาวทับ (สีเหลือง ไล่ตาม %)
                                if (scoreVal > 0) {
                                    const fillPercent = scoreVal / 5.0;
                                    ctx.save();
                                    ctx.beginPath();
                                    ctx.rect(startX, textY - 10, starWidth * fillPercent, 20);
                                    ctx.clip(); 
                                    ctx.fillStyle = '#f59e0b'; 
                                    ctx.fillText(starIcon, starX, textY);
                                    ctx.restore();
                                }
                            } else {
                                ctx.font = 'bold 13px "Sarabun", sans-serif';
                                ctx.fillStyle = '#475569';
                                ctx.fillText(Array.isArray(labelArray) ? labelArray.join(' ') : labelArray, yAxis.right - 10, y);
                            }
                        });
                        ctx.restore();
                    }
                }],
                data: {
                    // เติม Space หน้าตัวเลขเพื่อให้มีพื้นที่วาดดาว
                    labels: deptArr.length ? deptArr.map(d => ['   ' + d.topTechAvg, d.topTech, d.name]) : [['ไม่มีข้อมูล']],
                    datasets: [
                        { 
                            label: 'คะแนนเฉลี่ยฝ่าย', 
                            data: deptArr.length ? deptArr.map(d => d.avg) : [0], 
                            backgroundColor: deptArr.length ? deptArr.map(d => getRatingColor(d.avg)) : ['#e2e8f0'], 
                            borderRadius: 10,
                            barThickness: 24,
                            maxBarThickness: 32,
                            borderSkipped: false,
                            z: 2
                        },
                        {
                            label: 'เต็ม 5',
                            data: deptArr.length ? deptArr.map(d => 5.0) : [5.0],
                            backgroundColor: '#f1f5f9',
                            borderRadius: 10,
                            barThickness: 24,
                            maxBarThickness: 32,
                            borderSkipped: false,
                            z: 1
                        }
                    ]
                },
                options: { 
                    indexAxis: 'y', 
                    grouped: false,
                    responsive: true, 
                    maintainAspectRatio: false,
                    interaction: { mode: 'y', intersect: false },
                    onClick: (e, elements, chart) => {
                        const activeElements = chart.getElementsAtEventForMode(e, 'y', { intersect: false }, true);
                        if (activeElements && activeElements.length > 0 && deptArr.length > 0) {
                            const index = activeElements[0].index;
                            if(deptArr && deptArr[index]) {
                                let selectedDept = deptArr[index].name;
                                openTechReviewsModal(selectedDept, m, y);
                            }
                        }
                    },
                    onHover: (event, chartElement) => {
                        event.native.target.style.cursor = chartElement.length > 0 ? 'pointer' : 'default';
                    },
                    layout: { padding: { top: 10, bottom: 10, left: 0, right: 20 } },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            filter: function(tooltipItem) { return tooltipItem.datasetIndex === 0; },
                            callbacks: {
                                title: function(context) {
                                    if (!deptArr.length) return '';
                                    return deptArr[context[0].dataIndex].name;
                                },
                                label: function(context) {
                                    if (!deptArr.length) return ' ไม่มีข้อมูล';
                                    let dept = deptArr[context.dataIndex];
                                    return [' ช่าง ' + dept.topTech + ' (⭐ ' + parseFloat(dept.topTechAvg).toFixed(1) + ')', ' จำนวน: ' + dept.count + ' รีวิว'];
                                }
                            }
                        }
                    }, 
                    scales: { 
                        x: { 
                            min: 0, max: 5,
                            ticks: { stepSize: 1, font: { family: "'Sarabun', sans-serif", weight: 'bold' }, color: '#94a3b8' }, 
                            grid: { color: '#f1f5f9', drawBorder: false }, 
                            border: {display: false} 
                        }, 
                        y: { 
                            ticks: { 
                                color: 'transparent', // ซ่อน text จริง เพื่อให้ Plugin วาดทับ
                                font: { family: "'Sarabun', sans-serif", size: 14, weight: 'bold' } 
                            }, 
                            grid: { display: false }, 
                            border: {display: false} 
                        } 
                    } 
                }
            });
        }

        function setReviewFilter(val) {
            currentReviewFilter = val;
            
            const btnAll = document.getElementById('btnFilterAllReviews');
            const btnZero = document.getElementById('btnFilterZeroReviews');
            
            btnAll.className = "px-4 py-1.5 text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            if(btnZero) btnZero.className = "px-3 py-1.5 text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            
            if(val === 'all') {
                btnAll.className = "px-4 py-1.5 text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            } else if (val === 0) {
                if(btnZero) btnZero.className = "px-3 py-1.5 text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            }
            
            const stars = document.querySelectorAll('#starFilterContainer i');
            stars.forEach((star, index) => {
                let starVal = index + 1;
                if(val !== 'all' && val !== 0 && starVal <= val) {
                    star.className = "fas fa-star cursor-pointer text-amber-400 hover:scale-125 transition-all text-lg drop-shadow-sm";
                } else {
                    star.className = "fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200";
                }
            });
            
            renderTechReviewsList();
        }

        // ✨ 2. ฟังก์ชัน Dropdown ใน Modal (ดันขวาให้ตรงกัน + ใช้ดาว ⭐ ทึบ/โปร่ง + เล็กกะทัดรัด) ✨
        function openTechReviewsModal(deptName, month, year) {
            document.getElementById('techReviewsModalDept').innerText = deptName;
            
            let data = getFilteredRepairsByMonthYear(month, year);
            
            let allTechsInDept = Object.keys(techDeptMap).filter(tName => {
                let dName = techDeptMap[tName] ? techDeptMap[tName] : 'ไม่มีสังกัด';
                if (dName !== 'ไม่มีสังกัด' && !dName.startsWith('ฝ่ายงาน') && dName !== 'แม่บ้าน' && dName !== 'อื่นๆ') {
                    dName = 'ฝ่ายงาน' + dName;
                }
                return dName === deptName;
            });

            currentDeptReviewsData = data.filter(r => {
                let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                return allTechsInDept.includes(tName);
            });

            let techStats = {};
            allTechsInDept.forEach(tName => { techStats[tName] = { sum: 0, count: 0 }; });

            currentDeptReviewsData.forEach(r => {
                let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                if(techStats[tName]) {
                    let rating = parseFloat(r.rating) || 0;
                    if(rating > 0) { 
                        techStats[tName].sum += rating; 
                        techStats[tName].count++; 
                    }
                }
            });

            let techArr = Object.keys(techStats).map(k => {
                let tAvg = techStats[k].count > 0 ? (techStats[k].sum / techStats[k].count).toFixed(1) : 0;
                return { name: k, avg: parseFloat(tAvg), count: techStats[k].count };
            });

            techArr.sort((a, b) => b.avg - a.avg || b.count - a.count);

            const selector = document.getElementById('modalTechSelector');
            selector.innerHTML = '';
            
            // ปรับขนาดให้เล็กกะทัดรัดเหมือนเดิม (w-max min-w-[200px] max-w-[280px])
            selector.className = "w-max min-w-[200px] max-w-[320px] bg-slate-50 border border-slate-200 text-xs text-slate-700 rounded-md pl-3 pr-8 py-1.5 focus:outline-none focus:border-indigo-400 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm appearance-none mt-1";
            selector.style.backgroundImage = "url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')";
            selector.style.backgroundRepeat = "no-repeat";
            selector.style.backgroundPosition = "right 0.5rem top 50%";
            selector.style.backgroundSize = "0.55rem auto";
            
            if(techArr.length === 0) {
                selector.style.display = 'none';
                document.getElementById('techReviewsModalTitle').innerText = 'รีวิวฝ่ายงาน';
                document.getElementById('techReviewsModalCount').innerText = '0 รีวิว';
                currentTechReviewsData = [];
                renderTechReviewsList();
            } else {
                selector.style.display = 'block';
                techArr.forEach(t => {
                    let opt = document.createElement('option');
                    opt.value = t.name;
                    
                    // ดึงเฉพาะชื่อภาษาไทย
                    let thNameOnly = (techInfoMap[t.name] && techInfoMap[t.name].th) ? techInfoMap[t.name].th : t.name.split(' (')[0];
                    
                    // สูตรคำนวณการดันช่องไฟไปทางขวาให้ตรงกัน (ตัดสระบนล่างภาษาไทยทิ้งก่อนคำนวณความยาว)
                    let visualLen = thNameOnly.replace(/[\u0E31-\u0E3A\u0E47-\u0E4E]/g, '').length;
                    let padSpaces = '\u00A0'.repeat(Math.max(2, 38 - (visualLen * 1.8))); 
                    
                    // ใช้ดาว ⭐ และ ☆ ตามที่สั่ง
                    if (t.avg > 0) {
                        opt.innerHTML = `${thNameOnly}${padSpaces}⭐ ${t.avg} (${t.count} รีวิว)`;
                    } else {
                        opt.innerHTML = `${thNameOnly}${padSpaces}☆ ยังไม่มีคะแนน`;
                    }
                    selector.appendChild(opt);
                });
                
                changeModalTech(techArr[0].name);
            }
            
            toggleModal('techReviewsModal');
        }

        // ✨ 3. ฟังก์ชันดาวดวงใหญ่ใน Modal ✨
        function changeModalTech(techName) {
            let thNameOnly = (techInfoMap[techName] && techInfoMap[techName].th) ? techInfoMap[techName].th : techName.split(' (')[0];
            document.getElementById('techReviewsModalTitle').innerText = 'รีวิวของช่าง: ' + thNameOnly;
            
            let posName = (techInfoMap[techName] && techInfoMap[techName].pos) ? techInfoMap[techName].pos : '';
            document.getElementById('techReviewsModalPos').innerText = posName && posName !== '-' ? '(' + posName + ')' : '(ไม่ระบุตำแหน่ง)';

            currentTechReviewsData = currentDeptReviewsData.filter(r => {
                let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                let rRating = parseFloat(r.rating) || 0;
                let hasComment = r.review_comment && r.review_comment.trim() !== '' && r.review_comment.trim() !== '-';
                return tName === techName && (rRating > 0 || hasComment);
            });

            document.getElementById('techReviewsModalCount').innerText = currentTechReviewsData.length + ' รีวิว';
            
            let sum = 0, count = 0;
            currentTechReviewsData.forEach(r => {
                let rating = parseFloat(r.rating) || 0;
                if(rating > 0) { sum += rating; count++; }
            });
            let avg = count > 0 ? sum / count : 0;
            
            const bigStarIcon = document.getElementById('techReviewsModalTitle').parentNode.parentNode.querySelector('.fa-star');
            if (bigStarIcon) {
                if (avg > 0) {
                    let percent = (avg / 5.0) * 100;
                    bigStarIcon.style.background = `linear-gradient(90deg, #f59e0b ${percent}%, #e2e8f0 ${percent}%)`;
                    bigStarIcon.style.webkitBackgroundClip = 'text';
                    bigStarIcon.style.webkitTextFillColor = 'transparent';
                } else {
                    bigStarIcon.style.background = 'none';
                    bigStarIcon.style.webkitBackgroundClip = 'border-box';
                    bigStarIcon.style.webkitTextFillColor = 'initial';
                    bigStarIcon.style.color = '#cbd5e1'; 
                }
            }
            
            setReviewFilter('all'); 
        }
        
        // ✨ ฟังก์ชันคำนวณและแสดงผล Top Reporters + คลิกเด้งดูประวัติได้ ✨
        function renderTopReporters() {
            const container = document.getElementById('topReportersList');
            if(!container) return;
            container.innerHTML = '';

            let m = document.getElementById('reporterMonth') ? document.getElementById('reporterMonth').value : 'all';
            let y = document.getElementById('reporterYear') ? document.getElementById('reporterYear').value : 'all';

            let filteredRepairs = getFilteredRepairsByMonthYear(m, y);

            let reporterMap = {};
            filteredRepairs.forEach(r => {
                let name = formatValJS(r.reporter_name);
                if (name.includes('ไม่ระบุ') || name.includes('span')) name = 'ไม่ระบุชื่อผู้แจ้ง';
                else name = r.reporter_name.trim();

                if (!reporterMap[name]) reporterMap[name] = 0;
                reporterMap[name]++;
            });

            let reporterArr = Object.keys(reporterMap).map(k => ({ name: k, count: reporterMap[k] }));
            reporterArr.sort((a,b) => b.count - a.count);

            if (currentTopReportersLimit !== 'all') {
                reporterArr = reporterArr.slice(0, parseInt(currentTopReportersLimit));
            }

            if(reporterArr.length === 0) {
                container.innerHTML = `<div class='p-10 flex flex-col items-center justify-center text-center h-full min-h-[250px]'>
                                            <i class='fas fa-user-tag text-4xl text-slate-200 mb-3'></i>
                                            <p class='text-slate-400 font-medium text-sm mt-2'>ไม่พบข้อมูลผู้แจ้งในเดือน/ปีนี้</p>
                                        </div>`;
            } else {
                reporterArr.forEach((rep, index) => {
                    // ✨ ปรับสีให้ดูพรีเมียมขึ้น (ทอง, เงิน, ทองแดง)
                    let rankColor = index === 0 ? 'text-[#eab308] bg-[#fefce8] border-[#fde047]' : // ทอง
                                    index === 1 ? 'text-[#94a3b8] bg-[#f8fafc] border-[#e2e8f0]' : // เงิน
                                    index === 2 ? 'text-[#d97706] bg-[#fffbeb] border-[#fde68a]' : // ทองแดง
                                    'text-indigo-500 bg-indigo-50 border-indigo-100';

                    // ✨ เปลี่ยนไอคอนเป็นเซ็ต 'เหรียญรางวัล' (medal) ที่ทิศทางริบบิ้นชี้ลงเหมือนกันทั้งหมด
                    // ✨ คืนถ้วยรางวัลให้อันดับ 1 (ทอง) และใช้เหรียญรางวัลทิศทางเดียวกันให้อันดับ 2-3 ✨
                    let rankIcon = index === 0 ? '<i class="fas fa-trophy text-lg drop-shadow-sm"></i>' :
                                   index === 1 ? '<i class="fas fa-medal text-lg drop-shadow-sm"></i>' :
                                   index === 2 ? '<i class="fas fa-medal text-lg drop-shadow-sm"></i>' :
                                   `<span class="text-sm font-black">#${index + 1}</span>`;

                    let displayName = rep.name;
                    let lineIdHtml = `<div class='text-[11px] text-slate-400 font-medium mt-0.5'>บุคลากรผู้แจ้งซ่อม</div>`;
                    
                    if (lineUsersMap[rep.name] && lineUsersMap[rep.name].real_name) {
                        displayName = lineUsersMap[rep.name].real_name;
                        if (rep.name !== displayName) {
                            // ✨ เปลี่ยนไอคอนเป็นสีเขียว LINE และให้ชื่อเป็นสีน้ำเงิน
                            lineIdHtml = `<div class='text-[12px] font-bold text-indigo-600 mt-0.5 flex items-center'><i class='fab fa-line text-[#06C755] text-[14px] mr-1.5'></i> ${rep.name}</div>`;
                        }
                    }

                    let safeName = rep.name.replace(/'/g, "\\'");
                    let clickAction = rep.name === 'ไม่ระบุชื่อผู้แจ้ง' ? '' : `onclick="viewHistory('${safeName}', 'reporter')" class="p-4 md:p-5 hover:bg-slate-50 transition-colors flex items-center justify-between border-b border-slate-50 last:border-0 cursor-pointer group" title="คลิกเพื่อดูประวัติการแจ้งซ่อมของ ${displayName}"`;
                    let disableClickClass = rep.name === 'ไม่ระบุชื่อผู้แจ้ง' ? `class="p-4 md:p-5 flex items-center justify-between border-b border-slate-50 last:border-0 opacity-70"` : '';

                    container.innerHTML += `
                        <div ${clickAction || disableClickClass}>
                            <div class='flex items-center gap-4'>
                                <div class='w-10 h-10 rounded-full flex items-center justify-center border shadow-sm ${rankColor} shrink-0 group-hover:scale-110 transition-transform'>
                                    ${rankIcon}
                                </div>
                                <div>
                                    <div class='text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors'>${displayName}</div>
                                    ${lineIdHtml}
                                </div>
                            </div>
                            <div class='text-right flex items-center'>
                                <div class='flex flex-col items-end'>
                                    <span class='text-xl font-extrabold text-indigo-600'>${rep.count}</span>
                                    <span class='text-[11px] text-slate-500 font-medium ml-1'>รายการ</span>
                                </div>
                                ${rep.name !== 'ไม่ระบุชื่อผู้แจ้ง' ? `<div class="ml-4 w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center group-hover:bg-indigo-50 group-hover:border-indigo-200 transition-all shadow-sm"><i class="fas fa-chevron-right text-slate-400 group-hover:text-indigo-600 transition-all group-hover:translate-x-0.5 text-xs"></i></div>` : '<div class="ml-4 w-8 h-8"></div>'}
                            </div>
                        </div>`;
                });
            }
        }

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        function updateExcelLink() {
            const filterValue = document.getElementById('techFilter').value;
            if (filterValue !== 'all') {
                document.getElementById('exportExcelBtn').href = `export_excel.php?tech=${encodeURIComponent(filterValue)}`;
            } else {
                document.getElementById('exportExcelBtn').href = `export_excel.php`;
            }
        }

        function printOfficialReport() {
            const filterValue = document.getElementById('techFilter').value;
            let printUrl = 'print_report.php?type=table';
            if (filterValue !== 'all') {
                printUrl += `&tech=${encodeURIComponent(filterValue)}`;
            }
            window.open(printUrl, '_blank');
        }

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
            }
        }

        function toggleCustomInput(selectElement, customInputId) {
            const customInput = document.getElementById(customInputId);
            if(selectElement.value === 'อื่นๆ') { 
                customInput.classList.remove('hidden'); customInput.required = true;
            } else { 
                customInput.classList.add('hidden'); customInput.required = false; 
            }
        }

        function setDropdownOrCustom(selectId, customInputId, val) {
            const selectEl = document.getElementById(selectId);
            const customEl = document.getElementById(customInputId);
            if (!val || val === '-') { selectEl.value = ''; customEl.classList.add('hidden'); customEl.value = ''; customEl.required = false; return; }
            const options = Array.from(selectEl.options).map(opt => opt.value);
            if (options.includes(val) && val !== 'อื่นๆ') {
                selectEl.value = val; customEl.classList.add('hidden'); customEl.value = ''; customEl.required = false;
            } else {
                selectEl.value = 'อื่นๆ'; customEl.classList.remove('hidden'); customEl.value = val; customEl.required = true;
            }
        }

        function openAddAssetModal() { 
            document.getElementById('assetModalTitle').innerHTML = 'Add New Asset'; 
            document.getElementById('asset_id').value = ''; document.getElementById('asset_code').value = ''; document.getElementById('asset_name').value = ''; document.getElementById('asset_category').value = 'IT Support'; document.getElementById('asset_status').value = 'ใช้งานปกติ'; toggleModal('assetModal'); 
        }

        function openEditAssetModal(id, c, n, cat, s) { 
            document.getElementById('assetModalTitle').innerHTML = 'Edit Asset'; 
            document.getElementById('asset_id').value = id; document.getElementById('asset_code').value = c; document.getElementById('asset_name').value = n; document.getElementById('asset_category').value = cat; document.getElementById('asset_status').value = s; toggleModal('assetModal'); 
        }

        function openTechAdminModal(role, id='', u='', f='', en='', pos='', p='', d='', avatarUrl='') { 
            let isManagement = (role.toLowerCase() === 'admin' || role.toLowerCase() === 'executive');
            let baseRole = isManagement ? 'Admin' : 'Technician';
            let title = isManagement ? 'Manage Administrator' : 'Manage Technician';
            document.getElementById('techAdminModalTitle').innerHTML = title; 
            document.getElementById('techAdmin_role').value = baseRole; 
            
            const adminLevelDiv = document.getElementById('adminLevelDiv'); 
            const deptDiv = document.getElementById('deptDiv');
            const loginCredsDiv = document.getElementById('loginCredsDiv');
            const avatarDiv = document.getElementById('avatarDiv');
            const avatarLabelWrapper = document.getElementById('avatarLabelWrapper');
            const avatarPositionWrapper = document.getElementById('avatarPositionWrapper');
            const positionDiv = document.getElementById('positionDiv');
            const displayPositionLabel = document.getElementById('displayPositionLabel');
            
            let oldHidden = document.getElementById('final_avatar_position');
            if(oldHidden) oldHidden.remove();
            
            if(isManagement) {
                adminLevelDiv.classList.remove('hidden'); 
                deptDiv.classList.add('hidden'); 
                document.getElementById('techAdmin_department_select').required = false;
                
                let exactRole = (role.toLowerCase() === 'executive') ? 'Executive' : 'Admin'; document.getElementById('techAdmin_level').value = exactRole;
                loginCredsDiv.classList.remove('hidden'); document.getElementById('techAdmin_username').required = true;
                if(avatarDiv) avatarDiv.classList.add('hidden');
                if(positionDiv) positionDiv.classList.add('hidden');
            } else {
                adminLevelDiv.classList.add('hidden'); deptDiv.classList.remove('hidden'); document.getElementById('techAdmin_department_select').required = true;
                loginCredsDiv.classList.add('hidden'); document.getElementById('techAdmin_username').required = false; document.getElementById('techAdmin_password').required = false;
                if(avatarDiv) avatarDiv.classList.remove('hidden');
                
                if (id === '') {
                    if (avatarLabelWrapper) avatarLabelWrapper.classList.remove('hidden');
                    if (avatarPositionWrapper) avatarPositionWrapper.classList.add('hidden');
                    if (positionDiv) positionDiv.classList.remove('hidden');
                    
                    document.getElementById('techAdmin_position_select').name = 'position_select';
                    document.getElementById('techAdmin_position_custom').name = 'position_custom';
                    setDropdownOrCustom('techAdmin_position_select', 'techAdmin_position_custom', '');
                } else {
                    if (avatarLabelWrapper) avatarLabelWrapper.classList.add('hidden');
                    if (avatarPositionWrapper) avatarPositionWrapper.classList.remove('hidden');
                    if (positionDiv) positionDiv.classList.add('hidden');
                    
                    let displayPosText = pos ? pos : 'ระบุตำแหน่งงาน';
                    displayPositionLabel.innerText = displayPosText;
                    document.getElementById('techAdmin_position_select').name = '';
                    document.getElementById('techAdmin_position_custom').name = '';
                    
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.id = 'final_avatar_position';
                    hiddenInput.name = 'position';
                    hiddenInput.value = pos;
                    document.querySelector('form[action="dashboard.php?tab=technicians"]').appendChild(hiddenInput);
                }
            }

            document.getElementById('techAdmin_id').value = id; 
            document.getElementById('techAdmin_username').value = u; 
            document.getElementById('techAdmin_fullname').value = f; 
            document.getElementById('techAdmin_englishname').value = en;
            document.getElementById('techAdmin_phone').value = p; 
            
            const defaultImg = 'https://api.dicebear.com/7.x/notionists/svg?seed=' + encodeURIComponent(f || 'admin') + '&backgroundColor=e2e8f0';
            document.getElementById('avatarPreviewImg').src = avatarUrl ? avatarUrl : defaultImg;
            document.getElementById('fileNameDisplay').textContent = 'ไม่ได้เลือกไฟล์ใด';
            
            const avatarInput = document.getElementById('techAdmin_avatar');
            if(avatarInput) avatarInput.value = '';

            const pwdInput = document.getElementById('techAdmin_password'); 
            const pwdHint = document.getElementById('pwdHint'); 
            const eyeIcon = document.getElementById('eyeIcon');
            pwdInput.value = ''; pwdInput.type = 'password'; 
            if(eyeIcon) { eyeIcon.classList.remove('fa-eye-slash'); eyeIcon.classList.add('fa-eye'); }
            if(id === '') { if(isManagement) pwdInput.required = true; pwdHint.innerText = "(Required)"; } else { pwdInput.required = false; pwdHint.innerText = "(Leave blank to keep current)"; }
            
            document.getElementById('techAdmin_department_select').name = "department_select"; document.getElementById('techAdmin_department_custom').name = "department_custom";
            setDropdownOrCustom('techAdmin_department_select', 'techAdmin_department_custom', d);
            toggleModal('techAdminModal'); 
        }

        function openEditReporterModal(line_id, real_name, old_phone) {
            document.getElementById('edit_rep_old_name').value = line_id; 
            document.getElementById('edit_rep_new_name').value = real_name; 
            document.getElementById('edit_rep_new_phone').value = old_phone; 
            
            // ให้แสดงชื่อ ID LINE ในช่องที่ไม่ให้แก้
            let displayOldUi = document.getElementById('display_old_name_ui');
            if(displayOldUi) displayOldUi.value = line_id;
            
            toggleModal('editReporterModal');
        }

        // ✨ ประวัติ Modal การคลิกจาก Top Reporters และกราฟ ✨
        function viewHistory(fullName, type) {
            const tbody = document.getElementById('historyTableBody'); 
            tbody.innerHTML = '';

            const userRepairs = allRepairs.filter(r => {
                let nameToCompare = (type === 'reporter') ? r.reporter_name : r.technician_name;
                if (!nameToCompare) return false;
                nameToCompare = nameToCompare.trim();
                return nameToCompare === fullName;
            });

            if(userRepairs.length === 0) {
                let emptyMsg = type === 'reporter' ? 'ยังไม่มีประวัติการแจ้งซ่อม' : 'ยังไม่เคยรับงานซ่อมในระบบ';
                tbody.innerHTML = `<tr><td colspan="11" class="px-6 py-16 text-center text-slate-400 font-medium">${emptyMsg}</td></tr>`;
            } else {
                userRepairs.forEach(r => {
                    let statusClass = 'badge-pending';
                    if(r.status === 'กำลังดำเนินการ') statusClass = 'badge-progress';
                    else if(r.status === 'ซ่อมเสร็จแล้ว') statusClass = 'badge-success';

                    let statusText = formatValJS(r.status);
                    let ticket_no = formatValJS(r.ticket_no);

                    // สร้างรูปแบบวันที่รับแจ้ง (Date / Time)
                    let createdDate = '-';
                    let createdTime = '';
                    if(r.created_at && r.created_at != '0000-00-00 00:00:00') {
                        let parts = r.created_at.split(' ');
                        createdDate = parts[0] || "<span class='text-rose-500 font-bold'>-</span>";
                        createdTime = parts[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${parts[1].substring(0, 5)}</div>` : '';
                    } else {
                        createdDate = "<span class='text-rose-500 font-bold'>-</span>";
                    }
                    
                    // ข้อมูลช่าง
                    let techNameHtml = "<span class='text-rose-500 font-bold'>-</span>";
                    if (r.technician_name && r.technician_name !== '-') {
                        let info = techInfoMap[r.technician_name] || { th: r.technician_name, eng: '', pos: '' };
                        techNameHtml = `<div class='text-indigo-600 font-bold'>${info.th}</div>`;
                        if(info.eng) techNameHtml += `<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>${info.eng}</div>`;
                    }
                    let techName = techNameHtml;

                    // สังกัด/แผนก
                    let dName = r.technician_name && techDeptMap[r.technician_name] ? techDeptMap[r.technician_name] : 'General';
                    let deptEng = "<span class='text-rose-500 font-bold'>-</span>";
                    if (r.technician_name && r.technician_name !== '-') {
                        deptEng = `<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold tracking-wider mb-1 shadow-sm'>${dName}</div>`;
                        let info = techInfoMap[r.technician_name];
                        if (info && info.pos) {
                            deptEng += `<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>${info.pos}</div>`;
                        }
                    }

                    // เวลาเข้างานและเสร็จงาน
                    let has_received = (r.created_at && r.created_at != '0000-00-00 00:00:00');
                    let received_date = has_received ? createdDate : "<span class='text-rose-500 font-bold'>-</span>";
                    let received_time = has_received && r.created_at.split(' ')[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${r.created_at.split(' ')[1].substring(0, 5)}</div>` : '';

                    let has_completed = (r.completed_at && r.completed_at != '0000-00-00 00:00:00');
                    let completed_date = has_completed ? r.completed_at.split(' ')[0] : "<span class='text-rose-500 font-bold'>-</span>";
                    let completed_time = has_completed && r.completed_at.split(' ')[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${r.completed_at.split(' ')[1].substring(0, 5)}</div>` : '';

                    // ผู้แจ้งซ่อม
                    let rNameRaw = r.reporter_name;
                    let dispName = rNameRaw;
                    if (lineUsersMap[rNameRaw] && lineUsersMap[rNameRaw].real_name) {
                        dispName = lineUsersMap[rNameRaw].real_name;
                    }
                    let rName = formatValJS(dispName);
                    let rPhone = formatValJS(r.phone_number);
                    
                    // รายละเอียด
                    let eqType = formatValJS(r.equipment_type);
                    let pDesc = formatValJS(r.problem_desc);
                    let rootCause = !r.root_cause || r.root_cause === '-' ? "<span class='text-rose-500 font-bold'>-</span>" : `<span class='text-slate-700 font-medium'>${r.root_cause}</span>`;

                    let imageIcon = "";
                    if(r.image_path && r.image_path !== '') {
                        imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                    }

                    tbody.innerHTML += `<tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-100 last:border-0">
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class="font-medium text-slate-700">${createdDate}</div>
                            ${createdTime}
                        </td>
                        <td class="px-6 py-4 align-top font-mono font-semibold text-slate-600">${ticket_no}</td>
                        <td class="px-6 py-4 align-top">
                            <div class='flex items-center'>
                                <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 shrink-0'><i class='fas fa-user text-xs text-slate-400'></i></div>
                                <div>
                                    <div class="text-slate-800 font-bold">${rName}</div>
                                    <div class="text-slate-500 text-[11px] font-medium mt-0.5">${rPhone}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="text-slate-800 font-bold">${eqType} ${imageIcon}</div>
                            <div class="text-slate-500 text-[11px] font-medium mt-0.5 max-w-[180px] truncate" title="${pDesc.replace(/<[^>]*>?/gm, '')}">${pDesc}</div>
                        </td>
                        <td class="px-6 py-4 align-top">${deptEng}</td>
                        <td class="px-6 py-4 align-top">${techName}</td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-medium text-slate-700'>${received_date}</div>
                            ${received_time}
                        </td>
                        <td class="px-6 py-4 align-top">${rootCause}</td>
                        <td class="px-6 py-4 align-middle text-center"><span class="${statusClass}">${statusText}</span></td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-medium text-emerald-700'>${completed_date}</div>
                            ${completed_time}
                        </td>
                        <td class="px-6 py-4 align-middle text-center">
                            <div class='flex items-center justify-center'>
                                <a target='_blank' href='update_repair.php?id=${r.id}' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100 shadow-sm' title='Edit'><i class='fas fa-pen-to-square'></i></a>
                            </div>
                        </td>
                    </tr>`;
                });
            }
            
            let displayTitleName = fullName;
            if (type === 'reporter' && lineUsersMap[fullName] && lineUsersMap[fullName].real_name) {
                displayTitleName = lineUsersMap[fullName].real_name;
            }
            document.getElementById('historyModalTitle').innerText = (type === 'technician' ? 'ประวัติงานช่าง: ' : 'ประวัติการแจ้งซ่อม: ') + displayTitleName;
            
            const linkBtn = document.getElementById('historyModalLinkBtn');
            if (linkBtn) {
                // ✨ เช็คว่าตอนนี้กำลังเปิดหน้า Contacts (id="users") อยู่หรือไม่
                const isContactsPage = !document.getElementById('users').classList.contains('hidden');

                if (type === 'reporter' && !isContactsPage) {
                    linkBtn.style.display = ''; // แสดงปุ่มเฉพาะตอนอยู่หน้าอื่น (เช่น หน้า Overview)
                    linkBtn.innerHTML = '<i class="fas fa-address-book md:mr-2"></i> <span class="hidden md:inline">Contacts</span>';
                    linkBtn.onclick = function() { toggleModal('historyModal'); show('users'); };
                } else {
                    linkBtn.style.display = 'none'; // ซ่อนปุ่มถ้าเป็นประวัติช่าง หรือเปิดจากหน้า Contacts อยู่แล้ว
                }
            }
            
            toggleModal('historyModal');
        }

        function confirmUnlink(id) { 
            Swal.fire({ title: 'ยกเลิกการผูกบัญชี?', text: "ช่างจะไม่สามารถรับงานผ่าน LINE ได้จนกว่าจะนำรหัสใหม่ไปผูกบัญชีอีกครั้ง", icon: 'warning', showCancelButton: true, confirmButtonColor: '#f97316', confirmButtonText: 'ยืนยันการยกเลิก', cancelButtonText: 'ปิด' }).then((r) => { 
                if(r.isConfirmed) window.location.href = 'dashboard.php?unlink_tech=' + id; 
            }); 
        }

        function confirmDelete(type, id) { 
            Swal.fire({ title: 'ยืนยันการลบข้อมูล?', text: "เมื่อลบแล้วจะไม่สามารถกู้คืนได้!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ยืนยัน ลบข้อมูล', cancelButtonText: 'ยกเลิก' }).then((r) => { 
                if(r.isConfirmed) {
                    if(type === 'tech') window.location.href = 'dashboard.php?delete_tech=' + id;
                    else if(type === 'user') window.location.href = 'dashboard.php?delete_user=' + id;
                    else if(type === 'asset') window.location.href = 'dashboard.php?delete_asset=' + id;
                }
            }); 
        }

        function confirmDeleteReporter(name) { 
            Swal.fire({ title: 'ยืนยันลบผู้แจ้ง?', text: "ประวัติการแจ้งซ่อมทั้งหมดของบุคคลนี้จะถูกเคลียร์ชื่อออก!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ยืนยัน ลบข้อมูล', cancelButtonText: 'ยกเลิก' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_reporter=' + encodeURIComponent(name); }); 
        }

        let currentSelectedName = 'รวมทุกฝ่ายงาน (ทั้งหมด)';

        function focusReportSearch(e) {
            e.target.value = ''; 
            filterReportDropdown(); 
            toggleReportDropdown(e, true);
        }

        function blurReportSearch(e) {
            setTimeout(() => {
                if (document.getElementById('reportSearchInput').value === '') {
                    e.target.value = currentSelectedName;
                }
            }, 200);
        }

        function toggleReportDropdown(e, forceOpen = false) {
            if(e) e.stopPropagation();
            const list = document.getElementById('reportDropdownList');
            const input = document.getElementById('reportSearchInput'); // ✨ ดึง Input มาใช้งาน
            
            if(forceOpen) {
                list.classList.remove('hidden');
                list.classList.add('flex');
            } else {
                list.classList.toggle('hidden');
                list.classList.toggle('flex');
            }

            // ✨ บังคับ Focus ไปที่ช่อง Input ทันทีเมื่อเปิด Dropdown เพื่อรับคำสั่งจากคีย์บอร์ด
            if (!list.classList.contains('hidden')) {
                if (document.activeElement !== input) {
                    input.focus();
                }
            } else {
                input.blur();
            }
        }

        let currentReportFocus = -1; // ✨ ตัวแปรเก็บตำแหน่งลูกศรสำหรับหน้า Report

        function filterReportDropdown() {
            toggleReportDropdown(null, true);
            const searchVal = document.getElementById('reportSearchInput').value.toLowerCase().replace(/\s+/g, '');
            
            // ✨ รีเซ็ตสีเวลาเริ่มพิมพ์ค้นหาใหม่
            currentReportFocus = -1;
            removeReportActive(document.querySelectorAll('.report-dropdown-item'));

            let deptVisibility = {};
            
            const items = document.querySelectorAll('.report-dropdown-item');
            items.forEach(item => {
                if (item.getAttribute('data-value') === 'all') return;
                
                const searchData = item.getAttribute('data-search') || '';
                const dept = item.getAttribute('data-dept');
                
                if (!deptVisibility[dept]) deptVisibility[dept] = 0;
                
                if(searchData.includes(searchVal)) {
                    item.style.display = '';
                    deptVisibility[dept]++;
                } else {
                    item.style.display = 'none';
                }
            });

            const allItem = document.querySelector('.report-dropdown-item[data-value="all"]');
            if (allItem) {
                const searchData = allItem.getAttribute('data-search') || '';
                if (searchData.includes(searchVal)) {
                    allItem.style.display = '';
                } else {
                    allItem.style.display = 'none';
                }
            }

            const deptHeaders = document.querySelectorAll('.dropdown-dept-header');
            deptHeaders.forEach(header => {
                const dept = header.getAttribute('data-dept');
                if (deptVisibility[dept] > 0) {
                    header.style.display = '';
                } else {
                    header.style.display = 'none';
                }
            });
        }

        function selectReportTech(val, displayText) {
            currentSelectedName = displayText;
            document.getElementById('techFilter').value = val;
            document.getElementById('reportSearchInput').value = displayText;
            document.getElementById('reportDropdownList').classList.add('hidden');
            document.getElementById('reportDropdownList').classList.remove('flex');
            updateExcelLink();
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('reportDropdownContainer');
            if (container && !container.contains(e.target)) {
                const list = document.getElementById('reportDropdownList');
                if(list) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                }
            }

            // ปิด Dropdown ของกราฟทั้งหมดเมื่อคลิกที่อื่น
            document.querySelectorAll('.chart-dropdown-list').forEach(list => {
                if (!list.parentElement.contains(e.target)) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                }
            });
        });

        // ✨ ฟังก์ชันจัดการปุ่มลูกศร ขึ้น-ลง และ Enter ในช่องค้นหา (หน้า Report) ✨
        document.getElementById('reportSearchInput').addEventListener('keydown', function(e) {
            const list = document.getElementById('reportDropdownList');
            
            // ถ้า Dropdown ปิดอยู่ แล้วกดลูกศรลงหรือ Enter ให้เปิด Dropdown
            if (list.classList.contains('hidden')) {
                if (e.key === "ArrowDown" || e.key === "Enter") {
                    toggleReportDropdown(null, true);
                }
                return;
            }

            let items = list.querySelectorAll('.report-dropdown-item');
            let visibleItems = Array.from(items).filter(item => item.style.display !== 'none');

            if (e.key === "ArrowDown") {
                currentReportFocus++;
                addReportActive(visibleItems);
                e.preventDefault(); // ป้องกันไม่ให้ cursor ในช่องพิมพ์ขยับ
            } else if (e.key === "ArrowUp") {
                currentReportFocus--;
                addReportActive(visibleItems);
                e.preventDefault();
            } else if (e.key === "Enter") {
                e.preventDefault();
                if (currentReportFocus > -1) {
                    if (visibleItems[currentReportFocus]) {
                        const val = visibleItems[currentReportFocus].getAttribute('data-value');
                        let disp = 'รวมทุกฝ่ายงาน (ทั้งหมด)';
                        if (val !== 'all') {
                            const span = visibleItems[currentReportFocus].querySelector('span');
                            if(span) disp = span.innerText;
                        }
                        selectReportTech(val, disp);
                    }
                } else if (visibleItems.length === 1) {
                    const val = visibleItems[0].getAttribute('data-value');
                    let disp = 'รวมทุกฝ่ายงาน (ทั้งหมด)';
                    if (val !== 'all') {
                        const span = visibleItems[0].querySelector('span');
                        if(span) disp = span.innerText;
                    }
                    selectReportTech(val, disp);
                }
            }
        });

        function addReportActive(x) {
            if (!x) return false;
            removeReportActive(x);
            if (currentReportFocus >= x.length) currentReportFocus = 0;
            if (currentReportFocus < 0) currentReportFocus = (x.length - 1);
            x[currentReportFocus].classList.add("kb-active-item");
            // เปลี่ยนเป็น auto เพื่อให้ไม่มีแอนิเมชันหน่วงเวลากดปุ่มลูกศรค้าง
            x[currentReportFocus].scrollIntoView({ behavior: 'auto', block: 'nearest' });
        }

        function removeReportActive(x) {
            for (let i = 0; i < x.length; i++) {
                x[i].classList.remove("kb-active-item");
            }
        }

        // ✨ ระบบจัดการ Custom Dropdown ของกราฟทั้งหมด ✨
        let currentChartFocus = -1;
        let activeChartListId = '';

        function toggleChartDropdown(e, idPrefix) {
            if(e) e.stopPropagation();
            const list = document.getElementById(idPrefix + 'List');
            const container = document.getElementById(idPrefix + 'Container');

            // ปิดกล่องอื่นให้หมดก่อนเปิด
            document.querySelectorAll('.chart-dropdown-list').forEach(l => {
                if (l.id !== list.id) { l.classList.add('hidden'); l.classList.remove('flex'); }
            });

            list.classList.toggle('hidden');
            list.classList.toggle('flex');

            if (!list.classList.contains('hidden')) {
                container.focus();
                activeChartListId = list.id;
                currentChartFocus = -1;
                removeChartActive(list.querySelectorAll('.chart-dropdown-item'));
            } else {
                activeChartListId = '';
            }
        }

        function selectChartDropdown(idPrefix, val, display, renderCallback) {
            const list = document.getElementById(idPrefix + 'List');
            const actualInputId = idPrefix.replace('-', ''); // ลบขีดออก เช่น equip-Month -> equipMonth
            document.getElementById(actualInputId).value = val;
            document.getElementById(idPrefix + 'Text').innerText = display;
            list.classList.add('hidden'); list.classList.remove('flex');

            // ล้างแถบสีอันเก่าออกก่อน แต่ต้องเก็บสีของหัวข้อ (data-value="all") ไว้
            list.querySelectorAll('.chart-dropdown-item').forEach(item => {
                // ✨ ป้องกันไม่ให้ลบสีพื้นหลังของแถบหัวข้อ (เดือน/ปี)
                if (item.getAttribute('data-value') === 'all') {
                    item.classList.add('bg-indigo-50', 'text-indigo-600');
                    item.classList.remove('text-slate-700', 'hover:bg-slate-100');
                } else {
                    // รีเซ็ตสีตัวเลือกอื่นๆ ตามปกติ
                    item.classList.remove('bg-indigo-50', 'text-indigo-600');
                    item.classList.add('text-slate-700', 'hover:bg-slate-100', 'hover:text-indigo-600');
                }
            });

            // ไฮไลท์อันที่เพิ่งเลือก (ถ้าไม่ได้เลือกคำว่า เดือน/ปี)
            if (val !== 'all') {
                const selectedItem = list.querySelector(`.chart-dropdown-item[data-value="${val}"]`);
                if (selectedItem) {
                    selectedItem.classList.add('bg-indigo-50', 'text-indigo-600');
                    selectedItem.classList.remove('text-slate-700', 'hover:bg-slate-100');
                }
            }
            
            if (typeof renderCallback === 'function') renderCallback();
        }

        let lastChartKeyTime = 0; // ✨ ตัวแปรหน่วงเวลา
        function handleChartKeydown(e, idPrefix, renderCallback) {
            const list = document.getElementById(idPrefix + 'List');
            if (list.classList.contains('hidden')) {
                if (e.key === "ArrowDown" || e.key === "Enter") toggleChartDropdown(null, idPrefix);
                return;
            }

            // ✨ ป้องกันการวิ่งเร็วเกินไปตอนกดค้าง
            if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                const now = Date.now();
                if (now - lastChartKeyTime < 120) { // 120ms คือกำลังดีครับ
                    e.preventDefault();
                    return; 
                }
                lastChartKeyTime = now;
            }

            let items = list.querySelectorAll('.chart-dropdown-item');
            if (e.key === "ArrowDown") {
                currentChartFocus++; addChartActive(items); e.preventDefault();
            } else if (e.key === "ArrowUp") {
                currentChartFocus--; addChartActive(items); e.preventDefault();
            } else if (e.key === "Enter") {
                e.preventDefault();
                if (currentChartFocus > -1 && items[currentChartFocus]) {
                    items[currentChartFocus].click();
                }
            }
        }

        function addChartActive(x) {
            if (!x) return false;
            removeChartActive(x);
            if (currentChartFocus >= x.length) currentChartFocus = 0;
            if (currentChartFocus < 0) currentChartFocus = (x.length - 1);
            x[currentChartFocus].classList.add("kb-active-item");
            x[currentChartFocus].scrollIntoView({ behavior: 'auto', block: 'nearest' });
        }

        function removeChartActive(x) {
            for (let i = 0; i < x.length; i++) {
                x[i].classList.remove("kb-active-item");
            }
        }
    </script>
</body>
</html>