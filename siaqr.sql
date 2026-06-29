-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 22 Jun 2026 pada 06.00
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `siaqr`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `role` enum('admin','super_admin') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `nama`, `username`, `password`, `jabatan`, `role`) VALUES
(1, 'Yosia', '241011098', '241011098', 'admin baru', 'admin'),
(2, 'Super Admin', 'admin123', 'admin123', 'System Administrator', 'super_admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `id_dosen` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `program_studi` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `dosen`
--

INSERT INTO `dosen` (`id_dosen`, `nama`, `nip`, `program_studi`, `username`, `password`) VALUES
(1, 'Azwar', '241011053', 'Ilmu Komputer', '241011053', '241011053'),
(2, 'anonim', '241011002', 'Ilmu Komputer', '241011001', '241011001');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kehadiran`
--

CREATE TABLE `kehadiran` (
  `id_kehadiran` int(11) NOT NULL,
  `id_mahasiswa` int(11) DEFAULT NULL,
  `ip_device` varchar(50) DEFAULT NULL,
  `timestamp_hadir` datetime DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `id_sesi` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kehadiran`
--

INSERT INTO `kehadiran` (`id_kehadiran`, `id_mahasiswa`, `ip_device`, `timestamp_hadir`, `keterangan`, `id_sesi`) VALUES
(1, 1, '192.168.1.1', '2024-05-20 08:00:00', 'Hadir', NULL),
(2, 1, '192.168.1.1', '2024-05-21 08:05:00', 'Hadir', NULL),
(3, 1, '192.168.1.1', '2024-05-22 08:30:00', 'Terlambat', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id_kelas` int(11) NOT NULL,
  `nama_kelas` varchar(100) DEFAULT NULL,
  `tahun_akademik` varchar(20) DEFAULT NULL,
  `program_studi` varchar(100) DEFAULT NULL,
  `ruangan` varchar(100) DEFAULT NULL,
  `id_dosen` int(11) DEFAULT NULL,
  `id_matkul` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id_kelas`, `nama_kelas`, `tahun_akademik`, `program_studi`, `ruangan`, `id_dosen`, `id_matkul`) VALUES
(1, 'IK24-A', '2026/2027', '', 'LT-203', 2, 1),
(2, 'IK24-B', '2026/2027', 'Ilmu Komputer', NULL, NULL, NULL),
(3, 'IF-44-01', '2026/2027', '', 'LT-203', 1, 1),
(4, 'IF-44-05', '2026/2027', '', 'GP-104', 1, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id_mahasiswa` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `nim` varchar(50) DEFAULT NULL,
  `angkatan` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `program_studi` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id_mahasiswa`, `nama`, `nim`, `angkatan`, `username`, `password`, `program_studi`) VALUES
(1, 'steven', '241011078', 2024, '241011078', '241011078', 'ilmu komputer'),
(174, 'Budi Santoso', '241011001', 2024, '241011001', '241011001', 'ilmu komputer'),
(176, 'Citra Lestari', '241011003', 2024, '241011003', '241011003', 'ilmu komputer'),
(177, 'Dedi Prasetyo', '241011004', 2024, '241011004', '241011004', 'ilmu komputer'),
(178, 'Elisa Fitri', '241011005', 2024, '241011005', '241011005', 'ilmu komputer'),
(179, 'Geri Setiadi', '241011006', 2024, '241011006', '241011006', 'ilmu komputer');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `id_matkul` int(11) NOT NULL,
  `kode_matkul` varchar(50) DEFAULT NULL,
  `nama_matkul` varchar(100) DEFAULT NULL,
  `sks` int(11) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`id_matkul`, `kode_matkul`, `nama_matkul`, `sks`, `semester`) VALUES
(1, 'MK401', 'Pemrograman Web', 3, 4),
(2, 'MK405', 'Struktur Data', 3, 2),
(3, 'MK403', 'Basis Data Lanjut', 3, 4),
(4, 'MK404', 'Sistem Operasi', 3, 4);

-- --------------------------------------------------------

