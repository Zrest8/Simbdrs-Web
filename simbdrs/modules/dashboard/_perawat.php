<?php
// =====================================================================
// Dashboard PERAWAT — fokus ke ruangannya sendiri (BR-11).
// Di-require oleh dashboard/index.php (sudah ada $pdo, $saya, $stok, $gol_kurang, $sapaan).
// Tabel (SELECT): permintaan_darah, pasien, komponen_darah, kantong_darah, transfusi, dokter, pengguna
//
//   Kartu angka : permintaan berjalan · darah siap di BDRS · darah di ruangan
//                 belum dimulai · transfusi berjalan
//   ① Darah di ruangan (DIKELUARKAN) → yang perlu dicatat mulai/selesai
//   ② Permintaan ruangan yang masih berjalan + tombol Buat Permintaan
//   ③ Stok per golongan (untuk tahu ketersediaan sebelum meminta)
// =====================================================================

require_once __DIR__ . '/../permintaan/_fungsi.php';   // badge_status_permintaan()

$id_ruang    = (int) $saya['id_ruangan'];
$boleh_buat  = hak('permintaan', 'tambah');
$boleh_catat = hak('transfusi', 'ubah');

// ---------- Kartu angka ----------
$st = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM permintaan_darah
          WHERE id_ruangan = :r1 AND status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN')) AS berjalan,
        (SELECT COUNT(*) FROM permintaan_darah
          WHERE id_ruangan = :r2 AND status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN') AND urgensi = 'CITO') AS cito,
        (SELECT COUNT(*) FROM kantong_darah k JOIN permintaan_darah p ON p.id_permintaan = k.id_permintaan_dipesan
          WHERE p.id_ruangan = :r3 AND k.status = 'DIPESAN') AS siap_bdrs,
        (SELECT COUNT(*) FROM transfusi t JOIN kantong_darah k ON k.id_kantong = t.id_kantong
           JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
          WHERE p.id_ruangan = :r4 AND k.status = 'DIKELUARKAN' AND t.tgl_mulai IS NULL) AS belum_mulai,
        (SELECT COUNT(*) FROM transfusi t JOIN kantong_darah k ON k.id_kantong = t.id_kantong
           JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
          WHERE p.id_ruangan = :r5 AND k.status = 'DIKELUARKAN' AND t.tgl_mulai IS NOT NULL) AS berjalan_tf"
);
$st->execute(['r1' => $id_ruang, 'r2' => $id_ruang, 'r3' => $id_ruang, 'r4' => $id_ruang, 'r5' => $id_ruang]);
$angka = $st->fetch();

// ---------- ① Darah di ruangan ----------
$st = $pdo->prepare(
    "SELECT t.id_transfusi, t.tgl_keluar_bdrs, t.tgl_mulai, k.no_kantong, k.gol_darah, k.rhesus, c.kode,
            ps.nama_pasien, ps.no_rm, p.no_permintaan
     FROM transfusi t
     JOIN kantong_darah k    ON k.id_kantong = t.id_kantong
     JOIN komponen_darah c   ON c.id_komponen = k.id_komponen
     JOIN permintaan_darah p ON p.id_permintaan = t.id_permintaan
     JOIN pasien ps          ON ps.id_pasien = t.id_pasien
     WHERE p.id_ruangan = ? AND k.status = 'DIKELUARKAN'
     ORDER BY (t.tgl_mulai IS NULL) DESC, t.tgl_keluar_bdrs"
);
$st->execute([$id_ruang]);
$di_ruang = $st->fetchAll();

// ---------- ② Permintaan ruangan yang berjalan ----------
$st = $pdo->prepare(
    "SELECT p.id_permintaan, p.no_permintaan, p.urgensi, p.status, p.jumlah_kantong, p.tgl_permintaan,
            ps.nama_pasien, ps.no_rm, ps.gol_darah, ps.rhesus, c.kode, dk.nama AS nama_dokter,
            (SELECT COUNT(*) FROM kantong_darah k WHERE k.id_permintaan_dipesan = p.id_permintaan
               AND k.status IN ('DIPESAN','DIKELUARKAN','DITRANSFUSIKAN')) AS terpenuhi
     FROM permintaan_darah p
     JOIN pasien ps        ON ps.id_pasien = p.id_pasien
     JOIN komponen_darah c ON c.id_komponen = p.id_komponen
     JOIN dokter d         ON d.id_dokter = p.id_dokter
     JOIN pengguna dk      ON dk.id_pengguna = d.id_pengguna
     WHERE p.id_ruangan = ? AND p.status IN ('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN')
     ORDER BY (p.urgensi = 'CITO') DESC, p.tgl_permintaan
     LIMIT 10"
);
$st->execute([$id_ruang]);
$permintaan = $st->fetchAll();

