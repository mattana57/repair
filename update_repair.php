<?php
date_default_timezone_set('Asia/Bangkok');
include 'db_connect.php';

function splitThaiEngName($fullName, $engName) {
    $th = trim((string)$fullName);
    $en = trim((string)$engName);
    if (empty($en) && !empty($th)) {
        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $th, $matches)) {
            $th = trim($matches[1]);
            $en = trim($matches[2]);
        } elseif (preg_match('/^(.*?)\s+(Mr\.|Mrs\.|Miss|Ms\.)\s*(.*)$/i', $th, $matches)) {
            $th = trim($matches[1]);
            $en = trim($matches[2]) . ' ' . trim($matches[3]);
        }
    }
    return array($th, $en);
}

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
$tech_res = $conn->query("SELECT DISTINCT full_name FROM technicians WHERE full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
if($tech_res && $tech_res->num_rows > 0){
    while($t = $tech_res->fetch_assoc()) {
        $techs[] = $t['full_name'];
    }
}

$assets_list = [];
$assets_res = $conn->query("SELECT asset_code, asset_name FROM assets ORDER BY asset_code ASC");
if($assets_res && $assets_res->num_rows > 0){
    while($a = $assets_res->fetch_assoc()){
        $assets_list[] = $a;
    }
}

$asset_categories = ['IT Support', 'ไฟฟ้า/แอร์', 'อาคารสถานที่'];
$cat_res = $conn->query("SELECT DISTINCT category FROM assets WHERE category IS NOT NULL AND category != ''");
if($cat_res && $cat_res->num_rows > 0){
    while($c = $cat_res->fetch_assoc()){
        if(!in_array($c['category'], $asset_categories) && $c['category'] !== 'อื่นๆ') {
            $asset_categories[] = $c['category'];
        }
    }
}

$check_asset_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'asset_code'");
if($check_asset_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN asset_code VARCHAR(50) NULL AFTER equipment_type");
}

$check_root_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'root_cause'");
if($check_root_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN root_cause TEXT NULL");
}

$check_completed_col = $conn->query("SHOW COLUMNS FROM repairs LIKE 'completed_at'");
if($check_completed_col->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN completed_at DATETIME NULL");
}

$show_alert = false;
$show_asset_alert = false; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['save_asset_only'])) {
        $acode = trim($_POST['modal_asset_code']);
        $aname = trim($_POST['modal_asset_name']);
        
        $acat = $_POST['modal_category'];
        if ($acat === 'อื่นๆ' && !empty($_POST['modal_category_custom'])) {
            $acat = trim($_POST['modal_category_custom']);
        }
        
        $astat = 'ใช้งานปกติ';

        $chk = $conn->prepare("SELECT id FROM assets WHERE asset_code = ?");
        $chk->bind_param("s", $acode);
        $chk->execute();
        $res_chk = $chk->get_result();
        
        if ($res_chk->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $acode, $aname, $acat, $astat);
            $stmt_insert->execute();
            $stmt_insert->close();
            $show_asset_alert = true;
            $repair['asset_code'] = $acode; 
        }
        $chk->close();
        
    } else {
        $status = $_POST['status'];
        $repair_note = $_POST['repair_note'];
        $technician_name = isset($_POST['technician_name']) && $_POST['technician_name'] !== '' ? $_POST['technician_name'] : null;
        $asset_code = isset($_POST['asset_code']) && $_POST['asset_code'] !== '' ? trim($_POST['asset_code']) : null;
        $asset_status = isset($_POST['asset_status']) ? $_POST['asset_status'] : null;
        $update_id = $_POST['id'];

        if ($status === 'ซ่อมเสร็จแล้ว') {
            $update_sql = "UPDATE repairs SET status = ?, repair_note = ?, root_cause = ?, technician_name = ?, asset_code = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?";
        } else {
            $update_sql = "UPDATE repairs SET status = ?, repair_note = ?, root_cause = ?, technician_name = ?, asset_code = ?, completed_at = NULL WHERE id = ?";
        }
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssi", $status, $repair_note, $repair_note, $technician_name, $asset_code, $update_id);

        if ($update_stmt->execute()) {
            $show_alert = true;

            if (!empty($asset_code) && !empty($asset_status)) {
                $check_asset = $conn->prepare("SELECT id FROM assets WHERE asset_code = ?");
                $check_asset->bind_param("s", $asset_code);
                $check_asset->execute();
                $res_check = $check_asset->get_result();

                if ($res_check->num_rows > 0) {
                    $stmt_asset = $conn->prepare("UPDATE assets SET status = ? WHERE asset_code = ?");
                    $stmt_asset->bind_param("ss", $asset_status, $asset_code);
                    $stmt_asset->execute();
                    $stmt_asset->close();
                } else {
                    $new_asset_name = isset($_POST['new_asset_name']) && trim($_POST['new_asset_name']) !== '' ? trim($_POST['new_asset_name']) : "ครุภัณฑ์จากใบงาน " . (!empty($repair['ticket_no']) ? $repair['ticket_no'] : "ล่าสุด");
                    
                    $new_asset_category = isset($_POST['new_asset_category']) ? $_POST['new_asset_category'] : "อื่นๆ";
                    if ($new_asset_category === 'อื่นๆ' && !empty($_POST['new_asset_category_custom'])) {
                        $new_asset_category = trim($_POST['new_asset_category_custom']);
                    }
                    
                    $stmt_asset_insert = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
                    $stmt_asset_insert->bind_param("ssss", $asset_code, $new_asset_name, $new_asset_category, $asset_status);
                    $stmt_asset_insert->execute();
                    $stmt_asset_insert->close();
                }
                $check_asset->close();
            }

            $stmt->execute();
            $repair = $stmt->get_result()->fetch_assoc();

            $channelAccessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU=';

            $tech_display = !empty($technician_name) ? $technician_name : "- ไม่ระบุ -";
            $note_display = !empty($repair_note) ? $repair_note : "-";

            $current_time = date("d/m/Y H:i น.");

            $tech_phone = "- ไม่ระบุ -";
            if (!empty($technician_name)) {
                $stmt_tech = $conn->prepare("SELECT phone FROM technicians WHERE full_name = ? LIMIT 1");
                if ($stmt_tech) {
                    $stmt_tech->bind_param("s", $technician_name);
                    $stmt_tech->execute();
                    $res_tech = $stmt_tech->get_result();
                    if ($res_tech->num_rows > 0) {
                        $row_tech = $res_tech->fetch_assoc();
                        if (!empty($row_tech['phone'])) {
                            $tech_phone = $row_tech['phone'];
                        }
                    }
                    $stmt_tech->close();
                }
            }

            $status_display = $status;
            if ($status == 'กำลังดำเนินการ') {
                $status_display = 'ช่างรับเรื่องแจ้งซ่อมแล้ว';
            }

            if(!empty($repair['line_user_id'])) {
                $icon = "🔔";
                if($status == 'กำลังดำเนินการ') $icon = "🛠️";
                if($status == 'ซ่อมเสร็จแล้ว') $icon = "🎉";

                $messageText = $icon . " อัปเดตสถานะงานซ่อม\n\n" .
                               "📋 เลขที่ใบงาน: " . $repair['ticket_no'] . "\n" .
                               "🕒 เวลาอัปเดต: " . $current_time . "\n" .
                               "💻 อุปกรณ์: " . $repair['equipment_type'] . "\n" .
                               "⚠️ อาการ: " . $repair['problem_desc'] . "\n\n" .
                               "📌 สถานะใหม่: " . $status_display . "\n" .  
                               "👨‍🔧 ช่างผู้ดูแล: " . $tech_display . "\n" .
                               "📱 เบอร์ติดต่อช่าง: " . $tech_phone . "\n" .
                               "📝 หมายเหตุ: " . $note_display;

                $postData = [
                    'to' => $repair['line_user_id'],
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

            $line_group_id = 'Caed57e09981787d718ce11abb3b2db15'; 

            if(!empty($line_group_id) && $status == 'กำลังดำเนินการ') {
                $groupMessage = "📢 มีช่างรับงานแล้วจ้า!\n" .
                                "👨‍🔧 ช่าง: " . $tech_display . "\n" .
                                "💻 งาน: " . $repair['equipment_type'] . " (" . $repair['location'] . ")";

                $postDataGroup = [
                    'to' => $line_group_id,
                    'messages' => [['type' => 'text', 'text' => $groupMessage]]
                ];

                $ch2 = curl_init('https://api.line.me/v2/bot/message/push');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $channelAccessToken));
                curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($postDataGroup));
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false); 
                curl_exec($ch2);
                curl_close($ch2);
            }
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
        
        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20320%20512%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M31.3%20192h257.3c17.8%200%2026.7%2021.5%2014.1%2034.1L174.1%20354.8c-7.8%207.8-20.5%207.8-28.3%200L17.2%20226.1C4.6%20213.5%2013.5%20192%2031.3%20192z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 0.75em auto;
            padding-right: 2.5rem;
        }

        input[list]::-webkit-calendar-picker-indicator {
            opacity: 0 !important;
            cursor: pointer;
            width: 2rem;
            height: 100%;
        }

        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
    </style>
