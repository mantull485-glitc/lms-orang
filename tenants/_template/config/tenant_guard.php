<?php
// ============================================================
// TENANT STATUS GUARD — v2
// Otomatis deteksi root platform dari berbagai kedalaman folder
// ============================================================

function findPlatformRoot(): string {
    // __DIR__ = /path/platform/tenants/subdomain/config
    return dirname(dirname(dirname(__DIR__))); // naik 3x = root platform
}

$guard_base = findPlatformRoot();
$sa_config  = $guard_base . '/config/superadmin_db.php';

// Jika config tidak ditemukan, skip guard (standalone/dev mode)
if (!file_exists($sa_config)) return;

require_once $sa_config;

// Baca subdomain dari status_check.php
$status_config = __DIR__ . '/status_check.php';
if (file_exists($status_config)) {
    require_once $status_config;
    $subdomain = defined('TENANT_SUBDOMAIN') ? TENANT_SUBDOMAIN : '';
} else {
    $subdomain = basename(dirname(__DIR__)); // fallback: nama folder tenant
}

if (empty($subdomain)) return;

try {
    $stmt_guard = $pdo_global->prepare(
        "SELECT status, nama_lembaga, alasan_nonaktif FROM tenants WHERE subdomain = ?"
    );
    $stmt_guard->execute([$subdomain]);
    $tenant_info = $stmt_guard->fetch();
} catch (Exception $e) {
    return; // DB error: lewati guard
}

if (!$tenant_info || $tenant_info['status'] !== 'aktif') {
    $alasan = $tenant_info['alasan_nonaktif'] ?? '';
    $nama   = $tenant_info['nama_lembaga'] ?? 'Platform Ini';
    $status = $tenant_info['status'] ?? 'nonaktif';

    $nonaktif_page = $guard_base . '/templates/platform_nonaktif.php';
    if (file_exists($nonaktif_page)) {
        include $nonaktif_page;
    } else {
        http_response_code(503);
        echo "<!DOCTYPE html><html><body style='background:#0F172A;color:#94A3B8;font-family:sans-serif;text-align:center;padding:4rem'>";
        echo "<h2 style='color:#EF4444'>Platform Tidak Aktif</h2>";
        echo "<p>" . htmlspecialchars($nama) . " sedang tidak dapat diakses.</p>";
        echo "</body></html>";
    }
    exit;
}
