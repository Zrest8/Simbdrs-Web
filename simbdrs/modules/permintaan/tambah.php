<?php
// =====================================================================
// Permintaan Darah — BUAT PERMINTAAN (F-05) · hanya peran RUANGAN
// Tabel: pasien, dokter, komponen_darah (SELECT pilihan)
//        permintaan_darah (INSERT, status MENUNGGU), log_aktivitas (INSERT)
//
//  BR-11  ruangan diambil dari akun yang login (tidak bisa dipilih)
//  BR-13  pasien wajib sudah punya golongan darah + rhesus
//  BR-14  dokter penanggung jawab wajib diisi
// =====================================================================

require __DIR__ . '/../../auth/cek_akses.php';
require __DIR__ . '/_fungsi.php';
$saya = wajib_login(['RUANGAN']);

$judul = 'Buat Permintaan Darah';
$menu_aktif = 'permintaan';

const JUMLAH_MAKS = 20;   // batas wajar satu permintaan (bukan aturan medis)

$pasien = $pdo->query(
    "SELECT id_pasien, no_rm, nama_pasien, gol_darah, rhesus FROM pasien WHERE aktif = 1 ORDER BY nama_pasien"
)->fetchAll();
$dokter = $pdo->query("SELECT id_dokter, nama_dokter, spesialis FROM dokter WHERE aktif = 1 ORDER BY nama_dokter")->fetchAll();
$komponen = $pdo->query("SELECT id_komponen, kode, nama_komponen FROM komponen_darah ORDER BY masa_simpan_hari, kode")->fetchAll();

