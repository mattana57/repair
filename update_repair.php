<?php
// ตั้งค่าโซนเวลาเป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');
include 'db_connect.php';

$repair = null;
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM repairs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $repair = $result->fetch_assoc();
}

$techs = [];
$tech_res = $conn->query("SELECT full_name FROM users WHERE LOWER(role) = 'technician' ORDER BY full_name ASC");
if($tech_res && $tech_res->num_rows > 0){
    while($t = $tech_res->fetch_assoc()) {
        $techs[] = $t['full_name'];
    }
}

$show_alert = false;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $status = $_POST['status'];
    $repair_note = $_POST['repair_note'];
    $technician_name = isset($_POST['technician_name']) && $_POST['technician_name'] !== '' ? $_POST['technician_name'] : null;
    $update_id = $_POST['id'];

    $update_sql = "UPDATE repairs SET status = ?, repair_note = ?, technician_name = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $status, $repair_note, $technician_name, $update_id);
    
    if ($update_stmt->execute()) {
        $show_alert = true;
        
        $stmt->execute();
        $repair = $stmt->get_result()->fetch_assoc();

        if(!empty($repair['line_user_id'])) {
            // 🚨 นำ Channel Access Token ของคุณน้ำฝนมาใส่ตรงนี้
            $channelAccessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU=';
            
            $tech_display = !empty($technician_name) ? $technician_name : "- ไม่ระบุ -";
            $note_display = !empty($repair_note) ? $repair_note : "-";
            
            // ดึงเวลาปัจจุบันที่ช่างกดอัปเดต
            $current_time = date("d/m/Y H:i น.");

            // สัญลักษณ์ตามสถานะ
            $icon = "🔔";
            if($status == 'กำลังดำเนินการ') $icon = "🛠️";
            if($status == 'ซ่อมเสร็จแล้ว') $icon = "🎉";

            // เพิ่ม "อาการเสีย" เข้าไปในข้อความแจ้งเตือนแล้วค่ะ
            $messageText = $icon . " อัปเดตสถานะงานซ่อม\n\n" .
                           "📋 เลขที่ใบงาน: " . $repair['ticket_no'] . "\n" .
                           "🕒 เวลาอัปเดต: " . $current_time . "\n" .
                           "💻 อุปกรณ์: " . $repair['equipment_type'] . "\n" .
                           "⚠️ อาการ: " . $repair['problem_desc'] . "\n\n" .
                           "📌 สถานะใหม่: " . $status . "\n" .
                           "👨‍🔧 ช่างผู้ดูแล: " . $tech_display . "\n" .
                           "📝 หมายเหตุ: " . $note_display;

            $postData = [
                'to' => $repair['line_user_id'], // ส่งหาผู้แจ้ง
                'messages' => [['type' => 'text', 'text' => $messageText]]
            ];

            $ch = curl_init('https://api.line.me/v2/bot/message/push');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $channelAccessToken));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดตสถานะงานซ่อม | MBS MAINT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); }
    </style>
