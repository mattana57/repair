<?php
// ใช้ภายในไฟล์ระบบเท่านั้น
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbsSyncConfig() {
    $channelAccessToken='';
    require __DIR__.'/env.php';
    return ['token'=>$channelAccessToken, 'group'=>'C5fc77a991cf95f5813124548b64a95b9', 'base'=>'https://autism-unfreeze-snooze.ngrok-free.dev/repair'];
}
function mbsReadableFlexNode($node, $inside_flex = false) {
    if (!is_array($node)) { return $node; }
    // แบบประเมินคงรูปแบบเดิม ไม่ผ่านการปรับขนาดหรือสีของใบงาน
    if (($node['type'] ?? '') === 'flex' && ($node['altText'] ?? '') === 'ประเมินผลการซ่อม') {
        return $node;
    }
    $type = $node['type'] ?? null;
    $inside_flex = $inside_flex || $type === 'flex' || $type === 'bubble';
    if ($inside_flex && $type === 'bubble') {
        $node['size'] = $node['size'] ?? 'mega';
    }
    if ($inside_flex && $type === 'text') {
        if (!isset($node['size']) || in_array($node['size'], ['xxs', 'xs'], true)) {
            $node['size'] = 'sm';
        }
        $node['wrap'] = true;
        $node['color'] = ($node['weight'] ?? '') === 'bold' ? '#1E3A5F' : '#222222';
    }
    if ($inside_flex && $type === 'button') {
        $node['height'] = 'sm';
        if (($node['style'] ?? '') === 'primary') {
            $node['color'] = in_array($node['action']['label'] ?? '', ['แจ้งปิดงาน','ปิดงาน'], true) ? '#A8432D' : '#1E3A5F';
        }
    }
    foreach ($node as $key => $value) {
        if (is_array($value)) {
            $node[$key] = mbsReadableFlexNode($value, $inside_flex);
        }
    }
    return $node;
}


// การ์ดข้อมูลผู้แจ้ง: แยกหัวเรื่องและข้อมูลเพื่อให้อ่านง่าย
function mbsRequesterCard($title, $label, $lines, $footer) {
    $body = [
        ['type'=>'box','layout'=>'vertical','backgroundColor'=>'#EEF4FA','paddingAll'=>'12px','cornerRadius'=>'8px','contents'=>[
            ['type'=>'text','text'=>$title,'weight'=>'bold','size'=>'md','wrap'=>true],
            ['type'=>'text','text'=>$label,'weight'=>'bold','size'=>'sm','margin'=>'sm','wrap'=>true]
        ]],
        ['type'=>'separator','margin'=>'md']
    ];
    foreach ($lines as $line) {
        $body[] = ['type'=>'text','text'=>$line,'size'=>'md','margin'=>'sm','wrap'=>true];
    }
    if ($footer !== '') {
        $body[] = ['type'=>'separator','margin'=>'md'];
        $body[] = ['type'=>'text','text'=>$footer,'size'=>'sm','margin'=>'md','wrap'=>true];
    }
    return ['type'=>'flex','altText'=>$title,'contents'=>[
        'type'=>'bubble','size'=>'mega',
        'body'=>['type'=>'box','layout'=>'vertical','paddingAll'=>'16px','contents'=>$body]
    ]];
}

