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
        body { font-family: 'Kanit', sans-serif; background-color: #f1f5f9; }

        /* 🚀 Animation ตอนโหลดหน้าเว็บ (Slide Up Fade) */
        .fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* 🎨 กราฟิกวงกลมตกแต่งสำหรับฝั่งซ้าย */
        .shape-1 {
            position: absolute;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            top: -120px;
            left: -100px;
        }
        .shape-2 {
            position: absolute;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0) 100%);
            bottom: -80px;
            right: -80px;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-blue-500 selection:text-white">

    <!-- 💎 กล่องเข้าสู่ระบบ (Split Card Design) -->
    <div class="w-full max-w-4xl bg-white rounded-[2.5rem] shadow-[0_20px_50px_rgb(0,0,0,0.1)] flex flex-col md:flex-row overflow-hidden z-10 fade-in-up border border-white">
        
        <!-- ส่วนด้านซ้าย (Visual Branding - สีน้ำเงิน) -->
        <div class="relative w-full md:w-5/12 bg-gradient-to-br from-blue-600 to-indigo-800 p-10 py-16 flex flex-col items-center justify-center text-center overflow-hidden">
            <!-- กราฟิกวงกลมตกแต่ง -->
            <div class="shape-1"></div>
            <div class="shape-2"></div>

            <div class="relative z-10 flex flex-col items-center">
                <!-- Icon -->
                <div class="w-20 h-20 bg-white/20 backdrop-blur-md text-white rounded-2xl flex items-center justify-center text-4xl mb-6 shadow-xl border border-white/20 transform transition-transform hover:scale-110 duration-300">
                    <i class="fas fa-fingerprint"></i>
                </div>
                <!-- หัวข้อ -->
                <h2 class="text-3xl font-bold text-white tracking-tight mb-2">เข้าสู่ระบบเจ้าหน้าที่</h2>
                <p class="text-blue-100 text-sm font-medium">คณะการบัญชีและการจัดการ (MBS)</p>
            </div>
        </div>
        
        <!-- ส่วนด้านขวา (ฟอร์มเข้าสู่ระบบ - สีขาว) -->
        <div class="w-full md:w-7/12 p-8 md:p-14 lg:p-16 flex flex-col justify-center bg-white">
            
            <form action="auth.php" method="POST" class="space-y-7">
                
                <!-- ช่อง Username -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Username</label>
                    <div class="relative group">
                        <input type="text" name="username" class="peer w-full bg-slate-50/50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" required placeholder="ระบุชื่อผู้ใช้งาน">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-blue-600 transition-colors">
                            <i class="fas fa-at text-sm"></i>
                        </div>
                    </div>
                </div>
                
                <!-- ช่อง Password -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Password</label>
                    <div class="relative group">
                        <input type="password" id="password" name="password" class="peer w-full bg-slate-50/50 border border-slate-200 rounded-2xl pl-12 pr-12 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" required placeholder="ระบุรหัสผ่าน">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-blue-600 transition-colors">
                            <i class="fas fa-key text-sm"></i>
                        </div>
                        <!-- ปุ่มเปิดปิดตา -->
                        <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-blue-600 focus:outline-none transition-colors" onclick="togglePassword()">
                            <i id="eyeIcon" class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- ปุ่ม Submit -->
                <button type="submit" class="relative overflow-hidden w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold text-sm py-4 rounded-2xl shadow-lg shadow-blue-500/30 transform transition-all hover:-translate-y-0.5 hover:shadow-blue-500/50 active:scale-95 group mt-2">
                    <span class="relative z-10 flex items-center justify-center gap-2">
                        เข้าสู่ระบบ <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </button>
            </form>
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