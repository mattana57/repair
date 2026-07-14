<?php 
include 'db_connect.php'; 

// 1. สร้างตาราง assets อัตโนมัติ
$conn->query("CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ใช้งานปกติ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. สร้างตาราง users อัตโนมัติ 
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 3. อัปเดตตาราง users ให้รองรับเบอร์โทรศัพท์ (ถ้ายังไม่มี)
$check_phone_col = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if($check_phone_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER full_name");
}

// 4. ระบบ Auto-Sync: ดึงคนเคยแจ้งซ่อมที่ยังไม่มีชื่อ เข้าสู่ตารางบุคลากรอัตโนมัติ
$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs->num_rows > 0) {
    $conn->query("INSERT INTO users (username, full_name, phone, department, role) 
                  SELECT CONCAT('U', REPLACE(phone_number, '-', '')), reporter_name, phone_number, 'บุคลากรทั่วไป', 'User' 
                  FROM repairs 
                  WHERE reporter_name NOT IN (SELECT full_name FROM users) 
                  GROUP BY reporter_name, phone_number");
}

// ================= จัดการข้อมูล =================
if (isset($_GET['delete_asset'])) {
    $del_id = intval($_GET['delete_asset']);
    $conn->query("DELETE FROM assets WHERE id = $del_id");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ!', showConfirmButton: false, timer: 1500 }).then(() => { window.location.href='dashboard.php?tab=assets'; }); });</script>";
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
        $msg = 'เพิ่มอุปกรณ์สำเร็จ!';
    } else {
        $stmt = $conn->prepare("UPDATE assets SET asset_code=?, asset_name=?, category=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $asset_code, $asset_name, $category, $status, $asset_id);
        $msg = 'อัปเดตข้อมูลสำเร็จ!';
    }
    if ($stmt->execute()) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: '$msg', confirmButtonColor: '#0284c7' }).then(() => { window.location.href='dashboard.php?tab=assets'; }); });</script>";
    }
}

