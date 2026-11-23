<?php
// =====================================================================
// Dashboard — isi menyesuaikan peran (README bag. 9 "Halaman utama")
//   DOKTER  → dashboard sendiri: _dokter.php  (pasien yang ia tangani)
//   PERAWAT → dashboard sendiri: _perawat.php (ruangannya)
//   Peran lain (bagian tampil bila punya hak di menu terkait, tabel hak_akses):
//   ① Stok per golongan      → semua peran
//   ② Peringatan             → hak lihat Stok
//   ③ Permintaan masuk       → hak lihat Permintaan
//   ④ Grafik bulanan         → hak lihat Laporan
//   Ringkasan data master    → ADMIN
// Tabel yang dibaca (SELECT saja): kantong_darah, komponen_darah, stok_minimal,
// permintaan_darah, pasien, ruangan, transfusi, pemusnahan, + tabel master.
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login();   // semua peran boleh membuka dashboard
$role = $saya['role'];

$judul = 'Dashboard';
$menu_aktif = 'dashboard';

$lihat_peringatan = hak('stok');
$lihat_permintaan = hak('permintaan');
$lihat_grafik     = hak('laporan');
$boleh_musnah     = hak('keluar', 'tambah');

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

$jam = (int) date('H');
$sapaan = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

// Dokter & perawat punya dashboard sendiri (fokus ke pasien / ruangannya)
if ($role === 'DOKTER' || $role === 'PERAWAT') {
    require __DIR__ . '/_' . strtolower($role) . '.php';
    return;   // berhenti di sini; sisa file ini untuk peran lain
}

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
         ORDER BY k.tgl_kedaluwarsa ASC"
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
    [$batas, $param] = batas_data($saya);   // BR-11
    if ($batas !== '') {
        $sql .= " AND $batas";
    }
    // CITO dulu → yang belum disentuh (MENUNGGU) → DIPROSES → TERPENUHI_SEBAGIAN, lalu paling lama menunggu
    $sql .= " ORDER BY (p.urgensi = 'CITO') DESC,
                       FIELD(p.status, 'MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN'),
                       p.tgl_permintaan ASC LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($param);
    $permintaan = $stmt->fetchAll();

    // Jumlah seluruh permintaan berjalan (tabel di atas dibatasi 8 baris)
    $sql_jml = "SELECT COUNT(*) AS total, SUM(p.urgensi = 'CITO') AS cito FROM permintaan_darah p
                WHERE p.status IN ('MENUNGGU', 'DIPROSES', 'TERPENUHI_SEBAGIAN')"
             . ($batas !== '' ? " AND $batas" : '');
    $stmt = $pdo->prepare($sql_jml);
    $stmt->execute($param);
    $jml_permintaan = $stmt->fetch();
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
            (SELECT COUNT(*) FROM dokter d JOIN pengguna u ON u.id_pengguna = d.id_pengguna
              WHERE u.aktif = 1)                             AS dokter,
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
            <div class="stat-ikon"><i class="bi bi-<?= $ikon ?>" aria-hidden="true"></i></div>
            <div><div class="stat-angka"><?= (int) $master[$k] ?></div><div class="stat-label"><?= $lbl ?></div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($lihat_peringatan):
    // Ringkasan angka: setiap kartu bisa diklik menuju daftar yang sesuai
    $kpi = [
        ['angka' => array_sum($stok), 'label' => 'Kantong siap pakai', 'ikon' => 'droplet-fill',
         'nada' => 'merek', 'href' => url('modules/stok/index.php')],
        ['angka' => count($hampir), 'label' => 'Hampir kedaluwarsa', 'ikon' => 'hourglass-split',
         'nada' => count($hampir) ? 'waspada' : 'ok', 'href' => url('modules/stok/index.php?kondisi=hampir')],
        ['angka' => $lewat, 'label' => 'Kedaluwarsa, belum dimusnahkan', 'ikon' => 'x-octagon',
         'nada' => $lewat ? 'bahaya' : 'ok',
         'href' => $boleh_musnah ? url('modules/keluar/index.php') : url('modules/stok/index.php?kondisi=lewat')],
        ['angka' => count($kurang), 'label' => 'Di bawah stok minimal', 'ikon' => 'arrow-down-circle',
         'nada' => count($kurang) ? 'bahaya' : 'ok', 'href' => url('modules/rekomendasi/index.php')],
    ];
?>
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
<?php endif; ?>

