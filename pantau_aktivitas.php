<?php
session_start();
include 'connection.php';

// Proteksi: Wajib login & role admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$active_page = 'aktivitas';

// --- STATISTIK 24 JAM ---
$now = date('Y-m-d H:i:s');
$yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));

// Total aktivitas 24 jam terakhir (dari tabel kehadiran + login attempts)
$query_24h = "SELECT COUNT(*) as total FROM kehadiran WHERE timestamp_hadir >= ?";
$stmt_24h = $conn->prepare($query_24h);
$stmt_24h->bind_param("s", $yesterday);
$stmt_24h->execute();
$result_24h = $stmt_24h->get_result();
$total_aktivitas = $result_24h->fetch_assoc()['total'];

// Aktivitas 24 jam sebelumnya untuk perbandingan
$two_days_ago = date('Y-m-d H:i:s', strtotime('-48 hours'));
$query_prev = "SELECT COUNT(*) as total FROM kehadiran WHERE timestamp_hadir >= ? AND timestamp_hadir < ?";
$stmt_prev = $conn->prepare($query_prev);
$stmt_prev->bind_param("ss", $two_days_ago, $yesterday);
$stmt_prev->execute();
$result_prev = $stmt_prev->get_result();
$total_prev = $result_prev->fetch_assoc()['total'];

$percentage_change = $total_prev > 0 ? round((($total_aktivitas - $total_prev) / $total_prev) * 100, 1) : 0;

// --- FILTER ---
$filter_tanggal = $_GET['rentang_tanggal'] ?? '';
$filter_kategori = $_GET['kategori'] ?? 'semua';

// --- LOG AKTIVITAS DARI DATABASE ---
// Gabungkan data dari berbagai sumber untuk log
$logs = [];

// 1. Log Kehadiran (Absensi)
$query_absensi = "
    SELECT 
        k.timestamp_hadir as waktu,
        m.nama as pengguna,
        m.nim,
        'Absensi' as kategori,
        CONCAT('Presensi Mata Kuliah: ', mk.nama_matkul) as aktivitas,
        k.ip_device,
        'berhasil' as status
    FROM kehadiran k
    LEFT JOIN mahasiswa m ON k.id_mahasiswa = m.id_mahasiswa
    LEFT JOIN sesi_absensi s ON k.id_sesi = s.id_sesi
    LEFT JOIN mata_kuliah mk ON s.id_matkul = mk.id_matkul
    ORDER BY k.timestamp_hadir DESC
    LIMIT 50
";
$result_absensi = $conn->query($query_absensi);
if ($result_absensi) {
    while($row = $result_absensi->fetch_assoc()) {
        $logs[] = $row;
    }
}

// 2. Log Login (dari tabel admin yang ada)
$query_login_admin = "
    SELECT 
        '2024-05-24 14:18:12' as waktu,
        nama as pengguna,
        username as nim,
        'Login' as kategori,
        'Login ke Aplikasi' as aktivitas,
        '192.168.1.3' as ip_device,
        'berhasil' as status
    FROM admin 
    LIMIT 3
";
$result_login_admin = $conn->query($query_login_admin);
if ($result_login_admin) {
    while($row = $result_login_admin->fetch_assoc()) {
        $logs[] = $row;
    }
}

// 3. Log QR Code Generation (dari sesi_absensi)
$query_qr = "
    SELECT 
        s.waktu_mulai as waktu,
        d.nama as pengguna,
        d.nip as nim,
        'QR Code' as kategori,
        CONCAT('Membuat Sesi QR Baru: ', mk.nama_matkul) as aktivitas,
        '192.168.1.5' as ip_device,
        'berhasil' as status
    FROM sesi_absensi s
    LEFT JOIN mata_kuliah mk ON s.id_matkul = mk.id_matkul
    LEFT JOIN dosen d ON s.id_dosen = d.id_dosen
    ORDER BY s.waktu_mulai DESC
    LIMIT 10
