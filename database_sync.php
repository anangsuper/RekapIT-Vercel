<?php
require_once 'config/database.php';

/**
 * Script Sinkronisasi Database Rekap IT
 * Memastikan semua tabel dan kolom yang dibutuhkan tersedia.
 */

echo "<h2>🔄 Sinkronisasi Database Rekap IT</h2>";

// 1. Daftar Tabel yang harus ada
$tables = [
    "cabang" => "CREATE TABLE IF NOT EXISTS cabang (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_cabang VARCHAR(100) NOT NULL,
        alamat TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "divisi" => "CREATE TABLE IF NOT EXISTS divisi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_divisi VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "kategori_aset" => "CREATE TABLE IF NOT EXISTS kategori_aset (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_kategori VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "karyawan" => "CREATE TABLE IF NOT EXISTS karyawan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_karyawan VARCHAR(100) NOT NULL,
        nip VARCHAR(50) UNIQUE,
        id_cabang INT,
        id_divisi INT,
        jabatan VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "asset_mutations" => "CREATE TABLE IF NOT EXISTS asset_mutations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        user_id INT NOT NULL,
        id_cabang_lama INT,
        id_divisi_lama INT,
        id_karyawan_lama INT,
        id_cabang_baru INT,
        id_divisi_baru INT,
        id_karyawan_baru INT,
        tanggal_mutasi DATE NOT NULL,
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "sparepart" => "CREATE TABLE IF NOT EXISTS sparepart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_sparepart VARCHAR(50) UNIQUE,
        nama_sparepart VARCHAR(100) NOT NULL,
        stok INT DEFAULT 0,
        satuan VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($tables as $name => $sql) {
    try {
        $conn->exec($sql);
        echo "<div style='color: green;'>[OK] Tabel '$name' siap.</div>";
    } catch (PDOException $e) {
        echo "<div style='color: red;'>[FAIL] Tabel '$name': " . $e->getMessage() . "</div>";
    }
}

// 2. Sinkronisasi Kolom tabel 'assets'
echo "<h3>🛠 Memeriksa Kolom Tabel 'assets'...</h3>";
$needed_columns = [
    'serial_number' => "ALTER TABLE assets ADD COLUMN serial_number VARCHAR(100) AFTER nama_aset",
    'id_kategori' => "ALTER TABLE assets ADD COLUMN id_kategori INT AFTER serial_number",
    'merk' => "ALTER TABLE assets ADD COLUMN merk VARCHAR(50) AFTER id_kategori",
    'model' => "ALTER TABLE assets ADD COLUMN model VARCHAR(100) AFTER merk",
    'id_cabang' => "ALTER TABLE assets ADD COLUMN id_cabang INT AFTER model",
    'id_divisi' => "ALTER TABLE assets ADD COLUMN id_divisi INT AFTER id_cabang",
    'id_karyawan' => "ALTER TABLE assets ADD COLUMN id_karyawan INT AFTER id_divisi"
];

foreach ($needed_columns as $col => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM assets LIKE '$col'")->fetch();
    if (!$check) {
        try {
            $conn->exec($sql);
            echo "<div style='color: blue;'>[ADDED] Kolom '$col' berhasil ditambahkan ke tabel 'assets'.</div>";
        } catch (PDOException $e) {
            echo "<div style='color: red;'>[ERROR] Gagal menambah kolom '$col': " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div style='color: gray;'>[SKIP] Kolom '$col' sudah ada.</div>";
    }
}

echo "<h3>✅ Sinkronisasi Selesai.</h3>";
echo "<p><a href='index.php'>Kembali ke Dashboard</a></p>";
?>
