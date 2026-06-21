# Alur Aplikasi Rekap IT

Berikut adalah alur kerja (workflow) pengguna dalam aplikasi Rekap IT:

## 1. Akses Awal
- **Login:** Pengguna masuk ke sistem melalui halaman `login.php`.
- **Dashboard:** Setelah login berhasil, pengguna diarahkan ke `dashboard.php` yang menampilkan statistik aset (Total Aset, Maintenance, Perbaikan, Biaya).

## 2. Navigasi Utama (Navbar)
Pengguna dapat memilih menu berdasarkan peran (Admin/Teknisi):

- **Master Data (Admin Only):**
  - Mengelola data dasar: `Cabang`, `Divisi`, dan `Data Karyawan`.
- **Inventaris:**
  - Mengelola `Kategori` aset.
  - Mengelola `Data Aset` (Inventaris IT).
  - Mengelola `Mutasi` (perpindahan aset antar lokasi).
- **Operasional:**
  - `Perawatan` (Maintenance rutin).
  - `Perbaikan` (Tiket perbaikan aset rusak).
  - `Sparepart` (Manajemen suku cadang).
- **Laporan (Admin Only):**
  - `Audit Fisik` (Pengecekan aset langsung).
  - `Log Aktivitas` (Riwayat tindakan pengguna).
  - `Export Laporan` (Data ke Excel).

## 3. Alur Kerja Spesifik (Contoh)

### A. Registrasi Aset Baru
1. Masuk ke menu **Inventaris > Data Aset**.
2. Klik tombol "Add Asset".
3. Isi detail aset (Kode, SN, Nama, Cabang, Kondisi, dll).
4. Klik simpan.

### B. Proses Audit Fisik
1. Masuk ke menu **Laporan > Audit Fisik**.
2. Klik "Mulai Audit Baru".
3. Pilih aset yang akan diaudit (Sistem otomatis menampilkan kondisi dan lokasi saat ini).
4. Masukkan kondisi fisik hasil pemeriksaan lapangan dan catatan jika ada selisih.
5. Klik simpan (Sistem akan secara otomatis mencatat audit dan memperbarui status aset jika diperlukan).

### C. Penanganan Perbaikan
1. Masuk ke menu **Operasional > Perbaikan**.
2. Catat kerusakan aset dan status perbaikan (Proses/Selesai).
3. Jika selesai, sistem akan menghitung biaya perbaikan yang diakumulasikan ke biaya bulanan.
