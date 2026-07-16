<?php
session_start();
include 'db_connect.php';

$status_result = null;
$search_keyword = "";

// ================= จัดการการค้นหาสถานะ (Check Status) =================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_status'])) {
    $search_keyword = trim($_POST['search_query']);
    $search_param = "%" . $search_keyword . "%";

    // ค้นหาจาก "เลขที่ใบงาน" หรือ "ชื่อผู้แจ้ง"
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
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.5rem; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08); }
        .input-light { background-color: #f8fafc; border: 1px solid #e2e8f0; color: #334155; transition: all 0.3s ease; }
        .input-light:focus { border-color: #0ea5e9; outline: none; box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15); background-color: #ffffff; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4 md:p-8 relative selection:bg-sky-200">

<div class="max-w-xl mx-auto relative z-10">
    <!-- Header -->
    <div class="mb-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center shadow-lg shadow-sky-500/30 mx-auto mb-4">
            <i class="fas fa-tools text-white text-3xl"></i>
        </div>
        <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">MBS MAINTENANCE</h1>
        <p class="text-slate-500 font-medium mt-1">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
    </div>

    <!-- ปุ่มตรวจสอบสถานะ (ธีมสว่าง) -->
    <div class="flex justify-center mb-8">
        <button type="button" onclick="toggleModal('searchModal')" class="bg-white border border-slate-200 hover:border-sky-300 hover:bg-sky-50 text-slate-700 px-6 py-3 rounded-xl text-sm font-bold transition-all flex items-center shadow-sm">
            <i class="fas fa-search mr-2 text-sky-500"></i> ตรวจสอบสถานะการแจ้งซ่อม
        </button>
    </div>

    <!-- ฟอร์มแจ้งซ่อม (Light Theme) -->
    <div class="modern-card p-6 md:p-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6 border-b border-slate-100 pb-4"><i class="fas fa-edit text-sky-500 mr-2"></i> กรอกข้อมูลแจ้งซ่อม</h2>
        
        <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
            
            <div class="mb-5">
                <label class="block text-sm font-bold text-slate-700 mb-2">ชื่อ(ผู้แจ้ง) <span class="text-red-500">*</span></label>
                <input type="text" name="reporter_name" class="w-full p-3.5 rounded-xl input-light" required placeholder="กำลังดึงข้อมูลจาก LINE...">
            </div>

            <div class="mb-5">
                <label class="block text-sm font-bold text-slate-700 mb-2">อุปกรณ์ที่มีปัญหา <span class="text-red-500">*</span></label>
                <select name="equipment_type" id="equipSelect" class="w-full p-3.5 rounded-xl input-light appearance-none cursor-pointer" onchange="checkOther()" required>
                    <option value="" disabled selected>-- เลือกอุปกรณ์ --</option>
                    <option value="แอร์">แอร์</option>
                    <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                    <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                    <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                    <option value="ไมค์">ไมค์</option>
                    <option value="other">อื่นๆ (ระบุ...)</option>
                </select>
                <input type="text" name="other_equip" id="otherInput" class="w-full p-3.5 rounded-xl mt-3 hidden input-light" placeholder="ระบุชื่ออุปกรณ์">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">เลือกอาคาร <span class="text-red-500">*</span></label>
                    <select name="building" class="w-full p-3.5 rounded-xl input-light appearance-none cursor-pointer" required>
                        <option value="" disabled selected>-- เลือกตึก --</option>
                        <option value="SBB">SBB</option>
                        <option value="ACC.BIZ">ACC.BIZ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">เลขห้อง <span class="text-red-500">*</span></label>
                    <input type="text" name="room_no" class="w-full p-3.5 rounded-xl input-light" placeholder="เช่น 303" required>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-sm font-bold text-slate-700 mb-2">เบอร์ติดต่อกลับ <span class="text-red-500">*</span></label>
                <input type="tel" name="phone_number" class="w-full p-3.5 rounded-xl input-light" required placeholder="08x-xxx-xxxx">
            </div>

            <div class="mb-5">
                <label class="block text-sm font-bold text-slate-700 mb-2">อาการเสีย / รายละเอียด <span class="text-red-500">*</span></label>
                <textarea name="problem_desc" class="w-full p-3.5 rounded-xl input-light resize-none" rows="3" required placeholder="อธิบายปัญหาที่พบ..."></textarea>
            </div>

            <div class="mb-8">
                <label class="block text-sm font-bold text-slate-700 mb-2">แนบภาพประกอบ <span class="text-slate-400 font-normal">(ถ้ามี)</span></label>
                <input type="file" name="image_before" class="w-full p-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-600 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-sky-100 file:text-sky-700 hover:file:bg-sky-200 cursor-pointer" accept="image/*">
            </div>

            <!-- ซ่อนช่องรับค่า LINE User ID ไว้ในฟอร์ม -->
            <input type="hidden" name="line_user_id" id="line_user_id" value="">

            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-700 hover:to-sky-600 text-white p-4 rounded-xl font-bold text-lg shadow-lg shadow-sky-500/30 transition-all transform hover:-translate-y-1 flex justify-center items-center">
                ส่งรายการแจ้งซ่อม <i class="fas fa-paper-plane ml-2"></i>
            </button>
        </form>
    </div>
</div>

<!-- ================== MODALS ================== -->

<!-- Modal: ค้นหาสถานะ -->
<div id="searchModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
    <div class="absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('searchModal')"></div>
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
                <input type="text" name="search_query" required class="w-full p-4 rounded-xl input-light text-base font-medium" placeholder="เช่น MR-2026... หรือ สมชาย">
            </div>
            <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white py-3.5 rounded-xl font-bold text-lg shadow-lg shadow-sky-500/30 transition-all">ค้นหาประวัติ</button>
        </form>
    </div>
</div>

<!-- Modal: แสดงผลค้นหา -->
<div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
    <div class="absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('resultModal')"></div>
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
                        
                        <div class="pl-2 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-slate-600">
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

<!-- Scripts -->
<script>
    function checkOther() {
        const select = document.getElementById('equipSelect');
        const input = document.getElementById('otherInput');
        input.classList.toggle('hidden', select.value !== 'other');
        input.required = (select.value === 'other');
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
                background: '#ffffff',
                color: '#334155',
                confirmButtonColor: '#0284c7'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: urlParams.get('msg'),
                background: '#ffffff',
                color: '#334155',
                confirmButtonColor: '#ef4444'
            });
        }
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>

