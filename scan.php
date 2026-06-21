<?php
require_once 'connection.php';

$token = $_GET['token'] ?? '';

// Validasi token
$query = "SELECT sa.*, mk.nama_matkul, k.nama_kelas 
          FROM sesi_absensi sa
          JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
          JOIN kelas k ON sa.id_kelas = k.id_kelas
          WHERE sa.qr_code_token = ? AND sa.status = 'Aktif' 
          AND NOW() BETWEEN sa.waktu_mulai AND sa.waktu_selesai";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$sesi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sesi) {
    die("<h1>QR Code tidak valid atau sudah kadaluarsa</h1>");
}

// Simpan kehadiran (asumsi mahasiswa sudah login)
session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['role'] === 'mahasiswa') {
    $id_mahasiswa = $_SESSION['id_user'];
    $ip_device = $_SERVER['REMOTE_ADDR'];
    
    // Cek apakah sudah absen
    $check = "SELECT id_kehadiran FROM kehadiran WHERE id_sesi = ? AND id_mahasiswa = ?";
    $stmt = mysqli_prepare($conn, $check);
    mysqli_stmt_bind_param($stmt, "ii", $sesi['id_sesi'], $id_mahasiswa);
    mysqli_stmt_execute($stmt);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) == 0) {
        // Insert kehadiran
        $insert = "INSERT INTO kehadiran (id_sesi, id_mahasiswa, ip_device, timestamp_hadir, keterangan) 
                   VALUES (?, ?, ?, NOW(), 'Hadir')";
        $stmt = mysqli_prepare($conn, $insert);
        mysqli_stmt_bind_param($stmt, "iis", $sesi['id_sesi'], $id_mahasiswa, $ip_device);
        mysqli_stmt_execute($stmt);
        
        echo "<h1>✅ Absensi Berhasil!</h1>";
        echo "<p>Mata Kuliah: " . htmlspecialchars($sesi['nama_matkul']) . "</p>";
        echo "<p>Kelas: " . htmlspecialchars($sesi['nama_kelas']) . "</p>";
        echo "<p>Pertemuan ke-" . $sesi['pertemuan_ke'] . "</p>";
        echo "<p>Waktu: " . date('H:i:s') . "</p>";
    } else {
        echo "<h1>⚠️ Anda sudah melakukan absensi untuk sesi ini</h1>";
    }
} else {
    echo "<h1>Silakan login terlebih dahulu</h1>";
    echo "<a href='login.php'>Login</a>";
}
?>