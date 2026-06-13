-- ============================================================
-- MIGRATION: Tambah kolom custom_domain ke tabel tenants
-- Jalankan query ini di Supabase SQL Editor
-- ============================================================

-- 1. Tambah kolom custom_domain jika belum ada
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS custom_domain VARCHAR(255) UNIQUE DEFAULT NULL;

-- 2. Buat index untuk mempercepat pencarian domain
CREATE INDEX IF NOT EXISTS idx_tenants_custom_domain
    ON tenants (custom_domain);

-- ============================================================
-- Verifikasi Struktur Tabel Tenants
-- ============================================================
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'tenants'
ORDER BY ordinal_position;
