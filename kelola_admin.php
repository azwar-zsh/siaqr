<?php
session_start();
include 'connection.php';

// Proteksi: Hanya Super Admin yang bisa mengakses
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$super_admin_id = $_SESSION['id_user']; // ID Super Admin yang sedang login
$pesan = '';

// --- Proses Tambah/Edit Admin ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if ($action === 'add') {
        // Hanya Super Admin yang bisa menambahkan
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
        $username = mysqli_real_escape_string($conn, trim($_POST['username']));
        $password = $_POST['password']; // Harus di-hash di production
        $jabatan = mysqli_real_escape_string($conn, trim($_POST['jabatan']));
        $role = ($_POST['role'] === 'super_admin') ? 'super_admin' : 'admin'; // Validasi role

        if ($nama && $username && $password && $jabatan) {
            // Cek apakah username sudah ada
            $check_query = "SELECT id_admin FROM admin WHERE username = '$username'";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) == 0) {
                // Hash password (Gunakan password_hash di production!)
                // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // Untuk saat ini, simpan plain text sesuai struktur DB Anda
                $hashed_password = $password;

                $insert_query = "INSERT INTO admin (nama, username, password, jabatan, role) VALUES ('$nama', '$username', '$hashed_password', '$jabatan', '$role')";
                if (mysqli_query($conn, $insert_query)) {
                    $pesan = "Admin baru berhasil ditambahkan.";
                } else {
                    $pesan = "Error: Gagal menambahkan admin. " . mysqli_error($conn);
                }
            } else {
                $pesan = "Error: Username '$username' sudah digunakan.";
            }
        } else {
            $pesan = "Error: Semua field wajib diisi.";
        }
    } elseif ($action === 'edit' && $id) {
        // Hanya Super Admin yang bisa mengedit
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
        $username = mysqli_real_escape_string($conn, trim($_POST['username']));
        $new_password = $_POST['password']; // Boleh kosong jika tidak diganti
        $jabatan = mysqli_real_escape_string($conn, trim($_POST['jabatan']));
        $role = ($_POST['role'] === 'super_admin') ? 'super_admin' : 'admin'; // Validasi role

        if ($nama && $username && $jabatan) {
            // Cek username baru (jika berbeda dari yang lama)
            $check_query = "SELECT id_admin FROM admin WHERE username = '$username' AND id_admin != $id";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) == 0) {
                $update_query = "UPDATE admin SET nama='$nama', username='$username', jabatan='$jabatan', role='$role'";
                if ($new_password) { // Jika password diisi, update
                    // $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                    // Untuk saat ini, simpan plain text
                    $update_query .= ", password='$new_password'";
                }
                $update_query .= " WHERE id_admin = $id";

                if (mysqli_query($conn, $update_query)) {
                    $pesan = "Data admin berhasil diperbarui.";
                } else {
                    $pesan = "Error: Gagal memperbarui admin. " . mysqli_error($conn);
                }
            } else {
                $pesan = "Error: Username '$username' sudah digunakan oleh admin lain.";
            }
        } else {
            $pesan = "Error: Semua field wajib diisi.";
        }
    } elseif ($action === 'delete' && $id && $id != $super_admin_id) { // Jangan izinkan delete diri sendiri
        // Hanya Super Admin yang bisa menghapus
        $delete_query = "DELETE FROM admin WHERE id_admin = $id";
        if (mysqli_query($conn, $delete_query)) {
            $pesan = "Admin berhasil dihapus.";
        } else {
            $pesan = "Error: Gagal menghapus admin. " . mysqli_error($conn);
        }
    } else {
        $pesan = "Error: Aksi tidak valid atau Anda tidak bisa menghapus diri sendiri.";
    }
}

// --- Ambil Data Admin untuk Ditampilkan ---
$admins = [];
$query = "SELECT id_admin, nama, username, jabatan, role FROM admin ORDER BY role DESC, nama ASC"; // Super Admin di atas
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $admins[] = $row;
    }
}

// --- Ambil Data Admin untuk Form Edit ---
$admin_to_edit = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $query_edit = "SELECT id_admin, nama, username, jabatan, role FROM admin WHERE id_admin = $edit_id";
    $result_edit = mysqli_query($conn, $query_edit);
    if ($result_edit && mysqli_num_rows($result_edit) > 0) {
        $admin_to_edit = mysqli_fetch_assoc($result_edit);
    }
}

