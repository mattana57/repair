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
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; color: #1e293b; overflow-x: hidden; }
        .modal { transition: opacity 0.3s ease-in-out; }
        body.modal-active { overflow: hidden; }
        .font-serif-num { font-family: 'Playfair Display', serif; }
        
        /* Clean Corporate Colors */
        .text-mbs-dark { color: #0b1f4a; }
        .bg-mbs-dark { background-color: #0b1f4a; }
        
        /* Subtle Fade In */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.6s ease-out forwards; opacity: 0; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-200">

    <!-- Header (Clean & Solid) -->
    <header class="w-full bg-white/95 backdrop-blur-sm border-b border-slate-200 fixed top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 md:px-8 h-[76px] flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-mbs-dark flex items-center justify-center text-white shadow-sm">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="flex flex-col justify-center">
                    <h1 class="text-lg font-black text-mbs-dark tracking-wide uppercase leading-tight">MBS REPAIR</h1>
                    <span class="text-[9px] text-slate-500 font-bold tracking-[0.15em] uppercase">Faculty of Accountancy & Management</span>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="flex items-center gap-8">
                <nav class="hidden md:flex items-center gap-6 text-sm font-bold text-slate-500">
                    <a href="#" class="hover:text-blue-600 transition-colors tracking-widest uppercase">Home</a>
                    <a href="#categories" class="hover:text-blue-600 transition-colors tracking-widest uppercase">Services</a>
                </nav>
                <button onclick="toggleModal('loginModal')" class="bg-mbs-dark hover:bg-blue-800 text-white px-6 py-2.5 rounded-full font-bold text-xs transition-colors shadow-md uppercase tracking-wider flex items-center">
                    <i class="fas fa-lock mr-2"></i> <span>Login</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section (Clean Split Layout) -->
    <main class="pt-[100px] md:pt-[120px] relative z-10 w-full px-4 md:px-6 lg:px-8 mb-16 fade-in">
        <div class="max-w-[1280px] mx-auto bg-mbs-dark rounded-3xl shadow-xl overflow-hidden flex flex-col md:flex-row min-h-[480px] md:min-h-[550px] relative">
            
            <!-- Text Content (Left) -->
            <div class="w-full md:w-[55%] lg:w-[50%] p-8 md:p-16 relative z-10 flex flex-col justify-center bg-mbs-dark">
                <div class="inline-block px-3 py-1 mb-6 rounded-full bg-blue-900/50 border border-blue-800 text-blue-300 text-[10px] font-bold tracking-[0.2em] uppercase w-max">
                    <i class="fas fa-bolt text-yellow-400 mr-1"></i> IT Service Management
                </div>
                
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white leading-[1.15] mb-4 tracking-tight">
                    ระบบแจ้งซ่อม<br>
                    <span class="text-blue-400">ออนไลน์อัจฉริยะ</span>
                </h1>
                
                <p class="text-sm md:text-base text-slate-300 mb-10 max-w-md font-light leading-relaxed">
                    ยกระดับการให้บริการด้านเทคโนโลยีและอาคารสถานที่ คณะบัญชีฯ มมส. ด้วยระบบที่รวดเร็ว โปร่งใส และติดตามผลได้แบบ Real-time
                </p>
                
                <div>
                    <a href="form_repair.php" class="inline-flex bg-blue-600 hover:bg-blue-500 text-white px-8 py-4 rounded-xl font-bold tracking-wider transition-transform hover:-translate-y-1 items-center shadow-lg shadow-blue-600/30">
                        เริ่มแจ้งซ่อมอุปกรณ์ <i class="fas fa-arrow-right ml-3"></i>
                    </a>
                </div>

                <div class="flex items-center gap-8 mt-12 pt-6 border-t border-white/10">
                    <div>
                        <div class="text-2xl font-serif-num font-bold text-white">24<span class="text-sm text-blue-400">/7</span></div>
                        <div class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">Available</div>
                    </div>
                    <div>
                        <div class="text-2xl font-serif-num font-bold text-white">100<span class="text-sm text-blue-400">%</span></div>
                        <div class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">Tracking</div>
                    </div>
                </div>
            </div>

            <!-- Image (Right) -->
            <div class="absolute inset-0 md:static md:w-[45%] lg:w-[50%] h-full opacity-20 md:opacity-100">
                <!-- ใช้รูปคณะแบบเต็มกรอบขวา พร้อม Fade ไล่สีให้กลืนกับกรอบซ้าย -->
                <div class="w-full h-full bg-cover bg-center" style="background-image: url('uploads/mbs_bg.jpg?v=4');"></div>
                <div class="hidden md:block absolute inset-0 bg-gradient-to-r from-mbs-dark via-mbs-dark/60 to-transparent w-[30%]"></div>
            </div>

        </div>

        <!-- Search Bar (Solid White - HIGH VISIBILITY) -->
        <div class="relative w-[92%] md:w-[75%] max-w-3xl mx-auto -mt-12 md:-mt-10 z-20 fade-in delay-100">
            <div class="bg-white rounded-2xl shadow-[0_10px_30px_-10px_rgba(0,0,0,0.15)] border border-slate-200 p-2">
                <form action="" method="POST" class="flex flex-col md:flex-row items-stretch gap-2">
                    <input type="hidden" name="check_status" value="1">
                    
                    <div class="flex-1 flex items-center bg-slate-50 rounded-xl px-5 py-3 border border-slate-200 focus-within:border-blue-400 focus-within:bg-white transition-colors cursor-text" onclick="document.getElementById('searchInput').focus();">
                        <i class="fas fa-search text-slate-400 text-lg mr-3"></i>
                        <div class="w-full">
                            <p class="text-[10px] font-bold tracking-widest text-slate-500 mb-0.5 uppercase">ตรวจสอบสถานะ (Check Status)</p>
                            <!-- เปลี่ยน placeholder ให้สีเข้มขึ้น (slate-500) และตัวอักษรพิมพ์สีเข้ม (slate-800) -->
                            <input type="text" id="searchInput" name="search_query" required placeholder="พิมพ์เลขใบงาน หรือ ชื่อผู้แจ้ง" class="w-full text-base focus:outline-none text-slate-800 placeholder-slate-400 bg-transparent font-medium">
                        </div>
                    </div>

                    <button type="submit" class="bg-mbs-dark hover:bg-blue-700 text-white px-10 py-4 md:py-0 rounded-xl font-bold transition-colors uppercase tracking-widest text-sm flex items-center justify-center">
                        ค้นหา
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Categories Section (Clean Grid) -->
    <section id="categories" class="max-w-7xl mx-auto px-4 md:px-8 pt-10 pb-24 w-full fade-in delay-200">
        <div class="text-center mb-12">
            <h3 class="text-blue-600 font-bold tracking-[0.2em] text-[11px] uppercase mb-2">Our Services</h3>
            <h2 class="text-2xl md:text-3xl font-black text-mbs-dark tracking-tight">หมวดหมู่การให้บริการ</h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1 -->
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200 hover:border-blue-400 hover:shadow-lg transition-all flex flex-col items-center text-center group cursor-default">
                <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-5 group-hover:scale-110 transition-transform">
                    <i class="fas fa-desktop"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">คอมพิวเตอร์</h3>
                <p class="text-xs text-slate-500">ซ่อมแซมและแก้ไขปัญหาซอฟต์แวร์</p>
            </div>
            
            <!-- Card 2 -->
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200 hover:border-blue-400 hover:shadow-lg transition-all flex flex-col items-center text-center group cursor-default">
                <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-5 group-hover:scale-110 transition-transform">
                    <i class="fas fa-wifi"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">ระบบเครือข่าย</h3>
                <p class="text-xs text-slate-500">แก้ไขปัญหาอินเทอร์เน็ตและ Wi-Fi</p>
            </div>

            <!-- Card 3 -->
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200 hover:border-blue-400 hover:shadow-lg transition-all flex flex-col items-center text-center group cursor-default">
                <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-5 group-hover:scale-110 transition-transform">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">ระบบไฟฟ้า</h3>
                <p class="text-xs text-slate-500">หลอดไฟ ปลั๊กไฟ แอร์ อุปกรณ์ไฟฟ้า</p>
            </div>

            <!-- Card 4 -->
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200 hover:border-blue-400 hover:shadow-lg transition-all flex flex-col items-center text-center group cursor-default">
                <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-5 group-hover:scale-110 transition-transform">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">อาคารสถานที่</h3>
                <p class="text-xs text-slate-500">ซ่อมแซมประปา โต๊ะ เก้าอี้ ฯลฯ</p>
            </div>
        </div>
    </section>

    <!-- Footer (Clean & Professional) -->
    <footer class="bg-white border-t border-slate-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 md:px-8 py-16">
            <div class="flex flex-col md:flex-row justify-between items-start gap-12 md:gap-8">
                
                <!-- Brand Info -->
                <div class="w-full md:w-5/12 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-mbs-dark flex items-center justify-center text-white">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h2 class="text-xl font-black text-mbs-dark tracking-wide uppercase">MBS REPAIR</h2>
                    </div>
                    <p class="text-sm text-slate-500 leading-relaxed max-w-sm">
                        ระบบรับแจ้งซ่อมออนไลน์สำหรับบุคลากรและนิสิต คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </p>
                </div>

                <!-- Project Credit (Clean Box) -->
                <div class="w-full md:w-6/12 bg-slate-50 rounded-2xl p-6 md:p-8 border border-slate-100">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="fas fa-graduation-cap text-blue-600 text-lg"></i>
                        <h3 class="font-bold text-slate-800 tracking-widest text-xs uppercase">Graduation Project</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase mb-2">จัดทำโดย</p>
                            <ul class="space-y-1">
                                <li class="text-sm text-slate-700 font-medium">นางสาวภัทรวดี ขามประโคน</li>
                                <li class="text-sm text-slate-700 font-medium">นางสาวมัทนา รัตนแสง</li>
                            </ul>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase mb-2">ภาควิชา</p>
                            <p class="text-sm text-slate-600">
                                นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ<br>
                                คณะการบัญชีและการจัดการ
                            </p>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="mt-12 pt-6 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-xs text-slate-400 font-medium tracking-wider">&copy; <?php echo date('Y'); ?> MBS REPAIR SYSTEM.</p>
            </div>
            
        </div>
    </footer>

    <!-- Result Modal (Clean UI) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/50 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-full md:max-w-2xl mx-auto shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] rounded-2xl">
            
            <div class="p-6 md:p-8 flex justify-between items-start border-b border-slate-100 shrink-0 bg-slate-50">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold tracking-widest uppercase mb-2">Search Result</span>
                    <h2 class="text-xl font-black text-mbs-dark">ข้อมูลการแจ้งซ่อม</h2>
                    <p class="text-xs text-slate-500 mt-1">คำค้นหา: <span class="font-bold text-blue-600">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-white space-y-6">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        // Clean Status Colors
                        $borderClass = "border-slate-200"; 
                        $badgeClass = "bg-slate-100 text-slate-700";
                        $iconClass = "text-slate-400";
                        $icon = "fa-file-alt";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $borderClass = "border-amber-200";
                            $badgeClass = "bg-amber-100 text-amber-700";
                            $iconClass = "text-amber-500";
                            $icon = "fa-clock";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $borderClass = "border-blue-200";
                            $badgeClass = "bg-blue-100 text-blue-700";
                            $iconClass = "text-blue-500";
                            $icon = "fa-tools";
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $borderClass = "border-emerald-200";
                            $badgeClass = "bg-emerald-100 text-emerald-700";
                            $iconClass = "text-emerald-500";
                            $icon = "fa-check-circle";
                        }
                    ?>
                        <div class="rounded-xl border <?php echo $borderClass; ?> p-6 relative bg-white shadow-sm">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5 pb-4 border-b border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl <?php echo $iconClass; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ticket No.</p>
                                        <h3 class="text-lg font-bold text-mbs-dark"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?php echo $badgeClass; ?>">
                                        <?php echo $res['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">อุปกรณ์</p>
                                    <p class="font-medium text-slate-700"><?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">ผู้แจ้ง</p>
                                    <p class="font-medium text-slate-700"><?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">ช่างดูแล</p>
                                    <p class="font-medium <?php echo !empty($res['technician_name']) ? 'text-blue-600' : 'text-slate-400 italic'; ?>">
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : 'ยังไม่ระบุช่าง'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">วันที่แจ้ง</p>
                                    <p class="font-medium text-slate-700"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if(!empty($res['repair_note'])): ?>
                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold mb-1">หมายเหตุจากช่าง</p>
                                <p class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100"><?php echo $res['repair_note']; ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="p-6 bg-slate-50 border-t border-slate-100 shrink-0 flex justify-center">
                <button onclick="toggleModal('resultModal')" class="bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 px-8 py-2.5 rounded-xl font-bold uppercase tracking-wider text-sm transition-colors shadow-sm">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Login Modal (Clean Form) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/50 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-full max-w-sm mx-auto shadow-2xl z-50 overflow-hidden transform transition-all rounded-2xl">
            
            <div class="p-8 text-center relative border-b border-slate-100">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-14 h-14 rounded-xl bg-mbs-dark text-white flex items-center justify-center text-2xl mx-auto mb-4 shadow-md">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h2 class="text-xl font-black text-mbs-dark uppercase tracking-wide">Staff Login</h2>
                <p class="text-xs text-slate-500 mt-1">สำหรับเจ้าหน้าที่และผู้ดูแลระบบ</p>
            </div>

            <form action="" method="POST" class="p-8 pt-6">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" name="username" required placeholder="กรอกชื่อผู้ใช้" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 text-slate-800 rounded-lg focus:outline-none focus:border-blue-500 focus:bg-white transition-colors text-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" name="password" required placeholder="กรอกรหัสผ่าน" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 text-slate-800 rounded-lg focus:outline-none focus:border-blue-500 focus:bg-white transition-colors text-sm">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold text-sm tracking-wider transition-colors shadow-md">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>

    <!-- Floating LINE Button -->
    <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="fixed bottom-6 right-6 md:bottom-8 md:right-8 z-40 bg-[#00B900] hover:bg-[#009900] text-white w-14 h-14 md:w-auto md:h-auto md:px-5 md:py-3 rounded-full font-bold text-sm shadow-lg transition-transform hover:-translate-y-1 flex items-center justify-center">
        <i class="fab fa-line text-3xl md:text-xl md:mr-2"></i> 
        <span class="hidden md:inline tracking-wide">ติดต่อแอดมิน</span>
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
                title: 'เข้าสู่ระบบไม่สำเร็จ',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#0b1f4a',
                confirmButtonText: 'ลองอีกครั้ง',
                customClass: { popup: 'rounded-2xl' }
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
                customClass: { popup: 'rounded-2xl' }
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