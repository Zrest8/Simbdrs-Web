# Penjelasan Lengkap SIMBDRS

Dokumen ini menjelaskan SIMBDRS **dari gambaran besar sampai detail**, dengan satu cerita contoh
yang diikuti dari awal sampai akhir. Baca berurutan.

> Pasangan dokumen ini:
> - `PANDUAN_BELAJAR.md` → cara kerja **kode** & **phpMyAdmin**
> - `PRD.md` / `ERD.md` → spesifikasi resmi fitur & tabel
> - `ALUR_PEMBUATAN_SIMBDRS.md` → urutan **membangun** aplikasi dari nol & alasannya

**Isi**
1. SIMBDRS itu apa dan kenapa dibuat
2. Dunia nyata: dari pendonor sampai pasien
3. Siapa saja yang memakai SIMBDRS
4. "Benda" utama: kantong darah dan statusnya
5. Alur lengkap 9 langkah (dengan cerita contoh)
6. Dua fitur unggulan
7. Peta: langkah → halaman → tabel → aturan bisnis
8. Status pengerjaan (semua modul selesai)
9. Akun demo & data dummy
10. Ringkasan 1 halaman untuk presentasi

> *Diperbarui 5 November 2026 — semua modul sudah dibangun dan diuji.*
> *11 November 2026 — fitur Hemovigilans (lapor reaksi + lacak kantong) dikeluarkan dari ruang lingkup.*

---

## 1. SIMBDRS Itu Apa dan Kenapa Dibuat

**SIMBDRS** = *Sistem Informasi Manajemen Bank Darah Rumah Sakit*. Aplikasi web untuk **mencatat dan
mengelola setiap kantong darah** di rumah sakit, sejak diterima dari PMI sampai masuk ke tubuh pasien
(atau dikembalikan / dimusnahkan).

### Masalah yang diselesaikan
| Masalah di lapangan | Akibatnya | Jawaban SIMBDRS |
|---|---|---|
| Pencatatan manual, stok tidak real-time | Perawat harus menelepon BDRS | Stok tampil langsung di dashboard |
| Trombosit hanya tahan ±5 hari | Banyak darah kedaluwarsa & dibuang (36,17% TC di PMI Yogyakarta 2024) | **Peringatan dini** + urutan pakai **FEFO** |
| Pesan darah ke PMI berdasarkan perkiraan | Kadang kurang, kadang berlebih | **Rekomendasi pemesanan** dari data pemakaian |

### Fokus mata kuliah
Yang dinilai adalah **manajemen data**: struktur tabel, relasi, validasi, integritas, siklus hidup data,
hak akses, audit trail, dan laporan. Tampilan cukup rapi.

---

## 2. Dunia Nyata: Dari Pendonor Sampai Pasien

```
PENDONOR ──► UDD / UTD PMI ──────────────► BDRS (bank darah RS) ──────────► PASIEN
             "pabrik darah"                 "toko darah di RS"
             • seleksi pendonor            • terima & simpan kantong
             • ambil darah                 • uji silang (crossmatch)
             • uji saring IMLTD            • serahkan ke ruangan
               (HIV, Hep B, Hep C, Sifilis)• kembalikan / musnahkan
             • olah jadi komponen
             • kirim ke RS                       ▲
                                                 └── CAKUPAN SIMBDRS
```

SIMBDRS **hanya** mengurus bagian BDRS. Data pendonor dan hasil uji saring adalah urusan UDD (tidak disimpan
di SIMBDRS; kantong yang datang dianggap sudah lolos uji saring).

### Komponen darah yang dikelola
| Kode | Nama | Disimpan | Tahan |
|---|---|---|---|
| WB | Whole Blood (darah lengkap) | kulkas 2–6 °C | 35 hari |
| PRC | Packed Red Cell (sel darah merah) | kulkas 2–6 °C | 35 hari |
| TC | Trombosit | suhu ruang 20–24 °C, digoyang | **5 hari** ← paling cepat basi |
| FFP | Plasma beku | beku | hitungan bulan *(perlu verifikasi)* |

Angka-angka ini **disimpan di tabel `komponen_darah`**, bukan di kode, supaya bisa diubah admin.

