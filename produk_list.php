<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Success message
$success_message = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'added') {
        $success_message = 'Produk berhasil ditambahkan!';
    } elseif ($_GET['success'] == 'updated') {
        $success_message = 'Produk berhasil diupdate!';
    } elseif ($_GET['success'] == 'deleted') {
        $success_message = 'Produk berhasil dihapus!';
    }
}

// Query produk dengan JOIN kategori
$query = "SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori_produk k ON p.kategori_id = k.kategori_id WHERE p.user_id = ? ORDER BY p.produk_id DESC";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_produk = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - InGrosir</title>
    
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
    
    /* Alert Success */
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Stats Cards */
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
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
    .stat-card.danger { border-left-color: var(--danger); }
    
    .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .stat-card.primary .stat-value { color: var(--primary); }
    .stat-card.success .stat-value { color: var(--success); }
    .stat-card.danger .stat-value { color: var(--danger); }
    
    /* View Controls */
    .view-controls {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }
    
    /* Product Grid */
    .product-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .product-info {
        padding: 1.25rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .product-name {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }
    
    .product-category {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: rgba(37, 99, 235, 0.1);
        color: var(--primary);
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }
    
    .product-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }
    
    .product-meta {
        display: flex;
        justify-content: space-between;
        padding-top: 0.75rem;
        border-top: 1px solid #e9ecef;
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 1rem;
    }
    
    .product-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: auto;
    }
    
    /* Empty State */
    .empty-state {
        background: white;
        padding: 4rem 2rem;
        border-radius: 16px;
        text-align: center;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
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
        margin-bottom: 1.5rem;
    }
    
    /* Modal */
    .modal-content {
        border-radius: 16px;
        border: none;
    }
    
    .modal-header {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
        border-bottom: 1px solid #e9ecef;
        border-radius: 16px 16px 0 0;
    }
    
    .modal-icon {
        font-size: 3rem;
        color: var(--danger);
        margin-bottom: 1rem;
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
            <a href="produk_list.php" class="menu-item active">
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
        <div class="page-header p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="page-title">
                        <h1><i class="fas fa-box me-2"></i>Kelola Produk</h1>
                        <p>Manajemen produk toko Anda</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end">
                        <a href="produk_add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Tambah Produk Baru
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success_message) { ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php } ?>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card primary">
                    <div class="stat-label">Total Produk</div>
                    <div class="stat-value"><?php echo $total_produk; ?></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card success">
                    <div class="stat-label">Produk Aktif</div>
                    <div class="stat-value"><?php echo $total_produk; ?></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="stat-card danger">
                    <div class="stat-label">Stok Rendah</div>
                    <div class="stat-value">0</div>
                </div>
            </div>
        </div>

        <!-- View Controls -->
        <div class="view-controls">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo $total_produk; ?></strong> produk ditemukan
                </div>
            </div>
        </div>

        <!-- Product Grid -->
        <?php if ($total_produk > 0) { ?>
            <div class="row g-4">
                <?php 
                mysqli_data_seek($result, 0);
                while ($produk = mysqli_fetch_assoc($result)) { 
                    $gambar_path = !empty($produk['gambar_produk']) 
                        ? "uploads/" . htmlspecialchars($produk['gambar_produk']) 
                        : "https://via.placeholder.com/280x200/667eea/ffffff?text=" . urlencode(substr($produk['nama_produk'], 0, 20));
                ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="product-card">
                        <img src="<?php echo $gambar_path; ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="product-image">
                        
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></h3>
                            
                            <?php if ($produk['nama_kategori']) { ?>
                                <span class="product-category">
                                    <?php echo htmlspecialchars($produk['nama_kategori']); ?>
                                </span>
                            <?php } ?>
                            
                            <div class="product-price">
                                Rp <?php echo number_format($produk['harga_grosir'], 0, ',', '.'); ?>
                            </div>
                            
                            <div class="product-meta">
                                <span><i class="fas fa-boxes me-1"></i>Stok: <?php echo $produk['stok']; ?></span>
                                <span><i class="fas fa-eye me-1"></i>120 views</span>
                            </div>
                            
                            <div class="product-actions">
                                <a href="produk_edit.php?id=<?php echo $produk['produk_id']; ?>" class="btn btn-success btn-sm flex-fill">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button onclick="confirmDelete(<?php echo $produk['produk_id']; ?>, '<?php echo addslashes($produk['nama_produk']); ?>')" class="btn btn-danger btn-sm flex-fill">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Belum Ada Produk</h3>
                <p>Mulai tambahkan produk pertama Anda untuk ditampilkan di toko</p>
                <a href="produk_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Tambah Produk Pertama
                </a>
            </div>
        <?php } ?>
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hapus Produk?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p id="deleteMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Ya, Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>

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

        function confirmDelete(id, name) {
            document.getElementById('deleteMessage').textContent = 
                `Apakah Anda yakin ingin menghapus produk "${name}"? Tindakan ini tidak dapat dibatalkan.`;
            document.getElementById('confirmDeleteBtn').href = 
                `produk_delete.php?id=${id}`;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
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

        // Auto-hide success message after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.animation = 'slideDown 0.3s ease reverse';
                setTimeout(() => {
                    successAlert.remove();
                }, 300);
            }, 5000);
        }
    </script>
    </body>
</html>