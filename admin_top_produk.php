<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Filter periode
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan_ini';

// Tentukan range tanggal berdasarkan periode
switch ($periode) {
    case 'hari_ini':
        $where_date = "DATE(tanggal_pesanan) = CURDATE()";
        $group_by = "DATE_FORMAT(tanggal_pesanan, '%H:00')";
        $periode_label = "Hari Ini";
        break;
    case 'minggu_ini':
        $where_date = "YEARWEEK(tanggal_pesanan) = YEARWEEK(NOW())";
        $group_by = "DATE(tanggal_pesanan)";
        $periode_label = "Minggu Ini";
        break;
    case 'bulan_ini':
        $where_date = "MONTH(tanggal_pesanan) = MONTH(NOW()) AND YEAR(tanggal_pesanan) = YEAR(NOW())";
        $group_by = "DATE(tanggal_pesanan)";
        $periode_label = "Bulan Ini";
        break;
    case 'tahun_ini':
        $where_date = "YEAR(tanggal_pesanan) = YEAR(NOW())";
        $group_by = "DATE_FORMAT(tanggal_pesanan, '%Y-%m')";
        $periode_label = "Tahun Ini";
        break;
    default:
        $where_date = "1=1";
        $group_by = "DATE_FORMAT(tanggal_pesanan, '%Y-%m')";
        $periode_label = "Semua";
}

// Statistik Umum
$stats_query = "SELECT 
    COUNT(*) as total_pesanan,
    SUM(total_harga) as total_pendapatan,
    AVG(total_harga) as rata_rata_pesanan,
    SUM(CASE WHEN status_pesanan = 'selesai' THEN 1 ELSE 0 END) as pesanan_selesai
FROM pesanan WHERE $where_date";
$stats_result = mysqli_fetch_assoc(mysqli_query($koneksi, $stats_query));

// Top 10 Produk Terlaris
$top_produk_query = "SELECT p.nama_produk, p.gambar_produk, SUM(dp.jumlah) AS total_terjual, SUM(dp.harga_per_unit * dp.jumlah) as total_pendapatan
FROM detail_pesanan dp 
JOIN produk p ON dp.produk_id = p.produk_id 
JOIN pesanan ps ON dp.pesanan_id = ps.pesanan_id
WHERE $where_date
GROUP BY dp.produk_id 
ORDER BY total_terjual DESC 
LIMIT 10";
$top_produk_result = mysqli_query($koneksi, $top_produk_query);

// Top Penjual
$top_penjual_query = "SELECT u.nama_grosir, u.gambar_toko, COUNT(p.pesanan_id) as total_pesanan, SUM(p.total_harga) as total_pendapatan
FROM pesanan p
JOIN users u ON p.user_id_penjual = u.user_id
WHERE $where_date
GROUP BY p.user_id_penjual
ORDER BY total_pendapatan DESC
LIMIT 5";
$top_penjual_result = mysqli_query($koneksi, $top_penjual_query);

// Data untuk Chart
$chart_query = "SELECT $group_by as periode, COUNT(*) as total_pesanan, SUM(total_harga) as total_pendapatan
FROM pesanan
WHERE $where_date
GROUP BY periode
ORDER BY periode ASC";
$chart_result = mysqli_query($koneksi, $chart_query);

$chart_labels = [];
$chart_pesanan = [];
$chart_pendapatan = [];

while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_labels[] = $row['periode'];
    $chart_pesanan[] = (int)$row['total_pesanan'];
    $chart_pendapatan[] = (float)$row['total_pendapatan'];
}

