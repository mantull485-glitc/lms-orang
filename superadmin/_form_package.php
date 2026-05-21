<?php
// Partial: form fields for package add/edit
// $form_pkg = existing data (for edit), $form_fitur = textarea content
$fp = $form_pkg ?? null;
$ff = $form_fitur ?? '';
?>
<div class="sa-form-group">
    <label>Nama Paket <span style="color:var(--orange)">*</span></label>
    <input type="text" name="nama" class="sa-form-control" required placeholder="contoh: Basic, Pro, Enterprise" value="<?= htmlspecialchars($fp['nama'] ?? '') ?>">
</div>
<div class="row g-2">
    <div class="col-6">
        <div class="sa-form-group">
            <label>Harga Bulanan (Rp) <span style="color:var(--orange)">*</span></label>
            <input type="number" name="harga" class="sa-form-control" required placeholder="299000" value="<?= $fp['harga'] ?? '' ?>">
        </div>
    </div>
    <div class="col-6">
        <div class="sa-form-group">
            <label>Harga Tahunan (Rp)</label>
            <input type="number" name="harga_tahunan" class="sa-form-control" placeholder="2490000" value="<?= $fp['harga_tahunan'] ?? '' ?>">
        </div>
    </div>
    <div class="col-6">
        <div class="sa-form-group">
            <label>Maks. Kelas (999 = ∞)</label>
            <input type="number" name="max_kelas" class="sa-form-control" placeholder="10" value="<?= $fp['max_kelas'] ?? 10 ?>">
        </div>
    </div>
    <div class="col-6">
        <div class="sa-form-group">
            <label>Maks. Siswa (9999 = ∞)</label>
            <input type="number" name="max_users" class="sa-form-control" placeholder="100" value="<?= $fp['max_users'] ?? 100 ?>">
        </div>
    </div>
</div>
<div class="sa-form-group">
    <label>Fitur (satu baris = satu fitur)</label>
    <textarea name="fitur" class="sa-form-control" rows="5" placeholder="Sertifikat Digital&#10;Link Zoom&#10;Laporan Keuangan"><?= htmlspecialchars($ff) ?></textarea>
</div>
<div class="sa-form-group">
    <label>Status</label>
    <select name="status" class="sa-form-control">
        <option value="aktif" <?= ($fp['status'] ?? 'aktif')==='aktif'?'selected':'' ?>>Aktif (ditampilkan di sales page)</option>
        <option value="nonaktif" <?= ($fp['status'] ?? '')==='nonaktif'?'selected':'' ?>>Nonaktif (disembunyikan)</option>
    </select>
</div>
<div class="sa-form-group">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="is_popular" value="1" <?= ($fp['is_popular'] ?? 0) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--orange)">
        <span>Tandai sebagai <strong style="color:var(--orange)">Paling Populer</strong></span>
    </label>
</div>
