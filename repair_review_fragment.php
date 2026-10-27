<?php
// Presentation only; no record updates, outgoing LINE calls, or schema changes.
require_once __DIR__ . '/repair_review_datetime.php';
function mbs_repair_review_fragment(array $repair, string $layout, bool $is_executive = false): string {
    ob_start();
    if ($layout === 'view') {
?>
                                <?php 
                                $has_rating = !empty($repair['rating']) && (int)$repair['rating'] > 0;
                                $has_comment = !empty($repair['review_comment']) && trim($repair['review_comment']) !== '' && trim($repair['review_comment']) !== '-';
                                if ($has_rating || $has_comment): 
                                ?>
                                    <?php if ($is_executive): ?>
                                    <!-- ✨ โครงสร้างสำหรับผู้บริหาร (มือถือ/ไอแพดแนวตั้ง): ชื่อ -> ดาว -> เวลา ✨ -->
                                    <div class="flex landscape:hidden items-start mb-3 gap-3">
                                        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 shadow-sm mt-0.5">
                                            <i class="fas fa-user text-sm"></i>
                                        </div>
                                        <!-- ✨ ปรับช่องไฟใหม่: ลบ gap ออก แล้วใช้ mt-0.5 กับ mb-1.5 ที่ดาว เพื่อดันให้ระยะห่างทั้ง 3 บรรทัดบาลานซ์กันพอดีสายตา 100% ✨ -->
                                        <div class="flex flex-col">
                                            <div class="text-sm font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($repair['reporter_name']); ?></div>
                                            <div class="flex items-center gap-0.5 mt-0.5 mb-1.5">
                                                <?php 
                                                $rating = (int)($repair['rating'] ?? 0);
                                                if ($rating > 0) {
                                                    for($i=1; $i<=5; $i++) {
                                                        if($i <= $rating) echo '<i class="fas fa-star text-amber-400 text-[13px] drop-shadow-sm"></i>';
                                                        else echo '<i class="fas fa-star text-slate-200 text-[13px]"></i>';
                                                    }
                                                } else {
                                                    echo '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                                                }
                                                ?>
                                            </div>
                                            <div class="text-[11px] text-slate-400 font-medium leading-tight">
                                                <?php 
                                                require_once __DIR__ . '/repair_review_datetime.php';
                                                    echo mbs_repair_review_datetime($repair);
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ✨ โครงสร้างสำหรับผู้บริหาร (คอมพิวเตอร์, มือถือ/ไอแพดแนวนอน) ✨ -->
                                    <div class="hidden portrait:hidden landscape:flex flex-col sm:flex-row sm:items-center justify-start mb-2 gap-1.5 sm:gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 shadow-sm">
                                                <i class="fas fa-user text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-800 flex items-center flex-wrap gap-2">
                                                    <span><?php echo htmlspecialchars($repair['reporter_name']); ?></span>
                                                    <!-- ✨ ดึงดาวมาต่อท้ายชื่อ ✨ -->
                                                    <div class="flex gap-0.5 pt-0.5 sm:pt-0">
                                                        <?php 
                                                        $rating = (int)($repair['rating'] ?? 0);
                                                        if ($rating > 0) {
                                                            for($i=1; $i<=5; $i++) {
                                                                if($i <= $rating) echo '<i class="fas fa-star text-amber-400 text-[13px] drop-shadow-sm"></i>';
                                                                else echo '<i class="fas fa-star text-slate-200 text-[13px]"></i>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="text-[11px] text-slate-400 font-medium">
                                                    <?php 
                                                    require_once __DIR__ . '/repair_review_datetime.php';
                                                    echo mbs_repair_review_datetime($repair);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- ✨ โครงสร้างสำหรับแอดมินและเจ้าหน้าที่ (ทุกอุปกรณ์) ✨ -->
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-start mb-2 gap-1.5 sm:gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 shadow-sm">
                                                <i class="fas fa-user text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-800 flex items-center flex-wrap gap-2">
                                                    <span><?php echo htmlspecialchars($repair['reporter_name']); ?></span>
                                                    <!-- ✨ ดึงดาวมาต่อท้ายชื่อ ✨ -->
                                                    <div class="flex gap-0.5 pt-0.5 sm:pt-0">
                                                        <?php 
                                                        $rating = (int)($repair['rating'] ?? 0);
                                                        if ($rating > 0) {
                                                            for($i=1; $i<=5; $i++) {
                                                                if($i <= $rating) echo '<i class="fas fa-star text-amber-400 text-[13px] drop-shadow-sm"></i>';
                                                                else echo '<i class="fas fa-star text-slate-200 text-[13px]"></i>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="text-[11px] text-slate-400 font-medium">
                                                    <?php 
                                                    require_once __DIR__ . '/repair_review_datetime.php';
                                                    echo mbs_repair_review_datetime($repair);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($has_comment): ?>
                                        <p class="text-sm text-slate-600 font-medium pl-[52px] leading-relaxed mt-1"><?php echo nl2br(htmlspecialchars(trim($repair['review_comment']))); ?></p>
                                    <?php endif; ?>

                                    <div class="pl-[52px] mt-2.5">
                                        <div class="text-[11px] text-indigo-600 font-bold inline-block bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                            <i class="fas fa-tools mr-1.5 opacity-70"></i>ให้คะแนนเจ้าหน้าที่: <?php echo !empty($repair['technician_name']) && $repair['technician_name'] !== '-' ? htmlspecialchars($repair['technician_name']) : 'ไม่ระบุเจ้าหน้าที่'; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2">
                                            <i class="fas fa-star text-slate-300 text-lg"></i>
                                        </div>
                                        <p class="text-slate-500 text-xs font-medium">ยังไม่มีการประเมินผลการซ่อม</p>
                                    </div>
                                <?php endif; ?><?php
    } else {
?>
                        <?php 
                        $has_rating = !empty($repair['rating']) && (int)$repair['rating'] > 0;
                        $has_comment = !empty($repair['review_comment']) && trim($repair['review_comment']) !== '' && trim($repair['review_comment']) !== '-';
                        if ($has_rating || $has_comment): 
                        ?>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-start mb-2 gap-1.5 sm:gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 shadow-sm">
                                                <i class="fas fa-user text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-800 flex items-center flex-wrap gap-2">
                                                    <span><?php echo htmlspecialchars($repair['reporter_name']); ?></span>
                                                    <!-- ✨ ย้ายดาวมาต่อท้ายชื่อตรงนี้ ✨ -->
                                                    <div class="flex gap-0.5 pt-0.5 sm:pt-0">
                                                        <?php 
                                                        $rating = (int)($repair['rating'] ?? 0);
                                                        if ($rating > 0) {
                                                            for($i=1; $i<=5; $i++) {
                                                                if($i <= $rating) echo '<i class="fas fa-star text-amber-400 text-[13px] drop-shadow-sm"></i>';
                                                                else echo '<i class="fas fa-star text-slate-200 text-[13px]"></i>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="text-[11px] text-slate-400 font-medium">
                                                    <?php 
                                                    require_once __DIR__ . '/repair_review_datetime.php';
                                                    echo mbs_repair_review_datetime($repair);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            <?php if($has_comment): ?>
                                <p class="text-sm text-slate-600 font-medium pl-[52px] leading-relaxed mt-1"><?php echo nl2br(htmlspecialchars(trim($repair['review_comment']))); ?></p>
                            <?php endif; ?>

                            <div class="pl-[52px] mt-2.5">
                                <div class="text-[11px] text-indigo-600 font-bold inline-block bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                    <i class="fas fa-tools mr-1.5 opacity-70"></i>ให้คะแนนเจ้าหน้าที่: <?php echo !empty($repair['technician_name']) && $repair['technician_name'] !== '-' ? htmlspecialchars($repair['technician_name']) : 'ไม่ระบุเจ้าหน้าที่'; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-star text-slate-300 text-lg"></i>
                                </div>
                                <p class="text-slate-500 text-xs font-medium">ยังไม่มีการประเมินผลการซ่อม</p>
                            </div>
                        <?php endif; ?><?php
    }
    return ob_get_clean();
}