<div class="row g-3">
    <!-- ① Stok per golongan -->
    <div class="col-12 <?= $lihat_peringatan ? 'col-xl-7' : '' ?>">
        <?php require __DIR__ . '/_stok.php'; ?>
    </div>

    <!-- ② Peringatan: tingginya mengikuti panel stok, isinya bisa digulir -->
    <?php if ($lihat_peringatan):
        $jml_peringatan = ($lewat ? 1 : 0) + count($kurang) + count($hampir); ?>
    <div class="col-12 col-xl-5">
        <div class="ikut-tinggi">
        <section class="panel panel-gulir">
            <div class="panel-head">
                <i class="bi bi-bell" aria-hidden="true"></i><h2>Peringatan</h2>
                <?php if ($jml_peringatan): ?><span class="badge text-bg-danger"><?= $jml_peringatan ?></span><?php endif; ?>
            </div>
            <?php if (!$jml_peringatan): ?>
                <div class="empty-state"><i class="bi bi-check2-circle"></i><p class="mt-2 mb-0">Tidak ada peringatan. Semua stok aman.</p></div>
            <?php else: ?>
            <ul class="alert-list">
                <?php if ($lewat): ?>
                    <li class="nada-bahaya"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
                        <div><b><?= $lewat ?> kantong sudah kedaluwarsa</b> dan belum dimusnahkan.
                        <?php if ($boleh_musnah): ?><br><a class="small" href="<?= url('modules/keluar/index.php') ?>">Musnahkan / kembalikan →</a><?php endif; ?></div></li>
                <?php endif; ?>
                <?php foreach ($kurang as $k): ?>
                    <li class="nada-bahaya"><i class="bi bi-arrow-down-circle-fill" aria-hidden="true"></i>
                        <div>Stok <b><?= e($k['kode']) ?> <?= e($k['gol_darah'] . $k['rhesus']) ?></b> di bawah minimum<br>
                        <small class="text-muted">tersedia <?= (int) $k['tersedia'] ?> dari minimal <?= (int) $k['jumlah_minimal'] ?> kantong</small></div></li>
                <?php endforeach; ?>
                <?php foreach ($hampir as $h): $sisa = (int) $h['sisa_hari']; ?>
                    <li class="nada-waspada"><i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        <div class="flex-grow-1"><b><?= e($h['kode']) ?> <?= e($h['gol_darah'] . $h['rhesus']) ?></b> · <code><?= e($h['no_kantong']) ?></code><br>
                        <small class="text-muted">Kedaluwarsa <?= e(tgl_indo($h['tgl_kedaluwarsa'])) ?> — pakai lebih dulu atau kembalikan ke UDD</small></div>
                        <span class="badge <?= $sisa <= 1 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= $sisa === 0 ? 'Hari ini' : $sisa . ' hari' ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        </div>
    </div>
    <?php endif; ?>

    <!-- ③ Permintaan masuk -->
    <?php if ($lihat_permintaan): ?>
    <div class="col-12">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>
                <h2>Permintaan Masuk</h2>
                <?php if ((int) $jml_permintaan['total']): ?>
                    <span class="badge text-bg-secondary"><?= (int) $jml_permintaan['total'] ?> berjalan</span>
                    <?php if ((int) $jml_permintaan['cito']): ?><span class="badge badge-cito"><?= (int) $jml_permintaan['cito'] ?> CITO</span><?php endif; ?>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= url('modules/permintaan/index.php') ?>">Lihat semua</a>
            </div>
            <?php if (!$permintaan): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">Tidak ada permintaan yang sedang berjalan.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>No. Permintaan</th><th>Pasien</th><th>Darah</th><th>Ruangan</th><th>Waktu</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($permintaan as $p): ?>
                        <tr>
                            <td class="text-nowrap"><a href="<?= url('modules/permintaan/detail.php?id=' . (int) $p['id_permintaan']) ?>"><code><?= e($p['no_permintaan']) ?></code></a>
                                <?php if ($p['urgensi'] === 'CITO'): ?><span class="badge badge-cito ms-1">CITO</span><?php endif; ?></td>
                            <td class="text-nowrap"><?= e($p['nama_pasien']) ?></td>
                            <td class="text-nowrap"><b><?= e($p['kode']) ?> <?= e($p['gol_darah'] . $p['rhesus']) ?></b> <span class="text-muted">· <?= (int) $p['jumlah_kantong'] ?> ktg</span></td>
                            <td class="text-nowrap"><?= e($p['nama_ruangan']) ?></td>
                            <td class="text-nowrap"><small class="text-muted"><?= e(tgl_indo($p['tgl_permintaan'], true)) ?></small></td>
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
                <i class="bi bi-bar-chart" aria-hidden="true"></i><h2>Pemakaian vs Pemusnahan per Bulan</h2>
                <small class="text-muted ms-auto">6 bulan terakhir</small>
            </div>
            <div class="panel-body"><div style="height:280px"><canvas id="grafikBulanan" role="img" aria-label="Grafik pemakaian dan pemusnahan kantong darah per bulan"></canvas></div></div>
        </section>
    </div>
    <?php endif; ?>
</div>

<?php
if ($grafik) {
    $script_tambahan = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const g = ' . json_encode($grafik) . ';
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = "#6b7280";
new Chart(document.getElementById("grafikBulanan"), {
    type: "bar",
    data: { labels: g.label, datasets: [
        { label: "Dipakai (keluar ke pasien)", data: g.pakai,  backgroundColor: "#9b1c31", borderRadius: 4, maxBarThickness: 36 },
        { label: "Dimusnahkan",                data: g.musnah, backgroundColor: "#cbd0d8", borderRadius: 4, maxBarThickness: 36 }
    ]},
    options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "#eef0f3" }, border: { display: false } },
                         x: { grid: { display: false } } },
               plugins: { legend: { position: "bottom", labels: { usePointStyle: true, boxWidth: 8 } } } }
});
</script>';
}
require __DIR__ . '/../../includes/footer.php';
