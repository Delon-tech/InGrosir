<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search & Filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$filter_peran = isset($_GET['peran']) ? mysqli_real_escape_string($koneksi, $_GET['peran']) : '';

// Build query
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (nama_lengkap LIKE '%$search%' OR email LIKE '%$search%' OR nama_grosir LIKE '%$search%')";
}
if (!empty($filter_peran)) {
    $where .= " AND peran = '$filter_peran'";
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM users $where";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Get users
$users_query = "SELECT * FROM users $where ORDER BY tanggal_registrasi DESC LIMIT $start, $limit";
$users_result = mysqli_query($koneksi, $users_query);

// Statistics
$stats_pembeli = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE peran = 'pembeli'"))['total'];
$stats_penjual = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE peran = 'penjual'"))['total'];
$stats_admin = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE is_admin = 1"))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - InGrosir Admin</title>
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
        
        /* Reuse sidebar styles from dashboard */
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
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
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
            grid-template-columns: 1fr auto auto;
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
        
        /* Table */
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-wrapper {
            overflow-x: auto;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
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
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 0.125rem;
        }
        
        .user-email {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge.pembeli {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .badge.penjual {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge.admin {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        /* Pagination */
        .pagination-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-top: 1.5rem;
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
            
            .table-wrapper {
                font-size: 0.8125rem;
            }
            
            .action-buttons {
                flex-direction: column;
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
                    <a href="admin_users.php" class="menu-item active">
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
                        <i class="fas fa-users"></i>
                        Kelola Pengguna
                    </h1>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i>
                            Cetak
                        </button>
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </button>
                        <a href="admin_add_user.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Tambah Pengguna
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card primary">
                    <div class="stat-label">Total Pengguna</div>
                    <div class="stat-value"><?php echo $total_records; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Pembeli</div>
                    <div class="stat-value"><?php echo $stats_pembeli; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Penjual</div>
                    <div class="stat-value"><?php echo $stats_penjual; ?></div>
                </div>
                <div class="stat-card primary">
                    <div class="stat-label">Admin</div>
                    <div class="stat-value"><?php echo $stats_admin; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-search"></i>
                                Cari Pengguna
                            </label>
                            <input type="text" name="search" placeholder="Nama, email, atau toko..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-filter"></i>
                                Filter Peran
                            </label>
                            <select name="peran">
                                <option value="">Semua Peran</option>
                                <option value="pembeli" <?php echo $filter_peran == 'pembeli' ? 'selected' : ''; ?>>Pembeli</option>
                                <option value="penjual" <?php echo $filter_peran == 'penjual' ? 'selected' : ''; ?>>Penjual</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                            <i class="fas fa-search"></i>
                            Cari
                        </button>
                        
                        <?php if (!empty($search) || !empty($filter_peran)) { ?>
                        <a href="admin_users.php" class="btn btn-outline" style="margin-top: 1.5rem;">
                            <i class="fas fa-times"></i>
                            Reset
                        </a>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-container">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                </th>
                                <th>Pengguna</th>
                                <th>Peran</th>
                                <th>Nama Toko</th>
                                <th>Telepon</th>
                                <th>Terdaftar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($users_result) > 0) {
                                while ($user = mysqli_fetch_assoc($users_result)) {
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="user-checkbox" value="<?php echo $user['user_id']; ?>">
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['peran']; ?>">
                                        <?php echo ucfirst($user['peran']); ?>
                                    </span>
                                    <?php if ($user['is_admin'] == 1) { ?>
                                        <span class="badge admin">Admin</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo $user['nama_grosir'] ? htmlspecialchars($user['nama_grosir']) : '-'; ?></td>
                                <td><?php echo $user['nomor_telepon'] ? htmlspecialchars($user['nomor_telepon']) : '-'; ?></td>
                                <td><?php echo date('d M Y', strtotime($user['tanggal_registrasi'])); ?></td>
                                <td>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                        <i class="fas fa-check-circle"></i> Aktif
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin_edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        <button onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['nama_lengkap']); ?>')" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <h3>Tidak ada data pengguna</h3>
                                        <p>Belum ada pengguna yang terdaftar atau coba ubah filter pencarian</p>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Menampilkan <?php echo $start + 1; ?> - <?php echo min($start + $limit, $total_records); ?> dari <?php echo $total_records; ?> data
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&peran=<?php echo urlencode($filter_peran); ?>">
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
                            echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&peran=' . urlencode($filter_peran) . '">' . $i . '</a>';
                        }
                    }
                    ?>
                    
                    <?php if ($page < $total_pages) { ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&peran=<?php echo urlencode($filter_peran); ?>">
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

        // Select All Checkboxes
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        // Delete User with Confirmation
        function deleteUser(userId, userName) {
            if (confirm(`Apakah Anda yakin ingin menghapus pengguna "${userName}"?\n\nTindakan ini tidak dapat dibatalkan!`)) {
                window.location.href = `admin_delete_user.php?id=${userId}`;
            }
        }

        // Export to Excel
        function exportToExcel() {
            const table = document.querySelector('table');
            const rows = [];
            
            // Header
            const headers = [];
            table.querySelectorAll('thead th').forEach((th, index) => {
                if (index > 0 && index < table.querySelectorAll('thead th').length - 1) {
                    headers.push(th.textContent.trim());
                }
            });
            rows.push(headers.join('\t'));
            
            // Body
            table.querySelectorAll('tbody tr').forEach(tr => {
                const cols = [];
                tr.querySelectorAll('td').forEach((td, index) => {
                    if (index > 0 && index < tr.querySelectorAll('td').length - 1) {
                        cols.push(td.textContent.trim().replace(/\s+/g, ' '));
                    }
                });
                if (cols.length > 0) {
                    rows.push(cols.join('\t'));
                }
            });
            
            // Download
            const csvContent = rows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'data_pengguna_' + new Date().toISOString().split('T')[0] + '.csv';
            link.click();
        }

        // Print Function
        window.addEventListener('beforeprint', function() {
            document.querySelector('.sidebar').style.display = 'none';
            document.querySelector('.mobile-toggle').style.display = 'none';
        });

        window.addEventListener('afterprint', function() {
            document.querySelector('.sidebar').style.display = 'block';
            if (window.innerWidth <= 768) {
                document.querySelector('.mobile-toggle').style.display = 'flex';
            }
        });
    </script>
    <!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>