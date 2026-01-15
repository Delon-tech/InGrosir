<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id_pembeli = $_SESSION['user_id'];
$error = '';
$pesanan_per_penjual = [];
$produk_data = [];
$selected_produk_ids = [];
$total_all_orders = 0;

// Menangani permintaan dari cart.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selected_items'])) {
    $_SESSION['selected_items'] = $_POST['selected_items'];
    $_SESSION['checkout_timestamp'] = time();
}

// Validasi session data
if (!isset($_SESSION['selected_items']) || empty($_SESSION['selected_items'])) {
    header("Location: cart.php?error=" . urlencode("Pilih produk untuk checkout"));
    exit();
}

$selected_produk_ids = $_SESSION['selected_items'];
// ============================================
// VOUCHER SYSTEM - Load Applied Voucher
// ============================================
$voucher_applied = null;
$voucher_discount = 0;
$voucher_id = null;
if (isset($_SESSION['applied_voucher_code'])) {
    $kode_voucher = $_SESSION['applied_voucher_code'];
    
    // Re-validate voucher
    $query_voucher = "SELECT * FROM voucher_diskon 
                     WHERE kode_voucher = ? 
                     AND is_active = 1 
                     AND NOW() BETWEEN tanggal_mulai AND tanggal_berakhir
                     AND (kuota_total IS NULL OR kuota_terpakai < kuota_total)";
    $stmt_voucher = mysqli_prepare($koneksi, $query_voucher);
    mysqli_stmt_bind_param($stmt_voucher, "s", $kode_voucher);
    mysqli_stmt_execute($stmt_voucher);
    $result_voucher = mysqli_stmt_get_result($stmt_voucher);
    
    if ($voucher_data = mysqli_fetch_assoc($result_voucher)) {
        $voucher_applied = $voucher_data;
        $voucher_id = $voucher_data['voucher_id'];
    } else {
        // Voucher tidak valid, hapus dari session
        unset($_SESSION['applied_voucher_code']);
        unset($_SESSION['applied_voucher_id']);
    }
}

// Ambil data dari database
$produk_ids_placeholder = implode(',', array_fill(0, count($selected_produk_ids), '?'));
$query_cart = "SELECT k.produk_id, k.jumlah, p.user_id, p.nama_produk, p.harga_grosir, p.stok, p.gambar_produk
               FROM keranjang k 
               JOIN produk p ON k.produk_id = p.produk_id 
               WHERE k.user_id = ? AND k.produk_id IN ($produk_ids_placeholder)";
    
$stmt_cart = mysqli_prepare($koneksi, $query_cart);
$types = 'i' . str_repeat('i', count($selected_produk_ids));
$params = array_merge([$user_id_pembeli], $selected_produk_ids);
mysqli_stmt_bind_param($stmt_cart, $types, ...$params);
mysqli_stmt_execute($stmt_cart);
$result_cart = mysqli_stmt_get_result($stmt_cart);

$cart_items = [];
$seller_ids = [];
while ($row = mysqli_fetch_assoc($result_cart)) {
    $cart_items[$row['produk_id']] = $row;
    $produk_data[$row['produk_id']] = $row;
    $seller_ids[] = $row['user_id'];
}

// Deduplikasi seller IDs
$seller_ids = array_unique($seller_ids);

// Validasi: pastikan semua produk yang dipilih masih ada di keranjang
$missing_products = array_diff($selected_produk_ids, array_keys($cart_items));
if (!empty($missing_products)) {
    unset($_SESSION['selected_items']);
    header("Location: cart.php?error=" . urlencode("Beberapa produk tidak lagi tersedia di keranjang"));
    exit();
}

if (empty($cart_items)) {
    unset($_SESSION['selected_items']);
    header("Location: cart.php?error=" . urlencode("Tidak ada produk yang dipilih"));
    exit();
}

// Ambil alamat pengiriman user
$query_alamat = "SELECT * FROM alamat_pengiriman WHERE user_id = ? ORDER BY is_default DESC, alamat_id DESC";
$stmt_alamat = mysqli_prepare($koneksi, $query_alamat);
mysqli_stmt_bind_param($stmt_alamat, "i", $user_id_pembeli);
mysqli_stmt_execute($stmt_alamat);
$result_alamat = mysqli_stmt_get_result($stmt_alamat);
$alamat_list = mysqli_fetch_all($result_alamat, MYSQLI_ASSOC);

// Ambil metode pembayaran yang AKTIF dari SEMUA penjual terkait
$seller_ids_placeholder = implode(',', array_fill(0, count($seller_ids), '?'));
$query_metode = "SELECT mpp.metode_penjual_id, mpp.user_id as penjual_id, 
                 mpt.template_id, mpt.nama_metode, mpt.icon, mpt.deskripsi, mpt.tipe_metode,
                 mpp.account_number, mpp.account_name, mpp.bank_name, mpp.qr_image
                 FROM metode_pembayaran_penjual mpp
                 JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                 WHERE mpp.user_id IN ($seller_ids_placeholder) 
                 AND mpp.is_active = 1
                 ORDER BY mpt.sort_order";

