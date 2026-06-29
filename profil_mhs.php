<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') { header('Location: login.php'); exit; }
$id_mahasiswa = $_SESSION['id_user']; 

$query = "SELECT nama, nim, program_studi, angkatan FROM mahasiswa WHERE id_mahasiswa = ?";
$stmt = mysqli_prepare($conn, $query); mysqli_stmt_bind_param($stmt, "i", $id_mahasiswa); mysqli_stmt_execute($stmt);
$mhs = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$nama = $mhs['nama'] ?? 'Mahasiswa'; $nim = $mhs['nim'] ?? '-'; $prodi = ucwords(strtolower($mhs['program_studi'] ?? '')); $angkatan = $mhs['angkatan'] ?? date('Y');
$selisih_tahun = (int)date('Y') - $angkatan; $semester_aktif = ($selisih_tahun * 2) + ((int)date('n') > 6 ? 1 : 0); $jenis_semester = ($semester_aktif % 2 == 0) ? 'Genap' : 'Ganjil';

$query_sks = "SELECT SUM(mk.sks) as total_sks FROM kelas k JOIN mata_kuliah mk ON k.id_matkul = mk.id_matkul WHERE k.program_studi = ? OR k.program_studi = ''";
$stmt_sks = mysqli_prepare($conn, $query_sks); mysqli_stmt_bind_param($stmt_sks, "s", $prodi); mysqli_stmt_execute($stmt_sks);
$total_sks = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sks))['total_sks'] ?? 144;

