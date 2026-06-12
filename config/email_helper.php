<?php
// ============================================================
// EMAIL NOTIFICATION HELPER
// Gunakan PHP mail() native, bisa diganti PHPMailer jika perlu
// ============================================================

function sendEmail(string $to, string $subject, string $html_body, string $from_name = 'Platform LPK', string $from_email = 'noreply@platform.com'): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    // Gunakan @ untuk menyembunyikan warning jika sendmail tidak tersedia (misal di Vercel Serverless)
    return @mail($to, $subject, $html_body, $headers);
}

function emailTemplate(string $title, string $body, string $cta_text = '', string $cta_url = ''): string {
    $cta_html = $cta_text ? "
    <div style='text-align:center;margin:32px 0'>
        <a href='{$cta_url}' style='background:#FF6A00;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block'>
            {$cta_text}
        </a>
    </div>" : '';

    return "<!DOCTYPE html>
<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#0F172A;font-family:Arial,sans-serif'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#0F172A;padding:40px 16px'>
<tr><td align='center'>
<table width='100%' style='max-width:560px;background:#1E293B;border-radius:16px;overflow:hidden;border:1px solid #1E3A5F'>
    <!-- Header -->
    <tr><td style='background:linear-gradient(135deg,#FF6A00,#FF8800);padding:28px 32px;text-align:center'>
        <div style='font-size:22px;font-weight:800;color:#fff'>Platform<span style='opacity:.7'>.</span>LPK</div>
    </td></tr>
    <!-- Body -->
    <tr><td style='padding:32px'>
        <h2 style='color:#fff;font-size:20px;margin:0 0 16px'>{$title}</h2>
        <div style='color:#94A3B8;font-size:14px;line-height:1.8'>{$body}</div>
        {$cta_html}
        <hr style='border:none;border-top:1px solid #1E3A5F;margin:28px 0'>
        <p style='color:#475569;font-size:12px;margin:0;text-align:center'>
            Email ini dikirim otomatis. Jangan balas email ini.<br>
            © " . date('Y') . " Platform LPK
        </p>
    </td></tr>
</table>
</td></tr>
</table>
</body></html>";
}

// ── Template: Order Diterima & Platform Aktif
function emailPlatformAktif(array $data): bool {
    $body = "
        Halo <strong style='color:#fff'>{$data['nama_pemilik']}</strong>,<br><br>
        Kabar baik! Pembayaran Anda telah kami verifikasi dan platform <strong style='color:#fff'>{$data['nama_lembaga']}</strong>
        kini <span style='color:#10B981;font-weight:700'>sudah aktif</span>.<br><br>
        <strong style='color:#E2E8F0'>Detail Akses Platform:</strong><br>
        <table style='width:100%;margin:12px 0;font-size:13px'>
            <tr><td style='color:#64748B;padding:4px 0;width:140px'>URL Platform</td><td style='color:#fff'>{$data['url']}</td></tr>
            <tr><td style='color:#64748B;padding:4px 0'>URL Admin Panel</td><td style='color:#fff'>{$data['url']}admin/</td></tr>
            <tr><td style='color:#64748B;padding:4px 0'>Email Login</td><td style='color:#fff'>{$data['email']}</td></tr>
            <tr><td style='color:#64748B;padding:4px 0'>Password Awal</td><td style='color:#FF6A00;font-weight:700'>{$data['admin_pass']}</td></tr>
            <tr><td style='color:#64748B;padding:4px 0'>Paket</td><td style='color:#fff'>{$data['paket_nama']}</td></tr>
            <tr><td style='color:#64748B;padding:4px 0'>Aktif Hingga</td><td style='color:#fff'>{$data['expire']}</td></tr>
        </table>
        <div style='background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px;margin-top:12px;font-size:13px;color:#FCA5A5'>
            ⚠️ Segera ganti password setelah login pertama kali untuk keamanan akun Anda.
        </div>
    ";
    $html = emailTemplate("Platform Anda Sudah Aktif! 🎉", $body, "Buka Admin Panel", $data['url'] . 'admin/');
    return sendEmail($data['email'], "Platform {$data['nama_lembaga']} Berhasil Diaktifkan – Platform LPK", $html);
}