### Istilah penting
- **Aftap** = pengambilan darah dari pendonor. `tgl_aftap` adalah titik awal hitung kedaluwarsa.
- **Uji silang / crossmatch** = tes laboratorium kecocokan darah pasien dengan darah kantong.
- **FEFO** (*First Expired First Out*) = yang paling cepat kedaluwarsa dipakai duluan.
- **CITO** = permintaan darurat.
- **Sistem tertutup** = darah diantar petugas, bukan dibawa keluarga pasien.

---

## 3. Siapa Saja yang Memakai SIMBDRS

| Peran | Orangnya | Yang bisa dilakukan | Menu yang terlihat |
|---|---|---|---|
| **ADMIN** | Pengelola sistem | Kelola akun & data master, atur periode pemesanan, baca log aktivitas | Dashboard, Data Master (7 menu), Log Aktivitas |
| **PETUGAS** | Petugas BDRS | Terima kantong, proses permintaan, uji silang, serahkan darah, kembalikan/musnahkan, lihat rekomendasi & laporan | Dashboard, Penerimaan, Stok, Permintaan, Transfusi, Pengembalian & Pemusnahan, Rekomendasi, Laporan |
| **KEPALA** | Kepala BDRS | Memantau (**hanya baca**) | Dashboard, Stok, Rekomendasi, Laporan |
| **DOKTER** | Dokter penanggung jawab (setiap dokter punya akun) | Memantau permintaan & transfusi **pasien yang ia tangani** (hanya baca) | Dashboard Dokter, Permintaan, Transfusi |
| **PERAWAT** | Perawat ruangan (satu akun per perawat) | Minta darah, catat mulai/selesai transfusi — **hanya untuk ruangannya** | Dashboard Perawat, Permintaan, Transfusi |

Tabel di atas adalah hak **bawaan**. Hak sebenarnya disimpan di tabel `hak_akses` dan bisa diubah Admin lewat menu
**Hak Akses** (centang Lihat/Tambah/Ubah/Hapus per menu). Setiap halaman memanggil `wajib_hak('menu', 'aksi')`.
Menu yang tidak berhak tidak muncul di sidebar, dan kalau alamatnya diketik langsung muncul "Akses ditolak".
Aksi penting (misalnya "serahkan" atau "catat transfusi") juga dicek perannya di server, jadi tidak bisa diakali
dengan mengirim formulir palsu.

---

## 4. "Benda" Utama: Kantong Darah dan Statusnya

Seluruh sistem berputar di sekitar tabel **`kantong_darah`**. Satu baris = satu kantong fisik.
Setiap kantong punya **`no_kantong`** yang unik — inilah "nomor resi" kantong itu (bisa juga dicetak sebagai QR).

### Enam status kantong
```
                 ┌──────────────┐
      ┌─────────►│   TERSEDIA   │◄──────── batal dipakai ───────┐
      │ diterima └──────┬───────┘                                │
      │                 │                                        │
      │     ┌───────────┼──────────────────┐                     │
      │     ▼           ▼                  ▼                     │
      │ DIKEMBALIKAN  DIMUSNAHKAN       DIPESAN ─────────────────┘
      │   (ke UDD)   (dibuang)       (lolos uji silang,
      │      ✔           ✔            terkunci utk 1 pasien)
      │                                    │ diantar ke ruangan
      │                                    ▼
      │                               DIKELUARKAN
      │                                    │ dimasukkan ke pasien
      │                                    ▼
      │                              DITRANSFUSIKAN ✔
      │
   ✔ = status akhir (tidak bisa berubah lagi)
```

| Status | Artinya | Fisik kantong ada di |
|---|---|---|
| TERSEDIA | Siap dipakai | Kulkas BDRS |
| DIPESAN | Sudah cocok (uji silang) untuk pasien tertentu, disisihkan | Kulkas BDRS |
| DIKELUARKAN | Sudah diantar ke ruangan | Ruangan perawatan |
| DITRANSFUSIKAN | Sudah masuk ke tubuh pasien | — |
| DIKEMBALIKAN | Dikembalikan ke UDD (mis. hampir kedaluwarsa) | UDD |
| DIMUSNAHKAN | Dibuang (kedaluwarsa / rusak / hemolisis) | — |

