<?php
// Same structure, labels and page-number window as Transactions (50 rows/page).
function mbs_render_analytics_pagination(int $total, int $page, int $pages): void {
    $offset=($page-1)*50;
    $first=$total===0?0:$offset+1;$last=min($total,$offset+50);
    echo '<div class="mbs-pages mbs-analytics-pagination"><div class="tp-footer"><p class="tp-summary">แสดงรายการ <strong>'.number_format($first).'–'.number_format($last).'</strong> จากทั้งหมด <strong>'.number_format($total).'</strong> รายการ · หน้า '.number_format($page).' / '.number_format($pages).'</p><nav class="tp-nav" aria-label="แบ่งหน้ารายการ">';
    $link=static function(int $target,string $label,bool $current=false): void {
        if($current){echo '<span class="tp-link tp-current" aria-current="page">'.$label.'</span>';return;}
        $query=$_GET;$query['mbs_page']=$target;
        echo '<a class="tp-link" href="?'.mbs_h(http_build_query($query)).'">'.$label.'</a>';
    };
    if($page>1)$link($page-1,'‹ ก่อนหน้า');else echo '<span class="tp-disabled" aria-disabled="true">‹ ก่อนหน้า</span>';
    $numbers=array_unique(array_merge([1],range(max(1,$page-1),min($pages,$page+1)),[$pages]));sort($numbers);$previous=0;
    foreach($numbers as $number){if($previous&&$number>$previous+1)echo '<span class="tp-dots">…</span>';$link($number,number_format($number),$number===$page);$previous=$number;}
    if($page<$pages)$link($page+1,'ถัดไป ›');else echo '<span class="tp-disabled" aria-disabled="true">ถัดไป ›</span>';
    echo '</nav></div></div>';
}
