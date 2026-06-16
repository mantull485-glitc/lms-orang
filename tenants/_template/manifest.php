<?php
session_start();
require_once 'config/tenant_guard.php';
require_once 'config/database.php';
require_once 'config/tenant_settings.php';

header('Content-Type: application/json');

$brand = getTenantBranding($pdo);
$nama  = $brand['nama_lembaga'] ?? 'LMS Platform';
$tagline = $brand['tagline'] ?? 'Platform Pelatihan';
$logo  = $brand['logo'];

// Base URL for start_url
$base_url = './'; 
if (isset($_SERVER['TENANT_SUBDOMAIN']) || !empty($_SERVER['HTTP_HOST'])) {
    $base_url = './';
}

$icon_src = "assets/img/logo-192.png"; // Fallback
if ($logo && file_exists(__DIR__ . '/assets/img/' . $logo)) {
    // Ideally we resize this, but for now just use the logo
    $icon_src = "assets/img/" . $logo;
}

$manifest = [
    "name" => $nama,
    "short_name" => mb_substr($nama, 0, 12),
    "description" => $tagline,
    "start_url" => $base_url,
    "display" => "standalone",
    "background_color" => "#0F172A",
    "theme_color" => "#3B82F6",
    "icons" => [
        [
            "src" => $icon_src,
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => $icon_src,
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT);
