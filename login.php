<?php
session_start();
include 'connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['nim']));
    $password = $_POST['password'];

    $user = null;
    $role = '';

    // Cek Admin
    $query = "SELECT * FROM admin WHERE username='$username'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if ($password === $user['password']) {
            $role = 'admin';
        }
    }

    // Cek Dosen
    if (!$user) {
        $query = "SELECT * FROM dosen WHERE username='$username'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            if ($password === $user['password']) {
                $role = 'dosen';
            }
        }
    }

    // Cek Mahasiswa
    if (!$user) {
        $query = "SELECT * FROM mahasiswa WHERE username='$username' OR nim='$username'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            if ($password === $user['password']) {
                $role = 'mahasiswa';
            }
        }
    }

    if ($user && $role) {
        $_SESSION['logged_in'] = true;
        $_SESSION['id_user'] = $user['id_admin'] ?? $user['id_dosen'] ?? $user['id_mahasiswa'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nim'] = $user['nim'] ?? '-';
        $_SESSION['role'] = $role;

        if ($role === 'admin') {
            header('Location: dashboard_admin.php');
        } elseif ($role === 'dosen') {
            header('Location: dashboard_dosen.php');
        } else {
            header('Location: dashboard_mhs.php');
        }
        exit;
    } else {
        $error = 'Username/NIM atau password salah.';
    }
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: dashboard_admin.php');
    } elseif ($_SESSION['role'] === 'dosen') {
        header('Location: dashboard_dosen.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAQR – Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/login.css">
</head>
<body>
<div class="page-wrapper">

    <!-- LEFT -->
    <div class="left-panel">
        <div class="left-bg"></div>
        <div class="left-noise"></div>
        <div class="left-content">
            <div class="brand-logo">
                <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="4" width="14" height="14" rx="2" fill="white"/>
                    <rect x="26" y="4" width="14" height="14" rx="2" fill="white"/>
                    <rect x="4" y="26" width="14" height="14" rx="2" fill="white"/>
                    <rect x="26" y="26" width="6" height="6" rx="1" fill="white"/>
                    <rect x="34" y="26" width="6" height="6" rx="1" fill="white"/>
                    <rect x="26" y="34" width="6" height="6" rx="1" fill="white"/>
                    <rect x="7" y="7" width="8" height="8" rx="1" fill="#C45A0A"/>
                    <rect x="29" y="7" width="8" height="8" rx="1" fill="#C45A0A"/>
                    <rect x="7" y="29" width="8" height="8" rx="1" fill="#C45A0A"/>
                </svg>
            </div>
            <h1 class="brand-name">SIAQR</h1>
            <p class="brand-tagline">Catat kehadiran lebih mudah dengan QR Code. Solusi presensi akademik modern untuk civitas akademika.</p>
            <div class="badge-row">
                <div class="badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Secure Access
                </div>
                <div class="badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Real-time Sync
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="right-panel">
        <div class="form-card">
            <h2 class="form-title">Selamat Datang,</h2>
            <p class="form-subtitle">Masuk ke akun Anda</p>

            <?php if ($error): ?>
            <div class="alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="field-group">
                    <div class="field-label">
                        <span>Username / NIM</span>
                    </div>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" name="nim" class="input-field" placeholder="Masukkan NIM anda" value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label">
                        <span>Password</span>
                        <a href="#" class="forgot-link">Lupa password?</a>
                    </div>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input type="password" name="password" id="passwordField" class="input-field" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Masuk
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

            <div class="divider"></div>

            <p class="register-text">Belum punya akun? <a href="#">Hubungi Akademik</a></p>

        </div>
    </div>
</div>

<script>
function togglePassword() {
    const field = document.getElementById('passwordField');
    const icon = document.getElementById('eyeIcon');
    if (field.type === 'password') {
        field.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        field.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>
</body>
</html>
