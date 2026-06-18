<?php
$current_page = basename($_SERVER['PHP_SELF']);
function sa_active(string $page): string {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<aside class="sa-sidebar" id="saSidebar">
    <div class="sa-sidebar-brand">
        <div class="sa-brand-logo">
            <div class="brand-icon">
                <svg fill="none" stroke="#FF6A00" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 14l5 5 9-9"/></svg>
            </div>
            Platform<span class="dot">.</span>Admin
        </div>
        <div class="sa-brand-sub">Super Administrator</div>
    </div>

    <nav class="sa-nav">
        <div class="sa-nav-label">Utama</div>
        <a href="index.php" class="<?= sa_active('index.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>

        <div class="sa-nav-label">Manajemen</div>
        <a href="tenants.php" class="<?= sa_active('tenants.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            Tenant / LPK
        </a>
        <a href="packages.php" class="<?= sa_active('packages.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Paket &amp; Harga
        </a>
        <a href="orders.php" class="<?= sa_active('orders.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Semua Order
        </a>

        <div class="sa-nav-label">Keuangan</div>
        <a href="finance.php" class="<?= sa_active('finance.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Verifikasi Bayar
        </a>
        <a href="reports.php" class="<?= sa_active('reports.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Laporan &amp; Analitik
        </a>

        <div class="sa-nav-label">Sistem</div>
        <a href="settings.php" class="<?= sa_active('settings.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Pengaturan Akun
        </a>
        <a href="migrate_tenants.php" class="<?= sa_active('migrate_tenants.php') ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
            Migrasi DB Tenant
        </a>
    </nav>

    <div class="sa-sidebar-footer">
        <div class="sa-user-card">
            <div class="sa-user-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
            <div>
                <div class="sa-user-name"><?= htmlspecialchars($_SESSION['superadmin_nama'] ?? 'Super Admin') ?></div>
                <div class="sa-user-role">Super Administrator</div>
            </div>
        </div>
        <a href="../auth/superadmin_logout.php" class="sa-logout-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Logout
        </a>
    </div>
</aside>

<!-- Sidebar overlay (mobile backdrop) -->
<div class="sa-overlay" id="saOverlay" onclick="saCloseSidebar()"></div>

<!-- Mobile top bar (visible ≤ 768px) -->
<div class="sa-mobile-topbar" id="saMobileTopbar">
    <button class="sa-hamburger" onclick="saToggleSidebar()" aria-label="Buka menu">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <div class="sa-mobile-brand">
        Platform<span class="dot">.</span>Admin
    </div>
    <div class="sa-avatar" style="cursor:default">
        <?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?>
    </div>
</div>

<script>
function saToggleSidebar() {
    var sidebar = document.getElementById('saSidebar');
    var overlay = document.getElementById('saOverlay');
    var isOpen  = sidebar.classList.toggle('open');
    overlay.classList.toggle('show', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}
function saCloseSidebar() {
    document.getElementById('saSidebar').classList.remove('open');
    document.getElementById('saOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
document.querySelectorAll('.sa-nav a').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) saCloseSidebar();
    });
});
</script>