if (isset($_GET['delete_user'])) {
    $del_id = intval($_GET['delete_user']);
    $role_res = $conn->query("SELECT role FROM users WHERE id = $del_id");
    $tab_redirect = 'users';
    if($role_res->num_rows > 0) {
        $r = $role_res->fetch_assoc();
        if($r['role'] == 'Technician' || $r['role'] == 'Admin') $tab_redirect = 'technicians';
    }
    $conn->query("DELETE FROM users WHERE id = $del_id");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ!', showConfirmButton: false, timer: 1500 }).then(() => { window.location.href='dashboard.php?tab=$tab_redirect'; }); });</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $full_name = $_POST['full_name'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '-';
    $department = $_POST['department'];
    $role = $_POST['role']; 
    
    $tab_redirect = ($role == 'User') ? 'users' : 'technicians';

    if (empty($user_id)) {
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, phone, department, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $full_name, $phone, $department, $role);
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

// เตรียมข้อมูลประวัติซ่อมส่งให้ JavaScript สำหรับทำ Modal ดูประวัติ
$all_repairs_json = "[]";
if($check_repairs->num_rows > 0) {
    $rep_res = $conn->query("SELECT ticket_no, equipment_type, status, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at_fmt, reporter_name FROM repairs ORDER BY created_at DESC");
    $reps = [];
    while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
    $all_repairs_json = json_encode($reps);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSU Smart Maintenance Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .modern-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -2px rgba(0, 0, 0, 0.06); }
        .nav-btn { width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; margin-bottom: 0.25rem; border-radius: 0.75rem; color: #64748b; font-weight: 500; transition: all 0.2s; border: 1px solid transparent; }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.25rem; margin-right: 0.75rem; color: #94a3b8; transition: all 0.2s; }
        .nav-btn:hover { background-color: #f8fafc; color: #0284c7; }
        .nav-btn:hover i { color: #0ea5e9; transform: scale(1.1); }
        .active-btn { background-color: #f0f9ff; color: #0369a1; border-color: #bae6fd; font-weight: 600; box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1); }
        .active-btn i { color: #0284c7; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-200">

    <!-- Sidebar -->
    <aside class="w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 z-20 shadow-[4px_0_24px_rgba(0,0,0,0.02)]">
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
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-sky-100/50 to-transparent -z-10"></div>
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 sticky top-0">
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

        <div class="flex-1 overflow-y-auto p-10">
            <!-- Dashboard Stats -->
            <div id="dash" class="section space-y-8 animate-fade-in">
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

            <!-- Repairs List Section -->
            <div id="repairs" class="section hidden space-y-6">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">รายการแจ้งซ่อมทั้งหมด</h2>
                            <p class="text-sm text-slate-500 mt-1">ข้อมูลล่าสุดจากระบบฐานข้อมูล</p>
                        </div>
                        <button class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-sky-600 px-4 py-2.5 rounded-xl text-sm font-medium transition-all shadow-sm flex items-center">
                            <i class="fas fa-filter mr-2"></i> ตัวกรองข้อมูล
                        </button>
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
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$statusClass}'><span class='w-1.5 h-1.5 rounded-full bg-current mr-2'></span>{$row['status']}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <a href='update_repair.php?id={$row['id']}' class='w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all flex items-center justify-center border border-emerald-100 shadow-sm' title='อัปเดตสถานะ'><i class='fas fa-clipboard-check'></i></a>
                                                        <a href='view_repair.php?id={$row['id']}' class='w-9 h-9 rounded-xl bg-slate-50 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center border border-slate-200 shadow-sm' title='ดูรายละเอียดเต็ม'><i class='fas fa-eye'></i></a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='px-6 py-16 text-center text-slate-400 font-medium'>ยังไม่มีข้อมูลการแจ้งซ่อมในระบบ</td></tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Technician & Admin Management Section (ทีมงานระบบ) -->
            <div id="technicians" class="section hidden space-y-8">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-transparent mb-2">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">ทีมงานระบบ (แอดมิน & ช่างซ่อม)</h2>
                        <p class="text-sm text-slate-500 mt-1">จัดการรายชื่อผู้ดูแลระบบและช่างซ่อมประจำคณะ</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="openTechAdminModal('Admin')" class="bg-purple-600 hover:bg-purple-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-[0_4px_14px_0_rgba(147,51,234,0.39)] flex items-center">
                            <i class="fas fa-user-shield mr-2"></i> เพิ่มแอดมิน
                        </button>
                        <button onclick="openTechAdminModal('Technician')" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-[0_4px_14px_0_rgba(2,132,199,0.39)] flex items-center">
                            <i class="fas fa-hard-hat mr-2"></i> เพิ่มช่างซ่อม
                        </button>
                    </div>
                </div>

                <!-- ตารางที่ 1: แอดมิน (Admin) -->
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
                                        <th class="px-6 py-4">แผนก/ฝ่าย</th>
                                        <th class="px-6 py-4 text-center">ระดับสิทธิ์</th>
                                        <th class="px-6 py-4 text-right">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $admin_res = $conn->query("SELECT * FROM users WHERE role = 'Admin' ORDER BY created_at DESC");
                                    if($admin_res && $admin_res->num_rows > 0){
                                        while($u = $admin_res->fetch_assoc()) {
                                            $js_uid = $u['id'];
                                            $js_uname = htmlspecialchars($u['username'], ENT_QUOTES);
                                            $js_fname = htmlspecialchars($u['full_name'], ENT_QUOTES);
                                            $js_phone = htmlspecialchars($u['phone'], ENT_QUOTES);
                                            $js_dept = htmlspecialchars($u['department'], ENT_QUOTES);

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-slate-700'>{$u['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 mr-3 border border-purple-100'><i class='fas fa-user-shield text-xs'></i></div>
                                                        {$u['full_name']}
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600'>".($u['phone'] ? $u['phone'] : '-')."</td>
                                                <td class='px-6 py-4 text-slate-600'>{$u['department']}</td>
                                                <td class='px-6 py-4 text-center'>
                                                    <span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-purple-50 text-purple-600 border-purple-200'>Admin</span>
                                                </td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"openTechAdminModal('Admin', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$u['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='px-6 py-8 text-center text-slate-400'>ยังไม่มีข้อมูลผู้ดูแลระบบ</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ตารางที่ 2: ช่างซ่อม (Technician) -->
                <div>
                    <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center"><i class="fas fa-hard-hat text-sky-500 mr-2 text-xl"></i> ช่างซ่อม (Technician)</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">รหัสช่าง / Username</th>
                                        <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                                        <th class="px-6 py-4">เบอร์โทรศัพท์</th>
                                        <th class="px-6 py-4">ความเชี่ยวชาญ / แผนก</th>
                                        <th class="px-6 py-4 text-center">ระดับสิทธิ์</th>
                                        <th class="px-6 py-4 text-right">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $tech_res = $conn->query("SELECT * FROM users WHERE role = 'Technician' ORDER BY created_at DESC");
                                    if($tech_res->num_rows > 0){
                                        while($t = $tech_res->fetch_assoc()) {
                                            $js_uid = $t['id'];
                                            $js_uname = htmlspecialchars($t['username'], ENT_QUOTES);
                                            $js_fname = htmlspecialchars($t['full_name'], ENT_QUOTES);
                                            $js_phone = htmlspecialchars($t['phone'], ENT_QUOTES);
                                            $js_dept = htmlspecialchars($t['department'], ENT_QUOTES);

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-sky-600'>{$t['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 mr-3'><i class='fas fa-hard-hat text-xs'></i></div>
                                                        {$t['full_name']}
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600'>".($t['phone'] ? $t['phone'] : '-')."</td>
                                                <td class='px-6 py-4 text-slate-600'>{$t['department']}</td>
                                                <td class='px-6 py-4 text-center'>
                                                    <span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-sky-50 text-sky-600 border-sky-200'>Technician</span>
                                                </td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"openTechAdminModal('Technician', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$t['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='px-6 py-8 text-center text-slate-400'>ยังไม่มีข้อมูลช่างซ่อมในระบบ</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Management Section -->
            <div id="assets" class="section hidden space-y-6">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">ฐานข้อมูลอุปกรณ์และครุภัณฑ์</h2>
                            <p class="text-sm text-slate-500 mt-1">จัดการข้อมูลครุภัณฑ์ภายในคณะ</p>
                        </div>
                        <button onclick="openAddAssetModal()" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] flex items-center">
                            <i class="fas fa-plus mr-2"></i> เพิ่มอุปกรณ์ใหม่
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">รหัสครุภัณฑ์</th>
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
                                        
                                        $js_id = $a['id'];
                                        $js_code = htmlspecialchars($a['asset_code'], ENT_QUOTES);
                                        $js_name = htmlspecialchars($a['asset_name'], ENT_QUOTES);
                                        $js_cat = htmlspecialchars($a['category'], ENT_QUOTES);
                                        $js_status = htmlspecialchars($a['status'], ENT_QUOTES);

                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 font-bold text-indigo-600'>{$a['asset_code']}</td>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>{$a['asset_name']}</td>
                                            <td class='px-6 py-4 text-slate-600'><span class='bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-medium border border-slate-200'>{$a['category']}</span></td>
                                            <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$a_statusClass}'><span class='w-1.5 h-1.5 rounded-full bg-current mr-2'></span>{$a['status']}</span></td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"openEditAssetModal('$js_id', '$js_code', '$js_name', '$js_cat', '$js_status')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDelete('asset', {$a['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='px-6 py-12 text-center text-slate-400'>ยังไม่มีข้อมูลครุภัณฑ์</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Section (ประวัติผู้แจ้งซ่อม ที่จัดการได้) -->
            <div id="users" class="section hidden space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-transparent mb-2">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">ประวัติผู้แจ้งซ่อม</h2>
                        <p class="text-sm text-slate-500 mt-1">รายชื่อบุคลากรและประวัติการแจ้งซ่อมทั้งหมด</p>
                    </div>
                    <button onclick="openReporterModal()" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-[0_4px_14px_0_rgba(37,99,235,0.39)] flex items-center">
                        <i class="fas fa-user-plus mr-2"></i> เพิ่มผู้แจ้งซ่อม
                    </button>
                </div>

                <div class="modern-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">ชื่อ-นามสกุล (ผู้แจ้ง)</th>
                                    <th class="px-6 py-4">เบอร์โทรศัพท์ / แผนก</th>
                                    <th class="px-6 py-4 text-center">จำนวนที่แจ้งซ่อม</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $reporter_res = $conn->query("SELECT u.*, COUNT(r.id) as total_repairs, MAX(r.created_at) as last_date FROM users u LEFT JOIN repairs r ON u.full_name = r.reporter_name WHERE u.role = 'User' GROUP BY u.id ORDER BY u.created_at DESC");
                                
                                if($reporter_res && $reporter_res->num_rows > 0){
                                    while($r = $reporter_res->fetch_assoc()) {
                                        $js_uid = $r['id']; 
                                        $js_uname = htmlspecialchars($r['username'], ENT_QUOTES); 
                                        $js_fname = htmlspecialchars($r['full_name'], ENT_QUOTES); 
                                        $js_phone = htmlspecialchars($r['phone'], ENT_QUOTES); 
                                        $js_dept = htmlspecialchars($r['department'], ENT_QUOTES);
                                        
                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                <div class='flex items-center'>
                                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3 border border-slate-200'><i class='fas fa-user text-xs'></i></div>
                                                    {$r['full_name']}
                                                </div>
                                            </td>
                                            <td class='px-6 py-4'>
                                                <div class='text-slate-700 font-medium'><i class='fas fa-phone-alt text-slate-400 mr-1.5'></i> ".($r['phone'] ? $r['phone'] : '-')."</div>
                                                <div class='text-slate-500 text-xs mt-0.5'>แผนก: {$r['department']}</div>
                                            </td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-sky-50 text-sky-600 border-sky-200'>{$r['total_repairs']} งาน</span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"viewHistory('{$js_fname}')\" class='w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-500 hover:text-white transition-all flex items-center justify-center border border-sky-100 shadow-sm' title='ดูประวัติการซ่อม'><i class='fas fa-eye'></i></button>
                                                    <button onclick=\"openReporterModal('$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm' title='แก้ไขข้อมูล'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDelete('user', {$r['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm' title='ลบข้อมูล'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='px-6 py-12 text-center text-slate-400'>ยังไม่มีข้อมูลผู้แจ้งซ่อม</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sections อื่นๆ ซ่อนไว้ -->
            <div id="reports" class="section hidden"><h2 class="text-2xl font-bold text-slate-800 mb-6">รายงานสรุปผลการปฏิบัติงาน</h2><div class="modern-card p-10 text-center text-slate-500">กำลังพัฒนาส่วนนี้...</div></div>

        </div>
    </main>

    <!-- Modal เพิ่ม/แก้ไข อุปกรณ์ -->
    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-lg font-bold text-slate-800" id="assetModalTitle">เพิ่มอุปกรณ์ใหม่</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="save_asset" value="1">
                <input type="hidden" name="asset_id" id="asset_id" value="">
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">รหัสครุภัณฑ์ <span class="text-red-500">*</span></label><input type="text" name="asset_code" id="asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่ออุปกรณ์ <span class="text-red-500">*</span></label><input type="text" name="asset_name" id="asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label><select name="category" id="asset_category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"><option value="IT Support">IT Support (คอม/ปริ้นเตอร์)</option><option value="ไฟฟ้า/แอร์">ไฟฟ้า/แอร์</option><option value="อาคารสถานที่">อาคารสถานที่</option><option value="อื่นๆ">อื่นๆ</option></select></div>
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
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">แผนก / ความเชี่ยวชาญ <span class="text-red-500">*</span></label><input type="text" name="department" id="techAdmin_department" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('techAdminModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-sky-600 text-white rounded-xl text-sm font-bold hover:bg-sky-500 shadow-md">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไข ผู้แจ้งซ่อม (Reporter) -->
    <div id="reporterModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('reporterModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-lg font-bold text-slate-800" id="reporterModalTitle"><i class="fas fa-user-plus text-blue-500 mr-2"></i> เพิ่มบุคลากร/ผู้แจ้งซ่อม</p>
                <button onclick="toggleModal('reporterModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="save_user" value="1"><input type="hidden" name="user_id" id="rep_id" value=""><input type="hidden" name="role" value="User">
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">รหัสประจำตัว (ถ้ามี) <span class="text-red-500">*</span></label><input type="text" name="username" id="rep_username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label><input type="text" name="full_name" id="rep_fullname" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label><input type="text" name="phone" id="rep_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">แผนก/ฝ่าย <span class="text-red-500">*</span></label><input type="text" name="department" id="rep_department" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('reporterModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-500 shadow-md">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal ดูประวัติการแจ้งซ่อม -->
    <div id="historyModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('historyModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-3xl mx-auto rounded-2xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                <p class="text-lg font-bold text-slate-800" id="historyModalTitle">ประวัติการแจ้งซ่อม</p>
                <button onclick="toggleModal('historyModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                <table class="w-full text-left whitespace-nowrap border border-slate-100 rounded-xl overflow-hidden">
                    <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="px-4 py-3">เลขที่ใบงาน</th>
                            <th class="px-4 py-3">อุปกรณ์ / อาการ</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <th class="px-4 py-3">วันที่แจ้ง</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100" id="historyTableBody">
                        <!-- ถูกเติมด้วย JavaScript -->
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 shrink-0 flex justify-end">
                <button onclick="toggleModal('historyModal')" class="px-5 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-700 shadow-md">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;
        const pageTitles = {'dash': 'ภาพรวมระบบ (Dashboard)', 'repairs': 'ตรวจสอบงานแจ้งซ่อมทั้งหมด', 'technicians': 'ทีมงานระบบ (แอดมิน & ช่าง)', 'assets': 'ฐานข้อมูลอุปกรณ์และครุภัณฑ์', 'users': 'ประวัติผู้แจ้งซ่อม (รายบุคคล)', 'reports': 'รายงานสรุปผลการปฏิบัติงาน'};
        
        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'ระบบจัดการ';
        }
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if(tab) { show(tab); } else { show('dash'); }
        });

        function toggleModal(m) { document.getElementById(m).classList.toggle('opacity-0'); document.getElementById(m).classList.toggle('pointer-events-none'); document.body.classList.toggle('modal-active'); }

        // Asset Modal
        function openAddAssetModal() { document.getElementById('assetModalTitle').innerHTML = '<i class="fas fa-plus-circle text-sky-500 mr-2"></i> เพิ่มอุปกรณ์ใหม่'; document.getElementById('asset_id').value = ''; document.getElementById('asset_code').value = ''; document.getElementById('asset_name').value = ''; document.getElementById('asset_category').value = 'IT Support'; document.getElementById('asset_status').value = 'ใช้งานปกติ'; toggleModal('assetModal'); }
        function openEditAssetModal(id, c, n, cat, s) { document.getElementById('assetModalTitle').innerHTML = '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขอุปกรณ์'; document.getElementById('asset_id').value = id; document.getElementById('asset_code').value = c; document.getElementById('asset_name').value = n; document.getElementById('asset_category').value = cat; document.getElementById('asset_status').value = s; toggleModal('assetModal'); }

        // Tech/Admin Modal
        function openTechAdminModal(role, id='', u='', f='', p='', d='') { 
            let title = role === 'Admin' ? '<i class="fas fa-user-shield text-purple-500 mr-2"></i> เพิ่มผู้ดูแลระบบ' : '<i class="fas fa-hard-hat text-sky-500 mr-2"></i> เพิ่มช่างซ่อม';
            if(id !== '') title = role === 'Admin' ? '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขแอดมิน' : '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขช่างซ่อม';
            document.getElementById('techAdminModalTitle').innerHTML = title; 
            document.getElementById('techAdmin_role').value = role;
            document.getElementById('techAdmin_id').value = id; 
            document.getElementById('techAdmin_username').value = u; 
            document.getElementById('techAdmin_fullname').value = f; 
            document.getElementById('techAdmin_phone').value = p; 
            document.getElementById('techAdmin_department').value = d; 
            toggleModal('techAdminModal'); 
        }

        // Reporter Modal
        function openReporterModal(id='', u='', f='', p='', d='') {
            document.getElementById('reporterModalTitle').innerHTML = id === '' ? '<i class="fas fa-user-plus text-blue-500 mr-2"></i> เพิ่มบุคลากร/ผู้แจ้งซ่อม' : '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขข้อมูลผู้แจ้งซ่อม';
            document.getElementById('rep_id').value = id;
            document.getElementById('rep_username').value = u;
            document.getElementById('rep_fullname').value = f;
            document.getElementById('rep_phone').value = p;
            document.getElementById('rep_department').value = d;
            toggleModal('reporterModal');
        }

        // View History Modal
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
                    
                    tbody.innerHTML += `<tr class="border-b border-slate-50 hover:bg-slate-50/80 transition-colors">
                        <td class="px-4 py-3 font-bold text-sky-600">${r.ticket_no}</td>
                        <td class="px-4 py-3 text-slate-700">${r.equipment_type}</td>
                        <td class="px-4 py-3 text-center"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold ${statusClass}">${r.status}</span></td>
                        <td class="px-4 py-3 text-slate-500">${r.created_at_fmt}</td>
                    </tr>`;
                });
            }
            document.getElementById('historyModalTitle').innerHTML = '<i class="fas fa-history text-sky-500 mr-2"></i> ประวัติการแจ้งซ่อมของ: <span class="text-sky-600">' + fullName + '</span>';
            toggleModal('historyModal');
        }

        function confirmDelete(t, id) { Swal.fire({ title: 'ยืนยัน?', text: "ข้อมูลนี้จะถูกลบถาวร ไม่สามารถกู้คืนได้!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบเลย!' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_'+t+'=' + id; }); }
    </script>
</body>
</html>