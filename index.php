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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; color: #334155; overflow-x: hidden; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
        .glass-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }
        .hero-section {
            background-image: linear-gradient(to right, rgba(241, 245, 249, 0.95) 0%, rgba(241, 245, 249, 0.7) 50%, rgba(241, 245, 249, 0.2) 100%), url('https://images.unsplash.com/photo-1497366216548-37526070297c?q=80&w=2069&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-blue-200 relative">

    <!-- Header -->
    <header class="w-full glass-header fixed top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 md:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30 text-white">
                    <i class="fas fa-tools text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg md:text-xl font-extrabold text-slate-800 tracking-wide uppercase">MBS Repair</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-6">
                <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a href="#" class="hover:text-blue-600 transition-colors">หน้าแรก</a>
                    <a href="#categories" class="hover:text-blue-600 transition-colors">บริการของเรา</a>
                </nav>
                <button onclick="toggleModal('loginModal')" class="flex items-center text-slate-700 hover:text-blue-600 font-semibold text-sm transition-colors">
                    <i class="fas fa-sign-in-alt mr-2 text-lg"></i> <span class="hidden sm:inline">เจ้าหน้าที่เข้าสู่ระบบ</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="hero-section pt-28 pb-32 md:pt-40 md:pb-48 relative z-10 px-4 md:px-8 border-b border-slate-200">
        <div class="max-w-7xl mx-auto flex flex-col items-start relative z-20">
            
            <div class="inline-block px-3 py-1 mb-4 rounded-full bg-blue-100 text-blue-700 font-bold text-xs uppercase tracking-widest">
                Discover . Report . Fix .
            </div>
            
            <h2 class="text-4xl md:text-6xl font-extrabold text-slate-900 leading-[1.1] mb-6">
                ระบบแจ้งซ่อมออนไลน์ <br>
                <span class="text-blue-600 italic font-medium">รวดเร็ว ตรวจสอบได้</span>
            </h2>
            
            <p class="text-base md:text-lg text-slate-600 max-w-lg mb-8">
                ให้บริการแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ สำหรับบุคลากรและนิสิต คณะการบัญชีและการจัดการ มมส.
            </p>
            
            <a href="form_repair.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-full font-bold text-base md:text-lg shadow-xl shadow-blue-600/30 transition-all flex items-center group">
                <i class="fas fa-paper-plane mr-3 group-hover:-translate-y-1 group-hover:translate-x-1 transition-transform"></i> แจ้งซ่อมอุปกรณ์
            </a>
            
        </div>

        <!-- Floating Search Bar -->
        <div class="absolute -bottom-8 md:-bottom-10 left-0 right-0 mx-auto w-[92%] max-w-3xl bg-white rounded-2xl md:rounded-full shadow-[0_10px_40px_-10px_rgba(0,0,0,0.15)] z-30">
            <form action="" method="POST" class="flex flex-col md:flex-row items-center p-2">
                <input type="hidden" name="check_status" value="1">
                
                <div class="flex-1 w-full flex items-center px-4 py-4 md:px-6 md:py-2 cursor-text" onclick="document.getElementById('searchInput').focus();">
                    <i class="fas fa-search text-blue-500 text-xl md:text-2xl mr-4"></i>
                    <div class="w-full">
                        <p class="text-xs font-bold text-slate-800 mb-1">ตรวจสอบสถานะแจ้งซ่อม</p>
                        <input type="text" id="searchInput" name="search_query" required placeholder="พิมพ์เลขที่ใบงาน (เช่น MR-...) หรือ ชื่อผู้แจ้ง" class="w-full text-sm md:text-base focus:outline-none text-slate-700 placeholder-slate-400 bg-transparent">
                    </div>
                </div>

                <button type="submit" class="w-full md:w-auto mt-2 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white p-3 md:px-10 md:py-4 rounded-xl md:rounded-full font-bold transition-all shadow-md flex items-center justify-center mr-1">
                    <i class="fas fa-search md:mr-2"></i> <span class="md:hidden ml-2">ค้นหาข้อมูล</span><span class="hidden md:inline">ค้นหาเลย</span>
                </button>
            </form>
        </div>
    </main>

    <!-- Explore by Category Section -->
    <section id="categories" class="max-w-7xl mx-auto px-4 md:px-8 pt-20 md:pt-28 pb-16 w-full">
        <h3 class="text-xl font-bold text-slate-800 mb-6">หมวดหมู่การให้บริการ (Explore by Category)</h3>
        
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-6">
            <div class="bg-white border border-slate-100 p-5 rounded-2xl flex flex-col items-center justify-center text-center shadow-sm hover:shadow-md hover:border-blue-200 transition-all cursor-default group">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
                    <i class="fas fa-desktop"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">คอมพิวเตอร์</p>
            </div>
            <div class="bg-white border border-slate-100 p-5 rounded-2xl flex flex-col items-center justify-center text-center shadow-sm hover:shadow-md hover:border-blue-200 transition-all cursor-default group">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
                    <i class="fas fa-wifi"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">ระบบเครือข่าย</p>
            </div>
            <div class="bg-white border border-slate-100 p-5 rounded-2xl flex flex-col items-center justify-center text-center shadow-sm hover:shadow-md hover:border-blue-200 transition-all cursor-default group">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
                    <i class="fas fa-bolt"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">ระบบไฟฟ้า</p>
            </div>
            <div class="bg-white border border-slate-100 p-5 rounded-2xl flex flex-col items-center justify-center text-center shadow-sm hover:shadow-md hover:border-blue-200 transition-all cursor-default group">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-3 group-hover:scale-110 transition-transform">
                    <i class="fas fa-building"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">อาคารสถานที่</p>
            </div>
            <div class="col-span-2 md:col-span-1 bg-gradient-to-br from-blue-600 to-sky-500 p-5 rounded-2xl flex flex-col justify-center shadow-md relative overflow-hidden text-white">
                <i class="fas fa-headset absolute -bottom-4 -right-4 text-6xl opacity-20"></i>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100 mb-1">MBS Support</p>
                    <p class="text-sm font-bold mb-2">บริการรวดเร็ว<br>และโปร่งใส</p>
                    <a href="form_repair.php" class="text-xs font-semibold hover:underline flex items-center">แจ้งซ่อมเลย <i class="fas fa-chevron-right text-[10px] ml-1"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= ส่วน Footer โปรเจกต์จบ (ปรับให้สมดุลแบบ 2 คอลัมน์) ================= -->
    <footer class="bg-white border-t border-slate-200 mt-auto">
        <!-- ปรับ max-w-5xl เพื่อบีบให้ 2 คอลัมน์ขยับเข้าหากันตรงกลาง ดูสมดุลและสวยงามขึ้น -->
        <div class="max-w-5xl mx-auto px-4 md:px-8 py-10 md:py-12">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 md:gap-16">
                
                <!-- 1. ข้อมูลองค์กร (ด้านซ้าย) -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30 text-white">
                            <i class="fas fa-tools text-lg"></i>
                        </div>
                        <h2 class="text-xl font-extrabold text-slate-800 tracking-wide uppercase">MBS Repair</h2>
                    </div>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        ระบบให้บริการแจ้งซ่อมออนไลน์ สำหรับบุคลากรและนิสิต<br>
                        คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </p>
                </div>

                <!-- 2. คณะผู้จัดทำโปรเจกต์จบ (ด้านขวา) -->
                <div>
                    <h3 class="font-bold text-slate-800 mb-4 text-lg">คณะผู้จัดทำ (โปรเจกต์จบ)</h3>
                    <ul class="space-y-2 text-sm text-slate-500">
                        <li class="flex items-center gap-2"><i class="fas fa-user-graduate text-blue-500"></i> นางสาวภัทรวดี ขามประโคน</li>
                        <li class="flex items-center gap-2"><i class="fas fa-user-graduate text-blue-500"></i> นางสาวมัทนา รัตนแสง</li>
                        <li class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400 leading-relaxed">
                            นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ<br>คณะการบัญชีและการจัดการ
                        </li>
                    </ul>
                </div>

            </div>
            
            <!-- ส่วนลิขสิทธิ์ด้านล่างสุด -->
            <div class="mt-10 pt-6 border-t border-slate-100 text-center text-xs md:text-sm text-slate-400">
                <p>&copy; <?php echo date('Y'); ?> MBS Repair System. All rights reserved.</p>
            </div>
            
        </div>
    </footer>

    <!-- Modal 2: แสดงผลการค้นหาสถานะ (Results) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-full md:max-w-2xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh]">
            
            <div class="p-6 flex justify-between items-center bg-slate-50 border-b border-slate-100 shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-slate-800"><i class="fas fa-list-alt text-blue-500 mr-2"></i> ผลการค้นหา</h2>
                    <p class="text-sm text-slate-500">คำค้นหา: <span class="font-bold text-blue-600">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1 bg-slate-50/50 space-y-4">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                        if($res['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                        elseif($res['status'] == 'กำลังดำเนินการ') $statusClass = "bg-blue-50 text-blue-600 border-blue-200";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";
                    ?>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                            <!-- แถบสีด้านซ้าย -->
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php echo str_replace(['bg-', 'text-', 'border-'], ['bg-', 'bg-', 'bg-'], explode(' ', $statusClass)[1]); ?>"></div>
                            
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-3 pl-2">
                                <div>
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">เลขที่ใบงาน</span>
                                    <h3 class="text-lg font-bold text-blue-700"><?php echo $res['ticket_no']; ?></h3>
                                </div>
                                <div class="text-left md:text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border <?php echo $statusClass; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current mr-2"></span><?php echo $res['status']; ?>
                                    </span>
                                    <p class="text-xs text-slate-400 mt-1"><i class="far fa-clock"></i> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>

                            <div class="pl-2 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-slate-500 mb-0.5"><i class="fas fa-desktop text-slate-400 w-4 text-center mr-1"></i> <b>อุปกรณ์:</b> <?php echo $res['equipment_type']; ?></p>
                                    <p class="text-slate-500"><i class="fas fa-user text-slate-400 w-4 text-center mr-1"></i> <b>ผู้แจ้ง:</b> <?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-slate-500 mb-0.5"><i class="fas fa-hard-hat text-slate-400 w-4 text-center mr-1"></i> <b>ช่างผู้ดูแล:</b> <span class="<?php echo !empty($res['technician_name']) ? 'text-indigo-600 font-semibold' : ''; ?>"><?php echo !empty($res['technician_name']) ? $res['technician_name'] : '- ยังไม่ระบุ -'; ?></span></p>
                                    <p class="text-slate-500"><i class="fas fa-comment-dots text-slate-400 w-4 text-center mr-1"></i> <b>หมายเหตุ:</b> <?php echo !empty($res['repair_note']) ? $res['repair_note'] : '-'; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="p-6 bg-white border-t border-slate-100 shrink-0 flex justify-center">
                <button onclick="toggleModal('resultModal')" class="bg-slate-800 hover:bg-slate-700 text-white px-8 py-2.5 rounded-xl font-bold transition-colors shadow-md">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: เข้าสู่ระบบ (Login) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all">
            
            <div class="p-8 text-center bg-gradient-to-b from-blue-50 to-white border-b border-slate-100 relative">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white text-slate-400 hover:text-red-500 hover:bg-red-50 shadow-sm flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-16 h-16 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg shadow-blue-500/30">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-800">เข้าสู่ระบบเจ้าหน้าที่</h2>
                <p class="text-sm text-slate-500 mt-2">สำหรับ Admin, ผู้บริหาร และทีมช่างซ่อม</p>
            </div>

            <form action="" method="POST" class="p-8 pt-6">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-user text-slate-400"></i>
                            </div>
                            <input type="text" name="username" required placeholder="กรอก Username ของคุณ" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition-all font-medium">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">รหัสผ่าน (Password)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400"></i>
                            </div>
                            <input type="password" name="password" required placeholder="กรอกรหัสผ่าน" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition-all font-medium">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-slate-800 hover:bg-slate-700 text-white py-3.5 rounded-xl font-bold text-lg transition-all shadow-lg hover:shadow-xl flex items-center justify-center">
                    เข้าสู่ระบบ <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- ปุ่ม Floating LINE มุมขวาล่าง -->
    <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="fixed bottom-6 right-6 md:bottom-8 md:right-8 z-40 bg-[#00B900] hover:bg-[#009900] text-white px-5 py-3.5 rounded-full font-bold text-sm md:text-base shadow-xl shadow-green-500/40 transition-all transform hover:-translate-y-2 flex items-center group">
        <i class="fab fa-line text-2xl md:text-3xl mr-2 group-hover:scale-110 transition-transform"></i> 
        <span class="hidden md:inline">เพิ่มเพื่อน LINE</span>
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
                title: 'เข้าสู่ระบบไม่สำเร็จ!',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#0f172a',
                confirmButtonText: 'ลองใหม่อีกครั้ง'
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
                title: 'ไม่พบข้อมูล',
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบอีกครั้งค่ะ',
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