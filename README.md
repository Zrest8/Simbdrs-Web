# SIMBDRS — Sistem Informasi Manajemen Bank Darah Rumah Sakit

Aplikasi web untuk Bank Darah Rumah Sakit (BDRS): mencatat setiap kantong darah sejak diterima dari UDD
sampai ditransfusikan ke pasien, dikembalikan, atau dimusnahkan.

Tugas mata kuliah **Manajemen Data** — Teknologi Kedokteran, semester 3.

> ⚠️ **Semua data di repo ini fiktif.** Tidak ada data pasien sungguhan (UU No. 27 Tahun 2022 tentang
> Pelindungan Data Pribadi). Akun demo di bawah hanya untuk dicoba di komputer sendiri (localhost).

## Fitur

- Login dan hak akses untuk 4 peran: Admin, Petugas BDRS, Perawat Ruangan, Kepala BDRS
- Data master: ruangan, dokter, pasien, komponen darah, UDD, stok minimal, pengguna
- Penerimaan kantong (tanggal kedaluwarsa dihitung otomatis) dan stok real-time
- Permintaan darah: pencocokan **FEFO** dan uji silang
- Penyerahan dan transfusi dengan verifikasi no. kantong + No. RM
- Pengembalian & pemusnahan kantong
- Peringatan dini hampir kedaluwarsa dan stok di bawah minimum
- Rekomendasi pemesanan ke UDD, laporan (grafik, *wastage rate*, CSV, cetak/PDF), log aktivitas

## Teknologi

PHP 8 (native, tanpa framework) + PDO · MySQL/MariaDB · Bootstrap 5 · Chart.js · dijalankan dengan XAMPP

## Cara menjalankan di laptop sendiri

1. **Pasang XAMPP** (versi PHP 8.x) dari <https://www.apachefriends.org>, di lokasi bawaan `C:\xampp`.
2. **Ambil kode** dari repo ini, pilih salah satu cara:
   - Tombol hijau **Code → Download ZIP**, lalu ekstrak; atau
   - `git clone https://github.com/Zrest8/Simbdrs-Web.git`
3. **Salin folder `simbdrs`** (yang ada di dalam repo) ke `C:\xampp\htdocs\`, sehingga hasilnya:
   ```
   C:\xampp\htdocs\simbdrs\index.php
   ```
   Nama dan letak folder harus persis seperti ini, karena aplikasi memakai alamat `/simbdrs`
   (`simbdrs/config/app.php`).
4. Buka **XAMPP Control Panel**, lalu **Start** Apache dan MySQL.
5. Buka <http://localhost/phpmyadmin> → tab **Import**, lalu impor dua file ini **berurutan**:
   1. `simbdrs/database/simbdrs.sql` — membuat database `simbdrs`, 17 tabel, 4 akun dasar, dan hak akses bawaan
      (**menghapus** database `simbdrs` lama jika ada);
   2. `simbdrs/database/data_dummy.sql` — mengisi data contoh 90 hari, akun perawat ruangan, dan 7 akun dokter.
6. Buka <http://localhost/simbdrs> dan login dengan salah satu akun demo.

## Akun demo

| Peran | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Petugas BDRS | `petugas` | `petugas123` |
| Petugas BDRS 2 | `petugas2` | `petugas234` |
| Kepala BDRS | `kepala` | `kepala123` |
| Perawat IGD | `igd` | `igd123` |
| Perawat ICU | `icu` | `icu12345` |
| Perawat Bedah | `bedah` | `bedah123` |
| Perawat Interna | `interna` | `interna123` |
| Perawat Anak | `anak` | `anak1234` |
| Dokter (7 akun) | `dr.andika`, `dr.ratna`, `dr.hendra`, `dr.melati`, `dr.yusuf`, `dr.sari`, `dr.bambang` | `dokter123` |

Akun `petugas2`, `icu`, `bedah`, `interna`, `anak`, dan semua akun dokter baru ada setelah `data_dummy.sql` diimpor.
Hak setiap peran bisa diubah Admin lewat menu **Data Master → Hak Akses**.

## Kalau ada masalah

| Gejala | Solusi |
|---|---|
| `Not Found` / halaman tanpa tampilan rapi | Pastikan foldernya persis `C:\xampp\htdocs\simbdrs` |
| `Koneksi database gagal` | Nyalakan MySQL di XAMPP dan pastikan `simbdrs.sql` sudah diimpor |
| `Access denied for user 'root'` | Isi password MySQL laptopmu di `simbdrs/config/koneksi.php` |
| Apache tidak mau Start | Port 80 dipakai program lain; tutup program itu atau ganti port Apache |

## Struktur repo

```
├── simbdrs/              ← aplikasi web (disalin ke C:\xampp\htdocs\simbdrs)
│   ├── auth/             login, logout, cek hak akses
│   ├── config/           pengaturan aplikasi & koneksi database
│   ├── includes/         fungsi bersama & kerangka tampilan
│   ├── modules/          halaman per fitur (master, penerimaan, permintaan, transfusi, ...)
│   ├── assets/           CSS
│   └── database/         simbdrs.sql, data_dummy.sql, generator data dummy
└── dokumen/              PRD, ERD, proposal, dan penjelasan (Markdown + PDF)
```

## Dokumen

| Dokumen | Isi |
|---|---|
| [`dokumen/PRD.md`](dokumen/PRD.md) | Kebutuhan, fitur, dan aturan bisnis |
| [`dokumen/ERD.md`](dokumen/ERD.md) | Rancangan tabel & kamus data |
| [`dokumen/PENJELASAN_SIMBDRS.md`](dokumen/PENJELASAN_SIMBDRS.md) | Cara aplikasi dipakai |
| [`dokumen/PENJELASAN_TABEL_SIMBDRS.md`](dokumen/PENJELASAN_TABEL_SIMBDRS.md) | Penjelasan setiap tabel |
| [`dokumen/ALUR_PEMBUATAN_SIMBDRS.md`](dokumen/ALUR_PEMBUATAN_SIMBDRS.md) | Urutan membangun aplikasi |
| [`dokumen/PANDUAN_BELAJAR.md`](dokumen/PANDUAN_BELAJAR.md) | Cara kerja kode & phpMyAdmin |
