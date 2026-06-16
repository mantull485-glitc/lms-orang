<?php
$source = 'C:/Users/ASUS/.gemini/antigravity/brain/e6d1c038-6e67-4754-a848-1f682013be66/lpk_app_icon_1781634627563.png';

$targets = [
    'd:/1/htdocs/platform/assets/img/logo-192.png',
    'd:/1/htdocs/platform/assets/img/logo-512.png',
    'd:/1/htdocs/platform/tenants/_template/assets/img/logo-192.png',
    'd:/1/htdocs/platform/tenants/_template/assets/img/logo-512.png'
];

$success = true;
foreach ($targets as $target) {
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    if (!copy($source, $target)) {
        $success = false;
        echo "Gagal mengcopy ke: $target <br>";
    } else {
        echo "Berhasil mengcopy ke: $target <br>";
    }
}

if ($success) {
    echo "<h3>Semua logo berhasil dipasang! Anda sudah bisa menghapus file ini.</h3>";
}
