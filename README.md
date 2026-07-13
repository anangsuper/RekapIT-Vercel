# Rekap IT - Asset Management System dengan Google Sheets Backend

Rekap IT adalah aplikasi web modern berbasis PHP yang dirancang untuk mengelola inventaris perangkat keras perusahaan. Aplikasi ini memiliki fitur integrasi dua arah dengan Google Sheets, bertindak sebagai database cloud utama, dengan performa tinggi yang didukung oleh SQLite lokal sebagai read-cache.

---

## 🌟 Fitur Utama

- **Dashboard Statistik:** Ringkasan kondisi operasional IT secara real-time (Total Aset, Maintenance, Perlu Tindakan, Biaya Repair) dilengkapi dengan widget status sinkronisasi Google Sheets.
- **Manajemen Inventaris:** Pendaftaran, pelacakan, pencarian cepat, dan pembaruan detail aset (kategori, cabang, divisi, spesifikasi, kondisi, dan foto).
- **Manajemen Operasional:**
  - **Maintenance:** Pencatatan pengecekan rutin secara otomatis/massal dengan filter bulan berjalan untuk menghindari duplikasi.
  - **Perbaikan (Repair):** Pemantauan tiket kerusakan, estimasi biaya, dan pelacakan suku cadang (sparepart).
- **Audit & Mutasi:** Riwayat pelacakan perpindahan fisik aset serta audit kondisi fisik di lapangan.
- **Log Aktivitas:** Log terperinci yang mencatat aksi setiap pengguna demi transparansi.
- **Export Data:** Ekspor data laporan (Excel/PDF).
- **Sinkronisasi Google Sheets Terpadu:** Dukungan sinkronisasi otomatis *real-time* saat terjadi modifikasi data serta tombol manual sync di bagian header dashboard.

---

## 🏗️ Arsitektur Sinkronisasi Database

Aplikasi ini menggunakan pendekatan arsitektur hibrida untuk performa optimal dan reliabilitas:

1. **Write-Through to Google Sheets:** Setiap kali ada operasi penulisan data (Insert, Update, Delete) di aplikasi, aksi tersebut langsung diteruskan ke Google Sheets API secara real-time menggunakan autentikasi Google Service Account.
2. **High-Speed SQLite Cache:** Untuk operasi pembacaan (Read), aplikasi membaca cache database SQLite lokal agar performa rendering halaman sangat cepat dan bebas dari limitasi rate-limit API Google.
3. **Automated Background Sync:** Cache SQLite diperbarui secara otomatis dari Google Sheets setiap 15 menit, atau secara instan ketika pengguna menekan tombol **Sync** manual di header halaman.

---

## ⚙️ Persyaratan Sistem
- PHP 8.2 atau lebih baru
- Ekstensi PHP: `ext-dom`, `ext-mbstring`, `ext-gd`, `ext-curl`, `ext-openssl`, `ext-sqlite3`
- Composer (untuk menginstal Dompdf & FPDF)

---

## 🚀 Instalasi & Konfigurasi Lokal

1. **Clone repositori:**
   ```bash
   git clone https://github.com/anangsuper/RekapIT-Vercel.git
   cd RekapIT-Vercel
   ```

2. **Install dependency PHP:**
   ```bash
   composer install
   ```

3. **Salin kredensial Google Service Account:**
   Letakkan file kunci JSON Service Account Anda di folder root dengan format nama `rekapit-*.json` atau pindahkan ke `config/service-account.json`.

4. **Konfigurasikan file `.env`:**
   Salin `.env.example` menjadi `.env` di root proyek:
   ```bash
   cp .env.example .env
   ```
   Buka file `.env` dan masukkan detail Google Sheets Anda:
   ```env
   GOOGLE_SPREADSHEET_ID="MASUKKAN_SPREADSHEET_ID_ANDA"
   GOOGLE_SHEET_WEBAPP_URL="URL_APPS_SCRIPT_WEB_APP"
   ```

5. **Jalankan server PHP lokal:**
   ```bash
   php -S localhost:8000
   ```
   Akses `http://localhost:8000` di browser Anda.

---

## ☁️ Deployment ke Vercel

Aplikasi ini sepenuhnya dioptimalkan untuk berjalan secara serverless di Vercel:

1. Pastikan Anda menginstal CLI Vercel atau menghubungkan GitHub Anda ke Vercel.
2. Tambahkan **Environment Variables** berikut di Vercel Dashboard proyek Anda (*Project Settings > Environment Variables*):
   
   | Variable Name | Keterangan | Contoh Nilai |
   | --- | --- | --- |
   | `GOOGLE_SPREADSHEET_ID` | ID Spreadsheet Google Anda | `16GNxkTeEOhY9YgJHhZROEHdx87RAaloVgy9vWdT-pnQ` |
   | `GOOGLE_SHEET_WEBAPP_URL` | URL Web App Google Apps Script | `https://script.google.com/macros/s/AKfy.../exec` |
   | `GOOGLE_SERVICE_ACCOUNT_JSON` | Konten teks lengkap file service account key JSON Anda | `{"type": "service_account", ...}` |

3. Vercel akan otomatis mendeteksi konfigurasi `vercel.json` dan mendeploy file API di bawah folder `api/`.

---

## 📊 Menghubungkan Google Sheets & Apps Script

Untuk memfungsikan integrasi Google Sheets secara penuh:

### 1. Berikan Izin Akses Google Sheets
Bagikan Google Spreadsheet Anda dengan akses **Editor** ke alamat email Service Account Anda:
```text
rekapit-backend@rekapit.iam.gserviceaccount.com
```

### 2. Deploy API Bridge (Google Apps Script)
1. Di Google Spreadsheet Anda, buka menu **Extensions** ➡️ **Apps Script**.
2. Hapus semua kode bawaan lalu salin seluruh isi file `database/google_apps_script.js`.
3. Klik **Save** ➡️ **Deploy** ➡️ **New deployment**.
4. Pilih tipe **Web app** dan atur:
   - **Execute as:** `Me` (Akun Google Anda)
   - **Who has access:** `Anyone` (Siapa saja)
5. Klik **Deploy**, setujui izin akses, dan salin **Web app URL** yang dihasilkan ke variabel `GOOGLE_SHEET_WEBAPP_URL` di Vercel/`.env`.

### 3. Migrasi Awal Data MySQL ke Google Sheets
Akses halaman migrasi melalui browser:
`http://[domain_anda]/migrate_mysql_to_sheets.php`
Klik tombol **Mulai Migrasi** untuk mengekspor data awal MySQL lama Anda ke Google Sheets.
