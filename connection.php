<?php
$server = "localhost";
$username = "root";
$pasword = "";
$db = "siaqr" ;

$conn = new mysqli($server,$username,$pasword,$db);

if ($conn->connect_error) {
    die("Koneksi Gagal".$conn->connect_error);
} else {
    echo "Koneksi Berhasil";
}
