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
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@200;300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        darkBg: '#2a3036',
                        darkCard: '#373f47',
                        accentYellow: '#fde047',
                        accentHover: '#eab308'
                    },
                    fontFamily: {
                        kanit: ['Kanit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        body { background-color: #2a3036; color: #e2e8f0; overflow-x: hidden; }
        .modal { transition: opacity 0.3s ease-in-out; }
        body.modal-active { overflow: hidden; }
        
        /* Glass Dark Elements */
        .glass-nav {
            background: rgba(42, 48, 54, 0.6);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .glass-card-dark {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { animation: fadeUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }

        /* Custom Cut Button */
        .btn-cut {
            clip-path: polygon(0 0, 100% 0, 100% 70%, 90% 100%, 0 100%);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-accentYellow selection:text-darkBg font-kanit">

    <!-- Header (Sleek Glass Dark) -->
    <header class="w-full glass-nav fixed top-0 z-40">
        <div class="max-w-[1400px] mx-auto px-6 md:px-10 h-20 flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="text-accentYellow text-2xl">
                    <i class="fas fa-bolt"></i>
                </div>
                <h1 class="text-xl font-medium text-white tracking-wide">MBS Vault</h1>
            </div>
            
            <!-- Navigation -->
            <div class="flex items-center gap-10">
                <nav class="hidden md:flex items-center gap-8 text-sm font-light text-slate-300">
                    <a href="#" class="hover:text-white transition-colors">Home</a>
                    <a href="#categories" class="hover:text-white transition-colors">Services</a>
                    <a href="#" class="hover:text-white transition-colors">Tracking</a>
                </nav>
                <button onclick="toggleModal('loginModal')" class="bg-accentYellow hover:bg-accentHover text-darkBg px-8 py-2.5 font-semibold text-sm transition-colors btn-cut flex items-center shadow-[0_0_15px_rgba(253,224,71,0.3)] hover:shadow-[0_0_20px_rgba(253,224,71,0.5)]">
                    STAFF LOGIN <i class="fas fa-caret-right ml-2 text-lg leading-none"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section (Giant Thin Text & Centered Image) -->
    <main class="pt-[120px] pb-16 w-full px-4 md:px-8 max-w-[1400px] mx-auto relative z-10 flex flex-col items-center animate-fade-up">
        
        <!-- Massive Typography -->
        <div class="flex flex-wrap justify-center items-center gap-4 text-5xl md:text-7xl lg:text-[7rem] font-light text-white tracking-tight mb-8 w-full text-center">
            <span class="font-extralight tracking-wide text-slate-200">MBS</span> 
            <i class="fas fa-bolt text-accentYellow text-4xl md:text-6xl lg:text-[6rem] drop-shadow-[0_0_30px_rgba(253,224,71,0.4)]"></i> 
            <span class="font-light tracking-wide">REPAIR</span>
        </div>

        <!-- Central Image Showcase -->
        <div class="relative w-full max-w-5xl mx-auto h-[350px] md:h-[500px] rounded-[2rem] border border-white/10 shadow-2xl mt-4 mb-10 group overflow-hidden bg-darkBg">
            <!-- Overlay to blend image -->
            <div class="absolute inset-0 bg-gradient-to-t from-darkBg via-darkBg/20 to-transparent z-10"></div>
            
            <img src="uploads/mbs_bg.jpg?v=10" alt="MBS Building" class="w-full h-full object-cover object-center transform group-hover:scale-105 transition-transform duration-1000 ease-out opacity-80 mix-blend-luminosity">

            <!-- Floating Schematic Tags -->
            <div class="absolute top-[20%] left-[10%] z-20 flex items-center gap-2">
                <div class="w-2 h-2 bg-accentYellow rounded-full animate-pulse shadow-[0_0_10px_#fde047]"></div>
                <div class="border border-white/20 bg-darkBg/60 backdrop-blur-md px-3 py-1 rounded-sm text-[10px] text-white tracking-widest uppercase">IT Support</div>
                <div class="w-16 h-px bg-white/20 hidden md:block"></div>
            </div>
            
            <div class="absolute bottom-[30%] right-[10%] z-20 flex items-center gap-2 flex-row-reverse">
                <div class="w-2 h-2 bg-accentYellow rounded-full shadow-[0_0_10px_#fde047]"></div>
                <div class="border border-white/20 bg-darkBg/60 backdrop-blur-md px-3 py-1 rounded-sm text-[10px] text-white tracking-widest uppercase">Maintenance</div>
                <div class="w-16 h-px bg-white/20 hidden md:block"></div>
            </div>

            <!-- Call to Action Box inside Image Area -->
            <div class="absolute left-6 md:left-12 bottom-6 md:bottom-12 z-20 glass-card-dark p-6 rounded-2xl max-w-[280px]">
                <h3 class="text-white font-medium mb-2 leading-tight">Ready to assist you with fast and reliable service.</h3>
                <a href="form_repair.php" class="text-accentYellow text-xs font-bold uppercase tracking-widest hover:text-white transition-colors flex items-center mt-4">
                    Take Order <div class="w-8 h-px bg-accentYellow ml-2"></div>
                </a>
            </div>
        </div>

        <!-- Prominent Search Bar (High Visibility) -->
        <div class="w-full max-w-3xl mx-auto -mt-20 md:-mt-24 z-30 relative animate-fade-up delay-100">
            <div class="bg-white rounded-2xl shadow-[0_20px_50px_-15px_rgba(0,0,0,0.5)] p-2 border border-slate-200">
                <form action="" method="POST" class="flex flex-col sm:flex-row items-stretch gap-2">
                    <input type="hidden" name="check_status" value="1">
                    <div class="flex-1 flex items-center px-4 py-3 bg-slate-50 rounded-xl focus-within:bg-white border border-transparent focus-within:border-accentYellow transition-colors cursor-text">
                        <i class="fas fa-search text-slate-400 mr-3 text-lg"></i>
                        <div class="w-full">
                            <p class="text-[10px] font-bold tracking-widest text-slate-500 mb-0.5 uppercase">ตรวจสอบสถานะ (Check Status)</p>
                            <!-- สีข้อความเข้มชัดเจน พื้นหลังสีขาวสว่าง -->
                            <input type="text" id="searchInput" name="search_query" required placeholder="พิมพ์เลขใบงาน หรือ ชื่อผู้แจ้ง" class="w-full text-base focus:outline-none text-slate-900 placeholder-slate-400 bg-transparent font-medium">
                        </div>
                    </div>
                    <button type="submit" class="bg-accentYellow hover:bg-accentHover text-darkBg px-10 py-4 sm:py-0 rounded-xl font-bold transition-colors uppercase tracking-widest text-sm flex items-center justify-center shadow-md">
                        ค้นหา
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Categories Section (Mixed Cards Style like Reference) -->
    <section id="categories" class="max-w-[1400px] mx-auto px-4 md:px-8 pt-10 pb-24 w-full animate-fade-up delay-200">
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Card 1 (Dark Glass Profile Style) -->
            <div class="glass-card-dark rounded-[2rem] p-8 flex flex-col justify-between group hover:bg-white/5 transition-colors min-h-[250px]">
                <div>
                    <h3 class="text-xl font-medium text-white mb-1">คอมพิวเตอร์</h3>
                    <p class="text-xs text-slate-400 font-light">ซ่อมแซมและแก้ไขปัญหาซอฟต์แวร์</p>
                </div>
                <div class="flex items-end justify-between mt-8">
                    <div class="w-12 h-12 rounded-full bg-darkCard flex items-center justify-center text-accentYellow text-xl border border-white/10 group-hover:scale-110 transition-transform">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div class="flex flex-col items-end">
                        <i class="fas fa-star text-accentYellow text-[10px] mb-1"></i>
                        <span class="text-2xl font-light text-white leading-none">100%</span>
                    </div>
                </div>
            </div>
            
            <!-- Card 2 (Dark Clean Style) -->
            <div class="bg-darkCard rounded-[2rem] p-8 flex flex-col justify-between group hover:bg-slate-700 transition-colors border border-white/5 min-h-[250px]">
                <div class="text-center flex flex-col items-center">
                    <h3 class="text-xl font-medium text-white mb-1">ระบบเครือข่าย</h3>
                    <p class="text-xs text-slate-400 font-light">แก้ไขปัญหาอินเทอร์เน็ตและ Wi-Fi</p>
                    <div class="w-16 h-16 mt-6 rounded-2xl bg-darkBg border border-white/10 flex items-center justify-center text-2xl text-white shadow-inner group-hover:text-accentYellow transition-colors">
                        <i class="fas fa-wifi"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3 (White Bright Style) -->
            <div class="bg-white rounded-[2rem] p-8 flex flex-col justify-between group hover:shadow-lg transition-all border border-slate-200 min-h-[250px] col-span-1 lg:col-span-2 relative overflow-hidden">
                <!-- Water drop / abstract decor -->
                <div class="absolute -right-4 -top-4 w-32 h-32 bg-slate-50 rounded-full blur-2xl z-0"></div>
                
                <div class="relative z-10 w-full lg:w-1/2">
                    <h3 class="text-2xl font-medium text-slate-900 mb-2">อาคารและไฟฟ้า</h3>
                    <p class="text-sm text-slate-500 font-light leading-relaxed">
                        ให้บริการซ่อมแซมประปา ประตู หน้าต่าง โต๊ะ หลอดไฟ ปลั๊กไฟ แอร์ และอุปกรณ์ไฟฟ้าภายในคณะ
                    </p>
                </div>
                
                <div class="relative z-10 flex items-end justify-between mt-8">
                    <div class="flex gap-3">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-700 text-xl border border-slate-200">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-700 text-xl border border-slate-200">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">MBS Facility</span>
                </div>
            </div>
            
        </div>
    </section>

    <!-- Footer (Minimal Dark) -->
    <footer class="w-full border-t border-white/10 mt-auto bg-darkBg">
        <div class="max-w-[1400px] mx-auto px-4 md:px-8 py-16">
            <div class="flex flex-col md:flex-row justify-between items-start gap-12">
                
                <!-- Brand Info -->
                <div class="w-full md:w-1/2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="text-accentYellow text-2xl"><i class="fas fa-bolt"></i></div>
                        <h2 class="text-xl font-medium text-white tracking-wide">MBS Vault</h2>
                    </div>
                    <p class="text-sm text-slate-400 font-light max-w-sm leading-relaxed">
                        ระบบรับแจ้งซ่อมออนไลน์สำหรับบุคลากรและนิสิต คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม
                    </p>
                </div>

                <!-- Developers -->
                <div class="w-full md:w-1/2 flex flex-col md:items-end text-left md:text-right space-y-2">
                    <p class="text-[10px] font-bold tracking-[0.2em] text-accentYellow uppercase mb-2">Graduation Project</p>
                    <p class="text-sm text-white font-light">นางสาวภัทรวดี ขามประโคน</p>
                    <p class="text-sm text-white font-light">นางสาวมัทนา รัตนแสง</p>
                    <p class="text-xs text-slate-500 font-light mt-2">นิสิตชั้นปีที่ 4 สาขาคอมพิวเตอร์ธุรกิจ<br>คณะการบัญชีและการจัดการ</p>
                </div>

            </div>
            
            <div class="mt-16 pt-6 border-t border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-[10px] text-slate-500 font-light tracking-widest uppercase">&copy; <?php echo date('Y'); ?> MBS Repair System.</p>
                <a href="https://www.msu.ac.th/" target="_blank" class="text-[10px] text-slate-500 font-light tracking-widest uppercase hover:text-accentYellow transition-colors">Mahasarakham University</a>
            </div>
        </div>
    </footer>

    <!-- Result Modal (Dark Theme matched) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-black/70 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-darkCard w-full md:max-w-2xl mx-auto shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] rounded-3xl border border-white/10">
            
            <div class="p-6 md:p-8 flex justify-between items-start border-b border-white/5 shrink-0 bg-darkBg/50">
                <div>
                    <span class="inline-block px-3 py-1 rounded-full bg-accentYellow/20 text-accentYellow text-[10px] font-bold tracking-widest uppercase mb-2">Search Result</span>
                    <h2 class="text-xl font-medium text-white">ข้อมูลการแจ้งซ่อม</h2>
                    <p class="text-xs text-slate-400 mt-1">คำค้นหา: <span class="text-white">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 text-slate-400 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 md:p-8 overflow-y-auto flex-1 bg-darkCard space-y-6">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        // Dark Theme Status Colors
                        $borderColor = "border-white/10"; 
                        $badgeBg = "bg-white/10 text-slate-300";
                        $iconColor = "text-slate-400";
                        $icon = "fa-file-alt";

                        if($res['status'] == 'รอรับเรื่อง') {
                            $borderColor = "border-yellow-500/30";
                            $badgeBg = "bg-yellow-500/20 text-yellow-400";
                            $iconColor = "text-yellow-400";
                            $icon = "fa-clock";
                        } elseif($res['status'] == 'กำลังดำเนินการ') {
                            $borderColor = "border-blue-500/30";
                            $badgeBg = "bg-blue-500/20 text-blue-400";
                            $iconColor = "text-blue-400";
                            $icon = "fa-tools";
                        } elseif($res['status'] == 'ซ่อมเสร็จแล้ว') {
                            $borderColor = "border-green-500/30";
                            $badgeBg = "bg-green-500/20 text-green-400";
                            $iconColor = "text-green-400";
                            $icon = "fa-check-circle";
                        }
                    ?>
                        <div class="rounded-2xl border <?php echo $borderColor; ?> p-6 relative bg-darkBg/30 shadow-inner">
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5 pb-4 border-b border-white/5">
                                <div class="flex items-center gap-4">
                                    <div class="text-xl <?php echo $iconColor; ?> w-10 h-10 rounded-full bg-white/5 flex items-center justify-center border border-white/5">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Ticket No.</p>
                                        <h3 class="text-lg font-medium text-white"><?php echo $res['ticket_no']; ?></h3>
                                    </div>
                                </div>
                                <div>
                                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest <?php echo $badgeBg; ?>">
                                        <?php echo $res['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6 text-sm">
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">อุปกรณ์</p>
                                    <p class="font-light text-slate-300"><?php echo $res['equipment_type']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">ผู้แจ้ง</p>
                                    <p class="font-light text-slate-300"><?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">ช่างดูแล</p>
                                    <p class="font-light <?php echo !empty($res['technician_name']) ? 'text-accentYellow' : 'text-slate-500 italic'; ?>">
                                        <?php echo !empty($res['technician_name']) ? $res['technician_name'] : 'ยังไม่ระบุช่าง'; ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">วันที่แจ้ง</p>
                                    <p class="font-light text-slate-300"><?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if(!empty($res['repair_note'])): ?>
                            <div class="mt-4 pt-4 border-t border-white/5">
                                <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-2">หมายเหตุจากช่าง</p>
                                <p class="text-sm text-slate-400 bg-black/20 p-3 rounded-lg border border-white/5 font-light"><?php echo $res['repair_note']; ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="p-6 bg-darkBg/50 border-t border-white/5 shrink-0 flex justify-center">
                <button onclick="toggleModal('resultModal')" class="bg-white/5 border border-white/10 hover:bg-white/10 text-white px-10 py-3 btn-cut font-semibold text-xs transition-colors">CLOSE</button>
            </div>
        </div>
    </div>

    <!-- Login Modal (Dark Theme matched) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-black/70 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-darkCard w-full max-w-sm mx-auto shadow-2xl z-50 overflow-hidden transform transition-all rounded-3xl border border-white/10">
            
            <div class="p-8 text-center relative border-b border-white/5">
                <button onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/5 text-slate-400 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors border border-white/10">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-14 h-14 rounded-full bg-darkBg border border-white/10 text-accentYellow flex items-center justify-center text-xl mx-auto mb-4">
                    <i class="fas fa-fingerprint"></i>
                </div>
                <h2 class="text-xl font-medium text-white tracking-wide">Staff Access</h2>
                <p class="text-xs text-slate-400 mt-1 font-light">สำหรับเจ้าหน้าที่และผู้ดูแลระบบ</p>
            </div>

            <form action="" method="POST" class="p-8 pt-6">
                <input type="hidden" name="login" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                            <input type="text" name="username" required placeholder="Enter username" class="w-full pl-11 pr-4 py-3.5 bg-darkBg border border-white/10 text-white rounded-xl focus:outline-none focus:border-accentYellow focus:ring-1 focus:ring-accentYellow transition-colors text-sm font-light placeholder-slate-600">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-bold text-slate-400 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                                <i class="fas fa-lock text-sm"></i>
                            </div>
                            <input type="password" name="password" required placeholder="Enter password" class="w-full pl-11 pr-4 py-3.5 bg-darkBg border border-white/10 text-white rounded-xl focus:outline-none focus:border-accentYellow focus:ring-1 focus:ring-accentYellow transition-colors text-sm font-light placeholder-slate-600">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full mt-8 bg-accentYellow hover:bg-accentHover text-darkBg py-4 rounded-xl font-bold text-xs uppercase tracking-widest transition-colors shadow-[0_0_15px_rgba(253,224,71,0.2)]">
                    Authenticate
                </button>
            </form>
        </div>
    </div>

    <!-- Floating LINE Button -->
    <a href="https://line.me/R/ti/p/@941kflsc" target="_blank" class="fixed bottom-6 right-6 z-40 bg-[#00B900] hover:bg-[#009900] text-darkBg w-12 h-12 rounded-full flex items-center justify-center text-xl shadow-[0_0_15px_rgba(0,185,0,0.4)] transition-transform hover:scale-110 border-2 border-[#00B900]">
        <i class="fab fa-line text-white"></i> 
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
                title: 'Access Denied',
                text: '<?php echo $error_msg; ?>',
                background: '#373f47',
                color: '#ffffff',
                confirmButtonColor: '#fde047',
                confirmButtonText: '<span style="color:#0b1f4a; font-weight:bold;">TRY AGAIN</span>',
                customClass: { popup: 'rounded-3xl border border-white/10' }
            }).then(() => { toggleModal('loginModal'); });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Not Found',
                text: 'ไม่พบประวัติการแจ้งซ่อมจาก: "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>" กรุณาตรวจสอบอีกครั้ง',
                background: '#373f47',
                color: '#ffffff',
                confirmButtonColor: '#fde047',
                confirmButtonText: '<span style="color:#0b1f4a; font-weight:bold;">OK</span>',
                customClass: { popup: 'rounded-3xl border border-white/10' }
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