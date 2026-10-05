<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ ต้องเป็น Technician เท่านั้น และต้องมี technician_id
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician' || empty($_SESSION['technician_id'])) {
    header("Location: login.php");
    exit();
}

$tech_id = $_SESSION['technician_id'];
$full_name = $_SESSION['full_name'];

// จัดการการอัปเดตหมายเหตุจากหน้าเว็บ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_remark') {
    $repair_id = $_POST['repair_id'];
    $remark = $_POST['remark'];
    
    $stmt = $conn->prepare("UPDATE repairs SET remark = ? WHERE id = ? AND technician_id = ?");
    $stmt->bind_param("sii", $remark, $repair_id, $tech_id);
    if ($stmt->execute()) {
        $msg = "บันทึกหมายเหตุสำเร็จเรียบร้อยครับ";
    }
    $stmt->close();
}

// 1. ดึงสถิติภาพรวม 4 สถานะ (เฉพาะของช่างคนนี้)
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 font-sans">

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
        
        <!-- Alert Notification -->
        <?php if (!empty($msg)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle mr-2.5 text-lg"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-xl mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold mb-2">ยินดีต้อนรับกลับ, คุณ<?= htmlspecialchars($full_name) ?> 👋</h1>
                <p class="text-indigo-100 text-sm max-w-xl">จัดการใบงาน ตรวจสอบสถิติ และอัปเดตหมายเหตุงานซ่อมของคุณได้จากแดชบอร์ดส่วนตัวนี้ หรือกดรับงานผ่าน LINE Bot ตามปกติ</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
                <span class="block text-xs uppercase tracking-wider text-indigo-200">สถานะระบบ</span>
                <span class="flex items-center gap-2 font-bold text-sm mt-0.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> พร้อมปฏิบัติงาน</span>
            </div>
        </div>

        <!-- Stats Cards Grid (4 ก้อน ใช้สีตามฝั่งแอดมิน) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- ก้อนที่ 1: งานที่รับผิดชอบทั้งหมด (สีฟ้า/น้ำเงิน) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex items-center gap-4 border-l-4 border-l-sky-500">
                <div class="bg-sky-50 text-sky-600 w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase font-bold tracking-wider">งานที่รับผิดชอบทั้งหมด</p>
                    <p class="text-3xl font-extrabold text-slate-800 mt-1"><?= $stats['total'] ?> <span class="text-sm font-normal text-slate-400">รายการ</span></p>
                </div>
            </div>
            
            <!-- ก้อนที่ 2: รอรับเรื่อง (สีส้ม/เหลือง) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex items-center gap-4 border-l-4 border-l-amber-500">
                <div class="bg-amber-50 text-amber-600 w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase font-bold tracking-wider">รอรับเรื่อง</p>
                    <p class="text-3xl font-extrabold text-slate-800 mt-1"><?= $stats['pending'] ?> <span class="text-sm font-normal text-slate-400">รายการ</span></p>
                </div>
            </div>

            <!-- ก้อนที่ 3: กำลังดำเนินการ (สีม่วง/น้ำเงินเข้ม) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex items-center gap-4 border-l-4 border-l-indigo-600">
                <div class="bg-indigo-50 text-indigo-600 w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase font-bold tracking-wider">กำลังดำเนินการ</p>
                    <p class="text-3xl font-extrabold text-slate-800 mt-1"><?= $stats['in_progress'] ?> <span class="text-sm font-normal text-slate-400">รายการ</span></p>
                </div>
            </div>

            <!-- ก้อนที่ 4: ซ่อมเสร็จแล้ว (สีเขียว) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex items-center gap-4 border-l-4 border-l-emerald-500">
                <div class="bg-emerald-50 text-emerald-600 w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shadow-inner">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase font-bold tracking-wider">ซ่อมเสร็จแล้ว</p>
                    <p class="text-3xl font-extrabold text-slate-800 mt-1"><?= $stats['completed'] ?> <span class="text-sm font-normal text-slate-400">รายการ</span></p>
                </div>
            </div>
        </div>

        <!-- Main Content Area (Table & Chart) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Table Section (2/3 Width) -->
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h2 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-history text-indigo-500"></i> ประวัติและรายการใบงานของฉัน
                    </h2>
                    <span class="text-xs bg-indigo-50 text-indigo-600 font-semibold px-3 py-1 rounded-full"><?= count($repairs) ?> งานล่าสุด</span>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-400 text-[11px] uppercase tracking-wider">
                                <th class="p-4 font-bold border-b border-slate-100">รหัสงาน</th>
                                <th class="p-4 font-bold border-b border-slate-100">ปัญหา / สถานที่</th>
                                <th class="p-4 font-bold border-b border-slate-100">หมายเหตุช่าง</th>
                                <th class="p-4 font-bold border-b border-slate-100">สถานะ</th>
                                <th class="p-4 font-bold border-b border-slate-100 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50">
                            <?php if (count($repairs) > 0): ?>
                                <?php foreach ($repairs as $job): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="p-4 font-bold text-indigo-600">#<?= htmlspecialchars($job['repair_code'] ?? $job['id']) ?></td>
                                        <td class="p-4">
                                            <div class="font-semibold text-slate-800"><?= htmlspecialchars($job['problem'] ?? 'ไม่ระบุ') ?></div>
                                            <div class="text-xs text-slate-400 mt-0.5"><i class="fas fa-map-marker-alt text-rose-400 mr-1"></i> <?= htmlspecialchars($job['location'] ?? '-') ?></div>
                                        </td>
                                        <td class="p-4 text-slate-600 text-xs">
                                            <?= !empty($job['remark']) ? htmlspecialchars($job['remark']) : '<span class="text-slate-300 italic">ยังไม่มีหมายเหตุ</span>' ?>
                                        </td>
                                        <td class="p-4">
                                            <?php 
                                                $status = $job['status'] ?? 'รอดำเนินการ';
                                                $badgeClass = ($status === 'เสร็จสิ้น' || $status === 'ซ่อมเสร็จแล้ว') ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
                                            ?>
                                            <span class="<?= $badgeClass ?> px-3 py-1 rounded-full text-xs font-bold inline-block"><?= $status ?></span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <button onclick="openModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['remark'] ?? '')) ?>')" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 transition-colors inline-flex items-center justify-center shadow-sm" title="เพิ่ม/แก้ไขหมายเหตุ">
                                                <i class="fas fa-pen text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        <i class="fas fa-folder-open text-4xl mb-3 text-slate-200 block"></i>
                                        ยังไม่มีประวัติการรับงานในระบบ
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Chart Section (1/3 Width) -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between">
                <div>
                    <h2 class="font-bold text-slate-800 mb-1 flex items-center gap-2">
                        <i class="fas fa-chart-pie text-indigo-500"></i> สัดส่วนประสิทธิภาพงาน
                    </h2>
                    <p class="text-xs text-slate-400 mb-6">เปรียบเทียบงานที่กำลังทำและเสร็จสิ้นแล้ว</p>
                </div>
                <div class="relative w-full flex justify-center items-center py-4">
                    <canvas id="jobChart" style="max-height: 220px;"></canvas>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-400">
                    ข้อมูลอัปเดตแบบเรียลไทม์จากฐานข้อมูลกลาง
                </div>
            </div>

        </div>

    </div>

    <!-- Modal สำหรับเพิ่ม/แก้ไขหมายเหตุ -->
    <div id="noteModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl w-full max-w-md p-6 shadow-2xl transform transition-all">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-comment-medical text-indigo-500"></i> จัดการหมายเหตุใบงาน
                </h3>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-colors flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_remark">
                <input type="hidden" name="repair_id" id="modal_repair_id" value="">
                
                <div class="mb-5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">รายละเอียดหมายเหตุ / ความคืบหน้า / อะไหล่</label>
                    <textarea name="remark" id="modal_remark" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-indigo-100 outline-none resize-none text-slate-700" placeholder="ระบุข้อมูลเพิ่มเติมเพื่อให้ผู้แจ้งรับทราบ..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-sm transition-colors">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors shadow-md shadow-indigo-100">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Render Chart.js
        const ctx = document.getElementById('jobChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                datasets: [{
                    data: [<?= $stats['pending'] ?>, <?= $stats['in_progress'] ?>, <?= $stats['completed'] ?>],
                    backgroundColor: ['#f59e0b', '#4f46e5', '#10b981'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { family: 'sans-serif', size: 12 }
                        }
                    }
                }
            }
        });

        // Modal Controls
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