$stmt_metode = mysqli_prepare($koneksi, $query_metode);
$metode_types = str_repeat('i', count($seller_ids));
mysqli_stmt_bind_param($stmt_metode, $metode_types, ...$seller_ids);
mysqli_stmt_execute($stmt_metode);
$result_metode = mysqli_stmt_get_result($stmt_metode);

// Kelompokkan metode per template
$metode_per_seller = [];
$all_templates = [];
while ($row = mysqli_fetch_assoc($result_metode)) {
    $metode_per_seller[$row['penjual_id']][$row['template_id']] = $row;
    $all_templates[$row['template_id']] = $row;
}

// Filter: hanya tampilkan metode yang tersedia di SEMUA seller
$common_methods = [];
if (!empty($seller_ids)) {
    $first_seller = reset($metode_per_seller);
    if ($first_seller) {
        foreach ($first_seller as $template_id => $method) {
            $available_in_all = true;
            foreach ($seller_ids as $seller_id) {
                if (!isset($metode_per_seller[$seller_id][$template_id])) {
                    $available_in_all = false;
                    break;
                }
            }
            if ($available_in_all) {
                $common_methods[$template_id] = $all_templates[$template_id];
            }
        }
    }
}

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_method'])) {
    $template_id = intval($_POST['payment_method']);
    $metode_pengiriman = $_POST['metode_pengiriman'] ?? 'kurir';
    $alamat_id = ($metode_pengiriman == 'kurir' && !empty($_POST['alamat_id'])) ? intval($_POST['alamat_id']) : null;
    $catatan_pengiriman = $_POST['catatan_pengiriman'] ?? '';

    // Validasi metode pengiriman
    if ($metode_pengiriman == 'kurir' && !$alamat_id) {
        $error = "Pilih alamat pengiriman terlebih dahulu!";
    }

    // Validasi timestamp
    if (!$error && (!isset($_SESSION['checkout_timestamp']) || (time() - $_SESSION['checkout_timestamp']) > 600)) {
        $error = "Sesi checkout telah berakhir. Silakan ulangi dari keranjang.";
    }
    
    if (!$error) {
        // Re-fetch data terbaru
        mysqli_stmt_execute($stmt_cart);
        $result_cart_fresh = mysqli_stmt_get_result($stmt_cart);
        
        $fresh_cart_items = [];
        while ($row = mysqli_fetch_assoc($result_cart_fresh)) {
            $fresh_cart_items[$row['produk_id']] = $row;
        }
        
        // Validasi stok dengan data terbaru
        foreach ($fresh_cart_items as $produk_id => $item) {
            if ($item['jumlah'] > $item['stok']) {
                $error = "Stok " . $item['nama_produk'] . " tidak mencukupi. Tersedia: " . $item['stok'];
                break;
            }
        }
        
        if (!$error) {
            $cart_items = $fresh_cart_items;
            
            // Kelompokkan per penjual
            $pesanan_per_penjual = [];
            foreach ($cart_items as $produk_id => $item) {
                $subtotal = $item['jumlah'] * $item['harga_grosir'];
                $penjual_id = $item['user_id'];
                
                if (!isset($pesanan_per_penjual[$penjual_id])) {
                    $pesanan_per_penjual[$penjual_id] = [
                        'total_harga' => 0,
                        'items' => []
                    ];
                }
                $pesanan_per_penjual[$penjual_id]['total_harga'] += $subtotal;
                $pesanan_per_penjual[$penjual_id]['items'][] = [
                    'produk_id' => $produk_id,
                    'nama_produk' => $item['nama_produk'],
                    'jumlah' => $item['jumlah'],
                    'harga_per_unit' => $item['harga_grosir']
                ];
                $total_all_orders += $subtotal;
                }

                // --- PERBAIKAN: RESET TOTAL SEBELUM HITUNG ULANG ---
                $total_all_orders = 0; 
                // ---------------------------------------------------

            mysqli_begin_transaction($koneksi);
            try {
                $order_ids = [];
                
            foreach ($pesanan_per_penjual as $penjual_id => $data_pesanan) {
                    $subtotal_pesanan = $data_pesanan['total_harga'];
                    $order_id_gateway = 'INGROSIR-' . uniqid() . '-' . $penjual_id;
                    $order_ids[] = $order_id_gateway;
                    // Hitung diskon voucher jika ada
                    $diskon_voucher = 0;
                    if ($voucher_applied && $voucher_applied['user_id_penjual'] == $penjual_id) {
                        if ($subtotal_pesanan >= $voucher_applied['min_pembelian']) {
                            if ($voucher_applied['tipe_diskon'] == 'persentase') {
                                $diskon_voucher = ($subtotal_pesanan * $voucher_applied['nilai_diskon']) / 100;
                                
                                // Apply max_diskon
                                if ($voucher_applied['max_diskon'] && $diskon_voucher > $voucher_applied['max_diskon']) {
                                    $diskon_voucher = $voucher_applied['max_diskon'];
                                }
                            } else {
                                $diskon_voucher = $voucher_applied['nilai_diskon'];
                            }
                            
                            // Pastikan diskon tidak melebihi total
                            if ($diskon_voucher > $subtotal_pesanan) {
                                $diskon_voucher = $subtotal_pesanan;
                            }
                        }
                    }
                    
                    $total_after_discount = $subtotal_pesanan - $diskon_voucher;
                    
                    // Update total_all_orders
                    $total_all_orders += $total_after_discount;

                    $metode_penjual_id = $metode_per_seller[$penjual_id][$template_id]['metode_penjual_id'] ?? null;
                    
                    if (!$metode_penjual_id) {
                        throw new Exception("Metode pembayaran tidak tersedia untuk penjual ini");
                    }

                    // Insert pesanan
                    $query_pesanan = "INSERT INTO pesanan (user_id_pembeli, user_id_penjual, tanggal_pesanan, 
                    status_pesanan, payment_status, transaction_id, total_harga, is_notified, 
                    metode_penjual_id, metode_pengiriman, alamat_id, catatan_pengiriman,
                    voucher_id, kode_voucher, diskon_voucher) 
                    VALUES (?, ?, NOW(), 'pending', 'waiting_for_payment', ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
                    $kode_voucher_db = $voucher_applied['kode_voucher'] ?? null;

                    mysqli_stmt_bind_param($stmt_pesanan, "iisdisisisd", 
                        $user_id_pembeli, 
                        $penjual_id, 
                        $order_id_gateway, 
                        $total_after_discount,  
                        $metode_penjual_id,
                        $metode_pengiriman,
                        $alamat_id,
                        $catatan_pengiriman,
                        $voucher_id,
                        $kode_voucher_db,
                        $diskon_voucher
                    );
                                    
                    if (!mysqli_stmt_execute($stmt_pesanan)) {
                        throw new Exception("Gagal membuat pesanan");
                    }
                    $pesanan_id_baru = mysqli_insert_id($koneksi);

                    // Insert detail pesanan
                    $query_detail = "INSERT INTO detail_pesanan (pesanan_id, produk_id, jumlah, harga_per_unit) VALUES (?, ?, ?, ?)";
                    $stmt_detail = mysqli_prepare($koneksi, $query_detail);

                    foreach ($data_pesanan['items'] as $item) {
                        mysqli_stmt_bind_param($stmt_detail, "iiid", $pesanan_id_baru, $item['produk_id'], $item['jumlah'], $item['harga_per_unit']);
                        if (!mysqli_stmt_execute($stmt_detail)) {
                            throw new Exception("Gagal menyimpan detail pesanan");
                        }

                        // Update stok
                        $query_stok = "UPDATE produk SET stok = stok - ? WHERE produk_id = ? AND stok >= ?";
                        $stmt_stok = mysqli_prepare($koneksi, $query_stok);
                        mysqli_stmt_bind_param($stmt_stok, "iii", $item['jumlah'], $item['produk_id'], $item['jumlah']);
                        if (!mysqli_stmt_execute($stmt_stok) || mysqli_affected_rows($koneksi) === 0) {
                            throw new Exception("Stok produk tidak mencukupi");
                        }
                    }
                }
                
                // NOTIFIKASI: Kirim ke penjual
                require_once 'includes/notification_helper.php';
                kirim_notifikasi(
                    $penjual_id, 
                    '🆕 Pesanan Baru Masuk!', 
                    "Anda mendapat pesanan baru #$pesanan_id_baru dari " . $_SESSION['nama_lengkap'] . " senilai Rp " . number_format($data_pesanan['total_harga'], 0, ',', '.'),
                    "detail_pesanan.php?id=$pesanan_id_baru",
                    'shopping-cart'
                );

                // Update kuota voucher jika digunakan
                if ($voucher_id) {
                    $update_voucher = "UPDATE voucher_diskon SET kuota_terpakai = kuota_terpakai + 1 WHERE voucher_id = ?";
                    $stmt_update = mysqli_prepare($koneksi, $update_voucher);
                    mysqli_stmt_bind_param($stmt_update, "i", $voucher_id);
                    mysqli_stmt_execute($stmt_update);
                }

                mysqli_commit($koneksi);
                
                // Hapus dari keranjang
                $query_delete = "DELETE FROM keranjang WHERE user_id = ? AND produk_id IN ($produk_ids_placeholder)";
                $stmt_delete = mysqli_prepare($koneksi, $query_delete);
                mysqli_stmt_bind_param($stmt_delete, $types, ...$params);
                mysqli_stmt_execute($stmt_delete);
                
                // Clear session
                unset($_SESSION['selected_items']);
                unset($_SESSION['checkout_timestamp']);
                unset($_SESSION['applied_voucher_code']);
                unset($_SESSION['applied_voucher_id']);

                // Redirect ke payment instructions
                $order_ids_param = implode(',', $order_ids);
                header("Location: payment_instructions.php?order_id=" . urlencode($order_ids_param) . "&template=" . $template_id . "&total=" . $total_all_orders);
                exit();

            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = "Gagal memproses checkout: " . $e->getMessage();
            }
        }
    }
}