$fakultas = "Jurusan Teknologi Produksi dan Industri"; $ipk = "4.00"; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Mahasiswa – SIAQR</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #fdfbf9; color: #1a1a1a; display: flex; }
        .admin-wrapper { display: flex; width: 100%; min-height: 100vh;}

        /* SIDEBAR IDENTIK DOSEN */
        .sidebar { width: 280px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 1rem; }
        .logo-icon { width: 44px; height: 44px; }
        .logo-text { display: flex; flex-direction: column; }
        .logo-title { font-size: 1.125rem; font-weight: 700; color: #1a1a1a; }
        .logo-subtitle { font-size: 0.75rem; color: #6b7280; }
        .sidebar-nav { padding: 1.5rem 1rem; flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; margin-bottom: 0.25rem; border-radius: 0.5rem; color: #6b7280; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .nav-item svg { width: 20px; height: 20px; }
        .nav-item:hover { background: #f9fafb; color: #1a1a1a; }
        .nav-item.active { background: #fff0e4; color: #e8670a; }
        .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; }
        .logout-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: #ef4444; text-decoration: none; font-weight: 600; border-radius: 0.5rem; transition: all 0.2s; }
        .logout-link:hover { background: #fef2f2; }
        .logout-link svg { width: 20px; height: 20px; }

        /* MAIN CONTENT */
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; max-width: 500px; margin: 0 auto; }
        .profile-container { text-align: center; margin-bottom: 2rem; }
        .avatar-wrapper { position: relative; width: 90px; height: 90px; margin: 0 auto 1rem; }
        .avatar { width: 100%; height: 100%; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #9ca3af; border: 2px solid #e8670a; }
        .avatar svg { width: 50px; height: 50px; }
        .badge-verified { position: absolute; bottom: 0; right: 0; background: #e8670a; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
        .badge-verified svg { width: 12px; height: 12px; }
        
        .profile-container h2 { font-size: 1.25rem; font-weight: 700; color: #1a1a1a; margin-bottom: 0.2rem;}
        .profile-container p { font-size: 0.85rem; color: #6b7280; line-height: 1.4;}

        .semester-badge { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1rem; margin: 1.5rem 0; text-align: center; }
        .semester-badge span { font-size: 0.7rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}
        .semester-badge h3 { font-size: 1rem; font-weight: 700; color: #1a1a1a; margin-top: 0.2rem;}

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem; }
        .stat-box { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1rem; text-align: center; }
        .stat-icon { color: #e8670a; margin-bottom: 0.5rem; display: flex; justify-content: center;}
        .stat-box h4 { font-size: 1.2rem; font-weight: 800; color: #1a1a1a; }
        .stat-box span { font-size: 0.75rem; color: #6b7280; font-weight: 500;}

        .menu-list { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; margin-bottom: 1.5rem;}
        .menu-item { display: flex; justify-content: space-between; align-items: center; padding: 1.1rem 1.25rem; border-bottom: 1px solid #e5e7eb; text-decoration: none; color: #1a1a1a; font-size: 0.85rem; font-weight: 600; transition: background 0.2s;}
        .menu-item:last-child { border-bottom: none; }
        .menu-item:hover { background: #f9fafb; }
        .menu-left { display: flex; align-items: center; gap: 0.75rem; }
        .menu-left svg { width: 18px; height: 18px; color: #e8670a; }
        .menu-arrow { color: #9ca3af; }

        .btn-logout-mobile { display: none; background: #fff1f2; border: 1px solid #ffe4e6; color: #ef4444; width: 100%; padding: 1rem; border-radius: 0.75rem; justify-content: center; align-items: center; gap: 0.5rem; text-decoration: none; font-weight: 700; font-size: 0.85rem; transition: background 0.2s;}
        .btn-logout-mobile:hover { background: #ffe4e6; }
        .app-version { text-align: center; color: #9ca3af; font-size: 0.7rem; margin-top: 2rem; padding-bottom: 1rem;}

        /* BOTTOM NAV (MOBILE) */
        .bottom-nav { display: none; position: fixed; bottom: 0; width: 100%; background: #ffffff; border-top: 1px solid #e5e7eb; padding: 0.75rem 2rem; justify-content: space-between; z-index: 100; }
        .nav-bottom-item { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; color: #6b7280; text-decoration: none; font-size: 0.7rem; font-weight: 500; }
        .nav-bottom-item svg { width: 20px; height: 20px; }
        .nav-bottom-item.active { color: #e8670a; font-weight: 600; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 2.5rem 1.5rem 6rem; background: #ffffff; min-height: 100vh; max-width: 100%; }
            .bottom-nav { display: flex; }
            .btn-logout-mobile { display: flex; } /* Tampilkan tombol logout di layout utama jika di HP (karena sidebar hilang) */
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="4" width="14" height="14" rx="2" fill="#E8670A"/><rect x="26" y="4" width="14" height="14" rx="2" fill="#E8670A"/><rect x="4" y="26" width="14" height="14" rx="2" fill="#E8670A"/><rect x="26" y="26" width="6" height="6" rx="1" fill="#E8670A"/><rect x="34" y="26" width="6" height="6" rx="1" fill="#E8670A"/><rect x="26" y="34" width="6" height="6" rx="1" fill="#E8670A"/>
                    </svg>
                </div>
                <div class="logo-text"><span class="logo-title">Portal SIAQR</span><span class="logo-subtitle">Area Mahasiswa</span></div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a>
                <a href="riwayat_mhs.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Riwayat Kehadiran</a>
                <a href="profil_mhs.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="profile-container">
                <div class="avatar-wrapper">
                    <div class="avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                    <div class="badge-verified"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                </div>
                <h2><?= htmlspecialchars(ucwords($nama)) ?></h2>
                <p>NIM : <?= htmlspecialchars($nim) ?><br><strong><?= htmlspecialchars($prodi) ?></strong><br><?= $fakultas ?></p>
            </div>

            <div class="semester-badge"><span>SEMESTER AKTIF</span><h3>Semester <?= $semester_aktif ?> (<?= $jenis_semester ?>)</h3></div>

            <div class="stats-grid">
                <div class="stat-box"><div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div><h4><?= $ipk ?></h4><span>IPK TERAKHIR</span></div>
                <div class="stat-box"><div class="stat-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></div><h4><?= $total_sks ?> SKS</h4><span>TOTAL SKS</span></div>
            </div>

            <div style="font-size: 0.75rem; font-weight: 700; color: #6b7280; margin-bottom: 0.5rem; padding-left: 0.5rem;">Pengaturan Akun</div>
            <div class="menu-list">
                <a href="#" class="menu-item"><div class="menu-left"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> Ubah Password</div><svg class="menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
                <a href="#" class="menu-item"><div class="menu-left"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg> Pengaturan Notifikasi</div><svg class="menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
                <a href="#" class="menu-item"><div class="menu-left"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> Bantuan</div><svg class="menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
            </div>

            <a href="logout.php" class="btn-logout-mobile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>

            <div class="app-version">Versi Aplikasi 1.0 (Beta)</div>
        </main>
        
        <div class="bottom-nav">
            <a href="dashboard.php" class="nav-bottom-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
            <a href="riwayat_mhs.php" class="nav-bottom-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Riwayat</a>
            <a href="profil_mhs.php" class="nav-bottom-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>Profil</a>
        </div>
    </div>
</body>
</html>