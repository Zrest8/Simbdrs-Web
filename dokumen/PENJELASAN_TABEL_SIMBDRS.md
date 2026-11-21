# Penjelasan Tabel Database SIMBDRS

> Dokumen ini menjelaskan **16 tabel** di database `simbdrs` satu per satu: apa isinya, apa arti setiap kolom, aturan apa yang dijaga, dan tabel itu terhubung ke tabel mana saja.
>
> Sumber: `simbdrs/database/simbdrs.sql`. Semua contoh data di dokumen ini **fiktif** (UU 27/2022 tentang Pelindungan Data Pribadi).
>
> Pasangan dokumen ini:
> - `ERD.md` → gambar relasi
> - `PENJELASAN_SIMBDRS.md` → alur pemakaian aplikasi
> - `ALUR_PEMBUATAN_SIMBDRS.md` → urutan membangun aplikasi

**Isi**
1. Istilah yang perlu dipahami dulu
2. Peta 16 tabel
3. Tabel master (8 tabel)
4. Tabel transaksi (7 tabel)
5. Tabel tata kelola (1 tabel)
6. Daftar 24 foreign key
7. Contoh: menelusuri satu kantong dengan JOIN
8. Ringkasan untuk presentasi

---

## 1. Istilah yang perlu dipahami dulu

### Kunci & aturan

| Istilah | Arti sederhana | Contoh di SIMBDRS |
|---|---|---|
| **PRIMARY KEY (PK)** | Nomor identitas unik tiap baris, seperti NIK. Tidak boleh kembar, tidak boleh kosong | `id_kantong` |
| **AUTO_INCREMENT** | Nomornya diisi otomatis oleh MySQL: 1, 2, 3, … | semua kolom `id_…` |
| **FOREIGN KEY (FK)** | Kolom yang **menunjuk** ke PK tabel lain. MySQL menolak kalau yang ditunjuk tidak ada | `kantong_darah.id_penerimaan` → `penerimaan` |
| **UNIQUE** | Nilainya tidak boleh kembar, walaupun bukan PK | `pasien.no_rm`, `kantong_darah.no_kantong` |
| **NOT NULL** | Wajib diisi | `pasien.nama_pasien` |
| **NULL** | Boleh kosong | `pasien.gol_darah` (belum diperiksa) |
| **DEFAULT** | Nilai otomatis kalau tidak diisi | `status` default `'TERSEDIA'` |
| **CHECK** | Syarat tambahan yang dicek MySQL | `jumlah_kantong > 0` |
| **INDEX** | "Daftar isi" supaya pencarian cepat | `idx_kantong_cocok` |

### Tipe data

| Tipe | Isinya | Contoh |
|---|---|---|
| `INT` | Bilangan bulat | `350` (volume ml) |
| `VARCHAR(n)` | Teks pendek, maksimal n karakter | `VARCHAR(100)` untuk nama |
| `TEXT` | Teks panjang | catatan, keterangan |
| `DATE` | Tanggal saja | `2026-11-01` |
| `DATETIME` | Tanggal + jam | `2026-11-01 14:30:00` |
| `DECIMAL(4,1)` | Angka desimal, 4 digit dengan 1 di belakang koma | `4.5` (suhu °C) |
| `TINYINT(1)` | Dipakai sebagai ya/tidak: 1 = ya, 0 = tidak | `aktif` |
| `ENUM(...)` | Hanya boleh salah satu dari daftar | `ENUM('A','B','AB','O')` |

### Pola nama kolom di SIMBDRS

- `id_xxx` sebagai kolom pertama = **PK** tabel itu.
- `id_xxx` di tengah tabel = **FK** ke tabel lain.
- `id_petugas`, `id_peminta`, `id_pencatat` = FK ke `pengguna`. Namanya berbeda supaya jelas **peran** orang itu di kejadian tersebut.
- `tgl_xxx` = waktu kejadian.
- `aktif` = penanda **soft delete**. Data yang sudah pernah dipakai tidak dihapus, hanya diberi `aktif = 0` supaya riwayat lama tetap utuh.

---

## 2. Peta 16 tabel

