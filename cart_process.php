<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// ADD TO CART
if ($action == 'add_to_cart' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $produk_id = intval($_POST['produk_id']);
    $jumlah = intval($_POST['jumlah']);
    
    // Validasi stok
    $query_stok = "SELECT stok, nama_produk FROM produk WHERE produk_id = ?";
    $stmt_stok = mysqli_prepare($koneksi, $query_stok);
    mysqli_stmt_bind_param($stmt_stok, "i", $produk_id);
    mysqli_stmt_execute($stmt_stok);
    $result_stok = mysqli_stmt_get_result($stmt_stok);
    $produk = mysqli_fetch_assoc($result_stok);
    
    if (!$produk) {
        header("Location: produk_detail.php?id=$produk_id&error=" . urlencode("Produk tidak ditemukan"));
        exit();
    }
    
    if ($jumlah > $produk['stok']) {
        header("Location: produk_detail.php?id=$produk_id&error=" . urlencode("Stok tidak mencukupi. Tersedia: " . $produk['stok']));
        exit();
    }
    
    // Cek apakah produk sudah ada di keranjang
    $query_check = "SELECT jumlah FROM keranjang WHERE user_id = ? AND produk_id = ?";
    $stmt_check = mysqli_prepare($koneksi, $query_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $produk_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Update jumlah
        $row = mysqli_fetch_assoc($result_check);
        $jumlah_baru = $row['jumlah'] + $jumlah;
        
        if ($jumlah_baru > $produk['stok']) {
            header("Location: produk_detail.php?id=$produk_id&error=" . urlencode("Total di keranjang melebihi stok tersedia"));
            exit();
        }
        
        $query_update = "UPDATE keranjang SET jumlah = ? WHERE user_id = ? AND produk_id = ?";
        $stmt_update = mysqli_prepare($koneksi, $query_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $jumlah_baru, $user_id, $produk_id);
        mysqli_stmt_execute($stmt_update);
    } else {
        // Insert baru
        $query_insert = "INSERT INTO keranjang (user_id, produk_id, jumlah) VALUES (?, ?, ?)";
        $stmt_insert = mysqli_prepare($koneksi, $query_insert);
        mysqli_stmt_bind_param($stmt_insert, "iii", $user_id, $produk_id, $jumlah);
        mysqli_stmt_execute($stmt_insert);
    }
    
    header("Location: cart.php?success=" . urlencode("Produk berhasil ditambahkan ke keranjang"));
    exit();
}

// UPDATE QUANTITY
if ($action == 'update_quantity' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $produk_id = intval($_POST['produk_id']);
    $jumlah = intval($_POST['jumlah']);
    
    // Validasi stok
    $query_stok = "SELECT stok FROM produk WHERE produk_id = ?";
    $stmt_stok = mysqli_prepare($koneksi, $query_stok);
    mysqli_stmt_bind_param($stmt_stok, "i", $produk_id);
    mysqli_stmt_execute($stmt_stok);
    $result_stok = mysqli_stmt_get_result($stmt_stok);
    $produk = mysqli_fetch_assoc($result_stok);
    
    if ($jumlah <= $produk['stok'] && $jumlah > 0) {
        $query_update = "UPDATE keranjang SET jumlah = ? WHERE user_id = ? AND produk_id = ?";
        $stmt_update = mysqli_prepare($koneksi, $query_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $jumlah, $user_id, $produk_id);
        mysqli_stmt_execute($stmt_update);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
    }
    exit();
}

// REMOVE FROM CART
if ($action == 'remove_from_cart') {
    $produk_id = intval($_GET['produk_id']);
    
    $query_delete = "DELETE FROM keranjang WHERE user_id = ? AND produk_id = ?";
    $stmt_delete = mysqli_prepare($koneksi, $query_delete);
    mysqli_stmt_bind_param($stmt_delete, "ii", $user_id, $produk_id);
    mysqli_stmt_execute($stmt_delete);
    
    header("Location: cart.php?success=" . urlencode("Produk berhasil dihapus dari keranjang"));
    exit();
}

// CLEAR CART
if ($action == 'clear_cart') {
    $query_clear = "DELETE FROM keranjang WHERE user_id = ?";
    $stmt_clear = mysqli_prepare($koneksi, $query_clear);
    mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
    mysqli_stmt_execute($stmt_clear);
    
    header("Location: cart.php?success=" . urlencode("Keranjang berhasil dikosongkan"));
    exit();
}

// Default: redirect ke cart
header("Location: cart.php");
exit();
?>