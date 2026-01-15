<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Notification system (sama seperti index.php)
if (isset($_SESSION['user_id'])) {
    require_once 'includes/notification_helper.php';
    
    // Ambil jumlah notifikasi belum dibaca
    $notif_count = hitung_notifikasi_belum_dibaca($_SESSION['user_id']);
    
    // Ambil 5 notifikasi terbaru untuk dropdown
    $notif_list = ambil_notifikasi_terbaru($_SESSION['user_id'], 5);
}

// Cart count
$cart_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['peran'] == 'pembeli') {
    $cart_query = "SELECT COUNT(*) as total FROM keranjang WHERE user_id = ?";
    $cart_stmt = mysqli_prepare($koneksi, $cart_query);
    mysqli_stmt_bind_param($cart_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($cart_stmt);
    $cart_count = mysqli_fetch_assoc(mysqli_stmt_get_result($cart_stmt))['total'] ?? 0;
}

$produk_di_keranjang = [];
$error_message = isset($_GET['error']) ? $_GET['error'] : '';
$success_message = isset($_GET['success']) ? $_GET['success'] : '';

// Mengambil data dari tabel keranjang
$query_cart = "SELECT k.produk_id, k.jumlah, p.nama_produk, p.harga_grosir, p.stok, p.gambar_produk, u.nama_grosir, u.user_id as seller_id
               FROM keranjang k 
               JOIN produk p ON k.produk_id = p.produk_id 
               JOIN users u ON p.user_id = u.user_id 
               WHERE k.user_id = ?";
$stmt_cart = mysqli_prepare($koneksi, $query_cart);
mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
mysqli_stmt_execute($stmt_cart);
$result_cart = mysqli_stmt_get_result($stmt_cart);

while ($row = mysqli_fetch_assoc($result_cart)) {
    $row['subtotal'] = $row['jumlah'] * $row['harga_grosir'];
    $produk_di_keranjang[] = $row;
}

// ============================================
// VOUCHER SYSTEM - Ambil voucher yang tersedia untuk pembeli
// ============================================
$available_vouchers = [];
if (!empty($produk_di_keranjang)) {
    // Ambil semua seller_id dari keranjang
    $seller_ids = array_unique(array_column($produk_di_keranjang, 'seller_id'));
    
    // Cek apakah semua produk dari 1 seller (untuk voucher)
    $single_seller = (count($seller_ids) == 1);
    
    if ($single_seller) {
        $seller_id = $seller_ids[0];
        
        // Ambil voucher aktif dari seller ini
        $query_voucher = "SELECT * FROM voucher_diskon 
                         WHERE user_id_penjual = ? 
                         AND is_active = 1 
                         AND NOW() BETWEEN tanggal_mulai AND tanggal_berakhir
                         AND (kuota_total IS NULL OR kuota_terpakai < kuota_total)
                         ORDER BY nilai_diskon DESC";
        $stmt_voucher = mysqli_prepare($koneksi, $query_voucher);
        mysqli_stmt_bind_param($stmt_voucher, "i", $seller_id);
        mysqli_stmt_execute($stmt_voucher);
        $result_voucher = mysqli_stmt_get_result($stmt_voucher);
        
        while ($voucher = mysqli_fetch_assoc($result_voucher)) {
            $available_vouchers[] = $voucher;
        }
    }
}

// Validasi voucher jika ada session
$voucher_applied = null;
$voucher_discount = 0;
$voucher_error = '';

if (isset($_SESSION['applied_voucher_code'])) {
    $kode_voucher = $_SESSION['applied_voucher_code'];
    
    // Validasi ulang voucher
    $query_check = "SELECT * FROM voucher_diskon 
                   WHERE kode_voucher = ? 
                   AND is_active = 1 
                   AND NOW() BETWEEN tanggal_mulai AND tanggal_berakhir
                   AND (kuota_total IS NULL OR kuota_terpakai < kuota_total)";
    $stmt_check = mysqli_prepare($koneksi, $query_check);
    mysqli_stmt_bind_param($stmt_check, "s", $kode_voucher);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if ($voucher_data = mysqli_fetch_assoc($result_check)) {
        // Hitung total untuk validasi minimal pembelian
        $cart_total = 0;
        foreach ($produk_di_keranjang as $item) {
            $cart_total += $item['subtotal'];
        }
        
        if ($cart_total >= $voucher_data['min_pembelian']) {
            $voucher_applied = $voucher_data;
            
            // Hitung diskon
            if ($voucher_data['tipe_diskon'] == 'persentase') {
                $voucher_discount = ($cart_total * $voucher_data['nilai_diskon']) / 100;
                
                // Apply max_diskon jika ada
                if ($voucher_data['max_diskon'] && $voucher_discount > $voucher_data['max_diskon']) {
                    $voucher_discount = $voucher_data['max_diskon'];
                }
            } else {
                $voucher_discount = $voucher_data['nilai_diskon'];
            }
        } else {
            $voucher_error = 'Minimal pembelian Rp ' . number_format($voucher_data['min_pembelian'], 0, ',', '.');
            unset($_SESSION['applied_voucher_code']);
        }
    } else {
        $voucher_error = 'Voucher tidak valid atau sudah kadaluarsa';
        unset($_SESSION['applied_voucher_code']);
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - InGrosir</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --bg-white: #ffffff;
            --bg-gray: #f3f4f6;
            --bg-light: #f9fafb;
            --bg-dark: #111827;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-gray);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* ============================================
           HEADER - UNIFIED WITH INDEX.PHP
           ============================================ */
        .header {
            background: var(--bg-white);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }
        
        .header.scrolled { 
            box-shadow: var(--shadow-lg);
        }
        
        .header-top {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.625rem 0;
            font-size: 0.8125rem;
        }
        
        .header-top-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-top-links {
            display: flex;
            gap: 1.75rem;
            align-items: center;
        }
        
        .header-top-links a {
            color: rgba(255, 255, 255, 0.9);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
            text-decoration: none;
        }
        
        .header-top-links a:hover { 
            color: white;
            transform: translateY(-1px);
        }
        
        .header-main {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2.5rem;
            align-items: center;
        }
        
        .logo {
            font-size: 1.75rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            transition: var(--transition);
            letter-spacing: -0.5px;
            text-decoration: none;
        }
        
        .logo:hover { transform: scale(1.05); }
        
        .logo i { 
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header-search {
            max-width: 650px;
            position: relative;
        }
        
        .search-box {
            display: flex;
            background: var(--bg-gray);
            border-radius: var(--radius-lg);
            border: 2px solid transparent;
            transition: var(--transition);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .search-box:focus-within {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .search-box input {
            flex: 1;
            padding: 1rem 1.25rem;
            border: none;
            background: transparent;
            font-size: 0.9375rem;
            color: var(--text-primary);
        }
        
        .search-box input:focus { outline: none; }
        .search-box input::placeholder { color: var(--text-light); }
        
        .search-btn {
            padding: 0 1.75rem;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-btn:hover { 
            background: var(--primary-dark);
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .header-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            padding: 0.625rem 1rem;
            border-radius: var(--radius);
            transition: var(--transition);
            position: relative;
            cursor: pointer;
            color: var(--text-secondary);
            text-decoration: none;
        }
        
        .header-action:hover {
            background: var(--bg-light);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .header-action i { font-size: 1.5rem; }
        .header-action span { 
            font-size: 0.75rem; 
            font-weight: 600;
        }
        .header-action-logout {
            color: var(--danger) !important;
            border-top: 1px solid var(--border-light);
            margin-top: 0.5rem;
            padding-top: 1rem !important;
        }

        .header-action-logout:hover {
            background: rgba(239, 68, 68, 0.1) !important;
            color: var(--danger) !important;
        }

        /* Desktop: Sembunyikan tombol keluar di header-actions */
        @media (min-width: 769px) {
            .header-action-logout {
                display: none;
            }
        }

        /* Mobile: Tampilkan tombol keluar */
        @media (max-width: 768px) {
            .header-action-logout {
                display: flex !important;
            }
        }
        
        .cart-badge, .notification-badge {
            position: absolute;
            top: 0.375rem;
            right: 0.75rem;
            background: var(--danger);
            color: white;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6875rem;
            font-weight: 700;
            box-shadow: var(--shadow);
        }
        
        .mobile-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .mobile-toggle span {
            width: 24px;
            height: 2.5px;
            background: var(--text-primary);
            border-radius: 2px;
            transition: var(--transition);
        }
        
        /* ============================================
           NOTIFICATION DROPDOWN - SAME AS INDEX.PHP
           ============================================ */
        .notification-wrapper {
            position: relative;
        }
        
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 0.75rem);
            right: 0;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            width: 380px;
            max-height: 500px;
            overflow: hidden;
            display: none;
            z-index: 2000;
            border: 1px solid var(--border-light);
        }
        
        .notification-dropdown.active {
            display: block;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notification-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
        }
        
        .notification-header h4 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
        }
        
        .mark-all-read {
            color: var(--primary);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
        }
        
        .mark-all-read:hover {
            background: var(--bg-gray);
        }
        
        .notification-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-light);
            background: white;
        }
        
        .notification-tab {
            flex: 1;
            padding: 0.875rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 2px solid transparent;
        }
        
        .notification-tab:hover {
            background: var(--bg-light);
        }
        
        .notification-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: var(--bg-light);
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .notification-list::-webkit-scrollbar-track {
            background: var(--bg-light);
        }
        
        .notification-list::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }
        
        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
        }
        
        .notification-item:hover {
            background: var(--bg-light);
        }
        
        .notification-item.unread {
            background: rgba(37, 99, 235, 0.05);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }
        
        .notification-text {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 0.375rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        
        .notification-badge-new {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            background: var(--danger);
            color: white;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }
        
        .notification-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .notification-empty i {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .notification-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-light);
            text-align: center;
        }
        
        .notification-footer a {
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .notification-footer a:hover {
            text-decoration: underline;
        }
        
        .nav {
            background: white;
            border-top: 1px solid var(--border-light);
            padding: 0;
            box-shadow: var(--shadow-sm);
        }
        
        .nav-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        
        .nav-content::-webkit-scrollbar { display: none; }
        
        .nav-content a {
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
            font-size: 0.9375rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            text-decoration: none;
        }
        
        .nav-content a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background: var(--primary);
            transition: var(--transition);
        }
        
        .nav-content a:hover {
            color: var(--primary);
            background: var(--bg-light);
        }
        
        .nav-content a:hover::after {
            width: 60%;
        }
        
        /* ============================================
           CART SPECIFIC STYLES
           ============================================ */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .page-header {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .cart-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            transition: var(--transition);
            margin-bottom: 1rem;
        }
        
        .cart-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .cart-item-image {
            border-radius: var(--radius);
            object-fit: cover;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
            padding: 0.25rem;
        }
        
        .qty-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: white;
            border-radius: 6px;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .qty-input {
            width: 60px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 600;
        }
        
        .summary-card {
            position: sticky;
            top: 100px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: var(--transition);
            border-radius: var(--radius);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        
        .btn-outline-danger {
            transition: var(--transition);
        }
        
        .btn-outline-danger:hover {
            transform: scale(1.05);
        }
        
        .empty-cart {
            background: white;
            border-radius: var(--radius-lg);
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .alert {
            animation: slideDown 0.4s ease;
            border-radius: var(--radius-lg);
        }
        
        /* ============================================
           RESPONSIVE - MOBILE OPTIMIZATION
           ============================================ */
        @media (max-width: 768px) {
            .header-top { display: none; }
            
            .header-main {
                grid-template-columns: 1fr auto;
                gap: 1rem;
                padding: 0.875rem 1rem;
                position: relative;
            }
            
            .logo { 
                font-size: 1.5rem; 
                order: 1;
            }
            
            .logo i { font-size: 1.625rem; }
            
            .header-search { 
                display: none; 
            }
            
            .mobile-toggle { 
                display: flex;
                order: 3;
                z-index: 1001;
            }
            
            .header-actions {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: white;
                padding: 1rem;
                box-shadow: var(--shadow-xl);
                display: none;
                flex-direction: column;
                gap: 0.5rem;
                z-index: 999;
                max-height: calc(100vh - 60px);
                overflow-y: auto;
            }
            
            .header-actions.active { 
                display: flex;
            }
            
            .header-action {
                flex-direction: row;
                width: 100%;
                justify-content: flex-start;
                padding: 0.875rem 1rem;
                gap: 0.75rem;
            }
            
            .header-action i {
                font-size: 1.25rem;
            }
            
            .header-action span {
                font-size: 0.875rem;
            }
            
            .notification-wrapper {
                width: 100%;
            }
            
            .notification-dropdown {
                position: relative;
                top: 0;
                right: auto;
                width: 100%;
                margin-top: 0.5rem;
                max-height: 400px;
            }
            
            .notification-dropdown.active {
                display: block;
            }
            
            .nav { 
                display: none; 
            }
            
            .summary-card {
                position: static;
                margin-top: 2rem;
            }
            
            body.mobile-menu-open::before {
                content: '';
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 998;
            }
        }
        
        @media (min-width: 769px) {
            .notification-dropdown {
                display: none;
            }
            
            .notification-wrapper:hover .notification-dropdown {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER - UNIFIED WITH INDEX.PHP -->
    <header class="header" id="header">
        <div class="header-top">
            <div class="header-top-content">
                <div class="header-top-links">
                    <a href="https://wa.me/628971692840"><i class="fas fa-headset"></i> Customer Service</a>
                    <a href="detail_pesanan_pembeli.php"><i class="fas fa-truck"></i> Lacak Pesanan</a>
                </div>
                <div class="header-top-links">
                    <a href="notifications.php"><i class="fas fa-bell"></i> Notifikasi</a>
                    <a href="https://wa.me/628971692840"><i class="fas fa-question-circle"></i> Bantuan</a>
                </div>
            </div>
        </div>
        
        <div class="header-main">
            <a href="index.php" class="logo">
                <i class="fas fa-shopping-bag"></i>
                InGrosir
            </a>
            
            <div class="header-search">
                <form action="index.php" method="GET" class="search-box">
                    <input type="text" name="search" placeholder="Cari produk atau toko...">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        Cari
                    </button>
                </form>
            </div>
            
            <button class="mobile-toggle" onclick="toggleMobileMenu()" type="button" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <div class="header-actions" id="headerActions">
                <a href="cart.php" class="header-action">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Keranjang</span>
                    <?php if ($cart_count > 0) { ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php } ?>
                </a>
                <!-- NOTIFICATION BELL WITH REAL-TIME DROPDOWN -->
                <div class="notification-wrapper">
                    <div class="header-action" onclick="toggleNotifications(event)">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($notif_count > 0) { ?>
                            <span class="notification-badge" id="notificationBadge">
                                <?php echo $notif_count > 99 ? '99+' : $notif_count; ?>
                            </span>
                        <?php } ?>
                    </div>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4>
                                <i class="fas fa-bell"></i>
                                Notifikasi
                                <?php if ($notif_count > 0) { ?>
                                    <span class="badge bg-danger ms-2"><?php echo $notif_count; ?></span>
                                <?php } ?>
                            </h4>
                            <?php if ($notif_count > 0) { ?>
                                <span class="mark-all-read" onclick="markAllRead()">
                                    <i class="fas fa-check-double"></i>
                                    Tandai Semua Dibaca
                                </span>
                            <?php } ?>
                        </div>
                        
                        <div class="notification-tabs">
                            <div class="notification-tab active" data-tab="all">
                                Semua <?php echo $notif_count > 0 ? "($notif_count baru)" : ''; ?>
                            </div>
                            <div class="notification-tab" data-tab="unread">Belum Dibaca</div>
                        </div>
                        
                        <div class="notification-list" id="notificationList">
                            <?php if (empty($notif_list)) { ?>
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>Belum ada notifikasi</p>
                                </div>
                            <?php } else { ?>
                                <?php foreach ($notif_list as $notif) { ?>
                                    <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" 
                                       class="notification-item <?php echo $notif['sudah_dibaca'] ? '' : 'unread'; ?>"
                                       data-id="<?php echo $notif['notification_id']; ?>"
                                       onclick="markAsRead(event, <?php echo $notif['notification_id']; ?>)">
                                        <div class="notification-icon">
                                            <i class="fas <?php echo get_icon_class($notif['icon']); ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                <?php echo htmlspecialchars($notif['judul']); ?>
                                                <?php if (!$notif['sudah_dibaca']) { ?>
                                                    <span class="notification-badge-new">baru</span>
                                                <?php } ?>
                                            </div>
                                            <p class="notification-text">
                                                <?php echo htmlspecialchars($notif['pesan']); ?>
                                            </p>
                                            <span class="notification-time">
                                                <i class="fas fa-clock"></i>
                                                <?php echo format_waktu_relatif($notif['dibuat_pada']); ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        
                        <div class="notification-footer">
                            <a href="notifications.php">
                                Lihat Semua Notifikasi
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <a href="riwayat_pesanan.php" class="header-action">
                    <i class="fas fa-history"></i>
                    <span>Riwayat</span>
                </a>
                <a href="profil.php" class="header-action">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
                <a href="logout.php" class="header-action header-action-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </a>
            </div>
        </div>
        
        <nav class="nav">
            <div class="nav-content">
                <a href="index.php"><i class="fas fa-home"></i> Beranda</a>
                <a href="cart.php" style="color: var(--primary);"><i class="fas fa-shopping-cart"></i> Keranjang</a>
                <a href="riwayat_pesanan.php"><i class="fas fa-history"></i> Riwayat</a>
                <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
                <a href="logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </nav>
    </header>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" data-aos="fade-down">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                <li class="breadcrumb-item active">Keranjang Belanja</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
            <h1><i class="fas fa-shopping-cart text-primary me-2"></i> Keranjang Belanja</h1>
            <p class="text-muted mb-0">Kelola produk yang ingin Anda beli</p>
        </div>

        <!-- Alerts -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($produk_di_keranjang)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart" data-aos="zoom-in">
                <i class="fas fa-shopping-cart"></i>
                <h2 class="mb-3">Keranjang Belanja Kosong</h2>
                <p class="text-muted mb-4">Anda belum menambahkan produk ke keranjang</p>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-store me-2"></i>Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            <form action="checkout.php" method="POST" id="cartForm">
                <!-- Select All Bar -->
                <div class="card mb-3" data-aos="fade-up">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" checked onchange="toggleSelectAll()">
                            <label class="form-check-label fw-bold" for="selectAll">
                                Pilih Semua
                            </label>
                        </div>
                        <span class="text-muted small">
                            <span id="selectedCount"><?php echo count($produk_di_keranjang); ?></span> produk dipilih
                        </span>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <?php foreach ($produk_di_keranjang as $index => $item): ?>
                            <div class="cart-card p-3" data-price="<?php echo $item['harga_grosir']; ?>" data-stock="<?php echo $item['stok']; ?>" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                                <div class="row g-3 align-items-center">
                                    <!-- Checkbox -->
                                    <div class="col-auto">
                                        <input class="form-check-input item-select" type="checkbox" name="selected_items[]" value="<?php echo $item['produk_id']; ?>" checked onchange="updateTotal()">
                                    </div>

                                    <!-- Image -->
                                    <div class="col-auto">
                                        <?php 
                                        $img_src = !empty($item['gambar_produk']) 
                                            ? "uploads/" . htmlspecialchars($item['gambar_produk']) 
                                            : "https://via.placeholder.com/100x100/667eea/ffffff?text=No+Image";
                                        ?>
                                        <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" width="100" height="100" class="cart-item-image">
                                    </div>

                                    <!-- Details -->
                                    <div class="col">
                                        <h5 class="mb-2"><?php echo htmlspecialchars($item['nama_produk']); ?></h5>
                                        <p class="text-muted mb-2 small">
                                            <i class="fas fa-store me-1"></i>
                                            <?php echo htmlspecialchars($item['nama_grosir']); ?>
                                        </p>
                                        <h5 class="text-primary mb-3">
                                            Rp <?php echo number_format($item['harga_grosir'], 0, ',', '.'); ?>
                                        </h5>

                                        <!-- Quantity Control -->
                                        <div class="d-flex flex-wrap gap-3 align-items-center">
                                            <div class="quantity-control">
                                                <button type="button" class="qty-btn" onclick="decreaseQty(this, <?php echo $item['produk_id']; ?>)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="qty-input" value="<?php echo $item['jumlah']; ?>" min="1" max="<?php echo $item['stok']; ?>" readonly data-produk-id="<?php echo $item['produk_id']; ?>">
                                                <button type="button" class="qty-btn" onclick="increaseQty(this, <?php echo $item['produk_id']; ?>, <?php echo $item['stok']; ?>)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>

                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteItem(<?php echo $item['produk_id']; ?>)">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </button>
                                        </div>

                                        <?php if ($item['stok'] < 10): ?>
                                            <div class="alert alert-warning mt-2 mb-0 small">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Stok tinggal <?php echo $item['stok']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($available_vouchers) || $voucher_applied) { ?>
                    <div class="col-12">
                        <div class="card mb-3" data-aos="fade-up" style="border: 2px solid var(--warning); overflow: hidden;">
                            <div class="card-body" style="padding: 0;">
                                <!-- Header Voucher -->
                                <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05)); padding: 1.25rem; border-bottom: 1px solid var(--border);">
                                    <h5 style="margin: 0; display: flex; align-items: center; gap: 0.75rem; color: var(--warning);">
                                        <i class="fas fa-gift" style="font-size: 1.5rem;"></i>
                                        <span style="font-weight: 700;">Voucher Diskon Tersedia</span>
                                    </h5>
                                </div>

                                <div style="padding: 1.25rem;">
                                    <?php if ($voucher_applied) { ?>
                                        <!-- Voucher Applied State -->
                                        <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 2px solid var(--secondary); border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1rem;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                                                <div style="flex: 1; min-width: 200px;">
                                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem;">
                                                        <div style="width: 50px; height: 50px; background: var(--secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                            <i class="fas fa-check" style="font-size: 1.5rem; color: white;"></i>
                                                        </div>
                                                        <div>
                                                            <div style="font-size: 1.5rem; font-weight: 800; color: var(--secondary); letter-spacing: 2px; line-height: 1;">
                                                                <?php echo htmlspecialchars($voucher_applied['kode_voucher']); ?>
                                                            </div>
                                                            <small style="color: var(--text-secondary); font-weight: 500;">
                                                                Voucher diterapkan ✓
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                                        <div style="background: white; padding: 0.5rem 1rem; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                                                            <small style="color: var(--text-secondary); display: block; font-size: 0.7rem; margin-bottom: 0.125rem;">Hemat</small>
                                                            <strong style="color: var(--secondary); font-size: 1.125rem;">Rp <?php echo number_format($voucher_discount, 0, ',', '.'); ?></strong>
                                                        </div>
                                                        <?php if ($voucher_applied['tipe_diskon'] == 'persentase') { ?>
                                                        <div style="background: white; padding: 0.5rem 1rem; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                                                            <small style="color: var(--text-secondary); display: block; font-size: 0.7rem; margin-bottom: 0.125rem;">Diskon</small>
                                                            <strong style="color: var(--primary); font-size: 1.125rem;"><?php echo number_format($voucher_applied['nilai_diskon'], 0); ?>%</strong>
                                                        </div>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                                
                                                <button type="button" class="btn btn-outline-danger" onclick="removeVoucher()" style="flex-shrink: 0;">
                                                    <i class="fas fa-times-circle"></i> Hapus Voucher
                                                </button>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <!-- Input Voucher State -->
                                        <div style="margin-bottom: 1.25rem;">
                                            <label style="font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--text-primary);">
                                                <i class="fas fa-tag me-1"></i>Masukkan Kode Voucher
                                            </label>
                                            <div class="input-group" style="box-shadow: var(--shadow-sm); border-radius: var(--radius);">
                                                <input type="text" id="voucherInput" class="form-control" 
                                                    placeholder="Contoh: DISKON50K" 
                                                    style="text-transform: uppercase; font-weight: 600; font-size: 1rem; border: 2px solid var(--border); padding: 0.75rem 1rem;">
                                                <button class="btn btn-primary" type="button" onclick="applyVoucher()" style="padding: 0.75rem 1.5rem; font-weight: 600;">
                                                    <i class="fas fa-check-circle"></i> Gunakan
                                                </button>
                                            </div>
                                            <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                                <i class="fas fa-info-circle"></i> Masukkan kode voucher dari penjual untuk mendapatkan diskon
                                            </small>
                                        </div>
                                        
                                        <?php if ($voucher_error) { ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: var(--radius); margin-bottom: 1.25rem;">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                <strong>Gagal:</strong> <?php echo htmlspecialchars($voucher_error); ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        <?php } ?>
                                        
                                        <!-- Available Vouchers List -->
                                        <?php if (!empty($available_vouchers)) { ?>
                                            <div style="background: var(--bg-gray); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--warning);">
                                                <p style="font-weight: 600; color: var(--text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                                    <i class="fas fa-sparkles" style="color: var(--warning);"></i>
                                                    Voucher yang tersedia untuk toko ini:
                                                </p>
                                                <div class="row g-2">
                                                    <?php foreach ($available_vouchers as $voucher) { 
                                                        $diskon_text = $voucher['tipe_diskon'] == 'persentase' 
                                                            ? number_format($voucher['nilai_diskon'], 0) . '% OFF' 
                                                            : 'Rp ' . number_format($voucher['nilai_diskon'], 0, ',', '.');
                                                        
                                                        $sisa_kuota = $voucher['kuota_total'] ? ($voucher['kuota_total'] - $voucher['kuota_terpakai']) : null;
                                                    ?>
                                                    <div class="col-md-6">
                                                        <div style="background: white; border: 2px dashed var(--warning); border-radius: var(--radius); padding: 1rem; transition: all 0.3s ease; cursor: pointer;" 
                                                            onclick="quickApplyVoucher('<?php echo $voucher['kode_voucher']; ?>')"
                                                            onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';"
                                                            onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                                            
                                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                                                <div>
                                                                    <div style="font-size: 1.125rem; font-weight: 800; color: var(--primary); letter-spacing: 1px; margin-bottom: 0.25rem;">
                                                                        <?php echo htmlspecialchars($voucher['kode_voucher']); ?>
                                                                    </div>
                                                                    <div style="background: linear-gradient(135deg, var(--warning), #ff9500); color: white; padding: 0.25rem 0.75rem; border-radius: 12px; display: inline-block; font-size: 0.875rem; font-weight: 700;">
                                                                        <?php echo $diskon_text; ?>
                                                                    </div>
                                                                </div>
                                                                <button type="button" class="btn btn-sm btn-warning" 
                                                                        onclick="event.stopPropagation(); quickApplyVoucher('<?php echo $voucher['kode_voucher']; ?>')"
                                                                        style="padding: 0.375rem 0.75rem; font-weight: 600;">
                                                                    Pakai
                                                                </button>
                                                            </div>
                                                            
                                                            <?php if (!empty($voucher['deskripsi'])) { ?>
                                                            <p style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.5rem; line-height: 1.4;">
                                                                <?php echo htmlspecialchars($voucher['deskripsi']); ?>
                                                            </p>
                                                            <?php } ?>
                                                            
                                                            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.75rem; color: var(--text-secondary);">
                                                                <?php if ($voucher['min_pembelian'] > 0) { ?>
                                                                <span>
                                                                    <i class="fas fa-shopping-cart"></i>
                                                                    Min. Rp <?php echo number_format($voucher['min_pembelian'], 0, ',', '.'); ?>
                                                                </span>
                                                                <?php } ?>
                                                                
                                                                <?php if ($voucher['max_diskon']) { ?>
                                                                <span>
                                                                    <i class="fas fa-coins"></i>
                                                                    Max. Rp <?php echo number_format($voucher['max_diskon'], 0, ',', '.'); ?>
                                                                </span>
                                                                <?php } ?>
                                                                
                                                                <?php if ($sisa_kuota) { ?>
                                                                <span style="color: var(--danger); font-weight: 600;">
                                                                    <i class="fas fa-fire"></i>
                                                                    Sisa <?php echo $sisa_kuota; ?> kuota!
                                                                </span>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <style>
                    /* Hover effect untuk voucher cards */
                    .voucher-card-hover {
                        transition: all 0.3s ease;
                    }
                    .voucher-card-hover:hover {
                        transform: scale(1.02);
                        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    }
                    </style>

                    <!-- Summary -->
                    <div class="col-lg-4">
                        <div class="summary-card">
                            <h4 class="mb-4">Ringkasan Belanja</h4>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Produk</span>
                                <span id="totalItems"><?php echo count($produk_di_keranjang); ?> item</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span id="subtotalDisplay">Rp 0</span>
                            </div>
                            
                            <?php if ($voucher_applied && $voucher_discount > 0) { ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>
                                    <i class="fas fa-ticket-alt me-1"></i>
                                    Diskon Voucher
                                </span>
                                <span>- Rp <?php echo number_format($voucher_discount, 0, ',', '.'); ?></span>
                            </div>
                            <?php } ?>

                            <hr>

                            <!-- UBAH Total Harga menjadi: -->
                            <div class="d-flex justify-content-between mb-4">
                                <h5>Total Harga</h5>
                                <h4 class="text-success mb-0" id="totalDisplay">Rp 0</h4>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-2" id="checkoutBtn">
                                <i class="fas fa-shopping-bag me-2"></i>Lanjut ke Checkout
                            </button>
                            
                            <a href="index.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-2"></i>Lanjut Belanja
                            </a>

                            <div class="alert alert-info mt-3 mb-0 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Pilih produk untuk melanjutkan checkout
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // ============================================
        // INITIALIZE AOS ANIMATION
        // ============================================
        AOS.init({
            duration: 600,
            once: true
        });

        // ============================================
        // MOBILE MENU HANDLER (SAME AS INDEX.PHP)
        // ============================================
        function toggleMobileMenu() {
            const actions = document.getElementById('headerActions');
            const toggle = document.querySelector('.mobile-toggle');
            const body = document.body;
            
            if (!actions || !toggle) return;
            
            actions.classList.toggle('active');
            body.classList.toggle('mobile-menu-open');
            
            const spans = toggle.querySelectorAll('span');
            if (actions.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                spans[0].style.transform = '';
                spans[1].style.opacity = '';
                spans[2].style.transform = '';
                
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown) {
                    dropdown.classList.remove('active');
                }
            }
        }

        // ============================================
        // NOTIFICATION SYSTEM (SAME AS INDEX.PHP)
        // ============================================
        function toggleNotifications(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
        }

        function markAsRead(event, notificationId) {
            fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = event.currentTarget;
                    if (item) {
                        item.classList.remove('unread');
                        const badge = item.querySelector('.notification-badge-new');
                        if (badge) badge.remove();
                    }
                    updateNotificationCount();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function markAllRead() {
            if (!confirm('Tandai semua notifikasi sebagai sudah dibaca?')) return;
            
            fetch('api/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelectorAll('.notification-badge-new').forEach(badge => {
                        badge.remove();
                    });
                    updateNotificationCount();
                    alert('✅ Semua notifikasi telah ditandai sebagai dibaca!');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function updateNotificationCount() {
            fetch('api/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notificationBadge');
                    const allTab = document.querySelector('.notification-tab[data-tab="all"]');
                    
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'flex';
                        }
                        if (allTab) {
                            allTab.textContent = `Semua (${data.count} baru)`;
                        }
                    } else {
                        if (badge) badge.style.display = 'none';
                        if (allTab) allTab.textContent = 'Semua';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        document.addEventListener('click', function(event) {
            const notificationWrapper = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (notificationWrapper && dropdown && !notificationWrapper.contains(event.target)) {
                dropdown.classList.remove('active');
            }
            
            const actions = document.getElementById('headerActions');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (!actions || !toggle) return;
            
            if (!actions.contains(event.target) && 
                !toggle.contains(event.target) && 
                (!notificationWrapper || !notificationWrapper.contains(event.target)) &&
                actions.classList.contains('active') &&
                window.innerWidth <= 768) {
                toggleMobileMenu();
            }
        });

        document.querySelectorAll('.notification-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const tabType = this.getAttribute('data-tab');
                const notificationItems = document.querySelectorAll('.notification-item');
                
                notificationItems.forEach(item => {
                    if (tabType === 'all') {
                        item.style.display = 'flex';
                    } else if (tabType === 'unread') {
                        item.style.display = item.classList.contains('unread') ? 'flex' : 'none';
                    }
                });
            });
        });

        setInterval(function() {
            const notifBell = document.querySelector('.notification-wrapper');
            if (notifBell) {
                updateNotificationCount();
            }
        }, 30000);

        // ============================================
        // CART FUNCTIONALITY
        // ============================================
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-select');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateTotal();
        }

        // KODE BARU (AMAN)
        function updateTotal() {
            let total = 0;
            let count = 0;
            
            // Hitung total hanya jika ada item
            document.querySelectorAll('.cart-card').forEach(item => {
                const checkbox = item.querySelector('.item-select');
                const qtyInput = item.querySelector('.qty-input');
                
                // Cek extra agar aman
                if (checkbox && qtyInput) {
                    const price = parseFloat(item.dataset.price);
                    const qty = parseInt(qtyInput.value);
                    
                    if (checkbox.checked) {
                        total += price * qty;
                        count++;
                    }
                }
            });
            
            // --- BAGIAN PENTING: Cek dulu apakah elemennya ada sebelum diisi ---
            
            const elTotalDisplay = document.getElementById('totalDisplay');
            const elTotalItems = document.getElementById('totalItems');
            const elSelectedCount = document.getElementById('selectedCount');

            if (elTotalDisplay) {
                elTotalDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
            }
            
            if (elTotalItems) {
                elTotalItems.textContent = count + ' item';
            }
            
            if (elSelectedCount) {
                elSelectedCount.textContent = count;
            }
        }

        function increaseQty(btn, produkId, maxStock) {
            const input = btn.parentElement.querySelector('.qty-input');
            const current = parseInt(input.value);
            if (current < maxStock) {
                input.value = current + 1;
                updateQuantityInDB(produkId, current + 1);
                updateTotal();
            } else {
                alert('Stok maksimal: ' + maxStock);
            }
        }

        function decreaseQty(btn, produkId) {
            const input = btn.parentElement.querySelector('.qty-input');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
                updateQuantityInDB(produkId, current - 1);
                updateTotal();
            }
        }

        function updateQuantityInDB(produkId, jumlah) {
            fetch('cart_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&produk_id=${produkId}&jumlah=${jumlah}`
            });
        }

        function deleteItem(produkId) {
            if (confirm('Hapus produk dari keranjang?')) {
                window.location.href = 'cart_process.php?action=remove_from_cart&produk_id=' + produkId;
            }
        }

        document.getElementById('cartForm')?.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.item-select:checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('Pilih minimal 1 produk untuk checkout');
            }
        });

        // ============================================
        // HEADER SCROLL EFFECT
        // ============================================
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (header) {
                header.classList.toggle('scrolled', window.pageYOffset > 100);
            }
        });

        // ============================================
        // INITIALIZE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            updateTotal();
            updateNotificationCount();
        });
    </script>
    <script>
    // Voucher Functions
    function applyVoucher() {
        const voucherCode = document.getElementById('voucherInput').value.trim().toUpperCase();
        
        if (!voucherCode) {
            showNotification('❌ Masukkan kode voucher terlebih dahulu!', 'error');
            return;
        }
        
        // Show loading
        const btn = event.target;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        
        // Send request to apply voucher
        fetch('cart_voucher_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=apply&voucher_code=' + encodeURIComponent(voucherCode)
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            
            if (data.success) {
                showNotification('✅ ' + data.message, 'success');
                setTimeout(() => {
                    location.reload(); // Reload untuk apply voucher
                }, 1000);
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            console.error('Error:', error);
            showNotification('❌ Terjadi kesalahan. Silakan coba lagi.', 'error');
        });
    }

    function quickApplyVoucher(code) {
        document.getElementById('voucherInput').value = code;
        // Trigger apply
        const applyBtn = document.querySelector('[onclick="applyVoucher()"]');
        if (applyBtn) {
            applyBtn.click();
        }
    }

    function removeVoucher() {
        if (!confirm('Hapus voucher yang sudah diterapkan?')) return;
        
        fetch('cart_voucher_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remove'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('✅ ' + data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 800);
            } else {
                showNotification('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('❌ Terjadi kesalahan.', 'error');
        });
    }

    // Notification Helper
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelector('.custom-notification');
        if (existing) existing.remove();
        
        const notif = document.createElement('div');
        notif.className = 'custom-notification';
        notif.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            font-weight: 600;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        `;
        notif.textContent = message;
        document.body.appendChild(notif);
        
        setTimeout(() => {
            notif.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notif.remove(), 300);
        }, 3000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // Update fungsi updateTotal() yang sudah ada
    function updateTotal() {
        let total = 0;
        let count = 0;
        
        document.querySelectorAll('.cart-card').forEach(item => {
            const checkbox = item.querySelector('.item-select');
            const qtyInput = item.querySelector('.qty-input');
            
            if (checkbox && qtyInput && checkbox.checked) {
                const price = parseFloat(item.dataset.price);
                const qty = parseInt(qtyInput.value);
                total += price * qty;
                count++;
            }
        });
        
        // Apply voucher discount
        const voucherDiscount = <?php echo $voucher_discount ?? 0; ?>;
        const finalTotal = Math.max(0, total - voucherDiscount);
        
        const elSubtotal = document.getElementById('subtotalDisplay');
        const elTotalDisplay = document.getElementById('totalDisplay');
        const elTotalItems = document.getElementById('totalItems');
        const elSelectedCount = document.getElementById('selectedCount');

        if (elSubtotal) {
            elSubtotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
        }
        
        if (elTotalDisplay) {
            elTotalDisplay.textContent = 'Rp ' + finalTotal.toLocaleString('id-ID');
        }
        
        if (elTotalItems) {
            elTotalItems.textContent = count + ' item';
        }
        
        if (elSelectedCount) {
            elSelectedCount.textContent = count;
        }
    }
    </script>
</body>
</html>