</head>
<body class="p-4 md:p-10 selection:bg-sky-200">

    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-slate-800"><i class="fas fa-clipboard-check text-sky-500 mr-2"></i> ระบบจัดการใบงานแจ้งซ่อม</h1>
                <p class="text-sm md:text-base text-slate-500 mt-1">ตรวจสอบรายละเอียดและอัปเดตสถานะให้ผู้แจ้ง</p>
            </div>
            <a href="dashboard.php" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-sky-600 px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm w-full sm:w-auto text-center">
                <i class="fas fa-arrow-left mr-2"></i> กลับหน้าหลัก
            </a>
        </div>

        <?php if($repair): ?>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            
            <div class="lg:col-span-2 space-y-6">
                <div class="modern-card p-6 border-t-4 border-sky-500">
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-lg font-bold text-slate-800">ข้อมูลใบงาน</h2>
                        <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold border border-slate-200">
                            <?php echo $repair['ticket_no']; ?>
                        </span>
                    </div>
                    
                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="text-slate-400 text-[10px] md:text-xs uppercase tracking-wide">วัน/เวลาที่แจ้ง</p>
                            <p class="font-medium text-slate-700 mt-0.5"><i class="far fa-clock text-slate-400 mr-1"></i> <?php echo date("d/m/Y H:i", strtotime($repair['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[10px] md:text-xs uppercase tracking-wide">ผู้แจ้ง</p>
                            <p class="font-medium text-slate-700 mt-0.5"><i class="far fa-user text-slate-400 mr-1"></i> <?php echo htmlspecialchars($repair['reporter_name']); ?></p>
                            <p class="text-slate-500 mt-0.5"><i class="fas fa-phone-alt text-slate-400 mr-1"></i> <?php echo htmlspecialchars($repair['phone_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[10px] md:text-xs uppercase tracking-wide">สถานที่</p>
                            <p class="font-medium text-slate-700 mt-0.5"><i class="fas fa-map-marker-alt text-rose-400 mr-1"></i> <?php echo htmlspecialchars($repair['location']); ?></p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <p class="text-slate-400 text-[10px] md:text-xs uppercase tracking-wide mb-1">อุปกรณ์และอาการเสีย</p>
                            <p class="font-bold text-sky-700"><?php echo htmlspecialchars($repair['equipment_type']); ?></p>
                            <p class="text-slate-600 mt-1"><?php echo htmlspecialchars($repair['problem_desc']); ?></p>
                        </div>
                        
                        <?php if(!empty($repair['image_before'])): ?>
                        <div>
                            <p class="text-slate-400 text-[10px] md:text-xs uppercase tracking-wide mb-2">ภาพประกอบ</p>
                            <a href="uploads/<?php echo htmlspecialchars($repair['image_before']); ?>" target="_blank" class="block w-full h-32 rounded-xl border border-slate-200 overflow-hidden group relative">
                                <img src="uploads/<?php echo htmlspecialchars($repair['image_before']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-white font-medium text-sm"><i class="fas fa-expand mr-1"></i> ดูรูปภาพเต็ม</span>
                                </div>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="modern-card p-6 md:p-8 h-full">
                    <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-6">บันทึกการปฏิบัติงาน</h2>
                    
                    <form action="" method="POST" class="space-y-6">
                        <input type="hidden" name="id" value="<?php echo $repair['id']; ?>">
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-user-cog text-sky-500 mr-2"></i> มอบหมายช่างผู้รับผิดชอบ</label>
                            <select name="technician_name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all cursor-pointer">
                                <option value="">-- ยังไม่ระบุผู้รับผิดชอบ --</option>
                                <?php foreach($techs as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo (isset($repair['technician_name']) && $repair['technician_name'] == $t) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-tasks text-sky-500 mr-2"></i> อัปเดตสถานะงาน <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="รอรับเรื่อง" class="peer sr-only" <?php echo ($repair['status'] == 'รอรับเรื่อง') ? 'checked' : ''; ?> required>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-amber-50 peer-checked:border-amber-300 peer-checked:text-amber-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-clock mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">รอรับเรื่อง</div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="กำลังดำเนินการ" class="peer sr-only" <?php echo ($repair['status'] == 'กำลังดำเนินการ') ? 'checked' : ''; ?>>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-sky-50 peer-checked:border-sky-300 peer-checked:text-sky-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-tools mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">กำลังดำเนินการ</div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="ซ่อมเสร็จแล้ว" class="peer sr-only" <?php echo ($repair['status'] == 'ซ่อมเสร็จแล้ว') ? 'checked' : ''; ?>>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-emerald-50 peer-checked:border-emerald-300 peer-checked:text-emerald-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-check-circle mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">ซ่อมเสร็จแล้ว</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-edit text-sky-500 mr-2"></i> บันทึกผลการดำเนินการ / หมายเหตุช่าง</label>
                            <textarea name="repair_note" rows="4" placeholder="ระบุสาเหตุที่เสีย, อะไหล่ที่เปลี่ยน, หรือคำแนะนำ..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all resize-none"><?php echo isset($repair['repair_note']) ? htmlspecialchars($repair['repair_note']) : ''; ?></textarea>
                        </div>

                        <div class="pt-4 border-t border-slate-100">
                            <button type="submit" class="w-full md:w-auto md:float-right bg-sky-600 hover:bg-sky-500 text-white px-8 py-3 rounded-xl font-bold transition-colors shadow-lg shadow-sky-600/20 flex justify-center items-center">
                                <i class="fas fa-save mr-2"></i> บันทึกข้อมูลและแจ้งเตือนผู้ใช้
                            </button>
                            <div class="clear-both"></div>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
        <?php else: ?>
            <div class="modern-card p-12 text-center">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-3xl text-slate-400"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-700 mb-2">ไม่พบข้อมูลใบงาน</h2>
                <a href="dashboard.php" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-2.5 rounded-xl font-medium transition-colors inline-block mt-4">กลับหน้าหลัก</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if($show_alert): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ!',
            text: 'อัปเดตสถานะและส่ง LINE แจ้งเตือนผู้ใช้เรียบร้อยแล้ว',
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'dashboard.php';
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>