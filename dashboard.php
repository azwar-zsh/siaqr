<?php
session_start();
require_once 'connection.php'; 

// Proteksi: hanya mahasiswa yang boleh masuk
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') {
    header('Location: index.php');
    exit;
}

$id_mahasiswa = $_SESSION['id_user']; 
$nama = $_SESSION['nama'] ?? 'Mahasiswa';
$nim = $_SESSION['nim'] ?? 'NIM Tidak Diketahui';
$prodi_mahasiswa = $_SESSION['program_studi'] ?? ''; 

// STATISTIK KEHADIRAN 
$query_hadir = "SELECT COUNT(*) as total_hadir FROM kehadiran WHERE id_mahasiswa = ? AND keterangan IN ('Hadir', 'Terlambat')";
$stmt_hadir = mysqli_prepare($conn, $query_hadir);
mysqli_stmt_bind_param($stmt_hadir, "i", $id_mahasiswa);
mysqli_stmt_execute($stmt_hadir);
$hadir = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hadir))['total_hadir'] ?? 0;

$query_total_sesi = "SELECT COUNT(DISTINCT sa.id_sesi) as total_sesi FROM sesi_absensi sa JOIN kelas k ON sa.id_kelas = k.id_kelas WHERE k.program_studi = ? OR k.program_studi = ''"; 
$stmt_sesi = mysqli_prepare($conn, $query_total_sesi);
mysqli_stmt_bind_param($stmt_sesi, "s", $prodi_mahasiswa);
mysqli_stmt_execute($stmt_sesi);
$total_pertemuan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sesi))['total_sesi'] ?? 0;

$persentase_kehadiran = ($total_pertemuan > 0) ? round(($hadir / $total_pertemuan) * 100) : 0;
$status_batas = ($persentase_kehadiran >= 80) ? "Tepat Waktu" : (($persentase_kehadiran >= 75) ? "Aman" : "Perhatian");
$color_batas = ($persentase_kehadiran >= 75) ? "#E8670A" : "#ef4444";

// JADWAL HARI INI
$query_jadwal = "SELECT mk.nama_matkul, sa.waktu_mulai, sa.waktu_selesai, k.ruangan, sa.status FROM sesi_absensi sa JOIN kelas k ON sa.id_kelas = k.id_kelas JOIN mata_kuliah mk ON k.id_matkul = mk.id_matkul WHERE DATE(sa.waktu_mulai) = CURDATE() ORDER BY sa.waktu_mulai ASC";
$result_jadwal = mysqli_query($conn, $query_jadwal);
$jadwal_hari_ini = [];
while ($row = mysqli_fetch_assoc($result_jadwal)) { $jadwal_hari_ini[] = $row; }

