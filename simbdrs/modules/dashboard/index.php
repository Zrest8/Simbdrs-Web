<?php
// =====================================================================
// Dashboard — isi menyesuaikan peran (README bag. 9 "Halaman utama")
//   ① Stok per golongan      → semua peran
//   ② Peringatan             → PETUGAS, KEPALA
//   ③ Permintaan masuk       → PETUGAS, RUANGAN (hanya ruangannya, BR-11)
//   ④ Grafik bulanan         → PETUGAS, KEPALA
//   Ringkasan data master    → ADMIN
// Tabel yang dibaca (SELECT saja): kantong_darah, komponen_darah, stok_minimal,
// permintaan_darah, pasien, ruangan, transfusi, pemusnahan, + tabel master.
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login();   // semua peran boleh membuka dashboard
$role = $saya['role'];

$judul = 'Dashboard';
$menu_aktif = 'dashboard';

$lihat_peringatan = in_array($role, ['PETUGAS', 'KEPALA'], true);
$lihat_permintaan = in_array($role, ['PETUGAS', 'RUANGAN'], true);
$lihat_grafik     = in_array($role, ['PETUGAS', 'KEPALA'], true);

// ---------------------------------------------------------------------
// ① Stok per golongan + rhesus (kantong TERSEDIA & belum kedaluwarsa)
// ---------------------------------------------------------------------
$stok = [];
$rows = $pdo->query(
    "SELECT gol_darah, rhesus, COUNT(*) AS jumlah
     FROM kantong_darah
     WHERE status = 'TERSEDIA' AND tgl_kedaluwarsa >= CURDATE()
     GROUP BY gol_darah, rhesus"
)->fetchAll();
foreach ($rows as $r) {
    $stok[$r['gol_darah'] . $r['rhesus']] = (int) $r['jumlah'];
}

// Kombinasi (komponen, golongan, rhesus) di bawah stok minimal (BR-09)
$kurang = $pdo->query(
    "SELECT c.kode, s.gol_darah, s.rhesus, s.jumlah_minimal, COUNT(k.id_kantong) AS tersedia
     FROM stok_minimal s
     JOIN komponen_darah c ON c.id_komponen = s.id_komponen
     LEFT JOIN kantong_darah k
            ON k.id_komponen = s.id_komponen AND k.gol_darah = s.gol_darah AND k.rhesus = s.rhesus
           AND k.status = 'TERSEDIA' AND k.tgl_kedaluwarsa >= CURDATE()
     GROUP BY s.id_stok_minimal, c.kode, s.gol_darah, s.rhesus, s.jumlah_minimal
     HAVING tersedia < s.jumlah_minimal
     ORDER BY tersedia ASC"
)->fetchAll();
$gol_kurang = array_unique(array_map(fn($r) => $r['gol_darah'] . $r['rhesus'], $kurang));

// ---------------------------------------------------------------------
// ② Peringatan dini kedaluwarsa (BR-08) + sudah lewat tapi belum dimusnahkan
// ---------------------------------------------------------------------
$hampir = [];
$lewat = 0;
if ($lihat_peringatan) {
    $hampir = $pdo->query(
        "SELECT k.no_kantong, c.kode, k.gol_darah, k.rhesus, k.tgl_kedaluwarsa,
                DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) AS sisa_hari
         FROM kantong_darah k
         JOIN komponen_darah c ON c.id_komponen = k.id_komponen
         WHERE k.status = 'TERSEDIA'
           AND DATEDIFF(k.tgl_kedaluwarsa, CURDATE()) BETWEEN 0 AND c.batas_peringatan_hari
         ORDER BY k.tgl_kedaluwarsa ASC
         LIMIT 6"
    )->fetchAll();

    $lewat = (int) $pdo->query(
        "SELECT COUNT(*) FROM kantong_darah WHERE status = 'TERSEDIA' AND tgl_kedaluwarsa < CURDATE()"
    )->fetchColumn();
}

// ---------------------------------------------------------------------
// ③ Permintaan yang masih berjalan. CITO di atas, lalu yang paling lama.
// ---------------------------------------------------------------------
$permintaan = [];
if ($lihat_permintaan) {
    $sql = "SELECT p.id_permintaan, p.no_permintaan, p.urgensi, p.status, p.jumlah_kantong, p.tgl_permintaan,
                   ps.nama_pasien, ps.gol_darah, ps.rhesus, c.kode, r.nama_ruangan
            FROM permintaan_darah p
            JOIN pasien ps         ON ps.id_pasien  = p.id_pasien
            JOIN komponen_darah c  ON c.id_komponen = p.id_komponen
            JOIN ruangan r         ON r.id_ruangan  = p.id_ruangan
            WHERE p.status IN ('MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN')";
    $param = [];
    if ($role === 'RUANGAN') {           // BR-11: hanya ruangannya sendiri
        $sql .= " AND p.id_ruangan = ?";
        $param[] = $saya['id_ruangan'];
    }
    // CITO dulu → yang belum disentuh (MENUNGGU) → DIPROSES → TERPENUHI_SEBAGIAN, lalu paling lama menunggu
    $sql .= " ORDER BY (p.urgensi = 'CITO') DESC,
                       FIELD(p.status, 'MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN'),
                       p.tgl_permintaan ASC LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($param);
    $permintaan = $stmt->fetchAll();
}

