<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header("Location: ../admin/index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// Fetch registrations
$stmt_regs = $pdo->prepare("
    SELECT r.*, c.nama_kelas, c.jadwal, c.link_zoom, c.kategori, cert.file_path as cert_file
    FROM registrations r 
    JOIN classes c ON r.class_id = c.id 
    LEFT JOIN certificates cert ON r.user_id = cert.user_id AND r.class_id = cert.class_id
    WHERE r.user_id = ? 
    ORDER BY r.tanggal_daftar DESC
");
$stmt_regs->execute([$user_id]);
$registrations = $stmt_regs->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - LPK Lunarica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="mesh-bg dark-theme">

    <!-- Member Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-navy fixed-top px-lg-5 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="../index.php">
                <div class="bg-primary rounded-2 d-flex align-items-center justify-content-center"
                    style="width: 32px; height: 32px;">
                    <i class="fas fa-graduation-cap text-white fs-6"></i>
                </div>
                <span>LPK Lunarica</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2 mt-3 mt-lg-0">
                    <li class="nav-item"><a class="nav-link px-3" href="../index.php">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="../classes/index.php">Daftar Kelas</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="../about.php">Tentang Kami</a></li>
                    <li class="nav-item ms-lg-3">
                        <div class="d-flex align-items-center gap-3 bg-white bg-opacity-10 p-2 rounded-pill px-3">
                            <div class="extra-small text-white-50 d-none d-md-block">Halo, <span
                                    class="text-white fw-bold"><?= explode(' ', $user['nama'])[0] ?></span></div>
                            <a href="../auth/logout.php"
                                class="btn btn-primary btn-sm rounded-pill px-3 py-1 extra-small fw-bold border-0 shadow-sm">Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main style="padding-top: 80px; min-height: 100vh;">
        <div class="container py-3 py-md-0">

            <!-- Welcome Header -->
            <div class="row mb-5" data-aos="fade-up">
                <div class="col-12">
                    <div class="modern-card p-4 p-md-5 border-0 shadow-sm overflow-hidden position-relative">
                        <div class="position-relative" style="z-index: 2;">
                            <h2 class="fw-bold mb-2">Selamat Datang Meluncur, <?= htmlspecialchars($user['nama']); ?>!
                            </h2>
                            <p class="text-muted mb-0">Lihat progress belajar dan akses link pelatihan Anda dengan
                                mudah.</p>
                        </div>
                        <!-- Decorative element -->
                        <div class="position-absolute end-0 top-0 text-primary opacity-10"
                            style="font-size: 15rem; transform: translate(25%, -25%);">
                            <i class="fas fa-user-astronaut"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Summary Stats -->
                <div class="col-12 col-md-4 col-lg-3">
                    <div class="d-flex flex-column gap-4 mb-4 mb-md-0">
                        <div class="modern-card p-4 text-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                                style="width: 80px; height: 80px; font-size: 2rem;">
                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                            </div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['nama']); ?></h5>
                            <div class="extra-small text-muted mb-4"><?= htmlspecialchars($user['email']); ?></div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-2 rounded-3" style="background: rgba(255,255,255,0.05);">
                                        <div class="fw-bold fs-5"><?= count($registrations) ?></div>
                                        <div class="extra-small text-muted">Kelas</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <?php
                                    $completed = 0;
                                    foreach ($registrations as $r)
                                        if ($r['status'] == 'selesai')
                                            $completed++;
                                    ?>
                                    <div class="p-2 rounded-3 text-success" style="background: rgba(255,255,255,0.05);">
                                        <div class="fw-bold fs-5"><?= $completed ?></div>
                                        <div class="extra-small text-muted">Lulus</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modern-card p-4">
                            <h6 class="fw-bold mb-3 small">Bantuan & Support</h6>
                            <a href="https://wa.me/6282246957738" target="_blank"
                                class="btn btn-light w-100 rounded-4 text-start p-3 border-0 bg-success bg-opacity-10 text-success mb-2 shadow-none">
                                <i class="fab fa-whatsapp me-2"></i>
                                <span class="extra-small fw-bold">Chat Administrator</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="col-12 col-md-8 col-lg-9">
                    <div class="modern-card">
                        <div class="card-header border-0 p-4 d-flex justify-content-between align-items-center" style="background: transparent;">
                            <h5 class="fw-bold mb-0">Riwayat Pelatihan</h5>
                            <a href="../index.php#classes"
                                class="btn btn-outline-primary btn-sm rounded-pill px-3 extra-small fw-bold">Tambah
                                Kelas</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-modern align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Pelatihan</th>
                                        <th>Status & Progress</th>
                                        <th class="text-end">Akses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($registrations) > 0): ?>
                                        <?php foreach ($registrations as $reg): ?>
                                            <tr>
                                                <td>
                                                    <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1">
                                                        <?= htmlspecialchars($reg['kategori'] ?? 'Umum'); ?>
                                                    </div>
                                                    <div class="fw-bold small text-truncate" style="max-width: 250px;">
                                                        <?= htmlspecialchars($reg['nama_kelas']); ?>
                                                    </div>
                                                    <div class="extra-small text-muted mt-1">
                                                        <?= date('d M Y', strtotime($reg['jadwal'])); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="mb-2">
                                                        <span
                                                            class="badge badge-premium status-<?= $reg['status'] ?> extra-small">
                                                            <?= ucfirst($reg['status']) ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($reg['status'] == 'selesai' && !empty($reg['cert_file'])): ?>
                                                        <a href="../<?= htmlspecialchars($reg['cert_file']); ?>" target="_blank"
                                                            class="text-primary extra-small fw-bold text-decoration-none">
                                                            <i class="fas fa-file-invoice me-1"></i>Lihat E-Sertifikat
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($reg['status'] == 'diterima' && !empty($reg['link_zoom'])): ?>
                                                        <a href="<?= htmlspecialchars($reg['link_zoom']); ?>" target="_blank"
                                                            class="btn btn-success btn-sm rounded-pill px-3 extra-small shadow-none">
                                                            <i class="fas fa-video me-1"></i>Mulai Kelas
                                                        </a>
                                                    <?php elseif ($reg['status'] == 'pending'): ?>
                                                        <span class="extra-small text-muted fw-bold"><i
                                                                class="fas fa-hourglass-half me-1"></i>Verifikasi Admin</span>
                                                    <?php elseif ($reg['status'] == 'selesai'): ?>
                                                        <span class="extra-small text-success fw-bold"><i
                                                                class="fas fa-check-circle me-1"></i>Selesai</span>
                                                    <?php else: ?>
                                                        <span class="extra-small text-muted opacity-50">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <div class="text-muted opacity-50 mb-3" style="font-size: 3rem;"><i
                                                        class="fas fa-graduation-cap"></i></div>
                                                <div class="fw-bold text-muted small">Anda belum terdaftar di kelas manapun.
                                                </div>
                                                <a href="../index.php#classes"
                                                    class="btn btn-primary btn-sm mt-3 rounded-pill px-4">Jelajahi Kelas</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 border-top mt-5" style="border-color: rgba(255,255,255,0.1) !important;">
        <div class="container text-center">
            <p class="text-muted extra-small mb-0">&copy; <?= date('Y'); ?> LPK Lunarica. Dibuat dengan &hearts; untuk
                masa depan yang lebih baik.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js?v=<?= time(); ?>"></script>
</body>

</html>