<?php
// ============================================================
// KONFIGURASI DATABASE GLOBAL — Supabase PostgreSQL
// ============================================================

define('SA_DB_HOST', getenv('DB_HOST') ?: 'db.tvpgimvjfydligohxwbg.supabase.co');
define('SA_DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('SA_DB_USER', getenv('DB_USER') ?: 'postgres');
define('SA_DB_PASS', getenv('DB_PASS') ?: 'ARMAN.MANUSIA');
define('SA_DB_PORT', getenv('DB_PORT') ?: '5432');

try {
    $pdo_global = new PDO(
        'pgsql:host=' . SA_DB_HOST . ';port=' . SA_DB_PORT . ';dbname=' . SA_DB_NAME . ';sslmode=require',
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
