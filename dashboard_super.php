<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya super_admin boleh masuk
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$nama = $_SESSION['nama'] ?? 'Super Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin - SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; background: #f5f7fa; color: #1a1a1a; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        .subtitle { color: #6b7280; font-size: 0.95rem; margin-bottom: 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .btn { display: inline-block; padding: 0.75rem 1.25rem; background: #e8670a; color: white; border-radius: 0.5rem; text-decoration: none; font-weight: 600; }
        .btn-outline { background: transparent; border: 1px solid #e5e7eb; color: #1a1a1a; }
    </style>
</head>
<body>
<div class="container">
    <h1>Halo, <?= htmlspecialchars($nama) ?> 👑</h1>
    <p class="subtitle">Anda adalah Super Admin — memiliki akses penuh ke seluruh sistem.</p>

    <div class="grid">
        <div class="card">
            <h3>📊 Kelola Admin</h3>
            <p>Tambah, edit, atau hapus akun admin lain.</p>
            <a href="#" class="btn">Kelola Admin</a>
        </div>
        <div class="card">
            <h3>👥 Kelola Pengguna</h3>
            <p>Atur semua mahasiswa, dosen, dan peran sistem.</p>
            <a href="kelola_mahasiswa.php" class="btn">Kelola Semua Pengguna</a>
        </div>
        <div class="card">
            <h3>🔧 Konfigurasi Sistem</h3>
            <p>Setel parameter global, backup database, log aktivitas.</p>
            <a href="#" class="btn btn-outline">Pengaturan Sistem</a>
        </div>
        <div class="card">
            <h3>🔐 Reset Password Massal</h3>
            <p>Reset password semua pengguna (untuk darurat).</p>
            <a href="#" class="btn btn-outline">Reset Massal</a>
        </div>
    </div>

    <div class="card" style="margin-top: 2rem;">
        <h3>🔍 Aktivitas Terbaru</h3>
        <ul style="padding-left: 1.25rem;">
            <li>✅ <strong>Super Admin</strong> login dari IP 192.168.1.100 — 2 menit lalu</li>
            <li>✅ <strong>Yosia</strong> absen di "Pemrograman Web" — 15 menit lalu</li>
            <li>⚠️ <strong>Azwar</strong> gagal login 3x — 22 menit lalu</li>
        </ul>
    </div>
</div>
</body>
</html>