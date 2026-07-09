<?php
session_start();
// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}
require_once 'db_connect.php';

// ดึงข้อมูลสถิติ
$total_jobs = $conn->query("SELECT COUNT(*) as count FROM repairs")->fetch_assoc()['count'];
$pending_jobs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status='รอรับเรื่อง'")->fetch_assoc()['count'];
$progress_jobs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status='กำลังดำเนินการ'")->fetch_assoc()['count'];
$done_jobs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status IN ('ซ่อมเสร็จแล้ว', 'ปิดงาน')")->fetch_assoc()['count'];

$result_repairs = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Smart Maintenance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap');
        body { font-family: 'Prompt', sans-serif; }
        .menu-active { background-color: #2563eb !important; color: white !important; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen w-full overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-800 text-slate-300 flex flex-col hidden md:flex">
            <div class="h-16 flex items-center justify-center border-b border-slate-700">
                <h1 class="text-lg font-bold text-white"><i class="fas fa-tools text-blue-400 mr-2"></i>RepairSystem</h1>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <button type="button" onclick="switchPage('page-dashboard', this)" class="w-full text-left px-4 py-2 rounded-lg menu-btn menu-active"><i class="fas fa-chart-line w-6 text-center"></i> ภาพรวม (Dashboard)</button>
                <button type="button" onclick="switchPage('page-repairs', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn"><i class="fas fa-clipboard-list w-6 text-center"></i> รายการแจ้งซ่อม</button>
                <a href="logout.php" class="w-full text-left px-4 py-2 hover:bg-red-900 text-red-400 rounded-lg"><i class="fas fa-sign-out-alt w-6 text-center"></i> ออกจากระบบ</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 sticky top-0 z-10">
                <h2 class="text-xl font-semibold text-gray-800" id="headerTitle">ภาพรวม (Dashboard)</h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 font-medium"><i class="fas fa-user-circle text-xl mr-1 text-blue-600"></i> Admin</span>
                </div>
            </header>

            <div class="p-6">
                <!-- Dashboard Section -->
                <div id="page-dashboard" class="page-section space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500">
                            <p class="text-sm text-gray-500">งานทั้งหมด</p>
                            <p class="text-3xl font-bold"><?php echo $total_jobs; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-orange-500">
                            <p class="text-sm text-gray-500">รอรับเรื่อง</p>
                            <p class="text-3xl font-bold"><?php echo $pending_jobs; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500">
                            <p class="text-sm text-gray-500">กำลังดำเนินการ</p>
                            <p class="text-3xl font-bold"><?php echo $progress_jobs; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500">
                            <p class="text-sm text-gray-500">ซ่อมเสร็จแล้ว</p>
                            <p class="text-3xl font-bold"><?php echo $done_jobs; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Repairs List Section -->
                <div id="page-repairs" class="page-section hidden">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-sm">
                                <tr>
                                    <th class="p-3">เลขที่แจ้งซ่อม</th>
                                    <th class="p-3">อุปกรณ์</th>
                                    <th class="p-3">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result_repairs->fetch_assoc()) { ?>
                                    <tr class="border-b">
                                        <td class="p-3"><?php echo $row['ticket_no']; ?></td>
                                        <td class="p-3"><?php echo $row['equipment_type']; ?></td>
                                        <td class="p-3"><?php echo $row['status']; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchPage(pageId, btn) {
            document.querySelectorAll('.page-section').forEach(s => s.classList.add('hidden'));
            document.getElementById(pageId).classList.remove('hidden');
            document.querySelectorAll('.menu-btn').forEach(b => b.classList.remove('menu-active'));
            if(btn) btn.classList.add('menu-active');
        }
    </script>
</body>
</html>