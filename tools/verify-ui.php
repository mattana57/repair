<?php
// CLI only. Checks presentation edits without executing the application or SQL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$baseline = $argv[1] ?? null;
if ($baseline !== null && !is_dir($baseline)) { fwrite(STDERR, "Usage: php tools/verify-ui.php [PATH_TO_BASELINE]\n"); exit(2); }
$pages = ['dashboard.php','executive_dashboard.php','index.php','login.php','form_repair.php','view_repair.php','update_repair.php','executive_report.php','executive_summary_report.php','print_report.php','dashboard.html','executive_dashboard.html','index.html','report_form.html'];
function phpBlocks($src) {
    $out = [];
    foreach (token_get_all($src) as $t) if (!is_array($t) || $t[0] !== T_INLINE_HTML) $out[] = $t;
    // Line positions legitimately change when HTML is added.
    return array_map(function($t) { return is_array($t) ? [$t[0],$t[1]] : $t; }, $out);
}
function scripts($src) { preg_match_all('~<script\b[^>]*>.*?</script\s*>~is',$src,$m); return $m[0]; }
function visibleText($src) {
    $html='';
    foreach (token_get_all($src) as $t) if (is_array($t) && $t[0] === T_INLINE_HTML) $html .= $t[1];
    $html=preg_replace('~<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<!--.*?-->~is','',$html);
    return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($html),ENT_QUOTES,'UTF-8')));
}
function contract($src) {
    // Mask PHP so quoted strings inside expressions do not confuse attributes.
    $html = ''; $php = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t) && $t[0] === T_INLINE_HTML) {
            if ($php !== '') { $html .= '__PHP_' . hash('sha256',$php) . '__'; $php=''; }
            $html .= $t[1];
        } else $php .= is_array($t) ? $t[1] : $t;
    }
    $html = preg_replace('~<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<!--.*?-->~is','',$html);
    preg_match_all('~<([a-z][a-z0-9]*)\b(?:[^>\'\"]+|\"[^\"]*\"|\'[^\']*\')*>~i',$html,$tags,PREG_SET_ORDER);
    $out=[];
    foreach ($tags as $tag) {
        if (in_array(strtolower($tag[1]),['link','meta'])) continue;
        preg_match_all('~\b(id|name|value|type|href|src|action|method|enctype|for|on[a-z]+|data-[\w-]+)\s*=\s*([\'\"])(.*?)\2~s',$tag[0],$attrs,PREG_SET_ORDER);
        foreach ($attrs as $a) $out[] = [$tag[1],$a[1],$a[3]];
    }
    return $out;
}
$fail = 0;
foreach ($pages as $name) {
    $before = $baseline !== null ? file_get_contents($baseline.'/'.$name) : shell_exec('git -C '.escapeshellarg($root).' show HEAD:'.$name);
    if (!is_string($before) || $before === '') { fwrite(STDERR,"Missing baseline: $name\n"); exit(2); }
    $after=file_get_contents($root.'/'.$name);
    // Git stores LF while this Windows checkout uses CRLF. A filesystem
    // baseline remains byte-exact; only the Git comparison normalizes EOLs.
    if ($baseline === null) { $before = str_replace("\r\n","\n",$before); $after = str_replace("\r\n","\n",$after); }
    $afterText = visibleText($after);
    if (in_array($name,['dashboard.php','executive_dashboard.php'])) {
        $afterText = str_replace('MBS Repair Dashboard Overview ภาพรวมงานแจ้งซ่อม สถานะการดำเนินงาน และประสิทธิภาพการให้บริการ ', '', $afterText);
    }
    $checks=['PHP tokens'=>phpBlocks($before)===phpBlocks($after), 'scripts'=>scripts($before)===scripts($after), 'form/link/event contracts'=>contract($before)===contract($after), 'original text'=>visibleText($before)===$afterText];
    foreach ($checks as $what=>$ok) if (!$ok) { echo "FAIL $name: $what\n"; $fail++; }
    if (!in_array(false,$checks,true)) echo "PASS $name: PHP, scripts, IDs, fields, values, links, handlers, original text unchanged\n";
}
exit($fail ? 1 : 0);