// ก่อนรับงานแสดงคำขอเต็ม หลังรับงานแสดงสรุปเพื่อลดพื้นที่ในกลุ่ม
function mbsServiceJobCard($ticket, $location, $category, $detail, $reporter, $phone,
    $stage, $actions, $staff = '', $department = '', $has_image = false, $compact = false, $daily_label = '') {
    if ($compact) {
        $items = [
            ['type' => 'text', 'text' => '✅ รับงานเรียบร้อย', 'weight' => 'bold', 'size' => 'sm', 'wrap' => true],
            ['type' => 'text', 'text' => 'จนท. ' . $staff, 'weight' => 'bold', 'size' => 'md', 'wrap' => true],
            ['type' => 'separator', 'margin' => 'sm'],
            ['type' => 'text', 'text' => 'ใบงาน: ' . $ticket, 'weight' => 'bold', 'size' => 'sm', 'wrap' => true],
            ['type' => 'text', 'text' => 'สถานที่: ' . $location . ' · เรื่อง: ' . $category, 'size' => 'md', 'wrap' => true]
        ];
    } else {
        $items = [
            ['type' => 'text', 'text' => '🔔 คำขอรับบริการใหม่', 'weight' => 'bold', 'size' => 'sm', 'wrap' => true],
            ['type' => 'text', 'text' => $ticket, 'weight' => 'bold', 'size' => 'sm', 'wrap' => true],
            ['type' => 'separator', 'margin' => 'sm'],
            ['type' => 'text', 'text' => 'เรื่อง: ' . $category, 'size' => 'sm', 'wrap' => true],
            ['type' => 'text', 'text' => 'สถานที่: ' . $location, 'size' => 'md', 'wrap' => true],
            ['type' => 'text', 'text' => 'ผู้แจ้ง: ' . $reporter . ' · โทร ' . $phone, 'size' => 'sm', 'wrap' => true],
            ['type' => 'text', 'text' => 'รายละเอียด: ' . (trim((string) $detail) !== '' ? (string) $detail : 'ไม่ระบุรายละเอียดเพิ่มเติม'), 'size' => 'md', 'margin' => 'sm', 'wrap' => true]
        ];
        if ($has_image) {
            $items[] = ['type' => 'text', 'text' => 'มีไฟล์แนบในใบงาน', 'size' => 'sm', 'wrap' => true];
        }
    }
    if ($daily_label !== '') { array_unshift($items, ['type'=>'text','text'=>$daily_label,'weight'=>'bold','size'=>'lg','wrap'=>true]); }
    return mbsReadableFlexNode([
        'type' => 'flex', 'altText' => $stage . ' ' . $ticket,
        'contents' => [
            'type' => 'bubble', 'size' => 'kilo',
            'body' => ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '14px', 'backgroundColor' => '#F4F8FC', 'contents' => $items],
            'footer' => ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '12px', 'paddingTop' => '8px', 'contents' => $actions]
        ]
    ]);
}



