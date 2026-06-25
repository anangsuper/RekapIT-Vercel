-- Rekap IT - Full Database Schema
-- Updated: 18 Juni 2026

CREATE DATABASE IF NOT EXISTS rekap_it;
USE rekap_it;

-- 1. Cabang
CREATE TABLE IF NOT EXISTS cabang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_cabang VARCHAR(100) NOT NULL,
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Divisi
CREATE TABLE IF NOT EXISTS divisi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_divisi VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Kategori Aset
CREATE TABLE IF NOT EXISTS kategori_aset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Karyawan
CREATE TABLE IF NOT EXISTS karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_karyawan VARCHAR(100) NOT NULL,
    nip VARCHAR(50) UNIQUE,
    id_cabang INT,
    id_divisi INT,
    jabatan VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cabang) REFERENCES cabang(id) ON DELETE SET NULL,
    FOREIGN KEY (id_divisi) REFERENCES divisi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teknisi') DEFAULT 'teknisi',
    id_cabang INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cabang) REFERENCES cabang(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Assets
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_aset VARCHAR(50) NOT NULL UNIQUE,
    nama_aset VARCHAR(100) NOT NULL,
    serial_number VARCHAR(100),
    id_kategori INT,
    merk VARCHAR(50),
    model VARCHAR(100),
    id_cabang INT,
    id_divisi INT,
    id_karyawan INT,
    spesifikasi TEXT,
    kondisi ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') DEFAULT 'Baik',
    foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori_aset(id) ON DELETE SET NULL,
    FOREIGN KEY (id_cabang) REFERENCES cabang(id) ON DELETE SET NULL,
    FOREIGN KEY (id_divisi) REFERENCES divisi(id) ON DELETE SET NULL,
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Maintenance
CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    tanggal DATE NOT NULL,
    teknisi VARCHAR(100),
    temuan TEXT,
    tindakan TEXT,
    rekomendasi TEXT,
    status ENUM('Baik', 'Perlu Perbaikan', 'Rusak') DEFAULT 'Baik',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Repairs
CREATE TABLE IF NOT EXISTS repairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    keluhan TEXT NOT NULL,
    tindakan TEXT,
    biaya DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Proses', 'Selesai', 'Batal') DEFAULT 'Proses',
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Asset Mutations
CREATE TABLE IF NOT EXISTS asset_mutations (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Audits
CREATE TABLE IF NOT EXISTS audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    user_id INT NOT NULL,
    tanggal_audit DATE NOT NULL,
    kondisi_dilaporkan ENUM('Baik', 'Rusak Ringan', 'Rusak Berat'),
    kondisi_fisik ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') NOT NULL,
    lokasi_fisik VARCHAR(100),
    catatan TEXT,
    status_verifikasi ENUM('Sesuai', 'Tidak Sesuai') DEFAULT 'Sesuai',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Sparepart
CREATE TABLE IF NOT EXISTS sparepart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_sparepart VARCHAR(50) UNIQUE,
    nama_sparepart VARCHAR(100) NOT NULL,
    stok INT DEFAULT 0,
    satuan VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Data
INSERT IGNORE INTO users (nama, username, password, role)
VALUES ('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
