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

        .modal { transition: opacity 0.25s ease, visibility 0.25s ease; }
        body.modal-active { overflow: hidden; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    <!-- 1. NAVIGATION HEADER (อัปเดตตาม Reference เป๊ะๆ) -->
    <header class="w-full bg-white/95 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Logo & Title -->
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-[0.8rem] bg-gradient-to-tr from-blue-600 to-sky-400 text-white flex items-center justify-center text-xl shadow-lg shadow-blue-500/30">
                    <i class="fas fa-screwdriver-wrench"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-[22px] font-black tracking-tight leading-none text-slate-900">
                        MBS <span class="text-[#0ea5e9]">REPAIR</span>
                    </h1>
                    <p class="text-[10px] text-slate-500 font-bold mt-1">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                </div>
            </div>

            <!-- Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-[13px] font-extrabold text-slate-600">
                <a href="#" class="hover:text-blue-600 transition-colors flex items-center gap-2.5"><i class="fas fa-house text-blue-500 text-base"></i> หน้าแรก</a>
                <a href="#process" class="hover:text-blue-600 transition-colors flex items-center gap-2.5"><i class="fas fa-list-check text-blue-500 text-base"></i> ขั้นตอนการทำงาน</a>
                <a href="#developers" class="hover:text-blue-600 transition-colors flex items-center gap-2.5"><i class="fas fa-user-group text-blue-500 text-base"></i> ผู้พัฒนา</a>
            </nav>

            <!-- Login Button -->
            <button onclick="toggleModal('loginModal')" class="bg-[#0f172a] hover:bg-blue-600 text-white font-bold px-5 py-2.5 rounded-xl text-[13px] transition-all flex items-center gap-2 shadow-md hover:shadow-blue-500/20 active:scale-95">
                <i class="fas fa-user-shield text-blue-400 text-base"></i> เจ้าหน้าที่เข้าสู่ระบบ
            </button>
        </div>
    </header>

    <!-- 2. HERO BANNER & SEARCH SECTION -->
    <section class="hero-gradient text-white pt-16 pb-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 right-10 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-4xl mx-auto text-center space-y-6 relative z-10">

            <h1 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">
                แจ้งซ่อมอุปกรณ์และติดตามสถานะ <br>
                <span class="bg-gradient-to-r from-blue-200 via-sky-300 to-emerald-300 bg-clip-text text-transparent">ได้อย่างสะดวกรวดเร็ว</span>
            </h1>

            <p class="text-slate-300 text-xs sm:text-sm max-w-2xl mx-auto font-normal leading-relaxed">
                บริการรับแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า อาคารสถานที่ และอุปกรณ์ในห้องเรียน สำหรับบุคลากรและนิสิต MBS
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                <a href="form_repair.php" class="bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold px-8 py-4 rounded-2xl text-xs sm:text-sm shadow-xl shadow-blue-600/30 transition-all transform hover:-translate-y-1 flex items-center gap-2.5">
                    <i class="fas fa-file-pen text-base"></i> กรอกแบบฟอร์มแจ้งซ่อมใหม่
                </a>
                <a href="https://line.me" target="_blank" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-7 py-4 rounded-2xl text-xs sm:text-sm shadow-xl shadow-emerald-500/25 transition-all transform hover:-translate-y-1 flex items-center gap-2.5">
                    <i class="fab fa-line text-xl"></i> ติดต่อผ่าน LINE Official
                </a>
            </div>

            <!-- Floating Search Card -->
            <div class="max-w-2xl mx-auto bg-white/95 backdrop-blur-xl p-4 sm:p-5 rounded-3xl shadow-2xl border border-white/40 text-slate-800 text-left mt-8 transform hover:scale-[1.01] transition-transform">
                <div class="flex items-center justify-between mb-3 px-1">
                    <label class="text-xs font-extrabold text-blue-950 flex items-center gap-2">
                        <i class="fas fa-magnifying-glass text-blue-600"></i> ค้นหาประวัติ / ตรวจสอบสถานะการแจ้งซ่อม
                    </label>
                    <span class="text-[10px] bg-blue-50 text-blue-700 font-bold px-2.5 py-0.5 rounded-full border border-blue-200">Real-time Search</span>
                </div>
                
                <form action="" method="POST" class="flex flex-col sm:flex-row gap-2.5">
                    <input type="hidden" name="check_status" value="1">
                    <div class="relative flex-1">
                        <i class="fas fa-ticket-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" name="search_query" required placeholder="กรอกเลขที่ใบงาน (เช่น MR-001) หรือชื่อผู้แจ้ง..." class="w-full pl-11 pr-4 py-3 bg-slate-100/90 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:bg-white transition-all">
                    </div>
                    <button type="submit" class="bg-slate-900 hover:bg-blue-600 text-white font-bold px-7 py-3 rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-md hover:shadow-blue-500/20">
                        <i class="fas fa-search"></i> ตรวจสอบสถานะ
                    </button>
                </form>
            </div>

        </div>
    </section>

    <!-- 3. STATS CARDS SECTION -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-12 relative z-20 w-full">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            
            <div class="card-3d p-5 flex items-center gap-4">
                <div class="w-13 h-13 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-500 text-white shadow-lg shadow-amber-500/30 flex items-center justify-center text-xl font-bold shrink-0">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div>
                    <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">รอรับเรื่อง</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['pending']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="card-3d p-5 flex items-center gap-4">
                <div class="w-13 h-13 rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg shadow-blue-500/30 flex items-center justify-center text-xl font-bold shrink-0">
                    <i class="fa-regular fa-compass"></i>
                </div>
                <div>
                    <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">กำลังดำเนินการ</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['progress']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="card-3d p-5 flex items-center gap-4">
                <div class="w-13 h-13 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-lg shadow-emerald-500/30 flex items-center justify-center text-xl font-bold shrink-0">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">ซ่อมเสร็จแล้ว</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['completed']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="card-3d p-5 flex items-center gap-4">
                <div class="w-13 h-13 rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 text-white shadow-lg shadow-slate-900/30 flex items-center justify-center text-xl font-bold shrink-0">
                    <i class="fa-regular fa-clipboard"></i>
                </div>
                <div>
                    <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">งานซ่อมทั้งหมด</p>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['total']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

        </div>
    </section>

    <!-- 4. WORKFLOW TIMELINE -->
    <section id="process" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
        <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-3xl p-8 sm:p-12 text-white shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-blue-500/10 rounded-full blur-3xl"></div>
            
            <div class="text-center space-y-2 mb-12 relative z-10">
                <span class="text-blue-400 text-xs font-extrabold uppercase tracking-widest"><i class="fas fa-route mr-1"></i> Workflow Process</span>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tight">ขั้นตอนการแจ้งซ่อมง่ายๆ ใน 4 ขั้นตอน</h2>
                <p class="text-xs sm:text-sm text-slate-300 font-light">ติดตามเรื่องซ่อมสะดวกรวดเร็ว แม่นยำทุกขั้นตอน</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative z-10">
                
                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-blue-400/50 transition-all space-y-3 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 rounded-xl bg-blue-600 text-white font-extrabold flex items-center justify-center text-sm shadow-md">01</span>
                        <i class="fas fa-pen-to-square text-2xl text-blue-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-sm font-bold text-white">1. กรอกแบบฟอร์ม</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">ระบุรายละเอียดอุปกรณ์ อาคารสถานที่ และปัญหาที่พบผ่านเว็บ</p>
                </div>

                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-amber-400/50 transition-all space-y-3 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 rounded-xl bg-amber-500 text-white font-extrabold flex items-center justify-center text-sm shadow-md">02</span>
                        <i class="fas fa-user-check text-2xl text-amber-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-sm font-bold text-white">2. เจ้าหน้าที่รับเรื่อง</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">ทีมช่างตรวจสอบข้อมูลและมอบหมายผู้รับผิดชอบงานซ่อม</p>
                </div>

                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-sky-400/50 transition-all space-y-3 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 rounded-xl bg-sky-500 text-white font-extrabold flex items-center justify-center text-sm shadow-md">03</span>
                        <i class="fas fa-wrench text-2xl text-sky-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-sm font-bold text-white">3. ดำเนินการซ่อม</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">ช่างผู้เชี่ยวชาญเข้าแก้ไขตามจุดที่ได้รับแจ้งอย่างรวดเร็ว</p>
                </div>

                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-emerald-400/50 transition-all space-y-3 group">
                    <div class="flex items-center justify-between">
                        <span class="w-10 h-10 rounded-xl bg-emerald-500 text-white font-extrabold flex items-center justify-center text-sm shadow-md">04</span>
                        <i class="fas fa-circle-check text-2xl text-emerald-400 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-sm font-bold text-white">4. เสร็จสิ้น & แจ้งเตือน</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">รับอุปกรณ์คืน พร้อมอัปเดตสถานะเป็นซ่อมเสร็จเรียบร้อย</p>
                </div>

            </div>
        </div>
    </section>

    <!-- 5. CLEAN & MODERN LIGHT FOOTER -->
    <footer id="developers" class="w-full bg-white border-t border-slate-200/80 pt-12 pb-8 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pb-8 border-b border-slate-100">
                
                <!-- Left: System Branding Info -->
                <div class="lg:col-span-5 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-lg font-bold shadow-md shadow-blue-500/20">
                            <i class="fas fa-screwdriver-wrench"></i>
                        </div>
                        <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">MBS <span class="text-blue-600">REPAIR</span></h3>
                    </div>
                    
                    <p class="text-xs text-slate-500 leading-relaxed max-w-md font-normal">
                        ระบบรับแจ้งซ่อมออนไลน์ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม พัฒนาเพื่อยกระดับการให้บริการบุคลากรและนิสิตอย่างมีประสิทธิภาพ
                    </p>
                </div>

                <!-- Right: Developer Showcase -->
                <div class="lg:col-span-7 space-y-3">
                    <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-code text-blue-600"></i> ผู้พัฒนาโครงการ (Project Developers)
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        
                        <!-- Dev Card 1 -->
                        <div class="bg-slate-50 border border-slate-200/80 hover:border-blue-300 p-3.5 rounded-2xl transition-all flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h5 class="text-xs font-bold text-slate-800 truncate">นางสาวภัทรวดี ขามประโคน</h5>
                                <p class="text-[11px] text-slate-500">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                            </div>
                        </div>

                        <!-- Dev Card 2 -->
                        <div class="bg-slate-50 border border-slate-200/80 hover:border-blue-300 p-3.5 rounded-2xl transition-all flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h5 class="text-xs font-bold text-slate-800 truncate">นางสาวมัทนา รัตนแสง</h5>
                                <p class="text-[11px] text-slate-500">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Bottom Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-medium text-slate-400">
                <p>© 2026 MBS REPAIR — คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                <p class="text-slate-500 font-semibold flex items-center gap-1.5">
                    <i class="fas fa-graduation-cap text-blue-600"></i> Business Computer (BC) MBS
                </p>
            </div>

        </div>
    </footer>

    <!-- MODAL 1: RESULT MODAL -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-2xl mx-auto z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] p-6 rounded-3xl shadow-2xl border border-slate-100">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-list-check text-blue-600"></i> ผลการค้นหาประวัติการแจ้งซ่อม
                </h2>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center">
                    <i class="fas fa-xmark text-xs"></i>
                </button>
            </div>
            <div class="py-4 overflow-y-auto flex-1 space-y-3">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-700 border-slate-200"; 
                        if($res['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-800 border-amber-200";
                        elseif($res['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-800 border-sky-200";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-800 border-emerald-200";
                    ?>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">เลขที่ใบงาน</span>
                                    <h4 class="text-base font-bold text-blue-600"><?php echo $res['ticket_no']; ?></h4>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold border <?php echo $statusClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs border-t border-slate-200/60 pt-2 text-slate-600">
                                <p><b class="text-slate-800">อุปกรณ์:</b> <?php echo $res['equipment_type']; ?></p>
                                <p><b class="text-slate-800">ผู้แจ้ง:</b> <?php echo $res['reporter_name']; ?></p>
                                <p><b class="text-slate-800">ช่างผู้ดูแล:</b> <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?></p>
                                <p><b class="text-slate-800">วันที่แจ้ง:</b> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button onclick="toggleModal('resultModal')" class="w-full bg-slate-900 hover:bg-blue-600 py-3 rounded-xl font-bold text-xs text-white transition-colors">ปิดหน้าต่าง</button>
        </div>
    </div>

    <!-- MODAL 2: LOGIN MODAL -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto z-50 overflow-hidden transform transition-all p-8 rounded-3xl shadow-2xl border border-slate-100">
            <div class="text-center pb-4 border-b border-slate-100 relative">
                <button onclick="toggleModal('loginModal')" class="absolute top-0 right-0 w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center">
                    <i class="fas fa-xmark text-xs"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl mx-auto mb-2 shadow-md shadow-blue-500/30">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h2 class="text-lg font-bold text-slate-900">เจ้าหน้าที่เข้าสู่ระบบ</h2>
            </div>
            <form action="" method="POST" class="mt-6 space-y-4">
                <input type="hidden" name="login" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-600 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">รหัสผ่าน (Password)</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-600 focus:bg-white">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-xl font-bold text-xs text-white shadow-lg shadow-blue-600/30 transition-all mt-4">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
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