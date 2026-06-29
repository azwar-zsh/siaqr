<?php
session_start();
require_once 'connection.php';

// Proteksi akses hanya untuk mahasiswa
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') { 
    header('Location: index.php'); 
    exit; 
}

$id_mahasiswa = $_SESSION['id_user']; 
$prodi_mahasiswa = $_SESSION['program_studi'] ?? ''; 

// ==========================================
// QUERY 1: REKAP MATKUL
// ==========================================
// PERBAIKAN: JOIN dimulai dari sesi_absensi karena sesi_absensi yang memiliki id_kelas dan id_matkul
$query_matkul = "SELECT 
                    mk.nama_matkul, 
                    k.ruangan, 
                    COUNT(DISTINCT sa.id_sesi) as total_sesi, 
                    COUNT(DISTINCT kh.id_sesi) as total_hadir 
                 FROM sesi_absensi sa 
                 JOIN kelas k ON sa.id_kelas = k.id_kelas 
                 JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul 
                 LEFT JOIN kehadiran kh ON sa.id_sesi = kh.id_sesi AND kh.id_mahasiswa = ? 
                 WHERE k.program_studi = ? OR k.program_studi = '' 
                 GROUP BY mk.id_matkul";

$stmt_mk = mysqli_prepare($conn, $query_matkul); 
mysqli_stmt_bind_param($stmt_mk, "is", $id_mahasiswa, $prodi_mahasiswa); 
mysqli_stmt_execute($stmt_mk);

$result_mk = mysqli_stmt_get_result($stmt_mk);
$rekap_matkul = []; 
while ($row = mysqli_fetch_assoc($result_mk)) { 
    $rekap_matkul[] = $row; 
}
mysqli_stmt_close($stmt_mk); 

// ==========================================
// QUERY 2: RIWAYAT DETAIL (Sesi yang sudah Selesai)
// ==========================================
// PERBAIKAN: Struktur JOIN disesuaikan agar relasi antar tabel valid
// ==========================================
// QUERY 2: RIWAYAT DETAIL 
// ==========================================
$query_history = "SELECT 
                    sa.waktu_mulai, 
                    mk.nama_matkul, 
                    kh.timestamp_hadir, 
                    kh.keterangan 
                    FROM sesi_absensi sa 
                    JOIN kelas k ON sa.id_kelas = k.id_kelas 
                    JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul 
                    LEFT JOIN kehadiran kh ON sa.id_sesi = kh.id_sesi AND kh.id_mahasiswa = ? 
                    WHERE (k.program_studi = ? OR k.program_studi = '') 
                    AND sa.status IN ('Aktif', 'Selesai') 
                    ORDER BY sa.waktu_mulai DESC 
                    LIMIT 10";

$stmt_hist = mysqli_prepare($conn, $query_history); 
mysqli_stmt_bind_param($stmt_hist, "is", $id_mahasiswa, $prodi_mahasiswa); 
mysqli_stmt_execute($stmt_hist);

