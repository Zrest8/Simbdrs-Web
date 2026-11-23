<?php
// =====================================================================
// Master Komponen Darah — EDIT
// Tabel: komponen_darah (SELECT, UPDATE), kantong_darah (hitung), log_aktivitas (INSERT)
//
// Catatan: tgl_kedaluwarsa kantong disimpan saat penerimaan, jadi mengubah
// masa_simpan_hari di sini TIDAK mengubah kantong yang sudah ada.
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
wajib_hak('komponen', 'ubah');

$judul = 'Edit Komponen Darah';
$menu_aktif = 'komponen';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM komponen_darah WHERE id_komponen = ?");
$stmt->execute([$id]);
$lama = $stmt->fetch();

if (!$lama) {
    set_flash('danger', 'Data komponen tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM kantong_darah WHERE id_komponen = ?");
$stmt->execute([$id]);
$jml_kantong = (int) $stmt->fetchColumn();

// Angka DECIMAL dari MySQL berbentuk "2.0"; tampilkan apa adanya
$k = [
    'kode'                  => $lama['kode'],
    'nama_komponen'         => $lama['nama_komponen'],
    'suhu_min'              => $lama['suhu_min'] ?? '',
    'suhu_max'              => $lama['suhu_max'] ?? '',
    'masa_simpan_hari'      => (string) $lama['masa_simpan_hari'],
    'batas_peringatan_hari' => (string) $lama['batas_peringatan_hari'],
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $k = baca_form_komponen();
    $error = validasi_komponen($k);

    if ($error === '') {
        try {
            $stmt = $pdo->prepare(
                "UPDATE komponen_darah
                 SET kode = ?, nama_komponen = ?, suhu_min = ?, suhu_max = ?, masa_simpan_hari = ?, batas_peringatan_hari = ?
                 WHERE id_komponen = ?"
            );
            $stmt->execute([...nilai_komponen($k), $id]);

            // Angka aturan medis berubah → catat nilai lama dan baru di log
            $ket = "Ubah komponen: {$k['kode']}";
            if ((int) $lama['masa_simpan_hari'] !== (int) $k['masa_simpan_hari']) {
                $ket .= "; masa simpan {$lama['masa_simpan_hari']} → {$k['masa_simpan_hari']} hari";
            }
            if ((int) $lama['batas_peringatan_hari'] !== (int) $k['batas_peringatan_hari']) {
                $ket .= "; peringatan {$lama['batas_peringatan_hari']} → {$k['batas_peringatan_hari']} hari";
            }
            catat_log($pdo, 'UBAH', 'komponen_darah', $id, $ket);

            set_flash('success', "Komponen \"{$k['kode']}\" berhasil diperbarui.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = "Kode \"{$k['kode']}\" sudah dipakai komponen lain.";
            } else {
                throw $ex;
            }
        }
    }
}

$mode_edit = true;
require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
