<?php
// ============================================================
// KONFIGURASI DATABASE GLOBAL (Super Admin)
// Edit sesuai kredensial server Anda
// ============================================================

define('SA_DB_HOST', 'localhost');
define('SA_DB_NAME', 'platform_sales_db');
define('SA_DB_USER', 'root');
define('SA_DB_PASS', '');

try {
    $pdo_global = new PDO(
        'mysql:host=' . SA_DB_HOST . ';dbname=' . SA_DB_NAME . ';charset=utf8mb4',
        SA_DB_USER,
        SA_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
