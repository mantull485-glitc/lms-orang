<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($nama) || empty($email) || empty($no_hp) || empty($password)) {
        $error = 'Semua field wajib diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan Konfirmasi Password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Check if email already exists
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) {
            $error = 'Email sudah terdaftar!';
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (nama, email, no_hp, password, role) VALUES (?, ?, ?, ?, 'user')");
                $stmt->execute([$nama, $email, $no_hp, $hashed_password]);
                $_SESSION['flash_message'] = 'Pendaftaran berhasil! Silakan login.';
                header("Location: login.php");
                exit;
            } catch (PDOException $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - LPK Lunarica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dark-theme">

<div class="auth-container mesh-bg position-relative overflow-hidden">
    <!-- Glow Effects -->
    <div class="glow-container">
        <div class="glow-blob" style="background: var(--primary-color); width: 400px; height: 400px; top: -100px; right: -100px;"></div>
        <div class="glow-blob" style="background: var(--secondary-color); width: 500px; height: 500px; bottom: -200px; left: -150px; animation-delay: -5s;"></div>
    </div>

    <div class="container position-relative z-index-2 py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="modern-card p-2 p-md-4 border-0 shadow-lg" style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
                    <div class="text-center mb-4 mt-2">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-user-plus text-primary fs-4"></i>
                        </div>
                        <h3 class="fw-black text-white mb-1">Buat Akun</h3>
                        <p class="text-muted-light small mb-0">Bergabung dengan platform pelatihan LPK Lunarica</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 small"><i class="fas fa-exclamation-circle me-2"></i><?= $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">Nama Lengkap</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-user"></i></span>
                                    <input type="text" name="nama" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">Email Valid</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="name@example.com" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">No. Handphone (WA)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="no_hp" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" placeholder="08..." required>
                                </div>
                            </div>
                            <div class="row g-3 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" required minlength="6">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">Konfirmasi</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-check-circle"></i></span>
                                        <input type="password" name="confirm_password" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" required minlength="6">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold mb-4 shadow-lg" style="letter-spacing: 0.5px;">DAFTAR SEKARANG</button>
                        </form>
                        
                        <div class="text-center mt-3 pt-4 border-top border-secondary border-opacity-25">
                            <p class="text-muted-light small mb-3">Sudah memiliki akun? <a href="login.php" class="text-decoration-none fw-bold text-primary ms-1">Login di sini</a></p>
                            <button type="button" onclick="history.back()" class="btn btn-dark w-100 rounded-pill shadow-sm small py-2 border-secondary border-opacity-25 text-muted-light"><i class="fas fa-arrow-left me-2"></i>Kembali ke Halaman Utama</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
