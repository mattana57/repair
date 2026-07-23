<?php
session_start();
include 'db_connect.php';

$error_msg = "";
$status_result = null;
$search_keyword = "";

// ================= 1. จัดการการเข้าสู่ระบบ (Login) =================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; 

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        $role = strtolower($user['role']);
        
        // แยก Redirect ตามสิทธิ์การใช้งาน
        if ($role === 'executive') {
            header("Location: executive_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error_msg = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง!";
    }
}

// ================= 2. จัดการการค้นหาสถานะ (Check Status) =================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_status'])) {
    $search_keyword = trim($_POST['search_query']);
    $search_param = "%" . $search_keyword . "%";

    $stmt = $conn->prepare("SELECT ticket_no, equipment_type, status, created_at, technician_name, repair_note, reporter_name 
                            FROM repairs 
                            WHERE ticket_no = ? OR reporter_name LIKE ? 
                            ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("ss", $search_keyword, $search_param);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $status_result = [];
        while($row = $res->fetch_assoc()) {
            $status_result[] = $row;
        }
    } else {
        $status_result = 'not_found';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแจ้งซ่อม คณะการบัญชีและการจัดการ มมส.</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #1e293b; overflow-x: hidden; }
        .modal { transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        body.modal-active { overflow: hidden; }
        .font-serif-num { font-family: 'Playfair Display', serif; }
        
        /* Modern Color Palette */
        .text-brand-primary { color: #0f172a; } /* Dark Blue/Slate */
        .bg-brand-primary { background-color: #0f172a; }
        .text-brand-accent { color: #3b82f6; } /* Vibrant Blue */
        .bg-brand-accent { background-color: #3b82f6; }
        .bg-brand-gradient { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); }

        /* Premium Glassmorphism */
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }
        
        .glass-dark {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Hero Image with Dynamic Overlay */
        .hero-bg {
            background-image: 
                linear-gradient(to right, rgba(15,23,42, 1) 0%, rgba(15,23,42, 0.8) 40%, rgba(15,23,42, 0.2) 100%),
                url('uploads/mbs_bg.jpg?v=3');
            background-size: cover;
            background-position: center;
            background-attachment: fixed; /* Parallax effect */
        }

        /* Fluid Animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .floating { animation: float 6s ease-in-out infinite; }
        
        @keyframes revealUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .reveal-up {
            animation: revealUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-300 { animation-delay: 300ms; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-300 selection:text-blue-900 relative">

    <!-- Decorative Background Elements -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-400 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 -z-10 animate-pulse"></div>
    <div class="absolute top-[20%] right-[-5%] w-72 h-72 bg-indigo-400 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 -z-10 animate-pulse delay-75"></div>

    <!-- Header (Ultra Modern Glass) -->
    <header class="w-full glass-panel fixed top-4 z-40 transition-all duration-500 rounded-2xl mx-auto left-0 right-0 max-w-[96%] md:max-w-7xl">
        <div class="px-6 md:px-8 h-16 md:h-20 flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-4 group cursor-pointer">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-brand-gradient flex items-center justify-center text-white shadow-lg group-hover:rotate-12 transition-transform duration-500">
                    <i class="fas fa-microchip text-lg md:text-xl"></i>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-lg md:text-2xl font-black text-brand-primary tracking-tight leading-none">MBS<span class="text-brand-accent">REPAIR</span></h1>
                    <span class="text-[9px] md:text-[10px] text-slate-500 font-bold tracking-[0.2em] uppercase mt-0.5">Faculty of Accountancy & Management</span>
                </div>
            </div>
            
            <!-- Navigation & Action -->
            <div class="flex items-center gap-8">
                <nav class="hidden lg:flex items-center gap-8 text-sm font-bold text-slate-500">
                    <a href="#" class="hover:text-brand-accent transition-colors tracking-widest relative group">
                        HOME
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-brand-accent transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#categories" class="hover:text-brand-accent transition-colors tracking-widest relative group">
                        SERVICES
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-brand-accent transition-all duration-300 group-hover:w-full"></span>
                    </a>
                </nav>
                <button onclick="toggleModal('loginModal')" class="relative overflow-hidden group bg-white border border-slate-200 text-brand-primary px-6 py-2 md:py-2.5 rounded-full font-bold text-xs md:text-sm transition-all shadow-sm hover:shadow-lg hover:border-brand-accent">
                    <span class="absolute inset-0 bg-brand-accent translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out z-0"></span>
                    <div class="relative z-10 flex items-center group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-lock mr-2 text-[10px] md:text-xs"></i> <span class="tracking-widest">LOGIN</span>
                    </div>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section (Immersive & Dynamic) -->
    <main class="pt-[100px] md:pt-[120px] relative z-10 w-full px-4 md:px-6 lg:px-8 mb-16">
        <div class="max-w-[1400px] mx-auto hero-bg rounded-[2rem] md:rounded-[3rem] shadow-2xl overflow-hidden reveal-up min-h-[500px] md:min-h-[600px] flex items-center relative">
            
            <!-- Abstract Lines overlay -->
            <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>

            <div class="w-full md:w-[65%] lg:w-[55%] p-8 md:p-16 lg:p-24 relative z-10">
                
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-dark text-blue-300 text-[10px] font-bold tracking-[0.2em] uppercase mb-8 border-l-2 border-l-blue-400">
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    System Version 2.0
                </div>

                <!-- Main Typo -->
                <h2 class="text-sm md:text-base font-medium tracking-[0.3em] text-blue-200 uppercase mb-2 ml-1">IT Service Management</h2>
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-white leading-[1.1] mb-6 tracking-tight">
                    ระบบแจ้งซ่อม<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-200">ออนไลน์อัจฉริยะ</span>
                </h1>
                
                <p class="text-sm md:text-lg text-slate-300 mb-10 max-w-lg font-light leading-relaxed">
                    ยกระดับการให้บริการด้านเทคโนโลยีและอาคารสถานที่ คณะบัญชีฯ มมส. ด้วยระบบที่รวดเร็ว โปร่งใส และติดตามผลได้แบบ Real-time
                </p>
                
                <div class="flex flex-wrap items-center gap-4">
                    <a href="form_repair.php" class="bg-white text-brand-primary px-8 py-4 rounded-full font-bold tracking-wider transition-all duration-300 shadow-[0_0_20px_rgba(255,255,255,0.3)] hover:shadow-[0_0_30px_rgba(255,255,255,0.5)] hover:scale-105 flex items-center group">
                        เริ่มแจ้งซ่อมเลย <i class="fas fa-tools ml-3 text-brand-accent group-hover:rotate-12 transition-transform"></i>
                    </a>
                </div>

                <!-- Mini Stats -->
                <div class="flex items-center gap-8 mt-16 pt-8 border-t border-white/10">
                    <div>
                        <div class="text-3xl font-serif-num font-bold text-white">24<span class="text-sm text-blue-400">/7</span></div>
                        <div class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">Available</div>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <div class="text-3xl font-serif-num font-bold text-white">100<span class="text-sm text-blue-400">%</span></div>
                        <div class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">Tracking</div>
                    </div>
                </div>
            </div>

            <!-- Floating Decoration on Right -->
            <div class="hidden lg:block absolute right-16 top-1/2 transform -translate-y-1/2 floating">
                <div class="glass-dark p-6 rounded-3xl border border-white/10 shadow-2xl w-64 backdrop-blur-xl">
                    <div class="flex justify-between items-center mb-4">
                        <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400"><i class="fas fa-check"></i></div>
                        <span class="text-[10px] text-slate-300 tracking-widest">JUST NOW</span>
                    </div>
                    <div class="h-2 w-3/4 bg-slate-600 rounded-full mb-2"></div>
                    <div class="h-2 w-1/2 bg-slate-600 rounded-full mb-6"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-r from-blue-400 to-cyan-300"></div>
                        <div class="text-xs text-white font-medium">Job Completed</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Search Bar (Overlapping Glass Effect) -->
        <div class="relative w-[90%] md:w-[70%] max-w-4xl mx-auto -mt-10 md:-mt-14 z-20 reveal-up delay-100">
            <div class="glass-panel rounded-2xl p-2 shadow-[0_20px_40px_-15px_rgba(0,0,0,0.1)] border border-white">
                <form action="" method="POST" class="flex flex-col md:flex-row items-stretch">
                    <input type="hidden" name="check_status" value="1">
                    
                    <div class="flex-1 flex items-center px-4 md:px-6 py-3 cursor-text group" onclick="document.getElementById('searchInput').focus();">
                        <i class="fas fa-search text-slate-400 text-xl mr-4 group-focus-within:text-brand-accent transition-colors"></i>
                        <div class="w-full">
                            <input type="text" id="searchInput" name="search_query" required placeholder="พิมพ์เลขใบงาน (เช่น MR-001) หรือ ชื่อผู้แจ้ง เพื่อตรวจสอบสถานะ..." class="w-full text-sm md:text-base focus:outline-none text-brand-primary placeholder-slate-400 bg-transparent font-medium">
                        </div>
                    </div>

                    <button type="submit" class="bg-brand-primary hover:bg-brand-accent text-white px-8 py-4 md:py-0 rounded-xl font-bold transition-all duration-300 shadow-md uppercase tracking-widest text-xs flex items-center justify-center mt-2 md:mt-0">
                        Check Status <i class="fas fa-arrow-right ml-2 md:hidden"></i>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Categories Section (Bento Grid Style) -->
    <section id="categories" class="max-w-7xl mx-auto px-4 md:px-8 pt-16 pb-24 w-full reveal-up delay-200">
        <div class="text-center mb-16">
            <h3 class="text-brand-accent font-bold tracking-[0.2em] text-[11px] uppercase mb-3">Our Services</h3>
            <h2 class="text-3xl md:text-4xl font-black text-brand-primary tracking-tight">หมวดหมู่การให้บริการ</h2>
            <div class="w-16 h-1 bg-brand-accent mx-auto mt-6 rounded-full"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1 -->
            <div class="bg-white rounded-[2rem] p-8 shadow-sm hover:shadow-[0_20px_40px_-15px_rgba(59,130,246,0.15)] border border-slate-100 transition-all duration-500 group relative overflow-hidden flex flex-col items-center text-center hover:-translate-y-2">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-10 group-hover:scale-150 transition-transform duration-700 ease-out"></div>
                <div class="w-20 h-20 rounded-2xl bg-white shadow-lg flex items-center justify-center text-3xl text-brand-primary group-hover:text-brand-accent group-hover:rotate-12 transition-all duration-300 mb-6 border border-slate-50">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <h3 class="text-lg font-bold text-brand-primary mb-2">คอมพิวเตอร์</h3>
                <p class="text-xs text-slate-500 font-light px-2">ซ่อมแซม อัปเกรด และแก้ไขปัญหาซอฟต์แวร์</p>
            </div>
            
            <!-- Card 2 -->
            <div class="bg-white rounded-[2rem] p-8 shadow-sm hover:shadow-[0_20px_40px_-15px_rgba(59,130,246,0.15)] border border-slate-100 transition-all duration-500 group relative overflow-hidden flex flex-col items-center text-center hover:-translate-y-2 delay-75">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-10 group-hover:scale-150 transition-transform duration-700 ease-out"></div>
                <div class="w-20 h-20 rounded-2xl bg-white shadow-lg flex items-center justify-center text-3xl text-brand-primary group-hover:text-brand-accent group-hover:rotate-12 transition-all duration-300 mb-6 border border-slate-50">
                    <i class="fas fa-network-wired"></i>
                </div>
                <h3 class="text-lg font-bold text-brand-primary mb-2">ระบบเครือข่าย</h3>
                <p class="text-xs text-slate-500 font-light px-2">แก้ไขปัญหาอินเทอร์เน็ต LAN และ Wi-Fi (MSU-Net)</p>
            </div>

            <!-- Card 3 -->
            <div class="bg-white rounded-[2rem] p-8 shadow-sm hover:shadow-[0_20px_40px_-15px_rgba(59,130,246,0.15)] border border-slate-100 transition-all duration-500 group relative overflow-hidden flex flex-col items-center text-center hover:-translate-y-2 delay-150">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-10 group-hover:scale-150 transition-transform duration-700 ease-out"></div>
                <div class="w-20 h-20 rounded-2xl bg-white shadow-lg flex items-center justify-center text-3xl text-brand-primary group-hover:text-brand-accent group-hover:rotate-12 transition-all duration-300 mb-6 border border-slate-50">
                    <i class="fas fa-plug"></i>
                </div>
                <h3 class="text-lg font-bold text-brand-primary mb-2">ระบบไฟฟ้า</h3>
                <p class="text-xs text-slate-500 font-light px-2">หลอดไฟ ปลั๊กไฟ แอร์ และอุปกรณ์ไฟฟ้าภายในคณะ</p>
            </div>

            <!-- Card 4 -->
            <div class="bg-white rounded-[2rem] p-8 shadow-sm hover:shadow-[0_20px_40px_-15px_rgba(59,130,246,0.15)] border border-slate-100 transition-all duration-500 group relative overflow-hidden flex flex-col items-center text-center hover:-translate-y-2 delay-200">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-10 group-hover:scale-150 transition-transform duration-700 ease-out"></div>
                <div class="w-20 h-20 rounded-2xl bg-white shadow-lg flex items-center justify-center text-3xl text-brand-primary group-hover:text-brand-accent group-hover:rotate-12 transition-all duration-300 mb-6 border border-slate-50">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="text-lg font-bold text-brand-primary mb-2">อาคารสถานที่</h3>
                <p class="text-xs text-slate-500 font-light px-2">ซ่อมแซมประปา ประตู หน้าต่าง โต๊ะ เก้าอี้ และสภาพแวดล้อม</p>
            </div>
        </div>
    </section>

    <!-- Footer (Clean & Structured) -->
    <footer class="bg-white border-t border-slate-200 mt-auto reveal-up delay-300 relative overflow-hidden">
        <!-- Minimal Decor -->
        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-cyan-400 to-blue-600"></div>

        <div class="max-w-7xl mx-auto px-4 md:px-8 py-16">
            <div class="flex flex-col md:flex-row justify-between items-start gap-12 md:gap-8">
                
                <!-- Brand Info -->
                <div class="w-full md:w-5/12 space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-brand-primary flex items-center justify-center text-white">
                            <i class="fas fa-tools text-sm"></i>
                        </div>
                        <h2 class="text-xl font-black text-brand-primary tracking-tight">MBS<span class="text-brand-accent">REPAIR</span></h2>
                    </div>
                    <p class="text-sm text-slate-500 leading-relaxed font-light pr-4">
                        ระบบรับแจ้งซ่อมออนไลน์ที่พัฒนาขึ้นเพื่ออำนวยความสะดวกในการให้บริการซ่อมบำรุงอุปกรณ์และสถานที่ สำหรับคณาจารย์ บุคลากร และนิสิต คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </p>
                </div>

                <!-- Project Credit -->
                <div class="w-full md:w-6/12 bg-slate-50 rounded-3xl p-8 border border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center"><i class="fas fa-graduation-cap"></i></div>
                        <h3 class="font-bold text-brand-primary tracking-widest text-xs uppercase">Graduation Project</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase mb-2">Developers</p>
                            <ul class="space-y-2">
                                <li class="text-sm text-brand-primary font-medium flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-brand-accent"></div> นางสาวภัทรวดี ขามประโคน</li>
                                <li class="text-sm text-brand-primary font-medium flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-brand-accent"></div> นางสาวมัทนา รัตนแสง</li>
                            </ul>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase mb-2">Department</p>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ<br>
                                คณะการบัญชีและการจัดการ
                            </p>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="mt-12 pt-8 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs text-slate-400 font-medium tracking-wider">&copy; <?php echo date('Y'); ?> MBS REPAIR SYSTEM.</p>
                <div class="flex gap-4 text-slate-400">
                    <a href="https://www.msu.ac.th/" target="_blank" class="hover:text-brand-accent transition-colors"><i class="fas fa-university"></i> MSU</a>
                </div>
            </div>
            
        </div>
    </footer>

    <!-- Result Modal (Modern Card) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-full md:max-w-2xl mx-auto shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] rounded-3xl">
            
            <div class="p-6 md:p-8 flex justify-between items-start border-b border-slate-100 shrink-0 bg-slate-50/50">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold tracking-widest uppercase mb-3">Search Result</span>
                    <h2 class="text-2xl font-black text-brand-primary tracking-tight">ข้อมูลการแจ้งซ่อม</h2>
                    <p class="text-xs text-slate-500 mt-1">คำค้นหา: <span class="font-bold text-brand-accent">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-10 h-10 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-brand-primary hover:bg-slate-100 flex items-center justify-center transition-all shadow-sm hover:rotate-90">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-white space-y-6">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        // Modern Status Colors
                        $bgClass = "bg-slate-50 border-slate-200"; 
                        $badgeClass = "bg-slate-200 text-slate-700";
                        $dotClass = "bg-slate-500";
                        $icon = "fa-file-alt";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $bgClass = "bg-amber-50/50 border-amber-200";
                            $badgeClass = "bg-amber-100 text-amber-700";
                            $dotClass = "bg-amber-500";
                            $icon = "fa-clock";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $bgClass = "bg-blue-50/50 border-blue-200";
                            $badgeClass = "bg-blue-100 text-blue-700";
                            $dotClass = "bg-blue-500";
                            $icon = "fa-tools";
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $bgClass = "bg-emerald-50/50 border-emerald-200";
                            $badgeClass = "bg-emerald-100 text-emerald-700";
                            $dotClass = "bg-emerald-500";
                            $icon = "fa-check-circle";
                        }
                    ?>
                        <div class="rounded-2xl border <?php echo $bgClass; ?> p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-slate-200/60">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl <?php echo $badgeClass; ?> flex items-center justify-center text-xl">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Ticket No.</p>
                                        <h3 class="text-xl font-black text-brand-primary tracking-tight"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <div class="flex flex-col items-start sm:items-end">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold <?php echo $badgeClass; ?>">
                                        <span class="w-2 h-2 rounded-full <?php echo $dotClass; ?> mr-2"></span><?php echo $res['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">Equipment</p>
                                    <p class="font-medium text-slate-700 flex items-center gap-2"><i class="fas fa-tag text-slate-300"></i> <?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">Reporter</p>
                                    <p class="font-medium text-slate-700 flex items-center gap-2"><i class="fas fa-user text-slate-300"></i> <?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">Technician</p>
                                    <p class="font-medium flex items-center gap-2 <?php echo !empty($res['technician_name']) ? 'text-brand-accent' : 'text-slate-400 italic'; ?>">
                                        <i class="fas fa-hard-hat <?php echo !empty($res['technician_name']) ? 'text-brand-accent/50' : 'text-slate-300'; ?>"></i> 
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : 'ยังไม่ระบุช่าง'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">Date Reported</p>
                                    <p class="font-medium text-slate-700 flex items-center gap-2"><i class="far fa-calendar-alt text-slate-300"></i> <?php echo date("d/m/Y", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if(!empty($res['repair_note'])): ?>
                            <div class="mt-4 pt-4 border-t border-slate-200/60">
                                <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-2">Technician Note</p>
                                <p class="text-sm text-slate-600 bg-white/50 p-3 rounded-xl border border-slate-100">"<?php echo $res['repair_note']; ?>"</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Login Modal (Sleek Form) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-md transition-opacity" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-full max-w-sm mx-auto shadow-2xl z-50 overflow-hidden transform transition-all rounded-[2rem] relative">
            
            <button onclick="toggleModal('loginModal')" class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:text-brand-primary hover:bg-slate-200 flex items-center justify-center transition-colors z-10">
                <i class="fas fa-times"></i>
            </button>

            <div class="p-10 pb-6 text-center relative overflow-hidden">
                <div class="w-16 h-16 rounded-2xl bg-brand-primary text-white flex items-center justify-center text-2xl mx-auto mb-6 shadow-xl shadow-slate-900/20 relative z-10">
                    <i class="fas fa-fingerprint"></i>
                </div>
                <h2 class="text-2xl font-black text-brand-primary tracking-tight">Staff Portal</h2>
                <p class="text-xs text-slate-500 mt-2 font-light">Enter your credentials to access the dashboard</p>
            </div>

            <form action="" method="POST" class="p-10 pt-4">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4">
                    <div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-brand-accent transition-colors">
                                <i class="far fa-user"></i>
                            </div>
                            <input type="text" name="username" required placeholder="Username" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 text-brand-primary rounded-xl focus:outline-none focus:border-brand-accent focus:bg-white focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-brand-accent transition-colors">
                                <i class="far fa-eye-slash"></i>
                            </div>
                            <input type="password" name="password" required placeholder="Password" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 text-brand-primary rounded-xl focus:outline-none focus:border-brand-accent focus:bg-white focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-brand-primary hover:bg-slate-800 text-white py-4 rounded-xl font-bold text-sm tracking-widest transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 flex items-center justify-center group overflow-hidden relative">
                    <span class="absolute w-0 h-0 transition-all duration-500 ease-out bg-brand-accent rounded-full group-hover:w-full group-hover:h-56 opacity-10"></span>
                    <span class="relative">AUTHENTICATE</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Floating LINE Button -->
    <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="fixed bottom-6 right-6 md:bottom-8 md:right-8 z-40 bg-[#00B900] hover:bg-[#009900] text-white w-14 h-14 md:w-auto md:h-auto md:px-6 md:py-3.5 rounded-full font-bold text-sm shadow-[0_10px_20px_-10px_rgba(0,185,0,0.5)] transition-all transform hover:-translate-y-1 hover:scale-105 flex items-center justify-center group reveal-up delay-300">
        <i class="fab fa-line text-3xl md:text-xl md:mr-2"></i> 
        <span class="hidden md:inline tracking-wider">Contact Support</span>
    </a>

    <!-- Script -->
    <script>
        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        // Add blur to header on scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (window.scrollY > 20) {
                header.classList.add('shadow-md', 'py-2');
                header.classList.remove('py-4', 'top-4');
                header.classList.add('top-0', 'max-w-full', 'rounded-none');
            } else {
                header.classList.remove('shadow-md', 'py-2', 'top-0', 'max-w-full', 'rounded-none');
                header.classList.add('py-4', 'top-4');
            }
        });
    </script>

    <?php if(!empty($error_msg)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#0f172a',
                confirmButtonText: 'Try Again',
                customClass: { popup: 'rounded-3xl' }
            }).then(() => { toggleModal('loginModal'); });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Ticket Not Found',
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก: "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบอีกครั้ง',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'rounded-3xl' }
            });
        });
    </script>
    <?php elseif(is_array($status_result)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toggleModal('resultModal');
        });
    </script>
    <?php endif; ?>

</body>
</html>