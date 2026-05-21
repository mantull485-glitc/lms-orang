<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
require_once '../config/tenant_settings.php';

$brand = getTenantBranding($pdo);
$nama = $brand['nama_lembaga'];

$stmt = $pdo->prepare("SELECT * FROM classes WHERE tenant_id = ? ORDER BY jadwal ASC");
$stmt->execute([$GLOBALS['tenant_id'] ?? 0]);
$classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Kelas - <?= htmlspecialchars($nama); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <!-- FontAwesome 5.15.4 (Stable) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <?php outputBrandingCSS($brand); ?>
    <style>
        .section-premium {
            min-height: 40vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 100px !important;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
    </style>
</head>
<body class="dark-theme">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="../index.php">
            <?php if (!empty($brand['logo']) && file_exists(dirname(__DIR__).'/assets/img/'.$brand['logo'])): ?>
            <img src="../assets/img/<?= htmlspecialchars($brand['logo']) ?>" style="height:36px;width:auto;object-fit:contain" alt="<?= htmlspecialchars($nama) ?>">
            <?php else: ?>
            <div class="bg-primary rounded-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px"><i class="fas fa-graduation-cap text-white"></i></div>
            <span class="text-primary"><?= htmlspecialchars($nama) ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="index.php">Daftar Kelas</a></li>
                <li class="nav-item"><a class="nav-link" href="../about.php">Tentang Kami</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="../admin/index.php">Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="../user/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-danger btn-sm" href="../auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary btn-sm me-2" href="../auth/login.php">Login</a>
                        <a class="btn btn-primary btn-sm btn-custom text-white" href="../auth/register.php">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Premium Header with Glow Effects -->
<header class="mesh-bg text-white py-5 section-premium position-relative overflow-hidden">
    <!-- Glow Effects -->
    <div class="glow-container">
        <div class="glow-blob" style="background: var(--primary-color); width: 400px; height: 400px; top: -100px; right: 10%;"></div>
        <div class="glow-blob" style="background: var(--secondary-color); width: 500px; height: 500px; bottom: -200px; left: -5%;"></div>
    </div>
    <div class="container text-center position-relative z-index-2" data-aos="zoom-in" data-aos-duration="1000">
        <h1 class="display-4 fw-black mb-3 text-white" style="letter-spacing: -1px;">Katalog Kelas Populer</h1>
        <p class="lead text-muted-light mb-0 mx-auto" style="max-width: 700px;">
            Pilih program pelatihan terbaik yang dirancang khusus untuk mempercepat karier profesional Anda.
        </p>
    </div>
</header>

<!-- Main Content -->
<div class="container py-5 my-5">
    <div class="row g-5">
        <?php if (count($classes) > 0): ?>
            <?php foreach($classes as $class): ?>
            <div class="col-sm-6 col-md-4 reveal-on-scroll">
                <div class="modern-card card-class h-100 p-0 border-0 shadow-lg">
                    <div class="card-body p-5 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill extra-small fw-bold">
                                <?= htmlspecialchars($class['kategori'] ?? 'Premium'); ?>
                            </span>
                        </div>
                        <h3 class="fw-bold mb-3"><?= htmlspecialchars($class['nama_kelas']); ?></h3>
                        <p class="text-muted mb-4 flex-grow-1" style="line-height: 1.7;">
                            <?= mb_strimwidth(htmlspecialchars($class['deskripsi']), 0, 150, '...'); ?>
                        </p>
                        
                        <div class="p-4 rounded-4 mb-4" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="extra-small text-muted-light fw-bold text-uppercase ls-1 mb-1">Mulai Pelatihan</div>
                                    <div class="small fw-bold text-white"><i class="far fa-calendar-alt me-2 text-primary"></i><?= date('d M Y', strtotime($class['jadwal'])); ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="extra-small text-muted-light fw-bold text-uppercase ls-1 mb-1">Investasi</div>
                                    <?php if($class['harga'] == 0): ?>
                                        <div class="fw-black text-success">FREE</div>
                                    <?php else: ?>
                                        <div class="fw-black text-primary" style="font-size: 1.1rem;">Rp <?= number_format($class['harga_spesial'] ?? $class['harga'], 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <a href="detail.php?id=<?= $class['id']; ?>" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-none">
                            Lihat Detail Program <i class="fas fa-chevron-right ms-2 small"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 reveal-on-scroll">
                <div class="modern-card p-5 d-inline-block text-center border-0 shadow-lg">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                        <i class="far fa-folder-open fa-2x text-primary opacity-75"></i>
                    </div>
                    <h4 class="text-white fw-bold mb-2">Belum ada kelas saat ini</h4>
                    <p class="text-muted-light mb-0">Silahkan periksa kembali beberapa saat lagi, tim kami sedang menyiapkan modul pelatihan terbaru.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Class Not Found / Request -->
    <div class="row mt-5 pt-5 border-top border-light border-opacity-10 reveal-on-scroll">
        <div class="col-12 text-center">
            <div class="d-inline-flex flex-column align-items-center p-4 rounded-4" style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1);">
                <i class="fas fa-search text-muted-light fs-3 mb-3 opacity-50"></i>
                <h5 class="text-white fw-bold mb-2">Tidak menemukan kelas yang Anda cari?</h5>
                <p class="text-muted-light small mb-4">Kami terus memperbarui kurikulum. Beritahu kami kebutuhan pelatihan Anda.</p>
                <a href="mailto:info@lunarica.com" class="btn btn-outline-light rounded-pill px-4 text-white">
                    <i class="fas fa-envelope me-2"></i> Hubungi Kami
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer-custom pb-4 pt-5 mt-auto">
    <div class="container text-center text-muted">
        <p class="mb-0">&copy; <?= date('Y'); ?> <?= htmlspecialchars($nama); ?>. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<!-- Main Interaction JS -->
<script src="../assets/js/main.js?v=<?= time(); ?>"></script>
<script>
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true,
        offset: 50
    });

    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            document.querySelector('.navbar').classList.add('navbar-scrolled');
        } else {
            document.querySelector('.navbar').classList.remove('navbar-scrolled');
        }
    });
</script>
</body>
</html>
