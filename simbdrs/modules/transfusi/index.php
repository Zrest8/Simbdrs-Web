<?php
// =====================================================================
// Transfusi — PENYERAHAN & PENCATATAN (F-07)
// Tabel (SELECT): kantong_darah, permintaan_darah, pasien, ruangan,
//                 komponen_darah, transfusi, pengguna
// Aksi dikirim ke aksi.php:
//   hak "tambah" (petugas) : serahkan → kantong DIPESAN → DIKELUARKAN, baris transfusi dibuat
//   hak "ubah"  (perawat)  : mulai    → cek kantong & pasien di samping tempat tidur, isi tgl_mulai
//                            selesai  → isi tgl_selesai, kantong DIKELUARKAN → DITRANSFUSIKAN
// BR-11: perawat hanya melihat ruangannya; dokter hanya pasien yang ia tangani.
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_hak('transfusi');

$judul = 'Transfusi';
$menu_aktif = 'transfusi';
$boleh_serah = hak('transfusi', 'tambah');   // menyerahkan kantong ke ruangan
$boleh_catat = hak('transfusi', 'ubah');     // mencatat mulai / selesai transfusi
$kolom_ruang = $saya['role'] !== 'PERAWAT';

// BR-11: batas_data() memberi kondisi "p.id_ruangan = ?" / "p.id_dokter = ?" (nilainya angka)
[$batas, $param_batas] = batas_data($saya);
$filter_ruang = $batas === '' ? '' : ' AND ' . str_replace('?', (string) (int) $param_batas[0], $batas);

// ---------- A. Siap diserahkan (hak tambah) ----------
$siap = [];
if ($boleh_serah) {
    $siap = $pdo->query(
        "SELECT k.id_kantong, k.no_kantong, k.gol_darah, k.rhesus, k.tgl_kedaluwarsa,
                DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari,
                p.id_permintaan, p.no_permintaan, p.urgensi, c.kode,
                ps.nama_pasien, ps.no_rm, r.nama_ruangan
         FROM kantong_darah k
         JOIN permintaan_darah p ON p.id_permintaan = k.id_permintaan_dipesan
         JOIN pasien ps          ON ps.id_pasien = p.id_pasien
         JOIN ruangan r          ON r.id_ruangan = p.id_ruangan
         JOIN komponen_darah c   ON c.id_komponen = k.id_komponen
         WHERE k.status = 'DIPESAN'
         ORDER BY (p.urgensi = 'CITO') DESC, p.tgl_permintaan, k.tgl_kedaluwarsa"
    )->fetchAll();
}

// ---------- B. Sedang di ruangan (belum selesai ditransfusikan) ----------
$berjalan = $pdo->query(
    "SELECT t.*, k.no_kantong, k.gol_darah, k.rhesus, c.kode,
            p.no_permintaan, ps.nama_pasien, ps.no_rm, r.nama_ruangan
     FROM transfusi t
     JOIN kantong_darah k    ON k.id_kantong = t.id_kantong
     JOIN komponen_darah c   ON c.id_komponen = k.id_komponen
     JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
     JOIN pasien ps          ON ps.id_pasien = t.id_pasien
     JOIN ruangan r          ON r.id_ruangan = p.id_ruangan
     WHERE k.status = 'DIKELUARKAN'$filter_ruang
     ORDER BY t.tgl_keluar_bdrs"
)->fetchAll();

// ---------- C. Riwayat selesai (30 terakhir) ----------
$riwayat = $pdo->query(
    "SELECT t.*, k.no_kantong, k.gol_darah, k.rhesus, c.kode,
            ps.nama_pasien, ps.no_rm, r.nama_ruangan, pg.nama AS nama_pencatat
     FROM transfusi t
     JOIN kantong_darah k    ON k.id_kantong = t.id_kantong
     JOIN komponen_darah c   ON c.id_komponen = k.id_komponen
     JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
     JOIN pasien ps          ON ps.id_pasien = t.id_pasien
     JOIN ruangan r          ON r.id_ruangan = p.id_ruangan
     JOIN pengguna pg        ON pg.id_pengguna = t.id_pencatat
     WHERE k.status = 'DITRANSFUSIKAN'$filter_ruang
     ORDER BY t.tgl_selesai DESC LIMIT 30"
)->fetchAll();

