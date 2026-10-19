<?php
if (!in_array(strtolower((string)($_SESSION['role']??'')),['executive','technician'],true)) return;
require_once __DIR__.'/self_profile_service.php';
$spIsTech=strtolower($_SESSION['role'])==='technician';
if (!is_string($_SESSION['csrf_token']??null) || $_SESSION['csrf_token']==='') $_SESSION['csrf_token']=bin2hex(random_bytes(32));
?>
<dialog id="selfProfileModal" class="sp-dialog" aria-labelledby="selfProfileTitle" data-role="<?= $spIsTech ? 'Technician' : 'Executive' ?>">
    <form id="selfProfileForm" method="POST" action="self_profile_endpoint.php" novalidate>
        <header class="sp-header">
            <h2 id="selfProfileTitle">Manage <?= $spIsTech ? 'Technician' : 'Executive' ?></h2>
            <button class="sp-close" type="button" data-sp-close aria-label="ปิดหน้าต่าง">×</button>
        </header>
        <div class="sp-body">
            <p id="selfProfileMessage" class="sp-message" role="alert" hidden></p>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8') ?>">
            <input type="hidden" name="revision" value="">
            <div class="sp-field"><label for="spUsername">Username <span>*</span></label><input id="spUsername" name="username" type="text" maxlength="50" autocomplete="username" required></div>
            <?php if (!$spIsTech): ?>
            <div class="sp-field"><label>Role Level</label><div class="sp-static" aria-label="Role Level: Executive">Executive</div></div>
            <?php endif; ?>
            <div class="sp-field"><label for="spFullName">Full Name <span>*</span></label><input id="spFullName" name="full_name" type="text" maxlength="255" autocomplete="name" required></div>
            <div class="sp-field"><label for="spEnglishName">English Name <small>(ไม่บังคับ)</small></label><input id="spEnglishName" name="english_name" type="text" maxlength="255"></div>
            <div class="sp-field"><label for="spPhone">Phone <span>*</span></label><input id="spPhone" name="phone" type="tel" maxlength="20" autocomplete="tel" required></div>
            <div class="sp-field"><label for="spEmail">Email <span>*</span></label><input id="spEmail" name="email" type="email" maxlength="255" autocomplete="email" required></div>
            <?php if ($spIsTech): ?>
            <?php foreach (['position'=>['Position','-- Select Position --',spPositions(),'ระบุตำแหน่งงานเพิ่มเติม'],'department'=>['Department','-- Select Department --',spDepartments(),'ระบุฝ่ายงานเพิ่มเติม']] as $key=>$config): ?>
            <div class="sp-field" data-sp-choice="<?= $key ?>">
                <label id="spLabel_<?= $key ?>" for="spChoice_<?= $key ?>"><?= $config[0] ?> <span>*</span></label>
                <input type="hidden" name="<?= $key ?>_select" value="">
                <div class="sp-combo">
                    <button id="spChoice_<?= $key ?>" class="sp-select" type="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="spOptions_<?= $key ?>" aria-labelledby="spLabel_<?= $key ?> spValue_<?= $key ?>"><span id="spValue_<?= $key ?>" data-sp-value data-placeholder="<?= $config[1] ?>"><?= $config[1] ?></span><i class="sp-chevron" aria-hidden="true"></i></button>
                    <div class="sp-options" id="spOptions_<?= $key ?>" role="listbox" aria-labelledby="spLabel_<?= $key ?>" hidden>
                        <?php foreach ($config[2] as $value): ?>
                        <button class="sp-option" type="button" role="option" aria-selected="false" data-value="<?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?></button>
                        <?php endforeach; ?>
                        <button class="sp-option" type="button" role="option" aria-selected="false" data-value="อื่นๆ">อื่นๆ (Custom)</button>
                    </div>
                </div>
                <input class="sp-custom" type="text" name="<?= $key ?>_custom" aria-label="<?= $config[3] ?>" placeholder="<?= $config[3] ?>" maxlength="255" hidden>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <footer class="sp-footer">
            <button class="sp-cancel" type="button" data-sp-close>Cancel</button>
            <button id="selfProfileSave" class="sp-save" type="submit">Save Data</button>
        </footer>
    </form>
</dialog>
<script src="self_profile.js?v=20261020-1"></script>
