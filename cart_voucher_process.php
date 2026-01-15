<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// APPLY VOUCHER
if ($action == 'apply') {
    $voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));
    
    if (empty($voucher_code)) {
        echo json_encode(['success' => false, 'message' => 'Kode voucher tidak boleh kosong']);
        exit();
    }
    
    // Ambil seller dari keranjang (pastikan semua produk dari 1 seller)
    $query_cart = "SELECT DISTINCT p.user_id as seller_id FROM keranjang k 
                   JOIN produk p ON k.produk_id = p.produk_id 
                   WHERE k.user_id = ?";
    $stmt_cart = mysqli_prepare($koneksi, $query_cart);
    mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
    mysqli_stmt_execute($stmt_cart);
    $result_cart = mysqli_stmt_get_result($stmt_cart);
    
    $seller_ids = [];
    while ($row = mysqli_fetch_assoc($result_cart)) {
        $seller_ids[] = $row['seller_id'];
    }
    
    if (empty($seller_ids)) {
        echo json_encode(['success' => false, 'message' => 'Keranjang Anda kosong']);
        exit();
    }
    
    if (count($seller_ids) != 1) {
        echo json_encode(['success' => false, 'message' => 'Voucher hanya bisa digunakan untuk produk dari 1 toko. Pisahkan checkout per toko.']);
        exit();
    }
    
    $seller_id = $seller_ids[0];
    
    // Validasi voucher
    $query_voucher = "SELECT * FROM voucher_diskon 
                     WHERE kode_voucher = ? 
                     AND user_id_penjual = ?
                     AND is_active = 1 
                     AND NOW() BETWEEN tanggal_mulai AND tanggal_berakhir
                     AND (kuota_total IS NULL OR kuota_terpakai < kuota_total)";
    $stmt_voucher = mysqli_prepare($koneksi, $query_voucher);
    mysqli_stmt_bind_param($stmt_voucher, "si", $voucher_code, $seller_id);
    mysqli_stmt_execute($stmt_voucher);
    $result_voucher = mysqli_stmt_get_result($stmt_voucher);
    
    if ($voucher = mysqli_fetch_assoc($result_voucher)) {
        // Hitung total belanja
        $query_total = "SELECT SUM(k.jumlah * p.harga_grosir) as total 
                       FROM keranjang k 
                       JOIN produk p ON k.produk_id = p.produk_id 
                       WHERE k.user_id = ?";
        $stmt_total = mysqli_prepare($koneksi, $query_total);
        mysqli_stmt_bind_param($stmt_total, "i", $user_id);
        mysqli_stmt_execute($stmt_total);
        $cart_total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total))['total'] ?? 0;
        
        // Validasi minimal pembelian
        if ($cart_total < $voucher['min_pembelian']) {
            $min_format = number_format($voucher['min_pembelian'], 0, ',', '.');
            $current_format = number_format($cart_total, 0, ',', '.');
            echo json_encode([
                'success' => false, 
                'message' => "Minimal pembelian Rp {$min_format}. Belanja Anda: Rp {$current_format}"
            ]);
            exit();
        }
        
        // Hitung diskon untuk preview
        $diskon_amount = 0;
        if ($voucher['tipe_diskon'] == 'persentase') {
            $diskon_amount = ($cart_total * $voucher['nilai_diskon']) / 100;
            if ($voucher['max_diskon'] && $diskon_amount > $voucher['max_diskon']) {
                $diskon_amount = $voucher['max_diskon'];
            }
        } else {
            $diskon_amount = $voucher['nilai_diskon'];
        }
        
        if ($diskon_amount > $cart_total) {
            $diskon_amount = $cart_total;
        }
        
        // Apply voucher
        $_SESSION['applied_voucher_code'] = $voucher_code;
        $_SESSION['applied_voucher_id'] = $voucher['voucher_id'];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Voucher berhasil diterapkan!',
            'diskon_amount' => $diskon_amount,
            'diskon_formatted' => number_format($diskon_amount, 0, ',', '.')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kode voucher tidak valid, sudah kadaluarsa, atau tidak berlaku untuk toko ini']);
    }
    exit();
}

// REMOVE VOUCHER
if ($action == 'remove') {
    unset($_SESSION['applied_voucher_code']);
    unset($_SESSION['applied_voucher_id']);
    
    echo json_encode(['success' => true, 'message' => 'Voucher berhasil dihapus']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>