<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

// Generate redirect URL yang benar (custom domain atau path default)
$redirect_url = function_exists('tenantUrl') ? tenantUrl() : '../index.php';

session_unset();
session_destroy();

session_start();
$_SESSION['flash_message'] = "Anda telah berhasil logout.";
header("Location: " . $redirect_url);
exit;
?>
