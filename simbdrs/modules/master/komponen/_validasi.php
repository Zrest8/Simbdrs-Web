<?php
// =====================================================================
// Validasi bersama untuk tambah.php dan edit.php (tanpa query database).
// =====================================================================

function baca_form_komponen(): array
{
    return [
        'kode'                  => strtoupper(trim($_POST['kode'] ?? '')),
        'nama_komponen'         => trim($_POST['nama_komponen'] ?? ''),
        'suhu_min'              => trim($_POST['suhu_min'] ?? ''),
        'suhu_max'              => trim($_POST['suhu_max'] ?? ''),
        'masa_simpan_hari'      => trim($_POST['masa_simpan_hari'] ?? ''),
        'batas_peringatan_hari' => trim($_POST['batas_peringatan_hari'] ?? ''),
    ];
}

function validasi_komponen(array $k): string
{
    if ($k['kode'] === '' || $k['nama_komponen'] === '' || $k['masa_simpan_hari'] === '' || $k['batas_peringatan_hari'] === '') {
        return 'Kode, nama, masa simpan, dan batas peringatan wajib diisi.';
    }
    if (!preg_match('/^[A-Z0-9]{1,10}$/', $k['kode'])) {
        return 'Kode maksimal 10 karakter, hanya huruf dan angka (mis. PRC).';
    }
    if (mb_strlen($k['nama_komponen']) > 100) {
        return 'Nama komponen maksimal 100 karakter.';
    }

    // Suhu: boleh kosong keduanya, tetapi kalau diisi harus berpasangan dan min ≤ max
    if (($k['suhu_min'] === '') !== ($k['suhu_max'] === '')) {
        return 'Suhu minimum dan maksimum harus diisi bersamaan (atau dikosongkan keduanya).';
    }
    if ($k['suhu_min'] !== '') {
        if (!is_numeric($k['suhu_min']) || !is_numeric($k['suhu_max'])) {
            return 'Suhu harus berupa angka (pakai titik untuk desimal, mis. 2.5).';
        }
        if ((float) $k['suhu_min'] < -99.9 || (float) $k['suhu_max'] > 99.9) {
            return 'Suhu harus di antara -99,9 dan 99,9 °C.';
        }
        if ((float) $k['suhu_min'] > (float) $k['suhu_max']) {
            return 'Suhu minimum tidak boleh lebih besar dari suhu maksimum.';
        }
    }

    if (!ctype_digit($k['masa_simpan_hari']) || (int) $k['masa_simpan_hari'] < 1 || (int) $k['masa_simpan_hari'] > 3650) {
        return 'Masa simpan harus bilangan bulat 1–3650 hari.';
    }
    if (!ctype_digit($k['batas_peringatan_hari'])) {
        return 'Batas peringatan harus bilangan bulat (0 atau lebih).';
    }
    if ((int) $k['batas_peringatan_hari'] >= (int) $k['masa_simpan_hari']) {
        return 'Batas peringatan harus lebih kecil dari masa simpan.';
    }
    return '';
}

// Nilai siap simpan: suhu kosong → NULL, angka hari → int
function nilai_komponen(array $k): array
{
    return [
        $k['kode'],
        $k['nama_komponen'],
        $k['suhu_min'] === '' ? null : round((float) $k['suhu_min'], 1),
        $k['suhu_max'] === '' ? null : round((float) $k['suhu_max'], 1),
        (int) $k['masa_simpan_hari'],
        (int) $k['batas_peringatan_hari'],
    ];
}
