<?php
session_start();
include 'connection.php';

// Proteksi: Wajib Login
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') {
    header('Location: login.php');
    exit;
}

$id_user = $_SESSION['id_user'];
$name    = $_SESSION['nama'];
$nim     = $_SESSION['nim'];

// 1. Hitung Statistik Kehadiran dari Database
// Hitung total kehadiran
$sql_total = "SELECT COUNT(*) as total FROM kehadiran WHERE id_mahasiswa='$id_user'";
$res_total = mysqli_query($conn, $sql_total);
$data_total = mysqli_fetch_assoc($res_total);
$total_pertemuan = $data_total['total'];

// Hitung yang statusnya 'Hadir'
$sql_hadir = "SELECT COUNT(*) as hadir FROM kehadiran WHERE id_mahasiswa='$id_user' AND keterangan='Hadir'";
$res_hadir = mysqli_query($conn, $sql_hadir);
$data_hadir = mysqli_fetch_assoc($res_hadir);
$total_hadir = $data_hadir['hadir'];

// Hitung Persentase
$attendance_percent = ($total_pertemuan > 0) ? round(($total_hadir / $total_pertemuan) * 100) : 0;
$sisa_pekan = max(0, 16 - $total_pertemuan); // Asumsi 16 pekan

// 2. Data Jadwal (Masih statis/contoh karena tabel sesi belum lengkap relasinya)
// Anda bisa mengganti ini dengan query database nanti jika tabel sesi sudah lengkap
$jadwal_hari_ini = [
    [
        'status' => 'berlangsung',
        'matkul' => 'Pemrograman Web',
        'jam'    => '08:00 - 10:30',
        'ruang'  => 'Lab-203',
        'icon'   => 'book',
    ],
    [
        'status' => 'upcoming',
        'matkul' => 'Kecerdasan Buatan',
        'jam'    => '13:00',
        'ruang'  => 'LT-301',
        'icon'   => 'cpu',
    ],
];

$active_page = 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAQR – Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/dashboard.css">
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- QR Modal -->
<div class="modal-overlay" id="qrModal">
    <div class="modal-box">
        <div class="modal-title">Scan QR Absensi</div>
        <p class="modal-sub">Arahkan kamera ke kode QR yang ditampilkan dosen</p>
        <div class="modal-qr-frame">
            <svg width="80" height="80" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" opacity="0.4">
                <rect x="4" y="4" width="14" height="14" rx="2" fill="#E8670A"/>
                <rect x="26" y="4" width="14" height="14" rx="2" fill="#E8670A"/>
                <rect x="4" y="26" width="14" height="14" rx="2" fill="#E8670A"/>
                <rect x="26" y="26" width="6" height="6" rx="1" fill="#E8670A"/>
                <rect x="34" y="26" width="6" height="6" rx="1" fill="#E8670A"/>
                <rect x="26" y="34" width="6" height="6" rx="1" fill="#E8670A"/>
                <rect x="7" y="7" width="8" height="8" rx="1" fill="#FFF0E4"/>
                <rect x="29" y="7" width="8" height="8" rx="1" fill="#FFF0E4"/>
                <rect x="7" y="29" width="8" height="8" rx="1" fill="#FFF0E4"/>
            </svg>
        </div>
        <button class="modal-close" onclick="closeQrModal()">Tutup</button>
    </div>
</div>

