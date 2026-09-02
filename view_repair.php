<?php
session_start();
include 'db_connect.php';

// ✨ เช็คสิทธิ์ว่าเป็น Executive หรือไม่ เพื่อใช้ซ่อนปุ่มและเปลี่ยนหน้ากลับ ✨
$is_executive = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'executive';

// ✨ ระบบคำนวณ URL สำหรับปุ่มกลับ ให้ตรงกับหน้าของแต่ละสิทธิ์ ✨
if(isset($_SERVER['HTTP_REFERER']) && (strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false || strpos($_SERVER['HTTP_REFERER'], 'executive_dashboard.php') !== false)) {
    $_SESSION['last_view_url'] = $_SERVER['HTTP_REFERER'];
}
$default_back = $is_executive ? 'executive_dashboard.php' : 'dashboard.php?tab=repairs';
$back_url = isset($_SESSION['last_view_url']) ? $_SESSION['last_view_url'] : $default_back;

$query_string = '';
if (isset($_GET['source'])) {
    $source = $_GET['source'];
    if ($source === 'tech_history' && !empty($_GET['tech'])) {
        $query_string = '&source=tech_history&tech=' . urlencode($_GET['tech']);
    } elseif ($source === 'reporter_history' && !empty($_GET['reporter'])) {
        $query_string = '&source=reporter_history&reporter=' . urlencode($_GET['reporter']);
    } elseif ($source === 'overview') {
        $query_string = '&source=overview';
    }
}

// ฟังก์ชันช่วยเซ็นเซอร์เบอร์โทรศัพท์
function formatCensoredPhone($phone) {
    $phone = trim((string)$phone);
    $clean_phone = str_replace('-', '', $phone);

    if (strlen($clean_phone) >= 9) {
        return substr($clean_phone, 0, 3) . '-XXX-' . substr($clean_phone, -4);
    } elseif (strlen($clean_phone) > 0) {
        return '- ซ่อนข้อมูล -';
    }
    return '- ไม่ระบุ -';
}

// ฟังก์ชันคำนวณเวลาที่ผ่านไป (Time Ago)
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 0) $diff = 0; 
    if ($diff < 60) return "เมื่อสักครู่";
    if ($diff < 3600) return floor($diff / 60) . " นาทีที่แล้ว";
    if ($diff < 86400) return floor($diff / 3600) . " ชั่วโมงที่แล้ว";
    if ($diff < 604800) return floor($diff / 86400) . " วันที่แล้ว";
    $thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $m = date('n', $time) - 1;
    $y = date('Y', $time) + 543;
    $d = date('j', $time);
    return "$d " . $thaiMonths[$m] . " $y";
}

// ดึงข้อมูลใบงาน
$repair = null;
$tech_phone = null; 
$repair_line_id = "";
$repair_real_name = "";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM repairs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $repair = $result->fetch_assoc();

    // ✨ ดึง ID LINE และ ชื่อจริง จากตาราง line_users
    $repair_line_id = $repair['reporter_name'];
    $repair_real_name = $repair['reporter_name'];
    if (!empty($repair['reporter_name'])) {
        $stmt_lu = $conn->prepare("SELECT line_display_name, real_name FROM line_users WHERE line_display_name = ? OR real_name = ? LIMIT 1");
        if ($stmt_lu) {
            $stmt_lu->bind_param("ss", $repair['reporter_name'], $repair['reporter_name']);
            $stmt_lu->execute();
            $res_lu = $stmt_lu->get_result();
            if ($row_lu = $res_lu->fetch_assoc()) {
                if (!empty($row_lu['line_display_name'])) $repair_line_id = $row_lu['line_display_name'];
                if (!empty($row_lu['real_name'])) $repair_real_name = $row_lu['real_name'];
            }
            $stmt_lu->close();
        }
    }

    // ดึงเบอร์โทรศัพท์ของช่างผู้รับผิดชอบจากตาราง users
    if (!empty($repair['technician_name'])) {
        $stmt_tech = $conn->prepare("SELECT phone FROM users WHERE full_name = ? LIMIT 1");
        if ($stmt_tech) {
            $stmt_tech->bind_param("s", $repair['technician_name']);
            $stmt_tech->execute();
            $res_tech = $stmt_tech->get_result();
            if ($row_tech = $res_tech->fetch_assoc()) {
                $tech_phone = $row_tech['phone'];
            }
            $stmt_tech->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดใบงาน | MBS MAINT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); }
        .bg-pattern { background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 20px 20px; }
    </style>
