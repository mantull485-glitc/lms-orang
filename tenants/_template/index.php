<?php
session_start();
// Cek status aktif dari super admin
require_once 'config/tenant_guard.php';
require_once 'config/database.php';
require_once 'config/tenant_settings.php';

$brand = getTenantBranding($pdo);
$nama  = $brand['nama_lembaga'];
$tagline = $brand['tagline'];
$logo  = $brand['logo'];

// Fetch some recent classes to display on the landing page
$stmt = $pdo->prepare("SELECT * FROM classes WHERE tenant_id = ? ORDER BY id DESC LIMIT 3");
$stmt->execute([$GLOBALS['tenant_id'] ?? 0]);
$recent_classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($nama) ?> - <?= htmlspecialchars($tagline) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
    <!-- FontAwesome 5.15.4 (Stable) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- PWA Setup -->
    <link rel="manifest" href="manifest.php?v=<?= time() ?>">
    <meta name="theme-color" content="#0F172A">
    <link rel="apple-touch-icon" href="/assets/logo/logolpk.png?v=2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <?php outputBrandingCSS($brand); ?>
</head>
<body class="dark-theme">

<!-- PWA Service Worker Registration -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Service Worker Registered'))
                .catch(err => console.log('Service Worker Registration Failed:', err));
        });
    }
</script>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <?php if ($logo && file_exists(__DIR__.'/assets/img/'.$logo)): ?>
            <img src="assets/img/<?= htmlspecialchars($logo) ?>" style="height:36px;width:auto;object-fit:contain" alt="<?= htmlspecialchars($nama) ?>">
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
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="classes/index.php">Daftar Kelas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">Tentang Kami</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/index.php">Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="user/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-danger btn-sm" href="auth/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary btn-sm me-2" href="auth/login.php">Login</a>
                        <a class="btn btn-primary btn-sm btn-custom text-white" href="auth/register.php">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section text-center mesh-bg py-5 position-relative">
    <!-- Glow Effects -->
    <div class="glow-container">
        <div class="glow-blob" style="background: var(--primary-color); width: 400px; height: 400px; top: -100px; right: -100px;"></div>
        <div class="glow-blob" style="background: var(--secondary-color); width: 500px; height: 500px; bottom: -200px; left: -150px; animation-delay: -5s;"></div>
    </div>
    <div class="container hero-content py-5 position-relative" style="z-index: 2;">
        <div class="reveal-on-scroll">
            <h1 class="hero-title text-white mb-4" data-aos="fade-down" style="font-size: 3.5rem; font-weight: 800;">
                Tingkatkan Karier Anda bersama <br>
                <span class="text-warning"><?= htmlspecialchars($nama) ?></span>
            </h1>
            <p class="hero-subtitle text-white opacity-75 mt-3 mb-5 mx-auto" data-aos="zoom-in" style="max-width: 800px; font-size: 1.1rem;">
                Platform pelatihan profesional terkemuka dengan kurikulum berbasis industri. <br>
                Belajar dari para ahli dan raih sertifikasi kompetensi hari ini.
            </p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a href="classes/index.php" class="btn btn-custom btn-lg rounded-pill shadow-lg">
                    Lihat Kelas Sekarang <i class="fas fa-arrow-right ms-2 small"></i>
                </a>
                <a href="#about" class="btn btn-outline-light btn-lg rounded-pill px-5 fw-bold border-2">
                    Pelajari Selengkapnya
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Features / Benefits Section -->
<section class="py-5 position-relative overflow-hidden">
    <div class="container py-5">
        <div class="text-center mb-5 reveal-on-scroll">
            <h2 class="display-6 fw-bold text-dark">Keunggulan Pelatihan Kami</h2>
            <p class="text-secondary lead">Mengapa <?= htmlspecialchars($nama) ?> adalah pilihan terbaik untuk masa depan Anda?</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4 reveal-on-scroll">
                <div class="modern-card p-5 h-100 border-0 shadow-sm">
                    <div class="icon-box mx-auto">
                        <i class="fas fa-chalkboard-teacher fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Mentor Ahli</h4>
                    <p class="text-secondary">Kurikulum yang disusun dan diajarkan langsung oleh praktisi industri berpengalaman.</p>
                </div>
            </div>
            <div class="col-md-4 reveal-on-scroll">
                <div class="modern-card p-5 h-100 border-0 shadow-sm">
                    <div class="icon-box mx-auto">
                        <i class="fas fa-video fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Sesi Live Interaktif</h4>
                    <p class="text-secondary">Bukan sekadar video rekaman, tapi interaksi langsung melalui zoom meeting terjadwal.</p>
                </div>
            </div>
            <div class="col-md-4 reveal-on-scroll">
                <div class="modern-card p-5 h-100 border-0 shadow-sm">
                    <div class="icon-box mx-auto">
                        <i class="fas fa-award fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-3">Sertifikasi Resmi</h4>
                    <p class="text-secondary">Dapatkan e-certificate yang diakui sebagai bukti kompetensi profesional Anda.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Recent Classes Section -->
