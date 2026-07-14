<?php 
include 'db_connect.php'; 

// ================= 1. ปรับปรุงฐานข้อมูลอัตโนมัติ (Auto-Fix DB) =================
$conn->query("CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ใช้งานปกติ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) DEFAULT 'User'");
$check_fullname = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if($check_fullname->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER username");

$check_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if($check_phone->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER full_name");

$check_dept = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if($check_dept->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER phone");

$check_created = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if($check_created->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// ระบบ Auto-Sync
$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs->num_rows > 0) {
    $conn->query("INSERT INTO users (username, full_name, phone, department, role) 
                  SELECT CONCAT('U', REPLACE(phone_number, '-', '')), reporter_name, phone_number, 'บุคลากรทั่วไป', 'User' 
                  FROM repairs 
                  WHERE reporter_name NOT IN (SELECT full_name FROM users) 
                  GROUP BY reporter_name, phone_number");
}

// ================= 2. จัดการข้อมูลอุปกรณ์ (Assets) =================
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

// ================= 3. จัดการทีมงานระบบ (Admin & Tech) =================
if (isset($_GET['delete_user'])) {
    $del_id = intval($_GET['delete_user']);
    $conn->query("DELETE FROM users WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $password = "1234";
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'] ?? '-';
    
    $department = $_POST['department'];
    if ($department === 'อื่นๆ' && !empty($_POST['department_custom'])) {
        $department = $_POST['department_custom'];
    }

    $role = $_POST['role']; 
    $tab_redirect = ($role == 'User') ? 'users' : 'technicians';

    if (empty($user_id)) {
        $check_pwd = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
        if($check_pwd->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, phone, department, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $password, $full_name, $phone, $department, $role);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, phone, department, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $full_name, $phone, $department, $role);
        }
        $msg = 'บันทึกข้อมูลสำเร็จ!';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, phone=?, department=?, role=? WHERE id=?");
        $stmt->bind_param("sssssi", $username, $full_name, $phone, $department, $role, $user_id);
        $msg = 'อัปเดตข้อมูลสำเร็จ!';
    }
    if ($stmt->execute()) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: '$msg', confirmButtonColor: '#0284c7' }).then(() => { window.location.href='dashboard.php?tab=$tab_redirect'; }); });</script>";
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
    
    $stmt = $conn->prepare("UPDATE repairs SET reporter_name = ?, phone_number = ? WHERE reporter_name = ?");
    $stmt->bind_param("sss", $new_name, $new_phone, $old_name);
    $stmt->execute();
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อัปเดตข้อมูลผู้แจ้งสำเร็จ!', confirmButtonColor: '#0284c7' }).then(() => { window.location.href='dashboard.php?tab=users'; }); });</script>";
}

// ================= 4. เตรียมข้อมูลประวัติและกราฟ =================
$all_repairs_json = "[]";
$status_data_json = json_encode(['รอรับเรื่อง'=>0, 'กำลังดำเนินการ'=>0, 'ซ่อมเสร็จแล้ว'=>0]);
$equip_labels_json = "[]";
$equip_counts_json = "[]";

