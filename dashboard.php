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

function thaiNum($num) {
    return str_replace(
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        array('๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'),
        $num
    );
}

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
    full_name VARCHAR(100) NULL,
    department VARCHAR(100) NULL,
    role VARCHAR(50) DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) DEFAULT 'User'");
$check_fullname = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if($check_fullname->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER username");

$check_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if($check_phone->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER full_name");

$check_dept = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if($check_dept->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER phone");

$check_pwd = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
if($check_pwd->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN password VARCHAR(255) NULL AFTER username");
}

$check_created = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if($check_created->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

$check_tech_name = $conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'");
if($check_tech_name->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN technician_name VARCHAR(100) NULL");
}

$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs->num_rows > 0) {
    $conn->query("INSERT INTO users (username, full_name, phone, department, role) 
                  SELECT CONCAT('U', REPLACE(phone_number, '-', '')), reporter_name, phone_number, 'บุคลากรทั่วไป', 'User' 
                  FROM repairs 
                  WHERE reporter_name IS NOT NULL AND reporter_name != '' AND reporter_name NOT IN (SELECT full_name FROM users WHERE full_name IS NOT NULL) 
                  GROUP BY reporter_name, phone_number");
}

$has_image_col = ($conn->query("SHOW COLUMNS FROM repairs LIKE 'image_path'")->num_rows > 0);

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

if (isset($_GET['delete_user'])) {
    $del_id = intval($_GET['delete_user']);
    $conn->query("DELETE FROM users WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $password = $_POST['password']; 
    
    $full_name = !empty($_POST['full_name']) ? $_POST['full_name'] : NULL;
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : NULL;
    $role = $_POST['role']; 
    
    if (isset($_POST['admin_level']) && ($role === 'Admin' || $role === 'Executive')) {
        $role = $_POST['admin_level'];
        $department = NULL; 
    } else {
        $department = isset($_POST['department_select']) ? $_POST['department_select'] : NULL;
        if ($department === 'อื่นๆ' && !empty($_POST['department_custom'])) {
            $department = $_POST['department_custom'];
        }
    }

    $tab_redirect = ($role == 'User') ? 'users' : 'technicians';

    if (empty($user_id)) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, phone, department, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $password, $full_name, $phone, $department, $role);
        $msg = 'บันทึกข้อมูลสำเร็จ!';
    } else {
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=?, phone=?, department=?, role=? WHERE id=?");
            $stmt->bind_param("ssssssi", $username, $password, $full_name, $phone, $department, $role, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, phone=?, department=?, role=? WHERE id=?");
            $stmt->bind_param("sssssi", $username, $full_name, $phone, $department, $role, $user_id);
        }
        $msg = 'อัปเดตข้อมูลสำเร็จ!';
    }
    
    if ($stmt->execute()) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: '$msg', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=$tab_redirect'; }); });</script>";
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
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อัปเดตข้อมูลผู้แจ้งสำเร็จ!', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=users'; }); });</script>";
}

if (isset($_GET['approve_tech'])) {
    $id = intval($_GET['approve_tech']);
    $conn->query("UPDATE technicians SET approval_status = 'อนุมัติแล้ว', status = 'ว่าง' WHERE id = $id");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อนุมัติช่างสำเร็จ!', confirmButtonColor: '#4f46e5' }).then(() => { window.location.href='dashboard.php?tab=technicians'; }); });</script>";
}

