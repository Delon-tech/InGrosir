<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

$user_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$user_id) {
    header("Location: index.php");
    exit();
}

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

// Mengambil data grosir
$query_grosir = "SELECT nama_grosir, alamat_grosir, nomor_telepon, gambar_toko FROM users WHERE user_id = ? AND peran = 'penjual' AND status_verifikasi = 'approved'";
$stmt_grosir = mysqli_prepare($koneksi, $query_grosir);
mysqli_stmt_bind_param($stmt_grosir, "i", $user_id);
mysqli_stmt_execute($stmt_grosir);
$result_grosir = mysqli_stmt_get_result($stmt_grosir);
$grosir = mysqli_fetch_assoc($result_grosir);

if (!$grosir) {
    echo "<p>Toko tidak ditemukan atau belum diverifikasi.</p>";
    exit();
}

// Filter dan Sorting
$kategori_filter = isset($_GET['kategori']) ? intval($_GET['kategori']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query_produk = "SELECT * FROM produk WHERE user_id = ? AND is_active = 1";
$params = [$user_id];
$types = "i";

if (!empty($kategori_filter)) {
    $query_produk .= " AND kategori_id = ?";
    $params[] = $kategori_filter;
    $types .= "i";
}

if (!empty($search)) {
    $query_produk .= " AND nama_produk LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= "s";
}

if ($sort_by == 'price_asc') {
    $query_produk .= " ORDER BY harga_grosir ASC";
} elseif ($sort_by == 'price_desc') {
    $query_produk .= " ORDER BY harga_grosir DESC";
} elseif ($sort_by == 'name_asc') {
    $query_produk .= " ORDER BY nama_produk ASC";
} else {
    $query_produk .= " ORDER BY produk_id DESC";
}

$stmt_produk = mysqli_prepare($koneksi, $query_produk);
mysqli_stmt_bind_param($stmt_produk, $types, ...$params);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);

// Ambil kategori
$kategori_query = "SELECT * FROM kategori_produk ORDER BY nama_kategori ASC";
$kategori_result = mysqli_query($koneksi, $kategori_query);

// Hitung total produk
$total_produk = mysqli_num_rows($result_produk);

