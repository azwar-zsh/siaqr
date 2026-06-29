<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya dosen yang boleh masuk
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'dosen') {
    header('Location: index.php');
    exit;
}

$nama = $_SESSION['nama'] ?? 'Dosen';
$id_dosen = $_SESSION['id_user'] ?? 0;

// ============================================
// LOGIKA AKSI (Hapus, Akhiri, Perpanjang, Unduh)
// ============================================
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id_sesi = (int)$_GET['id'];
    $aksi = $_GET['aksi'];

    if ($aksi === 'hapus') {
        // Hapus data kehadiran terkait dulu (jika ada)
        mysqli_query($conn, "DELETE FROM kehadiran WHERE id_sesi = $id_sesi");
        // Hapus sesi
        $q = "DELETE FROM sesi_absensi WHERE id_sesi = $id_sesi AND id_dosen = $id_dosen";
        mysqli_query($conn, $q);
    } 
    elseif ($aksi === 'akhiri') {
        $q = "UPDATE sesi_absensi SET status = 'Selesai' WHERE id_sesi = $id_sesi AND id_dosen = $id_dosen";
        mysqli_query($conn, $q);
    } 
    elseif ($aksi === 'perpanjang') {
        $q = "UPDATE sesi_absensi SET waktu_selesai = DATE_ADD(waktu_selesai, INTERVAL 10 MINUTE) WHERE id_sesi = $id_sesi AND id_dosen = $id_dosen";
        mysqli_query($conn, $q);
    }
    elseif ($aksi === 'unduh') {
        $q = "SELECT m.nama, m.nim, k.timestamp_hadir FROM kehadiran k 
              JOIN mahasiswa m ON k.id_mahasiswa = m.id_mahasiswa 
              WHERE k.id_sesi = $id_sesi";
        $res = mysqli_query($conn, $q);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="absensi_sesi_'.$id_sesi.'.csv"');
        $fp = fopen('php://output', 'w');
        fputcsv($fp, ['Nama', 'NIM', 'Waktu Hadir']);
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        exit;
    }

    header("Location: dashboard_dosen.php");
    exit;
}

// ============================================
// STATISTIK
// ============================================
$query_sesi_aktif = "SELECT COUNT(*) as total FROM sesi_absensi 
                     WHERE DATE(waktu_mulai) = CURDATE() AND status = 'Aktif' AND id_dosen = ?";
$stmt = mysqli_prepare($conn, $query_sesi_aktif);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$sesi_aktif = mysqli_fetch_assoc($result)['total'] ?? 0;

$query_total_mhs = "SELECT COUNT(DISTINCT m.id_mahasiswa) as total 
                    FROM mahasiswa m
                    JOIN kelas k ON m.program_studi = k.program_studi
                    WHERE k.id_dosen = ?";
$stmt = mysqli_prepare($conn, $query_total_mhs);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_mahasiswa = mysqli_fetch_assoc($result)['total'] ?? 0;

if ($total_mahasiswa == 0) {
    $query_all_mhs = "SELECT COUNT(*) as total FROM mahasiswa";
    $result = mysqli_query($conn, $query_all_mhs);
    $total_mahasiswa = mysqli_fetch_assoc($result)['total'] ?? 0;
}

$query_avg = "SELECT 
    COUNT(CASE WHEN k.keterangan = 'Hadir' THEN 1 END) * 100.0 / 
    NULLIF(COUNT(k.id_kehadiran), 0) as rata_rata
FROM kehadiran k
JOIN sesi_absensi sa ON k.id_sesi = sa.id_sesi
WHERE sa.id_dosen = ?";
$stmt = mysqli_prepare($conn, $query_avg);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rata_rata = round(mysqli_fetch_assoc($result)['rata_rata'] ?? 92.4, 1);

// ============================================
// FILTER & PAGINATION (seperti riwayat_presensi.php)
// ============================================
$filter_semester = $_GET['semester'] ?? '';
$filter_matkul = $_GET['matkul'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Ambil data mata kuliah untuk filter
$query_matkul_filter = "SELECT DISTINCT mk.id_matkul, mk.nama_matkul 
                        FROM mata_kuliah mk
                        JOIN sesi_absensi sa ON mk.id_matkul = sa.id_matkul
                        WHERE sa.id_dosen = ?
                        ORDER BY mk.nama_matkul";
$stmt = mysqli_prepare($conn, $query_matkul_filter);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result_matkul = mysqli_stmt_get_result($stmt);
$matkul_list = [];
while ($row = mysqli_fetch_assoc($result_matkul)) {
    $matkul_list[] = $row;
}

// Build WHERE clause
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

$where_sql = implode(" AND ", $where_conditions);

// Count total
$query_count = "SELECT COUNT(*) as total 
                FROM sesi_absensi sa
                WHERE $where_sql";
$stmt_count = mysqli_prepare($conn, $query_count);
mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];

