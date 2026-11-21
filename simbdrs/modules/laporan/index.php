<?php
// =====================================================================
// Laporan BDRS per periode (F-12, F-14)
// Isi: ringkasan, rekap per komponen + wastage rate, grafik per bulan,
//      pemusnahan per alasan, pemakaian per golongan, permintaan per status.
//      Bisa dicetak (PDF lewat dialog cetak browser)
//      dan diunduh CSV (dibuka di Excel).
//
// Definisi (PRD): wastage rate = kantong dimusnahkan ÷ kantong diterima,
// keduanya dihitung dari tanggal kejadian di dalam periode.
// Dipakai = kantong keluar BDRS ke pasien (transfusi.tgl_keluar_bdrs).
//
// Tabel (SELECT saja): penerimaan, kantong_darah, komponen_darah, transfusi,
//   pemusnahan, pengembalian, permintaan_darah
// Peran: PETUGAS, KEPALA
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login(['PETUGAS', 'KEPALA']);

$judul = 'Laporan';
$menu_aktif = 'laporan';

// ---------- Periode ----------
function tanggal_valid(?string $s): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', (string) $s);
    return $d && $d->format('Y-m-d') === $s ? $s : null;
}
$dari   = tanggal_valid($_GET['dari'] ?? null) ?? date('Y-m-01');
$sampai = tanggal_valid($_GET['sampai'] ?? null) ?? date('Y-m-d');
$pesan_periode = '';
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
    $pesan_periode = 'Tanggal awal lebih besar dari tanggal akhir, jadi keduanya ditukar.';
}
if ((new DateTime($dari))->diff(new DateTime($sampai))->days > 366) {
    $dari = (new DateTime($sampai))->modify('-366 days')->format('Y-m-d');
    $pesan_periode = 'Periode dibatasi paling lama 1 tahun.';
}
$awal  = $dari . ' 00:00:00';
$akhir = (new DateTime($sampai))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';   // batas atas eksklusif
$P = [$awal, $akhir];

$komponen = $pdo->query("SELECT id_komponen, kode, nama_komponen FROM komponen_darah ORDER BY masa_simpan_hari, kode")->fetchAll();

/** Jalankan query hitung "GROUP BY id_komponen" → [id_komponen => n] */
function per_komponen(PDO $pdo, string $sql, array $p): array
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
}

