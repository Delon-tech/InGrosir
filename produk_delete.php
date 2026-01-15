<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$produk_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$produk_id) {
    header("Location: produk_list.php");
    exit();
}

// Ambil data produk untuk mendapatkan nama gambar
$query_produk = "SELECT gambar_produk FROM produk WHERE produk_id = ? AND user_id = ?";
$stmt_produk = mysqli_prepare($koneksi, $query_produk);
mysqli_stmt_bind_param($stmt_produk, "ii", $produk_id, $user_id);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$produk = mysqli_fetch_assoc($result_produk);

if (!$produk) {
    header("Location: produk_list.php");
    exit();
}

// Hapus produk dari database
$query_delete = "DELETE FROM produk WHERE produk_id = ? AND user_id = ?";
$stmt_delete = mysqli_prepare($koneksi, $query_delete);
mysqli_stmt_bind_param($stmt_delete, "ii", $produk_id, $user_id);

if (mysqli_stmt_execute($stmt_delete)) {
    // Hapus gambar dari folder uploads jika ada
    if (!empty($produk['gambar_produk']) && file_exists("uploads/" . $produk['gambar_produk'])) {
        unlink("uploads/" . $produk['gambar_produk']);
    }
    
    header("Location: produk_list.php?success=deleted");
} else {
    header("Location: produk_list.php?error=delete_failed");
}

exit();
?>