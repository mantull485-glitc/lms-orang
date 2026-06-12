<?php
// ============================================================
// KONFIGURASI DATABASE GLOBAL — Supabase PostgreSQL
// ============================================================

// Ambil host dari env var
$db_host = getenv('DB_HOST') ?: 'aws-1-ap-northeast-1.pooler.supabase.com';

// Jika menggunakan direct host Supabase (db.xxx.supabase.co), Vercel akan gagal karena masalah IPv6.
// Kita paksa ubah ke connection pooler Supabase (port 6543)
if (strpos($db_host, 'db.') === 0 && strpos($db_host, '.supabase.co') !== false) {
    $db_host = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Pooler default region Singapore
    $db_port = '6543'; // Port khusus pooler
} else {
    $db_port = getenv('DB_PORT') ?: '5432';
}

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
