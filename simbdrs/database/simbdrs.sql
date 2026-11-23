-- =====================================================================
-- SIMBDRS — Struktur Database
-- Sistem Informasi Manajemen Bank Darah Rumah Sakit
--
-- Cara pakai: phpMyAdmin → tab "Import" → pilih file ini → Go.
-- File ini MENGHAPUS database simbdrs lama (jika ada) lalu membuat ulang.
-- Kamus data lengkap: dokumen/ERD.md
-- =====================================================================

DROP DATABASE IF EXISTS simbdrs;
CREATE DATABASE simbdrs CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE simbdrs;

-- =====================================================================
-- 1. TABEL MASTER
-- =====================================================================

-- Ruangan RS yang bisa meminta darah
CREATE TABLE ruangan (
    id_ruangan    INT AUTO_INCREMENT PRIMARY KEY,
    nama_ruangan  VARCHAR(100) NOT NULL UNIQUE,
    aktif         TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- UDD/UTD PMI pengirim darah
CREATE TABLE udd_asal (
    id_udd    INT AUTO_INCREMENT PRIMARY KEY,
    nama_udd  VARCHAR(100) NOT NULL UNIQUE,
    alamat    VARCHAR(255) NULL,
    telepon   VARCHAR(20)  NULL,
    aktif     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Data referensi komponen darah. Angka aturan medis disimpan di sini,
-- BUKAN di kode PHP, supaya bisa diubah admin.
CREATE TABLE komponen_darah (
    id_komponen            INT AUTO_INCREMENT PRIMARY KEY,
    kode                   VARCHAR(10)  NOT NULL UNIQUE,
    nama_komponen          VARCHAR(100) NOT NULL,
    suhu_min               DECIMAL(4,1) NULL,
    suhu_max               DECIMAL(4,1) NULL,
    masa_simpan_hari       INT NOT NULL,          -- dipakai BR-01
    batas_peringatan_hari  INT NOT NULL           -- dipakai BR-08
) ENGINE=InnoDB;

-- Pasien (DATA FIKTIF saja — UU No. 27 Tahun 2022)
CREATE TABLE pasien (
    id_pasien      INT AUTO_INCREMENT PRIMARY KEY,
    no_rm          VARCHAR(20)  NOT NULL UNIQUE,
    nama_pasien    VARCHAR(100) NOT NULL,
    tgl_lahir      DATE         NOT NULL,
    jenis_kelamin  ENUM('L','P') NOT NULL,
    gol_darah      ENUM('A','B','AB','O') NULL,   -- wajib sebelum permintaan diproses (BR-13)
    rhesus         ENUM('+','-') NULL,
    aktif          TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Akun pengguna. id_ruangan wajib diisi jika role = PERAWAT (dicek di PHP).
CREATE TABLE pengguna (
    id_pengguna    INT AUTO_INCREMENT PRIMARY KEY,
    nama           VARCHAR(100) NOT NULL,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('ADMIN','PETUGAS','KEPALA','DOKTER','PERAWAT') NOT NULL,
    id_ruangan     INT NULL,
    aktif          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pengguna_ruangan FOREIGN KEY (id_ruangan) REFERENCES ruangan(id_ruangan)
) ENGINE=InnoDB;

-- Dokter penanggung jawab permintaan darah (BR-14).
-- Setiap dokter WAJIB punya akun login: id_pengguna NOT NULL + UNIQUE (relasi 1:1).
-- Nama & status aktif dokter dibaca dari tabel pengguna (tidak disimpan dua kali).
CREATE TABLE dokter (
    id_dokter    INT AUTO_INCREMENT PRIMARY KEY,
    id_pengguna  INT NOT NULL UNIQUE,
    spesialis    VARCHAR(100) NULL,
    CONSTRAINT fk_dokter_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB;

-- Hak akses per peran per menu, diatur Admin lewat menu "Hak Akses" (tanpa ubah kode).
-- kunci_menu mengacu pada daftar menu di includes/menu.php.
CREATE TABLE hak_akses (
    role        ENUM('ADMIN','PETUGAS','KEPALA','DOKTER','PERAWAT') NOT NULL,
    kunci_menu  VARCHAR(30) NOT NULL,
    lihat       TINYINT(1) NOT NULL DEFAULT 0,
    tambah      TINYINT(1) NOT NULL DEFAULT 0,
    ubah        TINYINT(1) NOT NULL DEFAULT 0,
    hapus       TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (role, kunci_menu)
) ENGINE=InnoDB;

-- Batas stok minimal per kombinasi komponen + golongan + rhesus (BR-09)
CREATE TABLE stok_minimal (
    id_stok_minimal  INT AUTO_INCREMENT PRIMARY KEY,
    id_komponen      INT NOT NULL,
    gol_darah        ENUM('A','B','AB','O') NOT NULL,
    rhesus           ENUM('+','-') NOT NULL,
    jumlah_minimal   INT NOT NULL DEFAULT 0,
    CONSTRAINT uq_stok_minimal UNIQUE (id_komponen, gol_darah, rhesus),
    CONSTRAINT fk_stokmin_komponen FOREIGN KEY (id_komponen) REFERENCES komponen_darah(id_komponen)
) ENGINE=InnoDB;

-- Pengaturan umum (angka aturan yang bisa diubah admin)
CREATE TABLE pengaturan (
    kunci       VARCHAR(50)  PRIMARY KEY,
    nilai       VARCHAR(100) NOT NULL,
    keterangan  VARCHAR(255) NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 2. TRANSAKSI STOK
-- =====================================================================

-- Header penerimaan: satu kiriman dari UDD
CREATE TABLE penerimaan (
    id_penerimaan  INT AUTO_INCREMENT PRIMARY KEY,
    no_dokumen     VARCHAR(50)  NOT NULL,
    id_udd         INT NOT NULL,
    tgl_terima     DATETIME     NOT NULL,
    suhu_terima    DECIMAL(4,1) NULL,
    id_petugas     INT NOT NULL,
    catatan        TEXT NULL,
    CONSTRAINT fk_penerimaan_udd     FOREIGN KEY (id_udd)     REFERENCES udd_asal(id_udd),
    CONSTRAINT fk_penerimaan_petugas FOREIGN KEY (id_petugas) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB;

-- =====================================================================
-- 3. LAYANAN PASIEN (permintaan dibuat sebelum kantong_darah
--    karena kantong_darah punya FK id_permintaan_dipesan)
-- =====================================================================

CREATE TABLE permintaan_darah (
    id_permintaan   INT AUTO_INCREMENT PRIMARY KEY,
    no_permintaan   VARCHAR(30)  NOT NULL UNIQUE,
    id_pasien       INT NOT NULL,
    id_ruangan      INT NOT NULL,                 -- BR-11
    id_peminta      INT NOT NULL,                 -- pengguna yang menginput
    id_dokter       INT NOT NULL,                 -- dokter penanggung jawab (BR-14)
    id_komponen     INT NOT NULL,
    jumlah_kantong  INT NOT NULL,
    urgensi         ENUM('BIASA','CITO') NOT NULL DEFAULT 'BIASA',
    diagnosis       VARCHAR(255) NOT NULL,
    tgl_permintaan  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('MENUNGGU','DIPROSES','TERPENUHI_SEBAGIAN','TERPENUHI','DIBATALKAN')
                    NOT NULL DEFAULT 'MENUNGGU',
    CONSTRAINT chk_jumlah_kantong CHECK (jumlah_kantong > 0),
    CONSTRAINT fk_permintaan_pasien   FOREIGN KEY (id_pasien)   REFERENCES pasien(id_pasien),
    CONSTRAINT fk_permintaan_ruangan  FOREIGN KEY (id_ruangan)  REFERENCES ruangan(id_ruangan),
    CONSTRAINT fk_permintaan_peminta  FOREIGN KEY (id_peminta)  REFERENCES pengguna(id_pengguna),
    CONSTRAINT fk_permintaan_dokter   FOREIGN KEY (id_dokter)   REFERENCES dokter(id_dokter),
    CONSTRAINT fk_permintaan_komponen FOREIGN KEY (id_komponen) REFERENCES komponen_darah(id_komponen)
) ENGINE=InnoDB;

-- TABEL INTI: satu baris = satu kantong darah
CREATE TABLE kantong_darah (
    id_kantong             INT AUTO_INCREMENT PRIMARY KEY,
    no_kantong             VARCHAR(30) NOT NULL UNIQUE,   -- BR-02
    id_penerimaan          INT NOT NULL,
    id_komponen            INT NOT NULL,
    gol_darah              ENUM('A','B','AB','O') NOT NULL,
    rhesus                 ENUM('+','-') NOT NULL,
    volume_ml              INT NOT NULL,
    tgl_aftap              DATE NOT NULL,
    tgl_kedaluwarsa        DATE NOT NULL,                 -- BR-01: dihitung PHP = tgl_aftap + masa_simpan_hari
    status                 ENUM('TERSEDIA','DIPESAN','DIKELUARKAN','DITRANSFUSIKAN','DIKEMBALIKAN','DIMUSNAHKAN')
                           NOT NULL DEFAULT 'TERSEDIA',
    id_permintaan_dipesan  INT NULL,                      -- terisi saat DIPESAN
    CONSTRAINT chk_tgl_kantong CHECK (tgl_kedaluwarsa >= tgl_aftap),
    CONSTRAINT fk_kantong_penerimaan FOREIGN KEY (id_penerimaan)         REFERENCES penerimaan(id_penerimaan),
    CONSTRAINT fk_kantong_komponen   FOREIGN KEY (id_komponen)           REFERENCES komponen_darah(id_komponen),
    CONSTRAINT fk_kantong_permintaan FOREIGN KEY (id_permintaan_dipesan) REFERENCES permintaan_darah(id_permintaan),
    -- Index untuk query pencocokan FEFO & hitung stok
    INDEX idx_kantong_cocok (status, id_komponen, gol_darah, rhesus, tgl_kedaluwarsa)
) ENGINE=InnoDB;

-- Hasil uji silang serasi (crossmatch), termasuk yang TIDAK_COCOK
CREATE TABLE uji_silang (
    id_uji         INT AUTO_INCREMENT PRIMARY KEY,
    id_permintaan  INT NOT NULL,
    id_kantong     INT NOT NULL,
    hasil          ENUM('COCOK','TIDAK_COCOK') NOT NULL,   -- BR-07
    tgl_uji        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_petugas     INT NOT NULL,
    catatan        TEXT NULL,
    CONSTRAINT fk_uji_permintaan FOREIGN KEY (id_permintaan) REFERENCES permintaan_darah(id_permintaan),
    CONSTRAINT fk_uji_kantong    FOREIGN KEY (id_kantong)    REFERENCES kantong_darah(id_kantong),
    CONSTRAINT fk_uji_petugas    FOREIGN KEY (id_petugas)    REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB;

-- Transfusi: dibuat saat kantong DIKELUARKAN, dilengkapi ruangan saat transfusi
CREATE TABLE transfusi (
    id_transfusi     INT AUTO_INCREMENT PRIMARY KEY,
    id_kantong       INT NOT NULL UNIQUE,     -- BR-06: satu kantong hanya untuk satu pasien
    id_permintaan    INT NOT NULL,
    id_pasien        INT NOT NULL,
    tgl_keluar_bdrs  DATETIME NOT NULL,
    tgl_mulai        DATETIME NULL,
    tgl_selesai      DATETIME NULL,
    id_pencatat      INT NOT NULL,
    CONSTRAINT fk_transfusi_kantong    FOREIGN KEY (id_kantong)    REFERENCES kantong_darah(id_kantong),
    CONSTRAINT fk_transfusi_permintaan FOREIGN KEY (id_permintaan) REFERENCES permintaan_darah(id_permintaan),
    CONSTRAINT fk_transfusi_pasien     FOREIGN KEY (id_pasien)     REFERENCES pasien(id_pasien),
    CONSTRAINT fk_transfusi_pencatat   FOREIGN KEY (id_pencatat)   REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB;

-- =====================================================================
-- 4. KANTONG KELUAR (bukan untuk pasien)
-- =====================================================================

-- UDD tujuan pengembalian dibaca lewat kantong_darah → penerimaan.id_udd (tidak disimpan ulang)
CREATE TABLE pengembalian (
    id_pengembalian  INT AUTO_INCREMENT PRIMARY KEY,
    id_kantong       INT NOT NULL UNIQUE,
    tgl_kembali      DATETIME NOT NULL,
    alasan           VARCHAR(255) NOT NULL,
    id_petugas       INT NOT NULL,
    CONSTRAINT fk_pengembalian_kantong FOREIGN KEY (id_kantong) REFERENCES kantong_darah(id_kantong),
    CONSTRAINT fk_pengembalian_petugas FOREIGN KEY (id_petugas) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB;

CREATE TABLE pemusnahan (
    id_pemusnahan  INT AUTO_INCREMENT PRIMARY KEY,
    id_kantong     INT NOT NULL UNIQUE,
    tgl_musnah     DATETIME NOT NULL,
    alasan         ENUM('KEDALUWARSA','RUSAK','HEMOLISIS','LAINNYA') NOT NULL,
    keterangan     TEXT NULL,
    id_petugas     INT NOT NULL,
    CONSTRAINT fk_pemusnahan_kantong FOREIGN KEY (id_kantong) REFERENCES kantong_darah(id_kantong),
    CONSTRAINT fk_pemusnahan_petugas FOREIGN KEY (id_petugas) REFERENCES pengguna(id_pengguna)
) ENGINE=InnoDB;

-- =====================================================================
-- 5. TATA KELOLA
-- =====================================================================

-- Audit trail (BR-12)
CREATE TABLE log_aktivitas (
    id_log       INT AUTO_INCREMENT PRIMARY KEY,
    id_pengguna  INT NULL,
    aksi         VARCHAR(50) NOT NULL,
    nama_tabel   VARCHAR(50) NULL,
    id_data      INT NULL,
    keterangan   TEXT NULL,
    waktu        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna),
    INDEX idx_log_waktu (waktu)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. DATA AWAL (wajib ada agar aplikasi bisa dipakai)
--    Data transaksi contoh ada di data_dummy.sql (dibuat terpisah).
-- =====================================================================

-- Komponen darah. Sumber: README bag. 3 (mengacu Permenkes 91/2015).
-- TODO: verifikasi -> masa simpan & suhu FFP, serta batas_peringatan_hari semua komponen.
INSERT INTO komponen_darah (kode, nama_komponen, suhu_min, suhu_max, masa_simpan_hari, batas_peringatan_hari) VALUES
('WB',  'Whole Blood (darah lengkap)',          2.0,  6.0,  35,  7),
('PRC', 'Packed Red Cell (sel darah merah pekat)', 2.0,  6.0,  35,  7),
('TC',  'Thrombocyte Concentrate (trombosit)',  20.0, 24.0,  5,  2),
('FFP', 'Fresh Frozen Plasma',                  NULL, NULL, 365, 30);

INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES
('periode_pemesanan_hari', '7',  'BR-10: pemesanan ke UDD dihitung untuk kebutuhan N hari ke depan'),
('periode_rata_rata_hari', '30', 'BR-10: rentang hari untuk menghitung rata-rata pemakaian harian');

-- Ruangan IGD dibutuhkan oleh akun contoh "igd"
INSERT INTO ruangan (nama_ruangan) VALUES ('IGD');

-- Hak akses bawaan (bisa diubah Admin di menu Hak Akses).
-- Kolom: role, menu, lihat, tambah, ubah, hapus
INSERT INTO hak_akses (role, kunci_menu, lihat, tambah, ubah, hapus) VALUES
('ADMIN',   'ruangan',      1, 1, 1, 1),
('ADMIN',   'dokter',       1, 1, 1, 1),
('ADMIN',   'pasien',       1, 1, 1, 1),
('ADMIN',   'komponen',     1, 1, 1, 1),
('ADMIN',   'udd',          1, 1, 1, 1),
('ADMIN',   'stok_minimal', 1, 0, 1, 0),
('ADMIN',   'pengguna',     1, 1, 1, 1),
('ADMIN',   'hak_akses',    1, 0, 1, 0),
('ADMIN',   'log',          1, 0, 0, 0),
('PETUGAS', 'penerimaan',   1, 1, 0, 0),
('PETUGAS', 'stok',         1, 0, 0, 0),
('PETUGAS', 'permintaan',   1, 0, 1, 1),
('PETUGAS', 'transfusi',    1, 1, 0, 0),
('PETUGAS', 'keluar',       1, 1, 0, 0),
('PETUGAS', 'rekomendasi',  1, 0, 0, 0),
('PETUGAS', 'laporan',      1, 0, 0, 0),
('KEPALA',  'stok',         1, 0, 0, 0),
('KEPALA',  'rekomendasi',  1, 0, 0, 0),
('KEPALA',  'laporan',      1, 0, 0, 0),
('DOKTER',  'permintaan',   1, 0, 0, 0),
('DOKTER',  'transfusi',    1, 0, 0, 0),
('PERAWAT', 'permintaan',   1, 1, 0, 1),
('PERAWAT', 'transfusi',    1, 0, 1, 0);

-- Akun contoh (HANYA untuk demo lokal). Password asli tercantum di README bag. 10.
-- Hash dibuat dengan password_hash() PHP 8.2.
INSERT INTO pengguna (nama, username, password_hash, role, id_ruangan) VALUES
('Administrator',      'admin',   '$2y$10$tqxA77hFBgd42QYckLOCTeIXDHrkCI/jfw2KK1./t4AUv.OJqQD0K', 'ADMIN',   NULL),
('Petugas BDRS',       'petugas', '$2y$10$Plqxib7sqIfqCTtA4j1XTuj16Jh0WeQ/RvOSbUosLbQqxIzppMRau', 'PETUGAS', NULL),
('Ns. Rina Marlina',   'igd',     '$2y$10$JZuSaDYuZETkBfcDHp4Rd.IebMgNEroKe35sKPwH6hZlZU6lvs3AG', 'PERAWAT', 1),
('Kepala BDRS',        'kepala',  '$2y$10$sp4WKPiqbV3k85/JvPwtGOF.MPHHZzNZRjnej0oF0Th4.T8rSe0OO', 'KEPALA',  NULL);
