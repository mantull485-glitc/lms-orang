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
        $_SESSION['superadmin_id']   = $admin['id'];
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
    <title>Login Super Admin — Platform LPK</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --orange: #FF6A00; --navy: #0B1120; --navy-card: #161f30; --border: rgba(255,255,255,0.08); --text: #E2E8F0; --text-muted: #94A3B8; }
        body { font-family:'Outfit',sans-serif; background:var(--navy); min-height:100vh; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative; }
        .orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
        .o1 { width:500px;height:500px;background:rgba(255,106,0,0.07);top:-150px;right:-100px;animation:f 14s ease-in-out infinite alternate; }
        .o2 { width:400px;height:400px;background:rgba(0,210,255,0.05);bottom:-100px;left:-100px;animation:f 18s ease-in-out infinite alternate-reverse; }
        .o3 { width:300px;height:300px;background:rgba(139,92,246,0.04);top:50%;left:50%;transform:translate(-50%,-50%);animation:f 10s ease-in-out infinite alternate; }
        @keyframes f { from{transform:translate(0,0) scale(1)} to{transform:translate(20px,-20px) scale(1.05)} }
        .grid { position:fixed;inset:0;z-index:0;opacity:.02;background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:50px 50px; }
        .wrap { position:relative;z-index:1;width:100%;max-width:420px;padding:1.25rem;animation:up .5s ease both; }
        @keyframes up { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .card { background:rgba(22,31,48,0.88);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border);border-radius:24px;padding:2.5rem;box-shadow:0 25px 60px rgba(0,0,0,0.5); }
        .brand { text-align:center;margin-bottom:2rem; }
        .icon { width:60px;height:60px;background:rgba(255,106,0,.12);border:1px solid rgba(255,106,0,.25);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;box-shadow:0 0 30px rgba(255,106,0,.1); }
        .icon svg { width:30px;height:30px;color:var(--orange); }
        h1 { font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-.3px; }
        .sub { font-size:.82rem;color:var(--text-muted);margin-top:4px; }
        .lbl { display:block;color:var(--text-muted);font-size:.82rem;font-weight:600;margin-bottom:.4rem; }
        .fg { margin-bottom:1.1rem; }
        input { width:100%;background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:.7rem 1rem;font-size:.93rem;font-family:'Outfit',sans-serif;outline:none;transition:all .22s; }
        input:focus { border-color:var(--orange);box-shadow:0 0 0 3px rgba(255,106,0,.14);background:rgba(255,255,255,.09); }
        input::placeholder { color:#475569; }
        input:-webkit-autofill { -webkit-box-shadow:0 0 0 40px #161f30 inset;-webkit-text-fill-color:var(--text); }
        .btn { background:linear-gradient(135deg,#FF6A00,#FF8800);color:#fff;border:none;width:100%;padding:.8rem;border-radius:10px;font-weight:700;font-size:.98rem;font-family:'Outfit',sans-serif;cursor:pointer;transition:all .22s;margin-top:.25rem;box-shadow:0 4px 20px rgba(255,106,0,.3); }
        .btn:hover { transform:translateY(-2px);box-shadow:0 8px 28px rgba(255,106,0,.45); }
        .btn:active { transform:translateY(0); }
        .err { background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-left:3px solid #EF4444;color:#EF4444;border-radius:10px;padding:.75rem 1rem;font-size:.875rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px; }
        .back { text-align:center;margin-top:1.5rem;font-size:.8rem; }
        .back a { color:#475569;text-decoration:none;transition:color .2s; }
        .back a:hover { color:var(--orange); }
        @media(max-width:480px){.card{padding:1.75rem 1.35rem;border-radius:20px}}
    </style>
</head>
<body>
<div class="orb o1"></div>
<div class="orb o2"></div>
<div class="orb o3"></div>
<div class="grid"></div>
<div class="wrap">
    <div class="card">
        <div class="brand">
            <div class="icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h1>Super Admin</h1>
            <p class="sub">Masuk ke panel kontrol platform</p>
        </div>
        <?php if ($error): ?>
        <div class="err">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <form method="POST">
            <div class="fg">
                <label class="lbl" for="un">Username</label>
                <input type="text" id="un" name="username" placeholder="Masukkan username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="fg">
                <label class="lbl" for="pw">Password</label>
                <input type="password" id="pw" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn">Masuk ke Panel Admin</button>
        </form>
        <div class="back"><a href="/index.php">← Kembali ke halaman utama</a></div>
    </div>
</div>
</body>
</html>