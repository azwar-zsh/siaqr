<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya dosen
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'dosen') {
    header('Location: login.php');
    exit;
}

$id_dosen = $_SESSION['id_user'] ?? 0;
$nama_dosen = $_SESSION['nama'] ?? 'Dosen';

// ============================================
// AMBIL PARAMETER FILTER
// ============================================
$filter_semester = $_GET['semester'] ?? '';
$filter_matkul = $_GET['matkul'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// ============================================
// BUILD QUERY DENGAN FILTER
// ============================================
$where_conditions = ["sa.id_dosen = ?"];
$params = [$id_dosen];
$types = "i";

if ($filter_matkul) {
    $where_conditions[] = "sa.id_matkul = ?";
    $params[] = (int)$filter_matkul;
    $types .= "i";
}

if ($filter_status) {
    $where_conditions[] = "sa.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_semester) {
    if (strpos($filter_semester, '2026/2027') !== false) {
        $where_conditions[] = "YEAR(sa.waktu_mulai) IN (2026, 2027)";
    } elseif (strpos($filter_semester, '2025/2026') !== false) {
        $where_conditions[] = "YEAR(sa.waktu_mulai) IN (2025, 2026)";
    }
}

if ($search) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(mk.nama_matkul LIKE '%$search_esc%' OR k.nama_kelas LIKE '%$search_esc%')";
}

$where_sql = implode(" AND ", $where_conditions);

// Query untuk mengambil SEMUA data (tanpa pagination)
$query = "SELECT 
    sa.id_sesi,
    sa.waktu_mulai,
    sa.waktu_selesai,
    sa.status,
    sa.pertemuan_ke,
    mk.nama_matkul,
    mk.kode_matkul,
    k.nama_kelas,
    d.nama as nama_dosen,
    (SELECT COUNT(*) FROM kehadiran WHERE id_sesi = sa.id_sesi) as jumlah_hadir,
    (SELECT COUNT(*) FROM mahasiswa m 
     JOIN kelas k2 ON m.program_studi = k2.program_studi 
     WHERE k2.id_kelas = sa.id_kelas) as total_mahasiswa
FROM sesi_absensi sa
JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
JOIN kelas k ON sa.id_kelas = k.id_kelas
JOIN dosen d ON sa.id_dosen = d.id_dosen
WHERE $where_sql
ORDER BY sa.waktu_mulai DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_sesi = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data_sesi[] = $row;
}

// ============================================
// GENERATE FILE EXCEL (CSV dengan format Excel)
// ============================================
$filename = "Laporan_Presensi_" . date('Y-m-d_His') . ".xls";

// Header untuk download file Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 agar karakter Indonesia terbaca di Excel
echo "\xEF\xBB\xBF";

// ============================================
// HEADER LAPORAN
// ============================================
echo "LAPORAN RIWAYAT PRESENSI DOSEN\n";
echo "Portal SIAQR - Sistem Absensi Berbasis QR Code\n";
echo "Dosen: " . $nama_dosen . "\n";
echo "Tanggal Ekspor: " . date('d F Y H:i') . "\n";

// Info filter
$filter_info = [];
if ($filter_semester) $filter_info[] = "Semester: $filter_semester";
if ($filter_matkul) {
    $q_mk = mysqli_query($conn, "SELECT nama_matkul FROM mata_kuliah WHERE id_matkul = " . (int)$filter_matkul);
    if ($q_mk && $row = mysqli_fetch_assoc($q_mk)) {
        $filter_info[] = "Mata Kuliah: " . $row['nama_matkul'];
    }
}
if ($filter_status) $filter_info[] = "Status: $filter_status";
if ($search) $filter_info[] = "Pencarian: $search";

if (!empty($filter_info)) {
    echo "Filter: " . implode(" | ", $filter_info) . "\n";
}

echo "\n"; // Baris kosong pemisah

// ============================================
// TABEL DATA
// ============================================
// Header kolom
echo "No\tTanggal\tJam\tMata Kuliah\tKode MK\tKelas\tPertemuan Ke\tStatus\tJumlah Hadir\tTotal Mahasiswa\tPersentase Kehadiran\n";

$no = 1;
foreach ($data_sesi as $sesi) {
    $tanggal = date('d-m-Y', strtotime($sesi['waktu_mulai']));
    $jam = date('H:i', strtotime($sesi['waktu_mulai'])) . ' - ' . date('H:i', strtotime($sesi['waktu_selesai']));
    $persentase = $sesi['total_mahasiswa'] > 0 
        ? round(($sesi['jumlah_hadir'] / $sesi['total_mahasiswa']) * 100, 1) 
        : 0;
    
    echo $no++ . "\t";
    echo $tanggal . "\t";
    echo $jam . "\t";
    echo $sesi['nama_matkul'] . "\t";
    echo $sesi['kode_matkul'] . "\t";
    echo $sesi['nama_kelas'] . "\t";
    echo $sesi['pertemuan_ke'] . "\t";
    echo $sesi['status'] . "\t";
    echo $sesi['jumlah_hadir'] . "\t";
    echo $sesi['total_mahasiswa'] . "\t";
    echo $persentase . "%\n";
}

// ============================================
// RINGKASAN
// ============================================
echo "\n";
echo "RINGKASAN\n";
echo "Total Sesi: " . count($data_sesi) . "\n";

$total_hadir_all = array_sum(array_column($data_sesi, 'jumlah_hadir'));
$total_mhs_all = array_sum(array_column($data_sesi, 'total_mahasiswa'));
$rata_rata = $total_mhs_all > 0 ? round(($total_hadir_all / $total_mhs_all) * 100, 1) : 0;

echo "Total Kehadiran: " . $total_hadir_all . " mahasiswa\n";
echo "Rata-rata Kehadiran: " . $rata_rata . "%\n";
echo "\n";
echo "--- Laporan ini digenerate otomatis oleh Portal SIAQR ---";

exit;
?>