// Pagination
$per_page = 5;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = ceil($total_records / $per_page);
$offset = ($current_page - 1) * $per_page;

// Query utama
$query = "SELECT 
    sa.id_sesi,
    sa.waktu_mulai,
    sa.waktu_selesai,
    sa.status,
    sa.pertemuan_ke,
    mk.nama_matkul,
    mk.kode_matkul,
    k.nama_kelas,
    (SELECT COUNT(*) FROM kehadiran WHERE id_sesi = sa.id_sesi) as jumlah_hadir,
    (SELECT COUNT(*) FROM mahasiswa m 
     JOIN kelas k2 ON m.program_studi = k2.program_studi 
     WHERE k2.id_kelas = sa.id_kelas) as total_mahasiswa
FROM sesi_absensi sa
JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
JOIN kelas k ON sa.id_kelas = k.id_kelas
WHERE $where_sql
ORDER BY sa.waktu_mulai DESC
LIMIT $per_page OFFSET $offset";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$daftar_sesi = [];
while ($row = mysqli_fetch_assoc($result)) {
    $daftar_sesi[] = $row;
}

// ============================================
// TANGGAL & SEMESTER
// ============================================
$hari_ini = date('l, d F Y');
$hari_indo = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
$bulan_indo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$tanggal_formatted = str_replace(array_keys($hari_indo), array_values($hari_indo), 
    str_replace(array_keys($bulan_indo), array_values($bulan_indo), $hari_ini));

$bulan_sekarang = (int)date('m');
$tahun_sekarang = date('Y');
if ($bulan_sekarang >= 1 && $bulan_sekarang <= 6) {
    $semester = "Semester Genap " . ($tahun_sekarang - 1) . "/" . $tahun_sekarang;
} else {
    $semester = "Semester Ganjil " . $tahun_sekarang . "/" . ($tahun_sekarang + 1);
}

$jam_sekarang = (int)date('H');
if ($jam_sekarang < 12) $greeting = "Selamat pagi";
elseif ($jam_sekarang < 15) $greeting = "Selamat siang";
elseif ($jam_sekarang < 18) $greeting = "Selamat sore";
else $greeting = "Selamat malam";

