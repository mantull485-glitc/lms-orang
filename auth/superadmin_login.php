<?php
session_start();
if (isset($_SESSION['superadmin_id'])) {
    header('Location: ../superadmin/index.php');
    exit;
}
require_once '../config/superadmin_db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo_global->prepare("SELECT * FROM superadmins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['superadmin_id'] = $admin['id'];
        $_SESSION['superadmin_nama'] = $admin['nama'];
        $_SESSION['superadmin_user'] = $admin['username'];
        header('Location: ../superadmin/index.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --orange: #FF6A00;
            --navy: #0F172A;
            --navy-light: #1E293B;
            --border: #1E3A5F;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--navy);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrap {
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }

        .login-card {
            background: var(--navy-light);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
        }

        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 106, 0, .12);
            border: 1px solid rgba(255, 106, 0, .2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto .75rem;
        }

        .brand-icon svg {
            width: 28px;
            height: 28px;
            color: var(--orange);
        }

        .brand h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
        }

        .brand p {
            font-size: .82rem;
            color: #64748B;
        }

        .form-label {
            color: #94A3B8;
            font-size: .83rem;
            font-weight: 500;
        }

        .form-control {
            background: var(--navy);
            border: 1px solid var(--border);
            color: #E2E8F0;
            border-radius: 8px;
            padding: .65rem .9rem;
            font-family: 'Outfit', sans-serif;
        }

        .form-control:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(255, 106, 0, .15);
            background: var(--navy);
            color: #E2E8F0;
        }

        .btn-login {
            background: var(--orange);
            color: #fff;
            border: none;
            width: 100%;
            padding: .75rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: background .2s;
            margin-top: .5rem;
        }

        .btn-login:hover {
            background: #e55c00;
        }

        .alert-err {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .2);
            color: #EF4444;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .88rem;
            margin-bottom: 1rem;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .82rem;
        }

        .back-link a {
            color: #64748B;
            text-decoration: none;
        }

        .back-link a:hover {
            color: var(--orange);
        }
    </style>
</head>

<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="brand">
                <div class="brand-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h1>Super Admin</h1>
                <p>Masuk ke panel kontrol platform</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-err"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required
                        autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password"
                        required>
                </div>
                <button type="submit" class="btn-login">Masuk ke Panel</button>
            </form>

            <div class="back-link">
                <a href="/index.php">← Kembali ke halaman utama</a>
            </div>
        </div>
    </div>
</body>

</html>