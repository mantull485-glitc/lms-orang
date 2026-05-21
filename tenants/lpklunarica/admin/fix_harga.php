<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$fixed = 0;
$message = '';

// Auto-fix: Update registrations where harga_saat_daftar = 0 using class price
if (isset($_POST['fix'])) {
    $stmt_fix = $pdo->query("
        SELECT r.id, r.harga_saat_daftar, c.harga, c.harga_spesial
        FROM registrations r
        JOIN classes c ON r.class_id = c.id
        WHERE r.harga_saat_daftar = 0 OR r.harga_saat_daftar IS NULL
    ");
    $to_fix = $stmt_fix->fetchAll();

    foreach ($to_fix as $row) {
        $correct_price = ($row['harga_spesial'] !== null) ? $row['harga_spesial'] : $row['harga'];
        if ($correct_price > 0) {
            $pdo->prepare("UPDATE registrations SET harga_saat_daftar = ? WHERE id = ?")->execute([$correct_price, $row['id']]);
            $fixed++;
        }
    }
    $message = "✅ Berhasil memperbaiki <b>$fixed</b> data pendaftaran. Silakan kembali ke halaman Keuangan.";
}

// Diagnose: show all registrations with price info
$stmt = $pdo->query("
    SELECT r.id, r.status, r.harga_saat_daftar, r.tanggal_daftar,
           u.nama, c.nama_kelas, c.harga as harga_kelas, c.harga_spesial
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN classes c ON r.class_id = c.id
    ORDER BY r.id DESC
");
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Diagnostik & Perbaikan Data Harga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { padding: 2rem; background: #f8f9fa; font-family: sans-serif; }</style>
</head>
<body>
<div class="container">
    <h4 class="fw-bold mb-1">🔧 Diagnostik Data Harga Pendaftaran</h4>
    <p class="text-muted small mb-4">Halaman ini mendeteksi dan memperbaiki data <code>harga_saat_daftar = 0</code> secara otomatis.</p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <button name="fix" type="submit" class="btn btn-danger fw-bold px-5">
            ⚡ Perbaiki Semua Data Harga = 0 Sekarang
        </button>
        <a href="finance.php" class="btn btn-secondary ms-2">← Kembali ke Keuangan</a>
    </form>

    <div class="card">
        <div class="card-header fw-bold">Semua Data Pendaftaran (<?= count($rows) ?> baris)</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th><th>Nama</th><th>Kelas</th>
                        <th>Status</th>
                        <th>Harga Kelas</th>
                        <th>harga_saat_daftar</th>
                        <th>Status Data</th>
                        <th>Tgl Daftar</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $ada_masalah = ($r['harga_saat_daftar'] == 0 || $r['harga_saat_daftar'] === null); ?>
                    <tr class="<?= $ada_masalah ? 'table-warning' : '' ?>">
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['nama']) ?></td>
                        <td><?= htmlspecialchars($r['nama_kelas']) ?></td>
                        <td><span class="badge bg-secondary"><?= $r['status'] ?></span></td>
                        <td>Rp <?= number_format($r['harga_spesial'] ?? $r['harga_kelas'], 0, ',', '.') ?></td>
                        <td>
                            <strong class="<?= $ada_masalah ? 'text-danger' : 'text-success' ?>">
                                Rp <?= number_format($r['harga_saat_daftar'], 0, ',', '.') ?>
                            </strong>
                        </td>
                        <td><?= $ada_masalah ? '⚠️ Perlu Perbaikan' : '✅ OK' ?></td>
                        <td><?= date('d/m/Y', strtotime($r['tanggal_daftar'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
