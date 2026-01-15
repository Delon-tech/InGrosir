<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();
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

// Search
$search = $_GET['search'] ?? '';

// Query produk - HANYA dari penjual APPROVED
$query_produk = "SELECT p.*, u.nama_grosir, u.user_id as seller_id 
    FROM produk p 
    INNER JOIN users u ON p.user_id = u.user_id 
    WHERE p.is_active = 1 
    AND u.peran = 'penjual' 
    AND u.status_verifikasi = 'approved'";

if (!empty($search)) {
    $query_produk .= " AND (p.nama_produk LIKE ? OR u.nama_grosir LIKE ?)";
}

$query_produk .= " ORDER BY p.produk_id DESC LIMIT 20";

if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $stmt_produk = mysqli_prepare($koneksi, $query_produk);
    mysqli_stmt_bind_param($stmt_produk, "ss", $search_param, $search_param);
    mysqli_stmt_execute($stmt_produk);
    $result_produk = mysqli_stmt_get_result($stmt_produk);
} else {
    $result_produk = mysqli_query($koneksi, $query_produk);
}

// Query toko - HANYA penjual APPROVED
$query_toko = "SELECT u.user_id, u.nama_grosir, u.alamat_grosir, u.gambar_toko, 
    COUNT(DISTINCT p.produk_id) as total_produk
    FROM users u 
    LEFT JOIN produk p ON u.user_id = p.user_id AND p.is_active = 1
    WHERE u.peran = 'penjual' 
    AND u.status_verifikasi = 'approved'";

if (!empty($search)) {
    $query_toko .= " AND u.nama_grosir LIKE ?";
}

$query_toko .= " GROUP BY u.user_id 
    ORDER BY total_produk DESC 
    LIMIT 8";

if (!empty($search)) {
    $stmt_toko = mysqli_prepare($koneksi, $query_toko);
    mysqli_stmt_bind_param($stmt_toko, "s", $search_param);
    mysqli_stmt_execute($stmt_toko);
    $result_toko = mysqli_stmt_get_result($stmt_toko);
} else {
    $result_toko = mysqli_query($koneksi, $query_toko);
}

// Banner carousel data
$banners = [
    ['image' => 'https://www.lalamove.com/hubfs/Cara%20Memulai%20Usaha%20Grosir%20Sembako%20Hingga%20Sukses%20%281%29%20%281%29.jpg', 'alt' => 'Promo Spesial'],
    ['image' => 'https://bajukakilima.com/wp-content/uploads/2021/01/WhatsApp-Image-2019-01-25-at-10.45.22-1-1024x768.jpeg', 'alt' => 'Diskon Besar-besaran'],
    ['image' => 'https://sp-ao.shortpixel.ai/client/to_webp,q_glossy,ret_img,w_2560,h_1340/https://awantoko.co.id/wp-content/uploads/2024/03/Pertimbangkan-Hal-Berikut-Sebelum-Membuka-Cabang-Toko-Grosir-scaled.webp', 'alt' => 'Produk Terlaris']
];

