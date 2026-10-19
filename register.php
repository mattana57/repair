<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db_connect.php';

// ==========================================
// ป้องกันฝั่งเซิร์ฟเวอร์ (Server-side Protection)
// เปลี่ยนค่าเป็น false หากต้องการปิดไม่ให้สมัครสมาชิกด้วยตนเอง
$ALLOW_REGISTRATION = true; 

if (!$ALLOW_REGISTRATION) {
    // หากปิดการสมัคร จะบล็อกทั้งการเข้าหน้าเว็บและการยิง POST เถื่อนทันที (ไม่ได้ซ่อนแค่ลิงก์)
    die("<div style='display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; font-family:sans-serif; background:#f8fafc;'>
            <h2 style='color:#334155;'>ระบบปิดรับการสมัครสมาชิกชั่วคราว</h2>
            <p style='color:#64748b;'>กรุณาติดต่อผู้ดูแลระบบ (Admin) เพื่อสร้างบัญชี</p>
            <a href='login.php' style='margin-top:15px; padding:10px 20px; background:#4f46e5; color:#fff; text-decoration:none; border-radius:8px;'>กลับไปหน้าเข้าสู่ระบบ</a>
         </div>");
}
// ==========================================

$error = '';
$success = '';
$form_values = ['full_name' => '', 'phone' => '', 'email' => '', 'username' => '', 'position' => '', 'department' => '', 'position_custom' => '', 'department_custom' => ''];

$registration_positions = ['นักวิชาการคอมพิวเตอร์', 'นักวิชาการโสตทัศนศึกษา', 'เจ้าหน้าที่บริหารงานทั่วไป', 'พนักงานขับรถยนต์'];
$registration_departments = ['ฝ่ายงานบริการเทคโนโลยีดิจิทัล', 'ฝ่ายงานยานยนต์', 'ฝ่ายงานโสตทัศนูปกรณ์'];

require_once __DIR__ . '/password_policy.php';
require_once __DIR__ . '/account_registration_service.php';
$registration_password_policy = passwordPolicyConfig();

if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $received_csrf = $_POST['csrf_token'] ?? null;
    $fields = ['full_name', 'phone', 'email', 'username', 'password', 'confirm_password', 'position', 'department'];
    $input = [];
    $valid_types = true;
    foreach ($fields as $field) {
        $value = $_POST[$field] ?? null;
        if (!is_string($value)) {
            $valid_types = false;
        } else {
            $input[$field] = $value;
        }
    }

    foreach (['position_custom', 'department_custom'] as $field) {
        $value = $_POST[$field] ?? '';
        if (!is_string($value)) $valid_types = false;
        else $input[$field] = $value;
    }

    if (!is_string($received_csrf)
        || !hash_equals($_SESSION['csrf_token'], $received_csrf)) {
        $error = 'คำขอไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่';
    } elseif (!$valid_types) {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่องและใช้รูปแบบข้อมูลที่ถูกต้อง';
    } else {
        foreach ($form_values as $field => $unused) {
            $form_values[$field] = trim($input[$field]);
        }
        $full_name = $form_values['full_name'];
        $phone = $form_values['phone'];
        $email = $form_values['email'];
        $username = $form_values['username'];
        $position = $form_values['position'] === 'อื่นๆ'
            ? $form_values['position_custom']
            : (in_array($form_values['position'], $registration_positions, true) ? $form_values['position'] : '');
        $department = $form_values['department'] === 'อื่นๆ'
            ? $form_values['department_custom']
            : (in_array($form_values['department'], $registration_departments, true) ? $form_values['department'] : '');
        // รักษารหัสผ่านตามที่กรอก ไม่ trim และไม่ใส่กลับลง HTML
        $password = $input['password'];
        $confirmation = $input['confirm_password'];
        $rules = passwordPolicyRules($password);

        if ($full_name === '' || $phone === '' || $email === '' || $username === ''
            || $position === '' || $department === '' || $password === '' || $confirmation === '') {
            $error = 'กรุณากรอกข้อมูลให้ครบทั้ง 8 ช่อง';
        } elseif (mb_strlen($full_name, 'UTF-8') > 255 || mb_strlen($username, 'UTF-8') > 50
            || mb_strlen($phone, 'UTF-8') > 20 || mb_strlen($position, 'UTF-8') > 255
            || mb_strlen($department, 'UTF-8') > 255
            || preg_match('/[\x00-\x1F\x7F]/u', $full_name . $username . $phone . $position . $department)) {
            $error = 'ข้อมูลยาวเกินกำหนดหรือมีอักขระควบคุม กรุณาตรวจสอบข้อมูลที่กรอก';
        } elseif (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'กรุณากรอกอีเมลที่มีรูปแบบถูกต้อง';
        } elseif (in_array(false, $rules, true)) {
            $error = 'รหัสผ่านต้องผ่านเงื่อนไขที่แสดงใต้ช่องรหัสผ่านทุกข้อ';
        } elseif ($password !== $confirmation) {
            $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
        } elseif (arGateRegistration($conn, 'Technician', array_merge($form_values, ['position'=>$position, 'department'=>$department]), $password, $error, $success)) {
            // บันทึกชื่อผู้ใช้และรหัสผ่านจากฟอร์มลงในรายการที่แอดมินเตรียมไว้
        } else {
            $stmt_check = null;
            $stmt_insert = null;
            $stmt_tech = null;
            $registration_started = false;
            try {
                $stmt_check = $conn->prepare('SELECT id FROM users WHERE username = ?');
                if (!$stmt_check || !$stmt_check->bind_param('s', $username)
                    || !$stmt_check->execute()) {
                    throw new RuntimeException('Registration lookup failed');
                }
                $result = $stmt_check->get_result();
                if (!$result) {
                    throw new RuntimeException('Registration lookup failed');
                }
                $exists = $result->num_rows > 0;
                $result->free();
                $stmt_check->close();
                $stmt_check = null;

                if ($exists) {
                    $error = 'ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว กรุณากรอกชื่อผู้ใช้ใหม่แล้วลองอีกครั้ง';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    if (!is_string($hashed_password)) {
                        throw new RuntimeException('Registration hash failed');
                    }
                    // สิทธิ์และรหัสเจ้าหน้าที่กำหนดโดยเซิร์ฟเวอร์ ไม่รับจากฟอร์ม
                    $role = 'Technician';
                    $is_active = 1;
                    $auth_version = 1;
                    if (!$conn->begin_transaction()) {
                        throw new RuntimeException('Registration transaction failed');
                    }
                    $registration_started = true;
                    // สร้างบัญชีไว้ก่อน ต้องผูก LINE จึงเข้า Dashboard ได้ ไม่จับคู่ช่างเดิมด้วยชื่อ
                    $stmt_tech = $conn->prepare(
                        "INSERT INTO technicians (full_name, phone, email, position, department,
                            approval_status, admin_new_notice)
                         VALUES (?, ?, ?, ?, ?, 'รอผูกบัญชี', 1)"
                    );
                    if (!$stmt_tech || !$stmt_tech->bind_param('sssss',
                        $full_name, $phone, $email, $position, $department)
                        || !$stmt_tech->execute() || $stmt_tech->affected_rows !== 1) {
                        throw new RuntimeException('Technician registration failed');
                    }
                    $tech_id = (int)$conn->insert_id;
                    if ($tech_id < 1) throw new RuntimeException('Technician identity missing');
                    $stmt_tech->close();
                    $stmt_tech = null;
                    // อีเมลยังไม่ยืนยัน ใช้ขั้นตอนยืนยันอีเมลเดิมภายหลัง
                    $stmt_insert = $conn->prepare(
                        'INSERT INTO users (username, password, full_name, phone, email,
                            role, is_active, auth_version, technician_id, position, department,
                            verified_email, email_verified_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)'
                    );
                    if (!$stmt_insert || !$stmt_insert->bind_param('ssssssiiiss',
                        $username, $hashed_password, $full_name, $phone, $email,
                        $role, $is_active, $auth_version, $tech_id, $position, $department)) {
                        throw new RuntimeException('Registration insert failed');
                    }
                    if (!$stmt_insert->execute() || $stmt_insert->affected_rows !== 1) {
                        $duplicate = (int)$stmt_insert->errno === 1062;
                        throw new RuntimeException('Account registration failed', $duplicate ? 1062 : 0);
                    }
                    if (!$conn->commit()) throw new RuntimeException('Registration commit failed');
                    $registration_started = false;
                    $success = 'สมัครสมาชิกเจ้าหน้าที่สำเร็จ! ข้อมูลแสดงในหน้า Team ของแอดมินแล้ว กรุณาผูกบัญชี LINE ก่อนเข้าสู่ Dashboard เจ้าหน้าที่ อีเมลที่กรอกยังไม่ได้รับการยืนยัน';
                }
            } catch (Throwable $e) {
                if ($registration_started) {
                    try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Registration rollback failed'); }
                }
                if ((int) $e->getCode() === 1062) {
                    $error = 'ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว กรุณากรอกชื่อผู้ใช้ใหม่แล้วลองอีกครั้ง';
                } else {
                    error_log('Registration failed');
                    $error = 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่ภายหลัง';
                }
            } finally {
                if ($stmt_tech) { $stmt_tech->close(); }
                if ($stmt_check) { $stmt_check->close(); }
                if ($stmt_insert) { $stmt_insert->close(); }
            }
        }
    }
    unset($password, $confirmation, $input, $hashed_password);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก | MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Kanit', sans-serif; }</style>

    <style>
        @supports (appearance: base-select) {
            .registration-select, .registration-select::picker(select) {appearance:base-select;}
            .registration-select::picker(select) {background:white;border:1px solid #e2e8f0;border-radius:16px;padding:6px;box-shadow:0 12px 28px #0f172a1a;max-height:260px;overflow-y:auto;}
            .registration-select option {border-radius:12px;padding:9px 12px;margin:2px 0;color:#334155;font-weight:500;}
            .registration-select option:hover, .registration-select option:focus {background:#f1f5f9;}
            .registration-select option:checked {background:#eef2ff;color:#4f46e5;}
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-6 sm:py-10">
    <div class="w-full max-w-4xl bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-100">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">สมัครสมาชิกเจ้าหน้าที่</h2>
            <p class="text-slate-500 text-sm mt-1">สร้างบัญชีเพื่อเข้าสู่ระบบแจ้งซ่อม</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-500 p-3 rounded-xl text-sm font-bold mb-4 text-center border border-rose-100">
                <i class="fas fa-exclamation-circle mr-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl text-sm font-bold mb-4 text-center border border-emerald-100">
                <i class="fas fa-check-circle mr-1"></i> <?php echo $success; ?>
            </div>
            <a href="login.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center px-4 py-3 rounded-xl font-bold transition-all mt-4">กลับไปหน้าเข้าสู่ระบบ</a>
        <?php else: ?>
            <form id="registrationForm" action="" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 items-start">
                    <!-- ฝั่งซ้าย: ชื่อ เบอร์โทร และอีเมล -->
                    <div class="space-y-4 min-w-0">
                        <div>
                            <label for="full_name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ชื่อ–นามสกุล <span class="text-rose-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" required maxlength="255" autocomplete="name"
                                value="<?php echo htmlspecialchars($form_values['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">เบอร์โทรศัพท์ <span class="text-rose-500">*</span></label>
                            <input type="tel" name="phone" id="phone" required maxlength="20" autocomplete="tel"
                                value="<?php echo htmlspecialchars($form_values['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                        </div>
                        <div>
                            <label for="email" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">อีเมล <span class="text-rose-500">*</span></label>
                            <input type="email" name="email" id="email" required maxlength="255" autocomplete="email"
                                value="<?php echo htmlspecialchars($form_values['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                placeholder="name@example.com"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                            <p class="text-xs text-slate-500 mt-1.5">การกรอกอีเมลยังไม่ถือเป็นการยืนยันอีเมล</p>
                        </div>
                        <div>
                            <label for="position" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ตำแหน่งงาน <span class="text-rose-500">*</span></label>
                            <select name="position" id="position" required onchange="toggleRegistrationOther('position')"
                                class="registration-select w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                                <option value="">-- เลือกตำแหน่งงาน --</option>
                                <?php foreach ($registration_positions as $registration_option): ?>
                                <option value="<?= htmlspecialchars($registration_option, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $form_values['position'] === $registration_option ? 'selected' : '' ?>><?= htmlspecialchars($registration_option, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                <option value="อื่นๆ" <?= $form_values['position'] === 'อื่นๆ' ? 'selected' : '' ?>>อื่นๆ (พิมพ์เอง)</option>
                            </select>
                            <div id="positionCustomField" class="mt-2 <?= $form_values['position'] === 'อื่นๆ' ? '' : 'hidden' ?>">
                                <label for="position_custom" class="sr-only">ระบุตำแหน่งงานอื่นๆ</label>
                                <input type="text" name="position_custom" id="position_custom" maxlength="255" placeholder="กรุณาระบุตำแหน่งงาน"
                                    <?= $form_values['position'] === 'อื่นๆ' ? 'required' : 'disabled' ?>
                                    value="<?= htmlspecialchars($form_values['position_custom'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                            </div>
                        </div>
                        <div>
                            <label for="department" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ฝ่ายงาน <span class="text-rose-500">*</span></label>
                            <select name="department" id="department" required onchange="toggleRegistrationOther('department')"
                                class="registration-select w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                                <option value="">-- เลือกฝ่ายงาน --</option>
                                <?php foreach ($registration_departments as $registration_option): ?>
                                <option value="<?= htmlspecialchars($registration_option, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $form_values['department'] === $registration_option ? 'selected' : '' ?>><?= htmlspecialchars($registration_option, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                <option value="อื่นๆ" <?= $form_values['department'] === 'อื่นๆ' ? 'selected' : '' ?>>อื่นๆ (พิมพ์เอง)</option>
                            </select>
                            <div id="departmentCustomField" class="mt-2 <?= $form_values['department'] === 'อื่นๆ' ? '' : 'hidden' ?>">
                                <label for="department_custom" class="sr-only">ระบุฝ่ายงานอื่นๆ</label>
                                <input type="text" name="department_custom" id="department_custom" maxlength="255" placeholder="กรุณาระบุฝ่ายงาน"
                                    <?= $form_values['department'] === 'อื่นๆ' ? 'required' : 'disabled' ?>
                                    value="<?= htmlspecialchars($form_values['department_custom'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                            </div>
                        </div>
                    </div>
                    <!-- ฝั่งขวา: Username รหัสผ่าน และยืนยันรหัสผ่าน -->
                    <div class="space-y-4 min-w-0">
                        <div>
                            <label for="username" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ชื่อผู้ใช้งาน <span class="text-rose-500">*</span></label>
                            <input type="text" name="username" id="username" required maxlength="50" autocomplete="username"
                                value="<?php echo htmlspecialchars($form_values['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                        </div>
                        <div>
                            <label for="password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">รหัสผ่าน <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password" id="password" required autocomplete="new-password" aria-describedby="passwordRules"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pr-12 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                                <button type="button" onclick="togglePassword('password', 'eyeIcon', this)" aria-label="แสดงรหัสผ่าน" aria-pressed="false"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors">
                                    <i id="eyeIcon" class="fas fa-eye-slash text-sm" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ยืนยันรหัสผ่าน <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password" aria-describedby="confirmMessage"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pr-12 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                                <button type="button" onclick="togglePassword('confirm_password', 'confirmEyeIcon', this)" aria-label="แสดงรหัสผ่านยืนยัน" aria-pressed="false"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors">
                                    <i id="confirmEyeIcon" class="fas fa-eye-slash text-sm" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p id="confirmMessage" class="text-xs text-slate-500 mt-1.5" aria-live="polite">○ กรอกรหัสผ่านอีกครั้งให้ตรงกัน</p>
                        </div>
                        <ul id="passwordRules" class="mt-3 rounded-xl bg-slate-50 p-3 space-y-1.5 text-xs text-slate-500" aria-live="polite">
                            <li data-rule="length">○ มีอย่างน้อย 15 ตัวอักษร</li>
                            <li data-rule="uppercase">○ มีตัวพิมพ์ใหญ่ A–Z</li>
                            <li data-rule="lowercase">○ มีตัวพิมพ์เล็ก a–z</li>
                            <li data-rule="number">○ มีตัวเลข 0–9</li>
                            <li data-rule="special">○ มีอักขระพิเศษ เช่น _ . ! @ # $ % &amp; *</li>
                            <li data-rule="bytes">○ ไม่เกิน 72 ไบต์ (ตัวอักษรไทยใช้หลายไบต์)</li>
                            <li data-rule="printable">○ ไม่มีอักขระควบคุม เช่น ขึ้นบรรทัดใหม่</li>
                            <li data-rule="common">○ ไม่ใช่รหัสตัวอย่างที่เดาง่ายในรายการของระบบ</li>
                        </ul>
                    </div>
                </div>
                <p class="text-xs text-slate-500">ช่องที่มี * ต้องกรอกทุกช่อง</p>
                <div class="text-center">
                    <a href="register_management.php" class="text-sm text-rose-600 hover:text-rose-700 font-bold transition-colors">สมัครบัญชีผู้ดูแลระบบ / ผู้บริหาร</a>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl font-bold transition-all shadow-md mt-6">ยืนยันการสมัครสมาชิก</button>
            </form>
            <div class="text-center mt-6">
                <a href="login.php" class="text-sm text-slate-500 hover:text-indigo-600 font-bold transition-colors">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="password_policy.js"></script>
    <script>
        const registrationPolicy = <?php echo json_encode($registration_password_policy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function togglePassword(inputId, iconId, button) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!input || !icon) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', show);
            icon.classList.toggle('fa-eye-slash', !show);
            if (button) {
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.setAttribute('aria-label', show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
            }
        }

            function registrationPasswordRules(password) {
            return PasswordPolicy.rules(password, registrationPolicy);
        }

        function toggleRegistrationOther(key) {
            const select = document.getElementById(key);
            const input = document.getElementById(key + '_custom');
            const field = document.getElementById(key + 'CustomField');
            if (!select || !input || !field) return;
            const custom = select.value === 'อื่นๆ';
            field.classList.toggle('hidden', !custom);
            input.disabled = !custom;
            input.required = custom;
            input.setCustomValidity(custom && input.value !== '' && input.value.trim() === ''
                ? 'กรุณากรอกข้อมูลที่ไม่เป็นช่องว่างทั้งหมด' : '');
        }
        ['position', 'department'].forEach(key => {
            const input = document.getElementById(key + '_custom');
            if (!input) return;
            input.addEventListener('input', () => toggleRegistrationOther(key));
            toggleRegistrationOther(key);
        });

        const registrationForm = document.getElementById('registrationForm');
        if (registrationForm) {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const message = document.getElementById('confirmMessage');
            const ruleRows = Array.from(document.querySelectorAll('#passwordRules [data-rule]'));
            ruleRows.forEach(row => { row.dataset.label = row.textContent.substring(2); });

            function updateRegistrationValidation() {
                const password = passwordInput.value;
                const rules = registrationPasswordRules(password);
                ruleRows.forEach(row => {
                    const passed = password !== '' && rules[row.dataset.rule];
                    row.textContent = (passed ? '✅ ' : '○ ') + row.dataset.label;
                    row.classList.toggle('text-emerald-700', passed);
                    row.classList.toggle('text-slate-500', !passed);
                });
                const validPassword = Object.values(rules).every(Boolean);
                passwordInput.setCustomValidity(password === '' || validPassword
                    ? '' : 'กรุณาตั้งรหัสผ่านให้ผ่านเงื่อนไขทุกข้อ');
                const matched = password !== '' && password === confirmInput.value;
                confirmInput.setCustomValidity(confirmInput.value === '' || matched
                    ? '' : 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
                message.textContent = matched ? '✅ รหัสผ่านตรงกัน'
                    : (confirmInput.value === '' ? '○ กรอกรหัสผ่านอีกครั้งให้ตรงกัน' : 'รหัสผ่านยังไม่ตรงกัน');
                message.classList.toggle('text-emerald-700', matched);
                message.classList.toggle('text-rose-600', confirmInput.value !== '' && !matched);
                message.classList.toggle('text-slate-500', confirmInput.value === '');
            }
            ['full_name', 'phone', 'email', 'username', 'position', 'department'].forEach(id => {
                const input = document.getElementById(id);
                input.addEventListener('input', () => {
                    input.setCustomValidity(input.value !== '' && input.value.trim() === ''
                        ? 'กรุณากรอกข้อมูลที่ไม่เป็นช่องว่างทั้งหมด' : '');
                });
            });
            passwordInput.addEventListener('input', updateRegistrationValidation);
            confirmInput.addEventListener('input', updateRegistrationValidation);
            registrationForm.addEventListener('submit', event => {
                updateRegistrationValidation();
                toggleRegistrationOther('position');
                toggleRegistrationOther('department');
                ['full_name', 'phone', 'email', 'username', 'position', 'department'].forEach(id => {
                    const input = document.getElementById(id);
                    input.setCustomValidity(input.value.trim() === '' ? 'กรุณากรอกข้อมูลช่องนี้' : '');
                });
                if (!registrationForm.checkValidity()) {
                    event.preventDefault();
                    registrationForm.reportValidity();
                }
            });
            updateRegistrationValidation();
        }

    </script>
</body>
</html>