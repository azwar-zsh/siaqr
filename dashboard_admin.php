<?php
session_start();
include 'connection.php';

// Proteksi: Wajib login & role admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$nama_admin = $_SESSION['nama'] ?? 'Admin';

// Statistik dari database
$query_stats = "
    SELECT 
        (SELECT COUNT(*) FROM mahasiswa) as total_mahasiswa,
        (SELECT COUNT(*) FROM dosen) as total_dosen,
        (SELECT COUNT(*) FROM mata_kuliah) as total_matkul,
        (SELECT COUNT(*) FROM sesi_absensi WHERE status='berlangsung') as sesi_berjalan
";
$result = mysqli_query($conn, $query_stats);
$stats = mysqli_fetch_assoc($result);

// Log aktivitas (contoh data - nanti bisa dibuat tabel log_activity)
$log_aktivitas = [
    [
        'pengguna' => 'Steven',
        'role' => 'Mahasiswa',
        'aktivitas' => 'Melakukan Presensi - IK Pemrograman Web',
        'waktu' => '10:45:23',
        'ip' => '192.168.1.12',
        'status' => 'success'
    ],
    [
        'pengguna' => 'Azwar',
        'role' => 'Dosen',
        'role' => 'Dosen',
        'aktivitas' => 'Generate QR Code - Sesi 4',
        'waktu' => '10:30:15',
        'ip' => '10.10.14.88',
        'status' => 'warning'
    ],
    [
        'pengguna' => 'Yosia',
        'role' => 'Mahasiswa',
        'aktivitas' => 'Login Sistem',
        'waktu' => '09:15:12',
        'ip' => '10.10.14.3',
        'status' => 'info'
    ],
    [
        'pengguna' => 'Elisa',
        'role' => 'Mahasiswa',
        'aktivitas' => 'Melakukan Presensi - MK Kecerdasan Buatan',
        'waktu' => '07:50:45',
        'ip' => '192.168.1.45',
        'status' => 'success'
    ],
];

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Portal SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/admin.css">
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
                    <span class="logo-subtitle">Management System</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard_admin.php" class="nav-item <?= $active_page === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Dashboard
                </a>
                <a href="kelola_mahasiswa.php" class="nav-item <?= $active_page === 'mahasiswa' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Kelola Mahasiswa
                </a>
                <a href="kelola_dosen.php" class="nav-item <?= $active_page === 'dosen' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Kelola Dosen
                </a>
                <a href="kelola_matkul.php" class="nav-item <?= $active_page === 'matkul' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                    Kelola Mata Kuliah
                </a>
                <a href="kelola_kelas.php" class="nav-item <?= $active_page === 'kelas' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Kelola Kelas
                </a>
                <a href="pantau_aktivitas.php" class="nav-item <?= $active_page === 'aktivitas' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Pantau Aktivitas
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
                    <h1>Selamat Datang, <?= htmlspecialchars($nama_admin) ?></h1>
                    <p class="header-subtitle">Kelola sistem absensi dengan mudah dan efisien</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Import CSV
                    </button>
                    <button class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Tambah Pengguna
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Total Mahasiswa</div>
                        <div class="stat-value"><?= number_format($stats['total_mahasiswa']) ?></div>
                        <div class="stat-change positive">+12% dari bulan lalu</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Total Dosen</div>
                        <div class="stat-value"><?= number_format($stats['total_dosen']) ?></div>
                        <div class="stat-change neutral">Stabil</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Mata Kuliah Aktif</div>
                        <div class="stat-value"><?= number_format($stats['total_matkul']) ?></div>
                        <div class="stat-change positive">+2 semester ini</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Sesi Berjalan</div>
                        <div class="stat-value"><?= number_format($stats['sesi_berjalan']) ?></div>
                        <div class="stat-change neutral">Aktif sekarang</div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Log Aktivitas Sistem</h2>
                    <a href="#" class="link-see-all">Lihat Semua</a>
                </div>
                <div class="activity-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Aktivitas</th>
                                <th>Waktu</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($log_aktivitas as $log): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar"><?= strtoupper(substr($log['pengguna'], 0, 1)) ?></div>
                                        <div>
                                            <div class="user-name"><?= htmlspecialchars($log['pengguna']) ?></div>
                                            <div class="user-role"><?= $log['role'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($log['aktivitas']) ?></td>
                                <td><?= $log['waktu'] ?></td>
                                <td><?= $log['ip'] ?></td>
                                <td>
                                    <span class="status-badge <?= $log['status'] ?>">
                                        <?php if ($log['status'] === 'success'): ?>
                                            Sukses
                                        <?php elseif ($log['status'] === 'warning'): ?>
                                            Peringatan
                                        <?php else: ?>
                                            Info
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>