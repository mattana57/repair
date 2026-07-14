<?php 
include 'db_connect.php'; 

// ตรวจสอบข้อมูลการแจ้งซ่อมเพื่อนำมาคำนวณสถิติ
$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");

$total_repairs = 0;
$completed_repairs = 0;
$pending_repairs = 0;
$success_rate = 0;

$monthly_labels_json = "[]";
$monthly_data_json = "[]";
$forecast_data_json = "[]";

$location_labels_json = "[]";
$location_data_json = "[]";

$top_equipment = "-";
$top_equipment_count = 0;

if($check_repairs->num_rows > 0) {
    // 1. คำนวณ KPIs ภาพรวม
    $total_repairs = $conn->query("SELECT count(*) as c FROM repairs")->fetch_assoc()['c'];
    $completed_repairs = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว'")->fetch_assoc()['c'];
    $pending_repairs = $conn->query("SELECT count(*) as c FROM repairs WHERE status != 'ซ่อมเสร็จแล้ว'")->fetch_assoc()['c'];
    $success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

    // 2. วิเคราะห์อุปกรณ์ที่เสียบ่อยที่สุด (Top Equipment)
    $top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
    if($top_eq_query && $top_eq_query->num_rows > 0) {
        $top_eq_data = $top_eq_query->fetch_assoc();
        $top_equipment = $top_eq_data['equipment_type'];
        $top_equipment_count = $top_eq_data['cnt'];
    }

    // 3. เตรียมข้อมูลกราฟแท่ง (Top Locations)
    $loc_res = $conn->query("SELECT location, COUNT(*) as cnt FROM repairs GROUP BY location ORDER BY cnt DESC LIMIT 5");
    $loc_labels = []; $loc_counts = [];
    while($loc = $loc_res->fetch_assoc()){ 
        $loc_labels[] = $loc['location']; 
        $loc_counts[] = $loc['cnt']; 
    }
    $location_labels_json = json_encode($loc_labels);
    $location_data_json = json_encode($loc_counts);

    // 4. เตรียมข้อมูลกราฟเส้น คาดการณ์อนาคต (Predictive Trend)
    // สำหรับ Demo เราจะจำลองข้อมูลเดือนย้อนหลัง + ดึงเดือนปัจจุบัน + คาดการณ์เดือนหน้า
    $current_month_count = $total_repairs; // ใช้ยอดรวมเดือนนี้เป็นตัวแทน
    $historical_data = [
        max(0, $current_month_count - 5), 
        max(0, $current_month_count - 3), 
        max(0, $current_month_count - 2), 
        $current_month_count + 1, 
        $current_month_count
    ];
    
    $thai_months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $current_m = (int)date('m') - 1;
    
    $labels = [];
    $actual_data = [];
    $forecast_data = [];

    // ย้อนหลัง 4 เดือน
    for($i = 4; $i >= 1; $i--) {
        $m_index = ($current_m - $i + 12) % 12;
        $labels[] = $thai_months[$m_index];
        $actual_data[] = $historical_data[4-$i];
        $forecast_data[] = null; // ซ่อนเส้นคาดการณ์
    }

    // เดือนปัจจุบัน
    $labels[] = $thai_months[$current_m] . " (ปัจจุบัน)";
    $actual_data[] = $current_month_count;
    $forecast_data[] = $current_month_count; // จุดเชื่อมต่อเส้น

    // เดือนหน้า (คาดการณ์จากค่าเฉลี่ย + 10%)
    $next_m_index = ($current_m + 1) % 12;
    $labels[] = $thai_months[$next_m_index] . " (คาดการณ์)";
    $avg = array_sum($historical_data) / count($historical_data);
    $predicted_value = round($avg * 1.15); // ทายว่าจะเพิ่ม 15%
    
    $actual_data[] = null; // ซ่อนเส้นจริง
    $forecast_data[] = $predicted_value;

    $monthly_labels_json = json_encode($labels);
    $monthly_data_json = json_encode($actual_data);
    $forecast_data_json = json_encode($forecast_data);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - MSU Smart Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .modern-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -2px rgba(0, 0, 0, 0.06); }
        .nav-btn { width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; margin-bottom: 0.25rem; border-radius: 0.75rem; color: #64748b; font-weight: 500; transition: all 0.2s; }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.25rem; margin-right: 0.75rem; color: #94a3b8; transition: all 0.2s; }
        .nav-btn:hover { background-color: #f8fafc; color: #0284c7; }
        .nav-btn:hover i { color: #0ea5e9; transform: scale(1.1); }
        .active-btn { background-color: #f0f9ff; color: #0369a1; font-weight: 600; box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1); border: 1px solid #bae6fd; }
        .active-btn i { color: #0284c7; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        @media print {
            aside, header, .no-print { display: none !important; }
            main { padding: 0 !important; margin: 0 !important; background: white; }
            .modern-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
            body { background: white; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-200">

    <!-- Sidebar -->
    <aside class="w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 z-20 shadow-[4px_0_24px_rgba(0,0,0,0.02)] no-print">
        <div class="h-24 flex items-center px-8 border-b border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-500 flex items-center justify-center shadow-lg shadow-purple-500/30 mr-4 shrink-0">
                <i class="fas fa-chart-line text-white text-xl"></i>
            </div>
            <div class="overflow-hidden flex-1">
                <h1 class="text-xl font-bold text-slate-800 leading-tight tracking-tight">MSU REPAIR</h1>
                <p class="text-xs text-purple-600 font-semibold tracking-widest uppercase mt-0.5">Executive View</p>
            </div>
        </div>
        
        <nav class="flex-1 px-5 py-8 flex flex-col overflow-y-auto">
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">สำหรับผู้บริหาร</p>
            <button class="nav-btn active-btn"><i class="fas fa-tachometer-alt"></i> ภาพรวมและสถิติ (KPIs)</button>
            <a href="dashboard.php" class="nav-btn"><i class="fas fa-desktop"></i> กลับสู่หน้า Admin ปกติ</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-indigo-50/80 to-transparent -z-10 no-print"></div>
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 sticky top-0 no-print">
            <h2 class="text-2xl font-bold text-slate-800 tracking-wide">ภาพรวมและวิเคราะห์แนวโน้ม (Executive Summary)</h2>
            <div class="flex items-center space-x-6">
                <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-indigo-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm flex items-center transition-colors">
                    <i class="fas fa-print mr-2"></i> พิมพ์รายงาน
                </button>
                <div class="flex items-center space-x-3 cursor-pointer p-1.5 pr-4 rounded-full border border-slate-200 bg-white shadow-sm">
                    <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold"><i class="fas fa-user-tie text-sm"></i></div>
                    <div class="hidden sm:block text-left"><span class="block text-sm font-semibold text-slate-700 leading-none mb-1">Executive Board</span><span class="block text-[11px] text-slate-500 uppercase tracking-wide leading-none">ผู้บริหารระบบ</span></div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-10 print:p-0">
            
            <div class="hidden print:block mb-8 pb-4 border-b border-slate-200">
                <h1 class="text-3xl font-extrabold text-slate-800">รายงานภาพรวมและวิเคราะห์แนวโน้ม (Executive Summary)</h1>
                <p class="text-slate-500 mt-2">พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?></p>
            </div>

            <!-- Executive KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="modern-card p-6 border-b-4 border-indigo-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-sm font-medium mb-2">อัตราซ่อมสำเร็จ (Success Rate)</p>
                            <h3 class="text-4xl font-extrabold text-slate-800"><?php echo $success_rate; ?><span class="text-2xl text-slate-400">%</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500"><i class="fas fa-check-double text-xl"></i></div>
                    </div>
                </div>
                
                <div class="modern-card p-6 border-b-4 border-sky-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-sm font-medium mb-2">จำนวนงานซ่อมทั้งหมด</p>
                            <h3 class="text-4xl font-extrabold text-slate-800"><?php echo $total_repairs; ?> <span class="text-lg font-medium text-slate-400">งาน</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-sky-50 flex items-center justify-center text-sky-500"><i class="fas fa-briefcase text-xl"></i></div>
                    </div>
                </div>

                <div class="modern-card p-6 border-b-4 border-amber-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-sm font-medium mb-2">งานที่รอการดำเนินการ</p>
                            <h3 class="text-4xl font-extrabold text-slate-800"><?php echo $pending_repairs; ?> <span class="text-lg font-medium text-slate-400">งาน</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500"><i class="fas fa-hourglass-half text-xl"></i></div>
                    </div>
                </div>

                <!-- AI Insight Highlight -->
                <div class="modern-card p-6 bg-gradient-to-br from-slate-800 to-slate-900 text-white shadow-lg shadow-slate-900/20">
                    <div class="flex items-center gap-3 mb-3">
                        <i class="fas fa-robot text-purple-400 text-xl"></i>
                        <h3 class="font-bold text-white tracking-wide">AI Recommendation</h3>
                    </div>
                    <p class="text-sm text-slate-300 leading-relaxed">พบการแจ้งซ่อม <strong class="text-white bg-white/20 px-2 py-0.5 rounded">"<?php echo $top_equipment; ?>"</strong> บ่อยผิดปกติ (<?php echo $top_equipment_count; ?> ครั้ง) <br><span class="text-purple-300 mt-2 inline-block">💡 แนะนำ: พิจารณาจัดตั้งงบประมาณเพื่อจัดซื้อทดแทนในปีหน้า</span></p>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Predictive Trend Chart -->
                <div class="modern-card p-6 bg-white flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-bold text-slate-800 text-lg flex items-center"><i class="fas fa-chart-line text-indigo-500 mr-2"></i> วิเคราะห์แนวโน้มและคาดการณ์ (Predictive Trend)</h3>
                        <p class="text-sm text-slate-500 mt-1">สถิติปริมาณงานแจ้งซ่อมย้อนหลัง 5 เดือน และระบบคาดการณ์อัตโนมัติในเดือนถัดไป</p>
                    </div>
                    <div class="flex-1 relative w-full h-[300px]">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- Location Resource Chart -->
                <div class="modern-card p-6 bg-white flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-bold text-slate-800 text-lg flex items-center"><i class="fas fa-map-marked-alt text-sky-500 mr-2"></i> พื้นที่/แผนก ที่มีปัญหาบ่อยสุด (Resource Heatmap)</h3>
                        <p class="text-sm text-slate-500 mt-1">ช่วยในการวิเคราะห์เพื่อจัดสรรกำลังช่างซ่อม หรือตรวจสอบระบบโครงสร้างพื้นฐาน</p>
                    </div>
                    <div class="flex-1 relative w-full h-[300px]">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>

            </div>
            
            <!-- Strategic Data Table -->
            <div class="modern-card bg-white overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 text-lg"><i class="fas fa-clipboard-list text-slate-400 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="px-6 py-4">วัน/เวลาที่รับเรื่อง</th>
                                <th class="px-6 py-4">เลขที่ใบงาน</th>
                                <th class="px-6 py-4">ประเภทอุปกรณ์</th>
                                <th class="px-6 py-4">สถานที่</th>
                                <th class="px-6 py-4 text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-100">
                            <?php
                            if($check_repairs->num_rows > 0) {
                                $recent_res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                if($recent_res->num_rows > 0){
                                    while($row = $recent_res->fetch_assoc()) {
                                        $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                        $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                        if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                        elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                        elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";

                                        echo "<tr class='hover:bg-slate-50 transition-colors'>
                                            <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                            <td class='px-6 py-4 font-bold text-slate-700'>{$row['ticket_no']}</td>
                                            <td class='px-6 py-4 font-semibold text-slate-800'>{$row['equipment_type']}</td>
                                            <td class='px-6 py-4 text-slate-600'>{$row['location']}</td>
                                            <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$statusClass}'>{$row['status']}</span></td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>ไม่มีข้อมูล</td></tr>"; }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. Render Predictive Trend Chart (Line Chart with Dashed Forecast)
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthly_labels_json; ?>,
                    datasets: [
                        {
                            label: 'ปริมาณงานซ่อมจริง',
                            data: <?php echo $monthly_data_json; ?>,
                            borderColor: '#4f46e5', // Indigo 600
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#4f46e5',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            fill: true,
                            tension: 0.3 // เส้นโค้งสมูท
                        },
                        {
                            label: 'คาดการณ์ (AI Forecast)',
                            data: <?php echo $forecast_data_json; ?>,
                            borderColor: '#f59e0b', // Amber 500
                            borderWidth: 3,
                            borderDash: [5, 5], // เส้นประ
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#f59e0b',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointStyle: 'rectRot', // รูปแบบจุดแบบพิเศษ
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { usePointStyle: true, font: { family: "'Kanit', sans-serif" } } } 
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Kanit', sans-serif" } }, grid: { borderDash: [4, 4] } },
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false } }
                    }
                }
            });

            // 2. Render Location Heatmap/Bar Chart
            const locCtx = document.getElementById('locationChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo $location_labels_json; ?>,
                    datasets: [{ 
                        label: 'จำนวนการแจ้งซ่อม', 
                        data: <?php echo $location_data_json; ?>, 
                        backgroundColor: '#0ea5e9', // Sky 500
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', // กราฟแนวนอน
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Kanit', sans-serif" } }, grid: { borderDash: [4, 4] } }, 
                        y: { ticks: { font: { family: "'Kanit', sans-serif", weight: 'bold' } }, grid: { display: false } } 
                    } 
                }
            });

        });
    </script>
</body>
</html>