**Aturan:** status hanya boleh pindah mengikuti panah. Setiap perpindahan **dicatat di `log_aktivitas`**.

---

## 5. Alur Lengkap 9 Langkah (Cerita Contoh)

> **Cerita:** Hari Senin, RS menerima 10 kantong dari UDD PMI. Hari Rabu, **Bapak Andi (golongan O+)**
> masuk IGD karena perdarahan. Dokter meminta **2 kantong PRC**, CITO.

### Langkah ① — Penerimaan kantong dari UDD
**Siapa:** Petugas BDRS · **Halaman:** Penerimaan Kantong

1. Kurir UDD datang membawa 10 kantong + surat jalan.
2. Petugas membuat **1 data penerimaan**: no. surat jalan, UDD asal, waktu terima, suhu saat diterima.
3. Petugas mencatat **setiap kantong** (ketik atau scan): no. kantong, komponen, golongan, rhesus, volume, tanggal aftap.
4. Sistem **menghitung sendiri** tanggal kedaluwarsa:
   `tgl_kedaluwarsa = tgl_aftap + masa_simpan_hari` → PRC aftap 1 Nov + 35 = **6 Des** *(BR-01)*
5. Nomor kantong yang sudah ada **ditolak** *(BR-02)*.

| Hasil di database | |
|---|---|
| `penerimaan` | +1 baris (header kiriman) |
| `kantong_darah` | +10 baris, status **TERSEDIA** |
| `log_aktivitas` | tercatat siapa yang menerima |

### Langkah ② — Penyimpanan & pemantauan (otomatis setiap hari)
**Siapa:** Sistem · **Halaman:** Dashboard

Dashboard selalu menghitung ulang dari database:
- **Stok per golongan**: hanya kantong TERSEDIA yang belum kedaluwarsa.
- **Peringatan hampir kedaluwarsa** *(BR-08)*: mis. TC B+ tinggal 1 hari → "pakai dulu atau kembalikan ke UDD".
- **Peringatan sudah kedaluwarsa**: kantong lewat tanggal tapi belum dimusnahkan.
- **Stok di bawah minimum** *(BR-09)*: mis. PRC O+ tinggal 1, minimal 5 → ubin O+ berwarna merah.

Tidak ada data yang berubah — dashboard hanya **membaca**.

### Langkah ③ — Permintaan darah dari ruangan
**Siapa:** Perawat IGD (peran PERAWAT) · **Halaman:** Permintaan Darah

1. Perawat mengisi: pasien **Bapak Andi**, dokter penanggung jawab, komponen **PRC**, jumlah **2**, urgensi **CITO**, diagnosis "perdarahan".
2. Syarat: golongan darah pasien sudah tercatat *(BR-13)* dan dokter wajib diisi *(BR-14)*.
3. Perawat IGD hanya melihat permintaan **IGD** *(BR-11)*.

| Hasil di database | |
|---|---|
| `permintaan_darah` | +1 baris, status **MENUNGGU** |

Di dashboard petugas, permintaan **CITO tampil paling atas**.

### Langkah ④ — Pencocokan otomatis (FEFO)
**Siapa:** Petugas BDRS · **Halaman:** Permintaan Darah → tombol **Proses** (halaman detail permintaan)

Sistem menampilkan **10 kantong saran** yang cocok dengan aturan *(BR-03, BR-04, BR-05)*:
- komponen = PRC, golongan = O, rhesus = +
- status = TERSEDIA, **belum kedaluwarsa**
- **diurutkan dari kedaluwarsa terdekat** (FEFO); kantong yang sudah TIDAK COCOK untuk permintaan ini disembunyikan

```
Saran kantong untuk Bapak Andi (PRC O+):
  1. SKM26-04118  exp 20 Nov  ← pakai ini dulu   [COCOK] [TIDAK COCOK]
  2. SKM26-04123  exp 6 Des                      [COCOK] [TIDAK COCOK]
  3. MKS26-07340  exp 12 Des                     [COCOK] [TIDAK COCOK]
```
Membuka halaman ini **tidak mengubah data** — status baru berubah saat hasil uji silang dicatat.