// Format nomor WhatsApp
$whatsapp_number = !empty($grosir['nomor_telepon']) ? preg_replace('/[^0-9]/', '', $grosir['nomor_telepon']) : '';
if ($whatsapp_number && substr($whatsapp_number, 0, 1) === '0') {
    $whatsapp_number = '62' . substr($whatsapp_number, 1);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($grosir['nama_grosir']); ?> - InGrosir</title>
    
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
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-gray);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* ============================================
           HEADER - SAMA SEPERTI INDEX.PHP
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
           STORE SPECIFIC STYLES
           ============================================ */
        .store-header {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-top: 2rem;
        }

        .store-header-top {
            padding: 2rem;
        }
        
        .store-image {
            width: 150px;
            height: 150px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            box-shadow: var(--shadow);
        }
        
        .store-placeholder {
            width: 150px;
            height: 150px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 800;
            box-shadow: var(--shadow);
        }
        
        .store-info h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
        }
        
        .store-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .meta-item i {
            color: var(--primary);
        }
        
        .meta-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .meta-item a:hover {
            text-decoration: underline;
        }
        
        .stat-badge {
            background: var(--bg-gray);
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-top: 0.25rem;
        }

        .store-location {
            padding: 2rem;
            background: var(--bg-gray);
            border-top: 1px solid var(--border);
        }

        .location-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .location-header i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .location-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .location-address {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            padding-left: 2rem;
        }

        .map-container {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            background: var(--bg-white);
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: var(--text-secondary);
        }

        .map-loading i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--border-light);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .product-content {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-content h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-weight: 600;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .product-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .product-rating i {
            color: var(--accent);
        }
        
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: #25D366;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
            cursor: pointer;
            z-index: 999;
            transition: var(--transition);
            text-decoration: none;
            animation: pulse 2s infinite;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6);
            color: white;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
            }
            50% {
                box-shadow: 0 4px 20px rgba(37, 211, 102, 0.7);
            }
        }

        .whatsapp-tooltip {
            position: absolute;
            right: 70px;
            background: white;
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            white-space: nowrap;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 600;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition);
        }

        .whatsapp-float:hover .whatsapp-tooltip {
            opacity: 1;
        }
        
        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }
        
        /* ============================================
           RESPONSIVE - MOBILE
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

            .store-info h1 {
                font-size: 1.5rem;
            }

            .store-meta {
                flex-direction: column;
                gap: 0.75rem;
            }

            .map-container {
                height: 300px;
            }

            .whatsapp-float {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                bottom: 20px;
                right: 20px;
            }

            .whatsapp-tooltip {
                display: none;
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
    <!-- HEADER - -->
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
                        <!-- NOTIFICATION BELL -->
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
                <a href="index.php#toko"><i class="fas fa-store"></i> Toko</a>
                <a href="index.php#produk"><i class="fas fa-box"></i> Produk</a>
                <?php if (isset($_SESSION['user_id'])) { ?>
                    <a href="logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                <?php } ?>
            </div>
        </nav>
    </header>

    <div class="container my-4">
        <!-- Store Header -->
        <div class="store-header mb-4" data-aos="fade-up">
            <div class="store-header-top">
                <div class="row align-items-center g-4">
                    <div class="col-md-auto text-center text-md-start">
                        <?php if (!empty($grosir['gambar_toko'])) { ?>
                            <img src="<?php echo htmlspecialchars($grosir['gambar_toko']); ?>" 
                                 alt="<?php echo htmlspecialchars($grosir['nama_grosir']); ?>" 
                                 class="store-image">
                        <?php } else { ?>
                            <div class="store-placeholder">
                                <?php echo strtoupper(substr($grosir['nama_grosir'], 0, 1)); ?>
                            </div>
                        <?php } ?>
                    </div>
                    
                    <div class="col-md">
                        <div class="store-info">
                            <h1><?php echo htmlspecialchars($grosir['nama_grosir']); ?></h1>
                            
                            <div class="store-meta">
                                <?php if (!empty($grosir['alamat_grosir'])) { ?>
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($grosir['alamat_grosir']); ?></span>
                                </div>
                                <?php } ?>
                                
                                <?php if (!empty($grosir['nomor_telepon'])) { ?>
                                <div class="meta-item">
                                    <i class="fas fa-phone"></i>
                                    <a href="tel:<?php echo htmlspecialchars($grosir['nomor_telepon']); ?>">
                                        <?php echo htmlspecialchars($grosir['nomor_telepon']); ?>
                                    </a>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-auto">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="stat-badge">
                                    <div class="stat-value"><?php echo $total_produk; ?></div>
                                    <div class="stat-label">Produk</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-badge">
                                    <div class="stat-value">4.8</div>
                                    <div class="stat-label">Rating</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-badge">
                                    <div class="stat-value">120</div>
                                    <div class="stat-label">Ulasan</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-badge">
                                    <div class="stat-value">500+</div>
                                    <div class="stat-label">Terjual</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Store Location with Maps -->
            <?php if (!empty($grosir['alamat_grosir'])) { ?>
            <div class="store-location" data-aos="fade-up" data-aos-delay="100">
                <div class="location-header">
                    <i class="fas fa-location-dot"></i>
                    <h3>Lokasi Toko</h3>
                </div>
                <div class="location-address">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($grosir['alamat_grosir']); ?>
                </div>
                <div class="map-container" id="mapContainer">
                    <div class="map-loading">
                        <i class="fas fa-spinner"></i>
                        <p>Memuat peta...</p>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar mb-4" data-aos="fade-up" data-aos-delay="200">
            <form action="store.php" method="GET">
                <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control"
                            placeholder="Cari produk di toko ini..." 
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    
                    <div class="col-md-3">
                        <select name="kategori" class="form-select">
                            <option value="">Semua Kategori</option>
                            <?php 
                            mysqli_data_seek($kategori_result, 0);
                            while ($kategori = mysqli_fetch_assoc($kategori_result)) { 
                            ?>
                                <option value="<?php echo $kategori['kategori_id']; ?>" 
                                    <?php echo ($kategori_filter == $kategori['kategori_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="sort" class="form-select">
                            <option value="default" <?php echo ($sort_by == 'default') ? 'selected' : ''; ?>>Terbaru</option>
                            <option value="name_asc" <?php echo ($sort_by == 'name_asc') ? 'selected' : ''; ?>>Nama A-Z</option>
                            <option value="price_asc" <?php echo ($sort_by == 'price_asc') ? 'selected' : ''; ?>>Harga Terendah</option>
                            <option value="price_desc" <?php echo ($sort_by == 'price_desc') ? 'selected' : ''; ?>>Harga Tertinggi</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                            Cari
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Section Header -->
        <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up" data-aos-delay="300">
            <h2 class="mb-0">Produk yang Tersedia</h2>
            <div class="text-muted small">
                Menampilkan <?php echo $total_produk; ?> produk
            </div>
        </div>

        <!-- Product Grid -->
        <div class="row g-4">
            <?php 
            if (mysqli_num_rows($result_produk) > 0) {
                $delay = 100;
                mysqli_data_seek($result_produk, 0);
                while ($produk = mysqli_fetch_assoc($result_produk)) {
                    $gambar_path = !empty($produk['gambar_produk']) ? "uploads/" . htmlspecialchars($produk['gambar_produk']) : "https://via.placeholder.com/250x200/667eea/ffffff?text=" . urlencode(substr($produk['nama_produk'], 0, 10));
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <a href="produk_detail.php?id=<?php echo $produk['produk_id']; ?>" class="product-card">
                    <img src="<?php echo $gambar_path; ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="product-image">
                    <div class="product-content">
                        <h3><?php echo htmlspecialchars($produk['nama_produk']); ?></h3>
                        <div class="product-price">
                            Rp <?php echo number_format($produk['harga_grosir'], 0, ',', '.'); ?>
                        </div>
                        <div class="product-meta">
                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <span>4.5</span>
                            </div>
                            <span>|</span>
                            <span>Stok: <?php echo $produk['stok'] ?? 'Tersedia'; ?></span>
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
                        <p>Toko ini belum memiliki produk atau produk yang Anda cari tidak tersedia.</p>
                        <a href="store.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                            <i class="fas fa-redo"></i>
                            Lihat Semua Produk
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- WhatsApp Float Button -->
    <?php if (!empty($whatsapp_number)) { ?>
    <a href="https://wa.me/<?php echo $whatsapp_number; ?>?text=Halo%20<?php echo urlencode($grosir['nama_grosir']); ?>,%20saya%20tertarik%20dengan%20produk%20Anda" 
       class="whatsapp-float" 
       target="_blank"
       data-aos="zoom-in"
       data-aos-delay="500">
        <span class="whatsapp-tooltip">Chat via WhatsApp</span>
        <i class="fab fa-whatsapp"></i>
    </a>
    <?php } ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS Animation
        AOS.init({
            duration: 600,
            easing: 'ease-out-cubic',
            once: true,
            offset: 50
        });

        // ============================================
        // MOBILE MENU HANDLER (SAMA SEPERTI INDEX.PHP)
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
        // NOTIFICATION SYSTEM (SAMA SEPERTI INDEX.PHP)
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
        // GOOGLE MAPS LOADER
        // ============================================
        window.addEventListener('DOMContentLoaded', function() {
            const storeAddress = <?php echo json_encode($grosir['alamat_grosir'] ?? ''); ?>;
            
            if (storeAddress) {
                const encodedAddress = encodeURIComponent(storeAddress);
                const mapContainer = document.getElementById('mapContainer');
                const iframe = document.createElement('iframe');

                iframe.src = `https://www.google.com/maps?q=${encodedAddress}&output=embed&z=16`;
                iframe.allowFullscreen = true;
                iframe.loading = 'lazy';
                iframe.referrerPolicy = 'no-referrer-when-downgrade';
                
                mapContainer.innerHTML = '';
                mapContainer.appendChild(iframe);
            }
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
    </script>
</body>
</html>