<?php
session_start();
include 'config/koneksi.php';

$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt_user_data = mysqli_prepare($koneksi, $user_query);
mysqli_stmt_bind_param($stmt_user_data, "i", $user_id);
mysqli_stmt_execute($stmt_user_data);
$result_user_data = mysqli_stmt_get_result($stmt_user_data);
$user = mysqli_fetch_assoc($result_user_data);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        
        $update_query = "UPDATE users SET nama_lengkap = ?, email = ? WHERE user_id = ?";
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ssi", $nama_lengkap, $email, $user_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Profil berhasil diperbarui!";
            $user['nama_lengkap'] = $nama_lengkap;
            $user['email'] = $email;
        } else {
            $error = "Gagal memperbarui profil.";
        }
    } 
    elseif (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "Kata sandi baru tidak cocok.";
        } elseif (strlen($new_password) < 6) {
            $error = "Kata sandi baru minimal 6 karakter.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = "Kata sandi saat ini salah.";
        } else {
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_stmt = mysqli_prepare($koneksi, $update_query);
            mysqli_stmt_bind_param($update_stmt, "si", $new_password_hashed, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Kata sandi berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui kata sandi.";
            }
        }
    } 
    elseif (isset($_POST['update_grosir']) && $user['peran'] == 'penjual') {
        $nama_grosir = trim($_POST['nama_grosir']);
        $alamat_grosir = trim($_POST['alamat_grosir']);
        $nomor_telepon = trim($_POST['nomor_telepon']);
        
        $update_query = "UPDATE users SET nama_grosir = ?, alamat_grosir = ?, nomor_telepon = ? WHERE user_id = ?";
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($update_stmt, "sssi", $nama_grosir, $alamat_grosir, $nomor_telepon, $user_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Data grosir berhasil diperbarui!";
            $_SESSION['nama_grosir'] = $nama_grosir;
            $user['nama_grosir'] = $nama_grosir;
            $user['alamat_grosir'] = $alamat_grosir;
            $user['nomor_telepon'] = $nomor_telepon;
        } else {
            $error = "Gagal memperbarui data grosir.";
        }
    }
}

// Cek apakah user adalah penjual atau pembeli
$isPenjual = ($user['peran'] == 'penjual');

