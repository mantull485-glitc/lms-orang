<?php
// Trigger Vercel Deploy 1
// ============================================================
// KONFIGURASI DATABASE GLOBAL — Supabase PostgreSQL
// ============================================================

// Kita PAKSA menggunakan Pooler Host yang benar, karena setting env var DB_HOST di Vercel 
// sepertinya menunjuk ke alamat db.xxx.supabase.co yang menyebabkan error IPv6.
$db_host = 'aws-1-ap-northeast-1.pooler.supabase.com';
$db_port = '5432';

define('SA_DB_HOST', $db_host);
define('SA_DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('SA_DB_USER', getenv('DB_USER') ?: 'postgres.tvpgimvjfydligohxwbg');
define('SA_DB_PASS', getenv('DB_PASS') ?: 'ARMAN.MANUSIA');
define('SA_DB_PORT', $db_port);


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
