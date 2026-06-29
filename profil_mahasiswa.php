<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya mahasiswa yang boleh akses
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') {
    header('Location: login.php');
    exit;
}

$id_mahasiswa = $_SESSION['id_user'] ?? 0;

// Ambil data mahasiswa dari database
$query = "SELECT nama, nim, angkatan, program_studi FROM mahasiswa WHERE id_mahasiswa = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_mahasiswa);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header('Location: login.php');
    exit;
}

// Hitung semester aktif berdasarkan angkatan
$tahun_sekarang = (int)date('Y');
$bulan_sekarang = (int)date('n');
$angkatan = (int)$user['angkatan'];
$selisih_tahun = $tahun_sekarang - $angkatan;
$semester_angka = (($tahun_sekarang - $angkatan) * 2);
if ($bulan_sekarang >= 8) {
    $semester_angka += 1;
}$semester_angka = max(1, min($semester_angka, 14));
$jenis = ($semester_angka % 2 === 0) ? 'Genap' : 'Ganjil';
$semester_label = "Semester $semester_angka ($jenis)";

// Hitung total kehadiran mahasiswa
$query_hadir = "SELECT COUNT(*) as total FROM kehadiran WHERE id_mahasiswa = ?";
$stmt2 = mysqli_prepare($conn, $query_hadir);
mysqli_stmt_bind_param($stmt2, "i", $id_mahasiswa);
mysqli_stmt_execute($stmt2);
$hadir_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
$total_hadir = $hadir_result['total'] ?? 0;

// Hitung total sesi yang ada (untuk persentase kehadiran)
$query_sesi = "SELECT COUNT(*) as total FROM sesi_absensi WHERE status = 'Selesai'";
$sesi_result = mysqli_fetch_assoc(mysqli_query($conn, $query_sesi));
$total_sesi = $sesi_result['total'] ?? 0;

$persen_hadir = $total_sesi > 0 ? round(($total_hadir / $total_sesi) * 100) : 0;

