<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Update notifikasi menjadi sudah dibaca
$update_notifikasi_query = "UPDATE pesanan SET is_notified = 1 WHERE user_id_penjual = ? AND is_notified = 0";
$update_notifikasi_stmt = mysqli_prepare($koneksi, $update_notifikasi_query);
mysqli_stmt_bind_param($update_notifikasi_stmt, "i", $user_id);
mysqli_stmt_execute($update_notifikasi_stmt);

// Ambil filter status
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Query pesanan dengan filter
if ($filter_status == 'all') {
    $query_pesanan = "SELECT p.*, u.nama_lengkap, u.email FROM pesanan p 
                      JOIN users u ON p.user_id_pembeli = u.user_id 
                      WHERE p.user_id_penjual = ? 
                      ORDER BY p.tanggal_pesanan DESC";
    $stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
    mysqli_stmt_bind_param($stmt_pesanan, "i", $user_id);
} else {
    $query_pesanan = "SELECT p.*, u.nama_lengkap, u.email FROM pesanan p 
                      JOIN users u ON p.user_id_pembeli = u.user_id 
                      WHERE p.user_id_penjual = ? AND p.status_pesanan = ? 
                      ORDER BY p.tanggal_pesanan DESC";
    $stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
    mysqli_stmt_bind_param($stmt_pesanan, "is", $user_id, $filter_status);
}

mysqli_stmt_execute($stmt_pesanan);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan);

// Hitung statistik
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status_pesanan = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status_pesanan = 'diproses' THEN 1 ELSE 0 END) as diproses,
    SUM(CASE WHEN status_pesanan = 'selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN status_pesanan = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
    FROM pesanan WHERE user_id_penjual = ?";
$stats_stmt = mysqli_prepare($koneksi, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - InGrosir</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
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
            padding: 2rem;
        }
        
        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-title p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid;
            text-decoration: none;
            display: block;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.all { border-left-color: var(--info); }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.process { border-left-color: var(--primary); }
        .stat-card.done { border-left-color: var(--success); }
        .stat-card.cancelled { border-left-color: var(--danger); }
        
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
        }
        
        .stat-card.all .stat-value { color: var(--info); }
        .stat-card.pending .stat-value { color: var(--warning); }
        .stat-card.process .stat-value { color: var(--primary); }
        .stat-card.done .stat-value { color: var(--success); }
        .stat-card.cancelled .stat-value { color: var(--danger); }
        
        /* Filter Tabs */
        .filter-tabs {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid #e5e7eb;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #6b7280;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-tab.active,
        .filter-tab:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Orders Table */
        .orders-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-responsive {
            border-radius: 0 0 16px 16px;
        }
        
        .orders-table {
            width: 100%;
            margin-bottom: 0;
        }
        
        .orders-table thead {
            background: #f8f9fa;
        }
        
        .orders-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e9ecef;
        }
        
        .orders-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        .orders-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .orders-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary);
        }
        
        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .status-diproses {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }
        
        .status-dikirim {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }
        
        .status-selesai {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-dibatalkan {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Empty State */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #6b7280;
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .empty-state p {
            color: #6b7280;
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
            
            .page-header {
                padding: 1.5rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .orders-table {
                font-size: 0.75rem;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 0.75rem 0.5rem;
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
            <a href="pesanan.php" class="menu-item active">
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
            <a href="laporan_penjualan.php" class="menu-item">
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
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-shopping-cart me-2"></i>Kelola Pesanan</h1>
                <p>Manajemen pesanan masuk dari pembeli</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                <a href="pesanan.php?status=all" class="stat-card all">
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                <a href="pesanan.php?status=pending" class="stat-card pending">
                    <div class="stat-label">Menunggu Konfirmasi</div>
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                <a href="pesanan.php?status=diproses" class="stat-card process">
                    <div class="stat-label">Sedang Diproses</div>
                    <div class="stat-value"><?php echo $stats['diproses']; ?></div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                <a href="pesanan.php?status=selesai" class="stat-card done">
                    <div class="stat-label">Pesanan Selesai</div>
                    <div class="stat-value"><?php echo $stats['selesai']; ?></div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl">
                <a href="pesanan.php?status=dibatalkan" class="stat-card cancelled">
                    <div class="stat-label">Pesanan Dibatalkan</div>
                    <div class="stat-value"><?php echo $stats['dibatalkan']; ?></div>
                </a>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <div class="d-flex flex-wrap gap-2">
                <a href="pesanan.php?status=all" class="filter-tab <?php echo $filter_status == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua Pesanan
                </a>
                <a href="pesanan.php?status=pending" class="filter-tab <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Menunggu
                </a>
                <a href="pesanan.php?status=diproses" class="filter-tab <?php echo $filter_status == 'diproses' ? 'active' : ''; ?>">
                    <i class="fas fa-sync"></i> Diproses
                </a>
                <a href="pesanan.php?status=selesai" class="filter-tab <?php echo $filter_status == 'selesai' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Selesai
                </a>
                <a href="pesanan.php?status=dibatalkan" class="filter-tab <?php echo $filter_status == 'dibatalkan' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Dibatalkan
                </a>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-container">
            <?php if (mysqli_num_rows($result_pesanan) > 0) { ?>
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
                            <?php while ($pesanan = mysqli_fetch_assoc($result_pesanan)) { 
                                $status_class = 'status-' . strtolower($pesanan['status_pesanan']);
                            ?>
                            <tr>
                                <td>
                                    <span class="order-id">#<?php echo $pesanan['pesanan_id']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pesanan['nama_lengkap']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pesanan['email']); ?></small>
                                </td>
                                <td><?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?></td>
                                <td><strong class="text-primary">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></strong></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($pesanan['status_pesanan']); ?></span></td>
                                <td>
                                    <a href="detail_pesanan.php?id=<?php echo $pesanan['pesanan_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Tidak Ada Pesanan</h3>
                    <p>Belum ada pesanan <?php echo $filter_status != 'all' ? 'dengan status ' . $filter_status : ''; ?> saat ini</p>
                </div>
            <?php } ?>
        </div>
    </main>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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