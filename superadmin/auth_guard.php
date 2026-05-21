<?php
// Include di awal setiap halaman superadmin
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: ../auth/superadmin_login.php'); exit;
}
require_once __DIR__ . '/../config/superadmin_db.php';
