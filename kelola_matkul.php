<?php
session_start();
require 'connection.php'; // Pastikan koneksi database terhubung

// Proteksi: Wajib login & role admin (Buka komentar di bawah ini jika halaman login admin sudah siap)
/*
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
*/

$active_page = 'matkul';

// --- PROSES TAMBAH MATA KULIAH ---
if (isset($_POST['tambah_matkul'])) {
    $kode = trim($_POST['kode_matkul']);
    $nama = trim($_POST['nama_matkul']);
    $sks = trim($_POST['sks']);
    $semester = trim($_POST['semester']);

    // Cek apakah Kode MK sudah ada
    $cek = $conn->prepare("SELECT kode_matkul FROM mata_kuliah WHERE kode_matkul = ?");
    $cek->bind_param("s", $kode);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Gagal: Kode Mata Kuliah $kode sudah terdaftar!";
    } else {
        $stmt = $conn->prepare("INSERT INTO mata_kuliah (kode_matkul, nama_matkul, sks, semester) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $kode, $nama, $sks, $semester);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Data Mata Kuliah berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Terjadi kesalahan sistem.";
        }
        $stmt->close();
    }
    $cek->close();
    header("Location: kelola_matkul.php");
    exit;
}

// --- PROSES EDIT MATA KULIAH ---
if (isset($_POST['edit_matkul'])) {
    $id = $_POST['id_matkul'];
    $kode = trim($_POST['kode_matkul']);
    $nama = trim($_POST['nama_matkul']);
    $sks = trim($_POST['sks']);
    $semester = trim($_POST['semester']);
    
    // Cek duplikasi kode MK pada data lain
    $cek = $conn->prepare("SELECT id_matkul FROM mata_kuliah WHERE kode_matkul = ? AND id_matkul != ?");
    $cek->bind_param("si", $kode, $id);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Gagal: Kode Mata Kuliah $kode sudah dipakai oleh mata kuliah lain!";
    } else {
        $stmt = $conn->prepare("UPDATE mata_kuliah SET kode_matkul = ?, nama_matkul = ?, sks = ?, semester = ? WHERE id_matkul = ?");
        $stmt->bind_param("ssiii", $kode, $nama, $sks, $semester, $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Data Mata Kuliah berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Terjadi kesalahan saat memperbarui data.";
        }
        $stmt->close();
    }
    $cek->close();
    header("Location: kelola_matkul.php");
    exit;
}

// --- PROSES HAPUS MATA KULIAH ---
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $stmt = $conn->prepare("DELETE FROM mata_kuliah WHERE id_matkul = ?");
    $stmt->bind_param("i", $id_hapus);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data Mata Kuliah berhasil dihapus permanen!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data. Mata kuliah mungkin sedang digunakan di kelas/sesi.";
    }
    $stmt->close();
    header("Location: kelola_matkul.php");
    exit;
}

// --- AMBIL DATA DARI DATABASE ---
$query_matkul = "SELECT * FROM mata_kuliah ORDER BY semester ASC, kode_matkul ASC";
$result_matkul = $conn->query($query_matkul);
$total_matkul = $result_matkul->num_rows;

