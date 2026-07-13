<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Repair Service | มหาวิทยาลัยมหาสารคาม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #f1f5f9; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .input-style { background: #f8fafc !important; border: 1px solid #cbd5e1 !important; transition: 0.3s; }
        .input-style:focus { border-color: #003399 !important; ring: 2px solid #bfdbfe; }
    </style>
</head>
<body class="p-4 md:p-8">

<div class="max-w-xl mx-auto">
    <!-- Header -->
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-semibold text-blue-900">แจ้งซ่อมออนไลน์</h1>
        <p class="text-blue-700/70">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
    </div>

    <div class="glass-card p-6 rounded-3xl shadow-xl">
        <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
            
            <!-- ชื่อผู้แจ้ง (แบบถาวรจะดีมาก) -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">ชื่อ-นามสกุล (ผู้แจ้ง)</label>
                <input type="text" name="reporter_name" class="w-full p-3 rounded-xl input-style" required placeholder="ระบุชื่อผู้แจ้ง">
            </div>

            <!-- เลือกประเภทอุปกรณ์ -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">อุปกรณ์ที่มีปัญหา</label>
                <select name="equipment_type" id="equipSelect" class="w-full p-3 rounded-xl input-style" onchange="checkOther()">
                    <option value="แอร์">แอร์</option>
                    <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                    <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                    <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                    <option value="ไมค์">ไมค์</option>
                    <option value="other">อื่นๆ (ระบุ...)</option>
                </select>
                <input type="text" name="other_equip" id="otherInput" class="w-full p-3 rounded-xl mt-2 hidden input-style" placeholder="ระบุอุปกรณ์อื่นๆ">
            </div>

            <!-- เลือกตึกและเลขห้อง -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">เลือกตึก</label>
                    <select name="building" class="w-full p-3 rounded-xl input-style">
                        <option value="SBB">SBB</option>
                        <option value="ACC.BIZ">ACC.BIZ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">เลขห้อง</label>
                    <input type="text" name="room_no" class="w-full p-3 rounded-xl input-style" placeholder="ระบุเลขห้อง" required>
                </div>
            </div>

            <!-- เบอร์ติดต่อ -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">เบอร์ติดต่อกลับ</label>
                <input type="tel" name="phone_number" class="w-full p-3 rounded-xl input-style" required>
            </div>

            <!-- อาการเสีย -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">อาการเสีย / รายละเอียด</label>
                <textarea name="problem_desc" class="w-full p-3 rounded-xl input-style" rows="3" required></textarea>
            </div>

            <!-- แนบภาพ -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">แนบภาพประกอบ</label>
                <input type="file" name="image_before" class="w-full p-3 rounded-xl input-style" accept="image/*">
            </div>

            <button type="submit" class="w-full bg-blue-800 text-white p-4 rounded-2xl font-semibold hover:bg-blue-900 transition-all shadow-lg hover:shadow-blue-200">
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
</body>
</html>