<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config/superadmin_db.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Cek Data Payment</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem}
table{width:100%;border-collapse:collapse;margin-bottom:2rem;font-size:.85rem}
th{background:#1e293b;padding:.6rem 1rem;text-align:left;color:#94a3b8;font-weight:600}
td{padding:.5rem 1rem;border-bottom:1px solid #1e293b;vertical-align:top}
.ok{color:#10B981} .err{color:#EF4444} .warn{color:#F59E0B}
h2{color:#FF6A00;margin-top:2rem;border-bottom:1px solid #1e293b;padding-bottom:.5rem}
</style>
</head>
<body>
<h2>📋 Orders Terbaru (10 terakhir)</h2>
<?php
$orders = $pdo_global->query("SELECT id, nama_lembaga, email, status, midtrans_order_id, metode_bayar, harga_bayar, created_at FROM orders ORDER BY id DESC LIMIT 10")->fetchAll();
if ($orders): ?>
<table>
<tr><th>ID</th><th>Lembaga</th><th>Email</th><th>Status</th><th>Midtrans Order ID</th><th>Metode</th><th>Harga</th><th>Tanggal</th></tr>
<?php foreach ($orders as $o): ?>
<tr>
    <td>#<?= $o['id'] ?></td>
    <td><?= htmlspecialchars($o['nama_lembaga']) ?></td>
    <td><?= htmlspecialchars($o['email']) ?></td>
    <td class="<?= $o['status']==='diterima'?'ok':($o['status']==='ditolak'?'err':'warn') ?>"><?= $o['status'] ?></td>
    <td style="font-size:.75rem;color:#64748b"><?= $o['midtrans_order_id'] ?? '—' ?></td>
    <td><?= $o['metode_bayar'] ?? '—' ?></td>
    <td>Rp <?= number_format($o['harga_bayar'],0,',','.') ?></td>
    <td><?= $o['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p class="err">❌ Tidak ada data orders.</p>
<?php endif; ?>

<h2>🏢 Tenants Terbaru (10 terakhir)</h2>
<?php
$tenants = $pdo_global->query("SELECT id, nama_lembaga, email, subdomain, status, tanggal_expire, created_at FROM tenants ORDER BY id DESC LIMIT 10")->fetchAll();
if ($tenants): ?>
<table>
<tr><th>ID</th><th>Lembaga</th><th>Email</th><th>Subdomain</th><th>Status</th><th>Expire</th></tr>
<?php foreach ($tenants as $t): ?>
<tr>
    <td>#<?= $t['id'] ?></td>
    <td><?= htmlspecialchars($t['nama_lembaga']) ?></td>
    <td><?= htmlspecialchars($t['email']) ?></td>
    <td><code style="color:#00D2FF"><?= $t['subdomain'] ?></code></td>
    <td class="<?= $t['status']==='aktif'?'ok':'err' ?>"><?= $t['status'] ?></td>
    <td><?= $t['tanggal_expire'] ? date('d M Y', strtotime($t['tanggal_expire'])) : '—' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p class="err">❌ Tidak ada data tenants.</p>
<?php endif; ?>

<h2>👤 Users Terbaru (10 terakhir)</h2>
<?php
$users = $pdo_global->query("SELECT id, tenant_id, nama, email, role, created_at FROM users ORDER BY id DESC LIMIT 10")->fetchAll();
if ($users): ?>
<table>
<tr><th>ID</th><th>Tenant ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Dibuat</th></tr>
<?php foreach ($users as $u): ?>
<tr>
    <td>#<?= $u['id'] ?></td>
    <td><?= $u['tenant_id'] ?></td>
    <td><?= htmlspecialchars($u['nama']) ?></td>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td><?= $u['role'] ?></td>
    <td><?= $u['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p class="err">❌ Tidak ada data users.</p>
<?php endif; ?>
</body>
</html>
