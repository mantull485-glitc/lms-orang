<?php
require_once 'auth_guard.php';

// ── Revenue per bulan (12 bulan terakhir)
$revenue_monthly = $pdo_global->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as bulan,
           COUNT(*) as total_order,
           SUM(harga_bayar) as revenue
    FROM orders
    WHERE status = 'diterima'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY bulan ASC
")->fetchAll();

// ── Tenant baru per bulan
$tenant_monthly = $pdo_global->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as bulan, COUNT(*) as total
    FROM tenants
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY bulan ASC
")->fetchAll();

// ── Revenue per paket
$revenue_by_pkg = $pdo_global->query("
    SELECT p.nama, COUNT(o.id) as total_order, COALESCE(SUM(o.harga_bayar),0) as revenue
    FROM packages p
    LEFT JOIN orders o ON o.package_id = p.id AND o.status = 'diterima'
    GROUP BY p.id, p.nama
    ORDER BY revenue DESC
")->fetchAll();

// ── Summary stats
$stats = [
    'total_revenue'   => $pdo_global->query("SELECT COALESCE(SUM(harga_bayar),0) FROM orders WHERE status='diterima'")->fetchColumn(),
    'revenue_bulan'   => $pdo_global->query("SELECT COALESCE(SUM(harga_bayar),0) FROM orders WHERE status='diterima' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn(),
    'total_tenant'    => $pdo_global->query("SELECT COUNT(*) FROM tenants")->fetchColumn(),
    'aktif_tenant'    => $pdo_global->query("SELECT COUNT(*) FROM tenants WHERE status='aktif'")->fetchColumn(),
    'total_order'     => $pdo_global->query("SELECT COUNT(*) FROM orders WHERE status='diterima'")->fetchColumn(),
    'order_bulan'     => $pdo_global->query("SELECT COUNT(*) FROM orders WHERE status='diterima' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn(),
    'pending_order'   => $pdo_global->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
    'nonaktif_tenant' => $pdo_global->query("SELECT COUNT(*) FROM tenants WHERE status='nonaktif'")->fetchColumn(),
];

// Build chart data arrays
$chart_labels  = [];
$chart_revenue = [];
$chart_tenants = [];

// Generate last 12 months
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $chart_labels[] = date('M Y', strtotime("$m-01"));
    $chart_revenue[$m] = 0;
    $chart_tenants[$m] = 0;
}
foreach ($revenue_monthly as $r) $chart_revenue[$r['bulan']] = (int)$r['revenue'];
foreach ($tenant_monthly  as $t) $chart_tenants[$t['bulan']] = (int)$t['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Analitik – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Laporan & Analitik</div>
        <div class="sa-topbar-actions">
            <span style="font-size:.8rem;color:var(--text-muted)">Data per <?= date('d M Y') ?></span>
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>

    <div class="sa-content">

        <!-- Summary Stats -->
        <div class="row g-3 mb-4">
            <?php
            $cards = [
                ['Total Revenue',     'Rp '.number_format($stats['total_revenue'],0,',','.'),  'Rp '.number_format($stats['revenue_bulan'],0,',','.').' bulan ini',  '#00D2FF','rgba(0,210,255,.1)'],
                ['Tenant Aktif',      $stats['aktif_tenant'],   $stats['nonaktif_tenant'].' nonaktif',     '#10B981','rgba(16,185,129,.1)'],
                ['Order Berhasil',    $stats['total_order'],    $stats['order_bulan'].' bulan ini',         '#FF6A00','rgba(255,106,0,.1)'],
                ['Order Pending',     $stats['pending_order'],  'Menunggu verifikasi',                      '#F59E0B','rgba(245,158,11,.1)'],
            ];
            foreach ($cards as [$label,$val,$sub,$color,$bg]):
            ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:<?= $bg ?>">
                        <svg fill="none" stroke="<?= $color ?>" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <div>
                        <div class="stat-label"><?= $label ?></div>
                        <div class="stat-value" style="color:<?= $color ?>;font-size:1.5rem"><?= $val ?></div>
                        <div class="stat-trend"><?= $sub ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <!-- Revenue Chart -->
            <div class="col-xl-8">
                <div class="sa-card h-100">
                    <div class="sa-card-header">Revenue 12 Bulan Terakhir</div>
                    <div class="sa-card-body">
                        <canvas id="revenueChart" height="90"></canvas>
                    </div>
                </div>
            </div>
            <!-- Tenant Growth -->
            <div class="col-xl-4">
                <div class="sa-card h-100">
                    <div class="sa-card-header">Tenant Baru per Bulan</div>
                    <div class="sa-card-body">
                        <canvas id="tenantChart" height="180"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue by Package -->
        <div class="row g-3">
            <div class="col-xl-6">
                <div class="sa-card">
                    <div class="sa-card-header">Revenue per Paket</div>
                    <div class="sa-card-body">
                        <canvas id="pkgChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Revenue Table -->
            <div class="col-xl-6">
                <div class="sa-card">
                    <div class="sa-card-header">Breakdown Paket</div>
                    <div style="overflow-x:auto">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th>Paket</th>
                                    <th>Total Order</th>
                                    <th>Revenue</th>
                                    <th>Kontribusi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_rev = array_sum(array_column($revenue_by_pkg, 'revenue')) ?: 1;
                                foreach ($revenue_by_pkg as $r):
                                    $pct = round(($r['revenue'] / $total_rev) * 100);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
                                    <td><?= $r['total_order'] ?> order</td>
                                    <td style="color:var(--orange);font-weight:600">Rp <?= number_format($r['revenue'],0,',','.') ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div style="flex:1;height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden">
                                                <div style="width:<?= $pct ?>%;height:100%;background:var(--orange);border-radius:3px"></div>
                                            </div>
                                            <span style="font-size:.75rem;color:var(--text-muted);width:30px"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent tenant list -->
        <div class="sa-card mt-3">
            <div class="sa-card-header">
                10 Tenant Terbaru
                <a href="tenants.php" class="btn-sa-outline" style="font-size:.78rem;padding:.3rem .7rem">Lihat Semua</a>
            </div>
            <div style="overflow-x:auto">
                <table class="sa-table">
                    <thead>
                        <tr><th>Lembaga</th><th>Paket</th><th>Status</th><th>Bergabung</th><th>Expire</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $recent = $pdo_global->query("SELECT t.*, p.nama as paket_nama FROM tenants t LEFT JOIN packages p ON t.package_id=p.id ORDER BY t.created_at DESC LIMIT 10")->fetchAll();
                    foreach ($recent as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['nama_lembaga']) ?></strong><br><small class="text-muted-sa"><?= htmlspecialchars($t['email']) ?></small></td>
                        <td><?= htmlspecialchars($t['paket_nama'] ?? '—') ?></td>
                        <td><span class="sa-badge badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                        <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                        <td><?= $t['tanggal_expire'] ? date('d M Y', strtotime($t['tanggal_expire'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
const chartDefaults = {
    color: '#94A3B8',
    plugins: { legend: { labels: { color: '#94A3B8', font: { family: 'Outfit' } } } },
    scales: {
        x: { ticks: { color: '#64748B', font: { family: 'Outfit', size: 10 } }, grid: { color: 'rgba(30,58,95,.5)' } },
        y: { ticks: { color: '#64748B', font: { family: 'Outfit', size: 10 } }, grid: { color: 'rgba(30,58,95,.5)' } }
    }
};

// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Revenue (Rp)',
            data: <?= json_encode(array_values($chart_revenue)) ?>,
            backgroundColor: 'rgba(255,106,0,.25)',
            borderColor: '#FF6A00',
            borderWidth: 2,
            borderRadius: 6,
        }, {
            label: 'Tenant Baru',
            data: <?= json_encode(array_values($chart_tenants)) ?>,
            type: 'line',
            borderColor: '#00D2FF',
            backgroundColor: 'rgba(0,210,255,.08)',
            borderWidth: 2,
            pointRadius: 4,
            fill: true,
            tension: .4,
            yAxisID: 'y1',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#94A3B8', font: { family: 'Outfit' } } } },
        scales: {
            x: { ticks: { color: '#64748B', font: { family: 'Outfit', size: 10 } }, grid: { color: 'rgba(30,58,95,.5)' } },
            y: {
                ticks: {
                    color: '#64748B', font: { family: 'Outfit', size: 10 },
                    callback: v => 'Rp' + Intl.NumberFormat('id-ID', {notation:'compact'}).format(v)
                },
                grid: { color: 'rgba(30,58,95,.5)' }
            },
            y1: {
                position: 'right', ticks: { color: '#64748B', font: { family: 'Outfit', size: 10 } },
                grid: { display: false }
            }
        }
    }
});

// Tenant donut
new Chart(document.getElementById('tenantChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Tenant Baru',
            data: <?= json_encode(array_values($chart_tenants)) ?>,
            backgroundColor: 'rgba(16,185,129,.3)',
            borderColor: '#10B981',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#64748B', font: { family: 'Outfit', size: 9 }, maxRotation: 45 }, grid: { color: 'rgba(30,58,95,.5)' } },
            y: { ticks: { color: '#64748B', font: { family: 'Outfit', size: 10 } }, grid: { color: 'rgba(30,58,95,.5)' } }
        }
    }
});

// Package pie
new Chart(document.getElementById('pkgChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($revenue_by_pkg, 'nama')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($revenue_by_pkg, 'revenue')) ?>,
            backgroundColor: ['rgba(255,106,0,.8)','rgba(0,210,255,.8)','rgba(16,185,129,.8)','rgba(139,92,246,.8)'],
            borderWidth: 0,
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#94A3B8', font: { family: 'Outfit' }, padding: 16 } }
        },
        cutout: '65%',
    }
});
</script>
</body>
</html>