// Testimoni
$testimonials = [
    ['name' => 'Budi Santoso', 'role' => 'Pemilik Toko Sembako', 'text' => 'InGrosir sangat membantu bisnis saya! Harga kompetitif dan pengiriman cepat.', 'rating' => 5],
    ['name' => 'Siti Aminah', 'role' => 'Reseller Fashion', 'text' => 'Platform yang mudah digunakan, produk lengkap, dan pelayanan ramah.', 'rating' => 5],
    ['name' => 'Ahmad Fauzi', 'role' => 'Pemilik Warung', 'text' => 'Sejak pakai InGrosir, keuntungan meningkat 40%. Recommended!', 'rating' => 5]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="InGrosir - Platform Digital UMKM Grosir Indonesia Terpercaya">
    <title>InGrosir - Platform Digital UMKM Grosir Terpercaya</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            --purple: #8b5cf6;
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
        
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }
        
        /* ============================================
           HEADER - FIXED & ENHANCED
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
        
        /* ============================================
           NOTIFICATION DROPDOWN - FIXED
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
           HERO SECTION
           ============================================ */
        .hero {
            background: #ffffff;
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            opacity: 0.5;
        }
        
        .hero-content {
            color: var(--text-primary);
            z-index: 2;
            position: relative;
        }
        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .hero h1 {
            font-size: clamp(2rem, 5vw, 2.75rem);
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.2;
            letter-spacing: -0.5px;
            color: var(--text-primary);
        }
        
        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.125rem);
            margin-bottom: 2rem;
            line-height: 1.6;
            color: var(--text-secondary);
        }

        .hero-feature-card {
            background: white;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .hero-feature-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.12);
            transform: translateY(-2px);
        }

        .hero-feature-card .icon-wrapper {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .hero-feature-card .icon-wrapper i {
            font-size: 1.25rem;
            color: white;
        }

        .hero-feature-card strong {
            display: block;
            color: var(--text-primary);
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
        }

        .hero-feature-card p {
            color: var(--text-secondary);
            font-size: 0.8125rem;
        }

        .carousel-wrapper {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-light);
        }

        .carousel-item img {
            height: 400px;
            object-fit: cover;
        }

        .carousel-control-prev, .carousel-control-next {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: 1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .carousel-control-prev {
            left: 1rem;
        }

        .carousel-control-next {
            right: 1rem;
        }

        .carousel-control-prev:hover, .carousel-control-next:hover {
            background: var(--primary);
        }

        .carousel-control-prev:hover .carousel-control-prev-icon,
        .carousel-control-next:hover .carousel-control-next-icon {
            filter: invert(0) brightness(100);
        }

        .carousel-control-prev-icon, .carousel-control-next-icon {
            filter: invert(1);
            width: 18px;
            height: 18px;
        }

        .carousel-indicators [data-bs-target] {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin: 0 4px;
            background-color: rgba(0, 0, 0, 0.3);
        }

        .carousel-indicators .active {
            width: 24px;
            border-radius: 4px;
            background-color: var(--primary);
        }

        .section-header {
            margin-bottom: 3rem;
            text-align: center;
        }

        .section-header h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            display: inline-block;
        }

        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 2px;
        }

        .section-header p {
            color: var(--text-secondary);
            font-size: 1.0625rem;
            margin-top: 1.5rem;
        }

        .store-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--border-light);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .store-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .store-badge {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.6875rem;
            font-weight: 700;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            box-shadow: var(--shadow);
        }
        
        .store-image-wrapper {
            position: relative;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .store-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        
        .store-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .store-name {
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .store-address {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            line-height: 1.5;
            flex: 1;
        }
        
        .store-address i {
            color: var(--primary);
            margin-top: 0.125rem;
            flex-shrink: 0;
        }
        
        .store-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
            margin-top: auto;
        }
        
        .store-products {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .store-products i {
            color: var(--secondary);
            font-size: 1rem;
        }
        
        .visit-store {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--border-light);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .product-image-wrapper {
            position: relative;
            background: linear-gradient(135deg, #667eea, #764ba2);
            overflow: hidden;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.1);
        }
        
        .product-badge {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: var(--danger);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.6875rem;
            font-weight: 700;
            box-shadow: var(--shadow);
        }
        
        .product-content {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            min-height: 2.8em;
        }
        
        .product-seller {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-seller i {
            color: var(--secondary);
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: auto;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-light);
        }

        .testimonial-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .testimonial-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
            color: var(--accent);
            font-size: 1rem;
        }
        
        .testimonial-text {
            margin-bottom: 1.5rem;
            line-height: 1.7;
            color: var(--text-primary);
            font-size: 0.9375rem;
            flex: 1;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }
        
        .testimonial-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
            flex-shrink: 0;
        }
        
        .testimonial-info h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }
        
        .testimonial-info p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .about-image-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .about-image-item {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: relative;
            height: 220px;
        }
        
        .about-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .about-image-item:hover img {
            transform: scale(1.1);
        }
        
        .about-image-item:first-child {
            grid-column: 1 / -1;
            height: 280px;
        }

        .trust-badge {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            transition: var(--transition);
            height: 100%;
        }

        .trust-badge:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .trust-badge-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--secondary), #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .trust-badge h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .trust-badge p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 5rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="white" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.2;
        }

        .cta-section h2 {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .cta-section p {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .btn {
            padding: 1rem 2.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }

        .btn-white {
            background: white;
            color: var(--primary);
        }

        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            color: var(--primary);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: white;
            color: white;
        }

        .footer {
            background: var(--bg-dark);
            color: rgba(255, 255, 255, 0.8);
            padding: 3.5rem 0 1.5rem;
        }

        .footer h3 {
            color: white;
            margin-bottom: 1.25rem;
            font-size: 1.125rem;
            font-weight: 700;
        }

        .footer p {
            line-height: 1.7;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .footer ul {
            list-style: none;
            padding: 0;
        }

        .footer li {
            margin-bottom: 0.75rem;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.7);
            transition: var(--transition);
            display: inline-block;
            font-size: 0.875rem;
        }

        .footer a:hover {
            color: white;
            transform: translateX(5px);
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        .social-links a {
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: rgba(255, 255, 255, 0.7);
        }

        .social-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px) !important;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.5rem;
            margin-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
        }

        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .scroll-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow-xl);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.4);
        }

        /* ============================================
           RESPONSIVE - MOBILE FIXES
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
            
            /* Notification dropdown di mobile */
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

            .carousel-item img {
                height: 250px;
            }

            .section-header h2 {
                font-size: 1.5rem;
            }

            .scroll-top {
                bottom: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
            }

            .about-image-item {
                height: 180px;
            }

            .about-image-item:first-child {
                height: 220px;
            }
            
            /* Mobile overlay when menu is open */
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
                    <input type="text" name="search" placeholder="Cari produk atau toko..." value="<?php echo htmlspecialchars($search); ?>">
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
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <?php if ($_SESSION['peran'] == 'penjual') { ?>
                        <a href="dashboard.php" class="header-action">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        <a href="profil.php" class="header-action">
                            <i class="fas fa-user"></i>
                            <span>Profil</span>
                        </a>
                        <a href="logout.php" class="header-action header-action-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Keluar</span>
                    </a>
                    <?php } else { ?>
                        <a href="cart.php" class="header-action">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Keranjang</span>
                            <?php if ($cart_count > 0) { ?>
                                <span class="cart-badge"><?php echo $cart_count; ?></span>
                            <?php } ?>
                        </a>
                        
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['peran'] == 'pembeli') { ?>
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
                                        <!-- Empty State -->
                                        <div class="notification-empty">
                                            <i class="fas fa-bell-slash"></i>
                                            <p>Belum ada notifikasi</p>
                                        </div>
                                    <?php } else { ?>
                                        <!-- Real Notification Items from Database -->
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
                        <?php } ?>
                        
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
                    <?php } ?>
                <?php } else { ?>
                    <a href="login.php" class="header-action">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Masuk</span>
                    </a>
                    <a href="register.php" class="header-action" style="color: var(--primary);">
                        <i class="fas fa-user-plus"></i>
                        <span>Daftar</span>
                    </a>
                <?php } ?>
            </div>
        </div>
        
        <nav class="nav">
            <div class="nav-content">
                <a href="#beranda"><i class="fas fa-home"></i> Beranda</a>
                <a href="#toko"><i class="fas fa-store"></i> Toko</a>
                <a href="#produk"><i class="fas fa-box"></i> Produk</a>
                <a href="#testimonial"><i class="fas fa-comments"></i> Testimoni</a>
                <a href="#tentang"><i class="fas fa-info-circle"></i> Tentang</a>
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <a href="logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                <?php } ?>
            </div>
        </nav>
    </header>

    <!-- SECTION 1: BERANDA/HERO - Simple & Clean -->
    <section id="beranda" class="hero py-5">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="hero-content">
                        <div class="hero-badge" data-aos="fade-down" data-aos-delay="100">
                            <i class="fas fa-shield-check"></i>
                            Platform Terpercaya
                        </div>
                        <h1 data-aos="fade-up" data-aos-delay="200">Platform Digital UMKM Grosir Terpercaya</h1>
                        <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="300">Temukan ribuan produk grosir berkualitas dengan harga terbaik dari seller terverifikasi</p>
                        
                        <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="400">
                            <div class="col-12">
                                <div class="hero-feature-card">
                                    <div class="icon-wrapper">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div>
                                        <strong>Transaksi 100% Aman & Terpercaya</strong>
                                        <p class="mb-0">Sistem keamanan berlapis dan enkripsi SSL</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="hero-feature-card">
                                    <div class="icon-wrapper">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div>
                                        <strong>Pengiriman Cepat ke Seluruh Indonesia</strong>
                                        <p class="mb-0">Tracking real-time dan jaminan aman sampai tujuan</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="hero-feature-card">
                                    <div class="icon-wrapper">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                    <div>
                                        <strong>Customer Support 24/7</strong>
                                        <p class="mb-0">Tim support siap membantu kapan saja</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 flex-wrap" data-aos="fade-up" data-aos-delay="500">
                            <a href="register.php" class="btn btn-primary btn-lg px-4">
                                <i class="fas fa-rocket"></i> Mulai Belanja
                            </a>
                            <a href="#tentang" class="btn btn-outline-primary btn-lg px-4">
                                <i class="fas fa-play-circle"></i> Pelajari Lebih Lanjut
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <div class="carousel-wrapper">
                        <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-indicators">
                                <?php foreach ($banners as $index => $banner) { ?>
                                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                                    <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                                    aria-label="Slide <?php echo $index + 1; ?>"></button>
                                <?php } ?>
                            </div>
                            <div class="carousel-inner">
                                <?php foreach ($banners as $index => $banner) { ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo $banner['image']; ?>" class="d-block w-100" alt="<?php echo $banner['alt']; ?>">
                                </div>
                                <?php } ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 2: TOKO -->
    <section id="toko" class="py-5 bg-white">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2>
                    <?php if (!empty($search)) { ?>
                        Hasil Pencarian Toko "<?php echo htmlspecialchars($search); ?>"
                    <?php } else { ?>
                        Toko Grosir Terverifikasi
                    <?php } ?>
                </h2>
                <p>Pilihan toko grosir terpercaya yang telah diverifikasi oleh admin</p>
            </div>

            <div class="row g-4">
                <?php 
                if (mysqli_num_rows($result_toko) > 0) {
                    mysqli_data_seek($result_toko, 0);
                    $delay = 100;
                    while ($row = mysqli_fetch_assoc($result_toko)) {
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <a href="store.php?id=<?php echo $row['user_id']; ?>" class="store-card">
                        <div class="store-image-wrapper">
                            <div class="store-badge">
                                <i class="fas fa-check-circle"></i> Terverifikasi
                            </div>
                            <?php if (!empty($row['gambar_toko'])) { ?>
                                <img src="<?php echo htmlspecialchars($row['gambar_toko']); ?>" alt="<?php echo htmlspecialchars($row['nama_grosir']); ?>" class="store-image">
                            <?php } else { ?>
                                <div class="store-image" style="display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 900;">
                                    <?php echo strtoupper(substr($row['nama_grosir'], 0, 1)); ?>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="store-content">
                            <div class="store-name"><?php echo htmlspecialchars($row['nama_grosir']); ?></div>
                            <div class="store-address">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars(substr($row['alamat_grosir'] ?? 'Indonesia', 0, 60)); ?><?php echo strlen($row['alamat_grosir'] ?? '') > 60 ? '...' : ''; ?></span>
                            </div>
                            <div class="store-footer">
                                <div class="store-products">
                                    <i class="fas fa-box"></i>
                                    <span><?php echo $row['total_produk']; ?> Produk</span>
                                </div>
                                <div class="visit-store">
                                    Kunjungi <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php 
                        $delay += 50;
                    }
                } else {
                ?>
                    <div class="col-12">
                        <div class="empty-state" data-aos="fade-up">
                            <i class="fas fa-store-slash"></i>
                            <h3>Toko Tidak Ditemukan</h3>
                            <p>
                                <?php if (!empty($search)) { ?>
                                    Tidak ada toko terverifikasi yang sesuai dengan "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                <?php } else { ?>
                                    Belum ada toko terverifikasi yang tersedia saat ini.
                                <?php } ?>
                            </p>
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-redo"></i> Lihat Semua Toko
                            </a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <!-- SECTION 3: PRODUK -->
    <section id="produk" class="py-5" style="background: var(--bg-gray);">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2>
                    <?php if (!empty($search)) { ?>
                        Hasil Pencarian Produk "<?php echo htmlspecialchars($search); ?>"
                    <?php } else { ?>
                        Produk Terbaru
                    <?php } ?>
                </h2>
                <p>Produk pilihan dengan harga grosir terbaik</p>
            </div>

            <div class="row g-4">
                <?php 
                if (mysqli_num_rows($result_produk) > 0) {
                    $delay = 100;
                    while ($produk = mysqli_fetch_assoc($result_produk)) {
                        $gambar_path = !empty($produk['gambar_produk']) ? "uploads/" . htmlspecialchars($produk['gambar_produk']) : "https://via.placeholder.com/220x200/667eea/ffffff?text=" . urlencode(substr($produk['nama_produk'], 0, 10));
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <a href="produk_detail.php?id=<?php echo $produk['produk_id']; ?>" class="product-card">
                        <div class="product-image-wrapper">
                            <img src="<?php echo $gambar_path; ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="product-image">
                            <?php if ($produk['stok'] < 10 && $produk['stok'] > 0) { ?>
                                <div class="product-badge">Stok Terbatas</div>
                            <?php } ?>
                        </div>
                        <div class="product-content">
                            <h3 class="product-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></h3>
                            <div class="product-seller">
                                <i class="fas fa-store"></i>
                                <span><?php echo htmlspecialchars($produk['nama_grosir']); ?></span>
                            </div>
                            <div class="product-price">
                                Rp <?php echo number_format($produk['harga_grosir'], 0, ',', '.'); ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php 
                        $delay += 50;
                    }
                } else {
                ?>
                    <div class="col-12">
                        <div class="empty-state" data-aos="fade-up">
                            <i class="fas fa-box-open"></i>
                            <h3>Produk Tidak Ditemukan</h3>
                            <p>
                                <?php if (!empty($search)) { ?>
                                    Tidak ada hasil untuk "<strong><?php echo htmlspecialchars($search); ?></strong>". Coba kata kunci lain.
                                <?php } else { ?>
                                    Belum ada produk yang tersedia saat ini.
                                <?php } ?>
                            </p>
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-redo"></i> Lihat Semua Produk
                            </a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <!-- SECTION 4: TESTIMONI -->
    <section id="testimonial" class="py-5 bg-white">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2>Apa Kata Mereka?</h2>
                <p>Ribuan UMKM telah merasakan manfaat InGrosir</p>
            </div>
            <div class="row g-4">
                <?php 
                $delay = 100;
                foreach ($testimonials as $testimonial) { 
                ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="testimonial-card card h-100 border-0 shadow-sm p-4">
                        <div class="testimonial-rating mb-3">
                            <?php for ($i = 0; $i < $testimonial['rating']; $i++) { ?>
                                <i class="fas fa-star"></i>
                            <?php } ?>
                        </div>
                        <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['text']); ?>"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-avatar"><?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?></div>
                            <div class="testimonial-info">
                                <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                                <p><?php echo htmlspecialchars($testimonial['role']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                    $delay += 100;
                } 
                ?>
            </div>
        </div>
    </section>

    <!-- SECTION 5: TENTANG -->
    <section id="tentang" class="py-5" style="background: var(--bg-gray);">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6" data-aos="fade-right">
                    <h2 class="mb-4">Tentang InGrosir</h2>
                    <p class="mb-3">InGrosir adalah platform digital terdepan yang menghubungkan UMKM grosir dengan pembeli di seluruh Indonesia. Kami hadir sebagai solusi modern untuk memudahkan transaksi grosir yang selama ini masih dilakukan secara konvensional.</p>
                    
                    <p class="mb-3">Dengan sistem keamanan berlapis dan verifikasi seller yang ketat, setiap transaksi di InGrosir dijamin aman dan terpercaya. Kami berkomitmen memberikan pengalaman belanja yang mudah, cepat, dan transparan untuk mendukung pertumbuhan bisnis UMKM di Indonesia.</p>
                    
                    <p class="mb-3">Platform kami dilengkapi dengan berbagai fitur canggih seperti sistem pembayaran terintegrasi, tracking pengiriman real-time, dan customer support yang siap membantu 24/7. InGrosir bukan hanya sekedar marketplace, tetapi mitra terpercaya untuk kesuksesan bisnis Anda.</p>
                    
                    <p class="mb-4">Bergabunglah dengan ribuan UMKM yang telah mempercayai InGrosir sebagai platform grosir digital mereka. Mari bersama-sama memajukan ekonomi digital Indonesia!</p>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="about-image-grid">
                        <div class="about-image-item" data-aos="zoom-in" data-aos-delay="100">
                            <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&h=280&fit=crop" alt="Shopping" class="img-fluid">
                        </div>
                        <div class="about-image-item" data-aos="zoom-in" data-aos-delay="200">
                            <img src="https://images.unsplash.com/photo-1553413077-190dd305871c?w=300&h=220&fit=crop" alt="Store" class="img-fluid">
                        </div>
                        <div class="about-image-item" data-aos="zoom-in" data-aos-delay="300">
                            <img src="https://images.unsplash.com/photo-1578574577315-3fbeb0cecdc2?w=300&h=220&fit=crop" alt="Delivery" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trust Badges -->
            <div class="row g-4 mt-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="trust-badge text-center">
                        <div class="trust-badge-icon mx-auto">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Keamanan Terjamin</h4>
                        <p>Sistem enkripsi SSL 256-bit</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="trust-badge text-center">
                        <div class="trust-badge-icon mx-auto">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <h4>Seller Terverifikasi</h4>
                        <p>100% toko telah divalidasi</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="trust-badge text-center">
                        <div class="trust-badge-icon mx-auto">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h4>Pembayaran Aman</h4>
                        <p>Berbagai metode tersedia</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="trust-badge text-center">
                        <div class="trust-badge-icon mx-auto">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Analytics Dashboard</h4>
                        <p>Monitor penjualan real-time</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container position-relative" data-aos="zoom-in">
            <h2>Siap Mengembangkan Bisnis Anda?</h2>
            <p>Bergabunglah dengan ribuan UMKM yang telah mempercayai InGrosir</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="register.php" class="btn btn-white btn-lg">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                </a>
                <a href="#produk" class="btn btn-outline btn-lg">
                    <i class="fas fa-shopping-bag"></i> Lihat Produk
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up">
                    <h3>InGrosir</h3>
                    <p>Platform digital terpercaya untuk UMKM grosir Indonesia.</p>
                    <div class="social-links">
                        <a href="https://web.facebook.com/alfian.resaa" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/mhmmdagil_028/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.tiktok.com/@delonnmo" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                        <a href="https://wa.me/628971692840" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <h3>Perusahaan</h3>
                    <ul>
                        <li><a href="#">Tentang Kami</a></li>
                        <li><a href="https://github.com/Delon-tech">Tim Kami</a></li>
                        <li><a href="https://unm.ac.id/">Karir</a></li>
                        <li><a href="https://ft.unm.ac.id/">Blog</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <h3>Bantuan</h3>
                    <ul>
                        <li><a href="#">Pusat Bantuan</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Syarat & Ketentuan</a></li>
                        <li><a href="#">Kebijakan Privasi</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <h3>Hubungi Kami</h3>
                    <p><i class="fas fa-envelope"></i> muhagil282004@gmail.com</p>
                    <p><i class="fas fa-phone"></i> +62 897-1692-840</p>
                    <p><i class="fas fa-map-marker-alt"></i> Makassar, Indonesia</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 InGrosir. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" onclick="scrollToTop()" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // ============================================
        // INITIALIZE AOS ANIMATION
        // ============================================
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 50
        });

        // ============================================
        // REAL-TIME NOTIFICATION SYSTEM
        // ============================================
        
        /**
         * Toggle notification dropdown
         * @param {Event} event - Click event
         */
        function toggleNotifications(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
            
            // Close mobile menu if open
            const headerActions = document.getElementById('headerActions');
            if (window.innerWidth <= 768 && headerActions && headerActions.classList.contains('active')) {
                // Don't close mobile menu, just show notification
            }
        }

        /**
         * Mark single notification as read (REAL-TIME WITH API)
         * @param {Event} event - Click event
         * @param {number} notificationId - ID of notification
         */
        function markAsRead(event, notificationId) {
            // Send AJAX request to mark as read
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
                    
                    // Remove unread class from clicked item
                    const item = event.currentTarget;
                    if (item) {
                        item.classList.remove('unread');
                        
                        // Remove "baru" badge
                        const badge = item.querySelector('.notification-badge-new');
                        if (badge) {
                            badge.remove();
                        }
                    }
                    
                    // Update notification count (real-time)
                    updateNotificationCount();
                } else {
                }
            })
            .catch(error => {

            });
        }

        /**
         * Mark ALL notifications as read (REAL-TIME WITH API)
         */
        function markAllRead() {
            if (!confirm('Tandai semua notifikasi sebagai sudah dibaca?')) {
                return;
            }
            
            fetch('api/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    
                    // Remove all unread classes
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    // Remove all "baru" badges
                    document.querySelectorAll('.notification-badge-new').forEach(badge => {
                        badge.remove();
                    });
                    
                    // Update notification count
                    updateNotificationCount();
                    
                    // Show success message
                    alert('✅ Semua notifikasi telah ditandai sebagai dibaca!');
                } else {
                    alert('❌ Gagal menandai notifikasi. Silakan coba lagi.');
                }
            })
            .catch(error => {
                alert('❌ Terjadi kesalahan. Silakan coba lagi.');
            });
        }

        /**
         * Update notification count from server (REAL-TIME)
         */
        function updateNotificationCount() {
            fetch('api/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notificationBadge');
                    const allTab = document.querySelector('.notification-tab[data-tab="all"]');
                    const headerBadge = document.querySelector('.notification-header .badge');
                    
                    if (data.count > 0) {
                        // Update or create badge
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'flex';
                        } else {
                            // Create badge if doesn't exist
                            const notificationAction = document.querySelector('.notification-wrapper .header-action');
                            if (notificationAction) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.id = 'notificationBadge';
                                newBadge.textContent = data.count > 99 ? '99+' : data.count;
                                notificationAction.appendChild(newBadge);
                            }
                        }
                        
                        // Update tab text
                        if (allTab) {
                            allTab.textContent = `Semua (${data.count} baru)`;
                        }
                        
                        // Update header badge in dropdown
                        if (headerBadge) {
                            headerBadge.textContent = data.count;
                            headerBadge.style.display = 'inline-block';
                        }
                    } else {
                        // Hide badge if no unread notifications
                        if (badge) {
                            badge.style.display = 'none';
                        }
                        
                        // Update tab text
                        if (allTab) {
                            allTab.textContent = 'Semua';
                        }
                        
                        // Hide header badge
                        if (headerBadge) {
                            headerBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                });
        }

        /**
         * Close notification dropdown when clicking outside
         */
        document.addEventListener('click', function(event) {
            const notificationWrapper = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (notificationWrapper && dropdown && !notificationWrapper.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        /**
         * Notification tabs filter handler
         */
        document.querySelectorAll('.notification-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Filter notifications based on tab
                const tabType = this.getAttribute('data-tab');
                const notificationItems = document.querySelectorAll('.notification-item');
                
                notificationItems.forEach(item => {
                    if (tabType === 'all') {
                        item.style.display = 'flex';
                    } else if (tabType === 'unread') {
                        if (item.classList.contains('unread')) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    }
                });
            });
        });

        /**
         * Auto-refresh notification count every 30 seconds (REAL-TIME)
         */
        setInterval(function() {
            // Only refresh if user is logged in and notification system exists
            const notifBell = document.querySelector('.notification-wrapper');
            if (notifBell) {
                updateNotificationCount();
            }
        }, 30000); // 30 seconds

        // ============================================
        // MOBILE MENU HANDLER
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
                
                // Close notification dropdown when closing mobile menu
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown) {
                    dropdown.classList.remove('active');
                }
            }
        }

        /**
         * Close mobile menu when clicking outside
         */
        document.addEventListener('click', function(e) {
            const actions = document.getElementById('headerActions');
            const toggle = document.querySelector('.mobile-toggle');
            const notificationWrapper = document.querySelector('.notification-wrapper');
            
            if (!actions || !toggle) return;
            
            if (!actions.contains(e.target) && 
                !toggle.contains(e.target) && 
                (!notificationWrapper || !notificationWrapper.contains(e.target)) &&
                actions.classList.contains('active') &&
                window.innerWidth <= 768) {
                toggleMobileMenu();
            }
        });

        // ============================================
        // SMOOTH SCROLL
        // ============================================
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                        
                        // Close mobile menu if open
                        const actions = document.getElementById('headerActions');
                        if (actions && actions.classList.contains('active')) {
                            toggleMobileMenu();
                        }
                    }
                }
            });
        });

        // ============================================
        // HEADER SCROLL EFFECT
        // ============================================
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (header) {
                const currentScroll = window.pageYOffset;
                header.classList.toggle('scrolled', currentScroll > 100);
                lastScroll = currentScroll;
            }
        });

        // ============================================
        // SCROLL TO TOP BUTTON
        // ============================================
        const scrollTopBtn = document.querySelector('.scroll-top');
        if (scrollTopBtn) {
            window.addEventListener('scroll', () => {
                scrollTopBtn.classList.toggle('visible', window.pageYOffset > 300);
            });
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ============================================
        // ACTIVE NAVIGATION HIGHLIGHTING
        // ============================================
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('section[id]');
            const scrollY = window.pageYOffset;

            sections.forEach(section => {
                const sectionHeight = section.offsetHeight;
                const sectionTop = section.offsetTop - 100;
                const sectionId = section.getAttribute('id');
                const navLink = document.querySelector(`.nav-content a[href="#${sectionId}"]`);

                if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                    document.querySelectorAll('.nav-content a').forEach(link => {
                        link.style.color = '';
                    });
                    if (navLink) {
                        navLink.style.color = 'var(--primary)';
                    }
                }
            });
        });

        // ============================================
        // LAZY LOADING IMAGES
        // ============================================
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // ============================================
        // PERFORMANCE OPTIMIZATION
        // ============================================
        function debounce(func, wait = 10, immediate = true) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }

        const debouncedScroll = debounce(function() {
            // Scroll-based animations or effects can go here
        });

        window.addEventListener('scroll', debouncedScroll);

        // ============================================
        // PREVENT EMPTY SEARCH SUBMISSION
        // ============================================
        const searchBox = document.querySelector('.search-box');
        if (searchBox) {
            searchBox.addEventListener('submit', function(e) {
                const searchInput = this.querySelector('input[name="search"]');
                if (searchInput && searchInput.value.trim() === '') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }

        // ============================================
        // KEYBOARD NAVIGATION
        // ============================================
        document.addEventListener('keydown', function(e) {
            // ESC key closes notification dropdown and mobile menu
            if (e.key === 'Escape') {
                const dropdown = document.getElementById('notificationDropdown');
                const mobileMenu = document.getElementById('headerActions');
                
                if (dropdown && dropdown.classList.contains('active')) {
                    dropdown.classList.remove('active');
                }
                
                if (mobileMenu && mobileMenu.classList.contains('active')) {
                    toggleMobileMenu();
                }
            }
        });

        // ============================================
        // WINDOW RESIZE HANDLER
        // ============================================
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Close mobile menu on resize to desktop
                if (window.innerWidth > 768) {
                    const actions = document.getElementById('headerActions');
                    const toggle = document.querySelector('.mobile-toggle');
                    const body = document.body;
                    
                    if (actions && toggle && actions.classList.contains('active')) {
                        actions.classList.remove('active');
                        body.classList.remove('mobile-menu-open');
                        
                        const spans = toggle.querySelectorAll('span');
                        spans[0].style.transform = '';
                        spans[1].style.opacity = '';
                        spans[2].style.transform = '';
                    }
                }
            }, 250);
        });

        // ============================================
        // ANIMATION ON SCROLL - TRIGGER ONCE
        // ============================================
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const fadeObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements with fade animation
        document.querySelectorAll('[data-aos]').forEach(el => {
            fadeObserver.observe(el);
        });

        // ============================================
        // PAGE LOAD COMPLETE
        // ============================================
        window.addEventListener('load', function() {
            // Hide any loading indicators
            const loader = document.querySelector('.page-loader');
            if (loader) {
                loader.style.display = 'none';
            }
            
            // Trigger any post-load animations
            document.body.classList.add('loaded');
        });
        
        // Check if notification bell exists
        const notifBell = document.querySelector('.notification-wrapper');
        if (notifBell) {
        } else {
        }

        // ============================================
        // SERVICE WORKER REGISTRATION (Optional PWA)
        // ============================================
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                // navigator.serviceWorker.register('/sw.js')
                //     .then(reg => console.log('Service Worker Registered'))
                //     .catch(err => console.log('Service Worker Registration Failed'));
            });
        }

        // ============================================
        // INITIAL NOTIFICATION COUNT UPDATE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Update notification count on page load
            const notifBell = document.querySelector('.notification-wrapper');
            if (notifBell) {
                updateNotificationCount();
            }
        });
    </script>
</body>
</html>