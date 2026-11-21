<?php
// =====================================================================
// Master UDD Asal — DAFTAR (F-02)
// Tabel: udd_asal (SELECT), penerimaan & kantong_darah (hitung riwayat)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_login(['ADMIN']);

$judul = 'Data UDD Asal';
$menu_aktif = 'udd';

$cari = trim($_GET['cari'] ?? '');

// Jumlah kiriman (penerimaan) dan jumlah kantong yang pernah dikirim UDD ini
$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM penerimaan pn WHERE pn.id_udd = u.id_udd) AS jml_kiriman,
               (SELECT COUNT(*) FROM kantong_darah kd
                  JOIN penerimaan pn ON pn.id_penerimaan = kd.id_penerimaan
                 WHERE pn.id_udd = u.id_udd)                                  AS jml_kantong
        FROM udd_asal u";
$param = [];
if ($cari !== '') {
    $sql .= " WHERE u.nama_udd LIKE ? OR u.alamat LIKE ?";
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
$sql .= " ORDER BY u.aktif DESC, u.nama_udd";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-building"></i><h2>Daftar UDD Asal</h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama / alamat..." aria-label="Cari UDD">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-building"></i>
            <p class="mt-2 mb-0"><?= $cari !== '' ? 'Tidak ada UDD dengan kata "' . e($cari) . '".' : 'Belum ada data UDD.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th style="width:60px">No</th><th>Nama UDD</th><th>Alamat</th><th>Telepon</th><th>Riwayat</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $r): ?>
                <tr class="<?= $r['aktif'] ? '' : 'text-muted' ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($r['nama_udd']) ?></td>
                    <td><small><?= $r['alamat'] ? e($r['alamat']) : '<span class="text-secondary">—</span>' ?></small></td>
                    <td class="text-nowrap"><?= $r['telepon'] ? e($r['telepon']) : '<span class="text-secondary">—</span>' ?></td>
                    <td><small><?= (int) $r['jml_kiriman'] ?> kiriman · <?= (int) $r['jml_kantong'] ?> kantong</small></td>
                    <td><?= $r['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $r['id_udd'] ?>"><i class="bi bi-pencil"></i> Edit</a>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus UDD <?= e(addslashes($r['nama_udd'])) ?>? Jika punya riwayat penerimaan, UDD hanya akan dinonaktifkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_udd'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
