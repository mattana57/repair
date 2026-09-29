<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<!-- 🌟 ขยายพื้นหลังสีม่วง 60/30/10 ให้เต็มหน้าจอตรงนี้ -->
<body class="min-h-screen bg-gradient-to-br from-violet-600 from-[60%] via-purple-800 via-[90%] to-indigo-500 flex items-center justify-center p-4 sm:p-6 md:p-10 selection:bg-purple-500 selection:text-white">

    <!-- โครงสร้างหลัก (เอาขอบและพื้นหลังการ์ดออก เพื่อให้กลืนไปกับพื้นหลังเต็มจอ) -->
    <div class="w-full max-w-[1000px] flex flex-col lg:flex-row items-center p-2 sm:p-4 lg:p-6">

        <!-- 👈 ฝั่งซ้าย: โลโก้และชื่อระบบ (คงเดิม 100%) -->
        <div class="w-full lg:w-1/2 p-6 lg:p-10 flex flex-col items-center justify-center text-center relative z-10">
            <div class="w-24 h-24 bg-white rounded-[1.5rem] flex items-center justify-center mb-6 shadow-md">
                <span class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">MBS</span>
            </div>
            <h3 class="text-3xl font-bold text-white tracking-wide">Repair System</h3>
            <p class="text-violet-200 mt-2 font-medium text-sm">ระบบแจ้งซ่อมและบำรุงรักษา</p>
        </div>

        <!-- 👉 ฝั่งขวา: กล่องฟอร์มสีขาวลอยตัว (คงเดิม 100%) -->
        <div class="w-full lg:w-1/2 flex justify-center items-center relative z-10 p-4 lg:p-6">
            <!-- กล่องขาวมีเอฟเฟกต์ยกตัวเมื่อ Hover -->
            <div class="w-full max-w-[420px] bg-white rounded-[2rem] p-8 lg:p-10 shadow-[0_25px_50px_rgba(0,0,0,0.15)] transform hover:-translate-y-2 transition-all duration-300 border border-slate-100">
                
                <!-- ส่วนหัว (Header) -->
                <div class="flex flex-col items-center mb-8 relative">
                    <div class="w-16 h-16 bg-gradient-to-br from-indigo-600 to-purple-500 text-white rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-[0_10px_20px_rgb(139,92,246,0.3)]">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <h2 class="text-2xl lg:text-3xl font-bold text-slate-800 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
                    <p class="text-slate-500 text-xs mt-2 text-center font-medium">คณะการบัญชีและการจัดการ (MBS)</p>
                </div>
                
                <!-- ฟอร์มเข้าสู่ระบบ -->
                <form action="auth.php" method="POST" class="space-y-6">
                    
                    <!-- ช่อง Username (มีเอฟเฟกต์ยกตัว) -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">Username</label>
                        <div class="relative group">
                            <input type="text" name="username" class="peer w-full bg-white border border-slate-100 shadow-[0_8px_20px_rgba(0,0,0,0.05)] rounded-2xl pl-12 pr-4 py-4 text-sm font-medium text-slate-800 placeholder-slate-400 focus:ring-4 focus:ring-purple-500/10 focus:shadow-[0_8px_25px_rgba(139,92,246,0.15)] outline-none transition-all hover:-translate-y-0.5" required placeholder="ระบุชื่อผู้ใช้งาน">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-purple-600 transition-colors">
                                <i class="fas fa-at text-sm"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ช่อง Password (มีเอฟเฟกต์ยกตัว) -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">Password</label>
                        <div class="relative group">
                            <input type="password" id="password" name="password" class="peer w-full bg-white border border-slate-100 shadow-[0_8px_20px_rgba(0,0,0,0.05)] rounded-2xl pl-12 pr-12 py-4 text-sm font-medium text-slate-800 placeholder-slate-400 focus:ring-4 focus:ring-purple-500/10 focus:shadow-[0_8px_25px_rgba(139,92,246,0.15)] outline-none transition-all hover:-translate-y-0.5" required placeholder="ระบุรหัสผ่าน">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-purple-600 transition-colors">
                                <i class="fas fa-key text-sm"></i>
                            </div>
                            <!-- ปุ่มเปิดปิดตา -->
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-purple-600 focus:outline-none transition-colors" onclick="togglePassword()">
                                <i id="eyeIcon" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ปุ่ม Submit (มีเอฟเฟกต์ยกตัว) -->
                    <button type="submit" class="relative w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-sm py-4 rounded-2xl shadow-[0_10px_20px_rgba(139,92,246,0.25)] hover:shadow-[0_15px_25px_rgba(139,92,246,0.4)] transform transition-all hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-2 group mt-4">
                        <span>เข้าสู่ระบบ</span> <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- Script สำหรับปุ่มแสดงรหัสผ่าน -->
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