### Langkah ⑤ — Uji silang (crossmatch)
**Siapa:** Petugas BDRS / laboratorium · **Halaman:** detail permintaan

1. Petugas mengambil kantong no. 1 dari kulkas.
2. Lab melakukan crossmatch → petugas **menekan COCOK atau TIDAK COCOK** (sistem tidak menghitung hasil lab).
   - **TIDAK COCOK** → tetap dicatat (riwayat), kantong tetap TERSEDIA; ambil kantong no. 2.
   - **COCOK** → kantong menjadi **DIPESAN**, terkunci untuk Bapak Andi *(BR-07)*.
3. Status permintaan dihitung ulang otomatis:
   ada uji tapi belum ada yang cocok → **DIPROSES**; 1 dari 2 cocok → **TERPENUHI_SEBAGIAN**; 2 dari 2 → **TERPENUHI**.
4. Sebelum menyimpan, server **memeriksa ulang** komponen, golongan, status, dan tanggal kedaluwarsa —
   formulir palsu dengan kantong golongan lain atau kantong kedaluwarsa akan ditolak.

Dari halaman yang sama petugas juga bisa:
- **Lepas** kantong DIPESAN (DIPESAN → TERSEDIA), misalnya pasien batal transfusi;
- **Batalkan** permintaan dengan alasan wajib — semua kantong DIPESAN otomatis kembali TERSEDIA.
  Pembatalan ditolak bila sudah ada kantong yang keluar ke ruangan.

| Hasil di database | |
|---|---|
| `uji_silang` | +1 baris per kantong yang diuji (termasuk yang tidak cocok) |
| `kantong_darah` | status → **DIPESAN**, `id_permintaan_dipesan` diisi |
| `permintaan_darah` | status diperbarui |
| `log_aktivitas` | hasil uji + perubahan status kantong & permintaan |

### Langkah ⑥ — Penyerahan ke ruangan
**Siapa:** Petugas BDRS · **Halaman:** Transfusi → bagian **"Siap Diserahkan ke Ruangan"** (CITO di atas)

Petugas mengantar sendiri ke IGD (sistem tertutup) lalu menekan **Serahkan**.
Kantong yang ternyata sudah lewat tanggal **tidak bisa diserahkan** *(BR-03)* — harus dilepas lalu dimusnahkan.

| Hasil di database | |
|---|---|
| `kantong_darah` | status → **DIKELUARKAN** |
| `transfusi` | +1 baris per kantong, berisi waktu keluar dari BDRS |

### Langkah ⑦ — Transfusi ke pasien
**Siapa:** Perawat IGD · **Halaman:** Transfusi

1. **Verifikasi di samping tempat tidur:** perawat mengetik (atau memindai) **no. kantong** pada label dan
   **No. RM** pada gelang pasien. Kalau salah satu tidak sama → muncul pesan **STOP** dan percobaannya
   dicatat di log (aksi `TOLAK`). Ini mencegah darah masuk ke pasien yang salah.
2. Kalau cocok → perawat menekan **Mulai** (waktu mulai tercatat).
3. Setelah habis → perawat menekan **Selesai**. Waktu tidak boleh di masa depan, dan selesai tidak boleh sebelum mulai.

| Hasil di database | |
|---|---|
| `transfusi` | `tgl_mulai` & `tgl_selesai` terisi |
| `kantong_darah` | status → **DITRANSFUSIKAN** (akhir) |

Satu kantong hanya untuk satu pasien *(BR-06)*, dijaga oleh `UNIQUE id_kantong` di tabel `transfusi`.

### Langkah ⑧ — Kantong yang tidak terpakai
**Siapa:** Petugas BDRS · **Halaman:** Pengembalian & Pemusnahan

Halaman ini menampilkan kantong TERSEDIA (bawaan: yang sudah kedaluwarsa), lalu petugas menekan **Tindak**.

| Situasi | Tindakan | Status jadi | Tabel |
|---|---|---|---|
| Kantong masih layak tapi tidak akan terpakai | Kembalikan ke UDD asal (dibaca dari data penerimaan) | DIKEMBALIKAN | `pengembalian` + alasan |
| Kedaluwarsa / rusak / hemolisis / lainnya | Musnahkan (sesuai SPO limbah medis RS) | DIMUSNAHKAN | `pemusnahan` + alasan |
| Sudah DIPESAN tapi pasien batal transfusi | **Lepas** di halaman permintaan | TERSEDIA | dicatat di `log_aktivitas` |