if (isset($_GET['reject_tech'])) {
    $id = intval($_GET['reject_tech']);
    $conn->query("DELETE FROM technicians WHERE id = $id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if (isset($_GET['delete_tech_db'])) {
    $id = intval($_GET['delete_tech_db']);
    $conn->query("DELETE FROM technicians WHERE id = $id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

$all_repairs_json = "[]";

if($check_repairs->num_rows > 0) {
    $select_fields = "ticket_no, equipment_type, status, DATE_FORMAT(created_at, '%Y-%m-%d') as created_at_fmt, reporter_name";
    $has_tech_name = ($conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'")->num_rows > 0);
    
    if ($has_tech_name) $select_fields .= ", technician_name"; else $select_fields .= ", '' as technician_name";
    if ($has_image_col) $select_fields .= ", image_path"; else $select_fields .= ", NULL as image_path";
    
    $select_query = "SELECT {$select_fields} FROM repairs ORDER BY created_at DESC";
    
    $rep_res = $conn->query($select_query);
    $reps = [];
    if($rep_res) {
        while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
        $all_repairs_json = json_encode($reps);
    }
}

$tech_options = [];
$tech_list_res = $conn->query("SELECT DISTINCT full_name FROM technicians WHERE approval_status = 'อนุมัติแล้ว' AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
if($tech_list_res){
    while($t = $tech_list_res->fetch_assoc()){
        $tech_options[] = $t['full_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS Smart Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; 
            background-color: #f3f4f6; 
            color: #1e293b; 
        }
        
        .modern-card { 
            background: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 4px 24px rgba(149, 157, 165, 0.15); 
            border: 1px solid #f1f5f9; 
        }
        
        .nav-btn { 
            width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; 
            margin-bottom: 0.25rem; border-radius: 12px; color: #64748b; 
            font-weight: 600; transition: all 0.2s ease; font-size: 0.95rem;
        }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.1rem; margin-right: 0.875rem; color: #94a3b8; transition: all 0.2s ease; }
        .nav-btn:hover { background-color: #f8fafc; color: #4f46e5; }
        .nav-btn:hover i { color: #4f46e5; }
        
        .active-btn { 
            background-color: #eef2ff; color: #4f46e5; font-weight: 700; 
        }
        .active-btn i { color: #4f46e5; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        
        .badge-pending { background-color: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-progress { background-color: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background-color: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        @media print {
            aside, header, .no-print, #sidebarOverlay, #dash, #repairs, #technicians, #assets, #users, #reports { display: none !important; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-indigo-100">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 z-40 hidden md:hidden backdrop-blur-sm transition-opacity" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="w-[260px] bg-white flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 border-r border-slate-100 no-print">
        <div class="h-24 flex items-center px-8 border-b border-slate-50">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/30 mr-3 shrink-0">
                <i class="fas fa-tools text-white text-lg"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">MBS<span class="text-indigo-600">Repair</span></h1>
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-8 flex flex-col overflow-y-auto">
            <p class="px-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4">Dashboard</p>
            <button onclick="show('dash')" class="nav-btn active-btn" id="btn-dash"><i class="fas fa-chart-pie"></i> Overview</button>
            <button onclick="show('repairs')" class="nav-btn" id="btn-repairs"><i class="fas fa-list-ul"></i> Transactions</button>
            <button onclick="show('technicians')" class="nav-btn" id="btn-technicians"><i class="fas fa-user-friends"></i> Team</button>
            
            <p class="px-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4 mt-8">Management</p>
            <button onclick="show('assets')" class="nav-btn" id="btn-assets"><i class="fas fa-box-open"></i> Assets</button>
            <button onclick="show('users')" class="nav-btn" id="btn-users"><i class="fas fa-address-book"></i> Contacts</button>
            <button onclick="show('reports')" class="nav-btn" id="btn-reports"><i class="fas fa-file-export"></i> Reports</button>
            
            <div class="mt-auto pt-6 border-t border-slate-50 flex flex-col gap-1">
                <a href="index.php" class="nav-btn text-slate-500 hover:bg-indigo-50 hover:text-indigo-600"><i class="fas fa-home"></i> Back to Website</a>
                <a href="logout.php" class="nav-btn text-slate-500 hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-[#f8fafc]">
        
        <header class="h-20 bg-white/80 backdrop-blur-md flex items-center justify-between px-8 z-10 sticky top-0 no-print border-b border-slate-100">
            <div class="flex items-center">
                <button onclick="toggleSidebar()" class="md:hidden mr-4 text-slate-500 hover:text-indigo-600 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight" id="headerTitle">Dashboard Overview</h2>
            </div>
            
            <div class="flex items-center">
                
                <div class="flex items-center gap-3 cursor-pointer group">
                    <div class="text-right hidden sm:block">
                        <span class="block text-sm font-bold text-slate-700 leading-none mb-1 group-hover:text-indigo-600 transition-colors">
                            <?php echo isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'); ?>
                        </span>
                        <span class="block text-[11px] text-slate-400 font-semibold">Administrator</span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 overflow-hidden">
                        <img src="https://api.dicebear.com/7.x/notionists/svg?seed=<?php echo $_SESSION['username'] ?? 'admin'; ?>&backgroundColor=e2e8f0" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            
            <div id="dash" class="section space-y-8 animate-fade-in no-print">
                
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Equipment Analytics</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">Frequency of reported broken assets</p>
                            </div>
                            <div class="px-3 py-1 bg-slate-100 rounded-lg text-xs font-bold text-slate-500">Overview</div>
                        </div>
                        <div class="flex-1 relative w-full h-[280px]">
                            <canvas id="mainEquipChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg">Work Status</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">Distribution</p>
                        </div>
                        <div class="flex-1 relative w-full h-[220px] flex justify-center items-center">
                            <canvas id="mainStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    <div class="modern-card overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Recent Transactions</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">Latest 5 repairs in system</p>
                            </div>
                            <button onclick="show('repairs')" class="flex items-center text-sm text-slate-600 font-bold hover:text-indigo-600 transition-colors group">
                                See All <i class="fas fa-arrow-right ml-2 text-xs text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-slate-50 text-slate-400 text-xs uppercase tracking-widest font-bold">
                                    <tr>
                                        <th class="px-6 py-4">Ticket No.</th>
                                        <th class="px-6 py-4">Reporter</th>
                                        <th class="px-6 py-4">Equipment</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100">
                                    <?php
                                    if($check_repairs->num_rows > 0) {
                                        $recent_dash = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                        if($recent_dash && $recent_dash->num_rows > 0){
                                            while($rd = $recent_dash->fetch_assoc()) {
                                                $stClass = ($rd['status'] == 'รอรับเรื่อง') ? 'badge-pending' : (($rd['status'] == 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-success');
                                                $statusText = ($rd['status'] == 'รอรับเรื่อง') ? 'Pending' : (($rd['status'] == 'กำลังดำเนินการ') ? 'In Progress' : 'Completed');
                                                $date_fmt = date("Y-m-d", strtotime($rd['created_at']));
                                                
                                                $imageIcon = "";
                                                if($has_image_col && !empty($rd['image_path'])) {
                                                    $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                                                }
                                                
                                                echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                    <td class='px-6 py-4 text-slate-500 font-mono font-semibold'>{$rd['ticket_no']}</td>
                                                    <td class='px-6 py-4 text-slate-800 font-bold'>
                                                        <div class='flex items-center'>
                                                            <div class='w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 mr-3 text-xs'><i class='fas fa-user'></i></div>
                                                            {$rd['reporter_name']}
                                                        </div>
                                                    </td>
                                                    <td class='px-6 py-4 text-slate-600 font-medium'>{$rd['equipment_type']} {$imageIcon}</td>
                                                    <td class='px-6 py-4 text-center'><span class='{$stClass}'>{$statusText}</span></td>
                                                    <td class='px-6 py-4 text-right text-slate-500 font-medium'>{$date_fmt}</td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>No transactions found</td></tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="repairs" class="section hidden space-y-6 no-print">
                <div class="modern-card overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Repairs List</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">All repair transactions</p>
                        </div>
                        <div class="w-full md:w-auto relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchInputMobile" placeholder="Search ticket or status..." class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium">
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left whitespace-nowrap min-w-[800px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 text-xs uppercase tracking-widest font-bold">
                                <tr>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Ticket No.</th>
                                    <th class="px-6 py-4">Reporter</th>
                                    <th class="px-6 py-4">Equipment</th>
                                    <th class="px-6 py-4 text-center">Technician</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                if($check_repairs->num_rows > 0) {
                                    $select_fields = "id, ticket_no, equipment_type, status, problem_desc, reporter_name, phone_number, created_at";
                                    if ($has_tech_name) $select_fields .= ", technician_name"; else $select_fields .= ", '' as technician_name";
                                    if ($has_image_col) $select_fields .= ", image_path"; else $select_fields .= ", NULL as image_path";
                                    
                                    $select_query = "SELECT {$select_fields} FROM repairs ORDER BY created_at DESC";
                                    
                                    $res = $conn->query($select_query);
                                    if($res && $res->num_rows > 0){
                                        while($row = $res->fetch_assoc()) {
                                            $date = !empty($row['created_at']) ? date("Y-m-d", strtotime($row['created_at'])) : "-";
                                            $stClass = ($row['status'] == 'รอรับเรื่อง') ? 'badge-pending' : (($row['status'] == 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-success');
                                            $techName = !empty($row['technician_name']) ? "<div class='text-indigo-600 font-bold'>{$row['technician_name']}</div>" : "<span class='text-slate-400'>Unassigned</span>";

                                            $imageIcon = "";
                                            if($has_image_col && !empty($row['image_path'])) {
                                                $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                                            }

                                            echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                <td class='px-6 py-4 text-slate-500 font-medium'>{$date}</td>
                                                <td class='px-6 py-4 font-mono font-semibold text-slate-600'>{$row['ticket_no']}</td>
                                                <td class='px-6 py-4'><div class='text-slate-800 font-bold'>{$row['reporter_name']}</div><div class='text-slate-500 text-[11px] font-medium mt-0.5'>{$row['phone_number']}</div></td>
                                                <td class='px-6 py-4'>
                                                    <div class='text-slate-800 font-bold'>{$row['equipment_type']} {$imageIcon}</div>
                                                    <div class='text-slate-500 text-[11px] font-medium mt-0.5 max-w-[150px] truncate' title='{$row['problem_desc']}'>{$row['problem_desc']}</div>
                                                </td>
                                                <td class='px-6 py-4 text-center'>{$techName}</td>
                                                <td class='px-6 py-4 text-center'><span class='{$stClass}'>{$row['status']}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <a href='update_repair.php?id={$row['id']}' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100'><i class='fas fa-edit'></i></a>
                                                        <a href='view_repair.php?id={$row['id']}' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-all flex items-center justify-center border border-slate-100'><i class='fas fa-eye'></i></a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='7' class='px-6 py-16 text-center text-slate-400 font-medium'>No records found</td></tr>"; }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="technicians" class="section hidden space-y-6 no-print">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
                    <div>
                        <h2 class="text-xl md:text-2xl font-extrabold text-slate-800">Team Management</h2>
                        <p class="text-sm font-medium text-slate-500 mt-0.5">Manage administrators and technicians</p>
                    </div>
                    <div class="flex w-full md:w-auto gap-3">
                        <button onclick="openTechAdminModal('Admin')" class="flex-1 md:flex-none bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm flex items-center justify-center transition-all"><i class="fas fa-shield-alt mr-2 text-slate-400"></i> Add Admin</button>
                    </div>
                </div>

                <?php
                $pending_res = $conn->query("SELECT * FROM technicians WHERE approval_status = 'รออนุมัติ' ORDER BY id DESC");
                if($pending_res && $pending_res->num_rows > 0) {
                ?>
                <div class="mb-8">
                    <h3 class="text-base font-extrabold text-amber-600 mb-3 flex items-center"><i class="fas fa-user-clock mr-2"></i> Pending Approvals (รออนุมัติ)</h3>
                    <div class="modern-card overflow-hidden border-2 border-amber-100">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                                <thead class="bg-amber-50 border-b border-amber-100 text-amber-600 text-xs uppercase tracking-widest font-bold">
                                    <tr>
                                        <th class="px-6 py-4">Name</th>
                                        <th class="px-6 py-4">Contact</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php while($p = $pending_res->fetch_assoc()) { ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4 text-slate-800 font-bold"><?php echo htmlspecialchars($p['full_name']); ?></td>
                                        <td class="px-6 py-4 text-slate-500 font-medium"><?php echo htmlspecialchars($p['phone']); ?></td>
                                        <td class="px-6 py-4 text-center"><span class="badge-pending">รออนุมัติ</span></td>
                                        <td class="px-6 py-4 text-right">
                                            <button onclick="window.location.href='dashboard.php?approve_tech=<?php echo $p['id']; ?>'" class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all mr-2"><i class="fas fa-check md:mr-1"></i> <span class="hidden md:inline">อนุมัติ</span></button>
                                            <button onclick="confirmRejectTech(<?php echo $p['id']; ?>)" class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all"><i class="fas fa-times md:mr-1"></i> <span class="hidden md:inline">ปฏิเสธ</span></button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <div>
                    <h3 class="text-base font-extrabold text-slate-700 mb-3 flex items-center">Administrators</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 text-xs uppercase tracking-widest font-bold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">Username</th>
                                        <th class="px-6 py-4">Name</th>
                                        <th class="px-6 py-4">Contact</th>
                                        <th class="px-6 py-4 text-center">Role</th>
                                        <th class="px-6 py-4 text-right">Action</th>
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
                                            
                                            $js_uid = $u['id']; $js_uname = htmlspecialchars($u['username'], ENT_QUOTES); $js_fname = htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES); $js_phone = htmlspecialchars($u['phone'] ?? '', ENT_QUOTES); $js_dept = htmlspecialchars($u['department'] ?? '', ENT_QUOTES); $js_role = htmlspecialchars($u['role'], ENT_QUOTES);

                                            echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-slate-700'>{$u['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-bold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3'><i class='fas {$icon} text-xs'></i></div>
                                                        ".(!empty($u['full_name']) ? $u['full_name'] : '-')."
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-500 font-medium'>".(!empty($u['phone']) ? $u['phone'] : '-')."</td>
                                                <td class='px-6 py-4 text-center'><span class='px-3 py-1 rounded-full text-[10px] font-bold {$roleClass}'>{$roleDisplay}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"openTechAdminModal('{$js_role}', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center'><i class='fas fa-edit'></i></button>
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

                <div class="mt-8">
                    <h3 class="text-base font-extrabold text-slate-700 mb-3 flex items-center">Technicians (ช่างที่อนุมัติแล้ว)</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 text-xs uppercase tracking-widest font-bold">
                                    <tr>
                                        <th class="px-6 py-4">Name</th>
                                        <th class="px-6 py-4">Contact</th> 
                                        <th class="px-6 py-4">Department</th>
                                        <th class="px-6 py-4 text-center">Jobs</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $tech_res = $conn->query("SELECT * FROM technicians WHERE approval_status = 'อนุมัติแล้ว' ORDER BY id DESC");
                                    
                                    if($tech_res && $tech_res->num_rows > 0){
                                        while($t = $tech_res->fetch_assoc()) {
                                            $js_fname = htmlspecialchars($t['full_name'] ?? '', ENT_QUOTES);
                                            
                                            $total_jobs = 0;
                                            if(!empty($t['full_name'])) {
                                                $safe_tech_name = $conn->real_escape_string($t['full_name']);
                                                $job_res = $conn->query("SELECT COUNT(id) as c FROM repairs WHERE technician_name = '{$safe_tech_name}'");
                                                if($job_res) $total_jobs = $job_res->fetch_assoc()['c'];
                                            }

                                            echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                <td class='px-6 py-4 text-slate-800 font-bold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 mr-3'><i class='fas fa-tools text-xs'></i></div>
                                                        ".(!empty($t['full_name']) ? $t['full_name'] : '-')."
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-500 font-medium'>".(!empty($t['phone']) ? $t['phone'] : '-')."</td> 
                                                <td class='px-6 py-4 text-slate-500 font-medium'>".(!empty($t['department']) ? $t['department'] : '-')."</td>
                                                <td class='px-6 py-4 text-center'><span class='px-3 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600'>{$total_jobs}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"viewHistory('{$js_fname}', 'technician')\" class='bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>View</span></button>
                                                        <button onclick=\"confirmDelete('tech_db', {$t['id']})\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>No technicians found</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="assets" class="section hidden space-y-6 no-print">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Assets Database</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">Manage all registered equipments</p>
                        </div>
                        <button onclick="openAddAssetModal()" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-indigo-200 flex items-center justify-center transition-all"><i class="fas fa-plus mr-2"></i> Add Asset</button>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left whitespace-nowrap min-w-[600px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 text-xs uppercase tracking-widest font-bold">
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
                                        $js_id = $a['id']; $js_code = htmlspecialchars($a['asset_code'], ENT_QUOTES); $js_name = htmlspecialchars($a['asset_name'], ENT_QUOTES); $js_cat = htmlspecialchars($a['category'], ENT_QUOTES); $js_status = htmlspecialchars($a['status'], ENT_QUOTES);

                                        echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                            <td class='px-6 py-4 font-mono font-semibold text-slate-500'>{$a['asset_code']}</td>
                                            <td class='px-6 py-4 text-slate-800 font-bold'>{$a['asset_name']}</td>
                                            <td class='px-6 py-4 text-slate-500 font-medium'>{$a['category']}</td>
                                            <td class='px-6 py-4 text-center'><span class='{$a_statusClass}'>{$a['status']}</span></td>
                                            <td class='px-6 py-4 text-right'>
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

            <div id="users" class="section hidden space-y-6 no-print">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Reporter History</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">Database of personnel who reported issues</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 text-xs uppercase tracking-widest font-bold">
                                <tr>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Contact</th>
                                    <th class="px-6 py-4 text-center">Reports</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $reporter_res = $conn->query("SELECT reporter_name, MAX(phone_number) as phone_number, COUNT(id) as total_repairs FROM repairs WHERE reporter_name IS NOT NULL AND reporter_name != '' GROUP BY reporter_name ORDER BY MAX(created_at) DESC");
                                
                                if($reporter_res && $reporter_res->num_rows > 0){
                                    while($r = $reporter_res->fetch_assoc()) {
                                        $js_old_name = htmlspecialchars($r['reporter_name'], ENT_QUOTES);
                                        $js_old_phone = htmlspecialchars($r['phone_number'], ENT_QUOTES);
                                        
                                        echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                            <td class='px-6 py-4 text-slate-800 font-bold'>
                                                <div class='flex items-center'>
                                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3'><i class='fas fa-user text-xs'></i></div>
                                                    {$r['reporter_name']}
                                                </div>
                                            </td>
                                            <td class='px-6 py-4 text-slate-500 font-medium'>".($r['phone_number'] ? $r['phone_number'] : '-')."</td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='px-3 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600'>{$r['total_repairs']}</span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"viewHistory('{$js_old_name}', 'reporter')\" class='bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>View</span></button>
                                                    <button onclick=\"openEditReporterModal('{$js_old_name}', '{$js_old_phone}')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDeleteReporter('{$js_old_name}')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
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

            <div id="reports" class="section hidden space-y-6 no-print">
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

                    <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                        <label class="font-bold text-slate-700 text-sm flex items-center"><i class="fas fa-filter text-indigo-500 mr-2"></i> Filter Data by Technician:</label>
                        <select id="techFilter" onchange="updateExcelLink()" class="bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium min-w-[250px] w-full sm:w-auto cursor-pointer">
                            <option value="all">Overall System (All Technicians)</option>
                            <?php 
                                foreach($tech_options as $tech) {
                                    echo "<option value=\"".htmlspecialchars($tech)."\">Technician: ".htmlspecialchars($tech)."</option>"; 
                                }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                <p class="text-lg font-extrabold text-slate-800" id="assetModalTitle">Add Asset</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="save_asset" value="1"><input type="hidden" name="asset_id" id="asset_id" value="">
                <div class="space-y-5">
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Code</label><input type="text" name="asset_code" id="asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Name</label><input type="text" name="asset_name" id="asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Category</label><select name="category" id="asset_category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"><option value="IT Support">IT Support</option><option value="ไฟฟ้า/แอร์">ไฟฟ้า/แอร์</option><option value="อาคารสถานที่">อาคารสถานที่</option><option value="อื่นๆ">อื่นๆ</option></select></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Status</label><select name="status" id="asset_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"><option value="ใช้งานปกติ">ใช้งานปกติ</option><option value="ชำรุด/ส่งซ่อม">ชำรุด/ส่งซ่อม</option><option value="แทงจำหน่าย">แทงจำหน่าย</option></select></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('assetModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Save Asset</button></div>
            </form>
        </div>
    </div>

    <div id="techAdminModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('techAdminModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-y-auto max-h-[90vh] transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl sticky top-0 z-10">
                <p class="text-lg font-extrabold text-slate-800" id="techAdminModalTitle">Add Member</p>
                <button onclick="toggleModal('techAdminModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="techAdmin_id" value="">
                <input type="hidden" name="role" id="techAdmin_role" value="">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Username</label>
                        <input type="text" name="username" id="techAdmin_username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password <span class="text-slate-400 font-normal normal-case" id="pwdHint"></span></label>
                        <div class="relative">
                            <input type="password" name="password" id="techAdmin_password" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-10 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium" placeholder="••••••••">
                            <button type="button" class="absolute inset-y-0 right-0 px-4 flex items-center text-slate-400 hover:text-indigo-600 focus:outline-none" onclick="togglePasswordVisibility('techAdmin_password', 'eyeIcon')">
                                <i id="eyeIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div id="adminLevelDiv" class="hidden">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Role Level</label>
                        <select name="admin_level" id="techAdmin_level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                            <option value="Admin">Admin</option>
                            <option value="Executive">Executive</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name</label>
                        <input type="text" name="full_name" id="techAdmin_fullname" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Phone</label>
                        <input type="text" name="phone" id="techAdmin_phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    
                    <div id="deptDiv">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Department</label>
                        <select name="department_select" id="techAdmin_department_select" onchange="toggleCustomDept(this, 'techAdmin_department_custom')" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium mb-2">
                            <option value="" disabled selected>-- Select Department --</option>
                            <option value="แผนกช่าง">แผนกช่าง</option>
                            <option value="แผนกไฟฟ้า">แผนกไฟฟ้า</option>
                            <option value="แผนกโสต">แผนกโสต</option>
                            <option value="แม่บ้าน">แม่บ้าน</option>
                            <option value="อื่นๆ">อื่นๆ (Custom)</option>
                        </select>
                        <input type="text" name="department_custom" id="techAdmin_department_custom" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 hidden focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium" placeholder="Specify department">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('techAdminModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Save Data</button></div>
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
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="edit_reporter" value="1">
                <input type="hidden" name="old_name" id="edit_rep_old_name" value="">
                <div class="bg-indigo-50 text-indigo-700 text-xs p-4 rounded-xl mb-5 font-medium flex items-start">
                    <i class="fas fa-info-circle mt-0.5 mr-2"></i> This will update all past repair records associated with this person.
                </div>
                <div class="space-y-5">
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name</label><input type="text" name="new_name" id="edit_rep_new_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"></div>
                    <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Phone Number</label><input type="text" name="new_phone" id="edit_rep_new_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium"></div>
                </div>
                <div class="mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('editReporterModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Update</button></div>
            </form>
        </div>
    </div>

    <div id="historyModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('historyModal')"></div>
        <div class="modal-container bg-white w-full max-w-3xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[80vh] max-h-[800px]">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl shrink-0">
                <p class="text-lg font-extrabold text-slate-800 truncate pr-4" id="historyModalTitle">History</p>
                <button onclick="toggleModal('historyModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm shrink-0"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 bg-white">
                <div class="w-full overflow-x-auto rounded-2xl border border-slate-100 shadow-sm">
                    <table class="w-full text-left whitespace-nowrap min-w-[500px]">
                        <thead class="bg-slate-50 text-slate-400 text-xs uppercase tracking-widest font-bold border-b border-slate-100">
                            <tr>
                                <th class="px-5 py-4">Ticket No.</th>
                                <th class="px-5 py-4">Equipment</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50" id="historyTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;
        
        const pageTitles = {
            'dash': 'Dashboard Overview',
            'repairs': 'All Repairs List',
            'technicians': 'Team Management',
            'assets': 'Assets Database',
            'users': 'Reporter History',
            'reports': 'Official Report'
        };
        
        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'System Management';
            
            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
            }

            let searchInputs = [document.getElementById('searchInput'), document.getElementById('searchInputMobile')];
            searchInputs.forEach(input => {
                if(input) {
                    input.value = '';
                    let activeSection = document.getElementById(id);
                    if(activeSection) {
                        activeSection.querySelectorAll('table tbody tr').forEach(row => row.style.display = '');
                    }
                }
            });

            if(id === 'dash' && !window.chartsRendered) {
                renderCharts();
                window.chartsRendered = true;
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            window.chartsRendered = false;
            
            if(tab) { show(tab); } else { show('dash'); }

            ['searchInput', 'searchInputMobile'].forEach(id => {
                const inputElement = document.getElementById(id);
                if(inputElement) {
                    inputElement.addEventListener('input', function() {
                        let filter = this.value.toLowerCase();
                        let activeSection = document.querySelector('.section:not(.hidden)');
                        if (!activeSection) return;
                        
                        let rows = activeSection.querySelectorAll('table tbody tr');
                        rows.forEach(row => {
                            if (row.innerText.toLowerCase().includes(filter)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
            });
        });

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        function filterRepairs(statusStr) {
            show('repairs');
            
            setTimeout(() => {
                let searchInputs = [document.getElementById('searchInput'), document.getElementById('searchInputMobile')];
                searchInputs.forEach(input => {
                    if(input) {
                        input.value = statusStr === 'all' ? '' : statusStr;
                        input.dispatchEvent(new Event('input'));
                    }
                });
            }, 50);
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
            let printUrl = 'print_report.php';
            if (filterValue !== 'all') {
                printUrl += `?tech=${encodeURIComponent(filterValue)}`;
            }
            window.open(printUrl, '_blank');
        }

        function renderCharts() {
            let pending = 0, progress = 0, completed = 0;
            let equipCountMap = {};

            allRepairs.forEach(r => {
                if(r.status === 'รอรับเรื่อง') pending++;
                else if(r.status === 'กำลังดำเนินการ') progress++;
                else if(r.status === 'ซ่อมเสร็จแล้ว') completed++;

                if(r.equipment_type) {
                    equipCountMap[r.equipment_type] = (equipCountMap[r.equipment_type] || 0) + 1;
                }
            });

            let sortedEquip = Object.keys(equipCountMap).map(key => {
                return { name: key, count: equipCountMap[key] };
            }).sort((a, b) => b.count - a.count).slice(0, 7);

            let eLabels = sortedEquip.map(e => e.name);
            let eCounts = sortedEquip.map(e => e.count);

            const ctxStatus = document.getElementById('mainStatusChart').getContext('2d');
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed'],
                    datasets: [{ 
                        data: [pending, progress, completed], 
                        backgroundColor: ['#f59e0b', '#38bdf8', '#8b5cf6'], 
                        borderWidth: 0, 
                        hoverOffset: 6 
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { 
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' } } } 
                    }, 
                    cutout: '75%' 
                }
            });

            const ctxEquip = document.getElementById('mainEquipChart').getContext('2d');
            let gradient = ctxEquip.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)'); 
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            new Chart(ctxEquip, {
                type: 'line', 
                data: {
                    labels: eLabels,
                    datasets: [{ 
                        label: 'Repairs', 
                        data: eCounts, 
                        borderColor: '#8b5cf6', 
                        backgroundColor: gradient, 
                        borderWidth: 3, 
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#8b5cf6',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.4 
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
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

        function toggleCustomDept(selectElement, customInputId) {
            const customInput = document.getElementById(customInputId);
            if(selectElement.value === 'อื่นๆ') { customInput.classList.remove('hidden'); customInput.required = true;
            } else { customInput.classList.add('hidden'); customInput.required = false; }
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

        function openTechAdminModal(role, id='', u='', f='', p='', d='') { 
            let isManagement = (role.toLowerCase() === 'admin' || role.toLowerCase() === 'executive');
            let baseRole = isManagement ? 'Admin' : 'Technician';
            let title = isManagement ? 'Manage Administrator' : 'Manage Technician';
            document.getElementById('techAdminModalTitle').innerHTML = title; document.getElementById('techAdmin_role').value = baseRole; 
            
            const adminLevelDiv = document.getElementById('adminLevelDiv'); const deptDiv = document.getElementById('deptDiv');
            if(isManagement) {
                adminLevelDiv.classList.remove('hidden'); deptDiv.classList.add('hidden'); document.getElementById('techAdmin_department_select').required = false;
                let exactRole = (role.toLowerCase() === 'executive') ? 'Executive' : 'Admin'; document.getElementById('techAdmin_level').value = exactRole;
            } else {
                adminLevelDiv.classList.add('hidden'); deptDiv.classList.remove('hidden'); document.getElementById('techAdmin_department_select').required = true;
            }

            document.getElementById('techAdmin_id').value = id; document.getElementById('techAdmin_username').value = u; 
            document.getElementById('techAdmin_fullname').value = f; document.getElementById('techAdmin_phone').value = p; 
            
            const pwdInput = document.getElementById('techAdmin_password'); const pwdHint = document.getElementById('pwdHint'); const eyeIcon = document.getElementById('eyeIcon');
            pwdInput.value = ''; pwdInput.type = 'password'; 
            if(eyeIcon) { eyeIcon.classList.remove('fa-eye-slash'); eyeIcon.classList.add('fa-eye'); }
            if(id === '') { pwdInput.required = true; pwdHint.innerText = "(Required)"; } else { pwdInput.required = false; pwdHint.innerText = "(Leave blank to keep current)"; }
            
            document.getElementById('techAdmin_department_select').name = "department_select"; document.getElementById('techAdmin_department_custom').name = "department_custom";
            setDropdownOrCustom('techAdmin_department_select', 'techAdmin_department_custom', d);
            toggleModal('techAdminModal'); 
        }

        function openEditReporterModal(old_name, old_phone) {
            document.getElementById('edit_rep_old_name').value = old_name; document.getElementById('edit_rep_new_name').value = old_name; document.getElementById('edit_rep_new_phone').value = old_phone; toggleModal('editReporterModal');
        }

        function viewHistory(fullName, type) {
            const tbody = document.getElementById('historyTableBody'); tbody.innerHTML = '';
            const userRepairs = allRepairs.filter(r => type === 'reporter' ? r.reporter_name === fullName : r.technician_name === fullName);
            
            if(userRepairs.length === 0) {
                let emptyMsg = type === 'reporter' ? 'No repair history found.' : 'No tasks assigned yet.';
                tbody.innerHTML = `<tr><td colspan="4" class="px-5 py-8 text-center text-slate-400 font-medium">${emptyMsg}</td></tr>`;
            } else {
                userRepairs.forEach(r => {
                    let statusClass = 'badge-pending';
                    if(r.status === 'กำลังดำเนินการ') statusClass = 'badge-progress';
                    else if(r.status === 'ซ่อมเสร็จแล้ว') statusClass = 'badge-success';
                    
                    let statusText = r.status == 'รอรับเรื่อง' ? 'Pending' : (r.status == 'กำลังดำเนินการ' ? 'In Progress' : 'Completed');

                    tbody.innerHTML += `<tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-4 font-mono font-semibold text-slate-500">${r.ticket_no}</td>
                        <td class="px-5 py-4 text-slate-800 font-medium whitespace-normal min-w-[150px]">${r.equipment_type}</td>
                        <td class="px-5 py-4 text-center"><span class="${statusClass}">${statusText}</span></td>
                        <td class="px-5 py-4 text-slate-500 font-medium">${r.created_at_fmt}</td>
                    </tr>`;
                });
            }
            document.getElementById('historyModalTitle').innerText = fullName;
            toggleModal('historyModal');
        }

        function confirmDelete(type, id) { 
            Swal.fire({ title: 'Are you sure?', text: "This action cannot be undone!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, delete it!' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_'+type+'=' + id; }); 
        }

        function confirmDeleteReporter(name) { 
            Swal.fire({ title: 'Delete this person?', text: "All past reporter names will be cleared!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, delete!' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_reporter=' + encodeURIComponent(name); }); 
        }

        function confirmRejectTech(id) { 
            Swal.fire({ 
                title: 'ปฏิเสธคำขอ?', 
                text: "คุณต้องการลบคำขอลงทะเบียนนี้ใช่หรือไม่", 
                icon: 'warning', 
                showCancelButton: true, 
                confirmButtonColor: '#ef4444', 
                confirmButtonText: 'ใช่, ปฏิเสธ!' 
            }).then((r) => { 
                if(r.isConfirmed) window.location.href = 'dashboard.php?reject_tech=' + id; 
            }); 
        }

    </script>
</body>
</html>