| No | Tabel | Kelompok | Satu baris = … |
|---|---|---|---|
| 1 | `ruangan` | Master | satu ruangan RS |
| 2 | `dokter` | Master | satu dokter |
| 3 | `udd_asal` | Master | satu UDD/UTD PMI pengirim darah |
| 4 | `komponen_darah` | Master | satu jenis komponen (WB/PRC/TC/FFP) |
| 5 | `pasien` | Master | satu pasien |
| 6 | `pengguna` | Master | satu akun login |
| 7 | `stok_minimal` | Master | satu batas minimal (komponen + golongan + rhesus) |
| 8 | `pengaturan` | Master | satu setelan angka |
| 9 | `penerimaan` | Transaksi | satu kali kiriman dari UDD |
| 10 | `kantong_darah` | Transaksi (**inti**) | satu kantong darah |
| 11 | `permintaan_darah` | Transaksi | satu formulir permintaan darah |
| 12 | `uji_silang` | Transaksi | satu kali uji kecocokan kantong ↔ permintaan |
| 13 | `transfusi` | Transaksi | satu kantong yang diberikan ke pasien |
| 14 | `pengembalian` | Transaksi | satu kantong yang dikembalikan ke UDD |
| 15 | `pemusnahan` | Transaksi | satu kantong yang dimusnahkan |
| 16 | `log_aktivitas` | Tata kelola | satu tindakan pengguna |

**Urutan pembuatan di `simbdrs.sql` mengikuti ketergantungan.** Tabel yang dirujuk harus dibuat lebih dulu daripada tabel yang merujuk. Contohnya, `permintaan_darah` dibuat **sebelum** `kantong_darah`, karena `kantong_darah` punya FK `id_permintaan_dipesan` yang menunjuk ke permintaan.

---

## 3. Tabel master

Tabel master adalah "kamus" yang dipakai berulang-ulang oleh tabel transaksi. Isinya jarang berubah.

### 3.1 `ruangan`
**Fungsi:** daftar ruangan RS yang bisa meminta darah (IGD, ICU, dan lain-lain).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_ruangan` | INT, PK, AUTO_INCREMENT | Nomor identitas ruangan |
| `nama_ruangan` | VARCHAR(100), NOT NULL, **UNIQUE** | Nama ruangan, tidak boleh kembar |
| `aktif` | TINYINT(1), DEFAULT 1 | 1 = dipakai, 0 = dinonaktifkan |

- **Dirujuk oleh:** `pengguna.id_ruangan` dan `permintaan_darah.id_ruangan`.
- **Aturan:** BR-11. Perawat hanya melihat permintaan ruangannya sendiri, dan ruangannya diketahui dari tabel ini lewat akun perawat.
- **Modul:** `modules/master/ruangan`.
- **Contoh:** `(1, 'IGD', 1)`.

### 3.2 `dokter`
**Fungsi:** dokter penanggung jawab setiap permintaan darah (BR-14).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_dokter` | INT, PK | Nomor identitas dokter |
| `nama_dokter` | VARCHAR(100), NOT NULL | Nama dokter (fiktif) |
| `spesialis` | VARCHAR(100), NULL | Boleh kosong, misalnya untuk dokter umum |
| `aktif` | TINYINT(1), DEFAULT 1 | Soft delete |

- **Dirujuk oleh:** `permintaan_darah.id_dokter`.
- **Modul:** `modules/master/dokter`.

