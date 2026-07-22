<?php
session_start();
session_unset(); 
session_destroy(); 

// เด้งไปที่หน้า login.php โดยตรง
header("Location: login.php");
exit();
?>