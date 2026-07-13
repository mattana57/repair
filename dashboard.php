<?php 
include 'db_connect.php'; 

// ตรวจสอบและสร้างตาราง assets อัตโนมัติถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ใช้งานปกติ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// จัดการเมื่อมีการ ลบ อุปกรณ์
if (isset($_GET['delete_asset'])) {
    $del_id = intval($_GET['delete_asset']);
    $conn->query("DELETE FROM assets WHERE id = $del_id");
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ!', showConfirmButton: false, timer: 1500 })
            .then(() => { window.location.href='dashboard.php?tab=assets'; });
        });
    </script>";
}

// จัดการเมื่อมีการกดปุ่ม บันทึก (เพิ่ม หรือ แก้ไข)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_asset'])) {
    $asset_id = $_POST['asset_id'];
    $asset_code = $_POST['asset_code'];
    $asset_name = $_POST['asset_name'];
    $category = $_POST['category'];
    $status = $_POST['status'];

    if (empty($asset_id)) {
        // เพิ่มข้อมูลใหม่
        $stmt = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $asset_code, $asset_name, $category, $status);
        $msg = 'เพิ่มอุปกรณ์สำเร็จ!';
    } else {
        // อัปเดตข้อมูลเดิม
        $stmt = $conn->prepare("UPDATE assets SET asset_code=?, asset_name=?, category=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $asset_code, $asset_name, $category, $status, $asset_id);
        $msg = 'อัปเดตข้อมูลสำเร็จ!';
    }
    
    if ($stmt->execute()) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({ icon: 'success', title: '$msg', confirmButtonColor: '#0284c7' })
                .then(() => { window.location.href='dashboard.php?tab=assets'; });
            });
        </script>";
    }
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
            <button onclick="show('assign')" class="nav-btn" id="btn-assign"><i class="fas fa-user-shield"></i> มอบหมายงานช่าง</button>
            
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 mt-8">การตั้งค่าและรายงาน</p>
            <button onclick="show('assets')" class="nav-btn" id="btn-assets"><i class="fas fa-server"></i> จัดการอุปกรณ์</button>
            <button onclick="show('users')" class="nav-btn" id="btn-users"><i class="fas fa-users-cog"></i> จัดการผู้ใช้</button>
            <button onclick="show('reports')" class="nav-btn" id="btn-reports"><i class="fas fa-file-invoice"></i> รายงานสรุป</button>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-sky-100/50 to-transparent -z-10"></div>
        
        <!-- Top Navigation -->
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 sticky top-0">
            <h2 class="text-2xl font-bold text-slate-800 tracking-wide" id="headerTitle">ภาพรวมระบบ (Dashboard)</h2>
            <div class="flex items-center space-x-6">
                <div class="relative hidden md:block">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" placeholder="ค้นหาเลขที่ใบงาน..." class="bg-white border border-slate-200 text-sm rounded-full pl-11 pr-5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all w-72 shadow-sm">
                </div>
                <div class="flex items-center space-x-3 cursor-pointer p-1.5 pr-4 rounded-full border border-slate-200 bg-white hover:bg-slate-50 transition-all shadow-sm">
                    <div class="w-9 h-9 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <div class="hidden sm:block text-left">
                        <span class="block text-sm font-semibold text-slate-700 leading-none mb-1">Administrator</span>
                        <span class="block text-[11px] text-slate-500 uppercase tracking-wide leading-none">ผู้ดูแลระบบ</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto p-10">
            
            <!-- Dashboard Stats -->
            <div id="dash" class="section space-y-8 animate-fade-in">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                    $stats = [
                        ["title" => "งานทั้งหมด", "query" => "repairs", "icon" => "fa-briefcase", "color" => "text-blue-600", "bg" => "bg-blue-100", "border" => "border-blue-200"],
                        ["title" => "รอรับเรื่อง", "query" => "status='รอรับเรื่อง'", "icon" => "fa-clock", "color" => "text-amber-500", "bg" => "bg-amber-100", "border" => "border-amber-200"],
                        ["title" => "กำลังดำเนินการ", "query" => "status='กำลังดำเนินการ'", "icon" => "fa-tools", "color" => "text-sky-500", "bg" => "bg-sky-100", "border" => "border-sky-200"],
                        ["title" => "ซ่อมเสร็จแล้ว", "query" => "status='ซ่อมเสร็จแล้ว'", "icon" => "fa-check-circle", "color" => "text-emerald-500", "bg" => "bg-emerald-100", "border" => "border-emerald-200"]
                    ];
                    
                    // เช็คว่ามีตาราง repairs ก่อนไหม ป้องกัน Error
                    $check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");
                    if($check_repairs->num_rows > 0) {
                        foreach($stats as $s) {
                            $c = $conn->query("SELECT count(*) as c FROM repairs ".($s['query'] != "repairs" ? "WHERE {$s['query']}" : ""))->fetch_assoc()['c'];
                            echo "
                            <div class='modern-card p-6 border-b-4 {$s['border']}'>
                                <div class='flex justify-between items-start'>
                                    <div>
                                        <p class='text-slate-500 text-sm font-medium mb-2'>{$s['title']}</p>
                                        <h3 class='text-4xl font-extrabold text-slate-800'>{$c}</h3>
                                    </div>
                                    <div class='w-14 h-14 rounded-2xl {$s['bg']} flex items-center justify-center {$s['color']} shadow-sm'>
                                        <i class='fas {$s['icon']} text-2xl'></i>
                                    </div>
                                </div>
                            </div>";
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
                                    <th class="px-6 py-4">ภาพประกอบ</th>
                                    <th class="px-6 py-4 text-center">สถานะ</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");
                                if($check_repairs->num_rows > 0) {
                                    $res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
                                    if($res->num_rows > 0){
                                        while($row = $res->fetch_assoc()) {
                                            $date = !empty($row['created_at']) ? date("d/m/Y H:i", strtotime($row['created_at'])) : "-";
                                            
                                            $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                            if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                            elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                            elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";

                                            $imageHtml = !empty($row['image_before']) 
                                                ? "<a href='uploads/{$row['image_before']}' target='_blank' class='inline-flex items-center text-xs text-sky-600 hover:text-sky-700 bg-sky-50 hover:bg-sky-100 px-3 py-1.5 rounded-lg font-medium transition-colors border border-sky-100'><i class='fas fa-image mr-1.5'></i> ดูรูปภาพ</a>" 
                                                : "<span class='text-slate-400 text-xs bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100'>ไม่มีรูป</span>";

                                            echo "
                                            <tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                                <td class='px-6 py-4 font-bold text-sky-600'>{$row['ticket_no']}</td>
                                                <td class='px-6 py-4'>
                                                    <div class='text-slate-800 font-semibold'>{$row['reporter_name']}</div>
                                                    <div class='text-slate-500 text-xs mt-1'><i class='fas fa-phone-alt mr-1 text-slate-400'></i> {$row['phone_number']}</div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600 font-medium'>
                                                    <div class='flex items-center'><i class='fas fa-map-marker-alt text-slate-400 mr-2'></i> {$row['location']}</div>
                                                </td>
                                                <td class='px-6 py-4'>
                                                    <div class='text-slate-800 font-semibold'>{$row['equipment_type']}</div>
                                                    <div class='text-slate-500 text-xs mt-1 max-w-[200px] truncate' title='{$row['problem_desc']}'>{$row['problem_desc']}</div>
                                                </td>
                                                <td class='px-6 py-4'>{$imageHtml}</td>
                                                <td class='px-6 py-4 text-center'>
                                                    <span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$statusClass}'>
                                                        <span class='w-1.5 h-1.5 rounded-full bg-current mr-2'></span>{$row['status']}
                                                    </span>
                                                </td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <a href='update_repair.php?id={$row['id']}' class='w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all flex items-center justify-center border border-emerald-100 shadow-sm' title='อัปเดตสถานะ'>
                                                            <i class='fas fa-clipboard-check'></i>
                                                        </a>
                                                        <a href='view_repair.php?id={$row['id']}' class='w-9 h-9 rounded-xl bg-slate-50 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center border border-slate-200 shadow-sm' title='ดูรายละเอียดเต็ม'>
                                                            <i class='fas fa-eye'></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='px-6 py-16 text-center text-slate-400 font-medium'>ยังไม่มีข้อมูลการแจ้งซ่อมในระบบ</td></tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Asset Management Section -->
            <div id="assets" class="section hidden space-y-6">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">ฐานข้อมูลอุปกรณ์และครุภัณฑ์</h2>
                            <p class="text-sm text-slate-500 mt-1">จัดการข้อมูลครุภัณฑ์ภายในคณะสำหรับการแจ้งซ่อม</p>
                        </div>
                        <button onclick="openAddModal()" class="bg-sky-600 hover:bg-sky-500 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-[0_4px_14px_0_rgba(2,132,199,0.39)] hover:shadow-[0_6px_20px_rgba(2,132,199,0.23)] hover:-translate-y-0.5 flex items-center">
                            <i class="fas fa-plus mr-2"></i> เพิ่มอุปกรณ์ใหม่
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">รหัสครุภัณฑ์</th>
                                    <th class="px-6 py-4">ชื่ออุปกรณ์ / รายละเอียด</th>
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

                                        echo "
                                        <tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 font-bold text-sky-600'>{$a['asset_code']}</td>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>{$a['asset_name']}</td>
                                            <td class='px-6 py-4 text-slate-600'>
                                                <span class='bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-medium border border-slate-200'>{$a['category']}</span>
                                            </td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$a_statusClass}'>
                                                    <span class='w-1.5 h-1.5 rounded-full bg-current mr-2'></span>{$a['status']}
                                                </span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"openEditModal('$js_id', '$js_code', '$js_name', '$js_cat', '$js_status')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <button onclick=\"confirmDelete({$a['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'>
                                                        <i class='fas fa-trash-alt'></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='px-6 py-16 text-center text-slate-400'>
                                        <div class='w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100'>
                                            <i class='fas fa-desktop text-2xl text-slate-300'></i>
                                        </div>
                                        <p class='font-medium text-slate-500'>ยังไม่มีข้อมูลครุภัณฑ์ในระบบ</p>
                                    </td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sections อื่นๆ ซ่อนไว้ -->
            <div id="assign" class="section hidden"><h2 class="text-2xl font-bold text-slate-800 mb-6">ระบบรับงานและมอบหมายช่าง</h2></div>
            <div id="users" class="section hidden"><h2 class="text-2xl font-bold text-slate-800 mb-6">จัดการสิทธิ์และผู้ใช้งาน</h2></div>
            <div id="reports" class="section hidden"><h2 class="text-2xl font-bold text-slate-800 mb-6">รายงานสรุปผลการปฏิบัติงาน</h2></div>

        </div>
    </main>

    <!-- Modal เพิ่ม/แก้ไข อุปกรณ์ -->
    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-lg font-bold text-slate-800" id="modalTitle"><i class="fas fa-plus-circle text-sky-500 mr-2"></i> เพิ่มอุปกรณ์ใหม่</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="p-6">
                <!-- ใช้เช็คว่าเป็นบันทึกฟอร์ม -->
                <input type="hidden" name="save_asset" value="1">
                <!-- เก็บ ID กรณีเป็นการแก้ไข -->
                <input type="hidden" name="asset_id" id="asset_id" value="">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">รหัสครุภัณฑ์ <span class="text-red-500">*</span></label>
                        <input type="text" name="asset_code" id="asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">ชื่ออุปกรณ์ <span class="text-red-500">*</span></label>
                        <input type="text" name="asset_name" id="asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label>
                        <select name="category" id="category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all">
                            <option value="IT Support">IT Support (คอม/ปริ้นเตอร์)</option>
                            <option value="ไฟฟ้า/แอร์">ไฟฟ้า/แอร์</option>
                            <option value="อาคารสถานที่">อาคารสถานที่</option>
                            <option value="อื่นๆ">อื่นๆ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">สถานะ</label>
                        <select name="status" id="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all">
                            <option value="ใช้งานปกติ">ใช้งานปกติ</option>
                            <option value="ชำรุด/ส่งซ่อม">ชำรุด/ส่งซ่อม</option>
                            <option value="แทงจำหน่าย">แทงจำหน่าย</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('assetModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2.5 bg-sky-600 text-white rounded-xl text-sm font-bold hover:bg-sky-500 transition-colors shadow-md">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const pageTitles = {
            'dash': 'ภาพรวมระบบ (Dashboard)',
            'repairs': 'ตรวจสอบงานแจ้งซ่อมทั้งหมด',
            'assign': 'ระบบรับงานและมอบหมายช่าง',
            'assets': 'ฐานข้อมูลอุปกรณ์และครุภัณฑ์',
            'users': 'จัดการสิทธิ์และผู้ใช้งาน',
            'reports': 'รายงานสรุปผลการปฏิบัติงาน'
        };

        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'ระบบจัดการ';
        }

        // เช็คว่าโหลดหน้ามาแล้วให้ไปเปิดแท็บไหน (ระบบจำหน้า)
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if(tab) {
                show(tab);
            } else {
                show('dash');
            }
        });

        // เปิด-ปิด Modal ปกติ
        function toggleModal(modalID) {
            document.getElementById(modalID).classList.toggle('opacity-0');
            document.getElementById(modalID).classList.toggle('pointer-events-none');
            document.body.classList.toggle('modal-active');
        }

        // จัดการฟอร์มสำหรับ เพิ่มข้อมูล
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle text-sky-500 mr-2"></i> เพิ่มอุปกรณ์ใหม่';
            document.getElementById('asset_id').value = '';
            document.getElementById('asset_code').value = '';
            document.getElementById('asset_name').value = '';
            document.getElementById('category').value = 'IT Support';
            document.getElementById('status').value = 'ใช้งานปกติ';
            toggleModal('assetModal');
        }

        // จัดการฟอร์มสำหรับ แก้ไขข้อมูล
        function openEditModal(id, code, name, cat, status) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขข้อมูลอุปกรณ์';
            document.getElementById('asset_id').value = id;
            document.getElementById('asset_code').value = code;
            document.getElementById('asset_name').value = name;
            document.getElementById('category').value = cat;
            document.getElementById('status').value = status;
            toggleModal('assetModal');
        }

        // แจ้งเตือนก่อนลบ
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลนี้จะถูกลบออกจากระบบถาวร ไม่สามารถกู้คืนได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl font-bold px-5',
                    cancelButton: 'rounded-xl font-bold px-5'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'dashboard.php?delete_asset=' + id;
                }
            });
        }
    </script>
</body>
</html>