<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Filter parameters
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'selesai';

// Total pendapatan (semua waktu)
$query_pendapatan = "SELECT SUM(total_harga) AS total FROM pesanan WHERE user_id_penjual = ? AND status_pesanan = 'selesai'";
$stmt_pendapatan = mysqli_prepare($koneksi, $query_pendapatan);
mysqli_stmt_bind_param($stmt_pendapatan, "i", $user_id);
mysqli_stmt_execute($stmt_pendapatan);
$result_pendapatan = mysqli_stmt_get_result($stmt_pendapatan);
$total_pendapatan = mysqli_fetch_assoc($result_pendapatan)['total'] ?? 0;

// Pendapatan bulan ini
$query_bulan_ini = "SELECT SUM(total_harga) AS total FROM pesanan WHERE user_id_penjual = ? AND status_pesanan = 'selesai' AND DATE_FORMAT(tanggal_pesanan, '%Y-%m') = ?";
$stmt_bulan_ini = mysqli_prepare($koneksi, $query_bulan_ini);
$bulan_sekarang = date('Y-m');
mysqli_stmt_bind_param($stmt_bulan_ini, "is", $user_id, $bulan_sekarang);
mysqli_stmt_execute($stmt_bulan_ini);
$result_bulan_ini = mysqli_stmt_get_result($stmt_bulan_ini);
$pendapatan_bulan_ini = mysqli_fetch_assoc($result_bulan_ini)['total'] ?? 0;

// Total pesanan selesai
$query_total_pesanan = "SELECT COUNT(*) AS total FROM pesanan WHERE user_id_penjual = ? AND status_pesanan = 'selesai'";
$stmt_total_pesanan = mysqli_prepare($koneksi, $query_total_pesanan);
mysqli_stmt_bind_param($stmt_total_pesanan, "i", $user_id);
mysqli_stmt_execute($stmt_total_pesanan);
$result_total_pesanan = mysqli_stmt_get_result($stmt_total_pesanan);
$total_pesanan_selesai = mysqli_fetch_assoc($result_total_pesanan)['total'] ?? 0;

// Rata-rata nilai pesanan
$rata_rata_pesanan = $total_pesanan_selesai > 0 ? $total_pendapatan / $total_pesanan_selesai : 0;

// Daftar pesanan dengan filter
$query_pesanan = "SELECT p.*, u.nama_lengkap FROM pesanan p 
                  JOIN users u ON p.user_id_pembeli = u.user_id 
                  WHERE p.user_id_penjual = ? AND p.status_pesanan = ?";

if (!empty($filter_bulan)) {
    $query_pesanan .= " AND DATE_FORMAT(p.tanggal_pesanan, '%Y-%m') = ?";
}

$query_pesanan .= " ORDER BY p.tanggal_pesanan DESC";

$stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);

if (!empty($filter_bulan)) {
    mysqli_stmt_bind_param($stmt_pesanan, "iss", $user_id, $filter_status, $filter_bulan);
} else {
    mysqli_stmt_bind_param($stmt_pesanan, "is", $user_id, $filter_status);
}

mysqli_stmt_execute($stmt_pesanan);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan);

// Data untuk grafik penjualan 6 bulan terakhir
$query_chart = "SELECT DATE_FORMAT(tanggal_pesanan, '%Y-%m') AS bulan, 
                SUM(total_harga) AS total_penjualan,
                COUNT(*) AS jumlah_pesanan
                FROM pesanan 
                WHERE user_id_penjual = ? AND status_pesanan = 'selesai' 
                GROUP BY bulan 
                ORDER BY bulan DESC 
                LIMIT 6";
$stmt_chart = mysqli_prepare($koneksi, $query_chart);
mysqli_stmt_bind_param($stmt_chart, "i", $user_id);
mysqli_stmt_execute($stmt_chart);
$result_chart = mysqli_stmt_get_result($stmt_chart);

