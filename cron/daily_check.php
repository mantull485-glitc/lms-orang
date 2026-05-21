<?php
// ============================================================
// CRON JOB - Jalankan setiap hari via cron
// Contoh cron: 0 8 * * * php /path/to/platform/cron/daily_check.php
// ============================================================

// Bisa dijalankan via browser untuk testing (tambahkan secret key)
$secret = $_GET['key'] ?? '';
$required_secret = 'GANTI_DENGAN_SECRET_ANDA_123'; // Ganti ini!
if (PHP_SAPI !== 'cli' && $secret !== $required_secret) {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/../config/superadmin_db.php';
require_once __DIR__ . '/../config/email_helper.php';

$log = [];
$today = date('Y-m-d');

// ── 1. Expired tenant: nonaktifkan yang sudah lewat tanggal expire
$expired = $pdo_global->query("
    SELECT * FROM tenants
    WHERE status = 'aktif'
      AND tanggal_expire IS NOT NULL
      AND tanggal_expire < '$today'
")->fetchAll();

foreach ($expired as $t) {
    $pdo_global->prepare("UPDATE tenants SET status='expired' WHERE id=?")->execute([$t['id']]);
    $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,NULL)")
               ->execute([$t['id'], 'aktif', 'expired', 'Otomatis expired oleh sistem (cron)']);
    $log[] = "EXPIRED: {$t['nama_lembaga']} ({$t['email']})";
}

// ── 2. Warning 7 hari sebelum expire
$expiring_soon = $pdo_global->query("
    SELECT * FROM tenants
    WHERE status = 'aktif'
      AND tanggal_expire IS NOT NULL
      AND tanggal_expire = '$today'::date + INTERVAL '7 days'
")->fetchAll();

foreach ($expiring_soon as $t) {
    $base_url = 'https://domain.com/'; // Sesuaikan domain Anda
    emailExpireWarning([
        'email'        => $t['email'],
        'nama_pemilik' => $t['nama_pemilik'],
        'nama_lembaga' => $t['nama_lembaga'],
        'sisa_hari'    => 7,
        'expire'       => date('d M Y', strtotime($t['tanggal_expire'])),
        'renew_url'    => $base_url . 'checkout.php',
    ]);
    $log[] = "WARNED (7d): {$t['nama_lembaga']} ({$t['email']}) - expire {$t['tanggal_expire']}";
}

// ── 3. Warning 1 hari sebelum expire
$expiring_tomorrow = $pdo_global->query("
    SELECT * FROM tenants
    WHERE status = 'aktif'
      AND tanggal_expire IS NOT NULL
      AND tanggal_expire = '$today'::date + INTERVAL '1 day'
")->fetchAll();

foreach ($expiring_tomorrow as $t) {
    $base_url = 'https://domain.com/';
    emailExpireWarning([
        'email'        => $t['email'],
        'nama_pemilik' => $t['nama_pemilik'],
        'nama_lembaga' => $t['nama_lembaga'],
        'sisa_hari'    => 1,
        'expire'       => date('d M Y', strtotime($t['tanggal_expire'])),
        'renew_url'    => $base_url . 'checkout.php',
    ]);
    $log[] = "WARNED (1d): {$t['nama_lembaga']} ({$t['email']}) - expire besok";
}

// ── Output log
$timestamp = date('Y-m-d H:i:s');
$summary = "[{$timestamp}] Cron selesai. Expired: " . count($expired) . " | Warned 7d: " . count($expiring_soon) . " | Warned 1d: " . count($expiring_tomorrow);

// Tulis ke log file
$log_dir = __DIR__ . '/../logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
file_put_contents($log_dir . 'cron.log', $summary . "\n" . implode("\n", $log) . "\n\n", FILE_APPEND);

if (PHP_SAPI === 'cli') {
    echo $summary . "\n";
    foreach ($log as $l) echo "  - $l\n";
} else {
    echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;border-radius:8px'>";
    echo htmlspecialchars($summary . "\n" . implode("\n", $log));
    echo "</pre>";
}