</head>
<body class="p-6 md:p-10 selection:bg-sky-200 relative">

    <div class="absolute inset-0 bg-pattern opacity-50 -z-10"></div>

    <div class="max-w-4xl mx-auto">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><i class="fas fa-file-alt text-sky-500 mr-2"></i> รายละเอียดใบงานแจ้งซ่อม</h1>
                <p class="text-slate-500 mt-1 text-sm">ข้อมูลการแจ้งซ่อมจากบุคลากร และบันทึกการปฏิบัติงานของช่าง</p>
            </div>
            <div class="flex gap-3 w-full sm:w-auto">
                
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="flex-1 sm:flex-none bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-md inline-flex items-center justify-center text-sm">
                    <i class="fas fa-times mr-2"></i> ปิดหน้าต่าง
                </a>
            </div>
        </div>

        <?php if($repair): 
            $statusColor = "bg-slate-100 text-slate-600 border-slate-200"; 
            $statusIcon = "fa-clock";
            if($repair['status'] == 'รอรับเรื่อง') { $statusColor = "bg-amber-50 text-amber-600 border-amber-200"; $statusIcon = "fa-clock"; }
            elseif($repair['status'] == 'กำลังดำเนินการ') { $statusColor = "bg-sky-50 text-sky-600 border-sky-200"; $statusIcon = "fa-tools"; $repair['status'] = 'ช่างรับเรื่องแจ้งซ่อมแล้ว';}
            elseif($repair['status'] == 'ซ่อมเสร็จแล้ว' || $repair['status'] == 'เสร็จสิ้น') { $statusColor = "bg-emerald-50 text-emerald-600 border-emerald-200"; $statusIcon = "fa-check-circle"; }
        ?>

        <div class="space-y-6">

            <div class="modern-card p-6 md:p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-1">รหัสใบงาน (Ticket No.)</p>
                    <h2 class="text-3xl font-extrabold text-sky-600 tracking-tight"><?php echo htmlspecialchars($repair['ticket_no']); ?></h2>
                    <p class="text-slate-500 text-sm mt-2"><i class="far fa-calendar-alt mr-1"></i> แจ้งเมื่อ: <?php echo !empty($repair['created_at']) ? date("d/m/Y เวลา H:i น.", strtotime($repair['created_at'])) : "-"; ?></p>
                </div>
                <div class="text-right">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-2 text-left md:text-right">สถานะปัจจุบัน</p>
                    <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold border <?php echo $statusColor; ?> shadow-sm">
                        <i class="fas <?php echo $statusIcon; ?> mr-2 text-lg"></i> <?php echo $repair['status']; ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- ฝั่งซ้าย: ข้อมูลผู้แจ้ง -->
                <div class="modern-card overflow-hidden">
                    <div class="bg-slate-50 p-4 border-b border-slate-100 flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
                            <i class="fas fa-user-tie text-sm"></i>
                        </div>
                        <h3 class="font-bold text-slate-800">ข้อมูลผู้แจ้ง (บุคลากร)</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 mb-1">
                                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">ข้อมูลผู้แจ้ง</p>
                                <p class="font-bold text-indigo-600 flex items-center"><i class="fab fa-line text-[#06C755] text-[16px] mr-1.5"></i> <?php echo htmlspecialchars($repair_line_id); ?></p>
                            </div>
                            <div>
                                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">ชื่อ-นามสกุล</p>
                                <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($repair_real_name); ?></p>
                            </div>
                            <div>
                                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">เบอร์โทรศัพท์</p>
                                <?php 
                                    // 🟢 บังคับเซ็นเซอร์เบอร์โทรศัพท์ผู้แจ้งเสมอในหน้านี้ (สาธารณะ)
                                    $display_phone = formatCensoredPhone($repair['phone_number']);
                                ?>
                                <p class="font-medium text-slate-700"><?php echo htmlspecialchars($display_phone); ?></p>
                            </div>
                        </div>
                        <hr class="border-slate-100">
                        <div>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">สถานที่ / ห้อง</p>
                            <p class="font-medium text-slate-700"><i class="fas fa-map-marker-alt text-sky-500 mr-1.5"></i> <?php echo htmlspecialchars($repair['location']); ?></p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">อุปกรณ์</p>
                            <p class="font-bold text-slate-800"><?php echo htmlspecialchars($repair['equipment_type']); ?></p>
                        </div>
                        <div class="bg-red-50/50 p-4 rounded-xl border border-red-100">
                            <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest mb-1">รายละเอียดอาการเสีย</p>
                            <p class="text-slate-700 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($repair['problem_desc'])); ?></p>
                        </div>

                        <div>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">ภาพประกอบปัญหา</p>
                            <?php 
                                $image_file = !empty($repair['image_before']) ? $repair['image_before'] : (!empty($repair['image_path']) ? $repair['image_path'] : null);
                                if($image_file): 
                            ?>
                                <a href="uploads/<?php echo htmlspecialchars($image_file); ?>" target="_blank" class="block w-full h-40 rounded-xl border border-slate-200 overflow-hidden relative group">
                                    <img src="uploads/<?php echo htmlspecialchars($image_file); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-white font-medium text-sm bg-black/40 px-3 py-1.5 rounded-lg backdrop-blur-sm"><i class="fas fa-search-plus mr-1.5"></i> คลิกดูรูปเต็ม</span>
                                    </div>
                                </a>
                            <?php else: ?>
                                <div class="w-full h-24 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center text-slate-400">
                                    <i class="fas fa-image text-xl mb-1 opacity-50"></i>
                                    <span class="text-[11px] font-medium">ไม่มีรูปภาพแนบมาด้วย</span>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- ฝั่งขวา: ข้อมูลการปฏิบัติงาน -->
                <div class="modern-card overflow-hidden flex flex-col h-full">
                    <div class="bg-slate-50 p-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-start md:items-center">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mr-3 shrink-0 mt-1 md:mt-0">
                                <i class="fas fa-tools text-sm"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">บันทึกการปฏิบัติงาน (ฝ่ายช่าง)</h3>

                                <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                                    <span>ผู้รับผิดชอบ: <span class="font-bold <?php echo !empty($repair['technician_name']) ? 'text-indigo-600' : 'text-slate-400'; ?>"><?php echo !empty($repair['technician_name']) ? htmlspecialchars($repair['technician_name']) : '- ยังไม่ระบุช่าง -'; ?></span></span>

                                    <?php 
                                    // 🟢 บังคับโชว์เบอร์ช่างเฉพาะตอนกำลังดำเนินการเท่านั้น (สำหรับหน้าสาธารณะ)
                                    if(!empty($tech_phone)): 
                                        if ($repair['status'] == 'กำลังดำเนินการ' || $repair['status'] == 'ช่างรับเรื่องแจ้งซ่อมแล้ว') {
                                            $display_tech_phone = $tech_phone;
                                            $phone_icon = "fa-phone-alt";
                                        } else {
                                            $display_tech_phone = 'ซ่อนเบอร์ (ปิดงานแล้ว)';
                                            $phone_icon = "fa-user-slash";
                                        }
                                    ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">
                                            <i class="fas <?php echo $phone_icon; ?> mr-1"></i> <?php echo htmlspecialchars($display_tech_phone); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 flex-1 flex flex-col">
                        <div class="flex-1 <?php echo empty($repair['repair_note']) ? 'flex items-center justify-center' : ''; ?>">
                            <?php if(!empty($repair['repair_note'])): ?>
                                <div class="prose prose-sm prose-slate max-w-none text-slate-700 leading-relaxed bg-slate-50 p-5 rounded-xl border border-slate-100 min-h-[200px]">
                                    <?php echo nl2br(htmlspecialchars($repair['repair_note'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-8">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i class="fas fa-pencil-alt text-2xl text-slate-300"></i>
                                    </div>
                                    <p class="text-slate-500 font-medium">ยังไม่มีการบันทึกผลการดำเนินการ</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($repair['status'] == 'รอรับเรื่อง'): ?>
                        <div class="mt-6 bg-amber-50 border border-amber-200 p-4 rounded-xl flex items-start">
                            <i class="fas fa-info-circle text-amber-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-amber-700">
                                <p class="font-bold mb-0.5">รอการตอบรับจากฝ่ายช่าง</p>
                                <p class="opacity-80">ใบงานนี้ยังไม่ถูกรับเข้าสู่กระบวนการซ่อมแซม</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mt-6 border-t border-slate-100 pt-6">
                            <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center"><i class="fas fa-star text-amber-400 mr-2"></i> ผลการประเมินจากผู้แจ้ง</h3>
                            <?php 
                            $has_rating = !empty($repair['rating']) && (int)$repair['rating'] > 0;
                            $has_comment = !empty($repair['review_comment']) && trim($repair['review_comment']) !== '' && trim($repair['review_comment']) !== '-';
                            if ($has_rating || $has_comment): 
                            ?>
                                <div class="bg-slate-50 rounded-xl p-5 border border-slate-100">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-slate-400 shrink-0 shadow-sm border border-slate-100">
                                                <i class="fas fa-user text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($repair['reporter_name']); ?></div>
                                                <div class="text-[11px] text-slate-400 font-medium">
                                                    <?php 
                                                    if (!empty($repair['completed_at']) && $repair['completed_at'] != '0000-00-00 00:00:00') {
                                                        echo timeAgo($repair['completed_at']);
                                                    } else {
                                                        echo "ไม่ระบุเวลา";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-0.5 pt-1">
                                            <?php 
                                            $rating = (int)($repair['rating'] ?? 0);
                                            if ($rating > 0) {
                                                for($i=1; $i<=5; $i++) {
                                                    if($i <= $rating) echo '<i class="fas fa-star text-amber-400 text-[13px] drop-shadow-sm"></i>';
                                                    else echo '<i class="fas fa-star text-slate-200 text-[13px]"></i>';
                                                }
                                            } else {
                                                echo '<span class="text-[10px] font-bold text-slate-400 bg-white border border-slate-200 px-2 py-0.5 rounded-md">ไม่มีคะแนน</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php if($has_comment): ?>
                                        <p class="text-sm text-slate-600 font-medium pl-[52px] leading-relaxed mt-1"><?php echo nl2br(htmlspecialchars(trim($repair['review_comment']))); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="pl-[52px] mt-2.5">
                                        <div class="text-[11px] text-indigo-600 font-bold inline-block bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                            <i class="fas fa-tools mr-1.5 opacity-70"></i>ให้คะแนนช่าง: <?php echo !empty($repair['technician_name']) && $repair['technician_name'] !== '-' ? htmlspecialchars($repair['technician_name']) : 'ไม่ระบุช่าง'; ?>
                                        </div>
                                    </div>

                                </div>
                            <?php else: ?>
                                <div class="bg-slate-50 rounded-xl p-6 text-center border border-slate-100">
                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center mx-auto mb-2 shadow-sm border border-slate-100">
                                        <i class="fas fa-star text-slate-300 text-lg"></i>
                                    </div>
                                    <p class="text-slate-500 text-xs font-medium">ใบงานนี้ยังไม่ได้รับการประเมินผล</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            </div>
            
        </div>
        <?php else: ?>
            <div class="modern-card p-16 text-center mt-10">
                <i class="fas fa-search text-5xl text-slate-300 mb-4 block"></i>
                <h2 class="text-2xl font-bold text-slate-700 mb-2">ไม่พบข้อมูลใบงาน</h2>
                <p class="text-slate-500">รหัสอ้างอิงไม่ถูกต้อง หรือใบงานนี้อาจถูกลบออกจากระบบแล้ว</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>