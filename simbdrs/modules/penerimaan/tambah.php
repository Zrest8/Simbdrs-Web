<?php
// =====================================================================
// Penerimaan Kantong — TERIMA KIRIMAN BARU (F-03)
// Tabel: udd_asal & komponen_darah (SELECT pilihan + masa simpan)
//        kantong_darah (SELECT cek duplikat)
//        penerimaan (INSERT 1 baris), kantong_darah (INSERT N baris, status TERSEDIA)
//        log_aktivitas (INSERT)
//
// Aturan bisnis:
//  BR-01  tgl_kedaluwarsa = tgl_aftap + komponen_darah.masa_simpan_hari (dihitung di sini, bukan diketik)
//  BR-02  no_kantong unik — dicek di form, di database (SELECT), dan dijaga UNIQUE
//  BR-12  setiap kantong yang masuk dicatat di log_aktivitas
// Semua INSERT dalam SATU transaksi: satu kantong gagal → seluruh kiriman batal.
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
$saya = wajib_login(['PETUGAS']);

$judul = 'Terima Kiriman';
$menu_aktif = 'penerimaan';

const MAKS_KANTONG = 100;
const VOLUME_MIN = 10;     // TODO: verifikasi rentang volume per komponen ke pedoman
const VOLUME_MAKS = 1000;

$udd = $pdo->query("SELECT id_udd, nama_udd FROM udd_asal WHERE aktif = 1 ORDER BY nama_udd")->fetchAll();
$komponen = $pdo->query("SELECT id_komponen, kode, nama_komponen, masa_simpan_hari FROM komponen_darah ORDER BY masa_simpan_hari, kode")->fetchAll();
$komponen_by_id = array_column($komponen, null, 'id_komponen');

