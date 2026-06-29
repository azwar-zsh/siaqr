<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya dosen yang boleh akses
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'dosen') {
    header('Location: index.php');
    exit;
}

$id_dosen = $_SESSION['id_user'] ?? 0;
$nama_dosen = $_SESSION['nama'] ?? 'Dosen';

// Ambil data mata kuliah yang diajar dosen
$query_matkul = "SELECT DISTINCT mk.id_matkul, mk.kode_matkul, mk.nama_matkul 
                 FROM mata_kuliah mk
                 JOIN kelas k ON mk.id_matkul = k.id_matkul
                 WHERE k.id_dosen = ? OR mk.id_matkul IN (
                     SELECT id_matkul FROM sesi_absensi WHERE id_dosen = ?
                 )";
$stmt = mysqli_prepare($conn, $query_matkul);
mysqli_stmt_bind_param($stmt, "ii", $id_dosen, $id_dosen);
mysqli_stmt_execute($stmt);
$result_matkul = mysqli_stmt_get_result($stmt);
$matkul_list = [];
while ($row = mysqli_fetch_assoc($result_matkul)) {
    $matkul_list[] = $row;
}

// Ambil data kelas beserta jumlah mahasiswanya (Diubah agar dinamis)
$query_kelas = "SELECT k.id_kelas, k.nama_kelas, k.tahun_akademik, 
                       (SELECT COUNT(*) FROM mahasiswa m WHERE m.program_studi = k.program_studi) as total_mhs
                FROM kelas k 
                WHERE k.id_dosen = ? OR k.id_dosen IS NULL
                ORDER BY k.nama_kelas";
$stmt = mysqli_prepare($conn, $query_kelas);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$result_kelas = mysqli_stmt_get_result($stmt);
$kelas_list = [];
$kelas_mhs_count = []; // Menyimpan data jumlah mahasiswa per kelas untuk Javascript
while ($row = mysqli_fetch_assoc($result_kelas)) {
    $kelas_list[] = $row;
    $kelas_mhs_count[$row['id_kelas']] = (int)$row['total_mhs'];
}

// Proses simpan sesi baru
$pesan = '';
$sesi_baru = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buat_sesi') {
    $id_matkul = (int)$_POST['id_matkul'];
    $id_kelas = (int)$_POST['id_kelas'];
    $pertemuan_ke = (int)$_POST['pertemuan_ke'];
    $durasi = (int)$_POST['durasi'];
    
    // Generate token unik untuk QR Code
    $token = bin2hex(random_bytes(16));
    
    // Generate kode manual (6 karakter acak kombinasi huruf dan angka)
    $kode_manual = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));
    
    // Waktu mulai dan selesai
    $waktu_mulai = date('Y-m-d H:i:s');
    $waktu_selesai = date('Y-m-d H:i:s', strtotime("+{$durasi} minutes"));
    
    // Insert sesi baru
    $query_insert = "INSERT INTO sesi_absensi 
                     (id_dosen, id_matkul, id_kelas, pertemuan_ke, waktu_mulai, waktu_selesai, 
                      durasi, qr_code_token, kode_manual, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif')";
    
    $stmt = mysqli_prepare($conn, $query_insert);
    mysqli_stmt_bind_param($stmt, "iiiisssss", 
        $id_dosen, $id_matkul, $id_kelas, $pertemuan_ke, 
        $waktu_mulai, $waktu_selesai, $durasi, $token, $kode_manual
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $id_sesi_baru = mysqli_insert_id($conn);
        
        // Ambil info sesi yang baru dibuat
        $query_sesi = "SELECT sa.*, mk.nama_matkul, k.nama_kelas,
                       (SELECT COUNT(*) FROM mahasiswa m 
                        JOIN kelas k2 ON m.program_studi = k2.program_studi 
                        WHERE k2.id_kelas = sa.id_kelas) as total_mahasiswa,
                       0 as jumlah_hadir
                       FROM sesi_absensi sa
                       JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
                       JOIN kelas k ON sa.id_kelas = k.id_kelas
                       WHERE sa.id_sesi = ?";
        
        $stmt = mysqli_prepare($conn, $query_sesi);
        mysqli_stmt_bind_param($stmt, "i", $id_sesi_baru);
        mysqli_stmt_execute($stmt);
        $sesi_baru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $sesi_baru['qr_token'] = $token;
    } else {
        $pesan = "Error: " . mysqli_error($conn);
    }
}

