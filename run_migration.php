<?php
// ============================================================
// DATABASE MIGRATION SCRIPT — RUN FROM BROWSER (SEKALI PAKAI)
// Mencakup semua perubahan schema yang diperlukan platform.
// Hapus file ini setelah dijalankan!
// ============================================================

require_once 'config/superadmin_db.php';

echo "<!DOCTYPE html><html><head><title>Database Migration</title><style>
body { background:#0F172A; color:#94A3B8; font-family:'Segoe UI',sans-serif; padding:2rem; }
.card { background:#1E293B; border:1px solid #334155; border-radius:16px; padding:2rem; max-width:700px; margin:0 auto; box-shadow:0 10px 30px rgba(0,0,0,.4); }
h2 { color:#fff; margin:0 0 1.5rem; font-size:1.3rem; display:flex;align-items:center;gap:.5rem; }
.step { margin-bottom:.6rem; padding:.6rem 1rem; border-radius:8px; font-size:.875rem; }
.ok   { background:rgba(16,185,129,.1);  border:1px solid rgba(16,185,129,.2); color:#10B981; }
.skip { background:rgba(100,116,139,.1); border:1px solid rgba(100,116,139,.2); color:#94A3B8; }
.err  { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.2);  color:#EF4444; }
.done { color:#10B981; font-weight:700; font-size:1.05rem; margin-top:1.5rem; border-top:1px solid #334155; padding-top:1.2rem; }
.warn { color:#F59E0B; font-size:.82rem; margin-top:.75rem; }
code  { background:rgba(0,0,0,.3); padding:2px 6px; border-radius:4px; font-size:.8rem; }
</style></head><body><div class='card'>";

echo "<h2>🔧 Migrasi Database Platform LPK</h2>";

$migrations = [];

// ── Helper: jalankan ALTER TABLE yang aman ──
function runSQL(PDO $pdo, string $label, string $sql): void {
    global $migrations;
    try {
        $pdo->exec($sql);
        $migrations[] = ['ok', $label];
    } catch (PDOException $e) {
        // Abaikan jika kolom/constraint sudah ada (common error codes)
        $msg = $e->getMessage();
        if (str_contains($msg, 'already exists') || str_contains($msg, 'duplicate column') || str_contains($msg, 'already been created')) {
            $migrations[] = ['skip', $label . ' <span style="font-size:.78rem">(sudah ada — dilewati)</span>'];
        } else {
            $migrations[] = ['err', $label . '<br><code>' . htmlspecialchars($msg) . '</code>'];
        }
    }
}

try {
    // ══════════════════════════════════════════════
    // BLOK 1: Tabel tenants — custom_domain
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ tenants.custom_domain — tambah kolom',
        "ALTER TABLE tenants ADD COLUMN IF NOT EXISTS custom_domain VARCHAR(255) UNIQUE DEFAULT NULL");
    runSQL($pdo_global, '✦ tenants.custom_domain — buat index',
        "CREATE INDEX IF NOT EXISTS idx_tenants_custom_domain ON tenants (custom_domain)");

    // ══════════════════════════════════════════════
    // BLOK 2: Tabel tenants — kolom status tracking
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ tenants.dinonaktifkan_oleh — tambah kolom',
        "ALTER TABLE tenants ADD COLUMN IF NOT EXISTS dinonaktifkan_oleh INTEGER DEFAULT NULL");
    runSQL($pdo_global, '✦ tenants.tanggal_nonaktif — tambah kolom',
        "ALTER TABLE tenants ADD COLUMN IF NOT EXISTS tanggal_nonaktif TIMESTAMP DEFAULT NULL");

    // ══════════════════════════════════════════════
    // BLOK 3: Tabel packages — status aktif
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ packages.status — tambah kolom',
        "ALTER TABLE packages ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'aktif'");
    runSQL($pdo_global, '✦ packages.is_popular — tambah kolom',
        "ALTER TABLE packages ADD COLUMN IF NOT EXISTS is_popular BOOLEAN DEFAULT false");
    // Sinkronkan is_aktif → status jika is_aktif ada
    try {
        $pdo_global->exec("UPDATE packages SET status = CASE WHEN is_aktif = true THEN 'aktif' ELSE 'nonaktif' END WHERE status IS NULL OR status = ''");
        $migrations[] = ['ok', '✦ packages.status — sinkronisasi dari is_aktif'];
    } catch (PDOException $e) {
        $migrations[] = ['skip', '✦ packages.status — sinkronisasi dilewati'];
    }

    // ══════════════════════════════════════════════
    // BLOK 4: Tabel orders — Midtrans
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ orders.midtrans_order_id — tambah kolom',
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS midtrans_order_id VARCHAR(100) DEFAULT NULL");
    runSQL($pdo_global, '✦ orders.metode_bayar — tambah kolom',
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS metode_bayar VARCHAR(50) DEFAULT NULL");
    runSQL($pdo_global, '✦ orders.updated_at — tambah kolom',
        "ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW()");
    // Unique constraint aman pakai DO NOTHING trick via query
    try {
        $check = $pdo_global->query("SELECT constraint_name FROM information_schema.table_constraints WHERE table_name='orders' AND constraint_name='uq_orders_midtrans_order_id'")->fetch();
        if (!$check) {
            $pdo_global->exec("ALTER TABLE orders ADD CONSTRAINT uq_orders_midtrans_order_id UNIQUE (midtrans_order_id)");
            $migrations[] = ['ok', '✦ orders.midtrans_order_id — unique constraint'];
        } else {
            $migrations[] = ['skip', '✦ orders.midtrans_order_id — unique constraint (sudah ada)'];
        }
    } catch (PDOException $e) {
        $migrations[] = ['skip', '✦ orders.midtrans_order_id — unique constraint (dilewati)'];
    }
    runSQL($pdo_global, '✦ orders.midtrans_order_id — buat index',
        "CREATE INDEX IF NOT EXISTS idx_orders_midtrans_order_id ON orders (midtrans_order_id)");

    // ══════════════════════════════════════════════
    // BLOK 5: Tabel users — kolom no_hp
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ users.no_hp — tambah kolom',
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS no_hp VARCHAR(20) DEFAULT NULL");

    // ══════════════════════════════════════════════
    // BLOK 6: Tabel registrations — kolom catatan_admin
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ registrations.catatan_admin — tambah kolom',
        "ALTER TABLE registrations ADD COLUMN IF NOT EXISTS catatan_admin TEXT DEFAULT NULL");
    runSQL($pdo_global, '✦ registrations.harga_saat_daftar — tambah kolom',
        "ALTER TABLE registrations ADD COLUMN IF NOT EXISTS harga_saat_daftar INTEGER DEFAULT 0");
    runSQL($pdo_global, '✦ registrations.metode_pembayaran — tambah kolom',
        "ALTER TABLE registrations ADD COLUMN IF NOT EXISTS metode_pembayaran VARCHAR(50) DEFAULT NULL");
    runSQL($pdo_global, '✦ registrations.midtrans_order_id — tambah kolom',
        "ALTER TABLE registrations ADD COLUMN IF NOT EXISTS midtrans_order_id VARCHAR(100) DEFAULT NULL");
    runSQL($pdo_global, '✦ registrations.midtrans_order_id — buat index',
        "CREATE INDEX IF NOT EXISTS idx_registrations_midtrans_order_id ON registrations (midtrans_order_id)");

    // ══════════════════════════════════════════════
    // BLOK 7: Tabel settings — indeks
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ settings — buat index tenant_id',
        "CREATE INDEX IF NOT EXISTS idx_settings_tenant_id ON settings (tenant_id)");

    // ══════════════════════════════════════════════
    // BLOK 8: tenant_status_logs — tambah kolom actions
    // ══════════════════════════════════════════════
    runSQL($pdo_global, '✦ tenant_status_logs.dilakukan_oleh — tambah kolom (jika belum ada)',
        "ALTER TABLE tenant_status_logs ADD COLUMN IF NOT EXISTS dilakukan_oleh INTEGER DEFAULT NULL");

    $has_error = !empty(array_filter($migrations, fn($m) => $m[0] === 'err'));

} catch (PDOException $e) {
    $migrations[] = ['err', 'KONEKSI DATABASE GAGAL: ' . htmlspecialchars($e->getMessage())];
    $has_error = true;
}

// ── Tampilkan Hasil ──
foreach ($migrations as [$type, $msg]) {
    echo "<div class='step $type'>$msg</div>";
}

echo "<div class='done'>" . ($has_error
    ? "⚠️ Beberapa migrasi gagal — periksa pesan error di atas."
    : "✅ Semua migrasi selesai dengan sukses!")
. "</div>";

echo "<p class='warn'>⚠️ Demi keamanan, hapus atau rename file <code>run_migration.php</code> setelah dijalankan.</p>";

echo "</div></body></html>";
