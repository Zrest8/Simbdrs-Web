<?php
// =====================================================================
// Halaman "sedang dibangun" untuk menu yang modulnya belum dibuat.
// Dibuka dari sidebar: modules/segera.php?m=<kunci menu>
// Tidak menjalankan query apa pun; hanya membaca daftar menu (includes/menu.php).
// Hak akses tetap mengikuti peran yang tertulis di daftar menu.
// =====================================================================

require __DIR__ . '/../auth/cek_akses.php';
require __DIR__ . '/../includes/menu.php';

$kunci = $_GET['m'] ?? '';
$modul = null;
foreach ($menu as $item) {
    if (($item['kunci'] ?? null) === $kunci && $item['path'] === null) {
        $modul = $item;
        break;
    }
}

if ($modul === null) {
    redirect('modules/dashboard/index.php');
}

wajib_login($modul['peran']);

$judul = $modul['label'];
$menu_aktif = $modul['kunci'];
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <div class="panel-body text-center py-5">
        <div class="stat-ikon mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
            <i class="bi bi-<?= e($modul['ikon']) ?>"></i>
        </div>
        <h2 class="h4 fw-bold mb-1"><?= e($modul['label']) ?></h2>
        <span class="badge text-bg-warning mb-3">Sedang dibangun</span>
        <p class="text-secondary mx-auto mb-2" style="max-width:560px;"><?= e($modul['ket'] ?? '') ?></p>
        <?php if (!empty($modul['tabel'])): ?>
            <p class="small text-secondary mb-4">Tabel database: <code><?= e($modul['tabel']) ?></code></p>
        <?php endif; ?>
        <a href="<?= url('modules/dashboard/index.php') ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
