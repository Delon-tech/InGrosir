<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'activate':
                $template_id = intval($_POST['template_id']);
                $account_number = $_POST['account_number'] ?? null;
                $account_name = $_POST['account_name'] ?? null;
                $bank_name = $_POST['bank_name'] ?? null;
                $notes = $_POST['notes'] ?? null;
                
                // Handle file upload untuk QRIS
                $qr_image = null;
                if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] == 0) {
                    $upload_dir = 'uploads/qris/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    $ext = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'qris_' . $user_id . '_' . time() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $target)) {
                        $qr_image = $filename;
                    }
                }
                
                // Insert atau update
                $query = "INSERT INTO metode_pembayaran_penjual 
                          (user_id, template_id, is_active, account_number, account_name, bank_name, qr_image, notes)
                          VALUES (?, ?, 1, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          is_active = 1, 
                          account_number = VALUES(account_number),
                          account_name = VALUES(account_name),
                          bank_name = VALUES(bank_name),
                          qr_image = IF(VALUES(qr_image) IS NOT NULL, VALUES(qr_image), qr_image),
                          notes = VALUES(notes)";
                
                $stmt = mysqli_prepare($koneksi, $query);
                mysqli_stmt_bind_param($stmt, "iisssss", $user_id, $template_id, $account_number, $account_name, $bank_name, $qr_image, $notes);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Metode pembayaran berhasil diaktifkan!";
                } else {
                    $error = "Gagal mengaktifkan metode pembayaran.";
                }
                break;
                
            case 'deactivate':
                $metode_penjual_id = intval($_POST['metode_penjual_id']);
                $query = "UPDATE metode_pembayaran_penjual SET is_active = 0 WHERE metode_penjual_id = ? AND user_id = ?";
                $stmt = mysqli_prepare($koneksi, $query);
                mysqli_stmt_bind_param($stmt, "ii", $metode_penjual_id, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Metode pembayaran berhasil dinonaktifkan!";
                } else {
                    $error = "Gagal menonaktifkan metode pembayaran.";
                }
                break;
        }
    }
}

// Ambil semua template metode pembayaran
$query_templates = "SELECT * FROM metode_pembayaran_template WHERE is_active = 1 ORDER BY sort_order, nama_metode";
$templates = mysqli_query($koneksi, $query_templates);

// Ambil metode yang sudah diaktifkan penjual
$query_active = "SELECT mpp.*, mpt.nama_metode, mpt.tipe_metode, mpt.icon, mpt.requires_account_number, mpt.requires_account_name, mpt.requires_image
                 FROM metode_pembayaran_penjual mpp
                 JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                 WHERE mpp.user_id = ?";
$stmt_active = mysqli_prepare($koneksi, $query_active);
mysqli_stmt_bind_param($stmt_active, "i", $user_id);
mysqli_stmt_execute($stmt_active);
$active_methods = mysqli_stmt_get_result($stmt_active);

