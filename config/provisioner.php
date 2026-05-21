<?php
// ============================================================
// PROVISIONER — Supabase Single-Database Multi-Tenant
// Tidak lagi membuat database baru per tenant.
// Semua tenant menggunakan 1 database Supabase (PostgreSQL)
// dengan pembeda kolom tenant_id di setiap tabel.
// ============================================================

require_once __DIR__ . '/superadmin_db.php';

function provisionTenant(int $order_id, PDO $pdo_global): array {
    // Ambil data order
    $stmt = $pdo_global->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if (!$order) return ['success' => false, 'message' => 'Order tidak ditemukan.'];

    $subdomain = preg_replace('/[^a-z0-9_]/', '', strtolower($order['subdomain_request']));
    if (empty($subdomain)) $subdomain = 'tenant_' . $order_id;

    // Cek subdomain unik
    $check = $pdo_global->prepare("SELECT id FROM tenants WHERE subdomain = ?");
    $check->execute([$subdomain]);
    if ($check->fetch()) $subdomain .= '_' . $order_id;

    // Hitung tanggal expire berdasarkan paket
    $pkg_stmt = $pdo_global->prepare("SELECT * FROM packages WHERE id = ?");
    $pkg_stmt->execute([$order['package_id']]);
    $pkg = $pkg_stmt->fetch();
    $billing_period = '+1 month';
    if ($pkg && !empty($pkg['harga_tahunan']) && ($order['harga_bayar'] ?? 0) >= $pkg['harga_tahunan']) {
        $billing_period = '+1 year';
    }
    $expire = date('Y-m-d', strtotime($billing_period));

    // 1. Buat atau update record tenant
    $existing_tenant = $pdo_global->prepare("SELECT id FROM tenants WHERE email = ? OR subdomain = ?");
    $existing_tenant->execute([$order['email'], $subdomain]);
    $existing = $existing_tenant->fetch();

    if ($existing) {
        $tenant_id = $existing['id'];
        $pdo_global->prepare("UPDATE tenants SET
            nama_lembaga=?, nama_pemilik=?, no_telp=?, package_id=?, subdomain=?,
            db_name=NULL, folder_path=NULL, status='aktif', tanggal_aktif=NOW(), tanggal_expire=?,
            alasan_nonaktif=NULL
            WHERE id=?")
            ->execute([
                $order['nama_lembaga'], $order['nama_pemilik'],
                $order['no_telp'], $order['package_id'], $subdomain,
                $expire, $tenant_id
            ]);
    } else {
        try {
            $pdo_global->prepare("INSERT INTO tenants
                (nama_lembaga, nama_pemilik, email, no_telp, package_id, subdomain, status, tanggal_aktif, tanggal_expire)
                VALUES (?,?,?,?,?,?,?,NOW(),?)")
                ->execute([
                    $order['nama_lembaga'], $order['nama_pemilik'], $order['email'],
                    $order['no_telp'], $order['package_id'], $subdomain,
                    'aktif', $expire
                ]);
            $tenant_id = (int)$pdo_global->lastInsertId();
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Gagal buat record tenant: ' . $e->getMessage()];
        }
    }

    // 2. Seed settings tenant
    $seed_settings = [
        ['nama_lembaga',    $order['nama_lembaga']],
        ['tagline',         'Platform Pelatihan Profesional'],
        ['alamat',          ''],
        ['no_telp',         $order['no_telp'] ?? ''],
        ['email_lembaga',   $order['email']],
        ['website',         ''],
        ['nama_bank',       ''],
        ['no_rekening',     ''],
        ['nama_rekening',   ''],
        ['instruksi_bayar', ''],
        ['logo',            ''],
        ['color_primary',   '#FF6A00'],
        ['color_secondary', '#00D2FF'],
        ['color_navy',      '#0F172A'],
        ['color_navy_light','#1E293B'],
    ];
    $seed_stmt = $pdo_global->prepare(
        "INSERT INTO settings (tenant_id, setting_key, setting_value)
         VALUES (?,?,?)
         ON CONFLICT (tenant_id, setting_key)
         DO UPDATE SET setting_value = CASE
             WHEN settings.setting_value IS NULL OR settings.setting_value = ''
             THEN EXCLUDED.setting_value
             ELSE settings.setting_value
         END"
    );
    foreach ($seed_settings as [$k, $v]) {
        $seed_stmt->execute([$tenant_id, $k, $v]);
    }

    // 3. Buat akun admin awal untuk pemilik LPK
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    try {
        $pdo_global->prepare(
            "INSERT INTO users (tenant_id, nama, email, password, role)
             VALUES (?,?,?,?,'admin')
             ON CONFLICT (tenant_id, email) DO NOTHING"
        )->execute([$tenant_id, $order['nama_pemilik'], $order['email'], $admin_pass]);
    } catch (PDOException $e) {
        // Lanjutkan meski gagal insert user
    }

    // 4. Update order dengan tenant_id dan status diterima
    $pdo_global->prepare("UPDATE orders SET status='diterima', tenant_id=?, tanggal_konfirmasi=NOW() WHERE id=?")
               ->execute([$tenant_id, $order_id]);

    // 5. Log status
    $pdo_global->prepare(
        "INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh)
         VALUES (?,?,?,?,?)"
    )->execute([$tenant_id, 'pending', 'aktif', 'Aktivasi setelah pembayaran diterima', $_SESSION['superadmin_id'] ?? null]);

    return [
        'success'   => true,
        'tenant_id' => $tenant_id,
        'subdomain' => $subdomain,
        'url'       => 'tenants/' . $subdomain . '/',
        'admin_pass'=> 'admin123',
    ];
}

// ──────────────────────────────────────────────
// HELPER FUNCTIONS (kept for local env fallback)
// ──────────────────────────────────────────────

function copyDirectory(string $src, string $dst): void {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) copyDirectory($s, $d);
        else copy($s, $d);
    }
}

// runTenantSchema & runTenantMigrations: tidak diperlukan lagi
// (schema dikelola via config/supabase_schema.sql di Supabase SQL Editor)
function runTenantSchema(PDO $pdo, string $db_name = ''): void { /* no-op */ }
function runTenantMigrations(PDO $pdo): void { /* no-op */ }

function generateTenantConfig(string $host, string $db, string $user, string $pass): string {
    return "<?php\n// Auto-generated - deprecated in Supabase mode\n";
}
function generateStatusCheck(string $subdomain): string {
    return "<?php\ndefine('TENANT_SUBDOMAIN', " . var_export($subdomain, true) . ");\n";
}
