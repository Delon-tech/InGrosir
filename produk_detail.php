<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

// Notification system (jika user adalah pembeli yang login)
if (isset($_SESSION['user_id']) && $_SESSION['peran'] == 'pembeli') {
    require_once 'includes/notification_helper.php';
    $notif_count = hitung_notifikasi_belum_dibaca($_SESSION['user_id']);
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

$produk_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$produk_id) {
    header("Location: index.php");
    exit();
}

// Query detail produk dengan JOIN
$query_produk = "SELECT p.*, u.nama_grosir, u.alamat_grosir, u.user_id, u.nomor_telepon, k.nama_kategori 
                 FROM produk p 
                 JOIN users u ON p.user_id = u.user_id 
                 LEFT JOIN kategori_produk k ON p.kategori_id = k.kategori_id 
                 WHERE p.produk_id = ? AND p.is_active = 1";
$stmt = mysqli_prepare($koneksi, $query_produk);
mysqli_stmt_bind_param($stmt, "i", $produk_id);
mysqli_stmt_execute($stmt);
$result_produk = mysqli_stmt_get_result($stmt);
$produk = mysqli_fetch_assoc($result_produk);

if (!$produk) {
    echo "<p>Produk tidak ditemukan.</p>";
    exit();
}

// Query ulasan produk
$ulasan_query = "SELECT up.*, u.nama_lengkap, u.email 
                 FROM ulasan_produk up 
                 JOIN users u ON up.user_id_pembeli = u.user_id 
                 WHERE up.produk_id = ? 
                 ORDER BY up.tanggal_ulasan DESC 
                 LIMIT 10";
$ulasan_stmt = mysqli_prepare($koneksi, $ulasan_query);
mysqli_stmt_bind_param($ulasan_stmt, "i", $produk_id);
mysqli_stmt_execute($ulasan_stmt);
$ulasan_result = mysqli_stmt_get_result($ulasan_stmt);

// Hitung rata-rata rating
$avg_rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM ulasan_produk WHERE produk_id = ?";
$avg_stmt = mysqli_prepare($koneksi, $avg_rating_query);
mysqli_stmt_bind_param($avg_stmt, "i", $produk_id);
mysqli_stmt_execute($avg_stmt);
$avg_result = mysqli_fetch_assoc(mysqli_stmt_get_result($avg_stmt));
$avg_rating = round($avg_result['avg_rating'] ?? 0, 1);
$total_reviews = $avg_result['total_reviews'] ?? 0;

// Query produk terkait
$related_query = "SELECT * FROM produk WHERE user_id = ? AND produk_id != ? ORDER BY RAND() LIMIT 4";
$related_stmt = mysqli_prepare($koneksi, $related_query);
mysqli_stmt_bind_param($related_stmt, "ii", $produk['user_id'], $produk_id);
mysqli_stmt_execute($related_stmt);
$related_products = mysqli_stmt_get_result($related_stmt);