<div class="app-shell">

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
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
            <span class="logo-text">SIAQR</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Menu Utama</div>
            <a href="dashboard.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                Jadwal Kuliah
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Riwayat Absensi
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Kalender
            </a>
            <div class="nav-section-title" style="margin-top:1rem;">Lainnya</div>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profil
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M5.34 18.66l-1.41 1.41M2 12H4M20 12h2M5.34 5.34L3.93 3.93M18.66 18.66l1.41 1.41M12 2v2M12 20v2"/></svg>
                Pengaturan
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="user-card">
                <div class="user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($name) ?></div>
                    <div class="user-nim">NIM: <?= htmlspecialchars($nim) ?></div>
                </div>
                <a href="logout.php" class="logout-btn" title="Keluar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- ─── MAIN ─── -->
    <div class="main-area">

        <!-- Topbar -->
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <button class="icon-btn hamburger" onclick="toggleSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <span class="topbar-title">Dashboard</span>
            </div>
            <div class="topbar-actions">
                <div class="notif-wrap">
                    <button class="icon-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </button>
                    <div class="notif-dot"></div>
                </div>
                <button class="icon-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </button>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">

            <!-- Greeting -->
            <div class="greeting">
                <h1>Halo, <?= htmlspecialchars($name) ?></h1>
                <p class="nim-tag">NIM: <?= htmlspecialchars($nim) ?></p>
            </div>

            <!-- QR Card -->
            <div class="qr-card" onclick="openQrModal()">
                <div class="qr-icon-wrap">
                    <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="4" width="14" height="14" rx="2" fill="white"/>
                        <rect x="26" y="4" width="14" height="14" rx="2" fill="white"/>
                        <rect x="4" y="26" width="14" height="14" rx="2" fill="white"/>
                        <rect x="26" y="26" width="6" height="6" rx="1" fill="white"/>
                        <rect x="34" y="26" width="6" height="6" rx="1" fill="white"/>
                        <rect x="26" y="34" width="6" height="6" rx="1" fill="white"/>
                        <rect x="7" y="7" width="8" height="8" rx="1" fill="rgba(255,255,255,0.3)"/>
                        <rect x="29" y="7" width="8" height="8" rx="1" fill="rgba(255,255,255,0.3)"/>
                        <rect x="7" y="29" width="8" height="8" rx="1" fill="rgba(255,255,255,0.3)"/>
                    </svg>
                </div>
                <h2>Scan QR Absensi</h2>
                <p>Ketuk untuk mulai memindai</p>
            </div>

            <!-- Attendance Stats -->
            <div class="stats-card">
                <div class="stats-header">
                    <span class="stats-label">Statistik Kehadiran</span>
                    <span class="stats-pct"><?= $attendance_percent ?>%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: <?= $attendance_percent ?>%"></div>
                </div>
                <div class="stats-footer">
                    <span>Rata-rata Semester ini</span>
                    <span class="status-badge">Tepat Waktu</span>
                </div>
            </div>

            <!-- Schedule -->
            <div class="section-header">
                <span class="section-title">Jadwal Hari Ini</span>
                <a href="#" class="see-all">Lihat Semua</a>
            </div>

            <div class="schedule-list">
                <?php foreach ($jadwal_hari_ini as $j): ?>
                <?php if ($j['status'] === 'berlangsung'): ?>
                <div class="schedule-card active-class">
                    <div class="scard-top">
                        <div class="scard-left">
                            <div class="scard-badge">
                                <div class="dot"></div>
                                Sedang Berlangsung
                            </div>
                            <div class="scard-title"><?= htmlspecialchars($j['matkul']) ?></div>
                        </div>
                        <div class="scard-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        </div>
                    </div>
                    <div class="scard-divider"></div>
                    <div class="scard-meta">
                        <div class="scard-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <div>
                                <div class="meta-label">JAM</div>
                                <strong><?= htmlspecialchars($j['jam']) ?></strong>
                            </div>
                        </div>
                        <div class="scard-meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <div>
                                <div class="meta-label">RUANG</div>
                                <strong><?= htmlspecialchars($j['ruang']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="schedule-card-simple">
                    <div class="simple-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </div>
                    <div class="simple-info">
                        <div class="simple-title"><?= htmlspecialchars($j['matkul']) ?></div>
                        <div class="simple-sub"><?= htmlspecialchars($j['jam']) ?> &bull; <?= htmlspecialchars($j['ruang']) ?></div>
                    </div>
                    <div class="simple-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>

                <!-- Total Kehadiran -->
                <div class="attendance-total">
                    <div class="donut-wrap">
                        <svg viewBox="0 0 52 52">
                            <circle class="donut-bg" cx="26" cy="26" r="20"/>
                            <circle class="donut-fill" cx="26" cy="26" r="20"/>
                        </svg>
                        <div class="donut-label"><?= $total_hadir ?>/<?= $total_pertemuan ?></div>
                    </div>
                    <div class="att-info">
                        <div class="att-title">Total Kehadiran</div>
                        <div class="att-sub"><?= $sisa_pekan ?> pertemuan tersisa pekan ini</div>
                    </div>
                </div>
            </div>

        </div><!-- /page-content -->
    </div><!-- /main-area -->

</div><!-- /app-shell -->

<!-- ─── BOTTOM NAV (mobile) ─── -->
<nav class="bottom-nav">
    <div class="bnav-inner">
        <a href="dashboard.php" class="bnav-item active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>
        <a href="#" class="bnav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Riwayat
        </a>
        <a href="#" class="bnav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profil
        </a>
    </div>
</nav>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
function openQrModal() {
    document.getElementById('qrModal').classList.add('show');
}
function closeQrModal() {
    document.getElementById('qrModal').classList.remove('show');
}
document.getElementById('qrModal').addEventListener('click', function(e) {
    if (e.target === this) closeQrModal();
});
</script>
</body>
</html>
