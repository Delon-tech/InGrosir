<?php
session_start();
include 'config/koneksi.php';
include 'config/helpers.php';

$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$produk_id = isset($_GET['id']) ? $_GET['id'] : null;
$error = '';
$produk = [];

$csrf_token = generateCsrfToken();

if (!$produk_id) {
    header("Location: produk_list.php");
    exit();
}

$produk_query = "SELECT * FROM produk WHERE produk_id = ? AND user_id = ?";
$stmt_produk_data = mysqli_prepare($koneksi, $produk_query);
mysqli_stmt_bind_param($stmt_produk_data, "ii", $produk_id, $user_id);
mysqli_stmt_execute($stmt_produk_data);
$result_produk_data = mysqli_stmt_get_result($stmt_produk_data);
$produk = mysqli_fetch_assoc($result_produk_data);

if (!$produk) {
    header("Location: produk_list.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Terjadi kesalahan keamanan. Silakan coba lagi.";
    } else {
        $nama_produk = $_POST['nama_produk'];
        $deskripsi_produk = $_POST['deskripsi_produk'];
        $harga_grosir = $_POST['harga_grosir'];
        $stok = $_POST['stok'];
        $kategori_id = $_POST['kategori_id'];
        $gambar_produk = $produk['gambar_produk'];

        if (isset($_FILES['gambar_produk']) && $_FILES['gambar_produk']['error'] == 0) {
            $target_dir = "uploads/";
            $new_filename = uniqid() . '.jpg';
            $destination_path = $target_dir . $new_filename;
            
            $source_path = $_FILES['gambar_produk']['tmp_name'];
            $image_info = getimagesize($source_path);
            if ($image_info) {
                $image_width = $image_info[0];
                $image_height = $image_info[1];
                $new_width = 800;
                $new_height = ($image_height / $image_width) * $new_width;
                $temp_image = imagecreatetruecolor($new_width, $new_height);
                $source_image = imagecreatefromstring(file_get_contents($source_path));
                imagecopyresampled($temp_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $image_width, $image_height);
                
                imagejpeg($temp_image, $destination_path, 80);
                imagedestroy($temp_image);
                imagedestroy($source_image);

                if (!empty($produk['gambar_produk']) && file_exists($target_dir . $produk['gambar_produk'])) {
                    unlink($target_dir . $produk['gambar_produk']);
                }
                $gambar_produk = $new_filename;
            }
        }
        
        $update_query = "UPDATE produk SET nama_produk = ?, deskripsi_produk = ?, harga_grosir = ?, stok = ?, kategori_id = ?, gambar_produk = ? WHERE produk_id = ?";
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ssdiisi", $nama_produk, $deskripsi_produk, $harga_grosir, $stok, $kategori_id, $gambar_produk, $produk_id);

        if ($stok < 10 && $stok > 0) {
            require_once 'includes/notification_helper.php';
            kirim_notifikasi(
                $_SESSION['user_id'], 
                '⚠️ Stok Produk Menipis!', 
                "Stok produk '$nama_produk' tinggal $stok unit. Segera restock!",
                "produk_edit.php?id=$produk_id",
                'exclamation-triangle'
            );
        }
        
        if (mysqli_stmt_execute($update_stmt)) {
            header("Location: produk_list.php?success=updated");
            exit();
        } else {
            $error = "Gagal mengupdate produk.";
        }
    }
}

$kategori_query = "SELECT * FROM kategori_produk";
$kategori_result = mysqli_query($koneksi, $kategori_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk - InGrosir</title>
    
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
        
        /* Sidebar Styles - SAMA DENGAN DASHBOARD */
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
        
        /* Page Header dengan Bootstrap */
        .page-header {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            padding: 2rem;
        }
        
        /* Form Container */
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 900px;
        }
        
        /* Image Preview */
        .image-preview-container {
            position: relative;
            width: 200px;
            height: 200px;
            border: 2px dashed #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            color: #6b7280;
        }
        
        /* File Input Custom */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            padding: 0.75rem 1.5rem;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .file-input-label:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
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
        }
    </style>
</head>
<body>
    <!-- Sidebar - SAMA DENGAN DASHBOARD -->
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
        <!-- Page Header dengan Bootstrap -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <h1 class="h3 mb-2 fw-bold">
                        <i class="fas fa-edit text-primary"></i> Edit Produk
                    </h1>
                    <p class="text-muted mb-0">Perbarui informasi produk Anda</p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-md-end">
                        <a href="produk_list.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <!-- Form Container -->
        <div class="form-container">
            <form action="produk_edit.php?id=<?php echo $produk_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-4">
                    <!-- Nama Produk -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Nama Produk <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama_produk" class="form-control" 
                               value="<?php echo htmlspecialchars($produk['nama_produk']); ?>" 
                               placeholder="Contoh: Beras Premium 25kg" required>
                        <div class="form-text">Masukkan nama produk yang jelas dan deskriptif</div>
                    </div>

                    <!-- Kategori -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Kategori Produk <span class="text-danger">*</span>
                        </label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php 
                            mysqli_data_seek($kategori_result, 0);
                            while ($kategori = mysqli_fetch_assoc($kategori_result)) { 
                            ?>
                                <option value="<?php echo $kategori['kategori_id']; ?>" 
                                        <?php echo ($kategori['kategori_id'] == $produk['kategori_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Deskripsi -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Deskripsi Produk <span class="text-danger">*</span>
                        </label>
                        <textarea name="deskripsi_produk" class="form-control" rows="5"
                                  placeholder="Jelaskan detail produk, keunggulan, spesifikasi, dll." 
                                  required><?php echo htmlspecialchars($produk['deskripsi_produk']); ?></textarea>
                        <div class="form-text">Berikan deskripsi lengkap untuk menarik pembeli</div>
                    </div>

                    <!-- Harga & Stok -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Harga Grosir (Rp) <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="harga_grosir" class="form-control" 
                               value="<?php echo $produk['harga_grosir']; ?>" 
                               placeholder="150000" step="0.01" required>
                        <div class="form-text">Harga per unit/paket</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Stok Tersedia <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="stok" class="form-control" 
                               value="<?php echo $produk['stok']; ?>" 
                               placeholder="100" required>
                        <div class="form-text">Jumlah stok yang tersedia</div>
                    </div>

                    <!-- Gambar Produk -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Gambar Produk</label>
                        
                        <div class="image-preview-container">
                            <?php if (!empty($produk['gambar_produk'])) { ?>
                                <img src="uploads/<?php echo htmlspecialchars($produk['gambar_produk']); ?>" 
                                     alt="Preview" class="image-preview" id="imagePreview">
                            <?php } else { ?>
                                <div class="image-preview-placeholder" id="imagePreview">
                                    <i class="fas fa-image" style="font-size: 3rem;"></i>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="file-input-wrapper">
                            <input type="file" name="gambar_produk" id="gambar_produk" accept="image/*" 
                                   onchange="previewImage(this)">
                            <label for="gambar_produk" class="file-input-label">
                                <i class="fas fa-upload"></i>
                                Pilih Gambar Baru
                            </label>
                        </div>
                        <div class="form-text">Format: JPG, PNG. Maksimal 5MB. Kosongkan jika tidak ingin mengganti gambar.</div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex gap-2 mt-4 pt-4 border-top">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                    <a href="produk_list.php" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </form>
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

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="image-preview">';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
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