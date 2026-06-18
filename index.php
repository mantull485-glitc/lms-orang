<?php
require_once 'config/superadmin_db.php';
$packages = $pdo_global->query("SELECT * FROM packages WHERE status='aktif' ORDER BY harga ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Platform LPK — Kelola Pelatihan Lebih Mudah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
    
    <!-- PWA Setup -->
    <link rel="manifest" href="manifest.json?v=2">
    <meta name="theme-color" content="#0F172A">
    <link rel="apple-touch-icon" href="assets/logo/logolpk.png?v=2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>

<!-- Floating Spinner Loading -->
<div id="floating-loader">
    <div class="spinner-ring"></div>
</div>
<style>
    #floating-loader {
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        z-index: 999999; display: flex; justify-content: center; align-items: center;
        width: 64px; height: 64px;
        background: rgba(11, 17, 32, 0.75); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5), 0 0 20px rgba(255,106,0,0.15);
        opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
    }
    .spinner-ring {
        width: 32px; height: 32px;
        border: 3px solid rgba(255, 106, 0, 0.2); border-top-color: #FF6A00;
        border-radius: 50%; animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const loader = document.getElementById('floating-loader');
        
        // Munculkan saat pertama load halaman
        loader.style.opacity = '1';
        window.addEventListener('load', () => {
            setTimeout(() => {
                loader.style.opacity = '0';
            }, 300);
        });

        // Animasi saat klik link pindah halaman
        document.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', e => {
                const href = a.getAttribute('href');
                if (href && !href.startsWith('#') && !href.startsWith('javascript:') && a.target !== '_blank') {
                    e.preventDefault();
                    if (loader) {
                        loader.style.opacity = '1';
                        setTimeout(() => window.location.href = href, 300);
                    } else {
                        window.location.href = href;
                    }
                }
            });
        });
    });
</script>

<!-- PWA Service Worker Registration -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Platform SW Registered'))
                .catch(err => console.log('Platform SW Registration Failed:', err));
        });
    }
</script>

<!-- NAVBAR -->
<nav class="sales-nav" id="salesNav">
    <div class="container">
        <a class="nav-brand" href="#">Platform<span>.</span>LPK</a>
        <ul class="nav-links">
            <li><a href="#fitur">Fitur</a></li>
            <li><a href="#cara-kerja">Cara Kerja</a></li>
            <li><a href="#harga">Harga</a></li>
        </ul>
        <div class="nav-actions">
            <a href="auth/superadmin_login.php" class="btn-nav-outline">Login</a>
            <a href="pricing.php" class="btn-nav">Mulai Sekarang</a>
            <button class="nav-hamburger" id="navHamburger" aria-label="Menu" onclick="toggleMobileNav()">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</nav>
<!-- Mobile Nav Drawer -->
<div class="nav-mobile-drawer" id="navMobileDrawer">
    <ul>
        <li><a href="#fitur" onclick="closeMobileNav()">Fitur</a></li>
        <li><a href="#cara-kerja" onclick="closeMobileNav()">Cara Kerja</a></li>
        <li><a href="#harga" onclick="closeMobileNav()">Harga</a></li>
    </ul>
    <div class="mobile-nav-cta">
        <a href="auth/superadmin_login.php" class="btn-nav-outline" style="padding:.65rem 1rem">Login</a>
        <a href="pricing.php" class="btn-nav" style="padding:.65rem 1rem">Mulai Sekarang</a>
    </div>
</div>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-grid"></div>
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
    <div class="container hero-content">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-badge">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Platform Manajemen LPK #1 di Indonesia
                </div>
                <h1>Kelola LPK Anda <span class="highlight">Lebih Cerdas</span> dan Efisien</h1>
                <p>Satu platform lengkap untuk mengelola kelas, siswa, pembayaran, dan sertifikat digital. Mulai dalam hitungan menit.</p>
                <div class="hero-cta">
                    <a href="pricing.php" class="btn-primary-sales">
                        Pilih Paket
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                    <a href="#cara-kerja" class="btn-outline-sales">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Lihat Demo
                    </a>
                </div>
                <div class="hero-stats">
                    <div><div class="hero-stat-val">200+</div><div class="hero-stat-label">LPK Aktif</div></div>
                    <div><div class="hero-stat-val">15K+</div><div class="hero-stat-label">Siswa Terdaftar</div></div>
                    <div><div class="hero-stat-val">99.9%</div><div class="hero-stat-label">Uptime</div></div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <!-- Dashboard Preview Card -->
                <div class="hero-preview-wrap"><div class="hero-preview">
                    <div style="display:flex;gap:6px;margin-bottom:1rem">
                        <span style="width:10px;height:10px;border-radius:50%;background:#EF4444;display:block"></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:#F59E0B;display:block"></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:#10B981;display:block"></span>
                    </div>
                    <!-- Mini dashboard preview -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
                        <?php
                        $preview_stats = [
                            ['12', 'Kelas Aktif', '#FF6A00'],
                            ['148', 'Total Siswa', '#00D2FF'],
                            ['Rp 24jt', 'Pendapatan', '#10B981'],
                            ['8', 'Pending', '#F59E0B'],
                        ];
                        foreach ($preview_stats as [$val, $label, $color]):
                        ?>
                        <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:.9rem">
                            <div style="font-size:1.3rem;font-weight:800;color:<?= $color ?>"><?= $val ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:.9rem">
                        <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.5rem">Pendaftaran Terbaru</div>
                        <?php foreach ([['Ahmad R.','Web Development','pending'],['Siti N.','Digital Marketing','diterima'],['Budi S.','UI/UX Design','selesai']] as [$name,$kelas,$st]): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:.35rem 0;border-bottom:1px solid rgba(255,255,255,.05)">
                            <div>
                                <div style="font-size:.82rem;color:#fff;font-weight:600"><?= $name ?></div>
                                <div style="font-size:.72rem;color:var(--text-muted)"><?= $kelas ?></div>
                            </div>
                            <span style="font-size:.7rem;padding:2px 8px;border-radius:20px;background:<?= $st==='diterima'?'rgba(16,185,129,.15)':($st==='pending'?'rgba(245,158,11,.15)':'rgba(59,130,246,.15)') ?>;color:<?= $st==='diterima'?'#10B981':($st==='pending'?'#F59E0B':'#3B82F6') ?>"><?= ucfirst($st) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</section>