// ---------------------------------------------------------------------
// ④ Grafik 6 bulan: kantong keluar untuk pasien vs kantong dimusnahkan
// ---------------------------------------------------------------------
$grafik = null;
if ($lihat_grafik) {
    $label = $pakai = $musnah = [];
    $nama_bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    for ($i = 5; $i >= 0; $i--) {
        $t = strtotime("first day of -$i month");
        $kunci = date('Y-m', $t);
        $label[$kunci] = $nama_bln[(int) date('n', $t)] . ' ' . date('y', $t);
        $pakai[$kunci] = 0;
        $musnah[$kunci] = 0;
    }
    $awal = array_key_first($label) . '-01';

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(tgl_keluar_bdrs, '%Y-%m') AS bln, COUNT(*) AS jml
         FROM transfusi WHERE tgl_keluar_bdrs >= ? GROUP BY bln"
    );
    $stmt->execute([$awal]);
    foreach ($stmt as $r) { if (isset($pakai[$r['bln']])) { $pakai[$r['bln']] = (int) $r['jml']; } }

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(tgl_musnah, '%Y-%m') AS bln, COUNT(*) AS jml
         FROM pemusnahan WHERE tgl_musnah >= ? GROUP BY bln"
    );
    $stmt->execute([$awal]);
    foreach ($stmt as $r) { if (isset($musnah[$r['bln']])) { $musnah[$r['bln']] = (int) $r['jml']; } }

    $grafik = ['label' => array_values($label), 'pakai' => array_values($pakai), 'musnah' => array_values($musnah)];
}

// ---------------------------------------------------------------------
// Ringkasan data master (ADMIN)
// ---------------------------------------------------------------------
$master = null;
if ($role === 'ADMIN') {
    $master = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM ruangan WHERE aktif = 1)  AS ruangan,
            (SELECT COUNT(*) FROM dokter WHERE aktif = 1)   AS dokter,
            (SELECT COUNT(*) FROM pasien WHERE aktif = 1)   AS pasien,
            (SELECT COUNT(*) FROM udd_asal WHERE aktif = 1) AS udd,
            (SELECT COUNT(*) FROM komponen_darah)           AS komponen,
            (SELECT COUNT(*) FROM pengguna WHERE aktif = 1) AS pengguna"
    )->fetch();
}

// Label & warna status permintaan
$warna_status = [
    'MENUNGGU'           => 'secondary',
    'DIPROSES'           => 'primary',
    'TERPENUHI_SEBAGIAN' => 'warning',
];

$jam = (int) date('H');
$sapaan = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

require __DIR__ . '/../../includes/header.php';
?>

<div class="salam">
    <h2><?= e($sapaan) ?>, <?= e($saya['nama']) ?></h2>
    <p><?= e(tgl_indo(date('Y-m-d'))) ?> · <?= e(nama_role($role)) ?><?= $saya['nama_ruangan'] ? ' · ' . e($saya['nama_ruangan']) : '' ?></p>
</div>

