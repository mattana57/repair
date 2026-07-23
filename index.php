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
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #f4f4f0; /* Off-white background */
            color: #111827; 
            overflow-x: hidden; 
        }
        .font-mono { font-family: 'Space Mono', monospace; }
        .modal { transition: opacity 0.2s ease-in-out; }
        body.modal-active { overflow: hidden; }
        
        /* Neo-Brutalism Components */
        .brutal-border { border: 2px solid #111827; }
        .brutal-border-thick { border: 4px solid #111827; }
        
        /* Hard Shadows */
        .brutal-shadow { box-shadow: 4px 4px 0 0 #111827; }
        .brutal-shadow-lg { box-shadow: 8px 8px 0 0 #111827; }
        .brutal-shadow-blue { box-shadow: 6px 6px 0 0 #2563eb; }
        
        /* Hover Effects */
        .brutal-hover:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0 0 #111827; }
        .brutal-hover-blue:hover { transform: translate(-2px, -2px); box-shadow: 8px 8px 0 0 #2563eb; }
        .brutal-active:active { transform: translate(2px, 2px); box-shadow: 0px 0px 0 0 #111827; }

        /* Grid Pattern Background */
        .bg-grid-pattern {
            background-image: 
                linear-gradient(to right, #e2e8f0 1px, transparent 1px),
                linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .bg-dot-pattern {
            background-image: radial-gradient(#cbd5e1 2px, transparent 2px);
            background-size: 20px 20px;
        }

        /* Marquee Animation */
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee { display: inline-block; white-space: nowrap; animation: marquee 20s linear infinite; }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-600 selection:text-white">

    <!-- Top Ticker Bar -->
    <div class="w-full brutal-border border-t-0 border-l-0 border-r-0 bg-blue-600 text-white py-1 overflow-hidden flex items-center">
        <div class="animate-marquee font-mono text-[10px] font-bold tracking-widest uppercase">
            [ SYSTEM ONLINE ] &nbsp;&nbsp;&nbsp; MBS IT SUPPORT & MAINTENANCE &nbsp;&nbsp;&nbsp; [ 24/7 TRACKING ] &nbsp;&nbsp;&nbsp; FACULTY OF ACCOUNTANCY AND MANAGEMENT &nbsp;&nbsp;&nbsp; [ SYSTEM ONLINE ] &nbsp;&nbsp;&nbsp; MBS IT SUPPORT & MAINTENANCE &nbsp;&nbsp;&nbsp; [ 24/7 TRACKING ]
        </div>
    </div>

    <!-- Header (Grid-based, Clean lines) -->
    <header class="w-full bg-white brutal-border border-t-0 border-l-0 border-r-0 fixed top-[24px] z-40">
        <div class="max-w-[1400px] mx-auto px-4 md:px-8 h-[70px] flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-slate-900 brutal-border text-white flex items-center justify-center brutal-shadow">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-xl font-black text-slate-900 tracking-tight uppercase leading-none">MBS REPAIR</h1>
                    <span class="text-[9px] font-mono text-slate-500 font-bold tracking-widest uppercase mt-0.5">Faculty of Accountancy & Mgt.</span>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="flex items-center gap-6">
                <nav class="hidden md:flex items-center gap-8 font-mono text-xs font-bold text-slate-600">
                    <a href="#" class="hover:text-blue-600 transition-colors uppercase border-b-2 border-transparent hover:border-blue-600 pb-1">Home</a>
                    <a href="#categories" class="hover:text-blue-600 transition-colors uppercase border-b-2 border-transparent hover:border-blue-600 pb-1">Services</a>
                </nav>
                <button onclick="toggleModal('loginModal')" class="bg-blue-600 text-white brutal-border px-5 py-2 font-mono font-bold text-xs uppercase brutal-shadow transition-all brutal-hover brutal-active flex items-center gap-2">
                    <i class="fas fa-lock text-[10px]"></i> Login
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section (Neo-Brutalism Split Grid) -->
    <main class="pt-[94px] w-full max-w-[1400px] mx-auto flex-grow flex flex-col">
        <div class="grid grid-cols-1 lg:grid-cols-12 brutal-border border-l-0 border-r-0 bg-white">
            
            <!-- Left: Typography & Action -->
            <div class="lg:col-span-7 p-8 md:p-12 lg:p-20 flex flex-col justify-center border-b-2 lg:border-b-0 lg:border-r-2 border-slate-900 relative bg-grid-pattern z-10">
                <div class="absolute inset-0 bg-white/80 -z-10"></div> <!-- Fade grid slightly -->
                
                <div class="font-mono text-[10px] md:text-xs font-bold tracking-[0.2em] text-blue-600 uppercase mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-600 inline-block"></span> [ IT Service Management ]
                </div>
                
                <h1 class="text-5xl md:text-6xl lg:text-[5rem] font-black text-slate-900 leading-[1.05] mb-6 uppercase tracking-tight">
                    ระบบแจ้งซ่อม<br>
                    <span class="text-blue-600 relative inline-block">
                        ออนไลน์อัจฉริยะ
                        <!-- Offset background gimmick -->
                        <span class="absolute top-2 left-2 w-full h-full bg-blue-100 -z-10 brutal-border border-blue-600 hidden md:block"></span>
                    </span>
                </h1>
                
                <p class="text-slate-600 mb-10 max-w-lg font-medium text-sm md:text-base border-l-4 border-slate-900 pl-4 py-1">
                    ยกระดับการให้บริการด้านเทคโนโลยีและอาคารสถานที่ คณะบัญชีฯ มมส. ด้วยระบบที่รวดเร็ว โปร่งใส และติดตามผลได้แบบ Real-time
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                    <a href="form_repair.php" class="bg-blue-600 text-white brutal-border border-slate-900 px-8 py-4 font-bold text-sm md:text-base uppercase tracking-wider brutal-shadow-lg transition-all brutal-hover-blue w-full sm:w-auto text-center flex items-center justify-center gap-3">
                        เริ่มแจ้งซ่อมเลย <i class="fas fa-arrow-right"></i>
                    </a>
                    
                    <!-- Small Stats -->
                    <div class="hidden sm:flex items-center gap-6 ml-4 font-mono">
                        <div>
                            <div class="text-xl font-bold text-slate-900">24/7</div>
                            <div class="text-[9px] text-slate-500 uppercase tracking-widest">Available</div>
                        </div>
                        <div class="w-1 h-8 bg-slate-200"></div>
                        <div>
                            <div class="text-xl font-bold text-slate-900">100%</div>
                            <div class="text-[9px] text-slate-500 uppercase tracking-widest">Tracking</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right: Image with Frame -->
            <div class="lg:col-span-5 p-8 md:p-16 flex items-center justify-center relative bg-slate-50 bg-dot-pattern">
                <div class="relative w-full max-w-[400px] aspect-square group">
                    <!-- Offset Border -->
                    <div class="absolute inset-0 bg-blue-600 translate-x-4 translate-y-4 brutal-border"></div>
                    <!-- Image Box -->
                    <div class="relative w-full h-full brutal-border-thick bg-white overflow-hidden z-10 transition-transform duration-300 group-hover:-translate-y-2 group-hover:-translate-x-2">
                        <img src="uploads/mbs_bg.jpg?v=8" alt="MBS MSU" class="w-full h-full object-cover grayscale-[20%] group-hover:grayscale-0 transition-all duration-500">
                        
                        <!-- Minimal Tag -->
                        <div class="absolute top-4 left-4 bg-white brutal-border px-3 py-1 font-mono text-[10px] font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            IMG_01: MBS_BLDG
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distinct Search Bar Section (Clear & High Contrast) -->
        <div class="brutal-border border-l-0 border-r-0 border-t-0 bg-[#e2e8f0] p-6 md:p-10 flex flex-col md:flex-row justify-center items-center gap-6">
            <div class="font-mono text-sm font-bold text-slate-800 uppercase tracking-widest hidden md:block">
                [ CHECK STATUS ]
            </div>
            
            <form action="" method="POST" class="w-full max-w-3xl flex flex-col md:flex-row gap-4">
                <input type="hidden" name="check_status" value="1">
                
                <div class="flex-1 brutal-border bg-white p-2 brutal-shadow flex items-stretch focus-within:translate-y-[-2px] focus-within:shadow-[6px_6px_0_0_#111827] transition-all relative">
                    <div class="bg-slate-100 brutal-border w-12 flex items-center justify-center text-slate-500 mr-2">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="w-full py-1 pr-2 flex flex-col justify-center">
                        <input type="text" id="searchInput" name="search_query" required placeholder="พิมพ์เลขใบงาน (เช่น MR-001) หรือ ชื่อผู้แจ้ง" class="w-full text-base font-medium text-slate-900 placeholder-slate-400 focus:outline-none bg-transparent h-full">
                    </div>
                </div>
                
                <button type="submit" class="bg-slate-900 text-white brutal-border px-10 py-4 font-bold brutal-shadow hover:shadow-[6px_6px_0_0_#3b82f6] hover:-translate-y-1 hover:-translate-x-1 transition-all uppercase tracking-widest text-sm flex items-center justify-center gap-2">
                    ค้นหาข้อมูล <i class="fas fa-terminal text-[10px] text-blue-400"></i>
                </button>
            </form>
        </div>
    </main>

    <!-- Categories Section (Raw Grid Style) -->
    <section id="categories" class="w-full max-w-[1400px] mx-auto bg-white brutal-border border-t-0 border-l-0 border-r-0">
        <div class="grid grid-cols-1 md:grid-cols-4 divide-y-2 md:divide-y-0 md:divide-x-2 divide-slate-900">
            <!-- Header for Section -->
            <div class="p-8 md:p-10 col-span-1 md:col-span-4 bg-slate-50 brutal-border border-t-0 border-l-0 border-r-0 flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 uppercase tracking-tight">Our Services</h2>
                    <p class="font-mono text-xs font-bold text-blue-600 uppercase tracking-widest mt-2">หมวดหมู่การให้บริการ</p>
                </div>
                <div class="hidden md:block font-mono text-[10px] text-slate-400 uppercase tracking-widest border border-slate-300 px-3 py-1">Select a category below</div>
            </div>

            <!-- Card 1 -->
            <div class="p-8 md:p-10 bg-white hover:bg-blue-50 transition-colors group cursor-default relative overflow-hidden">
                <div class="w-14 h-14 bg-slate-100 brutal-border text-slate-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors brutal-shadow">
                    <i class="fas fa-desktop"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">คอมพิวเตอร์</h3>
                <p class="text-sm text-slate-600 font-medium">ซ่อมแซม อัปเกรด และแก้ไขปัญหาซอฟต์แวร์</p>
            </div>
            
            <!-- Card 2 -->
            <div class="p-8 md:p-10 bg-white hover:bg-blue-50 transition-colors group cursor-default relative overflow-hidden">
                <div class="w-14 h-14 bg-slate-100 brutal-border text-slate-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors brutal-shadow">
                    <i class="fas fa-wifi"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">ระบบเครือข่าย</h3>
                <p class="text-sm text-slate-600 font-medium">แก้ไขปัญหาอินเทอร์เน็ต LAN และ Wi-Fi</p>
            </div>

            <!-- Card 3 -->
            <div class="p-8 md:p-10 bg-white hover:bg-blue-50 transition-colors group cursor-default relative overflow-hidden">
                <div class="w-14 h-14 bg-slate-100 brutal-border text-slate-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors brutal-shadow">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">ระบบไฟฟ้า</h3>
                <p class="text-sm text-slate-600 font-medium">หลอดไฟ ปลั๊กไฟ แอร์ อุปกรณ์ไฟฟ้า</p>
            </div>

            <!-- Card 4 -->
            <div class="p-8 md:p-10 bg-white hover:bg-blue-50 transition-colors group cursor-default relative overflow-hidden">
                <div class="w-14 h-14 bg-slate-100 brutal-border text-slate-800 flex items-center justify-center text-2xl mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors brutal-shadow">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">อาคารสถานที่</h3>
                <p class="text-sm text-slate-600 font-medium">ซ่อมแซมประปา โต๊ะ เก้าอี้ สภาพแวดล้อม</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="w-full max-w-[1400px] mx-auto bg-white mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 brutal-border border-t-0 border-l-0 border-r-0">
            
            <!-- Brand Info -->
            <div class="lg:col-span-7 p-8 md:p-12 border-b-2 lg:border-b-0 lg:border-r-2 border-slate-900 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 bg-slate-900 brutal-border text-white flex items-center justify-center text-xs">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">MBS REPAIR</h2>
                    </div>
                    <p class="text-sm text-slate-600 font-medium max-w-md">
                        ระบบรับแจ้งซ่อมออนไลน์สำหรับบุคลากรและนิสิต คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </p>
                </div>
                
                <div class="mt-12 font-mono text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-4">
                    <span>&copy; <?php echo date('Y'); ?> MBS REPAIR.</span>
                    <a href="https://www.msu.ac.th/" target="_blank" class="hover:text-blue-600 transition-colors underline decoration-2 underline-offset-4">MSU Website</a>
                </div>
            </div>

            <!-- Project Credit -->
            <div class="lg:col-span-5 p-8 md:p-12 bg-[#f4f4f0] flex flex-col justify-center">
                <div class="font-mono text-[10px] font-bold tracking-[0.2em] text-slate-400 uppercase mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-600"></i> Project Info
                </div>
                
                <div class="bg-white brutal-border p-6 brutal-shadow">
                    <p class="font-mono text-xs font-bold text-slate-400 uppercase tracking-widest border-b-2 border-slate-900 pb-2 mb-4">Developers</p>
                    <ul class="space-y-2 mb-6">
                        <li class="text-sm font-bold text-slate-900 flex items-center gap-2"><div class="w-2 h-2 bg-blue-600 brutal-border"></div> นางสาวภัทรวดี ขามประโคน</li>
                        <li class="text-sm font-bold text-slate-900 flex items-center gap-2"><div class="w-2 h-2 bg-blue-600 brutal-border"></div> นางสาวมัทนา รัตนแสง</li>
                    </ul>
                    
                    <p class="font-mono text-xs font-bold text-slate-400 uppercase tracking-widest border-b-2 border-slate-900 pb-2 mb-4">Department</p>
                    <p class="text-sm font-bold text-slate-800">นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ</p>
                    <p class="text-sm font-medium text-slate-600">คณะการบัญชีและการจัดการ</p>
                </div>
            </div>

        </div>
    </footer>

    <!-- Result Modal (Brutalist Style) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-[2px]" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-full md:max-w-2xl mx-auto brutal-border-thick brutal-shadow-lg z-50 flex flex-col max-h-[85vh]">
            
            <!-- Modal Header -->
            <div class="p-6 border-b-4 border-slate-900 shrink-0 bg-blue-50 flex justify-between items-center">
                <div>
                    <div class="font-mono text-[10px] font-bold tracking-[0.2em] text-blue-600 uppercase mb-1">Search Result</div>
                    <h2 class="text-2xl font-black text-slate-900 uppercase">ข้อมูลการแจ้งซ่อม</h2>
                    <p class="text-xs font-medium text-slate-600 mt-1">Keyword: <span class="bg-yellow-200 px-1 font-bold text-slate-900">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-10 h-10 bg-white brutal-border text-slate-900 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors brutal-shadow brutal-hover brutal-active">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-white space-y-6">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        // Brutalist Status Styling
                        $statusColor = "bg-slate-200"; 
                        $icon = "fa-file-alt";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $statusColor = "bg-yellow-400";
                            $icon = "fa-clock";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $statusColor = "bg-blue-400";
                            $icon = "fa-tools";
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $statusColor = "bg-green-400";
                            $icon = "fa-check";
                        }
                    ?>
                        <div class="brutal-border bg-[#f4f4f0] p-6 relative">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-slate-900">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-white brutal-border flex items-center justify-center text-xl shadow-[4px_4px_0_0_#111827]">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-widest">Ticket No.</p>
                                        <h3 class="text-xl font-black text-slate-900 uppercase"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <div>
                                    <!-- Hard Edge Badge -->
                                    <span class="inline-flex items-center px-4 py-2 brutal-border font-bold text-xs uppercase tracking-wider <?php echo $statusColor; ?> brutal-shadow">
                                        <?php echo $res['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                                <div>
                                    <p class="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-300 pb-1 mb-2">อุปกรณ์</p>
                                    <p class="font-bold text-slate-800"><?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-300 pb-1 mb-2">ผู้แจ้ง</p>
                                    <p class="font-bold text-slate-800"><?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-300 pb-1 mb-2">ช่างดูแล</p>
                                    <p class="font-bold <?php echo !empty($res['technician_name']) ? 'text-blue-600' : 'text-slate-400 line-through'; ?>">
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : 'N/A'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-300 pb-1 mb-2">วันที่แจ้ง</p>
                                    <p class="font-bold font-mono text-slate-800"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if(!empty($res['repair_note'])): ?>
                            <div class="mt-6 pt-4 border-t-2 border-slate-900 border-dashed">
                                <p class="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">หมายเหตุจากช่าง</p>
                                <p class="text-sm font-medium text-slate-700 bg-white brutal-border p-3">"<?php echo $res['repair_note']; ?>"</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Modal Footer -->
            <div class="p-6 border-t-4 border-slate-900 shrink-0 flex justify-center bg-slate-50">
                <button onclick="toggleModal('resultModal')" class="bg-slate-200 text-slate-900 brutal-border px-8 py-3 font-bold uppercase tracking-widest text-xs brutal-shadow brutal-hover brutal-active">Close Window</button>
            </div>
        </div>
    </div>

    <!-- Login Modal (Brutalist Form) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-[2px]" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-full max-w-sm mx-auto brutal-border-thick brutal-shadow-lg z-50 flex flex-col">
            
            <div class="p-8 border-b-4 border-slate-900 bg-blue-600 text-white relative">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 bg-white text-slate-900 brutal-border hover:bg-red-500 hover:text-white flex items-center justify-center brutal-shadow brutal-hover brutal-active transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-14 h-14 bg-white text-slate-900 brutal-border flex items-center justify-center text-2xl mb-4 shadow-[4px_4px_0_0_#111827]">
                    <i class="fas fa-key"></i>
                </div>
                <h2 class="text-2xl font-black uppercase tracking-tight">Staff Login</h2>
                <p class="font-mono text-[10px] font-bold tracking-[0.2em] mt-2 opacity-80 uppercase">Authentication Required</p>
            </div>

            <form action="" method="POST" class="p-8 bg-[#f4f4f0]">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block font-mono text-[10px] font-bold text-slate-600 uppercase tracking-widest mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-user text-xs"></i>
                            </div>
                            <input type="text" name="username" required class="w-full pl-10 pr-4 py-3 bg-white brutal-border text-slate-900 font-bold focus:outline-none focus:shadow-[4px_4px_0_0_#2563eb] focus:-translate-y-1 focus:-translate-x-1 transition-all">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block font-mono text-[10px] font-bold text-slate-600 uppercase tracking-widest mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-xs"></i>
                            </div>
                            <input type="password" name="password" required class="w-full pl-10 pr-4 py-3 bg-white brutal-border text-slate-900 font-bold focus:outline-none focus:shadow-[4px_4px_0_0_#2563eb] focus:-translate-y-1 focus:-translate-x-1 transition-all">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-slate-900 text-white brutal-border py-4 font-bold uppercase tracking-widest text-sm brutal-shadow-lg brutal-hover-blue brutal-active transition-all">
                    Login <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Floating LINE Button -->
    <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="fixed bottom-6 right-6 md:bottom-8 md:right-8 z-40 bg-[#00B900] hover:bg-[#009900] text-white brutal-border px-5 py-3 font-bold text-sm brutal-shadow-lg brutal-hover brutal-active transition-all flex items-center justify-center">
        <i class="fab fa-line text-2xl md:mr-2"></i> 
        <span class="hidden md:inline uppercase tracking-widest text-[10px]">Support</span>
    </a>

    <!-- Script -->
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
            Swal.fire({
                icon: 'error',
                title: 'ACCESS DENIED',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#111827',
                confirmButtonText: 'RETRY',
                customClass: { popup: 'brutal-border-thick brutal-shadow-lg rounded-none' }
            }).then(() => { toggleModal('loginModal'); });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'NOT FOUND',
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก: "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบอีกครั้ง',
                confirmButtonColor: '#2563eb',
                customClass: { popup: 'brutal-border-thick brutal-shadow-lg rounded-none' }
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