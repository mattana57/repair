<?php
session_start();
@include 'db_connect.php';

$error_msg = "";
$status_result = null;
$search_keyword = "";

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
$db_connected = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;

// ดึงสถิติจำนวนการแจ้งซ่อมเบื้องต้น
$stats = [
    'pending' => 0,
    'progress' => 0,
    'completed' => 0,
    'total' => 0
];

if ($db_connected) {
    $res_p = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE status = 'รอรับเรื่อง'");
    if ($res_p) $stats['pending'] = $res_p->fetch_assoc()['cnt'];

    $res_prog = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE status = 'กำลังดำเนินการ'");
    if ($res_prog) $stats['progress'] = $res_prog->fetch_assoc()['cnt'];

    $res_c = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE status = 'ซ่อมเสร็จแล้ว'");
    if ($res_c) $stats['completed'] = $res_c->fetch_assoc()['cnt'];

    $res_t = $conn->query("SELECT COUNT(*) as cnt FROM repairs");
    if ($res_t) $stats['total'] = $res_t->fetch_assoc()['cnt'];
}

// ================= 1. จัดการการเข้าสู่ระบบ (Login) =================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    if ($db_connected) {
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
            
            if ($role === 'executive') {
                header("Location: executive_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error_msg = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง!";
        }
    } else {
        $error_msg = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบการเชื่อมต่อ DB";
    }
}

