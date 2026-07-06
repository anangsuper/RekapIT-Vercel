# Alur Aplikasi Rekap IT (Panduan Pengguna)

Berikut adalah alur kerja (workflow) pengguna dalam aplikasi Rekap IT, disusun berdasarkan aktivitas yang sering dilakukan pengguna.

## 1. Login & Dashboard
- **Login:** Masuk melalui `login.php`.
- **Dashboard:** Ringkasan statistik (Total Aset, Maintenance, Perbaikan, Biaya) dan aktivitas terbaru.

## 2. Menu Utama (Berbasis Aktivitas)
Navigasi disusun agar pengguna lebih mudah menemukan fitur berdasarkan apa yang ingin mereka kerjakan:

- **Dashboard:** Pusat informasi.
- **Data Master:** Pengelolaan referensi (Cabang, Divisi, Karyawan).
- **Aset:** Pengelolaan Kategori, Inventaris, dan Mutasi.
- **Maintenance:** Pencatatan perawatan rutin.
- **Perbaikan:** Pengelolaan tiket kerusakan dan sparepart.
- **Audit:** Verifikasi fisik aset.
- **Laporan:** Export data laporan.

---

## 3. Alur Kerja Utama

### A. Menambahkan Aset Baru
1. Buka **Aset → Data Aset**.
2. Klik **Tambah Aset**.
3. Isi informasi aset (Kode, Nama, SN, Kategori, Cabang, PIC, Kondisi).
4. Klik **Simpan**.

### B. Memindahkan Aset (Mutasi)
1. Buka **Aset → Mutasi Aset**.
2. Klik **Mutasi Baru**.
3. Pilih aset, lokasi lama, lokasi baru, dan PIC baru.
4. Klik **Simpan**.

### C. Melakukan Maintenance
1. Buka **Maintenance**.
2. Pilih aset dan isi detail aktivitas perawatan.
3. Masukkan tanggal dan catatan.
4. Klik **Simpan**.

### D. Menangani Perbaikan Aset
1. Buka **Perbaikan → Buat Tiket**.
2. Pilih aset, isi jenis kerusakan, deskripsi, dan prioritas.
3. Simpan tiket.
4. Teknisi memperbarui status (Proses/Selesai) dan mencatat sparepart yang digunakan serta biaya.

### E. Melakukan Audit Fisik
1. Buka **Audit**.
2. Klik **Mulai Audit**.
3. Pilih aset/lokasi, periksa fisik di lapangan.
4. Isi kondisi aktual, lokasi aktual, dan catatan jika ada selisih.
5. Simpan hasil audit.

### F. Membuat Laporan
1. Buka **Laporan**.
2. Pilih jenis laporan (Inventaris, Maintenance, Perbaikan, Audit).
3. Tentukan periode.
4. Klik **Export Excel**.
