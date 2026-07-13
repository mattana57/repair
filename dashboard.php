<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>MSU Smart Maintenance Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #0f172a; color: #f1f5f9; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .nav-btn { @apply w-full text-left px-4 py-3 rounded-xl transition-all hover:bg-sky-600/20 hover:text-sky-400; }
        .active-btn { @apply bg-sky-600/30 text-sky-400 border-l-4 border-sky-500; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- Sidebar: จัดการเมนูครบวงจร -->
    <aside class="w-64 glass flex flex-col p-4 space-y-2">
        <h1 class="text-xl font-bold p-4 text-white"><i class="fas fa-tools text-sky-500 mr-2"></i>MSU MAINT</h1>
        <nav class="flex-1 space-y-1">
            <button onclick="show('dash')" class="nav-btn active-btn"><i class="fas fa-th-large w-8"></i> ภาพรวมระบบ</button>
            <button onclick="show('repairs')" class="nav-btn"><i class="fas fa-clipboard-list w-8"></i> ตรวจสอบงานแจ้งซ่อม</button>
            <button onclick="show('assign')" class="nav-btn"><i class="fas fa-user-check w-8"></i> รับงาน/มอบหมาย</button>
            <button onclick="show('assets')" class="nav-btn"><i class="fas fa-desktop w-8"></i> จัดการอุปกรณ์</button>
            <button onclick="show('users')" class="nav-btn"><i class="fas fa-users w-8"></i> จัดการผู้ใช้</button>
            <button onclick="show('reports')" class="nav-btn"><i class="fas fa-chart-pie w-8"></i> รายงานสรุป</button>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8">
        <!-- Dashboard Stats -->
        <div id="dash" class="section space-y-6">
            <div class="grid grid-cols-4 gap-6">
                <?php 
                $stats = ["ทั้งหมด" => "repairs", "รอรับเรื่อง" => "status='รอรับเรื่อง'", "กำลังทำ" => "status='กำลังดำเนินการ'", "เสร็จแล้ว" => "status='ซ่อมเสร็จแล้ว'"];
                foreach($stats as $title => $query) {
                    $c = $conn->query("SELECT count(*) as c FROM repairs ".($query != "repairs" ? "WHERE $query" : ""))->fetch_assoc()['c'];
                    echo "<div class='glass p-6 rounded-2xl'>
                            <p class='text-slate-400 text-sm'>$title</p>
                            <p class='text-4xl font-bold text-white'>$c</p>
                          </div>";
                }
                ?>
            </div>
        </div>

        <!-- Repairs List Table -->
        <div id="repairs" class="section hidden glass p-6 rounded-2xl">
            <h2 class="text-xl font-bold mb-4">รายการแจ้งซ่อมทั้งหมด</h2>
            <table class="w-full text-left">
                <thead class="text-slate-400 text-sm border-b border-white/10">
                    <tr><th class="py-3">ใบงาน</th><th class="py-3">อุปกรณ์</th><th class="py-3">สถานะ</th><th class="py-3">การจัดการ</th></tr>
                </thead>
                <tbody class="text-sm">
                    <?php
                    $res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
                    while($row = $res->fetch_assoc()) {
                        echo "<tr class='border-b border-white/5 hover:bg-white/5'>
                                <td class='py-4 font-bold text-sky-400'>{$row['ticket_no']}</td>
                                <td class='py-4'>{$row['equipment_type']}</td>
                                <td class='py-4'><span class='px-3 py-1 rounded-full bg-blue-500/20 text-blue-300'>{$row['status']}</span></td>
                                <td class='py-4 space-x-2'>
                                    <button class='text-emerald-400 hover:text-white'><i class='fas fa-check'></i> รับงาน</button>
                                    <button class='text-amber-400 hover:text-white'><i class='fas fa-edit'></i> อัปเดต</button>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            event.currentTarget.classList.add('active-btn');
        }
    </script>
</body>
</html>