$active_page = 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen – Portal SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #fdfbf9;
            color: #1a1a1a;
            display: flex;
        }
        .admin-wrapper { display: flex; width: 100%; }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-icon { width: 44px; height: 44px; }
        .logo-text { display: flex; flex-direction: column; }
        .logo-title { font-size: 1.125rem; font-weight: 700; color: #1a1a1a; }
        .logo-subtitle { font-size: 0.75rem; color: #6b7280; }
        .sidebar-nav { padding: 1.5rem 1rem; flex: 1; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-item svg { width: 20px; height: 20px; }
        .nav-item:hover { background: #f9fafb; color: #1a1a1a; }
        .nav-item.active { background: #fff0e4; color: #e8670a; }
        .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; }
        .logout-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .logout-link:hover { background: #fef2f2; }
        .logout-link svg { width: 20px; height: 20px; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Header */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }
        .header-left h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }
        .header-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .btn-create-session {
            background: #c45a0a;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-create-session:hover { background: #a04a08; }
        .btn-create-session .plus-icon {
            font-size: 1.25rem;
            font-weight: bold;
            line-height: 1;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
        }
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon.live { background: #fef3c7; color: #d97706; }
        .stat-icon.users { background: #dbeafe; color: #2563eb; }
        .stat-icon.percent { background: #dcfce7; color: #16a34a; }
        .stat-icon svg { width: 20px; height: 20px; }
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #16a34a;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #16a34a;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .stat-change {
            font-size: 0.75rem;
            font-weight: 500;
            color: #16a34a;
            margin-top: 0.5rem;
        }

        /* Filter Bar (seperti riwayat_presensi) */
        .filter-bar {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: flex-end;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
        }
        .filter-select {
            padding: 0.625rem 2rem 0.625rem 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-family: inherit;
            background: white;
            min-width: 160px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            cursor: pointer;
        }
        .filter-select:focus {
            outline: none;
            border-color: #e8670a;
            box-shadow: 0 0 0 3px rgba(232, 103, 10, 0.1);
        }
        .btn-filter {
            padding: 0.625rem 1rem;
            background: #e8670a;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            height: 38px;
        }
        .btn-filter:hover { background: #c45a0a; }
        .btn-reset {
            padding: 0.625rem 1rem;
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            height: 38px;
            text-decoration: none;
        }
        .btn-reset:hover { background: #f9fafb; }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-title svg {
            width: 20px;
            height: 20px;
            color: #e8670a;
        }

        /* Table Card */
        .table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            overflow: hidden;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 1rem 1.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            background: #fafafa;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table td {
            padding: 1rem 1.25rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: #fafafa; }

        .tanggal-cell {
            font-weight: 600;
            color: #1a1a1a;
        }
        .tanggal-sub {
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 400;
        }
        .matkul-cell {
            font-weight: 600;
            color: #1a1a1a;
        }
        .matkul-code {
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 400;
        }
        .kelas-cell {
            font-weight: 600;
            color: #1a1a1a;
        }

        /* Progress Bar Kehadiran */
        .kehadiran-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 160px;
        }
        .progress-track {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.3s;
        }
        .progress-fill.high { background: #16a34a; }
        .progress-fill.medium { background: #e8670a; }
        .progress-fill.low { background: #ef4444; }
        .kehadiran-count {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a1a1a;
            white-space: nowrap;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-badge.Selesai { background: #dcfce7; color: #16a34a; }
        .status-badge.Aktif { background: #fef3c7; color: #d97706; }
        .status-badge.Belum-Mulai { background: #f3f4f6; color: #6b7280; }

        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 0.375rem 0.625rem;
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            display: inline-block;
            white-space: nowrap;
        }
        .btn-action.extend {
            background: #dbeafe;
            color: #2563eb;
        }
        .btn-action.extend:hover { background: #bfdbfe; }
        .btn-action.end {
            background: #fef3c7;
            color: #d97706;
        }
        .btn-action.end:hover { background: #fde68a; }
        .btn-action.download {
            background: #dcfce7;
            color: #16a34a;
        }
        .btn-action.download:hover { background: #bbf7d0; }
        .btn-action.delete {
            background: #fee2e2;
            color: #ef4444;
        }
        .btn-action.delete:hover { background: #fecaca; }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-top: 1px solid #e5e7eb;
            background: #fafafa;
        }
        .pagination-info {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .page-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .page-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .page-btn.active {
            background: #e8670a;
            color: white;
            border-color: #e8670a;
        }
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-dots {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 0.875rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-select { min-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="4" width="14" height="14" rx="2" fill="white"/>
                        <rect x="26" y="4" width="14" height="14" rx="2" fill="white"/>
                        <rect x="4" y="26" width="14" height="14" rx="2" fill="white"/>
                        <rect x="26" y="26" width="6" height="6" rx="1" fill="white"/>
                        <rect x="34" y="26" width="6" height="6" rx="1" fill="white"/>
                        <rect x="26" y="34" width="6" height="6" rx="1" fill="white"/>
                        <rect x="7" y="7" width="8" height="8" rx="1" fill="#E8670A"/>
                        <rect x="29" y="7" width="8" height="8" rx="1" fill="#E8670A"/>
                        <rect x="7" y="29" width="8" height="8" rx="1" fill="#E8670A"/>
                    </svg>
                </div>
                <div class="logo-text">
                    <span class="logo-title">Portal SIAQR</span>
                    <span class="logo-subtitle">Manajemen Presensi</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard_dosen.php" class="nav-item <?= $active_page === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Dashboard
                </a>
                <a href="buat_sesi.php" class="nav-item <?= $active_page === 'buat_sesi' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Buat Sesi Absensi
                </a>
                <a href="riwayat_presensi.php" class="nav-item <?= $active_page === 'riwayat' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Riwayat Presensi
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div class="header-left">
                    <h1><?= $greeting ?>, Pak <?= htmlspecialchars($nama) ?></h1>
                    <p class="header-subtitle"><?= $tanggal_formatted ?> • <?= $semester ?></p>
                </div>
                <a href="buat_sesi.php" class="btn-create-session">
                    <span class="plus-icon">+</span>
                    Buat Sesi Baru
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon live">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
                                <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
                                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                                <line x1="12" y1="20" x2="12.01" y2="20"/>
                            </svg>
                        </div>
                        <?php if ($sesi_aktif > 0): ?>
                        <span class="live-badge"><span class="live-dot"></span> LIVE</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-value"><?= str_pad($sesi_aktif, 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="stat-label">Sesi Aktif Hari Ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $total_mahasiswa ?></div>
                    <div class="stat-label">Total Mahasiswa Terdaftar</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon percent">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= $rata_rata ?>%</div>
                    <div class="stat-label">Rata-rata Kehadiran</div>
                    <div class="stat-change">↑2.1% dari bulan lalu</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="dashboard_dosen.php" class="filter-bar">
                <div class="filter-group">
                    <label class="filter-label">Semester</label>
                    <select name="semester" class="filter-select">
                        <option value="">Semua Semester</option>
                        <option value="Genap 2026/2027" <?= $filter_semester === 'Genap 2026/2027' ? 'selected' : '' ?>>Genap 2026/2027</option>
                        <option value="Ganjil 2025/2026" <?= $filter_semester === 'Ganjil 2025/2026' ? 'selected' : '' ?>>Ganjil 2025/2026</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Mata Kuliah</label>
                    <select name="matkul" class="filter-select">
                        <option value="">Semua Mata Kuliah</option>
                        <?php foreach ($matkul_list as $mk): ?>
                        <option value="<?= $mk['id_matkul'] ?>" <?= $filter_matkul == $mk['id_matkul'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mk['nama_matkul']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?= $filter_status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Selesai" <?= $filter_status === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Filter
                </button>
                <a href="dashboard_dosen.php" class="btn-reset">Reset</a>
            </form>

            <!-- Section Header -->
            <div class="section-header">
                <div class="section-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Daftar Sesi Perkuliahan
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Mata Kuliah</th>
                                <th>Kelas</th>
                                <th>Kehadiran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_sesi)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <p>Belum ada sesi perkuliahan</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($daftar_sesi as $sesi): 
                                $persentase = $sesi['total_mahasiswa'] > 0 
                                    ? round(($sesi['jumlah_hadir'] / $sesi['total_mahasiswa']) * 100) 
                                    : 0;
                                
                                if ($persentase >= 75) $progress_class = 'high';
                                elseif ($persentase >= 50) $progress_class = 'medium';
                                else $progress_class = 'low';
                                
                                $tanggal = date('d M Y', strtotime($sesi['waktu_mulai']));
                                $jam = date('H:i', strtotime($sesi['waktu_mulai'])) . ' - ' . date('H:i', strtotime($sesi['waktu_selesai']));
                                $status_class = str_replace(' ', '-', $sesi['status']);
                            ?>
                            <tr>
                                <td>
                                    <div class="tanggal-cell"><?= $tanggal ?></div>
                                    <div class="tanggal-sub"><?= $jam ?></div>
                                </td>
                                <td>
                                    <div class="matkul-cell"><?= htmlspecialchars($sesi['nama_matkul']) ?></div>
                                    <div class="matkul-code"><?= htmlspecialchars($sesi['kode_matkul']) ?></div>
                                </td>
                                <td>
                                    <span class="kelas-cell"><?= htmlspecialchars($sesi['nama_kelas']) ?></span>
                                </td>
                                <td>
                                    <div class="kehadiran-cell">
                                        <div class="progress-track">
                                            <div class="progress-fill <?= $progress_class ?>" style="width: <?= $persentase ?>%"></div>
                                        </div>
                                        <span class="kehadiran-count"><?= $sesi['jumlah_hadir'] ?>/<?= $sesi['total_mahasiswa'] ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>"><?= strtoupper($sesi['status']) ?></span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <?php if ($sesi['status'] === 'Aktif'): ?>
                                            <a href="?aksi=perpanjang&id=<?= $sesi['id_sesi'] ?>" class="btn-action extend" title="Tambah 10 menit">+10 Mnt</a>
                                            <a href="?aksi=akhiri&id=<?= $sesi['id_sesi'] ?>" class="btn-action end" onclick="return confirm('Yakin ingin mengakhiri sesi ini sekarang?')">Akhiri</a>
                                        <?php endif; ?>
                                        <a href="?aksi=unduh&id=<?= $sesi['id_sesi'] ?>" class="btn-action download">Unduh</a>
                                        <a href="?aksi=hapus&id=<?= $sesi['id_sesi'] ?>" class="btn-action delete" onclick="return confirm('⚠️ PERINGATAN!\n\nAnda akan menghapus sesi ini beserta semua data kehadiran mahasiswa.\n\nTindakan ini TIDAK DAPAT dibatalkan.\n\nYakin ingin menghapus?')">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_records > 0): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_records) ?> dari <?= $total_records ?> sesi
                    </div>
                    <div class="pagination-controls">
                        <?php if ($current_page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" class="page-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="page-btn disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                        </span>
                        <?php endif; ?>

                        <?php
                        $pages_to_show = [];
                        if ($total_pages <= 7) {
                            $pages_to_show = range(1, $total_pages);
                        } else {
                            $pages_to_show = [1];
                            if ($current_page > 3) $pages_to_show[] = '...';
                            for ($i = max(2, $current_page - 1); $i <= min($total_pages - 1, $current_page + 1); $i++) {
                                $pages_to_show[] = $i;
                            }
                            if ($current_page < $total_pages - 2) $pages_to_show[] = '...';
                            $pages_to_show[] = $total_pages;
                        }

                        foreach ($pages_to_show as $p):
                            if ($p === '...'):
                        ?>
                        <span class="page-dots">...</span>
                        <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" 
                           class="page-btn <?= $p == $current_page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                        <?php endif; endforeach; ?>

                        <?php if ($current_page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" class="page-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                        <?php else: ?>
                        <span class="page-btn disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>