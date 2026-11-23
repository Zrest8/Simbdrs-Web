<?php
// =====================================================================
// Master Pengguna — EDIT
// Tabel: pengguna (SELECT, UPDATE), ruangan (SELECT), log_aktivitas (INSERT)
//
// Pengaman:
//  - Password kosong = tidak diubah.
//  - Admin tidak bisa mengubah peran / menonaktifkan akunnya sendiri.
//  - Harus selalu tersisa minimal satu ADMIN aktif.
//  - Akun DOKTER: perannya dikunci (akun dokter dikelola di Master Dokter).
// =====================================================================

require __DIR__ . '/../../../auth/cek_akses.php';
require __DIR__ . '/_validasi.php';
$saya = wajib_hak('pengguna', 'ubah');

$judul = 'Edit Pengguna';
$menu_aktif = 'pengguna';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
$stmt->execute([$id]);
$lama = $stmt->fetch();

if (!$lama) {
    set_flash('danger', 'Data pengguna tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$akun_sendiri = $id === (int) $saya['id_pengguna'];
$akun_dokter  = $lama['role'] === 'DOKTER';
$p = [
    'nama'       => $lama['nama'],
    'username'   => $lama['username'],
    'role'       => $lama['role'],
    'id_ruangan' => (int) $lama['id_ruangan'],
    'password'   => '',
    'password2'  => '',
];
$aktif = (int) $lama['aktif'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $p = baca_form_pengguna();
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Akun sendiri: peran & status dipaksa tetap, apa pun isi form
    if ($akun_sendiri) {
        $p['role'] = $lama['role'];
        $aktif = 1;
    }
    if ($akun_dokter) {
        $p['role'] = 'DOKTER';
    }

    $error = validasi_pengguna($p, false, $akun_dokter ? ['DOKTER'] : PERAN_VALID);

    if ($error === '' && $p['role'] === 'PERAWAT' && !ruangan_boleh($pdo, $p['id_ruangan'], (int) $lama['id_ruangan'])) {
        $error = 'Ruangan yang dipilih tidak ditemukan atau sudah nonaktif.';
    }

    // Jika admin aktif ini akan berhenti jadi admin aktif, pastikan masih ada admin aktif lain
    $tadinya_admin_aktif = $lama['role'] === 'ADMIN' && (int) $lama['aktif'] === 1;
    $nanti_admin_aktif   = $p['role'] === 'ADMIN' && $aktif === 1;
    if ($error === '' && $tadinya_admin_aktif && !$nanti_admin_aktif) {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE role = 'ADMIN' AND aktif = 1 AND id_pengguna <> ?");
        $cek->execute([$id]);
        if ((int) $cek->fetchColumn() === 0) {
            $error = 'Ini satu-satunya admin aktif. Buat atau aktifkan admin lain terlebih dahulu.';
        }
    }

    if ($error === '') {
        $id_ruangan = $p['role'] === 'PERAWAT' ? $p['id_ruangan'] : null;
        try {
            if ($p['password'] !== '') {
                $stmt = $pdo->prepare(
                    "UPDATE pengguna SET nama = ?, username = ?, role = ?, id_ruangan = ?, aktif = ?, password_hash = ? WHERE id_pengguna = ?"
                );
                $stmt->execute([$p['nama'], $p['username'], $p['role'], $id_ruangan, $aktif, password_hash($p['password'], PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE pengguna SET nama = ?, username = ?, role = ?, id_ruangan = ?, aktif = ? WHERE id_pengguna = ?"
                );
                $stmt->execute([$p['nama'], $p['username'], $p['role'], $id_ruangan, $aktif, $id]);
            }

            $ket = "Ubah akun: {$p['username']} (" . nama_role($p['role']) . ', ' . ($aktif ? 'aktif' : 'nonaktif') . ')';
            if ($lama['role'] !== $p['role']) {
                $ket .= "; peran {$lama['role']} → {$p['role']}";
            }
            if ($p['password'] !== '') {
                $ket .= '; password diganti';   // isi password TIDAK pernah ditulis ke log
            }
            catat_log($pdo, 'UBAH', 'pengguna', $id, $ket);

            // Jika mengubah akun sendiri, segarkan data di session (nama di pojok kanan atas)
            if ($akun_sendiri) {
                $_SESSION['pengguna']['nama'] = $p['nama'];
                $_SESSION['pengguna']['username'] = $p['username'];
            }

            set_flash('success', "Akun \"{$p['username']}\" berhasil diperbarui.");
            header('Location: index.php');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = "Username \"{$p['username']}\" sudah dipakai.";
            } else {
                throw $ex;
            }
        }
    }
    $p['password'] = $p['password2'] = '';
}

$ruangan = daftar_ruangan($pdo, (int) $lama['id_ruangan']);
$mode_edit = true;

require __DIR__ . '/../../../includes/header.php';
require __DIR__ . '/_form.php';
require __DIR__ . '/../../../includes/footer.php';
