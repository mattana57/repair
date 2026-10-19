<?php if (!defined('MBS_TECH_PERSONAL_PROFILE')) { http_response_code(403); exit; } ?>
<form id="profileAvatarForm" action="technician_home.php?tab=dash" method="post" enctype="multipart/form-data" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="tech_profile_action" value="upload">
    <input type="file" id="profileAvatarInput" name="profile_avatar" accept="image/jpeg,image/png,image/webp,image/gif" onchange="tpaPreview(this)">
</form>
<form id="tpaDeleteForm" action="technician_home.php?tab=dash" method="post" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="tech_profile_action" value="delete">
</form>
<dialog id="avatarPreviewConfirmModal" aria-label="ตัวอย่างรูปโปรไฟล์" class="tpa-preview">
        
        <!-- ✨ เอา pointer-events-none ออกจาก div และเอา pointer-events-auto ออกจาก button เพื่อไม่ให้ปุ่มล่องหนทะลุจอมาโดนคลิก ✨ -->
        <div class="hidden sm:flex absolute top-0 left-0 w-full justify-between items-center px-6 md:px-12 lg:px-16 py-5 md:py-6 shrink-0 z-40 bg-gradient-to-b from-black/80 to-transparent">
            <button type="button" onclick="tpaCancel()" class="w-12 h-12 flex items-center justify-center text-white hover:bg-white/10 rounded-full transition-colors cursor-pointer">
                <i class="fas fa-chevron-left text-2xl pr-1"></i>
            </button>
            <h3 class="text-white font-bold text-[16px] md:text-[18px] drop-shadow-md tracking-wide pointer-events-none">ตัวอย่างรูปโปรไฟล์</h3>
            <div class="w-12"></div>
        </div>

        <div class="relative w-full max-w-[400px] h-[550px] sm:max-w-none sm:w-full sm:h-full flex flex-col bg-[#0f0f0f] sm:bg-transparent rounded-[40px] sm:rounded-none overflow-hidden shadow-2xl sm:shadow-none mx-4 sm:mx-0">
            
            <div class="absolute top-4 left-0 w-full flex justify-center z-50 sm:hidden pointer-events-none">
                <div class="bg-white/90 backdrop-blur-md px-6 py-2 rounded-full shadow-lg border border-white/20">
                    <h3 class="text-slate-800 font-extrabold text-[14px] tracking-wide">ตัวอย่างรูปโปรไฟล์</h3>
                </div>
            </div>

            <div class="flex-1 relative overflow-hidden bg-[#0f0f0f]" id="dragContainer">
                <img id="avatarCropImage" src="" class="absolute left-1/2 top-1/2 pointer-events-none select-none" style="transform: translate(-50%, -50%) scale(1); will-change: transform;">

                <div class="absolute inset-0 pointer-events-none flex items-center justify-center z-20 overflow-hidden">
                    <div id="cropCircleMask" class="w-[320px] h-[320px] rounded-full shadow-[0_0_0_9999px_rgba(15,15,15,0.85)] border-2 border-white/30"></div>
                </div>

                <div id="dragTouchLayer" class="absolute inset-0 z-30 cursor-move" style="touch-action: none;"></div>
            </div>

            <div class="px-6 py-6 sm:pb-10 shrink-0 flex justify-center items-center gap-5 bg-[#0f0f0f] relative z-40 sm:border-t sm:border-white/10 sm:w-full sm:absolute sm:bottom-0 sm:left-0">
                <button type="button" onclick="tpaCancel()" class="py-2.5 px-8 sm:px-10 rounded-full bg-rose-600 text-white font-bold text-[14px] sm:text-[15px] hover:bg-rose-500 transition-colors shadow-lg shadow-rose-600/30">
                    ยกเลิก
                </button>
                <button type="button" onclick="tpaSave()" class="py-2.5 px-8 sm:px-10 rounded-full bg-blue-600 text-white font-bold text-[14px] sm:text-[15px] hover:bg-blue-500 transition-colors shadow-lg shadow-blue-600/30">
                    บันทึก
                </button>
            </div>

        </div>
    </dialog>
<script src="technician_profile_avatar.js?v=20261019-1"></script>
