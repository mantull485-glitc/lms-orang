# Platform LPK — Panduan Instalasi

## Prasyarat
- PHP 7.4+ atau PHP 8.x
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache (disarankan) atau Nginx
- `mod_rewrite` aktif (Apache)

---

## Struktur Folder

```
platform/
├── auth/                    ← Login/logout super admin
├── config/                  ← Konfigurasi database & provisioner
├── sales/                   ← Landing page penjualan
├── superadmin/              ← Panel super admin
├── tenants/
│   ├── _template/           ← Template tenant (dari file LPK Anda)
│   └── [subdomain]/         ← Folder tenant aktif (dibuat otomatis)
├── templates/               ← Template halaman sistem (nonaktif, dll)
└── uploads/
    └── payments/            ← Bukti pembayaran pembeli
```

---

## Langkah Instalasi

### 1. Upload file ke server
Upload seluruh isi folder `platform/` ke `public_html/` atau folder web root Anda.

### 2. Buat database global
Buat database baru di MySQL dengan nama `platform_sales_db` (atau sesuai keinginan).

### 3. Edit konfigurasi database
Buka `config/superadmin_db.php` dan sesuaikan:
```php
define('SA_DB_HOST', 'localhost');
define('SA_DB_NAME', 'platform_sales_db');
define('SA_DB_USER', 'root');          // username MySQL Anda
define('SA_DB_PASS', '');             // password MySQL Anda
```

### 4. Inisialisasi database
Akses URL berikut di browser:
```
https://domain.com/config/init_global_db.php
```
Ini akan membuat semua tabel dan akun super admin default.

**⚠️ PENTING: Hapus atau rename file `init_global_db.php` setelah selesai!**

### 5. Login Super Admin
```
URL      : https://domain.com/auth/superadmin_login.php
Username : superadmin
Password : admin123
```
**Ganti password segera setelah login pertama!**

### 6. Isi data rekening pembayaran
Buka `sales/payment.php` dan ubah variabel `$rek_info`:
```php
$rek_info = [
    'bank'      => 'BCA',
    'no'        => '1234567890',
    'atas_nama' => 'Nama Perusahaan Anda',
];
```

---

## Alur Penggunaan

### Calon Pembeli:
1. Buka `https://domain.com/sales/` 
2. Pilih paket → `checkout.php` → isi data lembaga
3. Transfer & upload bukti bayar → `payment.php`
4. Menunggu verifikasi

### Super Admin:
1. Login di `auth/superadmin_login.php`
2. Cek order baru di **Verifikasi Pembayaran** (`finance.php`)
3. Klik **"Terima & Aktifkan Tenant"** → platform otomatis dibuat
4. Kelola semua tenant di halaman **Tenant / LPK** (`tenants.php`)
5. **Nonaktifkan** / **Aktifkan** tenant kapan saja dengan 1 klik

### Pemilik LPK (setelah diaktifkan):
1. Akses platform di `https://domain.com/tenants/[subdomain]/`
2. Login admin di `.../admin/` dengan email & password: `admin123`
3. Ganti password, lalu mulai tambahkan kelas dan kelola siswa

---

## Fitur Utama Super Admin

| Halaman | Fungsi |
|---------|--------|
| `superadmin/index.php` | Dashboard: statistik global, order terbaru |
| `superadmin/tenants.php` | Daftar semua tenant + **Aktifkan/Nonaktifkan** |
| `superadmin/packages.php` | CRUD paket & harga yang dijual |
| `superadmin/orders.php` | Semua order dengan filter status |
| `superadmin/finance.php` | Verifikasi pembayaran + provisioning otomatis |

---

## Keamanan

- Ubah password super admin segera setelah instalasi
- Hapus `config/init_global_db.php` setelah setup
- Pastikan folder `uploads/payments/` tidak bisa diakses langsung via URL (sudah ada `.htaccess`)
- Gunakan HTTPS di produksi
- Backup database secara rutin

---

## Kustomisasi

### Ganti nama platform
Cari dan replace `Platform.LPK` / `Platform LPK` di semua file sales dan superadmin.

### Ganti warna tema
Edit variabel CSS di `superadmin/assets/css/sa-style.css` dan `sales/assets/css/sales.css`:
```css
:root {
    --orange: #FF6A00;  /* Warna utama */
    --navy:   #0F172A;  /* Background */
    --cyan:   #00D2FF;  /* Aksen */
}
```

---

## Troubleshooting

**Platform tenant menampilkan halaman "Tidak Aktif" padahal sudah diaktifkan?**  
Pastikan `config/status_check.php` ada di folder tenant dan berisi subdomain yang benar.

**Error koneksi database saat provisioning?**  
Pastikan user MySQL memiliki hak akses `CREATE DATABASE`. Atau buat database tenant manual dan jalankan schema dari `config/provisioner.php` (fungsi `runTenantSchema`).

**File bukti bayar tidak bisa diupload?**  
Pastikan folder `uploads/payments/` memiliki permission `755` dan web server dapat menulis ke dalamnya.

---

## Update v2 — Fitur Tambahan

### Yang Baru:
- **Laporan & Analitik** (`superadmin/reports.php`) — grafik revenue 12 bulan, pertumbuhan tenant, breakdown per paket menggunakan Chart.js
- **Email Notifikasi** (`config/email_helper.php`) — email otomatis saat platform diaktifkan, ditolak, hampir expired, dan dinonaktifkan
- **Perpanjang Paket / Renewal** (`sales/renewal.php`, `sales/renewal_payment.php`) — pemilik LPK bisa perpanjang masa aktif dengan memasukkan email
- **Pengaturan Tenant Admin** (`tenants/_template/admin/settings.php`) — pemilik LPK bisa ubah nama lembaga, logo, info rekening, dan password
- **Cron Job Expire Checker** (`cron/daily_check.php`) — otomatis nonaktifkan tenant expired dan kirim email peringatan
- **Security** (`.htaccess` root & uploads) — lindungi folder config, cron, logs; blokir eksekusi PHP di folder uploads

### Setup Cron Job:
Tambahkan ke crontab server (`crontab -e`):
```
0 8 * * * php /var/www/html/platform/cron/daily_check.php >> /dev/null 2>&1
```

### Konfigurasi Email:
File `config/email_helper.php` menggunakan `mail()` native PHP. Untuk hasil lebih reliable di production, ganti dengan PHPMailer:
```bash
composer require phpmailer/phpmailer
```
Lalu update fungsi `sendEmail()` di `email_helper.php`.

### Renewal Tenant:
Pemilik LPK bisa perpanjang masa aktif di:
```
https://domain.com/sales/renewal.php
```
Super admin verifikasi renewal sama seperti order biasa di `finance.php`.