</head>
<body class="p-4 md:p-10 selection:bg-sky-200">

    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-slate-800"><i class="fas fa-clipboard-check text-sky-500 mr-2"></i> ระบบจัดการใบงานแจ้งซ่อม</h1>
                <p class="text-sm md:text-base text-slate-500 mt-1">ตรวจสอบรายละเอียดและอัปเดตสถานะให้ผู้แจ้ง</p>
            </div>
            <a href="dashboard.php?tab=repairs" class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-md inline-flex items-center justify-center text-sm w-full sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i> กลับหน้ารายการ
            </a>
        </div>

        <?php if($repair): ?>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <div class="lg:col-span-2 space-y-6">
                <div class="modern-card p-6 border-t-4 border-sky-500">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-slate-800">ข้อมูลใบงาน</h2>
                        <span class="bg-slate-100 border border-slate-200 px-3 py-1 rounded-lg text-lg font-extrabold text-sky-600 tracking-tight">
                            <?php echo htmlspecialchars($repair['ticket_no']); ?>
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

                        <div>
                            <p class="text-slate-400 text-[10px] md:text-xs uppercase tracking-wide mb-2">ภาพประกอบ</p>
                            <?php 
                                $image_file = !empty($repair['image_before']) ? $repair['image_before'] : (!empty($repair['image_path']) ? $repair['image_path'] : null);
                                if($image_file): 
                            ?>
                                <a href="uploads/<?php echo htmlspecialchars($image_file); ?>" target="_blank" class="block w-full h-48 rounded-xl border border-slate-200 overflow-hidden group relative">
                                    <img src="uploads/<?php echo htmlspecialchars($image_file); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-white font-medium text-sm"><i class="fas fa-expand mr-1"></i> ดูรูปภาพเต็ม</span>
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
            </div>

            <div class="lg:col-span-3">
                <div class="modern-card p-6 md:p-8 h-full">
                    <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-6">บันทึกการปฏิบัติงาน</h2>

                    <form id="updateForm" action="" method="POST" class="space-y-6">
                        <input type="hidden" name="id" value="<?php echo $repair['id']; ?>">

                        <?php
                            $current_tech_full = $repair['technician_name'] ?? '';
                            $current_tech_display = '';
                            if ($current_tech_full) {
                                list($cth, $cen) = splitThaiEngName($current_tech_full, '');
                                $current_tech_display = $cth;
                            }
                        ?>
                        <div class="mb-4 relative" id="techDropdownContainer">
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-user-cog text-sky-500 mr-2"></i> มอบหมายช่างผู้รับผิดชอบ</label>
                            
                            <div class="flex items-center w-full bg-slate-50 border border-slate-200 rounded-xl overflow-hidden focus-within:border-sky-400 focus-within:ring-4 focus-within:ring-sky-100 transition-all cursor-text shadow-sm" onclick="toggleTechDropdown(event, true)">
                                <input type="text" id="techSearchInput" oninput="filterTechDropdown()" onfocus="focusTechSearch(event)" onblur="blurTechSearch(event)" autocomplete="off" class="w-full bg-transparent px-4 py-3 text-sm text-slate-700 focus:outline-none font-medium placeholder-slate-400" placeholder="-- ค้นหาหรือเลือกช่าง --">
                                
                                <button type="button" class="px-4 py-3 text-slate-400 hover:text-sky-600 focus:outline-none flex items-center justify-center" onclick="toggleTechDropdown(event)">
                                    <i class="fas fa-caret-down text-lg"></i>
                                </button>
                            </div>
                            
                            <div id="techDropdownList" class="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-xl max-h-60 overflow-y-auto hidden flex-col py-3 custom-scrollbar">
                                <div class="tech-dropdown-item px-4 py-2 mx-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 cursor-pointer transition-colors flex items-center" data-value="" data-search="" onmousedown="selectTech('', '-- ยังไม่ระบุผู้รับผิดชอบ --')">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 text-slate-400">
                                        <i class="fas fa-user-slash text-xs"></i>
                                    </div>
                                    -- ยังไม่ระบุผู้รับผิดชอบ --
                                </div>
                                
                                <div class="px-6 pt-4 pb-2 text-[11px] font-extrabold text-slate-400 tracking-wide">รายชื่อช่างในระบบ</div>
                                
                                <?php foreach($techs as $t): 
                                    list($th_name, $en_name) = splitThaiEngName($t, '');
                                    $searchStr = preg_replace('/\s+/', '', strtolower($th_name));
                                ?>
                                    <div class="tech-dropdown-item px-4 py-2 mx-2 mb-1 rounded-xl text-sm text-slate-700 font-bold hover:bg-sky-50 hover:text-sky-600 cursor-pointer flex justify-between items-center transition-all group" data-value="<?php echo htmlspecialchars($t); ?>" data-display="<?php echo htmlspecialchars($th_name); ?>" data-search="<?php echo htmlspecialchars($searchStr); ?>" onmousedown="selectTech('<?php echo htmlspecialchars($t, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($th_name, ENT_QUOTES); ?>')">
                                        <div class="flex items-center pointer-events-none">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 text-slate-400 group-hover:bg-sky-100 group-hover:text-sky-500 transition-colors">
                                                <i class="fas fa-user text-xs"></i>
                                            </div>
                                            <span><?php echo htmlspecialchars($th_name); ?></span>
                                        </div>
                                        <i class="fas fa-check text-sky-500 opacity-0 group-hover:opacity-100 transition-opacity check-icon pointer-events-none"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" name="technician_name" id="technician_name" value="<?php echo htmlspecialchars($repair['technician_name'] ?? ''); ?>">
                        </div>

                        <div class="mb-4 p-5 border border-slate-200 bg-slate-50/50 rounded-2xl shadow-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="block text-sm font-semibold text-slate-700"><i class="fas fa-barcode text-sky-500 mr-1.5"></i> รหัสครุภัณฑ์ (ที่ซ่อม)</label>
                                        <button type="button" onclick="openAssetModal()" class="text-[11px] bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg font-bold transition-all shadow-sm flex items-center shrink-0">
                                            <i class="fas fa-plus mr-1"></i> เพิ่มใหม่
                                        </button>
                                    </div>
                                    <input type="text" name="asset_code" id="asset_code" list="asset_code_list" 
                                           class="custom-select w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100 transition-all shadow-sm relative" 
                                           placeholder="-- เลือกรหัส หรือพิมพ์ค้นหา --" 
                                           value="<?php echo isset($repair['asset_code']) ? htmlspecialchars($repair['asset_code']) : ''; ?>">
                                    
                                    <datalist id="asset_code_list">
                                        <?php foreach($assets_list as $asset): ?>
                                            <option value="<?php echo htmlspecialchars($asset['asset_code']); ?>">
                                                <?php echo htmlspecialchars($asset['asset_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <p class="text-[11px] text-slate-500 mt-2 font-medium">กด <span class="text-indigo-600 font-bold">"เพิ่มใหม่"</span> หากไม่มีรหัสในระบบ</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-heartbeat text-sky-500 mr-2"></i> สถานะครุภัณฑ์ (ปัจจุบัน)</label>
                                    <select name="asset_status" id="asset_status" class="custom-select w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100 transition-all cursor-pointer shadow-sm mt-0.5 md:mt-0">
                                        <option value="ใช้งานปกติ">🟢 ใช้งานปกติ</option>
                                        <option value="ชำรุด/ส่งซ่อม">🟠 ชำรุด/ส่งซ่อม</option>
                                        <option value="แทงจำหน่าย">🔴 แทงจำหน่าย</option>
                                    </select>
                                </div>
                            </div>

                            <div id="new_asset_section" class="hidden mt-4 pt-4 border-t border-slate-200">
                                <div class="flex items-center mb-3">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 mr-2"><i class="fas fa-plus text-xs"></i></span>
                                    <p class="text-sm font-bold text-indigo-700">พบรหัสครุภัณฑ์ใหม่! กรุณาระบุข้อมูลเพื่อเพิ่มลงระบบ</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">ชื่อครุภัณฑ์ <span class="text-red-500">*</span></label>
                                        <input type="text" name="new_asset_name" id="new_asset_name" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="เช่น คอมพิวเตอร์ DELL">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">หมวดหมู่</label>
                                        <select name="new_asset_category" id="new_asset_category" onchange="toggleCustomInput(this, 'new_asset_category_custom')" class="custom-select w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm cursor-pointer">
                                            <?php foreach($asset_categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                            <?php endforeach; ?>
                                            <option value="อื่นๆ">อื่นๆ (พิมพ์ระบุเอง)</option>
                                        </select>
                                        <input type="text" name="new_asset_category_custom" id="new_asset_category_custom" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 hidden mt-2 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="ระบุหมวดหมู่ใหม่">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2"><i class="fas fa-tasks text-sky-500 mr-2"></i> อัปเดตสถานะงานแจ้งซ่อม <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="รอรับเรื่อง" class="peer sr-only" <?php echo ($repair['status'] == 'รอรับเรื่อง') ? 'checked' : ''; ?> required>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-amber-50 peer-checked:border-amber-300 peer-checked:text-amber-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-clock mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">รอรับเรื่อง</div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="กำลังดำเนินการ" class="peer sr-only" <?php echo ($repair['status'] == 'กำลังดำเนินการ' || $repair['status'] == 'ช่างรับเรื่องแจ้งซ่อมแล้ว') ? 'checked' : ''; ?>>
                                    <div class="text-center p-3 rounded-xl border border-slate-200 bg-white peer-checked:bg-sky-50 peer-checked:border-sky-300 peer-checked:text-sky-700 hover:bg-slate-50 transition-all">
                                        <i class="fas fa-tools mb-1 text-lg"></i>
                                        <div class="text-sm font-medium">ช่างรับเรื่องแจ้งซ่อมแล้ว</div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="ซ่อมเสร็จแล้ว" class="peer sr-only" <?php echo ($repair['status'] == 'ซ่อมเสร็จแล้ว' || $repair['status'] == 'เสร็จสิ้น') ? 'checked' : ''; ?>>
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
                                <i class="fas fa-save mr-2"></i> บันทึกข้อมูลและแจ้งเตือน
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
                <a href="dashboard.php?tab=repairs" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-2.5 rounded-xl font-medium transition-colors inline-block mt-4">กลับหน้ารายการ</a>
            </div>
        <?php endif; ?>
    </div>

    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-3xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                <p class="text-lg font-extrabold text-slate-800">Add New Asset</p>
                <button type="button" onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-sm"><i class="fas fa-times"></i></button>
            </div>
            <form action="update_repair.php?id=<?php echo $id; ?>" method="POST" class="p-6">
                <input type="hidden" name="save_asset_only" value="1">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Code <span class="text-red-500">*</span></label>
                        <input type="text" name="modal_asset_code" id="modal_asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Asset Name <span class="text-red-500">*</span></label>
                        <input type="text" name="modal_asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Category</label>
                        <select name="modal_category" id="modal_category" onchange="toggleCustomInput(this, 'modal_category_custom')" required class="custom-select w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium cursor-pointer">
                            <?php foreach($asset_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="อื่นๆ">อื่นๆ (พิมพ์ระบุเอง)</option>
                        </select>
                        <input type="text" name="modal_category_custom" id="modal_category_custom" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm text-slate-700 hidden mt-2 focus:ring-2 focus:ring-indigo-100 focus:outline-none font-medium shadow-sm" placeholder="ระบุหมวดหมู่ใหม่">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('assetModal')" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors shadow-sm">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">Save Asset</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentTechDisplay = '<?php echo addslashes($current_tech_display); ?>';
        let currentTechValue = '<?php echo addslashes($current_tech_full); ?>';

        function focusTechSearch(e) {
            e.target.value = ''; 
            filterTechDropdown(); 
            toggleTechDropdown(e, true);
        }

        function blurTechSearch(e) {
            setTimeout(() => {
                if (document.getElementById('techSearchInput').value === '') {
                    document.getElementById('techSearchInput').value = currentTechDisplay || '-- ยังไม่ระบุผู้รับผิดชอบ --';
                }
            }, 200);
        }

        function toggleTechDropdown(e, forceOpen = false) {
            if(e) e.stopPropagation();
            const list = document.getElementById('techDropdownList');
            if(forceOpen) {
                list.classList.remove('hidden');
                list.classList.add('flex');
            } else {
                list.classList.toggle('hidden');
                list.classList.toggle('flex');
            }
            updateTechCheckmarks();
        }

        function filterTechDropdown() {
            toggleTechDropdown(null, true);
            const searchVal = document.getElementById('techSearchInput').value.toLowerCase().replace(/\s+/g, '');
            const items = document.querySelectorAll('.tech-dropdown-item');
            items.forEach(item => {
                const searchData = item.getAttribute('data-search') || '';
                const displayData = (item.getAttribute('data-display') || '').toLowerCase().replace(/\s+/g, '');
                if(searchData.includes(searchVal) || displayData.includes(searchVal) || item.getAttribute('data-value') === '') {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function selectTech(val, displayText) {
            currentTechValue = val;
            currentTechDisplay = displayText;
            document.getElementById('technician_name').value = val;
            document.getElementById('techSearchInput').value = displayText;
            document.getElementById('techDropdownList').classList.add('hidden');
            document.getElementById('techDropdownList').classList.remove('flex');
            updateTechCheckmarks();
        }

        function updateTechCheckmarks() {
            document.querySelectorAll('.tech-dropdown-item').forEach(item => {
                const icon = item.querySelector('.check-icon');
                if(icon) {
                    if(item.getAttribute('data-value') === currentTechValue && currentTechValue !== '') {
                        icon.classList.remove('opacity-0');
                        icon.classList.add('opacity-100');
                    } else {
                        icon.classList.add('opacity-0');
                        icon.classList.remove('opacity-100');
                    }
                }
            });
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('techDropdownContainer');
            if (container && !container.contains(e.target)) {
                const list = document.getElementById('techDropdownList');
                if(list) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                    document.getElementById('techSearchInput').value = currentTechDisplay || '-- ยังไม่ระบุผู้รับผิดชอบ --';
                }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('techSearchInput').value = currentTechDisplay || '-- ยังไม่ระบุผู้รับผิดชอบ --';
            updateTechCheckmarks();
        });


        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        function openAssetModal() {
            let typedVal = document.getElementById('asset_code').value;
            document.getElementById('modal_asset_code').value = typedVal;
            toggleModal('assetModal');
        }

        function toggleCustomInput(selectElement, customInputId) {
            const customInput = document.getElementById(customInputId);
            if(selectElement.value === 'อื่นๆ') { 
                customInput.classList.remove('hidden'); customInput.required = true;
            } else { 
                customInput.classList.add('hidden'); customInput.required = false; 
            }
        }

        const existingAssets = <?php echo json_encode(array_column($assets_list, 'asset_code')); ?>;
        const assetCodeInput = document.getElementById('asset_code');
        const newAssetSection = document.getElementById('new_asset_section');
        const newAssetNameInput = document.getElementById('new_asset_name');

        function checkNewAsset() {
            const val = assetCodeInput.value.trim();
            if (val !== '' && !existingAssets.includes(val)) {
                newAssetSection.classList.remove('hidden');
                newAssetSection.classList.add('block');
                newAssetNameInput.required = true;
            } else {
                newAssetSection.classList.add('hidden');
                newAssetSection.classList.remove('block');
                newAssetNameInput.required = false;
            }
        }

        assetCodeInput.addEventListener('input', checkNewAsset);
        checkNewAsset();

        document.getElementById('updateForm').addEventListener('submit', function(e) {
            const statusChecked = document.querySelector('input[name="status"]:checked');
            const techName = document.getElementById('technician_name').value;

            if (statusChecked) {
                const status = statusChecked.value;
                if ((status === 'กำลังดำเนินการ' || status === 'ซ่อมเสร็จแล้ว' || status === 'ช่างรับเรื่องแจ้งซ่อมแล้ว') && techName === '') {
                    e.preventDefault(); 
                    Swal.fire({
                        icon: 'warning',
                        title: 'ลืมระบุชื่อช่างหรือเปล่าคะ?',
                        text: 'กรุณาเลือก "ช่างผู้รับผิดชอบ" ก่อนอัปเดตสถานะรับงานหรือปิดงานค่ะ',
                        confirmButtonColor: '#0284c7',
                        confirmButtonText: 'ตกลงเข้าใจแล้ว'
                    });
                }
            }
        });
    </script>

    <?php if($show_asset_alert): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'เพิ่มครุภัณฑ์ใหม่สำเร็จ!',
            text: 'รหัสครุภัณฑ์นี้ถูกเพิ่มเข้าสู่ระบบ และพร้อมเชื่อมโยงกับใบงานแล้ว',
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'ตกลง'
        });
    </script>
    <?php endif; ?>

    <?php if($show_alert): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกข้อมูลใบงานสำเร็จ!',
            text: 'อัปเดตสถานะและส่งแจ้งเตือนผ่าน LINE เรียบร้อยแล้ว',
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'dashboard.php?tab=repairs';
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>