function mbsSyncReview($job) {
    $repair_id=(int)$job['id'];
    $short_reporter_name_close=trim((string)$job['reporter_name']);
    $clicker_name=(string)$job['technician_name'];
$review_msg = [
                                        'type' => 'flex',
                                        'altText' => 'ประเมินผลการซ่อม',
                                        'contents' => [
                                            'type' => 'bubble',
                                            'body' => [
                                                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm',
                                                'contents' => [
                                                    ['type' => 'text', 'text' => '⭐ ประเมินผลการซ่อม', 'weight' => 'bold', 'color' => '#ffb700', 'size' => 'md'],
                                                    ['type' => 'text', 'text' => "ถึงคุณ ".$short_reporter_name_close, 'weight' => 'bold', 'color' => '#3b82f6', 'size' => 'xs'],
                                                    ['type' => 'text', 'text' => 'เจ้าหน้าที่ '.$clicker_name.' ดำเนินการเรียบร้อยแล้วค่ะ', 'weight' => 'bold', 'size' => 'xs', 'wrap' => true],
                                                    ['type' => 'separator', 'margin' => 'sm'],
                                                    ['type' => 'text', 'text' => '1️⃣ ให้คะแนนดาว (กดเปลี่ยนได้)', 'size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'sm'],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'primary', 'color' => '#fbbf24', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '5 ดาว', 'data' => "action=rate&score=5&id=$repair_id"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '4 ดาว', 'data' => "action=rate&score=4&id=$repair_id"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '3 ดาว', 'data' => "action=rate&score=3&id=$repair_id"]]
                                                        ]
                                                    ],
                                                    [
                                                        'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm', 'margin' => 'sm',
                                                        'contents' => [
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '2 ดาว', 'data' => "action=rate&score=2&id=$repair_id"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '1 ดาว', 'data' => "action=rate&score=1&id=$repair_id"]],
                                                            ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'action' => ['type' => 'postback', 'label' => '0 ดาว', 'data' => "action=rate&score=0&id=$repair_id"]]
                                                        ]
                                                    ],
                                                    ['type' => 'separator', 'margin' => 'sm'],
                                                    ['type' => 'text', 'text' => '2️⃣ เลือกรีวิว (กดได้หลายข้อ)', 'size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'sm'],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '⏱️ ดำเนินการเร็ว', 'data' => "action=add_tag&tag=ดำเนินการรวดเร็วทันใจ&id=$repair_id"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🎯 แก้ปัญหาตรงจุด', 'data' => "action=add_tag&tag=แก้ไขปัญหาได้อย่างตรงจุด&id=$repair_id"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '💡 ให้คำแนะนำดี', 'data' => "action=add_tag&tag=อธิบายและให้คำแนะนำชัดเจน&id=$repair_id"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🗣️ สุภาพเรียบร้อย', 'data' => "action=add_tag&tag=สุภาพเรียบร้อย บริการเต็มใจ&id=$repair_id"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '✨ เก็บงานเรียบร้อย', 'data' => "action=add_tag&tag=ซ่อมแซมและเก็บงานเรียบร้อย&id=$repair_id"]],
                                                    ['type' => 'button', 'style' => 'secondary', 'height' => 'sm', 'margin' => 'sm', 'action' => ['type' => 'postback', 'label' => '🙏 ช่วยเหลือดีเยี่ยม', 'data' => "action=add_tag&tag=ช่วยอำนวยความสะดวกได้ดีเยี่ยม&id=$repair_id"]],
                                                    ['type' => 'text', 'text' => '*หรือพิมพ์ข้อความรีวิวเพิ่มเติมส่งมาในแชทได้เลยค่ะ', 'size' => 'xxs', 'color' => '#bbbbbb', 'margin' => 'sm', 'wrap' => true]
                                                ]
                                            ]
                                        ]
                                    ];
    return $review_msg;
}
function mbsSyncStmt($db, $sql, $types = '', ...$params) {
    $stmt=$db->prepare($sql);
    if (!$stmt || ($types !== '' && !$stmt->bind_param($types,...$params)) || !$stmt->execute()) {
        throw new RuntimeException('Cannot process job sync query');
    }
    return $stmt;
}
function mbsSyncRow($db, $sql, $types = '', ...$params) {
    $stmt=mbsSyncStmt($db,$sql,$types,...$params);
    $row=$stmt->get_result()->fetch_assoc(); $stmt->close(); return $row;
}
// Caller owns the transaction. Tables are installed separately; no runtime DDL.
function mbsSyncLabel($db, $job) {
    $id=(int)$job['id'];
    $label=mbsSyncRow($db,'SELECT request_date,daily_no FROM repair_daily_labels WHERE repair_id=?','i',$id);
    if (!$label) {
        $date=substr((string)$job['created_at'],0,10);
        if (!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/',$date)) {
            throw new RuntimeException('Missing repair creation date');
        }
        mbsSyncStmt($db,'INSERT INTO repair_daily_counters(request_date,last_no) VALUES(?,0) ON DUPLICATE KEY UPDATE last_no=last_no','s',$date)->close();
        $counter=mbsSyncRow($db,'SELECT last_no FROM repair_daily_counters WHERE request_date=? FOR UPDATE','s',$date);
        $label=mbsSyncRow($db,'SELECT request_date,daily_no FROM repair_daily_labels WHERE repair_id=?','i',$id);
        if (!$label) {
            $number=(int)$counter['last_no']+1;
            mbsSyncStmt($db,'UPDATE repair_daily_counters SET last_no=? WHERE request_date=?','is',$number,$date)->close();
            mbsSyncStmt($db,'INSERT INTO repair_daily_labels(repair_id,request_date,daily_no) VALUES(?,?,?)','isi',$id,$date,$number)->close();
            $label=['request_date'=>$date,'daily_no'=>$number];
        }
    }
    return mbsSyncLabelText($label);
}
function mbsSyncLabelText($label) {
    $months=['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$label['request_date'],new DateTimeZone('Asia/Bangkok'));
    if (!$date) { throw new RuntimeException('Invalid daily date'); }
    return 'รายการแจ้ง #'.str_pad((string)$label['daily_no'],3,'0',STR_PAD_LEFT).' · '.(int)$date->format('j').' '.$months[(int)$date->format('n')-1].' '.str_pad((string)(((int)$date->format('Y')+543)%100),2,'0',STR_PAD_LEFT);
}
function mbsSyncAcceptedCard($job,$staff,$label) {
    $id=(int)$job['id'];$config=mbsSyncConfig();
    return mbsServiceJobCard($job['ticket_no'],$job['location'],$job['equipment_type'],$job['problem_desc'],
        $job['reporter_name'],$job['phone_number'],'รับงานแล้ว',[
            ['type'=>'button','style'=>'primary','height'=>'sm','action'=>['type'=>'postback','label'=>'แจ้งปิดงาน','data'=>'action=close&id='.$id]],
            ['type'=>'button','style'=>'secondary','height'=>'sm','action'=>['type'=>'uri','label'=>'จัดการ / เพิ่มหมายเหตุ','uri'=>$config['base'].'/update_repair.php?id='.$id]]
        ],$staff['full_name'],$staff['department'] ?? '',!empty($job['image_path']),true,$label);
}
function mbsSyncUuid() {
    $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);
    $h=bin2hex($bytes);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
}
// จัดข้อความทั่วไปเป็น Flex โดยคงข้อความและปุ่ม quick reply เดิม
function mbsCompactNoticeFlex($message) {
    if (!is_array($message) || ($message['type'] ?? '') !== 'text' || !isset($message['text']) || !is_string($message['text'])) return $message;
    $text = $message['text'];
    // ถ้าข้อความยาวมาก ให้ส่งรูปแบบเดิม เพื่อไม่ตัดข้อมูลหรือทำให้ Flex เกินขนาด
    if (trim($text) === '' || mb_strlen($text, 'UTF-8') > 1800 || strlen($text) > 9000) return $message;
    $parts = preg_split('/\R/u', $text, 2);
    $alt = trim($parts[0] ?? '');
    if ($alt === '') $alt = 'ข้อความจากระบบแจ้งบริการ';
    $alt = mb_substr($alt, 0, 150, 'UTF-8');
    $flex = [
        'type' => 'flex', 'altText' => $alt,
        'contents' => ['type' => 'bubble', 'size' => 'mega',
            'body' => ['type' => 'box', 'layout' => 'vertical', 'paddingAll' => '16px',
                'contents' => [['type' => 'text', 'text' => $text, 'size' => 'md', 'color' => '#263B50', 'wrap' => true]]]]
    ];
    if (isset($message['quickReply'])) $flex['quickReply'] = $message['quickReply'];
    return $flex;
}

