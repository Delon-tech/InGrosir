<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

// Ambil total pendapatan
$query_pendapatan = "SELECT SUM(total_harga) AS total FROM pesanan WHERE user_id_penjual = ? AND status_pesanan = 'selesai'";
$stmt_pendapatan = mysqli_prepare($koneksi, $query_pendapatan);
mysqli_stmt_bind_param($stmt_pendapatan, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_pendapatan);
$result_pendapatan = mysqli_stmt_get_result($stmt_pendapatan);
$total_pendapatan = mysqli_fetch_assoc($result_pendapatan)['total'] ?? 0;

// Ambil jumlah pesanan baru
$notifikasi_query = "SELECT COUNT(*) AS total_notifikasi FROM pesanan WHERE user_id_penjual = ? AND is_notified = 0";
$notifikasi_stmt = mysqli_prepare($koneksi, $notifikasi_query);
mysqli_stmt_bind_param($notifikasi_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($notifikasi_stmt);
$notifikasi_result = mysqli_stmt_get_result($notifikasi_stmt);
$total_notifikasi = mysqli_fetch_assoc($notifikasi_result)['total_notifikasi'] ?? 0;

// Hitung total produk
$produk_query = "SELECT COUNT(*) AS total FROM produk WHERE user_id = ?";
$produk_stmt = mysqli_prepare($koneksi, $produk_query);
mysqli_stmt_bind_param($produk_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($produk_stmt);
$total_produk = mysqli_fetch_assoc(mysqli_stmt_get_result($produk_stmt))['total'] ?? 0;

// Hitung total pesanan
$pesanan_query = "SELECT COUNT(*) AS total FROM pesanan WHERE user_id_penjual = ?";
$pesanan_stmt = mysqli_prepare($koneksi, $pesanan_query);
mysqli_stmt_bind_param($pesanan_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($pesanan_stmt);
$total_pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($pesanan_stmt))['total'] ?? 0;

// Ambil data penjualan bulanan untuk grafik
$sales_query = "SELECT DATE_FORMAT(tanggal_pesanan, '%Y-%m') AS bulan, SUM(total_harga) AS total_penjualan FROM pesanan WHERE user_id_penjual = ? AND status_pesanan = 'selesai' GROUP BY bulan ORDER BY bulan ASC LIMIT 12";
$sales_stmt = mysqli_prepare($koneksi, $sales_query);
mysqli_stmt_bind_param($sales_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($sales_stmt);
$sales_result = mysqli_stmt_get_result($sales_stmt);

$labels = [];
$data_penjualan = [];
while ($row = mysqli_fetch_assoc($sales_result)) {
    $labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
    $data_penjualan[] = (float) $row['total_penjualan'];
}

// Pesanan terbaru
$recent_query = "SELECT p.*, u.nama_lengkap FROM pesanan p JOIN users u ON p.user_id_pembeli = u.user_id WHERE p.user_id_penjual = ? ORDER BY p.tanggal_pesanan DESC LIMIT 5";
$recent_stmt = mysqli_prepare($koneksi, $recent_query);
mysqli_stmt_bind_param($recent_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($recent_stmt);
$recent_orders = mysqli_stmt_get_result($recent_stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - InGrosir</title>
    
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
        
        .menu-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 2rem;
        }
        
        /* Top Bar dengan Bootstrap */
        .top-bar {
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
        
        /* Orders Table */
        .orders-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-responsive {
            border-radius: 0 0 16px 16px;
        }
        
        /* Status Badges with Bootstrap */
        .badge-status {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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
            
            .stat-value {
                font-size: 1.5rem;
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
            <a href="dashboard.php" class="menu-item active">
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
                <?php if ($total_notifikasi > 0) { ?>
                    <span class="menu-badge"><?php echo $total_notifikasi; ?></span>
                <?php } ?>
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
        <!-- Top Bar dengan Bootstrap -->
        <div class="top-bar p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <h1 class="h3 mb-2 fw-bold">Selamat Datang Kembali! 👋</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo date('l, d F Y'); ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 flex-wrap justify-content-md-end">
                        <a href="produk_list.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Tambah Produk
                        </a>
                        <a href="pesanan.php" class="btn btn-outline-primary">
                            <i class="fas fa-clipboard-list me-2"></i>Lihat Pesanan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid dengan Bootstrap -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-label">Total Pendapatan</div>
                    <div class="stat-value">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up me-1"></i>12% dari bulan lalu
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-label">Total Produk</div>
                    <div class="stat-value"><?php echo $total_produk; ?></div>
                    <a href="produk_list.php" class="text-success text-decoration-none fw-semibold small">
                        Kelola Produk <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-value"><?php echo $total_pesanan; ?></div>
                    <div class="text-muted small">
                        <i class="fas fa-bell me-1"></i><?php echo $total_notifikasi; ?> pesanan baru
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-label">Rating Toko</div>
                    <div class="stat-value">
                        4.8 <i class="fas fa-star text-warning ms-1" style="font-size: 1.5rem;"></i>
                    </div>
                    <div class="text-muted small">Dari 120 ulasan</div>
                </div>
            </div>
        </div>

        <!-- Chart Section dengan Bootstrap Grid -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-8">
                <div class="chart-card">
                    <h5 class="fw-bold mb-1">Grafik Penjualan Bulanan</h5>
                    <p class="text-muted small mb-4">Statistik penjualan 12 bulan terakhir</p>
                    <canvas id="salesChart" height="80"></canvas>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="chart-card">
                    <h5 class="fw-bold mb-1">Ringkasan Cepat</h5>
                    <p class="text-muted small mb-4">Statistik hari ini</p>
                    
                    <div class="d-flex flex-column gap-3">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-2">Pesanan Hari Ini</div>
                            <div class="h4 fw-bold mb-0">5</div>
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-2">Pengunjung Toko</div>
                            <div class="h4 fw-bold mb-0">42</div>
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-2">Produk Terjual</div>
                            <div class="h4 fw-bold mb-0">18</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders dengan Bootstrap Table -->
        <div class="orders-card">
            <div class="p-4 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Pesanan Terbaru</h5>
                    <a href="pesanan.php" class="btn btn-sm btn-outline-primary">
                        Lihat Semua
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3">ID Pesanan</th>
                            <th class="py-3">Pembeli</th>
                            <th class="py-3">Tanggal</th>
                            <th class="py-3">Total</th>
                            <th class="py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($recent_orders) > 0) {
                            while ($order = mysqli_fetch_assoc($recent_orders)) {
                                $badge_class = '';
                                $status_text = '';
                                switch($order['status_pesanan']) {
                                    case 'pending':
                                        $badge_class = 'bg-warning text-dark';
                                        $status_text = 'Menunggu';
                                        break;
                                    case 'diproses':
                                        $badge_class = 'bg-info text-white';
                                        $status_text = 'Diproses';
                                        break;
                                    case 'dikirim':
                                        $badge_class = 'bg-primary';
                                        $status_text = 'Dikirim';
                                        break;
                                    case 'selesai':
                                        $badge_class = 'bg-success';
                                        $status_text = 'Selesai';
                                        break;
                                    case 'dibatalkan':
                                        $badge_class = 'bg-danger';
                                        $status_text = 'Dibatalkan';
                                        break;
                                }
                        ?>
                        <tr>
                            <td class="px-4 py-3 fw-bold text-primary">#<?php echo $order['pesanan_id']; ?></td>
                            <td class="py-3"><?php echo htmlspecialchars($order['nama_lengkap']); ?></td>
                            <td class="py-3"><?php echo date('d M Y', strtotime($order['tanggal_pesanan'])); ?></td>
                            <td class="py-3 fw-bold text-primary">Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></td>
                            <td class="py-3">
                                <span class="badge badge-status <?php echo $badge_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted mb-0">Belum ada pesanan</p>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
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
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Total Penjualan (Rp)',
                    data: <?php echo json_encode($data_penjualan); ?>,
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
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
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
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Toggle sidebar for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Close sidebar when clicking outside
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