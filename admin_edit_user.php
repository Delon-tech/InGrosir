<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$user_id) {
    header("Location: admin_users.php");
    exit();
}

// Get user data
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt_user = mysqli_prepare($koneksi, $user_query);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($result_user);

if (!$user) {
    $_SESSION['error'] = "Pengguna tidak ditemukan!";
    header("Location: admin_users.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $peran = $_POST['peran'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    // Optional fields for penjual
    $nama_grosir = $peran == 'penjual' ? trim($_POST['nama_grosir']) : null;
    $alamat_grosir = $peran == 'penjual' ? trim($_POST['alamat_grosir']) : null;
    $nomor_telepon = $peran == 'penjual' ? trim($_POST['nomor_telepon']) : null;

    // Validate email uniqueness
    $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $stmt_check = mysqli_prepare($koneksi, $check_email);
    mysqli_stmt_bind_param($stmt_check, "si", $email, $user_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $error = "Email sudah digunakan oleh pengguna lain!";
    }
    mysqli_stmt_close($stmt_check);

    // Handle password update if provided
    $password_update = "";
    $bind_types = "sssii";
    $bind_params = [$nama_lengkap, $email, $peran, $is_admin, $user_id];
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_update = ", password = ?";
        $bind_types = "ssssii";
        array_splice($bind_params, 3, 0, [$password]);
    }

    if (!$error) {
        // Update user
        $update_query = "UPDATE users SET nama_lengkap = ?, email = ?, peran = ?, is_admin = ?" . $password_update;
        
        if ($peran == 'penjual') {
            $update_query .= ", nama_grosir = ?, alamat_grosir = ?, nomor_telepon = ?";
            $bind_types .= "sss";
            array_splice($bind_params, -1, 0, [$nama_grosir, $alamat_grosir, $nomor_telepon]);
        } else {
            $update_query .= ", nama_grosir = NULL, alamat_grosir = NULL, nomor_telepon = NULL";
        }
        
        $update_query .= " WHERE user_id = ?";
        
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($update_stmt, $bind_types, ...$bind_params);

        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION['success'] = "Data pengguna berhasil diperbarui!";
            header("Location: admin_users.php");
            exit();
        } else {
            $error = "Gagal memperbarui data: " . mysqli_error($koneksi);
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
    <title>Edit Pengguna - InGrosir Admin</title>
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
        
        /* Sidebar - Reuse */
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
        
        /* Form Container */
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
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
        input[type="email"],
        input[type="password"],
        input[type="tel"],
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
            min-height: 120px;
        }
        
        .input-hint {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-top: 0.375rem;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
            border: 2px solid var(--border);
            transition: var(--transition);
        }
        
        .checkbox-wrapper:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-wrapper label {
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            margin: 0;
            flex: 1;
        }
        
        .checkbox-info {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 0.25rem;
        }
        
        /* User Avatar */
        .user-avatar-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2.5rem;
            flex-shrink: 0;
        }
        
        .avatar-info h3 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        .avatar-info .user-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        .user-meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
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
            color: #3b82f6;
        }
        
        .badge.penjual {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        /* Section Divider */
        .section-divider {
            margin: 2rem 0;
            border-top: 2px solid var(--border);
            padding-top: 2rem;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
        }
        
        .section-title i {
            color: var(--primary);
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
        
        /* Penjual Fields */
        .penjual-fields {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .penjual-fields.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-body {
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .user-avatar-section {
                flex-direction: column;
                text-align: center;
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
                    <a href="admin_users.php">Kelola Pengguna</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Edit Pengguna</span>
                </div>
                <div class="page-title">
                    <h1>
                        <i class="fas fa-user-edit"></i>
                        Edit Pengguna
                    </h1>
                </div>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <div class="form-header">
                    <h2>
                        <i class="fas fa-edit"></i>
                        Edit Data Pengguna
                    </h2>
                </div>
                
                <div class="form-body">
                    <?php if ($error) { ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                    <?php } ?>
                    
                    <!-- User Avatar Section -->
                    <div class="user-avatar-section">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                        </div>
                        <div class="avatar-info">
                            <h3><?php echo htmlspecialchars($user['nama_lengkap']); ?></h3>
                            <span class="badge <?php echo $user['peran']; ?>">
                                <?php echo ucfirst($user['peran']); ?>
                            </span>
                            <div class="user-meta">
                                <div class="user-meta-item">
                                    <i class="fas fa-calendar"></i>
                                    Terdaftar: <?php echo date('d M Y', strtotime($user['tanggal_registrasi'])); ?>
                                </div>
                                <div class="user-meta-item">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <!-- Basic Information -->
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            Informasi Dasar
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nama_lengkap">
                                    Nama Lengkap <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="nama_lengkap" 
                                    name="nama_lengkap" 
                                    value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" 
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="email">
                                    Email <span class="required">*</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo htmlspecialchars($user['email']); ?>" 
                                    required
                                >
                                <div class="input-hint">Email harus unik dan valid</div>
                            </div>

                            <div class="form-group">
                                <label for="peran">
                                    Peran <span class="required">*</span>
                                </label>
                                <select id="peran" name="peran" required>
                                    <option value="pembeli" <?php echo $user['peran'] == 'pembeli' ? 'selected' : ''; ?>>Pembeli</option>
                                    <option value="penjual" <?php echo $user['peran'] == 'penjual' ? 'selected' : ''; ?>>Penjual</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="password">
                                    Password Baru (Opsional)
                                </label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Kosongkan jika tidak ingin mengubah"
                                >
                                <div class="input-hint">Minimal 8 karakter jika ingin mengubah</div>
                            </div>
                        </div>

                        <!-- Admin Privilege -->
                        <div class="form-group full-width">
                            <div class="checkbox-wrapper">
                                <input 
                                    type="checkbox" 
                                    id="is_admin" 
                                    name="is_admin" 
                                    <?php echo $user['is_admin'] == 1 ? 'checked' : ''; ?>
                                >
                                <label for="is_admin">
                                    <i class="fas fa-shield-halved"></i>
                                    Berikan Akses Administrator
                                    <span class="checkbox-info">Pengguna dengan akses admin dapat mengelola seluruh sistem</span>
                                </label>
                            </div>
                        </div>

                        <!-- Penjual Fields -->
                        <div id="penjual_fields" class="penjual-fields <?php echo $user['peran'] == 'penjual' ? 'active' : ''; ?>">
                            <div class="section-divider">
                                <div class="section-title">
                                    <i class="fas fa-store"></i>
                                    Informasi Toko (Khusus Penjual)
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="nama_grosir">
                                            Nama Toko/Usaha
                                        </label>
                                        <input 
                                            type="text" 
                                            id="nama_grosir" 
                                            name="nama_grosir" 
                                            value="<?php echo htmlspecialchars($user['nama_grosir'] ?? ''); ?>"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="nomor_telepon">
                                            Nomor Telepon
                                        </label>
                                        <input 
                                            type="tel" 
                                            id="nomor_telepon" 
                                            name="nomor_telepon" 
                                            value="<?php echo htmlspecialchars($user['nomor_telepon'] ?? ''); ?>"
                                            placeholder="08xxxxxxxxxx"
                                        >
                                    </div>

                                    <div class="form-group full-width">
                                        <label for="alamat_grosir">
                                            Alamat Lengkap Toko
                                        </label>
                                        <textarea 
                                            id="alamat_grosir" 
                                            name="alamat_grosir"
                                        ><?php echo htmlspecialchars($user['alamat_grosir'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Simpan Perubahan
                            </button>
                            <a href="admin_users.php" class="btn btn-outline">
                                <i class="fas fa-times"></i>
                                Batal
                            </a>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete()" style="margin-left: auto;">
                                <i class="fas fa-trash"></i>
                                Hapus Pengguna
                            </button>
                        </div>
                    </form>
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

        // Toggle penjual fields based on role
        const peranSelect = document.getElementById('peran');
        const penjualFields = document.getElementById('penjual_fields');
        
        peranSelect.addEventListener('change', function() {
            if (this.value === 'penjual') {
                penjualFields.classList.add('active');
            } else {
                penjualFields.classList.remove('active');
            }
        });

        // Confirm delete
        function confirmDelete() {
            if (confirm('Apakah Anda yakin ingin menghapus pengguna ini?\n\nTindakan ini tidak dapat dibatalkan!')) {
                window.location.href = 'admin_delete_user.php?id=<?php echo $user_id; ?>';
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password && password.length < 8) {
                e.preventDefault();
                alert('Password minimal 8 karakter!');
                return false;
            }
        });

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
    </script>
    <!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>