Penjagaan: hanya kantong **TERSEDIA** yang bisa ditindak; alasan wajib; alasan "Kedaluwarsa" ditolak kalau
kantongnya belum lewat tanggal; alasan "Lainnya" wajib diberi keterangan.

### Langkah ⑨ (berkala) — Rekomendasi, laporan, dan audit
**Siapa:** Petugas & Kepala BDRS (rekomendasi, laporan), Admin (log) · **Halaman:** Rekomendasi Pemesanan, Laporan, Log Aktivitas

**Rekomendasi pesan ke UDD** *(BR-10)*, dihitung per komponen × golongan × rhesus. Contoh PRC O+:
```
Pemakaian 30 hari terakhir   = 60 kantong  → rata-rata 2 per hari
× periode pemesanan 7 hari   = ⌈14⌉ = 14
+ stok minimal               = 14 + 5 = 19
− stok tersedia sekarang     = 19 − 6 = 13  → sarankan pesan 13 kantong (paling kecil 0)
```
- "Pemakaian" = kantong yang keluar BDRS; kantong kedaluwarsa tidak dihitung sebagai tersedia.
- Angka 30 dan 7 disimpan di tabel `pengaturan` dan diubah Admin (Stok Minimal → **Periode pemesanan**).
- Bisa **simulasi** periode lain tanpa menyimpan, dan **dicetak sebagai draf pesanan** dengan kolom tanda tangan.
- Penanda **ED n** = n kantong tersedia akan kedaluwarsa sebelum periode pesan habis (informasi saja).

**Laporan per periode:** diterima, dipakai, dikembalikan, dimusnahkan, **wastage rate**
(= dimusnahkan ÷ diterima), grafik per bulan, pemakaian per golongan, alasan pemusnahan, dan status permintaan. Bisa **diunduh CSV untuk Excel** dan **dicetak / disimpan PDF**.

**Log aktivitas (audit trail):** Admin melihat siapa melakukan apa dan kapan, dengan filter dan unduhan CSV.
Log **hanya bisa dibaca** — tidak ada tombol ubah/hapus. Percobaan **login gagal** juga tercatat.

---

## 6. Dua Fitur Unggulan (Pembeda dari Penelitian Lain)

| Pilar | Masalah | Cara kerja di SIMBDRS | Langkah |
|---|---|---|---|
| **1. Peringatan dini + FEFO** | Darah terbuang karena kedaluwarsa | Dashboard menandai kantong hampir kedaluwarsa; saran kantong selalu urut kedaluwarsa terdekat | ②, ④ |
| **2. Rekomendasi pemesanan** | Pesan darah hanya perkiraan | Rumus BR-10 dari data pemakaian nyata | ⑨ |

**Pelengkap:** verifikasi kantong–pasien sebelum transfusi (no. kantong + No. RM gelang) sudah ada di langkah ⑦.
Kolom isiannya bisa diisi dengan **pemindai QR/barcode** biasa (alat ini bekerja seperti keyboard), jadi QR
tinggal soal mencetak label.

---

## 7. Peta: Langkah → Halaman → Tabel → Aturan Bisnis

