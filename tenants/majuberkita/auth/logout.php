<?php
session_start();
session_unset();
session_destroy();

session_start();
$_SESSION['flash_message'] = "Anda telah berhasil logout.";
header("Location: ../index.php");
exit;
?>
