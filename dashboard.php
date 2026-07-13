<?php 
// 1. เชื่อมต่อฐานข้อมูล
include 'db_connect.php'; 
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
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; } /* พื้นหลังสว่างขึ้น */
        .menu-active { background-color: #2563eb !important; color: white !important; }
    </style>
</head>
<body>
    <div class="flex h-screen w-full overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col shrink-0">
            <div class="h-16 flex items-center justify-center border-b border-slate-700">
                <h1 class="text-lg font-bold text-white"><i class="fas fa-tools text-blue-400 mr-2"></i>RepairSystem</h1>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <button onclick="switchPage('page-dashboard', this)" class="w-full text-left px-4 py-2 rounded-lg menu-btn menu-active transition-colors"><i class="fas fa-chart-line w-6 text-center"></i> ภาพรวม</button>
                <button onclick="switchPage('page-repairs', this)" class="w-full text-left px-4 py-2 hover:bg-slate-700 rounded-lg menu-btn transition-colors"><i class="fas fa-clipboard-list w-6 text-center"></i> รายการแจ้งซ่อม</button>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 sticky top-0 z-10">
                <h2 class="text-xl font-semibold text-gray-800" id="headerTitle">ภาพรวม (Dashboard)</h2>
            </header>

            <div class="p-6">
                <!-- Dashboard Content -->
                <div id="page-dashboard" class="page-section block space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <?php 
                        // ตัวอย่างการดึงสถิติจาก DB
                        $total = $conn->query("SELECT count(*) as c FROM repairs")->fetch_assoc()['c'];
                        ?>
                        <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500">
                            <p class="text-sm text-gray-500">งานทั้งหมด</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Repairs Table Page -->
                <div id="page-repairs" class="page-section hidden">
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden p-6">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 text-gray-600 text-sm">
                                <tr>
                                    <th class="px-6 py-3 border-b">เลขที่ใบงาน</th>
                                    <th class="px-6 py-3 border-b">ผู้แจ้ง</th>
                                    <th class="px-6 py-3 border-b">อุปกรณ์</th>
                                    <th class="px-6 py-3 border-b">วันที่แจ้ง</th>
                                    <th class="px-6 py-3 border-b">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // เชื่อมข้อมูลจริงจากฐานข้อมูล
                                $sql = "SELECT * FROM repairs ORDER BY created_at DESC";
                                $result = $conn->query($sql);
                                while($row = $result->fetch_assoc()) {
                                    $date = date("d/m/Y H:i", strtotime($row['created_at'])); // เชื่อมเวลาแจ้งซ่อม
                                    echo "<tr class='border-b hover:bg-gray-50 text-sm'>
                                            <td class='px-6 py-4 font-bold text-blue-600'>{$row['ticket_no']}</td>
                                            <td class='px-6 py-4'>{$row['reporter_name']}</td>
                                            <td class='px-6 py-4'>{$row['equipment_type']}</td>
                                            <td class='px-6 py-4'>{$date}</td>
                                            <td class='px-6 py-4'>
                                                <span class='bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs'>{$row['status']}</span>
                                            </td>
                                          </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchPage(pageId, btn) {
            document.querySelectorAll('.page-section').forEach(p => p.classList.add('hidden'));
            document.getElementById(pageId).classList.remove('hidden');
            document.querySelectorAll('.menu-btn').forEach(b => b.classList.remove('menu-active'));
            btn.classList.add('menu-active');
            const titles = {'page-dashboard':'ภาพรวม', 'page-repairs':'รายการแจ้งซ่อม'};
            document.getElementById('headerTitle').innerText = titles[pageId];
        }
    </script>
</body>
</html>