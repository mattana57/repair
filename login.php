<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- เปลี่ยนมาใช้ Kanit เพื่อความทันสมัยและเข้ากับหน้าอื่นๆ ของระบบ -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-blue-600 selection:text-white">

    <!-- พื้นหลังตกแต่ง (Background Element) ให้ดูมีมิติแบบไม่รก -->
    <div class="absolute top-0 left-0 w-full h-[40vh] bg-gradient-to-b from-blue-700 to-blue-600 rounded-b-[4rem] md:rounded-b-[6rem] shadow-lg z-0"></div>

    <!-- กล่องเข้าสู่ระบบ (Login Card) -->
    <div class="w-full max-w-md bg-white p-8 md:p-10 rounded-[2rem] shadow-2xl shadow-blue-900/10 z-10 relative border border-slate-100">
        
        <!-- ส่วนหัว (Header) -->
        <div class="flex flex-col items-center mb-8">
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-3xl mb-5 border border-blue-100 shadow-sm">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
            <p class="text-slate-500 text-sm mt-1.5 text-center font-medium">MBS REPAIR SYSTEM<br>คณะการบัญชีและการจัดการ</p>
        </div>
        
        <!-- ฟอร์มเข้าสู่ระบบ (Form) -->
        <form action="auth.php" method="POST" class="space-y-5">
            
            <!-- ช่อง Username -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">ชื่อผู้ใช้งาน (Username)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-user text-slate-400 text-sm"></i>
                    </div>
                    <input type="text" name="username" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-11 pr-4 py-3.5 text-sm font-medium text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-blue-500/15 focus:border-blue-500 outline-none transition-all" required placeholder="ระบุชื่อผู้ใช้งาน">
                </div>
            </div>
            
            <!-- ช่อง Password -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-2">รหัสผ่าน (Password)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-slate-400 text-sm"></i>
                    </div>
                    <!-- ซ่อนรหัสผ่านเป็นค่าเริ่มต้น[cite: 2] -->
                    <input type="password" id="password" name="password" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-11 pr-12 py-3.5 text-sm font-medium text-slate-900 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-blue-500/15 focus:border-blue-500 outline-none transition-all" required placeholder="ระบุรหัสผ่าน">
                    <!-- ปุ่มเปิดปิดตา[cite: 2] -->
                    <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-blue-600 focus:outline-none transition-colors" onclick="togglePassword()">
                        <i id="eyeIcon" class="fas fa-eye text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- ปุ่ม Submit -->
            <button type="submit" class="w-full mt-2 bg-blue-600 text-white font-bold text-sm py-4 rounded-xl hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-600/30 transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                เข้าสู่ระบบ <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        
        <!-- ลิงก์กลับหน้าหลัก -->
        <div class="mt-8 text-center border-t border-slate-100 pt-6">
            <a href="index.php" class="text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors flex items-center justify-center gap-2">
                <i class="fas fa-home"></i> กลับสู่หน้าหลัก
            </a>
        </div>
    </div>

    <!-- Script สำหรับปุ่มแสดงรหัสผ่าน[cite: 2] -->
    <script>
        function togglePassword() {
            var x = document.getElementById("password");
            var icon = document.getElementById("eyeIcon");
            if (x.type === "password") {
                x.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                x.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>