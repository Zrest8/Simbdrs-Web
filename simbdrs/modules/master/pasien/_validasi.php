<?php
// =====================================================================
// Validasi bersama untuk tambah.php dan edit.php (tanpa query database).
// Membaca $_POST, mengisi variabel form, dan mengembalikan pesan error
// ('' jika semua valid).
// =====================================================================

function baca_form_pasien(): array
{
    return [
        'no_rm'         => strtoupper(trim($_POST['no_rm'] ?? '')),
        'nama_pasien'   => trim($_POST['nama_pasien'] ?? ''),
        'tgl_lahir'     => trim($_POST['tgl_lahir'] ?? ''),
        'jenis_kelamin' => $_POST['jenis_kelamin'] ?? '',
        'gol_darah'     => $_POST['gol_darah'] ?? '',
        'rhesus'        => $_POST['rhesus'] ?? '',
    ];
}

function validasi_pasien(array $p): string
{
    if ($p['no_rm'] === '' || $p['nama_pasien'] === '' || $p['tgl_lahir'] === '' || $p['jenis_kelamin'] === '') {
        return 'No. RM, nama, tanggal lahir, dan jenis kelamin wajib diisi.';
    }
    if (!preg_match('/^[A-Z0-9\-\.\/]{1,20}$/', $p['no_rm'])) {
        return 'No. RM maksimal 20 karakter, hanya huruf, angka, dan tanda - . /';
    }
    if (mb_strlen($p['nama_pasien']) > 100) {
        return 'Nama pasien maksimal 100 karakter.';
    }

    $tgl = DateTime::createFromFormat('Y-m-d', $p['tgl_lahir']);
    if (!$tgl || $tgl->format('Y-m-d') !== $p['tgl_lahir']) {
        return 'Tanggal lahir tidak valid.';
    }
    if ($p['tgl_lahir'] > date('Y-m-d')) {
        return 'Tanggal lahir tidak boleh di masa depan.';
    }
    if ($p['tgl_lahir'] < '1900-01-01') {
        return 'Tanggal lahir terlalu lampau.';
    }

    if (!in_array($p['jenis_kelamin'], ['L', 'P'], true)) {
        return 'Jenis kelamin tidak valid.';
    }
    if ($p['gol_darah'] !== '' && !in_array($p['gol_darah'], ['A', 'B', 'AB', 'O'], true)) {
        return 'Golongan darah tidak valid.';
    }
    if ($p['rhesus'] !== '' && !in_array($p['rhesus'], ['+', '-'], true)) {
        return 'Rhesus tidak valid.';
    }
    // Golongan dan rhesus diisi berpasangan: dua-duanya atau tidak sama sekali
    if (($p['gol_darah'] === '') !== ($p['rhesus'] === '')) {
        return 'Golongan darah dan rhesus harus diisi bersamaan (atau dikosongkan keduanya jika belum diperiksa).';
    }
    return '';
}

// Ubah '' menjadi NULL untuk kolom yang boleh kosong
function null_jika_kosong(string $v): ?string
{
    return $v === '' ? null : $v;
}