// Kelompokkan per penjual untuk tampilan
if (!$error || $_SERVER['REQUEST_METHOD'] == 'POST') {
    $pesanan_per_penjual = [];
    $total_all_orders = 0; // Reset total
    $voucher_discount_total = 0; // Variabel baru untuk total semua diskon

    // 1. Kelompokkan item per penjual
    foreach ($cart_items as $produk_id => $item) {
        $subtotal = $item['jumlah'] * $item['harga_grosir'];
        $penjual_id = $item['user_id'];
        
        if (!isset($pesanan_per_penjual[$penjual_id])) {
            $query_penjual = "SELECT nama_grosir, alamat_grosir FROM users WHERE user_id = ?";
            $stmt_penjual = mysqli_prepare($koneksi, $query_penjual);
            mysqli_stmt_bind_param($stmt_penjual, "i", $penjual_id);
            mysqli_stmt_execute($stmt_penjual);
            $penjual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_penjual));
            
            $pesanan_per_penjual[$penjual_id] = [
                'nama_grosir' => $penjual['nama_grosir'] ?? 'Unknown',
                'alamat_grosir' => $penjual['alamat_grosir'] ?? '',
                'total_harga' => 0,
                'diskon_voucher' => 0, // Inisialisasi diskon per toko
                'items' => []
            ];
        }
        $pesanan_per_penjual[$penjual_id]['total_harga'] += $subtotal;
        $pesanan_per_penjual[$penjual_id]['items'][] = [
            'produk_id' => $produk_id,
            'nama_produk' => $item['nama_produk'],
            'jumlah' => $item['jumlah'],
            'harga_per_unit' => $item['harga_grosir'],
            'subtotal' => $subtotal,
            'gambar_produk' => $item['gambar_produk'] ?? null
        ];
    }

    // 2. Hitung Diskon dan Total Akhir
    foreach ($pesanan_per_penjual as $pid => &$data) {
        $subtotal_toko = $data['total_harga'];
        $diskon_toko = 0;

        // Cek apakah voucher berlaku untuk toko ini
        if ($voucher_applied && $voucher_applied['user_id_penjual'] == $pid) {
            if ($subtotal_toko >= $voucher_applied['min_pembelian']) {
                if ($voucher_applied['tipe_diskon'] == 'persentase') {
                    $diskon_toko = ($subtotal_toko * $voucher_applied['nilai_diskon']) / 100;
                    // Cek Max Diskon
                    if ($voucher_applied['max_diskon'] && $diskon_toko > $voucher_applied['max_diskon']) {
                        $diskon_toko = $voucher_applied['max_diskon'];
                    }
                } else {
                    $diskon_toko = $voucher_applied['nilai_diskon'];
                }
                
                // Jangan sampai diskon melebihi harga
                if ($diskon_toko > $subtotal_toko) {
                    $diskon_toko = $subtotal_toko;
                }
            }
        }

        // Simpan diskon ke array data toko
        $data['diskon_voucher'] = $diskon_toko;
        
        // Update total global
        $voucher_discount_total += $diskon_toko;
        $total_all_orders += ($subtotal_toko - $diskon_toko);
    }
    // Hapus referensi pointer
    unset($data);
}

