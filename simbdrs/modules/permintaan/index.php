<?php
// =====================================================================
// Permintaan Darah — DAFTAR (F-05)
// Tabel: permintaan_darah (SELECT), pasien, ruangan, komponen_darah (JOIN),
//        kantong_darah (hitung kantong terpenuhi)
// BR-11: perawat hanya melihat permintaan ruangannya; dokter hanya pasien yang ia tangani.
// Urutan: aktif dulu → CITO dulu → MENUNGGU → DIPROSES → TERPENUHI_SEBAGIAN
//         → paling lama menunggu dulu (supaya permintaan baru tidak tenggelam).
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
require __DIR__ . '/_fungsi.php';
$saya = wajib_hak('permintaan');
$boleh_buat   = hak('permintaan', 'tambah');
$boleh_proses = hak('permintaan', 'ubah');
$kolom_ruang  = $saya['role'] !== 'PERAWAT';   // perawat cukup tahu ruangannya sendiri

$judul = 'Permintaan Darah';
$menu_aktif = 'permintaan';

$tab  = in_array($_GET['tab'] ?? '', ['aktif', 'selesai', 'semua'], true) ? $_GET['tab'] : 'aktif';
$cari = trim($_GET['cari'] ?? '');

$where = [];
$param = [];
[$batas, $param_batas] = batas_data($saya);
if ($batas !== '') {
    $where[] = $batas;
    $param = array_merge($param, $param_batas);
}
if ($tab === 'aktif') {
    $where[] = "p.status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN')";
} elseif ($tab === 'selesai') {
    $where[] = "p.status IN ('TERPENUHI','DIBATALKAN')";
}
if ($cari !== '') {
    $where[] = '(p.no_permintaan LIKE ? OR ps.nama_pasien LIKE ? OR ps.no_rm LIKE ?)';
    array_push($param, "%$cari%", "%$cari%", "%$cari%");
}

$sql = "SELECT p.id_permintaan, p.no_permintaan, p.urgensi, p.status, p.jumlah_kantong, p.tgl_permintaan,
               ps.nama_pasien, ps.no_rm, ps.gol_darah, ps.rhesus, c.kode, r.nama_ruangan,
               (SELECT COUNT(*) FROM kantong_darah k
                 WHERE k.id_permintaan_dipesan = p.id_permintaan
                   AND k.status IN ('DIPESAN','DIKELUARKAN','DITRANSFUSIKAN')) AS terpenuhi
        FROM permintaan_darah p
        JOIN pasien ps        ON ps.id_pasien  = p.id_pasien
        JOIN komponen_darah c ON c.id_komponen = p.id_komponen
        JOIN ruangan r        ON r.id_ruangan  = p.id_ruangan"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY (p.status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN')) DESC,
                 (p.urgensi = 'CITO') DESC,
                 FIELD(p.status, 'MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN'),
                 CASE WHEN p.status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN') THEN p.tgl_permintaan END ASC,
                 p.tgl_permintaan DESC
        LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<section class="panel h-auto">
    <div class="panel-head flex-wrap border-0 pb-1">
        <i class="bi bi-clipboard2-pulse"></i>
        <h2><?= $saya['role'] === 'PERAWAT' ? 'Permintaan Ruangan ' . e($saya['nama_ruangan'] ?? '')
               : ($saya['role'] === 'DOKTER' ? 'Permintaan Pasien Saya' : 'Permintaan Darah dari Ruangan') ?></h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="No. permintaan / pasien / RM..." aria-label="Cari permintaan">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($boleh_buat): ?>
            <a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Buat Permintaan</a>
        <?php endif; ?>
    </div>

    <div class="panel-tabs">
        <ul class="nav nav-tabs">
            <?php foreach (['aktif' => 'Sedang berjalan', 'selesai' => 'Selesai / batal', 'semua' => 'Semua'] as $k => $label): ?>
                <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?><?= $cari !== '' ? '&cari=' . urlencode($cari) : '' ?>"><?= $label ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i>
            <p class="mt-2 mb-0"><?= $tab === 'aktif' ? 'Tidak ada permintaan yang sedang berjalan.' : 'Tidak ada permintaan.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>No. Permintaan</th><th>Pasien</th><th>Darah</th><th class="d-none d-md-table-cell">Terpenuhi</th>
                <?php if ($kolom_ruang): ?><th class="d-none d-lg-table-cell">Ruangan</th><?php endif; ?>
                <th>Status</th><th class="text-end"><span class="visually-hidden">Aksi</span></th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $p):
                $bisa_proses = $boleh_proses && in_array($p['status'], STATUS_PERMINTAAN_AKTIF, true);
                $persen = $p['jumlah_kantong'] > 0 ? min(100, round($p['terpenuhi'] / $p['jumlah_kantong'] * 100)) : 0; ?>
                <tr>
                    <td class="text-nowrap"><a href="detail.php?id=<?= (int) $p['id_permintaan'] ?>"><code><?= e($p['no_permintaan']) ?></code></a>
                        <?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?>
                        <small class="d-block text-muted"><?= e(tgl_indo($p['tgl_permintaan'], true)) ?></small></td>
                    <td class="text-nowrap"><?= e($p['nama_pasien']) ?><small class="d-block text-muted"><?= e($p['no_rm']) ?></small></td>
                    <td class="text-nowrap"><span class="fw-semibold"><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?></span></td>
                    <td class="text-nowrap d-none d-md-table-cell">
                        <div class="progres-mini" title="<?= (int) $p['terpenuhi'] ?> dari <?= (int) $p['jumlah_kantong'] ?> kantong terpenuhi">
                            <div class="progres-mini-bar"><span style="width: <?= $persen ?>%"></span></div>
                            <small><?= (int) $p['terpenuhi'] ?>/<?= (int) $p['jumlah_kantong'] ?> ktg</small>
                        </div>
                    </td>
                    <?php if ($kolom_ruang): ?><td class="text-nowrap d-none d-lg-table-cell"><?= e($p['nama_ruangan']) ?></td><?php endif; ?>
                    <td class="text-nowrap"><?= badge_status_permintaan($p['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm <?= $bisa_proses ? 'btn-outline-primary' : 'btn-outline-secondary' ?>" href="detail.php?id=<?= (int) $p['id_permintaan'] ?>">
                            <?= $bisa_proses ? 'Proses <i class="bi bi-arrow-right"></i>' : 'Detail' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