// Hitung total semester unik
$query_semester = "SELECT COUNT(DISTINCT semester) as total_sem FROM mata_kuliah";
$res_sem = $conn->query($query_semester);
$total_semester = $res_sem->fetch_assoc()['total_sem'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Mata Kuliah - Portal SIAQR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/admin.css">
<style>
    /* Custom Styles for Mata Kuliah Page */
    .badge-semester { background: #f1f5f9; color: #64748b; font-size: 0.75rem; padding: 2px 8px; border-radius: 99px; font-weight: 600; }
    .badge-status { font-size: 0.75rem; padding: 4px 10px; border-radius: 99px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
    .badge-status.wajib { background: #dcfce7; color: #166534; }
    .badge-status.wajib::before { content:''; width:6px; height:6px; background:#166534; border-radius:50%; }
    
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
    .filter-group { display: flex; gap: 0.75rem; flex: 1; min-width: 300px; }
    .search-box { position: relative; flex: 1; max-width: 400px; }
    .search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #64748b; }
    .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; outline: none; font-family: inherit; font-size: 0.9rem; }
    .search-box input:focus { border-color: #E8670A; }

    .btn-primary { background: #E8670A; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; transition: background 0.2s; }
    .btn-primary:hover { background: #C45A0A; }

    .pagination { display: flex; justify-content: space-between; align-items: center; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; margin-top: 1rem; color: #64748b; font-size: 0.875rem; }
    .page-numbers { display: flex; gap: 0.25rem; align-items: center; }
    .page-btn { width: 32px; height: 32px; display: flex; justify-content: center; align-items: center; border-radius: 0.375rem; cursor: pointer; text-decoration: none; color: #475569; font-weight: 600; transition: background 0.2s; }
    .page-btn:hover { background: #f1f5f9; }
    .page-btn.active { background: #E8670A; color: white; }

    /* Modal Styles (Copied from kelola_mahasiswa for consistency) */
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

<!-- Modal Tambah -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Tambah Mata Kuliah</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Kode Mata Kuliah</label>
                <input type="text" name="kode_matkul" placeholder="Contoh: IF502" required>
            </div>
            <div class="form-group">
                <label>Nama Mata Kuliah</label>
                <input type="text" name="nama_matkul" placeholder="Contoh: Pemrograman Web" required>
            </div>
            <div class="form-group" style="display:flex; gap:1rem;">
                <div style="flex:1">
                    <label>SKS</label>
                    <input type="number" name="sks" placeholder="3" min="1" max="5" required>
                </div>
                <div style="flex:1">
                    <label>Semester</label>
                    <input type="number" name="semester" placeholder="5" min="1" max="14" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                <button type="submit" name="tambah_matkul" class="btn-submit">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Data Mata Kuliah</h3>
            <button class="modal-close" onclick="closeEditModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_matkul" id="edit_id">
            <div class="form-group">
                <label>Kode Mata Kuliah</label>
                <input type="text" name="kode_matkul" id="edit_kode" required>
            </div>
            <div class="form-group">
                <label>Nama Mata Kuliah</label>
                <input type="text" name="nama_matkul" id="edit_nama" required>
            </div>
            <div class="form-group" style="display:flex; gap:1rem;">
                <div style="flex:1">
                    <label>SKS</label>
                    <input type="number" name="sks" id="edit_sks" min="1" max="5" required>
                </div>
                <div style="flex:1">
                    <label>Semester</label>
                    <input type="number" name="semester" id="edit_semester" min="1" max="14" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Batal</button>
                <button type="submit" name="edit_matkul" class="btn-submit">Perbarui Data</button>
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
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <div class="header-left">
                <h1>Kelola Mata Kuliah</h1>
                <p class="header-subtitle">Administrasi kurikulum dan daftar mata kuliah per semester.</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Mata Kuliah</div>
                    <div class="stat-value"><?= number_format($total_matkul) ?></div>
                    <div class="stat-change positive">Terdaftar di sistem</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Semester</div>
                    <div class="stat-value"><?= number_format($total_semester) ?></div>
                    <div class="stat-change neutral">Aktif tahun ini</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Status Kurikulum</div>
                    <div class="stat-value" style="font-size:1.5rem">Aktif</div>
                    <div class="stat-change neutral">Siap digunakan</div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="content-section">
            <div class="toolbar">
                <div class="filter-group">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="searchInput" placeholder="Cari Kode atau Nama Mata Kuliah...">
                    </div>
                </div>
                <button class="btn-primary" onclick="openModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Mata Kuliah
                </button>
            </div>

            <div class="activity-table">
                <table id="matkulTable">
                    <thead>
                        <tr>
                            <th>KODE MK</th>
                            <th>NAMA MATA KULIAH</th>
                            <th>SKS</th>
                            <th>SEMESTER</th>
                            <th>STATUS</th>
                            <th style="text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_matkul > 0): ?>
                            <?php while($row = $result_matkul->fetch_assoc()): ?>
                            <tr>
                                <td><strong style="color:#E8670A"><?= htmlspecialchars($row['kode_matkul']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($row['nama_matkul']) ?></strong></td>
                                <td><?= $row['sks'] ?> SKS</td>
                                <td><span class="badge-semester">Semester <?= $row['semester'] ?></span></td>
                                <!-- Status hardcoded karena tidak ada di DB, tapi ada di UI Image -->
                                <td><span class="badge-status wajib">Wajib</span></td>
                                
                                <!-- ACTION COLUMN: EDIT & HAPUS -->
                                <td style="text-align: center; display: flex; gap: 0.5rem; justify-content: center;">
                                    <!-- Button Edit -->
                                    <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" 
                                            data-id="<?= $row['id_matkul'] ?>" 
                                            data-kode="<?= htmlspecialchars($row['kode_matkul']) ?>" 
                                            data-nama="<?= htmlspecialchars($row['nama_matkul']) ?>" 
                                            data-sks="<?= $row['sks'] ?>" 
                                            data-semester="<?= $row['semester'] ?>" 
                                            onclick="openEditModal(this)">
                                        Edit
                                    </button>

                                    <!-- Button Hapus -->
                                    <a href="?hapus=<?= $row['id_matkul'] ?>"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus mata kuliah <?= htmlspecialchars($row['nama_matkul']) ?>? Tindakan ini permanen!');"
                                       class="btn btn-outline"
                                       style="padding: 0.3rem 0.6rem; font-size: 0.8rem; color: #EF4444; border-color: #F87171; text-decoration: none;">
                                       Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">Belum ada data mata kuliah di database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (Static for now) -->
            <div class="pagination">
                <span>Menampilkan <?= $total_matkul ?> data mata kuliah</span>
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
        const kode = btn.getAttribute('data-kode');
        const nama = btn.getAttribute('data-nama');
        const sks = btn.getAttribute('data-sks');
        const semester = btn.getAttribute('data-semester');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_kode').value = kode;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_sks').value = sks;
        document.getElementById('edit_semester').value = semester;
        
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

    // Fitur Pencarian Sederhana (Client-side)
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        let table = document.getElementById('matkulTable');
        let tr = table.getElementsByTagName('tr');
        for (let i = 1; i < tr.length; i++) {
            let td = tr[i].getElementsByTagName('td');
            let found = false;
            for (let j = 0; j < td.length; j++) {
                if (td[j]) {
                    let txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            tr[i].style.display = found ? '' : 'none';
        }
    });
</script>
</body>
</html>