<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์เบื้องต้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// 1. ดึง ID ช่าง
$res_u = $conn->query("SELECT technician_id FROM users WHERE id = $user_id");
$tech_id = ($res_u && $res_u->num_rows > 0) ? $res_u->fetch_assoc()['technician_id'] : 0;

if (empty($tech_id)) {
    $safe_name = $conn->real_escape_string($full_name);
    $res_f = $conn->query("SELECT id FROM technicians WHERE full_name = '$safe_name' LIMIT 1");
    if ($res_f && $res_f->num_rows > 0) {
        $tech_id = $res_f->fetch_assoc()['id'];
        $conn->query("UPDATE users SET technician_id = $tech_id WHERE id = $user_id");
    }
}

$line_id = '';
if (!empty($tech_id)) {
    $res_l = $conn->query("SELECT line_user_id FROM technicians WHERE id = $tech_id");
    if ($res_l && $res_l->num_rows > 0) {
        $line_id = $res_l->fetch_assoc()['line_user_id'];
    }
}

// 2. สร้างเงื่อนไข "Ultra-Link" เฉพาะงานของช่างคนนี้
$safe_tech_id = intval($tech_id);
$safe_line_id = $conn->real_escape_string($line_id);
$safe_full_name = $conn->real_escape_string($full_name);

$where = "(technician_id = '$safe_tech_id'";
if (!empty($safe_line_id)) {
    $where .= " OR technician_id = '$safe_line_id'";
}
if ($conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'")->num_rows > 0) {
    $where .= " OR technician_name = '$safe_full_name'";
}
$where .= ")";

// 3. ดึงสถิติภาพรวม 4 สถานะ
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
$res_stats = $conn->query("SELECT status, COUNT(*) as count FROM repairs WHERE $where GROUP BY status");
if ($res_stats) {
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
}

// 4. ข้อมูลกราฟ: อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด (Equipment)
$equipment_labels = [];
$equipment_data = [];
$res_eq = $conn->query("SELECT IFNULL(equipment, problem) as eq_name, COUNT(*) as count FROM repairs WHERE $where GROUP BY eq_name ORDER BY count DESC LIMIT 6");
if ($res_eq) {
    while ($row = $res_eq->fetch_assoc()) {
        $equipment_labels[] = $row['eq_name'];
        $equipment_data[] = $row['count'];
    }
}

// 5. ข้อมูลกราฟ: สถานที่เกิดปัญหาบ่อยที่สุด (Top Locations)
$location_labels = [];
$location_data = [];
$res_loc = $conn->query("SELECT location, COUNT(*) as count FROM repairs WHERE $where AND location IS NOT NULL AND location != '' GROUP BY location ORDER BY count DESC LIMIT 5");
if ($res_loc) {
    while ($row = $res_loc->fetch_assoc()) {
        $location_labels[] = $row['location'];
        $location_data[] = $row['count'];
    }
}

// 6. ข้อมูล: ผู้แจ้งซ่อมบ่อยที่สุด (Top Reporters) - ดึงแค่ 5 อันดับ
$top_reporters = [];
$res_rep = $conn->query("SELECT reporter_name, reporter_phone, COUNT(*) as count FROM repairs WHERE $where GROUP BY reporter_name, reporter_phone ORDER BY count DESC LIMIT 5");
if ($res_rep) {
    while ($row = $res_rep->fetch_assoc()) {
        $top_reporters[] = $row;
    }
}

