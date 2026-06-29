<?php
session_start();
require 'connection.php';

// Proteksi Admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$active_page = 'kelas';

// --- TAMBAH KELAS ---
if (isset($_POST['tambah_kelas'])) {
    $nama = trim($_POST['nama_kelas']);
    $tahun = trim($_POST['tahun_akademik']);
    $prodi = trim($_POST['program_studi']);
    $ruang = trim($_POST['ruangan']);
    $id_dosen = trim($_POST['id_dosen']);
    $id_matkul = trim($_POST['id_matkul']);

    $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tahun_akademik, program_studi, ruangan, id_dosen, id_matkul) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $nama, $tahun, $prodi, $ruang, $id_dosen, $id_matkul);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Kelas baru berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan kelas.";
    }
    header("Location: kelola_kelas.php");
    exit;
}

// --- EDIT KELAS ---
if (isset($_POST['edit_kelas'])) {
    $id = $_POST['id_kelas'];
    $nama = trim($_POST['nama_kelas']);
    $tahun = trim($_POST['tahun_akademik']);
    $prodi = trim($_POST['program_studi']);
    $ruang = trim($_POST['ruangan']);
    $id_dosen = trim($_POST['id_dosen']);
    $id_matkul = trim($_POST['id_matkul']);

    $stmt = $conn->prepare("UPDATE kelas SET nama_kelas=?, tahun_akademik=?, program_studi=?, ruangan=?, id_dosen=?, id_matkul=? WHERE id_kelas=?");
    $stmt->bind_param("ssssiii", $nama, $tahun, $prodi, $ruang, $id_dosen, $id_matkul, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data kelas berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui data.";
    }
    header("Location: kelola_kelas.php");
    exit;
}

// --- HAPUS KELAS ---
if (isset($_GET['hapus'])) {
    $stmt = $conn->prepare("DELETE FROM kelas WHERE id_kelas = ?");
    $stmt->bind_param("i", $_GET['hapus']);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Kelas berhasil dihapus!";
    }
    header("Location: kelola_kelas.php");
    exit;
}

// --- AMBIL DATA ---
// Query Join untuk menampilkan Nama Dosen dan Nama Matkul
$query = "SELECT k.*, d.nama as nama_dosen, m.nama_matkul, m.kode_matkul 
          FROM kelas k 
          LEFT JOIN dosen d ON k.id_dosen = d.id_dosen 
          LEFT JOIN mata_kuliah m ON k.id_matkul = m.id_matkul
          ORDER BY k.nama_kelas ASC";
$result = $conn->query($query);
$total_kelas = $result->num_rows;