// Format nama program studi (capitalize)
$prodi = ucwords(strtolower($user['program_studi'] ?? '-'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Profil - SIAQR</title>
    <style>
        /* ── Reset & Base ── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary:       #C8621A;
            --primary-light: #E07A35;
            --bg:            #FDF0E8;
            --white:         #FFFFFF;
            --text-dark:     #1A1A1A;
            --text-muted:    #888888;
            --text-label:    #AAAAAA;
            --border:        #F0E0D0;
            --nim-bg:        #EDE0D8;
            --logout-bg:     #FDE8E0;
            --logout-color:  #CC4400;
            --shadow:        0 2px 12px rgba(200,98,26,0.08);
            --green:         #28A745;
        }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
        }

        /* ── Page Wrapper ── */
        .page-wrapper {
            max-width: 480px;
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg);
            padding-bottom: 85px;
        }

        /* ── Top Header ── */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px 14px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.5px;
        }

        .header-icons {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icons a {
            display: flex;
            align-items: center;
            color: var(--text-dark);
            text-decoration: none;
        }

        .header-icons svg {
            width: 24px;
            height: 24px;
        }

        /* ── Profile Section ── */
        .profile-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 20px 18px;
            gap: 8px;
        }

        /* Avatar */
        .avatar-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            margin-bottom: 4px;
        }

        .avatar-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid var(--primary);
            background: #D9D9D9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .avatar-circle svg {
            width: 62px;
            height: 62px;
            color: #AAAAAA;
        }

        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .edit-avatar-btn {
            position: absolute;
            bottom: 3px;
            right: 3px;
            width: 28px;
            height: 28px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
            text-decoration: none;
            cursor: pointer;
        }

        .edit-avatar-btn svg {
            width: 13px;
            height: 13px;
            color: #fff;
        }

        /* Name & Info */
        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
        }

        .nim-badge {
            background: var(--nim-bg);
            color: var(--text-dark);
            font-size: 13px;
            font-weight: 500;
            padding: 5px 16px;
            border-radius: 20px;
        }

        .profile-prodi {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .profile-jurusan {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* ── Semester Card ── */
        .semester-card {
            margin: 4px 16px 12px;
            background: var(--white);
            border-radius: 16px;
            padding: 20px 24px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .card-icon { margin-bottom: 6px; }
        .card-icon svg { width: 26px; height: 26px; color: var(--primary); }

        .card-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-label);
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .card-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* ── Stats Row ── */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 0 16px 20px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 18px 16px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-icon { margin-bottom: 6px; }
        .stat-icon svg { width: 24px; height: 24px; }
        .stat-icon.green svg { color: var(--green); }
        .stat-icon.orange svg { color: var(--primary); }

        .stat-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-label);
            letter-spacing: 1.1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-dark);
        }

        /* ── Section Title ── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-label);
            letter-spacing: 1.2px;
            text-transform: uppercase;
            padding: 0 20px 10px;
        }

        /* ── Settings Group ── */
        .settings-group {
            margin: 0 16px;
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .settings-item {
            display: flex;
            align-items: center;
            padding: 16px 18px;
            text-decoration: none;
            color: var(--text-dark);
            gap: 14px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        .settings-item:last-child { border-bottom: none; }
        .settings-item:hover { background: #FDF5F0; }

        .settings-icon-box {
            width: 36px;
            height: 36px;
            background: var(--bg);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .settings-icon-box svg {
            width: 18px;
            height: 18px;
            color: var(--primary);
        }

        .settings-label {
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .chevron svg {
            width: 16px;
            height: 16px;
            color: var(--text-muted);
        }

        /* ── Logout ── */
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 16px;
            padding: 16px;
            background: var(--logout-bg);
            border: none;
            border-radius: 16px;
            color: var(--logout-color);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            width: calc(100% - 32px);
            transition: background 0.15s;
        }

        .logout-btn:hover { background: #FBDDD5; }
        .logout-btn svg { width: 20px; height: 20px; }

        /* ── Version ── */
        .app-version {
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            padding: 4px 0 20px;
        }

        /* ── Bottom Navigation ── */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            background: var(--white);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 10px 0 14px;
            border-top: 1px solid var(--border);
            z-index: 100;
            box-shadow: 0 -2px 16px rgba(0,0,0,0.06);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            text-decoration: none;
            color: #999;
            font-size: 11px;
            font-weight: 500;
            flex: 1;
        }

        .nav-item svg { width: 22px; height: 22px; }
        .nav-item.active { color: var(--primary); }
        .nav-item.active svg { color: var(--primary); }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- ── Top Header ── -->
    <header class="top-header">
        <span class="logo-text">SIAQR</span>
        <div class="header-icons">
            <a href="notifikasi.php" title="Notifikasi">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </a>
            <a href="profil_mahasiswa.php" title="Profil">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </a>
        </div>
    </header>

    <!-- ── Profile Section ── -->
    <section class="profile-section">

        <!-- Avatar -->
        <div class="avatar-wrapper">
            <div class="avatar-circle">
                <!-- Default person icon (ganti dengan <img> jika ada kolom foto) -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2
                             9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4
                             c0-3.2-6.4-4.8-9.6-4.8z"/>
                </svg>
            </div>
            <a href="edit_profil.php" class="edit-avatar-btn" title="Edit Foto">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71
                             7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83
                             1.83 3.75 3.75 1.83-1.83z"/>
                </svg>
            </a>
        </div>

        <h1 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h1>
        <span class="nim-badge">NIM: <?= htmlspecialchars($user['nim']) ?></span>
        <p class="profile-prodi"><?= htmlspecialchars($prodi) ?></p>
        <p class="profile-jurusan">Angkatan <?= htmlspecialchars($user['angkatan']) ?></p>

    </section>

    <!-- ── Semester Aktif ── -->
    <div class="semester-card">
        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/>
            </svg>
        </div>
        <p class="card-label">Semester Aktif</p>
        <p class="card-value"><?= $semester_label ?></p>
    </div>

    <!-- ── Stats: Kehadiran & Total Sesi ── -->
    <div class="stats-row">

        <div class="stat-card">
            <div class="stat-icon green">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M5 9h2v11H5V9zm4-5h2v16H9V4zm4 8h2v8h-2v-8zm4-4h2v12h-2V8z"/>
                </svg>
            </div>
            <p class="stat-label">Kehadiran</p>
            <p class="stat-value"><?= $persen_hadir ?>%</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
                </svg>
            </div>
            <p class="stat-label">Total Hadir</p>
            <p class="stat-value"><?= $total_hadir ?> Sesi</p>
        </div>

    </div>

    <!-- ── Pengaturan Akun ── -->
    <p class="section-title">Pengaturan Akun</p>

    <div class="settings-group">

        <!-- Ubah Password -->
        <a href="ubah_password.php" class="settings-item">
            <div class="settings-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="16" r="1"/>
                    <rect x="3" y="10" width="18" height="12" rx="2"/>
                    <path d="M7 10V7a5 5 0 0110 0v3"/>
                </svg>
            </div>
            <span class="settings-label">Ubah Password</span>
            <span class="chevron">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </span>
        </a>

        <!-- Pengaturan Notifikasi -->
        <a href="notifikasi_setting.php" class="settings-item">
            <div class="settings-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <span class="settings-label">Pengaturan Notifikasi</span>
            <span class="chevron">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </span>
        </a>

        <!-- Bantuan -->
        <a href="bantuan.php" class="settings-item">
            <div class="settings-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M9 9a3 3 0 015.12 2.1c0 2-3 2.9-3 2.9"/>
                    <circle cx="12" cy="17" r=".5" fill="currentColor"/>
                </svg>
            </div>
            <span class="settings-label">Bantuan</span>
            <span class="chevron">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </span>
        </a>

    </div>

    <!-- ── Logout ── -->
    <a href="logout.php" class="logout-btn"
       onclick="return confirm('Yakin ingin logout?')">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Logout
    </a>

    <!-- ── Versi Aplikasi ── -->
    <p class="app-version">Versi Aplikasi 1.0 (Beta)</p>

    <!-- ── Bottom Navigation ── -->
    <nav class="bottom-nav">
        <a href="dashboard_mahasiswa.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 3h8v8H3V3zm0 10h8v8H3v-8zm10-10h8v8h-8V3zm0 10h8v8h-8v-8z"/>
            </svg>
            <span>Dashboard</span>
        </a>
        <a href="riwayat_presensi.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <span>Riwayat</span>
        </a>
        <a href="profil_mahasiswa.php" class="nav-item active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2
                         9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4
                         c0-3.2-6.4-4.8-9.6-4.8z"/>
            </svg>
            <span>Profil</span>
        </a>
    </nav>

</div>
</body>
</html>
