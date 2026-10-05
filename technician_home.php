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

// 1. ดึง ID ช่างล่าสุดจากตาราง users
$res_u = $conn->query("SELECT technician_id FROM users WHERE id = $user_id");
$tech_id = ($res_u && $res_u->num_rows > 0) ? $res_u->fetch_assoc()['technician_id'] : 0;

// 2. ถ้ายังไม่มี tech_id ให้พยายามดึงจากตาราง technicians อัตโนมัติ (Fallback)
if (empty($tech_id)) {
    $safe_name = $conn->real_escape_string($full_name);
    $res_f = $conn->query("SELECT id FROM technicians WHERE full_name = '$safe_name' LIMIT 1");
    if ($res_f && $res_f->num_rows > 0) {
        $tech_id = $res_f->fetch_assoc()['id'];
        $conn->query("UPDATE users SET technician_id = $tech_id WHERE id = $user_id");
    }
}

// 3. ดึง LINE ID ของช่าง
$line_id = '';
if (!empty($tech_id)) {
    $res_l = $conn->query("SELECT line_user_id FROM technicians WHERE id = $tech_id");
    if ($res_l && $res_l->num_rows > 0) {
        $line_id = $res_l->fetch_assoc()['line_user_id'];
    }
}

// 4. สร้างเงื่อนไข "Ultra-Link" ควานหางานจากทุกรูปแบบ
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

// จัดการการอัปเดตหมายเหตุจากหน้าเว็บ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_remark') {
    $repair_id = intval($_POST['repair_id']);
    $remark = $conn->real_escape_string($_POST['remark']);
    
    if($conn->query("UPDATE repairs SET remark = '$remark' WHERE id = $repair_id AND $where")) {
        $msg = "บันทึกหมายเหตุสำเร็จเรียบร้อยครับ";
    }
}

// 5. ดึงสถิติภาพรวม 4 สถานะ
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

