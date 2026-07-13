<?php
include 'db_connect.php';

// ดึงข้อมูลใบงาน
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดใบงาน | MSU MAINT</title>
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
    
    <!-- พื้นหลังตกแต่ง -->
    <div class="absolute inset-0 bg-pattern opacity-50 -z-10"></div>

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><i class="fas fa-file-alt text-sky-500 mr-2"></i> รายละเอียดใบงานแจ้งซ่อม</h1>
                <p class="text-slate-500 mt-1 text-sm">ข้อมูลการแจ้งซ่อมจากบุคลากร และบันทึกการปฏิบัติงานของช่าง</p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-sky-600 px-4 py-2.5 rounded-xl font-medium transition-all shadow-sm flex items-center text-sm">
                    <i class="fas fa-print mr-2"></i> พิมพ์
                </button>
                <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-md flex items-center text-sm">
                    <i class="fas fa-arrow-left mr-2"></i> กลับหน้าหลัก
                </a>
            </div>
        </div>

        <?php if($repair): 
            // กำหนดสีสถานะ
            $statusColor = "bg-slate-100 text-slate-600 border-slate-200"; 
            $statusIcon = "fa-clock";
            if($repair['status'] == 'รอรับเรื่อง') { $statusColor = "bg-amber-50 text-amber-600 border-amber-200"; $statusIcon = "fa-clock"; }
            elseif($repair['status'] == 'กำลังดำเนินการ') { $statusColor = "bg-sky-50 text-sky-600 border-sky-200"; $statusIcon = "fa-tools"; }
            elseif($repair['status'] == 'ซ่อมเสร็จแล้ว') { $statusColor = "bg-emerald-50 text-emerald-600 border-emerald-200"; $statusIcon = "fa-check-circle"; }
        ?>
        
        <div class="space-y-6">
            
            <!-- ส่วนบน: ข้อมูลหลัก & สถานะ -->
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
                
                <!-- ฝั่งซ้าย: ข้อมูลจากผู้แจ้ง (บุคลากร) -->
                <div class="modern-card overflow-hidden">
                    <div class="bg-slate-50 p-4 border-b border-slate-100 flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
                            <i class="fas fa-user-tie text-sm"></i>
                        </div>
                        <h3 class="font-bold text-slate-800">ข้อมูลผู้แจ้ง (บุคลากร)</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">ชื่อ-นามสกุล</p>
                                <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($repair['reporter_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">เบอร์โทรศัพท์</p>
                                <p class="font-medium text-slate-700"><?php echo htmlspecialchars($repair['phone_number']); ?></p>
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

                        <?php if(!empty($repair['image_before'])): ?>
                        <div>
                            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">ภาพประกอบปัญหา</p>
                            <a href="uploads/<?php echo $repair['image_before']; ?>" target="_blank" class="block w-full h-40 rounded-xl border border-slate-200 overflow-hidden relative group">
                                <img src="uploads/<?php echo $repair['image_before']; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-white font-medium text-sm bg-black/40 px-3 py-1.5 rounded-lg backdrop-blur-sm"><i class="fas fa-search-plus mr-1.5"></i> คลิกดูรูปเต็ม</span>
                                </div>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ฝั่งขวา: บันทึกจากช่าง -->
                <div class="modern-card overflow-hidden flex flex-col h-full">
                    <div class="bg-slate-50 p-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mr-3">
                                <i class="fas fa-tools text-sm"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">บันทึกการปฏิบัติงาน (ฝ่ายช่าง)</h3>
                        </div>
                        <!-- ลิงก์ไปหน้าอัปเดต ถ้ามีสิทธิ์เป็นช่าง/แอดมิน -->
                        <a href="update_repair.php?id=<?php echo $repair['id']; ?>" class="text-xs font-semibold text-sky-600 hover:text-sky-700 bg-sky-50 px-2.5 py-1 rounded-md transition-colors">
                            แก้ไขบันทึก
                        </a>
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
                                    <p class="text-slate-400 text-xs mt-1">ช่างสามารถเพิ่มบันทึกได้ในเมนูอัปเดตสถานะ</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- แจ้งเตือนสถานะ -->
                        <?php if($repair['status'] == 'รอรับเรื่อง'): ?>
                        <div class="mt-6 bg-amber-50 border border-amber-200 p-4 rounded-xl flex items-start">
                            <i class="fas fa-info-circle text-amber-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-amber-700">
                                <p class="font-bold mb-0.5">รอการตอบรับจากฝ่ายช่าง</p>
                                <p class="opacity-80">ใบงานนี้ยังไม่ถูกรับเข้าสู่กระบวนการซ่อมแซม</p>
                            </div>
                        </div>
                        <?php endif; ?>
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