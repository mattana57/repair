<?php
session_start();
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
// ดึงข้อมูลรายการซ่อมทั้งหมด
$result_repairs = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Smart Maintenance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap');
        body { font-family: 'Prompt', sans-serif; }
        .menu-active { background-color: #2563eb !important; color: white !important; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen w-full overflow-hidden">
        <!-- Sidebar คงเดิม -->
        <aside class="w-64 bg-slate-800 text-slate-300 flex flex-col hidden md:flex">
            <!-- (โค้ด Sidebar เหมือนเดิม) -->
            <div class="h-16 flex items-center justify-center border-b border-slate-700">
                <h1 class="text-lg font-bold text-white"><i class="fas fa-tools text-blue-400 mr-2"></i>RepairSystem</h1>
            </div>
            <nav class="flex-1 px-4 py-6 flex flex-col justify-between">
                <div class="space-y-2">
                    <button type="button" onclick="switchPage('page-dashboard', this)" class="w-full text-left px-4 py-2 rounded-lg menu-btn menu-active transition-colors"><i class="fas fa-chart-line w-6 text-center"></i> ภาพรวม</button>
                    <button type="button" onclick="switchPage('page-repairs', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-clipboard-list w-6 text-center"></i> รายการแจ้งซ่อม</button>
                    <!-- (เมนูอื่นๆ คงเดิม) -->
                </div>
                <div class="mt-auto">
                    <a href="logout.php" class="flex items-center px-4 py-2 bg-red-900/30 text-red-400 hover:bg-red-600 hover:text-white rounded-lg transition-all"><i class="fas fa-sign-out-alt w-6 text-center"></i> ออกจากระบบ</a>
                </div>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center px-6 sticky top-0 z-10"><h2 class="text-xl font-semibold text-gray-800" id="headerTitle">ภาพรวม</h2></header>

            <div class="p-6">
                <!-- Dashboard Content (คงเดิม) -->
                <div id="page-dashboard" class="page-section space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500"><p class="text-gray-500">ทั้งหมด</p><p class="text-3xl font-bold"><?php echo $total_jobs; ?></p></div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-orange-500"><p class="text-gray-500">รอรับเรื่อง</p><p class="text-3xl font-bold"><?php echo $pending_jobs; ?></p></div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500"><p class="text-gray-500">กำลังซ่อม</p><p class="text-3xl font-bold"><?php echo $progress_jobs; ?></p></div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500"><p class="text-gray-500">เสร็จแล้ว</p><p class="text-3xl font-bold"><?php echo $done_jobs; ?></p></div>
                    </div>
                </div>

                <!-- ปรับปรุง Repairs List ตรงนี้ -->
                <div id="page-repairs" class="page-section hidden bg-white p-6 rounded-xl shadow-sm">
                    <div class="flex justify-between items-center mb-6"><h3 class="font-bold text-lg">รายการแจ้งซ่อมทั้งหมด</h3></div>
                    <table class="w-full text-left text-sm">
                        <tr class="bg-gray-100"><th class="p-3">เลขที่</th><th class="p-3">อุปกรณ์</th><th class="p-3">สถานะ</th><th class="p-3">ดำเนินการ</th></tr>
                        <?php 
                        // ย้อนกลับตัวชี้ข้อมูลเพื่อแสดงผล
                        $result_repairs->data_seek(0);
                        while($row = $result_repairs->fetch_assoc()) { 
                            $status_color = ($row['status'] == 'รอรับเรื่อง') ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700';
                        ?>
                        <tr class="border-b">
                            <td class="p-3 font-medium"><?php echo $row['ticket_no']; ?></td>
                            <td class="p-3"><?php echo $row['equipment_type']; ?></td>
                            <td class="p-3"><span class="px-2 py-1 rounded-full <?php echo $status_color; ?>"><?php echo $row['status']; ?></span></td>
                            <td class="p-3">
                                <?php if ($row['status'] == 'รอรับเรื่อง'): ?>
                                    <a href="update_status.php?ticket_no=<?php echo $row['ticket_no']; ?>&status=กำลังดำเนินการ" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">รับงาน</a>
                                <?php elseif ($row['status'] == 'กำลังดำเนินการ'): ?>
                                    <a href="update_status.php?ticket_no=<?php echo $row['ticket_no']; ?>&status=ซ่อมเสร็จแล้ว" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">ซ่อมเสร็จ</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
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
            const titles = {'page-dashboard':'ภาพรวม','page-repairs':'รายการแจ้งซ่อม'};
            document.getElementById('headerTitle').innerText = titles[pageId];
        }
    </script>
</body>
</html>