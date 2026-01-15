<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$success = '';
$error = '';

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $catatan = trim($_POST['catatan'] ?? '');
    
    $new_status = ($action == 'approve') ? 'approved' : 'rejected';
    $tanggal_verifikasi = date('Y-m-d H:i:s');
    $admin_id = $_SESSION['admin_id'];
    
    $update_query = "UPDATE users SET status_verifikasi = ?, tanggal_verifikasi = ?, verified_by = ?, catatan_verifikasi = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($koneksi, $update_query);
    mysqli_stmt_bind_param($stmt, "ssisi", $new_status, $tanggal_verifikasi, $admin_id, $catatan, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = ($action == 'approve') ? "Penjual berhasil diverifikasi!" : "Penjual berhasil ditolak.";
    } else {
        $error = "Gagal memproses verifikasi: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
}

// Get Pending Sellers
$pending_query = "SELECT user_id, nama_lengkap, email, nama_grosir, alamat_grosir, nomor_telepon, gambar_toko, tanggal_registrasi FROM users WHERE peran = 'penjual' AND status_verifikasi = 'pending' ORDER BY tanggal_registrasi DESC";
$pending_result = mysqli_query($koneksi, $pending_query);

// Get Verified Sellers
$verified_query = "SELECT u.user_id, u.nama_lengkap, u.email, u.nama_grosir, u.status_verifikasi, u.tanggal_verifikasi, u.catatan_verifikasi, a.nama_lengkap as verified_by_name FROM users u LEFT JOIN users a ON u.verified_by = a.user_id WHERE u.peran = 'penjual' AND u.status_verifikasi IN ('approved', 'rejected') ORDER BY u.tanggal_verifikasi DESC LIMIT 20";
$verified_result = mysqli_query($koneksi, $verified_query);

// Statistics
$total_pending = mysqli_num_rows($pending_result);
$stats_query = "SELECT 
    COUNT(CASE WHEN status_verifikasi = 'approved' THEN 1 END) as total_approved,
    COUNT(CASE WHEN status_verifikasi = 'rejected' THEN 1 END) as total_rejected
    FROM users WHERE peran = 'penjual'";
