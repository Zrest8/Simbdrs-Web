# Panduan Belajar SIMBDRS

Panduan ini menjelaskan **cara kerja aplikasi dari nol**, ditujukan untuk yang baru belajar XAMPP.
Baca berurutan, dan praktikkan setiap langkah sambil membaca.

**Daftar isi**
1. Gambaran besar: bagaimana web terhubung ke database
2. Membuka & memakai database (phpMyAdmin)
3. Peta folder: file apa melakukan apa
4. Membedah 1 alur: Login
5. Membedah pola CRUD: modul Ruangan
6. Dashboard: query yang dipakai
7. Tiga aturan keamanan wajib
8. Latihan mandiri
9. Persiapan pertanyaan dosen

---

## 1. Gambaran Besar

Aplikasi web punya **3 bagian** yang bekerja bersama:

```
  BROWSER                   APACHE + PHP                     MySQL / MariaDB
 (Chrome)                   (XAMPP)                          (XAMPP)
 ─────────                  ────────────                     ───────────────
 Kamu klik "Simpan"  ──►  file .php dijalankan     ──►      tabel menyimpan data
                          (mengirim query SQL)               (ruangan, kantong_darah, ...)
 Halaman HTML tampil ◄──  PHP menyusun HTML        ◄──      hasil data dikirim balik
```

| Bagian | Tugas | Analogi |
|---|---|---|
| **Browser** | Menampilkan halaman, mengirim isian form | Pelanggan di kasir |
| **Apache + PHP** | Menerima permintaan, menjalankan logika, bicara ke database | Kasir |
| **MySQL/MariaDB** | Menyimpan data secara permanen dalam tabel | Gudang |

> **Poin penting untuk presentasi:** browser **tidak pernah** berbicara langsung dengan database.
> Semua lewat PHP, dan PHP terhubung ke database melalui satu file: `config/koneksi.php`.

### Kenapa harus "Start Apache" dan "Start MySQL" di XAMPP?
- **Apache** = server web. Tanpa ini, `http://localhost/...` tidak bisa dibuka.
- **MySQL** = server database. Tanpa ini, muncul pesan *"Koneksi database gagal"*.

### Kenapa alamatnya `http://localhost/simbdrs`?
- `localhost` = komputer kamu sendiri.
- Apache membaca file dari folder `C:\xampp\htdocs\`. Jadi `localhost/simbdrs` = folder `C:\xampp\htdocs\simbdrs`.
- Folder itu adalah **junction** (pintu pintas) ke `OneDrive\Documents\Project Mandat\simbdrs`.
  Jadi file yang kamu edit di VS Code langsung terbaca Apache, sekaligus otomatis ter-backup di OneDrive.

---

## 2. Membuka & Memakai Database (phpMyAdmin)

**phpMyAdmin** adalah halaman web untuk melihat dan mengelola database tanpa mengetik perintah.

### Langkah membuka
1. XAMPP Control Panel → **Start** Apache dan MySQL (keduanya harus hijau).
2. Browser → buka **`http://localhost/phpmyadmin`**
3. Di panel kiri, klik database **`simbdrs`** → muncul daftar 17 tabel.

### Tab-tab yang perlu kamu kuasai

| Tab | Gunanya | Coba lakukan |
|---|---|---|
| **Structure** | Melihat kolom, tipe data, PK, index | Klik tabel `kantong_darah` → Structure. Perhatikan `no_kantong` bertanda UNIQUE |
| **Browse** | Melihat isi data (baris) | Klik tabel `pengguna` → Browse. Ada 4 akun. Perhatikan `password_hash` berupa kode acak, bukan password asli |
| **SQL** | Menjalankan query sendiri | Lihat contoh query di bawah |
| **Designer** | Diagram relasi antar tabel (ERD otomatis) | Klik nama database `simbdrs` → tab **More → Designer** → ikon **Open page** (folder) → pilih **ERD SIMBDRS** (tata letak rapi). Kalau berantakan lagi, jalankan ulang `database/designer_layout.sql` di tab SQL |
| **Export** | Backup database ke file `.sql` | Lakukan setiap selesai perubahan besar |
| **Import** | Memasukkan file `.sql` | Dipakai untuk memasang `simbdrs.sql` |

### Contoh query untuk dicoba di tab SQL
Klik database `simbdrs` → tab **SQL** → tempel → **Go**.

