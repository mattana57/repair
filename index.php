<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>MBS Smart Maintenance | คณะการบัญชีและการจัดการ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap');
        body { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body class="bg-slate-50">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm py-4 px-6 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-tools text-white text-sm"></i>
            </div>
            <span class="font-bold text-slate-800 tracking-tight">MBS Maintenance</span>
        </div>
        <a href="login.php" class="text-sm text-slate-600 hover:text-blue-600 font-medium">เข้าสู่ระบบ (Admin)</a>
    </nav>

    <!-- Hero Section -->
    <header class="bg-white py-16 px-6 text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-slate-800 mb-4">ระบบแจ้งซ่อมออนไลน์</h1>
        <p class="text-slate-500 text-lg mb-8 max-w-xl mx-auto">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม พร้อมให้บริการดูแลความเรียบร้อยของอุปกรณ์และอาคารสถานที่</p>
        <a href="report_form.html" class="inline-block bg-blue-600 text-white px-8 py-4 rounded-xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition transform hover:-translate-y-1">
            <i class="fas fa-plus mr-2"></i> แจ้งซ่อมทันที
        </a>
    </header>

    <!-- Features Section -->
    <section class="max-w-4xl mx-auto py-12 px-6 grid md:grid-cols-3 gap-6 text-center">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <i class="fas fa-bolt text-2xl text-blue-500 mb-4"></i>
            <h3 class="font-bold mb-2">รวดเร็ว</h3>
            <p class="text-sm text-slate-500">แจ้งปัญหาผ่านมือถือได้ทันที ไม่ต้องเดินไปแจ้งด้วยตัวเอง</p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <i class="fas fa-chart-line text-2xl text-blue-500 mb-4"></i>
            <h3 class="font-bold mb-2">ตรวจสอบได้</h3>
            <p class="text-sm text-slate-500">ติดตามสถานะงานซ่อมผ่านระบบออนไลน์ได้อย่างแม่นยำ</p>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <i class="fas fa-shield-alt text-2xl text-blue-500 mb-4"></i>
            <h3 class="font-bold mb-2">มาตรฐาน</h3>
            <p class="text-sm text-slate-500">จัดการข้อมูลด้วยระบบฐานข้อมูลที่มีความปลอดภัยสูง</p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-8 text-center text-slate-400 text-sm">
        <p>© 2026 คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
    </footer>

</body>
</html>