### 3.3 `udd_asal`
**Fungsi:** UDD/UTD PMI yang mengirim darah ke rumah sakit.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_udd` | INT, PK | Nomor identitas UDD |
| `nama_udd` | VARCHAR(100), NOT NULL, **UNIQUE** | Nama UDD |
| `alamat` | VARCHAR(255), NULL | Alamat |
| `telepon` | VARCHAR(20), NULL | Disimpan sebagai teks supaya angka 0 di depan tidak hilang |
| `aktif` | TINYINT(1) | Soft delete |

- **Dirujuk oleh:** `penerimaan.id_udd`.
- **Kenapa `pengembalian` tidak menyimpan `id_udd`?** Karena UDD tujuan pengembalian **sudah bisa dibaca** lewat kantong → penerimaan → UDD. Menyimpannya dua kali berisiko datanya tidak sama. Ini contoh menghindari **redundansi** (normalisasi).
- **Modul:** `modules/master/udd`.

### 3.4 `komponen_darah`
**Fungsi:** jenis komponen darah beserta **angka aturan medisnya**. Angka-angka ini sengaja disimpan di database, **bukan di kode PHP**, supaya bisa diubah admin tanpa mengubah program.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_komponen` | INT, PK | Nomor identitas |
| `kode` | VARCHAR(10), **UNIQUE** | Singkatan: WB, PRC, TC, FFP |
| `nama_komponen` | VARCHAR(100) | Nama lengkap |
| `suhu_min`, `suhu_max` | DECIMAL(4,1), NULL | Rentang suhu simpan (°C). Kalau diisi, harus berpasangan dan min ≤ max |
| `masa_simpan_hari` | INT, NOT NULL | Dipakai **BR-01**: kedaluwarsa = tgl aftap + masa simpan |
| `batas_peringatan_hari` | INT, NOT NULL | Dipakai **BR-08**: kantong "hampir kedaluwarsa" kalau sisa harinya ≤ angka ini |

**Isi awal:**

| kode | masa simpan | peringatan | suhu |
|---|---|---|---|
| WB | 35 hari | 7 hari | 2–6 °C |
| PRC | 35 hari | 7 hari | 2–6 °C |
| TC | 5 hari | 2 hari | 20–24 °C |
| FFP | 365 hari | 30 hari | (kosong) |

> **TODO: verifikasi** masa simpan dan suhu FFP, serta `batas_peringatan_hari` semua komponen, ke SPO rumah sakit atau ke dosen. Angka di atas mengacu pada README tugas (Permenkes 91/2015) dan belum dicek ulang.

- **Dirujuk oleh:** `kantong_darah`, `permintaan_darah`, `stok_minimal`.
- **Modul:** `modules/master/komponen`.

### 3.5 `pasien`
**Fungsi:** data pasien penerima darah. **Hanya data fiktif.**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_pasien` | INT, PK | Nomor identitas internal |
| `no_rm` | VARCHAR(20), **UNIQUE** | Nomor Rekam Medis, tidak boleh kembar |
| `nama_pasien` | VARCHAR(100) | Nama |
| `tgl_lahir` | DATE | Tanggal lahir |
| `jenis_kelamin` | ENUM('L','P') | Laki-laki / Perempuan |
| `gol_darah` | ENUM('A','B','AB','O'), **NULL** | Boleh kosong kalau belum diperiksa |
| `rhesus` | ENUM('+','-'), **NULL** | Diisi berpasangan dengan golongan |
| `aktif` | TINYINT(1) | Soft delete |

- **Aturan:** BR-13. Golongan darah boleh kosong saat pasien didaftarkan, tetapi **wajib** terisi sebelum permintaannya diproses. Golongan dan rhesus harus diisi berdua atau dikosongkan berdua (dicek di `_validasi.php`).
- **Dirujuk oleh:** `permintaan_darah.id_pasien`, `transfusi.id_pasien`.
- **Modul:** `modules/master/pasien`.

### 3.6 `pengguna`
**Fungsi:** akun untuk login ke aplikasi.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_pengguna` | INT, PK | Nomor identitas akun |
| `nama` | VARCHAR(100) | Nama tampilan |
| `username` | VARCHAR(50), **UNIQUE** | Nama login |
| `password_hash` | VARCHAR(255) | Hasil `password_hash()`. **Password asli tidak pernah disimpan** |
| `role` | ENUM('ADMIN','PETUGAS','RUANGAN','KEPALA') | Peran yang menentukan menu dan hak akses |
| `id_ruangan` | INT, NULL, **FK → ruangan** | Wajib kalau role = RUANGAN (dicek di PHP) |
| `aktif` | TINYINT(1) | Akun nonaktif tidak bisa login |
| `created_at` | DATETIME, DEFAULT sekarang | Kapan akun dibuat |

- **Kenapa `id_ruangan` boleh NULL?** Admin, petugas BDRS, dan kepala tidak terikat ke satu ruangan.
- **Kenapa password di-hash?** Kalau database bocor, password asli tetap tidak terbaca. Saat login, `password_verify()` mencocokkan isian dengan hash-nya.
- **Dirujuk oleh:** 7 FK dari tabel lain (petugas, peminta, pencatat, log). Lihat bagian 6.
- **Modul:** `modules/master/pengguna`. Admin tidak bisa menghapus akunnya sendiri, dan admin aktif terakhir dilindungi.