$kpi = [
    ['angka' => $angka['berjalan'], 'label' => 'Permintaan berjalan' . ($angka['cito'] ? " · {$angka['cito']} CITO" : ''),
     'ikon' => 'clipboard2-pulse', 'nada' => $angka['cito'] ? 'bahaya' : 'merek', 'href' => url('modules/permintaan/index.php')],
    ['angka' => $angka['siap_bdrs'], 'label' => 'Kantong siap di BDRS', 'ikon' => 'box-seam',
     'nada' => $angka['siap_bdrs'] ? 'waspada' : 'ok', 'href' => url('modules/permintaan/index.php')],
    ['angka' => $angka['belum_mulai'], 'label' => 'Darah di ruangan, belum dimulai', 'ikon' => 'hourglass-split',
     'nada' => $angka['belum_mulai'] ? 'bahaya' : 'ok', 'href' => url('modules/transfusi/index.php')],
    ['angka' => $angka['berjalan_tf'], 'label' => 'Transfusi sedang berjalan', 'ikon' => 'heart-pulse',
     'nada' => 'merek', 'href' => url('modules/transfusi/index.php')],
];

require __DIR__ . '/../../includes/header.php';
?>

<div class="salam d-flex flex-wrap align-items-end gap-2">
    <div>
        <h2><?= e($sapaan) ?>, <?= e($saya['nama']) ?></h2>
        <p><?= e(tgl_indo(date('Y-m-d'))) ?> · Perawat · <?= e($saya['nama_ruangan'] ?? '-') ?></p>
    </div>
    <?php if ($boleh_buat): ?>
        <a class="btn btn-primary ms-auto" href="<?= url('modules/permintaan/tambah.php') ?>"><i class="bi bi-plus-lg"></i> Buat Permintaan Darah</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($kpi as $s): ?>
    <div class="col-6 col-xl-3">
        <a class="panel stat" href="<?= $s['href'] ?>">
            <div class="stat-ikon nada-<?= $s['nada'] ?>"><i class="bi bi-<?= $s['ikon'] ?>" aria-hidden="true"></i></div>
            <div class="min-w-0">
                <div class="stat-angka"><?= (int) $s['angka'] ?></div>
                <div class="stat-label"><?= e($s['label']) ?></div>
            </div>
            <i class="bi bi-chevron-right stat-panah" aria-hidden="true"></i>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- ① Darah di ruangan -->
    <div class="col-12 col-xl-7">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-heart-pulse" aria-hidden="true"></i><h2>Darah di Ruangan</h2>
                <?php if ($boleh_catat && $di_ruang): ?>
                    <a class="btn btn-sm btn-outline-primary ms-auto" href="<?= url('modules/transfusi/index.php') ?>">Catat transfusi <i class="bi bi-arrow-right"></i></a>
                <?php endif; ?>
            </div>
            <?php if (!$di_ruang): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Tidak ada kantong darah di ruangan saat ini.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Kantong</th><th>Pasien</th><th>Diterima</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($di_ruang as $t): ?>
                        <tr>
                            <td class="text-nowrap"><code><?= e($t['no_kantong']) ?></code><small class="d-block fw-semibold"><?= e($t['kode'] . ' ' . $t['gol_darah'] . $t['rhesus']) ?></small></td>
                            <td class="text-nowrap"><?= e($t['nama_pasien']) ?><small class="d-block text-secondary"><?= e($t['no_rm']) ?></small></td>
                            <td class="text-nowrap small"><?= e(tgl_indo($t['tgl_keluar_bdrs'], true)) ?></td>
                            <td class="text-nowrap"><?= $t['tgl_mulai']
                                ? '<span class="badge text-bg-primary">Berjalan sejak ' . e(date('H:i', strtotime($t['tgl_mulai']))) . '</span>'
                                : '<span class="badge text-bg-warning">Belum dimulai</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ③ Stok -->
    <div class="col-12 col-xl-5">
        <?php require __DIR__ . '/_stok.php'; ?>
    </div>

    <!-- ② Permintaan ruangan -->
    <div class="col-12">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i><h2>Permintaan Ruangan <?= e($saya['nama_ruangan'] ?? '') ?></h2>
                <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url('modules/permintaan/index.php') ?>">Lihat semua</a>
            </div>
            <?php if (!$permintaan): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Tidak ada permintaan yang sedang berjalan.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>No. Permintaan</th><th>Pasien</th><th>Darah</th><th class="d-none d-md-table-cell">Dokter</th><th>Terpenuhi</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($permintaan as $p):
                        $persen = $p['jumlah_kantong'] > 0 ? min(100, round($p['terpenuhi'] / $p['jumlah_kantong'] * 100)) : 0; ?>
                        <tr>
                            <td class="text-nowrap"><a href="<?= url('modules/permintaan/detail.php?id=' . (int) $p['id_permintaan']) ?>"><code><?= e($p['no_permintaan']) ?></code></a>
                                <?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?>
                                <small class="d-block text-muted"><?= e(tgl_indo($p['tgl_permintaan'], true)) ?></small></td>
                            <td class="text-nowrap"><?= e($p['nama_pasien']) ?><small class="d-block text-muted"><?= e($p['no_rm']) ?></small></td>
                            <td class="text-nowrap"><b><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?></b></td>
                            <td class="text-nowrap small d-none d-md-table-cell"><?= e($p['nama_dokter']) ?></td>
                            <td class="text-nowrap">
                                <div class="progres-mini"><div class="progres-mini-bar"><span style="width: <?= $persen ?>%"></span></div>
                                    <small><?= (int) $p['terpenuhi'] ?>/<?= (int) $p['jumlah_kantong'] ?> ktg</small></div>
                            </td>
                            <td><?= badge_status_permintaan($p['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