function mbsSyncEnqueue($db,$jobId,$kind,$target,$messages) {
    if (!is_string($target) || $target==='') return;
    $messages = array_map('mbsCompactNoticeFlex', $messages);
    $body=json_encode(['to'=>$target,'messages'=>array_map('mbsReadableFlexNode',$messages)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    // Same event/recipient cannot be queued twice by competing LINE/web requests.
    mbsSyncStmt($db,'INSERT INTO repair_line_outbox(repair_id,event_kind,target_id,payload,retry_key) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
        'issss',$jobId,$kind,$target,$body,mbsSyncUuid())->close();
}
function mbsSyncNotifyTransition($db,$job,$oldStatus) {
    if ($job['status']===$oldStatus) return;
    $config=mbsSyncConfig();$id=(int)$job['id'];$label=mbsSyncLabel($db,$job);
    $staff=mbsSyncRow($db,'SELECT id,full_name,phone,department FROM technicians WHERE id=?','i',(int)$job['technician_id']);
    if (!$staff) throw new RuntimeException('Missing assigned staff');
    if ($job['status']==='กำลังดำเนินการ' && $oldStatus==='รอรับเรื่อง') {
        mbsSyncEnqueue($db,$id,'accept',$config['group'],[mbsSyncAcceptedCard($job,$staff,$label)]);
        $acceptNotice = mbsRequesterCard('✅ มีเจ้าหน้าที่รับงานแล้ว', $label, [
            'ผู้รับผิดชอบ: '.$staff['full_name'],
            'เบอร์ติดต่อ: '.($staff['phone'] ?: '-')
        ], 'เจ้าหน้าที่จะตรวจสอบและประสานงานค่ะ');
        mbsSyncEnqueue($db,$id,'accept',(string)$job['line_user_id'],[$acceptNotice]);
        $followers=mbsSyncStmt($db,'SELECT line_user_id FROM repair_followers WHERE repair_id=?','i',$id);$fresult=$followers->get_result();
        while($f=$fresult->fetch_assoc()){if($f['line_user_id']!==$job['line_user_id'])mbsSyncEnqueue($db,$id,'accept',$f['line_user_id'],[$acceptNotice]);}$followers->close();
    } elseif ($job['status']==='ซ่อมเสร็จแล้ว' && $oldStatus!=='ซ่อมเสร็จแล้ว') {
        $job['technician_name']=$staff['full_name'];
        $note=trim((string)($job['repair_note'] ?? ''));
        $text='🎉 '.$label."\nดำเนินการเรียบร้อยแล้วค่ะ".($note!==''?"\n📝 หมายเหตุ: ".$note:'');
        // Keep full notes in the job detail; LINE text payload is bounded.
        if (mb_strlen($text,'UTF-8')>4500) $text=mb_substr($text,0,4450,'UTF-8')."…\nดูหมายเหตุเต็มในรายการแจ้งของฉัน";
        mbsSyncEnqueue($db,$id,'close',(string)$job['line_user_id'],[['type'=>'text','text'=>$text],mbsSyncReview($job)]);
        $followers=mbsSyncStmt($db,'SELECT line_user_id FROM repair_followers WHERE repair_id=?','i',$id);$fresult=$followers->get_result();
        while($f=$fresult->fetch_assoc()){if($f['line_user_id']!==$job['line_user_id'])mbsSyncEnqueue($db,$id,'close',$f['line_user_id'],[['type'=>'text','text'=>$text]]);}$followers->close();
        $closedGroupNotice = mbsRequesterCard('✅ ปิดงานเรียบร้อยแล้ว', $label, [
            'เจ้าหน้าที่: '.$staff['full_name'],
            'สถานที่: '.$job['location'].' · เรื่อง: '.$job['equipment_type']
        ], '');
        mbsSyncEnqueue($db,$id,'close',$config['group'],[$closedGroupNotice]);
    }
}
function mbsSyncStaff($db,$techId,$lineId=null) {
    $sql="SELECT t.* FROM technicians t JOIN users u ON u.technician_id=t.id WHERE t.id=? AND t.deleted_at IS NULL AND t.approval_status='อนุมัติแล้ว' AND LOWER(u.role)='technician' AND u.is_active=1 AND u.deleted_at IS NULL";
    $types='i';$params=[$techId];
    if ($lineId!==null) {$sql.=' AND BINARY t.line_user_id=BINARY ?';$types.='s';$params[]=$lineId;}
    return mbsSyncRow($db,$sql,$types,...$params);
}
function mbsSyncTransition($db,$id,$techId,$action,$lineId=null) {
    if (!$db->begin_transaction()) { throw new RuntimeException('Cannot start job transaction'); }
    try {
        $job=mbsSyncRow($db,'SELECT * FROM repairs WHERE id=? AND deleted_at IS NULL FOR UPDATE','i',$id);
        $staff=mbsSyncStaff($db,$techId,$lineId);
        if (!$job || !$staff) throw new RuntimeException('ไม่พบรายการ หรือบัญชีเจ้าหน้าที่ไม่พร้อมใช้งานค่ะ');
        $old=$job['status'];
        if ($action==='accept') {
            if ($old!=='รอรับเรื่อง' || (int)$job['technician_id']>0) throw new RuntimeException('รายการนี้มีผู้รับผิดชอบแล้ว กรุณาเปิดรายการล่าสุดค่ะ');
            mbsSyncStmt($db,"UPDATE repairs SET status='กำลังดำเนินการ',technician_id=?,technician_name=?,received_at=COALESCE(received_at,CURRENT_TIMESTAMP) WHERE id=? AND deleted_at IS NULL AND status='รอรับเรื่อง' AND (technician_id IS NULL OR technician_id=0)",'isi',$techId,$staff['full_name'],$id)->close();
        } elseif ($action==='close') {
            if ((int)$job['technician_id']!==$techId) throw new RuntimeException('เฉพาะเจ้าหน้าที่ผู้รับงานเท่านั้นที่ปิดงานได้ค่ะ');
            if (!in_array($old,['กำลังดำเนินการ','ช่างรับเรื่องแจ้งซ่อมแล้ว'],true)) throw new RuntimeException('รายการนี้ปิดงานแล้ว หรือสถานะเปลี่ยนไปค่ะ');
            mbsSyncStmt($db,"UPDATE repairs SET status='ซ่อมเสร็จแล้ว',completed_at=CURRENT_TIMESTAMP WHERE id=? AND technician_id=? AND deleted_at IS NULL",'ii',$id,$techId)->close();
        } else {throw new RuntimeException('คำสั่งไม่ถูกต้อง');}
        $job=mbsSyncRow($db,'SELECT * FROM repairs WHERE id=?','i',$id);
        mbsSyncNotifyTransition($db,$job,$old);
        $label=mbsSyncLabel($db,$job);
        if (!$db->commit()) throw new RuntimeException('Cannot commit job');
        return ['job'=>$job,'label'=>$label];
    } catch(Throwable $e) {$db->rollback();throw $e;}
}
// Push retries reuse the same key. Do not blindly retry past LINE's 24h key retention.
function mbsSyncDispatch($db,$jobId=0,$limit=4) {
    $config=mbsSyncConfig();$limit=max(1,min(20,(int)$limit));
    $sql="SELECT * FROM repair_line_outbox WHERE sent_at IS NULL AND blocked=0 AND next_attempt<=NOW()";
    if ($jobId>0) $sql.=' AND repair_id='.(int)$jobId;
    $stmt=mbsSyncStmt($db,$sql.' ORDER BY id LIMIT '.$limit);$result=$stmt->get_result();
    while($row=$result->fetch_assoc()) {
        $lock='repair-line-send-'.$row['id'];
        $got=mbsSyncRow($db,'SELECT GET_LOCK(?,0) AS got','s',$lock);
        if ((int)($got['got']??0)!==1) continue;
        try {
            $fresh=mbsSyncRow($db,'SELECT *, TIMESTAMPDIFF(SECOND,first_attempt,UTC_TIMESTAMP()) AS age FROM repair_line_outbox WHERE id=?','i',(int)$row['id']);
            if ($fresh['sent_at']!==null || (int)$fresh['blocked']===1) continue;
            if ($fresh['first_attempt']!==null && (int)$fresh['age']>=82800) {
                mbsSyncStmt($db,"UPDATE repair_line_outbox SET blocked=1,last_http=0 WHERE id=?",'i',(int)$row['id'])->close();continue;
            }
            mbsSyncStmt($db,'UPDATE repair_line_outbox SET first_attempt=COALESCE(first_attempt,UTC_TIMESTAMP()),attempts=attempts+1,next_attempt=DATE_ADD(NOW(),INTERVAL 1 MINUTE) WHERE id=?','i',(int)$row['id'])->close();
            $accepted=false;
            $ch=curl_init('https://api.line.me/v2/bot/message/push');
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_POSTFIELDS=>$row['payload'],
                CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$config['token'],'X-Line-Retry-Key: '.$row['retry_key']],
                CURLOPT_HEADERFUNCTION=>static function($ch,$header)use(&$accepted){if(stripos($header,'x-line-accepted-request-id:')===0)$accepted=true;return strlen($header);}]);
            curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
            if (($http>=200 && $http<300) || ($http===409 && $accepted)) {
                mbsSyncStmt($db,'UPDATE repair_line_outbox SET sent_at=NOW(),last_http=? WHERE id=?','ii',$http,(int)$row['id'])->close();
            } else {
                $blocked=($http>=400 && $http<500 && !in_array($http,[408,429],true))?1:0;
                mbsSyncStmt($db,'UPDATE repair_line_outbox SET last_http=?,blocked=? WHERE id=?','iii',$http,$blocked,(int)$row['id'])->close();
                error_log('LINE job notification pending; outbox='.(int)$row['id'].' HTTP='.$http);
            }
        } finally {mbsSyncRow($db,'SELECT RELEASE_LOCK(?) AS released','s',$lock);}
    }
    $stmt->close();
}
function mbsSyncDispatchSafe($db,$jobId=0) {
    try {mbsSyncDispatch($db,$jobId);} catch(Throwable $e) {error_log('LINE job dispatcher needs attention: '.get_class($e));}
}