### 3.7 `stok_minimal`
**Fungsi:** batas stok paling sedikit untuk setiap kombinasi komponen + golongan + rhesus (BR-09).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_stok_minimal` | INT, PK | Nomor identitas |
| `id_komponen` | INT, **FK → komponen_darah** | Komponen apa |
| `gol_darah` | ENUM('A','B','AB','O') | Golongan |
| `rhesus` | ENUM('+','-') | Rhesus |
| `jumlah_minimal` | INT, DEFAULT 0 | Batas minimal (kantong) |

- **Aturan:** UNIQUE gabungan `(id_komponen, gol_darah, rhesus)`, jadi satu kombinasi hanya punya satu batas.
- **Dipakai di:** peringatan stok di Dashboard/Stok, dan rumus Rekomendasi (BR-10).
- **Modul:** `modules/master/stok_minimal`, yang diisi sebagai matriks.

### 3.8 `pengaturan`
**Fungsi:** setelan angka umum yang bisa diubah admin. Bentuknya pasangan **kunci → nilai**.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `kunci` | VARCHAR(50), **PK** | Nama setelan. PK-nya berupa teks, bukan angka |
| `nilai` | VARCHAR(100) | Nilainya |
| `keterangan` | VARCHAR(255), NULL | Penjelasan |

**Isi:**

| kunci | nilai | gunanya |
|---|---|---|
| `periode_pemesanan_hari` | 7 | BR-10: pesan untuk kebutuhan N hari ke depan |
| `periode_rata_rata_hari` | 30 | BR-10: rata-rata pemakaian dihitung dari N hari terakhir |

- **Satu-satunya tabel tanpa relasi.** Karena itu tabel ini tidak muncul di gambar ERD.
- **Modul:** `modules/master/stok_minimal/pengaturan.php`.

---

## 4. Tabel transaksi

Tabel transaksi mencatat **kejadian**. Datanya terus bertambah setiap hari.

### 4.1 `penerimaan`
**Fungsi:** "kepala surat" satu kali kiriman darah dari UDD. Satu penerimaan berisi banyak kantong.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_penerimaan` | INT, PK | Nomor identitas |
| `no_dokumen` | VARCHAR(50) | Nomor surat jalan dari UDD |
| `id_udd` | INT, **FK → udd_asal** | Dari UDD mana |
| `tgl_terima` | DATETIME | Kapan diterima |
| `suhu_terima` | DECIMAL(4,1), NULL | Suhu saat diterima |
| `id_petugas` | INT, **FK → pengguna** | Petugas yang menerima |
| `catatan` | TEXT, NULL | Catatan bebas |

- **Pola header–detail:** `penerimaan` adalah **header** (data yang sama untuk satu kiriman), sedangkan `kantong_darah` adalah **detail** (satu baris per kantong). Dengan begitu UDD, tanggal, dan petugas tidak perlu ditulis ulang di setiap kantong.
- **Modul:** `modules/penerimaan`.

