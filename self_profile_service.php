<?php
// บัญชีเจ้าของ Session เท่านั้น ไม่รับ user_id / role / technician_id จากฟอร์ม
function spPositions()
{
    return ['นักวิชาการคอมพิวเตอร์','นักวิชาการโสตทัศนศึกษา','เจ้าหน้าที่บริหารงานทั่วไป','พนักงานขับรถยนต์'];
}
function spDepartments()
{
    return ['ฝ่ายงานบริการเทคโนโลยีดิจิทัล','ฝ่ายงานยานยนต์','ฝ่ายงานโสตทัศนูปกรณ์','แม่บ้าน'];
}
function spStatement($db,$sql,$types='',$values=[])
{
    $s=$db->prepare($sql);
    if (!$s) throw new RuntimeException('Profile database operation failed');
    try {
        if ($types!=='' && !$s->bind_param($types,...$values)) throw new RuntimeException('Profile binding failed');
        if (!$s->execute()) throw new RuntimeException('Profile database operation failed',(int)$s->errno);
        return $s;
    } catch (Throwable $e) { $s->close(); throw $e; }
}
function spRow($db,$sql,$types='',$values=[])
{
    $s=spStatement($db,$sql,$types,$values);
    try {
        $r=$s->get_result();
        if (!$r) throw new RuntimeException('Profile result failed');
        return $r->num_rows===1 ? $r->fetch_assoc() : null;
    } finally { $s->close(); }
}
function spWrite($db,$sql,$types,$values)
{
    $s=spStatement($db,$sql,$types,$values);
    try { return $s->affected_rows; } finally { $s->close(); }
}
function spLoad($db,$session,$lock=false)
{
    if (!in_array(strtolower((string)($session['role']??'')),['executive','technician'],true)
        || authGuardPositiveInt($session['user_id']??null)===false) throw new DomainException('ไม่อนุญาตให้แก้ไขบัญชีนี้');
    $suffix=$lock ? ' FOR UPDATE' : '';
    $u=spRow($db,'SELECT * FROM users WHERE id=?'.$suffix,'i',[(int)$session['user_id']]);
    if (!$u || $u['deleted_at']!==null) throw new DomainException('ไม่พบบัญชีที่ใช้งานได้');
    $u['linked_technician_id']=null;
    $t=null;
    if (strtolower((string)$u['role'])==='technician') {
        $t=spRow($db,'SELECT * FROM technicians WHERE id=?'.$suffix,'i',[(int)$u['technician_id']]);
        if (!$t || $t['deleted_at']!==null || $t['approval_status']!=='อนุมัติแล้ว') throw new DomainException('สิทธิ์เจ้าหน้าที่เปลี่ยนแล้ว กรุณาเข้าสู่ระบบใหม่');
        $u['linked_technician_id']=$t['id'];
    }
    if (!authGuardSessionMatches($u,$session,time()) || (int)$u['must_change_password']!==0) throw new DomainException('สิทธิ์บัญชีเปลี่ยนแล้ว กรุณาเข้าสู่ระบบใหม่');
    return ['user'=>$u,'technician'=>$t];
}
function spProfile($account)
{
    $u=$account['user']; $p=$account['technician']??$u;
    $out=['role'=>strtolower($u['role'])==='technician' ? 'Technician' : 'Executive','username'=>(string)$u['username']];
    foreach (['full_name','english_name','phone','email','position','department'] as $key) $out[$key]=(string)($p[$key]??'');
    return $out;
}
function spRevision($account)
{
    $u=$account['user'];
    return hash('sha256',json_encode([spProfile($account),(int)$u['id'],$u['role'],$u['technician_id'],(int)$u['auth_version'],$u['verified_email'],$u['email_verified_at']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
}
function spText($input,$key,$required,$limit)
{
    if (!is_string($input[$key]??null)) throw new DomainException('กรุณากรอกข้อมูลให้ถูกต้อง');
    $v=trim($input[$key]);
    if (($required && $v==='') || mb_strlen($v,'UTF-8')>$limit || preg_match('/[\x00-\x1F\x7F]/u',$v)) throw new DomainException('กรุณาตรวจสอบข้อมูลที่จำเป็นและความยาวของข้อมูล');
    return $v;
}
function spDropdown($input,$name,$options)
{
    $choice=spText($input,$name.'_select',true,255);
    if ($choice==='อื่นๆ') return spText($input,$name.'_custom',true,255);
    if (!in_array($choice,$options,true)) throw new DomainException('กรุณาเลือกตำแหน่งงานหรือฝ่ายงานจากรายการ หรือเลือกอื่นๆ');
    return $choice;
}
function spSave($db,$session,$input)
{
    if (!is_string($input['csrf_token']??null) || !is_string($session['csrf_token']??null)
        || $session['csrf_token']==='' || !hash_equals($session['csrf_token'],$input['csrf_token'])) throw new DomainException('คำขอหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    if (!is_string($input['revision']??null) || !preg_match('/\A[a-f0-9]{64}\z/',$input['revision'])) throw new DomainException('กรุณาเปิดหน้าต่างแก้ไขข้อมูลใหม่');
    $v=[];
    foreach (['username'=>50,'full_name'=>255,'english_name'=>255,'phone'=>20,'email'=>255] as $key=>$limit) $v[$key]=spText($input,$key,$key!=='english_name',$limit);
    if (strlen($v['email'])>255 || !filter_var($v['email'],FILTER_VALIDATE_EMAIL)) throw new DomainException('กรุณากรอกอีเมลที่ถูกต้อง');
    $isTech=strtolower((string)($session['role']??''))==='technician';
    if ($isTech) {
        $v['position']=spDropdown($input,'position',spPositions());
        $v['department']=spDropdown($input,'department',spDepartments());
    }
    if (!$db->begin_transaction()) throw new RuntimeException('Profile transaction failed');
    try {
        $a=spLoad($db,$session,true); $u=$a['user']; $p=$a['technician']??$u;
        if (!hash_equals(spRevision($a),$input['revision'])) throw new DomainException('ข้อมูลถูกแก้ไขจากที่อื่น กรุณาปิดแล้วเปิดหน้าต่างนี้ใหม่');
        if (spRow($db,'SELECT id FROM users WHERE username=? AND id<>? LIMIT 1','si',[$v['username'],(int)$u['id']])) throw new DomainException('ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว กรุณากรอกชื่อผู้ใช้ใหม่แล้วลองอีกครั้ง');
        $emailChanged=(string)($p['email']??'')!==$v['email'];
        $position=$isTech ? $v['position'] : $u['position'];
        $department=$isTech ? $v['department'] : $u['department'];
        $verified=$emailChanged ? null : $u['verified_email'];
        $verifiedAt=$emailChanged ? null : $u['email_verified_at'];
        $english=$v['english_name']==='' ? null : $v['english_name'];
        $count=spWrite($db,'UPDATE users SET username=?,full_name=?,english_name=?,phone=?,email=?,position=?,department=?,verified_email=?,email_verified_at=?,auth_version=auth_version+1 WHERE id=? AND auth_version=? AND is_active=1 AND deleted_at IS NULL','sssssssssii',[$v['username'],$v['full_name'],$english,$v['phone'],$v['email'],$position,$department,$verified,$verifiedAt,(int)$u['id'],(int)$u['auth_version']]);
        if ($count!==1) throw new RuntimeException('Profile account update failed');
        if ($isTech) {
            spWrite($db,'UPDATE technicians SET full_name=?,english_name=?,phone=?,email=?,position=?,department=? WHERE id=? AND deleted_at IS NULL','ssssssi',[$v['full_name'],$english,$v['phone'],$v['email'],$position,$department,(int)$u['technician_id']]);
        }
        if ($emailChanged) spWrite($db,"UPDATE auth_requests SET is_canceled=1 WHERE user_id=? AND purpose IN ('email_verification','password_reset') AND is_canceled=0",'i',[(int)$u['id']]);
        if (!$db->commit()) throw new RuntimeException('Profile commit failed');
        // คืนเฉพาะตัวตนเดิมและรุ่นสิทธิ์ใหม่ให้ endpoint คง session ปัจจุบันไว้
        return ['username'=>$v['username'],'full_name'=>$v['full_name'],'english_name'=>$v['english_name'],'auth_version'=>(int)$u['auth_version']+1,'email_changed'=>$emailChanged];
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