$f = ['id_pasien' => 0, 'id_dokter' => 0, 'id_komponen' => 0, 'jumlah_kantong' => '1', 'urgensi' => 'BIASA', 'diagnosis' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $f = [
        'id_pasien'      => (int) ($_POST['id_pasien'] ?? 0),
        'id_dokter'      => (int) ($_POST['id_dokter'] ?? 0),
        'id_komponen'    => (int) ($_POST['id_komponen'] ?? 0),
        'jumlah_kantong' => trim($_POST['jumlah_kantong'] ?? ''),
        'urgensi'        => $_POST['urgensi'] ?? '',
        'diagnosis'      => trim($_POST['diagnosis'] ?? ''),
    ];

    // Pasien: harus ada, aktif, dan golongan darahnya sudah tercatat (BR-13)
    $ps = null;
    foreach ($pasien as $row) {
        if ((int) $row['id_pasien'] === $f['id_pasien']) { $ps = $row; }
    }
    $id_dokter_valid   = array_map('intval', array_column($dokter, 'id_dokter'));
    $id_komponen_valid = array_map('intval', array_column($komponen, 'id_komponen'));

    if (!$ps) {
        $error = 'Pilih pasien.';
    } elseif (!$ps['gol_darah'] || !$ps['rhesus']) {
        $error = "Golongan darah pasien {$ps['nama_pasien']} belum tercatat. Minta admin melengkapinya dulu (BR-13).";
    } elseif (!in_array($f['id_dokter'], $id_dokter_valid, true)) {
        $error = 'Pilih dokter penanggung jawab (BR-14).';
    } elseif (!in_array($f['id_komponen'], $id_komponen_valid, true)) {
        $error = 'Pilih komponen darah.';
    } elseif (!ctype_digit($f['jumlah_kantong']) || (int) $f['jumlah_kantong'] < 1 || (int) $f['jumlah_kantong'] > JUMLAH_MAKS) {
        $error = 'Jumlah kantong harus 1–' . JUMLAH_MAKS . '.';
    } elseif (!in_array($f['urgensi'], ['BIASA', 'CITO'], true)) {
        $error = 'Pilih urgensi.';
    } elseif ($f['diagnosis'] === '' || mb_strlen($f['diagnosis']) > 255) {
        $error = 'Diagnosis / indikasi wajib diisi (maksimal 255 karakter).';
    }

    if ($error === '') {
        // Nomor dibuat di dalam transaksi; jika bentrok (dua orang menyimpan bersamaan) coba sekali lagi
        for ($coba = 1; $coba <= 3; $coba++) {
            $pdo->beginTransaction();
            try {
                $no = nomor_permintaan_baru($pdo);
                $stmt = $pdo->prepare(
                    "INSERT INTO permintaan_darah
                        (no_permintaan, id_pasien, id_ruangan, id_peminta, id_dokter, id_komponen, jumlah_kantong, urgensi, diagnosis)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $no, $f['id_pasien'], (int) $saya['id_ruangan'], (int) $saya['id_pengguna'], $f['id_dokter'],
                    $f['id_komponen'], (int) $f['jumlah_kantong'], $f['urgensi'], $f['diagnosis'],
                ]);
                $id = (int) $pdo->lastInsertId();
                catat_log($pdo, 'TAMBAH', 'permintaan_darah', $id,
                    "Permintaan $no: {$f['jumlah_kantong']} kantong untuk {$ps['no_rm']} ({$ps['gol_darah']}{$ps['rhesus']}), {$f['urgensi']}");
                $pdo->commit();

                set_flash('success', "Permintaan $no berhasil dikirim ke BDRS" . ($f['urgensi'] === 'CITO' ? ' sebagai CITO.' : '.'));
                header('Location: detail.php?id=' . $id);
                exit;
            } catch (PDOException $ex) {
                $pdo->rollBack();
                if ($ex->getCode() !== '23000' || $coba === 3) {
                    throw $ex;
                }
            }
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <section class="panel">
            <div class="panel-head"><i class="bi bi-clipboard2-plus"></i><h2>Permintaan Darah — <?= e($saya['nama_ruangan'] ?? '') ?></h2></div>
            <div class="panel-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>
                <?php if (!$pasien || !$dokter || !$komponen): ?>
                    <div class="alert alert-warning">Data master belum lengkap (pasien, dokter, atau komponen darah kosong). Hubungi admin.</div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="id_pasien">Pasien <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_pasien" name="id_pasien" required>
                                <option value="0">— pilih pasien —</option>
                                <?php foreach ($pasien as $ps): $ada_gol = $ps['gol_darah'] && $ps['rhesus']; ?>
                                    <option value="<?= (int) $ps['id_pasien'] ?>" <?= $f['id_pasien'] === (int) $ps['id_pasien'] ? 'selected' : '' ?> <?= $ada_gol ? '' : 'disabled' ?>>
                                        <?= e($ps['no_rm'] . ' — ' . $ps['nama_pasien']) ?> · <?= $ada_gol ? e($ps['gol_darah'] . $ps['rhesus']) : 'golongan darah belum diperiksa' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pasien yang golongan darahnya belum tercatat tidak bisa dipilih (BR-13). Golongan kantong mengikuti golongan pasien.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="id_dokter">Dokter penanggung jawab <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_dokter" name="id_dokter" required>
                                <option value="0">— pilih dokter —</option>
                                <?php foreach ($dokter as $d): ?>
                                    <option value="<?= (int) $d['id_dokter'] ?>" <?= $f['id_dokter'] === (int) $d['id_dokter'] ? 'selected' : '' ?>><?= e($d['nama_dokter']) ?><?= $d['spesialis'] ? ' (' . e($d['spesialis']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="id_komponen">Komponen darah <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_komponen" name="id_komponen" required>
                                <option value="0">— pilih komponen —</option>
                                <?php foreach ($komponen as $c): ?>
                                    <option value="<?= (int) $c['id_komponen'] ?>" <?= $f['id_komponen'] === (int) $c['id_komponen'] ? 'selected' : '' ?>><?= e($c['kode'] . ' — ' . $c['nama_komponen']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="jumlah_kantong">Jumlah kantong <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" min="1" max="<?= JUMLAH_MAKS ?>" id="jumlah_kantong" name="jumlah_kantong" value="<?= e($f['jumlah_kantong']) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <span class="form-label d-block">Urgensi <span class="text-danger">*</span></span>
                            <div class="btn-group w-100" role="group" aria-label="Urgensi">
                                <input type="radio" class="btn-check" name="urgensi" id="urg-biasa" value="BIASA" <?= $f['urgensi'] === 'BIASA' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary" for="urg-biasa">Biasa</label>
                                <input type="radio" class="btn-check" name="urgensi" id="urg-cito" value="CITO" <?= $f['urgensi'] === 'CITO' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-danger" for="urg-cito"><i class="bi bi-lightning-charge-fill"></i> CITO (darurat)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="diagnosis">Diagnosis / indikasi transfusi <span class="text-danger">*</span></label>
                            <input class="form-control" id="diagnosis" name="diagnosis" maxlength="255" value="<?= e($f['diagnosis']) ?>" placeholder="mis. Perdarahan saluran cerna, Hb 6,2 g/dL">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Kirim ke BDRS</button>
                        <a class="btn btn-outline-secondary" href="index.php">Batal</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
