<?php
// =====================================================================
// Master Pengguna — DAFTAR (F-01 / F-02)
// Tabel: pengguna (SELECT), ruangan (JOIN), log_aktivitas (login terakhir)
// Kolom password_hash TIDAK pernah dipilih di halaman ini.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
$saya = wajib_hak('pengguna', 'lihat');
$boleh_tambah = hak('pengguna', 'tambah');
$boleh_ubah   = hak('pengguna', 'ubah');
$boleh_hapus  = hak('pengguna', 'hapus');

$judul = 'Data Pengguna';
$menu_aktif = 'pengguna';

$cari  = trim($_GET['cari'] ?? '');
$peran = $_GET['peran'] ?? '';
$daftar_peran = ['ADMIN', 'PETUGAS', 'KEPALA', 'DOKTER', 'PERAWAT'];

$sql = "SELECT p.id_pengguna, p.nama, p.username, p.role, p.aktif, p.created_at, r.nama_ruangan,
               (SELECT MAX(l.waktu) FROM log_aktivitas l
                 WHERE l.id_pengguna = p.id_pengguna AND l.aksi = 'LOGIN') AS login_terakhir
        FROM pengguna p
        LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan
        WHERE 1 = 1";
$param = [];
if ($cari !== '') {
    $sql .= " AND (p.nama LIKE ? OR p.username LIKE ?)";
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
if (in_array($peran, $daftar_peran, true)) {
    $sql .= " AND p.role = ?";
    $param[] = $peran;
}
$sql .= " ORDER BY p.aktif DESC, FIELD(p.role, 'ADMIN', 'KEPALA', 'PETUGAS', 'DOKTER', 'PERAWAT'), p.nama";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

$warna_peran = ['ADMIN' => 'text-bg-dark', 'KEPALA' => 'text-bg-warning', 'PETUGAS' => 'text-bg-primary', 'DOKTER' => 'text-bg-info', 'PERAWAT' => 'text-bg-secondary'];

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-person-gear"></i><h2>Daftar Pengguna</h2>
        <form class="d-flex gap-2 ms-auto flex-wrap" method="get" role="search">
            <select class="form-select form-select-sm" name="peran" aria-label="Filter peran" style="width:auto" onchange="this.form.submit()">
                <option value="">Semua peran</option>
                <?php foreach ($daftar_peran as $r): ?>
                    <option value="<?= $r ?>" <?= $peran === $r ? 'selected' : '' ?>><?= e(nama_role($r)) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control form-control-sm" style="width:auto" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama / username..." aria-label="Cari pengguna">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($boleh_tambah): ?><a class="btn btn-sm btn-primary" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-person-gear"></i><p class="mt-2 mb-0">Tidak ada pengguna yang cocok.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Ruangan</th><th>Login Terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $r): $sendiri = (int) $r['id_pengguna'] === (int) $saya['id_pengguna']; ?>
                <tr class="<?= $r['aktif'] ? '' : 'text-muted' ?>">
                    <td class="fw-semibold"><?= e($r['nama']) ?>
                        <?php if ($sendiri): ?><span class="badge text-bg-light border ms-1">Anda</span><?php endif; ?></td>
                    <td><code><?= e($r['username']) ?></code></td>
                    <td><span class="badge <?= $warna_peran[$r['role']] ?? 'text-bg-secondary' ?>"><?= e(nama_role($r['role'])) ?></span></td>
                    <td><?= $r['nama_ruangan'] ? e($r['nama_ruangan']) : '<span class="text-secondary">—</span>' ?></td>
                    <td><small><?= $r['login_terakhir'] ? e(tgl_indo($r['login_terakhir'], true)) : '<span class="text-secondary">belum pernah</span>' ?></small></td>
                    <td><?= $r['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($boleh_ubah): ?><a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $r['id_pengguna'] ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                        <?php if ($boleh_hapus): ?>
                        <?php if ($sendiri): ?>
                            <button class="btn btn-sm btn-ikon btn-hapus" type="button" disabled aria-label="Hapus (akun sendiri)" title="Tidak bisa menghapus akun sendiri"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        <?php else: ?>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus akun <?= e(addslashes($r['username'])) ?>? Jika punya jejak aktivitas, akun hanya akan dinonaktifkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_pengguna'] ?>">
                            <button class="btn btn-sm btn-ikon btn-hapus" type="submit" aria-label="Hapus" title="Hapus"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                        <?php endif; ?>
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