// ── Template: Order Ditolak
function emailOrderDitolak(array $data): bool {
    $body = "
        Halo <strong style='color:#fff'>{$data['nama_pemilik']}</strong>,<br><br>
        Mohon maaf, verifikasi pembayaran untuk order <strong style='color:#fff'>#{$data['order_id']}</strong>
        atas nama <strong style='color:#fff'>{$data['nama_lembaga']}</strong> tidak dapat kami proses.<br><br>
        <strong style='color:#E2E8F0'>Alasan:</strong><br>
        <div style='background:rgba(239,68,68,.1);border-left:3px solid #EF4444;padding:10px 14px;margin:8px 0;border-radius:0 6px 6px 0;color:#FCA5A5;font-size:13px'>
            {$data['alasan']}
        </div><br>
        Silakan lakukan pembelian ulang dengan memastikan bukti pembayaran yang Anda unggah valid dan nominal sesuai.
        Tim kami siap membantu jika ada pertanyaan.
    ";
    $html = emailTemplate("Verifikasi Pembayaran Ditolak", $body, "Coba Lagi", $data['checkout_url']);
    return sendEmail($data['email'], "Verifikasi Pembayaran Ditolak – Platform LPK", $html);
}

// ── Template: Peringatan Expire
function emailExpireWarning(array $data): bool {
    $body = "
        Halo <strong style='color:#fff'>{$data['nama_pemilik']}</strong>,<br><br>
        Masa aktif platform <strong style='color:#fff'>{$data['nama_lembaga']}</strong> akan
        <span style='color:#F59E0B;font-weight:700'>berakhir dalam {$data['sisa_hari']} hari</span>
        (<strong style='color:#fff'>{$data['expire']}</strong>).<br><br>
        Perpanjang sekarang agar platform Anda tidak terganggu dan data siswa tetap aman.
    ";
    $html = emailTemplate("Masa Aktif Platform Hampir Habis ⚠️", $body, "Perpanjang Sekarang", $data['renew_url']);
    return sendEmail($data['email'], "Peringatan: Masa Aktif Platform Akan Berakhir – Platform LPK", $html);
}

// ── Template: Platform Dinonaktifkan
function emailPlatformNonaktif(array $data): bool {
    $body = "
        Halo <strong style='color:#fff'>{$data['nama_pemilik']}</strong>,<br><br>
        Platform <strong style='color:#fff'>{$data['nama_lembaga']}</strong> saat ini telah
        <span style='color:#EF4444;font-weight:700'>dinonaktifkan</span> oleh tim kami.<br><br>
        " . ($data['alasan'] ? "<strong style='color:#E2E8F0'>Keterangan:</strong><br>
        <div style='background:rgba(239,68,68,.1);border-left:3px solid #EF4444;padding:10px 14px;margin:8px 0;border-radius:0 6px 6px 0;color:#FCA5A5;font-size:13px'>{$data['alasan']}</div><br>" : '') . "
        Hubungi tim support kami untuk informasi lebih lanjut mengenai reaktivasi platform Anda.
    ";
    $html = emailTemplate("Platform Anda Dinonaktifkan", $body, "Hubungi Support", "mailto:support@platform.com");
    return sendEmail($data['email'], "Platform {$data['nama_lembaga']} Telah Dinonaktifkan – Platform LPK", $html);
}

// ── Template: Renewal Berhasil
function emailRenewalBerhasil(array $data): bool {
    $body = "
        Halo <strong style='color:#fff'>{$data['nama_pemilik']}</strong>,<br><br>
        Pembayaran perpanjangan paket untuk platform <strong style='color:#fff'>{$data['nama_lembaga']}</strong>
        telah kami terima.<br><br>
        Masa aktif platform Anda telah berhasil diperpanjang hingga <strong style='color:#10B981'>{$data['expire']}</strong>.<br><br>
        Terima kasih atas kepercayaan Anda menggunakan layanan kami.
    ";
    $html = emailTemplate("Perpanjangan Paket Berhasil! 🎉", $body, "Buka Platform", $data['url']);
    return sendEmail($data['email'], "Perpanjangan Paket {$data['nama_lembaga']} Berhasil – Platform LPK", $html);
}

