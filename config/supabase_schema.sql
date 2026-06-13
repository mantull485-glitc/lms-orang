-- ============================================================
-- SUPABASE SCHEMA — Unified Multi-Tenant Schema
-- Jalankan seluruh script ini di Supabase SQL Editor:
-- Dashboard → SQL Editor → New Query → Paste → Run
-- ============================================================

-- ──────────────────────────────────────────────
-- GLOBAL TABLES (Super Admin)
-- ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS superadmins (
    id SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS superadmin_users (
    id SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);


CREATE TABLE IF NOT EXISTS packages (
    id SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    deskripsi TEXT,
    harga INTEGER NOT NULL DEFAULT 0,
    harga_tahunan INTEGER DEFAULT 0,
    durasi_bulan INTEGER DEFAULT 12,
    fitur TEXT,
    is_aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS tenants (
    id SERIAL PRIMARY KEY,
    nama_lembaga VARCHAR(200) NOT NULL,
    nama_pemilik VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    no_telp VARCHAR(20),
    subdomain VARCHAR(100) UNIQUE,
    custom_domain VARCHAR(255) UNIQUE DEFAULT NULL,
    db_name VARCHAR(100),
    folder_path VARCHAR(255),
    package_id INTEGER REFERENCES packages(id),
    paket_nama VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    alasan_nonaktif TEXT,
    tanggal_aktif TIMESTAMP,
    tanggal_expire TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id),
    package_id INTEGER REFERENCES packages(id),
    nama_lembaga VARCHAR(200),
    nama_pemilik VARCHAR(150),
    email VARCHAR(150),
    no_telp VARCHAR(20),
    subdomain_request VARCHAR(100),
    harga INTEGER,
    harga_bayar INTEGER,
    bukti_bayar VARCHAR(255),
    metode_bayar VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    tanggal_konfirmasi TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenant_status_logs (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
    status_lama VARCHAR(20),
    status_baru VARCHAR(20),
    alasan TEXT,
    dilakukan_oleh INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ──────────────────────────────────────────────
-- TENANT TABLES (Multi-Tenant, dipisah dengan tenant_id)
-- ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nama VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20),
    role VARCHAR(10) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(tenant_id, email)
);

CREATE TABLE IF NOT EXISTS classes (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nama_kelas VARCHAR(100) NOT NULL,
    kategori VARCHAR(50) DEFAULT 'Umum',
    deskripsi TEXT,
    harga INTEGER DEFAULT 0,
    harga_spesial INTEGER,
    jadwal TIMESTAMP NOT NULL,
    link_zoom VARCHAR(255),
    status VARCHAR(20) DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS registrations (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'pending',
    bukti_bayar VARCHAR(255),
    harga_saat_daftar INTEGER DEFAULT 0,
    metode_pembayaran VARCHAR(50),
    catatan_admin TEXT,
    tanggal_daftar TIMESTAMP DEFAULT NOW(),
    tanggal_konfirmasi TIMESTAMP,
    UNIQUE(tenant_id, user_id, class_id)
);

CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    UNIQUE(tenant_id, setting_key)
);

CREATE TABLE IF NOT EXISTS team (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    nama VARCHAR(150) NOT NULL,
    jabatan VARCHAR(100),
    bio TEXT,
    foto VARCHAR(255),
    urutan INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS certificates (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE,
    registration_id INTEGER REFERENCES registrations(id) ON DELETE CASCADE,
    nomor_sertifikat VARCHAR(100),
    file_path VARCHAR(255),
    tanggal_terbit TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(tenant_id, user_id, class_id)
);

-- ──────────────────────────────────────────────
-- DEFAULT SUPERADMIN ACCOUNT
-- ──────────────────────────────────────────────
-- Username: admin | Password: admin123
-- Ganti password setelah pertama kali login!
INSERT INTO superadmins (nama, username, email, password)
VALUES ('Super Admin', 'admin', 'admin@platform.com',
        '$2y$10$abcdefghijklmnopqrstuuVGZbQ8WtDhEcPvjSPMvVa/BKMO47HBK')
ON CONFLICT (username) DO NOTHING;
