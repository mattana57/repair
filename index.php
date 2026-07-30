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

// ================= 3. ดึงสถิติมาแสดงโชว์หน้าเว็บ =================
$c_pending = 0; $c_progress = 0; $c_completed = 0;
$resStats = @$conn->query("SELECT status, COUNT(*) as cnt FROM repairs GROUP BY status");
if($resStats) {
    while($row = $resStats->fetch_assoc()) {
        if($row['status'] == 'รอรับเรื่อง') $c_pending = $row['cnt'];
        if($row['status'] == 'กำลังดำเนินการ') $c_progress = $row['cnt'];
        if($row['status'] == 'ซ่อมเสร็จแล้ว') $c_completed = $row['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS REPAIR | คณะการบัญชีและการจัดการ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #f8fafc; /* พื้นหลังสีเทาอ่อนด้านล่าง */
            color: #1e293b;
        }
        
        /* สร้างคลาส Gradient สำหรับพื้นหลังสีน้ำเงินเข้มแบบในภาพเรฟ */
        .bg-hero-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }
        .bg-workflow-gradient {
            background: linear-gradient(180deg, #1e3a8a 0%, #172554 100%);
        }

        .modal { transition: opacity 0.2s ease, visibility 0.2s ease; }
        body.modal-active { overflow: hidden; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-600 selection:text-white">

    <!-- ================== ส่วนบน (Hero & Search) ================== -->
    <div class="bg-hero-gradient pb-32 relative">
        
        <!-- Navbar แบบใส -->
        <header class="w-full relative z-40 py-6">
            <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
                <!-- โลโก้ -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-full text-blue-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="text-white">
                        <h1 class="text-lg font-bold leading-none tracking-wide">MBS REPAIR</h1>
                        <span class="text-[10px] font-medium opacity-80 uppercase tracking-widest">Mahasarakham University</span>
                    </div>
                </div>
                
                <!-- เมนู Navigation -->
                <nav class="hidden md:flex items-center gap-8 text-white/90 text-sm font-medium">
                    <a href="#" class="hover:text-white transition-colors flex items-center gap-2"><i class="fas fa-home"></i> หน้าแรก</a>
                    <a href="#workflow" class="hover:text-white transition-colors flex items-center gap-2"><i class="fas fa-list-ol"></i> ขั้นตอนการทำงาน</a>
                    <a href="#developers" class="hover:text-white transition-colors flex items-center gap-2"><i class="fas fa-users"></i> ผู้พัฒนา</a>
                </nav>

                <!-- ปุ่ม Login เจ้าหน้าที่ -->
                <button onclick="toggleModal('loginModal')" class="bg-white/10 hover:bg-white text-white hover:text-blue-700 border border-white/20 px-5 py-2.5 rounded-full text-sm font-medium transition-all flex items-center gap-2 backdrop-blur-sm">
                    <i class="fas fa-lock"></i> <span class="hidden sm:inline">สำหรับเจ้าหน้าที่</span>
                </button>
            </div>
        </header>

        <!-- ข้อความ Hero Text -->
        <main class="max-w-4xl mx-auto px-6 pt-16 pb-8 text-center text-white relative z-10">
            <h2 class="text-4xl md:text-5xl lg:text-[3.5rem] font-extrabold tracking-tight mb-6 leading-tight drop-shadow-lg">
                แจ้งซ่อมอุปกรณ์และติดตามสถานะ<br>ได้อย่างสะดวกและรวดเร็ว
            </h2>
            <p class="text-white/80 text-sm md:text-base max-w-2xl mx-auto mb-10 font-light">
                บริการรับแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า อาคารสถานที่ และอุปกรณ์ในห้องเรียน สำหรับบุคลากรและนิสิต MBS
            </p>

            <!-- ปุ่ม Action 2 ปุ่ม -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-8">
                <a href="form_repair.php" class="w-full sm:w-auto bg-blue-500 hover:bg-blue-400 text-white px-8 py-4 rounded-xl font-bold shadow-lg shadow-blue-500/30 transition-transform hover:-translate-y-1 flex items-center justify-center gap-3">
                    <i class="fas fa-file-alt text-lg"></i> กรอกแบบฟอร์มแจ้งซ่อมใหม่
                </a>
                <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="w-full sm:w-auto bg-[#00B900] hover:bg-[#00a000] text-white px-8 py-4 rounded-xl font-bold shadow-lg shadow-[#00B900]/30 transition-transform hover:-translate-y-1 flex items-center justify-center gap-3">
                    <i class="fab fa-line text-xl"></i> ติดต่อผ่าน LINE Official
                </a>
            </div>
        </main>
    </div>

    <!-- ================== การ์ดค้นหา & สถิติ (ลอยทับพื้นหลัง) ================== -->
    <div class="max-w-5xl mx-auto px-6 w-full relative z-20 -mt-24 mb-16">
        
        <!-- กล่อง Search สีขาว -->
        <div class="bg-white rounded-3xl p-6 md:p-8 shadow-[0_20px_50px_rgb(0,0,0,0.1)] mb-6 border border-slate-100">
            <div class="flex justify-between items-center mb-4 px-2">
                <h3 class="text-slate-800 font-bold flex items-center gap-2">
                    <i class="fas fa-search text-blue-600"></i> ค้นหาประวัติ / ตรวจสอบสถานะการแจ้งซ่อม
                </h3>
                <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full hidden sm:inline-block border border-blue-100">Real-time Search</span>
            </div>
            
            <form action="" method="POST" class="flex flex-col md:flex-row gap-3">
                <input type="hidden" name="check_status" value="1">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <input type="text" name="search_query" required placeholder="กรอกเลขใบงาน (เช่น MR-001) หรือชื่อผู้แจ้ง..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl pl-12 pr-4 py-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all font-medium placeholder-slate-400">
                </div>
                <button type="submit" class="bg-slate-900 hover:bg-black text-white px-8 py-4 rounded-xl font-bold transition-all shadow-md flex items-center justify-center gap-2 whitespace-nowrap">
                    <i class="fas fa-search text-sm"></i> ตรวจสอบสถานะ
                </button>
            </form>
        </div>

        <!-- กล่องสถิติย่อย 3 กล่อง -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition-shadow cursor-default">
                <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-lg"><i class="fas fa-clock"></i></div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">รอรับเรื่อง</p>
                    <div class="flex items-baseline gap-1"><span class="text-2xl font-black text-slate-800 leading-none"><?php echo $c_pending; ?></span><span class="text-xs font-medium text-slate-500">รายการ</span></div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition-shadow cursor-default">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-lg"><i class="fas fa-tools"></i></div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">กำลังดำเนินการ</p>
                    <div class="flex items-baseline gap-1"><span class="text-2xl font-black text-slate-800 leading-none"><?php echo $c_progress; ?></span><span class="text-xs font-medium text-slate-500">รายการ</span></div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4 hover:shadow-md transition-shadow cursor-default">
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-lg"><i class="fas fa-check-circle"></i></div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">ซ่อมเสร็จแล้ว</p>
                    <div class="flex items-baseline gap-1"><span class="text-2xl font-black text-slate-800 leading-none"><?php echo $c_completed; ?></span><span class="text-xs font-medium text-slate-500">รายการ</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================== ส่วน Workflow ================== -->
    <section id="workflow" class="bg-workflow-gradient py-20 mt-10">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12">
                <p class="text-blue-300 font-bold text-xs uppercase tracking-widest mb-2"><i class="fas fa-sync-alt mr-1"></i> Workflow Process</p>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-3">ขั้นตอนการแจ้งซ่อมง่ายๆ ใน 4 ขั้นตอน</h2>
                <p class="text-white/70 text-sm">ติดตามเรื่องซ่อมสะดวกรวดเร็ว แม่นยำทุกขั้นตอน</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="bg-white/10 border border-white/10 rounded-3xl p-6 backdrop-blur-sm relative overflow-hidden group hover:bg-white/15 transition-colors">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-white font-black text-xl mb-6 shadow-lg">01</div>
                    <i class="fas fa-edit absolute top-6 right-6 text-3xl text-white/20 group-hover:text-white/30 transition-colors"></i>
                    <h3 class="text-white font-bold text-lg mb-2">1. แจ้งปัญหา</h3>
                    <p class="text-white/60 text-xs leading-relaxed">ผู้ใช้งานกรอกแบบฟอร์มแจ้งซ่อมผ่านระบบ ระบุรายละเอียดและรูปภาพ</p>
                </div>
                <!-- Step 2 -->
                <div class="bg-white/10 border border-white/10 rounded-3xl p-6 backdrop-blur-sm relative overflow-hidden group hover:bg-white/15 transition-colors">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-white font-black text-xl mb-6 shadow-lg">02</div>
                    <i class="fas fa-user-check absolute top-6 right-6 text-3xl text-white/20 group-hover:text-white/30 transition-colors"></i>
                    <h3 class="text-white font-bold text-lg mb-2">2. เจ้าหน้าที่รับเรื่อง</h3>
                    <p class="text-white/60 text-xs leading-relaxed">ทีมช่างตรวจสอบข้อมูลและมอบหมายผู้รับผิดชอบงานซ่อม</p>
                </div>
                <!-- Step 3 -->
                <div class="bg-white/10 border border-white/10 rounded-3xl p-6 backdrop-blur-sm relative overflow-hidden group hover:bg-white/15 transition-colors">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-white font-black text-xl mb-6 shadow-lg">03</div>
                    <i class="fas fa-wrench absolute top-6 right-6 text-3xl text-white/20 group-hover:text-white/30 transition-colors"></i>
                    <h3 class="text-white font-bold text-lg mb-2">3. ดำเนินการซ่อม</h3>
                    <p class="text-white/60 text-xs leading-relaxed">ช่างผู้เชี่ยวชาญเข้าแก้ไขตามจุดที่ได้รับแจ้งอย่างรวดเร็ว</p>
                </div>
                <!-- Step 4 -->
                <div class="bg-white/10 border border-white/10 rounded-3xl p-6 backdrop-blur-sm relative overflow-hidden group hover:bg-white/15 transition-colors">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-white font-black text-xl mb-6 shadow-lg">04</div>
                    <i class="fas fa-check-circle absolute top-6 right-6 text-3xl text-white/20 group-hover:text-white/30 transition-colors"></i>
                    <h3 class="text-white font-bold text-lg mb-2">4. เสร็จสิ้น</h3>
                    <p class="text-white/60 text-xs leading-relaxed">รับอุปกรณ์กลับมาใช้งานได้ตามปกติ ระบบแจ้งเตือนเมื่อเสร็จสิ้น</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================== ส่วนผู้พัฒนาโครงการ ================== -->
    <section id="developers" class="py-20 bg-white">
        <div class="max-w-5xl mx-auto px-6">
            <div class="text-center mb-10">
                <p class="text-blue-600 font-bold text-xs uppercase tracking-widest mb-2"><i class="fas fa-code mr-1"></i> ผู้พัฒนาโครงการ (Project Developers)</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border border-slate-100 rounded-3xl p-6 flex items-center gap-5 shadow-sm hover:shadow-md transition-shadow bg-slate-50/50">
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h4 class="text-slate-800 font-bold text-lg">นางสาวภัทรวดี ขามประโคน</h4>
                        <p class="text-slate-500 text-sm font-medium">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                    </div>
                </div>
                <div class="border border-slate-100 rounded-3xl p-6 flex items-center gap-5 shadow-sm hover:shadow-md transition-shadow bg-slate-50/50">
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-xl shrink-0"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h4 class="text-slate-800 font-bold text-lg">นางสาวมัทนา รัตนแสง</h4>
                        <p class="text-slate-500 text-sm font-medium">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="w-full border-t border-slate-200 py-8 bg-[#f8fafc]">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <p class="text-sm font-medium text-slate-500">
                &copy; <?php echo date('Y'); ?> MBS REPAIR. Faculty of Accountancy and Management. Mahasarakham University.
            </p>
        </div>
    </footer>


    <!-- ================== Modal: แสดงผลการค้นหาใบงาน ================== -->
    <div id="resultModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-50 px-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white w-full max-w-3xl rounded-3xl shadow-2xl z-50 flex flex-col max-h-[85vh] transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="resultModalContent">
            
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">ผลการค้นหา</h2>
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
                            $statusClass = "bg-amber-100 text-amber-800";
                            $icon = "fa-clock text-amber-600";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $statusClass = "bg-blue-100 text-blue-800";
                            $icon = "fa-tools text-blue-600";
                            $res['status'] = 'ช่างรับเรื่องแจ้งซ่อมแล้ว';
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $statusClass = "bg-emerald-100 text-emerald-800";
                            $icon = "fa-check-circle text-emerald-600";
                        }
                    ?>
                        <a href="view_repair.php?id=<?php echo $res['id']; ?>" target="_blank" class="block bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 pb-4 border-b border-slate-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-lg">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-0.5">Ticket No.</p>
                                        <h3 class="text-lg font-bold text-slate-900"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p class="text-xs font-medium text-slate-500 mb-1">อุปกรณ์</p>
                                    <p class="font-semibold text-slate-900 truncate"><?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-slate-500 mb-1">ผู้แจ้ง</p>
                                    <p class="font-semibold text-slate-900 truncate"><?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-slate-500 mb-1">ผู้รับผิดชอบ</p>
                                    <p class="font-semibold <?php echo !empty($res['technician_name']) ? 'text-blue-600' : 'text-slate-400'; ?>">
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-slate-500 mb-1">วันที่แจ้ง</p>
                                    <p class="font-semibold text-slate-900"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-6 border-t border-slate-100 bg-white text-right">
                <button onclick="toggleModal('resultModal')" class="bg-slate-100 text-slate-700 rounded-xl px-6 py-2.5 text-sm font-bold hover:bg-slate-200 transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ================== Modal: เข้าสู่ระบบเจ้าหน้าที่ ================== -->
    <div id="loginModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-50 px-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        
        <!-- Modal Content -->
        <div class="relative bg-white/90 backdrop-blur-xl w-full max-w-md rounded-[2.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-white z-50 flex flex-col transform transition-all duration-300 scale-95 data-[open=true]:scale-100" id="loginModalContent">
            
            <div class="absolute top-0 -left-10 w-40 h-40 bg-purple-300 rounded-full mix-blend-multiply filter blur-2xl opacity-50 animate-blob pointer-events-none"></div>
            <div class="absolute top-0 -right-10 w-40 h-40 bg-blue-300 rounded-full mix-blend-multiply filter blur-2xl opacity-50 animate-blob animation-delay-2000 pointer-events-none"></div>

            <div class="px-8 pt-10 pb-6 text-center relative z-10">
                <button onclick="toggleModal('loginModal')" class="absolute top-6 right-6 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-blue-600 text-white rounded-2xl flex items-center justify-center text-3xl mx-auto mb-5 shadow-lg shadow-blue-500/30 transform transition-transform hover:scale-110 hover:rotate-3 duration-300">
                    <i class="fas fa-fingerprint"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">เข้าสู่ระบบเจ้าหน้าที่</h2>
                <p class="text-sm text-slate-500 mt-1 font-medium">สำหรับผู้บริหาร และช่างซ่อมบำรุง</p>
            </div>

            <form action="" method="POST" class="p-8 pt-0 relative z-10">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Username</label>
                        <div class="relative group">
                            <input type="text" name="username" required placeholder="ระบุชื่อผู้ใช้งาน" class="peer w-full bg-slate-50/50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-indigo-600 transition-colors">
                                <i class="fas fa-at text-sm"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2">Password</label>
                        <div class="relative group">
                            <input type="password" id="modalPassword" name="password" required placeholder="ระบุรหัสผ่าน" class="peer w-full bg-slate-50/50 border border-slate-200 rounded-2xl pl-12 pr-12 py-3.5 text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 peer-focus:text-indigo-600 transition-colors">
                                <i class="fas fa-key text-sm"></i>
                            </div>
                            <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-600 focus:outline-none transition-colors" onclick="toggleModalPassword()">
                                <i id="modalEyeIcon" class="fas fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="relative overflow-hidden w-full mt-8 bg-gradient-to-r from-indigo-600 to-blue-500 text-white font-bold text-sm py-4 rounded-2xl shadow-lg shadow-indigo-500/30 transform transition-all hover:-translate-y-0.5 hover:shadow-indigo-500/50 active:scale-95 group">
                    <span class="relative z-10 flex items-center justify-center gap-2">
                        เข้าสู่ระบบ <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </span>
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
                title: 'ไม่สามารถเข้าสู่ระบบได้',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#1e293b',
                customClass: { popup: 'rounded-3xl shadow-xl' }
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
                confirmButtonColor: '#2563EB',
                customClass: { popup: 'rounded-3xl shadow-xl' }
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