```sql
-- 1. Lihat semua akun beserta ruangannya (JOIN dua tabel)
SELECT p.username, p.role, r.nama_ruangan
FROM pengguna p
LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan;

-- 2. Lihat aturan masa simpan tiap komponen darah
SELECT kode, nama_komponen, masa_simpan_hari, batas_peringatan_hari
FROM komponen_darah;

-- 3. Lihat jejak aktivitas (audit trail) terbaru
SELECT waktu, aksi, nama_tabel, keterangan
FROM log_aktivitas
ORDER BY waktu DESC;
```

### Eksperimen: buktikan bahwa foreign key bekerja
```sql
DELETE FROM ruangan WHERE nama_ruangan = 'IGD';
```
Hasilnya **error** `#1451 - Cannot delete or update a parent row: a foreign key constraint fails`.
Artinya: database **menolak** menghapus ruangan IGD karena masih dipakai akun `igd`.
Inilah yang disebut **integritas referensial**, dan ini contoh bagus untuk presentasi.

### Kalau database rusak / mau mulai dari awal
phpMyAdmin → tab **Import** → pilih `simbdrs/database/simbdrs.sql` → **Go**.
File ini menghapus database lama lalu membuat ulang 17 tabel + data awal.

### Backup (penting!)
Klik database `simbdrs` → tab **Export** → **Go** → simpan file `.sql`-nya.
Lakukan sebelum mencoba hal yang berisiko.

---

## 3. Peta Folder

```
simbdrs/
├── index.php                  → pintu masuk: arahkan ke login atau dashboard
├── config/
│   ├── app.php                → pengaturan: BASE_URL, nama aplikasi, nama RS
│   └── koneksi.php            → ⭐ membuat koneksi PDO ($pdo) ke database
├── auth/
│   ├── login.php              → form login + cek username/password
│   ├── logout.php             → keluar
│   └── cek_akses.php          → ⭐ fungsi wajib_login(): cek sudah login & perannya
├── includes/
│   ├── fungsi.php             → ⭐ kumpulan helper: e(), url(), catat_log(), dll.
│   ├── header.php             → bagian atas halaman (judul, user, menu)
│   ├── sidebar.php            → menu kiri, disaring sesuai peran
│   └── footer.php             → bagian bawah halaman + JavaScript Bootstrap
├── modules/
│   ├── dashboard/index.php    → halaman dashboard
│   └── master/ruangan/        → ⭐ contoh CRUD lengkap (pola untuk modul lain)
├── assets/css/style.css       → tampilan (warna, ukuran)
└── database/simbdrs.sql       → struktur 17 tabel + data awal
```

### Urutan file yang dimuat saat kamu membuka satu halaman
Contoh: membuka `modules/master/ruangan/index.php`

```
ruangan/index.php
   └─ require auth/cek_akses.php
         └─ require includes/fungsi.php
               ├─ require config/app.php        (BASE_URL, nama app)
               ├─ require config/koneksi.php    (→ $pdo siap dipakai)
               └─ session_start()               (ingat siapa yang login)
   └─ wajib_hak('ruangan', 'lihat')              (tak punya hak? → ditolak; dibaca dari tabel hak_akses)
   └─ query SELECT ke tabel ruangan
   └─ require includes/header.php  → sidebar.php
   └─ (isi halaman: tabel daftar ruangan)
   └─ require includes/footer.php
```

---

## 4. Membedah Alur Login

File: [`auth/login.php`](../simbdrs/auth/login.php)

```
① Kamu isi username + password, klik Masuk
② Browser mengirim data ke login.php (method POST)
③ PHP cek: kosong? → tampilkan "wajib diisi"
④ PHP kirim query ke database:
      SELECT ... FROM pengguna WHERE username = ?
⑤ Database mengembalikan 1 baris akun (atau tidak ada)
⑥ PHP cek password dengan password_verify(password_ketikan, password_hash_di_database)
⑦ Cocok → data akun disimpan di SESSION → pindah ke dashboard
   Tidak cocok → "Username atau password salah"
⑧ Dicatat ke log_aktivitas: aksi LOGIN
```

### Apa itu SESSION?
Session = "ingatan" server tentang siapa yang sedang login. Setelah login, `$_SESSION['pengguna']`
berisi nama, role, dan ruangan kamu. Setiap halaman membaca session ini untuk tahu **siapa kamu**
dan **boleh apa**. Saat logout, session dihapus.

### Kenapa password tidak disimpan apa adanya?
Password `admin123` disimpan sebagai **hash**, misalnya `$2y$10$tqxA77...`. Hash tidak bisa dibalik menjadi password asli.
Jadi kalau database bocor, password tetap aman. Saat login, `password_verify()` mencocokkan ketikanmu dengan hash itu.

---

## 5. Membedah Pola CRUD: Modul Ruangan

