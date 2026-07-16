<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>MBS Smart Maintenance | คณะการบัญชีฯ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #0f172a; color: white; }
        /* Glassmorphism Effect */
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .input-dark { background: rgba(0, 0, 0, 0.2) !important; border: 1px solid #334155 !important; color: white !important; }
        .input-dark:focus { border-color: #38bdf8 !important; outline: none; box-shadow: 0 0 10px rgba(56, 189, 248, 0.3); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4 md:p-8">

<div class="max-w-xl mx-auto">
    <!-- Header -->
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-sky-400 to-blue-600 bg-clip-text text-transparent">MBS MAINTENANCE</h1>
        <p class="text-slate-400">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
    </div>

    <!-- ฟอร์ม -->
    <div class="glass-card p-6 md:p-8 rounded-3xl shadow-2xl">
        <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
            
            <!-- ชื่อผู้แจ้ง -->
            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">ชื่อ-นามสกุล (ผู้แจ้ง)</label>
                <input type="text" name="reporter_name" class="w-full p-4 rounded-2xl input-dark" required placeholder="ระบุชื่อจริงของคุณ">
            </div>

            <!-- อุปกรณ์ -->
            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">อุปกรณ์ที่มีปัญหา</label>
                <select name="equipment_type" id="equipSelect" class="w-full p-4 rounded-2xl input-dark" onchange="checkOther()">
                    <option value="แอร์">แอร์</option>
                    <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                    <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                    <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                    <option value="ไมค์">ไมค์</option>
                    <option value="other">อื่นๆ (ระบุ...)</option>
                </select>
                <input type="text" name="other_equip" id="otherInput" class="w-full p-4 rounded-2xl mt-2 hidden input-dark" placeholder="ระบุชื่ออุปกรณ์">
            </div>

            <!-- ตึกและห้อง -->
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">เลือกตึก</label>
                    <select name="building" class="w-full p-4 rounded-2xl input-dark">
                        <option value="SBB">SBB</option>
                        <option value="ACC.BIZ">ACC.BIZ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">เลขห้อง</label>
                    <input type="text" name="room_no" class="w-full p-4 rounded-2xl input-dark" placeholder="เช่น 303" required>
                </div>
            </div>

            <!-- เบอร์ติดต่อ -->
            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">เบอร์ติดต่อกลับ</label>
                <input type="tel" name="phone_number" class="w-full p-4 rounded-2xl input-dark" required placeholder="08x-xxx-xxxx">
            </div>

            <!-- อาการเสีย -->
            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">อาการเสีย / รายละเอียด</label>
                <textarea name="problem_desc" class="w-full p-4 rounded-2xl input-dark" rows="3" required placeholder="อธิบายปัญหาที่พบ..."></textarea>
            </div>

            <!-- แนบภาพ -->
            <div class="mb-6">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">แนบภาพประกอบ</label>
                <input type="file" name="image_before" class="w-full p-4 rounded-2xl input-dark" accept="image/*">
            </div>

            <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white p-5 rounded-2xl font-bold shadow-lg hover:shadow-sky-500/50 transition-all">
                ส่งรายการแจ้งซ่อม
            </button>
        </form>
    </div>
</div>

<script>
    function checkOther() {
        const select = document.getElementById('equipSelect');
        const input = document.getElementById('otherInput');
        input.classList.toggle('hidden', select.value !== 'other');
    }
</script>
<!-- วางไว้ก่อนปิด tag </body> ใน index.php -->
<script>
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status')) {
        const status = urlParams.get('status');
        if (status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'แจ้งซ่อมสำเร็จ!',
                text: 'เลขที่ใบงาน: ' + urlParams.get('ticket'),
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#0284c7'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: urlParams.get('msg'),
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#ef4444'
            });
        }
        // ลบ Query String ออกจาก URL หลังจากแสดงป๊อบอัพเสร็จ
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>
</body>
</html>