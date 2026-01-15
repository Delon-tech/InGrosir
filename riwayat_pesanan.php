<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'pembeli') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Load notification helper
require_once 'includes/notification_helper.php';
$notif_count = hitung_notifikasi_belum_dibaca($user_id);
$notif_list = ambil_notifikasi_terbaru($user_id, 5);

// Cart count
$cart_count = 0;
$cart_query = "SELECT COUNT(*) as total FROM keranjang WHERE user_id = ?";
$cart_stmt = mysqli_prepare($koneksi, $cart_query);
mysqli_stmt_bind_param($cart_stmt, "i", $user_id);
mysqli_stmt_execute($cart_stmt);
$cart_count = mysqli_fetch_assoc(mysqli_stmt_get_result($cart_stmt))['total'] ?? 0;

// Filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Query pesanan
$query_pesanan = "SELECT p.*, u.nama_grosir FROM pesanan p 
                  JOIN users u ON p.user_id_penjual = u.user_id 
                  WHERE p.user_id_pembeli = ?";
if ($status_filter != 'all') {
    $query_pesanan .= " AND p.status_pesanan = ?";
}
$query_pesanan .= " ORDER BY p.tanggal_pesanan DESC";

$stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
if ($status_filter != 'all') {
    mysqli_stmt_bind_param($stmt_pesanan, "is", $user_id, $status_filter);
} else {
    mysqli_stmt_bind_param($stmt_pesanan, "i", $user_id);
}
mysqli_stmt_execute($stmt_pesanan);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan);

