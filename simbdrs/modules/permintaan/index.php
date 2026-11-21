<?php
// =====================================================================
// Permintaan Darah — DAFTAR (F-05)
// Tabel: permintaan_darah (SELECT), pasien, ruangan, komponen_darah (JOIN),
//        kantong_darah (hitung kantong terpenuhi)
// BR-11: pengguna RUANGAN hanya melihat permintaan ruangannya.
// Urutan: aktif dulu → CITO dulu → MENUNGGU → DIPROSES → TERPENUHI_SEBAGIAN
//         → paling lama menunggu dulu (supaya permintaan baru tidak tenggelam).
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
require __DIR__ . '/_fungsi.php';
$saya = wajib_login(['PETUGAS', 'RUANGAN']);

$judul = 'Permintaan Darah';
$menu_aktif = 'permintaan';

$tab  = in_array($_GET['tab'] ?? '', ['aktif', 'selesai', 'semua'], true) ? $_GET['tab'] : 'aktif';
$cari = trim($_GET['cari'] ?? '');

$where = [];
$param = [];
if ($saya['role'] === 'RUANGAN') {
    $where[] = 'p.id_ruangan = ?';
    $param[] = (int) $saya['id_ruangan'];
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
    <div class="panel-head flex-wrap">
        <i class="bi bi-clipboard2-pulse"></i>
        <h2><?= $saya['role'] === 'RUANGAN' ? 'Permintaan Ruangan ' . e($saya['nama_ruangan'] ?? '') : 'Permintaan Darah dari Ruangan' ?></h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="No. permintaan / pasien / RM..." aria-label="Cari permintaan">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($saya['role'] === 'RUANGAN'): ?>
            <a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Buat Permintaan</a>
        <?php endif; ?>
    </div>

    <div class="panel-body pb-0">
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
            <thead><tr><th>No. Permintaan</th><th>Pasien</th><th>Darah</th><th>Terpenuhi</th>
                <?php if ($saya['role'] === 'PETUGAS'): ?><th>Ruangan</th><?php endif; ?>
                <th>Waktu</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $p): ?>
                <tr>
                    <td class="text-nowrap"><code><?= e($p['no_permintaan']) ?></code>
                        <?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?></td>
                    <td><?= e($p['nama_pasien']) ?><small class="d-block text-secondary"><?= e($p['no_rm']) ?></small></td>
                    <td class="text-nowrap"><span class="fw-semibold"><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?></span></td>
                    <td class="text-nowrap"><?= (int) $p['terpenuhi'] ?> / <?= (int) $p['jumlah_kantong'] ?> ktg</td>
                    <?php if ($saya['role'] === 'PETUGAS'): ?><td><?= e($p['nama_ruangan']) ?></td><?php endif; ?>
                    <td class="text-nowrap"><small><?= e(tgl_indo($p['tgl_permintaan'], true)) ?></small></td>
                    <td><?= badge_status_permintaan($p['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm <?= $saya['role'] === 'PETUGAS' && in_array($p['status'], STATUS_PERMINTAAN_AKTIF, true) ? 'btn-primary' : 'btn-outline-secondary' ?>" href="detail.php?id=<?= (int) $p['id_permintaan'] ?>">
                            <?= $saya['role'] === 'PETUGAS' && in_array($p['status'], STATUS_PERMINTAAN_AKTIF, true) ? '<i class="bi bi-gear"></i> Proses' : '<i class="bi bi-eye"></i> Detail' ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