$baris_kosong = ['no_kantong' => '', 'id_komponen' => '', 'gol_darah' => '', 'rhesus' => '', 'volume_ml' => '', 'tgl_aftap' => ''];
$h = [
    'no_dokumen'  => '',
    'id_udd'      => '',
    'tgl_terima'  => date('Y-m-d\TH:i'),
    'suhu_terima' => '',
    'catatan'     => '',
];
$baris = array_fill(0, 3, $baris_kosong);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    // ---------- 1. Baca input ----------
    $h = [
        'no_dokumen'  => strtoupper(trim($_POST['no_dokumen'] ?? '')),
        'id_udd'      => (string) (int) ($_POST['id_udd'] ?? 0),
        'tgl_terima'  => trim($_POST['tgl_terima'] ?? ''),
        'suhu_terima' => trim($_POST['suhu_terima'] ?? ''),
        'catatan'     => trim($_POST['catatan'] ?? ''),
    ];
    $baris = [];
    foreach (array_values((array) ($_POST['kantong'] ?? [])) as $b) {
        $r = [
            'no_kantong'  => strtoupper(trim($b['no_kantong'] ?? '')),
            'id_komponen' => trim($b['id_komponen'] ?? ''),
            'gol_darah'   => $b['gol_darah'] ?? '',
            'rhesus'      => $b['rhesus'] ?? '',
            'volume_ml'   => trim($b['volume_ml'] ?? ''),
            'tgl_aftap'   => trim($b['tgl_aftap'] ?? ''),
        ];
        if (implode('', $r) !== '') {      // baris yang benar-benar kosong diabaikan
            $baris[] = $r;
        }
    }

    // ---------- 2. Validasi header ----------
    $waktu_terima = DateTime::createFromFormat('Y-m-d\TH:i', $h['tgl_terima']);
    if ($h['no_dokumen'] === '' || mb_strlen($h['no_dokumen']) > 50) {
        $errors[] = 'No. dokumen / surat jalan wajib diisi (maksimal 50 karakter).';
    }
    if (!in_array((int) $h['id_udd'], array_map('intval', array_column($udd, 'id_udd')), true)) {
        $errors[] = 'Pilih UDD asal yang aktif.';
    }
    if (!$waktu_terima || $waktu_terima->format('Y-m-d\TH:i') !== $h['tgl_terima']) {
        $errors[] = 'Waktu terima tidak valid.';
        $waktu_terima = null;
    } elseif ($waktu_terima > new DateTime('+10 minutes')) {
        $errors[] = 'Waktu terima tidak boleh di masa depan.';
    }
    if ($h['suhu_terima'] !== '' && (!is_numeric($h['suhu_terima']) || abs((float) $h['suhu_terima']) > 99.9)) {
        $errors[] = 'Suhu saat diterima harus angka antara -99,9 dan 99,9 °C.';
    }
    if (mb_strlen($h['catatan']) > 1000) {
        $errors[] = 'Catatan maksimal 1000 karakter.';
    }

    // ---------- 3. Validasi tiap kantong + hitung kedaluwarsa (BR-01) ----------
    if (!$baris) {
        $errors[] = 'Isi minimal satu kantong.';
    } elseif (count($baris) > MAKS_KANTONG) {
        $errors[] = 'Maksimal ' . MAKS_KANTONG . ' kantong per penerimaan.';
    }

    $siap = [];            // baris valid + tgl_kedaluwarsa hasil hitung
    $nomor_di_form = [];
    foreach ($baris as $i => $r) {
        $no = 'Baris ' . ($i + 1);
        if ($r['no_kantong'] === '' || $r['id_komponen'] === '' || $r['gol_darah'] === '' || $r['rhesus'] === '' || $r['volume_ml'] === '' || $r['tgl_aftap'] === '') {
            $errors[] = "$no: semua kolom kantong wajib diisi.";
            continue;
        }
        if (!preg_match('/^[A-Z0-9\-\/\.]{3,30}$/', $r['no_kantong'])) {
            $errors[] = "$no: no. kantong 3–30 karakter (huruf, angka, - / .).";
            continue;
        }
        if (isset($nomor_di_form[$r['no_kantong']])) {
            $errors[] = "$no: no. kantong {$r['no_kantong']} sama dengan baris " . $nomor_di_form[$r['no_kantong']] . ' (BR-02).';
            continue;
        }
        $nomor_di_form[$r['no_kantong']] = $i + 1;

        $komp = $komponen_by_id[(int) $r['id_komponen']] ?? null;
        if (!$komp) {
            $errors[] = "$no: komponen tidak valid.";
            continue;
        }
        if (!in_array($r['gol_darah'], ['A', 'B', 'AB', 'O'], true) || !in_array($r['rhesus'], ['+', '-'], true)) {
            $errors[] = "$no: golongan darah / rhesus tidak valid.";
            continue;
        }
        if (!ctype_digit($r['volume_ml']) || (int) $r['volume_ml'] < VOLUME_MIN || (int) $r['volume_ml'] > VOLUME_MAKS) {
            $errors[] = "$no: volume harus " . VOLUME_MIN . '–' . VOLUME_MAKS . ' ml.';
            continue;
        }
        $aftap = DateTime::createFromFormat('!Y-m-d', $r['tgl_aftap']);
        if (!$aftap || $aftap->format('Y-m-d') !== $r['tgl_aftap']) {
            $errors[] = "$no: tanggal aftap tidak valid.";
            continue;
        }
        if ($waktu_terima && $aftap->format('Y-m-d') > $waktu_terima->format('Y-m-d')) {
            $errors[] = "$no: tanggal aftap tidak boleh setelah tanggal terima.";
            continue;
        }

        // BR-01: kedaluwarsa dihitung dari masa simpan komponen di database
        $exp = (clone $aftap)->modify('+' . (int) $komp['masa_simpan_hari'] . ' days');
        if ($waktu_terima && $exp->format('Y-m-d') < $waktu_terima->format('Y-m-d')) {
            $errors[] = "$no: kantong {$r['no_kantong']} sudah kedaluwarsa saat diterima (" . tgl_indo($exp->format('Y-m-d')) . '). Jangan dicatat sebagai stok — tolak/kembalikan ke UDD.';
            continue;
        }
        $siap[] = $r + ['tgl_kedaluwarsa' => $exp->format('Y-m-d'), 'kode' => $komp['kode']];
    }

    // ---------- 4. BR-02: no. kantong sudah ada di database? ----------
    if (!$errors && $siap) {
        $tanda = implode(',', array_fill(0, count($siap), '?'));
        $cek = $pdo->prepare("SELECT no_kantong FROM kantong_darah WHERE no_kantong IN ($tanda)");
        $cek->execute(array_column($siap, 'no_kantong'));
        foreach ($cek->fetchAll(PDO::FETCH_COLUMN) as $dobel) {
            $errors[] = "No. kantong $dobel sudah pernah diterima sebelumnya (BR-02).";
        }
    }

    // Cegah kiriman yang sama dicatat dua kali
    if (!$errors) {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM penerimaan WHERE no_dokumen = ? AND id_udd = ?");
        $cek->execute([$h['no_dokumen'], (int) $h['id_udd']]);
        if ((int) $cek->fetchColumn() > 0) {
            $errors[] = "Dokumen {$h['no_dokumen']} dari UDD ini sudah pernah dicatat.";
        }
    }

    // ---------- 5. Simpan dalam satu transaksi ----------
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO penerimaan (no_dokumen, id_udd, tgl_terima, suhu_terima, id_petugas, catatan)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $h['no_dokumen'], (int) $h['id_udd'], $waktu_terima->format('Y-m-d H:i:00'),
                $h['suhu_terima'] === '' ? null : round((float) $h['suhu_terima'], 1),
                (int) $saya['id_pengguna'], $h['catatan'] === '' ? null : $h['catatan'],
            ]);
            $id_penerimaan = (int) $pdo->lastInsertId();

            $ins = $pdo->prepare(
                "INSERT INTO kantong_darah
                    (no_kantong, id_penerimaan, id_komponen, gol_darah, rhesus, volume_ml, tgl_aftap, tgl_kedaluwarsa, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'TERSEDIA')"
            );
            foreach ($siap as $r) {
                $ins->execute([
                    $r['no_kantong'], $id_penerimaan, (int) $r['id_komponen'], $r['gol_darah'], $r['rhesus'],
                    (int) $r['volume_ml'], $r['tgl_aftap'], $r['tgl_kedaluwarsa'],
                ]);
                catat_log($pdo, 'TERIMA', 'kantong_darah', (int) $pdo->lastInsertId(),
                    "Kantong {$r['no_kantong']} ({$r['kode']} {$r['gol_darah']}{$r['rhesus']}) diterima → TERSEDIA, exp {$r['tgl_kedaluwarsa']}");
            }
            catat_log($pdo, 'TAMBAH', 'penerimaan', $id_penerimaan,
                "Penerimaan {$h['no_dokumen']}: " . count($siap) . ' kantong');

            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            // Bentrok UNIQUE no_kantong (mis. diinput petugas lain di saat bersamaan)
            $errors[] = 'Salah satu no. kantong baru saja tercatat oleh pengguna lain. Periksa lalu simpan ulang.';
        }

        if (!$errors) {
            set_flash('success', count($siap) . " kantong dari dokumen {$h['no_dokumen']} berhasil diterima dan masuk stok (TERSEDIA).");
            header('Location: detail.php?id=' . $id_penerimaan);
            exit;
        }
    }

    if (!$baris) {
        $baris = [$baris_kosong];
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<form method="post" novalidate id="form-terima">
    <?= csrf_field() ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1"><i class="bi bi-x-circle"></i> Kiriman belum disimpan. Perbaiki dulu:</div>
            <ul class="mb-0 small"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (!$udd || !$komponen): ?>
        <div class="alert alert-warning">Data master belum lengkap: minimal harus ada satu <strong>UDD aktif</strong> dan satu <strong>komponen darah</strong>. Minta admin mengisinya terlebih dahulu.</div>
    <?php endif; ?>

    <section class="panel mb-3">
        <div class="panel-head"><i class="bi bi-file-earmark-text"></i><h2>1. Data Kiriman</h2></div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="no_dokumen">No. dokumen / surat jalan <span class="text-danger">*</span></label>
                    <input class="form-control" id="no_dokumen" name="no_dokumen" maxlength="50" value="<?= e($h['no_dokumen']) ?>" placeholder="mis. SJ-2611-001" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="id_udd">UDD asal <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_udd" name="id_udd" required>
                        <option value="">— pilih UDD —</option>
                        <?php foreach ($udd as $u): ?>
                            <option value="<?= (int) $u['id_udd'] ?>" <?= (int) $h['id_udd'] === (int) $u['id_udd'] ? 'selected' : '' ?>><?= e($u['nama_udd']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tgl_terima">Waktu terima <span class="text-danger">*</span></label>
                    <input class="form-control" type="datetime-local" id="tgl_terima" name="tgl_terima" value="<?= e($h['tgl_terima']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="suhu_terima">Suhu saat diterima</label>
                    <div class="input-group">
                        <input class="form-control" type="number" step="0.1" id="suhu_terima" name="suhu_terima" value="<?= e($h['suhu_terima']) ?>" placeholder="mis. 4.0">
                        <span class="input-group-text">°C</span>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="catatan">Catatan</label>
                    <textarea class="form-control" id="catatan" name="catatan" rows="1" maxlength="1000" placeholder="mis. kemasan baik, cool box tersegel (boleh kosong)"><?= e($h['catatan']) ?></textarea>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head flex-wrap">
            <i class="bi bi-droplet"></i><h2>2. Daftar Kantong</h2>
            <span class="small text-secondary ms-2" id="jumlah-kantong"></span>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-tambah-baris"><i class="bi bi-plus-lg"></i> Tambah baris</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle tabel-kantong mb-0">
                <thead>
                    <tr><th style="width:36px">#</th><th>No. Kantong</th><th>Komponen</th><th>Gol.</th><th>Rh</th><th>Volume (ml)</th><th>Tgl Aftap</th><th>Kedaluwarsa <small class="fw-normal text-lowercase">(otomatis)</small></th><th></th></tr>
                </thead>
                <tbody id="isi-kantong">
                <?php foreach ($baris as $i => $r): ?>
                    <tr class="baris-kantong">
                        <td class="nomor text-secondary"><?= $i + 1 ?></td>
                        <td><input class="form-control form-control-sm" name="kantong[<?= $i ?>][no_kantong]" data-f="no_kantong" maxlength="30" value="<?= e($r['no_kantong']) ?>" placeholder="UDD-2611-0001" aria-label="No. kantong"></td>
                        <td><select class="form-select form-select-sm" name="kantong[<?= $i ?>][id_komponen]" data-f="id_komponen" aria-label="Komponen">
                                <option value="">—</option>
                                <?php foreach ($komponen as $c): ?>
                                    <option value="<?= (int) $c['id_komponen'] ?>" data-masa="<?= (int) $c['masa_simpan_hari'] ?>" <?= (string) $r['id_komponen'] === (string) $c['id_komponen'] ? 'selected' : '' ?>><?= e($c['kode']) ?></option>
                                <?php endforeach; ?>
                            </select></td>
                        <td><select class="form-select form-select-sm" name="kantong[<?= $i ?>][gol_darah]" data-f="gol_darah" aria-label="Golongan darah">
                                <option value="">—</option>
                                <?php foreach (['A', 'B', 'AB', 'O'] as $g): ?><option <?= $r['gol_darah'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?>
                            </select></td>
                        <td><select class="form-select form-select-sm" name="kantong[<?= $i ?>][rhesus]" data-f="rhesus" aria-label="Rhesus">
                                <option value="">—</option>
                                <option value="+" <?= $r['rhesus'] === '+' ? 'selected' : '' ?>>+</option>
                                <option value="-" <?= $r['rhesus'] === '-' ? 'selected' : '' ?>>−</option>
                            </select></td>
                        <td><input class="form-control form-control-sm" type="number" min="<?= VOLUME_MIN ?>" max="<?= VOLUME_MAKS ?>" name="kantong[<?= $i ?>][volume_ml]" data-f="volume_ml" value="<?= e($r['volume_ml']) ?>" aria-label="Volume"></td>
                        <td><input class="form-control form-control-sm" type="date" name="kantong[<?= $i ?>][tgl_aftap]" data-f="tgl_aftap" value="<?= e($r['tgl_aftap']) ?>" max="<?= date('Y-m-d') ?>" aria-label="Tanggal aftap"></td>
                        <td class="exp small text-nowrap text-secondary">—</td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-salin" title="Salin baris (komponen, golongan, volume, aftap)"><i class="bi bi-copy"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-hapus" title="Hapus baris"><i class="bi bi-x-lg"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body border-top d-flex flex-wrap gap-3 align-items-center">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan penerimaan</button>
            <a class="btn btn-outline-secondary" href="index.php">Batal</a>
            <small class="text-secondary ms-auto">
                <i class="bi bi-info-circle"></i> Kedaluwarsa = tgl aftap + masa simpan komponen (BR-01). Baris kosong diabaikan.
                Semua kantong tersimpan sekaligus; jika satu salah, tidak ada yang tersimpan.
            </small>
        </div>
    </section>
</form>

<?php
// Pratinjau kedaluwarsa & pengelolaan baris di browser. Server tetap menghitung ulang (sumber kebenaran).
$script_tambahan = <<<'JS'
<script>
(function () {
    const tbody = document.getElementById('isi-kantong');
    const bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    function hitungExp(tr) {
        const komp = tr.querySelector('[data-f=id_komponen]');
        const aftap = tr.querySelector('[data-f=tgl_aftap]').value;
        const sel = komp.options[komp.selectedIndex];
        const sel_exp = tr.querySelector('.exp');
        if (!aftap || !sel || !sel.dataset.masa) { sel_exp.textContent = '—'; sel_exp.className = 'exp small text-nowrap text-secondary'; return; }
        const d = new Date(aftap + 'T00:00:00');
        d.setDate(d.getDate() + parseInt(sel.dataset.masa, 10));
        const hariIni = new Date(); hariIni.setHours(0, 0, 0, 0);
        const sisa = Math.round((d - hariIni) / 86400000);
        sel_exp.innerHTML = d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear()
            + '<br><span>' + (sisa < 0 ? 'SUDAH LEWAT ' + (-sisa) + ' hari' : sisa + ' hari lagi') + '</span>';
        sel_exp.className = 'exp small text-nowrap ' + (sisa < 0 ? 'text-danger fw-semibold' : 'text-success');
    }

    function rapikan() {
        const rows = tbody.querySelectorAll('tr.baris-kantong');
        let terisi = 0;
        rows.forEach((tr, i) => {
            tr.querySelector('.nomor').textContent = i + 1;
            tr.querySelectorAll('[data-f]').forEach(el => { el.name = 'kantong[' + i + '][' + el.dataset.f + ']'; });
            if (tr.querySelector('[data-f=no_kantong]').value.trim() !== '') terisi++;
            hitungExp(tr);
        });
        document.getElementById('jumlah-kantong').textContent = terisi + ' kantong terisi';
    }

    function barisBaru(dari, salinIsi) {
        const tr = dari.cloneNode(true);
        tr.querySelectorAll('[data-f]').forEach(el => {
            const f = el.dataset.f;
            if (!salinIsi || f === 'no_kantong') { el.value = ''; }
            else { el.value = dari.querySelector('[data-f=' + f + ']').value; }
        });
        return tr;
    }

    document.getElementById('btn-tambah-baris').addEventListener('click', () => {
        const rows = tbody.querySelectorAll('tr.baris-kantong');
        const tr = barisBaru(rows[rows.length - 1], false);
        tbody.appendChild(tr);
        rapikan();
        tr.querySelector('[data-f=no_kantong]').focus();
    });

    tbody.addEventListener('click', (ev) => {
        const tr = ev.target.closest('tr');
        if (ev.target.closest('.btn-salin')) {
            const baru = barisBaru(tr, true);
            tr.after(baru);
            rapikan();
            baru.querySelector('[data-f=no_kantong]').focus();
        } else if (ev.target.closest('.btn-hapus')) {
            if (tbody.querySelectorAll('tr.baris-kantong').length > 1) { tr.remove(); }
            else { tr.querySelectorAll('[data-f]').forEach(el => el.value = ''); }
            rapikan();
        }
    });

    tbody.addEventListener('input', (ev) => {
        if (ev.target.dataset.f === 'no_kantong') { ev.target.value = ev.target.value.toUpperCase(); }
        rapikan();
    });
    tbody.addEventListener('change', rapikan);
    rapikan();
})();
</script>
JS;
require __DIR__ . '/../../includes/footer.php';
