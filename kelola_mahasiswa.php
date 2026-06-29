<?php
session_start();
require 'connection.php'; // Mengaktifkan koneksi ke database

// Proteksi: Wajib login & role admin (Buka komentar di bawah ini jika halaman login admin sudah siap)
/*
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
*/

$active_page = 'mahasiswa';

// --- TAMBAHAN: PROSES TAMBAH DATA MAHASISWA ---
if (isset($_POST['tambah_mahasiswa'])) {
    $nama = trim($_POST['nama']);
    $nim = trim($_POST['nim']);
    $prodi = trim($_POST['program_studi']);
    $angkatan = trim($_POST['angkatan']);
    
    // Default akun: username dan password sama dengan NIM
    $username = $nim;
    $password = $nim;
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $cek = $conn->prepare("SELECT nim FROM mahasiswa WHERE nim = ?");
    $cek->bind_param("s", $nim);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Gagal: Mahasiswa dengan NIM $nim sudah terdaftar!";
    } else {
        $stmt = $conn->prepare("INSERT INTO mahasiswa (nama, nim, program_studi, angkatan, username, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $nama, $nim, $prodi, $angkatan, $username, $hashed_password);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Data mahasiswa $nama berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Terjadi kesalahan sistem.";
        }
        $stmt->close();
    }
    $cek->close();
    
    // Refresh halaman untuk mencegah form resubmission
    header("Location: kelola_mahasiswa.php");
    exit;
}
// ----------------------------------------------

// --- TAMBAHAN FITUR EDIT: PROSES EDIT DATA MAHASISWA ---
if (isset($_POST['edit_mahasiswa'])) {
    $id_mhs = $_POST['id_mahasiswa'];
    $nama = trim($_POST['nama']);
    $nim = trim($_POST['nim']);
    $prodi = trim($_POST['program_studi']);
    $angkatan = trim($_POST['angkatan']);

    // Cek apakah NIM yang baru ini sudah dipakai oleh mahasiswa LAIN
    $cek = $conn->prepare("SELECT id_mahasiswa FROM mahasiswa WHERE nim = ? AND id_mahasiswa != ?");
    $cek->bind_param("si", $nim, $id_mhs);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Gagal: NIM $nim sudah dipakai oleh mahasiswa lain!";
    } else {
        // Update ke database
        $stmt = $conn->prepare("UPDATE mahasiswa SET nama = ?, nim = ?, program_studi = ?, angkatan = ? WHERE id_mahasiswa = ?");
        $stmt->bind_param("ssssi", $nama, $nim, $prodi, $angkatan, $id_mhs);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Data mahasiswa berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Terjadi kesalahan sistem saat memperbarui data.";
        }
        $stmt->close();
    }
    $cek->close();
    
    header("Location: kelola_mahasiswa.php");
    exit;
}
// ----------------------------------------------

// --- TAMBAHAN FITUR HAPUS: PROSES HAPUS DATA MAHASISWA ---
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM mahasiswa WHERE id_mahasiswa = ?");
    $stmt->bind_param("i", $id_hapus);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data mahasiswa berhasil dihapus permanen!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data. Pastikan tidak ada data yang terikat dengan mahasiswa ini.";
    }
    $stmt->close();
    header("Location: kelola_mahasiswa.php");
    exit;
}
// ---------------------------------------------------------

// Menarik semua data mahasiswa dari database
$query_mhs = "SELECT * FROM mahasiswa ORDER BY angkatan DESC, nama ASC";
$result_mhs = $conn->query($query_mhs);

