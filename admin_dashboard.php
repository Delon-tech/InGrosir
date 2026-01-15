<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Statistik Utama
$total_users_query = "SELECT COUNT(*) AS total FROM users";
$total_users = mysqli_fetch_assoc(mysqli_query($koneksi, $total_users_query))['total'];

$total_produk_query = "SELECT COUNT(*) AS total FROM produk";
$total_produk = mysqli_fetch_assoc(mysqli_query($koneksi, $total_produk_query))['total'];

$total_pembeli_query = "SELECT COUNT(*) AS total FROM users WHERE peran = 'pembeli'";
$total_pembeli = mysqli_fetch_assoc(mysqli_query($koneksi, $total_pembeli_query))['total'];

$total_penjual_query = "SELECT COUNT(*) AS total FROM users WHERE peran = 'penjual'";
$total_penjual = mysqli_fetch_assoc(mysqli_query($koneksi, $total_penjual_query))['total'];

// TAMBAHAN: Hitung penjual yang pending verifikasi (INI YANG HILANG!)
$pending_sellers_query = "SELECT COUNT(*) AS total FROM users WHERE peran = 'penjual' AND status_verifikasi = 'pending'";
$pending_sellers = mysqli_fetch_assoc(mysqli_query($koneksi, $pending_sellers_query))['total'] ?? 0;

// Total Pesanan & Pendapatan
$total_pesanan_query = "SELECT COUNT(*) AS total, SUM(total_harga) AS pendapatan FROM pesanan WHERE status_pesanan = 'selesai'";
$pesanan_result = mysqli_fetch_assoc(mysqli_query($koneksi, $total_pesanan_query));
$total_pesanan = $pesanan_result['total'] ?? 0;
$total_pendapatan = $pesanan_result['pendapatan'] ?? 0;

// Pesanan Bulan Ini
$bulan_ini_query = "SELECT COUNT(*) AS total FROM pesanan WHERE MONTH(tanggal_pesanan) = MONTH(CURRENT_DATE()) AND YEAR(tanggal_pesanan) = YEAR(CURRENT_DATE())";
$pesanan_bulan_ini = mysqli_fetch_assoc(mysqli_query($koneksi, $bulan_ini_query))['total'] ?? 0;

// Produk Stok Rendah
$stok_rendah_query = "SELECT COUNT(*) AS total FROM produk WHERE stok < 10";
$stok_rendah = mysqli_fetch_assoc(mysqli_query($koneksi, $stok_rendah_query))['total'] ?? 0;

// Data untuk Chart - Pesanan per Bulan (6 bulan terakhir)
$chart_query = "SELECT DATE_FORMAT(tanggal_pesanan, '%Y-%m') AS bulan, COUNT(*) AS total FROM pesanan WHERE tanggal_pesanan >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) GROUP BY bulan ORDER BY bulan ASC";
$chart_result = mysqli_query($koneksi, $chart_query);
$chart_labels = [];
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
    $chart_data[] = (int)$row['total'];
}

// Aktivitas Terbaru
$activity_query = "SELECT u.nama_lengkap, u.peran, u.tanggal_registrasi FROM users u ORDER BY u.tanggal_registrasi DESC LIMIT 5";
$activity_result = mysqli_query($koneksi, $activity_query);

// Produk Terlaris (Top 5)
$top_produk_query = "SELECT p.nama_produk, SUM(dp.jumlah) AS total_terjual FROM detail_pesanan dp JOIN produk p ON dp.produk_id = p.produk_id GROUP BY dp.produk_id ORDER BY total_terjual DESC LIMIT 5";
$top_produk_result = mysqli_query($koneksi, $top_produk_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - InGrosir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* ===== CSS VARIABLES ===== */
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --success: #10b981;
            --purple: #8b5cf6;
            
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            
            --bg-white: #ffffff;
            --bg-gray: #f9fafb;
            --bg-dark: #111827;
            
            --border: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 300ms ease;
            
            --sidebar-width: 280px;
        }
        
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-gray);
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        /* ===== LAYOUT ===== */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: var(--shadow-lg);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
        }
        
        .sidebar-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
        }
        
        .sidebar-logo {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .menu-section {
            margin-bottom: 1.5rem;
        }
        
        .menu-section-title {
            padding: 0 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-light);
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 2rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            font-weight: 500;
        }
        
        .menu-item:hover {
            background: rgba(37, 99, 235, 0.05);
            color: var(--primary);
        }
        
        .menu-item.active {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            font-weight: 600;
        }
        
        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
            border-radius: 0 4px 4px 0;
        }
        
        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 1.125rem;
        }
        
        .menu-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        
        /* ===== TOP BAR ===== */
        .top-bar {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .welcome h1 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .welcome p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .top-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.875rem;
            transition: var(--transition);
            width: 250px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-gray);
            color: var(--text-secondary);
            position: relative;
        }
        
        .btn-icon:hover {
            background: var(--primary);
            color: white;
        }
        
        .notification-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid white;
        }
        
        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        /* Alert Box untuk Pending Sellers */
