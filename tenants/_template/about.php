<?php
session_start();
require_once 'config/database.php';
require_once 'config/tenant_settings.php';

$brand = getTenantBranding($pdo);
$nama  = $brand['nama_lembaga'];
$logo  = $brand['logo'];

$stmt = $pdo->prepare("SELECT *, bio AS deskripsi FROM team WHERE tenant_id = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$GLOBALS['tenant_id'] ?? 0]);
$teams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - <?= htmlspecialchars($nama) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <?php outputBrandingCSS($brand); ?>
    <style>
        .section-premium {
            min-height: 50vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 120px !important;
            padding-bottom: 60px !important;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .team-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), border-color 0.3s;
        }
        .modern-card:hover .team-img {
            transform: scale(1.05) translateY(-5px);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body class="dark-theme">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <?php if ($logo && file_exists(__DIR__.'/assets/img/'.$logo)): ?>
            <img src="assets/img/<?= htmlspecialchars($logo) ?>" style="height:36px;width:auto;object-fit:contain" alt="<?= htmlspecialchars($nama) ?>">
            <?php else: ?>
            <div class="bg-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="fas fa-graduation-cap text-white fs-6"></i>
            </div>
            <span><?= htmlspecialchars($nama) ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="classes/index.php">Daftar Kelas</a></li>
                <li class="nav-item"><a class="nav-link active" href="about.php">Tentang Kami</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="admin/index.php">Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="user/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-danger btn-sm" href="auth/logout.php">Logout</a></li>
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

<!-- Premium Header with Glow Effects -->
<header class="mesh-bg text-white section-premium position-relative overflow-hidden">
    <!-- Glow Effects -->
    <div class="glow-container">
        <div class="glow-blob" style="background: var(--primary-color); width: 450px; height: 450px; top: -150px; right: 5%;"></div>
        <div class="glow-blob" style="background: var(--secondary-color); width: 500px; height: 500px; bottom: -200px; left: -10%;"></div>
    </div>
    <div class="container text-center position-relative z-index-2" data-aos="zoom-in" data-aos-duration="1000">
        <div class="badge bg-white bg-opacity-10 text-white px-3 py-2 rounded-pill mb-4 border border-white border-opacity-10" style="letter-spacing: 2px;">MENGENAL KAMI LEBIH DEKAT</div>
        <h1 class="display-4 fw-black mb-4 text-white" style="letter-spacing: -1px;">Tentang <?= htmlspecialchars($nama) ?></h1>
        <p class="lead text-muted-light mb-0 mx-auto" style="max-width: 800px; line-height: 1.8;">
            Kami adalah lembaga pelatihan profesional yang didedikasikan untuk membangun generasi ahli terkemuka yang siap bersaing di industri global.
        </p>
    </div>
</header>

<!-- Team Section -->
<div class="container py-5 my-5">
    
    <div class="row mb-5 justify-content-center text-center reveal-on-scroll">
        <div class="col-lg-6">
            <h2 class="fw-bold text-white mb-3">Tim di Balik Layar</h2>
            <p class="text-muted-light">Mengenal para profesional berdedikasi yang siap mendampingi kesuksesan belajar Anda di platform ini.</p>
        </div>
    </div>

    <div class="row g-5 justify-content-center">
        <?php if (count($teams) > 0): ?>
            <?php foreach($teams as $member): ?>
            <div class="col-sm-6 col-lg-4 reveal-on-scroll">
                <div class="modern-card h-100 p-0 border-0 shadow-lg text-center" style="background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px);">
                    <div class="card-body p-5 d-flex flex-column align-items-center">
                        <?php if(!empty($member['foto']) && file_exists($member['foto'])): ?>
                            <img src="<?= htmlspecialchars($member['foto']); ?>" alt="<?= htmlspecialchars($member['nama']); ?>" class="team-img">
                        <?php else: ?>
                            <div class="team-img d-flex align-items-center justify-content-center bg-primary bg-opacity-10 mx-auto">
                                <i class="fas fa-user-tie text-primary" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h4 class="fw-bold mb-1 text-white"><?= htmlspecialchars($member['nama']); ?></h4>
                        <div class="badge bg-primary bg-opacity-25 text-primary px-3 py-1 rounded-pill small fw-bold mb-3 border border-primary border-opacity-25">
                            <?= htmlspecialchars($member['jabatan']); ?>
                        </div>
                        
                        <?php if(!empty($member['deskripsi'])): ?>
                            <p class="text-muted-light small mb-0 mt-2" style="line-height: 1.7;">
                                "<?= htmlspecialchars($member['deskripsi']); ?>"
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 reveal-on-scroll">
                <div class="modern-card p-5 d-inline-block text-center border-0 shadow-lg">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                        <i class="fas fa-users-slash fa-2x text-primary opacity-75"></i>
                    </div>
                    <h4 class="text-white fw-bold mb-2">Profil Anggota Tim Masih Kosong</h4>
                    <p class="text-muted-light mb-0">Admin belum mempublikasikan informasi anggota tim. Silakan akses halaman Administrator untuk menambahkan data.</p>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin/team.php" class="btn btn-outline-primary rounded-pill mt-4 px-4"><i class="fas fa-plus me-2"></i>Tambah Anggota</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<footer class="footer-custom pb-4 pt-5 mt-auto border-top border-light border-opacity-10">
    <div class="container text-center text-muted-light">
        <p class="mb-0">&copy; <?= date('Y'); ?> <?= htmlspecialchars($nama) ?>. All rights reserved.</p>
    </div>
</footer>

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
