# 🏷️ Panduan Lengkap Migrasi & Kustomisasi: Modul Cetak Kartu Inventaris (CR80) + Database Google Sheets Otomatis

Panduan ini disusun untuk memudahkan Anda memindahkan (**porting**) fitur **Cetak Kartu Inventaris & Manajemen Aset** dari aplikasi [Rekap IT (Cetak Kartu)](https://rekap-it-vercel-txjt.vercel.app/index.php?page=cetak_kartu) ke website lain, menyesuaikan desain fisik kartu maupun antarmuka website baru, serta menghubungkan database **Google Spreadsheet ("spearsheet")** agar bertambah dan tersinkronisasi secara otomatis tanpa perlu setup tabel manual.

---

## 📑 Daftar Isi
1. [Fitur Unggulan Modul](#1-fitur-unggulan-modul)
2. [Arsitektur & File yang Dibutuhkan untuk Dipindahkan](#2-arsitektur--file-yang-dibutuhkan-untuk-dipindahkan)
3. [Setup Database Google Spreadsheet Otomatis (Tanpa Setup Manual)](#3-setup-database-google-spreadsheet-otomatis)
4. [Helper PHP & Model Data untuk Website Baru](#4-helper-php--model-data-untuk-website-baru)
5. [Panduan Kustomisasi Desain Menyesuaikan Website Baru](#5-panduan-kustomisasi-desain-menyesuaikan-website-baru)
   - [A. Menyesuaikan Desain Kartu Fisik ATM (CR80)](#a-menyesuaikan-desain-kartu-fisik-atm-cr80)
   - [B. Menyesuaikan Tema Antarmuka Web (UI Website)](#b-menyesuaikan-tema-antarmuka-web-ui-website)
   - [C. Menyesuaikan Layout Cetak Kertas A4](#c-menyesuaikan-layout-cetak-kertas-a4)
6. [Panduan Integrasi ke Berbagai Framework](#6-panduan-integrasi-ke-berbagai-framework)
   - [Opsi 1: PHP Native / Website Sederhana](#opsi-1-php-native--website-sederhana)
   - [Opsi 2: Laravel (Blade & Controller)](#opsi-2-laravel-blade--controller)
   - [Opsi 3: CodeIgniter 3 / 4](#opsi-3-codeigniter-3--4)
7. [Tips Cetak Presisi & Troubleshooting](#7-tips-cetak-presisi--troubleshooting)

---

## 1. Fitur Unggulan Modul

Modul Cetak Kartu Inventaris ini memiliki kapabilitas kelas industri:
* **Ukuran Standar Kartu ATM (CR80):** Berdimensi presisi `85.6mm x 54.0mm` yang cocok dicetak pada kertas PVC kartu ATM, stiker chromo/vinyl, ataupun kertas HVS/karton A4.
* **Layout Cetak Massal A4 Fleksibel:**
  * **8 Kartu / Lembar:** Format 2 x 4 (Portrait) – ideal dengan jarak potong longgar.
  * **10 Kartu / Lembar:** Format 2 x 5 (Portrait) – standar hemat kertas kartu nama.
  * **12 Kartu / Lembar:** Format 3 x 4 (Landscape) – kapasitas maksimal per lembar A4.
* **Generator QR Code Otomatis:** Menggunakan library JavaScript client-side (`qrcodejs`), QR Code digenerate secara instan dan dapat diarahkan ke teks barcode maupun tautan URL verifikasi aset online.
* **Live Preview Interaktif:** Sebelum mencetak atau mendownload, pengguna dapat melihat pratinjau kartu 1-per-1 langsung di layar monitor dengan skala kartu nyata.
* **Input Lokasi Cepat:** Opsi input lokasi aset secara fleksibel—bisa diset massal ("Terapkan ke Semua") atau diketik manual per kartu saat sesi cetak.
* **Ekspor Multi-Format:**
  * Cetak langsung via browser dialog (print preview / Simpan sebagai PDF).
  * Ekspor ke dokumen **Microsoft Word (.doc)** dengan tabel kartu siap cetak offline.
  * Ekspor data ke **CSV / Excel**.
* **Database Google Sheets Otomatis:** Menambahkan data baru langsung membuat tab sheet `inventaris_kartu` beserta kolom header-nya di Google Spreadsheet Anda tanpa perlu buat manual.

---

## 2. Arsitektur & File yang Dibutuhkan untuk Dipindahkan

Untuk memindahkan fitur ini ke website lain, berikut adalah daftar komponen yang perlu disalin:

```text
📁 website-tujuan/
├── 📁 assets/
│   └── 🖼️ logo_kartu.png            <-- Logo instansi/perusahaan untuk kartu
├── 📁 config/
│   └── 📄 google_sheets.php         <-- Konfigurasi URL Google Apps Script
├── 📁 helpers/
│   └── 📄 GoogleSheetsBridge.php     <-- Helper komunikasi cURL ke Google Sheets
├── 📁 models/
│   └── 📄 InventarisKartu.php        <-- Model CRUD data kartu
├── 📁 views/
│   └── 📄 cetak_kartu.php           <-- File tampilan utama (UI, CSS, JS Cetak)
└── 📄 .env                          <-- Menyimpan URL Apps Script & kredensial
```

### Library Frontend yang Digunakan (via CDN, tidak perlu install manual):
1. **Bootstrap 5.3:** `<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">`
2. **Bootstrap Icons:** `<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">`
3. **QRCode.js:** `<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>`
4. **Google Fonts (Plus Jakarta Sans):** Digunakan agar tipografi kartu terlihat modern dan tajam.

---

## 3. Setup Database Google Spreadsheet Otomatis

Agar database Google Spreadsheet Anda **otomatis membuat sheet tab `inventaris_kartu`** dan **otomatis menambahkan baris baru** saat Anda menambah data dari website, ikuti langkah mudah berikut:

### Langkah 1: Buat Spreadsheet Baru
1. Buka [Google Sheets](https://sheets.new) di browser Anda.
2. Beri nama Spreadsheet, misalnya: `Database Inventaris & Kartu Aset`.
3. Anda **TIDAK PERLU** membuat kolom atau nama tab apa pun secara manual. Skrip di Langkah 2 akan membuatnya otomatis.

### Langkah 2: Pasang Google Apps Script (Bridge Otomatis)
1. Di Google Spreadsheet Anda, klik menu **Extensions (Ekstensi)** > **Apps Script**.
2. Hapus semua kode default yang ada di editor, lalu salin dan tempel kode berikut:

```javascript
/**
 * GOOGLE APPS SCRIPT - DATABASE OTOMATIS KARTU INVENTARIS & ASET
 * Mendukung auto-create tab sheet, auto-header kolom, insert, update, delete, dan read.
 */

// 1. Otorisasi Awal (Jalankan fungsi ini 1x pertama kali lalu klik Review Permissions)
function runAuthorization() {
  SpreadsheetApp.getActiveSpreadsheet();
  Logger.log("Otorisasi Google Sheets Berhasil!");
}

// 2. Fungsi Pembantu: Mengambil atau Otomatis Membuat Tab Sheet & Kolom Header
function getOrCreateSheet(sheetName, defaultHeaders) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(sheetName);
  
  if (!sheet) {
    sheet = ss.insertSheet(sheetName);
  }
  
  var dataRange = sheet.getDataRange();
  var values = dataRange.getValues();
  
  // Jika sheet baru atau baris pertama masih kosong, buat baris header otomatis
  if (values.length === 0 || values[0].length === 0 || (values.length === 1 && values[0][0] === "")) {
    sheet.clear();
    sheet.appendRow(defaultHeaders);
    // Format visual header (latar gelap, teks tebal putih)
    var headerRange = sheet.getRange(1, 1, 1, defaultHeaders.length);
    headerRange.setBackground("#003b73")
               .setFontColor("#ffffff")
               .setFontWeight("bold");
    sheet.setFrozenRows(1);
  }
  
  return sheet;
}

// 3. Menangani Permintaan HTTP GET (Membaca Data)
function doGet(e) {
  var action = e.parameter.action || 'readAll';
  var tableName = e.parameter.table || 'inventaris_kartu';
  
  if (action === 'readAll') {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(tableName);
    if (!sheet) {
      return ContentService.createTextOutput(JSON.stringify([]))
                           .setMimeType(ContentService.MimeType.JSON);
    }
    
    var data = sheet.getDataRange().getValues();
    if (data.length <= 1) {
      return ContentService.createTextOutput(JSON.stringify([]))
                           .setMimeType(ContentService.MimeType.JSON);
    }
    
    var headers = data[0];
    var rows = [];
    for (var i = 1; i < data.length; i++) {
      var row = {};
      var isEmpty = true;
      for (var j = 0; j < headers.length; j++) {
        var val = data[i][j];
        // Format tanggal jika objek Date
        if (val instanceof Date) {
          val = Utilities.formatDate(val, Session.getScriptTimeZone(), "yyyy-MM-dd");
        }
        row[headers[j]] = val;
        if (val !== "" && val !== null && val !== undefined) isEmpty = false;
      }
      if (!isEmpty) rows.push(row);
    }
    
    return ContentService.createTextOutput(JSON.stringify(rows))
                         .setMimeType(ContentService.MimeType.JSON);
  }
  
  return ContentService.createTextOutput(JSON.stringify({ error: "Invalid action" }))
                       .setMimeType(ContentService.MimeType.JSON);
}

// 4. Menangani Permintaan HTTP POST (Tambah, Edit, Hapus)
function doPost(e) {
  try {
    var postData = JSON.parse(e.postData.contents);
    var action = postData.action;
    var table = postData.table || 'inventaris_kartu';
    var data = postData.data || {};
    
    // Header default untuk tabel inventaris_kartu
    var defaultHeaders = ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'barcode_data', 'created_at'];
    var sheet = getOrCreateSheet(table, defaultHeaders);
    
    // === AKSI: INSERT (TAMBAH DATA BARU) ===
    if (action === 'insert') {
      var values = sheet.getDataRange().getValues();
      var headers = values[0];
      
      // Auto increment ID
      var idIndex = headers.indexOf('id');
      var maxId = 0;
      for (var i = 1; i < values.length; i++) {
        var curId = parseInt(values[i][idIndex]);
        if (!isNaN(curId) && curId > maxId) maxId = curId;
      }
      var newId = (data.id && parseInt(data.id) > 0) ? parseInt(data.id) : (maxId + 1);
      data['id'] = newId;
      if (!data['created_at']) {
        data['created_at'] = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy-MM-dd HH:mm:ss");
      }
      
      var newRow = [];
      headers.forEach(function(h) {
        newRow.push(data[h] !== undefined ? data[h] : "");
      });
      
      sheet.appendRow(newRow);
      return ContentService.createTextOutput(JSON.stringify({ success: true, id: newId }))
                           .setMimeType(ContentService.MimeType.JSON);
    }
    
    // === AKSI: UPDATE (PERBARUI DATA) ===
    else if (action === 'update') {
      var targetId = postData.id;
      var values = sheet.getDataRange().getValues();
      var headers = values[0];
      var idIndex = headers.indexOf('id');
      
      var rowIndex = -1;
      for (var i = 1; i < values.length; i++) {
        if (String(values[i][idIndex]) === String(targetId)) {
          rowIndex = i + 1; // 1-based index di Sheets
          break;
        }
      }
      
      if (rowIndex === -1) {
        return ContentService.createTextOutput(JSON.stringify({ error: "ID tidak ditemukan" }))
                             .setMimeType(ContentService.MimeType.JSON);
      }
      
      headers.forEach(function(h, colIdx) {
        if (h !== 'id' && data[h] !== undefined) {
          sheet.getRange(rowIndex, colIdx + 1).setValue(data[h]);
        }
      });
      
      return ContentService.createTextOutput(JSON.stringify({ success: true }))
                           .setMimeType(ContentService.MimeType.JSON);
    }
    
    // === AKSI: DELETE (HAPUS SATU ATAU BANYAK BARIS) ===
    else if (action === 'delete') {
      var idsToDelete = Array.isArray(postData.id) ? postData.id : [postData.id];
      var values = sheet.getDataRange().getValues();
      var headers = values[0];
      var idIndex = headers.indexOf('id');
      
      // Hapus dari indeks baris terbesar ke terkecil agar urutan baris tidak bergeser
      var deletedCount = 0;
      for (var i = values.length - 1; i >= 1; i--) {
        var rowId = String(values[i][idIndex]);
        if (idsToDelete.map(String).indexOf(rowId) !== -1) {
          sheet.deleteRow(i + 1);
          deletedCount++;
        }
      }
      
      return ContentService.createTextOutput(JSON.stringify({ success: true, deleted: deletedCount }))
                           .setMimeType(ContentService.MimeType.JSON);
    }
    
    return ContentService.createTextOutput(JSON.stringify({ error: "Aksi tidak dikenal" }))
                         .setMimeType(ContentService.MimeType.JSON);
                         
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ error: err.message }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
}
```

### Langkah 3: Deploy sebagai Web App
1. Di jendela Apps Script, klik tombol biru **Deploy** (di kanan atas) > pilih **New deployment**.
2. Klik ikon gerigi (Select type) di sebelah kiri > pilih **Web app**.
3. Isi kolom:
   * **Description:** `API Database Inventaris Kartu`
   * **Execute as:** `Me (email Anda)`
   * **Who has access:** `Anyone` *(Sangat penting: pilih "Anyone" agar server website Anda dapat mengirim & mengambil data tanpa hambatan)*
4. Klik **Deploy**.
5. Jika muncul permintaan izin, klik **Authorize access** > pilih akun Google Anda > klik **Advanced** > klik **Go to Untitled project (unsafe)** > klik **Allow**.
6. Salin **Web app URL** yang muncul (formatnya: `https://script.google.com/macros/s/AKfycb.../exec`). Simpan URL ini!

---

## 4. Helper PHP & Model Data untuk Website Baru

Buat file helper PHP di website baru Anda agar form tambah/edit/hapus kartu otomatis berkomunikasi dengan Google Sheets Web App.

### File: `helpers/GoogleSheetsBridge.php`
```php
<?php
class GoogleSheetsBridge {
    private static $webAppUrl = 'https://script.google.com/macros/s/AKfycbx48lncJM_fCh6jwGBClxL57Ee09_WSj7KBEoHfS3AoKM1F98NpT6HkjQGjdWZvwlY/exec'; // Ganti dengan URL hasil deploy Anda

    public static function setUrl($url) {
        self::$webAppUrl = $url;
    }

    public static function post($action, $data = [], $id = null, $table = 'inventaris_kartu') {
        $payload = json_encode([
            'action' => $action,
            'table'  => $table,
            'id'     => $id,
            'data'   => $data
        ]);

        $ch = curl_init(self::$webAppUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }

    public static function get($table = 'inventaris_kartu') {
        $url = self::$webAppUrl . '?action=readAll&table=' . urlencode($table);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
}
```

### File: `models/InventarisKartu.php`
Model ini bekerja dalam mode **Hybrid** (simpan ke database lokal MySQL/SQLite **DAN** otomatis sinkron ke Google Spreadsheet), atau bisa juga mode **Spreadsheet-Only**:

```php
<?php
require_once __DIR__ . '/../helpers/GoogleSheetsBridge.php';

class InventarisKartu {
    private $conn; // PDO Connection (Opsional, jika menggunakan MySQL/SQLite)

    public function __construct($db = null) {
        $this->conn = $db;
    }

    // 1. Ambil Semua Data Kartu
    public function getAll() {
        // Jika ada database lokal, prioritaskan lokal untuk performa super cepat
        if ($this->conn) {
            $stmt = $this->conn->query("SELECT * FROM inventaris_kartu ORDER BY id DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Jika tanpa DB lokal, langsung baca dari Google Sheets
        return GoogleSheetsBridge::get('inventaris_kartu');
    }

    // 2. Tambah Data Kartu (Otomatis Masuk ke Spreadsheet)
    public function create($data) {
        $insertedId = null;

        if ($this->conn) {
            $stmt = $this->conn->prepare("
                INSERT INTO inventaris_kartu (nomor_rekening, nama_barang, tanggal_perolehan, barcode_data)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['nomor_rekening'],
                $data['nama_barang'],
                $data['tanggal_perolehan'],
                $data['barcode_data']
            ]);
            $insertedId = $this->conn->lastInsertId();
            $data['id'] = $insertedId;
        }

        // Sinkronisasi otomatis ke Google Sheets
        GoogleSheetsBridge::post('insert', $data);
        return true;
    }

    // 3. Update Data Kartu (Otomatis Update di Spreadsheet)
    public function update($id, $data) {
        if ($this->conn) {
            $stmt = $this->conn->prepare("
                UPDATE inventaris_kartu 
                SET nomor_rekening = ?, nama_barang = ?, tanggal_perolehan = ?, barcode_data = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['nomor_rekening'],
                $data['nama_barang'],
                $data['tanggal_perolehan'],
                $data['barcode_data'],
                $id
            ]);
        }

        // Sinkronisasi otomatis ke Google Sheets
        GoogleSheetsBridge::post('update', $data, $id);
        return true;
    }

    // 4. Hapus Data Kartu (Otomatis Hapus di Spreadsheet)
    public function delete($id) {
        if ($this->conn) {
            $stmt = $this->conn->prepare("DELETE FROM inventaris_kartu WHERE id = ?");
            $stmt->execute([$id]);
        }

        // Sinkronisasi otomatis ke Google Sheets
        GoogleSheetsBridge::post('delete', [], $id);
        return true;
    }

    // 5. Hapus Massal Data Kartu Terpilih
    public function deleteMultiple($ids) {
        if (empty($ids)) return false;

        if ($this->conn) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->conn->prepare("DELETE FROM inventaris_kartu WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        }

        // Sinkronisasi otomatis ke Google Sheets (batch delete)
        GoogleSheetsBridge::post('delete', [], $ids);
        return true;
    }
}
```

---

## 5. Panduan Kustomisasi Desain Menyesuaikan Website Baru

### A. Menyesuaikan Desain Kartu Fisik ATM (CR80)
Desain fisik kartu dikendalikan oleh class CSS khusus di dalam file `views/cetak_kartu.php`. Anda dapat menyesuaikannya agar selaras dengan **Corporate Brand Identity** perusahaan atau instansi Anda:

#### 1. Mengganti Logo & Nama Instansi
Cari bagian Header Kartu di `views/cetak_kartu.php`:
```html
<!-- Ganti Path Logo Anda -->
<div class="header-logo-box">
    <img src="assets/logo_perusahaan_anda.png" alt="Logo">
</div>

<!-- Ganti Judul Utama & Sub-Title -->
<div class="header-title-box">
    <div class="header-main-title">NAMA INSTANSI / PT PERUSAHAAN ANDA</div>
    <div class="header-sub-sec">
        <div class="green-banner-wrapper">
            <div class="teal-stripe"></div>
            <div class="green-banner">KARTU INVENTARIS ASET</div>
        </div>
    </div>
</div>
```

#### 2. Mengubah Skema Warna Kartu
Untuk mengubah warna Navy, Hijau, dan Teal default ke warna brand Anda, sesuaikan kode CSS berikut di blok `<style>`:
```css
/* 1. Warna Header Utama & Icon Box (Default: Navy #003b73) */
.header-main-title, .field-icon-box {
    background-color: #1e3a8a !important; /* Ganti dengan kode Hex warna brand Anda */
}
.field-lbl {
    color: #1e3a8a !important;
}

/* 2. Warna Banner Diagonal & Garis Pemisah (Default: Hijau #7ac142) */
.green-banner {
    background: #10b981 !important; /* Contoh: Emerald Green */
}
.field-sep, .card-bottom-sec {
    border-color: #10b981 !important;
}

/* 3. Warna Garis Aksen Miring (Default: Teal #009ca6) */
.teal-stripe {
    background-color: #06b6d4 !important; /* Contoh: Cyan Accent */
}
```

#### 3. Mengatur Konten QR Code
Secara default, QR Code menghasilkan teks dari kolom `barcode_data`. Jika Anda ingin saat QR Code di-scan oleh kamera HP langsung membuka halaman info detail aset di website Anda, modifikasi fungsi generator di JavaScript:
```javascript
// Mengubah target QR Code menjadi Link Web Verifikasi
const verificationUrl = `https://website-anda.com/cek-aset.php?kode=${encodeURIComponent(item.barcode)}`;
const qrBase64 = await generateQrBase64(verificationUrl);
```

#### 4. Menyesuaikan Teks Peringatan (Disclaimer Footer)
Di bagian tab modal cetak, terdapat textarea untuk mengubah teks peringatan. Anda juga dapat mengubah nilai default-nya:
```html
<textarea id="printAttention" class="form-control" rows="2">
Perhatian:
Dilarang memindahkan barang inventaris ini tanpa seizin Departemen IT & Sarana Prasarana.
</textarea>
```

---

### B. Menyesuaikan Tema Antarmuka Web (UI Website)
Agar halaman tabel `cetak_kartu.php` menyatu dengan framework website tujuan:

* **Jika Website Menggunakan Bootstrap:**
  * Komponen seperti `.card`, `.btn-primary`, `.table-hover`, `.modal`, dan `.form-control` akan otomatis mengikuti tema Bootstrap yang terpasang pada template utama Anda.
* **Jika Website Menggunakan Tailwind CSS:**
  * Anda dapat memanggil Bootstrap secara terisolasi (scoped) atau cukup mengganti wrapper class:
    * `.card` -> `bg-white rounded-xl shadow-sm border border-slate-200`
    * `.btn-primary` -> `bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg`
    * `.table` -> `w-full text-left text-sm text-slate-600`

---

### C. Menyesuaikan Layout Cetak Kertas A4
Modul ini menyediakan pemilih layout kertas:
1. **8 Kartu / Lembar (2 x 4):** Ideal jika Anda ingin kartu dipotong dengan margin keliling yang longgar.
2. **10 Kartu / Lembar (2 x 5):** Tata letak kartu nama standar (paling efisien untuk kertas A4 Portrait).
3. **12 Kartu / Lembar (3 x 4 Landscape):** Tata letak orientasi mendatar untuk memaksimalkan jumlah kartu dalam 1 lembar A4.

CSS cetak presisi sudah tertanam dengan aturan:
```css
@media print {
    @page {
        size: A4 portrait; /* atau A4 landscape secara otomatis */
        margin: 0;         /* Menghilangkan margin browser bawaan */
    }
    body {
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
```

---

## 6. Panduan Integrasi ke Berbagai Framework

### Opsi 1: PHP Native / Website Sederhana
1. Buat folder `views/` dan letakkan file `cetak_kartu.php`.
2. Buat tabel database (jika menggunakan MySQL):
```sql
CREATE TABLE IF NOT EXISTS inventaris_kartu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_rekening VARCHAR(100) NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    tanggal_perolehan DATE NOT NULL,
    barcode_data TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
3. Panggil halaman menggunakan query parameter atau routing:
```php
// index.php
require_once 'config/database.php';
include 'views/cetak_kartu.php';
```

### Opsi 2: Laravel (Blade & Controller)
1. Salin `models/InventarisKartu.php` menjadi Eloquent Model `app/Models/InventarisKartu.php`.
2. Buat Controller:
```bash
php artisan make:controller KartuInventarisController
```
3. Di Controller, ambil data dan kirim ke view:
```php
public function index() {
    $items = InventarisKartu::orderBy('created_at', 'desc')->get();
    return view('kartu.index', compact('items'));
}
```
4. Ubah `cetak_kartu.php` menjadi `resources/views/kartu/index.blade.php`, lalu sesuaikan sintaks PHP native (`<?= $item['nama_barang'] ?>`) menjadi sintaks Blade (`{{ $item->nama_barang }}`).

### Opsi 3: CodeIgniter 3 / 4
1. Letakkan `InventarisKartu.php` di `app/Models/`.
2. Buat method di Controller:
```php
public function cetak_kartu() {
    $model = new \App\Models\InventarisKartu();
    $data['items'] = $model->getAll();
    return view('cetak_kartu', $data);
}
```

---

## 7. Tips Cetak Presisi & Troubleshooting

### Tips Saat Mencetak di Browser (Chrome / Edge):
1. **Centang Opsi "Background Graphics" (Grafik Latar Belakang):**
   * Saat jendela print browser terbuka, klik **More settings (Setelan lainnya)**.
   * Pastikan kotak **"Background graphics"** dicentang agar warna latar kartu, gradien, dan logo tercetak sempurna.
2. **Atur Margin ke "None" atau "Minimum":**
   * Pada pilihan **Margins**, pilih **None** agar tata letak grid A4 presisi sesuai koordinat milimeter (mm).
3. **Simpan sebagai PDF Berkualitas Tinggi:**
   * Pada pilihan **Destination (Tujuan)**, pilih **"Save as PDF" / "Simpan sebagai PDF"** untuk menghasilkan file dokumen PDF vektor siap kirim ke percetakan.

### Troubleshooting:
* **Masalah: QR Code tidak muncul.**
  * *Solusi:* Pastikan koneksi internet aktif untuk memuat library `qrcode.min.js` dari CDN, atau download file JS tersebut ke folder `assets/js/qrcode.min.js` lokal website Anda.
* **Masalah: Data baru tidak masuk ke Google Sheet.**
  * *Solusi:* Periksa kembali deployment Google Apps Script Anda. Pastikan opsi **Who has access** dipilih **"Anyone"**, dan jalankan fungsi `runAuthorization()` sekali untuk memberikan izin akses spreadsheet.
* **Masalah: Logo perusahaan pecah atau tidak muncul saat dicetak.**
  * *Solusi:* Gunakan format gambar PNG transparan beresolusi minimal 300 DPI dengan dimensi proporsional (misalnya 400x150 px).

---
*Dibuat untuk sistem manajemen aset modern. Silakan sesuaikan brand, warna, dan kolom sesuai kebutuhan instansi Anda.*
