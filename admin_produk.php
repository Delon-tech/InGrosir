<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Pagination
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search & Filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$filter_stok = isset($_GET['stok']) ? mysqli_real_escape_string($koneksi, $_GET['stok']) : '';

// Build query
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (p.nama_produk LIKE '%$search%' OR u.nama_grosir LIKE '%$search%')";
}
if (!empty($filter_kategori)) {
    $where .= " AND p.kategori_id = '$filter_kategori'";
}
if ($filter_stok == 'rendah') {
    $where .= " AND p.stok < 10";
} elseif ($filter_stok == 'habis') {
    $where .= " AND p.stok = 0";
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM produk p JOIN users u ON p.user_id = u.user_id $where";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Get products
$produk_query = "SELECT p.*, u.nama_grosir, k.nama_kategori FROM produk p JOIN users u ON p.user_id = u.user_id LEFT JOIN kategori_produk k ON p.kategori_id = k.kategori_id $where ORDER BY p.produk_id DESC LIMIT $start, $limit";
$produk_result = mysqli_query($koneksi, $produk_query);

// Get categories for filter
$kategori_query = "SELECT * FROM kategori_produk ORDER BY nama_kategori ASC";
$kategori_result = mysqli_query($koneksi, $kategori_query);

// Statistics
$stats_total = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk"))['total'];
$stats_stok_rendah = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk WHERE stok < 10"))['total'];
$stats_habis = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk WHERE stok = 0"))['total'];
$stats_aktif = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk WHERE stok > 0"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - InGrosir Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --success: #10b981;
            
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
        
        /* Sidebar - Reuse from previous files */
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
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border-left: 4px solid;
        }
        
        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        
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
        
        /* Filters */
        .filters-bar {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select {
            padding: 0.625rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        /* Product Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--bg-gray);
        }
        
        .product-content {
            padding: 1.25rem;
        }
        
        .product-header {
            margin-bottom: 1rem;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .product-seller {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .product-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .meta-value {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary);
        }
        
        .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .stock-badge.high {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stock-badge.low {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stock-badge.out {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Pagination */
        .pagination-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.875rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            transition: var(--transition);
        }
        
        .pagination a:hover {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .pagination .active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--text-secondary);
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
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .products-grid {
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
                        <a href="admin_produk.php" class="menu-item active">
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>
                        <i class="fas fa-box"></i>
                        Kelola Produk
                    </h1>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i>
                            Cetak
                        </button>
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card primary">
                    <div class="stat-label">Total Produk</div>
                    <div class="stat-value"><?php echo $stats_total; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Produk Aktif</div>
                    <div class="stat-value"><?php echo $stats_aktif; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Stok Rendah</div>
                    <div class="stat-value"><?php echo $stats_stok_rendah; ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">Stok Habis</div>
                    <div class="stat-value"><?php echo $stats_habis; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-search"></i>
                                Cari Produk
                            </label>
                            <input type="text" name="search" placeholder="Nama produk atau toko..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-tag"></i>
                                Kategori
                            </label>
                            <select name="kategori">
                                <option value="">Semua Kategori</option>
                                <?php 
                                mysqli_data_seek($kategori_result, 0);
                                while ($kat = mysqli_fetch_assoc($kategori_result)) { 
                                ?>
                                <option value="<?php echo $kat['kategori_id']; ?>" <?php echo $filter_kategori == $kat['kategori_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-filter"></i>
                                Filter Stok
                            </label>
                            <select name="stok">
                                <option value="">Semua Stok</option>
                                <option value="rendah" <?php echo $filter_stok == 'rendah' ? 'selected' : ''; ?>>Stok Rendah</option>
                                <option value="habis" <?php echo $filter_stok == 'habis' ? 'selected' : ''; ?>>Stok Habis</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                            <i class="fas fa-search"></i>
                            Cari
                        </button>
                        
                        <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_stok)) { ?>
                        <a href="admin_produk.php" class="btn btn-outline" style="margin-top: 1.5rem;">
                            <i class="fas fa-times"></i>
                            Reset
                        </a>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <?php if (mysqli_num_rows($produk_result) > 0) { ?>
            <div class="products-grid">
                <?php while ($produk = mysqli_fetch_assoc($produk_result)) { 
                    $stock_class = 'high';
                    $stock_label = 'Tersedia';
                    if ($produk['stok'] == 0) {
                        $stock_class = 'out';
                        $stock_label = 'Habis';
                    } elseif ($produk['stok'] < 10) {
                        $stock_class = 'low';
                        $stock_label = 'Stok Rendah';
                    }
                ?>
                <div class="product-card">
                    <?php if (!empty($produk['gambar_produk'])) { ?>
                        <img src="uploads/<?php echo htmlspecialchars($produk['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="product-image">
                    <?php } else { ?>
                        <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-light);">
                            <i class="fas fa-image" style="font-size: 3rem;"></i>
                        </div>
                    <?php } ?>
                    
                    <div class="product-content">
                        <div class="product-header">
                            <h3 class="product-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></h3>
                            <div class="product-seller">
                                <i class="fas fa-store"></i>
                                <?php echo htmlspecialchars($produk['nama_grosir']); ?>
                            </div>
                        </div>
                        
                        <div class="product-meta">
                            <div class="meta-item">
                                <span class="meta-label">Harga</span>
                                <span class="meta-value">Rp <?php echo number_format($produk['harga_grosir'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Stok</span>
                                <span class="meta-value"><?php echo $produk['stok']; ?> unit</span>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <span class="stock-badge <?php echo $stock_class; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo $stock_label; ?>
                            </span>
                            <?php if (!empty($produk['nama_kategori'])) { ?>
                            <span class="stock-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--info); margin-left: 0.5rem;">
                                <?php echo htmlspecialchars($produk['nama_kategori']); ?>
                            </span>
                            <?php } ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="admin_edit_produk.php?id=<?php echo $produk['produk_id']; ?>" class="btn btn-primary btn-sm" style="flex: 1;">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <button onclick="deleteProduct(<?php echo $produk['produk_id']; ?>, '<?php echo htmlspecialchars($produk['nama_produk']); ?>')" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Tidak ada produk ditemukan</h3>
                <p>Belum ada produk yang terdaftar atau coba ubah filter pencarian</p>
            </div>
            <?php } ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Menampilkan <?php echo $start + 1; ?> - <?php echo min($start + $limit, $total_records); ?> dari <?php echo $total_records; ?> produk
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($filter_kategori); ?>&stok=<?php echo urlencode($filter_stok); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php } else { ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                    <?php } ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&kategori=' . urlencode($filter_kategori) . '&stok=' . urlencode($filter_stok) . '">' . $i . '</a>';
                        }
                    }
                    ?>
                    
                    <?php if ($page < $total_pages) { ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($filter_kategori); ?>&stok=<?php echo urlencode($filter_stok); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php } else { ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script>
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

        // Delete Product with Confirmation
        function deleteProduct(productId, productName) {
            if (confirm(`Apakah Anda yakin ingin menghapus produk "${productName}"?\n\nTindakan ini tidak dapat dibatalkan!`)) {
                window.location.href = `admin_delete_produk.php?id=${productId}`;
            }
        }

        // Export to Excel
        function exportToExcel() {
            alert('Fitur export akan segera tersedia!');
            // Implementasi export dapat ditambahkan di sini
        }
    </script>
    <!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>