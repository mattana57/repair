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
            background-color: #d4d4d8; 
            overflow-x: hidden; 
        }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        body.modal-active { overflow: hidden; }
        
        /* Custom Scrollbar for elegant look */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.4); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-2 md:p-6 lg:p-10 selection:bg-indigo-500 selection:text-white relative bg-[#e2e2e5]">

    <!-- Main App Container (Glassy Look) -->
    <main class="relative w-full max-w-[1500px] min-h-[85vh] md:min-h-[90vh] bg-white rounded-[2rem] md:rounded-[3rem] overflow-hidden shadow-2xl flex flex-col">
        
        <!-- Background Image inside the container -->
        <div class="absolute inset-0 z-0">
            <!-- ใส่รูปพื้นหลังของคณะที่นี่ -->
            <img src="uploads/mbs_bg.jpg?v=9" alt="MBS Background" class="w-full h-full object-cover object-center" onerror="this.src='https://images.unsplash.com/photo-1497366216548-37526070297c?q=80&w=2069&auto=format&fit=crop'">
            <!-- Overlay เพื่อให้อ่านตัวหนังสือได้ง่ายขึ้น -->
            <div class="absolute inset-0 bg-slate-900/40 mix-blend-multiply"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-slate-900/30"></div>
        </div>

        <!-- Top Navigation Bar -->
        <header class="relative z-20 flex flex-col md:flex-row justify-between items-center px-6 md:px-10 pt-8 gap-4">
            
            <!-- Logo area -->
            <div class="bg-white/90 backdrop-blur-md rounded-full px-5 py-2.5 flex items-center gap-3 shadow-lg shrink-0">
                <i class="fas fa-tools text-indigo-700"></i>
                <span class="font-bold text-slate-800 tracking-wide text-sm md:text-base">MBS REPAIR</span>
            </div>

            <!-- Central Search Bar (Check Status) -->
            <form action="" method="POST" class="w-full md:w-auto bg-white/90 backdrop-blur-md rounded-full p-1.5 flex items-center shadow-lg hover:shadow-xl transition-shadow flex-1 max-w-2xl">
                <input type="hidden" name="check_status" value="1">
                <div class="hidden sm:flex items-center text-slate-400 px-4 border-r border-slate-200 text-sm">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" name="search_query" required placeholder="พิมพ์เลขใบงาน หรือ ชื่อผู้แจ้ง เพื่อติดตามสถานะ" class="bg-transparent border-none focus:outline-none w-full px-4 text-sm font-medium text-slate-700 placeholder-slate-400 h-10">
                <button type="submit" class="bg-slate-900 text-white rounded-full px-6 py-2.5 text-sm font-semibold flex items-center justify-center hover:bg-indigo-600 transition-colors shrink-0">
                    ค้นหา <i class="fas fa-arrow-right ml-2 text-[10px]"></i>
                </button>
            </form>

            <!-- Login / Admin Button -->
            <button onclick="toggleModal('loginModal')" class="hidden md:flex bg-white/90 backdrop-blur-md rounded-full px-6 py-2.5 font-bold text-sm text-slate-800 shadow-lg hover:bg-white hover:text-indigo-600 transition-colors items-center gap-2 shrink-0">
                สำหรับเจ้าหน้าที่ <i class="fas fa-arrow-up-right-from-square text-[10px] opacity-70"></i>
            </button>
        </header>

        <!-- Sidebar Navigation (Left Floating) -->
        <nav class="hidden lg:flex absolute top-1/2 -translate-y-1/2 left-8 bg-white/80 backdrop-blur-xl rounded-full py-6 px-3 flex-col gap-6 shadow-xl z-20 border border-white/50">
            <a href="#" class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center shadow-md hover:bg-indigo-600 transition-colors" title="หน้าหลัก"><i class="fas fa-home text-sm"></i></a>
            <a href="form_repair.php" class="w-10 h-10 rounded-full text-slate-500 flex items-center justify-center hover:bg-white hover:text-indigo-600 transition-colors shadow-sm" title="แจ้งซ่อมใหม่"><i class="fas fa-plus text-sm"></i></a>
            <hr class="border-slate-300 mx-2">
            <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="w-10 h-10 rounded-full bg-[#00B900] text-white flex items-center justify-center shadow-md hover:bg-[#009900] transition-colors" title="LINE Bot"><i class="fab fa-line text-lg"></i></a>
        </nav>

        <!-- Center Hero Typography -->
        <div class="relative z-10 flex-1 flex flex-col items-center justify-center text-center px-4 mt-[-5%] md:mt-0 pointer-events-none">
            <h1 class="text-5xl md:text-7xl lg:text-[6rem] font-bold text-white drop-shadow-2xl leading-tight tracking-tight">
                MBS <span class="font-light">Smart Repair</span>
            </h1>
            <p class="text-white/90 mt-4 text-base md:text-lg font-light drop-shadow-md tracking-wide max-w-2xl">
                ค้นพบประสบการณ์ใหม่ในการดูแลรักษาสภาพแวดล้อมและการเรียนการสอน<br class="hidden md:block">แจ้งซ่อมรวดเร็ว โปร่งใส และติดตามผลได้แบบเรียลไทม์
            </p>
        </div>

        <!-- Bottom Overlay Cards Container -->
        <div class="relative z-20 w-full flex flex-col lg:flex-row justify-between items-end p-6 md:p-10 gap-6 mt-auto">
            
            <!-- Left Info Card -->
            <div class="w-full lg:w-96 bg-white/95 backdrop-blur-2xl rounded-[2rem] p-8 shadow-2xl relative lg:ml-20">
                <h3 class="text-2xl font-bold text-slate-800 mb-3">แจ้งปัญหาการใช้งาน</h3>
                <p class="text-sm text-slate-500 leading-relaxed mb-8">
                    แพลตฟอร์มรับแจ้งซ่อมสำหรับบุคลากรและนิสิต รองรับปัญหาคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่
                </p>
                <div class="flex items-center justify-between border-t border-slate-100 pt-6">
                    <div>
                        <span class="text-3xl font-black text-slate-900 tracking-tighter">24/7</span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest block mt-1">Available System</span>
                    </div>
                    <a href="form_repair.php" class="w-14 h-14 rounded-full bg-slate-900 text-white flex items-center justify-center hover:bg-indigo-600 transition-all shadow-xl hover:-translate-y-1 hover:shadow-indigo-500/30">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Right Detail Card -->
            <div class="w-full lg:w-[450px] bg-white/70 backdrop-blur-xl border border-white/50 rounded-[2rem] p-6 shadow-2xl relative">
                <!-- Abstract floating circle over card -->
                <div class="hidden lg:flex absolute -left-8 -bottom-8 w-24 h-24 bg-indigo-900 rounded-full items-center justify-center shadow-2xl border-[6px] border-white z-30">
                    <img src="https://cdn-icons-png.flaticon.com/512/3612/3612595.png" alt="icon" class="w-10 h-10 filter invert opacity-90">
                </div>

                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="text-lg font-bold text-slate-800">Faculty of Accountancy & Management</h4>
                        <p class="text-xs font-medium text-indigo-600 mt-1"><i class="fas fa-map-marker-alt mr-1"></i> SBB & ACC.BIZ Building</p>
                    </div>
                    <button class="w-8 h-8 rounded-full bg-white/80 flex items-center justify-center shadow-sm text-slate-500 hover:text-slate-900 transition-colors"><i class="fas fa-arrow-up-right-from-square text-[10px]"></i></button>
                </div>
                
                <p class="text-xs text-slate-600 leading-relaxed mb-6">
                    โครงงานการพัฒนาระบบสารสนเทศเพื่อการแจ้งซ่อมและบำรุงรักษาคณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                </p>
                
                <div class="flex items-center gap-6 border-t border-slate-200/60 pt-4">
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <i class="far fa-user-circle text-slate-400 text-base"></i> ภัทรวดี & มัทนา
                    </div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <i class="fas fa-code text-slate-400 text-base"></i> BIS 4th Year
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Mobile Bottom Login Action -->
        <div class="md:hidden w-full bg-white p-4 z-20">
            <button onclick="toggleModal('loginModal')" class="w-full bg-slate-900 text-white rounded-full py-4 font-bold text-sm">
                เข้าสู่ระบบสำหรับเจ้าหน้าที่ <i class="fas fa-lock ml-2"></i>
            </button>
        </div>

    </main>


    <!-- ==============================================
         Result Modal (Glassmorphism Style) 
         ============================================== -->
    <div id="resultModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-50 px-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white/95 backdrop-blur-xl border border-white/50 w-full max-w-2xl rounded-[2rem] shadow-2xl z-50 flex flex-col max-h-[85vh] overflow-hidden transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="resultModalContent">
            
            <!-- Modal Header -->
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-white/50">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">ข้อมูลการแจ้งซ่อม</h2>
                    <p class="text-xs font-medium text-slate-500 mt-1">ผลการค้นหา: <span class="text-indigo-600 font-bold">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-slate-50/50 space-y-4 custom-scroll">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        // Status styling
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
            
            <div class="p-4 border-t border-slate-100 bg-white/50 text-center">
                <button onclick="toggleModal('resultModal')" class="bg-slate-900 text-white rounded-full px-8 py-2.5 text-sm font-semibold hover:bg-slate-800 transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ==============================================
         Login Modal (Glassmorphism Style) 
         ============================================== -->
    <div id="loginModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-50 px-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="bg-white/95 backdrop-blur-xl border border-white/50 w-full max-w-sm rounded-[2rem] shadow-2xl z-50 flex flex-col transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="loginModalContent">
            
            <div class="p-8 text-center relative pt-12">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:bg-slate-200 hover:text-slate-800 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-4 shadow-inner">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">ระบบเจ้าหน้าที่</h2>
                <p class="text-xs text-slate-500 mt-2 font-medium">กรุณาเข้าสู่ระบบเพื่อจัดการใบงาน</p>
            </div>

            <form action="" method="POST" class="p-8 pt-0">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4">
                    <div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                            <input type="text" name="username" required placeholder="ชื่อผู้ใช้งาน" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all">
                        </div>
                    </div>
                    
                    <div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-sm"></i>
                            </div>
                            <input type="password" name="password" required placeholder="รหัสผ่าน" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-slate-900 text-white rounded-xl py-3.5 font-bold text-sm hover:bg-indigo-600 hover:shadow-lg hover:shadow-indigo-500/30 transition-all flex justify-center items-center gap-2">
                    เข้าสู่ระบบ <i class="fas fa-sign-in-alt"></i>
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
                // Open
                modal.classList.remove('opacity-0', 'invisible');
                content.setAttribute('data-open', 'true');
                document.body.classList.add('modal-active');
            } else {
                // Close
                modal.classList.add('opacity-0');
                content.setAttribute('data-open', 'false');
                setTimeout(() => {
                    modal.classList.add('invisible');
                    document.body.classList.remove('modal-active');
                }, 300); // Wait for transition
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