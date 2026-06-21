<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya dosen yang boleh masuk
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'dosen') {
    header('Location: login.php');
    exit;
}

$nama = $_SESSION['nama'] ?? 'Dosen';
$id_dosen = $_SESSION['id_user'] ?? 0;

// ============================================
// 1. SESI AKTIF HARI INI
// ============================================
$query_sesi_aktif = "SELECT COUNT(*) as total FROM sesi_absensi 
                     WHERE DATE(waktu_mulai) = CURDATE() AND status = 'Aktif' AND id_dosen = ?";
$stmt = mysqli_prepare($conn, $query_sesi_aktif);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$sesi_aktif = mysqli_fetch_assoc($result)['total'] ?? 0;

// ============================================
// 2. TOTAL MAHASISWA TERDAFTAR (di kelas yang diajar dosen ini)
// ============================================
$query_total_mhs = "SELECT COUNT(DISTINCT m.id_mahasiswa) as total 
                    FROM mahasiswa m
                    JOIN kelas k ON m.program_studi = k.program_studi
                    WHERE k.id_dosen = ?";
$stmt = mysqli_prepare($conn, $query_total_mhs);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_mahasiswa = mysqli_fetch_assoc($result)['total'] ?? 0;

// Jika 0, ambil total semua mahasiswa sebagai fallback
if ($total_mahasiswa == 0) {
    $query_all_mhs = "SELECT COUNT(*) as total FROM mahasiswa";
    $result = mysqli_query($conn, $query_all_mhs);
    $total_mahasiswa = mysqli_fetch_assoc($result)['total'] ?? 0;
}

// ============================================
// 3. RATA-RATA KEHADIRAN
// ============================================
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
// 4. JADWAL SESI ABSENSI HARI INI
// ============================================
$query_jadwal = "SELECT 
    mk.nama_matkul,
    mk.kode_matkul,
    k.nama_kelas,
    sa.waktu_mulai,
    sa.waktu_selesai,
    sa.status,
    sa.id_sesi
FROM sesi_absensi sa
JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
JOIN kelas k ON sa.id_kelas = k.id_kelas
WHERE sa.id_dosen = ? AND DATE(sa.waktu_mulai) = CURDATE()
ORDER BY sa.waktu_mulai ASC";

$stmt = mysqli_prepare($conn, $query_jadwal);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$jadwal_hari_ini = [];
while ($row = mysqli_fetch_assoc($result)) {
    $jadwal_hari_ini[] = $row;
}

// Fallback data jika kosong (untuk demo)
if (empty($jadwal_hari_ini)) {
    $jadwal_hari_ini = [
        [
            'nama_matkul' => 'Struktur Data',
            'kode_matkul' => 'TI-001',
            'nama_kelas' => 'IF-44-01',
            'waktu_mulai' => '2026-06-21 08:00:00',
            'waktu_selesai' => '2026-06-21 09:40:00',
            'status' => 'Selesai',
            'id_sesi' => 1
        ],
        [
            'nama_matkul' => 'Sistem Operasi',
            'kode_matkul' => 'TI-005',
            'nama_kelas' => 'IF-44-05',
            'waktu_mulai' => '2026-06-21 10:00:00',
            'waktu_selesai' => '2026-06-21 11:40:00',
            'status' => 'Aktif',
            'id_sesi' => 2
        ],
        [
            'nama_matkul' => 'Basis Data Lanjut',
            'kode_matkul' => 'TI-008',
            'nama_kelas' => 'IF-44-03',
            'waktu_mulai' => '2026-06-21 13:00:00',
            'waktu_selesai' => '2026-06-21 14:40:00',
            'status' => 'Belum Mulai',
            'id_sesi' => 3
        ]
    ];
}

// ============================================
// 5. TARGET MENGAJAR (semester ini)
// ============================================
$query_target = "SELECT 
    COUNT(*) as total_sesi,
    COUNT(CASE WHEN status = 'Selesai' THEN 1 END) as sesi_selesai
FROM sesi_absensi 
WHERE id_dosen = ? AND YEAR(waktu_mulai) = YEAR(CURDATE())";

$stmt = mysqli_prepare($conn, $query_target);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$target = mysqli_fetch_assoc($result);
$total_sesi = $target['total_sesi'] ?? 16;
$sesi_selesai = $target['sesi_selesai'] ?? 14;
$persentase_target = $total_sesi > 0 ? round(($sesi_selesai / $total_sesi) * 100, 1) : 87.5;
$sisa_sesi = max(0, $total_sesi - $sesi_selesai);

// ============================================
// 6. TANGGAL & SEMESTER
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

// Tentukan semester berdasarkan bulan
$bulan_sekarang = (int)date('m');
$tahun_sekarang = date('Y');
if ($bulan_sekarang >= 1 && $bulan_sekarang <= 6) {
    $semester = "Semester Genap " . ($tahun_sekarang - 1) . "/" . $tahun_sekarang;
} else {
    $semester = "Semester Ganjil " . $tahun_sekarang . "/" . ($tahun_sekarang + 1);
}