<section class="py-5 position-relative">
    <div class="container py-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5 reveal-on-scroll">
            <div class="mb-3 mb-md-0">
                <h2 class="display-6 fw-black mb-0">Kelas Populer</h2>
                <p class="text-muted">Lompatan besar karir dimulai dari sini.</p>
            </div>
            <a href="classes/index.php" class="btn btn-outline-primary rounded-pill px-4 border-2 fw-bold">Lihat Semua <i class="fas fa-arrow-right ms-2"></i></a>
        </div>
        
        <div class="row g-4">
            <?php if (count($recent_classes) > 0): ?>
                <?php foreach($recent_classes as $class): ?>
                <div class="col-md-4 reveal-on-scroll">
                    <div class="modern-card h-100 p-0 overflow-hidden border-0">
                        <div class="card-body d-flex flex-column p-4">
                            <span class="badge bg-primary bg-opacity-10 text-primary extra-small mb-3 align-self-start py-2 px-3"><?= htmlspecialchars($class['kategori'] ?? 'Premium'); ?></span>
                            <h4 class="card-title fw-bold mb-3"><?= htmlspecialchars($class['nama_kelas']); ?></h4>
                            <p class="card-text text-muted mb-4 flex-grow-1" style="font-size: 0.95rem; line-height: 1.6;">
                                <?= mb_strimwidth(htmlspecialchars($class['deskripsi']), 0, 100, '...'); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-4">
                                <span class="small fw-bold"><i class="far fa-calendar-alt me-2 text-primary"></i><?= date('d M', strtotime($class['jadwal'])); ?></span>
                                
                                <div class="text-end">
                                    <?php if($class['harga'] == 0): ?>
                                        <span class="badge bg-success">Gratis</span>
                                    <?php else: ?>
                                        <div class="fw-black text-primary" style="font-size:1.2rem;">Rp <?= number_format($class['harga_spesial'] ?? $class['harga'], 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="classes/detail.php?id=<?= $class['id']; ?>" class="btn btn-primary w-100 rounded-4 py-3 shadow-none fw-bold">Ambil Kelas <i class="fas fa-chevron-right ms-1 small"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted py-5">
                    <p>Segera hadir kelas pelatihan terbaru.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5 overflow-hidden position-relative" style="background: var(--navy-gradient); color: white;">
    <div class="container text-center py-5 position-relative z-index-2" data-aos="zoom-in-up">
        <h2 class="fw-bold mb-3 text-white">Siap Memulai Perjalanan Karier Anda?</h2>
        <p class="mb-4 text-white">Bergabunglah dengan ribuan siswa lainnya yang telah sukses bersama kami.</p>
        <a href="auth/register.php" class="btn btn-primary btn-lg fw-bold px-5 rounded-pill shadow-lg mt-3" data-aos="flip-up" data-aos-delay="200">Daftar Sekarang - Gratis!</a>
    </div>
</section>

<!-- Footer -->
<footer class="footer-custom mb-0 pb-4 pt-5 reveal-on-scroll">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h4 class="fw-bold text-white mb-3"><?= htmlspecialchars($nama) ?></h4>
                <p class="text-muted-light"><?= htmlspecialchars($tagline) ?> — Lembaga pelatihan profesional yang berdedikasi meningkatkan kualitas SDM melalui teknologi dan industri.</p>
            </div>
            <div class="col-md-4">
                <h5 class="fw-bold text-white mb-3 text-gradient">Tautan Cepat</h5>
                <ul class="list-unstyled">
                    <li><a href="index.php" class="text-muted-light text-decoration-none hover-white">Beranda</a></li>
                    <li><a href="classes/index.php" class="text-muted-light text-decoration-none hover-white">Daftar Kelas</a></li>
                    <li><a href="auth/login.php" class="text-muted-light text-decoration-none hover-white">Masuk</a></li>
                    <li><a href="auth/register.php" class="text-muted-light text-decoration-none hover-white">Daftar</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5 class="fw-bold text-white mb-3 text-gradient">Kontak Kami</h5>
                <ul class="list-unstyled text-muted-light">
                    <li><i class="fas fa-map-marker-alt me-2 text-primary"></i> Jl. Pendidikan No. 123, Jakarta</li>
                    <li><i class="fas fa-envelope me-2 text-primary"></i> info@lunarica.com</li>
                    <li><i class="fas fa-phone me-2 text-primary"></i> +62 812 3456 7890</li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-4 mb-4 opacity-10">
        <div class="text-center text-muted-light">
            <small>&copy; <?= date('Y'); ?> <?= htmlspecialchars($nama) ?>. All rights reserved.</small>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<!-- Main Interaction JS -->
<script src="assets/js/main.js?v=<?= time(); ?>"></script>
<script>
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true,
        offset: 50
    });
</script>
</body>
</html>
