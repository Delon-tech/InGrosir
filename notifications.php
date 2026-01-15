<?php
session_start();
require_once 'config/koneksi.php';
require_once 'includes/notification_helper.php';

$koneksi = connectDB();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter berdasarkan status
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "user_id = $user_id";

if ($filter === 'unread') {
    $where_clause .= " AND sudah_dibaca = 0";
} elseif ($filter === 'read') {
    $where_clause .= " AND sudah_dibaca = 1";
}

// Query total notifikasi
$count_query = "SELECT COUNT(*) as total FROM notifications WHERE $where_clause";
$count_result = mysqli_query($koneksi, $count_query);
$total_notif = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_notif / $limit);

// Query notifikasi dengan pagination
$query = "SELECT * FROM notifications 
          WHERE $where_clause 
          ORDER BY dibuat_pada DESC 
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($koneksi, $query);
$notifications = [];

while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

// Hitung statistik
$count_unread = hitung_notifikasi_belum_dibaca($user_id);
$count_read_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = $user_id AND sudah_dibaca = 1";
$count_read = mysqli_fetch_assoc(mysqli_query($koneksi, $count_read_query))['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Notifikasi - InGrosir</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            --radius: 8px;
            --radius-lg: 12px;
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
           HEADER WITH BOOTSTRAP NAVBAR
           ============================================ */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar {
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-size: 1.75rem;
            font-weight: 800;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-brand i {
            font-size: 2rem;
        }
        
        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.5);
            padding: 0.5rem 0.75rem;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
        }
        
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: var(--radius);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            color: white !important;
        }
        
        /* Mobile Menu Styling */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                border-radius: var(--radius-lg);
                padding: 1rem;
                margin-top: 1rem;
            }
            
            .navbar-nav {
                gap: 0.5rem;
            }
            
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem !important;
            }
        }
        
        /* ============================================
           PAGE CONTENT
           ============================================ */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--primary);
            height: 100%;
        }
        
        .stat-card.unread {
            border-left-color: var(--danger);
        }
        
        .stat-card.read {
            border-left-color: var(--secondary);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-card.unread .stat-value {
            color: var(--danger);
        }
        
        .stat-card.read .stat-value {
            color: var(--secondary);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin: 0;
        }
        
        /* Filter Tabs */
        .filter-tabs {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border);
            background: white;
            border-radius: var(--radius);
            cursor: pointer;
            transition: 0.3s;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-block;
            margin: 0.25rem;
        }
        
        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Notification List */
        .notification-list {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background: var(--bg-gray);
        }
        
        .notification-item.unread {
            background: #eff6ff;
        }
        
        .notification-item-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .notification-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .notification-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 700;
        }
        
        .badge-unread {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 5rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        /* Action Buttons */
        .action-buttons {
            margin-top: 2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .filter-tab {
                display: block;
                text-align: center;
                margin: 0.25rem 0;
            }
            
            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Bootstrap Navbar -->
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-shopping-bag"></i>
                    InGrosir
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-home"></i> Beranda
                            </a>
                        </li>
                        <?php if ($_SESSION['peran'] == 'penjual') { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                        <?php } else { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="cart.php">
                                    <i class="fas fa-shopping-cart"></i> Keranjang
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="riwayat_pesanan.php">
                                    <i class="fas fa-history"></i> Riwayat
                                </a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="notifications.php">
                                <i class="fas fa-bell"></i> Notifikasi
                                <?php if ($count_unread > 0) { ?>
                                    <span class="badge bg-danger"><?php echo $count_unread; ?></span>
                                <?php } ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profil.php">
                                <i class="fas fa-user"></i> Profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container my-4">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-bell"></i>
                Pusat Notifikasi
            </h1>
            <p>Kelola semua notifikasi Anda</p>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_notif; ?></div>
                    <p class="stat-label">Total Notifikasi</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card unread">
                    <div class="stat-value"><?php echo $count_unread; ?></div>
                    <p class="stat-label">Belum Dibaca</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card read">
                    <div class="stat-value"><?php echo $count_read; ?></div>
                    <p class="stat-label">Sudah Dibaca</p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="notifications.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Semua (<?php echo $total_notif; ?>)
            </a>
            <a href="notifications.php?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Belum Dibaca (<?php echo $count_unread; ?>)
            </a>
            <a href="notifications.php?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open"></i> Sudah Dibaca (<?php echo $count_read; ?>)
            </a>
        </div>

        <!-- Notification List -->
        <div class="notification-list">
            <?php if (empty($notifications)) { ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>Tidak Ada Notifikasi</h3>
                    <p>Belum ada notifikasi <?php echo $filter !== 'all' ? 'dengan filter ini' : 'untuk Anda'; ?>.</p>
                </div>
            <?php } else { ?>
                <?php foreach ($notifications as $notif) { ?>
                    <a href="<?php echo $notif['link'] ?? '#'; ?>" 
                       class="notification-item <?php echo $notif['sudah_dibaca'] ? '' : 'unread'; ?>"
                       onclick="markAsRead(event, <?php echo $notif['notification_id']; ?>)">
                        <div class="notification-item-header">
                            <div class="notification-icon">
                                <i class="fas <?php echo get_icon_class($notif['icon']); ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notif['judul']); ?>
                                    <?php if (!$notif['sudah_dibaca']) { ?>
                                        <span class="notification-badge badge-unread">BARU</span>
                                    <?php } ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notif['pesan']); ?>
                                </div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo format_waktu_relatif($notif['dibuat_pada']); ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            <?php } ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1) { ?>
            <nav aria-label="Notification pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1) { ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i> Sebelumnya
                            </a>
                        </li>
                    <?php } ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php } ?>
                    
                    <?php if ($page < $total_pages) { ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                                Selanjutnya <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </nav>
        <?php } ?>

        <!-- Action Buttons -->
        <?php if ($count_unread > 0) { ?>
            <div class="action-buttons d-flex gap-3 flex-wrap">
                <button onclick="markAllAsRead()" class="btn btn-primary">
                    <i class="fas fa-check-double"></i>
                    Tandai Semua Dibaca
                </button>
            </div>
        <?php } ?>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Tandai notifikasi sebagai dibaca
        function markAsRead(event, notificationId) {
            fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .catch(error => console.error('Error:', error));
        }

        // Tandai semua sebagai dibaca
        function markAllAsRead() {
            if (!confirm('Tandai semua notifikasi sebagai sudah dibaca?')) {
                return;
            }
            
            fetch('api/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Auto close navbar on mobile after click
        document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                const navbar = document.querySelector('.navbar-collapse');
                if (navbar.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(navbar);
                    bsCollapse.hide();
                }
            });
        });
    </script>
</body>
</html>