**CRUD** = **C**reate (tambah), **R**ead (lihat), **U**pdate (ubah), **D**elete (hapus).
Semua modul data master nanti mengikuti pola yang **sama persis** dengan Ruangan.

| File | Huruf CRUD | Query SQL utama |
|---|---|---|
| [`index.php`](../simbdrs/modules/master/ruangan/index.php) | **R**ead | `SELECT ... FROM ruangan` |
| [`tambah.php`](../simbdrs/modules/master/ruangan/tambah.php) | **C**reate | `INSERT INTO ruangan (nama_ruangan) VALUES (?)` |
| [`edit.php`](../simbdrs/modules/master/ruangan/edit.php) | **U**pdate | `UPDATE ruangan SET nama_ruangan = ?, aktif = ? WHERE id_ruangan = ?` |
| [`hapus.php`](../simbdrs/modules/master/ruangan/hapus.php) | **D**elete | `DELETE FROM ruangan WHERE id_ruangan = ?` |
| [`_form.php`](../simbdrs/modules/master/ruangan/_form.php) | – | Tampilan form, dipakai bersama oleh tambah & edit |

### Isi `tambah.php`, baris demi baris (disederhanakan)
```php
require __DIR__ . '/../../../auth/cek_akses.php';   // 1. muat koneksi + session
wajib_hak('ruangan', 'tambah');                     // 2. hanya peran yang punya hak "tambah" boleh

if ($_SERVER['REQUEST_METHOD'] === 'POST') {        // 3. tombol Simpan ditekan?
    $nama = trim($_POST['nama_ruangan']);           // 4. ambil isian form

    if ($nama === '') {                             // 5. validasi
        $error = 'Nama ruangan wajib diisi.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO ruangan (nama_ruangan) VALUES (?)");  // 6. siapkan query
        $stmt->execute([$nama]);                                                // 7. jalankan dengan data
        catat_log($pdo, 'TAMBAH', 'ruangan', ...);                              // 8. catat audit trail
        header('Location: index.php');                                         // 9. kembali ke daftar
    }
}
```

### Kenapa ada tanda tanya `?` di query?
Itu **prepared statement**. Query dan data dikirim **terpisah**:
- `prepare("... VALUES (?)")` → kirim bentuk query-nya dulu
- `execute([$nama])` → baru kirim datanya

Kalau seseorang mengetik `'); DROP TABLE ruangan; --` di form, isian itu dianggap **teks biasa**, bukan perintah.
Ini mencegah serangan **SQL Injection**. ❌ Jangan pernah menulis `"VALUES ('$nama')"`.

### Kenapa hapus ruangan IGD malah jadi "nonaktif"?
Di `hapus.php`:
```php
try {
    DELETE FROM ruangan WHERE id_ruangan = ?      // coba hapus
} catch (PDOException $ex) {                      // database menolak (kode 23000 = foreign key)
    UPDATE ruangan SET aktif = 0 WHERE ...        // → cukup nonaktifkan
}
```
Ruangan yang punya riwayat (akun/permintaan) **tidak boleh hilang**, karena riwayat permintaan darah
pasien bisa kehilangan informasi ruangannya. Jadi ruangan hanya dinonaktifkan.

### Kenapa nama ruangan tidak bisa kembar?
Di database, kolom `nama_ruangan` diberi `UNIQUE`. Kalau ada yang menambah "IGD" lagi,
database menolak (kode error `23000`), dan PHP menampilkan pesan *"Ruangan IGD sudah ada"*.
**Validasi dijaga di dua lapis**: di PHP (form) dan di database (constraint).

---

## 6. Dashboard: Query yang Dipakai

File: [`modules/dashboard/index.php`](../simbdrs/modules/dashboard/index.php). Dashboard **hanya membaca** (SELECT), tidak mengubah data.

| Panel | Query inti | Aturan bisnis |
|---|---|---|
| Stok per golongan | `SELECT gol_darah, rhesus, COUNT(*) FROM kantong_darah WHERE status='TERSEDIA' AND tgl_kedaluwarsa >= CURDATE() GROUP BY gol_darah, rhesus` | F-04 |
| Hampir kedaluwarsa | `... WHERE status='TERSEDIA' AND DATEDIFF(tgl_kedaluwarsa, CURDATE()) BETWEEN 0 AND batas_peringatan_hari` | BR-08 |
| Stok di bawah minimum | `stok_minimal LEFT JOIN kantong_darah ... HAVING tersedia < jumlah_minimal` | BR-09 |
| Permintaan masuk | `... ORDER BY (urgensi='CITO') DESC, tgl_permintaan ASC` → CITO selalu paling atas | – |
| Permintaan (peran PERAWAT / DOKTER) | ditambah `AND p.id_ruangan = ?` (perawat) atau `AND p.id_dokter = ?` (dokter) lewat fungsi `batas_data()` | BR-11 |
| Grafik | `COUNT(*) ... GROUP BY DATE_FORMAT(tgl, '%Y-%m')` dari tabel `transfusi` dan `pemusnahan` | F-12 |

