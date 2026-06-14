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

// Detect subdomain dari server var, URL path, atau custom domain
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$subdomain = '';

if (isset($_SERVER['TENANT_SUBDOMAIN']) && !empty($_SERVER['TENANT_SUBDOMAIN'])) {
    $subdomain = $_SERVER['TENANT_SUBDOMAIN'];
} elseif (preg_match('/\/tenants\/([a-zA-Z0-9_-]+)/', $request_uri, $matches)) {
    $subdomain = $matches[1];
}

// Cek jika diakses lewat custom domain (jika belum terdeteksi dari path/server var)
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

// Fallback: coba baca dari status_check.php
if (empty($subdomain) || $subdomain === '_template') {
    $status_config = __DIR__ . '/status_check.php';
    if (file_exists($status_config)) {
        require_once $status_config;
        $subdomain = defined('TENANT_SUBDOMAIN') ? TENANT_SUBDOMAIN : '';
    }
}

// Ambil tenant_id dan info domain dari database berdasarkan subdomain
$tenant_id     = 0;
$custom_domain = '';
$tenant_info   = null;
if (!empty($subdomain) && $subdomain !== '_template') {
    try {
        $stmt_tid = $pdo_global->prepare("SELECT id, custom_domain FROM tenants WHERE subdomain = ? AND status = 'aktif'");
        $stmt_tid->execute([$subdomain]);
        $tenant_info = $stmt_tid->fetch();
        if ($tenant_info) {
            $tenant_id     = (int)$tenant_info['id'];
            $custom_domain = $tenant_info['custom_domain'] ?? '';
        }
    } catch (Exception $e) {
        $tenant_id = 0;
    }
}

// Helper: generate URL publik tenant yang benar
// Jika punya custom domain, gunakan itu. Jika tidak, gunakan path subdomain.
if (!function_exists('tenantUrl')) {
    function tenantUrl(string $path = ''): string {
        $cd = $GLOBALS['custom_domain'] ?? '';
        $sub = $GLOBALS['subdomain'] ?? '';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $path = ltrim($path, '/');
        if (!empty($cd)) {
            return $scheme . '://' . $cd . '/' . $path;
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/tenants/' . $sub . '/' . $path;
    }
}

// Expose globally
$GLOBALS['tenant_id']     = $tenant_id;
$GLOBALS['subdomain']     = $subdomain;
$GLOBALS['custom_domain'] = $custom_domain;