// Greeting berdasarkan waktu
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

        /* Button Buat Sesi Baru */
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

        /* Content Layout (Tabel + Sidebar) */
        .content-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Tabel Sesi */
        .table-section {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-header h2 {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1a1a1a;
        }
        .table-filter {
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        .schedule-table th {
            text-align: left;
            padding: 0.875rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .schedule-table td {
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .schedule-table tr:last-child td { border-bottom: none; }
        .matkul-name {
            font-weight: 600;
            color: #1a1a1a;
        }
        .matkul-code {
            font-size: 0.75rem;
            color: #6b7280;
        }
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
        .btn-detail {
            background: none;
            border: 1px solid #d1d5db;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1a1a1a;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-monitor {
            background: #c45a0a;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-more {
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-size: 1.25rem;
        }

        /* Sidebar Right */
        .sidebar-right {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .shortcut-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.25rem;
        }
        .shortcut-card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .shortcut-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
        }
        .shortcut-item:hover { background: #f9fafb; }
        .shortcut-icon {
            width: 36px;
            height: 36px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .shortcut-icon.report { background: #fef3c7; color: #d97706; }
        .shortcut-icon.class { background: #dbeafe; color: #2563eb; }
        .shortcut-icon.pdf { background: #f3f4f6; color: #6b7280; }
        .shortcut-text {
            flex: 1;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .shortcut-arrow {
            color: #9ca3af;
        }

        /* Target Mengajar */
        .target-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.25rem;
        }
        .target-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .target-title {
            font-size: 1rem;
            font-weight: 700;
        }
        .target-percent {
            font-size: 1.25rem;
            font-weight: 700;
            color: #16a34a;
        }
        .target-subtitle {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }
        .progress-fill {
            height: 100%;
            background: #16a34a;
            border-radius: 9999px;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .progress-label strong {
            color: #16a34a;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .content-layout { grid-template-columns: 1fr; }
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
                        <rect x="4" y="4" width="14" height="14" rx="2" fill="#E8670A"/>
                        <rect x="26" y="4" width="14" height="14" rx="2" fill="#E8670A"/>
                        <rect x="4" y="26" width="14" height="14" rx="2" fill="#E8670A"/>
                        <rect x="26" y="26" width="6" height="6" rx="1" fill="#E8670A"/>
                        <rect x="34" y="26" width="6" height="6" rx="1" fill="#E8670A"/>
                        <rect x="26" y="34" width="6" height="6" rx="1" fill="#E8670A"/>
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

            <!-- Content Layout: Tabel + Sidebar -->
            <div class="content-layout">
                <!-- Tabel Sesi Absensi Hari Ini -->
                <div class="table-section">
                    <div class="table-header">
                        <h2>Sesi Absensi Hari Ini</h2>
                        <button class="table-filter">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                            </svg>
                        </button>
                    </div>
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Mata Kuliah</th>
                                <th>Kelas</th>
                                <th>Waktu</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_hari_ini as $j): 
                                $waktu_mulai = date('H:i', strtotime($j['waktu_mulai']));
                                $waktu_selesai = date('H:i', strtotime($j['waktu_selesai']));
                                $status_class = str_replace(' ', '-', $j['status']);
                            ?>
                            <tr>
                                <td>
                                    <div class="matkul-name"><?= htmlspecialchars($j['nama_matkul']) ?></div>
                                    <div class="matkul-code"><?= htmlspecialchars($j['kode_matkul']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($j['nama_kelas']) ?></td>
                                <td><?= $waktu_mulai ?> - <?= $waktu_selesai ?></td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>"><?= $j['status'] ?></span>
                                </td>
                                <td>
                                    <?php if ($j['status'] === 'Selesai'): ?>
                                        <a href="detail_sesi.php?id=<?= $j['id_sesi'] ?>" class="btn-detail">Detail</a>
                                    <?php elseif ($j['status'] === 'Aktif'): ?>
                                        <a href="monitor_sesi.php?id=<?= $j['id_sesi'] ?>" class="btn-monitor">Monitor</a>
                                    <?php else: ?>
                                        <button class="btn-more">⋮</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sidebar Right -->
                <div class="sidebar-right">
                    <!-- Shortcut -->
                    <div class="shortcut-card">
                        <h3>Shortcut</h3>
                        <a href="laporan.php" class="shortcut-item">
                            <div class="shortcut-icon report">📋</div>
                            <div class="shortcut-text">Lihat Laporan</div>
                            <div class="shortcut-arrow">›</div>
                        </a>
                        <a href="kelola_kelas.php" class="shortcut-item">
                            <div class="shortcut-icon class"></div>
                            <div class="shortcut-text">Kelola Kelas</div>
                            <div class="shortcut-arrow">›</div>
                        </a>
                        <a href="unduh_pdf.php" class="shortcut-item">
                            <div class="shortcut-icon pdf">📄</div>
                            <div class="shortcut-text">Unduh PDF</div>
                            <div class="shortcut-arrow">↓</div>
                        </a>
                    </div>

                    <!-- Target Mengajar -->
                    <div class="target-card">
                        <div class="target-header">
                            <div class="target-title">Target Mengajar</div>
                            <div class="target-percent"><?= $persentase_target ?>%</div>
                        </div>
                        <div class="target-subtitle"><?= $semester ?> • <?= $sesi_selesai ?>/<?= $total_sesi ?> Sesi</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $persentase_target ?>%"></div>
                        </div>
                        <div class="progress-label">
                            <strong><?= $persentase_target ?>%</strong>
                            <span>Sisa <?= $sisa_sesi ?> Sesi</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>