--
-- Struktur dari tabel `sesi_absensi`
--

CREATE TABLE `sesi_absensi` (
  `id_sesi` int(11) NOT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `qr_code_token` text DEFAULT NULL,
  `kode_manual` varchar(10) DEFAULT NULL,
  `pertemuan_ke` int(11) DEFAULT NULL,
  `id_matkul` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `id_dosen` int(11) DEFAULT NULL,
  `durasi` int(11) DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sesi_absensi`
--

INSERT INTO `sesi_absensi` (`id_sesi`, `waktu_mulai`, `waktu_selesai`, `status`, `qr_code_token`, `kode_manual`, `pertemuan_ke`, `id_matkul`, `id_kelas`, `id_dosen`, `durasi`) VALUES
(1, '2026-06-10 05:55:05', '2026-06-10 06:10:05', 'Selesai', '23277c530753680536a155998abe0af5', NULL, 4, 3, 3, 1, 15),
(2, '2026-06-10 05:55:06', '2026-06-10 06:10:06', 'Selesai', '0baeefad4f374a07eda860822bb98774', NULL, 4, 3, 3, 1, 15),
(3, '2026-06-10 05:55:06', '2026-06-10 06:10:06', 'Selesai', 'f472cd81258661a98e0bc00f3133bda5', NULL, 4, 3, 3, 1, 15),
(4, '2026-06-10 05:55:07', '2026-06-10 06:10:07', 'Selesai', '448f6d3e5f2f735b8e02e584da06b978', NULL, 4, 3, 3, 1, 15),
(9, '2026-06-22 04:46:47', '2026-06-22 05:01:47', 'Selesai', '839cfa7f22026944b8759edbec64feb0', 'XP2VUE', 1, 1, 3, 1, 15),
(10, '2026-06-22 04:47:39', '2026-06-22 05:12:39', 'Selesai', 'b7c62980dac93bff5eb5be617c8d8074', 'T5LX3S', 1, 1, 3, 1, 15),
(13, '2026-06-22 04:50:43', '2026-06-22 05:05:43', 'Selesai', 'e817cd9646902f479d050c22e41601f3', 'VUH695', 1, 1, 3, 1, 15),
(14, '2026-06-22 04:51:17', '2026-06-22 05:06:17', 'Selesai', '148f89b417f156f9a038cf887b269c3e', '8CJ0BI', 1, 1, 2, 1, 15),
(15, '2026-06-22 04:58:39', '2026-06-22 05:13:39', 'Selesai', '9faecf0108c6a661f3992ef3e1313499', 'O92K6Y', 1, 1, 2, 1, 15),
(16, '2026-06-22 05:18:52', '2026-06-22 05:33:52', 'Selesai', 'fa01865e4249cbc8f8f8116aa0bd0316', '8A7E4I', 1, 1, 3, 1, 15),
(18, '2026-06-22 05:19:40', '2026-06-22 05:34:40', 'Selesai', '4f6f485434dd5c5da7f98582346a5da1', 'Z7O1GA', 1, 1, 2, 1, 15),
(19, '2026-06-22 05:25:58', '2026-06-22 05:40:58', 'Selesai', '3fae0302fa0d70f13d230af8dfacf55f', 'OFVYDL', 1, 1, 2, 1, 15),
(20, '2026-06-22 05:26:14', '2026-06-22 05:41:14', 'Selesai', '891a85c27f56c9d96f49297f720f211b', 'LN95JO', 1, 1, 4, 1, 15);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id_dosen`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `kehadiran`
--
ALTER TABLE `kehadiran`
  ADD PRIMARY KEY (`id_kehadiran`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id_kelas`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id_mahasiswa`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`id_matkul`);

--
-- Indeks untuk tabel `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  ADD PRIMARY KEY (`id_sesi`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id_dosen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `kehadiran`
--
ALTER TABLE `kehadiran`
  MODIFY `id_kehadiran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id_kelas` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id_mahasiswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

--
-- AUTO_INCREMENT untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  MODIFY `id_matkul` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  MODIFY `id_sesi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
