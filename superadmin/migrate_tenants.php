<?php
require_once 'auth_guard.php';
require_once '../config/provisioner.php';

// Ambil semua tenant yang punya db_name
$tenants = $pdo_global->query("SELECT id, nama_lembaga, subdomain, db_name FROM tenants WHERE db_name IS NOT NULL AND db_name != '' ORDER BY id ASC")->fetchAll();

$results = [];

foreach ($tenants as $t) {
    try {
        $pdo_t = new PDO(
            'mysql:host=' . SA_DB_HOST . ';dbname=' . $t['db_name'] . ';charset=utf8mb4',
            SA_DB_USER, SA_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        runTenantMigrations($pdo_t);
        $results[] = ['nama' => $t['nama_lembaga'], 'db' => $t['db_name'], 'status' => 'ok'];
    } catch (PDOException $e) {
        $results[] = ['nama' => $t['nama_lembaga'], 'db' => $t['db_name'], 'status' => 'error', 'msg' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Tenant DB – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">🛠️ Migrasi Database Tenant</div>
        <div class="sa-topbar-actions">
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>
    <div class="sa-content">
        <div class="sa-card mb-3">
            <div class="sa-card-header">Hasil Migrasi</div>
            <div class="sa-card-body">
                <p style="color:var(--text-sub);font-size:.875rem">
                    Script ini menambahkan kolom <code>no_hp</code> (tabel users) dan <code>catatan_admin</code> (tabel registrations) ke semua database tenant yang belum memilikinya.
                    Kolom yang sudah ada akan dilewati secara otomatis.
                </p>
                <table class="sa-table">
                    <thead>
                        <tr><th>#</th><th>Tenant</th><th>Database</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
                            <td><code style="color:var(--cyan)"><?= htmlspecialchars($r['db']) ?></code></td>
                            <td>
                                <?php if ($r['status'] === 'ok'): ?>
                                    <span class="sa-badge badge-aktif">✅ Berhasil</span>
                                <?php else: ?>
                                    <span class="sa-badge badge-nonaktif" title="<?= htmlspecialchars($r['msg'] ?? '') ?>">❌ Error</span>
                                    <small style="color:#EF4444;display:block"><?= htmlspecialchars($r['msg'] ?? '') ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($results)): ?>
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Tidak ada tenant dengan database yang terdaftar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div style="display:flex;gap:1rem">
            <a href="tenants.php" class="btn-sa-outline">← Kembali ke Daftar Tenant</a>
        </div>
    </div>
</div>
</body>
</html>
