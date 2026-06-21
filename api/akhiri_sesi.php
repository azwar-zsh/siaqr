<?php
session_start();
require_once '../connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_sesi = (int)($data['id_sesi'] ?? 0);
$id_dosen = $_SESSION['id_user'] ?? 0;

$query = "UPDATE sesi_absensi SET status = 'Selesai' 
          WHERE id_sesi = ? AND id_dosen = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $id_sesi, $id_dosen);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengakhiri sesi']);
}
?>