// Statistik
$stats_query = "SELECT COUNT(*) as total,
    SUM(CASE WHEN status_pesanan = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status_pesanan = 'diproses' THEN 1 ELSE 0 END) as diproses,
    SUM(CASE WHEN status_pesanan = 'dikirim' THEN 1 ELSE 0 END) as dikirim,
    SUM(CASE WHEN status_pesanan = 'selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN status_pesanan = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
    FROM pesanan WHERE user_id_pembeli = ?";
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
    <title>Riwayat Pesanan - InGrosir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb; --primary-dark: #1e40af; --secondary: #10b981;
            --accent: #f59e0b; --danger: #ef4444; --warning: #f59e0b;
            --text-primary: #1f2937; --text-secondary: #6b7280; --text-light: #9ca3af;
            --bg-white: #ffffff; --bg-gray: #f3f4f6; --bg-light: #f9fafb;
            --border: #e5e7eb; --border-light: #f3f4f6;
            --shadow: 0 1px 3px 0 rgba(0,0,0,0.1); --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius: 8px; --radius-lg: 12px; --transition: 200ms ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-gray); color: var(--text-primary); }
        
        /* HEADER - SAMA SEPERTI INDEX.PHP */
        .header { background: var(--bg-white); box-shadow: var(--shadow); position: sticky; top: 0; z-index: 1000; }
        .header-top { background: linear-gradient(90deg, var(--primary), var(--primary-dark)); color: white; padding: 0.625rem 0; font-size: 0.8125rem; }
        .header-top-content { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header-top-links { display: flex; gap: 1.75rem; }
        .header-top-links a { color: rgba(255,255,255,0.9); transition: var(--transition); display: flex; align-items: center; gap: 0.4rem; font-weight: 500; }
        .header-top-links a:hover { color: white; text-decoration: none; }
        
        .header-main { max-width: 1280px; margin: 0 auto; padding: 1rem 1.5rem; display: grid; grid-template-columns: auto 1fr auto; gap: 2.5rem; align-items: center; }
        .logo { font-size: 1.75rem; font-weight: 900; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: flex; align-items: center; gap: 0.625rem; }
        .logo i { font-size: 2rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .header-search { max-width: 650px; }
        .search-box { display: flex; background: var(--bg-gray); border-radius: var(--radius-lg); border: 2px solid transparent; transition: var(--transition); }
        .search-box:focus-within { border-color: var(--primary); background: white; }
        .search-box input { flex: 1; padding: 1rem 1.25rem; border: none; background: transparent; }
        .search-box input:focus { outline: none; }
        .search-btn { padding: 0 1.75rem; background: var(--primary); color: white; border: none; cursor: pointer; font-weight: 600; }
        
        .header-actions { display: flex; gap: 0.5rem; align-items: center; }
        .header-action { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.625rem 1rem; border-radius: var(--radius); transition: var(--transition); position: relative; cursor: pointer; color: var(--text-secondary); }
        .header-action:hover { background: var(--bg-light); color: var(--primary); transform: translateY(-2px); }
        .header-action i { font-size: 1.5rem; }
        .header-action span { font-size: 0.75rem; font-weight: 600; }
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
        .cart-badge, .notification-badge { position: absolute; top: 0.375rem; right: 0.75rem; background: var(--danger); color: white; min-width: 20px; height: 20px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.6875rem; font-weight: 700; }
        
        .mobile-toggle { display: none; flex-direction: column; gap: 5px; background: none; border: none; cursor: pointer; padding: 0.5rem; }
        .mobile-toggle span { width: 24px; height: 2.5px; background: var(--text-primary); border-radius: 2px; transition: var(--transition); }
        
        .nav { background: white; border-top: 1px solid var(--border-light); padding: 0; }
        .nav-content { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; display: flex; gap: 0.5rem; }
        .nav-content a { color: var(--text-primary); font-weight: 600; padding: 1rem 1.5rem; border-radius: var(--radius); transition: var(--transition); white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; }
        .nav-content a:hover { color: var(--primary); background: var(--bg-light); text-decoration: none; }
        
        /* NOTIFICATION DROPDOWN */
        .notification-wrapper { position: relative; }
        .notification-dropdown { position: absolute; top: calc(100% + 0.75rem); right: 0; background: white; border-radius: var(--radius-lg); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); width: 380px; max-height: 500px; overflow: hidden; display: none; z-index: 2000; border: 1px solid var(--border-light); }
        .notification-dropdown.active { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notification-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: var(--bg-light); }
        .notification-header h4 { font-size: 1rem; font-weight: 700; margin: 0; }
        .mark-all-read { color: var(--primary); font-size: 0.8125rem; font-weight: 600; cursor: pointer; padding: 0.375rem 0.75rem; border-radius: var(--radius); }
        .notification-list { max-height: 400px; overflow-y: auto; }
        .notification-item { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-light); cursor: pointer; display: flex; gap: 1rem; }
        .notification-item:hover { background: var(--bg-light); }
        .notification-item.unread { background: rgba(37,99,235,0.05); }
        .notification-icon { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .notification-text { font-size: 0.8125rem; color: var(--text-secondary); }
        .notification-time { font-size: 0.75rem; color: var(--text-light); }
        .notification-badge-new { padding: 0.125rem 0.5rem; background: var(--danger); color: white; border-radius: 12px; font-size: 0.6875rem; font-weight: 700; margin-left: 0.5rem; }
        .notification-empty { padding: 3rem 1.5rem; text-align: center; color: var(--text-secondary); }
        .notification-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-light); text-align: center; }
        .notification-footer a { color: var(--primary); font-size: 0.875rem; font-weight: 600; }
        
        /* PAGE CONTENT */
        .page-header h1 { font-size: 2rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .page-header p { color: var(--text-secondary); }
        .stat-card { background: white; padding: 1.5rem; border-radius: var(--radius-lg); box-shadow: var(--shadow); border-left: 4px solid var(--primary); transition: var(--transition); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.diproses { border-left-color: #3b82f6; }
        .stat-card.dikirim { border-left-color: var(--accent); }
        .stat-card.selesai { border-left-color: var(--secondary); }
        .stat-card.dibatalkan { border-left-color: var(--danger); }
        .stat-label { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: 600; }
        .stat-value { font-size: 2rem; font-weight: 700; }
        
        .filter-bar { background: white; padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; box-shadow: var(--shadow); }
        .filter-label { font-weight: 600; margin-bottom: 1rem; display: block; }
        .filter-btn { padding: 0.5rem 1rem; border: 2px solid var(--border); background: white; color: var(--text-primary); border-radius: var(--radius); cursor: pointer; transition: var(--transition); font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; white-space: nowrap; }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .order-card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); overflow: hidden; transition: var(--transition); margin-bottom: 1.5rem; }
        .order-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
        .order-header { background: var(--bg-gray); padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 0.75rem; }
        .order-id { font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .order-date { font-size: 0.875rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; }
        .order-body { padding: 1.5rem; }
        .store-name { display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.875rem; }
        .info-item { display: flex; flex-direction: column; margin-bottom: 1rem; }
        .info-label { font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .info-value { font-weight: 600; }
        .info-value.price { color: var(--secondary); font-size: 1.25rem; }
        .status-badge { padding: 0.375rem 0.875rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.375rem; }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-diproses { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .status-dikirim { background: rgba(245,158,11,0.1); color: var(--accent); }
        .status-selesai { background: rgba(16,185,129,0.1); color: var(--secondary); }
        .status-dibatalkan { background: rgba(239,68,68,0.1); color: var(--danger); }
        .order-footer { display: flex; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border); }
        .btn { padding: 0.625rem 1.25rem; border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.875rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); color: white; }
        
        .empty-state { background: white; padding: 4rem 2rem; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow); }
        .empty-state i { font-size: 5rem; color: var(--text-secondary); margin-bottom: 1.5rem; opacity: 0.5; }
        .empty-state h2 { margin-bottom: 1rem; font-size: 1.75rem; }
        .empty-state p { color: var(--text-secondary); margin-bottom: 2rem; }
        
        .cancelled-info { background: rgba(239,68,68,0.05); padding: 0.75rem; border-radius: var(--radius); border-left: 3px solid var(--danger); margin-top: 0.75rem; }
        .cancelled-info-label { font-size: 0.7rem; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600; text-transform: uppercase; }
        .cancelled-info-text { font-size: 0.8rem; font-style: italic; }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-top { display: none; }
            .header-main { grid-template-columns: 1fr auto; gap: 1rem; padding: 0.875rem 1rem; }
            .logo { font-size: 1.5rem; order: 1; }
            .header-search { display: none; }
            .mobile-toggle { display: flex; order: 3; }
            .header-actions { position: fixed; top: 60px; left: 0; right: 0; background: white; padding: 1rem; box-shadow: var(--shadow-lg); display: none; flex-direction: column; gap: 0.5rem; z-index: 999; }
            .header-actions.active { display: flex; }
            .header-action { flex-direction: row; width: 100%; justify-content: flex-start; padding: 0.875rem 1rem; gap: 0.75rem; }
            .notification-wrapper { width: 100%; }
            .notification-dropdown { position: relative; top: 0; right: auto; width: 100%; margin-top: 0.5rem; max-height: 400px; }
            .nav { display: none; }
            body.mobile-menu-open::before { content: ''; position: fixed; top: 60px; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 998; }
        }
    </style>