$active_page = 'admin'; // Untuk highlight menu sidebar
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Admin - SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Gaya Umum (sama seperti dashboard_admin.php) */
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

        .admin-wrapper {
            width: 100%;
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

        /* Main Content */
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
            transition: all 0.2s;
            text-decoration: none;
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        /* Content Section */
        .content-section {
            background: white;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .link-see-all {
            color: #e8670a;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #e8670a;
            box-shadow: 0 0 0 3px rgba(232, 103, 10, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
        }

        /* Table */
        .admin-table {
            overflow-x: auto;
        }

        .admin-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.875rem 1rem;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }

        .admin-table th {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            background: #f9fafb;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .admin-role.super_admin {
            background: #fef3c7;
            color: #d97706;
        }

        .admin-role.admin {
            background: #dbeafe;
            color: #2563eb;
        }

        .admin-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Responsive */
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

            <!-- Ganti bagian sidebar nav -->
            <nav class="sidebar-nav">
                <a href="dashboard_super.php" class="nav-item <?= $active_page === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
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
                <!-- ... tambahkan menu lainnya ... -->
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
                    <h1>Kelola Akun Admin</h1>
                    <p class="header-subtitle">Tambah, edit, atau hapus akun admin sistem</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddForm()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Tambah Admin
                    </button>
                </div>
            </div>

            <?php if ($pesan): ?>
            <div class="alert <?= strpos($pesan, 'Error') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
            <?php endif; ?>

            <!-- Form Tambah/Edit -->
            <div id="adminFormSection" class="content-section" style="display: <?= $admin_to_edit ? 'block' : 'none' ?>;">
                <div class="section-header">
                    <h2><?= $admin_to_edit ? 'Edit Admin' : 'Tambah Admin Baru' ?></h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?= $admin_to_edit ? 'edit' : 'add' ?>">
                    <?php if ($admin_to_edit): ?>
                    <input type="hidden" name="id" value="<?= $admin_to_edit['id_admin'] ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" class="form-control" value="<?= htmlspecialchars($admin_to_edit['nama'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($admin_to_edit['username'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Password <?= !$admin_to_edit ? '(Baru)' : '(Kosongkan jika tidak diganti)' ?></label>
                            <input type="password" id="password" name="password" class="form-control" <?= !$admin_to_edit ? 'required' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="jabatan" class="form-label">Jabatan</label>
                            <input type="text" id="jabatan" name="jabatan" class="form-control" value="<?= htmlspecialchars($admin_to_edit['jabatan'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-control" disabled> <!-- Disable karena hanya super admin bisa ubah via SQL -->
                                <option value="admin" <?= (!isset($admin_to_edit['role']) || $admin_to_edit['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="super_admin" <?= (isset($admin_to_edit['role']) && $admin_to_edit['role'] === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                            <small>Role hanya bisa diubah via database secara langsung.</small>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $admin_to_edit ? 'Simpan Perubahan' : 'Tambah Admin' ?></button>
                        <button type="button" class="btn btn-outline" onclick="cancelEdit()">Batal</button>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Admin -->
            <div class="content-section">
                <div class="section-header">
                    <h2>Daftar Admin</h2>
                </div>
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Jabatan</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['nama']) ?></td>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars($admin['jabatan']) ?></td>
                                <td><span class="admin-role <?= $admin['role'] ?>"><?= ucfirst($admin['role']) ?></span></td>
                                <td>
                                    <div class="admin-actions">
                                        <a href="?edit_id=<?= $admin['id_admin'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <?php if ($admin['id_admin'] != $super_admin_id): // Jangan tampilkan tombol hapus untuk diri sendiri ?>
                                        <a href="?delete_id=<?= $admin['id_admin'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus admin <?= addslashes(htmlspecialchars($admin['nama'])) ?>?')">Hapus</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function openAddForm() {
            document.getElementById('adminFormSection').style.display = 'block';
            // Reset form
            const form = document.querySelector('#adminFormSection form');
            if(form) form.reset();
            // Ensure action is set to add
            const actionInput = document.querySelector('#adminFormSection input[name="action"]');
            if(actionInput) actionInput.value = 'add';
            // Remove id if exists
            const idInput = document.querySelector('#adminFormSection input[name="id"]');
            if(idInput) idInput.remove();
        }

        function cancelEdit() {
            document.getElementById('adminFormSection').style.display = 'none';
            // Reset form
            const form = document.querySelector('#adminFormSection form');
            if(form) form.reset();
        }

        // Show form if editing was requested via URL
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_id')) {
                document.getElementById('adminFormSection').style.display = 'block';
            }
        };
    </script>
</body>
</html>