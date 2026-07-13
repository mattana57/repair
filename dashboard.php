<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSU Smart Maintenance | Professional Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #0f172a; color: #f1f5f9; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .menu-active { background: #0284c7 !important; color: white !important; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="flex h-screen w-full overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 glass flex flex-col shrink-0">
            <div class="h-16 flex items-center justify-center border-b border-white/10">
                <h1 class="text-xl font-bold text-white"><i class="fas fa-tools text-sky-400 mr-2"></i>MSU MAINT</h1>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <button onclick="switchPage('page-dashboard', this)" class="w-full text-left px-4 py-3 rounded-xl menu-btn menu-active transition-all"><i class="fas fa-chart-line w-8"></i> ภาพรวม</button>
                <button onclick="switchPage('page-repairs', this)" class="w-full text-left px-4 py-3 hover:bg-slate-700/50 rounded-xl menu-btn transition-all"><i class="fas fa-clipboard-list w-8"></i> รายการแจ้งซ่อม</button>
                <button onclick="switchPage('page-assign', this)" class="w-full text-left px-4 py-3 hover:bg-slate-700/50 rounded-xl menu-btn transition-all"><i class="fas fa-user-cog w-8"></i> มอบหมายงาน</button>
            </nav>
        </aside>

        <!-- Main -->
        <main class="flex-1 overflow-y-auto p-8">
            <header class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold text-white" id="headerTitle">ภาพรวม (Dashboard)</h2>
                <div class="glass px-5 py-2 rounded-full text-sm font-medium"><i class="fas fa-user-shield mr-2 text-sky-400"></i> Admin Management</div>
            </header>

            <!-- Dashboard Page -->
            <div id="page-dashboard" class="page-section space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="glass p-6 rounded-3xl border-l-4 border-sky-500">
                        <p class="text-slate-400 text-xs uppercase tracking-widest">งานทั้งหมด</p>
                        <p class="text-3xl font-bold mt-2">128</p>
                    </div>
                    <div class="glass p-6 rounded-3xl border-l-4 border-amber-500">
                        <p class="text-slate-400 text-xs uppercase tracking-widest">รอรับเรื่อง</p>
                        <p class="text-3xl font-bold mt-2">18</p>
                    </div>
                    <div class="glass p-6 rounded-3xl border-l-4 border-blue-400">
                        <p class="text-slate-400 text-xs uppercase tracking-widest">กำลังทำ</p>
                        <p class="text-3xl font-bold mt-2">45</p>
                    </div>
                    <div class="glass p-6 rounded-3xl border-l-4 border-emerald-500">
                        <p class="text-slate-400 text-xs uppercase tracking-widest">เสร็จแล้ว</p>
                        <p class="text-3xl font-bold mt-2">65</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="glass p-6 rounded-3xl"><canvas id="monthlyChart" class="h-64"></canvas></div>
                    <div class="glass p-6 rounded-3xl"><canvas id="equipmentPieChart" class="h-64"></canvas></div>
                </div>
            </div>

            <!-- Repairs Table Page -->
            <div id="page-repairs" class="page-section hidden">
                <div class="glass p-8 rounded-3xl overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-slate-400 text-sm uppercase">
                                <th class="pb-4">เลขที่ใบงาน</th>
                                <th class="pb-4">อุปกรณ์</th>
                                <th class="pb-4">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="text-white">
                            <tr class="border-t border-white/10">
                                <td class="py-4 text-sky-400 font-bold">MR-2026-0001</td>
                                <td class="py-4">คอมพิวเตอร์</td>
                                <td class="py-4"><span class="bg-amber-500/20 text-amber-400 px-3 py-1 rounded-full text-xs">รอรับเรื่อง</span></td>
                            </tr>
                        </tbody>
                    </table>
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
            const titles = {'page-dashboard':'ภาพรวม', 'page-repairs':'รายการแจ้งซ่อม', 'page-assign':'มอบหมายงาน'};
            document.getElementById('headerTitle').innerText = titles[pageId];
        }

        // Charts
        new Chart(document.getElementById('monthlyChart'), { type: 'line', data: { labels: ['ม.ค.', 'ก.พ.', 'มี.ค.'], datasets: [{ label: 'งาน', data: [12, 19, 3], borderColor: '#38bdf8', fill: true }] }, options: { responsive: true, maintainAspectRatio: false } });
        new Chart(document.getElementById('equipmentPieChart'), { type: 'doughnut', data: { labels: ['คอมฯ', 'แอร์'], datasets: [{ data: [60, 40], backgroundColor: ['#38bdf8', '#fbbf24'] }] }, options: { responsive: true, maintainAspectRatio: false } });
    </script>
</body>
</html>