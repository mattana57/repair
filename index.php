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
}

// ================= 3. ดึงสถิติการแจ้งซ่อม (สำหรับโชว์บนการ์ด) =================
$stats = ['รอรับเรื่อง' => 0, 'กำลังดำเนินการ' => 0, 'ซ่อมเสร็จแล้ว' => 0];
$res_stats = $conn->query("SELECT status, COUNT(*) as cnt FROM repairs GROUP BY status");
if ($res_stats) {
    while ($row = $res_stats->fetch_assoc()) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['cnt'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS REPAIR | แจ้งซ่อมและติดตามสถานะ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #0c1222; /* สีพื้นหลังหลักแบบเข้ม */
            color: #ffffff;
            overflow-x: hidden;
        }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        body.modal-active { overflow: hidden; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0c1222; }
        ::-webkit-scrollbar-thumb { background: #1e3a8a; border-radius: 10px; }
        
        /* Gradient ล้ำๆ สำหรับ Hero Section */
        .hero-gradient {
            background: radial-gradient(circle at top center, #1e3a8a 0%, #0c1222 70%);
        }
    </style>
</head>
<body class="min-h-screen selection:bg-blue-500 selection:text-white">

    <!-- ================= Navbar (แถบเมนูสีขาว) ================= -->
    <header class="w-full bg-white text-slate-800 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-[72px] flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-600 rounded-lg text-white flex items-center justify-center shadow-md">
                    <i class="fas fa-tools text-sm"></i>
                </div>
                <div>
                    <h1 class="text-[17px] font-bold leading-none tracking-tight">MBS REPAIR</h1>
                    <span class="text-[9px] font-semibold text-slate-500 uppercase tracking-widest">มหาวิทยาลัยมหาสารคาม</span>
                </div>
            </div>
            
            <!-- Menu Center -->
            <nav class="hidden md:flex items-center gap-8 font-semibold text-sm">
                <a href="#home" class="text-blue-600 flex items-center gap-2 hover:text-blue-700 transition"><i class="fas fa-home"></i> หน้าแรก</a>
                <a href="#workflow" class="text-slate-500 flex items-center gap-2 hover:text-blue-600 transition"><i class="fas fa-list-ul"></i> ขั้นตอนการทำงาน</a>
                <a href="#developers" class="text-slate-500 flex items-center gap-2 hover:text-blue-600 transition"><i class="fas fa-users"></i> ผู้พัฒนา</a>
            </nav>

            <!-- Login Right -->
            <button onclick="toggleModal('loginModal')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-full text-xs font-bold transition-colors flex items-center gap-2">
                <i class="fas fa-lock text-[10px]"></i> เจ้าหน้าที่
            </button>
        </div>
    </header>

    <!-- ================= Hero Section ================= -->
    <section id="home" class="w-full hero-gradient pt-24 pb-48 relative">
        <div class="max-w-4xl mx-auto px-4 text-center z-10 relative">
            
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight leading-[1.2] mb-6 drop-shadow-lg">
                แจ้งซ่อมอุปกรณ์และติดตามสถานะ<br>ได้อย่างสะดวกรวดเร็ว
            </h1>
            
            <p class="text-blue-100 text-sm md:text-base font-medium max-w-2xl mx-auto mb-10 opacity-90">
                บริการรับแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า อาคารสถานที่ และอุปกรณ์ในห้องเรียน สำหรับบุคลากรและนิสิต MBS
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="form_repair.php" class="w-full sm:w-auto bg-[#3b82f6] hover:bg-blue-500 text-white px-8 py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-3 shadow-[0_8px_20px_rgb(59,130,246,0.4)] transition-transform hover:-translate-y-1">
                    <i class="fas fa-file-alt text-lg"></i> กรอกแบบฟอร์มแจ้งซ่อมใหม่
                </a>
                <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="w-full sm:w-auto bg-[#10B981] hover:bg-emerald-400 text-white px-8 py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-3 shadow-[0_8px_20px_rgb(16,185,129,0.4)] transition-transform hover:-translate-y-1">
                    <i class="fab fa-line text-xl"></i> ติดต่อผ่าน LINE Official
                </a>
            </div>
        </div>
    </section>

    <!-- ================= Search & Stats Section (ลอยทับ Hero) ================= -->
    <section class="w-full relative z-20 px-4 -mt-28 mb-20">
        <div class="max-w-4xl mx-auto">
            
            <!-- กล่องค้นหา (Search Card) -->
            <div class="bg-white rounded-3xl p-6 md:p-8 shadow-2xl border border-slate-100 mb-8">
                <div class="flex justify-between items-center mb-5">
                    <div class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-search text-[#3b82f6]"></i> ค้นหาประวัติ / ตรวจสอบสถานะการแจ้งซ่อม
                    </div>
                    <div class="bg-blue-50 text-blue-600 text-[10px] font-bold px-3 py-1 rounded-full border border-blue-100">
                        Real-time Search
                    </div>
                </div>
                
                <form action="" method="POST" class="flex flex-col md:flex-row gap-3">
                    <input type="hidden" name="check_status" value="1">
                    <div class="flex-1 bg-slate-50 rounded-2xl flex items-center px-5 border border-slate-200 focus-within:border-blue-400 focus-within:ring-4 ring-blue-50 transition-all group">
                        <i class="fas fa-ticket-alt text-slate-400 mr-3 group-focus-within:text-blue-500"></i>
                        <input type="text" name="search_query" required placeholder="กรอกเลขใบงาน (เช่น MR-001) หรือชื่อผู้แจ้ง..." class="w-full bg-transparent border-none py-4 focus:outline-none text-sm font-medium text-slate-800 placeholder-slate-400">
                    </div>
                    <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white px-8 py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 shadow-lg transition-transform hover:-translate-y-0.5 whitespace-nowrap">
                        <i class="fas fa-search"></i> ตรวจสอบสถานะ
                    </button>
                </form>
            </div>

            <!-- การ์ดสถานะ (Stats Cards) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- รอรับเรื่อง -->
                <div class="bg-white rounded-[1.5rem] p-5 shadow-lg border border-slate-100 flex items-center gap-4 hover:shadow-xl transition-shadow cursor-default">
                    <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 text-lg shrink-0">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-500 mb-0.5">รอรับเรื่อง</p>
                        <p class="text-2xl font-extrabold text-slate-800 leading-none"><?= $stats['รอรับเรื่อง'] ?> <span class="text-xs font-medium text-slate-500 ml-1">รายการ</span></p>
                    </div>
                </div>
                <!-- กำลังดำเนินการ -->
                <div class="bg-white rounded-[1.5rem] p-5 shadow-lg border border-slate-100 flex items-center gap-4 hover:shadow-xl transition-shadow cursor-default">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-lg shrink-0">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-500 mb-0.5">กำลังดำเนินการ</p>
                        <p class="text-2xl font-extrabold text-slate-800 leading-none"><?= $stats['กำลังดำเนินการ'] ?> <span class="text-xs font-medium text-slate-500 ml-1">รายการ</span></p>
                    </div>
                </div>
                <!-- ซ่อมเสร็จแล้ว -->
                <div class="bg-white rounded-[1.5rem] p-5 shadow-lg border border-slate-100 flex items-center gap-4 hover:shadow-xl transition-shadow cursor-default">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-lg shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-500 mb-0.5">ซ่อมเสร็จแล้ว</p>
                        <p class="text-2xl font-extrabold text-slate-800 leading-none"><?= $stats['ซ่อมเสร็จแล้ว'] ?> <span class="text-xs font-medium text-slate-500 ml-1">รายการ</span></p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ================= Workflow Section ================= -->
    <section id="workflow" class="w-full bg-[#0c1222] py-20 border-t border-white/5">
        <div class="max-w-6xl mx-auto px-4">
            
            <div class="text-center mb-12">
                <div class="inline-flex items-center gap-2 text-[10px] font-bold text-blue-400 tracking-[0.2em] mb-3 uppercase">
                    <i class="fas fa-rocket"></i> Workflow Process
                </div>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4 tracking-tight">ขั้นตอนการแจ้งซ่อมง่ายๆ ใน 4 ขั้นตอน</h2>
                <p class="text-slate-400 text-sm font-medium">ติดตามเรื่องซ่อมสะดวกรวดเร็ว แม่นยำทุกขั้นตอน</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="bg-[#182142] rounded-3xl p-8 border border-white/5 relative overflow-hidden group hover:bg-[#1e2954] transition-colors">
                    <div class="flex justify-between items-start mb-12">
                        <div class="bg-amber-500 text-white text-sm font-black px-3 py-1.5 rounded-lg shadow-lg">01</div>
                        <i class="fas fa-edit text-3xl text-blue-400 opacity-80 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">1. แจ้งซ่อมระบบ</h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-medium">กรอกแบบฟอร์มแจ้งรายละเอียดปัญหา ระบุสถานที่ และเบอร์ติดต่อกลับ</p>
                </div>
                <!-- Step 2 -->
                <div class="bg-[#182142] rounded-3xl p-8 border border-white/5 relative overflow-hidden group hover:bg-[#1e2954] transition-colors">
                    <div class="flex justify-between items-start mb-12">
                        <div class="bg-amber-500 text-white text-sm font-black px-3 py-1.5 rounded-lg shadow-lg">02</div>
                        <i class="fas fa-user-check text-3xl text-amber-400 opacity-80 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">2. เจ้าหน้าที่รับเรื่อง</h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-medium">ทีมช่างตรวจสอบข้อมูลและมอบหมายผู้รับผิดชอบงานซ่อม</p>
                </div>
                <!-- Step 3 -->
                <div class="bg-[#182142] rounded-3xl p-8 border border-white/5 relative overflow-hidden group hover:bg-[#1e2954] transition-colors">
                    <div class="flex justify-between items-start mb-12">
                        <div class="bg-amber-500 text-white text-sm font-black px-3 py-1.5 rounded-lg shadow-lg">03</div>
                        <i class="fas fa-wrench text-3xl text-sky-400 opacity-80 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">3. ดำเนินการซ่อม</h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-medium">ช่างผู้เชี่ยวชาญเข้าแก้ไขตามจุดที่ได้รับแจ้งอย่างรวดเร็ว</p>
                </div>
                <!-- Step 4 -->
                <div class="bg-[#182142] rounded-3xl p-8 border border-white/5 relative overflow-hidden group hover:bg-[#1e2954] transition-colors">
                    <div class="flex justify-between items-start mb-12">
                        <div class="bg-amber-500 text-white text-sm font-black px-3 py-1.5 rounded-lg shadow-lg">04</div>
                        <i class="fas fa-check-circle text-3xl text-emerald-400 opacity-80 group-hover:scale-110 transition-transform"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">4. เสร็จสิ้น</h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-medium">รับอุปกรณ์กลับไปใช้งานตามปกติ พร้อมระบบแจ้งเตือนเมื่อเสร็จสิ้น</p>
                </div>
            </div>

        </div>
    </section>

    <!-- ================= Developers Section ================= -->
    <section id="developers" class="w-full bg-white text-slate-800 py-20 rounded-t-[3rem]">
        <div class="max-w-4xl mx-auto px-4">
            
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 text-[10px] font-bold text-blue-600 tracking-[0.2em] mb-2 uppercase">
                    <i class="fas fa-code"></i> Project Developers
                </div>
                <h2 class="text-2xl font-extrabold text-slate-800">ผู้พัฒนาโครงการ</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Dev 1 -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 flex items-center gap-5 hover:shadow-lg transition-shadow">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-slate-900">นางสาวภัทรวดี ขามประโคน</h4>
                        <p class="text-xs font-medium text-slate-500">นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ (BC)</p>
                    </div>
                </div>
                <!-- Dev 2 -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 flex items-center gap-5 hover:shadow-lg transition-shadow">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-slate-900">นางสาวมัทนา รัตนแสง</h4>
                        <p class="text-xs font-medium text-slate-500">นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ (BC)</p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-12 pt-8 border-t border-slate-100">
                <p class="text-xs font-medium text-slate-400">&copy; <?php echo date('Y'); ?> คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
            </div>

        </div>
    </section>

    <!-- ==============================================
         Modal: แสดงผลการค้นหาใบงาน
         ============================================== -->
    <div id="resultModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white w-full max-w-3xl rounded-[2rem] shadow-2xl z-50 flex flex-col max-h-[85vh] transform transition-transform duration-300 scale-95 data-[open=true]:scale-100 text-slate-800" id="resultModalContent">
            
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900">ผลการค้นหา</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">คำค้นหา: <span class="text-blue-600 font-bold">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-slate-50 space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-700"; 
                        $icon = "fa-file-alt text-slate-400";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $statusClass = "bg-amber-100 text-amber-800 border-amber-200";
                            $icon = "fa-clock text-amber-500";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $statusClass = "bg-blue-100 text-blue-800 border-blue-200";
                            $icon = "fa-tools text-blue-500";
                            $res['status'] = 'ช่างรับเรื่องแจ้งซ่อมแล้ว';
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $statusClass = "bg-emerald-100 text-emerald-800 border-emerald-200";
                            $icon = "fa-check-circle text-emerald-500";
                        }
                    ?>
                        <a href="view_repair.php?id=<?php echo $res['id']; ?>" target="_blank" class="block bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 pb-4 border-b border-slate-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-lg shadow-inner">
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

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">อุปกรณ์</p>
                                    <p class="font-bold text-slate-800 truncate"><?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ผู้แจ้ง</p>
                                    <p class="font-bold text-slate-800 truncate"><?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ผู้รับผิดชอบ</p>
                                    <p class="font-bold <?php echo !empty($res['technician_name']) ? 'text-blue-600' : 'text-slate-400'; ?>">
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">วันที่แจ้ง</p>
                                    <p class="font-bold text-slate-800"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-5 border-t border-slate-100 bg-white text-center rounded-b-[2rem]">
                <button onclick="toggleModal('resultModal')" class="bg-slate-900 text-white rounded-xl px-10 py-3 text-sm font-bold hover:bg-slate-800 transition-colors shadow-md">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ==============================================
         Modal: เข้าสู่ระบบเจ้าหน้าที่
         ============================================== -->
    <div id="loginModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl z-50 flex flex-col transform transition-transform duration-300 scale-95 data-[open=true]:scale-100 text-slate-800" id="loginModalContent">
            
            <div class="px-8 pt-10 pb-6 text-center relative border-b border-slate-100">
                <button onclick="toggleModal('loginModal')" class="absolute top-6 right-6 w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4 border border-blue-100 shadow-sm">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
                <p class="text-sm text-slate-500 mt-1 font-medium">สำหรับผู้บริหาร และช่างซ่อมบำรุง</p>
            </div>

            <form action="" method="POST" class="p-8 bg-slate-50/50 rounded-b-[2.5rem]">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="relative group">
                            <input type="text" name="username" required placeholder="ระบุชื่อผู้ใช้งาน" class="peer w-full bg-white border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-blue-500 transition-colors">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">รหัสผ่าน (Password)</label>
                        <div class="relative group">
                            <input type="password" id="modalPassword" name="password" required placeholder="ระบุรหัสผ่าน" class="peer w-full bg-white border border-slate-200 rounded-2xl pl-12 pr-12 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-blue-500 transition-colors">
                                <i class="fas fa-lock text-sm"></i>
                            </div>
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-blue-600 focus:outline-none transition-colors" onclick="toggleModalPassword()">
                                <i id="modalEyeIcon" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-[#0f172a] text-white rounded-2xl py-4 font-bold text-sm hover:bg-blue-600 shadow-lg hover:shadow-blue-500/30 transform transition-all hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-2">
                    เข้าสู่ระบบ <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleModal(modalID) { 
            const modal = document.getElementById(modalID);
            const content = document.getElementById(modalID + 'Content');
            
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