$hari_ini = date('l, d F Y');
$hari_indo = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];
$bulan_indo = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$tanggal_formatted = str_replace(array_keys($hari_indo), array_values($hari_indo), str_replace(array_keys($bulan_indo), array_values($bulan_indo), $hari_ini));

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa – Portal SIAQR</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #fdfbf9; color: #1a1a1a; display: flex; }
        .admin-wrapper { display: flex; width: 100%; min-height: 100vh;}

        /* SIDEBAR IDENTIK DENGAN DOSEN */
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
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; max-width: 800px; margin-right: auto; }
        .mobile-header-icons { display: none; justify-content: flex-end; gap: 1rem; margin-bottom: 1rem; color: #1a1a1a; }
        .profile-header { margin-bottom: 1.5rem; }
        .profile-header h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; }
        .profile-header p { font-size: 0.85rem; color: #6b7280; }

        .scan-card { background: #e8670a; border-radius: 1rem; padding: 2rem 1.5rem; text-align: center; color: white; box-shadow: 0 8px 20px rgba(232, 103, 10, 0.25); margin-bottom: 2rem; cursor: pointer; transition: transform 0.2s; }
        .scan-card:hover { transform: translateY(-3px); }
        .scan-icon-wrapper { width: 64px; height: 64px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(255, 255, 255, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); } }
        .scan-card h2 { font-size: 1.125rem; font-weight: 700; margin-bottom: 0.25rem; }
        .scan-card p { font-size: 0.75rem; opacity: 0.9; }

        .stats-section { margin-bottom: 2rem; }
        .stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
        .stats-title { font-size: 0.75rem; font-weight: 700; color: #6b7280; letter-spacing: 0.5px; }
        .stats-percentage { font-size: 1.25rem; font-weight: 800; color: #e8670a; }
        .progress-bg { background-color: #e5e7eb; height: 6px; border-radius: 3px; width: 100%; margin-bottom: 0.75rem; }
        .progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease-in-out; }
        .stats-footer { display: flex; justify-content: space-between; font-size: 0.75rem; color: #6b7280; }

        .schedule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .schedule-header h3 { font-size: 1rem; font-weight: 700; color: #1a1a1a; }
        .schedule-header a { font-size: 0.75rem; color: #e8670a; text-decoration: none; font-weight: 600; }
        .schedule-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1rem; }
        .badge-ongoing { background-color: #fff0e4; color: #e8670a; font-size: 0.65rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 0.25rem; display: inline-block; margin-bottom: 0.75rem; }
        .schedule-card h4 { font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; color: #1a1a1a; }
        .schedule-details { display: flex; gap: 1.5rem; font-size: 0.8rem; color: #6b7280; }
        .detail-item { display: flex; align-items: center; gap: 0.4rem; }
        .detail-item svg { width: 14px; height: 14px; }

        .total-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; }
        .total-icon { width: 40px; height: 40px; background: #f9fafb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #1a1a1a; border: 1px solid #e5e7eb; font-size: 0.8rem; font-weight: 700; }
        .total-text h4 { font-size: 0.875rem; font-weight: 700; color: #1a1a1a; }
        .total-text p { font-size: 0.75rem; color: #6b7280; margin-top: 0.2rem; }

        /* BOTTOM NAV (MOBILE) */
        .bottom-nav { display: none; position: fixed; bottom: 0; width: 100%; background: #ffffff; border-top: 1px solid #e5e7eb; padding: 0.75rem 2rem; justify-content: space-between; z-index: 100; }
        .nav-bottom-item { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; color: #6b7280; text-decoration: none; font-size: 0.7rem; font-weight: 500; }
        .nav-bottom-item svg { width: 20px; height: 20px; }
        .nav-bottom-item.active { color: #e8670a; font-weight: 600; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1.5rem 1.5rem 6rem; background: #ffffff; min-height: 100vh; }
            .bottom-nav { display: flex; }
            .mobile-header-icons { display: flex; }
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
                    <span class="logo-subtitle">Area Mahasiswa</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    Dashboard
                </a>
                <a href="riwayat_mhs.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Riwayat Kehadiran
                </a>
                <a href="profil_mhs.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Profil
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
            <div class="mobile-header-icons">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <div class="profile-header">
                <h1>Halo, <?= htmlspecialchars($nama) ?></h1>
                <p>NIM: <?= htmlspecialchars($nim) ?></p>
            </div>
            <div class="scan-card" onclick="window.location.href='scan.php'">
                <div class="scan-icon-wrapper">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h3"></path><path d="M17 4h3v3"></path><path d="M4 17v3h3"></path><path d="M17 20h3v-3"></path><rect x="9" y="9" width="6" height="6"></rect></svg>
                </div>
                <h2>Scan QR Absensi</h2>
                <p>Ketuk untuk mulai memindai</p>
            </div>
            <div class="stats-section">
                <div class="stats-header">
                    <span class="stats-title">STATISTIK KEHADIRAN</span>
                    <span class="stats-percentage"><?= $persentase_kehadiran ?>%</span>
                </div>
                <div class="progress-bg"><div class="progress-fill" style="width: <?= $persentase_kehadiran ?>%; background-color: <?= $color_batas ?>;"></div></div>
                <div class="stats-footer">
                    <span>Batas mata kuliah semester ini</span>
                    <strong style="color: <?= $color_batas ?>;"><?= $status_batas ?></strong>
                </div>
            </div>
            <div class="schedule-header">
                <h3>Jadwal Hari Ini</h3><a href="jadwal.php">Lihat Semua</a>
            </div>
            <?php if (empty($jadwal_hari_ini)): ?>
                <div class="schedule-card" style="text-align: center; color: #6b7280;">Tidak ada jadwal perkuliahan hari ini.</div>
            <?php else: ?>
                <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                <div class="schedule-card">
                    <?php if ($jadwal['status'] === 'Aktif'): ?><div class="badge-ongoing">SEDANG BERLANGSUNG</div><?php endif; ?>
                    <h4><?= htmlspecialchars($jadwal['nama_matkul']) ?></h4>
                    <div class="schedule-details">
                        <div class="detail-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?></div>
                        <div class="detail-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> <?= htmlspecialchars($jadwal['ruangan'] ?? 'Ruangan belum diatur') ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            <div class="total-card">
                <div class="total-icon"><?= $hadir ?>/<?= $total_pertemuan ?></div>
                <div class="total-text"><h4>Total Kehadiran</h4><p><?= $hadir ?> pertemuan bersisa pekan ini</p></div>
            </div>
        </main>
        
        <div class="bottom-nav">
            <a href="dashboard.php" class="nav-bottom-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
            <a href="riwayat_mhs.php" class="nav-bottom-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Riwayat</a>
            <a href="profil_mhs.php" class="nav-bottom-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>Profil</a>
        </div>
    </div>
</body>
</html>