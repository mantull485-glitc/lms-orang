<?php
// ============================================================
// TENANT SETTINGS HELPER — Supabase multi-tenant compatible
// Memuat nama lembaga, logo, dan warna dari database
// Menggunakan tenant_id dari GLOBALS untuk isolasi per tenant
// ============================================================

if (!function_exists('getSetting')) {
    function getSetting(PDO $pdo, string $key, string $default = '', int $tid = 0): string {
        static $cache = [];
        $cacheKey = $tid . ':' . $key;
        if (isset($cache[$cacheKey])) return $cache[$cacheKey];
        if ($tid === 0) $tid = (int)($GLOBALS['tenant_id'] ?? 0);
        $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=? AND tenant_id=?");
        $s->execute([$key, $tid]);
        $r = $s->fetchColumn();
        $cache[$cacheKey] = $r !== false ? $r : $default;
        return $cache[$cacheKey];
    }
}

function getAllSettings(PDO $pdo, int $tid = 0): array {
    if ($tid === 0) $tid = (int)($GLOBALS['tenant_id'] ?? 0);
    $s = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id=?");
    $s->execute([$tid]);
    return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}

function getTenantBranding(PDO $pdo, int $tid = 0): array {
    if ($tid === 0) $tid = (int)($GLOBALS['tenant_id'] ?? 0);
    return [
        'nama_lembaga'   => getSetting($pdo, 'nama_lembaga',    'Platform LPK', $tid),
        'tagline'        => getSetting($pdo, 'tagline',          'Platform Pelatihan Profesional', $tid),
        'alamat'         => getSetting($pdo, 'alamat',           '', $tid),
        'no_telp'        => getSetting($pdo, 'no_telp',          '', $tid),
        'email_lembaga'  => getSetting($pdo, 'email_lembaga',    '', $tid),
        'website'        => getSetting($pdo, 'website',          '', $tid),
        'logo'           => getSetting($pdo, 'logo',             '', $tid),
        'color_primary'  => getSetting($pdo, 'color_primary',    '#FF6A00', $tid),
        'color_secondary'=> getSetting($pdo, 'color_secondary',  '#00D2FF', $tid),
        'color_navy'     => getSetting($pdo, 'color_navy',       '#0F172A', $tid),
        'color_navy_light'=> getSetting($pdo, 'color_navy_light','#1E293B', $tid),
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
