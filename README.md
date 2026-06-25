# 🖥️ Rekap IT - Sistem Manajemen Aset & Maintenance Profesional

Rekap IT adalah aplikasi manajemen infrastruktur IT berbasis web yang dibangun untuk memantau siklus hidup aset, mulai dari pendataan, pemeliharaan rutin, hingga manajemen perbaikan jika terjadi kerusakan.

---

## 🚀 Fitur Utama

### 1. Manajemen Inventarisasi Aset
Sistem terpusat untuk mendata aset IT perusahaan dengan fitur:
- **CRUD Aset:** Mengelola data detail (Kode, Serial Number, Lokasi/Cabang, Divisi, Pemegang).
- **Kategorisasi:** Klasifikasi aset untuk memudahkan filtering dan pelaporan.

### 2. Manajemen Pemeliharaan (Maintenance)
- **Log Maintenance:** Pencatatan rutinitas pemeriksaan kondisi aset.
- **Filter Cerdas:** Aset yang sudah dimaintenance pada bulan berjalan akan **otomatis disembunyikan** dari form input maintenance untuk mencegah duplikasi pengecekan.
- **Maintenance Massal (Bulk):** Efisiensi input untuk teknisi dalam melakukan pengecekan aset dalam jumlah besar.

### 3. Manajemen Perbaikan (Repair)
- **Tiket Perbaikan:** Jika aset mengalami kerusakan, sistem menyediakan modul Repair yang terpisah untuk melacak:
    - Keluhan/Kerusakan.
    - Status Perbaikan (Proses, Selesai, Batal).
    - Histori biaya dan tindakan perbaikan.
- Aset yang sedang dalam status perbaikan tetap dapat dikelola di modul ini meskipun sudah melewati bulan maintenance.

### 4. Audit & Mutasi
- **Audit Fisik:** Modul untuk verifikasi kecocokan data sistem dengan kondisi nyata.
- **Mutasi:** Pencatatan riwayat perpindahan aset antar lokasi atau karyawan.

---

## 🛠️ Spesifikasi Teknis

| Komponen | Spesifikasi |
| :--- | :--- |
| **Bahasa** | PHP 8.2+ |
| **Database** | MySQL / MariaDB |
| **Frontend** | Bootstrap 5.3, Vanilla JS, CSS3 |
| **Keamanan** | PDO Prepared Statements (SQL Injection Protection) |

---

## 📦 Panduan Instalasi & Setup

### Prasyarat
- PHP 8.2 atau lebih tinggi.
- MySQL/MariaDB server.
- Web Server (Apache/Nginx/Localhost).

### Langkah-langkah
1. **Clone Repositori:**
   ```bash
   git clone https://github.com/anangsuper/RekapIT-Vercel.git
   ```
2. **Setup Database:**
   - Impor file `database/rekap_it.sql` ke database MySQL Anda.
3. **Konfigurasi:**
   - Sesuaikan konfigurasi database pada file `config/database.php` sesuai dengan environment lokal Anda.
4. **Jalankan Aplikasi:**
   - Arahkan web server Anda ke direktori root proyek.

---

## 📂 Struktur Direktori
- `/config` : Konfigurasi koneksi database.
- `/controllers` : Logic pemrosesan data (MVC pattern).
- `/models` : Interaksi dengan tabel database.
- `/views` : File tampilan antarmuka (UI).
- `/database` : Skema database.

---

**Rekap IT - Transformasi Digital untuk Manajemen Aset IT yang Lebih Terukur.**
