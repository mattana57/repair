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

// ดึงข้อมูลรายการทั้งหมดแบบละเอียด
$result_repairs = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
$result_techs = $conn->query("SELECT * FROM technicians ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Smart Maintenance Dashboard - Full System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap');
        body { font-family: 'Prompt', sans-serif; background-color: #f3f4f6; }
        .menu-active { background-color: #2563eb !important; color: white !important; }
        .page-section { display: block; }
        .hidden { display: none; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen w-full overflow-hidden">
        <!-- Sidebar เริ่มต้น -->
        <aside class="w-64 bg-slate-800 text-slate-300 flex flex-col hidden md:flex">
            <div class="h-16 flex items-center justify-center border-b border-slate-700">
                <h1 class="text-lg font-bold text-white"><i class="fas fa-tools text-blue-400 mr-2"></i>RepairSystem</h1>
            </div>
            <nav class="flex-1 px-4 py-6 flex flex-col justify-between">
                <div class="space-y-2">
                    <button type="button" onclick="switchPage('page-dashboard', this)" class="w-full text-left px-4 py-2 rounded-lg menu-btn menu-active transition-colors"><i class="fas fa-chart-line w-6 text-center"></i> ภาพรวม</button>
                    <button type="button" onclick="switchPage('page-repairs', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-clipboard-list w-6 text-center"></i> รายการแจ้งซ่อม</button>
                    <button type="button" onclick="switchPage('page-technicians', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-user-tie w-6 text-center"></i> จัดการช่าง</button>
                    <button type="button" onclick="switchPage('page-assign', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-user-cog w-6 text-center"></i> มอบหมายงานช่าง</button>
                    <button type="button" onclick="switchPage('page-assets', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-desktop w-6 text-center"></i> จัดการครุภัณฑ์</button>
                    <button type="button" onclick="switchPage('page-users', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-users w-6 text-center"></i> จัดการผู้ใช้งาน</button>
                    <button type="button" onclick="switchPage('page-reports', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-file-export w-6 text-center"></i> ออกรายงาน</button>
                </div>
                <div class="mt-auto">
                    <a href="logout.php" class="flex items-center px-4 py-2 bg-red-900/30 text-red-400 hover:bg-red-600 hover:text-white rounded-lg transition-all"><i class="fas fa-sign-out-alt w-6 text-center"></i> ออกจากระบบ</a>
                </div>
            </nav>
        </aside>

        <!-- Main Content เริ่มต้น -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center px-6 sticky top-0 z-10"><h2 class="text-xl font-semibold text-gray-800" id="headerTitle">ภาพรวม</h2></header>

            <div class="p-6">
                <!-- ส่วน Dashboard -->
                <div id="page-dashboard" class="page-section space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500"><p class="text-gray-500">ทั้งหมด</p><p class="text-3xl font-bold"><?php echo $total_jobs; ?></p></div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-orange-500"><p class="text-gray-500">รอรับเรื่อง</p><p class="text-3xl font-bold"><?php echo $pending_jobs; ?></p></div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500"><p class="text-gray-500">กำลังซ่อม</p><p class="text-3xl font-bold"><?php echo $progress_jobs; ?></p></div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500"><p class="text-gray-500">เสร็จแล้ว</p><p class="text-3xl font-bold"><?php echo $done_jobs; ?></p></div>
                    </div>
                </div>

                <!-- ส่วนรายการแจ้งซ่อม -->
                <div id="page-repairs" class="page-section hidden bg-white p-6 rounded-xl shadow-sm">
                    <div class="flex justify-between items-center mb-6"><h3 class="font-bold text-lg">รายการแจ้งซ่อมทั้งหมด</h3></div>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100"><tr><th class="p-3">เลขที่</th><th class="p-3">อุปกรณ์</th><th class="p-3">สถานะ</th><th class="p-3">ดำเนินการ</th></tr></thead>
                        <tbody>
                            <?php 
                            $result_repairs->data_seek(0);
                            while($row = $result_repairs->fetch_assoc()) { 
                                $status_color = ($row['status'] == 'รอรับเรื่อง') ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700';
                                echo "<tr class='border-b'><td class='p-3'>{$row['ticket_no']}</td><td class='p-3'>{$row['equipment_type']}</td><td class='p-3'><span class='px-2 py-1 rounded-full {$status_color}'>{$row['status']}</span></td><td class='p-3'>";
                                if ($row['status'] == 'รอรับเรื่อง') echo "<a href='update_status.php?ticket_no={$row['ticket_no']}&status=กำลังดำเนินการ' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700'>รับงาน</a>";
                                elseif ($row['status'] == 'กำลังดำเนินการ') echo "<a href='update_status.php?ticket_no={$row['ticket_no']}&status=ซ่อมเสร็จแล้ว' class='bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700'>ซ่อมเสร็จ</a>";
                                echo "</td></tr>";
                            } 
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- ส่วนจัดการช่าง -->
                <div id="page-technicians" class="page-section hidden bg-white p-6 rounded-xl shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-lg">จัดการข้อมูลช่าง</h3>
                        <a href="add_technician.php" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>เพิ่มช่าง</a>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100"><tr><th class="p-3">ชื่อ-นามสกุล</th><th class="p-3">เบอร์โทร</th><th class="p-3">จัดการ</th></tr></thead>
                        <tbody>
                            <?php while($t = $result_techs->fetch_assoc()) { ?>
                            <tr class="border-b">
                                <td class="p-3"><?php echo $t['name']; ?></td>
                                <td class="p-3"><?php echo $t['phone']; ?></td>
                                <td class="p-3">
                                    <a href="edit_technician.php?id=<?php echo $t['id']; ?>" class="text-blue-600 mr-3"><i class="fas fa-edit"></i></a>
                                    <a href="delete_technician.php?id=<?php echo $t['id']; ?>" class="text-red-600" onclick="return confirm('ยืนยันลบข้อมูล?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- คงพื้นที่ว่างส่วน Management ไว้ เพื่อรอคุณน้ำฝนเพิ่มเติมในอนาคต -->
                <div id="page-assign" class="page-section hidden bg-white p-6 rounded-xl shadow-sm"><h3 class="font-bold text-lg mb-6">มอบหมายงานช่าง</h3></div>
                <div id="page-assets" class="page-section hidden bg-white p-6 rounded-xl shadow-sm"><h3 class="font-bold text-lg mb-6">จัดการครุภัณฑ์</h3></div>
                <div id="page-users" class="page-section hidden bg-white p-6 rounded-xl shadow-sm"><h3 class="font-bold text-lg mb-6">จัดการผู้ใช้งาน</h3></div>
                <div id="page-reports" class="page-section hidden bg-white p-6 rounded-xl shadow-sm"><h3 class="font-bold text-lg mb-6">ออกรายงาน</h3></div>
            </div>
        </main>
    </div>

    <!-- ส่วน JavaScript ที่รวมทุกฟังก์ชันให้ทำงานได้จริง -->
    <script>
        function switchPage(pageId, btn) {
            document.querySelectorAll('.page-section').forEach(s => s.classList.add('hidden'));
            const target = document.getElementById(pageId);
            if(target) target.classList.remove('hidden');
            
            document.querySelectorAll('.menu-btn').forEach(b => b.classList.remove('menu-active'));
            if(btn) btn.classList.add('menu-active');
            
            const titles = {
                'page-dashboard':'ภาพรวม', 'page-repairs':'รายการแจ้งซ่อม', 
                'page-technicians':'จัดการข้อมูลช่าง', 'page-assign':'มอบหมายงานช่าง', 
                'page-assets':'จัดการครุภัณฑ์', 'page-users':'จัดการผู้ใช้งาน', 'page-reports':'ออกรายงาน'
            };
            const h = document.getElementById('headerTitle');
            if(h) h.innerText = titles[pageId] || 'ภาพรวม';
        }
        document.addEventListener('DOMContentLoaded', () => {
            const firstBtn = document.querySelector('.menu-btn');
            switchPage('page-dashboard', firstBtn);
        });
    </script>
    <script>
    // เลือกปุ่มทั้งหมดที่มี class menu-btn
    document.querySelectorAll('.menu-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // ซ่อนทุกหน้า (ต้องมี class page-section ใน div เนื้อหา)
            document.querySelectorAll('.page-section').forEach(s => s.classList.add('hidden'));
            
            // แสดงหน้าที่ต้องการ (ต้องตั้งชื่อ data-page="page-id")
            const pageId = this.getAttribute('data-page');
            const target = document.getElementById(pageId);
            if(target) {
                target.classList.remove('hidden');
            }
            
            // ปรับสีปุ่ม
            document.querySelectorAll('.menu-btn').forEach(b => b.classList.remove('menu-active'));
            this.classList.add('menu-active');
            
            // เปลี่ยนหัวข้อ
            const titles = {
                'page-dashboard': 'ภาพรวม',
                'page-repairs': 'รายการแจ้งซ่อม',
                'page-technicians': 'จัดการช่าง'
            };
            document.getElementById('headerTitle').innerText = titles[pageId] || 'เมนู';
        });
    });
</script>
</body>
</html>