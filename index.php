<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>MSU Smart Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #0f172a; color: white; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.1); }
        input, select, textarea { background: rgba(0, 0, 0, 0.3) !important; color: white !important; border: 1px solid #334155 !important; }
        input:focus, select:focus, textarea:focus { border-color: #38bdf8 !important; outline: none; box-shadow: 0 0 10px rgba(56, 189, 248, 0.3); }
        .btn-glow { transition: 0.4s; }
        .btn-glow:hover { box-shadow: 0 0 20px #38bdf8; background: #0284c7; }
    </style>
</head>
<body class="p-6">

<div class="max-w-md mx-auto">
    <!-- Header แบบล้ำๆ -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-sky-400 to-blue-600 bg-clip-text text-transparent">MSU MAINTENANCE</h1>
        <p class="text-slate-400 text-sm">คณะการบัญชีและการจัดการ</p>
    </div>

    <div class="glass p-6 rounded-3xl">
        <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
            
            <div class="mb-4">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">ผู้แจ้ง</label>
                <input type="text" name="reporter_name" class="w-full p-3 rounded-xl" required placeholder="ชื่อ-นามสกุล">
            </div>

            <div class="mb-4">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">ประเภทอุปกรณ์</label>
                <select name="equipment_type" id="equipSelect" class="w-full p-3 rounded-xl" onchange="checkOther()">
                    <option value="แอร์">แอร์</option>
                    <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                    <option value="other">อื่นๆ (ระบุ...)</option>
                </select>
                <input type="text" name="other_equip" id="otherInput" class="w-full p-3 rounded-xl mt-2 hidden" placeholder="ระบุชื่ออุปกรณ์">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">ตึก</label>
                    <select name="building" class="w-full p-3 rounded-xl">
                        <option>SBB</option><option>ACC.BIZ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">ห้อง</label>
                    <input type="text" name="room_no" class="w-full p-3 rounded-xl" placeholder="เช่น 303">
                </div>
            </div>

            <button type="submit" class="w-full btn-glow bg-sky-600 p-4 rounded-2xl font-bold mt-4 shadow-lg">
                ยืนยันการแจ้งซ่อม
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