<?php if($status_result === 'not_found'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'warning',
            title: 'ไม่พบข้อมูล',
            text: 'ไม่พบประวัติการแจ้งซ่อม กรุณาตรวจสอบเลขที่ใบงานหรือชื่อผู้แจ้งอีกครั้ง',
            background: '#ffffff',
            color: '#334155',
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

<!-- โหลด LINE LIFF SDK และฟังก์ชันจดจำข้อมูล -->
<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
    // ฟังก์ชันจัดการระบบจำข้อมูลที่กรอก (Local Storage) - จำเฉพาะเบอร์โทร
    function handleFormMemory() {
        const phoneInput = document.querySelector('input[name="phone_number"]');

        if (localStorage.getItem('mbs_saved_phone')) {
            phoneInput.value = localStorage.getItem('mbs_saved_phone');
        }

        // บันทึกเฉพาะเบอร์โทรตอนกดส่งฟอร์ม
        document.querySelector('form').addEventListener('submit', function() {
            localStorage.setItem('mbs_saved_phone', phoneInput.value);
        });
    }

    // ฟังก์ชันเริ่มการทำงานของระบบ LINE LIFF
    async function initializeLiff() {
        try {
            // 🚨 นำ LIFF ID มาใส่ตรงนี้
            await liff.init({ liffId: "2010615776-jmvGJZSx" });
            
            if (liff.isLoggedIn()) {
                const profile = await liff.getProfile();
                
                // แอบดึง User ID มาใส่ในช่องที่ซ่อนไว้
                document.getElementById('line_user_id').value = profile.userId;
                
                // ดึงชื่อ LINE มาใส่ในช่องเสมอ
                const nameInput = document.querySelector('input[name="reporter_name"]');
                if(nameInput) {
                    nameInput.value = profile.displayName; 
                }
            } else {
                liff.login();
            }
        } catch (err) {
            console.error('LIFF Initialization failed', err);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        handleFormMemory(); 
        initializeLiff();   
    });
</script>

</body>
</html>