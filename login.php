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
        
        /* 🎨 Custom Animation สำหรับกราฟิกฝั่งซ้าย */
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

        /* 🚀 Animation ตอนโหลดหน้าฟอร์ม */
        .fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 selection:bg-purple-500 selection:text-white">

    <!-- เค้าโครงหลักแบบแบ่ง 2 ฝั่งเต็มหน้าจอ -->
    <div class="flex w-full min-h-screen">
        
        <!-- ฝั่งซ้าย: กราฟิกสีโทนเดียวกับ Dashboard (Indigo-Purple) -->
        <div class="hidden lg:flex lg:w-7/12 relative overflow-hidden bg-gradient-to-br from-indigo-700 via-purple-600 to-indigo-900 items-center justify-center">
            
            <!-- วงกลมแสงลอยๆ ด้านหลัง -->
            <div class="absolute inset-0 w-full h-full pointer-events-none">
                <div class="absolute top-[-10%] left-[-5%] w-[600px] h-[600px] rounded-full bg-gradient-to-br from-white/20 to-transparent blur-3xl mix-blend-overlay animate-blob"></div>
                <div class="absolute bottom-[-10%] right-[10%] w-[500px] h-[500px] rounded-full bg-gradient-to-tr from-indigo-400/30 to-transparent blur-3xl mix-blend-overlay animate-blob animation-delay-2000"></div>
            </div>
            
            <!-- การ์ด Glassmorphism ตกแต่งฝั่งซ้าย -->
            <div class="relative z-10 w-96 p-10 bg-white/10 backdrop-blur-xl border border-white/20 rounded-[3rem] shadow-2xl flex flex-col items-center justify-center transform -rotate-3 hover:rotate-0 transition-transform duration-500">
                <div class="w-24 h-24 bg-white rounded-[1.5rem] flex items-center justify-center mb-6 shadow-xl transform rotate-6">
                    <span class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">MBS</span>
                </div>
                <h3 class="text-3xl font-bold text-white tracking-wide text-center">Repair System</h3>
                <p class="text-indigo-100 mt-2 font-medium text-center">ระบบแจ้งซ่อมและบำรุงรักษา</p>
            </div>
            
        </div>

        <!-- ฝั่งขวา: ฟอร์มเข้าสู่ระบบ (ปรับพื้นหลังมีมิติ + ปุ่ม/ช่องกรอกแบบลอย 3D) -->
        <div class="w-full lg:w-5/12 flex items-center justify-center p-8 relative z-10 bg-gradient-to-br from-slate-50 via-purple-50/30 to-slate-100 overflow-hidden">
            
            <!-- ✨ แสงออร่าซอฟต์ๆ ด้านหลังฝั่งขวา (ช่วยให้พื้นหลังดูมีอะไร แต่ไม่รก) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-20 -right-20 w-80 h-80 bg-purple-200/30 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-indigo-200/20 rounded-full blur-3xl"></div>
            </div>

            <div class="w-full max-w-md fade-in-up relative z-10">
                
                <!-- ส่วนหัว (Header) -->
                <div class="flex flex-col items-center mb-10 relative">
                    <!-- Icon อิงโทนสี Dashboard -->
                    <div class="w-16 h-16 bg-gradient-to-br from-indigo-600 to-purple-500 text-white rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-[0_12px_25px_rgba(139,92,246,0.3)] transform transition-transform hover:scale-110 hover:rotate-3 duration-300">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-slate-800 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
                    <p class="text-slate-500 text-sm mt-2 text-center font-medium">คณะการบัญชีและการจัดการ (MBS)</p>
                </div>
                
                <!-- ฟอร์มเข้าสู่ระบบ -->
                <form action="auth.php" method="POST" class="space-y-6">
                    
                    <!-- ช่อง Username (แบบปุ่มลอยมีมิติ 3D) -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">Username</label>
                        <div class="relative group">
                            <!-- เพิ่มเงาลอยหลายชั้น + ขอบขาวเนียน + เอฟเฟกต์ยกตัวเมื่อ Hover/Focus -->
                            <input type="text" name="username" class="peer w-full bg-white/90 backdrop-blur-md border border-white/80 shadow-[0_10px_25px_-5px_rgba(0,0,0,0.06),0_8px_10px_-6px_rgba(0,0,0,0.02)] rounded-2xl pl-12 pr-4 py-4 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-purple-500/15 focus:shadow-[0_15px_30px_rgba(139,92,246,0.18)] hover:-translate-y-0.5 outline-none transition-all duration-300" required placeholder="ระบุชื่อผู้ใช้งาน">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-purple-600 transition-colors">
                                <i class="fas fa-at text-sm"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ช่อง Password (แบบปุ่มลอยมีมิติ 3D) -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">Password</label>
                        <div class="relative group">
                            <!-- เพิ่มเงาลอยหลายชั้น + ขอบขาวเนียน + เอฟเฟกต์ยกตัวเมื่อ Hover/Focus -->
                            <input type="password" id="password" name="password" class="peer w-full bg-white/90 backdrop-blur-md border border-white/80 shadow-[0_10px_25px_-5px_rgba(0,0,0,0.06),0_8px_10px_-6px_rgba(0,0,0,0.02)] rounded-2xl pl-12 pr-12 py-4 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-purple-500/15 focus:shadow-[0_15px_30px_rgba(139,92,246,0.18)] hover:-translate-y-0.5 outline-none transition-all duration-300" required placeholder="ระบุรหัสผ่าน">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-purple-600 transition-colors">
                                <i class="fas fa-key text-sm"></i>
                            </div>
                            <!-- ปุ่มเปิดปิดตา -->
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-purple-600 focus:outline-none transition-colors" onclick="togglePassword()">
                                <i id="eyeIcon" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ปุ่ม Submit (แบบลอยยกมิติ สีโทน Dashboard) -->
                    <button type="submit" class="relative overflow-hidden w-full bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-600 text-white font-bold text-sm py-4 rounded-2xl shadow-[0_12px_25px_rgba(124,58,237,0.35)] hover:shadow-[0_18px_35px_rgba(124,58,237,0.48)] transform transition-all duration-300 hover:-translate-y-1 active:translate-y-0 active:scale-[0.98] group mt-4">
                        <span class="relative z-10 flex items-center justify-center gap-2">
                            เข้าสู่ระบบ <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </span>
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