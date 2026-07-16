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
    <title>MBS Smart Maintenance | คณะการบัญชีและการจัดการ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; color: #334155; }
        .bg-pattern { background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 20px 20px; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
        
        /* สไตล์ช่องกรอกข้อมูลให้ดูคลีนๆ */
        .input-light { background-color: #f8fafc; border: 1px solid #e2e8f0; color: #334155; transition: all 0.3s ease; }
        .input-light:focus { border-color: #38bdf8; outline: none; box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.15); background-color: #ffffff; }
    </style>
</head>
<body class="bg-pattern min-h-screen flex flex-col selection:bg-sky-200 relative">

    <!-- Navbar -->
    <header class="w-full glass-card sticky top-0 z-40 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3 md:gap-4">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center shadow-lg shadow-sky-500/30 shrink-0">
                    <i class="fas fa-tools text-white text-lg md:text-xl"></i>
                </div>
                <div>
                    <h1 class="text-lg md:text-xl font-bold text-slate-800 leading-tight">MBS REPAIR</h1>
                    <p class="text-[10px] md:text-xs text-sky-500 font-semibold tracking-widest uppercase mt-0.5">คณะการบัญชีและการจัดการ</p>
                </div>
            </div>
            <div class="flex items-center gap-2 md:gap-3">
                <button onclick="toggleModal('searchModal')" class="bg-white border border-slate-200 text-slate-600 hover:text-sky-600 hover:bg-sky-50 hover:border-sky-200 px-3 md:px-5 py-2 md:py-2.5 rounded-xl text-sm font-bold shadow-sm transition-all flex items-center">
                    <i class="fas fa-search md:mr-2"></i> <span class="hidden md:inline">ตรวจสอบสถานะ</span>
                </button>
                <button onclick="toggleModal('loginModal')" class="bg-slate-800 hover:bg-slate-700 text-white px-3 md:px-5 py-2 md:py-2.5 rounded-xl text-sm font-bold shadow-md transition-all flex items-center">
                    <i class="fas fa-user-lock md:mr-2"></i> <span class="hidden md:inline">เจ้าหน้าที่</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content (Hero & Form) -->
    <main class="flex-1 flex items-center py-8 md:py-12 relative z-10 px-4 md:px-6">
        <div class="absolute inset-0 bg-gradient-to-b from-sky-50/50 to-transparent -z-10"></div>
        
        <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
            
            <!-- ฝั่งซ้าย: ข้อความต้อนรับ -->
            <div class="lg:col-span-5 space-y-6 md:space-y-8 lg:sticky lg:top-32">
                <div class="inline-block px-4 py-1.5 rounded-full bg-sky-100 text-sky-700 font-semibold text-sm border border-sky-200 shadow-sm">
                    <i class="fas fa-bolt text-amber-500 mr-2"></i> ระบบให้บริการแจ้งซ่อมออนไลน์
                </div>
                
                <h2 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-slate-800 leading-tight">
                    บริการรับแจ้งซ่อม <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-sky-400">สะดวกรวดเร็ว</span>
                </h2>
                
                <p class="text-base md:text-lg text-slate-600 leading-relaxed max-w-lg">
                    ระบบแจ้งซ่อมอุปกรณ์ คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ สำหรับบุคลากรและนิสิต <b>คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</b>
                </p>

                <div class="grid grid-cols-3 gap-4 pt-6 border-t border-slate-200 max-w-md">
                    <div>
                        <p class="text-2xl md:text-3xl font-black text-sky-600">24/7</p>
                        <p class="text-xs md:text-sm text-slate-500 font-medium mt-1">รับเรื่องตลอดเวลา</p>
                    </div>
                    <div>
                        <p class="text-2xl md:text-3xl font-black text-sky-600">100%</p>
                        <p class="text-xs md:text-sm text-slate-500 font-medium mt-1">ติดตามผลออนไลน์</p>
                    </div>
                    <div>
                        <p class="text-2xl md:text-3xl font-black text-sky-600">Fast</p>
                        <p class="text-xs md:text-sm text-slate-500 font-medium mt-1">ดำเนินการรวดเร็ว</p>
                    </div>
                </div>
            </div>

            <!-- ฝั่งขวา: ฟอร์มแจ้งซ่อม (Theme สว่าง) -->
            <div class="lg:col-span-7">
                <div class="bg-white p-6 md:p-10 rounded-[2rem] shadow-xl border border-slate-100 relative overflow-hidden">
                    <!-- ลวดลายตกแต่งมุมขวาบนของฟอร์ม -->
                    <div class="absolute top-0 right-0 -mt-16 -mr-16 w-48 h-48 bg-gradient-to-br from-sky-100 to-blue-50 rounded-full blur-3xl opacity-60 -z-10"></div>

                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-slate-800 mb-2"><i class="fas fa-edit text-sky-500 mr-2"></i> กรอกรายละเอียดการแจ้งซ่อม</h3>
                        <p class="text-slate-500 text-sm">ข้อมูลที่มีเครื่องหมาย <span class="text-red-500">*</span> จำเป็นต้องกรอก</p>
                    </div>

                    <form action="submit_repair.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                        
                        <!-- ชื่อผู้แจ้ง -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">ชื่อ-นามสกุล (ผู้แจ้ง) <span class="text-red-500">*</span></label>
                            <input type="text" name="reporter_name" required placeholder="ระบุชื่อจริงของคุณ" class="w-full p-3.5 rounded-xl input-light">
                        </div>

                        <!-- อุปกรณ์ -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">อุปกรณ์ที่มีปัญหา <span class="text-red-500">*</span></label>
                            <select name="equipment_type" id="equipSelect" required class="w-full p-3.5 rounded-xl input-light appearance-none cursor-pointer" onchange="checkOther()">
                                <option value="" disabled selected>-- เลือกอุปกรณ์ --</option>
                                <option value="แอร์">แอร์</option>
                                <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                                <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                                <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                                <option value="ไมค์">ไมค์</option>
                                <option value="other">อื่นๆ (ระบุ...)</option>
                            </select>
                            <input type="text" name="other_equip" id="otherInput" placeholder="ระบุชื่ออุปกรณ์" class="w-full mt-3 p-3.5 rounded-xl input-light hidden">
                        </div>

                        <!-- ตึกและห้อง -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">เลือกอาคาร <span class="text-red-500">*</span></label>
                                <select name="building" required class="w-full p-3.5 rounded-xl input-light appearance-none cursor-pointer">
                                    <option value="" disabled selected>-- เลือกอาคาร --</option>
                                    <option value="SBB">SBB</option>
                                    <option value="ACC.BIZ">ACC.BIZ</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">เลขห้อง <span class="text-red-500">*</span></label>
                                <input type="text" name="room_no" required placeholder="เช่น 303" class="w-full p-3.5 rounded-xl input-light">
                            </div>
                        </div>

                        <!-- เบอร์ติดต่อ -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">เบอร์ติดต่อกลับ <span class="text-red-500">*</span></label>
                            <input type="tel" name="phone_number" required placeholder="08x-xxx-xxxx" class="w-full p-3.5 rounded-xl input-light">
                        </div>

                        <!-- อาการเสีย -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">อาการเสีย / รายละเอียด <span class="text-red-500">*</span></label>
                            <textarea name="problem_desc" rows="3" required placeholder="อธิบายปัญหาที่พบเพื่อให้ช่างประเมินเบื้องต้น..." class="w-full p-3.5 rounded-xl input-light resize-none"></textarea>
                        </div>

                        <!-- แนบภาพ -->
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">แนบภาพประกอบ <span class="text-slate-400 font-normal">(ถ้ามี)</span></label>
                            <input type="file" name="image_before" accept="image/*" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-600 focus:outline-none focus:border-sky-400 transition-all file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-100 file:text-sky-700 hover:file:bg-sky-200 cursor-pointer">
                        </div>

                        <button type="submit" class="w-full mt-6 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-700 hover:to-sky-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-sky-500/30 transition-all transform hover:-translate-y-1">
                            ส่งรายการแจ้งซ่อม <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <!-- ================== MODALS ================== -->

    <!-- Modal: ค้นหาสถานะ (Search) -->
    <div id="searchModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('searchModal')"></div>
        <div class="bg-white w-full max-w-md mx-auto rounded-3xl z-50 overflow-hidden shadow-2xl transform transition-all">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 relative">
                <div class="w-12 h-12 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center text-xl mr-4 shrink-0">
                    <i class="fas fa-search"></i>
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-slate-800">ตรวจสอบสถานะ</h2>
                    <p class="text-xs text-slate-500">กรอกเลขที่ใบงาน หรือ ชื่อ-นามสกุล</p>
                </div>
                <button type="button" onclick="toggleModal('searchModal')" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="check_status" value="1">
                <div>
                    <input type="text" name="search_query" required class="w-full p-4 rounded-xl input-light text-base" placeholder="เช่น MR-2026... หรือ สมชาย">
                </div>
                <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white py-3.5 rounded-xl font-bold shadow-lg shadow-sky-500/30 transition-all">ค้นหาประวัติ</button>
            </form>
        </div>
    </div>

    <!-- Modal: แสดงผลการค้นหา (Results) -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
        <div class="bg-white w-full max-w-2xl mx-auto rounded-3xl z-50 overflow-hidden shadow-2xl flex flex-col max-h-[85vh] transform transition-all">
            <div class="p-5 md:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-slate-800"><i class="fas fa-list-alt text-sky-500 mr-2"></i> ผลการค้นหา</h2>
                    <p class="text-xs md:text-sm text-slate-500 mt-1">คำค้นหา: <span class="font-bold text-sky-600">"<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"</span></p>
                </div>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-4 md:p-6 overflow-y-auto flex-1 space-y-4 bg-slate-50/50">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $badgeClass = "bg-slate-100 border-slate-200 text-slate-600";
                        if($res['status'] == 'รอรับเรื่อง') $badgeClass = "bg-amber-50 border-amber-200 text-amber-600";
                        elseif($res['status'] == 'กำลังดำเนินการ') $badgeClass = "bg-sky-50 border-sky-200 text-sky-600";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $badgeClass = "bg-emerald-50 border-emerald-200 text-emerald-600";
                    ?>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php echo str_replace(['bg-', 'text-', 'border-'], ['bg-', 'bg-', 'bg-'], explode(' ', $badgeClass)[0]); ?>"></div>
                            
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4 border-b border-slate-100 pb-3 pl-2">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">เลขที่ใบงาน</p>
                                    <h3 class="text-lg font-bold text-sky-700"><?php echo $res['ticket_no']; ?></h3>
                                </div>
                                <div class="text-left md:text-right">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold border <?php echo $badgeClass; ?>">
                                        <?php echo $res['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="pl-2 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-slate-600">
                                <div>
                                    <p class="mb-1"><i class="fas fa-desktop text-slate-400 w-4 text-center mr-1"></i> <b class="text-slate-700">อุปกรณ์:</b> <?php echo $res['equipment_type']; ?></p>
                                    <p><i class="fas fa-user text-slate-400 w-4 text-center mr-1"></i> <b class="text-slate-700">ผู้แจ้ง:</b> <?php echo $res['reporter_name']; ?></p>
                                </div>
                                <div>
                                    <p class="mb-1"><i class="fas fa-hard-hat text-slate-400 w-4 text-center mr-1"></i> <b class="text-slate-700">ผู้รับผิดชอบ:</b> <span class="font-medium <?php echo !empty($res['technician_name']) ? 'text-indigo-600' : 'text-slate-400'; ?>"><?php echo !empty($res['technician_name']) ? $res['technician_name'] : '- ยังไม่ระบุ -'; ?></span></p>
                                    <p><i class="far fa-clock text-slate-400 w-4 text-center mr-1"></i> <b class="text-slate-700">วันที่แจ้ง:</b> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if(!empty($res['repair_note'])): ?>
                            <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-slate-100 text-sm text-slate-600 pl-2">
                                <b class="text-slate-700 block mb-1"><i class="fas fa-comment-dots text-slate-400 mr-1"></i> หมายเหตุจากช่าง:</b> <?php echo $res['repair_note']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="p-4 border-t border-slate-100 flex justify-center shrink-0 bg-white">
                <button onclick="toggleModal('resultModal')" class="bg-slate-800 hover:bg-slate-700 text-white px-8 py-2.5 rounded-xl text-sm font-bold shadow-md transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Modal: เข้าสู่ระบบ (Login) -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
        <div class="bg-white w-full max-w-md mx-auto rounded-3xl z-50 overflow-hidden shadow-2xl transform transition-all">
            <div class="p-8 text-center border-b border-slate-100 relative bg-slate-50">
                <button type="button" onclick="toggleModal('loginModal')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-16 h-16 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg shadow-blue-500/30">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">เข้าสู่ระบบเจ้าหน้าที่</h2>
                <p class="text-sm text-slate-500 mt-2">สำหรับ Admin, ผู้บริหาร และทีมช่างซ่อม</p>
            </div>
            <form action="" method="POST" class="p-8 space-y-5">
                <input type="hidden" name="login" value="1">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Username</label>
                    <input type="text" name="username" required class="w-full p-4 rounded-xl input-light text-base" placeholder="ชื่อผู้ใช้งาน">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Password</label>
                    <input type="password" name="password" required class="w-full p-4 rounded-xl input-light text-base" placeholder="รหัสผ่าน">
                </div>
                <button type="submit" class="w-full mt-2 bg-slate-800 hover:bg-slate-700 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition-all">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function checkOther() {
            const select = document.getElementById('equipSelect');
            const input = document.getElementById('otherInput');
            if(select.value === 'other') {
                input.classList.remove('hidden');
                input.required = true;
            } else {
                input.classList.add('hidden');
                input.required = false;
            }
        }

        function toggleModal(modalID) {
            document.getElementById(modalID).classList.toggle('opacity-0');
            document.getElementById(modalID).classList.toggle('pointer-events-none');
            document.body.classList.toggle('modal-active');
        }

        // กรณีแจ้งซ่อมสำเร็จ/ผิดพลาด
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status')) {
            const status = urlParams.get('status');
            if (status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'แจ้งซ่อมสำเร็จ!',
                    text: 'เลขที่ใบงาน: ' + urlParams.get('ticket'),
                    confirmButtonColor: '#0284c7'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: urlParams.get('msg'),
                    confirmButtonColor: '#ef4444'
                });
            }
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>

    <?php if(!empty($error_msg)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'เข้าสู่ระบบไม่สำเร็จ',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#ef4444'
            }).then(() => toggleModal('loginModal'));
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบข้อมูล',
                text: 'ไม่พบประวัติการแจ้งซ่อม กรุณาตรวจสอบเลขที่ใบงานหรือชื่อผู้แจ้งอีกครั้ง',
                confirmButtonColor: '#0ea5e9'
            }).then(() => toggleModal('searchModal'));
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