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
        body { 
            font-family: 'Kanit', sans-serif; 
            /* ปรับพื้นหลังเต็มจอด้วย Gradient สีน้ำเงิน-คราม */
            background: linear-gradient(135deg, #1e3a8a 0%, #312e81 50%, #1e40af 100%);
        }
        
        /* 🎨 Custom Animation สำหรับก้อนสีพื้นหลังที่ขยับได้ */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(40px, -60px) scale(1.2); }
            66% { transform: translate(-30px, 30px) scale(0.8); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob {
            animation: blob 8s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        .animation-delay-4000 {
            animation-delay: 4s;
        }

        /* 🚀 Animation ตอนโหลดหน้าเว็บ (Slide Up Fade) */
        .fade-in-up {
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
        }
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-white selection:text-blue-900">

    <!-- 🌌 Animated Background Blobs (พื้นหลังเต็มจอที่มีลูกเล่นลอยๆ) -->
    <div class="absolute inset-0 w-full h-full flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <div class="relative w-full h-full max-w-4xl flex items-center justify-center">
            <div class="absolute top-1/4 -left-10 w-96 h-96 bg-blue-500 rounded-full mix-blend-screen filter blur-[100px] opacity-40 animate-blob"></div>
            <div class="absolute top-1/3 -right-10 w-96 h-96 bg-indigo-500 rounded-full mix-blend-screen filter blur-[100px] opacity-40 animate-blob animation-delay-2000"></div>
            <div class="absolute -bottom-20 left-1/3 w-96 h-96 bg-blue-400 rounded-full mix-blend-screen filter blur-[100px] opacity-40 animate-blob animation-delay-4000"></div>
        </div>
    </div>

    <!-- 💎 กล่องเข้าสู่ระบบแบบลอย (Floating Card) -->
    <div class="w-full max-w-md bg-white p-8 md:p-10 rounded-[2.5rem] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] border border-white/20 z-10 fade-in-up relative">
        
        <!-- ส่วนหัว (Header) -->
        <div class="flex flex-col items-center mb-8 relative">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-lg shadow-blue-500/40 transform transition-transform hover:scale-110 hover:rotate-3 duration-300">
                <i class="fas fa-fingerprint"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
            <p class="text-slate-500 text-sm mt-1 text-center font-medium">คณะการบัญชีและการจัดการ (MBS)</p>
        </div>
        
        <!-- ฟอร์มเข้าสู่ระบบ -->
        <form action="auth.php" method="POST" class="space-y-6">
            
            <!-- ช่อง Username -->
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Username</label>
                <div class="relative group">
                    <input type="text" name="username" class="peer w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" required placeholder="ระบุชื่อผู้ใช้งาน">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-blue-600 transition-colors">
                        <i class="fas fa-at text-sm"></i>
                    </div>
                </div>
            </div>
            
            <!-- ช่อง Password -->
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Password</label>
                <div class="relative group">
                    <input type="password" id="password" name="password" class="peer w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-12 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all" required placeholder="ระบุรหัสผ่าน">
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