// 6. ดึงประวัติรายการแจ้งซ่อมทั้งหมด
$repairs = [];
$res_repairs = $conn->query("SELECT * FROM repairs WHERE $where ORDER BY created_at DESC");
if ($res_repairs) {
    while ($row = $res_repairs->fetch_assoc()) {
        $repairs[] = $row;
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
        body { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body class="bg-slate-50">

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
            <!-- ก้อนที่ 1: งานที่รับผิดชอบทั้งหมด -->
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-purple-500 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['total'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">งานที่รับผิดชอบทั้งหมด</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-50 flex items-center justify-center text-purple-500 text-xl">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
            
            <!-- ก้อนที่ 2: รอรับเรื่อง -->
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-amber-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['pending'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">รอรับเรื่อง</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 text-xl">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <!-- ก้อนที่ 3: กำลังดำเนินการ -->
            <div class="bg-white rounded-xl shadow-sm p-6 border-b-4 border-sky-400 flex items-center justify-between">
                <div>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?= $stats['in_progress'] ?></div>
                    <div class="text-sm text-slate-500 font-medium">กำลังดำเนินการ</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 text-xl">
                    <i class="fas fa-tools"></i>
                </div>
            </div>

            <!-- ก้อนที่ 4: ซ่อมเสร็จแล้ว -->
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

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Table Section -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-white">
                    <h2 class="font-bold text-slate-800 text-lg">ประวัติและรายการใบงานของฉัน</h2>
                </div>
                
                <!-- Table Wrapper (Scrollbar + ธีม Admin) -->
                <div class="overflow-x-auto overflow-y-auto flex-1 max-h-[500px]">
                    <table class="w-full text-left border-collapse relative whitespace-nowrap">
                        <thead class="sticky top-0 bg-[#fef3c7] z-10">
                            <tr class="text-slate-800 text-[11px] tracking-wider uppercase">
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a]">DATE / TIME</th>
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a]">TICKET NO.</th>
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a]">REPORTER</th>
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a]">EQUIPMENT</th>
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a]">ROOT CAUSE</th>
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a]">STATUS</th>
                                <th class="px-4 py-3 font-bold border-b border-[#fde68a] text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-100 bg-white">
                            <?php if (count($repairs) > 0): ?>
                                <?php foreach ($repairs as $job): 
                                    // จัดการรูปแบบวันที่และเวลา
                                    $created_at = !empty($job['created_at']) ? strtotime($job['created_at']) : time();
                                    $date_str = date('Y-m-d', $created_at);
                                    $time_str = date('H:i', $created_at);
                                ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="text-slate-800"><?= $date_str ?></div>
                                            <div class="text-blue-600 font-medium text-xs mt-0.5"><?= $time_str ?></div>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-slate-700"><?= htmlspecialchars($job['repair_code'] ?? 'MR-'.$job['id']) ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($job['reporter_name'] ?? 'ไม่ระบุ') ?></div>
                                            <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($job['reporter_phone'] ?? '-') ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-slate-800"><?= htmlspecialchars($job['equipment'] ?? $job['problem'] ?? 'ไม่ระบุ') ?></div>
                                            <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($job['location'] ?? '-') ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 text-xs max-w-[150px] truncate" title="<?= htmlspecialchars($job['remark'] ?? '') ?>">
                                            <?= !empty($job['remark']) ? htmlspecialchars($job['remark']) : '<span class="text-slate-300">-</span>' ?>
                                        </td>
                                        <td class="px-4 py-3">
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
                                        <td class="px-4 py-3 text-center">
                                            <button onclick="openModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['remark'] ?? '')) ?>')" class="w-8 h-8 rounded border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-indigo-600 transition-colors inline-flex items-center justify-center shadow-sm" title="เพิ่ม/แก้ไขหมายเหตุ">
                                                <i class="fas fa-edit text-sm"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <i class="fas fa-folder-open text-4xl mb-3 text-slate-200 block"></i>
                                        ยังไม่มีประวัติการรับงานในระบบ
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                <div>
                    <h2 class="font-bold text-slate-800 text-lg mb-6">สัดส่วนสถานะการดำเนินงาน</h2>
                </div>
                <div class="relative w-full flex justify-center items-center py-4">
                    <canvas id="jobChart" style="max-height: 250px;"></canvas>
                </div>
                <div class="mt-6 pt-4 text-center text-xs text-slate-400 hidden">
                    ข้อมูลอัปเดตแบบเรียลไทม์จากฐานข้อมูลกลาง
                </div>
            </div>

        </div>
    </div>

    <!-- Modal -->
    <div id="noteModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6 shadow-xl transform transition-all">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-800">เพิ่ม/แก้ไขหมายเหตุ</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_remark">
                <input type="hidden" name="repair_id" id="modal_repair_id" value="">
                
                <div class="mb-5">
                    <textarea name="remark" id="modal_remark" rows="4" class="w-full border border-slate-200 rounded p-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none resize-none text-slate-700" placeholder="พิมพ์ข้อความ..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-sm font-medium transition-colors">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm font-medium transition-colors">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('jobChart').getContext('2d');
        
        const pendingCount = <?= $stats['pending'] ?>;
        const inProgressCount = <?= $stats['in_progress'] ?>;
        const completedCount = <?= $stats['completed'] ?>;
        const totalCount = pendingCount + inProgressCount + completedCount;
        
        let chartData, chartColors;
        
        if (totalCount === 0) {
            chartData = [1];
            chartColors = ['#e2e8f0'];
        } else {
            chartData = [pendingCount, inProgressCount, completedCount];
            chartColors = ['#fbbf24', '#38bdf8', '#10b981'];
        }

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: totalCount === 0 ? ['ไม่มีข้อมูล'] : ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                datasets: [{
                    data: chartData,
                    backgroundColor: chartColors,
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
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { family: "'Prompt', sans-serif", size: 12 }
                        }
                    },
                    tooltip: { enabled: totalCount > 0 }
                }
            }
        });

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