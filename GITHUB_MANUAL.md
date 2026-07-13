# Panduan Git & GitHub - Rekap IT

Dokumen ini adalah panduan lengkap cara mengelola source code proyek **Rekap IT** menggunakan sistem kontrol versi Git dan platform GitHub.

---

## 📌 Alur Integrasi Vercel & GitHub

Proyek ini telah dikonfigurasi untuk berjalan di **Vercel** serverless. Ketika Anda menggunakan GitHub:
1. Setiap push ke branch `main` akan secara otomatis memicu deploy ke production.
2. Setiap push ke branch fitur (selain `main`) atau saat pembuatan *Pull Request* (PR) akan memicu *Preview Deployment* untuk diuji coba sebelum digabungkan.

---

## 🛠️ 1. Setup Awal (First Time Setup)

Jika Anda baru saja mengambil repositori atau ingin menginisialisasi ulang:

### a. Konfigurasi Identitas Git Anda
```bash
git config --global user.name "Nama Anda"
git config --global user.email "emailanda@example.com"
```

### b. Menghubungkan Repositori Lokal ke GitHub
Jika repositori lokal belum terhubung ke remote repository (GitHub):
```bash
# Inisialisasi git jika belum
git init

# Tambahkan URL remote repository
git remote add origin https://github.com/anangsuper/RekapIT-Vercel.git

# Set nama branch utama ke main
git branch -M main
```

---

## 🔄 2. Alur Kerja Harian (Daily Workflow)

Ikuti alur kerja ini untuk menjaga kode Anda tetap ter-update dan aman dari konflik (*conflict*):

### Langkah 1: Update Repositori Lokal
Selalu tarik versi kode terbaru dari GitHub sebelum Anda mulai menulis kode baru:
```bash
git pull origin main
```

### Langkah 2: Lakukan Perubahan Kode
Edit, tambah, atau hapus kode Anda di lokal editor.

### Langkah 3: Periksa Status Modifikasi
Gunakan perintah ini untuk melihat berkas apa saja yang telah berubah:
```bash
git status
```

### Langkah 4: Registrasi Berkas ke Staging Area
Pilih berkas yang ingin disimpan. Jika ingin memasukkan semua berkas:
```bash
git add .
```
Atau jika hanya ingin memasukkan berkas spesifik:
```bash
git add views/cabang.php
```

### Langkah 5: Buat Commit
Rekam perubahan Anda dengan deskripsi yang jelas dan informatif:
```bash
git commit -m "feat: tambahkan total biaya perbaikan per cabang di menu Cabang"
```

### Langkah 6: Push Perubahan ke GitHub
Unggah commit Anda dari repositori lokal ke GitHub:
```bash
git push origin main
```

---

## 🌿 3. Manajemen Cabang (Branching)

Untuk menjaga kestabilan branch `main` (production), disarankan untuk mengerjakan fitur baru atau perbaikan bug di branch terpisah.

### a. Membuat Branch Baru & Berpindah ke Sana
```bash
# Membuat dan masuk ke branch baru bernama 'fitur-laporan-biaya'
git checkout -b fitur-laporan-biaya
```

### b. Melihat Semua Branch yang Ada
```bash
git branch -a
```

### c. Mengirim Branch Fitur ke GitHub
```bash
git push origin fitur-laporan-biaya
```

### d. Menggabungkan Branch Fitur ke `main` (Merging)
Setelah fitur selesai dibuat dan diuji coba:
```bash
# Pindah kembali ke branch main
git checkout main

# Tarik update terbaru
git pull origin main

# Gabungkan branch fitur
git merge fitur-laporan-biaya

# Push hasil penggabungan ke GitHub
git push origin main

# Hapus branch fitur lokal (opsional)
git branch -d fitur-laporan-biaya
```

---

## ⚠️ 4. Best Practices (Praktik Terbaik)

- **Keamanan Kredensial & Secrets**: Jangan pernah melakukan commit untuk berkas kredensial seperti `.env` atau `config/service-account.json` (kunci akun Google Service Account) ke GitHub publik. Pastikan berkas-berkas tersebut terdaftar di berkas `.gitignore`.
- **Menghindari Konflik Database Cache**: Berkas database SQLite cache lokal (`database/rekapit_cache.sqlite`) berisi data sementara hasil sync dari Google Sheets. Hindari membagikan berkas ini antar tim jika sering mengalami konflik sinkronisasi. Setiap developer sebaiknya menjalankan tombol **Sync** manual untuk membangun cache mereka sendiri di komputer lokal.
- **Commit Message yang Baik**: Gunakan awalan yang standar dalam membuat pesan commit agar riwayat proyek mudah dibaca:
  - `feat:` untuk penambahan fitur baru (misal: `feat: add cost per branch`)
  - `fix:` untuk perbaikan bug (misal: `fix: repair query syntax error`)
  - `docs:` untuk perubahan dokumentasi (misal: `docs: update git manual`)
  - `style:` untuk perubahan kosmetik/layout UI tanpa mengubah logic.
