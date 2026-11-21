<?php
// =====================================================================
// Menu samping. Setiap menu punya daftar peran yang boleh melihatnya.
// Daftar menu ada di includes/menu.php.
// Menu yang modulnya belum dibuat tetap bisa diklik → halaman "sedang dibangun".
// =====================================================================
require __DIR__ . '/menu.php';

$peran_saya = pengguna()['role'];

// Buang judul grup yang tidak punya menu untuk peran ini
$tampil = [];
$judul_tunda = null;
foreach ($menu as $m) {
    if (isset($m['judul'])) {
        $judul_tunda = $m;
        continue;
    }
    if (!in_array($peran_saya, $m['peran'], true)) {
        continue;
    }
    if ($judul_tunda) {
        $tampil[] = $judul_tunda;
        $judul_tunda = null;
    }
    $tampil[] = $m;
}
?>
<aside class="sidebar offcanvas-lg offcanvas-start" id="sidebar" tabindex="-1">
    <div class="sidebar-brand">
        <span class="brand-logo sm"><i class="bi bi-droplet-fill"></i></span>
        <div>
            <div class="fw-bold"><?= e(NAMA_APP) ?></div>
            <small><?= e(NAMA_RS) ?></small>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($tampil as $m): ?>
            <?php if (isset($m['judul'])): ?>
                <div class="nav-group"><?= e($m['judul']) ?></div>
            <?php elseif ($m['path']): ?>
                <a class="nav-item <?= $menu_aktif === $m['kunci'] ? 'active' : '' ?>" href="<?= url($m['path']) ?>">
                    <i class="bi bi-<?= e($m['ikon']) ?>"></i><span><?= e($m['label']) ?></span>
                </a>
            <?php else: ?>
                <a class="nav-item <?= $menu_aktif === $m['kunci'] ? 'active' : '' ?>" href="<?= url('modules/segera.php?m=' . urlencode($m['kunci'])) ?>" title="Modul sedang dibangun">
                    <i class="bi bi-<?= e($m['ikon']) ?>"></i><span><?= e($m['label']) ?></span>
                    <em class="badge-soon">segera</em>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