<!-- FITUR -->
<section id="fitur" class="features-bg">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-badge">Fitur Unggulan</div>
            <h2 class="section-title">Semua yang Anda Butuhkan</h2>
            <p class="section-sub">Platform lengkap dirancang khusus untuk LPK dan lembaga pelatihan profesional di Indonesia.</p>
        </div>
        <div class="row g-3">
            <?php
            $features = [
                ['📚', 'Manajemen Kelas', 'Buat dan kelola kelas pelatihan dengan mudah. Atur jadwal, kapasitas, dan harga per kelas.', 'rgba(255,106,0,.1)', '#FF6A00'],
                ['👥', 'Manajemen Siswa', 'Daftarkan dan pantau progres setiap siswa. Histori lengkap dari pendaftaran hingga kelulusan.', 'rgba(0,210,255,.1)', '#00D2FF'],
                ['💳', 'Pembayaran Digital', 'Konfirmasi pembayaran dengan mudah. Siswa upload bukti bayar, admin verifikasi dalam satu klik.', 'rgba(16,185,129,.1)', '#10B981'],
                ['🎓', 'Sertifikat Digital', 'Generate sertifikat digital otomatis untuk siswa yang telah menyelesaikan pelatihan.', 'rgba(139,92,246,.1)', '#8B5CF6'],
                ['📹', 'Integrasi Zoom', 'Link Zoom kelas terintegrasi langsung di platform. Siswa akses dari dashboard mereka sendiri.', 'rgba(245,158,11,.1)', '#F59E0B'],
                ['📊', 'Laporan Keuangan', 'Laporan pendapatan dan keuangan lengkap. Pantau performa finansial LPK Anda secara real-time.', 'rgba(239,68,68,.1)', '#EF4444'],
            ];
            foreach ($features as $i => [$icon, $title, $desc, $bg, $color]):
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card reveal reveal-delay-<?= ($i % 3) + 1 ?>">
                    <div class="feature-icon" style="background:<?= $bg ?>;font-size:1.5rem"><?= $icon ?></div>
                    <div class="feature-title"><?= $title ?></div>
                    <div class="feature-desc"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CARA KERJA -->
<section id="cara-kerja">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-badge">Cara Kerja</div>
            <h2 class="section-title">Mulai dalam 3 Langkah</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $steps = [
                ['1', 'Pilih Paket', 'Pilih paket yang sesuai kebutuhan LPK Anda dan lakukan pembayaran.'],
                ['2', 'Aktivasi Instan', 'Tim kami memverifikasi pembayaran dan mengaktifkan platform Anda dalam 1x24 jam.'],
                ['3', 'Langsung Pakai', 'Login ke panel admin Anda dan mulai tambahkan kelas, siswa, dan kelola pelatihan.'],
            ];
            foreach ($steps as $i => [$num, $title, $desc]):
            ?>
            <div class="col-md-4">
                <div class="text-center p-4 reveal reveal-delay-<?= $i + 1 ?>">
                    <div class="step-num mx-auto mb-3"><?= $num ?></div>
                    <h5 style="color:#fff;font-weight:700;margin-bottom:.5rem"><?= $title ?></h5>
                    <p style="color:var(--text-muted);font-size:.9rem;line-height:1.65"><?= $desc ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- HARGA -->
