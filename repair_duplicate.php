<?php
// ใช้เปรียบเทียบแบบระมัดระวัง: ไม่ลบคำบอกอาการหรือจำนวนอุปกรณ์
function mbsDuplicateNormalize($text) {
    $text = mb_strtolower(trim((string)$text), 'UTF-8');
    $text = preg_replace('/(?:นะคะ|นะครับ|ค่ะ|ครับ|คะ|คับ|จ้า|จ๊ะ)[\s.!?]*$/u', '', $text);
    return preg_replace('/[\s\x{200B}.!,，。！？]+/u', '', $text);
}
// คำพ้องของอุปกรณ์เท่านั้น หากไม่อยู่ในกลุ่มให้ใช้ประเภทที่ระบบเดิมตรวจพบ
function mbsDuplicateEquipmentKey($category) {
    $c=mbsDuplicateNormalize($category);
    $groups=[
        "air"=>["แอร์", "แอร", "แอ์", "แอร์คอน", "แอร์คอนดิชั่น", "aircon", "airconditioner", "เครื่องปรับอากาศ"],
        "computer"=>["คอม", "คอมพิวเตอร์", "คอมพิวเต้อ", "คอมฯ", "pc", "computer", "เดสก์ท็อป", "เดสก์ทอป"],
        "notebook"=>["โน้ตบุ๊ก", "โน๊ตบุ๊ค", "โน๊ตบุค", "โน้ตบุ๊ค", "โนตบุค", "notebook", "laptop", "แล็ปท็อป"],
        "printer"=>["เครื่องพิมพ์", "เครื่องปริ้น", "เครื่องปริ๊น", "เครื่องปริน", "เครื่องพริน", "ปริ้นเตอร์", "ปริ๊นเตอร์", "ปรินเตอร์", "พรินเตอร์", "ปริ้น", "ปริ๊น", "printer", "พรินเตอร์"],
        "projector"=>["โปรเจ็คเตอร์", "โปรเจคเตอร์", "โปรเจกเตอร์", "โปรเจ็กเตอร์", "โปรเจคเตอ", "projector", "เครื่องฉาย"],
        "internet"=>["เน็ต", "เนต", "wifi", "wi-fi", "วายฟาย", "ไว้ฟาย", "ไวฟาย", "ไวไฟ", "อินเทอร์เน็ต", "อินเตอร์เน็ต", "อินเตอเน็ต", "internet"],
        "microphone"=>["ไมค์", "ไมค", "ไมโครโฟน", "microphone", "mic"],
        "monitor"=>["จอ", "จอภาพ", "จอมอนิเตอร์", "มอนิเตอร์", "monitor"],
        "television"=>["ทีวี", "โทรทัศน์", "television", "tv"],
        "fan"=>["พัดลม", "พัดลมไฟฟ้า"],
        "power_socket"=>["ปลั๊ก", "ปลั๊กไฟ", "เต้ารับ", "ปลั๊กไฟฟ้า"],
        "light_bulb"=>["หลอด", "หลอดไฟ", "หลอดไฟฟ้า"],
        "lift"=>["ลิฟต์", "ลิฟท์", "ลิฟ", "ลิฟต์โดยสาร"],
        "faucet"=>["ก๊อก", "ก๊อกน้ำ", "ก๊อกน้ํา", "ก๊อกนำ้"],
        "toilet"=>["ชักโครก", "โถส้วม"],
        "network_cable"=>["สายแลน", "สายlan", "lancable"],
        "tablet"=>["ไอแพด", "ipad"],
    ];
    foreach($groups as $key=>$words){foreach($words as $word){if($c===mbsDuplicateNormalize($word))return $key;}}
    return $c;
}
// ใช้อาการที่ระบุชัดเท่านั้น ไม่เดาเมื่อไม่รู้หรือมีคำแก้ว่าอาการหายแล้ว
function mbsDuplicateSymptomKeys($text) {
    $text=mbsDuplicateNormalize($text);
    foreach(['ไม่ใช่','ไม่พบ','ไม่ได้เสีย','ไม่เสีย','ไม่ได้พัง','ไม่พัง','ไม่ได้ชำรุด','ไม่ชำรุด','หายแล้ว','แก้แล้ว','ซ่อมแล้ว','ปกติแล้ว','กลับมาเย็น','เย็นปกติ'] as $negative){if(mb_strpos($text,$negative,0,'UTF-8')!==false)return [];}
    $patterns=[
        "not_cold"=>["ไม่เย็น", "ไม่ค่อยเย็น", "เย็นไม่ออก", "ไม่มีความเย็น", "ไม่ทำความเย็น", "ความเย็นไม่ออก", "ลมไม่เย็น", "ลมร้อน", "มีแต่ลมร้อน", "ออกแต่ลมร้อน", "แอร์ร้อน", "เย็นช้า"],
        "water_leak"=>["น้ำหยด", "น้ําหยด", "นำ้หยด", "น้ำรั่ว", "น้ํารั่ว", "นำ้รั่ว", "น้ำซึม", "น้ําซึม", "น้ำไหลซึม", "มีน้ำรั่ว", "มีน้ำหยด"],
        "not_start"=>["เปิดไม่ติด", "เปิดไม่ได้", "เปิดเครื่องไม่ติด", "กดเปิดไม่ติด", "กดไม่ติด", "สตาร์ทไม่ติด", "บูตไม่ติด", "บูทไม่ติด", "บู๊ตไม่ขึ้น", "เปิดเครื่องไม่ขึ้น", "เครื่องไม่ติด"],
        "no_power"=>["ไฟไม่เข้า", "ไม่มีไฟเข้า", "ไฟเข้าไม่ได้", "ไม่มีกระแสไฟเข้า"],
        "power_outage"=>["ไฟดับ", "ไฟฟ้าดับ", "ไฟตกดับ"],
        "lamp_off"=>["ไฟไม่ติด", "ไฟไม่สว่าง", "หลอดไม่ติด", "หลอดไฟไม่ติด", "ไฟไม่ขึ้น"],
        "flicker"=>["ไฟกระพริบ", "ไฟกะพริบ", "หลอดกระพริบ", "หลอดกะพริบ", "ไฟติดๆดับๆ", "ไฟติดดับ", "ไฟวูบวาบ"],
        "no_display"=>["จอไม่ขึ้น", "จอไม่ติด", "หน้าจอไม่ขึ้น", "ภาพไม่ขึ้น", "ไม่ขึ้นภาพ", "ภาพไม่แสดง", "ไม่มีภาพ", "จอดำ", "จอมืด", "หน้าจอดำ", "หน้าจอมืด"],
        "no_sound"=>["เสียงไม่ออก", "ไม่มีเสียง", "เสียงไม่ดัง", "เสียงไม่มา", "เสียงหาย", "ไม่ได้ยินเสียง", "ไมค์ไม่ออกเสียง", "ไมค์ไม่มีเสียง", "ไมค์ไม่ดัง", "พูดแล้วไม่มีเสียง"],
        "sound_distorted"=>["เสียงแตก", "เสียงซ่า", "เสียงขาดๆหายๆ", "เสียงสะดุด", "เสียงเพี้ยน", "เสียงขาดหาย"],
        "internet_down"=>["เน็ตไม่ติด", "เนตไม่ติด", "เน็ตใช้ไม่ได้", "เน็ตไม่มา", "เน็ตล่ม", "อินเทอร์เน็ตใช้ไม่ได้", "อินเตอร์เน็ตใช้ไม่ได้", "อินเทอร์เน็ตล่ม", "ไวไฟใช้ไม่ได้", "ไวไฟไม่ติด", "wifiใช้ไม่ได้", "wifiไม่ติด", "เชื่อมเน็ตไม่ได้", "เชื่อมต่อเน็ตไม่ได้", "เชื่อมต่ออินเทอร์เน็ตไม่ได้", "ไม่มีอินเทอร์เน็ต"],
        "internet_slow"=>["เน็ตช้า", "เนตช้า", "อินเทอร์เน็ตช้า", "อินเตอร์เน็ตช้า", "ไวไฟช้า", "wifiช้า", "เน็ตอืด"],
        "cannot_print"=>["พิมพ์ไม่ได้", "ปริ้นไม่ได้", "ปริ๊นไม่ได้", "ปรินไม่ได้", "พิมพ์ไม่ออก", "ปริ้นไม่ออก", "ปริ๊นไม่ออก", "เครื่องพิมพ์ไม่ทำงาน"],
        "paper_jam"=>["กระดาษติด", "กระดาษค้าง", "กระดาษติดเครื่อง"],
        "cracked"=>["แตก", "ร้าว", "แตกร้าว"],
        "snapped"=>["หัก", "ขาหัก"],
        "noise"=>["เสียงดังผิดปกติ", "มีเสียงดังผิดปกติ", "มีเสียงแปลก", "มีเสียงแปลกๆ", "เครื่องเสียงดังผิดปกติ"],
        "stuck"=>["ติดขัด", "เปิดไม่ออก", "เลื่อนติด", "ล็อกค้าง", "ล็อคค้าง"],
        "cannot_close"=>["ปิดไม่สนิท", "ปิดไม่ได้", "ปิดไม่ลง"],
        "cannot_connect"=>["เชื่อมต่อไม่ได้", "เชื่อมไม่ได้", "ต่อไม่ได้", "จับคู่ไม่ได้", "เชื่อมต่อไม่สำเร็จ"],
        "cannot_flush"=>["กดน้ำไม่ลง", "กดชักโครกไม่ลง", "ชักโครกกดไม่ลง", "น้ำไม่ลง"],
        "clogged"=>["อุดตัน", "ท่อตัน", "ส้วมตัน", "ชักโครกตัน", "ระบายน้ำไม่ลง"],
        "overheat"=>["ร้อนผิดปกติ", "เครื่องร้อนมาก", "เครื่องร้อนเกิน", "ร้อนเกินไป"],
    ];
    $found=[];foreach($patterns as $key=>$words){foreach($words as $word){if(mb_strpos($text,mbsDuplicateNormalize($word),0,'UTF-8')!==false){$found[]=$key;break;}}}
    // คำทั่วไปใช้เมื่อไม่มีอาการเฉพาะ และไม่จับคำว่า ไม่เสีย หรือ เสียบ
    if (!$found && preg_match('/(?<!ไม่)(?:เสีย(?!บ)|พัง|ชำรุด|ขัดข้อง|ใช้งานไม่ได้|ใช้การไม่ได้|ใช้ไม่ได้|ไม่ทำงาน)/u', $text)) $found[]='general_fault';
    sort($found);return $found;
}
function mbsDuplicateFind($db, $uid, $category, $location, $text) {
    if ($category === 'ไม่ระบุปัญหา' || $category === 'ขอความช่วยเหลือ/บริการ' || $location === 'ไม่ระบุสถานที่' || trim($location)==='') return null;
    $equipment=mbsDuplicateEquipmentKey($category);$symptoms=mbsDuplicateSymptomKeys($text);
    if ($equipment==='' || !$symptoms) return null;
    // created_at ของระบบนี้เก็บเวลาไทย UTC+7; รายการของผู้แจ้งเดิมตรวจได้ตลอดเวลาที่ยังเปิดอยู่
    $sql="SELECT r.id,r.ticket_no,r.equipment_type,r.location,r.problem_desc,r.status,r.technician_name,r.line_user_id,r.created_at FROM repairs r WHERE r.deleted_at IS NULL AND COALESCE(r.status,'') NOT IN ('ซ่อมเสร็จแล้ว','เสร็จสิ้น','ดำเนินการเสร็จแล้ว','ปิดงานแล้ว','ยกเลิก','ยกเลิกแล้ว') AND (BINARY r.location=BINARY ? OR r.location IS NULL OR r.location='' OR r.location='ไม่ระบุสถานที่') AND ((r.created_at >= DATE_SUB(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), INTERVAL 10 MINUTE) AND r.created_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR)) OR BINARY r.line_user_id=BINARY ? OR EXISTS (SELECT 1 FROM repair_followers f WHERE f.repair_id=r.id AND BINARY f.line_user_id=BINARY ?)) ORDER BY r.created_at ASC, r.id ASC";
    $q=$db->prepare($sql);if(!$q)throw new RuntimeException('duplicate query prepare');
    $q->bind_param('sss',$location,$uid,$uid);if(!$q->execute())throw new RuntimeException('duplicate query execute');
    $rows=$q->get_result();
    while($row=$rows->fetch_assoc()) {
        $rowLocation=(string)($row['location'] ?? '');
        if ($rowLocation==='' || $rowLocation==='ไม่ระบุสถานที่') {
            // งานเดิมที่เก็บสถานที่ไม่สำเร็จ: อ่านจากข้อความเดิมโดยไม่แก้ประวัติ
            if (!function_exists('extract_repair_info')) continue;
            $parsed=extract_repair_info((string)$row['problem_desc']);
            $rowLocation=(string)($parsed[1] ?? '');
        }
        if ($rowLocation !== $location) continue;
        if(mbsDuplicateEquipmentKey($row['equipment_type']) === $equipment && mbsDuplicateSymptomKeys($row['problem_desc']) === $symptoms) { $row['location']=$rowLocation; $q->close();return $row; }
    }
    $q->close();return null;
}
function mbsDuplicateJoin($db,$id,$uid,$description){
    $q=$db->prepare('INSERT INTO repair_followers (repair_id,line_user_id,problem_desc,reported_at) VALUES (?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE reported_at=UTC_TIMESTAMP()');
    if(!$q)throw new RuntimeException('follower prepare');$q->bind_param('iss',$id,$uid,$description);if(!$q->execute())throw new RuntimeException('follower insert');$q->close();
}
function mbsDuplicateWebNotice($db,$job,$oldStatus,$oldNote,$token) {
    $status=(string)$job['status'];$note=trim((string)($job['repair_note']??''));
    if ($status===$oldStatus && $note===$oldNote) return;
    $id=(int)$job['id'];
    if ($status==='ซ่อมเสร็จแล้ว' && $status!==$oldStatus) $kind='close';
    elseif ($status==='กำลังดำเนินการ' && $oldStatus==='รอรับเรื่อง') $kind='accept';
    else $kind='n'.substr(hash('sha256',$status."\n".$note),0,18);
    $text='อัปเดตรายการ '.$job['ticket_no']."\nสถานะ: ".$status;
    if ($note!=='') $text.="\nหมายเหตุ: ".$note;
    if (mb_strlen($text,'UTF-8')>4500) $text=mb_substr($text,0,4450,'UTF-8').'…';
    $select=$db->prepare('SELECT line_user_id FROM repair_followers WHERE repair_id=?');if(!$select)throw new RuntimeException('follower query');
    $select->bind_param('i',$id);$select->execute();$rows=$select->get_result();
    while($f=$rows->fetch_assoc()) {
        $recipient=(string)$f['line_user_id'];if($recipient===(string)$job['line_user_id'])continue;
        $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);$hex=bin2hex($bytes);$retry=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
        $payload=json_encode(['to'=>$recipient,'messages'=>[['type'=>'text','text'=>$text]]],JSON_UNESCAPED_UNICODE);
        $q=$db->prepare('INSERT INTO repair_line_outbox(repair_id,event_kind,target_id,payload,retry_key) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');if(!$q)throw new RuntimeException('queue prepare');
        $q->bind_param('issss',$id,$kind,$recipient,$payload,$retry);if(!$q->execute())throw new RuntimeException('queue save');$newId=(int)$q->insert_id;$q->close();
        // Existing worker retries unsent rows using the same retry key.
        if ($newId<=0) continue;
        $ch=curl_init('https://api.line.me/v2/bot/message/push');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token,'X-Line-Retry-Key: '.$retry]]);
        $response=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($http>=200 && $http<300) { $q=$db->prepare('UPDATE repair_line_outbox SET first_attempt=UTC_TIMESTAMP(),attempts=attempts+1,sent_at=NOW(),last_http=? WHERE id=?');$q->bind_param('ii',$http,$newId);$q->execute();$q->close(); }
        // On failure leave the row pending for the existing worker; its retry key prevents duplication.
    }
    $select->close();
}
