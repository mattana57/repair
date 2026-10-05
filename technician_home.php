<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ ต้องเป็น Technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician' || empty($_SESSION['technician_id'])) {
    header("Location: login.php");
    exit();
}

$tech_id = $_SESSION['technician_id'];
$full_name = $_SESSION['full_name'];

// จัดการการอัปเดตหมายเหตุจากหน้าเว็บ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_remark') {
    $repair_id = intval($_POST['repair_id']);
    $remark = $_POST['remark'];
    $stmt = $conn->prepare("UPDATE repairs SET remark = ? WHERE id = ? AND technician_id = ?");
    $stmt->bind_param("sii", $remark, $repair_id, $tech_id);
    if ($stmt->execute()) {
        $msg = "บันทึกหมายเหตุสำเร็จเรียบร้อยครับ";
    }
    $stmt->close();
}

// 1. ดึงสถิติภาพรวม 4 สถานะ
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
$stmt_stats = $conn->prepare("SELECT status, COUNT(*) as count FROM repairs WHERE technician_id = ? GROUP BY status");
$stmt_stats->bind_param("i", $tech_id);
$stmt_stats->execute();
$res_stats = $stmt_stats->get_result();
while ($row = $res_stats->fetch_assoc()) {
    $stats['total'] += $row['count'];
    $db_status = trim($row['status']);
    if ($db_status === 'รอดำเนินการ' || $db_status === 'รอรับเรื่อง') {
        $stats['pending'] += $row['count'];
    } elseif ($db_status === 'กำลังดำเนินการ') {
        $stats['in_progress'] += $row['count'];
    } elseif ($db_status === 'เสร็จสิ้น' || $db_status === 'ซ่อมเสร็จแล้ว') {
        $stats['completed'] += $row['count'];
    }
}
$stmt_stats->close();

