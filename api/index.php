<?php
// ============================================================
// VERCEL PHP GATEWAY ROUTER
// Menerima semua request dan meneruskannya ke script PHP yang tepat
// ============================================================

$uri = $_SERVER['REQUEST_URI'] ?? '/';
// Ambil URL path tanpa query parameters
$path = parse_url($uri, PHP_URL_PATH);

// Daftar aturan rewrite untuk Multi-Tenancy
$rules = [
    // 1. Tenants index (landing page tenant)
    '#^/tenants/([^/]+)/?$#' => '/tenants/_template/index.php',
    // 2. Tenants admin index
    '#^/tenants/([^/]+)/admin/?$#' => '/tenants/_template/admin/index.php',
    // 3. Tenants admin pages
    '#^/tenants/([^/]+)/admin/(.*)$#' => '/tenants/_template/admin/$2',
    // 4. Tenants classes
    '#^/tenants/([^/]+)/classes/(.*)$#' => '/tenants/_template/classes/$2',
    // 5. Tenants config
    '#^/tenants/([^/]+)/config/(.*)$#' => '/tenants/_template/config/$2',
    // 6. Tenants auth
    '#^/tenants/([^/]+)/auth/(.*)$#' => '/tenants/_template/auth/$2',
    // 7. Tenants user
    '#^/tenants/([^/]+)/user/(.*)$#' => '/tenants/_template/user/$2',
    // 8. Tenants uploads
    '#^/tenants/([^/]+)/uploads/(.*)$#' => '/tenants/_template/uploads/$2',
    // 9. Tenants assets
    '#^/tenants/([^/]+)/assets/(.*)$#' => '/tenants/_template/assets/$2',
    // 10. Tenants root php files
    '#^/tenants/([^/]+)/([^/]+\.php)$#' => '/tenants/_template/$2',
];

$targetFile = null;

// Cari kecocokan aturan rewrite
foreach ($rules as $pattern => $replacement) {
    if (preg_match($pattern, $path, $matches)) {
        $targetFile = preg_replace($pattern, $replacement, $path);
        break;
    }
}

// Jika tidak cocok dengan aturan tenant, gunakan file standar di root
if (!$targetFile) {
    if ($path === '/' || $path === '') {
        $targetFile = '/index.php';
    } else {
        $targetFile = $path;
    }
}

$realPath = dirname(__DIR__) . '/' . ltrim($targetFile, '/');

// Set variabel global agar script yang di-require berjalan normal seolah diakses langsung
$_SERVER['SCRIPT_NAME'] = $targetFile;
$_SERVER['PHP_SELF'] = $targetFile;

if (file_exists($realPath) && is_file($realPath)) {
    // Jika file PHP, lakukan require
    if (pathinfo($realPath, PATHINFO_EXTENSION) === 'php') {
        chdir(dirname($realPath)); // Pastikan path relatif seperti ../config/ bekerja dengan benar
        require $realPath;
        exit;
    } else {
        // Jika file statis cadangan, serve secara manual dengan MIME type yang sesuai
        $mime_types = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
        ];
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (isset($mime_types[$ext])) {
            header('Content-Type: ' . $mime_types[$ext]);
        }
        readfile($realPath);
        exit;
    }
} else {
    // Jika direktori, coba cari index.php di dalamnya
    $indexPath = rtrim($realPath, '/') . '/index.php';
    if (is_dir($realPath) && file_exists($indexPath)) {
        $_SERVER['SCRIPT_NAME'] = rtrim($targetFile, '/') . '/index.php';
        $_SERVER['PHP_SELF']    = rtrim($targetFile, '/') . '/index.php';
        chdir(rtrim($realPath, '/')); // Set CWD ke direktori index
        require $indexPath;
        exit;
    }
    // Jika file tidak ditemukan, tampilkan 404
    http_response_code(404);
    echo "404 Not Found: " . htmlspecialchars($path);
    exit;
}
