<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
require_once '../config/tenant_settings.php';
$tenant_id = $GLOBALS['tenant_id'] ?? 0;
$brand     = getTenantBranding($pdo);
$nama      = $brand['nama_lembaga'];

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email dan Password wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$email, $tenant_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Credentials match
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama'] = $user['nama'];
            
            // Handle redirect if coming from detail class
            if(isset($_GET['redirect'])) {
                header("Location: " . $_GET['redirect']);
                exit;
            }

            // Standard redirection
            if ($user['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
            exit;
        } else {
            $error = 'Kombinasi Email dan Password tidak valid!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login – <?= htmlspecialchars($nama) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php outputBrandingCSS($brand); ?>
</head>
<body class="dark-theme">

<div class="auth-container mesh-bg position-relative overflow-hidden">
    <!-- Glow Effects -->
    <div class="glow-container">
        <div class="glow-blob" style="background: var(--primary-color); width: 400px; height: 400px; top: -100px; right: -100px;"></div>
        <div class="glow-blob" style="background: var(--secondary-color); width: 500px; height: 500px; bottom: -200px; left: -150px; animation-delay: -5s;"></div>
    </div>

    <div class="container position-relative z-index-2">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['flash_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error; ?></div>
                <?php endif; ?>

                <div class="modern-card p-2 p-md-4 border-0 shadow-lg" style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);">
                    <div class="text-center mb-4 mt-2">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-rocket text-primary fs-4"></i>
                        </div>
                        <h3 class="fw-black text-white mb-1">Selamat Datang</h3>
                        <p class="text-muted-light small mb-0">Login untuk mengakses dasbor Anda</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="name@example.com" required>
                                </div>
                            </div>
                            <div class="mb-5">
                                <label class="form-label text-muted-light small fw-bold text-uppercase ls-1">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-muted-light border-secondary border-opacity-25 border-end-0"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control bg-dark border-secondary border-opacity-25 border-start-0 text-white ps-0 shadow-none" placeholder="••••••••" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold mb-4 shadow-lg" style="letter-spacing: 0.5px;">MASUK SEKARANG</button>
                        </form>
                        
                        <div class="text-center mt-3 pt-4 border-top border-secondary border-opacity-25">
                            <p class="text-muted-light small mb-3">Belum memiliki akun? <a href="register.php" class="text-decoration-none fw-bold text-primary ms-1">Daftar Akun Baru</a></p>
                            <button type="button" onclick="history.back()" class="btn btn-dark w-100 rounded-pill shadow-sm small py-2 border-secondary border-opacity-25 text-muted-light"><i class="fas fa-arrow-left me-2"></i>Kembali ke Halaman Utama</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
