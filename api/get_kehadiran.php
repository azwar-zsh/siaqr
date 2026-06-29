<?php
session_start();
require_once '../connection.php';

header('Content-Type: application/json');

$id_sesi = (int)($_GET['id_sesi'] ?? 0);

$query = "SELECT 
          (SELECT COUNT(*) FROM kehadiran WHERE id_sesi = ?) as jumlah_hadir,
          (SELECT COUNT(*) FROM sesi_absensi sa
           JOIN kelas k ON sa.id_kelas = k.id_kelas
           JOIN mahasiswa m ON m.program_studi = k.program_studi
           WHERE sa.id_sesi = ?) as total_mahasiswa";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $id_sesi, $id_sesi);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

echo json_encode($data);
?>