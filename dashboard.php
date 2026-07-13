<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSU Smart Maintenance Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #b7cbf9; color: #f8fafc; }
        
        /* สไตล์ Glassmorphism ที่นุ่มนวลและดูพรีเมียมขึ้น */
        .glass-panel { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-header { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* เมนู Sidebar แบบสากล */
        .nav-btn { @apply w-full flex items-center px-5 py-3.5 rounded-xl text-slate-400 font-medium transition-all duration-200 hover:bg-slate-800 hover:text-white; }
        .nav-btn i { @apply w-6 text-lg mr-3 opacity-70; }
        .active-btn { @apply bg-sky-500/10 text-sky-400 border-l-4 border-sky-500 shadow-sm; }
        .active-btn i { @apply text-sky-400 opacity-100; }

        /* ปรับแต่ง Scrollbar ให้เรียบหรู */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-500/30">

    <!-- Sidebar -->
    <aside class="w-72 glass-panel flex flex-col shrink-0 z-20">
        <div class="h-20 flex items-center px-8 glass-header">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center shadow-lg shadow-sky-500/20 mr-3">
                <i class="fas fa-tools text-white"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white leading-tight">MSU MAINT</h1>
                <p class="text-xs text-sky-400 font-medium tracking-wider uppercase">Admin Portal</p>
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
            <p class="px-5 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-2">เมนูหลัก</p>
            <button onclick="show('dash')" class="nav-btn active-btn"><i class="fas fa-chart-pie"></i> ภาพรวมระบบ</button>
            <button onclick="show('repairs')" class="nav-btn"><i class="fas fa-layer-group"></i> ตรวจสอบงานแจ้งซ่อม</button>
            <button onclick="show('assign')" class="nav-btn"><i class="fas fa-user-shield"></i> รับงาน/มอบหมาย</button>
            
            <p class="px-5 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-6">ฐานข้อมูล</p>
            <button onclick="show('assets')" class="nav-btn"><i class="fas fa-server"></i> จัดการอุปกรณ์</button>
            <button onclick="show('users')" class="nav-btn"><i class="fas fa-users-cog"></i> จัดการผู้ใช้</button>
            <button onclick="show('reports')" class="nav-btn"><i class="fas fa-file-invoice"></i> รายงานสรุป</button>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-slate-800 via-slate-900 to-slate-900">
        
        <!-- Top Navigation -->
        <header class="h-20 glass-header flex items-center justify-between px-8 shrink-0 z-10">
            <h2 class="text-xl font-semibold text-white tracking-wide" id="headerTitle">ภาพรวมระบบ (Dashboard)</h2>
            <div class="flex items-center space-x-4">
                <div class="relative hidden md:block">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" placeholder="ค้นหาเลขที่ใบงาน..." class="bg-slate-800/50 border border-slate-700 text-sm rounded-full pl-9 pr-4 py-2 text-white focus:outline-none focus:border-sky-500 transition-colors w-64">
                </div>
                <div class="h-8 w-px bg-slate-700 mx-2"></div>
                <div class="flex items-center space-x-3 cursor-pointer hover:bg-slate-800 py-1.5 px-3 rounded-full transition-colors">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-sky-400">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="text-sm font-medium text-slate-300">Administrator</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- Dashboard Stats -->
            <div id="dash" class="section space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                    $stats = [
                        ["title" => "งานทั้งหมด", "query" => "repairs", "icon" => "fa-briefcase", "color" => "text-blue-400", "bg" => "bg-blue-400/10", "border" => "border-blue-500/30"],
                        ["title" => "รอรับเรื่อง", "query" => "status='รอรับเรื่อง'", "icon" => "fa-clock", "color" => "text-amber-400", "bg" => "bg-amber-400/10", "border" => "border-amber-500/30"],
                        ["title" => "กำลังดำเนินการ", "query" => "status='กำลังดำเนินการ'", "icon" => "fa-tools", "color" => "text-sky-400", "bg" => "bg-sky-400/10", "border" => "border-sky-500/30"],
                        ["title" => "ซ่อมเสร็จแล้ว", "query" => "status='ซ่อมเสร็จแล้ว'", "icon" => "fa-check-circle", "color" => "text-emerald-400", "bg" => "bg-emerald-400/10", "border" => "border-emerald-500/30"]
                    ];
                    
                    foreach($stats as $s) {
                        $c = $conn->query("SELECT count(*) as c FROM repairs ".($s['query'] != "repairs" ? "WHERE {$s['query']}" : ""))->fetch_assoc()['c'];
                        echo "
                        <div class='glass-panel p-6 rounded-2xl border {$s['border']} relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300'>
                            <div class='flex justify-between items-start'>
                                <div>
                                    <p class='text-slate-400 text-sm font-medium mb-1'>{$s['title']}</p>
                                    <h3 class='text-4xl font-bold text-white'>{$c}</h3>
                                </div>
                                <div class='w-12 h-12 rounded-xl {$s['bg']} flex items-center justify-center {$s['color']}'>
                                    <i class='fas {$s['icon']} text-xl'></i>
                                </div>
                            </div>
                            <div class='absolute -bottom-4 -right-4 text-8xl opacity-5 group-hover:opacity-10 transition-opacity duration-300 {$s['color']}'>
                                <i class='fas {$s['icon']}'></i>
                            </div>
                        </div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Repairs List Table (ดึงข้อมูลครบทุกฟิลด์) -->
            <div id="repairs" class="section hidden space-y-6">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h2 class="text-xl font-bold text-white">รายการแจ้งซ่อมทั้งหมด</h2>
                    <button class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-sky-600/20">
                        <i class="fas fa-filter mr-2"></i> ตัวกรองข้อมูล
                    </button>
                </div>

                <div class="glass-panel rounded-2xl overflow-hidden border border-slate-700/50">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-800/50 border-b border-slate-700 text-slate-400 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">วัน/เวลาที่แจ้ง</th>
                                    <th class="px-6 py-4">เลขที่ใบงาน</th>
                                    <th class="px-6 py-4">ข้อมูลผู้แจ้ง</th>
                                    <th class="px-6 py-4">สถานที่</th>
                                    <th class="px-6 py-4">อุปกรณ์ / อาการเสีย</th>
                                    <th class="px-6 py-4">ภาพประกอบ</th>
                                    <th class="px-6 py-4 text-center">สถานะ</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-700/50">
                                <?php
                                $res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
                                if($res->num_rows > 0){
                                    while($row = $res->fetch_assoc()) {
                                        // จัดฟอร์แมตวันที่
                                        $date = !empty($row['created_at']) ? date("d/m/Y H:i", strtotime($row['created_at'])) : "-";
                                        
                                        // กำหนดสีของ Status Badge
                                        $statusClass = "bg-slate-500/20 text-slate-300 border-slate-500/30"; // Default
                                        if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-500/10 text-amber-400 border-amber-500/20";
                                        elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-500/10 text-sky-400 border-sky-500/20";
                                        elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-500/10 text-emerald-400 border-emerald-500/20";

                                        // ตรวจสอบรูปภาพ
                                        $imageHtml = !empty($row['image_before']) 
                                            ? "<a href='uploads/{$row['image_before']}' target='_blank' class='inline-flex items-center text-xs text-sky-400 hover:text-sky-300 bg-sky-400/10 px-2 py-1 rounded-md'><i class='fas fa-image mr-1.5'></i> ดูรูปภาพ</a>" 
                                            : "<span class='text-slate-600 text-xs'>ไม่มีรูป</span>";

                                        echo "
                                        <tr class='hover:bg-slate-800/40 transition-colors'>
                                            <td class='px-6 py-4 text-slate-400'>{$date}</td>
                                            <td class='px-6 py-4 font-semibold text-sky-400'>{$row['ticket_no']}</td>
                                            <td class='px-6 py-4'>
                                                <div class='text-white font-medium'>{$row['reporter_name']}</div>
                                                <div class='text-slate-400 text-xs mt-0.5'><i class='fas fa-phone-alt mr-1'></i> {$row['phone_number']}</div>
                                            </td>
                                            <td class='px-6 py-4 text-slate-300'>{$row['location']}</td>
                                            <td class='px-6 py-4'>
                                                <div class='text-white font-medium'>{$row['equipment_type']}</div>
                                                <div class='text-slate-400 text-xs mt-0.5 max-w-[200px] truncate' title='{$row['problem_desc']}'>{$row['problem_desc']}</div>
                                            </td>
                                            <td class='px-6 py-4'>{$imageHtml}</td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {$statusClass}'>
                                                    <span class='w-1.5 h-1.5 rounded-full bg-current mr-2'></span>{$row['status']}
                                                </span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button class='w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition-colors flex items-center justify-center' title='รับงาน/เปลี่ยนสถานะ'>
                                                        <i class='fas fa-clipboard-check'></i>
                                                    </button>
                                                    <button class='w-8 h-8 rounded-lg bg-sky-500/10 text-sky-400 hover:bg-sky-500/20 transition-colors flex items-center justify-center' title='ดูรายละเอียดเต็ม'>
                                                        <i class='fas fa-eye'></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='px-6 py-12 text-center text-slate-500'>ยังไม่มีข้อมูลการแจ้งซ่อมในระบบ</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sections สำหรับเมนูอื่นๆ สามารถทำโครงสร้างซ่อนไว้แบบ id="repairs" ได้เลย -->
            <div id="assign" class="section hidden"><h2 class="text-xl font-bold text-white">รับงาน / มอบหมายงาน</h2></div>
            <div id="assets" class="section hidden"><h2 class="text-xl font-bold text-white">จัดการอุปกรณ์</h2></div>
            <div id="users" class="section hidden"><h2 class="text-xl font-bold text-white">จัดการผู้ใช้งาน</h2></div>
            <div id="reports" class="section hidden"><h2 class="text-xl font-bold text-white">รายงานสรุป</h2></div>

        </div>
    </main>

    <script>
        const pageTitles = {
            'dash': 'ภาพรวมระบบ (Dashboard)',
            'repairs': 'ตรวจสอบงานแจ้งซ่อมทั้งหมด',
            'assign': 'ระบบรับงานและมอบหมายช่าง',
            'assets': 'ฐานข้อมูลอุปกรณ์และครุภัณฑ์',
            'users': 'จัดการสิทธิ์และผู้ใช้งาน',
            'reports': 'รายงานสรุปผลการปฏิบัติงาน'
        };

        function show(id) {
            // ซ่อนทุกหน้า
            document.querySelectorAll('.section').forEach(s => {
                s.classList.add('hidden');
                s.classList.remove('animate-fade-in');
            });
            
            // แสดงหน้าที่เลือกพร้อม Effect
            const targetSection = document.getElementById(id);
            targetSection.classList.remove('hidden');
            
            // เปลี่ยนสถานะปุ่มเมนู
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            event.currentTarget.classList.add('active-btn');
            
            // เปลี่ยนหัวข้อ
            document.getElementById('headerTitle').innerText = pageTitles[id];
        }
    </script>
</body>
</html>