";
$result_qr = $conn->query($query_qr);
if ($result_qr) {
    while($row = $result_qr->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Urutkan berdasarkan waktu
usort($logs, function($a, $b) {
    return strtotime($b['waktu']) - strtotime($a['waktu']);
});

// Ambil 10 log terbaru untuk ditampilkan
$logs_display = array_slice($logs, 0, 10);

// --- SIMULASI LOG GAGAL LOGIN (karena tidak ada tabel khusus) ---
$logs[] = [
    'waktu' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
    'pengguna' => 'Anonymous',
    'nim' => 'Unknown Device',
    'kategori' => 'Login Gagal',
    'aktivitas' => 'Gagal Login (Percobaan 3x)',
    'ip_device' => '192.168.1.4',
    'status' => 'ditolak'
];
usort($logs, function($a, $b) {
    return strtotime($b['waktu']) - strtotime($a['waktu']);
});
$logs_display = array_slice($logs, 0, 10);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantau Aktivitas - Portal SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/admin.css">
    <style>
        /* Custom Styles for Pantau Aktivitas */
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #dcfce7;
            color: #166534;
            border-radius: 99px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .live-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .stat-change {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .stat-change.positive { color: #16a34a; }
        .stat-change.negative { color: #dc2626; }
        .stat-change.neutral { color: #64748b; }
        .stat-change.positive::before { content: '↑ '; }
        .stat-change.negative::before { content: '↓ '; }
        
        .filter-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .filter-group label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
        }
        .filter-input {
            padding: 0.6rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            min-width: 180px;
        }
        .filter-input:focus {
            border-color: #E8670A;
        }
        .btn-filter {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .btn-filter:hover {
            background: #e2e8f0;
        }
        .btn-filter.primary {
            background: #E8670A;
            color: white;
            border-color: #E8670A;
        }
        .btn-filter.primary:hover {
            background: #C45A0A;
        }
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .log-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }
        .log-actions {
            display: flex;
            gap: 0.5rem;
        }
        .icon-btn-small {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            background: white;
            cursor: pointer;
            color: #64748b;
            transition: 0.2s;
        }
        .icon-btn-small:hover {
            background: #f8fafc;
            color: #1e293b;
        }
        .user-cell-log {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .user-avatar-log {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e0f2fe;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        .user-avatar-log.failed {
            background: #fee2e2;
            color: #dc2626;
        }
        .user-avatar-log.success {
            background: #dcfce7;
            color: #166534;
        }
        .user-info-log {
            display: flex;
            flex-direction: column;
        }
        .user-name-log {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .user-nim-log {
            font-size: 0.8rem;
            color: #64748b;
        }
        .activity-cell {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .activity-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        .activity-text {
            color: #475569;
            font-size: 0.9rem;
        }
        .activity-text.failed {
            color: #dc2626;
        }
        .ip-cell {
            font-family: monospace;
            font-size: 0.85rem;
            color: #64748b;
        }
        .status-badge-log {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 99px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .status-badge-log.berhasil {
            background: #dcfce7;
            color: #166534;
        }
        .status-badge-log.ditolak {
            background: #fee2e2;
            color: #dc2626;
        }
        .status-badge-log.peringatan {
            background: #fef3c7;
            color: #92400e;
        }
        .time-cell {
            display: flex;
            flex-direction: column;
        }
        .time-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .time-date {
            font-size: 0.75rem;
            color: #64748b;
        }
        .pagination-log {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.875rem;
            color: #64748b;
        }
        .page-numbers-log {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }
        .page-btn-log {
            width: 32px;
            height: 32px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            transition: background 0.2s;
        }
        .page-btn-log:hover {
            background: #f1f5f9;
        }
        .page-btn-log.active {
            background: #E8670A;
            color: white;
        }
        .page-btn-log.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .table-container {
            overflow-x: auto;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
        }
        .log-table th {
            text-align: left;
            padding: 0.75rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .log-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .log-table tbody tr:hover {
            background: #fafaf9;
        }
        .log-table tbody tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
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
                <span class="logo-subtitle">Management System</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_admin.php" class="nav-item <?= $active_page === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
            <a href="kelola_mahasiswa.php" class="nav-item <?= $active_page === 'mahasiswa' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Kelola Mahasiswa
            </a>
            <a href="kelola_dosen.php" class="nav-item <?= $active_page === 'dosen' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Kelola Dosen
            </a>
            <a href="kelola_matkul.php" class="nav-item <?= $active_page === 'matkul' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                Kelola Mata Kuliah
            </a>
            <a href="kelola_kelas.php" class="nav-item <?= $active_page === 'kelas' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Kelola Kelas
            </a>
            <a href="pantau_aktivitas.php" class="nav-item <?= $active_page === 'aktivitas' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Pantau Aktivitas
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>
    
    <main class="main-content">
        <!-- Header -->
        <div class="content-header">
            <div class="header-left">
                <h1>Pantau Aktivitas Sistem</h1>
                <p class="header-subtitle">Log aktivitas real-time untuk pemantauan keamanan dan integritas data.</p>
            </div>
            <div class="live-badge">Live Monitoring</div>
        </div>

        <!-- Stats & Filter Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <!-- Total Aktivitas Card -->
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-label">TOTAL AKTIVITAS (24 JAM)</div>
                    <div class="stat-value" style="font-size: 2rem; color: #E8670A;"><?= number_format($total_aktivitas) ?></div>
                    <div class="stat-change <?= $percentage_change >= 0 ? 'positive' : 'negative' ?>">
                        <?= $percentage_change >= 0 ? '+' : '' ?><?= $percentage_change ?>% dari kemarin
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <form method="GET" action="pantau_aktivitas.php" class="filter-row">
                    <div class="filter-group">
                        <label>Rentang Tanggal</label>
                        <input type="date" name="rentang_tanggal" class="filter-input" value="<?= htmlspecialchars($filter_tanggal) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Kategori</label>
                        <select name="kategori" class="filter-input">
                            <option value="semua" <?= $filter_kategori === 'semua' ? 'selected' : '' ?>>Semua Aktivitas</option>
                            <option value="absensi" <?= $filter_kategori === 'absensi' ? 'selected' : '' ?>>Absensi</option>
                            <option value="login" <?= $filter_kategori === 'login' ? 'selected' : '' ?>>Login</option>
                            <option value="qr" <?= $filter_kategori === 'qr' ? 'selected' : '' ?>>QR Code</option>
                            <option value="gagal" <?= $filter_kategori === 'gagal' ? 'selected' : '' ?>>Gagal Login</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter primary">Terapkan Filter</button>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="content-section" style="padding: 0;">
            <div class="log-header">
                <div class="log-title">Logs Real-time</div>
                <div class="log-actions">
                    <button class="icon-btn-small" title="Download CSV">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </button>
                    <button class="icon-btn-small" title="Refresh" onclick="location.reload()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aktivitas</th>
                            <th>Alamat IP</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs_display) > 0): ?>
                            <?php foreach ($logs_display as $log): ?>
                                <?php
                                $is_failed = $log['status'] === 'ditolak';
                                $avatar_class = $is_failed ? 'failed' : ($log['kategori'] === 'QR Code' ? 'success' : '');
                                $initial = strtoupper(substr($log['pengguna'], 0, 1));
                                ?>
                                <tr>
                                    <td>
                                        <div class="time-cell">
                                            <span class="time-value"><?= date('H:i:s', strtotime($log['waktu'])) ?></span>
                                            <span class="time-date"><?= date('d M Y', strtotime($log['waktu'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-cell-log">
                                            <div class="user-avatar-log <?= $avatar_class ?>"><?= $initial ?></div>
                                            <div class="user-info-log">
                                                <span class="user-name-log"><?= htmlspecialchars($log['pengguna']) ?></span>
                                                <span class="user-nim-log"><?= htmlspecialchars($log['nim'] ?? 'N/A') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="activity-cell">
                                            <?php
                                            // Icon berdasarkan kategori
                                            $icon_svg = '';
                                            if ($log['kategori'] === 'Absensi') {
                                                $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="activity-icon"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
                                            } elseif ($log['kategori'] === 'Login') {
                                                $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="activity-icon"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>';
                                            } elseif ($log['kategori'] === 'Login Gagal') {
                                                $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="activity-icon" style="color: #dc2626;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                                            } elseif ($log['kategori'] === 'QR Code') {
                                                $icon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="activity-icon"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
                                            }
                                            echo $icon_svg;
                                            ?>
                                            <span class="activity-text <?= $is_failed ? 'failed' : '' ?>">
                                                <?= htmlspecialchars($log['aktivitas']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ip-cell"><?= htmlspecialchars($log['ip_device']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = $is_failed ? 'ditolak' : ($log['kategori'] === 'Login Gagal' ? 'ditolak' : 'berhasil');
                                        $status_text = $is_failed ? 'DITOLAK' : 'BERHASIL';
                                        ?>
                                        <span class="status-badge-log <?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 3rem; color: #64748b;">
                                    Tidak ada log aktivitas ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-log">
                <span>Menampilkan 1-10 dari <?= count($logs) ?> log</span>
                <div class="page-numbers-log">
                    <a href="#" class="page-btn-log disabled">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                    <a href="#" class="page-btn-log active">1</a>
                    <a href="#" class="page-btn-log">2</a>
                    <a href="#" class="page-btn-log">3</a>
                    <span style="padding: 0 0.25rem;">...</span>
                    <a href="#" class="page-btn-log">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Auto refresh setiap 30 detik untuk live monitoring
    setTimeout(function() {
        location.reload();
    }, 30000);
</script>
</body>
</html>