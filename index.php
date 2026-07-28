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
    <title>MBS REPAIR | คณะการบัญชีและการจัดการ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #F9FAFB; /* สีเทาอ่อนมากๆ คลีนๆ */
            color: #111827;
        }
        /* ลายตารางบางๆ เป็นกิมมิคความเทคๆ */
        .bg-grid {
            background-image: linear-gradient(to right, #f3f4f6 1px, transparent 1px),
                              linear-gradient(to bottom, #f3f4f6 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .modal { transition: opacity 0.2s ease, visibility 0.2s ease; }
        body.modal-active { overflow: hidden; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-grid selection:bg-blue-600 selection:text-white">

    <!-- Navbar: เรียบหรู คลีนๆ -->
    <header class="w-full bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl text-white flex items-center justify-center shadow-md shadow-blue-200">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-none">MBS REPAIR</h1>
                    <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-widest">Mahasarakham University</span>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <button onclick="toggleModal('loginModal')" class="bg-gray-900 hover:bg-gray-800 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-colors flex items-center gap-2">
                    <i class="fas fa-user-circle"></i> เจ้าหน้าที่
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content: เน้นจุดโฟกัสตรงกลาง -->
    <main class="flex-1 flex flex-col items-center justify-center px-4 pt-12 pb-20 z-10">
        
        <!-- ส่วนข้อความต้อนรับ (Hero Section) -->
        <div class="text-center max-w-3xl mx-auto mb-10">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold mb-6 border border-blue-100">
                <span class="relative flex h-2.5 w-2.5">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-600"></span>
                </span>
                ระบบพร้อมให้บริการ 24 ชั่วโมง
            </div>
            
            <h2 class="text-5xl md:text-6xl font-extrabold text-gray-900 tracking-tight mb-6 leading-tight">
                แจ้งซ่อมง่าย <br class="hidden sm:block">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">ตรวจสอบได้แบบ Real-time</span>
            </h2>
            
            <p class="text-gray-500 text-lg max-w-xl mx-auto">
                แพลตฟอร์มรับแจ้งซ่อมสำหรับบุคลากรและนิสิต คณะการบัญชีและการจัดการ ดูแลครอบคลุมทุกอุปกรณ์และอาคารสถานที่
            </p>
        </div>

        <!-- ช่องค้นหาสถานะ (ใหญ่ เด่น ใช้งานง่าย) -->
        <div class="w-full max-w-2xl mx-auto mb-16">
            <form action="" method="POST" class="bg-white p-2 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 flex items-center transition-all focus-within:shadow-[0_8px_30px_rgb(37,99,235,0.12)] focus-within:border-blue-200">
                <input type="hidden" name="check_status" value="1">
                <div class="pl-6 pr-4 text-gray-400">
                    <i class="fas fa-search text-lg"></i>
                </div>
                <input type="text" name="search_query" required placeholder="พิมพ์เลขใบงาน หรือ ชื่อผู้แจ้ง เพื่อดูสถานะ..." class="w-full py-4 text-base font-medium text-gray-700 placeholder-gray-400 bg-transparent border-none focus:outline-none">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full px-8 py-4 font-bold text-sm transition-colors shadow-md flex items-center gap-2 whitespace-nowrap">
                    ติดตามสถานะ <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- เมนูทางลัด (Bento Grid) คลีนๆ จัดเรียงสวยงาม -->
        <div class="w-full max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- ปุ่มแจ้งซ่อม (เน้นสีสัน) -->
            <a href="form_repair.php" class="group bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:border-blue-100 hover:-translate-y-1 transition-all flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mb-5 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <i class="fas fa-plus"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">แจ้งซ่อมใหม่</h3>
                <p class="text-sm text-gray-500">กรอกฟอร์มเพื่อแจ้งปัญหาให้ช่างทราบทันที</p>
            </a>

            <!-- ปุ่ม LINE (เน้นสีเขียว) -->
            <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="group bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:border-[#00B900]/20 hover:-translate-y-1 transition-all flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-[#00B900]/10 text-[#00B900] rounded-2xl flex items-center justify-center text-3xl mb-5 group-hover:bg-[#00B900] group-hover:text-white transition-colors">
                    <i class="fab fa-line"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">ติดต่อผ่าน LINE</h3>
                <p class="text-sm text-gray-500">รับแจ้งเตือนและสอบถามผ่าน LINE Official</p>
            </a>

            <!-- ข้อมูลหมวดหมู่ (ตกแต่งให้สมดุล) -->
            <div class="bg-gray-900 p-8 rounded-3xl shadow-xl flex flex-col justify-center text-left relative overflow-hidden">
                <div class="absolute -right-6 -top-6 text-white/5 text-9xl"><i class="fas fa-cogs"></i></div>
                <h3 class="text-xl font-bold text-white mb-4 relative z-10">หมวดหมู่บริการ</h3>
                <ul class="space-y-3 text-sm text-gray-400 font-medium relative z-10">
                    <li class="flex items-center gap-3"><i class="fas fa-desktop text-blue-400 w-4"></i> คอมพิวเตอร์ & ไอที</li>
                    <li class="flex items-center gap-3"><i class="fas fa-wifi text-blue-400 w-4"></i> ระบบเครือข่าย</li>
                    <li class="flex items-center gap-3"><i class="fas fa-bolt text-blue-400 w-4"></i> ไฟฟ้า & แอร์</li>
                    <li class="flex items-center gap-3"><i class="fas fa-building text-blue-400 w-4"></i> อาคารสถานที่</li>
                </ul>
            </div>

        </div>

    </main>

    <!-- Footer แบบคลีนๆ -->
    <footer class="w-full bg-white border-t border-gray-200 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4 text-sm font-medium text-gray-500">
            <p>&copy; <?php echo date('Y'); ?> MBS REPAIR. Faculty of Accountancy and Management.</p>
            <p>Developed by <span class="text-gray-900 font-bold">ภัทรวดี & มัทนา</span> (BIS 4th Year)</p>
        </div>
    </footer>


    <!-- ==============================================
         Modal: แสดงผลการค้นหาใบงาน (Clean Design)
         ============================================== -->
    <div id="resultModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-50 px-4">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white w-full max-w-3xl rounded-3xl shadow-2xl z-50 flex flex-col max-h-[85vh] transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="resultModalContent">
            
            <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">ผลการค้นหา</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">คำค้นหา: <span class="text-blue-600 font-bold">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-10 h-10 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-900 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-gray-50 space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        // แต่งสีสถานะแบบเรียบหรู
                        $statusClass = "bg-gray-100 text-gray-700"; 
                        $icon = "fa-file-alt text-gray-400";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $statusClass = "bg-yellow-100 text-yellow-800";
                            $icon = "fa-clock text-yellow-600";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $statusClass = "bg-blue-100 text-blue-800";
                            $icon = "fa-tools text-blue-600";
                            $res['status'] = 'ช่างรับเรื่องแจ้งซ่อมแล้ว';
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $statusClass = "bg-green-100 text-green-800";
                            $icon = "fa-check-circle text-green-600";
                        }
                    ?>
                        <a href="view_repair.php?id=<?php echo $res['id']; ?>" target="_blank" class="block bg-white rounded-2xl p-6 border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 pb-4 border-b border-gray-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-lg">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Ticket No.</p>
                                        <h3 class="text-lg font-bold text-gray-900"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-1">อุปกรณ์</p>
                                    <p class="font-semibold text-gray-900 truncate"><?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-1">ผู้แจ้ง</p>
                                    <p class="font-semibold text-gray-900 truncate"><?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-1">ผู้รับผิดชอบ</p>
                                    <p class="font-semibold <?php echo !empty($res['technician_name']) ? 'text-blue-600' : 'text-gray-400'; ?>">
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-1">วันที่แจ้ง</p>
                                    <p class="font-semibold text-gray-900"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-6 border-t border-gray-100 bg-white text-right">
                <button onclick="toggleModal('resultModal')" class="bg-gray-100 text-gray-700 rounded-xl px-6 py-2.5 text-sm font-bold hover:bg-gray-200 transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ==============================================
         Modal: เข้าสู่ระบบเจ้าหน้าที่ (Clean Form)
         ============================================== -->
    <div id="loginModal" class="modal opacity-0 invisible fixed inset-0 flex items-center justify-center z-50 px-4">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl z-50 flex flex-col overflow-hidden transform transition-transform duration-300 scale-95 data-[open=true]:scale-100" id="loginModalContent">
            
            <!-- Header Form -->
            <div class="px-8 pt-10 pb-6 text-center relative border-b border-gray-100">
                <button onclick="toggleModal('loginModal')" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-gray-900">เข้าสู่ระบบเจ้าหน้าที่</h2>
                <p class="text-sm text-gray-500 mt-2">สำหรับผู้บริหาร และช่างซ่อมบำรุง</p>
            </div>

            <!-- Body Form -->
            <form action="" method="POST" class="p-8 bg-gray-50">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" name="username" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">รหัสผ่าน (Password)</label>
                        <input type="password" name="password" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all">
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-gray-900 text-white rounded-xl py-4 font-bold text-sm hover:bg-blue-600 shadow-md transition-all">
                    เข้าสู่ระบบ
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
                }, 200);
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
                confirmButtonColor: '#111827', // สีดำเข้ม
                customClass: { popup: 'rounded-2xl shadow-xl' }
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
                confirmButtonColor: '#2563EB', // สีฟ้า
                customClass: { popup: 'rounded-2xl shadow-xl' }
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