// Ambil data dropdown
$dosen_list = $conn->query("SELECT * FROM dosen");
$matkul_list = $conn->query("SELECT * FROM mata_kuliah");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kelas - Portal SIAQR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style/admin.css">
<style>
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
    .search-box { position: relative; flex: 1; max-width: 350px; }
    .search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #64748b; }
    .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; outline: none; font-family: inherit; font-size: 0.9rem; }
    .search-box input:focus { border-color: #E8670A; }
    .btn-primary { background: #E8670A; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; transition: background 0.2s; }
    .btn-primary:hover { background: #C45A0A; }
    
    /* Table Specific */
    .avatar-sm { width: 28px; height: 28px; background: #E0F2FE; color: #0284C7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; margin-right: 8px; }
    .matkul-info strong { display: block; color: #0F172A; }
    .matkul-info span { font-size: 0.8rem; color: #64748B; }
    
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: 0.3s; backdrop-filter: blur(2px); }
    .modal-overlay.show { opacity: 1; visibility: visible; }
    .modal-box { background: white; padding: 2rem; border-radius: 16px; width: 100%; max-width: 500px; transform: translateY(-20px); transition: 0.3s; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
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
    
    .pagination { display: flex; justify-content: space-between; align-items: center; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; margin-top: 1rem; color: #64748b; font-size: 0.875rem; }
    .page-numbers { display: flex; gap: 0.25rem; align-items: center; }
    .page-btn { width: 32px; height: 32px; display: flex; justify-content: center; align-items: center; border-radius: 0.375rem; cursor: pointer; text-decoration: none; color: #475569; font-weight: 600; transition: background 0.2s; }
    .page-btn:hover { background: #f1f5f9; }
    .page-btn.active { background: #E8670A; color: white; }
</style>
</head>
<body>

<!-- Modal Tambah -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Tambah Kelas Baru</h3>
            <button class="modal-close" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <form method="POST" action="">
            <div class="form-group"><label>Nama Kelas</label><input type="text" name="nama_kelas" placeholder="Contoh: IF-A 2024" required></div>
            <div class="form-group" style="display:flex; gap:1rem;">
                <div style="flex:1"><label>Tahun Akademik</label><input type="text" name="tahun_akademik" placeholder="2024/2025" required></div>
                <div style="flex:1"><label>Ruangan</label><input type="text" name="ruangan" placeholder="Lantai 1 - Lab A" required></div>
            </div>
            <div class="form-group"><label>Mata Kuliah</label>
                <select name="id_matkul" required>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php while($m = $matkul_list->fetch_assoc()): ?>
                        <option value="<?= $m['id_matkul'] ?>"><?= $m['kode_matkul'] ?> - <?= $m['nama_matkul'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label>Dosen Pengampu</label>
                <select name="id_dosen" required>
                    <option value="">-- Pilih Dosen --</option>
                    <?php while($d = $dosen_list->fetch_assoc()): ?>
                        <option value="<?= $d['id_dosen'] ?>"><?= $d['nama'] ?> (NIP: <?= $d['nip'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                <button type="submit" name="tambah_kelas" class="btn-submit">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Data Kelas</h3>
            <button class="modal-close" onclick="closeEditModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_kelas" id="edit_id">
            <div class="form-group"><label>Nama Kelas</label><input type="text" name="nama_kelas" id="edit_nama" required></div>
            <div class="form-group" style="display:flex; gap:1rem;">
                <div style="flex:1"><label>Tahun Akademik</label><input type="text" name="tahun_akademik" id="edit_tahun" required></div>
                <div style="flex:1"><label>Ruangan</label><input type="text" name="ruangan" id="edit_ruang" required></div>
            </div>
            <div class="form-group"><label>Mata Kuliah</label>
                <select name="id_matkul" id="edit_matkul" required>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php 
                    $matkul_list->data_seek(0); // Reset pointer
                    while($m = $matkul_list->fetch_assoc()): ?>
                        <option value="<?= $m['id_matkul'] ?>"><?= $m['kode_matkul'] ?> - <?= $m['nama_matkul'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label>Dosen Pengampu</label>
                <select name="id_dosen" id="edit_dosen" required>
                    <option value="">-- Pilih Dosen --</option>
                    <?php 
                    $dosen_list->data_seek(0); // Reset pointer
                    while($d = $dosen_list->fetch_assoc()): ?>
                        <option value="<?= $d['id_dosen'] ?>"><?= $d['nama'] ?> (NIP: <?= $d['nip'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Batal</button>
                <button type="submit" name="edit_kelas" class="btn-submit">Perbarui Data</button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?> <script>alert("<?= $_SESSION['success'] ?>");</script> <?php unset($_SESSION['success']); ?> <?php endif; ?>
<?php if (isset($_SESSION['error'])): ?> <script>alert("<?= $_SESSION['error'] ?>");</script> <?php unset($_SESSION['error']); ?> <?php endif; ?>

<div class="admin-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="4" width="14" height="14" rx="2" fill="white"/><rect x="26" y="4" width="14" height="14" rx="2" fill="white"/><rect x="4" y="26" width="14" height="14" rx="2" fill="white"/><rect x="26" y="26" width="6" height="6" rx="1" fill="white"/><rect x="34" y="26" width="6" height="6" rx="1" fill="white"/><rect x="26" y="34" width="6" height="6" rx="1" fill="white"/><rect x="7" y="7" width="8" height="8" rx="1" fill="#E8670A"/><rect x="29" y="7" width="8" height="8" rx="1" fill="#E8670A"/><rect x="7" y="29" width="8" height="8" rx="1" fill="#E8670A"/>
                </svg>
            </div>
            <div class="logo-text"><span class="logo-title">Portal SIAQR</span><span class="logo-subtitle">Management System</span></div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard_admin.php" class="nav-item <?= $active_page === 'dashboard' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
            <a href="kelola_mahasiswa.php" class="nav-item <?= $active_page === 'mahasiswa' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Kelola Mahasiswa</a>
            <a href="kelola_dosen.php" class="nav-item <?= $active_page === 'dosen' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Kelola Dosen</a>
            <a href="kelola_matkul.php" class="nav-item <?= $active_page === 'matkul' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>Kelola Mata Kuliah</a>
            <a href="kelola_kelas.php" class="nav-item <?= $active_page === 'kelas' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Kelola Kelas</a>
            <a href="pantau_aktivitas.php" class="nav-item <?= $active_page === 'aktivitas' ? 'active' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Pantau Aktivitas</a>
        </nav>
        <div class="sidebar-footer"><a href="logout.php" class="logout-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a></div>
    </aside>
    
    <main class="main-content">
        <div class="content-header">
            <div class="header-left">
                <h1>Kelola Kelas</h1>
                <p class="header-subtitle">Atur dan kelola daftar kelas mahasiswa aktif.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                <div class="stat-content"><div class="stat-label">Total Kelas</div><div class="stat-value"><?= $total_kelas ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div class="stat-content"><div class="stat-label">Kelas Aktif</div><div class="stat-value"><?= $total_kelas ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                <div class="stat-content"><div class="stat-label">Total Ruangan</div><div class="stat-value">12</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                <div class="stat-content"><div class="stat-label">Mata Kuliah Terdaftar</div><div class="stat-value"><?= $matkul_list->num_rows ?></div></div>
            </div>
        </div>

        <div class="content-section">
            <div class="toolbar">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="searchInput" placeholder="Cari Nama Kelas atau Mata Kuliah...">
                </div>
                <button class="btn-primary" onclick="openModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Kelas
                </button>
            </div>

            <div class="activity-table">
                <table id="kelasTable">
                    <thead>
                        <tr>
                            <th>NAMA KELAS</th>
                            <th>MATA KULIAH</th>
                            <th>DOSEN PENGAMPU</th>
                            <th>RUANGAN</th>
                            <th style="text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_kelas > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nama_kelas']) ?></strong></td>
                                <td>
                                    <div class="matkul-info">
                                        <strong><?= htmlspecialchars($row['nama_matkul']) ?></strong>
                                        <span>Kode: <?= htmlspecialchars($row['kode_matkul']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center;">
                                        <span class="avatar-sm"><?= strtoupper(substr($row['nama_dosen'], 0, 1)) ?></span>
                                        <span><?= htmlspecialchars($row['nama_dosen']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['ruangan']) ?></td>
                                <td style="text-align: center; display: flex; gap: 0.5rem; justify-content: center;">
                                    <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" 
                                            data-id="<?= $row['id_kelas'] ?>" 
                                            data-nama="<?= htmlspecialchars($row['nama_kelas']) ?>" 
                                            data-tahun="<?= htmlspecialchars($row['tahun_akademik']) ?>" 
                                            data-ruang="<?= htmlspecialchars($row['ruangan']) ?>" 
                                            data-matkul="<?= $row['id_matkul'] ?>" 
                                            data-dosen="<?= $row['id_dosen'] ?>" 
                                            onclick="openEditModal(this)">
                                        Edit
                                    </button>
                                    <a href="?hapus=<?= $row['id_kelas'] ?>"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus kelas ini?');"
                                       class="btn btn-outline"
                                       style="padding: 0.3rem 0.6rem; font-size: 0.8rem; color: #EF4444; border-color: #F87171; text-decoration: none;">
                                       Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">Belum ada data kelas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <span>Menampilkan <?= $total_kelas ?> data kelas</span>
                <div class="page-numbers">
                    <a href="#" style="margin-right: 8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="15 18 9 12 15 6"/></svg></a>
                    <a href="#" class="page-btn active">1</a>
                    <a href="#" style="margin-left: 8px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><polyline points="9 18 15 12 9 6"/></svg></a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    const modal = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    
    function openModal() { modal.classList.add('show'); }
    function closeModal() { modal.classList.remove('show'); }
    
    function openEditModal(btn) {
        const id = btn.getAttribute('data-id');
        const nama = btn.getAttribute('data-nama');
        const tahun = btn.getAttribute('data-tahun');
        const ruang = btn.getAttribute('data-ruang');
        const matkul = btn.getAttribute('data-matkul');
        const dosen = btn.getAttribute('data-dosen');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama').value = nama;
        document.getElementById('edit_tahun').value = tahun;
        document.getElementById('edit_ruang').value = ruang;
        document.getElementById('edit_matkul').value = matkul;
        document.getElementById('edit_dosen').value = dosen;
        
        modalEdit.classList.add('show');
    }
    
    function closeEditModal() { modalEdit.classList.remove('show'); }
    
    window.onclick = function(event) {
        if (event.target == modal) closeModal();
        if (event.target == modalEdit) closeEditModal();
    }

    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        let table = document.getElementById('kelasTable');
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