// Ambil data sesi yang sedang aktif
$query_sesi_aktif = "SELECT sa.*, mk.nama_matkul, k.nama_kelas,
                    (SELECT COUNT(*) FROM kehadiran k 
                     WHERE k.id_sesi = sa.id_sesi) as jumlah_hadir,
                    (SELECT COUNT(*) FROM mahasiswa m 
                     JOIN kelas k2 ON m.program_studi = k2.program_studi 
                     WHERE k2.id_kelas = sa.id_kelas) as total_mahasiswa
                    FROM sesi_absensi sa
                    JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
                    JOIN kelas k ON sa.id_kelas = k.id_kelas
                    WHERE sa.id_dosen = ? AND sa.status = 'Aktif'
                    ORDER BY sa.waktu_mulai DESC
                    LIMIT 1";

$stmt = mysqli_prepare($conn, $query_sesi_aktif);
mysqli_stmt_bind_param($stmt, "i", $id_dosen);
mysqli_stmt_execute($stmt);
$sesi_aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Jika ada sesi aktif, ambil QR token-nya dan kode manual
if ($sesi_aktif) {
    $query_token = "SELECT qr_code_token, kode_manual FROM sesi_absensi WHERE id_sesi = ?";
    $stmt = mysqli_prepare($conn, $query_token);
    mysqli_stmt_bind_param($stmt, "i", $sesi_aktif['id_sesi']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $sesi_aktif['qr_token'] = $row['qr_code_token'];
    $sesi_aktif['kode_manual'] = $row['kode_manual'];
}

$active_page = 'buat_sesi';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Sesi Absensi - Portal SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }
        .page-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        /* Layout Grid */
        .session-layout {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 2rem;
            align-items: start;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
        }
        .form-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #e8670a;
        }
        .form-card .desc {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #e8670a;
            box-shadow: 0 0 0 3px rgba(232, 103, 10, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn-generate {
            width: 100%;
            padding: 1rem;
            background: #e8670a;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-generate:hover {
            background: #c45a0a;
            transform: translateY(-1px);
        }
        .btn-generate:active {
            transform: translateY(0);
        }
        
        /* QR Display Card */
        .qr-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #dcfce7;
            color: #16a34a;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .qr-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .qr-container {
            background: #fffbeb;
            border: 2px dashed #fbbf24;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 280px;
            position: relative;
        }
        .timer-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: monospace;
        }
        #qrcode {
            display: flex;
            justify-content: center;
        }
        #qrcode img {
            max-width: 100%;
            height: auto;
        }
        .session-info {
            background: #f9fafb;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .session-info h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }
        .session-info p {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        /* Progress Mahasiswa */
        .attendance-progress {
            background: #f0f9ff;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .progress-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a1a1a;
        }
        .progress-count {
            font-size: 0.875rem;
            font-weight: 700;
            color: #e8670a;
        }
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .progress-fill {
            height: 100%;
            background: #e8670a;
            border-radius: 9999px;
            transition: width 0.3s;
        }
        .progress-note {
            font-size: 0.75rem;
            color: #6b7280;
            text-align: center;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .btn-action {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-action:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        .btn-action svg {
            width: 16px;
            height: 16px;
        }
        .btn-end {
            grid-column: 1 / -1;
            background: #ef4444;
            color: white;
            border: none;
            padding: 1rem;
        }
        .btn-end:hover {
            background: #dc2626;
        }
        
        /* Total Mahasiswa Card */
        .total-mhs-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.5rem;
        }
        .total-mhs-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .total-mhs-icon {
            width: 40px;
            height: 40px;
            background: #fff0e4;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e8670a;
        }
        .total-mhs-icon svg {
            width: 20px;
            height: 20px;
        }
        .total-mhs-text {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .total-mhs-text strong {
            display: block;
            color: #1a1a1a;
            font-weight: 600;
        }
        .total-mhs-arrow {
            color: #9ca3af;
        }
        
        /* Placeholder State */
        .placeholder-state {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
        }
        .placeholder-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
        }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .session-layout { grid-template-columns: 1fr; }
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

        <main class="main-content">
            <div class="page-header">
                <h1>Buat Sesi Absensi</h1>
                <p>Konfigurasi detail sesi perkuliahan untuk generate QR Code kehadiran</p>
            </div>

            <?php if ($pesan): ?>
            <div class="alert alert-error"><?= htmlspecialchars($pesan) ?></div>
            <?php endif; ?>

            <div class="session-layout">
                <div class="form-card">
                    <h2>Buat Sesi Absensi</h2>
                    <p class="desc">Konfigurasi detail sesi perkuliahan untuk generate QR Code kehadiran.</p>
                    
                    <form method="POST" id="formSesi">
                        <input type="hidden" name="action" value="buat_sesi">
                        
                        <div class="form-group">
                            <label class="form-label">Mata Kuliah</label>
                            <select name="id_matkul" class="form-control" required>
                                <option value="">Pilih Mata Kuliah</option>
                                <?php foreach ($matkul_list as $mk): ?>
                                <option value="<?= $mk['id_matkul'] ?>">
                                    <?= htmlspecialchars($mk['nama_matkul']) ?> (<?= htmlspecialchars($mk['kode_matkul']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Kelas</label>
                                <select name="id_kelas" class="form-control" required>
                                    <option value="">Pilih Kelas</option>
                                    <?php foreach ($kelas_list as $k): ?>
                                    <option value="<?= $k['id_kelas'] ?>">
                                        <?= htmlspecialchars($k['nama_kelas']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Pertemuan Ke-</label>
                                <input type="number" name="pertemuan_ke" class="form-control" 
                                       min="1" max="16" value="1" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Durasi Aktif (Menit)</label>
                            <input type="number" name="durasi" class="form-control" 
                                   min="1" max="120" value="15" required>
                            <small style="color: #6b7280; font-size: 0.75rem;">
                                QR Code akan aktif selama durasi yang ditentukan
                            </small>
                        </div>

                        <button type="submit" class="btn-generate">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            Generate QR Code
                        </button>
                    </form>

                    <div class="total-mhs-card">
                        <div class="total-mhs-left">
                            <div class="total-mhs-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <div class="total-mhs-text">
                                Total Mahasiswa
                                <strong id="totalMahasiswa">- Mahasiswa</strong>
                            </div>
                        </div>
                        <div class="total-mhs-arrow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </div>
                    </div>

                    <script>
                        const dataTotalMhs = <?= json_encode($kelas_mhs_count) ?>;
                        
                        document.querySelector('select[name="id_kelas"]').addEventListener('change', function() {
                            const idKelas = this.value;
                            const labelTotal = document.getElementById('totalMahasiswa');
                            
                            if (idKelas && dataTotalMhs[idKelas] !== undefined) {
                                labelTotal.textContent = dataTotalMhs[idKelas] + ' Mahasiswa';
                            } else {
                                labelTotal.textContent = '- Mahasiswa';
                            }
                        });
                    </script>
                </div>

                <div class="qr-card">
                    <?php if ($sesi_aktif || $sesi_baru): ?>
                        <?php 
                        $sesi = $sesi_baru ?? $sesi_aktif;
                        $waktu_selesai = strtotime($sesi['waktu_selesai']);
                        $waktu_sekarang = time();
                        $sisa_detik = max(0, $waktu_selesai - $waktu_sekarang);
                        ?>
                        
                        <span class="status-badge">⬤ SESI AKTIF</span>
                        <div class="qr-label">Scan untuk Presensi</div>
                        
                        <div class="qr-container">
                            <div class="timer-badge" id="timerBadge">
                                <?= gmdate('H:i:s', $sisa_detik) ?>
                            </div>
                            <div id="qrcode"></div>
                        </div>

                        <div class="manual-code-display" style="text-align: center; margin-top: -0.5rem; margin-bottom: 1.5rem;">
                            <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">Atau gunakan kode manual berikut:</p>
                            <div style="background: #f3f4f6; padding: 0.5rem 1.5rem; border-radius: 0.5rem; display: inline-block; font-size: 1.5rem; font-weight: 800; letter-spacing: 0.25rem; color: #e8670a; border: 1px dashed #d1d5db;">
                                <?= htmlspecialchars($sesi['kode_manual'] ?? strtoupper(substr($sesi['qr_token'], 0, 6))) ?>
                            </div>
                        </div>

                        <div class="session-info">
                            <h3><?= htmlspecialchars($sesi['nama_matkul']) ?></h3>
                            <p>Kelas <?= htmlspecialchars($sesi['nama_kelas']) ?> • Pertemuan Ke-<?= $sesi['pertemuan_ke'] ?></p>
                        </div>

                        <div class="attendance-progress">
                            <div class="progress-header">
                                <span class="progress-title">Mahasiswa Hadir</span>
                                <span class="progress-count">
                                    <?= $sesi['jumlah_hadir'] ?? 0 ?> / <?= $sesi['total_mahasiswa'] ?>
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" 
                                     style="width: <?= $sesi['total_mahasiswa'] > 0 ? round((($sesi['jumlah_hadir'] ?? 0) / $sesi['total_mahasiswa']) * 100) : 0 ?>%"></div>
                            </div>
                            <div class="progress-note">Update otomatis setiap 5 detik</div>
                        </div>

                        <div class="action-buttons">
                            <button class="btn-action" onclick="toggleFullscreen()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                                </svg>
                                Fullscreen
                            </button>
                            <button class="btn-action" onclick="refreshQR()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10"/>
                                    <polyline points="1 20 1 14 7 14"/>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                </svg>
                                Refresh QR
                            </button>
                            <button class="btn-action btn-end" onclick="akhiriSesi(<?= $sesi['id_sesi'] ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="8" x2="12" y2="16"/>
                                    <line x1="8" y1="12" x2="16" y2="12"/>
                                </svg>
                                Akhiri Sesi Absensi
                            </button>
                        </div>

                        <script>
                            // Generate QR Code
                            const qrToken = '<?= $sesi['qr_token'] ?>';
                            const sessionId = <?= $sesi['id_sesi'] ?>;
                            const waktuSelesai = <?= $waktu_selesai ?> * 1000;
                            
                            // URL untuk scan QR (sesuaikan dengan domain Anda)
                            const scanURL = window.location.origin + '/scan.php?token=' + qrToken;
                            
                            new QRCode(document.getElementById("qrcode"), {
                                text: scanURL,
                                width: 200,
                                height: 200,
                                colorDark : "#000000",
                                colorLight : "#ffffff",
                                correctLevel : QRCode.CorrectLevel.H
                            });

                            // Timer Countdown
                            let timerInterval;
                            let sessionEnded = false;

                            function updateTimer() {
                                if (sessionEnded) return; // Stop jika sesi sudah berakhir
                                
                                const now = Date.now();
                                const remaining = Math.max(0, waktuSelesai - now);
                                const hours = Math.floor(remaining / 3600000);
                                const minutes = Math.floor((remaining % 3600000) / 60000);
                                const seconds = Math.floor((remaining % 60000) / 1000);
                                
                                const timerBadge = document.getElementById('timerBadge');
                                if (timerBadge) {
                                    timerBadge.textContent = 
                                        String(hours).padStart(2, '0') + ':' + 
                                        String(minutes).padStart(2, '0') + ':' + 
                                        String(seconds).padStart(2, '0');
                                }
                                
                                // Jika waktu habis
                                if (remaining <= 0 && !sessionEnded) {
                                    sessionEnded = true;
                                    clearInterval(timerInterval);
                                    endSessionDisplay();
                                }
                            }

                            // Tampilkan pesan sesi berakhir & hilangkan QR Code
                            function endSessionDisplay() {
                                // Update status di database
                                fetch('api/akhiri_sesi.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({id_sesi: sessionId})
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Hilangkan QR Code dan tampilkan pesan
                                        const qrContainer = document.querySelector('.qr-container');
                                        if (qrContainer) {
                                            qrContainer.innerHTML = `
                                                <div style="text-align: center; padding: 2rem;">
                                                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">⏰</div>
                                                    <h3 style="color: #ef4444; margin-bottom: 0.5rem;">Sesi Absensi Berakhir</h3>
                                                    <p style="color: #6b7280; margin-bottom: 1.5rem;">Waktu absensi telah habis</p>
                                                </div>
                                            `;
                                        }
                                        
                                        // Hilangkan kode manual
                                        const manualCodeDisplay = document.querySelector('.manual-code-display');
                                        if (manualCodeDisplay) {
                                            manualCodeDisplay.style.display = 'none';
                                        }

                                        // Update status badge
                                        const statusBadge = document.querySelector('.status-badge');
                                        if (statusBadge) {
                                            statusBadge.textContent = '⏹ SESI SELESAI';
                                            statusBadge.style.background = '#fee2e2';
                                            statusBadge.style.color = '#ef4444';
                                        }
                                        
                                        // Disable tombol refresh & fullscreen, tapi enable generate button di form
                                        document.querySelectorAll('.btn-action').forEach(btn => {
                                            btn.disabled = true;
                                            btn.style.opacity = '0.5';
                                            btn.style.cursor = 'not-allowed';
                                        });
                                        
                                        // Hilangkan timer badge
                                        const timerBadge = document.getElementById('timerBadge');
                                        if (timerBadge) {
                                            timerBadge.style.display = 'none';
                                        }
                                        
                                        // Enable kembali form untuk sesi baru
                                        const form = document.getElementById('formSesi');
                                        if (form) {
                                            const submitBtn = form.querySelector('button[type="submit"]');
                                            if (submitBtn) {
                                                submitBtn.disabled = false;
                                                submitBtn.style.opacity = '1';
                                                submitBtn.style.cursor = 'pointer';
                                            }
                                        }
                                        
                                        alert('Sesi absensi telah berakhir. Anda dapat membuat sesi baru.');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error ending session:', error);
                                });
                            }

                            // Start timer
                            timerInterval = setInterval(updateTimer, 1000);
                            updateTimer(); // Call immediately

                            // Auto refresh kehadiran setiap 5 detik (hanya jika sesi masih aktif)
                            function updateKehadiran() {
                                if (sessionEnded) return;
                                
                                fetch('api/get_kehadiran.php?id_sesi=' + sessionId)
                                    .then(response => response.json())
                                    .then(data => {
                                        const progressCount = document.querySelector('.progress-count');
                                        const progressFill = document.querySelector('.progress-fill');
                                        
                                        if (progressCount) {
                                            progressCount.textContent = data.jumlah_hadir + ' / ' + data.total_mahasiswa;
                                        }
                                        if (progressFill) {
                                            const percent = data.total_mahasiswa > 0 ? 
                                                (data.jumlah_hadir / data.total_mahasiswa * 100) : 0;
                                            progressFill.style.width = percent + '%';
                                        }
                                    })
                                    .catch(error => console.error('Error updating kehadiran:', error));
                            }
                            setInterval(updateKehadiran, 5000);

                            // Fullscreen
                            function toggleFullscreen() {
                                if (!document.fullscreenElement) {
                                    document.documentElement.requestFullscreen();
                                } else {
                                    document.exitFullscreen();
                                }
                            }

                            // Refresh QR
                            function refreshQR() {
                                if (confirm('Generate QR Code baru? Token sebelumnya akan tetap aktif.')) {
                                    document.getElementById('qrcode').innerHTML = '';
                                    setTimeout(() => {
                                        new QRCode(document.getElementById("qrcode"), {
                                            text: scanURL,
                                            width: 200,
                                            height: 200,
                                            colorDark : "#000000",
                                            colorLight : "#ffffff",
                                            correctLevel : QRCode.CorrectLevel.H
                                        });
                                    }, 100);
                                    const btn = event.target.closest('button');
                                    const originalHTML = btn.innerHTML;
                                    btn.innerHTML = '⟳ Memuat...';
                                    setTimeout(() => {
                                        btn.innerHTML = originalHTML;
                                    }, 1000);
                                }
                            }

                            // Akhiri Sesi
                            function akhiriSesi(idSesi) {
                                if (confirm('Yakin ingin mengakhiri sesi absensi ini?')) {
                                    fetch('api/akhiri_sesi.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json'},
                                        body: JSON.stringify({id_sesi: idSesi})
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert('Sesi berhasil diakhiri');
                                            window.location.href = 'dashboard_dosen.php';
                                        } else {
                                            alert('Error: ' + data.message);
                                        }
                                    });
                                }
                            }
                        </script>
                    <?php else: ?>
                        <div class="placeholder-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            <p>QR Code akan muncul setelah Anda membuat sesi</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>