// 2. ดึงประวัติรายการแจ้งซ่อมทั้งหมดของช่างคนนี้
$repairs = [];
$stmt_repairs = $conn->prepare("SELECT * FROM repairs WHERE technician_id = ? ORDER BY created_at DESC");
$stmt_repairs->bind_param("i", $tech_id);
$stmt_repairs->execute();
$res_repairs = $stmt_repairs->get_result();
while ($row = $res_repairs->fetch_assoc()) {
    $repairs[] = $row;
}
$stmt_repairs->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard | MBS Repair</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-700">

    <!-- Top Navbar -->
    <nav class="bg-indigo-600 text-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl">
                        <i class="fas fa-tools text-xl"></i>
                    </div>
                    <div>
                        <span class="font-bold text-lg tracking-wide">MBS <span class="font-light">Technician Portal</span></span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-indigo-700/50 px-3 py-1.5 rounded-xl border border-indigo-500/30">
                        <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-sm">
                            <?= mb_substr($full_name, 0, 1) ?>
                        </div>
                        <span class="text-sm font-medium hidden sm:block"><?= htmlspecialchars($full_name) ?></span>
                    </div>
                    <a href="logout.php" class="bg-rose-500 hover:bg-rose-600 px-3.5 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if (!empty($msg)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle mr-2.5 text-lg"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">ยินดีต้อนรับกลับ, คุณ<?= htmlspecialchars($full_name) ?> 👋</h1>
                <p class="text-indigo-100 text-sm max-w-xl font-light">จัดการใบงาน ตรวจสอบสถิติ และอัปเดตหมายเหตุงานซ่อมของคุณได้จากแดชบอร์ดส่วนตัวนี้ หรือกดรับงานผ่าน LINE Bot ตามปกติ</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
                <span class="block text-xs uppercase tracking-wider text-indigo-200 mb-1">สถานะระบบ</span>
                <span class="flex items-center gap-2 font-bold text-sm"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> พร้อมปฏิบัติงาน</span>
            </div>
        </div>

        <!-- Stats Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-purple-500 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['total'] ?></div>
                    <div class="text-sm text-slate-500 font-bold">งานที่รับผิดชอบทั้งหมด</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-500 text-xl">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-amber-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['pending'] ?></div>
                    <div class="text-sm text-slate-500 font-bold">รอรับเรื่อง</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 text-xl">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-sky-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['in_progress'] ?></div>
                    <div class="text-sm text-slate-500 font-bold">กำลังดำเนินการ</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 text-xl">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-emerald-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['completed'] ?></div>
                    <div class="text-sm text-slate-500 font-bold">ซ่อมเสร็จแล้ว</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
        </div>

        <!-- ปุ่มเมนูแท็บแบบ Capsule เหมือนฝั่ง Admin -->
        <div class="flex items-center gap-2.5 mb-8">
            <a href="technician_home.php" class="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-full border border-indigo-600 shadow-md shadow-indigo-200 transition-colors cursor-pointer">
                ทั้งหมด
            </a>
            <a href="#" class="px-6 py-2 bg-white text-slate-600 text-sm font-bold rounded-full shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors cursor-pointer" onclick="alert('ขณะนี้คุณอยู่ในหน้าดูภาพรวมทั้งหมดแล้วครับ');">
                ประวัติงาน
            </a>
        </div>

        <!-- ========================================== -->
        <!-- กราฟแถวที่ 1: Equipment & Status -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Line Chart -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="mb-6">
                    <h2 class="text-lg font-extrabold text-slate-800">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</h2>
                    <p class="text-xs font-medium text-slate-400 mt-0.5">สถิติอุปกรณ์ที่คุณได้รับมอบหมาย</p>
                </div>
                <div class="h-64 w-full">
                    <canvas id="eqChart"></canvas>
                </div>
            </div>
            
            <!-- Doughnut Chart -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="mb-6">
                    <h2 class="text-lg font-extrabold text-slate-800">สัดส่วนสถานะการดำเนินงาน</h2>
                    <p class="text-xs font-medium text-slate-400 mt-0.5">สถานะงานซ่อมทั้งหมดของคุณ</p>
                </div>
                <div class="h-64 w-full flex justify-center pb-4">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- กราฟแถวที่ 2: Locations & Top Reporters -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Bar Chart (Horizontal) -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="mb-6">
                    <h2 class="text-lg font-extrabold text-slate-800">สถานที่เกิดปัญหาบ่อยที่สุด</h2>
                    <p class="text-xs font-medium text-slate-400 mt-0.5">ห้องหรืออาคารที่คุณไปซ่อมบ่อยๆ</p>
                </div>
                <div class="h-64 w-full">
                    <canvas id="locChart"></canvas>
                </div>
            </div>

            <!-- Top Reporters List -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                <div class="mb-6">
                    <h2 class="text-lg font-extrabold text-slate-800">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</h2>
                    <p class="text-xs font-medium text-slate-400 mt-0.5">รายชื่อผู้ใช้งานที่คุณให้บริการบ่อย</p>
                </div>
                <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar" id="topReportersContainer">
                    <!-- Javascript จะวาดรายชื่อตรงนี้ -->
                </div>
            </div>
        </div>

        <!-- Table: รายการรับแจ้งซ่อมล่าสุด -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-slate-100 bg-white flex justify-between items-center">
                <div>
                    <h2 class="font-extrabold text-slate-800 text-lg">รายการรับแจ้งซ่อมล่าสุด</h2>
                    <p class="text-xs font-medium text-slate-400 mt-0.5">ประวัติการทำงานของคุณ</p>
                </div>
            </div>
            <div class="overflow-x-auto overflow-y-auto flex-1 max-h-[500px] custom-scrollbar">
                <table class="w-full text-left border-collapse relative whitespace-nowrap min-w-[900px]">
                    <thead class="sticky top-0 bg-[#fef9c3] z-10 shadow-sm border-b border-[#fef08a]">
                        <tr class="text-[#854d0e] text-xs tracking-widest uppercase font-extrabold">
                            <th class="px-6 py-4">DATE / TIME</th>
                            <th class="px-6 py-4">TICKET NO.</th>
                            <th class="px-6 py-4">REPORTER</th>
                            <th class="px-6 py-4">EQUIPMENT</th>
                            <th class="px-6 py-4 text-center">STATUS</th>
                            <th class="px-6 py-4 text-center">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100 bg-white">
                        <?php if (count($repairs) > 0): ?>
                            <?php foreach ($repairs as $job): 
                                $created_at = !empty($job['created_at']) ? strtotime($job['created_at']) : time();
                                $date_str = date('Y-m-d', $created_at);
                                $time_str = date('H:i', $created_at);
                                
                                $status = trim($job['status'] ?? 'รอดำเนินการ');
                                if ($status === 'เสร็จสิ้น' || $status === 'ซ่อมเสร็จแล้ว') {
                                    $badgeClass = 'text-emerald-600 bg-emerald-50 border-emerald-200';
                                } elseif ($status === 'กำลังดำเนินการ') {
                                    $badgeClass = 'text-sky-600 bg-sky-50 border-sky-200';
                                } else {
                                    $badgeClass = 'text-amber-600 bg-amber-50 border-amber-200';
                                }
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-slate-800 font-bold"><?= $date_str ?></div>
                                        <div class="text-blue-600 font-bold text-[11px] mt-0.5"><?= $time_str ?></div>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-slate-600"><?= htmlspecialchars($job['repair_code'] ?? 'MR-'.$job['id']) ?></td>
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-400 flex items-center justify-center text-xs"><i class="fas fa-user"></i></div>
                                        <div>
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($job['reporter_name'] ?? 'ไม่ระบุ') ?></div>
                                            <div class="text-[11px] text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($job['reporter_phone'] ?? '-') ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800">
                                            <?= htmlspecialchars($job['equipment'] ?? $job['problem'] ?? 'ไม่ระบุ') ?>
                                            <?php if(isset($job['image_path']) && !empty($job['image_path'])): ?>
                                                <i class="fas fa-image text-slate-300 ml-1" title="มีรูปภาพ"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[11px] text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($job['location'] ?? '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?= $badgeClass ?> inline-block shadow-sm"><?= htmlspecialchars($status) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="openModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['remark'] ?? '')) ?>')" class="w-8 h-8 rounded border border-slate-200 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors inline-flex items-center justify-center shadow-sm" title="เพิ่ม/แก้ไขหมายเหตุ">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <i class="fas fa-folder-open text-4xl mb-3 text-slate-200 block"></i>
                                    ยังไม่มีประวัติการรับงานในระบบ
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal เพิ่ม/แก้ไขหมายเหตุ -->
    <div id="noteModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6 shadow-xl transform transition-all">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-800">เพิ่ม/แก้ไขหมายเหตุ</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-rose-500 transition-colors bg-slate-50 hover:bg-rose-50 w-8 h-8 rounded-full flex justify-center items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_remark">
                <input type="hidden" name="repair_id" id="modal_repair_id" value="">
                
                <div class="mb-5">
                    <textarea name="remark" id="modal_remark" rows="4" class="w-full border border-slate-200 rounded-xl p-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none resize-none text-slate-700 bg-slate-50" placeholder="พิมพ์ข้อความที่ต้องการแจ้งให้ผู้ใช้งานทราบ..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-bold transition-colors">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-md shadow-indigo-200 transition-all">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Javascript สำหรับวาดกราฟทั้งหมด (คำนวณจาก Array ของช่างโดยตรง) -->
    <script>
        const repairsData = <?php echo json_encode($repairs); ?>;
        
        Chart.defaults.font.family = "'Prompt', sans-serif";
        Chart.defaults.color = '#64748b';

        function renderAllCharts() {
            let pending = 0, progress = 0, completed = 0;
            let eqMap = {}, locMap = {}, reporterMap = {};

            repairsData.forEach(r => {
                // จัดการข้อมูลสถานะ
                let st = (r.status || '').trim();
                if (st === 'รอรับเรื่อง' || st === 'รอดำเนินการ') pending++;
                else if (st === 'กำลังดำเนินการ') progress++;
                else if (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') completed++;

                // จัดการข้อมูลอุปกรณ์
                let eq = r.equipment || r.problem || 'ไม่ระบุ';
                eqMap[eq] = (eqMap[eq] || 0) + 1;

                // จัดการข้อมูลสถานที่
                let loc = r.location || 'ไม่ระบุสถานที่';
                if (loc !== 'ไม่ระบุสถานที่' && loc.trim() !== '') {
                    locMap[loc] = (locMap[loc] || 0) + 1;
                }

                // จัดการข้อมูลผู้แจ้งซ่อม
                let repName = r.reporter_name || 'ไม่ระบุผู้แจ้ง';
                reporterMap[repName] = (reporterMap[repName] || 0) + 1;
            });

            // ==========================================
            // 1. Equipment Chart (Line)
            let eqSorted = Object.keys(eqMap).map(k => ({name: k, count: eqMap[k]})).sort((a,b) => b.count - a.count).slice(0, 6);
            const eqCtx = document.getElementById('eqChart').getContext('2d');
            let gradient = eqCtx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            new Chart(eqCtx, {
                type: 'line',
                data: {
                    labels: eqSorted.length ? eqSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        data: eqSorted.length ? eqSorted.map(e => e.count) : [0],
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
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        x: { grid: { display: false }, border: {display: false}, ticks: { font: { weight: 'bold' } } }
                    }
                }
            });

            // ==========================================
            // 2. Status Chart (Doughnut)
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            let totalStatus = pending + progress + completed;
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: totalStatus === 0 ? ['ไม่มีข้อมูล'] : ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                    datasets: [{
                        data: totalStatus === 0 ? [1] : [pending, progress, completed],
                        backgroundColor: totalStatus === 0 ? ['#f1f5f9'] : ['#fbbf24', '#38bdf8', '#10b981'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: 'bold' } } },
                        tooltip: { enabled: totalStatus > 0 }
                    }
                }
            });

            // ==========================================
            // 3. Location Chart (Horizontal Bar)
            let locSorted = Object.keys(locMap).map(k => ({name: k, count: locMap[k]})).sort((a,b) => b.count - a.count).slice(0, 5);
            const locCtx = document.getElementById('locChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: locSorted.length ? locSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        data: locSorted.length ? locSorted.map(e => e.count) : [0],
                        backgroundColor: '#f43f5e',
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        y: { grid: { display: false }, border: {display: false}, ticks: { font: { weight: 'bold' } } }
                    }
                }
            });

            // ==========================================
            // 4. Render Top Reporters List (List HTML)
            let repSorted = Object.keys(reporterMap).map(k => ({name: k, count: reporterMap[k]})).sort((a,b) => b.count - a.count).slice(0, 5);
            const repContainer = document.getElementById('topReportersContainer');
            if (repSorted.length === 0) {
                repContainer.innerHTML = `<div class='py-10 flex flex-col items-center justify-center text-center'>
                    <div class='w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mb-3'><i class='fas fa-user-tag text-2xl text-slate-300'></i></div>
                    <p class='text-slate-400 font-bold text-sm'>ยังไม่มีผู้แจ้งซ่อม</p>
                </div>`;
            } else {
                let html = '<ul class="divide-y divide-slate-100">';
                repSorted.forEach((r, index) => {
                    let rankIcon = index === 0 ? '<i class="fas fa-trophy text-lg text-amber-500 drop-shadow-sm"></i>' :
                                   index === 1 ? '<i class="fas fa-medal text-lg text-slate-400 drop-shadow-sm"></i>' :
                                   index === 2 ? '<i class="fas fa-medal text-lg text-amber-600 drop-shadow-sm"></i>' :
                                   `<span class="text-sm font-black text-indigo-500">#${index + 1}</span>`;
                    let rankBg = index === 0 ? 'bg-[#fefce8] border-[#fde047]' : 
                                 index === 1 ? 'bg-[#f8fafc] border-[#e2e8f0]' : 
                                 index === 2 ? 'bg-[#fffbeb] border-[#fde68a]' : 'bg-indigo-50 border-indigo-100';
                    
                    html += `
                        <li class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center border shadow-sm shrink-0 ${rankBg}">
                                    ${rankIcon}
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 text-sm">${r.name}</p>
                                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">บุคลากรผู้แจ้งซ่อม</p>
                                </div>
                            </div>
                            <div class="text-right flex items-center gap-3">
                                <div>
                                    <p class="font-extrabold text-indigo-600 text-xl leading-none">${r.count}</p>
                                    <p class="text-[10px] text-slate-400 font-medium mt-1">รายการ</p>
                                </div>
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                repContainer.innerHTML = html;
            }
        }

        // วาดกราฟทันทีเมื่อโหลดหน้าเว็บเสร็จ
        document.addEventListener('DOMContentLoaded', renderAllCharts);

        // ควบคุม Modal หมายเหตุ
        const modal = document.getElementById('noteModal');
        const repairInput = document.getElementById('modal_repair_id');
        const remarkInput = document.getElementById('modal_remark');

        function openModal(id, currentRemark) {
            repairInput.value = id;
            remarkInput.value = currentRemark;
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>