$terima = per_komponen($pdo,
    "SELECT k.id_komponen, COUNT(*) FROM kantong_darah k JOIN penerimaan pn ON pn.id_penerimaan = k.id_penerimaan
     WHERE pn.tgl_terima >= ? AND pn.tgl_terima < ? GROUP BY k.id_komponen", $P);
$pakai = per_komponen($pdo,
    "SELECT k.id_komponen, COUNT(*) FROM transfusi t JOIN kantong_darah k ON k.id_kantong = t.id_kantong
     WHERE t.tgl_keluar_bdrs >= ? AND t.tgl_keluar_bdrs < ? GROUP BY k.id_komponen", $P);
$musnah = per_komponen($pdo,
    "SELECT k.id_komponen, COUNT(*) FROM pemusnahan m JOIN kantong_darah k ON k.id_kantong = m.id_kantong
     WHERE m.tgl_musnah >= ? AND m.tgl_musnah < ? GROUP BY k.id_komponen", $P);
$kembali = per_komponen($pdo,
    "SELECT k.id_komponen, COUNT(*) FROM pengembalian b JOIN kantong_darah k ON k.id_kantong = b.id_kantong
     WHERE b.tgl_kembali >= ? AND b.tgl_kembali < ? GROUP BY k.id_komponen", $P);

$rekap = [];
$total = ['terima' => 0, 'pakai' => 0, 'kembali' => 0, 'musnah' => 0];
foreach ($komponen as $c) {
    $id = $c['id_komponen'];
    $r = ['kode' => $c['kode'], 'nama' => $c['nama_komponen'],
          'terima' => $terima[$id] ?? 0, 'pakai' => $pakai[$id] ?? 0, 'kembali' => $kembali[$id] ?? 0, 'musnah' => $musnah[$id] ?? 0];
    $r['wastage'] = $r['terima'] > 0 ? $r['musnah'] / $r['terima'] * 100 : null;
    $rekap[] = $r;
    foreach ($total as $f => $_) {
        $total[$f] += $r[$f];
    }
}
$total['wastage'] = $total['terima'] > 0 ? $total['musnah'] / $total['terima'] * 100 : null;
$fmt_persen = fn(?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';

// ---------- Per bulan (grafik) ----------
$bulan = [];
$b = new DateTime(substr($dari, 0, 7) . '-01');
while ($b->format('Y-m') <= substr($sampai, 0, 7)) {
    $bulan[$b->format('Y-m')] = ['terima' => 0, 'pakai' => 0, 'musnah' => 0];
    $b->modify('+1 month');
}
$per_bulan = [
    'terima' => "SELECT DATE_FORMAT(pn.tgl_terima, '%Y-%m'), COUNT(*) FROM kantong_darah k JOIN penerimaan pn ON pn.id_penerimaan = k.id_penerimaan
                 WHERE pn.tgl_terima >= ? AND pn.tgl_terima < ? GROUP BY 1",
    'pakai'  => "SELECT DATE_FORMAT(tgl_keluar_bdrs, '%Y-%m'), COUNT(*) FROM transfusi WHERE tgl_keluar_bdrs >= ? AND tgl_keluar_bdrs < ? GROUP BY 1",
    'musnah' => "SELECT DATE_FORMAT(tgl_musnah, '%Y-%m'), COUNT(*) FROM pemusnahan WHERE tgl_musnah >= ? AND tgl_musnah < ? GROUP BY 1",
];
foreach ($per_bulan as $f => $sql) {
    $st = $pdo->prepare($sql);
    $st->execute($P);
    foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $ym => $n) {
        if (isset($bulan[$ym])) {
            $bulan[$ym][$f] = (int) $n;
        }
    }
}
$nama_bln = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
             '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];
$label_bln = fn(string $ym) => $nama_bln[substr($ym, 5, 2)] . ' ' . substr($ym, 0, 4);

// ---------- Rincian lain ----------
$st = $pdo->prepare("SELECT alasan, COUNT(*) FROM pemusnahan WHERE tgl_musnah >= ? AND tgl_musnah < ? GROUP BY alasan ORDER BY 2 DESC");
$st->execute($P);
$alasan_musnah = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->prepare(
    "SELECT CONCAT(k.gol_darah, k.rhesus) AS gol, COUNT(*) FROM transfusi t JOIN kantong_darah k ON k.id_kantong = t.id_kantong
     WHERE t.tgl_keluar_bdrs >= ? AND t.tgl_keluar_bdrs < ? GROUP BY k.gol_darah, k.rhesus ORDER BY 2 DESC");
$st->execute($P);
$pakai_gol = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->prepare(
    "SELECT status, COUNT(*) AS n, SUM(urgensi = 'CITO') AS cito FROM permintaan_darah
     WHERE tgl_permintaan >= ? AND tgl_permintaan < ? GROUP BY status");
$st->execute($P);
$permintaan = [];
foreach ($st as $r) {
    $permintaan[$r['status']] = ['n' => (int) $r['n'], 'cito' => (int) $r['cito']];
}
$total_permintaan = array_sum(array_column($permintaan, 'n'));

// ---------- Unduh CSV (dibuka di Excel) ----------
if (($_GET['unduh'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_bdrs_' . $dari . '_sd_' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       // BOM agar Excel membaca UTF-8
    $tulis = fn(array $baris) => fputcsv($out, $baris, ';');   // ; = pemisah kolom Excel Indonesia
    $tulis(['Laporan BDRS', 'Periode', $dari, 's.d.', $sampai]);
    $tulis(['Dibuat', date('Y-m-d H:i'), 'oleh', $saya['nama']]);
    $tulis([]);
    $tulis(['Komponen', 'Diterima', 'Dipakai', 'Dikembalikan', 'Dimusnahkan', 'Wastage (%)']);
    foreach ($rekap as $r) {
        $tulis([$r['kode'], $r['terima'], $r['pakai'], $r['kembali'], $r['musnah'], $r['wastage'] === null ? '' : number_format($r['wastage'], 1, ',', '')]);
    }
    $tulis(['TOTAL', $total['terima'], $total['pakai'], $total['kembali'], $total['musnah'], $total['wastage'] === null ? '' : number_format($total['wastage'], 1, ',', '')]);
    $tulis([]);
    $tulis(['Bulan', 'Diterima', 'Dipakai', 'Dimusnahkan']);
    foreach ($bulan as $ym => $v) {
        $tulis([$ym, $v['terima'], $v['pakai'], $v['musnah']]);
    }
    $tulis([]);
    $tulis(['Alasan pemusnahan', 'Jumlah']);
    foreach ($alasan_musnah as $a => $n) {
        $tulis([$a, $n]);
    }
    $tulis([]);
    $tulis(['Golongan (dipakai)', 'Jumlah']);
    foreach ($pakai_gol as $g => $n) {
        $tulis([$g, $n]);
    }
    $tulis([]);
    $tulis(['Status permintaan', 'Jumlah', 'di antaranya CITO']);
    foreach ($permintaan as $s => $v) {
        $tulis([$s, $v['n'], $v['cito']]);
    }
    fclose($out);
    exit;
}

$q = fn(array $ubah) => '?' . http_build_query(array_merge(['dari' => $dari, 'sampai' => $sampai], $ubah));
$pintas = [
    'Bulan ini'    => [date('Y-m-01'), date('Y-m-d')],
    'Bulan lalu'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    '3 bulan'      => [date('Y-m-01', strtotime('first day of -2 month')), date('Y-m-d')],
    'Tahun ini'    => [date('Y-01-01'), date('Y-m-d')],
];

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-none d-print-block mb-3">
    <h4 class="mb-0">Laporan Bank Darah Rumah Sakit</h4>
    <div class="small">Periode <?= e(tgl_indo($dari)) ?> s.d. <?= e(tgl_indo($sampai)) ?> · dicetak <?= e(tgl_indo(date('Y-m-d H:i:s'), true)) ?> oleh <?= e($saya['nama']) ?></div>
</div>

<section class="panel h-auto mb-3 d-print-none">
    <div class="panel-body">
        <form class="d-flex flex-wrap align-items-end gap-2" method="get">
            <div><label class="form-label small mb-1" for="dari">Dari</label><input class="form-control form-control-sm" type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
            <div><label class="form-label small mb-1" for="sampai">Sampai</label><input class="form-control form-control-sm" type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>" max="<?= date('Y-m-d') ?>"></div>
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> Tampilkan</button>
            <div class="btn-group btn-group-sm ms-lg-2" role="group" aria-label="Periode cepat">
                <?php foreach ($pintas as $label => [$a, $z]): ?>
                    <a class="btn btn-outline-secondary <?= $a === $dari && $z === $sampai ? 'active' : '' ?>" href="?dari=<?= $a ?>&amp;sampai=<?= $z ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a class="btn btn-sm btn-outline-success" href="<?= e($q(['unduh' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> Unduh Excel (CSV)</a>
                <button class="btn btn-sm btn-outline-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Cetak / PDF</button>
            </div>
        </form>
        <?php if ($pesan_periode): ?><div class="small text-warning-emphasis mt-2"><i class="bi bi-info-circle"></i> <?= e($pesan_periode) ?></div><?php endif; ?>
    </div>
</section>

<div class="row g-3 mb-3">
    <?php
    $kartu = [
        ['Diterima', $total['terima'], 'box-arrow-in-down', ''],
        ['Dipakai', $total['pakai'], 'heart-pulse', ''],
        ['Dikembalikan', $total['kembali'], 'arrow-return-left', ''],
        ['Dimusnahkan', $total['musnah'], 'trash3', ''],
        ['Wastage rate', $fmt_persen($total['wastage']), 'percent', $total['wastage'] !== null && $total['wastage'] > 0 ? 'text-danger' : ''],
    ];
    foreach ($kartu as [$label, $nilai, $ikon, $kelas]): ?>
    <div class="col-6 col-md-4 col-xl">
        <div class="panel stat h-auto"><div class="panel-body">
            <div class="small text-secondary"><i class="bi bi-<?= $ikon ?>"></i> <?= $label ?></div>
            <div class="fs-3 fw-bold <?= $kelas ?>"><?= e((string) $nilai) ?></div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xxl-7">
        <section class="panel h-auto">
            <div class="panel-head"><i class="bi bi-table"></i><h2>Rekap per Komponen</h2></div>
            <div class="table-responsive">
                <table class="table align-middle text-end mb-0">
                    <thead><tr><th class="text-start">Komponen</th><th>Diterima</th><th>Dipakai</th><th>Dikembalikan</th><th>Dimusnahkan</th><th>Wastage</th></tr></thead>
                    <tbody>
                    <?php foreach ($rekap as $r): ?>
                        <tr>
                            <td class="text-start fw-semibold" title="<?= e($r['nama']) ?>"><?= e($r['kode']) ?></td>
                            <td><?= $r['terima'] ?></td><td><?= $r['pakai'] ?></td><td><?= $r['kembali'] ?></td><td><?= $r['musnah'] ?></td>
                            <td class="<?= $r['wastage'] ? 'text-danger fw-semibold' : '' ?>"><?= $fmt_persen($r['wastage']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="fw-bold">
                        <td class="text-start">Total</td><td><?= $total['terima'] ?></td><td><?= $total['pakai'] ?></td><td><?= $total['kembali'] ?></td><td><?= $total['musnah'] ?></td><td><?= $fmt_persen($total['wastage']) ?></td>
                    </tr></tfoot>
                </table>
            </div>
            <div class="panel-body border-top small text-secondary">
                Wastage rate = dimusnahkan ÷ diterima dalam periode yang sama. Kantong yang dimusnahkan bisa saja diterima sebelum periode ini, jadi angka per bulan bisa naik-turun; bacalah bersama tren beberapa bulan.
            </div>
        </section>
    </div>
    <div class="col-xxl-5">
        <section class="panel h-auto">
            <div class="panel-head"><i class="bi bi-bar-chart"></i><h2>Tren per Bulan</h2></div>
            <div class="panel-body"><div style="height:280px"><canvas id="grafikLaporan" aria-label="Grafik diterima, dipakai, dimusnahkan per bulan"></canvas></div></div>
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6 col-xl-4">
        <section class="panel h-auto">
            <div class="panel-head"><i class="bi bi-droplet"></i><h2>Dipakai per Golongan</h2></div>
            <?php if (!$pakai_gol): ?><div class="panel-body small text-secondary">Tidak ada pemakaian.</div><?php else: ?>
            <table class="table table-sm mb-0"><tbody>
                <?php foreach ($pakai_gol as $g => $n): ?><tr><td class="fw-semibold ps-3"><?= e($g) ?></td><td class="text-end pe-3"><?= (int) $n ?></td></tr><?php endforeach; ?>
            </tbody></table><?php endif; ?>
        </section>
    </div>
    <div class="col-md-6 col-xl-4">
        <section class="panel h-auto">
            <div class="panel-head"><i class="bi bi-trash3"></i><h2>Alasan Pemusnahan</h2></div>
            <?php if (!$alasan_musnah): ?><div class="panel-body small text-secondary">Tidak ada pemusnahan.</div><?php else: ?>
            <table class="table table-sm mb-0"><tbody>
                <?php foreach ($alasan_musnah as $a => $n): ?><tr><td class="ps-3"><?= e(ucfirst(strtolower($a))) ?></td><td class="text-end pe-3"><?= (int) $n ?></td></tr><?php endforeach; ?>
            </tbody></table><?php endif; ?>
        </section>
    </div>
    <div class="col-md-6 col-xl-4">
        <section class="panel h-auto">
            <div class="panel-head"><i class="bi bi-clipboard2-pulse"></i><h2>Permintaan</h2><span class="ms-auto small text-secondary"><?= $total_permintaan ?> total</span></div>
            <?php if (!$permintaan): ?><div class="panel-body small text-secondary">Tidak ada permintaan.</div><?php else: ?>
            <table class="table table-sm mb-0"><tbody>
                <?php foreach ($permintaan as $s => $v): ?><tr><td class="ps-3"><?= e(str_replace('_', ' ', $s)) ?><?= $v['cito'] ? ' <span class="badge badge-cito">' . $v['cito'] . ' CITO</span>' : '' ?></td><td class="text-end pe-3"><?= $v['n'] ?></td></tr><?php endforeach; ?>
            </tbody></table><?php endif; ?>
        </section>
    </div>
</div>

<div class="d-none d-print-flex justify-content-end mt-5 small">
    <div class="text-center">Mengetahui, Kepala BDRS<br><br><br><br>( ................................ )</div>
</div>

<?php
$grafik = [
    'label'  => array_map($label_bln, array_keys($bulan)),
    'terima' => array_column($bulan, 'terima'),
    'pakai'  => array_column($bulan, 'pakai'),
    'musnah' => array_column($bulan, 'musnah'),
];
$script_tambahan = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const g = ' . json_encode($grafik) . ';
new Chart(document.getElementById("grafikLaporan"), {
    type: "bar",
    data: { labels: g.label, datasets: [
        { label: "Diterima",    data: g.terima, backgroundColor: "#6c8ebf", borderRadius: 4 },
        { label: "Dipakai",     data: g.pakai,  backgroundColor: "#9b1c31", borderRadius: 4 },
        { label: "Dimusnahkan", data: g.musnah, backgroundColor: "#c9ccd1", borderRadius: 4 }
    ]},
    options: { maintainAspectRatio: false, animation: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
               plugins: { legend: { position: "bottom" } } }
});
</script>';
require __DIR__ . '/../../includes/footer.php';
