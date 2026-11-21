-- =====================================================================
-- Tata letak rapi untuk phpMyAdmin Designer (diagram relasi SIMBDRS)
-- Membuat halaman Designer "ERD SIMBDRS" berisi posisi 16 tabel.
--
-- Cara pakai: phpMyAdmin → tab SQL (di level server, bukan di dalam db) → tempel → Go.
-- Lalu buka simbdrs → More → Designer → ikon "Open page" → pilih "ERD SIMBDRS".
-- Boleh dijalankan ulang; halaman lama dengan nama sama akan diganti.
-- =====================================================================

USE phpmyadmin;

DELETE c FROM pma__table_coords c
JOIN pma__pdf_pages p ON p.page_nr = c.pdf_page_number
WHERE p.db_name = 'simbdrs' AND p.page_descr = 'ERD SIMBDRS';
DELETE FROM pma__pdf_pages WHERE db_name = 'simbdrs' AND page_descr = 'ERD SIMBDRS';

INSERT INTO pma__pdf_pages (db_name, page_descr) VALUES ('simbdrs', 'ERD SIMBDRS');
SET @hal = LAST_INSERT_ID();

INSERT INTO pma__table_coords (db_name, table_name, pdf_page_number, x, y) VALUES
-- Alur stok (baris atas): UDD → penerimaan → kantong → keluar
('simbdrs', 'udd_asal',         @hal,   20,  20),
('simbdrs', 'penerimaan',       @hal,  260,  20),
('simbdrs', 'kantong_darah',    @hal,  500,  20),
('simbdrs', 'pengembalian',     @hal, 1210,  20),
('simbdrs', 'pemusnahan',       @hal, 1210, 190),
-- Referensi komponen
('simbdrs', 'komponen_darah',   @hal,   20, 190),
('simbdrs', 'stok_minimal',     @hal,  260, 240),
-- Layanan pasien (tengah): uji silang → transfusi
('simbdrs', 'uji_silang',       @hal,  480, 420),
('simbdrs', 'transfusi',        @hal,  780, 420),
-- Master pasien & permintaan (bawah kiri)
('simbdrs', 'ruangan',          @hal,   20, 410),
('simbdrs', 'dokter',           @hal,   20, 530),
('simbdrs', 'pasien',           @hal,   20, 680),
('simbdrs', 'permintaan_darah', @hal,  260, 680),
-- Tata kelola (kanan)
('simbdrs', 'pengguna',         @hal, 1680,  20),
('simbdrs', 'log_aktivitas',    @hal, 1680, 260),
('simbdrs', 'pengaturan',       @hal, 1680, 470);

SELECT @hal AS nomor_halaman_designer;
