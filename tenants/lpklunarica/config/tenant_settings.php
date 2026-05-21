<?php
// ============================================================
// TENANT SETTINGS HELPER — Include di semua halaman publik
// Memuat nama lembaga, logo, dan warna dari database
// ============================================================

if (!function_exists('getSetting')) {
    function getSetting(PDO $pdo, string $key, string $default = ''): string {
        static $cache = [];
        if (isset($cache[$key])) return $cache[$key];
        $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
        $s->execute([$key]);
        $r = $s->fetchColumn();
        $cache[$key] = $r !== false ? $r : $default;
        return $cache[$key];
    }
}

function getAllSettings(PDO $pdo): array {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    return $rows;
}

function getTenantBranding(PDO $pdo): array {
    return [
        'nama_lembaga'   => getSetting($pdo, 'nama_lembaga', 'Platform LPK'),
        'tagline'        => getSetting($pdo, 'tagline', 'Platform Pelatihan Profesional'),
        'alamat'         => getSetting($pdo, 'alamat', ''),
        'no_telp'        => getSetting($pdo, 'no_telp', ''),
        'email_lembaga'  => getSetting($pdo, 'email_lembaga', ''),
        'website'        => getSetting($pdo, 'website', ''),
        'logo'           => getSetting($pdo, 'logo', ''),
        'color_primary'  => getSetting($pdo, 'color_primary', '#FF6A00'),
        'color_secondary'=> getSetting($pdo, 'color_secondary', '#00D2FF'),
        'color_navy'     => getSetting($pdo, 'color_navy', '#0F172A'),
        'color_navy_light'=> getSetting($pdo, 'color_navy_light', '#1E293B'),
    ];
}

// Hitung turunan warna dari primary
function hexToRgb(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    return "$r, $g, $b";
}

function outputBrandingCSS(array $b): void {
    $primary_rgb = hexToRgb($b['color_primary']);
    $secondary_rgb = hexToRgb($b['color_secondary']);
    echo "<style>
:root {
    --primary-color: {$b['color_primary']};
    --primary-gradient: linear-gradient(135deg, {$b['color_primary']} 0%, {$b['color_primary']}cc 100%);
    --secondary-color: {$b['color_secondary']};
    --navy-color: {$b['color_navy']};
    --navy-light: {$b['color_navy_light']};
    --navy-gradient: linear-gradient(135deg, {$b['color_navy_light']}, {$b['color_navy']});
    --bs-primary: {$b['color_primary']};
    --bs-primary-rgb: {$primary_rgb};
    --bs-link-color: {$b['color_primary']};
    --bs-link-hover-color: {$b['color_primary']}cc;
    --shadow-primary: 0 4px 15px rgba({$primary_rgb}, 0.3);
}
.btn-primary, .btn-custom {
    background: var(--primary-gradient) !important;
    box-shadow: 0 4px 15px rgba({$primary_rgb}, 0.3) !important;
}
.btn-primary:hover, .btn-custom:hover {
    box-shadow: 0 8px 25px rgba({$primary_rgb}, 0.4) !important;
}
.text-primary { color: var(--primary-color) !important; }
.bg-primary { background-color: var(--primary-color) !important; }
.border-primary { border-color: var(--primary-color) !important; }
.btn-outline-primary { color: var(--primary-color) !important; border-color: var(--primary-color) !important; }
.btn-outline-primary:hover { background-color: var(--primary-color) !important; color: #fff !important; }
.admin-sidebar .nav-link.active { background: var(--primary-gradient) !important; box-shadow: 0 4px 15px rgba({$primary_rgb}, 0.3) !important; }
body.dark-theme, .mesh-bg, .hero-section { background-color: {$b['color_navy']} !important; }
.admin-sidebar { background: {$b['color_navy']} !important; }
</style>\n";
}