| Langkah | Folder kode (`simbdrs/modules/…`) | Tabel yang ditulis | Status kantong | Aturan bisnis |
|---|---|---|---|---|
| ① Penerimaan | `penerimaan/` | `penerimaan`, `kantong_darah` | → TERSEDIA | BR-01, BR-02 |
| ② Pemantauan | `dashboard/`, `stok/` | (hanya baca) | — | BR-08, BR-09 |
| ③ Permintaan | `permintaan/tambah.php` | `permintaan_darah` | — | BR-11, BR-13, BR-14 |
| ④ Pencocokan | `permintaan/detail.php` | (hanya baca) | — | BR-03, BR-04, BR-05 |
| ⑤ Uji silang | `permintaan/aksi.php` | `uji_silang`, `kantong_darah`, `permintaan_darah` | TERSEDIA ⇄ DIPESAN | BR-07 |
| ⑥ Penyerahan | `transfusi/aksi.php` | `transfusi`, `kantong_darah` | DIPESAN → DIKELUARKAN | BR-03, BR-06 |
| ⑦ Transfusi | `transfusi/aksi.php` | `transfusi`, `kantong_darah` | DIKELUARKAN → DITRANSFUSIKAN | BR-11 |
| ⑧ Keluar | `keluar/` | `pengembalian` / `pemusnahan` | TERSEDIA → DIKEMBALIKAN / DIMUSNAHKAN | BR-03 |
| ⑨ Rekomendasi & laporan | `rekomendasi/`, `laporan/` | (hanya baca) | — | BR-10 |
| ⑨ Audit | `log/` | (hanya baca) | — | BR-12 |
| **Semua** | fungsi `catat_log()` | `log_aktivitas` | setiap perubahan | **BR-12** |

**Pola keamanan yang sama di semua modul:** prepared statement (anti SQL injection), `htmlspecialchars`
lewat fungsi `e()` (anti XSS), token CSRF di setiap formulir, `password_hash` untuk password, transaksi
database (berhasil semua atau batal semua), dan perubahan status dengan `WHERE status = 'lama'` supaya
klik ganda atau dua petugas bersamaan tidak membuat data dobel.

### Tabel pendukung (data master)
| Tabel | Isi | Dipakai di langkah |
|---|---|---|
| `udd_asal` | Daftar UDD pengirim | ① |
| `komponen_darah` | WB/PRC/TC/FFP + masa simpan + batas peringatan | ①, ②, ④ |
| `stok_minimal` | Batas minimal per komponen-golongan-rhesus | ②, ⑨ |
| `pengaturan` | Periode pemesanan, periode rata-rata (diatur Admin) | ⑨ |
| `pasien` | Data pasien (fiktif) + golongan darah | ③, ⑦ |
| `ruangan`, `dokter` | Ruangan peminta & dokter penanggung jawab | ③ |
| `pengguna` | Akun & peran | semua |

**Total 17 tabel (termasuk `hak_akses`), 25 foreign key.** Foreign key memastikan tidak ada data "yatim" — misalnya transfusi
tanpa kantong, atau ruangan yang dihapus padahal masih punya riwayat permintaan.

---

## 8. Status Pengerjaan (Semua Modul Selesai)

| Bagian | Status |
|---|---|
| Proposal, PRD, ERD, kamus data, alur, aturan bisnis | ✅ selesai |
| Database 17 tabel di XAMPP | ✅ selesai |
| Login (termasuk catat login gagal), hak akses 5 peran (diatur Admin di menu Hak Akses), dashboard dokter & perawat, layout & menu | ✅ selesai |
| Dashboard (stok, peringatan, permintaan, grafik) | ✅ selesai |
| Data master: ruangan, dokter, pasien, komponen, UDD, stok minimal (+ periode pemesanan), pengguna | ✅ selesai |
| ① Penerimaan · ② Stok darah | ✅ selesai |
| ③–⑤ Permintaan, FEFO, uji silang, lepas, batal | ✅ selesai |
| ⑥–⑦ Penyerahan & transfusi (dengan verifikasi identitas) | ✅ selesai |
| ⑧ Pengembalian & pemusnahan | ✅ selesai |
| ⑨ Rekomendasi pemesanan, laporan (CSV + cetak/PDF), log aktivitas | ✅ selesai |
| Data dummy 90 hari (`database/data_dummy.sql`) | ✅ selesai, sudah diimpor |
| Uji fungsi per modul (validasi, hak akses, formulir palsu) | ✅ dilakukan saat pembangunan |
| Black box testing formal (tabel skenario untuk laporan) | ⏳ belum |
| Cetak label QR | ⏳ opsional |
| Laporan akhir & presentasi | ⏳ belum |

**Masih perlu diverifikasi (ditandai `TODO: verifikasi` di kode):** masa simpan FFP, rentang volume kantong
yang wajar, prosedur pemusnahan sesuai SPO RS, dan aturan
kompatibilitas lanjutan (misalnya pemberian golongan O ke pasien lain). Sistem sengaja hanya mengizinkan
golongan + rhesus yang **persis sama**.