### 4.2 `kantong_darah` ★ tabel inti
**Fungsi:** satu baris = **satu kantong darah fisik**. Hampir semua tabel lain terhubung ke sini.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_kantong` | INT, PK | Nomor identitas internal |
| `no_kantong` | VARCHAR(30), **UNIQUE** | Nomor di label kantong (**BR-02**: tidak boleh kembar) |
| `id_penerimaan` | INT, **FK → penerimaan** | Datang di kiriman mana |
| `id_komponen` | INT, **FK → komponen_darah** | Jenis komponen |
| `gol_darah` | ENUM('A','B','AB','O') | Golongan darah kantong |
| `rhesus` | ENUM('+','-') | Rhesus |
| `volume_ml` | INT | Volume (ml) |
| `tgl_aftap` | DATE | Tanggal darah diambil dari pendonor |
| `tgl_kedaluwarsa` | DATE | **BR-01**: dihitung otomatis = `tgl_aftap` + `masa_simpan_hari` |
| `status` | ENUM 6 nilai, DEFAULT 'TERSEDIA' | Posisi kantong dalam siklus hidupnya |
| `id_permintaan_dipesan` | INT, **NULL**, **FK → permintaan_darah** | Terisi saat kantong dipesan untuk sebuah permintaan |

**Aturan di database:**
- `CHECK (tgl_kedaluwarsa >= tgl_aftap)`: tanggal kedaluwarsa tidak mungkin sebelum tanggal aftap.
- `INDEX idx_kantong_cocok (status, id_komponen, gol_darah, rhesus, tgl_kedaluwarsa)`: mempercepat pencarian kantong yang cocok dengan urutan **FEFO** (*First Expired, First Out*). Urutan kolom index-nya sama dengan urutan filter di query.

**6 status kantong:**

| Status | Artinya | Diubah oleh modul |
|---|---|---|
| `TERSEDIA` | Ada di kulkas BDRS, siap dipakai | Penerimaan |
| `DIPESAN` | Sudah cocok uji silang, disiapkan untuk satu permintaan | Permintaan |
| `DIKELUARKAN` | Sudah diserahkan ke ruangan | Transfusi (serahkan) |
| `DITRANSFUSIKAN` | Selesai diberikan ke pasien | Transfusi (selesai) |
| `DIKEMBALIKAN` | Dikembalikan ke UDD | Pengembalian |
| `DIMUSNAHKAN` | Dimusnahkan (kedaluwarsa, rusak, dll.) | Pemusnahan |

```
TERSEDIA → DIPESAN → DIKELUARKAN → DITRANSFUSIKAN
   ↑   ↓ (lepas/batal: kembali TERSEDIA)
   ├──→ DIKEMBALIKAN
   └──→ DIMUSNAHKAN
```

- **Kenapa `id_permintaan_dipesan` boleh NULL?** Kantong yang masih TERSEDIA belum menjadi milik permintaan mana pun. Kolom ini baru terisi saat DIPESAN, dan dikosongkan lagi kalau pesanan dilepas.
- **Modul yang membaca/mengubah:** hampir semua, yaitu penerimaan, stok, permintaan, transfusi, keluar, rekomendasi, laporan, dashboard.

### 4.3 `permintaan_darah`
**Fungsi:** formulir permintaan darah dari ruangan untuk seorang pasien.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_permintaan` | INT, PK | Nomor identitas |
| `no_permintaan` | VARCHAR(30), **UNIQUE** | Nomor formulir yang dibaca manusia |
| `id_pasien` | INT, **FK → pasien** | Untuk pasien siapa |
| `id_ruangan` | INT, **FK → ruangan** | Dari ruangan mana (**BR-11**) |
| `id_peminta` | INT, **FK → pengguna** | Siapa yang menginput |
| `id_dokter` | INT, **FK → dokter** | Dokter penanggung jawab (**BR-14**) |
| `id_komponen` | INT, **FK → komponen_darah** | Komponen yang diminta |
| `jumlah_kantong` | INT, `CHECK > 0` | Berapa kantong |
| `urgensi` | ENUM('BIASA','CITO') | CITO = darurat, didahulukan |
| `diagnosis` | VARCHAR(255) | Alasan medis |
| `tgl_permintaan` | DATETIME | Kapan diminta |
| `status` | ENUM 5 nilai | Lihat tabel di bawah |

**5 status permintaan:**

| Status | Kapan |
|---|---|
| `MENUNGGU` | Baru dibuat, belum disentuh petugas |
| `DIPROSES` | Sudah ada uji silang, tapi belum ada kantong yang dipesan |
| `TERPENUHI_SEBAGIAN` | Sebagian kantong sudah dipesan |
| `TERPENUHI` | Jumlah kantong terikat ≥ jumlah diminta |
| `DIBATALKAN` | Dibatalkan, dan kantongnya dilepas kembali |

- **Kenapa ada `id_ruangan` padahal `id_peminta` sudah punya ruangan?** Supaya riwayat tetap benar walaupun suatu saat perawat itu pindah ruangan. Permintaan tetap tercatat milik ruangan **saat itu**.
- **Tabel dengan FK terbanyak (5).**
- **Modul:** `modules/permintaan`.