$sekarang = date('Y-m-d\TH:i');
require __DIR__ . '/../../includes/header.php';
?>

<?php if ($boleh_serah): ?>
<section class="panel h-auto mb-3">
    <div class="panel-head"><i class="bi bi-box-arrow-right"></i><h2>Siap Diserahkan ke Ruangan</h2>
        <span class="ms-auto small text-secondary">kantong DIPESAN (lolos uji silang) · CITO di atas</span></div>
    <?php if (!$siap): ?>
        <div class="empty-state py-4"><i class="bi bi-check2-all"></i><p class="mt-2 mb-0">Tidak ada kantong yang menunggu diserahkan.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Permintaan</th><th>Pasien</th><th>Ruangan</th><th>Kantong</th><th>Kedaluwarsa</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($siap as $k): $lewat = (int) $k['sisa_hari'] < 0; ?>
                <tr class="<?= $lewat ? 'table-danger' : '' ?>">
                    <td class="text-nowrap"><a href="<?= url('modules/permintaan/detail.php?id=' . (int) $k['id_permintaan']) ?>"><code><?= e($k['no_permintaan']) ?></code></a>
                        <?php if ($k['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?></td>
                    <td class="text-nowrap"><?= e($k['nama_pasien']) ?><small class="d-block text-secondary"><?= e($k['no_rm']) ?></small></td>
                    <td class="text-nowrap"><?= e($k['nama_ruangan']) ?></td>
                    <td class="text-nowrap"><code><?= e($k['no_kantong']) ?></code> <span class="fw-semibold"><?= e($k['kode'] . ' ' . $k['gol_darah'] . $k['rhesus']) ?></span></td>
                    <td class="text-nowrap small <?= $lewat ? 'text-danger fw-semibold' : '' ?>"><?= e(tgl_indo($k['tgl_kedaluwarsa'])) ?> · <?= e(teks_sisa_hari((int) $k['sisa_hari'])) ?></td>
                    <td class="text-end">
                        <?php if ($lewat): ?>
                            <a class="btn btn-sm btn-outline-danger" href="<?= url('modules/permintaan/detail.php?id=' . (int) $k['id_permintaan']) ?>" title="Kantong kedaluwarsa: lepas dari permintaan, lalu musnahkan">Lepas kantong <i class="bi bi-arrow-right"></i></a>
                        <?php else: ?>
                        <form method="post" action="aksi.php" class="d-inline" onsubmit="return confirm('Serahkan kantong <?= e($k['no_kantong']) ?> ke <?= e(addslashes($k['nama_ruangan'])) ?> sekarang?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="serahkan">
                            <input type="hidden" name="id_kantong" value="<?= (int) $k['id_kantong'] ?>">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-send"></i> Serahkan</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body border-top small text-secondary"><i class="bi bi-info-circle"></i> Darah diantar oleh petugas BDRS (sistem tertutup). Saat diserahkan, status kantong menjadi DIKELUARKAN dan waktu keluar BDRS dicatat.</div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel h-auto mb-3">
    <div class="panel-head"><i class="bi bi-heart-pulse"></i><h2><?= $boleh_catat ? 'Darah Diterima Ruangan — Catat Transfusi' : 'Sedang di Ruangan' ?></h2>
        <span class="ms-auto small text-secondary">status DIKELUARKAN</span></div>
    <?php if (!$berjalan): ?>
        <div class="empty-state py-4"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Tidak ada kantong yang sedang berada di ruangan.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Kantong</th><th>Pasien</th><?php if ($kolom_ruang): ?><th>Ruangan</th><?php endif; ?><th>Keluar BDRS</th><th>Mulai</th><?php if ($boleh_catat): ?><th style="min-width:330px">Pencatatan</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($berjalan as $t): ?>
                <tr>
                    <td class="text-nowrap"><code><?= e($t['no_kantong']) ?></code><small class="d-block fw-semibold"><?= e($t['kode'] . ' ' . $t['gol_darah'] . $t['rhesus']) ?></small></td>
                    <td class="text-nowrap"><?= e($t['nama_pasien']) ?><small class="d-block text-secondary"><?= e($t['no_rm']) ?></small></td>
                    <?php if ($kolom_ruang): ?><td class="text-nowrap"><?= e($t['nama_ruangan']) ?></td><?php endif; ?>
                    <td class="text-nowrap small"><?= e(tgl_indo($t['tgl_keluar_bdrs'], true)) ?></td>
                    <td class="text-nowrap small"><?= $t['tgl_mulai'] ? e(tgl_indo($t['tgl_mulai'], true)) . '<span class="badge text-bg-primary d-block mt-1" style="width:fit-content">Sedang berjalan</span>' : '<span class="badge text-bg-secondary">Belum mulai</span>' ?></td>
                    <?php if ($boleh_catat): ?>
                    <td>
                        <?php if (!$t['tgl_mulai']): ?>
                        <form method="post" action="aksi.php" class="row g-1 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="mulai">
                            <input type="hidden" name="id_transfusi" value="<?= (int) $t['id_transfusi'] ?>">
                            <div class="col-6"><input class="form-control form-control-sm" name="cek_kantong" placeholder="Scan/ketik no. kantong" aria-label="Verifikasi no. kantong" required></div>
                            <div class="col-6"><input class="form-control form-control-sm" name="cek_rm" placeholder="Scan/ketik No. RM gelang" aria-label="Verifikasi No. RM pasien" required></div>
                            <div class="col-8"><input class="form-control form-control-sm" type="datetime-local" name="waktu" value="<?= $sekarang ?>" aria-label="Waktu mulai"></div>
                            <div class="col-4"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-play-fill"></i> Mulai</button></div>
                        </form>
                        <?php else: ?>
                        <form method="post" action="aksi.php" class="row g-1 align-items-center" onsubmit="return confirm('Tandai transfusi kantong <?= e($t['no_kantong']) ?> selesai?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="selesai">
                            <input type="hidden" name="id_transfusi" value="<?= (int) $t['id_transfusi'] ?>">
                            <div class="col-8"><input class="form-control form-control-sm" type="datetime-local" name="waktu" value="<?= $sekarang ?>" aria-label="Waktu selesai"></div>
                            <div class="col-4"><button class="btn btn-sm btn-success w-100" type="submit"><i class="bi bi-check2"></i> Selesai</button></div>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($boleh_catat): ?>
        <div class="panel-body border-top small text-secondary"><i class="bi bi-shield-check"></i> Sebelum mulai, cocokkan <strong>no. kantong</strong> pada label dan <strong>No. RM</strong> pada gelang pasien. Sistem menolak bila tidak sama (mencegah salah pasien). Kolom ini bisa diisi dengan pemindai QR/barcode.</div>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="panel h-auto">
    <div class="panel-head"><i class="bi bi-clock-history"></i><h2>Riwayat Transfusi Selesai</h2><span class="ms-auto small text-secondary">30 terakhir</span></div>
    <?php if (!$riwayat): ?>
        <div class="empty-state py-4"><p class="mb-0">Belum ada transfusi yang selesai.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Kantong</th><th>Pasien</th><?php if ($kolom_ruang): ?><th>Ruangan</th><?php endif; ?><th>Mulai</th><th>Selesai</th></tr></thead>
            <tbody>
            <?php foreach ($riwayat as $t): ?>
                <tr>
                    <td class="text-nowrap"><code><?= e($t['no_kantong']) ?></code> <small class="fw-semibold"><?= e($t['kode'] . ' ' . $t['gol_darah'] . $t['rhesus']) ?></small></td>
                    <td class="text-nowrap"><?= e($t['nama_pasien']) ?><small class="d-block text-secondary"><?= e($t['no_rm']) ?></small></td>
                    <?php if ($kolom_ruang): ?><td class="text-nowrap"><?= e($t['nama_ruangan']) ?></td><?php endif; ?>
                    <td class="text-nowrap small"><?= e(tgl_indo($t['tgl_mulai'], true)) ?></td>
                    <td class="text-nowrap small"><?= e(tgl_indo($t['tgl_selesai'], true)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
