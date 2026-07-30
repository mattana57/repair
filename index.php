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
        
        /* 1. แก้ไขพื้นหลังให้สว่างและไล่เฉดสีเหมือนในรูปเป๊ะๆ */
        .hero-bg {
            background: linear-gradient(180deg, #111827 0%, #1e3a8a 55%, #2563eb 100%);
        }

        /* 2. เพิ่มแสงเงา (Glow) ให้กับปุ่ม */
        .btn-glow-blue {
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.6);
        }
        .btn-glow-green {
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.6);
        }

        .modal { transition: opacity 0.25s ease, visibility 0.25s ease; }
        body.modal-active { overflow: hidden; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-600 selection:text-white">

    <!-- NAVIGATION HEADER -->
    <header class="w-full bg-white sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-[76px] flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center text-lg shadow-md">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <h1 class="text-base font-extrabold text-slate-900 tracking-tight leading-none">MBS REPAIR</h1>
                    <p class="text-[10px] font-semibold text-slate-500 uppercase mt-0.5">มหาวิทยาลัยมหาสารคาม</p>
                </div>
            </div>

            <nav class="hidden md:flex items-center gap-10 text-sm font-bold text-slate-600">
                <a href="#" class="text-blue-600 flex items-center gap-2 hover:text-blue-700 transition"><i class="fas fa-home"></i> หน้าแรก</a>
                <a href="#workflow" class="hover:text-blue-600 transition flex items-center gap-2"><i class="fas fa-list-ul"></i> ขั้นตอนการทำงาน</a>
                <a href="#developers" class="hover:text-blue-600 transition flex items-center gap-2"><i class="fas fa-users"></i> ผู้พัฒนา</a>
            </nav>

            <button onclick="toggleModal('loginModal')" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-5 py-2 rounded-full text-xs font-bold transition-colors flex items-center gap-2 shadow-sm">
                <i class="fas fa-lock text-slate-400"></i> เจ้าหน้าที่
            </button>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero-bg text-white pt-24 pb-48 px-4 relative">
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight leading-[1.2] mb-6 drop-shadow-md">
                แจ้งซ่อมอุปกรณ์และติดตามสถานะ<br>
                ได้อย่างสะดวกรวดเร็ว
            </h1>
            
            <p class="text-blue-100 text-sm md:text-base font-medium max-w-2xl mx-auto mb-10 opacity-90">
                บริการรับแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า อาคารสถานที่ และอุปกรณ์ในห้องเรียน สำหรับบุคลากรและนิสิต MBS
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-5">
                <a href="form_repair.php" class="w-full sm:w-auto bg-[#3b82f6] hover:bg-blue-500 text-white px-8 py-4 rounded-xl font-bold text-sm flex items-center justify-center gap-3 btn-glow-blue transition-all hover:-translate-y-1 hover:shadow-[0_15px_35px_-5px_rgba(59,130,246,0.8)]">
                    <i class="fas fa-file-pen text-lg"></i> กรอกแบบฟอร์มแจ้งซ่อมใหม่
                </a>
                <a href="https://line.me" target="_blank" class="w-full sm:w-auto bg-[#10b981] hover:bg-emerald-500 text-white px-8 py-4 rounded-xl font-bold text-sm flex items-center justify-center gap-3 btn-glow-green transition-all hover:-translate-y-1 hover:shadow-[0_15px_35px_-5px_rgba(16,185,129,0.8)]">
                    <i class="fab fa-line text-xl"></i> ติดต่อผ่าน LINE Official
                </a>
            </div>
        </div>
    </section>

    <!-- SEARCH & STATUS CARDS -->
    <section class="w-full relative z-20 px-4 -mt-28 mb-0">
        <div class="max-w-[1000px] mx-auto">
            
            <!-- Search Card -->
            <div class="bg-white rounded-[2rem] p-6 md:p-8 shadow-xl mb-6">
                <div class="flex justify-between items-center mb-5 px-1">
                    <label class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-search text-blue-500"></i> ค้นหาประวัติ / ตรวจสอบสถานะการแจ้งซ่อม
                    </label>
                    <span class="text-[10px] text-blue-500 font-bold px-3 py-1 rounded-full border border-blue-200">Real-time Search</span>
                </div>
                
                <form action="" method="POST" class="flex flex-col sm:flex-row gap-3">
                    <input type="hidden" name="check_status" value="1">
                    <div class="relative flex-1">
                        <i class="fas fa-ticket-alt absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search_query" required placeholder="กรอกเลขใบงาน (เช่น MR-001) หรือชื่อผู้แจ้ง..." class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:bg-white focus:ring-4 ring-blue-50 transition-all">
                    </div>
                    <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white px-10 py-3.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-transform hover:-translate-y-0.5 whitespace-nowrap shadow-md">
                        <i class="fas fa-search"></i> ตรวจสอบสถานะ
                    </button>
                </form>
            </div>

            <!-- Status Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4 hover:-translate-y-1 transition-transform">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-base shrink-0"><i class="fas fa-file-alt"></i></div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 mb-0.5">แจ้งซ่อมทั้งหมด</p>
                        <h3 class="text-2xl font-black text-slate-800 leading-none"><?php echo number_format($stats['total']); ?> <span class="text-[10px] font-medium text-slate-400">รายการ</span></h3>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4 hover:-translate-y-1 transition-transform">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-base shrink-0"><i class="fas fa-compass"></i></div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 mb-0.5">กำลังดำเนินการ</p>
                        <h3 class="text-2xl font-black text-slate-800 leading-none"><?php echo number_format($stats['progress']); ?> <span class="text-[10px] font-medium text-slate-400">รายการ</span></h3>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4 hover:-translate-y-1 transition-transform">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-base shrink-0"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 mb-0.5">ซ่อมเสร็จแล้ว</p>
                        <h3 class="text-2xl font-black text-slate-800 leading-none"><?php echo number_format($stats['completed']); ?> <span class="text-[10px] font-medium text-slate-400">รายการ</span></h3>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4 hover:-translate-y-1 transition-transform">
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-base shrink-0"><i class="fas fa-trash-alt"></i></div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 mb-0.5">ยกเลิก/ลบ</p>
                        <h3 class="text-2xl font-black text-slate-800 leading-none">0 <span class="text-[10px] font-medium text-slate-400">รายการ</span></h3>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- WORKFLOW SECTION -->
    <section id="workflow" class="w-full bg-[#1e293b] pt-20 pb-20 -mt-16">
        <div class="max-w-[1100px] mx-auto px-4 mt-20">
            
            <div class="text-center mb-12">
                <div class="inline-flex items-center justify-center gap-2 text-[11px] font-black text-blue-300 tracking-[0.1em] mb-3 uppercase">
                    <i class="fas fa-code-branch"></i> WORKFLOW PROCESS
                </div>
                <h2 class="text-3xl md:text-[2.5rem] font-black text-white mb-4 tracking-tight">ขั้นตอนการแจ้งซ่อมง่ายๆ ใน 4 ขั้นตอน</h2>
                <p class="text-white/60 text-sm font-medium">ติดตามเรื่องซ่อมสะดวกรวดเร็ว แม่นยำทุกขั้นตอน</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="bg-[#2e3b5e] rounded-[2rem] p-8 border border-transparent transition-all hover:border-blue-400 hover:-translate-y-1 shadow-lg">
                    <div class="flex justify-between items-start mb-10">
                        <div class="bg-amber-500 text-white text-sm font-black px-4 py-1.5 rounded-[0.6rem]">01</div>
                        <i class="fas fa-pen-to-square text-3xl text-blue-400"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">1. กรอกแบบฟอร์ม</h3>
                    <p class="text-xs text-white/60 font-medium leading-relaxed">ระบุรายละเอียดอุปกรณ์ อาคารสถานที่ และปัญหาที่พบผ่านเว็บ</p>
                </div>

                <div class="bg-[#2e3b5e] rounded-[2rem] p-8 border border-transparent transition-all hover:border-amber-500 hover:-translate-y-1 shadow-lg">
                    <div class="flex justify-between items-start mb-10">
                        <div class="bg-amber-500 text-white text-sm font-black px-4 py-1.5 rounded-[0.6rem]">02</div>
                        <i class="fas fa-user-check text-3xl text-amber-500"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">2. เจ้าหน้าที่รับเรื่อง</h3>
                    <p class="text-xs text-white/60 font-medium leading-relaxed">ทีมช่างตรวจสอบข้อมูลและมอบหมายผู้รับผิดชอบงานซ่อม</p>
                </div>

                <div class="bg-[#2e3b5e] rounded-[2rem] p-8 border border-transparent transition-all hover:border-sky-400 hover:-translate-y-1 shadow-lg">
                    <div class="flex justify-between items-start mb-10">
                        <div class="bg-sky-400 text-white text-sm font-black px-4 py-1.5 rounded-[0.6rem]">03</div>
                        <i class="fas fa-wrench text-3xl text-sky-400"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">3. ดำเนินการซ่อม</h3>
                    <p class="text-xs text-white/60 font-medium leading-relaxed">ช่างผู้เชี่ยวชาญเข้าแก้ไขตามจุดที่ได้รับแจ้งอย่างรวดเร็ว</p>
                </div>

                <div class="bg-[#2e3b5e] rounded-[2rem] p-8 border border-transparent transition-all hover:border-emerald-500 hover:-translate-y-1 shadow-lg">
                    <div class="flex justify-between items-start mb-10">
                        <div class="bg-emerald-500 text-white text-sm font-black px-4 py-1.5 rounded-[0.6rem]">04</div>
                        <i class="fas fa-check-circle text-3xl text-emerald-500"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">4. เสร็จสิ้น</h3>
                    <p class="text-xs text-white/60 font-medium leading-relaxed">รับอุปกรณ์คืน พร้อมอัปเดตสถานะเป็นซ่อมเสร็จเรียบร้อย</p>
                </div>
            </div>

        </div>
    </section>

    <!-- DEVELOPERS SECTION -->
    <section id="developers" class="w-full bg-white text-slate-800 pt-16 pb-16 border-t border-slate-100">
        <div class="max-w-[1000px] mx-auto px-4">
            
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 text-xs font-extrabold text-blue-600 uppercase mb-2">
                    <i class="fas fa-code"></i> ผู้พัฒนาโครงการ (PROJECT DEVELOPERS)
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-center gap-4">
                <div class="bg-white border border-slate-200 rounded-[2rem] p-2 flex items-center gap-4 hover:shadow-md transition-shadow md:w-96">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-800">นางสาวภัทรวดี ขามประโคน</h4>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 rounded-[2rem] p-2 flex items-center gap-4 hover:shadow-md transition-shadow md:w-96">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-slate-800">นางสาวมัทนา รัตนแสง</h4>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- FOOTER -->
    <footer class="w-full bg-white border-t border-slate-100 py-8">
        <div class="max-w-[1000px] mx-auto px-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs font-medium text-slate-400">© 2026 MBS REPAIR — คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
            <p class="text-xs font-bold text-slate-500 flex items-center gap-2"><i class="fas fa-shield-alt text-slate-300"></i> Smart Maintenance System</p>
        </div>
    </footer>

    <!-- MODAL RESULTS & LOGIN -->
    <div id="resultModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white w-full max-w-3xl rounded-3xl shadow-2xl z-50 flex flex-col max-h-[85vh] transform transition-transform duration-300 scale-95 data-[open=true]:scale-100 text-slate-800" id="resultModalContent">
            
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-extrabold text-slate-900">ผลการค้นหาประวัติการแจ้งซ่อม</h2>
                    <p class="text-xs font-medium text-slate-500 mt-1">คำค้นหา: <span class="text-blue-600 font-bold">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50 space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-700 border-slate-200"; 
                        $icon = "fa-file-alt text-slate-400";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $statusClass = "bg-amber-100 text-amber-800 border-amber-200";
                            $icon = "fa-clock text-amber-500";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $statusClass = "bg-blue-100 text-blue-800 border-blue-200";
                            $icon = "fa-tools text-blue-500";
                            $res['status'] = 'ช่างรับเรื่องแล้ว';
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $statusClass = "bg-emerald-100 text-emerald-800 border-emerald-200";
                            $icon = "fa-check-circle text-emerald-500";
                        }
                    ?>
                        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 pb-4 border-b border-slate-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-lg">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Ticket No.</p>
                                        <h3 class="text-lg font-extrabold text-slate-900"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold border <?php echo $statusClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs text-slate-600">
                                <div><p class="text-[10px] font-bold text-slate-400 mb-1">อุปกรณ์</p><p class="font-bold text-slate-800"><?php echo $res['equipment_type']; ?></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 mb-1">ผู้แจ้ง</p><p class="font-bold text-slate-800"><?php echo $res['reporter_name']; ?></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 mb-1">ผู้รับผิดชอบ</p><p class="font-bold text-slate-800"><?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 mb-1">วันที่แจ้ง</p><p class="font-bold text-slate-800"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-4 border-t border-slate-100 bg-white text-center rounded-b-3xl">
                <button onclick="toggleModal('resultModal')" class="bg-slate-900 hover:bg-slate-800 text-white rounded-xl px-10 py-3 text-sm font-bold transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl z-50 flex flex-col transform transition-transform duration-300 scale-95 data-[open=true]:scale-100 text-slate-800" id="loginModalContent">
            
            <div class="px-8 pt-10 pb-6 text-center relative border-b border-slate-100">
                <button onclick="toggleModal('loginModal')" class="absolute top-6 right-6 w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4 border border-blue-100">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="text-xl font-extrabold text-slate-900">เจ้าหน้าที่เข้าสู่ระบบ</h2>
            </div>

            <form action="" method="POST" class="p-8">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="relative group">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="username" required placeholder="ระบุชื่อผู้ใช้งาน" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">รหัสผ่าน (Password)</label>
                        <div class="relative group">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password" id="modalPassword" name="password" required placeholder="ระบุรหัสผ่าน" class="w-full pl-11 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-blue-600 transition-colors" onclick="toggleModalPassword()">
                                <i id="modalEyeIcon" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-6 bg-slate-900 text-white rounded-xl py-3.5 font-bold text-sm hover:bg-blue-600 transition-all flex items-center justify-center gap-2 shadow-md">
                    เข้าสู่ระบบ <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleModal(m) { 
            const modal = document.getElementById(m);
            const content = document.getElementById(m + 'Content');
            
            if (modal.classList.contains('opacity-0')) {
                modal.classList.remove('opacity-0', 'invisible');
                content.setAttribute('data-open', 'true');
                document.body.classList.add('modal-active');
            } else {
                modal.classList.add('opacity-0');
                content.setAttribute('data-open', 'false');
                setTimeout(() => {
                    modal.classList.add('invisible');
                    document.body.classList.remove('modal-active');
                }, 200);
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
            Swal.fire({
                icon: 'error',
                title: 'เข้าสู่ระบบไม่สำเร็จ',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#0f172a',
                customClass: { popup: 'rounded-[2rem] shadow-2xl' }
            }).then(() => { toggleModal('loginModal'); });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบข้อมูล',
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก: "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบอีกครั้ง',
                confirmButtonColor: '#3b82f6',
                customClass: { popup: 'rounded-[2rem] shadow-2xl' }
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