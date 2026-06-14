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

// Baca subdomain secara dinamis dari server var, URL path, atau custom domain
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$subdomain = '';
if (isset($_SERVER['TENANT_SUBDOMAIN']) && !empty($_SERVER['TENANT_SUBDOMAIN'])) {
    $subdomain = $_SERVER['TENANT_SUBDOMAIN'];
} elseif (preg_match('/\/tenants\/([a-zA-Z0-9_-]+)/', $request_uri, $matches)) {
    $subdomain = $matches[1];
}

// Cek jika diakses lewat custom domain
if (empty($subdomain) || $subdomain === '_template') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host) && !in_array($host, ['localhost', '127.0.0.1']) && !str_ends_with($host, '.vercel.app') && !str_contains($host, '192.168.')) {
        try {
            $stmt_host = $pdo_global->prepare("SELECT subdomain FROM tenants WHERE custom_domain = ? AND status = 'aktif' LIMIT 1");
            $stmt_host->execute([$host]);
            $res_host = $stmt_host->fetch();
            if ($res_host) {
                $subdomain = $res_host['subdomain'];
            }
        } catch (Exception $e) {
            // Abaikan jika database bermasalah
        }
    }
}

// Jika tidak ketemu di URL, fallback ke file status_check atau folder
if ($subdomain === '_template' || empty($subdomain)) {
    $status_config = __DIR__ . '/status_check.php';
    if (file_exists($status_config)) {
        require_once $status_config;
        $subdomain = defined('TENANT_SUBDOMAIN') ? TENANT_SUBDOMAIN : '';
    } else {
        $subdomain = basename(dirname(__DIR__)); // fallback: nama folder tenant
    }
}

if (empty($subdomain) || $subdomain === '_template') return;

try {
    $stmt_guard = $pdo_global->prepare(
        "SELECT status, nama_lembaga, alasan_nonaktif, custom_domain FROM tenants WHERE subdomain = ?"
    );
    $stmt_guard->execute([$subdomain]);
    $tenant_info = $stmt_guard->fetch();
} catch (Exception $e) {
    return; // DB error: lewati guard
}

// FORCE REDIRECT: Jika punya custom domain tapi diakses via platform/vercel
if ($tenant_info && $tenant_info['status'] === 'aktif' && !empty($tenant_info['custom_domain'])) {
    $current_host = $_SERVER['HTTP_HOST'] ?? '';
    // Jika host saat ini BUKAN custom domain (berarti masih pakai URL vercel/localhost)
    if (strcasecmp($current_host, $tenant_info['custom_domain']) !== 0) {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        
        // Bersihkan prefix path /tenants/subdomain dari URL
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $prefix = "/tenants/$subdomain";
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
        if (empty($path)) $path = '/';

        $redirect_url = $scheme . '://' . $tenant_info['custom_domain'] . $path;
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $redirect_url);
        exit;
    }
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
