<?php
session_start();
include 'connection.php'; 
// Proteksi: hanya super_admin boleh masuk
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php');
    exit;
}

$nama = $_SESSION['nama'] ?? 'Super Admin';
$active_page = 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin - SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* === GLOBAL RESET & FONT === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5f7fa;
            color: #1a1a1a;
        }

        .admin-wrapper {
            width: 100%;
        }

        /* === SIDEBAR === */
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

        .logo-icon {
            width: 44px;
            height: 44px;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .logo-subtitle {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .sidebar-nav {
            padding: 1.5rem 1rem;
            flex: 1;
        }

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

        .nav-item svg {
            width: 20px;
            height: 20px;
        }

        .nav-item:hover {
            background: #f9fafb;
            color: #1a1a1a;
        }

        .nav-item.active {
            background: #fff0e4;
            color: #e8670a;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

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

        .logout-link:hover {
            background: #fef2f2;
        }

        .logout-link svg {
            width: 20px;
            height: 20px;
        }

        /* === MAIN CONTENT === */

        .main-content {
            margin-left: 280px;
            padding: 2rem;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* === BUTTONS === */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .btn-primary {
            background: #e8670a;
            color: white;
        }

        .btn-primary:hover {
            background: #c45a0a;
        }

        .btn-outline {
            background: white;
            color: #1a1a1a;
            border: 1px solid #d1d5db;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        /* === CARD & GRID === */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card h3 {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #1a1a1a;
        }

        .card p {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        /* === ACTIVITY LIST === */
        .activity-list {
            margin-top: 1.5rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon.success { background: #dcfce7; color: #16a34a; }
        .activity-icon.warning { background: #fef3c7; color: #d97706; }
        .activity-icon.info { background: #dbeafe; color: #2563eb; }

        .activity-content {
            flex: 1;
        }

        .activity-content strong {
            font-weight: 600;
            color: #1a1a1a;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* === RESPONSIVE === */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .main-content {
                margin-left: 0;
            }
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
                    <span class="logo-subtitle">Management System</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard_super.php" class="nav-item <?= $active_page === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Dashboard
                </a>
                <a href="kelola_admin.php" class="nav-item <?= $active_page === 'admin' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Kelola Admin
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
            <div class="content-header">
                <div class="header-left">
                    <h1>Halo, <?= htmlspecialchars($nama) ?></h1>
                    <p class="header-subtitle">Anda adalah Super Admin — memiliki akses penuh ke seluruh sistem.</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Tambah Pengguna
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid">
                <div class="card">
                    <h3>Total Admin</h3>
                    <?php
                    $q = mysqli_query($conn, "SELECT COUNT(*) as total FROM admin");
                    $r = mysqli_fetch_assoc($q);
                    ?>
                    <p><strong><?= $r['total'] ?></strong> akun</p>
                    <a href="kelola_admin.php" class="btn btn-outline">Kelola</a>
                </div>

                <div class="card">
                    <h3>Total Mata Kuliah</h3>
                    <?php
                    $q = mysqli_query($conn, "SELECT COUNT(*) as total FROM mata_kuliah");
                    $r = mysqli_fetch_assoc($q);
                    ?>
                    <p><strong><?= $r['total'] ?></strong> mata kuliah</p>
                    <a href="kelola_matkul.php" class="btn btn-outline">Kelola</a>
                </div>

                <div class="card">
                    <h3>Sesi Berjalan</h3>
                    <?php
                    $q = mysqli_query($conn, "SELECT COUNT(*) as total FROM sesi_absensi WHERE status = 'berlangsung'");
                    $r = mysqli_fetch_assoc($q);
                    ?>
                    <p><strong><?= $r['total'] ?></strong> sesi aktif</p>
                    <a href="#" class="btn btn-outline">Pantau</a>
                </div>
            </div>

            <!-- Aktivitas Terbaru -->
            <div class="card" style="margin-top: 1.5rem;">
                <div class="section-header">
                    <h2>Aktivitas Terbaru</h2>
                </div>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-8.64"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <strong>Super Admin</strong> login dari IP <code>192.168.1.100</code>
                            <div class="activity-time">2 menit lalu</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-8.64"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <strong>Yosia</strong> absen di <em>"Pemrograman Web"</em>
                            <div class="activity-time">15 menit lalu</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon warning">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <strong>Azwar</strong> gagal login 3x
                            <div class="activity-time">22 menit lalu</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>