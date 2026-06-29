<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya mahasiswa yang boleh mengakses halaman ini
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') {
    header('Location: login.php');
    exit;
}

$id_mahasiswa = $_SESSION['id_user'];

// ==========================================
// LOGIKA PROSES ABSENSI VIA AJAX POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $ip_device = $_SERVER['REMOTE_ADDR'];

    // 1. Validasi Token QR
    $query = "SELECT sa.*, mk.nama_matkul, k.nama_kelas 
              FROM sesi_absensi sa
              JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
              JOIN kelas k ON sa.id_kelas = k.id_kelas
              WHERE sa.qr_code_token = ? AND sa.status = 'Aktif' 
              AND NOW() BETWEEN sa.waktu_mulai AND sa.waktu_selesai";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $sesi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$sesi) {
        echo json_encode(['status' => 'error', 'message' => 'QR Code tidak valid atau sesi sudah kadaluarsa!']);
        exit;
    }

    // 2. Cek apakah sudah absen sebelumnya
    $check = "SELECT id_kehadiran FROM kehadiran WHERE id_sesi = ? AND id_mahasiswa = ?";
    $stmt = mysqli_prepare($conn, $check);
    mysqli_stmt_bind_param($stmt, "ii", $sesi['id_sesi'], $id_mahasiswa);
    mysqli_stmt_execute($stmt);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
        echo json_encode(['status' => 'warning', 'message' => 'Anda sudah melakukan absensi untuk sesi ini.']);
        exit;
    }

    // 3. Simpan Kehadiran
    $insert = "INSERT INTO kehadiran (id_sesi, id_mahasiswa, ip_device, timestamp_hadir, keterangan) 
               VALUES (?, ?, ?, NOW(), 'Hadir')";
    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, "iis", $sesi['id_sesi'], $id_mahasiswa, $ip_device);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Absensi Berhasil!',
            'matkul' => $sesi['nama_matkul'],
            'kelas' => $sesi['nama_kelas'],
            'pertemuan' => $sesi['pertemuan_ke']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data absensi.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR - Portal SIAQR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #E8670A;
            --bg-body: #000000; /* Gelap saat scan */
            --text-light: #ffffff;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(0,0,0,0.5);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 10;
        }
        
        .btn-back {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .scanner-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding-top: 60px; /* Space for header */
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Container untuk kotak kamera */
        #reader {
            width: 100%;
            border-radius: 1rem;
            overflow: hidden;
            border: none !important;
        }

        #reader video {
            object-fit: cover;
            border-radius: 1rem;
        }

        .instruction {
            text-align: center;
            margin-top: 2rem;
            padding: 0 2rem;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .manual-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            margin-top: 2rem;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="btn-back">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span style="margin-left:8px; font-weight:600;">Kembali</span>
        </a>
    </div>

    <div class="scanner-container">
        <div id="reader"></div>
        
        <div class="instruction">
            Arahkan kamera ke QR Code yang ditampilkan oleh Dosen di layar depan kelas.
        </div>

        <button class="manual-btn" onclick="inputManual()">Masukkan Kode Manual</button>
    </div>

    <script>
        // Inisialisasi scanner
        let html5QrcodeScanner;
        let isScanning = false;

        function onScanSuccess(decodedText, decodedResult) {
            // Hentikan scan sementara agar tidak terkirim berulang kali
            if (isScanning) return;
            isScanning = true;

            html5QrcodeScanner.pause();

            // Tampilkan loading popup
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengecek validitas QR Code',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Asumsi format QR code dari dosen adalah string token
            // Kirim token ke PHP via AJAX (Fetch API)
            fetch('scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'token=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Presensi Berhasil!',
                        html: `<b>${data.matkul}</b><br>Kelas: ${data.kelas}<br>Pertemuan ke-${data.pertemuan}`,
                        confirmButtonColor: '#E8670A',
                        confirmButtonText: 'Kembali ke Dashboard'
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                } else if (data.status === 'warning') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sudah Absen',
                        text: data.message,
                        confirmButtonColor: '#E8670A'
                    }).then(() => {
                        isScanning = false;
                        html5QrcodeScanner.resume();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#E8670A'
                    }).then(() => {
                        isScanning = false;
                        html5QrcodeScanner.resume();
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: 'Terjadi kesalahan jaringan, coba lagi.',
                    confirmButtonColor: '#E8670A'
                }).then(() => {
                    isScanning = false;
                    html5QrcodeScanner.resume();
                });
            });
        }

        function onScanFailure(error) {
            // Abaikan error saat proses scanning frame-by-frame
        }

        // Konfigurasi dan jalankan scanner saat halaman selesai dimuat
        document.addEventListener("DOMContentLoaded", function() {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { fps: 10, qrbox: {width: 250, height: 250}, aspectRatio: 1.0 },
                /* verbose= */ false
            );
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });

        // Placeholder untuk fitur kode manual (opsional jika dibutuhkan)
        function inputManual() {
            Swal.fire({
                title: 'Kode Manual',
                input: 'text',
                inputAttributes: {
                    autocapitalize: 'off',
                    placeholder: 'Masukkan 6 digit kode'
                },
                showCancelButton: true,
                confirmButtonText: 'Submit',
                confirmButtonColor: '#E8670A',
                showLoaderOnConfirm: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    // Logic yang sama dengan AJAX di atas, namun disesuaikan untuk kode manual.
                    // Saat ini kita tampilkan alert placeholder.
                    Swal.fire('Fitur belum diimplementasikan di PHP', '', 'info');
                }
            });
        }
    </script>
</body>
</html>