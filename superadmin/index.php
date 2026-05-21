<?php
require_once 'auth_guard.php';

// Stats
$total_tenants  = $pdo_global->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$aktif_tenants  = $pdo_global->query("SELECT COUNT(*) FROM tenants WHERE status='aktif'")->fetchColumn();
$pending_orders = $pdo_global->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$total_revenue  = $pdo_global->query("SELECT COALESCE(SUM(harga_bayar),0) FROM orders WHERE status='diterima'")->fetchColumn();

// Recent orders
$recent_orders = $pdo_global->query("
    SELECT o.*, p.nama as paket_nama 
    FROM orders o LEFT JOIN packages p ON o.package_id=p.id
    ORDER BY o.created_at DESC LIMIT 8
")->fetchAll();

// Tenant status overview
$status_counts = $pdo_global->query("SELECT status, COUNT(*) as total FROM tenants GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Dashboard</div>
        <div class="sa-topbar-actions">
            <?php if ($pending_orders > 0): ?>
            <a href="finance.php" class="btn-sa-primary" style="font-size:.82rem;padding:.4rem .9rem">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?= $pending_orders ?> Order Pending
            </a>
            <?php endif; ?>
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>

    <div class="sa-content">

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(255,106,0,.1)">
                        <svg fill="none" stroke="#FF6A00" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
                    </div>
                    <div>
                        <div class="stat-label">Total Tenant</div>
                        <div class="stat-value"><?= $total_tenants ?></div>
                        <div class="stat-trend"><?= $aktif_tenants ?> aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,.1)">
                        <svg fill="none" stroke="#10B981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="stat-label">Tenant Aktif</div>
                        <div class="stat-value"><?= $aktif_tenants ?></div>
                        <div class="stat-trend"><?= ($status_counts['nonaktif'] ?? 0) ?> nonaktif</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,.1)">
                        <svg fill="none" stroke="#F59E0B" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="stat-label">Order Pending</div>
                        <div class="stat-value"><?= $pending_orders ?></div>
                        <div class="stat-trend">Perlu diverifikasi</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(0,210,255,.1)">
                        <svg fill="none" stroke="#00D2FF" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value" style="font-size:1.3rem">Rp <?= number_format($total_revenue, 0, ',', '.') ?></div>
                        <div class="stat-trend">Order diterima</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="sa-card">
            <div class="sa-card-header">
                Order Terbaru
                <a href="orders.php" class="btn-sa-outline" style="font-size:.78rem;padding:.35rem .8rem">Lihat Semua</a>
            </div>
            <div style="overflow-x:auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lembaga</th>
                            <th>Paket</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                        <tr><td colspan="7" class="empty-state">Belum ada order masuk.</td></tr>
                        <?php else: foreach ($recent_orders as $o): ?>
                        <tr>
                            <td>#<?= $o['id'] ?></td>
                            <td><strong><?= htmlspecialchars($o['nama_lembaga']) ?></strong><br><small class="text-muted-sa"><?= htmlspecialchars($o['email']) ?></small></td>
                            <td><?= htmlspecialchars($o['paket_nama'] ?? '-') ?></td>
                            <td>Rp <?= number_format($o['harga_bayar'], 0, ',', '.') ?></td>
                            <td><span class="sa-badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            <td>
                                <?php if ($o['status'] === 'pending'): ?>
                                <a href="finance.php?highlight=<?= $o['id'] ?>" class="btn-sa-primary" style="font-size:.78rem;padding:.3rem .7rem">Verifikasi</a>
                                <?php else: ?>
                                <a href="orders.php?id=<?= $o['id'] ?>" class="btn-sa-outline" style="font-size:.78rem;padding:.3rem .7rem">Detail</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
