<?php
session_start();
require_once 'connection.php';

// Proteksi: hanya mahasiswa yang boleh mengakses halaman ini
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'mahasiswa') {
    header('Location: index.php');
    exit;
}

$id_mahasiswa = $_SESSION['id_user'];

// ==========================================
// DEBUG MODE: Tampilkan sesi aktif dengan kode manual
// ==========================================
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
    echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
    echo "table{border-collapse:collapse;width:100%;background:white;margin:20px 0;}";
    echo "th,td{border:1px solid #ddd;padding:12px;text-align:left;}";
    echo "th{background:#E8670A;color:white;}";
    echo ".code{font-size:24px;font-weight:bold;color:#E8670A;letter-spacing:3px;}";
    echo ".success{color:green;} .error{color:red;} .warning{color:orange;}";
    echo "h2{color:#E8670A;}</style></head><body>";
    
    echo "<h2>🧪 Debug Mode - Kode Manual Aktif</h2>";
    echo "<p><a href='scan.php'>← Kembali ke Scanner</a></p>";
    
    $debug_query = "SELECT sa.id_sesi, sa.kode_manual, sa.qr_code_token, 
                    sa.waktu_mulai, sa.waktu_selesai, sa.status,
                    mk.nama_matkul, k.nama_kelas, d.nama as nama_dosen
                    FROM sesi_absensi sa
                    JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
                    JOIN kelas k ON sa.id_kelas = k.id_kelas
                    LEFT JOIN dosen d ON sa.id_dosen = d.id_dosen
                    WHERE sa.status = 'Aktif'
                    ORDER BY sa.waktu_mulai DESC";
    
    $debug_result = mysqli_query($conn, $debug_query);
    
    if (mysqli_num_rows($debug_result) > 0) {
        echo "<table><tr><th>ID</th><th>Mata Kuliah</th><th>Kelas</th><th>Dosen</th>";
        echo "<th>Kode Manual</th><th>Waktu Selesai</th><th>Status</th></tr>";
        
        while ($row = mysqli_fetch_assoc($debug_result)) {
            $now = time();
            $selesai = strtotime($row['waktu_selesai']);
            $valid = ($now < $selesai);
            
            $status = $valid ? "<span class='success'>✓ Valid</span>" : "<span class='error'>✗ Expired</span>";
            $kode = empty($row['kode_manual']) ? "<span class='error'>NULL</span>" : "<span class='code'>{$row['kode_manual']}</span>";
            
            echo "<tr>";
            echo "<td>{$row['id_sesi']}</td>";
            echo "<td>{$row['nama_matkul']}</td>";
            echo "<td>{$row['nama_kelas']}</td>";
            echo "<td>{$row['nama_dosen']}</td>";
            echo "<td>{$kode}</td>";
            echo "<td>{$row['waktu_selesai']}</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>📝 Cara Testing:</h3>";
        echo "<ol>";
        echo "<li>Pilih salah satu <strong>KODE MANUAL</strong> dari tabel di atas (yang Valid)</li>";
        echo "<li>Klik <a href='scan.php'>Kembali ke Scanner</a></li>";
        echo "<li>Klik tombol <strong>\"Masukkan Kode Manual\"</strong></li>";
        echo "<li>Input kode 6 digit yang Anda pilih</li>";
        echo "<li>Submit → Absensi akan berhasil! ✅</li>";
        echo "</ol>";
    } else {
        echo "<p class='warning'>⚠ Tidak ada sesi aktif. Buat sesi baru dulu lewat dosen.</p>";
    }
    
    echo "</body></html>";
    exit;
}

