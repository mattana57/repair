<?php
session_start();
// ตรวจสอบสิทธิ์ว่าใช่ผู้บริหารหรือไม่
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'executive') {
    header("Location: login.php");
    exit();
}
require_once 'db_connect.php';

// ดึงข้อมูลสรุปสำหรับผู้บริหาร
$total = $conn->query("SELECT COUNT(*) as count FROM repairs")->fetch_assoc()['count'];
$finished = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status IN ('ซ่อมเสร็จแล้ว', 'ปิดงาน')")->fetch_assoc()['count'];
$pending = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'รอรับเรื่อง'")->fetch_assoc()['count'];
$success_rate = ($total > 0) ? round(($finished / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Executive Dashboard | MBS Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>@import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap'); body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen">

    <nav class="bg-indigo-900 text-white p-4 flex justify-between items-center">
        <span class="font-bold text-lg">MBS Executive Overview</span>
        <a href="logout.php" class="text-sm bg-indigo-700 px-4 py-2 rounded-lg hover:bg-indigo-600">ออกจากระบบ</a>
    </nav>

    <main class="p-6 max-w-6xl mx-auto">
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-blue-500">
                <p class="text-slate-500">ยอดแจ้งซ่อมรวม</p>
                <p class="text-3xl font-bold"><?php echo $total; ?> งาน</p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-green-500">
                <p class="text-slate-500">อัตราปิดงานสำเร็จ</p>
                <p class="text-3xl font-bold"><?php echo $success_rate; ?>%</p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-amber-500">
                <p class="text-slate-500">งานที่ยังค้างอยู่</p>
                <p class="text-3xl font-bold"><?php echo $pending; ?> งาน</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm">
            <h3 class="font-bold text-slate-800 mb-6">สรุปสถานะงาน (ภาพรวม)</h3>
            <div class="h-80"><canvas id="execChart"></canvas></div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('execChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['งานทั้งหมด', 'ซ่อมเสร็จแล้ว', 'รอรับเรื่อง'],
                datasets: [{
                    label: 'จำนวน (งาน)',
                    data: [<?php echo "$total, $finished, $pending"; ?>],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</body>
</html>