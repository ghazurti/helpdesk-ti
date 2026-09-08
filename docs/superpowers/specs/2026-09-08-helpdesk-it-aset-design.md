# Desain: Helpdesk IT & Inventaris Aset (Standalone)

- **Tanggal:** 2026-09-08
- **Status:** Disetujui untuk implementasi (menunggu review spec)
- **Pemilik:** Tim IT

## 1. Tujuan & Ruang Lingkup

Aplikasi web internal untuk **tim IT rumah sakit** mencatat pekerjaan yang
sudah dilakukan (perbaikan, instalasi, dll.) dan mengelola **inventaris aset**
(komputer, printer, dll.), lalu menghasilkan **laporan** yang bisa dicetak.

**Berdiri sendiri (standalone).** Tidak terhubung ke database SIMRS-Khanza.
Semua data dikelola sendiri.

**Bukan** sistem tiket pengaduan: unit lain **tidak** mengajukan permintaan.
Tim IT yang mencatat sendiri pekerjaannya. Tiap pekerjaan tetap diberi
**nomor tiket otomatis** sebagai identitas/antrian.

### Di luar ruang lingkup (untuk sekarang)
- Integrasi baca/tulis ke database SIMRS.
- Portal/pengajuan dari unit lain.
- Notifikasi email/WhatsApp.

## 2. Stack Teknologi

- **Laravel 11** + **Filament v3** (panel admin: tabel, filter, form, dashboard).
- **spatie/laravel-permission** untuk role.
- **Export**: Excel (aksi bawaan Filament) + **PDF** via `barryvdh/laravel-dompdf`.
- **Database**: **MySQL/MariaDB** (XAMPP, MariaDB 10.4.28), database `helpdesk_it`.
- **Testing**: Pest (bawaan Laravel).

### Lingkungan (terverifikasi 2026-09-08)
- PHP 8.2.30, Composer 2.9.5, Node 22.
- MariaDB 10.4.28 via XAMPP di `127.0.0.1:3306`, user `root` tanpa password.
- Database `helpdesk_it` sudah dibuat (utf8mb4).

## 3. Peran & Hak Akses

Dua role via spatie/laravel-permission:

| Kemampuan                                   | Admin | Staf IT |
|---------------------------------------------|:-----:|:-------:|
| Kelola user                                 |  ✔    |         |
| Kelola master data (unit, kategori)         |  ✔    |         |
| Input/edit aset & tiket                      |  ✔    |   ✔     |
| Hapus aset & tiket                           |  ✔    |         |
| Lihat data, dashboard, laporan               |  ✔    |   ✔     |
| Export laporan (PDF/Excel)                   |  ✔    |   ✔     |

## 4. Modul & Model Data

### A. Master Data (dikelola Admin)
- **units** — `id, nama, keterangan, timestamps`
- **work_categories** — `id, nama, timestamps` (hardware, jaringan, software, SIMRS, printer, …)
- **asset_categories** — `id, nama, timestamps` (komputer, printer, switch, …)
- **users** — `id, name, email, password, timestamps` (+ role via spatie)

### B. Inventaris Aset
- **assets**
  - `id, kode (unik), nama, asset_category_id, merk, model, serial_number`
  - `tanggal_beli, harga, garansi_sampai`
  - `kondisi` enum: `baik | rusak | perbaikan | dihapus`
  - `unit_id` (lokasi saat ini), `masa_manfaat_bulan`, `catatan`, `timestamps`
  - **Penyusutan**: dihitung garis lurus dari `harga`, `tanggal_beli`,
    `masa_manfaat_bulan` (nilai buku ditampilkan, tidak disimpan).
- **asset_movements** (riwayat mutasi antar unit)
  - `id, asset_id, from_unit_id (nullable), to_unit_id, tanggal, keterangan, user_id, timestamps`
- **Riwayat perbaikan**: relasi `assets hasMany tickets` — otomatis tampil di halaman aset.

### C. Tiket / Log Pekerjaan
- **tickets**
  - `id, nomor (unik, auto: TKT-YYYY-#### )`
  - `user_id (petugas IT), unit_id, work_category_id, asset_id (nullable)`
  - `judul, deskripsi, tindakan (nullable)`
  - `prioritas` enum: `rendah | sedang | tinggi`
  - `status` enum: `baru | proses | pending | selesai`
  - `tanggal, selesai_at (nullable), timestamps`
- **ticket_attachments** — `id, ticket_id, path, nama_asli, timestamps` (foto/lampiran)
- **ticket_logs** — `id, ticket_id, user_id, status_lama, status_baru, catatan, timestamps` (riwayat status)

### D. Laporan & Dashboard
- **Dashboard** (widget Filament):
  - Jumlah tiket per status, per kategori, per unit, per petugas.
  - Jumlah aset per kondisi.
  - Grafik tren pekerjaan per bulan.
- **Halaman Laporan**:
  - Filter: rentang tanggal, petugas, unit, kategori, status.
  - Tampil sebagai tabel → **Export PDF** (dompdf) & **Export Excel**.

## 5. Aturan Bisnis Utama
- **Nomor tiket** dibuat otomatis saat create: format `TKT-{tahun}-{urut 4 digit}`,
  urut di-reset per tahun. Digenerate di model event `creating` dengan penguncian
  agar tidak bentrok.
- Saat **status tiket** berubah → catat ke `ticket_logs`. Saat menjadi `selesai`,
  set `selesai_at`.
- **Mutasi aset**: menyimpan `asset_movements` dan memperbarui `assets.unit_id`.
- Staf IT hanya bisa mengubah data, tidak menghapus (policy Filament + spatie).

## 6. Rencana Bertahap
1. Setup Laravel 11 + Filament v3 + auth + spatie role + seeder admin.
2. Master data (unit, kategori kerja, kategori aset) + manajemen user.
3. Inventaris Aset (CRUD, mutasi, penyusutan, riwayat perbaikan).
4. Tiket/Log Pekerjaan (nomor otomatis, lampiran, riwayat status).
5. Dashboard + Laporan + Export PDF/Excel.

## 7. Strategi Testing (Pest)
- Nomor tiket otomatis benar & unik (termasuk reset per tahun).
- Perubahan status menulis `ticket_logs` & mengisi `selesai_at`.
- Mutasi aset memperbarui `unit_id` dan menyimpan riwayat.
- Perhitungan penyusutan garis lurus.
- Pembatasan akses per role (Staf IT tidak bisa hapus / akses master data).