// ================= 2. จัดการการค้นหาสถานะ (Check Status) =================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_status'])) {
    if ($db_connected) {
        $search_keyword = trim($_POST['search_query']);
        $search_param = "%" . $search_keyword . "%";

        $stmt = $conn->prepare("SELECT id, ticket_no, equipment_type, status, created_at, technician_name, repair_note, reporter_name 
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
    } else {
        $error_msg = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบการเชื่อมต่อ DB";
    }
}
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS REPAIR | แจ้งซ่อมและติดตามสถานะ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #f8fafc;
            color: #0f172a;
        }

        /* พื้นหลังไล่สี */
        .hero-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 45%, #2563eb 100%);
        }

        /* เอฟเฟกต์การ์ด 3D สไตล์ Glassmorphism */
        .card-3d {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.05);
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .card-3d:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 22px 40px -12px rgba(37, 99, 235, 0.18);
            border-color: #60a5fa;
        }

        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        body.modal-active { overflow: hidden; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    <!-- 1. NAVIGATION HEADER (Responsive สำหรับมือถือ) -->
    <header class="w-full bg-white/95 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-2">
            
            <!-- Logo & Title -->
            <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
                <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl sm:rounded-[0.8rem] bg-gradient-to-tr from-blue-600 to-sky-400 text-white flex items-center justify-center text-base sm:text-xl shadow-lg shadow-blue-500/30 shrink-0">
                    <i class="fas fa-screwdriver-wrench"></i>
                </div>
                <div class="truncate">
                    <h1 class="text-lg sm:text-2xl font-black tracking-tight leading-none text-slate-900 truncate">
                        MBS <span class="text-[#0ea5e9]">REPAIR</span>
                    </h1>
                    <!-- ซ่อนชื่อคณะเมื่อดูผ่านจอมือถือ ป้องกันเลย์เอาต์พัง -->
                    <p class="hidden sm:block text-[11px] sm:text-xs text-slate-500 font-bold mt-1 truncate">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                </div>
            </div>

            <!-- Login Button -->
            <button onclick="toggleModal('loginModal')" class="bg-[#0f172a] hover:bg-blue-600 text-white font-bold px-3.5 py-2 sm:px-6 sm:py-3 rounded-lg sm:rounded-xl text-xs sm:text-sm focus:outline-none transition-all flex items-center gap-2 shadow-md hover:shadow-blue-500/20 active:scale-95 shrink-0">
                <i class="fas fa-user-shield text-blue-400 sm:text-lg"></i> 
                <!-- เปลี่ยนข้อความให้สั้นลงในจอมือถือ -->
                <span class="hidden sm:inline">เจ้าหน้าที่เข้าสู่ระบบ</span>
                <span class="sm:hidden">เข้าสู่ระบบ</span>
            </button>
        </div>
    </header>

    <!-- 2. HERO BANNER & SEARCH SECTION -->
    <section class="hero-gradient text-white pt-12 pb-24 sm:pt-16 sm:pb-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 right-10 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-4xl mx-auto text-center space-y-6 relative z-10">

            <h1 class="text-3xl sm:text-6xl font-black tracking-tight leading-tight">
                แจ้งซ่อมอุปกรณ์และติดตามสถานะ <br>
                <span class="bg-gradient-to-r from-blue-200 via-sky-300 to-emerald-300 bg-clip-text text-transparent">ได้อย่างสะดวกรวดเร็ว</span>
            </h1>

            <p class="text-slate-300 text-sm sm:text-lg max-w-3xl mx-auto font-normal leading-relaxed px-2">
                บริการรับแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า อาคารสถานที่ และอุปกรณ์ในห้องเรียน สำหรับบุคลากรและนิสิต MBS
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 pt-4">
                <a href="form_repair.php" class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold px-8 py-3.5 sm:py-4 rounded-2xl text-sm sm:text-base focus:outline-none shadow-xl shadow-blue-600/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2.5">
                    <i class="fas fa-file-pen text-xl"></i> กรอกแบบฟอร์มแจ้งซ่อมใหม่
                </a>
                <a href="https://line.me" target="_blank" class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-8 py-3.5 sm:py-4 rounded-2xl text-sm sm:text-base focus:outline-none shadow-xl shadow-emerald-500/25 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2.5">
                    <i class="fab fa-line text-2xl"></i> ติดต่อผ่าน LINE Official
                </a>
            </div>

            <!-- Floating Search Card -->
            <div class="max-w-2xl mx-auto bg-white/95 backdrop-blur-xl p-4 sm:p-7 rounded-3xl shadow-2xl border border-white/40 text-slate-800 text-left mt-8 sm:mt-10 transform hover:scale-[1.01] transition-transform">
                <!-- Flex Wrap เพื่อไม่ให้ป้ายหดไปทับข้อความในมือถือ -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 sm:mb-4 px-1">
                    <label class="text-sm sm:text-base font-extrabold text-blue-950 flex items-center gap-2">
                        <i class="fas fa-magnifying-glass text-blue-600"></i> ค้นหาประวัติ / ตรวจสอบสถานะ
                    </label>
                    <span class="text-[10px] sm:text-xs bg-blue-50 text-blue-700 font-bold px-3 py-1 rounded-full border border-blue-200 shrink-0">Real-time Search</span>
                </div>
                
                <form action="" method="POST" class="flex flex-col sm:flex-row gap-2.5 sm:gap-3">
                    <input type="hidden" name="check_status" value="1">
                    <div class="relative flex-1">
                        <i class="fas fa-ticket-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-base sm:text-lg"></i>
                        <input type="text" name="search_query" required placeholder="กรอกเลขที่ใบงาน (เช่น MR-001)..." class="w-full pl-11 pr-4 py-3.5 sm:py-4 bg-slate-100/90 border border-slate-200 rounded-xl text-sm sm:text-base font-semibold text-slate-900 placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:bg-white transition-all">
                    </div>
                    <button type="submit" class="bg-slate-900 hover:bg-blue-600 text-white font-bold px-8 py-3.5 sm:py-4 rounded-xl text-sm sm:text-base focus:outline-none transition-all flex items-center justify-center gap-2 shadow-md hover:shadow-blue-500/20">
                        <i class="fas fa-search"></i> ตรวจสอบสถานะ
                    </button>
                </form>
            </div>

        </div>
    </section>

    <!-- 3. STATS CARDS SECTION -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-12 relative z-20 w-full">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
            
            <div class="card-3d p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
                <div class="w-10 h-10 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl bg-gradient-to-br from-amber-400 to-amber-500 text-white shadow-lg shadow-amber-500/30 flex items-center justify-center text-lg sm:text-2xl font-bold shrink-0">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div>
                    <p class="text-[10px] sm:text-sm font-extrabold text-slate-400 uppercase tracking-wider">รอรับเรื่อง</p>
                    <h3 class="text-xl sm:text-3xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['pending']); ?> <span class="text-xs sm:text-sm font-medium text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="card-3d p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
                <div class="w-10 h-10 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg shadow-blue-500/30 flex items-center justify-center text-lg sm:text-2xl font-bold shrink-0">
                    <i class="fa-regular fa-compass"></i>
                </div>
                <div>
                    <p class="text-[10px] sm:text-sm font-extrabold text-slate-400 uppercase tracking-wider">กำลังดำเนินการ</p>
                    <h3 class="text-xl sm:text-3xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['progress']); ?> <span class="text-xs sm:text-sm font-medium text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="card-3d p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
                <div class="w-10 h-10 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-lg shadow-emerald-500/30 flex items-center justify-center text-lg sm:text-2xl font-bold shrink-0">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-[10px] sm:text-sm font-extrabold text-slate-400 uppercase tracking-wider">ซ่อมเสร็จแล้ว</p>
                    <h3 class="text-xl sm:text-3xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['completed']); ?> <span class="text-xs sm:text-sm font-medium text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="card-3d p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
                <div class="w-10 h-10 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 text-white shadow-lg shadow-slate-900/30 flex items-center justify-center text-lg sm:text-2xl font-bold shrink-0">
                    <i class="fa-regular fa-clipboard"></i>
                </div>
                <div>
                    <p class="text-[10px] sm:text-sm font-extrabold text-slate-400 uppercase tracking-wider">งานซ่อมทั้งหมด</p>
                    <h3 class="text-xl sm:text-3xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['total']); ?> <span class="text-xs sm:text-sm font-medium text-slate-500">รายการ</span></h3>
                </div>
            </div>

        </div>
    </section>

    <!-- 4. WORKFLOW TIMELINE -->
    <section id="process" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
        <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-3xl p-6 sm:p-12 text-white shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl"></div>
            
            <div class="text-center space-y-2 mb-8 sm:mb-12 relative z-10">
                <span class="text-blue-400 text-xs sm:text-sm font-extrabold uppercase tracking-widest"><i class="fas fa-route mr-1"></i> Workflow Process</span>
                <h2 class="text-2xl sm:text-4xl font-black tracking-tight">ขั้นตอนการแจ้งซ่อมใน 4 ขั้นตอน</h2>
                <p class="text-sm sm:text-base text-slate-300 font-light">ติดตามเรื่องซ่อมสะดวกรวดเร็ว แม่นยำทุกขั้นตอน</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 relative z-10">
                
                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-blue-400/50 transition-all space-y-3 sm:space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-blue-600 text-white font-extrabold flex items-center justify-center text-sm sm:text-lg shadow-md">01</span>
                        <i class="fas fa-pen-to-square text-2xl sm:text-3xl text-blue-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-white">1. กรอกแบบฟอร์ม</h3>
                    <p class="text-xs sm:text-sm text-slate-300 font-light leading-relaxed">ระบุรายละเอียดอุปกรณ์ อาคารสถานที่ และปัญหาที่พบผ่านเว็บ</p>
                </div>

                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-amber-400/50 transition-all space-y-3 sm:space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-amber-500 text-white font-extrabold flex items-center justify-center text-sm sm:text-lg shadow-md">02</span>
                        <i class="fas fa-user-check text-2xl sm:text-3xl text-amber-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-white">2. เจ้าหน้าที่รับเรื่อง</h3>
                    <p class="text-xs sm:text-sm text-slate-300 font-light leading-relaxed">ทีมช่างตรวจสอบข้อมูลและมอบหมายผู้รับผิดชอบงานซ่อม</p>
                </div>

                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-sky-400/50 transition-all space-y-3 sm:space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-sky-500 text-white font-extrabold flex items-center justify-center text-sm sm:text-lg shadow-md">03</span>
                        <i class="fas fa-wrench text-2xl sm:text-3xl text-sky-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-white">3. ดำเนินการซ่อม</h3>
                    <p class="text-xs sm:text-sm text-slate-300 font-light leading-relaxed">ช่างผู้เชี่ยวชาญเข้าแก้ไขตามจุดที่ได้รับแจ้งอย่างรวดเร็ว</p>
                </div>

                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-emerald-400/50 transition-all space-y-3 sm:space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-emerald-500 text-white font-extrabold flex items-center justify-center text-sm sm:text-lg shadow-md">04</span>
                        <i class="fas fa-circle-check text-2xl sm:text-3xl text-emerald-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-base sm:text-lg font-bold text-white">4. เสร็จสิ้น & แจ้งเตือน</h3>
                    <p class="text-xs sm:text-sm text-slate-300 font-light leading-relaxed">รับอุปกรณ์คืน พร้อมอัปเดตสถานะเป็นซ่อมเสร็จเรียบร้อย</p>
                </div>

            </div>
        </div>
    </section>

    <!-- 5. CLEAN & MODERN LIGHT FOOTER -->
    <footer id="developers" class="w-full bg-white border-t border-slate-200/80 pt-10 pb-8 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start pb-6 sm:pb-8 border-b border-slate-100">
                
                <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-blue-600 text-white flex items-center justify-center text-lg sm:text-xl font-bold shadow-md shadow-blue-500/20">
                            <i class="fas fa-screwdriver-wrench"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">MBS <span class="text-blue-600">REPAIR</span></h3>
                    </div>
                    
                    <p class="text-xs sm:text-sm text-slate-500 leading-relaxed max-w-md font-normal">
                        ระบบรับแจ้งซ่อมออนไลน์ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม พัฒนาเพื่อยกระดับการให้บริการบุคลากรและนิสิตอย่างมีประสิทธิภาพ
                    </p>
                </div>

                <div class="lg:col-span-7 space-y-3 sm:space-y-4">
                    <h4 class="text-xs sm:text-sm font-extrabold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-code text-blue-600"></i> ผู้พัฒนาโครงการ (Project Developers)
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        
                        <div class="bg-slate-50 border border-slate-200/80 hover:border-blue-300 p-3 sm:p-4 rounded-2xl transition-all flex items-center gap-3 sm:gap-4">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-base sm:text-lg font-bold shrink-0">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h5 class="text-sm sm:text-base font-bold text-slate-800 truncate">นางสาวภัทรวดี ขามประโคน</h5>
                                <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                            </div>
                        </div>

                        <div class="bg-slate-50 border border-slate-200/80 hover:border-blue-300 p-3 sm:p-4 rounded-2xl transition-all flex items-center gap-3 sm:gap-4">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-base sm:text-lg font-bold shrink-0">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h5 class="text-sm sm:text-base font-bold text-slate-800 truncate">นางสาวมัทนา รัตนแสง</h5>
                                <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs sm:text-sm font-medium text-slate-400">
                <p>© 2026 MBS REPAIR — คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                <p class="text-slate-500 font-semibold flex items-center gap-1.5">
                    <i class="fas fa-graduation-cap text-blue-600 text-base sm:text-lg"></i> Business Computer (BC) MBS
                </p>
            </div>

        </div>
    </footer>

    <!-- ==============================================
         MODAL 1: RESULT MODAL 
         ============================================== -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div id="resultModalContent" class="relative bg-white w-full max-w-2xl mx-auto z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] rounded-3xl sm:rounded-[2rem] shadow-2xl border border-white">
            
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 via-sky-400 to-emerald-400"></div>

            <div class="px-6 pt-6 pb-4 sm:px-8 sm:pt-8 sm:pb-5 flex justify-between items-center border-b border-slate-50 shrink-0">
                <div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 flex items-center gap-2">
                        <i class="fas fa-list-check text-blue-600"></i> ผลการค้นหาประวัติ
                    </h2>
                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mt-1.5">
                        คำค้นหา: <span class="text-blue-600"><?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?></span>
                    </p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 focus:outline-none transition-colors flex items-center justify-center shrink-0">
                    <i class="fas fa-times text-base sm:text-lg"></i>
                </button>
            </div>
            
            <div class="p-4 sm:p-8 overflow-y-auto flex-1 bg-slate-50/50 space-y-3 sm:space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-700 border-slate-200"; 
                        if($res['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-800 border-amber-200";
                        elseif($res['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-800 border-sky-200";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-800 border-emerald-200";
                    ?>
                        <a href="view_repair.php?id=<?php echo $res['id']; ?>" target="_blank" class="block bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-md hover:border-blue-300 transition-all cursor-pointer">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-4 mb-4">
                                <div>
                                    <span class="text-[10px] sm:text-xs font-extrabold text-slate-400 uppercase tracking-widest">เลขที่ใบงาน</span>
                                    <h4 class="text-base sm:text-lg font-black text-blue-600 mt-0.5 sm:mt-1"><?php echo $res['ticket_no']; ?></h4>
                                </div>
                                <span class="px-3 sm:px-4 py-1 sm:py-1.5 rounded-full text-[11px] sm:text-sm font-bold border <?php echo $statusClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 text-xs sm:text-sm border-t border-slate-100 pt-3 sm:pt-4 text-slate-600">
                                <p><b class="text-slate-800 font-extrabold">อุปกรณ์:</b> <?php echo $res['equipment_type']; ?></p>
                                <p><b class="text-slate-800 font-extrabold">ผู้แจ้ง:</b> <?php echo $res['reporter_name']; ?></p>
                                <p><b class="text-slate-800 font-extrabold">ช่างผู้ดูแล:</b> <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?></p>
                                <p><b class="text-slate-800 font-extrabold">วันที่แจ้ง:</b> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-4 sm:p-6 border-t border-slate-50 bg-white shrink-0">
                <button onclick="toggleModal('resultModal')" class="w-full bg-slate-900 hover:bg-slate-800 py-3 sm:py-4 rounded-xl font-bold text-sm sm:text-base text-white focus:outline-none transition-colors shadow-md active:scale-95">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ==============================================
         MODAL 2: LOGIN MODAL 
         ============================================== -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div id="loginModalContent" class="relative bg-white w-full max-w-md rounded-3xl sm:rounded-[2rem] shadow-2xl z-50 flex flex-col transform transition-transform duration-300 scale-95 data-[open=true]:scale-100 overflow-hidden">
            
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 via-sky-400 to-emerald-400"></div>

            <div class="px-6 pt-8 pb-5 sm:px-8 sm:pt-10 sm:pb-6 text-center relative border-b border-slate-50">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 sm:top-6 sm:right-6 w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 focus:outline-none transition-colors">
                    <i class="fas fa-times text-base sm:text-lg"></i>
                </button>
                
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-tr from-blue-600 to-sky-400 text-white rounded-2xl flex items-center justify-center text-2xl sm:text-3xl mx-auto mb-4 sm:mb-5 shadow-lg shadow-blue-500/30">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">เจ้าหน้าที่เข้าสู่ระบบ</h2>
                <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mt-1 sm:mt-1.5">MBS Smart Maintenance</p>
            </div>

            <form action="" method="POST" class="p-6 pt-5 sm:p-8 sm:pt-6 bg-slate-50/30">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4 sm:space-y-6">
                    <div>
                        <label class="block text-xs sm:text-sm font-extrabold text-slate-600 uppercase tracking-widest mb-2 sm:mb-2.5 pl-1">Username</label>
                        <div class="relative group">
                            <i class="fas fa-user absolute left-4 sm:left-5 top-1/2 -translate-y-1/2 text-slate-400 text-base sm:text-lg group-focus-within:text-blue-500 transition-colors"></i>
                            <input type="text" name="username" required placeholder="ระบุชื่อผู้ใช้งาน" class="w-full pl-11 pr-4 py-3 sm:pl-14 sm:pr-4 sm:py-4 bg-white border border-slate-200 rounded-xl text-sm sm:text-base font-bold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15 transition-all shadow-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-extrabold text-slate-600 uppercase tracking-widest mb-2 sm:mb-2.5 pl-1">Password</label>
                        <div class="relative group">
                            <i class="fas fa-lock absolute left-4 sm:left-5 top-1/2 -translate-y-1/2 text-slate-400 text-base sm:text-lg group-focus-within:text-blue-500 transition-colors"></i>
                            <input type="password" id="modalPassword" name="password" required placeholder="ระบุรหัสผ่าน" class="w-full pl-11 pr-10 py-3 sm:pl-14 sm:pr-12 sm:py-4 bg-white border border-slate-200 rounded-xl text-sm sm:text-base font-bold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15 transition-all shadow-sm">
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 sm:pr-5 flex items-center text-slate-400 hover:text-blue-600 focus:outline-none transition-colors" onclick="toggleModalPassword()">
                                <i id="modalEyeIcon" class="fas fa-eye text-base sm:text-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-6 sm:mt-8 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-xl py-3.5 sm:py-4 font-bold text-sm sm:text-base hover:from-blue-500 hover:to-blue-400 focus:outline-none transition-all flex items-center justify-center gap-2 shadow-[0_10px_20px_-5px_rgba(59,130,246,0.5)] transform hover:-translate-y-0.5 active:scale-95">
                    เข้าสู่ระบบ <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(m) { 
            const modal = document.getElementById(m);
            const content = document.getElementById(m + 'Content');
            
            if (modal.classList.contains('opacity-0')) {
                modal.classList.remove('opacity-0', 'pointer-events-none');
                if (content) content.setAttribute('data-open', 'true');
                document.body.classList.add('modal-active');
            } else {
                modal.classList.add('opacity-0');
                if (content) content.setAttribute('data-open', 'false');
                setTimeout(() => {
                    modal.classList.add('pointer-events-none');
                    document.body.classList.remove('modal-active');
                }, 300);
            }
        }

        function toggleModalPassword() {
            var x = document.getElementById("modalPassword");
            var icon = document.getElementById("modalEyeIcon");
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

    <?php if(!empty($error_msg)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'error', title: 'แจ้งเตือนระบบ', text: '<?php echo $error_msg; ?>', confirmButtonColor: '#2563eb' });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'warning', title: 'ไม่พบข้อมูล', text: 'ไม่พบประวัติการแจ้งซ่อมจาก "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"', confirmButtonColor: '#2563eb' });
        });
    </script>
    <?php elseif(is_array($status_result)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() { toggleModal('resultModal'); });
    </script>
    <?php endif; ?>

</body>
</html>