// ==========================================
// LOGIKA PROSES ABSENSI VIA AJAX POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $ip_device = $_SERVER['REMOTE_ADDR'];

    // 1. Validasi Token QR atau Kode Manual
    $token_upper = strtoupper(trim($token));
    $token_clean = trim($token);
    
    // Ekstrak token jika berupa URL penuh
    if (strpos($token_clean, 'token=') !== false) {
        parse_str(parse_url($token_clean, PHP_URL_QUERY), $params);
        if (isset($params['token'])) {
            $token_clean = $params['token'];
            $token_upper = strtoupper($token_clean);
        }
    }
    
    // Set timezone untuk MySQL session
    mysqli_query($conn, "SET time_zone = '+07:00'");
    
    // Query dengan UPPER() untuk case-insensitive matching
    $query = "SELECT sa.*, mk.nama_matkul, k.nama_kelas 
              FROM sesi_absensi sa
              JOIN mata_kuliah mk ON sa.id_matkul = mk.id_matkul
              JOIN kelas k ON sa.id_kelas = k.id_kelas
              WHERE (sa.qr_code_token = ? 
                     OR UPPER(sa.kode_manual) = ?) 
              AND UPPER(sa.status) = 'AKTIF' 
              AND NOW() BETWEEN sa.waktu_mulai AND sa.waktu_selesai";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $token_clean, $token_upper);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sesi = mysqli_fetch_assoc($result);

    if (!$sesi) {
        // Cek apakah ada sesi aktif sama sekali (untuk debug)
        $check_query = "SELECT id_sesi, kode_manual, qr_code_token, status, 
                        waktu_mulai, waktu_selesai, NOW() as waktu_sekarang
                        FROM sesi_absensi 
                        WHERE status = 'Aktif' 
                        LIMIT 5";
        $check_result = mysqli_query($conn, $check_query);
        
        $debug_info = "Token input: '$token_upper'. ";
        
        if (mysqli_num_rows($check_result) > 0) {
            $debug_info .= "Sesi aktif ditemukan: ";
            $codes = [];
            while ($row = mysqli_fetch_assoc($check_result)) {
                $waktu_valid = (strtotime($row['waktu_sekarang']) >= strtotime($row['waktu_mulai']) && 
                               strtotime($row['waktu_sekarang']) <= strtotime($row['waktu_selesai']));
                $codes[] = $row['kode_manual'] . ($waktu_valid ? ' (valid)' : ' (expired)');
            }
            $debug_info .= implode(', ', $codes);
        } else {
            $debug_info .= "Tidak ada sesi dengan status Aktif.";
        }
        
        echo json_encode([
            'status' => 'error', 
            'message' => 'Kode tidak valid atau sesi sudah kadaluarsa!',
            'debug' => $debug_info
        ]);
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
            max-width: 100%;
            border-radius: 1rem;
            overflow: hidden;
            border: none !important;
        }

        #reader video {
            width: 100% !important;
            max-width: 100% !important;
            object-fit: cover;
            border-radius: 1rem;
        }
        
        #reader__scan_region {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        #reader__camera_selection {
            width: 100% !important;
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
        
        .switch-camera-btn {
            background: var(--primary);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
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

        <button class="switch-camera-btn" id="switchCameraBtn" onclick="switchCamera()" style="display:none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
                <path d="M17 8l-1.5-1.5"/>
            </svg>
            Ganti Kamera
        </button>
        
        <button class="manual-btn" onclick="inputManual()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Masukkan Kode Manual
        </button>
    </div>

    <script>
        let html5QrcodeScanner;
        let isScanning = false;
        let cameras = [];
        let currentCameraIndex = 0;

        function onScanSuccess(decodedText, decodedResult) {
            if (isScanning) return;
            isScanning = true;

            html5QrcodeScanner.pause();

            Swal.fire({
                title: 'Memproses...',
                text: 'Mengecek validitas QR Code',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            let token = decodedText;
            if (decodedText.includes('token=')) {
                const urlParams = new URLSearchParams(decodedText.split('?')[1]);
                token = urlParams.get('token');
            }

            fetch('scan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'token=' + encodeURIComponent(token)
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
        }

        async function getCameras() {
            try {
                const devices = await Html5Qrcode.getCameras();
                cameras = devices;
                if (cameras.length > 1) {
                    document.getElementById('switchCameraBtn').style.display = 'flex';
                }
                return devices;
            } catch (err) {
                console.error('Error getting cameras:', err);
                return [];
            }
        }

        async function switchCamera() {
            if (cameras.length <= 1) return;
            
            currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
            
            await html5QrcodeScanner.clear();
            
            startScanner(cameras[currentCameraIndex].id);
        }

        function startScanner(cameraId = null) {
            const config = {
                fps: 10,
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    let minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    let qrboxSize = Math.floor(minEdge * 0.7);
                    return {
                        width: qrboxSize,
                        height: qrboxSize
                    };
                },
                aspectRatio: 1.0,
                formatsToSupport: [ Html5QrcodeScanType.SCAN_TYPE_CAMERA ]
            };

            if (cameraId) {
                config.videoConstraints = {
                    deviceId: { exact: cameraId }
                };
            } else {
                config.videoConstraints = {
                    facingMode: { ideal: "environment" }
                };
            }

            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                config,
                false
            );
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }

        document.addEventListener("DOMContentLoaded", async function() {
            await getCameras();
            
            if (cameras.length > 0) {
                const backCamera = cameras.find(camera => 
                    camera.label.toLowerCase().includes('back') || 
                    camera.label.toLowerCase().includes('rear') ||
                    camera.label.toLowerCase().includes('environment')
                );
                
                if (backCamera) {
                    currentCameraIndex = cameras.indexOf(backCamera);
                    startScanner(backCamera.id);
                } else {
                    startScanner(cameras[0].id);
                }
            } else {
                startScanner();
            }
        });

        function inputManual() {
            Swal.fire({
                title: 'Kode Manual',
                input: 'text',
                inputAttributes: {
                    autocapitalize: 'on',
                    placeholder: 'Masukkan 6 digit kode',
                    maxlength: 6
                },
                showCancelButton: true,
                confirmButtonText: 'Submit',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#E8670A',
                showLoaderOnConfirm: true,
                preConfirm: (kodeManual) => {
                    if (!kodeManual || kodeManual.length !== 6) {
                        Swal.showValidationMessage('Kode harus 6 karakter');
                        return false;
                    }
                    
                    return fetch('scan.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'token=' + encodeURIComponent(kodeManual.toUpperCase())
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            throw new Error(data.message);
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Gagal: ${error.message || error}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const data = result.value;
                    Swal.fire({
                        icon: 'success',
                        title: 'Presensi Berhasil!',
                        html: `<b>${data.matkul}</b><br>Kelas: ${data.kelas}<br>Pertemuan ke-${data.pertemuan}`,
                        confirmButtonColor: '#E8670A',
                        confirmButtonText: 'Kembali ke Dashboard'
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                }
            });
        }
    </script>
</body>
</html>