// Menghitung total data
$total_mahasiswa = $result_mhs->num_rows;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mahasiswa - Portal SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/admin.css">
    <style>
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
        .filter-group { display: flex; gap: 0.75rem; flex: 1; min-width: 300px; }
        .search-box { position: relative; flex: 1; max-width: 300px; }
        .search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #64748b; }
        .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; outline: none; font-family: inherit; font-size: 0.9rem; }
        .search-box input:focus { border-color: #E8670A; }
        .select-filter { padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; outline: none; background: white; font-family: inherit; color: #475569; font-size: 0.9rem; cursor: pointer; }
        
        .btn-primary { background: #E8670A; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; transition: background 0.2s; }
        .btn-primary:hover { background: #C45A0A; }

        .pagination { display: flex; justify-content: space-between; align-items: center; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; margin-top: 1rem; color: #64748b; font-size: 0.875rem; }
        .page-numbers { display: flex; gap: 0.25rem; align-items: center; }
        .page-btn { width: 32px; height: 32px; display: flex; justify-content: center; align-items: center; border-radius: 0.375rem; cursor: pointer; text-decoration: none; color: #475569; font-weight: 600; transition: background 0.2s; }
        .page-btn:hover { background: #f1f5f9; }
        .page-btn.active { background: #E8670A; color: white; }

        /* --- TAMBAHAN: CSS MODAL TAMBAH DATA --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: 0.3s; backdrop-filter: blur(2px); }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-box { background: white; padding: 2rem; border-radius: 16px; width: 100%; max-width: 450px; transform: translateY(-20px); transition: 0.3s; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .modal-overlay.show .modal-box { transform: translateY(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-header h3 { color: #1E293B; font-size: 1.25rem; font-weight: 700; margin:0; }
        .modal-close { background: none; border: none; color: #64748B; cursor: pointer; padding: 0.2rem; }
        .modal-close:hover { color: #EF4444; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: #475569; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; outline: none; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus { border-color: #E8670A; box-shadow: 0 0 0 3px rgba(232,103,10,0.1); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { background: #e2e8f0; }
        .btn-submit { background: #E8670A; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background: #C45A0A; }
    </style>
</head>
<body>

    <div class="modal-overlay" id="modalTambah">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Tambah Mahasiswa</h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Contoh: Elisa Steven Tandilo" required>
                </div>
                <div class="form-group">
                    <label>NIM</label>
                    <input type="number" name="nim" placeholder="Contoh: 241011078" required>
                </div>
                <div class="form-group">
                    <label>Program Studi</label>
                    <select name="program_studi" required>
                        <option value="" disabled selected>-- Pilih Program Studi --</option>
                        <option value="Informatika">Informatika</option>
                        <option value="Sistem Informasi">Sistem Informasi</option>
                        <option value="Ilmu Komputer">Ilmu Komputer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Angkatan (Tahun)</label>
                    <input type="number" name="angkatan" value="2024" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" name="tambah_mahasiswa" class="btn-submit">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalEdit">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit Data Mahasiswa</h3>
                <button class="modal-close" onclick="closeEditModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id_mahasiswa" id="edit_id">
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" id="edit_nama" required>
                </div>
                <div class="form-group">
                    <label>NIM</label>
                    <input type="number" name="nim" id="edit_nim" required>
                </div>
                <div class="form-group">
                    <label>Program Studi</label>
                    <select name="program_studi" id="edit_prodi" required>
                        <option value="Informatika">Informatika</option>
                        <option value="Sistem Informasi">Sistem Informasi</option>
                        <option value="Ilmu Komputer">Ilmu Komputer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Angkatan (Tahun)</label>
                    <input type="number" name="angkatan" id="edit_angkatan" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Batal</button>
                    <button type="submit" name="edit_mahasiswa" class="btn-submit">Perbarui Data</button>
                </div>
            </form>
        </div>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <script>alert("<?= $_SESSION['success'] ?>");</script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <script>alert("<?= $_SESSION['error'] ?>");</script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

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

        <main class="main-content">
            <div class="content-header">
                <div class="header-left">
                    <h1>Kelola Data Mahasiswa</h1>
                    <p class="header-subtitle">Manajemen data master, kelas, dan status akademik mahasiswa</p>
                </div>
            </div>

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
                        <div class="stat-value"><?= number_format($total_mahasiswa) ?></div>
                        <div class="stat-change positive">Terdaftar di sistem</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Status Aktif</div>
                        <div class="stat-value"><?= number_format($total_mahasiswa) ?></div>
                        <div class="stat-change neutral">Tahun akademik ini</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Cuti / Non-Aktif</div>
                        <div class="stat-value">0</div>
                        <div class="stat-change neutral">Butuh validasi</div>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <div class="toolbar">
                    <div class="filter-group">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" placeholder="Cari Nama atau NIM...">
                        </div>
                        <select class="select-filter">
                            <option value="">Semua Prodi</option>
                            <option value="Informatika">Informatika</option>
                            <option value="Sistem Informasi">Sistem Informasi</option>
                            <option value="Ilmu Komputer">Ilmu Komputer</option>
                        </select>
                        <select class="select-filter">
                            <option value="">Angkatan</option>
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                        </select>
                    </div>
                    
                    <button class="btn-primary" onclick="openModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        Tambah Data
                    </button>
                </div>

                <div class="activity-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Mahasiswa</th>
                                <th>NIM</th>
                                <th>Program Studi</th>
                                <th>Angkatan</th>
                                <th>Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_mahasiswa > 0): ?>
                                <?php while($row = $result_mhs->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar"><?= strtoupper(substr($row['nama'], 0, 1)) ?></div>
                                            <div>
                                                <div class="user-name"><?= htmlspecialchars(ucwords($row['nama'])) ?></div>
                                                <div class="user-role"><?= htmlspecialchars($row['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                                    <td><?= htmlspecialchars(ucwords($row['program_studi'])) ?></td>
                                    <td><?= htmlspecialchars($row['angkatan']) ?></td>
                                    <td><span class="status-badge success">Aktif</span></td>
                                    <td style="text-align: center; display: flex; gap: 0.5rem; justify-content: center;">
                                        <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" 
                                                data-id="<?= $row['id_mahasiswa'] ?>" 
                                                data-nama="<?= htmlspecialchars($row['nama']) ?>" 
                                                data-nim="<?= htmlspecialchars($row['nim']) ?>" 
                                                data-prodi="<?= htmlspecialchars($row['program_studi']) ?>" 
                                                data-angkatan="<?= htmlspecialchars($row['angkatan']) ?>" 
                                                onclick="openEditModal(this)">
                                            Edit
                                        </button>
                                        <a href="?hapus=<?= $row['id_mahasiswa'] ?>" 
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus data <?= htmlspecialchars($row['nama']) ?>? Tindakan ini permanen!');" 
                                           class="btn btn-outline" 
                                           style="padding: 0.3rem 0.6rem; font-size: 0.8rem; color: #EF4444; border-color: #F87171; text-decoration: none;">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">Belum ada data mahasiswa di database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <span>Menampilkan <?= $total_mahasiswa ?> data mahasiswa</span>
                    <div class="page-numbers">
                        <a href="#" style="margin-right: 8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="15 18 9 12 15 6"/></svg>
                        </a>
                        <a href="#" class="page-btn active">1</a>
                        <a href="#" style="margin-left: 8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        const modal = document.getElementById('modalTambah');
        const modalEdit = document.getElementById('modalEdit');

        function openModal() {
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        function openEditModal(btn) {
            const id = btn.getAttribute('data-id');
            const nama = btn.getAttribute('data-nama');
            const nim = btn.getAttribute('data-nim');
            const prodi = btn.getAttribute('data-prodi');
            const angkatan = btn.getAttribute('data-angkatan');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_nim').value = nim;
            document.getElementById('edit_angkatan').value = angkatan;

            const selectProdi = document.getElementById('edit_prodi');
            for(let i = 0; i < selectProdi.options.length; i++) {
                if(selectProdi.options[i].value.toLowerCase() === prodi.toLowerCase()) {
                    selectProdi.selectedIndex = i;
                    break;
                }
            }

            modalEdit.classList.add('show');
        }

        function closeEditModal() {
            modalEdit.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == modalEdit) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>