---

## 9. Akun Demo & Data Dummy

**Semua data di SIMBDRS adalah data fiktif** (tidak ada data pasien sungguhan — UU 27/2022 tentang
Pelindungan Data Pribadi). Akun di bawah hanya untuk demo di komputer sendiri.

| Peran | Username / password | Coba ini |
|---|---|---|
| Admin | `admin` / `admin123` | Data master, periode pemesanan, Log Aktivitas |
| Petugas BDRS | `petugas` / `petugas123` | Proses permintaan, serahkan darah, musnahkan kantong kedaluwarsa |
| Perawat IGD | `igd` / `igd123` | Buat permintaan, mulai & selesaikan transfusi |
| Kepala BDRS | `kepala` / `kepala123` | Dashboard, Rekomendasi, Laporan |
| Perawat ruangan lain | `icu`/`icu12345`, `bedah`/`bedah123`, `interna`/`interna123`, `anak`/`anak1234` | Bukti BR-11: hanya melihat ruangannya |
| Petugas kedua | `petugas2` / `petugas234` | Log aktivitas dengan lebih dari satu petugas |

**Isi data dummy** — simulasi 90 hari operasional sampai hari impor:
- 5 ruangan, 7 dokter, 3 UDD (fiktif), 42 pasien (2 Rh−, 2 belum diperiksa golongannya);
- ±545 kantong, ±185 permintaan, ±384 transfusi, ±81 pemusnahan, 2 pengembalian, ±2.800 log;
- wastage ±14% (TC paling tinggi karena hanya tahan 5 hari);
- keadaan "hari ini": ada permintaan MENUNGGU & DIPROSES, kantong DIPESAN siap diserahkan,
  kantong sedang di ruangan, dan beberapa kantong kedaluwarsa yang belum ditindak (memicu peringatan).

Tanggal ditulis **relatif terhadap waktu impor** (`NOW()`/`CURDATE()`), jadi data selalu tampak terbaru.
Data dibuat oleh `database/buat_data_dummy.php` dan sudah dicek dengan 13 query: tidak ada satu pun yang
melanggar aturan bisnis (misalnya kantong keluar setelah kedaluwarsa, atau golongan tidak cocok).

**Cara memasang ulang dari nol** (phpMyAdmin → Import, atau terminal):
1. `database/simbdrs.sql` — **menghapus** database lama lalu membuat struktur + 4 akun dasar;
2. `database/data_dummy.sql` — mengisi data dummy. Jalankan **sekali**; kalau terulang, impor dibatalkan
   seluruhnya sehingga tidak ada data dobel.

Folder `database/` ditutup dari browser (`.htaccess`), jadi file SQL tidak bisa diunduh lewat localhost.

---

## 10. Ringkasan 1 Halaman (untuk Presentasi)

> **SIMBDRS** adalah aplikasi web untuk Bank Darah Rumah Sakit yang mencatat setiap kantong darah dari
> diterima dari UDD sampai ditransfusikan ke pasien.
>
> **Alurnya:** kantong diterima dan tanggal kedaluwarsanya dihitung otomatis → ruangan mengajukan
> permintaan → sistem menyarankan kantong yang cocok dengan urutan FEFO → petugas mencatat hasil
> uji silang → darah diserahkan, identitas kantong dan pasien diverifikasi, lalu ditransfusikan. Kantong yang tidak terpakai dikembalikan atau
> dimusnahkan beserta alasannya. Data pemakaian dipakai untuk rekomendasi pemesanan dan laporan wastage.
>
> **Pembedanya:** peringatan dini kedaluwarsa dan rekomendasi pemesanan berbasis data.
>
> **Dari sisi manajemen data:** setiap kantong punya siklus hidup 6 status yang dijaga sistem, 17 tabel
> terhubung dengan 25 foreign key, setiap perubahan tercatat di log aktivitas, hak akses dibedakan untuk
> 5 peran dengan hak akses yang bisa diatur Admin, dan semua angka aturan medis disimpan di database sehingga bisa diubah tanpa mengubah kode.
>
> **Status:** 16 menu berfungsi penuh, diuji per modul, dengan data dummy 90 hari untuk demo.
