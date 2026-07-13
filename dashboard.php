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
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; color: #334155; }
        
        /* สไตล์การ์ดแบบสว่าง คลีนๆ */
        .white-panel { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        
        /* เมนู Sidebar แบบสว่างและเป็นระเบียบ */
        .nav-btn { @apply w-full flex items-center px-4 py-3 mb-1 rounded-xl text-slate-600 font-medium transition-all duration-200 hover:bg-slate-50 hover:text-sky-600 whitespace-nowrap overflow-hidden; }
        .nav-btn i { @apply w-8 text-center text-lg text-slate-400 transition-colors duration-200; }
        .nav-btn:hover i { @apply text-sky-500; }
        
        /* สถานะเมนูที่ถูกเลือก */
        .active-btn { @apply bg-sky-50 text-sky-700 font-semibold; }
        .active-btn i { @apply text-sky-600; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-100">

    <!-- Sidebar (Light Mode) -->
    <aside class="w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 z-20">
        <div class="h-20 flex items-center px-6 border-b border-slate-100">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-md shadow-sky-500/20 mr-3 shrink-0">
                <i class="fas fa-tools text-white text-sm"></i>
            </div>
            <div class="overflow-hidden">
                <h1 class="text-lg font-bold text-slate-800 leading-tight truncate">MSU MAINT</h1>
                <p class="text-[11px] text-sky-600 font-semibold tracking-wider uppercase truncate">Admin Portal</p>
            </div>
        </div>
        
        <nav class="flex-1 px-4 py-6 overflow-y-auto">
            <p class="px-4 text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 mt-2">เมนูหลัก</p>
            <button onclick="show('dash')" class="nav-btn active-btn"><i class="fas fa-chart-pie"></i> ภาพรวมระบบ</button>
            <button onclick="show('repairs')" class="nav-btn"><i class="fas fa-layer-group"></i> รายการแจ้งซ่อม</button>
            <button onclick="show('assign')" class="nav-btn"><i class="fas fa-user-shield"></i> มอบหมายงานช่าง</button>
            
            <p class="px-4 text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 mt-6">ฐานข้อมูล</p>
            <button onclick="show('assets')" class="nav-btn"><i class="fas fa-server"></i> จัดการอุปกรณ์</button>
            <button onclick="show('users')" class="nav-btn"><i class="fas fa-users-cog"></i> จัดการผู้ใช้</button>
            <button onclick="show('reports')" class="nav-btn"><i class="fas fa-file-invoice"></i> รายงานสรุป</button>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
        
        <!-- Top Navigation -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0 z-10">
            <h2 class="text-xl font-bold text-slate-800 tracking-wide" id="headerTitle">ภาพรวมระบบ (Dashboard)</h2>
            <div class="flex items-center space-x-5">
                <div class="relative hidden md:block">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" placeholder="ค้นหาเลขที่ใบงาน..." class="bg-slate-50 border border-slate-200 text-sm rounded-full pl-10 pr-4 py-2 text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 transition-all w-64">
                </div>
                <div class="h-8 w-px bg-slate-200 mx-1"></div>
                <div class="flex items-center space-x-3 cursor-pointer hover:bg-slate-50 py-1.5 px-3 rounded-full transition-colors">
                    <div class="w-9 h-9 rounded-full bg-sky-100 flex items-center justify-center text-sky-600">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="hidden sm:block">
                        <span class="block text-sm font-semibold text-slate-700 leading-tight">Administrator</span>
                        <span class="block text-xs text-slate-500">ผู้ดูแลระบบ</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- Dashboard Stats -->
            <div id="dash" class="section space-y-8 animate-fade-in">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                    $stats = [
                        ["title" => "งานทั้งหมด", "query" => "repairs", "icon" => "fa-briefcase", "color" => "text-blue-600", "bg" => "bg-blue-50", "border" => "border-blue-100"],
                        ["title" => "รอรับเรื่อง", "query" => "status='รอรับเรื่อง'", "icon" => "fa-clock", "color" => "text-amber-600", "bg" => "bg-amber-50", "border" => "border-amber-100"],
                        ["title" => "กำลังดำเนินการ", "query" => "status='กำลังดำเนินการ'", "icon" => "fa-tools", "color" => "text-sky-600", "bg" => "bg-sky-50", "border" => "border-sky-100"],
                        ["title" => "ซ่อมเสร็จแล้ว", "query" => "status='ซ่อมเสร็จแล้ว'", "icon" => "fa-check-circle", "color" => "text-emerald-600", "bg" => "bg-emerald-50", "border" => "border-emerald-100"]
                    ];
                    
                    foreach($stats as $s) {
                        $c = $conn->query("SELECT count(*) as c FROM repairs ".($s['query'] != "repairs" ? "WHERE {$s['query']}" : ""))->fetch_assoc()['c'];
                        echo "
                        <div class='white-panel rounded-2xl p-6 relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300'>
                            <div class='flex justify-between items-start'>
                                <div>
                                    <p class='text-slate-500 text-sm font-medium mb-1'>{$s['title']}</p>
                                    <h3 class='text-4xl font-bold text-slate-800'>{$c}</h3>
                                </div>
                                <div class='w-12 h-12 rounded-xl {$s['bg']} flex items-center justify-center {$s['color']}'>
                                    <i class='fas {$s['icon']} text-xl'></i>
                                </div>
                            </div>
                        </div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Repairs List Table (ดึงข้อมูลครบทุกฟิลด์) -->
            <div id="repairs" class="section hidden space-y-6">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
                    <h2 class="text-xl font-bold text-slate-800">รายการแจ้งซ่อมทั้งหมด</h2>
                    <button class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-sky-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center">
                        <i class="fas fa-filter mr-2"></i> ตัวกรองข้อมูล
                    </button>
                </div>

                <div class="white-panel rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider font-semibold">
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
                            <tbody class="text-sm divide-y divide-slate-100">
                                <?php
                                $res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC");
                                if($res->num_rows > 0){
                                    while($row = $res->fetch_assoc()) {
                                        // จัดฟอร์แมตวันที่
                                        $date = !empty($row['created_at']) ? date("d/m/Y H:i", strtotime($row['created_at'])) : "-";
                                        
                                        // กำหนดสีของ Status Badge สำหรับ Light Mode
                                        $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; // Default
                                        if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                        elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                        elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";

                                        // ตรวจสอบรูปภาพ
                                        $imageHtml = !empty($row['image_before']) 
                                            ? "<a href='uploads/{$row['image_before']}' target='_blank' class='inline-flex items-center text-xs text-sky-600 hover:text-sky-700 bg-sky-50 px-2.5 py-1.5 rounded-md font-medium transition-colors'><i class='fas fa-image mr-1.5'></i> ดูรูปภาพ</a>" 
                                            : "<span class='text-slate-400 text-xs bg-slate-50 px-2.5 py-1.5 rounded-md'>ไม่มีรูป</span>";

                                        echo "
                                        <tr class='hover:bg-slate-50 transition-colors'>
                                            <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                            <td class='px-6 py-4 font-bold text-sky-600'>{$row['ticket_no']}</td>
                                            <td class='px-6 py-4'>
                                                <div class='text-slate-800 font-semibold'>{$row['reporter_name']}</div>
                                                <div class='text-slate-500 text-xs mt-0.5'><i class='fas fa-phone-alt mr-1 opacity-70'></i> {$row['phone_number']}</div>
                                            </td>
                                            <td class='px-6 py-4 text-slate-600 font-medium'>{$row['location']}</td>
                                            <td class='px-6 py-4'>
                                                <div class='text-slate-800 font-semibold'>{$row['equipment_type']}</div>
                                                <div class='text-slate-500 text-xs mt-0.5 max-w-[200px] truncate' title='{$row['problem_desc']}'>{$row['problem_desc']}</div>
                                            </td>
                                            <td class='px-6 py-4'>{$imageHtml}</td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {$statusClass}'>
                                                    <span class='w-1.5 h-1.5 rounded-full bg-current mr-1.5'></span>{$row['status']}
                                                </span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button class='w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition-colors flex items-center justify-center border border-emerald-100' title='รับงาน/เปลี่ยนสถานะ'>
                                                        <i class='fas fa-clipboard-check'></i>
                                                    </button>
                                                    <button class='w-8 h-8 rounded-lg bg-slate-50 text-slate-600 hover:bg-slate-100 transition-colors flex items-center justify-center border border-slate-200' title='ดูรายละเอียดเต็ม'>
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
            
            <!-- Sections สำหรับเมนูอื่นๆ -->
            <div id="assign" class="section hidden"><h2 class="text-xl font-bold text-slate-800">ระบบรับงานและมอบหมายช่าง</h2></div>
            <div id="assets" class="section hidden"><h2 class="text-xl font-bold text-slate-800">ฐานข้อมูลอุปกรณ์และครุภัณฑ์</h2></div>
            <div id="users" class="section hidden"><h2 class="text-xl font-bold text-slate-800">จัดการสิทธิ์และผู้ใช้งาน</h2></div>
            <div id="reports" class="section hidden"><h2 class="text-xl font-bold text-slate-800">รายงานสรุปผลการปฏิบัติงาน</h2></div>

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
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            event.currentTarget.classList.add('active-btn');
            
            document.getElementById('headerTitle').innerText = pageTitles[id];
        }
    </script>
</body>
</html>