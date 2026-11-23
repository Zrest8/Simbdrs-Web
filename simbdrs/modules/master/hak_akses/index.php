<?php
// =====================================================================
// Master Hak Akses — atur menu & aksi per peran (tanpa mengubah kode)
// Tabel: hak_akses (SELECT, DELETE + INSERT per peran), log_aktivitas (INSERT)
//
// Satu peran ditampilkan per tab. Baris = menu, kolom = Lihat/Tambah/Ubah/Hapus.
// Aksi yang tidak berlaku di suatu menu ditampilkan "—" (lihat 'aksi' di includes/menu.php).
// Aturan:
//   • aksi Tambah/Ubah/Hapus hanya berlaku bila Lihat dicentang
//   • hak Admin atas menu Pengguna & Hak Akses dikunci (HAK_TERKUNCI) agar
//     Admin tidak bisa mengunci dirinya sendiri keluar
//   • perubahan langsung berlaku: sidebar & tombol mengikuti tabel ini
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
$saya = wajib_hak('hak_akses', 'lihat');
$boleh_ubah = hak('hak_akses', 'ubah');

$judul = 'Hak Akses';
$menu_aktif = 'hak_akses';

const PERAN_SEMUA = ['ADMIN', 'PETUGAS', 'KEPALA', 'DOKTER', 'PERAWAT'];
const AKSI_SEMUA  = ['lihat' => 'Lihat', 'tambah' => 'Tambah', 'ubah' => 'Ubah', 'hapus' => 'Hapus'];

$peran = in_array($_GET['peran'] ?? '', PERAN_SEMUA, true) ? $_GET['peran'] : 'PETUGAS';
$menu_list = array_filter(daftar_menu(), fn($m) => isset($m['aksi']));   // dashboard tidak diatur
$terkunci  = HAK_TERKUNCI[$peran] ?? [];

// ---------- Simpan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_hak('hak_akses', 'ubah');
    cek_csrf();
    $input = $_POST['hak'] ?? [];

    $baris = [];
    foreach ($menu_list as $kunci => $m) {
        $nilai = [];
        foreach (AKSI_SEMUA as $a => $_) {
            $berlaku = isset($m['aksi'][$a]);
            $nilai[$a] = $berlaku && in_array($kunci, $terkunci, true) ? 1
                       : (int) ($berlaku && !empty($input[$kunci][$a]));
        }
        if (!$nilai['lihat']) {                    // tanpa Lihat, aksi lain tidak berarti
            $nilai = array_fill_keys(array_keys(AKSI_SEMUA), 0);
        }
        if (array_sum($nilai) > 0) {
            $baris[$kunci] = $nilai;
        }
    }

    // Bandingkan dengan isi lama untuk keterangan log
    $st = $pdo->prepare('SELECT kunci_menu, lihat, tambah, ubah, hapus FROM hak_akses WHERE role = ?');
    $st->execute([$peran]);
    $lama = [];
    foreach ($st as $r) {
        $lama[$r['kunci_menu']] = $r;
    }
    $berubah = [];
    foreach ($menu_list as $kunci => $m) {
        $sebelum = array_map(fn($a) => (int) ($lama[$kunci][$a] ?? 0), array_keys(AKSI_SEMUA));
        $sesudah = array_map(fn($a) => (int) ($baris[$kunci][$a] ?? 0), array_keys(AKSI_SEMUA));
        if ($sebelum !== $sesudah) {
            $aktif = [];
            foreach (array_keys(AKSI_SEMUA) as $i => $a) {
                if ($sesudah[$i]) {
                    $aktif[] = AKSI_SEMUA[$a];
                }
            }
            $berubah[] = $m['label'] . ': ' . ($aktif ? implode('/', $aktif) : 'tanpa akses');
        }
    }

    if (!$berubah) {
        set_flash('info', 'Tidak ada perubahan hak akses.');
        redirect('modules/master/hak_akses/index.php?peran=' . $peran);
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM hak_akses WHERE role = ?')->execute([$peran]);
    $ins = $pdo->prepare('INSERT INTO hak_akses (role, kunci_menu, lihat, tambah, ubah, hapus) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($baris as $kunci => $n) {
        $ins->execute([$peran, $kunci, $n['lihat'], $n['tambah'], $n['ubah'], $n['hapus']]);
    }
    catat_log($pdo, 'UBAH', 'hak_akses', null,
        mb_substr('Hak akses ' . nama_role($peran) . ' diubah — ' . implode('; ', $berubah), 0, 255));
    $pdo->commit();

    set_flash('success', 'Hak akses ' . nama_role($peran) . ' disimpan (' . count($berubah) . ' menu berubah). Berlaku langsung.');
    redirect('modules/master/hak_akses/index.php?peran=' . $peran);
}