</head>
<body>
    <!-- HEADER - SAMA SEPERTI INDEX.PHP -->
    <header class="header">
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
            
            <button class="mobile-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <div class="header-actions" id="headerActions">
                <a href="cart.php" class="header-action">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Keranjang</span>
                    <?php if ($cart_count > 0) { ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php } ?>
                </a>
                
                <!-- NOTIFICATION BELL -->
                <div class="notification-wrapper">
                    <div class="header-action" onclick="toggleNotifications(event)">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($notif_count > 0) { ?>
                            <span class="notification-badge" id="notificationBadge"><?php echo $notif_count > 99 ? '99+' : $notif_count; ?></span>
                        <?php } ?>
                    </div>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4>
                                <i class="fas fa-bell"></i> Notifikasi
                                <?php if ($notif_count > 0) { ?>
                                    <span class="badge bg-danger ms-2"><?php echo $notif_count; ?></span>
                                <?php } ?>
                            </h4>
                            <?php if ($notif_count > 0) { ?>
                                <span class="mark-all-read" onclick="markAllRead()">
                                    <i class="fas fa-check-double"></i> Tandai Semua
                                </span>
                            <?php } ?>
                        </div>
                        
                        <div class="notification-list">
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
                        
                        <div class="notification-footer">
                            <a href="notifications.php">
                                Lihat Semua Notifikasi <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <a href="riwayat_pesanan.php" class="header-action active">
                    <i class="fas fa-history"></i>
                    <span>Riwayat</span>
                </a>
                <a href="profil.php" class="header-action">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
                <a href="logout.php" class="header-action header-action-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </a>
            </div>
        </div>
        
        <nav class="nav">
            <div class="nav-content">
                <a href="index.php"><i class="fas fa-home"></i> Beranda</a>
                <a href="cart.php"><i class="fas fa-shopping-cart"></i> Keranjang</a>
                <a href="riwayat_pesanan.php"><i class="fas fa-history"></i> Riwayat</a>
                <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
                <a href="logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </nav>
    </header>

    <div class="container py-4">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <h1><i class="fas fa-history"></i> Riwayat Pesanan</h1>
            <p>Kelola dan lacak semua pesanan Anda</p>
        </div>

        <!-- Stats Grid -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card">
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card pending">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card diproses">
                    <div class="stat-label">Diproses</div>
                    <div class="stat-value"><?php echo $stats['diproses'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card dikirim">
                    <div class="stat-label">Dikirim</div>
                    <div class="stat-value"><?php echo $stats['dikirim'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card selesai">
                    <div class="stat-label">Selesai</div>
                    <div class="stat-value"><?php echo $stats['selesai'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="stat-card dibatalkan">
                    <div class="stat-label">Dibatalkan</div>
                    <div class="stat-value"><?php echo $stats['dibatalkan'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span class="filter-label">Filter Status:</span>
            <div class="d-flex flex-wrap gap-2">
                <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua
                </a>
                <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
                <a href="?status=diproses" class="filter-btn <?php echo $status_filter == 'diproses' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Diproses
                </a>
                <a href="?status=dikirim" class="filter-btn <?php echo $status_filter == 'dikirim' ? 'active' : ''; ?>">
                    <i class="fas fa-shipping-fast"></i> Dikirim
                </a>
                <a href="?status=selesai" class="filter-btn <?php echo $status_filter == 'selesai' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Selesai
                </a>
                <a href="?status=dibatalkan" class="filter-btn <?php echo $status_filter == 'dibatalkan' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Dibatalkan
                </a>
            </div>
        </div>

        <!-- Orders List -->
        <div class="orders-container">
            <?php if (mysqli_num_rows($result_pesanan) > 0) { ?>
                <?php while ($pesanan = mysqli_fetch_assoc($result_pesanan)) { 
                    $status_class = 'status-' . $pesanan['status_pesanan'];
                    $status_icon = 'fa-clock';
                    $status_text = 'Menunggu Konfirmasi';
                    
                    switch($pesanan['status_pesanan']) {
                        case 'pending': $status_icon = 'fa-clock'; $status_text = 'Menunggu Konfirmasi'; break;
                        case 'diproses': $status_icon = 'fa-cog'; $status_text = 'Sedang Diproses'; break;
                        case 'dikirim': $status_icon = 'fa-shipping-fast'; $status_text = 'Dalam Pengiriman'; break;
                        case 'selesai': $status_icon = 'fa-check-circle'; $status_text = 'Selesai'; break;
                        case 'dibatalkan': $status_icon = 'fa-times-circle'; $status_text = 'Dibatalkan'; break;
                    }
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">
                                <i class="fas fa-receipt"></i>
                                Pesanan #<?php echo htmlspecialchars($pesanan['pesanan_id']); ?>
                            </div>
                            <div class="order-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?>
                            </div>
                        </div>
                        
                        <div class="order-body">
                            <div class="store-name">
                                <i class="fas fa-store"></i>
                                <?php echo htmlspecialchars($pesanan['nama_grosir']); ?>
                            </div>
                            
                            <div class="row g-3 order-info">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="info-item">
                                        <span class="info-label">Total Pembayaran</span>
                                        <span class="info-value price">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="info-item">
                                        <span class="info-label">Status Pesanan</span>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas <?php echo $status_icon; ?>"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($pesanan['nomor_resi']) && $pesanan['status_pesanan'] != 'dibatalkan') { ?>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="info-item">
                                        <span class="info-label">Nomor Resi</span>
                                        <span class="info-value"><?php echo htmlspecialchars($pesanan['nomor_resi']); ?></span>
                                    </div>
                                </div>
                                <?php } ?>
                                <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="info-item">
                                        <span class="info-label">Dibatalkan Oleh</span>
                                        <span class="info-value" style="color: var(--danger);">
                                            <?php echo ucfirst($pesanan['dibatalkan_oleh'] ?? 'Penjual'); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            
                            <?php if ($pesanan['status_pesanan'] == 'dibatalkan' && !empty($pesanan['alasan_batal'])) { ?>
                            <div class="cancelled-info">
                                <div class="cancelled-info-label">ALASAN PEMBATALAN</div>
                                <div class="cancelled-info-text">
                                    "<?php echo htmlspecialchars($pesanan['alasan_batal']); ?>"
                                </div>
                            </div>
                            <?php } ?>
                            
                            <div class="order-footer">
                                <a href="detail_pesanan_pembeli.php?id=<?php echo $pesanan['pesanan_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> Lihat Detail
                                </a>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h2>Belum Ada Pesanan</h2>
                    <p>Anda belum pernah melakukan pesanan<?php echo $status_filter != 'all' ? ' dengan status ' . $status_filter : ''; ?>.</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Mulai Belanja Sekarang
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // MOBILE MENU TOGGLE
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
                if (dropdown) dropdown.classList.remove('active');
            }
        }

        // NOTIFICATION FUNCTIONS
        function toggleNotifications(event) {
            event.preventDefault();
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) dropdown.classList.toggle('active');
        }

        function markAsRead(event, notificationId) {
            fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
            
            fetch('api/mark_all_notifications_read.php', { method: 'POST' })
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

        // CLOSE DROPDOWNS WHEN CLICKING OUTSIDE
        document.addEventListener('click', function(e) {
            const notificationWrapper = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            const actions = document.getElementById('headerActions');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (notificationWrapper && dropdown && !notificationWrapper.contains(e.target)) {
                dropdown.classList.remove('active');
            }
            
            if (actions && toggle && !actions.contains(e.target) && !toggle.contains(e.target) && 
                actions.classList.contains('active') && window.innerWidth <= 768) {
                toggleMobileMenu();
            }
        });

        // HEADER SCROLL EFFECT
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.header');
            if (header) {
                header.classList.toggle('scrolled', window.pageYOffset > 100);
            }
        });

        // AUTO-REFRESH NOTIFICATION COUNT
        setInterval(function() {
            const notifBell = document.querySelector('.notification-wrapper');
            if (notifBell) updateNotificationCount();
        }, 30000);

        // KEYBOARD NAVIGATION
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const dropdown = document.getElementById('notificationDropdown');
                const mobileMenu = document.getElementById('headerActions');
                
                if (dropdown && dropdown.classList.contains('active')) {
                    dropdown.classList.remove('active');
                }
                if (mobileMenu && mobileMenu.classList.contains('active')) {
                    toggleMobileMenu();
                }
            }
        });

    </script>
</body>
</html>