<?php if ($master): ?>
<div class="row g-3 mb-3">
    <?php foreach ([
        ['ruangan', 'hospital', 'Ruangan'], ['dokter', 'person-badge', 'Dokter'], ['pasien', 'people', 'Pasien'],
        ['udd', 'building', 'UDD Asal'], ['komponen', 'capsule', 'Komponen Darah'], ['pengguna', 'person-gear', 'Pengguna Aktif'],
    ] as [$k, $ikon, $lbl]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="panel stat">
            <div class="stat-ikon"><i class="bi bi-<?= $ikon ?>"></i></div>
            <div><div class="stat-angka"><?= (int) $master[$k] ?></div><div class="stat-label"><?= $lbl ?></div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- ① Stok per golongan -->
    <div class="col-12 <?= $lihat_peringatan ? 'col-xl-7' : '' ?>">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-droplet-half"></i><h2>Stok per Golongan Darah</h2>
                <small class="text-muted ms-auto">kantong tersedia, semua komponen</small>
            </div>
            <div class="panel-body">
                <div class="stok-grid">
                    <?php foreach (['A+', 'B+', 'AB+', 'O+', 'A-', 'B-', 'AB-', 'O-'] as $g):
                        $jml = $stok[$g] ?? 0;
                        $is_kurang = in_array($g, $gol_kurang, true); ?>
                        <div class="stok-tile <?= $is_kurang ? 'kurang' : '' ?>">
                            <?php if ($is_kurang): ?><i class="bi bi-exclamation-circle-fill tanda" title="Ada komponen di bawah stok minimal"></i><?php endif; ?>
                            <div class="gol"><?= e($g) ?></div>
                            <div class="jml"><?= $jml ?></div>
                            <small>kantong</small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (array_sum($stok) === 0): ?>
                    <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Belum ada kantong tersedia. Data akan muncul setelah penerimaan kantong dicatat atau data dummy di-import.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- ② Peringatan -->
    <?php if ($lihat_peringatan): ?>
    <div class="col-12 col-xl-5">
        <section class="panel">
            <div class="panel-head"><i class="bi bi-bell"></i><h2>Peringatan</h2></div>
            <?php if (!$hampir && !$lewat && !$kurang): ?>
                <div class="empty-state"><i class="bi bi-check2-circle"></i><p class="mt-2 mb-0">Tidak ada peringatan. Semua stok aman.</p></div>
            <?php else: ?>
            <ul class="alert-list">
                <?php if ($lewat): ?>
                    <li><i class="bi bi-x-octagon-fill text-danger"></i>
                        <div><b><?= $lewat ?> kantong sudah kedaluwarsa</b> dan belum dimusnahkan.</div></li>
                <?php endif; ?>
                <?php foreach ($hampir as $h): ?>
                    <li><i class="bi bi-hourglass-split text-warning"></i>
                        <div><b><?= e($h['kode']) ?> <?= e($h['gol_darah'] . $h['rhesus']) ?></b> · <?= e($h['no_kantong']) ?><br>
                        <small class="text-muted"><?= (int) $h['sisa_hari'] === 0 ? 'Kedaluwarsa hari ini' : 'Kedaluwarsa ' . (int) $h['sisa_hari'] . ' hari lagi' ?> (<?= e(tgl_indo($h['tgl_kedaluwarsa'])) ?>) — pakai lebih dulu atau kembalikan ke UDD</small></div></li>
                <?php endforeach; ?>
                <?php foreach ($kurang as $k): ?>
                    <li><i class="bi bi-arrow-down-circle-fill text-danger"></i>
                        <div>Stok <b><?= e($k['kode']) ?> <?= e($k['gol_darah'] . $k['rhesus']) ?></b> di bawah minimum<br>
                        <small class="text-muted">tersedia <?= (int) $k['tersedia'] ?> dari minimal <?= (int) $k['jumlah_minimal'] ?> kantong</small></div></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>

    <!-- ③ Permintaan masuk -->
    <?php if ($lihat_permintaan): ?>
    <div class="col-12">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-clipboard2-pulse"></i>
                <h2><?= $role === 'RUANGAN' ? 'Permintaan Ruangan Saya' : 'Permintaan Masuk' ?></h2>
            </div>
            <?php if (!$permintaan): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Tidak ada permintaan yang sedang berjalan.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>No.</th><th>Pasien</th><th>Darah</th><th>Ruangan</th><th>Waktu</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($permintaan as $p): ?>
                        <tr>
                            <td><a href="<?= url('modules/permintaan/detail.php?id=' . (int) $p['id_permintaan']) ?>"><?= e($p['no_permintaan']) ?></a>
                                <?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?></td>
                            <td><?= e($p['nama_pasien']) ?></td>
                            <td><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?> · <?= (int) $p['jumlah_kantong'] ?> ktg</td>
                            <td><?= e($p['nama_ruangan']) ?></td>
                            <td><small><?= e(tgl_indo($p['tgl_permintaan'], true)) ?></small></td>
                            <td><span class="badge badge-status text-bg-<?= $warna_status[$p['status']] ?? 'secondary' ?>"><?= e(str_replace('_', ' ', $p['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>

    <!-- ④ Grafik -->
    <?php if ($grafik): ?>
    <div class="col-12">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-bar-chart"></i><h2>Pemakaian vs Pemusnahan per Bulan</h2>
                <small class="text-muted ms-auto">6 bulan terakhir</small>
            </div>
            <div class="panel-body"><div style="height:280px"><canvas id="grafikBulanan" aria-label="Grafik pemakaian dan pemusnahan kantong darah per bulan"></canvas></div></div>
        </section>
    </div>
    <?php endif; ?>
</div>

<?php
if ($grafik) {
    $script_tambahan = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const g = ' . json_encode($grafik) . ';
new Chart(document.getElementById("grafikBulanan"), {
    type: "bar",
    data: { labels: g.label, datasets: [
        { label: "Dipakai (keluar ke pasien)", data: g.pakai,  backgroundColor: "#9b1c31", borderRadius: 4 },
        { label: "Dimusnahkan",                data: g.musnah, backgroundColor: "#c9ccd1", borderRadius: 4 }
    ]},
    options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
               plugins: { legend: { position: "bottom" } } }
});
</script>';
}
require __DIR__ . '/../../includes/footer.php';
