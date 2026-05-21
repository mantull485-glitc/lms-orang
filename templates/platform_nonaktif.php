<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Tidak Aktif</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --orange: #FF6A00; --navy: #0F172A; }
        body { font-family: 'Outfit', sans-serif; background: var(--navy); color: #CBD5E1; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-nonaktif { background: #1E293B; border: 1px solid #334155; border-radius: 20px; padding: 3rem; max-width: 520px; text-align: center; }
        .icon-wrap { width: 80px; height: 80px; background: rgba(255,106,0,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
        .icon-wrap svg { width: 40px; height: 40px; color: var(--orange); }
        h2 { color: #F1F5F9; font-weight: 700; }
        .badge-status { background: rgba(255,106,0,0.15); color: var(--orange); border: 1px solid rgba(255,106,0,0.3); border-radius: 20px; padding: 4px 14px; font-size: .8rem; display: inline-block; margin-bottom: 1rem; }
        .alasan-box { background: rgba(255,255,255,0.04); border: 1px solid #334155; border-radius: 10px; padding: 1rem; margin-top: 1.5rem; font-size: .9rem; color: #94A3B8; }
    </style>
</head>
<body>
<?php
$status_label = match($status ?? 'nonaktif') {
    'nonaktif' => 'Dinonaktifkan',
    'suspend'  => 'Disuspend',
    'expired'  => 'Masa Berlaku Habis',
    default    => 'Tidak Aktif',
};
?>
<div class="card-nonaktif">
    <div class="icon-wrap">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div class="badge-status"><?= htmlspecialchars($status_label) ?></div>
    <h2><?= htmlspecialchars($nama ?? 'Platform Ini') ?></h2>
    <p class="text-muted mt-2">Platform pelatihan ini sedang tidak dapat diakses saat ini.</p>

    <?php if (!empty($alasan)): ?>
    <div class="alasan-box">
        <strong style="color:#CBD5E1">Keterangan:</strong><br>
        <?= htmlspecialchars($alasan) ?>
    </div>
    <?php endif; ?>

    <p class="mt-4 text-muted" style="font-size:.85rem">
        Jika Anda pemilik platform ini, silakan hubungi tim support kami untuk informasi lebih lanjut.
    </p>
</div>
</body>
</html>
