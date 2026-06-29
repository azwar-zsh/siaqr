<?php
// Samakan timezone PHP dengan timezone MySQL (WIB/UTC+7) agar perbandingan
// waktu sesi (waktu_mulai/waktu_selesai vs NOW()) tidak meleset.
date_default_timezone_set('Asia/Jakarta');

$server = "localhost";
$username = "root";
$pasword = "";
$db = "siaqr" ;

$conn = new mysqli($server,$username,$pasword,$db);

if ($conn->connect_error) {
    die("Koneksi Gagal".$conn->connect_error);
}

// Set timezone untuk semua query MySQL (WIB/UTC+7)
$conn->query("SET time_zone = '+07:00'");
// echo "Koneksi Berhasil";