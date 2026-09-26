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
        
        /* 🎨 Custom Animation สำหรับแสงหลังการ์ด */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob {
            animation: blob 8s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-200/80 flex items-center justify-center p-4 sm:p-6 md:p-10 selection:bg-purple-500 selection:text-white">

    <!-- 🌟 โครงสร้างหลักแบบรูปที่ 1 (การ์ดใหญ่ลอยกลางหน้าจอ) -->
    <div class="w-full max-w-5xl bg-gradient-to-br from-indigo-700 via-purple-600 to-indigo-900 rounded-[2.5rem] shadow-[0_25px_60px_-15px_rgba(79,70,229,0.4)] overflow-hidden relative min-h-[600px] flex flex-col lg:flex-row items-center p-6 lg:p-8">
        
        <!-- วงกลมแสงเอฟเฟกต์ด้านหลัง -->
        <div class="absolute inset-0 w-full h-full pointer-events-none overflow-hidden">
            <div class="absolute top-[-10%] left-[-5%] w-[500px] h-[500px] rounded-full bg-gradient-to-br from-white/20 to-transparent blur-3xl mix-blend-overlay animate-blob"></div>
            <div class="absolute bottom-[-10%] right-[30%] w-[400px] h-[400px] rounded-full bg-gradient-to-tr from-indigo-400/30 to-transparent blur-3xl mix-blend-overlay animate-blob animation-delay-2000"></div>
        </div>

        <!-- 👈 ฝั่งซ้าย: ข้อมูล MBS Repair System (จากรูปที่ 2) -->
        <div class="w-full lg:w-1/2 p-6 lg:p-10 flex flex-col items-center lg:items-start justify-center relative z-10 text-center lg:text-left">
            <div class="w-20 h-20 lg:w-24 lg:h-24 bg-white rounded-2xl flex items-center justify-center mb-6 shadow-xl transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <span class="text-3xl lg:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">MBS</span>
            </div>
            <h3 class="text-3xl lg:text-4xl font-bold text-white tracking-wide">Repair System</h3>
            <p class="text-indigo-100 mt-2 text-base lg:text-lg font-medium opacity-90">ระบบแจ้งซ่อมและบำรุงรักษา</p>
        </div>

        <!-- 👉 ฝั่งขวา: ก้อนฟอร์มเข้าสู่ระบบแบบ "การ์ดลอยมีมิติ 3D" (ตามสไตล์รูปที่ 1) -->
        <div class="w-full lg:w-1/2 flex justify-center items-center relative z-10 p-2 lg:p-4">
            <div class="w-full max-w-md bg-white/95 backdrop-blur-md rounded-[2rem] p-8 lg:p-10 shadow-[0_20px_50px_rgba(0,0,0,0.3)] border border-white/40 transform hover:-translate-y-1 transition-all duration-300">
                
                <!-- หัวข้อฟอร์ม -->
                <div class="flex flex-col items-center mb-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-600 to-purple-500 text-white rounded-2xl flex items-center justify-center text-2xl mb-4 shadow-[0_8px_20px_rgba(139,92,246,0.3)]">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
                    <p class="text-slate-500 text-xs mt-1 text-center font-medium">คณะการบัญชีและการจัดการ (MBS)</p>
                </div>

                <!-- ฟอร์มกรอกข้อมูล -->
                <form action="auth.php" method="POST" class="space-y-5">
                    
                    <!-- Username -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Username</label>
                        <div class="relative group">
                            <input type="text" name="username" class="peer w-full bg-slate-50/80 border border-slate-200/80 shadow-[0_4px_12px_rgba(0,0,0,0.03)] rounded-xl pl-11 pr-4 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:border-purple-500 focus:ring-4 focus:ring-purple-500/10 outline-none transition-all" required placeholder="ระบุชื่อผู้ใช้งาน">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-purple-600 transition-colors">
                                <i class="fas fa-at text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Password</label>
                        <div class="relative group">
                            <input type="password" id="password" name="password" class="peer w-full bg-slate-50/80 border border-slate-200/80 shadow-[0_4px_12px_rgba(0,0,0,0.03)] rounded-xl pl-11 pr-11 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:border-purple-500 focus:ring-4 focus:ring-purple-500/10 outline-none transition-all" required placeholder="ระบุรหัสผ่าน">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-purple-600 transition-colors">
                                <i class="fas fa-key text-xs"></i>
                            </div>
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-purple-600 focus:outline-none transition-colors" onclick="togglePassword()">
                                <i id="eyeIcon" class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ปุ่มเข้าสู่ระบบ -->
                    <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold text-sm py-3.5 rounded-xl shadow-[0_10px_20px_rgba(124,58,237,0.3)] hover:shadow-[0_14px_25px_rgba(124,58,237,0.4)] transform active:scale-[0.98] transition-all duration-200 mt-2 flex items-center justify-center gap-2">
                        <span>เข้าสู่ระบบ</span>
                        <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                </form>

            </div>
        </div>

    </div>

    <!-- Script เปิด-ปิดรหัสผ่าน -->
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