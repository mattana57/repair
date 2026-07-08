<?php
// ดึงไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// --- 1. คิวรี่ดึงตัวเลขสถิติ ---
$sql_total = "SELECT COUNT(*) as count FROM repairs";
$total_jobs = $conn->query($sql_total)->fetch_assoc()['count'];

$sql_pending = "SELECT COUNT(*) as count FROM repairs WHERE status='รอรับเรื่อง'";
$pending_jobs = $conn->query($sql_pending)->fetch_assoc()['count'];

$sql_progress = "SELECT COUNT(*) as count FROM repairs WHERE status='กำลังดำเนินการ'";
$progress_jobs = $conn->query($sql_progress)->fetch_assoc()['count'];

$sql_done = "SELECT COUNT(*) as count FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='ปิดงาน'";
$done_jobs = $conn->query($sql_done)->fetch_assoc()['count'];

// --- 2. คิวรี่ดึงข้อมูลตารางแจ้งซ่อม ---
$sql_repairs = "SELECT * FROM repairs ORDER BY created_at DESC";
$result_repairs = $conn->query($sql_repairs);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Maintenance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap');
        body { font-family: 'Prompt', sans-serif; margin: 0; padding: 0; }
        .menu-active { background-color: #2563eb !important; color: white !important; }
    </style>
</head>
<body>
    <div class="flex h-screen w-full overflow-hidden bg-gray-100">

        <aside class="w-64 bg-slate-800 text-slate-300 flex flex-col hidden md:flex shrink-0">
            <div class="h-16 flex items-center justify-center border-b border-slate-700">
                <h1 class="text-lg font-bold text-white"><i class="fas fa-tools text-blue-400 mr-2"></i>MBS Repair</h1>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto" id="sidebarMenu">
                <button type="button" onclick="switchPage('page-dashboard', this)" class="w-full text-left px-4 py-2 rounded-lg menu-btn menu-active transition-colors"><i class="fas fa-chart-line w-6 text-center"></i> ภาพรวม (Dashboard)</button>
                <button type="button" onclick="switchPage('page-repairs', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-clipboard-list w-6 text-center"></i> รายการแจ้งซ่อม</button>
                </nav>
        </aside>

        <main class="flex-1 flex flex-col overflow-y-auto relative bg-gray-100">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 sticky top-0 z-10 shrink-0">
                <h2 class="text-xl font-semibold text-gray-800" id="headerTitle">ภาพรวม (Dashboard)</h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 font-medium"><i class="fas fa-user-circle text-xl mr-1 text-blue-600 align-middle"></i> ช่าง/แอดมิน</span>
                </div>
            </header>

            <div class="p-6">
                <div id="page-dashboard" class="page-section block space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500">
                            <p class="text-sm text-gray-500 font-medium">จำนวนงานทั้งหมด</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_jobs; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-orange-500">
                            <p class="text-sm text-gray-500 font-medium">รอรับเรื่อง</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $pending_jobs; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500">
                            <p class="text-sm text-gray-500 font-medium">กำลังดำเนินการ</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $progress_jobs; ?></p>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500">
                            <p class="text-sm text-gray-500 font-medium">ซ่อมเสร็จแล้ว</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $done_jobs; ?></p>
                        </div>
                    </div>
                </div>

                <div id="page-repairs" class="page-section hidden">
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">จัดการรายการแจ้งซ่อมทั้งหมด</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse border border-gray-100 min-w-max">
                                <thead class="bg-gray-50 text-gray-600 text-sm">
                                    <tr>
                                        <th class="px-6 py-3 border-b">เลขที่แจ้งซ่อม</th>
                                        <th class="px-6 py-3 border-b">วันเวลา</th>
                                        <th class="px-6 py-3 border-b">หมวดหมู่</th>
                                        <th class="px-6 py-3 border-b">สถานที่</th>
                                        <th class="px-6 py-3 border-b">อาการ</th>
                                        <th class="px-6 py-3 border-b text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php
                                    // วนลูปดึงข้อมูลจาก Database มาแสดงเป็นแถวๆ
                                    if ($result_repairs->num_rows > 0) {
                                        while($row = $result_repairs->fetch_assoc()) {
                                            
                                            // จัดการสีของสถานะ
                                            $badge_class = "bg-orange-100 text-orange-700"; // Default รอรับเรื่อง
                                            if($row['status'] == 'กำลังดำเนินการ') $badge_class = "bg-yellow-100 text-yellow-700";
                                            if($row['status'] == 'ซ่อมเสร็จแล้ว' || $row['status'] == 'ปิดงาน') $badge_class = "bg-green-100 text-green-700";

                                            echo "<tr class='hover:bg-gray-50 border-b transition-colors'>";
                                            echo "<td class='px-6 py-4 font-medium text-blue-600'>" . htmlspecialchars($row['ticket_no']) . "</td>";
                                            echo "<td class='px-6 py-4 text-gray-500'>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>";
                                            echo "<td class='px-6 py-4 font-medium'>" . htmlspecialchars($row['equipment_type']) . "</td>";
                                            echo "<td class='px-6 py-4'>" . htmlspecialchars($row['location']) . "</td>";
                                            echo "<td class='px-6 py-4 truncate max-w-xs' title='" . htmlspecialchars($row['problem_desc']) . "'>" . htmlspecialchars($row['problem_desc']) . "</td>";
                                            echo "<td class='px-6 py-4 text-center'><span class='{$badge_class} px-3 py-1 rounded-full text-xs font-bold'>" . htmlspecialchars($row['status']) . "</span></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='px-6 py-8 text-center text-gray-500'>ยังไม่มีรายการแจ้งซ่อม</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function switchPage(pageId, btnElement) {
            const sections = document.querySelectorAll('.page-section');
            for (let i = 0; i < sections.length; i++) {
                sections[i].style.display = 'none';
                sections[i].classList.add('hidden');
                sections[i].classList.remove('block');
            }
            
            const selectedPage = document.getElementById(pageId);
            if (selectedPage) {
                selectedPage.style.display = 'block';
                selectedPage.classList.remove('hidden');
                selectedPage.classList.add('block');
            }

            const btns = document.querySelectorAll('.menu-btn');
            for (let i = 0; i < btns.length; i++) {
                btns[i].classList.remove('menu-active', 'bg-blue-600');
                btns[i].classList.add('hover:bg-slate-700');
            }
            
            if (btnElement) {
                btnElement.classList.add('menu-active', 'bg-blue-600');
                btnElement.classList.remove('hover:bg-slate-700');
            }

            const titleMap = {
                'page-dashboard': 'ภาพรวม (Dashboard)',
                'page-repairs': 'จัดการรายการแจ้งซ่อม'
            };
            
            const headerObj = document.getElementById('headerTitle');
            if(headerObj) {
                headerObj.innerText = titleMap[pageId] || 'ระบบแจ้งซ่อม';
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>