$result_hist = mysqli_stmt_get_result($stmt_hist);
$riwayat_detail = []; 
while ($row = mysqli_fetch_assoc($result_hist)) { 
    $riwayat_detail[] = $row; 
}
mysqli_stmt_close($stmt_hist);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kehadiran – SIAQR</title>
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
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; max-width: 800px; margin-right: auto; }
        .page-header h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; color: #1a1a1a; }
        .page-header p { font-size: 0.85rem; color: #6b7280; margin-bottom: 1.5rem;}
        
        .filter-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .filter-select { flex: 1; padding: 0.6rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; font-size: 0.8rem; background: #ffffff; color: #1a1a1a; outline: none; }

        .matkul-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1rem; }
        .mc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .mc-title h4 { font-size: 0.9rem; font-weight: 700; color: #1a1a1a; }
        .mc-title p { font-size: 0.75rem; color: #6b7280; margin-top: 0.2rem;}
        .mc-percentage { font-size: 0.9rem; font-weight: 800; }
        .progress-bg { background-color: #e5e7eb; height: 6px; border-radius: 3px; width: 100%; margin-bottom: 0.5rem; }
        .progress-fill { height: 100%; border-radius: 3px; transition: width 0.3s ease; }
        .mc-footer { display: flex; justify-content: space-between; font-size: 0.75rem; color: #6b7280; }

        .history-header { display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem; font-size: 0.85rem; font-weight: 700;}
        .history-header a { color: #e8670a; text-decoration: none; font-size: 0.75rem;}
        
        .history-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #e5e7eb; }
        .date-badge { background: #fff0e4; color: #e8670a; width: 45px; height: 45px; border-radius: 0.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 700; line-height: 1.1; }
        .date-badge span:first-child { font-size: 0.65rem; text-transform: uppercase; }
        .date-badge span:last-child { font-size: 1rem; }
        .hist-info { flex: 1; }
        .hist-info h5 { font-size: 0.9rem; font-weight: 700; color: #1a1a1a; margin-bottom: 0.2rem;}
        .hist-info p { font-size: 0.75rem; color: #6b7280; }
        .hist-status { font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 1rem; }
        .status-hadir { background: #dcfce7; color: #16a34a; }
        .status-absen { background: #fee2e2; color: #ef4444; }
        .status-izin { background: #fef3c7; color: #d97706; } /* Tambahan untuk status Izin/Sakit */

        /* BOTTOM NAV (MOBILE) */
        .bottom-nav { display: none; position: fixed; bottom: 0; width: 100%; background: #ffffff; border-top: 1px solid #e5e7eb; padding: 0.75rem 2rem; justify-content: space-between; z-index: 100; }
        .nav-bottom-item { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; color: #6b7280; text-decoration: none; font-size: 0.7rem; font-weight: 500; }
        .nav-bottom-item svg { width: 20px; height: 20px; }
        .nav-bottom-item.active { color: #e8670a; font-weight: 600; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1.5rem 1.5rem 6rem; background: #ffffff; min-height: 100vh; }
            .bottom-nav { display: flex; }
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
                <div class="logo-text"><span class="logo-title">Portal SIAQR</span><span class="logo-subtitle">Area Mahasiswa</span></div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a>
                <a href="riwayat_mhs.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Riwayat Kehadiran</a>
                <a href="profil_mhs.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Riwayat Presensi</h1>
                <p>Pantau kehadiran mata kuliah semester ini</p>
            </div>
            <div class="filter-row">
                <select class="filter-select"><option>Semester 4 (Genap)</option></select>
                <select class="filter-select"><option>Semua Mata Kuliah</option></select>
            </div>
            
            <?php if (empty($rekap_matkul)): ?>
                <p style="text-align: center; color: #6b7280; padding: 2rem;">Belum ada jadwal mata kuliah untuk program studi Anda.</p>
            <?php else: ?>
                <?php foreach ($rekap_matkul as $mk): 
                    $persen = $mk['total_sesi'] > 0 ? round(($mk['total_hadir'] / $mk['total_sesi']) * 100) : 0;
                    $isAman = $persen >= 75; 
                    $color = $isAman ? '#16a34a' : '#e8670a'; 
                    $status = $isAman ? 'Aman' : 'Perhatian';
                ?>
                <div class="matkul-card">
                    <div class="mc-header">
                        <div class="mc-title">
                            <h4><?= htmlspecialchars($mk['nama_matkul']) ?></h4>
                            <p><?= htmlspecialchars($mk['ruangan'] ?? 'Ruangan belum diatur') ?></p>
                        </div>
                        <span class="mc-percentage" style="color: <?= $color ?>"><?= $persen ?>%</span>
                    </div>
                    <div class="progress-bg">
                        <div class="progress-fill" style="width: <?= $persen ?>%; background-color: <?= $color ?>;"></div>
                    </div>
                    <div class="mc-footer">
                        <span>Kehadiran: <?= $mk['total_hadir'] ?>/<?= $mk['total_sesi'] ?> Sesi</span>
                        <strong style="color: <?= $color ?>; display: flex; align-items: center; gap: 4px;">
                            <?= !$isAman ? '⚠️ ' : '✅ ' ?><?= $status ?>
                        </strong>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="history-header">
                <span>Riwayat Detail</span>
                <a href="#">LIHAT SEMUA</a>
            </div>
            
            <?php if(empty($riwayat_detail)): ?>
                <p style="text-align: center; color: #6b7280; font-size: 0.85rem; padding: 2rem;">Belum ada riwayat kehadiran.</p>
            <?php else: ?>
                <?php foreach ($riwayat_detail as $hist): 
                    // Logika deteksi status (Hadir, Izin, Sakit, atau Absen)
                    if (!empty($hist['keterangan'])) {
                        $ket = strtolower($hist['keterangan']);
                        if (in_array($ket, ['izin', 'sakit'])) {
                            $status_text = ucfirst($ket);
                            $status_class = 'status-izin';
                        } else {
                            $status_text = 'Hadir';
                            $status_class = 'status-hadir';
                        }
                    } else {
                        $status_text = 'Absen'; 
                        $status_class = 'status-absen';
                    }
                ?>
                <div class="history-item">
                    <div class="date-badge">
                        <span><?= date('M', strtotime($hist['waktu_mulai'])) ?></span>
                        <span><?= date('d', strtotime($hist['waktu_mulai'])) ?></span>
                    </div>
                    <div class="hist-info">
                        <h5><?= htmlspecialchars($hist['nama_matkul']) ?></h5>
                        <p><?= $status_text ?> • <?= date('H:i', strtotime($hist['waktu_mulai'])) ?> WIB</p>
                    </div>
                    <div class="hist-status <?= $status_class ?>"><?= $status_text ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
        
        <div class="bottom-nav">
            <a href="dashboard.php" class="nav-bottom-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
            <a href="riwayat_mhs.php" class="nav-bottom-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Riwayat
            </a>
            <a href="profil_mhs.php" class="nav-bottom-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Profil
            </a>
        </div>
    </div>
</body>
</html>