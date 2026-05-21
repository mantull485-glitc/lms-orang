<?php
// ============================================================
// PROVISIONER - Aktivasi Tenant Baru
// Dipanggil oleh superadmin/finance.php saat approve order
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

    $db_name   = 'tenant_' . $order_id . '_db';
    $base_path = dirname(__DIR__);
    $tenant_folder = $base_path . '/tenants/' . $subdomain;

    // 1. Buat folder tenant dari template
    if (!is_dir($tenant_folder)) {
        copyDirectory($base_path . '/tenants/_template', $tenant_folder);
    }

    // 2. Buat database tenant
    try {
        $pdo_raw = new PDO(
            'mysql:host=' . SA_DB_HOST . ';charset=utf8mb4',
            SA_DB_USER, SA_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo_raw->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo_raw->exec("USE `{$db_name}`");

        // Jalankan schema tenant
        runTenantSchema($pdo_raw, $db_name);
        // Jalankan migrasi (patch kolom yang mungkin kurang)
        runTenantMigrations($pdo_raw);

        // Seed settings dengan data dari order (nama, email, kontak, warna default)
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
        $seed_stmt = $pdo_raw->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = IF(setting_value='' OR setting_value IS NULL, VALUES(setting_value), setting_value)");
        foreach ($seed_settings as [$k, $v]) {
            $seed_stmt->execute([$k, $v]);
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Gagal buat DB: ' . $e->getMessage()];
    }

    // 3. Tulis config/database.php tenant
    $config_content = generateTenantConfig(SA_DB_HOST, $db_name, SA_DB_USER, SA_DB_PASS);
    file_put_contents($tenant_folder . '/config/database.php', $config_content);

    // 4. Tulis config status_check.php (untuk cek status dari superadmin)
    $status_content = generateStatusCheck($subdomain);
    file_put_contents($tenant_folder . '/config/status_check.php', $status_content);

    // 5. Buat akun admin awal untuk pemilik LPK
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    try {
        $pdo_tenant = new PDO(
            "mysql:host=" . SA_DB_HOST . ";dbname={$db_name};charset=utf8mb4",
            SA_DB_USER, SA_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo_tenant->prepare("INSERT IGNORE INTO users (nama, email, password, role) VALUES (?, ?, ?, 'admin')")
                   ->execute([$order['nama_pemilik'], $order['email'], $admin_pass]);
    } catch (PDOException $e) {
        // Lanjutkan meski gagal insert user
    }

    // 6. Hitung tanggal expire berdasarkan paket (bulanan/tahunan)
    $pkg_stmt = $pdo_global->prepare("SELECT * FROM packages WHERE id = ?");
    $pkg_stmt->execute([$order['package_id']]);
    $pkg = $pkg_stmt->fetch();
    // Cek apakah bayar harga tahunan
    $billing_period = '+1 month';
    if ($pkg && !empty($pkg['harga_tahunan']) && $order['harga_bayar'] >= $pkg['harga_tahunan']) {
        $billing_period = '+1 year';
    }
    $expire = date('Y-m-d', strtotime($billing_period));

    // 7. Buat atau update record tenant (handle duplikat email)
    $existing_tenant = $pdo_global->prepare("SELECT id FROM tenants WHERE email = ? OR subdomain = ?");
    $existing_tenant->execute([$order['email'], $subdomain]);
    $existing = $existing_tenant->fetch();

    if ($existing) {
        // Update tenant lama yang sudah ada
        $tenant_id = $existing['id'];
        $pdo_global->prepare("UPDATE tenants SET 
            nama_lembaga=?, nama_pemilik=?, no_telp=?, package_id=?, subdomain=?,
            db_name=?, folder_path=?, status='aktif', tanggal_aktif=?, tanggal_expire=?,
            alasan_nonaktif=NULL
            WHERE id=?")
            ->execute([
                $order['nama_lembaga'], $order['nama_pemilik'],
                $order['no_telp'], $order['package_id'], $subdomain,
                $db_name, 'tenants/' . $subdomain, date('Y-m-d'), $expire,
                $tenant_id
            ]);
    } else {
        // Insert tenant baru
        try {
            $pdo_global->prepare("INSERT INTO tenants 
                (nama_lembaga, nama_pemilik, email, no_telp, package_id, subdomain, db_name, folder_path, status, tanggal_aktif, tanggal_expire)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $order['nama_lembaga'], $order['nama_pemilik'], $order['email'],
                    $order['no_telp'], $order['package_id'], $subdomain, $db_name,
                    'tenants/' . $subdomain, 'aktif', date('Y-m-d'), $expire
                ]);
            $tenant_id = $pdo_global->lastInsertId();
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Gagal buat record tenant: ' . $e->getMessage()];
        }
    }

    // 8. Update order dengan tenant_id dan status diterima
    $pdo_global->prepare("UPDATE orders SET status='diterima', tenant_id=? WHERE id=?")
               ->execute([$tenant_id, $order_id]);

    // 9. Log status
    $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,?)")
               ->execute([$tenant_id, 'pending', 'aktif', 'Aktivasi setelah pembayaran diterima', $_SESSION['superadmin_id'] ?? null]);

    return [
        'success'    => true,
        'tenant_id'  => $tenant_id,
        'subdomain'  => $subdomain,
        'db_name'    => $db_name,
        'folder'     => 'tenants/' . $subdomain,
        'admin_pass' => 'admin123',
        'url'        => 'tenants/' . $subdomain . '/',
    ];
}

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

function runTenantSchema(PDO $pdo, string $db_name): void {
    $sql = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nama` varchar(150) NOT NULL,
        `email` varchar(150) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `no_hp` varchar(20) DEFAULT NULL,
        `role` enum('admin','user') DEFAULT 'user',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `classes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_kelas` varchar(100) NOT NULL,
        `kategori` varchar(50) DEFAULT 'Umum',
        `deskripsi` text DEFAULT NULL,
        `harga` int(11) DEFAULT 0,
        `harga_spesial` int(11) DEFAULT NULL,
        `jadwal` datetime NOT NULL,
        `link_zoom` varchar(255) DEFAULT NULL,
        `status` enum('aktif','nonaktif') DEFAULT 'aktif',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `registrations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `class_id` int(11) NOT NULL,
        `status` enum('pending','diterima','ditolak','selesai') DEFAULT 'pending',
        `bukti_bayar` varchar(255) DEFAULT NULL,
        `harga_saat_daftar` int(11) DEFAULT 0,
        `metode_pembayaran` varchar(50) DEFAULT NULL,
        `catatan_admin` text DEFAULT NULL,
        `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
        `tanggal_konfirmasi` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_class` (`user_id`,`class_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `certificates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `class_id` int(11) NOT NULL,
        `nomor_sertifikat` varchar(255) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_class_cert` (`user_id`,`class_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `company_teams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nama` varchar(150) NOT NULL,
        `jabatan` varchar(150) NOT NULL,
        `deskripsi` text DEFAULT NULL,
        `foto` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(50) NOT NULL,
        `setting_value` text DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('nama_lembaga', 'Nama LPK Anda'),
    ('tagline', 'Platform Pelatihan Profesional'),
    ('no_rekening', ''),
    ('nama_bank', ''),
    ('nama_rekening', '');
    ";

    foreach (explode(';', $sql) as $q) {
        $q = trim($q);
        if (!empty($q)) {
            try { $pdo->exec($q); } catch (PDOException $e) { /* skip */ }
        }
    }
}

// ============================================================
// MIGRATION — patch kolom yang belum ada di tenant lama
// ============================================================
function runTenantMigrations(PDO $pdo): void {
    $alters = [
        "ALTER TABLE `users` ADD COLUMN `no_hp` varchar(20) DEFAULT NULL",
        "ALTER TABLE `registrations` ADD COLUMN `catatan_admin` text DEFAULT NULL",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* sudah ada, skip */ }
    }
}

function generateTenantConfig(string $host, string $db, string $user, string $pass): string {
    return <<<PHP
<?php
// Konfigurasi Database Tenant - Auto-generated
\$host = '{$host}';
\$dbname = '{$db}';
\$username = '{$user}';
\$password = '{$pass}';

try {
    \$pdo = new PDO(
        "mysql:host={\$host};dbname={\$dbname};charset=utf8mb4",
        \$username,
        \$password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException \$e) {
    die('Koneksi database gagal.');
}
PHP;
}

function generateStatusCheck(string $subdomain): string {
    return <<<PHP
<?php
// Auto-generated status check - jangan diedit manual
define('TENANT_SUBDOMAIN', '{$subdomain}');
PHP;
}
