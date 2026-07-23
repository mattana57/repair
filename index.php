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
    <!-- เพิ่มฟอนต์ Serif สำหรับตัวเลขตกแต่งสไตล์ PPT -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f4f5f7; color: #334155; overflow-x: hidden; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
        .font-serif-num { font-family: 'Playfair Display', serif; }
        
        /* Corporate Style Colors & BG */
        .bg-corporate-dark { background-color: #0b1f4a; }
        .text-corporate-dark { color: #0b1f4a; }
        .border-corporate-dark { border-color: #0b1f4a; }
        
        /* ================================================================= */
        /* 🚨 แก้ไข Path เรียกรูปจากโฟลเดอร์ uploads ที่อยู่ระดับเดียวกัน 🚨 */
        /* ================================================================= */
        .bg-tech-image {
            background-image: linear-gradient(to right, rgba(11, 31, 74, 1) 0%, rgba(11, 31, 74, 0.4) 50%, rgba(11, 31, 74, 0.1) 100%), 
                              url('uploads/mbs_bg.jpg?v=2'); /* <--- แก้ตรงนี้ค่ะ (เอา slash ข้างหน้าออก) */
            background-color: #0b1f4a; 
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-200 relative">

    <!-- Header (Clean & Sharp) -->
    <header class="w-full bg-white border-b-2 border-corporate-dark fixed top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 md:px-8 h-[72px] flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-corporate-dark flex items-center justify-center text-white">
                    <i class="fas fa-tools text-lg"></i>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-lg md:text-xl font-extrabold text-corporate-dark tracking-wider uppercase leading-none">MBS Repair</h1>
                    <span class="text-[10px] text-slate-500 tracking-widest uppercase mt-1">Smart Innovation</span>
                </div>
            </div>
            
            <div class="flex items-center gap-8">
                <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                    <a href="#" class="hover:text-blue-700 transition-colors uppercase tracking-wider">Home</a>
                    <a href="#categories" class="hover:text-blue-700 transition-colors uppercase tracking-wider">Services</a>
                </nav>
                <button onclick="toggleModal('loginModal')" class="flex items-center bg-corporate-dark text-white px-5 py-2 hover:bg-blue-800 font-semibold text-sm transition-colors uppercase tracking-wider">
                    <i class="fas fa-sign-in-alt mr-2"></i> <span class="hidden sm:inline">Login</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section (Corporate PPT Style) -->
    <main class="pt-[72px] relative z-10 w-full max-w-7xl mx-auto md:mt-10 px-4 md:px-8">
        <div class="flex flex-col md:flex-row w-full bg-white shadow-2xl relative overflow-hidden">
            
            <!-- Left Side: Deep Blue Block -->
            <div class="w-full md:w-3/5 bg-corporate-dark text-white p-8 md:p-14 lg:p-20 relative z-10 flex flex-col justify-center">
                
                <!-- Gimmick Number -->
                <div class="flex items-start gap-4 mb-8">
                    <span class="text-6xl md:text-7xl font-serif-num font-bold text-blue-200 leading-none">01</span>
                    <div class="pt-2">
                        <h2 class="text-xs font-bold tracking-[0.2em] text-blue-300 uppercase mb-1">Service Management</h2>
                        <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold tracking-wide">ระบบแจ้งซ่อมออนไลน์</h1>
                    </div>
                </div>

                <!-- Simulating PPT Stats -->
                <div class="flex gap-6 md:gap-12 mb-8 border-t border-blue-800/50 pt-8">
                    <div>
                        <div class="text-2xl md:text-3xl font-serif-num font-bold">100<span class="text-base font-sans">%</span></div>
                        <div class="text-xs text-blue-300 mt-1 tracking-wider">ติดตามผลได้ตลอด</div>
                    </div>
                    <div>
                        <div class="text-2xl md:text-3xl font-serif-num font-bold">24<span class="text-base font-sans">/7</span></div>
                        <div class="text-xs text-blue-300 mt-1 tracking-wider">ให้บริการทุกวัน</div>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-2xl md:text-3xl font-serif-num font-bold">FAST</div>
                        <div class="text-xs text-blue-300 mt-1 tracking-wider">ดำเนินการรวดเร็ว</div>
                    </div>
                </div>
                
                <p class="text-sm md:text-base text-blue-100/80 mb-10 max-w-md font-light leading-relaxed">
                    ระบบแจ้งซ่อมอุปกรณ์ คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ สำหรับบุคลากรและนิสิต คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                </p>
                
                <div>
                    <a href="form_repair.php" class="inline-flex bg-blue-600 hover:bg-blue-500 text-white px-8 py-3.5 font-bold tracking-wider transition-all items-center border border-blue-500 hover:border-blue-400 shadow-[0_0_20px_rgba(37,99,235,0.3)] group">
                        แจ้งซ่อมอุปกรณ์ <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>

            <!-- Right Side: Faculty Image -->
            <div class="hidden md:block w-2/5 relative bg-tech-image">
                <!-- Overlay elements -->
                <div class="absolute bottom-8 right-8 text-white/70 text-[10px] tracking-[0.3em] uppercase flex items-center gap-2 shadow-sm">
                    MBS MSU <i class="fas fa-university"></i>
                </div>
            </div>

        </div>

        <!-- Structure Floating Search Bar -->
        <div class="relative w-[95%] mx-auto md:mx-0 md:w-3/4 bg-white shadow-xl border-t-4 border-blue-600 -mt-6 md:-mt-12 z-20">
            <form action="" method="POST" class="flex flex-col md:flex-row items-stretch">
                <input type="hidden" name="check_status" value="1">
                
                <div class="flex-1 w-full flex items-center px-6 py-5 cursor-text" onclick="document.getElementById('searchInput').focus();">
                    <i class="fas fa-search text-slate-300 text-2xl mr-4"></i>
                    <div class="w-full">
                        <p class="text-[10px] font-bold tracking-widest text-corporate-dark mb-1 uppercase">Check Status</p>
                        <input type="text" id="searchInput" name="search_query" required placeholder="ตรวจสอบสถานะ: พิมพ์เลขใบงาน หรือ ชื่อผู้แจ้ง" class="w-full text-sm md:text-base focus:outline-none text-slate-700 placeholder-slate-400 bg-transparent font-medium">
                    </div>
                </div>

                <button type="submit" class="bg-corporate-dark hover:bg-blue-900 text-white px-8 py-4 font-bold transition-all flex items-center justify-center uppercase tracking-wider text-sm border-l border-white/10">
                    <i class="fas fa-search md:hidden mr-2"></i> ค้นหา
                </button>
            </form>
        </div>
    </main>

    <!-- Explore by Category Section -->
    <section id="categories" class="max-w-7xl mx-auto px-4 md:px-8 pt-24 pb-20 w-full">
        <div class="flex items-start gap-4 mb-10">
            <span class="text-5xl font-serif-num font-bold text-slate-300 leading-none">02</span>
            <div class="pt-1">
                <h3 class="text-[10px] font-bold tracking-[0.2em] text-blue-600 uppercase mb-1">Categories</h3>
                <h2 class="text-2xl font-bold text-corporate-dark">หมวดหมู่การให้บริการ</h2>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <!-- Item 1 -->
            <div class="bg-white border border-slate-200 p-6 flex flex-col shadow-sm hover:shadow-lg hover:border-corporate-dark transition-all cursor-default group relative overflow-hidden">
                <div class="w-1 absolute left-0 top-0 bottom-0 bg-blue-600 transform scale-y-0 group-hover:scale-y-100 transition-transform origin-bottom"></div>
                <div class="text-3xl text-slate-300 group-hover:text-corporate-dark transition-colors mb-6">
                    <i class="fas fa-desktop"></i>
                </div>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Service Type</p>
                <p class="text-lg font-bold text-slate-800">คอมพิวเตอร์</p>
            </div>
            <!-- Item 2 -->
            <div class="bg-white border border-slate-200 p-6 flex flex-col shadow-sm hover:shadow-lg hover:border-corporate-dark transition-all cursor-default group relative overflow-hidden">
                <div class="w-1 absolute left-0 top-0 bottom-0 bg-blue-600 transform scale-y-0 group-hover:scale-y-100 transition-transform origin-bottom"></div>
                <div class="text-3xl text-slate-300 group-hover:text-corporate-dark transition-colors mb-6">
                    <i class="fas fa-wifi"></i>
                </div>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Service Type</p>
                <p class="text-lg font-bold text-slate-800">ระบบเครือข่าย</p>
            </div>
            <!-- Item 3 -->
            <div class="bg-white border border-slate-200 p-6 flex flex-col shadow-sm hover:shadow-lg hover:border-corporate-dark transition-all cursor-default group relative overflow-hidden">
                <div class="w-1 absolute left-0 top-0 bottom-0 bg-blue-600 transform scale-y-0 group-hover:scale-y-100 transition-transform origin-bottom"></div>
                <div class="text-3xl text-slate-300 group-hover:text-corporate-dark transition-colors mb-6">
                    <i class="fas fa-bolt"></i>
                </div>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Service Type</p>
                <p class="text-lg font-bold text-slate-800">ระบบไฟฟ้า</p>
            </div>
            <!-- Item 4 -->
            <div class="bg-white border border-slate-200 p-6 flex flex-col shadow-sm hover:shadow-lg hover:border-corporate-dark transition-all cursor-default group relative overflow-hidden">
                <div class="w-1 absolute left-0 top-0 bottom-0 bg-blue-600 transform scale-y-0 group-hover:scale-y-100 transition-transform origin-bottom"></div>
                <div class="text-3xl text-slate-300 group-hover:text-corporate-dark transition-colors mb-6">
                    <i class="fas fa-building"></i>
                </div>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Service Type</p>
                <p class="text-lg font-bold text-slate-800">อาคารสถานที่</p>
            </div>
        </div>
    </section>

    <!-- ================= ส่วน Footer โปรเจกต์จบ ================= -->
    <footer class="bg-white border-t border-slate-200 mt-auto">
        <div class="max-w-5xl mx-auto px-4 md:px-8 py-12 md:py-16">
            
            <div class="flex flex-col md:flex-row justify-center items-start gap-12 md:gap-24 lg:gap-40">
                
                <!-- 1. ข้อมูลองค์กร (ด้านซ้าย) -->
                <div class="w-full md:w-auto max-w-sm space-y-5">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-corporate-dark flex items-center justify-center text-white">
                            <i class="fas fa-tools text-lg"></i>
                        </div>
                        <h2 class="text-xl font-extrabold text-corporate-dark tracking-widest uppercase">MBS Repair</h2>
                    </div>
                    <div class="w-8 border-b-2 border-blue-600"></div>
                    <p class="text-sm text-slate-500 leading-relaxed font-light">
                        ระบบให้บริการแจ้งซ่อมออนไลน์ สำหรับบุคลากรและนิสิต<br>
                        คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </p>
                </div>

                <!-- 2. คณะผู้จัดทำโปรเจกต์จบ (ด้านขวา) -->
                <div class="w-full md:w-auto max-w-sm space-y-5">
                    <h3 class="font-bold text-corporate-dark text-lg uppercase tracking-wider">คณะผู้จัดทำ (โปรเจกต์จบ)</h3>
                    <div class="w-8 border-b-2 border-blue-600"></div>
                    <ul class="space-y-3 text-sm text-slate-600 font-medium">
                        <li class="flex items-center gap-3"><i class="fas fa-square text-[8px] text-blue-600"></i> นางสาวภัทรวดี ขามประโคน</li>
                        <li class="flex items-center gap-3"><i class="fas fa-square text-[8px] text-blue-600"></i> นางสาวมัทนา รัตนแสง</li>
                    </ul>
                    <div class="mt-4 pt-4 border-t border-slate-100 text-[11px] text-slate-400 leading-relaxed uppercase tracking-widest">
                        นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ<br>คณะการบัญชีและการจัดการ
                    </div>
                </div>

            </div>
            
            <!-- ส่วนลิขสิทธิ์ด้านล่างสุด -->
            <div class="mt-12 pt-6 border-t border-slate-100 text-center text-xs text-slate-400 uppercase tracking-widest">
                <p>&copy; <?php echo date('Y'); ?> MBS Repair System. All rights reserved.</p>
            </div>
            
        </div>
    </footer>

    <!-- Modal 2: แสดงผลการค้นหาสถานะ (Results) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-corporate-dark/80 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-full md:max-w-2xl mx-auto shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] rounded-none border-t-4 border-blue-600">
            
            <div class="p-6 flex justify-between items-center bg-slate-50 border-b border-slate-200 shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-corporate-dark uppercase tracking-wider"><i class="fas fa-list-alt text-blue-600 mr-2"></i> ผลการค้นหา</h2>
                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest">Keyword: <span class="font-bold text-blue-600">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 bg-white border border-slate-300 text-slate-400 hover:text-white hover:bg-red-500 hover:border-red-500 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50 space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                        if($res['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-700 border-amber-200";
                        elseif($res['status'] == 'กำลังดำเนินการ') $statusClass = "bg-blue-50 text-blue-700 border-blue-200";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-700 border-emerald-200";
                    ?>
                        <div class="bg-white p-5 border border-slate-200 shadow-sm relative overflow-hidden">
                            <!-- แถบสีด้านซ้าย -->
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php echo str_replace(['bg-', 'text-', 'border-'], ['bg-', 'bg-', 'bg-'], explode(' ', $statusClass)[1]); ?>"></div>
                            
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-3 pl-3">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">TICKET NO.</span>
                                    <h3 class="text-lg font-bold text-corporate-dark"><?php echo $res['ticket_no']; ?></h3>
                                </div>
                                <div class="text-left md:text-right">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-bold border <?php echo $statusClass; ?>">
                                        <span class="w-1.5 h-1.5 bg-current mr-2"></span><?php echo $res['status']; ?>
                                    </span>
                                    <p class="text-xs text-slate-400 mt-2 font-mono"><i class="far fa-clock"></i> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>

                            <div class="pl-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mt-4 pt-4 border-t border-slate-100">
                                <div>
                                    <p class="text-slate-500 mb-1"><i class="fas fa-desktop text-slate-300 w-5 text-center mr-1"></i> <span class="text-xs uppercase tracking-widest font-bold">อุปกรณ์:</span> <span class="text-corporate-dark font-medium"><?php echo $res['equipment_type']; ?></span></p>
                                    <p class="text-slate-500"><i class="fas fa-user text-slate-300 w-5 text-center mr-1"></i> <span class="text-xs uppercase tracking-widest font-bold">ผู้แจ้ง:</span> <span class="text-corporate-dark font-medium"><?php echo $res['reporter_name']; ?></span></p>
                                </div>
                                <div>
                                    <p class="text-slate-500 mb-1"><i class="fas fa-hard-hat text-slate-300 w-5 text-center mr-1"></i> <span class="text-xs uppercase tracking-widest font-bold">ช่างดูแล:</span> <span class="<?php echo !empty($res['technician_name']) ? 'text-blue-600 font-bold' : 'text-slate-400'; ?>"><?php echo !empty($res['technician_name']) ? $res['technician_name'] : '- N/A -'; ?></span></p>
                                    <p class="text-slate-500"><i class="fas fa-comment-dots text-slate-300 w-5 text-center mr-1"></i> <span class="text-xs uppercase tracking-widest font-bold">หมายเหตุ:</span> <span class="text-corporate-dark"><?php echo !empty($res['repair_note']) ? $res['repair_note'] : '-'; ?></span></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="p-6 bg-white border-t border-slate-200 shrink-0 flex justify-center">
                <button onclick="toggleModal('resultModal')" class="bg-corporate-dark hover:bg-slate-800 text-white px-10 py-3 font-bold uppercase tracking-wider text-sm transition-colors shadow-md">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: เข้าสู่ระบบ (Login) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-corporate-dark/80 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto shadow-2xl z-50 overflow-hidden transform transition-all rounded-none border-t-4 border-blue-600">
            
            <div class="p-8 text-center bg-slate-50 border-b border-slate-200 relative">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 bg-white border border-slate-300 text-slate-400 hover:text-white hover:bg-red-500 hover:border-red-500 shadow-sm flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-14 h-14 bg-corporate-dark text-white flex items-center justify-center text-2xl mx-auto mb-4 shadow-lg shadow-blue-900/20">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h2 class="text-2xl font-bold text-corporate-dark uppercase tracking-wider">Staff Login</h2>
                <p class="text-xs tracking-widest uppercase text-slate-500 mt-2">Admin & Technician Access</p>
            </div>

            <form action="" method="POST" class="p-8 pt-6">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-bold tracking-widest text-slate-500 uppercase mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-user text-slate-300"></i>
                            </div>
                            <input type="text" name="username" required placeholder="Enter username" class="w-full pl-11 pr-4 py-3 bg-white border border-slate-300 text-slate-800 focus:outline-none focus:border-corporate-dark focus:ring-1 focus:ring-corporate-dark transition-all font-medium rounded-none">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold tracking-widest text-slate-500 uppercase mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-300"></i>
                            </div>
                            <input type="password" name="password" required placeholder="Enter password" class="w-full pl-11 pr-4 py-3 bg-white border border-slate-300 text-slate-800 focus:outline-none focus:border-corporate-dark focus:ring-1 focus:ring-corporate-dark transition-all font-medium rounded-none">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-blue-600 hover:bg-blue-700 text-white py-4 font-bold text-sm uppercase tracking-widest transition-all shadow-lg shadow-blue-600/30 flex items-center justify-center">
                    Login <i class="fas fa-arrow-right ml-3"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- ปุ่ม Floating LINE มุมขวาล่าง -->
    <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="fixed bottom-6 right-6 z-40 bg-[#00B900] hover:bg-[#009900] text-white w-14 h-14 md:w-auto md:h-auto md:px-5 md:py-3.5 rounded-full md:rounded-none font-bold text-sm md:text-base shadow-xl shadow-green-500/40 transition-all transform hover:-translate-y-1 flex items-center justify-center group">
        <i class="fab fa-line text-3xl md:mr-2 group-hover:scale-110 transition-transform"></i> 
        <span class="hidden md:inline uppercase tracking-wider text-xs">Add Line</span>
    </a>

    <!-- Script สำหรับควบคุม Modal -->
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
                title: 'Login Failed',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#0b1f4a',
                confirmButtonText: 'Try Again'
            }).then(() => {
                toggleModal('loginModal');
            });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Data Not Found',
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบอีกครั้ง',
                confirmButtonColor: '#2563eb'
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