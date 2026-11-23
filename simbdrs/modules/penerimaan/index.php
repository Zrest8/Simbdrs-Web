<?php
// =====================================================================
// Penerimaan Kantong — DAFTAR KIRIMAN (F-03)
// Tabel: penerimaan (SELECT), udd_asal & pengguna (JOIN),
//        kantong_darah + komponen_darah (rekap jumlah per komponen)
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
wajib_hak('penerimaan');

$judul = 'Penerimaan Kantong';
$menu_aktif = 'penerimaan';

$cari = trim($_GET['cari'] ?? '');

// GROUP_CONCAT merangkum isi kiriman, mis. "PRC×4, TC×2"
$sql = "SELECT pn.id_penerimaan, pn.no_dokumen, pn.tgl_terima, pn.suhu_terima,
               u.nama_udd, pg.nama AS nama_petugas,
               COUNT(kd.id_kantong) AS jml_kantong,
               (SELECT GROUP_CONCAT(CONCAT(x.kode, '×', x.jml) ORDER BY x.kode SEPARATOR ', ')
                  FROM (SELECT c.kode, COUNT(*) AS jml, k2.id_penerimaan
                          FROM kantong_darah k2 JOIN komponen_darah c ON c.id_komponen = k2.id_komponen
                         GROUP BY k2.id_penerimaan, c.kode) x
                 WHERE x.id_penerimaan = pn.id_penerimaan) AS rincian
        FROM penerimaan pn
        JOIN udd_asal u  ON u.id_udd = pn.id_udd
        JOIN pengguna pg ON pg.id_pengguna = pn.id_petugas
        LEFT JOIN kantong_darah kd ON kd.id_penerimaan = pn.id_penerimaan";
$param = [];
if ($cari !== '') {
    // cari berdasarkan no. dokumen, nama UDD, atau no. kantong di dalam kiriman
    $sql .= " WHERE pn.no_dokumen LIKE ? OR u.nama_udd LIKE ?
              OR pn.id_penerimaan IN (SELECT id_penerimaan FROM kantong_darah WHERE no_kantong LIKE ?)";
    $param = array_fill(0, 3, '%' . $cari . '%');
}
$sql .= " GROUP BY pn.id_penerimaan ORDER BY pn.tgl_terima DESC, pn.id_penerimaan DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-box-arrow-in-down"></i><h2>Riwayat Penerimaan dari UDD</h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="No. dokumen / UDD / no. kantong..." aria-label="Cari penerimaan">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if (hak('penerimaan', 'tambah')): ?><a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Terima Kiriman</a><?php endif; ?>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-box-seam"></i>
            <p class="mt-2 mb-0"><?= $cari !== '' ? 'Tidak ada penerimaan yang cocok dengan "' . e($cari) . '".' : 'Belum ada kiriman yang diterima. Klik "Terima Kiriman" untuk mencatat kantong pertama.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Waktu Terima</th><th>No. Dokumen</th><th>UDD Asal</th><th>Isi Kiriman</th><th>Suhu</th><th>Petugas</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $r): ?>
                <tr>
                    <td class="text-nowrap"><?= e(tgl_indo($r['tgl_terima'], true)) ?></td>
                    <td><code><?= e($r['no_dokumen']) ?></code></td>
                    <td><?= e($r['nama_udd']) ?></td>
                    <td><span class="fw-semibold"><?= (int) $r['jml_kantong'] ?> kantong</span><br><small class="text-secondary"><?= e($r['rincian'] ?? '') ?></small></td>
                    <td class="text-nowrap"><?= $r['suhu_terima'] !== null ? e(str_replace('.', ',', $r['suhu_terima'])) . ' °C' : '<span class="text-secondary">—</span>' ?></td>
                    <td class="text-nowrap"><small><?= e($r['nama_petugas']) ?></small></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="detail.php?id=<?= (int) $r['id_penerimaan'] ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
