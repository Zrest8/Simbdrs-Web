<?php
// =====================================================================
// Master Dokter — DAFTAR (F-02)
// Tabel: dokter + pengguna (SELECT; setiap dokter punya akun login), permintaan_darah (hitung pemakaian)
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('dokter', 'lihat');
$boleh_tambah = hak('dokter', 'tambah');
$boleh_ubah   = hak('dokter', 'ubah');
$boleh_hapus  = hak('dokter', 'hapus');

$judul = 'Data Dokter';
$menu_aktif = 'dokter';

$cari = trim($_GET['cari'] ?? '');

// Hitung berapa permintaan darah yang memakai dokter ini (dokter penanggung jawab, BR-14)
$sql = "SELECT d.id_dokter, u.nama AS nama_dokter, d.spesialis, u.username, u.aktif,
               (SELECT MAX(l.waktu) FROM log_aktivitas l
                 WHERE l.id_pengguna = u.id_pengguna AND l.aksi = 'LOGIN') AS login_terakhir,
               (SELECT COUNT(*) FROM permintaan_darah p WHERE p.id_dokter = d.id_dokter) AS jml_permintaan
        FROM dokter d
        JOIN pengguna u ON u.id_pengguna = d.id_pengguna";
$param = [];
if ($cari !== '') {
    $sql .= " WHERE u.nama LIKE ? OR d.spesialis LIKE ? OR u.username LIKE ?";
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
$sql .= " ORDER BY u.aktif DESC, u.nama";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-person-badge"></i><h2>Daftar Dokter</h2>
        <form class="d-flex gap-2 ms-auto" method="get" role="search">
            <input class="form-control form-control-sm" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama / spesialis / username..." aria-label="Cari dokter">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($boleh_tambah): ?><a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-person-badge"></i>
            <p class="mt-2 mb-0"><?= $cari !== '' ? 'Tidak ada dokter dengan kata "' . e($cari) . '".' : 'Belum ada data dokter.' ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th style="width:60px">No</th><th>Nama Dokter</th><th>Spesialis</th><th>Akun Login</th><th>Dipakai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $d): ?>
                <tr class="<?= $d['aktif'] ? '' : 'text-muted' ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($d['nama_dokter']) ?></td>
                    <td><?= $d['spesialis'] !== null && $d['spesialis'] !== '' ? e($d['spesialis']) : '<span class="text-secondary">—</span>' ?></td>
                    <td class="text-nowrap"><code><?= e($d['username']) ?></code>
                        <small class="d-block text-secondary"><?= $d['login_terakhir'] ? 'login ' . e(tgl_indo($d['login_terakhir'], true)) : 'belum pernah login' ?></small></td>
                    <td><small><?= (int) $d['jml_permintaan'] ?> permintaan</small></td>
                    <td><?= $d['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($boleh_ubah): ?><a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $d['id_dokter'] ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                        <?php if ($boleh_hapus): ?>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus dokter <?= e(addslashes($d['nama_dokter'])) ?> beserta akunnya? Jika masih punya riwayat, dokter hanya akan dinonaktifkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $d['id_dokter'] ?>">
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
