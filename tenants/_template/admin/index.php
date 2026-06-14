<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
require_once '../config/tenant_settings.php';
$tenant_id = $GLOBALS['tenant_id'] ?? 0;
$brand     = getTenantBranding($pdo);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Get statistics
$st_users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND tenant_id = ?"); $st_users->execute([$tenant_id]);
$st_cls   = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE tenant_id = ?"); $st_cls->execute([$tenant_id]);
$st_pend  = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE status = 'pending' AND tenant_id = ?"); $st_pend->execute([$tenant_id]);
$stats = [
    'users'       => $st_users->fetchColumn(),
    'classes'     => $st_cls->fetchColumn(),
    'pending_regs'=> $st_pend->fetchColumn(),
];

// Get recent registrations
$stmt_recent = $pdo->prepare("
    SELECT r.*, u.nama, c.nama_kelas, c.kategori 
    FROM registrations r 
    JOIN users u ON r.user_id = u.id 
    JOIN classes c ON r.class_id = c.id 
    WHERE r.tenant_id = ?
    ORDER BY r.tanggal_daftar DESC LIMIT 5
");
$stmt_recent->execute([$tenant_id]);
$recent_regs = $stmt_recent->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($brand['nama_lembaga']) ?> – Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php outputBrandingCSS($brand); ?>
</head>
<body class="mesh-bg dark-theme">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Beranda Dashboard</h2>
                    <p class="text-muted mb-0">Pantau statistik dan aktivitas terbaru platform Anda.</p>
                </div>
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold fs-5 mb-0"><?= date('H:i'); ?> WIB</div>
                    <div class="text-muted small"><?= date('d F Y'); ?></div>
                </div>
            </div>

            <!-- Stats with Clear Contrast -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="modern-card p-4 h-100 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-4 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-users text-primary fs-4"></i>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">+2%</span>
                        </div>
                        <h2 class="display-6 fw-bold mb-1 animate-count" style="color: var(--navy-color);" data-target="<?= $stats['users']; ?>">0</h2>
                        <p class="text-muted small mb-0 fw-bold text-uppercase ls-2">Peserta Terdaftar</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="modern-card p-4 h-100 border-0 shadow-sm">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-4 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-graduation-cap text-primary fs-4"></i>
                            </div>
                        </div>
                        <h2 class="display-5 fw-bold mb-1 animate-count" style="color: var(--navy-color);" data-target="<?= $stats['classes']; ?>">0</h2>
                        <p class="text-muted small mb-0 fw-bold text-uppercase ls-2">Katalog Kelas</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="modern-card p-4 h-100 border-0 shadow-sm border-start border-4 border-warning">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-4 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-clock text-warning fs-4"></i>
                            </div>
                        </div>
                        <h2 class="display-5 fw-bold mb-1 animate-count" style="color: var(--navy-color);" data-target="<?= $stats['pending_regs']; ?>">0</h2>
                        <p class="text-muted small mb-0 fw-bold text-uppercase ls-2">Menunggu Review</p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Activity -->
                <div class="col-lg-8">
                    <div class="modern-card h-100">
                        <div class="card-header border-0 p-4 pb-0 d-flex justify-content-between align-items-center" style="background: transparent;">
                            <h5 class="fw-bold mb-0">Pendaftaran Masuk</h5>
                            <a href="registrations.php" class="btn btn-link text-primary text-decoration-none fw-bold small p-0">Detail Penuh <i class="fas fa-chevron-right ms-1"></i></a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-modern align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Peserta</th>
                                            <th>Kelas</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($recent_regs) > 0): ?>
                                            <?php foreach($recent_regs as $reg): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold small"><?= htmlspecialchars($reg['nama']); ?></div>
                                                        <div class="extra-small text-muted"><?= date('d M, H:i', strtotime($reg['tanggal_daftar'])); ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1"><?= htmlspecialchars($reg['kategori'] ?? 'Umum'); ?></div>
                                                        <div class="small fw-bold text-truncate" style="max-width:150px;"><?= htmlspecialchars($reg['nama_kelas']); ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-premium status-<?= $reg['status'] ?>">
                                                            <?= ucfirst($reg['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center py-5 text-muted">Belum ada aktivitas.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="modern-card p-4 h-100">
                        <h5 class="fw-bold mb-4">Aksi Cepat</h5>
                        <div class="d-grid gap-3">
                            <a href="classes.php?action=add" class="btn btn-primary d-flex align-items-center justify-content-between p-3 rounded-4 shadow-none">
                                <span class="fw-bold">Buka Kelas Baru</span>
                                <i class="fas fa-plus-circle opacity-50"></i>
                            </a>
                            <a href="registrations.php?action=add" class="btn d-flex align-items-center justify-content-between p-3 rounded-4" style="background: rgba(255,255,255,0.05);">
                                <span class="fw-bold text-primary">Daftar Manual</span>
                                <i class="fas fa-user-plus text-primary opacity-50"></i>
                            </a>
                            <a href="certificates.php" class="btn d-flex align-items-center justify-content-between p-3 rounded-4 border-0" style="background: rgba(255,255,255,0.05);">
                                <span class="fw-bold text-muted">Cetak Sertifikat</span>
                                <i class="fas fa-award text-warning opacity-50"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animasi counter untuk stat cards
document.querySelectorAll('.animate-count').forEach(el => {
    const target = parseInt(el.dataset.target || '0');
    let count = 0;
    const step = Math.max(1, Math.floor(target / 40));
    const timer = setInterval(() => {
        count = Math.min(count + step, target);
        el.textContent = count;
        if (count >= target) clearInterval(timer);
    }, 30);
});
</script>
</body>
</html>