$active_array = [];
while ($row = mysqli_fetch_assoc($active_methods)) {
    $active_array[$row['template_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Metode Pembayaran - InGrosir</title>
    
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
            padding: 2rem;
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
        
        /* Alert */
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
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
        
        /* Method Cards */
        .method-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .method-card.active {
            border-color: var(--secondary);
            background: linear-gradient(to bottom, #ecfdf5 0%, white 100%);
        }
        
        .method-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .method-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .method-icon {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .method-card.active .method-icon {
            background: var(--secondary);
            color: white;
        }
        
        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }
        
        .status-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .method-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .method-body h3 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .method-body p {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .method-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .qr-preview {
            width: 100%;
            max-width: 200px;
            margin: 1rem auto;
            display: block;
            border-radius: 12px;
            border: 2px solid #e9ecef;
        }
        
        /* Modal */
        .modal-content {
            border-radius: 16px;
            border: none;
        }
        
        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .file-upload {
            border: 2px dashed #e9ecef;
            padding: 2rem;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.02);
        }
        
        .file-upload input {
            display: none;
        }
        
        .file-upload i {
            font-size: 3rem;
            color: var(--primary);
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
            <a href="kelola_metode_pembayaran.php" class="menu-item active">
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
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-credit-card me-2"></i>Kelola Metode Pembayaran</h1>
                <p>Aktifkan metode pembayaran yang tersedia di toko Anda</p>
            </div>
        </div>
        
        <?php if ($success) { ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php } ?>
        
        <?php if ($error) { ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php } ?>
        
        <!-- Methods Grid -->
        <div class="row g-4">
            <?php 
            mysqli_data_seek($templates, 0);
            while ($template = mysqli_fetch_assoc($templates)) { 
                $is_active = isset($active_array[$template['template_id']]);
                $active_data = $is_active ? $active_array[$template['template_id']] : null;
                $is_enabled = $is_active && $active_data['is_active'];
            ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="method-card <?php echo $is_enabled ? 'active' : ''; ?>">
                        <div class="method-header">
                            <div class="method-icon">
                                <i class="fas <?php echo htmlspecialchars($template['icon']); ?>"></i>
                            </div>
                            <span class="status-badge <?php echo $is_enabled ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $is_enabled ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </div>
                        
                        <div class="method-body">
                            <h3><?php echo htmlspecialchars($template['nama_metode']); ?></h3>
                            <p><?php echo htmlspecialchars($template['deskripsi']); ?></p>
                            
                            <?php if ($is_enabled) { ?>
                                <div class="method-details">
                                    <?php if ($active_data['account_number']) { ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Nomor Akun</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($active_data['account_number']); ?></span>
                                        </div>
                                    <?php } ?>
                                    
                                    <?php if ($active_data['account_name']) { ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Nama Akun</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($active_data['account_name']); ?></span>
                                        </div>
                                    <?php } ?>
                                    
                                    <?php if ($active_data['bank_name']) { ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Bank</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($active_data['bank_name']); ?></span>
                                        </div>
                                    <?php } ?>
                                    
                                    <?php if ($active_data['qr_image']) { ?>
                                        <img src="uploads/qris/<?php echo htmlspecialchars($active_data['qr_image']); ?>" 
                                             alt="QR Code" class="qr-preview">
                                    <?php } ?>
                                </div>
                                
                                <div class="d-flex gap-2 mt-auto">
                                    <button type="button" class="btn btn-outline-primary flex-fill" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modal_<?php echo $template['template_id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="flex-fill">
                                        <input type="hidden" name="action" value="deactivate">
                                        <input type="hidden" name="metode_penjual_id" value="<?php echo $active_data['metode_penjual_id']; ?>">
                                        <button type="submit" class="btn btn-danger w-100" 
                                                onclick="return confirm('Yakin ingin menonaktifkan metode ini?')">
                                            <i class="fas fa-times"></i> Nonaktifkan
                                        </button>
                                    </form>
                                </div>
                            <?php } else { ?>
                                <button type="button" class="btn btn-primary w-100 mt-auto" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modal_<?php echo $template['template_id']; ?>">
                                    <i class="fas fa-plus"></i> Aktifkan Metode Ini
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </main>
    
    <!-- Modals untuk setiap template -->
    <?php 
    mysqli_data_seek($templates, 0);
    while ($template = mysqli_fetch_assoc($templates)) { 
        $active_data = $active_array[$template['template_id']] ?? null;
    ?>
        <div class="modal fade" id="modal_<?php echo $template['template_id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo htmlspecialchars($template['nama_metode']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="template_id" value="<?php echo $template['template_id']; ?>">
                            
                            <?php if ($template['requires_account_number']) { ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <?php echo $template['tipe_metode'] == 'transfer_bank' ? 'Nomor Rekening' : 'Nomor E-Wallet'; ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="account_number" class="form-control"
                                           value="<?php echo $active_data['account_number'] ?? ''; ?>" 
                                           placeholder="Masukkan nomor akun" required>
                                </div>
                            <?php } ?>
                            
                            <?php if ($template['requires_account_name']) { ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        Nama Pemilik Akun
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="account_name" class="form-control"
                                           value="<?php echo $active_data['account_name'] ?? ''; ?>" 
                                           placeholder="Masukkan nama pemilik akun" required>
                                </div>
                            <?php } ?>
                            
                            <?php if ($template['tipe_metode'] == 'transfer_bank') { ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        Nama Bank
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="bank_name" class="form-control"
                                           value="<?php echo $active_data['bank_name'] ?? ''; ?>" 
                                           placeholder="Contoh: Bank BRI" required>
                                </div>
                            <?php } ?>
                            
                            <?php if ($template['requires_image']) { ?>
                                <div class="mb-3">
                                    <label class="form-label">Upload QR Code</label>
                                    <?php if ($active_data && $active_data['qr_image']) { ?>
                                        <img src="uploads/qris/<?php echo htmlspecialchars($active_data['qr_image']); ?>" 
                                             class="qr-preview mb-3">
                                    <?php } ?>
                                    <label class="file-upload">
                                        <input type="file" name="qr_image" accept="image/*">
                                        <i class="fas fa-cloud-upload-alt d-block"></i>
                                        <p class="mb-0">Klik untuk upload QR Code</p>
                                        <small class="text-muted">Format: JPG, PNG (Max 2MB)</small>
                                    </label>
                                </div>
                            <?php } ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Catatan tambahan untuk pembeli"><?php echo $active_data['notes'] ?? ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Metode Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php } ?>

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