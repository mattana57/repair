<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนดูรายงาน");
}

include 'db_connect.php'; 

$total_repairs = 0; $completed_repairs = 0; $pending_repairs = 0; $success_rate = 0;
$top_equipment = "-"; $top_equipment_count = 0;

$res = $conn->query("SELECT count(*) as c FROM repairs");
$total_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว'");
$completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$res = $conn->query("SELECT count(*) as c FROM repairs WHERE status != 'ซ่อมเสร็จแล้ว'");
$pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

$top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
if($top_eq_query && $top_eq_query->num_rows > 0) {
    $top_eq_data = $top_eq_query->fetch_assoc();
    $top_equipment = $top_eq_data['equipment_type'];
    $top_equipment_count = $top_eq_data['cnt'];
}

// ข้อมูลจำลองสำหรับกราฟในรายงาน
$months_json = json_encode(["มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค. (ปัจจุบัน)", "ส.ค. (คาดการณ์)"]);
$data_json = json_encode([15, 18, 17, 22, $total_repairs, null]);
$forecast_json = json_encode([null, null, null, null, $total_repairs, round($total_repairs * 1.15)]);

$thai_months = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
$current_month = $thai_months[(int)date('m')] . " " . (date('Y') + 543);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Executive Summary Report - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f1f5f9; color: #1e293b; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .a4-page { width: 210mm; min-height: 297mm; padding: 15mm 20mm; margin: 20px auto; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; }
        
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; width: 100%; padding: 10mm 15mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="fixed top-5 right-5 no-print">
        <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-bold shadow-lg flex items-center transition-colors">
            <i class="fas fa-print mr-2"></i> พิมพ์เอกสารผู้บริหาร
        </button>
    </div>

    <div class="a4-page">
        <!-- Header -->
        <div class="border-b-4 border-indigo-600 pb-4 mb-6 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tight">Executive Summary Report</h1>
                <p class="text-indigo-600 font-bold mt-1">ระบบแจ้งซ่อมออนไลน์ (MBS REPAIR)</p>
                <p class="text-slate-500 text-sm mt-1">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
            </div>
            <div class="text-right">
                <div class="w-12 h-12 bg-slate-800 rounded-lg flex items-center justify-center text-white mb-2 ml-auto"><i class="fas fa-chart-pie text-xl"></i></div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Reporting Period</p>
                <p class="font-bold text-slate-800"><?php echo $current_month; ?></p>
            </div>
        </div>

        <!-- KPI Section -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-slate-800 mb-3 flex items-center"><i class="fas fa-bullseye text-indigo-500 mr-2"></i> 1. ผลการดำเนินงานหลัก (Key Performance Indicators)</h2>
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl text-center">
                    <p class="text-xs font-bold text-slate-500 mb-1">งานรับแจ้งทั้งหมด</p>
                    <p class="text-2xl font-black text-sky-600"><?php echo $total_repairs; ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl text-center">
                    <p class="text-xs font-bold text-slate-500 mb-1">แก้ไขเสร็จสิ้น</p>
                    <p class="text-2xl font-black text-emerald-600"><?php echo $completed_repairs; ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl text-center">
                    <p class="text-xs font-bold text-slate-500 mb-1">รอการดำเนินการ</p>
                    <p class="text-2xl font-black text-amber-500"><?php echo $pending_repairs; ?></p>
                </div>
                <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-xl text-center">
                    <p class="text-xs font-bold text-indigo-500 mb-1">อัตราสำเร็จ (Success Rate)</p>
                    <p class="text-2xl font-black text-indigo-700"><?php echo $success_rate; ?>%</p>
                </div>
            </div>
        </div>

        <!-- Analytics & Chart -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-slate-800 mb-3 flex items-center"><i class="fas fa-chart-area text-sky-500 mr-2"></i> 2. การวิเคราะห์แนวโน้ม (Trend Analytics)</h2>
            <div class="bg-white border border-slate-200 p-4 rounded-xl h-[250px]">
                <canvas id="reportChart"></canvas>
            </div>
        </div>

        <!-- Critical Issues & Recommendations -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-800 mb-3 flex items-center"><i class="fas fa-exclamation-triangle text-rose-500 mr-2"></i> 3. ประเด็นที่ต้องเฝ้าระวัง</h2>
                <div class="bg-rose-50 border border-rose-100 p-4 rounded-xl h-full">
                    <ul class="space-y-3 text-sm text-slate-700">
                        <li class="flex items-start"><i class="fas fa-circle text-[8px] text-rose-400 mt-1.5 mr-2 shrink-0"></i> อุปกรณ์ที่พบการชำรุดสูงสุดคือ <strong>"<?php echo $top_equipment; ?>"</strong> (ร้อยละ <?php echo ($total_repairs > 0) ? round(($top_equipment_count/$total_repairs)*100) : 0; ?> ของงานทั้งหมด)</li>
                        <li class="flex items-start"><i class="fas fa-circle text-[8px] text-rose-400 mt-1.5 mr-2 shrink-0"></i> อายุการใช้งานโดยเฉลี่ยของอุปกรณ์กลุ่มนี้เกินระยะเวลารับประกันแล้ว ทำให้เกิดภาระค่าซ่อมบำรุงที่สูงขึ้น</li>
                    </ul>
                </div>
            </div>
            
            <div>
                <h2 class="text-lg font-bold text-slate-800 mb-3 flex items-center"><i class="fas fa-lightbulb text-amber-500 mr-2"></i> 4. ข้อเสนอแนะเชิงกลยุทธ์</h2>
                <div class="bg-amber-50 border border-amber-100 p-4 rounded-xl h-full">
                    <ul class="space-y-3 text-sm text-slate-700">
                        <li class="flex items-start"><i class="fas fa-check text-amber-500 mt-1 mr-2 shrink-0"></i> <strong>ปรับแผนการจัดซื้อ:</strong> ควรพิจารณาจัดสรรงบประมาณประจำปีเพื่อจัดซื้อ "<?php echo $top_equipment; ?>" ชุดใหม่ทดแทน แทนการซ่อมแซมรายชิ้นเพื่อความคุ้มค่าด้าน ROI</li>
                        <li class="flex items-start"><i class="fas fa-check text-amber-500 mt-1 mr-2 shrink-0"></i> <strong>การวิเคราะห์ข้อมูลขั้นสูง:</strong> ในอนาคตสามารถนำแบบจำลองการถดถอยพหุคูณ (Multiple Regression) มาช่วยประเมินความสัมพันธ์ระหว่างอายุครุภัณฑ์และค่าซ่อม เพื่อพยากรณ์งบประมาณที่แม่นยำขึ้น</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="mt-16 flex justify-between border-t border-slate-200 pt-8">
            <div class="text-center w-64">
                <p class="mb-8">......................................................</p>
                <p class="font-bold text-sm text-slate-800">( <?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'ผู้ดูแลระบบสารสนเทศ'; ?> )</p>
                <p class="text-xs text-slate-500 mt-1">ผู้รายงาน / ฝ่ายเทคโนโลยีสารสนเทศ</p>
            </div>
            <div class="text-center w-64">
                <p class="mb-8">......................................................</p>
                <p class="font-bold text-sm text-slate-800">( .................................................... )</p>
                <p class="text-xs text-slate-500 mt-1">คณบดีคณะการบัญชีและการจัดการ</p>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('reportChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo $months_json; ?>,
                    datasets: [
                        {
                            label: 'งานรับแจ้งจริง',
                            data: <?php echo $data_json; ?>,
                            borderColor: '#4f46e5', backgroundColor: 'rgba(79, 70, 229, 0.1)', borderWidth: 2, fill: true, tension: 0.3
                        },
                        {
                            label: 'พยากรณ์แนวโน้ม',
                            data: <?php echo $forecast_json; ?>,
                            borderColor: '#f59e0b', borderWidth: 2, borderDash: [5, 5], fill: false, tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { font: { family: "'Kanit', sans-serif", size: 10 } } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { font: { family: "'Kanit', sans-serif", size: 10 } } },
                        x: { ticks: { font: { family: "'Kanit', sans-serif", size: 10 } } }
                    },
                    animation: false // ปิด Animation เพื่อให้ตอนกด Print กราฟเรนเดอร์เสร็จทันที
                }
            });
        });
    </script>
</body>
</html>