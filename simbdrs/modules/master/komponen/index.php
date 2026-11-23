<?php
// =====================================================================
// Master Komponen Darah — DAFTAR (F-02)
// Tabel: komponen_darah (SELECT), kantong_darah, permintaan_darah,
//        stok_minimal (hitung pemakaian)
//
// Angka di tabel ini (masa simpan, batas peringatan) adalah aturan medis
// yang dipakai BR-01 dan BR-08. Disimpan di database, bukan di kode.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
wajib_hak('komponen', 'lihat');
$boleh_tambah = hak('komponen', 'tambah');
$boleh_ubah   = hak('komponen', 'ubah');
$boleh_hapus  = hak('komponen', 'hapus');

$judul = 'Data Komponen Darah';
$menu_aktif = 'komponen';

$sql = "SELECT k.*,
               (SELECT COUNT(*) FROM kantong_darah kd   WHERE kd.id_komponen = k.id_komponen) AS jml_kantong,
               (SELECT COUNT(*) FROM permintaan_darah p WHERE p.id_komponen  = k.id_komponen) AS jml_permintaan,
               (SELECT COUNT(*) FROM stok_minimal s     WHERE s.id_komponen  = k.id_komponen) AS jml_stokmin
        FROM komponen_darah k
        ORDER BY k.masa_simpan_hari, k.kode";
$daftar = $pdo->query($sql)->fetchAll();

// Tampilkan suhu "2,0–6,0 °C" atau tanda strip bila kosong
function teks_suhu(?string $min, ?string $max): string
{
    if ($min === null || $max === null) {
        return '—';
    }
    $f = fn($v) => str_replace('.', ',', rtrim(rtrim($v, '0'), '.') ?: '0');
    return $f($min) . '–' . $f($max) . ' °C';
}

require __DIR__ . '/../../../includes/header.php';
?>

<section class="panel">
    <div class="panel-head flex-wrap">
        <i class="bi bi-capsule"></i><h2>Daftar Komponen Darah</h2>
        <?php if ($boleh_tambah): ?><a class="btn btn-sm btn-primary ms-auto" href="tambah.php"><i class="bi bi-plus-lg"></i> Tambah</a><?php endif; ?>
    </div>

    <?php if (!$daftar): ?>
        <div class="empty-state"><i class="bi bi-capsule"></i><p class="mt-2 mb-0">Belum ada data komponen darah.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Kode</th><th>Nama Komponen</th><th>Suhu Simpan</th><th>Masa Simpan</th><th>Peringatan</th><th>Dipakai</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $r): ?>
                <?php $dipakai = $r['jml_kantong'] + $r['jml_permintaan'] + $r['jml_stokmin']; ?>
                <tr>
                    <td><span class="kode-komponen"><?= e($r['kode']) ?></span></td>
                    <td class="fw-semibold"><?= e($r['nama_komponen']) ?></td>
                    <td><?= e(teks_suhu($r['suhu_min'], $r['suhu_max'])) ?></td>
                    <td><?= (int) $r['masa_simpan_hari'] ?> hari</td>
                    <td>≤ <?= (int) $r['batas_peringatan_hari'] ?> hari</td>
                    <td><small><?= (int) $r['jml_kantong'] ?> kantong · <?= (int) $r['jml_permintaan'] ?> permintaan</small></td>
                    <td class="text-end text-nowrap">
                        <?php if ($boleh_ubah): ?><a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $r['id_komponen'] ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                        <?php if ($boleh_hapus): ?>
                        <?php if ($dipakai > 0): ?>
                            <button class="btn btn-sm btn-ikon btn-hapus" type="button" disabled aria-label="Hapus (sudah dipakai)" title="Sudah dipakai, tidak bisa dihapus"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        <?php else: ?>
                        <form class="d-inline" method="post" action="hapus.php"
                              onsubmit="return confirm('Hapus komponen <?= e(addslashes($r['kode'])) ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id_komponen'] ?>">
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
    <div class="panel-body border-top small text-secondary">
        <i class="bi bi-info-circle"></i>
        Angka masa simpan dan suhu bersumber dari pedoman pelayanan darah. Masa simpan FFP (365 hari) masih
        <strong>TODO: verifikasi</strong> ke pedoman resmi / dosen sebelum dipakai.
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
