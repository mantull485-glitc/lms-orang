<?php
// ============================================================
// TENANT DATABASE — Supabase Single-Database Mode
// Koneksi ke Supabase dan deteksi tenant_id dari URL
// ============================================================

if (!function_exists('findPlatformRoot')) {
    function findPlatformRoot(): string {
        return dirname(dirname(dirname(__DIR__)));
    }
}

$platform_root = findPlatformRoot();
require_once $platform_root . '/config/superadmin_db.php';

// Gunakan koneksi global (Supabase) sebagai $pdo untuk tenant
$pdo = $pdo_global;

// Detect subdomain dari URL path
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$subdomain = '';
if (preg_match('/\/tenants\/([a-zA-Z0-9_-]+)/', $request_uri, $matches)) {
    $subdomain = $matches[1];
}

// Fallback: coba baca dari status_check.php
if (empty($subdomain) || $subdomain === '_template') {
    $status_config = __DIR__ . '/status_check.php';
    if (file_exists($status_config)) {
        require_once $status_config;
        $subdomain = defined('TENANT_SUBDOMAIN') ? TENANT_SUBDOMAIN : '';
    }
}

// Ambil tenant_id dari database berdasarkan subdomain
$tenant_id = 0;
if (!empty($subdomain) && $subdomain !== '_template') {
    try {
        $stmt_tid = $pdo_global->prepare("SELECT id FROM tenants WHERE subdomain = ? AND status = 'aktif'");
        $stmt_tid->execute([$subdomain]);
        $row_tid = $stmt_tid->fetch();
        if ($row_tid) {
            $tenant_id = (int)$row_tid['id'];
        }
    } catch (Exception $e) {
        $tenant_id = 0;
    }
}

// Expose globally
$GLOBALS['tenant_id']  = $tenant_id;
$GLOBALS['subdomain']  = $subdomain;