$stats_result = mysqli_fetch_assoc(mysqli_query($koneksi, $stats_query));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Penjual - Admin InGrosir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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
        
        /* Sidebar - Consistent with admin_produk.php */
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
        
        .menu-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }
        
        /* Main Content */
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
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        /* Stats */
        .stats-grid {
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
        
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.danger { border-left-color: var(--danger); }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* Card */
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        
        /* Seller Card */
        .seller-card {
            background: white;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        
        .seller-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .seller-header {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .seller-image {
            width: 120px;
            height: 120px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 3px solid var(--border);
        }
        
        .seller-info {
            flex: 1;
        }
        
        .seller-info h3 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .info-row i {
            width: 20px;
            color: var(--primary);
        }
        
        /* Actions */
        .seller-actions {
            display: flex;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: var(--bg-gray);
            color: var(--text-primary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--border);
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--bg-gray);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--text-light);
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
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
            align-items: center;
            justify-content: center;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
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
            }
            
            .seller-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .seller-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
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
                    <a href="admin_dashboard.php" class="menu-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="admin_users.php" class="menu-item">
                        <i class="fas fa-users"></i>
                        <span>Kelola Pengguna</span>
                    </a>
                    <a href="admin_verify_sellers.php" class="menu-item active">
                        <i class="fas fa-user-check"></i>
                        <span>Verifikasi Penjual</span>
                        <?php if ($total_pending > 0) { ?>
                        <span class="menu-badge"><?php echo $total_pending; ?></span>
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-user-check"></i>
                    Verifikasi Penjual
                </h1>
                <p>Tinjau dan verifikasi penjual yang mendaftar di platform</p>
            </div>

            <?php if ($success) { ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <?php } ?>

            <?php if ($error) { ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <?php } ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card warning">
                    <div class="stat-label">Menunggu Verifikasi</div>
                    <div class="stat-value"><?php echo $total_pending; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Disetujui</div>
                    <div class="stat-value"><?php echo $stats_result['total_approved']; ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">Ditolak</div>
                    <div class="stat-value"><?php echo $stats_result['total_rejected']; ?></div>
                </div>
            </div>

            <!-- Pending Sellers -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-clock"></i>
                        Penjual Menunggu Verifikasi
                    </h2>
                </div>

                <?php if (mysqli_num_rows($pending_result) > 0) {
                    mysqli_data_seek($pending_result, 0);
                    while ($seller = mysqli_fetch_assoc($pending_result)) { ?>
                <div class="seller-card">
                    <div class="seller-header">
                        <img src="<?php echo htmlspecialchars($seller['gambar_toko']); ?>" alt="Toko" class="seller-image" onerror="this.src='assets/images/default-store.png'">
                        <div class="seller-info">
                            <h3><?php echo htmlspecialchars($seller['nama_grosir']); ?></h3>
                            <div class="info-row">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($seller['nama_lengkap']); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($seller['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($seller['nomor_telepon']); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($seller['alamat_grosir']); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-calendar"></i>
                                <span>Terdaftar: <?php echo date('d M Y, H:i', strtotime($seller['tanggal_registrasi'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seller-actions">
                        <button class="btn btn-success" onclick="openApproveModal(<?php echo $seller['user_id']; ?>, '<?php echo htmlspecialchars($seller['nama_grosir'], ENT_QUOTES); ?>')">
                            <i class="fas fa-check"></i>
                            Setujui
                        </button>
                        <button class="btn btn-danger" onclick="openRejectModal(<?php echo $seller['user_id']; ?>, '<?php echo htmlspecialchars($seller['nama_grosir'], ENT_QUOTES); ?>')">
                            <i class="fas fa-times"></i>
                            Tolak
                        </button>
                    </div>
                </div>
                <?php } 
                } else { ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>Tidak Ada Penjual Pending</h3>
                    <p>Semua penjual sudah diverifikasi</p>
                </div>
                <?php } ?>
            </div>

            <!-- Verified Sellers History -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-history"></i>
                        Riwayat Verifikasi
                    </h2>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Toko</th>
                                <th>Pemilik</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Verified By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($verified_result) > 0) {
                                while ($verified = mysqli_fetch_assoc($verified_result)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($verified['nama_grosir']); ?></td>
                                <td><?php echo htmlspecialchars($verified['nama_lengkap']); ?></td>
                                <td>
                                    <span class="badge <?php echo $verified['status_verifikasi'] == 'approved' ? 'badge-success' : 'badge-danger'; ?>">
                                        <i class="fas fa-<?php echo $verified['status_verifikasi'] == 'approved' ? 'check' : 'times'; ?>"></i>
                                        <?php echo $verified['status_verifikasi'] == 'approved' ? 'Disetujui' : 'Ditolak'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($verified['tanggal_verifikasi'])); ?></td>
                                <td><?php echo htmlspecialchars($verified['verified_by_name'] ?? 'Admin'); ?></td>
                            </tr>
                            <?php } 
                            } else { ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                    Belum ada riwayat verifikasi
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    Setujui Penjual
                </h3>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="approve_user_id">
                <input type="hidden" name="action" value="approve">
                
                <p style="margin-bottom: 1.5rem;">Anda yakin ingin menyetujui toko <strong id="approve_store_name"></strong>?</p>
                
                <div class="form-group">
                    <label>Catatan (Opsional)</label>
                    <textarea name="catatan" placeholder="Tambahkan catatan..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i>
                        Setujui
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                    Tolak Penjual
                </h3>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="reject_user_id">
                <input type="hidden" name="action" value="reject">
                
                <p style="margin-bottom: 1.5rem;">Anda yakin ingin menolak toko <strong id="reject_store_name"></strong>?</p>
                
                <div class="form-group">
                    <label>Alasan Penolakan <span style="color: var(--danger);">*</span></label>
                    <textarea name="catatan" placeholder="Jelaskan alasan penolakan..." required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

        // Modal Functions
        function openApproveModal(userId, storeName) {
            document.getElementById('approve_user_id').value = userId;
            document.getElementById('approve_store_name').textContent = storeName;
            document.getElementById('approveModal').classList.add('active');
        }
        
        function openRejectModal(userId, storeName) {
            document.getElementById('reject_user_id').value = userId;
            document.getElementById('reject_store_name').textContent = storeName;
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>