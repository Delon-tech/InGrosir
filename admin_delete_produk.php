<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$produk_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$produk_id) {
    header("Location: admin_produk.php");
    exit();
}

// Ambil nama file gambar sebelum menghapus
$gambar_query = "SELECT gambar_produk FROM produk WHERE produk_id = ?";
$gambar_stmt = mysqli_prepare($koneksi, $gambar_query);
mysqli_stmt_bind_param($gambar_stmt, "i", $produk_id);
mysqli_stmt_execute($gambar_stmt);
$gambar_result = mysqli_stmt_get_result($gambar_stmt);
$gambar = mysqli_fetch_assoc($gambar_result);

if ($gambar && !empty($gambar['gambar_produk'])) {
    $file_path = "uploads/" . $gambar['gambar_produk'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Hapus produk
$query = "DELETE FROM produk WHERE produk_id = ?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $produk_id);
mysqli_stmt_execute($stmt);

header("Location: admin_produk.php");
exit();
?>