// 7. ข้อมูลตาราง: 5 งานล่าสุด (Recent Transactions)
$recent_repairs = [];
$res_recent = $conn->query("SELECT * FROM repairs WHERE $where ORDER BY created_at DESC LIMIT 5");
if ($res_recent) {
    while ($row = $res_recent->fetch_assoc()) {
        $recent_repairs[] = $row;
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        /* สไตล์ไอคอนจัดอันดับ */
        .rank-icon-1 { color: #fbbf24; } /* ทอง */
        .rank-icon-2 { color: #94a3b8; } /* เงิน */
        .rank-icon-3 { color: #b45309; } /* ทองแดง */
        .rank-icon-other { background-color: #e0e7ff; color: #4f46e5; border-radius: 50%; font-size: 0.75rem; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
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

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">ยินดีต้อนรับกลับ, คุณ<?= htmlspecialchars($full_name) ?> 👋</h1>
                <p class="text-indigo-100 text-sm max-w-xl font-light">จัดการใบงาน ตรวจสอบสถิติส่วนตัว และดูผลการดำเนินงานของคุณได้จากหน้านี้</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
                <span class="block text-xs uppercase tracking-wider text-indigo-200 mb-1">สถานะระบบ</span>
                <span class="flex items-center gap-2 font-bold text-sm"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> พร้อมปฏิบัติงาน</span>
            </div>
        </div>

        <!-- 4 Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-purple-500 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['total'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">งานที่รับผิดชอบทั้งหมด</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-500 text-xl">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-amber-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['pending'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">รอรับเรื่อง</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 text-xl">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-sky-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['in_progress'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">กำลังดำเนินการ</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 text-xl">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-emerald-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['completed'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">ซ่อมเสร็จแล้ว</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
        </div>

        <!-- ปุ่มเมนูแท็บ (ทั้งหมด / ประวัติงาน) -->
        <div class="flex items-center gap-3 mb-8">
            <a href="technician_home.php" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
                ทั้งหมด
            </a>
            <a href="technician_history.php" class="px-6 py-2.5 bg-white text-slate-600 text-sm font-medium rounded-lg shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors">
                ประวัติงาน
            </a>
        </div>

        <!-- Charts Row 1: Line Chart & Doughnut -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Line Chart -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</h2>
                        <p class="text-xs text-slate-400">สถิติอุปกรณ์ที่คุณได้รับมอบหมาย</p>
                    </div>
                </div>
                <div class="h-64 w-full">
                    <canvas id="eqChart"></canvas>
                </div>
            </div>
            
            <!-- Doughnut Chart -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">สัดส่วนสถานะการดำเนินงาน</h2>
                        <p class="text-xs text-slate-400">สถานะงานซ่อมทั้งหมดของคุณ</p>
                    </div>
                </div>
                <div class="h-64 w-full flex justify-center pb-4">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2: Bar Chart & Top Reporters -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Bar Chart (Horizontal) -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">สถานที่เกิดปัญหาบ่อยที่สุด</h2>
                        <p class="text-xs text-slate-400">ห้องหรืออาคารที่คุณไปซ่อมบ่อยๆ</p>
                    </div>
                </div>
                <div class="h-64 w-full">
                    <canvas id="locChart"></canvas>
                </div>
            </div>

            <!-- Top Reporters List -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex flex-col">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</h2>
                        <p class="text-xs text-slate-400">รายชื่อผู้ใช้งานที่คุณให้บริการบ่อย</p>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto pr-2">
                    <?php if (count($top_reporters) > 0): ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach($top_reporters as $index => $rep): ?>
                                <li class="py-3 flex justify-between items-center">
                                    <div class="flex items-center gap-4">
                                        <?php if($index == 0): ?>
                                            <i class="fas fa-trophy rank-icon-1 text-2xl w-8 text-center"></i>
                                        <?php elseif($index == 1): ?>
                                            <i class="fas fa-medal rank-icon-2 text-2xl w-8 text-center"></i>
                                        <?php elseif($index == 2): ?>
                                            <i class="fas fa-medal rank-icon-3 text-2xl w-8 text-center"></i>
                                        <?php else: ?>
                                            <div class="w-8 flex justify-center"><div class="rank-icon-other">#<?= $index + 1 ?></div></div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($rep['reporter_name'] ?? 'ไม่ระบุ') ?></p>
                                            <p class="text-xs text-slate-400">บุคลากรผู้แจ้งซ่อม</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-indigo-600 text-lg"><?= $rep['count'] ?></p>
                                        <p class="text-[10px] text-slate-400 uppercase">รายการ</p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center text-slate-400 py-10 text-sm">ยังไม่มีข้อมูลผู้แจ้งซ่อม</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden mb-12">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-slate-800 text-lg">รายการรับแจ้งซ่อมล่าสุด</h2>
                    <p class="text-xs text-slate-400">5 งานล่าสุดในระบบของคุณ</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead class="bg-[#fef3c7]">
                        <tr class="text-slate-700 text-xs tracking-wider uppercase">
                            <th class="px-6 py-4 font-bold border-b border-[#fde68a]">DATE / TIME</th>
                            <th class="px-6 py-4 font-bold border-b border-[#fde68a]">TICKET NO.</th>
                            <th class="px-6 py-4 font-bold border-b border-[#fde68a]">REPORTER</th>
                            <th class="px-6 py-4 font-bold border-b border-[#fde68a]">EQUIPMENT</th>
                            <th class="px-6 py-4 font-bold border-b border-[#fde68a]">STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php if (count($recent_repairs) > 0): ?>
                            <?php foreach ($recent_repairs as $job): 
                                $created_at = !empty($job['created_at']) ? strtotime($job['created_at']) : time();
                                $date_str = date('Y-m-d', $created_at);
                                $time_str = date('H:i', $created_at);
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-slate-800"><?= $date_str ?></div>
                                        <div class="text-blue-600 font-medium text-xs mt-0.5"><?= $time_str ?></div>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-slate-600"><?= htmlspecialchars($job['repair_code'] ?? 'MR-'.$job['id']) ?></td>
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-400 flex items-center justify-center text-xs"><i class="fas fa-user"></i></div>
                                        <div>
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($job['reporter_name'] ?? 'ไม่ระบุ') ?></div>
                                            <div class="text-xs text-slate-400"><?= htmlspecialchars($job['reporter_phone'] ?? '-') ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-slate-800 flex items-center gap-1.5">
                                            <?= htmlspecialchars($job['equipment'] ?? $job['problem'] ?? 'ไม่ระบุ') ?>
                                            <i class="far fa-image text-slate-300"></i>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                            $status = trim($job['status'] ?? 'รอดำเนินการ');
                                            if ($status === 'เสร็จสิ้น' || $status === 'ซ่อมเสร็จแล้ว') {
                                                $badgeClass = 'text-emerald-600 bg-emerald-50 border-emerald-200';
                                            } elseif ($status === 'กำลังดำเนินการ') {
                                                $badgeClass = 'text-blue-600 bg-blue-50 border-blue-200';
                                            } else {
                                                $badgeClass = 'text-amber-600 bg-amber-50 border-amber-200';
                                            }
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?= $badgeClass ?> inline-block"><?= htmlspecialchars($status) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400 text-sm">ยังไม่มีประวัติการรับงาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Scripts for Charts -->
    <script>
        Chart.defaults.font.family = "'Prompt', sans-serif";
        Chart.defaults.color = '#64748b';

        // 1. Line Chart (Equipment)
        const eqCtx = document.getElementById('eqChart').getContext('2d');
        new Chart(eqCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($equipment_labels) ?>,
                datasets: [{
                    label: 'จำนวนครั้ง (งานของคุณ)',
                    data: <?= json_encode($equipment_data) ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.15)',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#8b5cf6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#f1f5f9' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });

        // 2. Doughnut Chart (Status)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const pendingCount = <?= $stats['pending'] ?>;
        const inProgressCount = <?= $stats['in_progress'] ?>;
        const completedCount = <?= $stats['completed'] ?>;
        const totalCount = pendingCount + inProgressCount + completedCount;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: totalCount === 0 ? ['ไม่มีข้อมูล'] : ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                datasets: [{
                    data: totalCount === 0 ? [1] : [pendingCount, inProgressCount, completedCount],
                    backgroundColor: totalCount === 0 ? ['#f1f5f9'] : ['#fbbf24', '#38bdf8', '#10b981'],
                    borderWidth: 0,
                    hoverOffset: totalCount === 0 ? 0 : 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { 
                        display: totalCount > 0,
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 20, font: { size: 12 } }
                    },
                    tooltip: { enabled: totalCount > 0 }
                }
            }
        });

        // 3. Horizontal Bar Chart (Locations)
        const locCtx = document.getElementById('locChart').getContext('2d');
        new Chart(locCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($location_labels) ?>,
                datasets: [{
                    label: 'จำนวนครั้ง',
                    data: <?= json_encode($location_data) ?>,
                    backgroundColor: '#fb7185',
                    borderRadius: 4,
                    barThickness: 16
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { display: false }, border: { display: false } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { font: { weight: 'bold' } } }
                }
            }
        });
    </script>
</body>
</html>