> Dashboard sekarang menampilkan 0 karena belum ada data transaksi. Angka akan muncul setelah
> data dummy dibuat atau modul penerimaan kantong selesai.

---

## 7. Tiga Aturan Keamanan Wajib (cek di setiap file baru)

| # | Aturan | Di kode | Mencegah |
|---|---|---|---|
| 1 | **Prepared statement** | `$pdo->prepare("... ?")` + `->execute([...])` | SQL Injection |
| 2 | **Escape saat menampilkan** | `<?= e($data) ?>` (fungsi `e()` = `htmlspecialchars`) | XSS (sisipan script jahat) |
| 3 | **Cek sesi & peran** | `wajib_hak('ruangan', 'tambah')` di baris paling atas | Orang tanpa izin membuka halaman |

Tambahan: **password_hash / password_verify** (password tidak disimpan asli) dan **token CSRF**
(`csrf_field()` di form + `cek_csrf()` saat diproses) supaya form tidak bisa dikirim dari situs lain.

---

## 8. Latihan Mandiri

Kerjakan berurutan. Setiap latihan, cek hasilnya di **browser** lalu di **phpMyAdmin**.

1. **Lihat data mengalir.** Login `admin` → Ruangan → Tambah "ICU". Lalu di phpMyAdmin buka tabel `ruangan` (Browse) dan `log_aktivitas`. Temukan baris baru di keduanya.
2. **Uji UNIQUE.** Tambah lagi "ICU". Apa pesan yang muncul? Kolom mana di tabel yang menyebabkannya?
3. **Uji foreign key.** Hapus "ICU" (berhasil, terhapus). Hapus "IGD" (hanya dinonaktifkan). Jelaskan bedanya.
4. **Uji hak akses.** Logout → login `igd` → ketik alamat `http://localhost/simbdrs/modules/master/ruangan/index.php`. Apa yang terjadi? Baris kode mana yang menolak?
5. **Ubah tampilan.** Di VS Code buka `config/app.php`, ganti `NAMA_RS`. Simpan, refresh browser.
6. **Tulis query sendiri.** Di tab SQL, tulis query untuk menghitung jumlah pengguna per role.
   <details><summary>Jawaban</summary>

   ```sql
   SELECT role, COUNT(*) AS jumlah FROM pengguna GROUP BY role;
   ```
   </details>

---

## 9. Persiapan Pertanyaan Dosen

| Pertanyaan | Jawaban singkat |
|---|---|
| Bagaimana web terhubung ke database? | Lewat PHP PDO di `config/koneksi.php`. Browser → Apache/PHP → query SQL → MySQL → hasil → HTML. |
| Kenapa pakai PDO & prepared statement? | Data dan query dipisah, sehingga aman dari SQL Injection. |
| Bagaimana menjaga integritas data? | PK, FK (InnoDB), UNIQUE, ENUM, NOT NULL, validasi di PHP, dan data yang sudah dipakai tidak dihapus tetapi dinonaktifkan. |
| Apa itu normalisasi di project ini? | Golongan, rhesus, komponen hanya disimpan di `kantong_darah`; tabel lain cukup merujuk `id_kantong`. UDD asal dibaca lewat relasi, tidak disimpan ulang. |
| Bagaimana tanggal kedaluwarsa dihitung? | `tgl_aftap + masa_simpan_hari` dari tabel `komponen_darah` (BR-01); angkanya disimpan di database, bukan di kode. |
| Apa itu FEFO? | First Expired First Out: kantong yang paling dekat kedaluwarsa disarankan dipakai dulu (`ORDER BY tgl_kedaluwarsa ASC`). |
| Bagaimana audit trail bekerja? | Fungsi `catat_log()` menulis ke tabel `log_aktivitas` setiap login, tambah, ubah, hapus. |
| Bagaimana hak akses diatur? | Kolom `role` di tabel `pengguna` + tabel `hak_akses` (diatur Admin di menu Hak Akses); fungsi `wajib_hak('menu', 'aksi')` di setiap halaman; menu sidebar & tombol mengikuti hak tersebut. |
| Kenapa data dummy? | Data kesehatan dilindungi UU No. 27/2022 tentang Pelindungan Data Pribadi. |