$chart_labels = [];
$chart_data = [];
$chart_orders = [];

while ($row = mysqli_fetch_assoc($result_chart)) {
    array_unshift($chart_labels, date('M Y', strtotime($row['bulan'] . '-01')));
    array_unshift($chart_data, (float) $row['total_penjualan']);
    array_unshift($chart_orders, (int) $row['jumlah_pesanan']);
}

// Top selling products
$query_top_products = "SELECT p.nama_produk, SUM(dp.jumlah) AS total_terjual, SUM(dp.jumlah * dp.harga_per_unit) AS total_pendapatan
                       FROM detail_pesanan dp
                       JOIN produk p ON dp.produk_id = p.produk_id
                       JOIN pesanan pe ON dp.pesanan_id = pe.pesanan_id
                       WHERE p.user_id = ? AND pe.status_pesanan = 'selesai'
                       GROUP BY dp.produk_id
                       ORDER BY total_terjual DESC
                       LIMIT 5";
$stmt_top = mysqli_prepare($koneksi, $query_top_products);
mysqli_stmt_bind_param($stmt_top, "i", $user_id);
mysqli_stmt_execute($stmt_top);
$result_top = mysqli_stmt_get_result($stmt_top);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - InGrosir</title>
    <!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --success: #10b981;
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 2rem;
            border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
        }
        
        .sidebar-logo {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .user-info h3 {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .user-info p {
            font-size: 0.8rem;
            color: #6b7280;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 2rem;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary);
        }
        
        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
        }
        
        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 2rem;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        /* Stats Cards Enhancement */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
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
        
        .stat-card.info .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .stat-trend {
            font-size: 0.8rem;
            color: var(--success);
            font-weight: 600;
        }
        
        /* Chart Card */
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .chart-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .chart-header p {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0 0 1.5rem 0;
        }
        
        /* Product List */
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
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .product-item:hover {
            background: #e5e7eb;
            transform: translateX(5px);
        }
        
        .product-info h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }
        
        .product-meta {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .product-sales {
            text-align: right;
        }
        
        .sales-count {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .sales-revenue {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        /* Orders Card */
        .orders-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .orders-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .orders-card-header h5 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .table-responsive {
            border-radius: 0 0 16px 16px;
        }
        
        /* Sales Table */
        .sales-table {
            width: 100%;
            margin-bottom: 0;
        }
        
        .sales-table thead {
            background: #f8f9fa;
        }
        
        .sales-table th {
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e9ecef;
        }
        
        .sales-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        .sales-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .sales-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Status Badges with Bootstrap */
        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.5;
            margin-bottom: 1rem;
            display: block;
        }
        
        .empty-state h3 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        /* Summary Footer */
        .summary-footer {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .summary-footer h3 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .summary-total {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 1rem 0;
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            z-index: 999;
            transition: all 0.3s ease;
        }
        
        .mobile-toggle:hover {
            transform: scale(1.1);
        }
        
        /* Print Styles */
        @media print {
            .sidebar,
            .page-header .btn,
            .filter-section,
            .mobile-toggle {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            body {
                background: white;
            }
        }
        
        /* Responsive */
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
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .summary-total {
                font-size: 2rem;
            }
        }
    </style>
       
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">InGrosir</div>
            <div class="sidebar-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['nama_grosir'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['nama_grosir']); ?></h3>
                    <p>Penjual</p>
                </div>
            </div>
        </div>
        <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="produk_list.php" class="menu-item">
            <i class="fas fa-box"></i>
            <span>Kelola Produk</span>
        </a>
        <a href="pesanan.php" class="menu-item">
            <i class="fas fa-shopping-cart"></i>
            <span>Kelola Pesanan</span>
        </a>
        <a href="kelola_voucher.php" class="menu-item">
                <i class="fas fa-ticket-alt"></i>
                <span>Kelola Voucher</span>
            </a>
        <a href="kelola_metode_pembayaran.php" class="menu-item">
            <i class="fas fa-credit-card"></i>
            <span>Metode Pembayaran</span>
        </a>
        <a href="laporan_penjualan.php" class="menu-item active">
            <i class="fas fa-chart-line"></i>
            <span>Laporan Penjualan</span>
        </a>
        <a href="profil.php" class="menu-item">
            <i class="fas fa-user"></i>
            <span>Profil Saya</span>
        </a>
        <a href="index.php" class="menu-item">
            <i class="fas fa-globe"></i>
            <span>Halaman Utama</span>
        </a>
        <a href="logout.php" class="menu-item text-danger">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content">
    <!-- Page Header -->
    <div class="page-header p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="page-title">
                    <h1><i class="fas fa-chart-line me-2"></i>Laporan Penjualan</h1>
                    <p>Analisis dan statistik penjualan toko Anda</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        <i class="fas fa-print me-2"></i>Cetak Laporan
                    </button>
                    <button onclick="exportToCSV()" class="btn btn-success">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-label">Total Pendapatan</div>
                <div class="stat-value">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                <div class="stat-footer">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Dari semua waktu</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-label">Pendapatan Bulan Ini</div>
                <div class="stat-value">Rp <?php echo number_format($pendapatan_bulan_ini, 0, ',', '.'); ?></div>
                <div class="stat-footer">
                    <i class="fas fa-calendar-check"></i>
                    <span><?php echo date('F Y'); ?></span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-label">Total Pesanan Selesai</div>
                <div class="stat-value"><?php echo number_format($total_pesanan_selesai); ?></div>
                <div class="stat-footer">
                    <i class="fas fa-check-circle"></i>
                    <span>Pesanan berhasil</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-label">Rata-rata Nilai Pesanan</div>
                <div class="stat-value">Rp <?php echo number_format($rata_rata_pesanan, 0, ',', '.'); ?></div>
                <div class="stat-footer">
                    <i class="fas fa-info-circle"></i>
                    <span>Per transaksi</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Grafik Penjualan 6 Bulan Terakhir</h3>
                    <p>Tren pendapatan dan jumlah pesanan</p>
                </div>
                <canvas id="salesChart" height="80"></canvas>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Produk Terlaris</h3>
                    <p>Top 5 produk dengan penjualan tertinggi</p>
                </div>
                <div class="product-list">
                    <?php 
                    if (mysqli_num_rows($result_top) > 0) {
                        $rank = 1;
                        while ($product = mysqli_fetch_assoc($result_top)) { 
                    ?>
                    <div class="product-item">
                        <div class="product-info">
                            <h4><?php echo $rank; ?>. <?php echo htmlspecialchars($product['nama_produk']); ?></h4>
                            <div class="product-meta"><?php echo $product['total_terjual']; ?> unit terjual</div>
                        </div>
                        <div class="product-sales">
                            <div class="sales-count"><?php echo $product['total_terjual']; ?></div>
                            <div class="sales-revenue">Rp <?php echo number_format($product['total_pendapatan'], 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    <?php 
                            $rank++;
                        }
                    } else {
                    ?>
                    <div class="empty-state" style="padding: 2rem;">
                        <i class="fas fa-box-open"></i>
                        <p>Belum ada data produk terlaris</p>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                    <i class="fas fa-calendar me-2"></i>Filter Bulan
                </label>
                <input type="month" name="bulan" class="form-control" value="<?php echo $filter_bulan; ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                    <i class="fas fa-filter me-2"></i>Status Pesanan
                </label>
                <select name="status" class="form-select">
                    <option value="selesai" <?php echo ($filter_status == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                    <option value="dikirim" <?php echo ($filter_status == 'dikirim') ? 'selected' : ''; ?>>Dikirim</option>
                    <option value="diproses" <?php echo ($filter_status == 'diproses') ? 'selected' : ''; ?>>Diproses</option>
                    <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="dibatalkan" <?php echo ($filter_status == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
            </div>
            
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-search me-2"></i>Terapkan Filter
                </button>
                <a href="laporan_penjualan.php" class="btn btn-outline-primary flex-fill">
                    <i class="fas fa-redo me-2"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Sales Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Riwayat Transaksi Penjualan</h5>
                <span class="text-muted small">
                    <?php echo mysqli_num_rows($result_pesanan); ?> transaksi ditemukan
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="orders-table table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
<th>Pembeli</th>
<th>Tanggal</th>
<th>Total Harga</th>
<th>Status</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>
<?php 
                     if (mysqli_num_rows($result_pesanan) > 0) {
                         while ($row = mysqli_fetch_assoc($result_pesanan)) { 
                             $status_class = '';
                             switch($row['status_pesanan']) {
                                 case 'pending': $status_class = 'bg-warning text-dark'; break;
                                 case 'diproses': $status_class = 'bg-info text-white'; break;
                                 case 'dikirim': $status_class = 'bg-primary'; break;
                                 case 'selesai': $status_class = 'bg-success'; break;
                                 case 'dibatalkan': $status_class = 'bg-danger'; break;
                             }
                     ?>
<tr>
<td><strong class="text-primary">#<?php echo $row['pesanan_id']; ?></strong></td>
<td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
<td><?php echo date('d M Y, H:i', strtotime($row['tanggal_pesanan'])); ?></td>
<td><strong class="text-primary">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></strong></td>
<td>
<span class="status-badge <?php echo $status_class; ?>">
<?php echo ucfirst($row['status_pesanan']); ?>
</span>
</td>
<td>
<a href="detail_pesanan.php?id=<?php echo $row['pesanan_id']; ?>" class="btn btn-sm btn-outline-primary">
<i class="fas fa-eye"></i> Detail
</a>
</td>
</tr>
<?php 
                         }
                     } else { 
                     ?>
<tr>
<td colspan="6">
<div class="empty-state">
<i class="fas fa-inbox"></i>
<h3>Tidak Ada Data</h3>
<p>Belum ada transaksi dengan filter yang dipilih</p>
</div>
</td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
<!-- Summary Footer -->
    <div class="summary-footer">
        <h3>Total dari Filter Saat Ini</h3>
        <div class="summary-total">
            Rp <?php 
                $total_filtered = 0;
                mysqli_data_seek($result_pesanan, 0);
                while ($row = mysqli_fetch_assoc($result_pesanan)) {
                    $total_filtered += $row['total_harga'];
                }
                echo number_format($total_filtered, 0, ',', '.'); 
            ?>
        </div>
        <p class="text-muted mb-0">
            Dari <?php echo mysqli_num_rows($result_pesanan); ?> transaksi yang ditampilkan
        </p>
    </div>
</main>

<!-- Mobile Menu Toggle -->
<button class="mobile-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Chart.js configuration
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Total Penjualan (Rp)',
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
                    pointHoverRadius: 7,
                    yAxisID: 'y'
                },
                {
                    label: 'Jumlah Pesanan',
                    data: <?php echo json_encode($chart_orders); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(16, 185, 129, 1)',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            } else {
                                label += context.parsed.y + ' pesanan';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return value + ' pcs';
                        }
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

    // Export to CSV function
    function exportToCSV() {
        let csv = 'ID Pesanan,Pembeli,Tanggal,Total Harga,Status\n';
        
        const table = document.querySelector('.orders-table tbody');
        const rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td');
            if (cols.length > 1) {
                const rowData = [
                    cols[0].innerText.trim(),
                    cols[1].innerText.trim(),
                    cols[2].innerText.trim(),
                    cols[3].innerText.trim().replace('Rp ', ''),
                    cols[4].innerText.trim()
                ];
                csv += rowData.join(',') + '\n';
            }
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'laporan_penjualan_' + new Date().getTime() + '.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
    
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-toggle');
        
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
</script>
</body>
</html>