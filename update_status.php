<?php
require_once 'db_connect.php';

if (isset($_GET['ticket_no']) && isset($_GET['status'])) {
    $ticket_no = $_GET['ticket_no'];
    $status = $_GET['status'];

    $stmt = $conn->prepare("UPDATE repairs SET status = ? WHERE ticket_no = ?");
    $stmt->bind_param("ss", $status, $ticket_no);
    $stmt->execute();

    // ส่วนส่ง LINE (ใช้ Broadcast ตามที่คุณน้ำฝนแจ้งว่าเวิร์ค)
    $accessToken = 'GszSbZaQoKn+FUVG1Co2O12utBahenfC3DZ3Qx4Pr2xAWxaALZKUJOUcUaczHm+enwF80HCuvLzUssUDjqCVOT++/gl8NlhzncqdORF/2dOyXyt2GtMBdSeAYR9bevwB/3Y4txPDWrQM++i1TockxQdB04t89/1O/w1cDnyilFU=';
    $message = "📢 อัปเดตสถานะงานแจ้งซ่อม\nเลขที่: $ticket_no\nเปลี่ยนสถานะเป็น: $status";

    $ch = curl_init("https://api.line.me/v2/bot/message/broadcast");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['messages' => [['type' => 'text', 'text' => $message]]]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    echo "<script>alert('อัปเดตสถานะเรียบร้อย!'); window.location='admin_dashboard.php';</script>";
}
?>