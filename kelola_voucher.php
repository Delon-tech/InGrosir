<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Ambil semua voucher penjual
$query = "SELECT v.*, 
          CASE 
            WHEN v.tanggal_berakhir < NOW() THEN 'expired'
            WHEN v.kuota_total IS NOT NULL AND v.kuota_terpakai >= v.kuota_total THEN 'habis'
            WHEN v.is_active = 0 THEN 'nonaktif'
            ELSE 'aktif'
          END as status_real,
          (SELECT COUNT(*) FROM pesanan WHERE voucher_id = v.voucher_id) as total_penggunaan
          FROM voucher_diskon v 
          WHERE v.user_id_penjual = ? 
          ORDER BY v.created_at DESC";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Voucher - InGrosir</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        
        /* Sidebar - Same as dashboard.php */
        .sidebar {
            position: fixed; left: 0; top: 0; width: var(--sidebar-width); height: 100vh;
            background: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); z-index: 1000;
            overflow-y: auto; transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 2rem; border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
        }
        .sidebar-logo {
            font-size: 1.75rem; font-weight: 800; margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .sidebar-user {
            display: flex; align-items: center; gap: 1rem; padding: 1rem;
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .user-avatar {
            width: 50px; height: 50px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 1.25rem; flex-shrink: 0;
        }
        .user-info h3 { font-size: 0.95rem; margin-bottom: 0.25rem; font-weight: 600; color: #1f2937; }
        .user-info p { font-size: 0.8rem; color: #6b7280; margin: 0; }
        .sidebar-menu { padding: 1rem 0; }
        .menu-item {
            display: flex; align-items: center; gap: 1rem; padding: 0.875rem 2rem;
            color: #6b7280; text-decoration: none; transition: all 0.3s ease; position: relative;
        }
        .menu-item:hover, .menu-item.active { background: rgba(37, 99, 235, 0.08); color: var(--primary); }
        .menu-item.active::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px; background: var(--primary);
        }
        .menu-item i { width: 20px; text-align: center; font-size: 1.1rem; }
        
        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; padding: 2rem; }
        
        /* Page Header */
        .page-header {
            background: white; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem; padding: 2rem;
        }
        .page-title h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        .page-title p { color: #6b7280; font-size: 0.875rem; margin: 0; }
        
        /* Voucher Card */
        .voucher-card {
            background: white; border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); transition: all 0.3s ease;
            border-left: 4px solid var(--primary); margin-bottom: 1.5rem;
        }
        .voucher-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
        .voucher-code {
            font-size: 1.5rem; font-weight: 800; color: var(--primary);
            padding: 0.75rem 1.5rem; background: rgba(37, 99, 235, 0.1);
            border-radius: 8px; display: inline-block; letter-spacing: 2px;
            border: 2px dashed var(--primary); margin-bottom: 1rem;
        }
        .voucher-badge {
            padding: 0.35rem 0.85rem; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600; display: inline-block;
        }
        .badge-aktif { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-expired { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-habis { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-nonaktif { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none; position: fixed; bottom: 2rem; right: 2rem;
            width: 60px; height: 60px; background: var(--primary); color: white;
            border: none; border-radius: 50%; font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); z-index: 999;
            transition: all 0.3s ease;
        }
        .mobile-toggle:hover { transform: scale(1.1); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .voucher-code { font-size: 1.25rem; padding: 0.5rem 1rem; }
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
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="produk_list.php" class="menu-item">
                <i class="fas fa-box"></i><span>Kelola Produk</span>
            </a>
            <a href="pesanan.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i><span>Kelola Pesanan</span>
            </a>
            <a href="kelola_voucher.php" class="menu-item active">
                <i class="fas fa-ticket-alt"></i><span>Kelola Voucher</span>
            </a>
            <a href="kelola_metode_pembayaran.php" class="menu-item">
                <i class="fas fa-credit-card"></i><span>Metode Pembayaran</span>
            </a>
            <a href="laporan_penjualan.php" class="menu-item">
                <i class="fas fa-chart-line"></i><span>Laporan Penjualan</span>
            </a>
            <a href="profil.php" class="menu-item">
                <i class="fas fa-user"></i><span>Profil Saya</span>
            </a>
            <a href="index.php" class="menu-item">
                <i class="fas fa-globe"></i><span>Halaman Utama</span>
            </a>
            <a href="logout.php" class="menu-item text-danger">
                <i class="fas fa-sign-out-alt"></i><span>Keluar</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="page-title">
                    <h1><i class="fas fa-ticket-alt me-2"></i>Kelola Voucher Diskon</h1>
                    <p>Buat dan kelola voucher diskon untuk pembeli</p>
                </div>
                <a href="voucher_add.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Buat Voucher Baru
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Berhasil!</strong> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <?php if ($error) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <!-- Voucher List -->
        <?php if (mysqli_num_rows($result) > 0) { ?>
            <div class="row">
                <?php while ($voucher = mysqli_fetch_assoc($result)) { 
                    $badge_class = 'badge-' . $voucher['status_real'];
                    $status_text = ucfirst($voucher['status_real']);
                    
                    // Format nilai diskon
                    if ($voucher['tipe_diskon'] == 'persentase') {
                        $diskon_text = number_format($voucher['nilai_diskon'], 0) . '%';
                    } else {
                        $diskon_text = 'Rp ' . number_format($voucher['nilai_diskon'], 0, ',', '.');
                    }
                ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="voucher-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="voucher-code"><?php echo htmlspecialchars($voucher['kode_voucher']); ?></div>
                            <span class="voucher-badge <?php echo $badge_class; ?>">
                                <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <h5 class="mb-2">
                                <i class="fas fa-gift text-primary me-2"></i>
                                Diskon <strong class="text-success"><?php echo $diskon_text; ?></strong>
                            </h5>
                            <?php if (!empty($voucher['deskripsi'])) { ?>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($voucher['deskripsi']); ?></p>
                            <?php } ?>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <small class="text-muted d-block">Min. Pembelian</small>
                                    <strong>Rp <?php echo number_format($voucher['min_pembelian'], 0, ',', '.'); ?></strong>
                                </div>
                            </div>
                            <?php if ($voucher['max_diskon']) { ?>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <small class="text-muted d-block">Max. Diskon</small>
                                    <strong>Rp <?php echo number_format($voucher['max_diskon'], 0, ',', '.'); ?></strong>
                                </div>
                            </div>
                            <?php } ?>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <small class="text-muted d-block">Kuota</small>
                                    <strong>
                                        <?php 
                                        if ($voucher['kuota_total']) {
                                            echo $voucher['kuota_terpakai'] . '/' . $voucher['kuota_total'];
                                        } else {
                                            echo 'Unlimited';
                                        }
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded">
                                    <small class="text-muted d-block">Digunakan</small>
                                    <strong><?php echo $voucher['total_penggunaan']; ?>x</strong>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Berlaku: <?php echo date('d M Y', strtotime($voucher['tanggal_mulai'])); ?> 
                                - <?php echo date('d M Y', strtotime($voucher['tanggal_berakhir'])); ?>
                            </small>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="voucher_edit.php?id=<?php echo $voucher['voucher_id']; ?>" 
                               class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button onclick="confirmDelete(<?php echo $voucher['voucher_id']; ?>, '<?php echo htmlspecialchars($voucher['kode_voucher']); ?>')" 
                                    class="btn btn-sm btn-outline-danger flex-fill">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <!-- Empty State -->
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-ticket-alt" style="font-size: 5rem; color: #e5e7eb;"></i>
                </div>
                <h3 class="mb-3">Belum Ada Voucher</h3>
                <p class="text-muted mb-4">Mulai buat voucher diskon untuk menarik lebih banyak pembeli</p>
                <a href="voucher_add.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Buat Voucher Pertama
                </a>
            </div>
        <?php } ?>
    </main>

    <!-- Mobile Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

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

        function confirmDelete(id, code) {
            if (confirm(`Hapus voucher "${code}"?\n\nVoucher yang sudah digunakan di pesanan akan tetap tercatat.`)) {
                window.location.href = `voucher_delete.php?id=${id}`;
            }
        }
    </script>
</body>
</html>