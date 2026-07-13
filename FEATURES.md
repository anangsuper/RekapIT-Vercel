# Daftar Fitur Rekap IT

Dokumen ini merangkum seluruh fungsionalitas yang tersedia pada aplikasi **Rekap IT**.

## 1. Modul Manajemen Aset
*   **Inventarisasi:** Pencatatan detail aset (kode, serial, merk, kondisi, lokasi).
*   **Kategorisasi:** Pengelompokan aset berdasarkan kategori.
*   **Status Kondisi:** Pelacakan kondisi aset (Baik, Rusak Ringan, Rusak Berat).

## 2. Modul Karyawan
*   **Direktori Karyawan:** Manajemen data karyawan dan jabatan.
*   **Filter Berbasis Cabang:** Pemisahan data karyawan melalui tab navigasi per cabang untuk kemudahan akses.
*   **Validasi NIP:** Pencegahan duplikasi NIP saat penambahan karyawan.

## 3. Modul Pemeliharaan (Maintenance)
*   **Maintenance Rutin:** Pencatatan pemeriksaan aset.
*   **Filter Cerdas (Automated):** Aset yang sudah dimaintenance pada bulan berjalan otomatis tidak muncul di formulir tambah maintenance untuk mencegah duplikasi.
*   **Maintenance Massal:** Input cepat untuk pengecekan aset dalam jumlah banyak.

## 4. Modul Perbaikan (Repair)
*   **Tiket Perbaikan:** Manajemen kerusakan aset dari laporan hingga penyelesaian.
*   **Pencarian Aset Cepat:** Fitur pencarian (search bar) pada dropdown aset di formulir pembuatan tiket perbaikan.
*   **Tracking Biaya:** Pencatatan biaya perbaikan per tiket.
*   **Status Tiket:** Pelacakan status (Proses, Selesai, Batal).

## 5. Modul Tambahan & Keamanan
*   **Audit & Mutasi:** Verifikasi fisik dan pelacakan perpindahan aset.
*   **Stok Sparepart:** Manajemen suku cadang.
*   **Pelaporan:** Ekspor data ke format Excel.
*   **Log Aktivitas:** Pencatatan rekam jejak pengguna untuk transparansi.
*   **Keamanan:** Proteksi terhadap SQL Injection menggunakan *PDO Prepared Statements*.
*   **Lokalisasi:** Antarmuka aplikasi dalam Bahasa Indonesia yang baku.
