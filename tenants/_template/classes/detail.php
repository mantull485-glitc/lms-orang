<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$class_id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

if (!$class) {
    header("Location: index.php");
    exit;
}

// Check if user is already registered for this class
$is_registered = false;
$registration_status = '';
if (isset($_SESSION['user_id'])) {
    $stmt_check = $pdo->prepare("SELECT * FROM registrations WHERE user_id = ? AND class_id = ?");
    $stmt_check->execute([$_SESSION['user_id'], $class_id]);
    $reg = $stmt_check->fetch();
    if ($reg) {
        $is_registered = true;
        $registration_status = $reg['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($class['nama_kelas']); ?> - LPK Lunarica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; }
    </style>
</head>
<body class="mesh-bg dark-theme">

<!-- Cinematic Glow Aura Effects -->
<div class="glow-container">
    <div class="glow-blob" style="background: var(--primary-color); width: 40vw; height: 40vw; top: -10%; left: -10%;"></div>
    <div class="glow-blob" style="background: var(--secondary-color); width: 30vw; height: 30vw; bottom: -10%; right: -5%; animation-delay: -5s;"></div>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">LPK Lunarica</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-toggle="target" aria-controls="navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="index.php">Daftar Kelas</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="../admin/index.php">Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="../user/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-danger btn-sm rounded-pill px-3" href="../auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary btn-sm me-2 rounded-pill px-3" href="../auth/login.php">Login</a>
                        <a class="btn btn-primary btn-sm btn-custom text-white rounded-pill px-3" href="../auth/register.php">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container pt-4 pb-5" style="position: relative; z-index: 10;">
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['flash_error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="mb-4">
        <button type="button" onclick="history.back()" class="btn btn-outline-light rounded-pill py-2 px-4 shadow-sm" style="border-color: rgba(255,255,255,0.15); color: #fff; background: rgba(255,255,255,0.05); transition: 0.3s;">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Kelas
        </button>
    </div>

    <div class="row g-4 g-xl-5 align-items-start">
        <!-- Main Content Box -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="modern-card p-4 p-lg-5 shadow-lg w-100">
                <div class="mb-4 d-inline-block">
                    <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm" style="font-weight: 600; letter-spacing: 0.5px; font-size: 0.85rem;">
                        <i class="fas fa-star me-1 text-warning"></i> Pelatihan Profesional
                    </span>
                </div>
                <h1 class="fw-bolder mb-3 text-white" style="font-size: 2.8rem; letter-spacing: -1px; line-height: 1.2;">
                    <?= htmlspecialchars($class['nama_kelas']); ?>
                </h1>
                
                <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">

                <div class="d-flex align-items-center mb-4">
                    <div class="icon-box me-3 mb-0" style="width: 45px; height: 45px; border-radius: 12px; font-size: 1.2rem;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-0">Deskripsi Kelas</h5>
                </div>
                
                <div class="text-muted-light" style="line-height: 1.8; font-size: 1.1rem;">
                    <?= nl2br(htmlspecialchars($class['deskripsi'])); ?>
                </div>
            </div>
        </div>

        <!-- Sidebar / Registration Form Box -->
        <div class="col-lg-4">
            <div class="modern-card p-4 p-lg-5 shadow-lg w-100">
                <h5 class="fw-bold mb-4 text-white pb-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">Informasi Detail</h5>
                
                <ul class="list-unstyled mb-4">
                    <li class="mb-4 d-flex align-items-start">
                        <div class="icon-box me-3 mb-0" style="width: 45px; height: 45px; border-radius: 12px; font-size: 1.1rem;">
                            <i class="far fa-calendar-alt"></i>
                        </div>
                        <div class="pt-1">
                            <small class="text-muted-light d-block mb-1" style="font-size: 0.85rem;">Tanggal Pelaksanaan</small>
                            <span class="fw-bold text-white d-block" style="font-size: 1.1rem; line-height: 1.2;"><?= date('d F Y', strtotime($class['jadwal'])); ?></span>
                        </div>
                    </li>
                    <li class="mb-4 d-flex align-items-start">
                        <div class="icon-box me-3 mb-0" style="width: 45px; height: 45px; border-radius: 12px; font-size: 1.1rem;">
                            <i class="far fa-clock"></i>
                        </div>
                        <div class="pt-1">
                            <small class="text-muted-light d-block mb-1" style="font-size: 0.85rem;">Waktu Pelaksanaan</small>
                            <span class="fw-bold text-white d-block" style="font-size: 1.1rem; line-height: 1.2;"><?= date('H:i', strtotime($class['jadwal'])); ?> WIB</span>
                        </div>
                    </li>
                    <li class="mb-3 d-flex align-items-start">
                        <div class="icon-box me-3 mb-0" style="width: 45px; height: 45px; border-radius: 12px; background: rgba(25, 135, 84, 0.1); color: #198754; font-size: 1.1rem;">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="pt-1">
                            <small class="text-muted-light d-block mb-1" style="font-size: 0.85rem;">Nilai Investasi</small>
                            <?php if(isset($class['harga']) && $class['harga'] == 0): ?>
                                <span class="badge bg-success px-3 py-2 rounded-pill fs-6 mt-1 shadow-sm d-inline-block">Gratis</span>
                            <?php else: ?>
                                <?php if(isset($class['harga_spesial']) && $class['harga_spesial'] !== null): ?>
                                    <div class="text-decoration-line-through text-muted small mt-1" style="line-height:1.2;">Rp <?= number_format($class['harga'] ?? 0, 0, ',', '.'); ?></div>
                                    <div class="fw-bold text-success fs-4 d-block" style="line-height:1.2; margin-top: 4px;">Rp <?= number_format($class['harga_spesial'], 0, ',', '.'); ?></div>
                                <?php else: ?>
                                    <div class="fw-bold text-success fs-4 d-block mt-1" style="line-height:1.2;">Rp <?= number_format($class['harga'] ?? 0, 0, ',', '.'); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </li>
                </ul>

                <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">

                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="alert py-3 px-3 text-center rounded-4 mb-3" style="background: rgba(13, 202, 240, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #0dcaf0;">
                        <i class="fas fa-info-circle me-2"></i>Silakan login untuk daftar.
                    </div>
                    <a href="../auth/login.php?redirect=../classes/detail.php?id=<?= $class_id ?>" class="btn btn-outline-primary w-100 rounded-pill py-3 fw-bold" style="letter-spacing: 0.5px;">Login untuk Mendaftar</a>
                    
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <div class="alert py-3 text-center rounded-4 mb-0" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.2); color: #ffc107;">
                        <i class="fas fa-user-shield me-2"></i> Mode Admin
                        <div class="small mt-1 text-white-50">(Tidak memiliki akses pelanggan)</div>
                    </div>
                <?php else: ?>
                    <?php if ($is_registered): ?>
                        <?php if($registration_status == 'pending'): ?>
                            <button class="btn btn-warning w-100 rounded-pill py-3 fw-bold shadow-sm text-dark d-flex align-items-center justify-content-center" disabled style="background: #ffc107; border: none; opacity: 1;">
                                <i class="fas fa-hourglass-half me-2"></i>Menunggu Konfirmasi
                            </button>
                        <?php elseif($registration_status == 'diterima'): ?>
                            <button class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center" disabled style="opacity: 1;">
                                <i class="fas fa-check-circle me-2"></i>Pendaftaran Diterima
                            </button>
                            <div class="mt-4 p-4 rounded-4 text-center" style="background: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.2);">
                                <small class="d-block text-success mb-3 fw-bold">Akses Link Pertemuan:</small>
                                <a href="<?= htmlspecialchars($class['link_zoom']); ?>" target="_blank" class="btn btn-success w-100 rounded-pill py-2 shadow-sm fw-semibold">
                                    <i class="fas fa-video me-2"></i> Join Zoom Sekarang
                                </a>
                            </div>
                        <?php else: ?>
                            <button class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center" disabled style="opacity: 1;">
                                <i class="fas fa-times-circle me-2"></i>Pendaftaran Ditolak
                            </button>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <form action="../registrations/process.php" method="POST">
                            <input type="hidden" name="class_id" value="<?= $class['id']; ?>">
                            <div class="mb-4">
                                <label class="form-label text-muted-light small fw-bold mb-2">Nama Pendaftar</label>
                                <div class="position-relative">
                                    <div class="position-absolute top-50 translate-middle-y text-muted d-flex align-items-center justify-content-center" style="left: 15px; width: 24px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <input type="text" class="form-control form-control-modern bg-transparent text-white" value="<?= htmlspecialchars($_SESSION['nama'] ?? 'User'); ?>" readonly disabled style="border-color: rgba(255,255,255,0.15); padding-left: 45px; background: rgba(0,0,0,0.2) !important;">
                                </div>
                            </div>
                            <button type="submit" name="register_class" class="btn btn-custom w-100 py-3 rounded-pill fw-bold" style="font-size: 1.05rem;">
                                Dapatkan Tiket Akses Sekarang
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