<section id="harga" style="background:var(--navy-light);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-badge">Harga</div>
            <h2 class="section-title">Pilihan Paket Fleksibel</h2>
            <p class="section-sub">Mulai gratis, upgrade kapan saja. Tidak ada biaya tersembunyi.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($packages as $pkg):
                $fitur = json_decode($pkg['fitur'] ?? '[]', true) ?: [];
                $is_popular = $pkg['is_popular'];
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="pricing-card <?= $is_popular ? 'popular' : '' ?>">
                    <?php if ($is_popular): ?><div class="popular-tag">★ POPULER</div><?php endif; ?>
                    <div class="pricing-name"><?= htmlspecialchars($pkg['nama']) ?></div>
                    <div class="pricing-price">
                        Rp <?= number_format($pkg['harga'], 0, ',', '.') ?>
                        <span>/bulan</span>
                    </div>
                    <?php if ($pkg['harga_tahunan']): ?>
                    <div class="pricing-yearly">atau Rp <?= number_format($pkg['harga_tahunan'], 0, ',', '.') ?>/tahun
                        <span style="color:#10B981;font-size:.78rem">(hemat <?= round((1 - $pkg['harga_tahunan'] / ($pkg['harga'] * 12)) * 100) ?>%)</span>
                    </div>
                    <?php else: ?><div class="pricing-yearly">&nbsp;</div><?php endif; ?>
                    <ul class="pricing-features">
                        <?php foreach ($fitur as $f): ?>
                        <li><?= htmlspecialchars($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="checkout.php?paket=<?= $pkg['id'] ?>" class="btn-pricing <?= $is_popular ? 'btn-pricing-primary' : 'btn-pricing-outline' ?>">
                        Pilih <?= htmlspecialchars($pkg['nama']) ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4" style="color:var(--text-muted);font-size:.88rem">
            Butuh paket khusus atau volume besar? <a href="mailto:support@platform.com" style="color:var(--orange)">Hubungi kami</a>
        </div>
    </div>
</section>

<!-- CTA -->
<section>
    <div class="container">
        <div class="cta-box reveal">
            <h2 style="font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;color:#fff;margin-bottom:1rem;letter-spacing:-.5px">Siap Digitalisasi LPK Anda?</h2>
            <p style="color:var(--text-muted);margin-bottom:2rem;max-width:480px;margin-left:auto;margin-right:auto;line-height:1.7">Bergabung dengan ratusan LPK yang sudah menggunakan platform kami. Aktivasi cepat, support responsif.</p>
            <a href="pricing.php" class="btn-primary-sales" style="font-size:1rem;padding:.9rem 2.5rem">
                Mulai Sekarang &mdash; Gratis Konsultasi
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="sales-footer">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="nav-brand mb-2">Platform<span>.</span>LPK</div>
                <p style="color:var(--text-muted);font-size:.88rem;line-height:1.6">Platform manajemen LPK terpercaya untuk lembaga pelatihan profesional di Indonesia.</p>
            </div>
            <div class="col-md-4">
                <div style="font-weight:600;color:#fff;margin-bottom:.75rem">Navigasi</div>
                <ul style="list-style:none;display:flex;flex-direction:column;gap:.4rem;padding-left:0">
                    <li><a href="#fitur" style="color:var(--text-muted);text-decoration:none;font-size:.88rem">Fitur</a></li>
                    <li><a href="#harga" style="color:var(--text-muted);text-decoration:none;font-size:.88rem">Harga</a></li>
                    <li><a href="checkout.php" style="color:var(--text-muted);text-decoration:none;font-size:.88rem">Beli Sekarang</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <div style="font-weight:600;color:#fff;margin-bottom:.75rem">Kontak</div>
                <div style="color:var(--text-muted);font-size:.88rem;line-height:1.8">
                    Email: lpk_lunarica@gmail.com<br>
                    WhatsApp: 081524765812<br>
                    Senin – Jumat, 08.00 – 16.00 WIB
                </div>
            </div>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:1.5rem;text-align:center;color:var(--text-muted);font-size:.82rem">
            © <?= date('Y') ?> Platform LPK. All rights reserved.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        const el = document.querySelector(a.getAttribute('href'));
        if (el) el.scrollIntoView({ behavior: 'smooth' });
    });
});

// Navbar scroll effect
const salesNav = document.getElementById('salesNav');
window.addEventListener('scroll', () => {
    salesNav.classList.toggle('scrolled', window.scrollY > 20);
});

// Mobile nav toggle
function toggleMobileNav() {
    const drawer = document.getElementById('navMobileDrawer');
    const btn = document.getElementById('navHamburger');
    const isOpen = drawer.classList.toggle('open');
    btn.classList.toggle('open', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}
function closeMobileNav() {
    document.getElementById('navMobileDrawer').classList.remove('open');
    document.getElementById('navHamburger').classList.remove('open');
    document.body.style.overflow = '';
}

// Scroll reveal
const revealEls = document.querySelectorAll('.reveal');
const revealObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: 0.12 });
revealEls.forEach(el => revealObs.observe(el));
</script>
</body>
</html>
