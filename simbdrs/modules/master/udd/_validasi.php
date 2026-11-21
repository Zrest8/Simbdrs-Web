<?php
// =====================================================================
// Validasi bersama untuk tambah.php dan edit.php (tanpa query database).
// =====================================================================

function baca_form_udd(): array
{
    return [
        'nama_udd' => trim($_POST['nama_udd'] ?? ''),
        'alamat'   => trim($_POST['alamat'] ?? ''),
        'telepon'  => trim($_POST['telepon'] ?? ''),
    ];
}

function validasi_udd(array $u): string
{
    if ($u['nama_udd'] === '') {
        return 'Nama UDD wajib diisi.';
    }
    if (mb_strlen($u['nama_udd']) > 100) {
        return 'Nama UDD maksimal 100 karakter.';
    }
    if (mb_strlen($u['alamat']) > 255) {
        return 'Alamat maksimal 255 karakter.';
    }
    // Telepon boleh kosong; jika diisi hanya angka, spasi, +, -, ( )
    if ($u['telepon'] !== '' && !preg_match('/^[0-9+\-\s()]{5,20}$/', $u['telepon'])) {
        return 'Nomor telepon 5–20 karakter, hanya angka, spasi, dan tanda + - ( ).';
    }
    return '';
}

// Nilai siap simpan: kolom opsional yang kosong → NULL
function nilai_udd(array $u): array
{
    return [
        $u['nama_udd'],
        $u['alamat'] === '' ? null : $u['alamat'],
        $u['telepon'] === '' ? null : $u['telepon'],
    ];
}
