-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 29 Jun 2026 pada 09.40
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
(1, 'Yosia', '241011098', '$2y$10$q9H9OVUbJSp6CFOxGND9b.IG6Vqhoq1AttHdEGt12We96V150QhhS', 'admin baru', 'admin'),
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
(3, 'Hacker', '1435131551', 'Teknik Elektro', 'dosen1', '$2y$10$cdiqc2B6RCi3pL1NzmoIfOx4X.hdZsGP37NjTg8FYuGC77.hjL1Yq'),
(4, 'Azwar', '241011053', 'Matematika', 'dosen2', '$2y$10$dhOrRoM5E1ZDkQ0J0TxfmOf51CEQdwJK1ms5zkDmPLr065sD5alAG');

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
(3, 1, '192.168.1.1', '2024-05-22 08:30:00', 'Terlambat', NULL),
(4, 1, '10.37.30.181', '2026-06-29 13:33:08', 'Hadir', 28),
(5, 179, '::1', '2026-06-29 13:34:36', 'Hadir', 28),
(6, 179, '10.37.30.204', '2026-06-29 13:35:50', 'Hadir', 29),
(7, 1, '::1', '2026-06-29 13:55:29', 'Hadir', 32),
(8, 1, '::1', '2026-06-29 14:07:23', 'Hadir', 33);

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
(180, 'Elisa Steven Tandilo', '241011078', 2024, '241011078', '$2y$10$6wXTbodkOYU6YWeHg.Tghu2nsLmUUvd/6Y2Ra1u1p.e997heNfNWy', 'Ilmu Komputer'),
(181, 'Naufal', '241011128', 2024, '241011128', '$2y$10$UIf8ejc1sNyWoH1zEkf0BOGdPZbMRGWe2pmtizFlqATnz8.d05iXK', 'Informatika'),
(182, 'Akmal', '241011124', 2024, '241011124', '$2y$10$6sRZyXwk0i4IFFkgXZFP/.rNzGkxXO/unVcJZeZW1Mrqc4fOHAO1u', 'Sistem Informasi'),
(183, 'Rezky', '241011106', 2024, '241011106', '$2y$10$IeBOanK5lL3V4IvDocILSOBzd9RP2.OFPFBnRIv.yks36.gD0Dxom', 'Ilmu Komputer'),
(184, 'Habel', '241011110', 2024, '241011110', '$2y$10$kj804xXs4mi.virKLqDJOeEl0uqI9RvsByFuaycymySIrZxT/mC22', 'Sistem Informasi'),
(185, 'Yosia', '241011098', 2024, '241011098', '$2y$10$9duDMITsvbOMzN3dSh/zKOTelbYEjaI.MQ76dLbWYfgASPUnjwvKa', 'Sistem Informasi'),
(186, 'Steff', '241011001', 2024, '241011001', '$2y$10$abQYyk9yoWG.9RA.Jwc0rOJjKmq9ccJE176CvxRcx0jesp4nl0ROq', 'Sistem Informasi');

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
(28, '2026-06-29 13:32:57', '2026-06-29 13:47:57', 'Selesai', 'f25819647384f0fe33579a365e2f4e9f', 'S0FZ8E', 1, 1, 2, 1, 15),
(29, '2026-06-29 13:35:40', '2026-06-29 13:50:40', 'Selesai', 'bcfbabd7438e7b0373bb482526086562', 'JT293Y', 1, 1, 2, 1, 15),
(32, '2026-06-29 13:55:24', '2026-06-29 14:10:24', 'Selesai', '6fc0ce8a05d4e530a0d4d537c7520e04', 'F20GRC', 1, 1, 2, 1, 15),
(33, '2026-06-29 14:07:19', '2026-06-29 14:22:19', 'Selesai', '9e57ea206728ddd053c764e7d0ef8c12', 'IK0YWT', 1, 1, 2, 1, 15);

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
  MODIFY `id_dosen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `kehadiran`
--
ALTER TABLE `kehadiran`
  MODIFY `id_kehadiran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id_kelas` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id_mahasiswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  MODIFY `id_matkul` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  MODIFY `id_sesi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
