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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแจ้งซ่อม คณะการบัญชีและการจัดการ มมส.</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #d1d5db; /* สีเทาอ่อนเรียบๆ ตัดกับกรอบขาว */
            background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
            overflow-x: hidden; 
        }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        body.modal-active { overflow: hidden; }
        
        /* สกอร์บาร์ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.3); }

        /* การ์ดกระจก (Glassmorphism) */
        .glass-card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-0 md:p-6 lg:p-10 selection:bg-indigo-500 selection:text-white">

    <!-- กรอบแอปพลิเคชันหลัก (App Window) -->
    <div class="w-full max-w-[1400px] h-[100vh] md:h-[90vh] md:min-h-[700px] bg-white md:rounded-[3rem] shadow-2xl flex flex-col md:flex-row overflow-hidden relative">

        <!-- Sidebar แนวตั้ง (สำหรับ Tablet & Desktop) -->
        <aside class="hidden md:flex w-24 border-r border-slate-100 flex-col items-center py-8 gap-8 shrink-0 z-20 bg-white">
            <!-- Logo Icon -->
            <div class="w-12 h-12 bg-indigo-600 rounded-full text-white flex items-center justify-center shadow-lg shadow-indigo-200">
                <i class="fas fa-tools text-xl"></i>
            </div>
            
            <!-- Navigation Icons -->
            <nav class="flex flex-col gap-6 w-full items-center mt-4">
                <a href="#" class="w-12 h-12 rounded-full bg-slate-100 text-indigo-600 flex items-center justify-center relative group shadow-inner">
                    <i class="fas fa-home text-lg"></i>
                    <span class="absolute left-16 bg-slate-800 text-white text-xs px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-lg z-50">หน้าหลัก</span>
                </a>
                <a href="form_repair.php" class="w-12 h-12 rounded-full text-slate-400 hover:bg-slate-50 hover:text-indigo-600 flex items-center justify-center transition-all relative group">
                    <i class="fas fa-plus text-lg"></i>
                    <span class="absolute left-16 bg-slate-800 text-white text-xs px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-lg z-50">แจ้งซ่อมใหม่ (สำหรับบุคลากร)</span>
                </a>
                <button onclick="toggleModal('loginModal')" class="w-12 h-12 rounded-full text-slate-400 hover:bg-slate-50 hover:text-indigo-600 flex items-center justify-center transition-all relative group">
                    <i class="fas fa-user-lock text-lg"></i>
                    <span class="absolute left-16 bg-slate-800 text-white text-xs px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-lg z-50">เข้าสู่ระบบ (เจ้าหน้าที่)</span>
                </button>
            </nav>

            <!-- Bottom Line Icon -->
            <div class="mt-auto">
                <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="w-12 h-12 rounded-full bg-[#00B900]/10 text-[#00B900] flex items-center justify-center hover:bg-[#00B900] hover:text-white transition-all relative group">
                    <i class="fab fa-line text-2xl"></i>
                    <span class="absolute left-16 bg-slate-800 text-white text-xs px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-lg z-50">ติดต่อ LINE Bot</span>
                </a>
            </div>
        </aside>

        <!-- พื้นที่เนื้อหาหลัก (Main Content) -->
        <main class="flex-1 p-3 md:p-6 flex flex-col relative z-10 h-full overflow-hidden bg-white pb-24 md:pb-6">
            
            <!-- Header ด้านบน (เหมือนแถบ Filter) -->
            <header class="flex justify-between items-center mb-4 px-2 mt-2 md:mt-0">
                <div class="flex items-center gap-4 bg-slate-50 rounded-full p-1.5 pr-6 border border-slate-100 shadow-sm">
                    <div class="bg-white rounded-full px-5 py-2 shadow-sm text-sm font-bold text-slate-800">
                        MBS REPAIR
                    </div>
                    <span class="text-xs font-medium text-slate-500 hidden sm:block">Faculty of Accountancy and Management</span>
                </div>
                <div class="hidden sm:flex items-center gap-2 bg-slate-50 rounded-full px-5 py-2.5 border border-slate-100 shadow-sm">
                    <i class="far fa-clock text-indigo-500"></i>
                    <span class="text-xs font-semibold text-slate-600 tracking-wide uppercase">24/7 Service</span>
                </div>
            </header>

            <!-- รูปภาพหลัก (Hero Image) แบบขอบมน -->
            <div class="flex-1 rounded-[2rem] relative overflow-hidden bg-slate-900 group shadow-inner">
                <!-- ใช้ภาพพื้นหลัง (เปลี่ยนชื่อไฟล์ตามที่มีได้เลยค่ะ) -->
                <img src="uploads/mbs_bg.jpg?v=1" onerror="this.src='https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?q=80&w=2070&auto=format&fit=crop'" class="absolute inset-0 w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-[2s] ease-in-out">
                
                <!-- การไล่สีให้ตัวหนังสืออ่านง่าย -->
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/30 to-slate-900/10"></div>

                <!-- ข้อความทักทาย -->
                <div class="absolute top-8 left-6 md:top-14 md:left-12 max-w-xl z-10">
                    <h1 class="text-4xl md:text-6xl font-semibold text-white leading-[1.1] tracking-tight mb-3 drop-shadow-lg">
                        New Way Of<br>Maintenance
                    </h1>
                    <p class="text-white/90 text-sm md:text-base font-light max-w-md leading-relaxed hidden sm:block drop-shadow-md">
                        ยกระดับการให้บริการด้านอาคารสถานที่และไอที คณะบัญชีฯ มมส. ด้วยระบบแจ้งซ่อมออนไลน์ที่รวดเร็วและตรวจสอบได้
                    </p>
                </div>

                <!-- การ์ดค้นหา (ไฮไลท์ของหน้านี้ ให้คนกดใช้ง่ายสุดๆ) -->
                <div class="absolute bottom-6 left-1/2 -translate-x-1/2 md:translate-x-0 md:left-12 w-[90%] md:w-[420px] glass-card rounded-[2rem] p-6 md:p-8 shadow-2xl z-20">
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-1 drop-shadow">ติดตามสถานะใบงาน</h2>
                    <p class="text-xs md:text-sm text-white/80 mb-6 drop-shadow-sm">กรอกรหัส หรือชื่อผู้แจ้ง เพื่อตรวจสอบความคืบหน้า</p>

                    <form action="" method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="check_status" value="1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                                <i class="fas fa-search text-slate-400"></i>
                            </div>
                            <input type="text" name="search_query" required placeholder="เช่น MR-2026..." class="w-full pl-12 pr-4 py-4 bg-white/95 rounded-2xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-indigo-500/50 shadow-inner transition-all">
                        </div>
                        <button type="submit" class="w-full bg-indigo-600/90 hover:bg-indigo-600 text-white rounded-2xl py-4 text-sm font-bold shadow-lg transition-colors flex justify-center items-center gap-2 backdrop-blur-sm border border-indigo-500/50">
                            ค้นหาข้อมูล <i class="fas fa-arrow-right text-[10px]"></i>
                        </button>
                    </form>
                </div>

                <!-- การ์ดข้อมูลเล็กๆ ด้านขวาล่าง (เติมเต็ม Layout ให้สวยงาม) -->
                <div class="hidden lg:flex absolute bottom-12 right-12 glass-card rounded-[2rem] p-6 shadow-2xl w-[320px] z-20 flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-white font-bold text-lg leading-tight drop-shadow">MBS Smart<br>Support Team</h3>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-white/90 text-indigo-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-headset"></i>
                        </div>
                    </div>
                    <p class="text-xs text-white/80 leading-relaxed mb-5 drop-shadow-sm">
                        บริการแก้ไขปัญหาด้านคอมพิวเตอร์ เครือข่าย ไฟฟ้า และอาคารสถานที่ โดยทีมช่างผู้เชี่ยวชาญ
                    </p>
                    <div class="flex items-center gap-4 text-white text-xs font-medium bg-white/10 p-3 rounded-xl border border-white/20">
                        <div class="flex items-center gap-1.5"><i class="fas fa-check-circle text-emerald-400"></i> สะดวก</div>
                        <div class="w-px h-3 bg-white/30"></div>
                        <div class="flex items-center gap-1.5"><i class="fas fa-check-circle text-emerald-400"></i> โปร่งใส</div>
                    </div>
                </div>

            </div>
        </main>

        <!-- Mobile Bottom Navigation (เมนูด้านล่างสำหรับมือถือ โชว์เฉพาะจอมือถือ) -->
        <nav class="md:hidden fixed bottom-4 left-4 right-4 bg-white/90 backdrop-blur-xl border border-slate-200 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] flex justify-around items-center p-2 z-50">
            <a href="#" class="flex flex-col items-center p-2 text-indigo-600">
                <i class="fas fa-home text-lg mb-1"></i>
                <span class="text-[9px] font-bold">หน้าหลัก</span>
            </a>
            <a href="form_repair.php" class="flex flex-col items-center p-2 text-slate-400 hover:text-indigo-600 transition-colors">
                <div class="w-12 h-12 bg-indigo-600 text-white rounded-full flex items-center justify-center -mt-8 shadow-lg shadow-indigo-200 border-4 border-white">
                    <i class="fas fa-plus text-xl"></i>
                </div>
                <span class="text-[9px] font-bold mt-1 text-slate-600">แจ้งซ่อม</span>
            </a>
            <button onclick="toggleModal('loginModal')" class="flex flex-col items-center p-2 text-slate-400 hover:text-indigo-600 transition-colors">
                <i class="fas fa-user-lock text-lg mb-1"></i>
                <span class="text-[9px] font-bold">เจ้าหน้าที่</span>
            </button>
        </nav>

    </div>


    <!-- ==============================================
         Result Modal (แสดงผลการค้นหา)
         ============================================== -->
    <div id="resultModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white/95 backdrop-blur-xl w-full max-w-2xl rounded-[2.5rem] shadow-2xl z-50 flex flex-col max-h-[85vh] overflow-hidden transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="resultModalContent">
            
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-white">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">ผลการค้นหาใบงาน</h2>
                    <p class="text-xs font-medium text-slate-500 mt-1">คำค้นหา: <span class="text-indigo-600 font-bold">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-10 h-10 rounded-full bg-slate-50 text-slate-400 hover:bg-slate-200 hover:text-slate-800 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-4 md:p-8 overflow-y-auto flex-1 bg-slate-50/50 space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                        $icon = "fa-file-alt text-slate-400";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                            $icon = "fa-clock text-amber-500";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                            $icon = "fa-tools text-sky-500";
                            $res['status'] = 'ช่างรับเรื่องแจ้งซ่อมแล้ว';
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";
                            $icon = "fa-check-circle text-emerald-500";
                        }
                    ?>
                        <a href="view_repair.php?id=<?php echo $res['id']; ?>" target="_blank" class="block group">
                            <div class="bg-white rounded-[1.5rem] p-6 shadow-sm border border-slate-100 hover:shadow-lg hover:border-indigo-100 transition-all duration-300">
                                
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 pb-4 border-b border-slate-50">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-indigo-50 transition-colors">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">เลขที่ใบงาน</p>
                                            <h3 class="text-lg font-extrabold text-slate-800 group-hover:text-indigo-600 transition-colors"><?php echo $res['ticket_no']; ?></h3>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold border <?php echo $statusClass; ?>">
                                        <?php echo $res['status']; ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-y-4 gap-x-6 text-sm">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-desktop text-slate-300 w-4"></i>
                                        <span class="text-slate-500 text-xs">อุปกรณ์:</span>
                                        <span class="font-semibold text-slate-700"><?php echo $res['equipment_type']; ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-hard-hat text-slate-300 w-4"></i>
                                        <span class="text-slate-500 text-xs">ผู้ดูแล:</span>
                                        <span class="font-semibold <?php echo !empty($res['technician_name']) ? 'text-indigo-600' : 'text-slate-400'; ?>">
                                            <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="far fa-user text-slate-300 w-4"></i>
                                        <span class="text-slate-500 text-xs">ผู้แจ้ง:</span>
                                        <span class="font-semibold text-slate-700"><?php echo $res['reporter_name']; ?></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="far fa-calendar-alt text-slate-300 w-4"></i>
                                        <span class="text-slate-500 text-xs">วันที่แจ้ง:</span>
                                        <span class="font-semibold text-slate-700 text-[11px]"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-4 border-t border-slate-100 bg-white text-center">
                <button onclick="toggleModal('resultModal')" class="bg-slate-900 text-white rounded-full px-8 py-3 text-sm font-bold hover:bg-slate-800 transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ==============================================
         Login Modal (ฟอร์มล็อกอินเจ้าหน้าที่)
         ============================================== -->
    <div id="loginModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-[100] px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="bg-white/95 backdrop-blur-xl w-full max-w-sm rounded-[2.5rem] shadow-2xl z-50 flex flex-col transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="loginModalContent">
            
            <div class="p-8 text-center relative pt-12">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:bg-slate-200 hover:text-slate-800 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-4 border border-indigo-100">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">ระบบเจ้าหน้าที่</h2>
                <p class="text-xs text-slate-500 mt-2 font-medium">กรุณาเข้าสู่ระบบเพื่อจัดการใบงาน</p>
            </div>

            <form action="" method="POST" class="p-8 pt-0">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <input type="text" name="username" required placeholder="ชื่อผู้ใช้งาน" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all">
                    </div>
                    
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-lock text-sm"></i>
                        </div>
                        <input type="password" name="password" required placeholder="รหัสผ่าน" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all">
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-slate-900 text-white rounded-2xl py-4 font-bold text-sm hover:bg-indigo-600 hover:shadow-lg hover:shadow-indigo-500/30 transition-all flex justify-center items-center gap-2">
                    เข้าสู่ระบบ <i class="fas fa-sign-in-alt text-xs"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts สำหรับเปิด/ปิด Popup -->
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
    </script>

    <?php if(!empty($error_msg)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'เข้าสู่ระบบไม่สำเร็จ',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-[2rem] shadow-2xl border border-slate-100' }
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
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-[2rem] shadow-2xl border border-slate-100' }
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