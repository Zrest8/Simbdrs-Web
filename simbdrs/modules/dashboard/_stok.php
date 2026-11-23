<?php
// Panel "Stok per Golongan Darah" — dipakai semua dashboard (umum, dokter, perawat).
// Butuh: $stok (gol+rh → jumlah) dan $gol_kurang dari dashboard/index.php.
// Kotak bisa diklik ke halaman Stok hanya bila peran punya hak lihat Stok.
?>
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-droplet-half" aria-hidden="true"></i><h2>Stok per Golongan Darah</h2>
                <small class="text-muted ms-auto">kantong tersedia, semua komponen</small>
            </div>
            <div class="panel-body">
                <?php $bisa_klik = hak('stok'); ?>
                <div class="stok-grid">
                    <?php foreach (['A+', 'B+', 'AB+', 'O+', 'A-', 'B-', 'AB-', 'O-'] as $g):
                        $jml = $stok[$g] ?? 0;
                        $is_kurang = in_array($g, $gol_kurang, true);
                        if ($jml === 0)      { $kelas = 'habis';  $ket = 'Habis'; }
                        elseif ($is_kurang)  { $kelas = 'kurang'; $ket = 'Di bawah minimum'; }
                        else                 { $kelas = '';       $ket = 'kantong'; }
                        $tag = $bisa_klik ? 'a' : 'div';
                        $atribut = $bisa_klik ? ' href="' . url('modules/stok/index.php?gol=' . urlencode($g)) . '" title="Lihat kantong ' . e($g) . '"' : ''; ?>
                        <<?= $tag ?> class="stok-tile <?= $kelas ?>"<?= $atribut ?>>
                            <div class="gol"><?= e($g) ?></div>
                            <div class="jml"><?= $jml ?></div>
                            <small class="ket"><?php if ($kelas): ?><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> <?php endif; ?><?= $ket ?></small>
                        </<?= $tag ?>>
                    <?php endforeach; ?>
                </div>
                <div class="stok-legenda">
                    <span><i class="titik titik-aman"></i>Aman</span>
                    <span><i class="titik titik-kurang"></i>Ada komponen di bawah minimum</span>
                    <span><i class="titik titik-habis"></i>Habis</span>
                </div>
                <?php if (array_sum($stok) === 0): ?>
                    <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Belum ada kantong tersedia. Data akan muncul setelah penerimaan kantong dicatat atau data dummy di-import.</p>
                <?php endif; ?>
            </div>
        </section>