$gambar_path = !empty($produk['gambar_produk']) 
    ? "uploads/" . htmlspecialchars($produk['gambar_produk']) 
    : "https://via.placeholder.com/600x600/667eea/ffffff?text=" . urlencode($produk['nama_produk']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($produk['nama_produk']); ?> - InGrosir</title>
    
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
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-white: #ffffff;
            --bg-gray: #f9fafb;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 300ms ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gray);
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        /* ============================================
           ENHANCED HEADER - SAMA SEPERTI INDEX.PHP
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
        
        .header-content {
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
        
        /* NOTIFICATION DROPDOWN */
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
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
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
        
        .nav-content a:hover {
            color: var(--primary);
            background: var(--bg-light);
        }

        
        /* Container */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Product Detail Layout */
        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        /* Product Gallery */
        .product-gallery {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .main-image-container {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .main-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
        }
        
        .image-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--secondary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Product Info */
        .product-info {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .product-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            line-height: 1.2;
            font-weight: 800;
        }
        
        .product-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .category-badge {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }
        
        .rating-badge {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent);
        }
        
        .rating-stars {
            color: var(--accent);
            display: flex;
            gap: 0.25rem;
        }
        
        /* Price Section */
        .price-card {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(16, 185, 129, 0.05));
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            border: 2px solid var(--border);
        }
        
        .price-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .price-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .stock-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: var(--radius);
            color: var(--secondary);
            font-weight: 600;
        }
        
        .stock-status.low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent);
        }
        
        .stock-status.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Seller Card */
        .seller-card {
            background: var(--bg-gray);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }
        
        .seller-header {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .seller-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .seller-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .seller-details {
            flex: 1;
        }
        
        .seller-name {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .seller-location {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .seller-phone {
            font-size: 0.875rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .seller-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        /* Quantity Section */
        .quantity-section {
            margin-bottom: 2rem;
        }
        
        .quantity-label {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: block;
            color: var(--text-primary);
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quantity-selector {
            display: flex;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        
        .qty-btn {
            width: 48px;
            height: 48px;
            border: none;
            background: var(--bg-gray);
            cursor: pointer;
            font-size: 1.25rem;
            transition: var(--transition);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .qty-input {
            width: 70px;
            height: 48px;
            border: none;
            text-align: center;
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--text-primary);
        }
        
        .qty-max {
            font-size: 0.875rem;
            color: var(--text-secondary);
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
            gap: 0.75rem;
            justify-content: center;
            font-size: 1rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            width: 100%;
            margin-top: 0.75rem;
        }
        
        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Description Section */
        .section-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .description-text {
            line-height: 1.8;
            color: var(--text-secondary);
            white-space: pre-line;
        }
        
        /* Reviews Section */
        .reviews-summary {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            padding: 2rem;
            background: var(--bg-gray);
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }
        
        .rating-overview {
            text-align: center;
        }
        
        .rating-number {
            font-size: 4rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        
        .rating-overview .rating-stars {
            font-size: 1.5rem;
            justify-content: center;
            margin: 0.5rem 0;
        }
        
        .rating-count {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .rating-bars {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .bar-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            width: 60px;
        }
        
        .bar-container {
            flex: 1;
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .bar-fill {
            height: 100%;
            background: var(--accent);
            transition: var(--transition);
        }
        
        .bar-count {
            font-size: 0.875rem;
            color: var(--text-secondary);
            width: 40px;
            text-align: right;
        }
        
        /* Review Item */
        .review-item {
            background: var(--bg-gray);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .reviewer-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .reviewer-name {
            font-weight: 600;
        }
        
        .review-stars {
            color: var(--accent);
            font-size: 0.875rem;
        }
        
        .review-text {
            line-height: 1.6;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }
        
        .review-date {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        /* Related Products */
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .related-card {
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            background: white;
        }
        
        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .related-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .related-info {
            padding: 1.25rem;
        }
        
        .related-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        
        .related-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.125rem;
        }
        
        /* RESPONSIVE MOBILE */
        @media (max-width: 768px) {
            .header-top { display: none; }
            
            .header-content {
                grid-template-columns: 1fr auto;
                gap: 1rem;
                padding: 0.875rem 1rem;
            }
            
            .logo { 
                font-size: 1.5rem; 
                order: 1;
            }
            
            .logo i { font-size: 1.625rem; }
            
            .header-search { display: none; }
            
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
            
            .notification-wrapper {
                width: 100%;
            }
            
            .notification-dropdown {
                position: relative;
                top: 0;
                right: auto;
                width: 100%;
                margin-top: 0.5rem;
            }
            .nav { display: none; }
    
            /* --- PERBAIKAN LAYOUT PRODUK DI SINI --- */
            .product-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .product-gallery {
                position: static;
                padding: 1rem;
            }
            
            .product-info {
                padding: 1.5rem;
            }
            .nav { display: none; }
            
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
    </style>
</head>
<body>
    <!-- HEADER - ENHANCED NAVBAR -->
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
        
        <div class="header-content">
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
                <a href="index.php"><i class="fas fa-home"></i> Beranda</a>
                <a href="store.php?id=<?php echo $produk['user_id']; ?>"><i class="fas fa-store"></i> Toko</a>
                <a href="index.php#produk"><i class="fas fa-box"></i> Produk</a>
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <a href="logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                <?php } ?>
            </div>
        </nav>
    </header>

    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Beranda</a>
            <span>/</span>
            <a href="store.php?id=<?php echo $produk['user_id']; ?>"><?php echo htmlspecialchars($produk['nama_grosir']); ?></a>
            <span>/</span>
            <span><?php echo htmlspecialchars($produk['nama_produk']); ?></span>
        </div>

        <!-- Product Layout -->
        <div class="product-layout">
            <!-- Gallery -->
            <div class="product-gallery">
                <div class="main-image-container">
                    <img src="<?php echo $gambar_path; ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="main-image">
                    <?php if ($produk['stok'] > 0 && $produk['stok'] <= 10) { ?>
                        <div class="image-badge" style="background: var(--accent);">
                            <i class="fas fa-exclamation-triangle"></i>
                            Stok Terbatas
                        </div>
                    <?php } elseif ($produk['stok'] > 0) { ?>
                        <div class="image-badge">
                            <i class="fas fa-check-circle"></i>
                            Stok Tersedia
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($produk['nama_produk']); ?></h1>
                
                <div class="product-meta">
                    <?php if ($produk['nama_kategori']) { ?>
                        <div class="meta-badge category-badge">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($produk['nama_kategori']); ?>
                        </div>
                    <?php } ?>
                    
                    <div class="meta-badge rating-badge">
                        <div class="rating-stars">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= floor($avg_rating)) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i - 0.5 <= $avg_rating) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> ulasan)</span>
                    </div>
                </div>

                <!-- Price -->
                <div class="price-card">
                    <div class="price-label">Harga Grosir</div>
                    <div class="price-amount">Rp <?php echo number_format($produk['harga_grosir'], 0, ',', '.'); ?></div>
                    
                    <?php if ($produk['stok'] > 0) { ?>
                        <div class="stock-status <?php echo $produk['stok'] <= 10 ? 'low-stock' : ''; ?>">
                            <i class="fas fa-<?php echo $produk['stok'] <= 10 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                            <span>Stok: <?php echo htmlspecialchars($produk['stok']); ?> unit tersedia</span>
                        </div>
                    <?php } else { ?>
                        <div class="stock-status out-of-stock">
                            <i class="fas fa-times-circle"></i>
                            <span>Stok Habis</span>
                        </div>
                    <?php } ?>
                </div>

                <!-- Seller Info -->
                <div class="seller-card">
                    <div class="seller-header">DIJUAL OLEH</div>
                    <div class="seller-content">
                        <div class="seller-avatar">
                            <?php echo strtoupper(substr($produk['nama_grosir'], 0, 1)); ?>
                        </div>
                        <div class="seller-details">
                            <div class="seller-name"><?php echo htmlspecialchars($produk['nama_grosir']); ?></div>
                            <div class="seller-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($produk['alamat_grosir']); ?>
                            </div>
                            <?php if (!empty($produk['nomor_telepon'])) { ?>
                            <div class="seller-phone">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?php echo htmlspecialchars($produk['nomor_telepon']); ?>" style="color: var(--primary);">
                                    <?php echo htmlspecialchars($produk['nomor_telepon']); ?>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="seller-actions">
                        <a href="store.php?id=<?php echo $produk['user_id']; ?>" class="btn btn-secondary btn-small">
                            <i class="fas fa-store"></i>
                            Kunjungi Toko
                        </a>
                    </div>
                </div>

                <!-- Quantity Form -->
                <?php if ($produk['stok'] > 0) { ?>
                <form action="cart_process.php" method="POST" id="addToCartForm">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="produk_id" value="<?php echo $produk_id; ?>">
                    
                    <div class="quantity-section">
                        <label class="quantity-label">Pilih Jumlah</label>
                        <div class="quantity-controls">
                            <div class="quantity-selector">
                                <button type="button" class="qty-btn" onclick="decreaseQty()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input 
                                    type="number" 
                                    id="jumlah" 
                                    name="jumlah" 
                                    class="qty-input" 
                                    value="1" 
                                    min="1" 
                                    max="<?php echo $produk['stok']; ?>"
                                    required
                                >
                                <button type="button" class="qty-btn" onclick="increaseQty()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <span class="qty-max">Maks. <?php echo $produk['stok']; ?> unit</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        Tambah ke Keranjang
                    </button>
                </form>
                <?php } else { ?>
                    <button class="btn btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-times-circle"></i>
                        Stok Habis
                    </button>
                <?php } ?>

                <a href="store.php?id=<?php echo $produk['user_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-boxes"></i>
                    Lihat Produk Lainnya
                </a>
            </div>
        </div>

        <!-- Description Section -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-align-left"></i>
                Deskripsi Produk
            </h2>
            <p class="description-text"><?php echo nl2br(htmlspecialchars($produk['deskripsi_produk'])); ?></p>
        </div>

        <!-- Reviews Section -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-star"></i>
                Ulasan Pembeli
            </h2>

            <!-- Reviews Summary -->
            <?php if ($total_reviews > 0) { ?>
            <div class="reviews-summary">
                <div class="rating-overview">
                    <div class="rating-number"><?php echo $avg_rating; ?></div>
                    <div class="rating-stars">
                        <?php 
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= floor($avg_rating)) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i - 0.5 <= $avg_rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </div>
                    <div class="rating-count"><?php echo $total_reviews; ?> ulasan</div>
                </div>
                
                <div class="rating-bars">
                    <?php 
                    // Hitung distribusi rating
                    for ($star = 5; $star >= 1; $star--) {
                        $rating_count_query = "SELECT COUNT(*) as count FROM ulasan_produk WHERE produk_id = ? AND rating = ?";
                        $rating_stmt = mysqli_prepare($koneksi, $rating_count_query);
                        mysqli_stmt_bind_param($rating_stmt, "ii", $produk_id, $star);
                        mysqli_stmt_execute($rating_stmt);
                        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($rating_stmt));
                        $star_count = $count_result['count'];
                        $percentage = $total_reviews > 0 ? ($star_count / $total_reviews) * 100 : 0;
                    ?>
                    <div class="rating-bar-item">
                        <div class="bar-label">
                            <i class="fas fa-star"></i> <?php echo $star; ?>
                        </div>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                        <div class="bar-count"><?php echo $star_count; ?></div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>

            <!-- Review Items -->
            <div style="margin-top: 2rem;">
                <?php 
                if (mysqli_num_rows($ulasan_result) > 0) {
                    while ($ulasan = mysqli_fetch_assoc($ulasan_result)) {
                ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">
                                    <?php echo strtoupper(substr($ulasan['nama_lengkap'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="reviewer-name"><?php echo htmlspecialchars($ulasan['nama_lengkap']); ?></div>
                                    <div class="review-stars">
                                        <?php 
                                        for ($i = 0; $i < 5; $i++) {
                                            if ($i < $ulasan['rating']) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="review-text"><?php echo htmlspecialchars($ulasan['komentar']); ?></p>
                        <div class="review-date">
                            <i class="fas fa-clock"></i>
                            <?php echo date('d M Y, H:i', strtotime($ulasan['tanggal_ulasan'])); ?>
                        </div>
                    </div>
                <?php 
                    }
                } else {
                ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Belum Ada Ulasan</h3>
                        <p>Jadilah yang pertama memberikan ulasan untuk produk ini!</p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (mysqli_num_rows($related_products) > 0) { ?>
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-boxes"></i>
                Produk Lainnya dari Toko Ini
            </h2>
            
            <div class="related-grid">
                <?php 
                while ($related = mysqli_fetch_assoc($related_products)) { 
                    $related_image = !empty($related['gambar_produk']) 
                        ? "uploads/" . htmlspecialchars($related['gambar_produk']) 
                        : "https://via.placeholder.com/250x200/667eea/ffffff?text=" . urlencode($related['nama_produk']);
                ?>
                <a href="produk_detail.php?id=<?php echo $related['produk_id']; ?>" class="related-card">
                    <img src="<?php echo $related_image; ?>" alt="<?php echo htmlspecialchars($related['nama_produk']); ?>" class="related-image">
                    <div class="related-info">
                        <h4 class="related-name"><?php echo htmlspecialchars($related['nama_produk']); ?></h4>
                        <div class="related-price">Rp <?php echo number_format($related['harga_grosir'], 0, ',', '.'); ?></div>
                        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-secondary);">
                            <i class="fas fa-boxes"></i> Stok: <?php echo $related['stok']; ?>
                        </div>
                    </div>
                </a>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // ============================================
        // PRODUCT DETAIL PAGE - COMPLETE JAVASCRIPT
        // ============================================

        // Bootstrap & AOS initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS if available
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 600,
                    easing: 'ease-out-cubic',
                    once: true,
                    offset: 50
                });
            }
            
            // Initial notification count update
            const notifBell = document.querySelector('.notification-wrapper');
            if (notifBell) {
                updateNotificationCount();
            }
        });

        // ============================================
        // QUANTITY CONTROLS
        // ============================================
        const maxQty = <?php echo $produk['stok']; ?>;
        const qtyInput = document.getElementById('jumlah');

        function increaseQty() {
            if (!qtyInput) return;
            let currentQty = parseInt(qtyInput.value) || 1;
            if (currentQty < maxQty) {
                qtyInput.value = currentQty + 1;
            }
        }

        function decreaseQty() {
            if (!qtyInput) return;
            let currentQty = parseInt(qtyInput.value) || 1;
            if (currentQty > 1) {
                qtyInput.value = currentQty - 1;
            }
        }

        // Prevent manual input of invalid quantity
        if (qtyInput) {
            qtyInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                if (isNaN(value) || value < 1) {
                    this.value = 1;
                } else if (value > maxQty) {
                    this.value = maxQty;
                }
            });

            // Prevent non-numeric input
            qtyInput.addEventListener('keypress', function(e) {
                if (e.which < 48 || e.which > 57) {
                    e.preventDefault();
                }
            });
        }

        // Form submission validation
        const form = document.getElementById('addToCartForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const qty = parseInt(qtyInput.value);
                if (isNaN(qty) || qty < 1 || qty > maxQty) {
                    e.preventDefault();
                    alert(`Jumlah harus antara 1 dan ${maxQty}`);
                    return false;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan...';
                }
            });
        }

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
                
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown) {
                    dropdown.classList.remove('active');
                }
            }
        }

        // Close mobile menu when clicking outside
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
        // NOTIFICATION SYSTEM
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
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'flex';
                        }
                    } else {
                        if (badge) badge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const notificationWrapper = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (notificationWrapper && dropdown && !notificationWrapper.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Auto-refresh notification count every 30 seconds
        setInterval(function() {
            const notifBell = document.querySelector('.notification-wrapper');
            if (notifBell) {
                updateNotificationCount();
            }
        }, 30000);

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
        // IMAGE ZOOM ON HOVER
        // ============================================
        const mainImage = document.querySelector('.main-image');
        if (mainImage) {
            mainImage.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            mainImage.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        }

        // ============================================
        // RATING BARS ANIMATION
        // ============================================
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px'
        };

        const ratingObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bars = entry.target.querySelectorAll('.bar-fill');
                    bars.forEach(bar => {
                        const width = bar.style.width;
                        bar.style.width = '0%';
                        setTimeout(() => {
                            bar.style.width = width;
                        }, 100);
                    });
                    ratingObserver.unobserve(entry.target);
                }
            });
        }, observerOptions);

        const ratingBars = document.querySelector('.rating-bars');
        if (ratingBars) {
            ratingObserver.observe(ratingBars);
        }

        // ============================================
        // LAZY LOAD IMAGES FOR RELATED PRODUCTS
        // ============================================
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                        }
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('.related-image').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // ============================================
        // SMOOTH SCROLL FOR RELATED PRODUCTS
        // ============================================
        document.querySelectorAll('.related-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Smooth transition animation
                this.style.transition = 'opacity 0.3s ease';
                this.style.opacity = '0.7';
                setTimeout(() => {
                    this.style.opacity = '1';
                }, 300);
            });
        });

        // ============================================
        // AUTO-HIDE ALERTS
        // ============================================
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // ============================================
        // WINDOW RESIZE HANDLER
        // ============================================
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
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
        // FORM VALIDATION HELPER
        // ============================================
        function validateQuantity(input) {
            const value = parseInt(input.value);
            if (isNaN(value) || value < 1) {
                input.value = 1;
                return false;
            }
            if (value > maxQty) {
                input.value = maxQty;
                return false;
            }
            return true;
        }

        // ============================================
        // BREADCRUMB ANIMATION
        // ============================================
        const breadcrumb = document.querySelector('.breadcrumb');
        if (breadcrumb) {
            breadcrumb.style.opacity = '0';
            breadcrumb.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                breadcrumb.style.transition = 'all 0.5s ease';
                breadcrumb.style.opacity = '1';
                breadcrumb.style.transform = 'translateY(0)';
            }, 100);
        }

        // ============================================
        // PAGE LOAD PERFORMANCE
        // ============================================
        window.addEventListener('load', function() {
            // Hide any loading indicators
            const loader = document.querySelector('.page-loader');
            if (loader) {
                loader.style.display = 'none';
            }
            
            // Trigger post-load animations
            document.body.classList.add('loaded');
        });

        // ============================================
        // ERROR HANDLING
        // ============================================
        window.addEventListener('error', function(e) {
        });

        // ============================================
        // PREVENT MULTIPLE FORM SUBMISSIONS
        // ============================================
        if (form) {
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
            });
        }

        // ============================================
        // BACK TO TOP (Optional Enhancement)
        // ============================================
        const scrollTopBtn = document.createElement('button');
        scrollTopBtn.className = 'scroll-top';
        scrollTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        scrollTopBtn.style.cssText = `
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
            transition: all 0.3s ease;
            box-shadow: var(--shadow-xl);
            z-index: 999;
        `;

        document.body.appendChild(scrollTopBtn);

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.style.opacity = '1';
                scrollTopBtn.style.visibility = 'visible';
            } else {
                scrollTopBtn.style.opacity = '0';
                scrollTopBtn.style.visibility = 'hidden';
            }
        });

        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>