// ---------- Tampil ----------
$st = $pdo->prepare('SELECT kunci_menu, lihat, tambah, ubah, hapus FROM hak_akses WHERE role = ?');
$st->execute([$peran]);
$hak_peran = [];
foreach ($st as $r) {
    $hak_peran[$r['kunci_menu']] = $r;
}
$jml_akun = $pdo->prepare('SELECT COUNT(*) FROM pengguna WHERE role = ? AND aktif = 1');
$jml_akun->execute([$peran]);
$jml_akun = (int) $jml_akun->fetchColumn();

require __DIR__ . '/../../../includes/menu.php';   // $menu lengkap dengan judul grup

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel h-auto">
    <div class="panel-head flex-wrap border-0 pb-1">
        <i class="bi bi-shield-lock"></i><h2>Hak Akses per Peran</h2>
        <span class="ms-auto small text-secondary"><?= $jml_akun ?> akun aktif ber-peran <?= e(nama_role($peran)) ?></span>
    </div>

    <div class="panel-tabs">
        <ul class="nav nav-tabs">
            <?php foreach (PERAN_SEMUA as $r): ?>
                <li class="nav-item"><a class="nav-link <?= $peran === $r ? 'active' : '' ?>" href="?peran=<?= $r ?>"><?= e(nama_role($r)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="post" id="form-hak">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table align-middle tabel-hak mb-0">
                <thead>
                    <tr>
                        <th>Menu</th>
                        <?php foreach (AKSI_SEMUA as $label): ?><th class="text-center"><?= $label ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($menu as $m):
                    if (isset($m['judul'])): ?>
                        <tr class="baris-grup"><th colspan="5"><?= e($m['judul']) ?></th></tr>
                    <?php continue; endif;
                    if (!isset($m['aksi'])): ?>
                        <tr>
                            <td><i class="bi bi-<?= e($m['ikon']) ?> me-1 text-secondary"></i> <?= e($m['label']) ?>
                                <small class="d-block text-secondary">Selalu bisa dibuka semua peran; isinya menyesuaikan peran.</small></td>
                            <td class="text-center"><i class="bi bi-check-lg text-success" title="Selalu"></i></td>
                            <td class="text-center text-secondary" colspan="3">—</td>
                        </tr>
                    <?php continue; endif;
                    $k = $m['kunci'];
                    $kunci_baris = in_array($k, $terkunci, true); ?>
                    <tr data-menu="<?= e($k) ?>">
                        <td><i class="bi bi-<?= e($m['ikon']) ?> me-1 text-secondary"></i> <?= e($m['label']) ?>
                            <?php if ($kunci_baris): ?><span class="badge text-bg-light ms-1" title="Dikunci agar Admin tetap bisa mengatur akun & hak akses"><i class="bi bi-lock-fill"></i> dikunci</span><?php endif; ?>
                        </td>
                        <?php foreach (AKSI_SEMUA as $a => $label): ?>
                            <td class="text-center">
                                <?php if (!isset($m['aksi'][$a])): ?>
                                    <span class="text-secondary" title="Tidak berlaku di menu ini">—</span>
                                <?php else:
                                    $on = $kunci_baris || !empty($hak_peran[$k]['lihat']) && !empty($hak_peran[$k][$a]); ?>
                                    <input class="form-check-input cek-hak" type="checkbox" data-aksi="<?= $a ?>"
                                           name="hak[<?= e($k) ?>][<?= $a ?>]" value="1" <?= $on ? 'checked' : '' ?>
                                           <?= $kunci_baris || !$boleh_ubah ? 'disabled' : '' ?>
                                           title="<?= e($m['aksi'][$a]) ?>" aria-label="<?= e($label . ' — ' . $m['label'] . ': ' . $m['aksi'][$a]) ?>">
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body border-top d-flex flex-wrap gap-3 align-items-center">
            <?php if ($boleh_ubah): ?>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan hak <?= e(nama_role($peran)) ?></button>
            <?php endif; ?>
            <small class="text-secondary">
                <i class="bi bi-info-circle"></i>
                Arahkan kursor ke kotak centang untuk melihat artinya. Tambah/Ubah/Hapus otomatis butuh Lihat.
                Perubahan berlaku langsung untuk semua akun ber-peran ini (menu di samping & tombol ikut menyesuaikan).
            </small>
        </div>
    </form>
</section>

<script>
// Centang Tambah/Ubah/Hapus → Lihat ikut tercentang. Lepas Lihat → semua aksi di baris itu lepas.
document.querySelectorAll('#form-hak tr[data-menu]').forEach(function (tr) {
    const lihat = tr.querySelector('[data-aksi="lihat"]');
    const lain = tr.querySelectorAll('.cek-hak:not([data-aksi="lihat"])');
    if (!lihat) return;
    lihat.addEventListener('change', function () {
        if (!lihat.checked) lain.forEach(function (c) { c.checked = false; });
    });
    lain.forEach(function (c) {
        c.addEventListener('change', function () { if (c.checked) lihat.checked = true; });
    });
});
</script>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
