<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap'); body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white p-8 rounded-3xl shadow-xl border border-slate-100">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-slate-800">เข้าสู่ระบบ</h2>
            <p class="text-slate-500 text-sm">สำหรับผู้ดูแลระบบหรือผู้บริหาร</p>
        </div>
        
        <form action="auth.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input type="text" name="username" class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none transition" required placeholder="ชื่อผู้ใช้งาน">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" class="w-full border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none transition" required placeholder="รหัสผ่าน">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-4 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                เข้าสู่ระบบ
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm text-slate-400 hover:text-blue-600 transition">← กลับสู่หน้าหลัก</a>
        </div>
    </div>

</body>
</html>