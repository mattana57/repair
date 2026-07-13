<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reporter_name = $_POST['reporter_name'];
    $equipment = ($_POST['equipment_type'] == 'other') ? $_POST['other_equip'] : $_POST['equipment_type'];
    $location = $_POST['building'] . " ห้อง " . $_POST['room_no'];
    $phone_number = $_POST['phone_number'];
    $problem_desc = $_POST['problem_desc'];
    $ticket_no = "MR-" . date("Ymd-His");

    $target_dir = "uploads/";
    $image_name = null;
    if (!empty($_FILES["image_before"]["name"])) {
        $image_name = $ticket_no . "_" . basename($_FILES["image_before"]["name"]);
        move_uploaded_file($_FILES["image_before"]["tmp_name"], $target_dir . $image_name);
    }

    $sql = "INSERT INTO repairs (ticket_no, reporter_name, equipment_type, location, phone_number, problem_desc, image_before, status, building, room_no) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'รอรับเรื่อง', ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $ticket_no, $reporter_name, $equipment, $location, $phone_number, $problem_desc, $image_name, $_POST['building'], $_POST['room_no']);

    if ($stmt->execute()) {
        header("Location: index.php?status=success&ticket=" . urlencode($ticket_no));
    } else {
        header("Location: index.php?status=error&msg=" . urlencode($stmt->error));
    }
    $stmt->close();
    exit();
}
?>