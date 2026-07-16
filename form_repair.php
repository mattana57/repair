<?php
session_start();
include 'db_connect.php';

$error_msg = "";
$status_result = null;
$search_keyword = "";

// ================= จัดการการเข้าสู่ระบบ (Login) =================
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

// ================= จัดการการค้นหาสถานะ (Check Status) =================
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
    <title>MBS Smart Maintenance | คณะการบัญชีฯ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #0f172a; color: white; }
        /* Glassmorphism Effect */
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .input-dark { background: rgba(0, 0, 0, 0.2) !important; border: 1px solid #334155 !important; color: white !important; }
        .input-dark:focus { border-color: #38bdf8 !important; outline: none; box-shadow: 0 0 10px rgba(56, 189, 248, 0.3); }
        /* Modal Style */
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4 md:p-8 relative selection:bg-sky-500 selection:text-white">

<div class="max-w-xl mx-auto relative z-10">
    <!-- Header -->
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-sky-400 to-blue-600 bg-clip-text text-transparent">MBS MAINTENANCE</h1>
        <p class="text-slate-400 mt-1">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
    </div>

    <!-- เมนูเพิ่มเติม (ตรวจสอบสถานะ / เข้าสู่ระบบ) -->
    <div class="flex justify-center gap-3 mb-8">
        <button type="button" onclick="toggleModal('searchModal')" class="bg-slate-800 border border-slate-600 hover:bg-slate-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center shadow-md">
            <i class="fas fa-search mr-2 text-sky-400"></i> ตรวจสอบสถานะ
        </button>
        <button type="button" onclick="toggleModal('loginModal')" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-all shadow-lg shadow-sky-500/30 flex items-center">
            <i class="fas fa-user-lock mr-2"></i> เจ้าหน้าที่
        </button>
    </div>

    <!-- ฟอร์ม -->
    <div class="glass-card p-6 md:p-8 rounded-3xl shadow-2xl">
        <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
            
            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">ชื่อ-นามสกุล (ผู้แจ้ง)</label>
                <input type="text" name="reporter_name" class="w-full p-4 rounded-2xl input-dark" required placeholder="ระบุชื่อจริงของคุณ">
            </div>

            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">อุปกรณ์ที่มีปัญหา</label>
                <select name="equipment_type" id="equipSelect" class="w-full p-4 rounded-2xl input-dark" onchange="checkOther()">
                    <option value="แอร์">แอร์</option>
                    <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                    <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                    <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                    <option value="ไมค์">ไมค์</option>
                    <option value="other">อื่นๆ (ระบุ...)</option>
                </select>
                <input type="text" name="other_equip" id="otherInput" class="w-full p-4 rounded-2xl mt-2 hidden input-dark" placeholder="ระบุชื่ออุปกรณ์">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">เลือกตึก</label>
                    <select name="building" class="w-full p-4 rounded-2xl input-dark">
                        <option value="SBB">SBB</option>
                        <option value="ACC.BIZ">ACC.BIZ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">เลขห้อง</label>
                    <input type="text" name="room_no" class="w-full p-4 rounded-2xl input-dark" placeholder="เช่น 303" required>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">เบอร์ติดต่อกลับ</label>
                <input type="tel" name="phone_number" class="w-full p-4 rounded-2xl input-dark" required placeholder="08x-xxx-xxxx">
            </div>

            <div class="mb-5">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">อาการเสีย / รายละเอียด</label>
                <textarea name="problem_desc" class="w-full p-4 rounded-2xl input-dark" rows="3" required placeholder="อธิบายปัญหาที่พบ..."></textarea>
            </div>

            <div class="mb-6">
                <label class="block text-xs uppercase tracking-widest text-slate-400 mb-2">แนบภาพประกอบ</label>
                <input type="file" name="image_before" class="w-full p-4 rounded-2xl input-dark text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-sky-400 hover:file:bg-slate-600 cursor-pointer" accept="image/*">
            </div>

            <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white p-5 rounded-2xl font-bold shadow-lg hover:shadow-sky-500/50 transition-all">
                ส่งรายการแจ้งซ่อม
            </button>
        </form>
    </div>
</div>

<!-- ================== MODALS ================== -->

<!-- Modal: ค้นหาสถานะ -->
<div id="searchModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
    <div class="absolute w-full h-full bg-slate-900/80 backdrop-blur-sm" onclick="toggleModal('searchModal')"></div>
    <div class="glass-card w-full max-w-md mx-auto rounded-3xl z-50 overflow-hidden text-white shadow-2xl shadow-black">
        <div class="p-6 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-xl font-bold"><i class="fas fa-search text-sky-400 mr-2"></i> ตรวจสอบสถานะ</h2>
            <button onclick="toggleModal('searchModal')" class="text-slate-400 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="check_status" value="1">
            <div>
                <label class="block text-sm text-slate-300 mb-2">เลขที่ใบงาน หรือ ชื่อ-นามสกุลผู้แจ้ง</label>
                <input type="text" name="search_query" required class="w-full p-4 rounded-2xl input-dark text-base" placeholder="เช่น MR-2026... หรือ สมชาย">
            </div>
            <button type="submit" class="w-full mt-2 bg-sky-600 hover:bg-sky-500 text-white py-3.5 rounded-2xl font-bold shadow-lg shadow-sky-500/30 transition-all">ค้นหาประวัติ</button>
        </form>
    </div>
</div>

<!-- Modal: แสดงผลค้นหา -->
<div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
    <div class="absolute w-full h-full bg-slate-900/80 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
    <div class="glass-card w-full max-w-2xl mx-auto rounded-3xl z-50 overflow-hidden text-white flex flex-col max-h-[85vh] shadow-2xl shadow-black">
        <div class="p-5 border-b border-slate-700 flex justify-between items-center shrink-0">
            <h2 class="text-lg md:text-xl font-bold"><i class="fas fa-list-alt text-sky-400 mr-2"></i> ผลการค้นหา</h2>
            <button onclick="toggleModal('resultModal')" class="text-slate-400 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-4 md:p-6 overflow-y-auto flex-1 space-y-4">
            <?php if (is_array($status_result)): ?>
                <?php foreach($status_result as $res): 
                    $badgeClass = "bg-slate-700 border-slate-500 text-white";
                    if($res['status'] == 'รอรับเรื่อง') $badgeClass = "bg-amber-500/20 border-amber-500/50 text-amber-400";
                    elseif($res['status'] == 'กำลังดำเนินการ') $badgeClass = "bg-sky-500/20 border-sky-500/50 text-sky-400";
                    elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $badgeClass = "bg-emerald-500/20 border-emerald-500/50 text-emerald-400";
                ?>
                    <div class="bg-slate-800/80 p-4 md:p-5 rounded-2xl border border-slate-700 relative overflow-hidden">
                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-2 mb-3 border-b border-slate-700/50 pb-3">
                            <div>
                                <p class="text-[10px] md:text-xs text-slate-400 uppercase tracking-widest">เลขที่ใบงาน</p>
                                <h3 class="text-base md:text-lg font-bold text-sky-400"><?php echo $res['ticket_no']; ?></h3>
                            </div>
                            <div class="text-left md:text-right">
                                <span class="inline-block px-3 py-1 md:py-1.5 rounded-full text-xs font-bold border <?php echo $badgeClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs md:text-sm text-slate-300">
                            <div>
                                <p class="mb-1"><b class="text-slate-400">อุปกรณ์:</b> <?php echo $res['equipment_type']; ?></p>
                                <p><b class="text-slate-400">ผู้แจ้ง:</b> <?php echo $res['reporter_name']; ?></p>
                            </div>
                            <div>
                                <p class="mb-1"><b class="text-slate-400">ผู้รับผิดชอบ:</b> <span class="text-sky-300"><?php echo !empty($res['technician_name']) ? $res['technician_name'] : '- ยังไม่ระบุ -'; ?></span></p>
                                <p><b class="text-slate-400">วันที่แจ้ง:</b> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                            </div>
                        </div>
                        <?php if(!empty($res['repair_note'])): ?>
                        <div class="mt-3 p-3 bg-slate-900/50 rounded-xl border border-slate-700/50 text-xs md:text-sm">
                            <b class="text-slate-400 block mb-1">หมายเหตุจากช่าง:</b> <?php echo $res['repair_note']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="p-4 border-t border-slate-700 flex justify-center shrink-0">
            <button onclick="toggleModal('resultModal')" class="bg-slate-700 hover:bg-slate-600 text-white px-6 py-2 rounded-xl text-sm font-bold transition-colors">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<!-- Modal: เข้าสู่ระบบ (Login) -->
<div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
    <div class="absolute w-full h-full bg-slate-900/80 backdrop-blur-sm" onclick="toggleModal('loginModal')"></div>
    <div class="glass-card w-full max-w-md mx-auto rounded-3xl z-50 overflow-hidden text-white shadow-2xl shadow-black">
        <div class="p-6 border-b border-slate-700 flex justify-between items-center">
            <h2 class="text-xl font-bold"><i class="fas fa-user-lock text-sky-400 mr-2"></i> เข้าสู่ระบบเจ้าหน้าที่</h2>
            <button onclick="toggleModal('loginModal')" class="text-slate-400 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form action="" method="POST" class="p-6 space-y-5">
            <input type="hidden" name="login" value="1">
            <div>
                <label class="block text-sm text-slate-300 mb-2">Username</label>
                <input type="text" name="username" required class="w-full p-4 rounded-2xl input-dark text-base" placeholder="ชื่อผู้ใช้งาน">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-2">Password</label>
                <input type="password" name="password" required class="w-full p-4 rounded-2xl input-dark text-base" placeholder="รหัสผ่าน">
            </div>
            <button type="submit" class="w-full mt-2 bg-sky-600 hover:bg-sky-500 text-white py-3.5 rounded-2xl font-bold shadow-lg shadow-sky-500/30 transition-all">เข้าสู่ระบบ</button>
        </form>
    </div>
</div>

<!-- Scripts -->
<script>
    function checkOther() {
        const select = document.getElementById('equipSelect');
        const input = document.getElementById('otherInput');
        input.classList.toggle('hidden', select.value !== 'other');
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
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#0284c7'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: urlParams.get('msg'),
                background: '#1e293b',
                color: '#fff',
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
            background: '#1e293b',
            color: '#fff',
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
            background: '#1e293b',
            color: '#fff',
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