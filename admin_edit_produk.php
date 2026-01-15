<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$produk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$produk_id) {
    header("Location: admin_produk.php");
    exit();
}

// Get product data
$produk_query = "SELECT p.*, u.nama_grosir FROM produk p JOIN users u ON p.user_id = u.user_id WHERE p.produk_id = ?";
$stmt_produk = mysqli_prepare($koneksi, $produk_query);
mysqli_stmt_bind_param($stmt_produk, "i", $produk_id);
mysqli_stmt_execute($stmt_produk);
$result_produk = mysqli_stmt_get_result($stmt_produk);
$produk = mysqli_fetch_assoc($result_produk);

if (!$produk) {
    $_SESSION['error'] = "Produk tidak ditemukan!";
    header("Location: admin_produk.php");
    exit();
}

// Get categories
$kategori_query = "SELECT * FROM kategori_produk ORDER BY nama_kategori ASC";
$kategori_result = mysqli_query($koneksi, $kategori_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_produk = trim($_POST['nama_produk']);
    $deskripsi_produk = trim($_POST['deskripsi_produk']);
    $harga_grosir = (float)$_POST['harga_grosir'];
    $stok = (int)$_POST['stok'];
    $kategori_id = (int)$_POST['kategori_id'];
    $gambar_produk = $produk['gambar_produk'];

    // Handle image upload
    if (isset($_FILES['gambar_produk']) && $_FILES['gambar_produk']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['gambar_produk']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('prod_', true) . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Validate file
        $max_file_size = 5 * 1024 * 1024; // 5MB
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if ($_FILES['gambar_produk']['size'] > $max_file_size) {
            $error = "Ukuran file terlalu besar. Maksimum 5MB.";
        } elseif (!in_array($file_extension, $allowed_types)) {
            $error = "Hanya file JPG, JPEG, PNG, GIF & WEBP yang diizinkan.";
        } else {
            $check = getimagesize($_FILES['gambar_produk']['tmp_name']);
            if ($check === false) {
                $error = "File yang diunggah bukan gambar.";
            }
        }

        if (empty($error)) {
            if (move_uploaded_file($_FILES['gambar_produk']['tmp_name'], $target_file)) {
                // Delete old image
                if (!empty($produk['gambar_produk']) && file_exists($target_dir . $produk['gambar_produk'])) {
                    unlink($target_dir . $produk['gambar_produk']);
                }
                $gambar_produk = $new_filename;
            } else {
                $error = "Gagal mengunggah gambar.";
            }
        }
    }

    if (!$error) {
        $update_query = "UPDATE produk SET nama_produk = ?, deskripsi_produk = ?, harga_grosir = ?, stok = ?, kategori_id = ?, gambar_produk = ? WHERE produk_id = ?";
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ssdissi", $nama_produk, $deskripsi_produk, $harga_grosir, $stok, $kategori_id, $gambar_produk, $produk_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION['success'] = "Produk berhasil diperbarui!";
            header("Location: admin_produk.php");
            exit();
        } else {
            $error = "Gagal memperbarui produk: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($update_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk - InGrosir Admin</title>
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
        
        /* Sidebar - Sama seperti file sebelumnya */
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
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .page-title h1 {
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Form Layout */
        .form-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        .form-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .form-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg-gray);
        }
        
        .form-header h2 {
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
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
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        .required {
            color: var(--danger);
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .input-hint {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-top: 0.375rem;
        }
        
        /* Image Upload */
        .image-upload-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .current-image {
            padding: 2rem;
            text-align: center;
        }
        
        .image-preview {
            width: 100%;
            max-width: 300px;
            height: 300px;
            margin: 0 auto 1rem;
            border-radius: var(--radius);
            object-fit: cover;
            border: 2px solid var(--border);
        }
        
        .no-image {
            width: 100%;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-gray);
            border-radius: var(--radius);
            color: var(--text-light);
            font-size: 4rem;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-gray);
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .file-input-label:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            color: var(--primary);
        }
        
        .file-input-label i {
            font-size: 1.5rem;
        }
        
        .file-name {
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: rgba(16, 185, 129, 0.1);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--success);
            text-align: center;
            display: none;
        }
        
        .file-name.show {
            display: block;
        }
        
        /* Product Info Card */
        .product-info-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8125rem;
            font-weight: 600;
        }
        
        .stock-status.high {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stock-status.low {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stock-status.out {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.9375rem;
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
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-secondary);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
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
        @media (max-width: 1024px) {
            .form-layout {
                grid-template-columns: 1fr;
            }
        }
        
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
            
            .form-body {
                padding: 1.5rem;
            }
            
            .form-actions {
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
                <div class="breadcrumb">
                    <a href="admin_dashboard.php">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="admin_produk.php">Kelola Produk</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Edit Produk</span>
                </div>
                <div class="page-title">
                    <h1>
                        <i class="fas fa-edit"></i>
                        Edit Produk
                    </h1>
                </div>
            </div>

            <!-- Form Layout -->
            <div class="form-layout">
                <!-- Left Column - Form -->
                <div class="form-container">
                    <div class="form-header">
                        <h2>
                            <i class="fas fa-box"></i>
                            Informasi Produk
                        </h2>
                    </div>
                    
                    <div class="form-body">
                        <?php if ($error) { ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                        <?php } ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="nama_produk">
                                    Nama Produk <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="nama_produk" 
                                    name="nama_produk" 
                                    value="<?php echo htmlspecialchars($produk['nama_produk']); ?>" 
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="deskripsi_produk">
                                    Deskripsi Produk <span class="required">*</span>
                                </label>
                                <textarea 
                                    id="deskripsi_produk" 
                                    name="deskripsi_produk" 
                                    required
                                ><?php echo htmlspecialchars($produk['deskripsi_produk']); ?></textarea>
                                <div class="input-hint">Jelaskan detail produk dengan jelas</div>
                            </div>

                            <div class="form-group">
                                <label for="harga_grosir">
                                    Harga Grosir (Rp) <span class="required">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    id="harga_grosir" 
                                    name="harga_grosir" 
                                    step="0.01" 
                                    value="<?php echo htmlspecialchars($produk['harga_grosir']); ?>" 
                                    required
                                >
                                <div class="input-hint">Masukkan harga dalam Rupiah</div>
                            </div>

                            <div class="form-group">
                                <label for="stok">
                                    Stok <span class="required">*</span>
                                </label>
                                <input 
                                    type="number" 
                                    id="stok" 
                                    name="stok" 
                                    value="<?php echo htmlspecialchars($produk['stok']); ?>" 
                                    required
                                >
                                <div class="input-hint">Jumlah unit yang tersedia</div>
                            </div>

                            <div class="form-group">
                                <label for="kategori_id">
                                    Kategori <span class="required">*</span>
                                </label>
                                <select id="kategori_id" name="kategori_id" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php while ($row = mysqli_fetch_assoc($kategori_result)) { ?>
                                    <option value="<?php echo $row['kategori_id']; ?>" <?php echo ($row['kategori_id'] == $produk['kategori_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama_kategori']); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Simpan Perubahan
                                </button>
                                <a href="admin_produk.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i>
                                    Batal
                                </a>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete()" style="margin-left: auto;">
                                    <i class="fas fa-trash"></i>
                                    Hapus Produk
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Image & Info -->
                <div>
                    <!-- Product Info Card -->
                    <div class="product-info-card">
                        <h3 style="margin-bottom: 1rem; font-size: 1rem; color: var(--text-secondary);">
                            <i class="fas fa-info-circle"></i>
                            Informasi Produk
                        </h3>
                        <div class="info-item">
                            <span class="info-label">Penjual</span>
                            <span class="info-value"><?php echo htmlspecialchars($produk['nama_grosir']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status Stok</span>
                            <span class="info-value">
                                <?php 
                                $stock_class = 'high';
                                $stock_label = 'Tersedia';
                                $stock_icon = 'check-circle';
                                if ($produk['stok'] == 0) {
                                    $stock_class = 'out';
                                    $stock_label = 'Habis';
                                    $stock_icon = 'times-circle';
                                } elseif ($produk['stok'] < 10) {
                                    $stock_class = 'low';
                                    $stock_label = 'Stok Rendah';
                                    $stock_icon = 'exclamation-circle';
                                }
                                ?>
                                <span class="stock-status <?php echo $stock_class; ?>">
                                    <i class="fas fa-<?php echo $stock_icon; ?>"></i>
                                    <?php echo $stock_label; ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ID Produk</span>
                            <span class="info-value">#<?php echo $produk['produk_id']; ?></span>
                        </div>
                    </div>

                    <!-- Image Upload Section -->
                    <div class="image-upload-section">
                        <div class="form-header">
                            <h2>
                                <i class="fas fa-image"></i>
                                Gambar Produk
                            </h2>
                        </div>
                        <div class="current-image">
                            <?php if (!empty($produk['gambar_produk'])) { ?>
                                <img src="uploads/<?php echo htmlspecialchars($produk['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="image-preview" id="imagePreview">
                            <?php } else { ?>
                                <div class="no-image" id="imagePreview">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php } ?>
                            
                            <form method="POST" action="" enctype="multipart/form-data" id="imageForm">
                                <!-- Copy all other fields as hidden -->
                                <input type="hidden" name="nama_produk" value="<?php echo htmlspecialchars($produk['nama_produk']); ?>">
                                <input type="hidden" name="deskripsi_produk" value="<?php echo htmlspecialchars($produk['deskripsi_produk']); ?>">
                                <input type="hidden" name="harga_grosir" value="<?php echo htmlspecialchars($produk['harga_grosir']); ?>">
                                <input type="hidden" name="stok" value="<?php echo htmlspecialchars($produk['stok']); ?>">
                                <input type="hidden" name="kategori_id" value="<?php echo htmlspecialchars($produk['kategori_id']); ?>">
                                
                                <div class="file-input-wrapper">
                                    <input type="file" id="gambar_produk" name="gambar_produk" accept="image/*" onchange="previewImage(this)">
                                    <label for="gambar_produk" class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Pilih Gambar Baru</span>
                                    </label>
                                </div>
                                <div id="file_name" class="file-name"></div>
                                <div class="input-hint" style="margin-top: 1rem; text-align: center;">
                                    Format: JPG, PNG, GIF, WEBP<br>
                                    Maksimal: 5MB
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
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

        // Preview Image
        function previewImage(input) {
            const fileNameDiv = document.getElementById('file_name');
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.id = 'imagePreview';
                        preview.parentNode.replaceChild(img, preview);
                    }
                };
                
                reader.readAsDataURL(input.files[0]);
                
                fileNameDiv.textContent = '📁 ' + input.files[0].name;
                fileNameDiv.classList.add('show');
            }
        }

        // Confirm Delete
        function confirmDelete() {
            if (confirm('Apakah Anda yakin ingin menghapus produk ini?\n\nTindakan ini tidak dapat dibatalkan!')) {
                window.location.href = 'admin_delete_produk.php?id=<?php echo $produk_id; ?>';
            }
        }

        // Close sidebar on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Form validation
        document.querySelector('form:not(#imageForm)').addEventListener('submit', function(e) {
            const harga = parseFloat(document.getElementById('harga_grosir').value);
            const stok = parseInt(document.getElementById('stok').value);
            
            if (harga <= 0) {
                e.preventDefault();
                alert('Harga harus lebih dari 0!');
                return false;
            }
            
            if (stok < 0) {
                e.preventDefault();
                alert('Stok tidak boleh negatif!');
                return false;
            }
        });
    </script>
    <!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>