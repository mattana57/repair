<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Admin System - คณะการบัญชีและการจัดการ</title>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-blue-900 text-white flex-shrink-0">
            <div class="p-6 border-b border-blue-800">
                <h1 class="text-xl font-bold text-yellow-400">Admin Dashboard</h1>
            </div>
            <nav class="p-4 space-y-2">
                <a href="#" class="block p-3 bg-blue-800 rounded">หน้าสรุปภาพรวม</a>
                <a href="#" class="block p-3 hover:bg-blue-800 rounded">รายการแจ้งซ่อม (Tickets)</a>
                <a href="#" class="block p-3 hover:bg-blue-800 rounded">จัดการช่าง (Technicians)</a>
                <a href="#" class="block p-3 hover:bg-blue-800 rounded">ฐานข้อมูลครุภัณฑ์</a>
                <a href="#" class="block p-3 hover:bg-blue-800 rounded">ประวัติการซ่อมย้อนหลัง</a>
                <a href="#" class="block p-3 hover:bg-blue-800 rounded text-yellow-300">รายงาน/ส่งออกข้อมูล</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold">แผงควบคุมหลัก</h2>
                <button class="bg-yellow-500 text-blue-900 px-6 py-2 rounded font-bold shadow-lg hover:bg-yellow-400">
                    + สร้างรายงาน (PDF/Excel)
                </button>
            </div>

            <!-- Dashboard Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Cards -->
                <div class="bg-white p-6 rounded shadow border-l-4 border-red-500">
                    <p class="text-sm text-gray-500">รอรับเรื่อง</p>
                    <span class="text-3xl font-bold">128</span>
                </div>
                <div class="bg-white p-6 rounded shadow border-l-4 border-yellow-500">
                    <p class="text-sm text-gray-500">กำลังดำเนินการ</p>
                    <span class="text-3xl font-bold">45</span>
                </div>
                <div class="bg-white p-6 rounded shadow border-l-4 border-orange-500">
                    <p class="text-sm text-gray-500">รออะไหล่</p>
                    <span class="text-3xl font-bold">18</span>
                </div>
                <div class="bg-white p-6 rounded shadow border-l-4 border-green-500">
                    <p class="text-sm text-gray-500">ปิดงานสำเร็จ</p>
                    <span class="text-3xl font-bold">65</span>
                </div>
            </div>

            <!-- Main Tables Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- รายการแจ้งซ่อม -->
                <div class="bg-white p-6 rounded shadow">
                    <h3 class="font-bold mb-4">รายการแจ้งซ่อมล่าสุด</h3>
                    <table class="w-full text-sm">
                        <tr class="border-b text-gray-400 text-left"><th class="py-2">เลขที่</th><th class="py-2">อุปกรณ์</th><th class="py-2">สถานะ</th></tr>
                        <tr class="border-b"><td class="py-3">MR-001</td><td class="py-3">Printer Canon</td><td class="py-3 text-red-500">รอดำเนินการ</td></tr>
                        <tr class="border-b"><td class="py-3">MR-002</td><td class="py-3">คอมพิวเตอร์</td><td class="py-3 text-yellow-500">กำลังซ่อม</td></tr>
                    </table>
                </div>

                <!-- ข้อมูลช่าง / สถิติ -->
                <div class="bg-white p-6 rounded shadow">
                    <h3 class="font-bold mb-4">ประสิทธิภาพช่าง (Performance)</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>นายสมชาย (ไฟฟ้า)</span><span>92%</span></div>
                            <div class="w-full bg-gray-200 h-2 rounded"><div class="bg-blue-600 h-2 rounded" style="width: 92%"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>นางสาวสมศรี (คอมพิวเตอร์)</span><span>85%</span></div>
                            <div class="w-full bg-gray-200 h-2 rounded"><div class="bg-blue-600 h-2 rounded" style="width: 85%"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>