function getPaymentIcon($tipe_metode) {
    switch($tipe_metode) {
        case 'transfer_bank': return 'fa-university';
        case 'qris': return 'fa-qrcode';
        case 'ewallet': return 'fa-wallet';
        case 'cod': return 'fa-hand-holding-usd';
        default: return 'fa-credit-card';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - InGrosir</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-white: #ffffff;
            --bg-gray: #f9fafb;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 300ms ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gray);
            color: var(--text-primary);
        }
        
        /* Header Enhancement */
        .header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            z-index: 1001;
        }

        /* Burger Menu */
        .burger-menu {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
            z-index: 1001;
        }

        .burger-menu:hover {
            background: var(--bg-gray);
        }

        .burger-line {
            width: 25px;
            height: 3px;
            background: var(--text-primary);
            border-radius: 2px;
            transition: var(--transition);
        }

        .burger-menu.active .burger-line:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .burger-menu.active .burger-line:nth-child(2) {
            opacity: 0;
        }

        .burger-menu.active .burger-line:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Navigation Menu */
        .header-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .header-nav a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            transition: var(--transition);
            white-space: nowrap;
        }

        .header-nav a:hover {
            background: var(--bg-gray);
            color: var(--primary);
        }

        .header-nav a i {
            margin-right: 0.5rem;
        }

        /* Mobile Styles - DROPDOWN FROM TOP */
        @media (max-width: 768px) {
            .header {
                position: relative;
            }
            
            .burger-menu {
                display: flex;
            }
            
            .header-nav {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                width: 100%;
                background: white;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 0;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                max-height: 0;
                overflow: hidden;
                opacity: 0;
                transition: max-height 0.4s ease, opacity 0.3s ease, padding 0.3s ease;
                border-top: 1px solid var(--border);
            }
            
            .header-nav.active {
                max-height: 500px;
                opacity: 1;
                padding: 0.5rem 0;
            }
            
            .header-nav a {
                padding: 1rem 1.5rem;
                border-radius: 0;
                border-bottom: 1px solid var(--border);
                display: flex;
                align-items: center;
                font-size: 1rem;
                transition: all 0.2s ease;
            }
            
            .header-nav a:last-child {
                border-bottom: none;
            }
            
            .header-nav a:hover {
                background: var(--bg-gray);
                padding-left: 2rem;
            }
            
            .header-nav a.text-danger:hover {
                background: rgba(239, 68, 68, 0.05);
            }
            
            .header-nav a i {
                width: 24px;
                text-align: center;
            }
            
            /* Overlay - HAPUS ATAU NONAKTIFKAN */
            .nav-overlay {
                display: none !important; /* Nonaktifkan overlay */
            }
        }
        
        /* Breadcrumb */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Checkout Steps */
        .checkout-steps {
            position: relative;
            margin-bottom: 2.5rem;
        }
        
        .checkout-steps::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 20%;
            right: 20%;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }
        
        .step {
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .step-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--border);
            margin: 0 auto 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text-secondary);
            transition: var(--transition);
        }
        
        .step.active .step-circle {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .step.completed .step-circle {
            border-color: var(--secondary);
            background: var(--secondary);
            color: white;
        }
        
        .step-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .step.active .step-label {
            color: var(--primary);
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid var(--border);
            padding: 1.5rem;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .card-header h3 {
            font-size: 1.25rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Seller Section */
        .seller-section {
            background: var(--bg-gray);
            padding: 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.25rem;
            border-left: 4px solid var(--primary);
        }
        
        .seller-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .product-item {
            background: white;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius);
            object-fit: cover;
            background: var(--bg-gray);
            flex-shrink: 0;
        }
        
        .product-details {
            flex: 1;
            min-width: 0;
        }
        
        .product-details h4 {
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .product-price {
            text-align: right;
            font-weight: 700;
            color: var(--secondary);
            white-space: nowrap;
        }
        
        .seller-total {
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--border);
            font-weight: 700;
        }

        /* Shipping Method Styles */
        .shipping-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .shipping-method {
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            text-align: center;
        }

        .shipping-method:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.02);
        }

        .shipping-method input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .shipping-method input[type="radio"]:checked ~ .shipping-icon {
            background: var(--primary);
            color: white;
        }

        .shipping-icon {
            width: 60px;
            height: 60px;
            background: var(--bg-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0 auto 1rem;
            transition: var(--transition);
        }

        .shipping-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .shipping-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Address Section */
        .address-section {
            display: none;
            margin-top: 1.5rem;
        }

        .address-section.active {
            display: block;
        }

        .address-list {
            display: grid;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .address-card {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .address-card:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.02);
        }

        .address-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .address-card input[type="radio"]:checked ~ .address-content {
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .address-label {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .address-default {
            background: var(--secondary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
        }

        .address-name {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .address-phone {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .address-detail {
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--text-secondary);
        }

        .btn-add-address {
            width: 100%;
            padding: 1rem;
            background: white;
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-add-address:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.02);
        }

        /* Store Pickup Section */
        .pickup-section {
            display: none;
            margin-top: 1.5rem;
        }

        .pickup-section.active {
            display: block;
        }

        .store-list {
            display: grid;
            gap: 1rem;
        }

        .store-card {
            background: var(--bg-gray);
            padding: 1.25rem;
            border-radius: var(--radius);
            border-left: 4px solid var(--secondary);
        }

        .store-name {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .store-address {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Notes Section */
        .notes-section {
            margin-top: 1.5rem;
        }

        .notes-section label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .notes-section textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.875rem;
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
        }

        .notes-section textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Payment Methods */
        .payment-methods {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-option {
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .payment-option:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.02);
        }
        
        .payment-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .payment-option input[type="radio"]:checked + .payment-info {
            border-color: var(--primary);
        }
        
        .payment-option input[type="radio"]:checked ~ .check-icon {
            display: flex;
        }
        
        .payment-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .payment-icon {
            width: 50px;
            height: 50px;
            background: var(--bg-gray);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .payment-details {
            flex: 1;
            min-width: 0;
        }
        
        .payment-details h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .payment-details p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .check-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 30px;
            height: 30px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        /* Summary Card */
        .cart-summary {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .cart-summary h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--border);
            margin-top: 1rem;
            padding-top: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .summary-row.total .amount {
            color: var(--secondary);
        }
        
        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }
        
        .btn-primary:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-gray);
            color: var(--text-primary);
        }
        
        /* Alert Animation */
        .alert {
            animation: slideDown 0.4s ease;
            border-radius: var(--radius-lg);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .cart-summary {
                position: static;
                margin-bottom: 2rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 1rem;
            }
            
            .logo {
                font-size: 1.25rem;
            }
            
            .header-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .header-nav a {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            
            .checkout-steps::before {
                left: 10%;
                right: 10%;
            }
            
            .step-circle {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
            
            .step-label {
                font-size: 0.75rem;
            }
            
            .card-header {
                padding: 1rem;
            }
            
            .card-header h3 {
                font-size: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .product-item {
                flex-direction: column;
                text-align: center;
            }
            
            .product-image {
                width: 80px;
                height: 80px;
                margin: 0 auto;
            }
            
            .product-price {
                text-align: center;
                margin-top: 0.5rem;
            }
            
            .shipping-methods {
                grid-template-columns: 1fr;
            }
            
            .address-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn {
                width: 100%;
                padding: 0.875rem 1.5rem;
            }
            
            .summary-row {
                font-size: 0.875rem;
            }
            
            .summary-row.total {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            .step-circle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .step-label {
                font-size: 0.625rem;
            }
            
            .seller-section {
                padding: 1rem;
            }
            
            .product-item {
                padding: 0.75rem;
            }
            
            .shipping-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
            
            .address-card {
                padding: 1rem;
            }
            
            .payment-option {
                padding: 1rem;
            }
            
            .payment-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
        <nav class="header">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-shopping-bag me-2"></i>InGrosir
                </a>
                
                <!-- Burger Menu Button -->
                <div class="burger-menu" id="burgerMenu">
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                </div>
                
                <!-- Navigation Menu -->
                <div class="header-nav" id="navMenu">
                    <a href="index.php">
                        <i class="fas fa-home"></i>
                        <span>Beranda</span>
                    </a>
                    <a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Keranjang</span>
                    </a>
                    <a href="logout.php" class="text-danger">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Keluar</span>
                    </a>
                </div>
            </div>
            
            <!-- Overlay for mobile menu -->
            <div class="nav-overlay" id="navOverlay"></div>
        </nav>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" data-aos="fade-down">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="cart.php">Keranjang</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>

        <!-- Checkout Steps -->
        <div class="row checkout-steps mb-4" data-aos="fade-up">
            <div class="col-4">
                <div class="step completed">
                    <div class="step-circle"><i class="fas fa-check"></i></div>
                    <div class="step-label">Keranjang</div>
                </div>
            </div>
            <div class="col-4">
                <div class="step active">
                    <div class="step-circle"><i class="fas fa-credit-card"></i></div>
                    <div class="step-label">Pembayaran</div>
                </div>
            </div>
            <div class="col-4">
                <div class="step">
                    <div class="step-circle"><i class="fas fa-check-circle"></i></div>
                    <div class="step-label">Selesai</div>
                </div>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if ($error) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" data-aos="fade-up">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <form action="checkout.php" method="POST" id="checkoutForm">
            <div class="row g-4">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Order Summary -->
                    <div class="card" data-aos="fade-up">
                        <div class="card-header">
                            <h3><i class="fas fa-receipt me-2"></i>Ringkasan Pesanan</h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($pesanan_per_penjual as $penjual_id => $data_pesanan) { ?>
                                <div class="seller-section">
                                    <div class="seller-name">
                                        <i class="fas fa-store"></i>
                                        <?php echo htmlspecialchars($data_pesanan['nama_grosir']); ?>
                                    </div>
                                    
                                    <?php foreach ($data_pesanan['items'] as $item) { 
                                        $img_src = !empty($item['gambar_produk']) 
                                            ? "uploads/" . htmlspecialchars($item['gambar_produk']) 
                                            : "https://via.placeholder.com/60x60/667eea/ffffff?text=No+Image";
                                    ?>
                                        <div class="product-item">
                                            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" class="product-image">
                                            
                                            <div class="product-details">
                                                <h4><?php echo htmlspecialchars($item['nama_produk']); ?></h4>
                                                <div class="product-meta">
                                                    <?php echo number_format($item['jumlah']); ?> pcs × Rp <?php echo number_format($item['harga_per_unit'], 0, ',', '.'); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="product-price">
                                                Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    
                                    <div class="seller-total">
                                        Subtotal: <span style="color: var(--secondary);">Rp <?php echo number_format($data_pesanan['total_harga'], 0, ',', '.'); ?></span>
                                    </div>
                                    <?php if ($voucher_applied && $voucher_applied['user_id_penjual'] == $penjual_id && $data_pesanan['diskon_voucher'] > 0) { ?>
                                        <div style="background: rgba(16, 185, 129, 0.1); padding: 0.75rem; border-radius: var(--radius); margin-top: 0.75rem; border-left: 4px solid var(--secondary);">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span style="font-size: 0.875rem; color: var(--secondary); font-weight: 600;">
                                                    <i class="fas fa-ticket-alt me-1"></i>
                                                    Voucher: <?php echo htmlspecialchars($voucher_applied['kode_voucher']); ?>
                                                </span>
                                                <span style="font-size: 0.875rem; color: var(--secondary); font-weight: 700;">
                                                    - Rp <?php echo number_format($data_pesanan['diskon_voucher'], 0, ',', '.'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="card" data-aos="fade-up" data-aos-delay="100">
                        <div class="card-header">
                            <h3><i class="fas fa-truck me-2"></i>Metode Pengiriman</h3>
                        </div>
                        <div class="card-body">
                            <div class="shipping-methods">
                                <label class="shipping-method">
                                    <input type="radio" name="metode_pengiriman" value="kurir" checked onchange="toggleShippingOption()">
                                    <div class="shipping-icon">
                                        <i class="fas fa-shipping-fast"></i>
                                    </div>
                                    <div class="shipping-label">Dikirim ke Alamat</div>
                                    <div class="shipping-desc">Produk akan dikirim ke alamat Anda</div>
                                </label>

                                <label class="shipping-method">
                                    <input type="radio" name="metode_pengiriman" value="ambil_sendiri" onchange="toggleShippingOption()">
                                    <div class="shipping-icon">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <div class="shipping-label">Ambil di Toko</div>
                                    <div class="shipping-desc">Ambil langsung di lokasi toko</div>
                                </label>
                            </div>

                            <!-- Address Selection -->
                            <div class="address-section active" id="addressSection">
                                <h5 class="mb-3">
                                    <i class="fas fa-map-marker-alt me-2"></i>Pilih Alamat Pengiriman
                                </h5>
                                
                                <?php if (!empty($alamat_list)) { ?>
                                    <div class="address-list">
                                        <?php foreach ($alamat_list as $index => $alamat) { ?>
                                            <label class="address-card">
                                                <input type="radio" name="alamat_id" value="<?php echo $alamat['alamat_id']; ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                                <div class="address-content">
                                                    <div class="address-header">
                                                        <span class="address-label">
                                                            <i class="fas fa-home me-1"></i><?php echo htmlspecialchars($alamat['label_alamat']); ?>
                                                        </span>
                                                        <?php if ($alamat['is_default'] == 1) { ?>
                                                            <span class="address-default">Utama</span>
                                                        <?php } ?>
                                                    </div>
                                                    <div class="address-name"><?php echo htmlspecialchars($alamat['nama_penerima']); ?></div>
                                                    <div class="address-phone">
                                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($alamat['nomor_telepon']); ?>
                                                    </div>
                                                    <div class="address-detail">
                                                        <?php echo htmlspecialchars($alamat['alamat_lengkap']); ?><br>
                                                        <?php 
                                                        $location_parts = array_filter([
                                                            $alamat['kelurahan'],
                                                            $alamat['kecamatan'],
                                                            $alamat['kota'],
                                                            $alamat['provinsi'],
                                                            $alamat['kode_pos']
                                                        ]);
                                                        echo htmlspecialchars(implode(', ', $location_parts));
                                                        ?>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Belum ada alamat tersimpan. Silakan tambah alamat terlebih dahulu.
                                    </div>
                                <?php } ?>
                                
                                <a href="kelola_alamat.php?from=checkout" class="btn-add-address" target="_blank">
                                    <i class="fas fa-plus-circle me-2"></i>Tambah Alamat Baru
                                </a>
                            </div>

                            <!-- Store Pickup Info -->
                            <div class="pickup-section" id="pickupSection">
                                <h5 class="mb-3">
                                    <i class="fas fa-store me-2"></i>Lokasi Toko untuk Diambil
                                </h5>
                                
                                <div class="store-list">
                                    <?php foreach ($pesanan_per_penjual as $penjual_id => $data_pesanan) { ?>
                                        <div class="store-card">
                                            <div class="store-name">
                                                <i class="fas fa-store-alt"></i>
                                                <?php echo htmlspecialchars($data_pesanan['nama_grosir']); ?>
                                            </div>
                                            <div class="store-address">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?php echo !empty($data_pesanan['alamat_grosir']) 
                                                    ? htmlspecialchars($data_pesanan['alamat_grosir']) 
                                                    : 'Alamat toko tidak tersedia. Hubungi penjual untuk detail lokasi.'; ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Catatan:</strong> Pastikan Anda mengambil pesanan sesuai jam operasional toko.
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="notes-section">
                                <label for="catatan">
                                    <i class="fas fa-sticky-note me-2"></i>Catatan Pengiriman (Opsional)
                                </label>
                                <textarea 
                                    name="catatan_pengiriman" 
                                    id="catatan" 
                                    class="form-control"
                                    placeholder="Contoh: Tolong kirim pagi hari, Patokan rumah dekat masjid, dll..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="card" data-aos="fade-up" data-aos-delay="200">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card me-2"></i>Pilih Metode Pembayaran</h3>
                        </div>
                        <div class="card-body">
                            <div class="payment-methods">
                                <?php 
                                if (!empty($common_methods)) {
                                    foreach ($common_methods as $template_id => $metode) {
                                        $icon = getPaymentIcon($metode['tipe_metode']);
                                ?>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="<?php echo $template_id; ?>" required>
                                        <div class="payment-info">
                                            <div class="payment-icon">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="payment-details">
                                                <h4><?php echo htmlspecialchars($metode['nama_metode']); ?></h4>
                                                <p><?php echo htmlspecialchars($metode['deskripsi']); ?></p>
                                            </div>
                                        </div>
                                        <div class="check-icon">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    </label>
                                <?php 
                                    }
                                } else { 
                                ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Tidak ada metode pembayaran yang tersedia di semua toko. Silakan hubungi penjual.
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <a href="cart.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-arrow-left me-2"></i>Kembali
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($common_methods)) { ?>
                                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                            <i class="fas fa-lock me-2"></i>Bayar Sekarang
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary" data-aos="fade-up" data-aos-delay="300">
                        <h3>Ringkasan Pembayaran</h3>
                        
                        <div class="summary-row">
                            <span>Jumlah Toko</span>
                            <span><?php echo count($pesanan_per_penjual); ?> toko</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Total Produk</span>
                            <span><?php echo array_sum(array_column($cart_items, 'jumlah')); ?> item</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Subtotal Produk</span>
                            <span id="subtotalBeforeDiscount">Rp <?php echo number_format($total_all_orders + $voucher_discount_total, 0, ',', '.'); ?></span>
                        </div>

                        <?php if ($voucher_applied && $voucher_discount_total > 0) { ?>
                        <div class="summary-row" style="color: var(--secondary);">
                            <span>
                                <i class="fas fa-ticket-alt me-1"></i>
                                Diskon Voucher (<?php echo htmlspecialchars($voucher_applied['kode_voucher']); ?>)
                            </span>
                            <span>- Rp <?php echo number_format($voucher_discount_total, 0, ',', '.'); ?></span>
                        </div>
                        <?php } ?>

                        <div class="summary-row">
                            <span>Ongkos Kirim</span>
                            <span>Rp 0</span>
                        </div>

                        <div class="summary-row total">
                            <span>Total Bayar</span>
                            <span class="amount">Rp <?php echo number_format($total_all_orders, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Pesanan akan diproses setelah pembayaran dikonfirmasi</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 600,
            once: true
        });
       // Burger Menu Toggle
        const burgerMenu = document.getElementById('burgerMenu');
        const navMenu = document.getElementById('navMenu');
        const navOverlay = document.getElementById('navOverlay');

        function toggleMenu() {
            burgerMenu.classList.toggle('active');
            navMenu.classList.toggle('active');
            // HAPUS atau COMMENT baris overlay jika tidak dipakai
            // navOverlay.classList.toggle('active');
            // document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
        }

        burgerMenu.addEventListener('click', toggleMenu);

        // HAPUS atau COMMENT baris ini jika overlay tidak dipakai
        // navOverlay.addEventListener('click', toggleMenu);

        // Close menu when clicking nav links
        document.querySelectorAll('.header-nav a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMenu();
                }
            });
        });

        // Close menu on window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && navMenu.classList.contains('active')) {
                toggleMenu();
            }
        });

        // TAMBAHKAN: Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                const isClickInsideNav = navMenu.contains(e.target);
                const isClickOnBurger = burgerMenu.contains(e.target);
                
                if (!isClickInsideNav && !isClickOnBurger && navMenu.classList.contains('active')) {
                    toggleMenu();
                }
            }
        });

        // Toggle shipping option
        function toggleShippingOption() {
            const addressSection = document.getElementById('addressSection');
            const pickupSection = document.getElementById('pickupSection');
            const selectedMethod = document.querySelector('input[name="metode_pengiriman"]:checked').value;
            
            if (selectedMethod === 'kurir') {
                addressSection.classList.add('active');
                pickupSection.classList.remove('active');
            } else {
                addressSection.classList.remove('active');
                pickupSection.classList.add('active');
            }
        }

        // Handle form submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            const selectedShipping = document.querySelector('input[name="metode_pengiriman"]:checked');
            
            if (!selectedPayment) {
                e.preventDefault();
                alert('⚠️ Pilih metode pembayaran terlebih dahulu!');
                return false;
            }

            // Validasi alamat jika metode kirim
            if (selectedShipping && selectedShipping.value === 'kurir') {
                const selectedAddress = document.querySelector('input[name="alamat_id"]:checked');
                if (!selectedAddress) {
                    e.preventDefault();
                    alert('⚠️ Pilih alamat pengiriman terlebih dahulu!');
                    return false;
                }
            }
            
            // Disable button & show loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses Pembayaran...';
            
            return true;
        });
        
        // Payment option click handler
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.style.borderColor = 'var(--border)';
                    opt.style.background = 'white';
                });
                
                this.style.borderColor = 'var(--primary)';
                this.style.background = 'rgba(37, 99, 235, 0.02)';
            });
        });

        // Shipping method click handler
        document.querySelectorAll('.shipping-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.shipping-method').forEach(m => {
                    m.style.borderColor = 'var(--border)';
                    m.style.background = 'white';
                });
                
                this.style.borderColor = 'var(--primary)';
                this.style.background = 'rgba(37, 99, 235, 0.02)';
            });
        });

        // Address card click handler
        document.querySelectorAll('.address-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.address-card').forEach(c => {
                    c.style.borderColor = 'var(--border)';
                    c.style.background = 'white';
                });
                
                this.style.borderColor = 'var(--primary)';
                this.style.background = 'rgba(37, 99, 235, 0.02)';
            });
        });
        
        // Warning before leaving
        let formSubmitted = false;
        
        document.getElementById('checkoutForm').addEventListener('submit', function() {
            formSubmitted = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (!formSubmitted) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>