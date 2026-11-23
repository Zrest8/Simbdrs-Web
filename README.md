# SIMBDRS — Sistem Informasi Manajemen Bank Darah Rumah Sakit

Aplikasi web untuk Bank Darah Rumah Sakit (BDRS). Aplikasi ini mencatat setiap kantong darah sejak diterima dari UDD
sampai ditransfusikan ke pasien, dikembalikan, atau dimusnahkan.
Dibuat untuk tugas mata kuliah **Manajemen Data**, Teknologi Kedokteran semester 3.

> ⚠️ **Semua data di repo ini fiktif.** Tidak ada data pasien sungguhan (UU No. 27 Tahun 2022 tentang
> Pelindungan Data Pribadi). Akun demo hanya untuk dicoba di komputer sendiri (localhost).

## Fitur Utama
- **5 peran:** Admin, Petugas BDRS, Kepala BDRS, Dokter, dan Perawat. Setiap peran punya dashboard sendiri.
- **Hak akses diatur Admin:** Admin mencentang Lihat / Tambah / Ubah / Hapus per menu dan per peran.
- **Akun dokter:** setiap dokter wajib punya akun; akun dibuat otomatis saat Admin menambah dokter.
- Penerimaan kantong dan stok real-time, dengan tanggal kedaluwarsa dihitung otomatis.
- Permintaan darah dengan pencocokan **FEFO** dan uji silang.
- Penyerahan dan transfusi dengan verifikasi no. kantong + No. RM.
- Pengembalian, pemusnahan, peringatan dini, rekomendasi pemesanan, laporan, dan log aktivitas.

**Teknologi:** PHP 8 native + PDO, MySQL/MariaDB, Bootstrap 5, dan Chart.js. Aplikasi dijalankan dengan XAMPP.

## Cara Instal
1. Pasang **XAMPP** (PHP 8.x) dari <https://www.apachefriends.org> di lokasi bawaan `C:\xampp`.
2. Ambil kode dengan **Code → Download ZIP** lalu ekstrak, atau jalankan `git clone https://github.com/Zrest8/Simbdrs-Web.git`.
3. Salin folder **`simbdrs`** ke `C:\xampp\htdocs\`, sehingga ada file `C:\xampp\htdocs\simbdrs\index.php`.
4. Buka **XAMPP Control Panel**, lalu klik **Start** pada Apache dan MySQL.
5. Buka <http://localhost/phpmyadmin> → tab **Import**, lalu impor dua file ini **berurutan**:
   1. `simbdrs/database/simbdrs.sql`: membuat database, 17 tabel, akun dasar, dan hak akses bawaan.
      File ini **menghapus** database `simbdrs` lama jika ada.
   2. `simbdrs/database/data_dummy.sql`: mengisi data contoh 90 hari, akun perawat, dan akun dokter.
6. Buka <http://localhost/simbdrs>, lalu login dengan salah satu akun di bawah.

## Akun Demo
| Peran | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Petugas BDRS | `petugas` / `petugas2` | `petugas123` / `petugas234` |
| Kepala BDRS | `kepala` | `kepala123` |
| Dokter | `dr.andika`, `dr.ratna`, `dr.hendra`, `dr.melati`, `dr.yusuf`, `dr.sari`, `dr.bambang` | `dokter123` |
| Perawat IGD / ICU / Bedah | `igd` / `icu` / `bedah` | `igd123` / `icu12345` / `bedah123` |
| Perawat Interna / Anak | `interna` / `anak` | `interna123` / `anak1234` |

Akun dokter, perawat selain `igd`, dan `petugas2` baru ada setelah `data_dummy.sql` diimpor.

## Kalau Ada Masalah
| Gejala | Solusi |
|---|---|
| `Not Found` atau tampilan berantakan | Pastikan foldernya persis `C:\xampp\htdocs\simbdrs` |
| `Koneksi database gagal` | Nyalakan MySQL dan pastikan `simbdrs.sql` sudah diimpor |
| `Access denied for user 'root'` | Isi password MySQL di `simbdrs/config/koneksi.php` |
| Apache tidak mau Start | Port 80 dipakai program lain; tutup program itu atau ganti port Apache |

## Dokumen
- [`dokumen/PRD.md`](dokumen/PRD.md) berisi kebutuhan, fitur, dan aturan bisnis.
- [`dokumen/ERD.md`](dokumen/ERD.md) berisi diagram relasi tabel dan kamus data.