.alert-box {
    background: linear-gradient(135deg, #fff3cd 0%, #fff9e6 100%);
    border: 2px solid #ffc107;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-lg);
    animation: slideDown 0.5s ease;
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

.alert-box i {
    font-size: 2.5rem;
    color: #ffc107;
    flex-shrink: 0;
}

.alert-content {
    flex: 1;
}

.alert-content h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #856404;
}

.alert-content p {
    font-size: 0.9375rem;
    color: #856404;
    margin: 0;
}

.alert-box .btn-primary {
    background: #ffc107;
    color: #000;
    border: none;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    white-space: nowrap;
}

.alert-box .btn-primary:hover {
    background: #e0a800;
}
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.danger::before { background: var(--danger); }
        .stat-card.info::before { background: var(--info); }
        .stat-card.purple::before { background: var(--purple); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card.primary .stat-icon {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }
        
        .stat-card.success .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stat-card.warning .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stat-card.danger .stat-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .stat-card.info .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .stat-card.purple .stat-icon {
            background: rgba(139, 92, 246, 0.1);
            color: var(--purple);
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-footer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }
        
        .stat-trend.up {
            color: var(--success);
        }
        
        .stat-trend.down {
            color: var(--danger);
        }
        
        /* ===== DASHBOARD GRID ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .card-header h3 {
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-header .btn {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }
        
        /* ===== CHART ===== */
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* ===== ACTIVITY LIST ===== */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius);
            background: var(--bg-gray);
            transition: var(--transition);
        }
        
        .activity-item:hover {
            background: rgba(37, 99, 235, 0.05);
        }
        
        .activity-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .activity-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }
        
        .activity-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .activity-badge.pembeli {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .activity-badge.penjual {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        /* ===== TOP PRODUCTS ===== */
        .product-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
            transition: var(--transition);
        }
        
        .product-item:hover {
            background: rgba(37, 99, 235, 0.05);
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .product-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }
        
        .product-name {
            font-weight: 600;
        }
        
        .product-sales {
            font-weight: 700;
            color: var(--primary);
        }
        
        /* ===== QUICK ACTIONS ===== */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .quick-action-card {
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
        }
        
        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .quick-action-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .quick-action-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .quick-action-desc {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }
        
        /* ===== MOBILE TOGGLE ===== */
        .mobile-toggle {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 999;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .top-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-halved"></i>
                    InGrosir Admin
                </div>
                <p class="sidebar-subtitle">Panel Administrasi</p>
            </div>
            
            <nav class="sidebar-menu">
    <div class="menu-section">
        <div class="menu-section-title">Menu Utama</div>
        <a href="admin_dashboard.php" class="menu-item active">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="admin_users.php" class="menu-item">
            <i class="fas fa-users"></i>
            <span>Kelola Pengguna</span>
            <span class="menu-badge"><?php echo $total_users; ?></span>
        </a>
        <!-- MENU BARU: Verifikasi Penjual -->
        <a href="admin_verify_sellers.php" class="menu-item">
            <i class="fas fa-user-check"></i>
            <span>Verifikasi Penjual</span>
            <?php if ($pending_sellers > 0) { ?>
            <span class="menu-badge"><?php echo $pending_sellers; ?></span>
            <?php } ?>
        </a>
        <a href="admin_produk.php" class="menu-item">
            <i class="fas fa-box"></i>
            <span>Kelola Produk</span>
        </a>
        <a href="admin_top_produk.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Laporan Penjualan</span>
        </a>
    </div>
    <div class="menu-section">
        <div class="menu-section-title">Lainnya</div>
        <a href="index.php" class="menu-item">
            <i class="fas fa-globe"></i>
            <span>Lihat Website</span>
        </a>
        <a href="admin_logout.php" class="menu-item" style="color: var(--danger);">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
    </div>
</nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome">
                    <h1>
                        <span>Dashboard Admin</span>
                        <span style="font-size: 1.5rem;">👋</span>
                    </h1>
                    <p><?php echo date('l, d F Y'); ?></p>
                </div>
                
                <div class="top-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Cari data...">
                    </div>
                    <button class="btn btn-icon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-dot"></span>
                    </button>
                    <a href="admin_users.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Tambah Data
                    </a>
                </div>
            </div>


             <?php if ($pending_sellers > 0) { ?>
            <div class="alert-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <h3>⚠️ Ada <?php echo $pending_sellers; ?> Penjual Menunggu Verifikasi</h3>
                    <p>Terdapat penjual baru yang mendaftar dan menunggu untuk diverifikasi. Silakan tinjau dan verifikasi segera.</p>
                </div>
                <a href="admin_verify_sellers.php" class="btn-primary">
                    <i class="fas fa-eye"></i>
                    Lihat Sekarang
                </a>
            </div>
            <?php } ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Total Pengguna</div>
                            <div class="stat-value"><?php echo $total_users; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <span class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 12%
                        </span>
                        <span style="color: var(--text-secondary);">dari bulan lalu</span>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Total Produk</div>
                            <div class="stat-value"><?php echo $total_produk; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <a href="admin_produk.php" style="color: var(--success); text-decoration: none; font-weight: 600;">
                            Kelola Produk <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Pesanan Bulan Ini</div>
                            <div class="stat-value"><?php echo $pesanan_bulan_ini; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <span class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 8%
                        </span>
                        <span style="color: var(--text-secondary);">dari bulan lalu</span>
                    </div>
                </div>

                <div class="stat-card danger">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Stok Rendah</div>
                            <div class="stat-value"><?php echo $stok_rendah; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <span style="color: var(--text-secondary);">Produk perlu restock</span>
                    </div>
                </div>

                <?php if ($pending_sellers > 0) { ?>
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Pending Verifikasi</div>
                            <div class="stat-value"><?php echo $pending_sellers; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Total Pembeli</div>
                            <div class="stat-value"><?php echo $total_pembeli; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <span style="color: var(--text-secondary);">Pengguna aktif</span>
                    </div>
                </div>

                <div class="stat-card purple">
    <div class="stat-header">
        <div class="stat-content">
            <div class="stat-label">Penjual Terverifikasi</div>
            <div class="stat-value"><?php echo $total_penjual - $pending_sellers; ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>
    <div class="stat-footer">
        <span style="color: var(--text-secondary);">Seller aktif</span>
    </div>
</div>

<?php if ($pending_sellers > 0) { ?>
<div class="stat-card warning">
    <div class="stat-header">
        <div class="stat-content">
            <div class="stat-label">Pending Verifikasi</div>
            <div class="stat-value"><?php echo $pending_sellers; ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
    </div>
    <div class="stat-footer">
        <a href="admin_verify_sellers.php" style="color: var(--warning); text-decoration: none; font-weight: 600;">
            Verifikasi Sekarang <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>
<?php } ?>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Chart Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-chart-area"></i>
                            Grafik Pesanan
                        </h3>
                        <select class="btn" style="padding: 0.5rem 1rem;">
                            <option>6 Bulan Terakhir</option>
                            <option>12 Bulan Terakhir</option>
                            <option>Tahun Ini</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-fire"></i>
                            Produk Terlaris
                        </h3>
                        <a href="admin_top_produk.php" class="btn btn-primary">
                            Lihat Semua
                        </a>
                    </div>
                    <div class="product-list">
                        <?php 
                        $rank = 1;
                        if (mysqli_num_rows($top_produk_result) > 0) {
                            while ($produk = mysqli_fetch_assoc($top_produk_result)) {
                        ?>
                        <div class="product-item">
                            <div class="product-info">
                                <div class="product-rank"><?php echo $rank++; ?></div>
                                <div class="product-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                            </div>
                            <div class="product-sales"><?php echo $produk['total_terjual']; ?> terjual</div>
                        </div>
                        <?php 
                            }
                        } else {
                        ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                            Belum ada data penjualan
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-clock"></i>
                        Aktivitas Terbaru
                    </h3>
                    <a href="#" class="btn btn-primary">
                        Lihat Semua
                    </a>
                </div>
                <div class="activity-list">
                    <?php 
                    if (mysqli_num_rows($activity_result) > 0) {
                        while ($activity = mysqli_fetch_assoc($activity_result)) {
                    ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <?php echo strtoupper(substr($activity['nama_lengkap'], 0, 1)); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($activity['nama_lengkap']); ?></div>
                            <div class="activity-meta">
                                Terdaftar sebagai 
                                <span class="activity-badge <?php echo $activity['peran']; ?>">
                                    <?php echo ucfirst($activity['peran']); ?>
                                </span>
                                • <?php echo date('d M Y, H:i', strtotime($activity['tanggal_registrasi'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php 
                        }
                    } else {
                    ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        Belum ada aktivitas
                    </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 2rem;">
                <h3 style="margin-bottom: 1.5rem; font-size: 1.25rem;">
                    <i class="fas fa-bolt"></i> Aksi Cepat
                </h3>
                <div class="quick-actions-grid">
                    <a href="admin_users.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="quick-action-title">Tambah Pengguna</div>
                        <div class="quick-action-desc">Daftarkan pengguna baru</div>
                    </a>
                    
                    <a href="admin_produk.php" class="quick-action-card">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, var(--success), var(--accent));">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="quick-action-title">Kelola Produk</div>
                        <div class="quick-action-desc">Tambah atau edit produk</div>
                    </a>
                    
                    <a href="admin_top_produk.php" class="quick-action-card">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, var(--warning), var(--danger));">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="quick-action-title">Lihat Laporan</div>
                        <div class="quick-action-desc">Analisis penjualan</div>
                    </a>
                    
                    <a href="#" class="quick-action-card">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, var(--info), var(--purple));">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="quick-action-title">Pengaturan</div>
                        <div class="quick-action-desc">Konfigurasi sistem</div>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        // Chart.js Configuration
        const ctx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Total Pesanan',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(37, 99, 235, 1)',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            // Implement search logic here
            console.log('Searching for:', searchTerm);
        });
    </script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
