<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$voucher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($voucher_id > 0) {
    // Cek kepemilikan voucher
    $check_query = "SELECT kode_voucher FROM voucher_diskon WHERE voucher_id = ? AND user_id_penjual = ?";
    $check_stmt = mysqli_prepare($koneksi, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $voucher_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if ($result->num_rows > 0) {
        // Delete voucher (pesanan yang sudah pakai voucher tetap tercatat karena FK ON DELETE SET NULL)
        $delete_query = "DELETE FROM voucher_diskon WHERE voucher_id = ? AND user_id_penjual = ?";
        $delete_stmt = mysqli_prepare($koneksi, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "ii", $voucher_id, $user_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            header("Location: kelola_voucher.php?success=" . urlencode("Voucher berhasil dihapus!"));
            exit();
        } else {
            header("Location: kelola_voucher.php?error=" . urlencode("Gagal menghapus voucher"));
            exit();
        }
    } else {
        header("Location: kelola_voucher.php?error=" . urlencode("Voucher tidak ditemukan"));
        exit();
    }
} else {
    header("Location: kelola_voucher.php?error=" . urlencode("ID voucher tidak valid"));
    exit();
}
?>