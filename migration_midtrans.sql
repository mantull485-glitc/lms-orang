-- ============================================================
-- MIGRATION: Tambah kolom Midtrans ke tabel orders
-- Jalankan di Supabase SQL Editor
-- ============================================================

-- 1. Tambah kolom midtrans_order_id
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS midtrans_order_id VARCHAR(100) DEFAULT NULL;

-- 2. Tambah kolom updated_at jika belum ada
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

-- 3. Buat index untuk lookup cepat via midtrans_order_id
CREATE INDEX IF NOT EXISTS idx_orders_midtrans_order_id
    ON orders (midtrans_order_id);

-- 4. (Opsional) Hapus kolom bukti_bayar jika tidak dipakai lagi
-- Komentar baris ini jika ingin tetap menyimpan bukti manual
-- ALTER TABLE orders DROP COLUMN IF EXISTS bukti_bayar;

-- ============================================================
-- Verifikasi
-- ============================================================
SELECT column_name, data_type
FROM information_schema.columns
WHERE table_name = 'orders'
ORDER BY ordinal_position;