// Hitung jumlah item di keranjang jika user adalah pembeli
$cart_count = 0;
if (!$isPenjual) {
    $cart_query = "SELECT COUNT(*) as total FROM keranjang WHERE user_id = ?";
    $cart_stmt = mysqli_prepare($koneksi, $cart_query);
    mysqli_stmt_bind_param($cart_stmt, "i", $user_id);
    mysqli_stmt_execute($cart_stmt);
    $cart_result = mysqli_stmt_get_result($cart_stmt);
    $cart_count = mysqli_fetch_assoc($cart_result)['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - InGrosir</title>
    
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
            --danger: #ef4444;
            --warning: #f59e0b;
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
        
        /* Navbar untuk Pembeli */
        .navbar-custom {
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-logo {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        
        .navbar-link {
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .navbar-link:hover,
        .navbar-link.active {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.08);
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Mobile Menu Overlay */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Mobile Menu Sidebar */
        .mobile-menu {
            position: fixed;
            top: -100%;
            left: 0;
            right: 0;
            width: 100%;
            background: white;
            z-index: 1999;
            transition: top 0.3s ease;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-height: 80vh;
        }
        
        .mobile-menu.active {
            top: 0;
        }
        
        .mobile-menu-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .mobile-menu-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }
        
        .mobile-menu-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        
        .mobile-menu-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .mobile-menu-items {
            padding: 1rem 0;
        }
        
        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: #374151;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .mobile-menu-item:hover,
        .mobile-menu-item.active {
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .mobile-menu-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .mobile-menu-item span {
            font-weight: 500;
        }
        
        .mobile-menu-item.logout {
            color: var(--danger);
            margin-top: 1rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }
        
        .mobile-menu-item.logout:hover {
            background: rgba(239, 68, 68, 0.08);
            border-left-color: var(--danger);
        }
        
        /* Sidebar untuk Penjual */
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
            min-height: 100vh;
            padding: 2rem;
        }
        
        .main-content.with-sidebar {
            margin-left: var(--sidebar-width);
        }
        
        .main-content.no-sidebar {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            padding: 2rem;
        }
        
        /* Card Enhancement */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            height: 100%;
        }
        
        .card-header-custom {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .card-header-custom i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .card-header-custom h3 {
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .card-header-custom p {
            font-size: 0.875rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .card-header-custom.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .card-header-custom.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
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
            
            .main-content,
            .main-content.with-sidebar,
            .main-content.no-sidebar {
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
            
            .page-header h1 {
                font-size: 1.5rem !important;
            }
        }
    </style>
</head>
<body>
    <?php if ($isPenjual) { ?>
    <!-- Sidebar untuk Penjual -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">InGrosir</div>
            <div class="sidebar-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['nama_grosir'] ?? $user['nama_lengkap'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($user['nama_grosir'] ?? $user['nama_lengkap']); ?></h3>
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
            <a href="profil.php" class="menu-item active">
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
    <?php } else { ?>
    <!-- Navbar untuk Pembeli -->
    <nav class="navbar-custom">
        <div class="container">
            <div class="row align-items-center w-100">
                <div class="col-6 col-md-3">
                    <a href="index.php" class="navbar-logo">InGrosir</a>
                </div>
                <div class="col-md-9 d-none d-md-block">
                    <div class="d-flex gap-2 justify-content-end align-items-center">
                        <a href="index.php" class="navbar-link">
                            <i class="fas fa-home me-1"></i> Beranda
                        </a>
                        <a href="cart.php" class="navbar-link position-relative">
                            <i class="fas fa-shopping-cart me-1"></i> Keranjang
                            <?php if ($cart_count > 0) { ?>
                                <span class="cart-badge"><?php echo $cart_count; ?></span>
                            <?php } ?>
                        </a>
                        <a href="riwayat_pesanan.php" class="navbar-link">
                            <i class="fas fa-history me-1"></i> Riwayat
                        </a>
                        <a href="profil.php" class="navbar-link active">
                            <i class="fas fa-user me-1"></i> Profil
                        </a>
                        <a href="logout.php" class="navbar-link text-danger">
                            <i class="fas fa-sign-out-alt me-1"></i> Keluar
                        </a>
                    </div>
                </div>
                <div class="col-6 d-md-none text-end">
                    <button class="btn btn-link text-dark p-0" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="toggleMobileMenu()"></div>
    
    <!-- Mobile Menu Sidebar -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <h3>Menu</h3>
            <button class="mobile-menu-close" onclick="toggleMobileMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-menu-items">
            <a href="index.php" class="mobile-menu-item">
                <i class="fas fa-home"></i>
                <span>Beranda</span>
            </a>
            <a href="cart.php" class="mobile-menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Keranjang <?php if ($cart_count > 0) echo "($cart_count)"; ?></span>
            </a>
            <a href="riwayat_pesanan.php" class="mobile-menu-item">
                <i class="fas fa-history"></i>
                <span>Riwayat Pesanan</span>
            </a>
            <a href="profil.php" class="mobile-menu-item active">
                <i class="fas fa-user"></i>
                <span>Profil Saya</span>
            </a>
            <a href="logout.php" class="mobile-menu-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
    <?php } ?>

    <!-- Main Content -->
    <main class="main-content <?php echo $isPenjual ? 'with-sidebar' : 'no-sidebar'; ?>">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-12">
                    <h1 class="h3 mb-2 fw-bold">
                        <i class="fas fa-user-circle text-primary"></i> Profil Saya
                    </h1>
                    <p class="text-muted mb-0">
                        Kelola informasi akun<?php echo $isPenjual ? ' dan data grosir' : ''; ?> Anda
                    </p>
                </div>
            </div>
        </div>

        <?php if ($success) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Berhasil!</strong> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <?php if ($error) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <!-- Profile Grid -->
        <div class="row g-4">
            <!-- Card: Profil Pribadi -->
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header-custom">
                        <i class="fas fa-user"></i>
                        <div>
                            <h3>Profil Pribadi</h3>
                            <p>Informasi akun Anda</p>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form action="profil.php" method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama_lengkap" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Simpan Profil
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card: Data Grosir (hanya untuk penjual) -->
            <?php if ($user['peran'] == 'penjual') { ?>
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header-custom warning">
                        <i class="fas fa-store"></i>
                        <div>
                            <h3>Data Grosir</h3>
                            <p>Informasi toko Anda</p>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form action="profil.php" method="POST">
                            <input type="hidden" name="update_grosir" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Nama Grosir <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama_grosir" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['nama_grosir']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Alamat Grosir <span class="text-danger">*</span>
                                </label>
                                <textarea name="alamat_grosir" class="form-control" rows="3" required><?php echo htmlspecialchars($user['alamat_grosir']); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Nomor Telepon <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nomor_telepon" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['nomor_telepon']); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 text-white">
                                <i class="fas fa-save me-2"></i>Simpan Data Grosir
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php } ?>

            <!-- Card: Ubah Kata Sandi -->
            <div class="col-12 <?php echo $isPenjual ? 'col-lg-6' : 'col-lg-6'; ?>">
                <div class="card">
                    <div class="card-header-custom danger">
                        <i class="fas fa-lock"></i>
                        <div>
                            <h3>Keamanan</h3>
                            <p>Ubah kata sandi Anda</p>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Gunakan kata sandi yang kuat minimal 6 karakter</small>
                        </div>

                        <form action="profil.php" method="POST">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Kata Sandi Saat Ini <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Kata Sandi Baru <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Konfirmasi Kata Sandi <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-key me-2"></i>Ubah Kata Sandi
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Menu Toggle untuk Penjual -->
    <?php if ($isPenjual) { ?>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <?php } ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar untuk penjual
        function toggleSidebar() {
            document.getElementById('sidebar')?.classList.toggle('active');
        }

        // Toggle mobile menu untuk pembeli
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            
            if (mobileMenu && overlay) {
                mobileMenu.classList.toggle('active');
                overlay.classList.toggle('active');
                
                // Prevent body scroll when menu is open
                if (mobileMenu.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }

        // Close sidebar/menu when clicking outside (untuk penjual)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (sidebar && window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && toggle && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Close mobile menu dengan tombol ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const mobileMenu = document.getElementById('mobileMenu');
                const overlay = document.getElementById('mobileMenuOverlay');
                
                if (mobileMenu && mobileMenu.classList.contains('active')) {
                    mobileMenu.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    </script>
</body>
</html>