### 4.4 `uji_silang`
**Fungsi:** hasil uji kecocokan (*crossmatch*) antara satu kantong dan satu permintaan. Hasil **TIDAK_COCOK** juga dicatat.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_uji` | INT, PK | Nomor identitas |
| `id_permintaan` | INT, **FK → permintaan_darah** | Untuk permintaan mana |
| `id_kantong` | INT, **FK → kantong_darah** | Kantong yang diuji |
| `hasil` | ENUM('COCOK','TIDAK_COCOK') | **BR-07**: kantong hanya boleh dipesan kalau COCOK |
| `tgl_uji` | DATETIME | Kapan diuji |
| `id_petugas` | INT, **FK → pengguna** | Petugas yang menguji |
| `catatan` | TEXT, NULL | Catatan |

- **Ini tabel penghubung untuk relasi banyak-ke-banyak (M:N).** Satu permintaan bisa diuji dengan banyak kantong, dan satu kantong bisa diuji untuk banyak permintaan (misalnya tidak cocok untuk pasien A, lalu dicoba untuk pasien B). Relasi M:N tidak bisa disimpan langsung di dua tabel, jadi dibuat tabel ketiga yang sekaligus menyimpan **hasil** ujinya.
- **Modul:** `modules/permintaan` (proses).

### 4.5 `transfusi`
**Fungsi:** catatan pemberian satu kantong ke pasien. Baris dibuat saat kantong **diserahkan** ke ruangan, lalu dilengkapi saat transfusi dimulai dan selesai.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_transfusi` | INT, PK | Nomor identitas |
| `id_kantong` | INT, **UNIQUE**, **FK → kantong_darah** | **BR-06**: satu kantong hanya untuk satu transfusi |
| `id_permintaan` | INT, **FK → permintaan_darah** | Memenuhi permintaan mana |
| `id_pasien` | INT, **FK → pasien** | Pasien penerima |
| `tgl_keluar_bdrs` | DATETIME, NOT NULL | Kapan darah keluar dari BDRS |
| `tgl_mulai` | DATETIME, **NULL** | Diisi perawat saat mulai, setelah verifikasi no. kantong + No. RM |
| `tgl_selesai` | DATETIME, **NULL** | Diisi saat selesai |
| `id_pencatat` | INT, **FK → pengguna** | Petugas yang menyerahkan |

- **UNIQUE pada `id_kantong` mengubah relasi 1:N menjadi 1:1.** Kalau ada yang mencoba memakai kantong yang sama dua kali, MySQL menolak dengan kode error 23000.
- **Kenapa `tgl_mulai`/`tgl_selesai` boleh NULL?** Karena keduanya belum terjadi saat baris dibuat. Dari kolom ini bisa dibaca posisinya: NULL–NULL = masih di jalan atau menunggu, terisi–NULL = sedang berlangsung, terisi–terisi = selesai.
- **Kenapa ada `id_pasien` padahal permintaan sudah punya pasien?** Supaya riwayat transfusi pasien bisa dibaca langsung tanpa JOIN tambahan. Kode memastikan nilainya selalu sama dengan pasien di permintaan.
- **Modul:** `modules/transfusi`.

### 4.6 `pengembalian`
**Fungsi:** kantong yang dikembalikan ke UDD (bukan untuk pasien).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_pengembalian` | INT, PK | Nomor identitas |
| `id_kantong` | INT, **UNIQUE**, **FK → kantong_darah** | Satu kantong hanya bisa dikembalikan sekali |
| `tgl_kembali` | DATETIME | Kapan |
| `alasan` | VARCHAR(255) | Alasan |
| `id_petugas` | INT, **FK → pengguna** | Petugas |

- **Aturan:** hanya kantong berstatus TERSEDIA yang boleh dikembalikan (dicek di PHP).
- **Modul:** `modules/keluar`.

### 4.7 `pemusnahan`
**Fungsi:** kantong yang dimusnahkan.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_pemusnahan` | INT, PK | Nomor identitas |
| `id_kantong` | INT, **UNIQUE**, **FK → kantong_darah** | Satu kantong hanya bisa dimusnahkan sekali |
| `tgl_musnah` | DATETIME | Kapan |
| `alasan` | ENUM('KEDALUWARSA','RUSAK','HEMOLISIS','LAINNYA') | Alasan baku, supaya bisa direkap di laporan |
| `keterangan` | TEXT, NULL | **Wajib** kalau alasan = LAINNYA (dicek di PHP) |
| `id_petugas` | INT, **FK → pengguna** | Petugas |

