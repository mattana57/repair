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
        .bg-pattern {
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex flex-col selection:bg-sky-200 relative">

    <header class="w-full glass-card fixed top-0 z-40 border-b border-slate-200/50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center shadow-lg shadow-sky-500/30">
                    <i class="fas fa-tools text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-800 leading-tight tracking-tight">MBS REPAIR</h1>
                    <p class="text-[11px] text-sky-500 font-semibold tracking-widest uppercase mt-0.5">คณะการบัญชีและการจัดการ</p>
                </div>
            </div>
            <div>
                <button onclick="toggleModal('loginModal')" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md transition-all flex items-center group">
                    <i class="fas fa-sign-in-alt mr-2 group-hover:translate-x-1 transition-transform"></i> เจ้าหน้าที่เข้าสู่ระบบ
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center pt-20 relative z-10">
        <div class="absolute inset-0 bg-gradient-to-b from-sky-50/50 to-transparent -z-10"></div>
        
        <div class="max-w-7xl mx-auto px-6 w-full grid grid-cols-1 lg:grid-cols-2 gap-12 items-center py-12">
            
            <div class="space-y-8 relative z-20">
                <div class="inline-block px-4 py-1.5 rounded-full bg-sky-100 text-sky-700 font-semibold text-sm border border-sky-200">
                    <i class="fas fa-bolt text-amber-500 mr-2"></i> ระบบให้บริการแจ้งซ่อมออนไลน์
                </div>
                
                <h2 class="text-5xl lg:text-6xl font-extrabold text-slate-800 leading-tight">
                    บริการรับแจ้งซ่อม <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-sky-400">สะดวกรวดเร็ว ติดตามผลได้</span>
                </h2>
                
                <p class="text-lg text-slate-600 leading-relaxed max-w-lg">
                    ระบบแจ้งซ่อมอุปกรณ์ คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ สำหรับบุคลากรและนิสิต <b>คณะการบัญชีและการจัดการ</b> มหาวิทยาลัยมหาสารคาม
                </p>
                
                <div class="flex flex-wrap items-center gap-4 pt-4">
                    <a href="form_repair.php" class="bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-700 hover:to-sky-600 text-white px-8 py-4 rounded-2xl font-bold text-lg shadow-lg shadow-sky-500/30 transition-all transform hover:-translate-y-1 flex items-center group">
                        <i class="fas fa-plus-circle mr-3 text-xl group-hover:rotate-90 transition-transform"></i> แจ้งซ่อมอุปกรณ์
                    </a>
                    
                    <button onclick="toggleModal('searchModal')" class="bg-white border-2 border-slate-200 text-slate-700 hover:border-sky-300 hover:text-sky-600 hover:bg-sky-50 px-8 py-4 rounded-2xl font-bold text-lg shadow-sm transition-all flex items-center">
                        <i class="fas fa-search mr-3 text-slate-400"></i> ตรวจสอบสถานะ
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-6 pt-8 border-t border-slate-200/60 max-w-lg">
                    <div>
                        <p class="text-3xl font-black text-slate-800">24/7</p>
                        <p class="text-sm text-slate-500 font-medium mt-1">รับเรื่องตลอดเวลา</p>
                    </div>
                    <div>
                        <p class="text-3xl font-black text-slate-800">100%</p>
                        <p class="text-sm text-slate-500 font-medium mt-1">ติดตามผลออนไลน์</p>
                    </div>
                    <div>
                        <p class="text-3xl font-black text-slate-800">Fast</p>
                        <p class="text-sm text-slate-500 font-medium mt-1">ดำเนินการรวดเร็ว</p>
                    </div>
                </div>
            </div>

            <div class="hidden lg:flex justify-center relative">
                <div class="absolute inset-0 bg-gradient-to-tr from-sky-200/40 to-purple-200/40 rounded-full blur-3xl -z-10 scale-90"></div>
                <img src="https://cdn-icons-png.flaticon.com/512/4144/4144883.png" alt="Maintenance Illustration" class="w-full max-w-md object-contain drop-shadow-xl opacity-90">
            </div>
        </div>
    </main>

    <!-- Modal 1: ค้นหาสถานะ (Search) -->
    <div id="searchModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('searchModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-lg mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all">
            
            <div class="p-6 text-center border-b border-slate-100 relative">
                <button onclick="toggleModal('searchModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-14 h-14 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fas fa-search"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">ตรวจสอบสถานะแจ้งซ่อม</h2>
                <p class="text-sm text-slate-500 mt-1">กรอกเลขที่ใบงาน หรือ ชื่อ-นามสกุล ผู้แจ้ง</p>
            </div>

            <form action="" method="POST" class="p-8 bg-slate-50">
                <input type="hidden" name="check_status" value="1">
                
                <div class="mb-6">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-clipboard-list text-slate-400"></i>
                        </div>
                        <input type="text" name="search_query" required placeholder="เช่น MR-2026... หรือ ชื่อผู้แจ้ง" class="w-full pl-11 pr-4 py-3.5 bg-white border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all font-medium text-lg shadow-sm">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white py-3.5 rounded-xl font-bold text-lg transition-all shadow-lg shadow-sky-600/30 flex items-center justify-center">
                    ค้นหาข้อมูล <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal 2: แสดงผลการค้นหาสถานะ (Results) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-2xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh]">
            
            <div class="p-6 flex justify-between items-center bg-slate-50 border-b border-slate-100 shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-slate-800"><i class="fas fa-list-alt text-sky-500 mr-2"></i> ผลการค้นหา</h2>
                    <p class="text-sm text-slate-500">คำค้นหา: <span class="font-bold text-sky-600">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
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
                        elseif($res['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";
                    ?>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                            <!-- แถบสีด้านซ้าย -->
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php echo str_replace(['bg-', 'text-', 'border-'], ['bg-', 'bg-', 'bg-'], explode(' ', $statusClass)[1]); ?>"></div>
                            
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-3 pl-2">
                                <div>
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">เลขที่ใบงาน</span>
                                    <h3 class="text-lg font-bold text-sky-700"><?php echo $res['ticket_no']; ?></h3>
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
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all">
            
            <div class="p-8 text-center bg-gradient-to-b from-sky-50 to-white border-b border-slate-100 relative">
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
                            <input type="text" name="username" required placeholder="กรอก Username ของคุณ" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all font-medium">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">รหัสผ่าน (Password)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400"></i>
                            </div>
                            <input type="password" name="password" required placeholder="กรอกรหัสผ่าน" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all font-medium">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-slate-800 hover:bg-slate-700 text-white py-3.5 rounded-xl font-bold text-lg transition-all shadow-lg hover:shadow-xl flex items-center justify-center">
                    เข้าสู่ระบบ <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Script สำหรับควบคุม Modal และ Alert -->
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
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบเลขที่ใบงานหรือชื่อผู้แจ้งอีกครั้งค่ะ',
                confirmButtonColor: '#0ea5e9'
            }).then(() => {
                toggleModal('searchModal');
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