// Status Pesanan
$status_query = "SELECT status_pesanan, COUNT(*) as total FROM pesanan WHERE $where_date GROUP BY status_pesanan";
$status_result = mysqli_query($koneksi, $status_query);
$status_data = [];
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_data[$row['status_pesanan']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - InGrosir Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
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
            --border: #e5e7eb;
            
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 300ms ease;
            --sidebar-width: 280px;
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
        }
        
        /* Sidebar styles - reuse from previous */
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
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
        }
        
        .menu-item i {
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .page-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title h1 {
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-actions {
            display: flex;
            gap: 0.75rem;
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
            background: var(--primary);
            color: white;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Period Filter */
        .period-filter {
            display: flex;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
        }
        
        .period-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            background: transparent;
            color: var(--text-secondary);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.875rem;
        }
        
        .period-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .period-btn:hover:not(.active) {
            background: white;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
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
        
        .stat-card.purple .stat-icon {
            background: rgba(139, 92, 246, 0.1);
            color: var(--purple);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* Charts */
        .charts-grid {
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
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .table-header h3 {
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--bg-gray);
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        
        tbody tr:hover {
            background: var(--bg-gray);
        }
        
        td {
            padding: 1rem;
            font-size: 0.875rem;
        }
        
        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }
        
        .rank-1 { background: #fbbf24; color: white; }
        .rank-2 { background: #94a3b8; color: white; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-other { background: var(--bg-gray); color: var(--text-secondary); }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            border-radius: var(--radius);
            object-fit: cover;
            background: var(--bg-gray);
        }
        
        .product-name {
            font-weight: 600;
        }
        
        /* Mobile Toggle */
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
        
        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
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
            
            .page-title {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .period-filter {
                flex-wrap: wrap;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
                    <a href="admin_dashboard.php" class="menu-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="admin_users.php" class="menu-item">
                        <i class="fas fa-users"></i>
                        <span>Kelola Pengguna</span>
                    </a>
                    <a href="admin_verify_sellers.php" class="menu-item">
                            <i class="fas fa-user-check"></i>
                            <span>Verifikasi Penjual</span>
                    </a>
                    <a href="admin_produk.php" class="menu-item">
                        <i class="fas fa-box"></i>
                        <span>Kelola Produk</span>
                    </a>
                    <a href="admin_top_produk.php" class="menu-item active">
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <div>
                        <h1>
                            <i class="fas fa-chart-line"></i>
                            Laporan Penjualan
                        </h1>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Periode: <strong><?php echo $periode_label; ?></strong></p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i>
                            Cetak
                        </button>
                        <button class="btn btn-success" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i>
                            Export PDF
                        </button>
                        <button class="btn" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </button>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <div class="period-filter">
                        <a href="?periode=hari_ini" class="period-btn <?php echo $periode == 'hari_ini' ? 'active' : ''; ?>">Hari Ini</a>
                        <a href="?periode=minggu_ini" class="period-btn <?php echo $periode == 'minggu_ini' ? 'active' : ''; ?>">Minggu Ini</a>
                        <a href="?periode=bulan_ini" class="period-btn <?php echo $periode == 'bulan_ini' ? 'active' : ''; ?>">Bulan Ini</a>
                        <a href="?periode=tahun_ini" class="period-btn <?php echo $periode == 'tahun_ini' ? 'active' : ''; ?>">Tahun Ini</a>
                        <a href="?periode=semua" class="period-btn <?php echo $periode == 'semua' ? 'active' : ''; ?>">Semua</a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Pendapatan</div>
                            <div class="stat-value">Rp <?php echo number_format($stats_result['total_pendapatan'] ?? 0, 0, ',', '.'); ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total Pesanan</div>
                            <div class="stat-value"><?php echo $stats_result['total_pesanan'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Rata-rata Pesanan</div>
                            <div class="stat-value">Rp <?php echo number_format($stats_result['rata_rata_pesanan'] ?? 0, 0, ',', '.'); ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Pesanan Selesai</div>
                            <div class="stat-value"><?php echo $stats_result['pesanan_selesai'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-chart-area"></i>
                            Grafik Pendapatan & Pesanan
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-chart-pie"></i>
                            Status Pesanan
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="table-container" style="margin-bottom: 2rem;">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-fire"></i>
                        10 Produk Terlaris
                    </h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Produk</th>
                            <th>Total Terjual</th>
                            <th>Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        if (mysqli_num_rows($top_produk_result) > 0) {
                            while ($produk = mysqli_fetch_assoc($top_produk_result)) {
                                $rank_class = 'rank-other';
                                if ($rank == 1) $rank_class = 'rank-1';
                                elseif ($rank == 2) $rank_class = 'rank-2';
                                elseif ($rank == 3) $rank_class = 'rank-3';
                        ?>
                        <tr>
                            <td>
                                <div class="rank-badge <?php echo $rank_class; ?>"><?php echo $rank; ?></div>
                            </td>
                            <td>
                                <div class="product-info">
                                    <?php if (!empty($produk['gambar_produk'])) { ?>
                                        <img src="uploads/<?php echo htmlspecialchars($produk['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="product-image">
                                    <?php } else { ?>
                                        <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-light);">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php } ?>
                                    <div class="product-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                                </div>
                            </td>
                            <td><strong><?php echo $produk['total_terjual']; ?></strong> unit</td>
                            <td><strong style="color: var(--primary);">Rp <?php echo number_format($produk['total_pendapatan'], 0, ',', '.'); ?></strong></td>
                        </tr>
                        <?php 
                                $rank++;
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 3rem;">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-light); display: block; margin-bottom: 1rem;"></i>
                                Belum ada data penjualan
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Sellers Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-trophy"></i>
                        Top 5 Penjual Terbaik
                    </h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Nama Toko</th>
                            <th>Total Pesanan</th>
                            <th>Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        if (mysqli_num_rows($top_penjual_result) > 0) {
                            while ($penjual = mysqli_fetch_assoc($top_penjual_result)) {
                                $rank_class = 'rank-other';
                                if ($rank == 1) $rank_class = 'rank-1';
                                elseif ($rank == 2) $rank_class = 'rank-2';
                                elseif ($rank == 3) $rank_class = 'rank-3';
                        ?>
                        <tr>
                            <td>
                                <div class="rank-badge <?php echo $rank_class; ?>"><?php echo $rank; ?></div>
                            </td>
                            <td>
                                <div class="product-info">
                                    <?php if (!empty($penjual['gambar_toko'])) { ?>
                                        <img src="<?php echo htmlspecialchars($penjual['gambar_toko']); ?>" alt="<?php echo htmlspecialchars($penjual['nama_grosir']); ?>" class="product-image">
                                    <?php } else { ?>
                                        <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; font-weight: 700;">
                                            <?php echo strtoupper(substr($penjual['nama_grosir'], 0, 1)); ?>
                                        </div>
                                    <?php } ?>
                                    <div class="product-name"><?php echo htmlspecialchars($penjual['nama_grosir']); ?></div>
                                </div>
                            </td>
                            <td><strong><?php echo $penjual['total_pesanan']; ?></strong> pesanan</td>
                            <td><strong style="color: var(--primary);">Rp <?php echo number_format($penjual['total_pendapatan'], 0, ',', '.'); ?></strong></td>
                        </tr>
                        <?php 
                                $rank++;
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 3rem;">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-light); display: block; margin-bottom: 1rem;"></i>
                                Belum ada data penjualan
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        // Sales Chart
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?php echo json_encode($chart_pendapatan); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderColor: '#2563eb',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Jumlah Pesanan',
                    data: <?php echo json_encode($chart_pesanan); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Status Chart
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'],
                datasets: [{
                    data: [
                        <?php echo $status_data['pending'] ?? 0; ?>,
                        <?php echo $status_data['diproses'] ?? 0; ?>,
                        <?php echo $status_data['dikirim'] ?? 0; ?>,
                        <?php echo $status_data['selesai'] ?? 0; ?>,
                        <?php echo $status_data['dibatalkan'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#f59e0b',
                        '#3b82f6',
                        '#8b5cf6',
                        '#10b981',
                        '#ef4444'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Export Functions
        function exportToPDF() {
            alert('Fitur export PDF akan segera tersedia!');
        }

        function exportToExcel() {
            alert('Fitur export Excel akan segera tersedia!');
        }

        // Close sidebar on mobile
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
    <!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>