- **Kenapa alasan berupa ENUM, sedangkan alasan pengembalian berupa teks bebas?** Alasan pemusnahan dipakai untuk **statistik** (*wastage* per alasan di Laporan), jadi harus seragam. Alasan pengembalian jarang terjadi dan cukup dicatat bebas.
- **Aturan:** alasan KEDALUWARSA hanya diterima kalau kantong itu memang sudah lewat tanggal kedaluwarsanya. **TODO: verifikasi** prosedur pemusnahan sesuai SPO RS.
- **Modul:** `modules/keluar`.

---

## 5. Tabel tata kelola

### 5.1 `log_aktivitas`
**Fungsi:** jejak audit, yaitu siapa melakukan apa, pada data mana, dan kapan (**BR-12**).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id_log` | INT, PK | Nomor identitas |
| `id_pengguna` | INT, **NULL**, **FK → pengguna** | Pelaku, diambil dari session. NULL untuk `LOGIN_GAGAL` karena orangnya belum login; akun yang dicoba disimpan di `id_data` |
| `aksi` | VARCHAR(50) | Yang dipakai: LOGIN, LOGIN_GAGAL, LOGOUT, TAMBAH, UBAH, HAPUS, NONAKTIF, TERIMA, UJI_SILANG, STATUS, BATAL, MULAI, TOLAK |
| `nama_tabel` | VARCHAR(50), NULL | Tabel yang diubah |
| `id_data` | INT, NULL | ID baris yang diubah |
| `keterangan` | TEXT, NULL | Penjelasan untuk manusia |
| `waktu` | DATETIME, DEFAULT sekarang | Kapan. Diberi INDEX supaya filter tanggal cepat |

- **Kenapa `nama_tabel` + `id_data`, bukan FK?** Satu kolom FK hanya bisa menunjuk ke satu tabel, padahal log mencatat perubahan di banyak tabel. Pasangan "nama tabel + id" ini disebut **referensi polimorfik**. Kekurangannya, MySQL tidak bisa menjamin baris yang ditunjuk masih ada. Itu dapat diterima karena log sifatnya catatan sejarah.
- **Ditulis oleh:** fungsi `catat_log()` di `includes/fungsi.php`, yang dipanggil di setiap aksi.
- **Hanya bisa dibaca** (tidak ada fitur ubah/hapus), dan hanya oleh ADMIN.
- **Modul:** `modules/log`.

---

## 6. Daftar 24 foreign key

| No | Tabel (anak) | Kolom | → Tabel (induk) | Arti relasi |
|---|---|---|---|---|
| 1 | pengguna | id_ruangan | ruangan | akun perawat milik ruangan |
| 2 | stok_minimal | id_komponen | komponen_darah | batas untuk komponen |
| 3 | penerimaan | id_udd | udd_asal | kiriman dari UDD |
| 4 | penerimaan | id_petugas | pengguna | yang menerima |
| 5 | permintaan_darah | id_pasien | pasien | untuk pasien |
| 6 | permintaan_darah | id_ruangan | ruangan | dari ruangan |
| 7 | permintaan_darah | id_peminta | pengguna | yang menginput |
| 8 | permintaan_darah | id_dokter | dokter | penanggung jawab |
| 9 | permintaan_darah | id_komponen | komponen_darah | komponen diminta |
| 10 | kantong_darah | id_penerimaan | penerimaan | datang di kiriman |
| 11 | kantong_darah | id_komponen | komponen_darah | jenis kantong |
| 12 | kantong_darah | id_permintaan_dipesan | permintaan_darah | dipesan untuk |
| 13 | uji_silang | id_permintaan | permintaan_darah | uji untuk permintaan |
| 14 | uji_silang | id_kantong | kantong_darah | kantong yang diuji |
| 15 | uji_silang | id_petugas | pengguna | penguji |
| 16 | transfusi | id_kantong | kantong_darah | kantong dipakai |
| 17 | transfusi | id_permintaan | permintaan_darah | memenuhi permintaan |
| 18 | transfusi | id_pasien | pasien | penerima |
| 19 | transfusi | id_pencatat | pengguna | yang menyerahkan |
| 20 | pengembalian | id_kantong | kantong_darah | kantong dikembalikan |
| 21 | pengembalian | id_petugas | pengguna | petugas |
| 22 | pemusnahan | id_kantong | kantong_darah | kantong dimusnahkan |
| 23 | pemusnahan | id_petugas | pengguna | petugas |
| 24 | log_aktivitas | id_pengguna | pengguna | pelaku |

**Pengamatan:**
- `pengguna` paling sering dirujuk (**7 kali**), karena hampir setiap kejadian dicatat "siapa yang melakukan".
- `permintaan_darah` dirujuk **3 kali** dan merujuk ke **5 tabel**. Ini tabel yang paling "sibuk" relasinya.
- `kantong_darah` dirujuk **4 kali**: uji silang, transfusi, pengembalian, pemusnahan.
- **Efek FK saat menghapus:** kalau ruangan yang sudah punya permintaan coba dihapus, MySQL menolak (error 23000). Karena itu modul master memakai **soft delete** (`aktif = 0`) sebagai gantinya.

---

## 7. Contoh: menelusuri satu kantong dengan JOIN

Inilah kekuatan relasi. Dengan menyambung tabel-tabel lewat FK, satu kantong bisa ditelusuri dari UDD asal sampai pasien penerimanya (query ini bisa dicoba di tab SQL phpMyAdmin):

```sql
SELECT k.no_kantong, c.kode AS komponen, k.gol_darah, k.rhesus,
       u.nama_udd, pn.tgl_terima,
       ps.no_rm, ps.nama_pasien, r.nama_ruangan,
       t.tgl_mulai, t.tgl_selesai