if($check_repairs->num_rows > 0) {
    $rep_res = $conn->query("SELECT ticket_no, equipment_type, status, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at_fmt, reporter_name FROM repairs ORDER BY created_at DESC");
    $reps = [];
    while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
    $all_repairs_json = json_encode($reps);

    $stat_res = $conn->query("SELECT status, COUNT(*) as cnt FROM repairs GROUP BY status");
    $status_counts = ['รอรับเรื่อง'=>0, 'กำลังดำเนินการ'=>0, 'ซ่อมเสร็จแล้ว'=>0];
    while($st = $stat_res->fetch_assoc()){ 
        if(isset($status_counts[$st['status']])) {
            $status_counts[$st['status']] = $st['cnt']; 
        }
    }
    $status_data_json = json_encode(array_values($status_counts));

    $eq_res = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 5");
    $eq_labels = []; $eq_counts = [];
    while($eq = $eq_res->fetch_assoc()){ 
        $eq_labels[] = $eq['equipment_type']; 
        $eq_counts[] = $eq['cnt']; 
    }
    $equip_labels_json = json_encode($eq_labels);
    $equip_counts_json = json_encode($eq_counts);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSU Smart Maintenance Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .nav-btn { width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; margin-bottom: 0.25rem; border-radius: 0.75rem; color: #64748b; font-weight: 500; transition: all 0.2s; }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.25rem; margin-right: 0.75rem; color: #94a3b8; transition: all 0.2s; }
        .nav-btn:hover { background-color: #f8fafc; color: #0284c7; }
        .nav-btn:hover i { color: #0ea5e9; transform: scale(1.1); }
        .active-btn { background-color: #f0f9ff; color: #0369a1; font-weight: 600; box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1); border: 1px solid #bae6fd; }
        .active-btn i { color: #0284c7; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        
        @media print {
            aside, header, .no-print { display: none !important; }
            main { padding: 0 !important; margin: 0 !important; background: white; }
            .modern-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
            body { background: white; }
            #reports { display: block !important; }
            .print-header { display: flex !important; }
            .chart-container { height: 350px !important; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-200">

    <!-- Sidebar -->
    <aside class="w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 z-20 shadow-[4px_0_24px_rgba(0,0,0,0.02)] no-print">
        <div class="h-24 flex items-center px-8 border-b border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center shadow-lg shadow-sky-500/30 mr-4 shrink-0">
                <i class="fas fa-tools text-white text-xl"></i>
            </div>
            <div class="overflow-hidden flex-1">
                <h1 class="text-xl font-bold text-slate-800 leading-tight tracking-tight">MSU REPAIR</h1>
                <p class="text-xs text-sky-500 font-semibold tracking-widest uppercase mt-0.5">Admin Portal</p>
            </div>
        </div>
        
        <nav class="flex-1 px-5 py-8 flex flex-col overflow-y-auto">
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">ระบบจัดการหลัก</p>
            <button onclick="show('dash')" class="nav-btn active-btn" id="btn-dash"><i class="fas fa-chart-pie"></i> ภาพรวมระบบ</button>
            <button onclick="show('repairs')" class="nav-btn" id="btn-repairs"><i class="fas fa-layer-group"></i> รายการแจ้งซ่อม</button>
            <button onclick="show('technicians')" class="nav-btn" id="btn-technicians"><i class="fas fa-user-shield"></i> ทีมงานระบบ</button>
            
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 mt-8">การตั้งค่าและรายงาน</p>
            <button onclick="show('assets')" class="nav-btn" id="btn-assets"><i class="fas fa-server"></i> จัดการอุปกรณ์</button>
            <button onclick="show('users')" class="nav-btn" id="btn-users"><i class="fas fa-users"></i> ประวัติผู้แจ้งซ่อม</button>
            <button onclick="show('reports')" class="nav-btn" id="btn-reports"><i class="fas fa-file-invoice"></i> รายงานสรุป</button>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-sky-100/50 to-transparent -z-10 no-print"></div>
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 sticky top-0 no-print">
            <h2 class="text-2xl font-bold text-slate-800 tracking-wide" id="headerTitle">ภาพรวมระบบ (Dashboard)</h2>
            <div class="flex items-center space-x-6">
                <div class="relative hidden md:block">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" placeholder="ค้นหาข้อมูล..." class="bg-white border border-slate-200 text-sm rounded-full pl-11 pr-5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all w-72 shadow-sm">
                </div>
                <div class="flex items-center space-x-3 cursor-pointer p-1.5 pr-4 rounded-full border border-slate-200 bg-white hover:bg-slate-50 transition-all shadow-sm">
                    <div class="w-9 h-9 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold"><i class="fas fa-user text-sm"></i></div>
                    <div class="hidden sm:block text-left"><span class="block text-sm font-semibold text-slate-700 leading-none mb-1">Administrator</span><span class="block text-[11px] text-slate-500 uppercase tracking-wide leading-none">ผู้ดูแลระบบ</span></div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-10 print:p-0">
            
            <!-- Dashboard Stats -->
            <div id="dash" class="section space-y-8 animate-fade-in no-print">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                    if($check_repairs->num_rows > 0) {
                        $stats = [
                            ["งานทั้งหมด", "repairs", "fa-briefcase", "text-blue-600", "bg-blue-100", "border-blue-200"], 
                            ["รอรับเรื่อง", "status='รอรับเรื่อง'", "fa-clock", "text-amber-500", "bg-amber-100", "border-amber-200"], 
                            ["กำลังดำเนินการ", "status='กำลังดำเนินการ'", "fa-tools", "text-sky-500", "bg-sky-100", "border-sky-200"], 
                            ["ซ่อมเสร็จแล้ว", "status='ซ่อมเสร็จแล้ว'", "fa-check-circle", "text-emerald-500", "bg-emerald-100", "border-emerald-200"]
                        ];
                        foreach($stats as $s) {
                            $c = $conn->query("SELECT count(*) as c FROM repairs ".($s[1] != "repairs" ? "WHERE {$s[1]}" : ""))->fetch_assoc()['c'];
                            echo "<div class='modern-card p-6 border-b-4 {$s[5]}'><div class='flex justify-between items-start'><div><p class='text-slate-500 text-sm font-medium mb-2'>{$s[0]}</p><h3 class='text-4xl font-extrabold text-slate-800'>{$c}</h3></div><div class='w-14 h-14 rounded-2xl {$s[4]} flex items-center justify-center {$s[3]} shadow-sm'><i class='fas {$s[2]} text-2xl'></i></div></div></div>";
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Repairs List -->
            <div id="repairs" class="section hidden space-y-6 no-print">
                <div class="modern-card overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white">
                        <h2 class="text-xl font-bold text-slate-800">รายการแจ้งซ่อมทั้งหมด</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">วัน/เวลาที่แจ้ง</th>
                                    <th class="px-6 py-4">เลขที่ใบงาน</th>
                                    <th class="px-6 py-4">ข้อมูลผู้แจ้ง</th>
                                    <th class="px-6 py-4">สถานที่</th>
                                    <th class="px-6 py-4">อุปกรณ์ / อาการเสีย</th>
                                    <th class="px-6 py-4 text-center">สถานะ</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                if($check_repairs->num_rows > 0) {
                                    $res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
                                    if($res->num_rows > 0){
                                        while($row = $res->fetch_assoc()) {
                                            $date = !empty($row['created_at']) ? date("d/m/Y H:i", strtotime($row['created_at'])) : "-";
                                            $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                            if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                            elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                            elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                                <td class='px-6 py-4 font-bold text-sky-600'>{$row['ticket_no']}</td>
                                                <td class='px-6 py-4'><div class='text-slate-800 font-semibold'>{$row['reporter_name']}</div><div class='text-slate-500 text-xs mt-1'><i class='fas fa-phone-alt mr-1 text-slate-400'></i> {$row['phone_number']}</div></td>
                                                <td class='px-6 py-4 text-slate-600 font-medium'>{$row['location']}</td>
                                                <td class='px-6 py-4'><div class='text-slate-800 font-semibold'>{$row['equipment_type']}</div><div class='text-slate-500 text-xs mt-1 max-w-[200px] truncate' title='{$row['problem_desc']}'>{$row['problem_desc']}</div></td>
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$statusClass}'>{$row['status']}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <a href='update_repair.php?id={$row['id']}' class='w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all flex items-center justify-center border border-emerald-100 shadow-sm'><i class='fas fa-clipboard-check'></i></a>
                                                        <a href='view_repair.php?id={$row['id']}' class='w-9 h-9 rounded-xl bg-slate-50 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center border border-slate-200 shadow-sm'><i class='fas fa-eye'></i></a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='7' class='px-6 py-16 text-center text-slate-400 font-medium'>ยังไม่มีข้อมูลการแจ้งซ่อมในระบบ</td></tr>"; }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Technician & Admin Section (ทีมงานระบบ) -->
            <div id="technicians" class="section hidden space-y-6 no-print">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">ทีมงานระบบ</h2>
                        <p class="text-sm text-slate-500 mt-1">จัดการรายชื่อผู้ดูแลระบบและช่างซ่อม</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="openTechAdminModal('Admin')" class="bg-purple-600 hover:bg-purple-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center"><i class="fas fa-user-shield mr-2"></i> เพิ่มแอดมิน</button>
                        <button onclick="openTechAdminModal('Technician')" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center"><i class="fas fa-hard-hat mr-2"></i> เพิ่มช่างซ่อม</button>
                    </div>
                </div>

                <!-- ตาราง Admin -->
                <div>
                    <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center"><i class="fas fa-user-shield text-purple-500 mr-2 text-xl"></i> ผู้ดูแลระบบ (Admin)</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">Username</th>
                                        <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                                        <th class="px-6 py-4">เบอร์โทรศัพท์</th>
                                        <th class="px-6 py-4">แผนก/ความเชี่ยวชาญ</th>
                                        <th class="px-6 py-4 text-center">สิทธิ์</th>
                                        <th class="px-6 py-4 text-right">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $admin_res = $conn->query("SELECT * FROM users WHERE role IN ('Admin', 'admin', 'executive') ORDER BY created_at DESC");
                                    if($admin_res && $admin_res->num_rows > 0){
                                        while($u = $admin_res->fetch_assoc()) {
                                            $js_uid = $u['id']; $js_uname = htmlspecialchars($u['username'], ENT_QUOTES); $js_fname = htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES); $js_phone = htmlspecialchars($u['phone'] ?? '', ENT_QUOTES); $js_dept = htmlspecialchars($u['department'] ?? '', ENT_QUOTES);

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-slate-700'>{$u['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full flex items-center justify-center mr-3 border bg-purple-50 text-purple-600 border-purple-100'><i class='fas fa-user-shield text-xs'></i></div>
                                                        ".($u['full_name'] ?: 'ไม่ระบุ')."
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600'>".($u['phone'] ?: '-')."</td>
                                                <td class='px-6 py-4 text-slate-600'>".($u['department'] ?: '-')."</td>
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-purple-50 text-purple-600 border-purple-200'>Admin</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"openTechAdminModal('Admin', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$u['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='6' class='px-6 py-8 text-center text-slate-400'>ยังไม่มีข้อมูลแอดมิน</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ตาราง Technician -->
                <div>
                    <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center"><i class="fas fa-hard-hat text-sky-500 mr-2 text-xl"></i> ช่างซ่อม (Technician)</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">Username</th>
                                        <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                                        <th class="px-6 py-4">เบอร์โทรศัพท์</th>
                                        <th class="px-6 py-4">แผนก/ความเชี่ยวชาญ</th>
                                        <th class="px-6 py-4 text-center">สิทธิ์</th>
                                        <th class="px-6 py-4 text-right">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $tech_res = $conn->query("SELECT * FROM users WHERE role = 'Technician' ORDER BY created_at DESC");
                                    if($tech_res && $tech_res->num_rows > 0){
                                        while($t = $tech_res->fetch_assoc()) {
                                            $js_uid = $t['id']; $js_uname = htmlspecialchars($t['username'], ENT_QUOTES); $js_fname = htmlspecialchars($t['full_name'] ?? '', ENT_QUOTES); $js_phone = htmlspecialchars($t['phone'] ?? '', ENT_QUOTES); $js_dept = htmlspecialchars($t['department'] ?? '', ENT_QUOTES);

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-slate-700'>{$t['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full flex items-center justify-center mr-3 border bg-sky-50 text-sky-600 border-sky-100'><i class='fas fa-hard-hat text-xs'></i></div>
                                                        ".($t['full_name'] ?: 'ไม่ระบุ')."
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600'>".($t['phone'] ?: '-')."</td>
                                                <td class='px-6 py-4 text-slate-600'>".($t['department'] ?: '-')."</td>
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-sky-50 text-sky-600 border-sky-200'>Technician</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"openTechAdminModal('Technician', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$t['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='6' class='px-6 py-8 text-center text-slate-400'>ยังไม่มีข้อมูลช่างซ่อม</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Management -->
            <div id="assets" class="section hidden space-y-6 no-print">
                <div class="modern-card overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white">
                        <h2 class="text-xl font-bold text-slate-800">ฐานข้อมูลอุปกรณ์</h2>
                        <button onclick="openAddAssetModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center"><i class="fas fa-plus mr-2"></i> เพิ่มอุปกรณ์ใหม่</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">รหัส</th>
                                    <th class="px-6 py-4">ชื่ออุปกรณ์</th>
                                    <th class="px-6 py-4">หมวดหมู่</th>
                                    <th class="px-6 py-4 text-center">สถานะ</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $asset_res = $conn->query("SELECT * FROM assets ORDER BY created_at DESC");
                                if($asset_res->num_rows > 0){
                                    while($a = $asset_res->fetch_assoc()) {
                                        $a_statusClass = ($a['status'] == 'ใช้งานปกติ') ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200';
                                        $js_id = $a['id']; $js_code = htmlspecialchars($a['asset_code'], ENT_QUOTES); $js_name = htmlspecialchars($a['asset_name'], ENT_QUOTES); $js_cat = htmlspecialchars($a['category'], ENT_QUOTES); $js_status = htmlspecialchars($a['status'], ENT_QUOTES);

                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 font-bold text-indigo-600'>{$a['asset_code']}</td>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>{$a['asset_name']}</td>
                                            <td class='px-6 py-4 text-slate-600'><span class='bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-medium border border-slate-200'>{$a['category']}</span></td>
                                            <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$a_statusClass}'>{$a['status']}</span></td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"openEditAssetModal('$js_id', '$js_code', '$js_name', '$js_cat', '$js_status')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDelete('asset', {$a['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Section (ประวัติผู้แจ้งซ่อม ที่จัดการได้) -->
            <div id="users" class="section hidden space-y-6 no-print">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-transparent mb-2">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">ประวัติผู้แจ้งซ่อม</h2>
                        <p class="text-sm text-slate-500 mt-1">รายชื่อบุคลากรและประวัติการแจ้งซ่อมทั้งหมด</p>
                    </div>
                </div>

                <div class="modern-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">ชื่อ-นามสกุล (ผู้แจ้ง)</th>
                                    <th class="px-6 py-4">เบอร์โทรศัพท์</th>
                                    <th class="px-6 py-4 text-center">จำนวนที่แจ้งซ่อม</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $reporter_res = $conn->query("SELECT reporter_name, MAX(phone_number) as phone_number, COUNT(id) as total_repairs FROM repairs WHERE reporter_name IS NOT NULL AND reporter_name != '' GROUP BY reporter_name ORDER BY MAX(created_at) DESC");
                                
                                if($reporter_res && $reporter_res->num_rows > 0){
                                    while($r = $reporter_res->fetch_assoc()) {
                                        $js_old_name = htmlspecialchars($r['reporter_name'], ENT_QUOTES);
                                        $js_old_phone = htmlspecialchars($r['phone_number'], ENT_QUOTES);
                                        
                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                <div class='flex items-center'>
                                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3 border border-slate-200'><i class='fas fa-user text-xs'></i></div>
                                                    {$r['reporter_name']}
                                                </div>
                                            </td>
                                            <td class='px-6 py-4'>
                                                <div class='text-slate-700 font-medium'><i class='fas fa-phone-alt text-slate-400 mr-1.5'></i> ".($r['phone_number'] ? $r['phone_number'] : '-')."</div>
                                            </td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-sky-50 text-sky-600 border-sky-200'>{$r['total_repairs']} งาน</span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"viewHistory('{$js_old_name}')\" class='w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-500 hover:text-white transition-all flex items-center justify-center border border-sky-100 shadow-sm' title='ดูประวัติการซ่อม'><i class='fas fa-eye'></i></button>
                                                    <button onclick=\"openEditReporterModal('{$js_old_name}', '{$js_old_phone}')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm' title='แก้ไขข้อมูล'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDeleteReporter('{$js_old_name}')\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm' title='ลบประวัติ'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='4' class='px-6 py-12 text-center text-slate-400'>ยังไม่มีประวัติการแจ้งซ่อม</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Report Summary Section (รายงานสรุปแบบสากล) -->
            <div id="reports" class="section hidden space-y-6">
                <!-- Header สำหรับการ Print -->
                <div class="hidden print-header print:flex items-center gap-4 mb-8 pb-6 border-b border-slate-200">
                    <div class="w-16 h-16 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-tools text-white text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-extrabold text-slate-800">รายงานสรุปผลการปฏิบัติงาน</h1>
                        <p class="text-slate-500 font-medium">MSU Smart Maintenance Hub • พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?></p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-transparent mb-2 no-print">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">รายงานสรุปผลการปฏิบัติงาน (Report Summary)</h2>
                        <p class="text-sm text-slate-500 mt-1">สถิติและภาพรวมของการแจ้งซ่อมทั้งหมดในระบบ</p>
                    </div>
                    <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-sky-600 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm flex items-center transition-colors">
                        <i class="fas fa-print mr-2"></i> พิมพ์รายงาน
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 print:grid-cols-2 print:gap-8">
                    <!-- กราฟที่ 1: สถานะการแจ้งซ่อม (Doughnut Chart) -->
                    <div class="modern-card p-6 bg-white flex flex-col h-[400px] print:h-[400px] print:shadow-none print:border">
                        <h3 class="font-bold text-slate-800 mb-2 flex items-center"><i class="fas fa-chart-pie text-sky-500 mr-2"></i> สัดส่วนสถานะงานซ่อม</h3>
                        <p class="text-xs text-slate-500 mb-4">แสดงเปอร์เซ็นต์ของงานที่รอรับเรื่อง, กำลังดำเนินการ, และเสร็จสิ้น</p>
                        <div class="flex-1 relative w-full h-full flex justify-center pb-4 chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <!-- กราฟที่ 2: อุปกรณ์ที่เสียบ่อยสุด (Bar Chart) -->
                    <div class="modern-card p-6 bg-white flex flex-col h-[400px] print:h-[400px] print:shadow-none print:border">
                        <h3 class="font-bold text-slate-800 mb-2 flex items-center"><i class="fas fa-chart-bar text-indigo-500 mr-2"></i> อุปกรณ์ที่พบปัญหามากที่สุด (Top 5)</h3>
                        <p class="text-xs text-slate-500 mb-4">แสดงประเภทครุภัณฑ์หรืออุปกรณ์ที่มีสถิติการแจ้งซ่อมสูงสุด</p>
                        <div class="flex-1 relative w-full h-full pb-4 chart-container">
                            <canvas id="equipChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ================== MODALS ================== -->

    <!-- Modal เพิ่ม/แก้ไข อุปกรณ์ -->
    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-lg font-bold text-slate-800" id="assetModalTitle">เพิ่มอุปกรณ์ใหม่</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="save_asset" value="1"><input type="hidden" name="asset_id" id="asset_id" value="">
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">รหัสครุภัณฑ์ <span class="text-red-500">*</span></label><input type="text" name="asset_code" id="asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่ออุปกรณ์ <span class="text-red-500">*</span></label><input type="text" name="asset_name" id="asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label><select name="category" id="asset_category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"><option value="IT Support">IT Support</option><option value="ไฟฟ้า/แอร์">ไฟฟ้า/แอร์</option><option value="อาคารสถานที่">อาคารสถานที่</option><option value="อื่นๆ">อื่นๆ</option></select></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">สถานะ</label><select name="status" id="asset_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"><option value="ใช้งานปกติ">ใช้งานปกติ</option><option value="ชำรุด/ส่งซ่อม">ชำรุด/ส่งซ่อม</option><option value="แทงจำหน่าย">แทงจำหน่าย</option></select></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('assetModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-500 shadow-md">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไข ทีมงาน (Admin & Tech) -->
    <div id="techAdminModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('techAdminModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-lg font-bold text-slate-800" id="techAdminModalTitle">เพิ่มทีมงานระบบ</p>
                <button onclick="toggleModal('techAdminModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="save_user" value="1"><input type="hidden" name="user_id" id="techAdmin_id" value=""><input type="hidden" name="role" id="techAdmin_role" value="">
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">Username / รหัสประจำตัว <span class="text-red-500">*</span></label><input type="text" name="username" id="techAdmin_username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label><input type="text" name="full_name" id="techAdmin_fullname" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์</label><input type="text" name="phone" id="techAdmin_phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">แผนก / ความเชี่ยวชาญ <span class="text-red-500">*</span></label>
                        <select name="department_select" id="techAdmin_department_select" onchange="toggleCustomDept(this, 'techAdmin_department_custom')" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 mb-2">
                            <option value="" disabled selected>-- เลือกแผนก --</option>
                            <option value="แผนกช่าง">แผนกช่าง</option>
                            <option value="แผนกไฟฟ้า">แผนกไฟฟ้า</option>
                            <option value="แผนกโสต">แผนกโสต</option>
                            <option value="แม่บ้าน">แม่บ้าน</option>
                            <option value="อื่นๆ">อื่นๆ (พิมพ์เอง)</option>
                        </select>
                        <input type="text" name="department_custom" id="techAdmin_department_custom" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 hidden" placeholder="ระบุแผนก/ความเชี่ยวชาญ">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('techAdminModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-sky-600 text-white rounded-xl text-sm font-bold hover:bg-sky-500 shadow-md">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal แก้ไขข้อมูลผู้แจ้งซ่อม (เฉพาะอัปเดตชื่อใน repairs) -->
    <div id="editReporterModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('editReporterModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-lg font-bold text-slate-800"><i class="fas fa-user-edit text-amber-500 mr-2"></i> แก้ไขข้อมูลผู้แจ้งซ่อม</p>
                <button onclick="toggleModal('editReporterModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="edit_reporter" value="1">
                <input type="hidden" name="old_name" id="edit_rep_old_name" value="">
                <div class="bg-blue-50 border border-blue-100 text-blue-700 text-xs p-3 rounded-xl mb-4">
                    <i class="fas fa-info-circle mr-1"></i> ข้อมูลจะถูกอัปเดตไปยังประวัติการแจ้งซ่อมที่ผ่านมาทั้งหมดของบุคคลนี้
                </div>
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label><input type="text" name="new_name" id="edit_rep_new_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label><input type="text" name="new_phone" id="edit_rep_new_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('editReporterModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-400 shadow-md">อัปเดตข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal ดูประวัติการแจ้งซ่อม (👁️) -->
    <div id="historyModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('historyModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-3xl mx-auto rounded-2xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                <p class="text-lg font-bold text-slate-800" id="historyModalTitle">ประวัติการแจ้งซ่อม</p>
                <button onclick="toggleModal('historyModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50">
                <table class="w-full text-left whitespace-nowrap border border-slate-200 bg-white rounded-xl overflow-hidden shadow-sm">
                    <thead class="bg-slate-100 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider font-bold">
                        <tr>
                            <th class="px-4 py-3">เลขที่ใบงาน</th>
                            <th class="px-4 py-3">อุปกรณ์ / อาการ</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <th class="px-4 py-3">วันที่แจ้ง</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100" id="historyTableBody">
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-white shrink-0 flex justify-end">
                <button onclick="toggleModal('historyModal')" class="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-700 shadow-md">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ================== JAVASCRIPT ================== -->
    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;
        const statusDataArray = <?php echo $status_data_json; ?>;
        const equipLabels = <?php echo $equip_labels_json; ?>;
        const equipCounts = <?php echo $equip_counts_json; ?>;

        const pageTitles = {
            'dash': 'ภาพรวมระบบ (Dashboard)',
            'repairs': 'ตรวจสอบงานแจ้งซ่อมทั้งหมด',
            'technicians': 'ทีมงานระบบ (แอดมิน & ช่าง)',
            'assets': 'ฐานข้อมูลอุปกรณ์และครุภัณฑ์',
            'users': 'ประวัติผู้แจ้งซ่อม (รายบุคคล)',
            'reports': 'รายงานสรุปผลการปฏิบัติงาน'
        };
        
        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'ระบบจัดการ';
            
            if(id === 'reports' && !window.chartsRendered) {
                renderCharts();
                window.chartsRendered = true;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if(tab) { show(tab); } else { show('dash'); }
            window.chartsRendered = false;
        });

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        function toggleCustomDept(selectElement, customInputId) {
            const customInput = document.getElementById(customInputId);
            if(selectElement.value === 'อื่นๆ') {
                customInput.classList.remove('hidden');
                customInput.required = true;
            } else {
                customInput.classList.add('hidden');
                customInput.required = false;
            }
        }

        function setDropdownOrCustom(selectId, customInputId, val) {
            const selectEl = document.getElementById(selectId);
            const customEl = document.getElementById(customInputId);
            
            if (!val || val === '-') {
                selectEl.value = '';
                customEl.classList.add('hidden');
                customEl.value = '';
                customEl.required = false;
                return;
            }

            const options = Array.from(selectEl.options).map(opt => opt.value);
            if (options.includes(val) && val !== 'อื่นๆ') {
                selectEl.value = val;
                customEl.classList.add('hidden');
                customEl.value = '';
                customEl.required = false;
            } else {
                selectEl.value = 'อื่นๆ';
                customEl.classList.remove('hidden');
                customEl.value = val;
                customEl.required = true;
            }
        }

        function openAddAssetModal() { 
            document.getElementById('assetModalTitle').innerHTML = '<i class="fas fa-plus-circle text-sky-500 mr-2"></i> เพิ่มอุปกรณ์ใหม่'; 
            document.getElementById('asset_id').value = ''; document.getElementById('asset_code').value = ''; document.getElementById('asset_name').value = ''; document.getElementById('asset_category').value = 'IT Support'; document.getElementById('asset_status').value = 'ใช้งานปกติ'; toggleModal('assetModal'); 
        }

        function openEditAssetModal(id, c, n, cat, s) { 
            document.getElementById('assetModalTitle').innerHTML = '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขอุปกรณ์'; 
            document.getElementById('asset_id').value = id; document.getElementById('asset_code').value = c; document.getElementById('asset_name').value = n; document.getElementById('asset_category').value = cat; document.getElementById('asset_status').value = s; toggleModal('assetModal'); 
        }

        function openTechAdminModal(role, id='', u='', f='', p='', d='') { 
            let title = role === 'Admin' ? '<i class="fas fa-user-shield text-purple-500 mr-2"></i> เพิ่มผู้ดูแลระบบ' : '<i class="fas fa-hard-hat text-sky-500 mr-2"></i> เพิ่มช่างซ่อม';
            if(id !== '') title = role === 'Admin' ? '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขแอดมิน' : '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขช่างซ่อม';
            
            document.getElementById('techAdminModalTitle').innerHTML = title; 
            document.getElementById('techAdmin_role').value = role;
            document.getElementById('techAdmin_id').value = id; 
            document.getElementById('techAdmin_username').value = u; 
            document.getElementById('techAdmin_fullname').value = f; 
            document.getElementById('techAdmin_phone').value = p; 
            
            document.getElementById('techAdmin_department_select').name = "department";
            document.getElementById('techAdmin_department_custom').name = "department_custom";
            setDropdownOrCustom('techAdmin_department_select', 'techAdmin_department_custom', d);
            
            toggleModal('techAdminModal'); 
        }

        function openEditReporterModal(old_name, old_phone) {
            document.getElementById('edit_rep_old_name').value = old_name;
            document.getElementById('edit_rep_new_name').value = old_name;
            document.getElementById('edit_rep_new_phone').value = old_phone;
            toggleModal('editReporterModal');
        }

        function viewHistory(fullName) {
            const tbody = document.getElementById('historyTableBody');
            tbody.innerHTML = '';
            const userRepairs = allRepairs.filter(r => r.reporter_name === fullName);
            
            if(userRepairs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">ไม่พบประวัติการแจ้งซ่อมในระบบ</td></tr>';
            } else {
                userRepairs.forEach(r => {
                    let statusClass = 'bg-slate-100 text-slate-600';
                    if(r.status === 'รอรับเรื่อง') statusClass = 'bg-amber-50 text-amber-600';
                    else if(r.status === 'กำลังดำเนินการ') statusClass = 'bg-sky-50 text-sky-600';
                    else if(r.status === 'ซ่อมเสร็จแล้ว') statusClass = 'bg-emerald-50 text-emerald-600';
                    
                    tbody.innerHTML += `<tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-bold text-sky-600">${r.ticket_no}</td>
                        <td class="px-4 py-3 text-slate-700 font-medium">${r.equipment_type}</td>
                        <td class="px-4 py-3 text-center"><span class="px-3 py-1 rounded-full text-[10px] font-bold ${statusClass}">${r.status}</span></td>
                        <td class="px-4 py-3 text-slate-500">${r.created_at_fmt}</td>
                    </tr>`;
                });
            }
            document.getElementById('historyModalTitle').innerHTML = '<i class="fas fa-history text-sky-500 mr-2"></i> ประวัติการซ่อมของ: <span class="text-sky-700 ml-1">' + fullName + '</span>';
            toggleModal('historyModal');
        }

        function confirmDelete(type, id) { 
            Swal.fire({ title: 'ยืนยัน?', text: "ลบแล้วไม่สามารถกู้คืนได้!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบเลย!' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_'+type+'=' + id; }); 
        }

        function confirmDeleteReporter(name) { 
            Swal.fire({ title: 'ลบประวัติบุคคลนี้?', text: "จะทำให้ชื่อผู้แจ้งซ่อมในประวัติที่ผ่านมาถูกลบทั้งหมด!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ยืนยันลบ' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_reporter=' + encodeURIComponent(name); }); 
        }

        function renderCharts() {
            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                    datasets: [{ data: statusDataArray, backgroundColor: ['#f59e0b', '#0ea5e9', '#10b981'], borderWidth: 0, hoverOffset: 4 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: "'Kanit', sans-serif" } } } }, cutout: '70%' }
            });

            const ctxEquip = document.getElementById('equipChart').getContext('2d');
            new Chart(ctxEquip, {
                type: 'bar',
                data: {
                    labels: equipLabels,
                    datasets: [{ label: 'จำนวนครั้งที่แจ้งซ่อม', data: equipCounts, backgroundColor: '#6366f1', borderRadius: 6 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Kanit', sans-serif" } }, grid: { borderDash: [4, 4] } }, x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false } } } }
            });
        }
    </script>
</body>
</html>