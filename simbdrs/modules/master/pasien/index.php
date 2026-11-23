<?php
// =====================================================================
// Master Pasien — DAFTAR (F-02)
// Tabel: pasien (SELECT), permintaan_darah & transfusi (hitung riwayat)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('pasien', 'lihat');
$boleh_tambah = hak('pasien', 'tambah');
$boleh_ubah   = hak('pasien', 'ubah');
$boleh_hapus  = hak('pasien', 'hapus');

$judul = 'Data Pasien';
$menu_aktif = 'pasien';

$cari = trim($_GET['cari'] ?? '');

// Umur dihitung oleh MySQL dari tgl_lahir (TIMESTAMPDIFF), bukan disimpan di tabel
$sql = "SELECT ps.id_pasien, ps.no_rm, ps.nama_pasien, ps.tgl_lahir, ps.jenis_kelamin,
               ps.gol_darah, ps.rhesus, ps.aktif,
               TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) AS umur,
               (SELECT COUNT(*) FROM permintaan_darah d WHERE d.id_pasien = ps.id_pasien) AS jml_permintaan,
               (SELECT COUNT(*) FROM transfusi t WHERE t.id_pasien = ps.id_pasien)        AS jml_transfusi
        FROM pasien ps";
$param = [];
if ($cari !== '') {
    $sql .= " WHERE ps.no_rm LIKE ? OR ps.nama_pasien LIKE ?";
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
$sql .= " ORDER BY ps.aktif DESC, ps.nama_pasien";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-people"></i><h2>Daftar Pasien</h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="Cari No. RM / nama..." aria-label="Cari pasien">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($boleh_tambah): ?><a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-people"></i>
            <p class="mt-2 mb-0"><?= $cari !== '' ? 'Tidak ada pasien dengan kata "' . e($cari) . '".' : 'Belum ada data pasien.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th style="width:60px">No</th><th>No. RM</th><th>Nama Pasien</th><th>L/P</th><th>Umur</th><th>Gol. Darah</th><th>Riwayat</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $r): ?>
                <tr class="<?= $r['aktif'] ? '' : 'text-muted' ?>">
                    <td><?= $i + 1 ?></td>
                    <td><code><?= e($r['no_rm']) ?></code></td>
                    <td class="fw-semibold"><?= e($r['nama_pasien']) ?><br><small class="text-secondary fw-normal"><?= e(tgl_indo($r['tgl_lahir'])) ?></small></td>
                    <td><?= $r['jenis_kelamin'] === 'L' ? 'L' : 'P' ?></td>
                    <td><?= (int) $r['umur'] ?> th</td>
                    <td>
                        <?php if ($r['gol_darah']): ?>
                            <span class="kode-komponen"><?= e($r['gol_darah'] . $r['rhesus']) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-warning" title="Wajib diisi sebelum permintaan darah diproses (BR-13)">Belum diperiksa</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= (int) $r['jml_permintaan'] ?> permintaan · <?= (int) $r['jml_transfusi'] ?> transfusi</small></td>
                    <td><?= $r['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($boleh_ubah): ?><a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $r['id_pasien'] ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                        <?php if ($boleh_hapus): ?>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus pasien <?= e(addslashes($r['nama_pasien'])) ?>? Jika punya riwayat, pasien hanya akan dinonaktifkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_pasien'] ?>">
                            <button class="btn btn-sm btn-ikon btn-hapus" type="submit" aria-label="Hapus" title="Hapus"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