FROM kantong_darah k
JOIN komponen_darah   c  ON c.id_komponen    = k.id_komponen
JOIN penerimaan       pn ON pn.id_penerimaan = k.id_penerimaan
JOIN udd_asal         u  ON u.id_udd         = pn.id_udd
LEFT JOIN transfusi        t  ON t.id_kantong     = k.id_kantong
LEFT JOIN permintaan_darah pm ON pm.id_permintaan = t.id_permintaan
LEFT JOIN pasien           ps ON ps.id_pasien     = t.id_pasien
LEFT JOIN ruangan          r  ON r.id_ruangan     = pm.id_ruangan
WHERE k.no_kantong = 'SKM26-04118';   -- contoh nomor fiktif
```

- **`JOIN` biasa** untuk relasi yang **pasti ada**: setiap kantong pasti punya komponen, penerimaan, dan UDD.
- **`LEFT JOIN`** untuk relasi yang **mungkin belum ada**: kantong TERSEDIA belum punya transfusi. Kalau memakai JOIN biasa, kantong seperti itu akan hilang dari hasil.

---

## 8. Ringkasan untuk presentasi

> Database SIMBDRS terdiri dari **16 tabel**: 8 tabel master, 7 tabel transaksi, dan 1 tabel log, dihubungkan oleh **24 foreign key**.
>
> **Tabel intinya `kantong_darah`.** Setiap kantong punya siklus hidup **6 status**, dari TERSEDIA sampai DITRANSFUSIKAN, DIKEMBALIKAN, atau DIMUSNAHKAN.
>
> **Aturan bisnis dijaga di dua lapis:**
> - **Database:** PRIMARY KEY, FOREIGN KEY, UNIQUE (misalnya satu kantong hanya untuk satu transfusi), CHECK, ENUM.
> - **PHP:** aturan yang butuh logika, misalnya golongan harus cocok dan kantong kedaluwarsa ditolak.
>
> **Prinsip desainnya:**
> - **Normalisasi:** UDD tujuan pengembalian tidak disimpan ulang, cukup dibaca lewat penerimaan.
> - **Tabel penghubung** `uji_silang` untuk relasi banyak-ke-banyak.
> - **Pola header–detail** pada penerimaan dan kantong.
> - **Angka aturan medis disimpan di tabel**, bukan di kode, supaya bisa diubah tanpa mengubah program.
> - **Soft delete** supaya riwayat tidak hilang.
> - **Jejak audit** di `log_aktivitas`.
