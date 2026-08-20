# Panduan Mengubah Rekap IT Menjadi Aplikasi Desktop (.EXE)

Dokumen ini menjelaskan cara menjalankan **Rekap IT** sebagai aplikasi desktop Windows mandiri serta cara mengonversinya menjadi file executable (`RekapIT.exe`).

---

## ⚡ Cara Menjalankan Aplikasi Desktop Sekarang (Tanpa Install)

1. Buka folder proyek **Rekap IT**.
2. Klik ganda (**double-click**) file **`RekapIT_Launcher.vbs`**.
3. Aplikasi **Rekap IT** akan langsung terbuka sebagai **aplikasi desktop Windows mandiri** (tanpa tab browser dan tanpa jendela hitam Command Prompt).

---

## 🛠️ Cara Mengubah Menjadi File `RekapIT.exe` Mandiri

Untuk menggabungkan script launcher di atas menjadi satu file **`RekapIT.exe`** resmi:

### Metode 1: Menggunakan "Bat To Exe Converter" (Gratis & Paling Cepat)
1. Unduh software gratis **Bat To Exe Converter** (atau versi portable-nya).
2. Buka software, pilih file **`RekapIT_Launcher.bat`** sebagai *Batch File*.
3. Pada opsi pengaturan (*Options*):
   - **Visibility**: Pilih `Invisible application` (agar tidak muncul jendela CMD hitam).
   - **Architecture**: Pilih `64 Bit`.
   - **Icon**: Pilih file ikon `.ico` yang Anda inginkan (misal logo Rekap IT).
4. Klik **Convert**.
5. File **`RekapIT.exe`** siap dipakai!

---

### Metode 2: Membuat File Instalasi (`RekapIT_Setup.exe`) dengan Inno Setup
1. Unduh **Inno Setup** (Gratis).
2. Buat script installer baru yang mengarahkan ke folder proyek Rekap IT.
3. Inno Setup akan menghasilkan file `RekapIT_Setup.exe` yang otomatis membuat shortcut di Desktop & Start Menu komputer pengguna.

---

## 📁 File Peluncur yang Disediakan:
- **`RekapIT_Launcher.bat`**: Script utama peluncur server PHP lokal & pemanggil Standalone App Window.
- **`RekapIT_Launcher.vbs`**: Peluncur senyap tanpa jendela console hitam.
