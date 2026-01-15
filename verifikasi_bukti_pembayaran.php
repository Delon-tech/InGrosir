<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

// Cek login penjual
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pesanan_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$pesanan_id) {
    header("Location: pesanan.php?error=" . urlencode("ID pesanan tidak valid"));
    exit();
}

// Ambil data pesanan
$query_pesanan = "SELECT p.*, u.nama_lengkap, u.email, u.nomor_telepon,
                  bp.*, 
                  mpp.account_number, mpp.account_name, mpp.bank_name,
                  mpt.nama_metode
                  FROM pesanan p
                  JOIN users u ON p.user_id_pembeli = u.user_id
                  LEFT JOIN bukti_pembayaran bp ON p.payment_proof_id = bp.bukti_id
                  LEFT JOIN metode_pembayaran_penjual mpp ON p.metode_penjual_id = mpp.metode_penjual_id
                  LEFT JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                  WHERE p.pesanan_id = ? AND p.user_id_penjual = ?";
$stmt = mysqli_prepare($koneksi, $query_pesanan);
mysqli_stmt_bind_param($stmt, "ii", $pesanan_id, $user_id);
mysqli_stmt_execute($stmt);
$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$pesanan) {
    header("Location: pesanan.php?error=" . urlencode("Pesanan tidak ditemukan"));
    exit();
}

// Cek apakah ada bukti pembayaran
if (empty($pesanan['bukti_id'])) {
    header("Location: detail_pesanan.php?id=$pesanan_id&error=" . urlencode("Belum ada bukti pembayaran"));
    exit();
}

$success = '';
$error = '';

// Proses verifikasi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_payment'])) {
        mysqli_begin_transaction($koneksi);
        try {
            // Update bukti pembayaran
            $query_bukti = "UPDATE bukti_pembayaran 
                           SET status_verifikasi = 'verified',
                               verified_by = ?,
                               verified_at = NOW()
                           WHERE bukti_id = ?";
            $stmt_bukti = mysqli_prepare($koneksi, $query_bukti);
            mysqli_stmt_bind_param($stmt_bukti, "ii", $user_id, $pesanan['bukti_id']);
            mysqli_stmt_execute($stmt_bukti);
            
            // Update status pesanan
            $query_update = "UPDATE pesanan 
                            SET payment_status = 'payment_verified',
                                payment_verified_at = NOW(),
                                status_pesanan = 'diproses'
                            WHERE pesanan_id = ?";
            $stmt_update = mysqli_prepare($koneksi, $query_update);
            mysqli_stmt_bind_param($stmt_update, "i", $pesanan_id);
            mysqli_stmt_execute($stmt_update);
            
            // Log payment
            $query_log = "INSERT INTO payment_log (pesanan_id, bukti_id, action_type, old_status, new_status, action_by, notes)
                         VALUES (?, ?, 'verify', 'payment_uploaded', 'payment_verified', ?, 'Pembayaran telah diverifikasi')";
            $stmt_log = mysqli_prepare($koneksi, $query_log);
            mysqli_stmt_bind_param($stmt_log, "iii", $pesanan_id, $pesanan['bukti_id'], $user_id);
            mysqli_stmt_execute($stmt_log);
            
            mysqli_commit($koneksi);
            
            $success = "✅ Pembayaran berhasil diverifikasi! Pesanan akan diproses.";
            header("refresh:2;url=detail_pesanan.php?id=$pesanan_id");
            
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error = "Gagal verifikasi pembayaran: " . $e->getMessage();
        }
    } 
    elseif (isset($_POST['reject_payment'])) {
        $alasan_reject = trim($_POST['alasan_reject']);
        
        if (empty($alasan_reject)) {
            $error = "Alasan penolakan harus diisi!";
        } else {
            mysqli_begin_transaction($koneksi);
            try {
                // Update bukti pembayaran
                $query_bukti = "UPDATE bukti_pembayaran 
                               SET status_verifikasi = 'rejected',
                                   alasan_reject = ?,
                                   verified_by = ?,
                                   verified_at = NOW()
                               WHERE bukti_id = ?";
                $stmt_bukti = mysqli_prepare($koneksi, $query_bukti);
                mysqli_stmt_bind_param($stmt_bukti, "sii", $alasan_reject, $user_id, $pesanan['bukti_id']);
                mysqli_stmt_execute($stmt_bukti);
                
                // Update status pesanan
                $query_update = "UPDATE pesanan 
                                SET payment_status = 'payment_rejected'
                                WHERE pesanan_id = ?";
                $stmt_update = mysqli_prepare($koneksi, $query_update);
                mysqli_stmt_bind_param($stmt_update, "i", $pesanan_id);
                mysqli_stmt_execute($stmt_update);
                
                // Log payment
                $query_log = "INSERT INTO payment_log (pesanan_id, bukti_id, action_type, old_status, new_status, action_by, notes)
                             VALUES (?, ?, 'reject', 'payment_uploaded', 'payment_rejected', ?, ?)";
                $stmt_log = mysqli_prepare($koneksi, $query_log);
                mysqli_stmt_bind_param($stmt_log, "iiis", $pesanan_id, $pesanan['bukti_id'], $user_id, $alasan_reject);
                mysqli_stmt_execute($stmt_log);
                
                mysqli_commit($koneksi);
                
                $success = "❌ Bukti pembayaran ditolak. Pembeli akan menerima notifikasi.";
                header("refresh:2;url=detail_pesanan.php?id=$pesanan_id");
                
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = "Gagal menolak pembayaran: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Bukti Pembayaran - InGrosir</title>
    
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
        
        /* Sidebar - SAMA DENGAN DASHBOARD */
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
        
        /* Card Enhancement */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Bukti Image */
        .bukti-image-container {
            text-align: center;
        }
        
        .bukti-image {
            max-width: 100%;
            max-height: 600px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }
        
        .bukti-image:hover {
            transform: scale(1.02);
        }
        
        /* Info Item */
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }
        
        .info-value.amount {
            font-size: 1.5rem;
            color: var(--secondary);
        }
        
        /* Comparison Box */
        .comparison-box {
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid var(--warning);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .match-indicator {
            text-align: center;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 700;
            margin-top: 0.5rem;
        }
        
        .match-indicator.match {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }
        
        .match-indicator.not-match {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            align-items: center;
            justify-content: center;
        }
        
        .image-modal.active {
            display: flex;
        }
        
        .image-modal img {
            max-width: 95%;
            max-height: 95vh;
            object-fit: contain;
        }
        
        .image-modal-close {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: white;
            color: #1f2937;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
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
            <a href="produk_list.php" class="menu-item">
                <i class="fas fa-box"></i>
                <span>Kelola Produk</span>
            </a>
            <a href="pesanan.php" class="menu-item active">
                <i class="fas fa-shopping-cart"></i>
                <span>Kelola Pesanan</span>
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
                        <i class="fas fa-file-invoice-dollar text-primary"></i> Verifikasi Bukti Pembayaran
                    </h1>
                    <p class="text-muted mb-0">Pesanan #<?php echo $pesanan_id; ?></p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-md-end">
                        <a href="detail_pesanan.php?id=<?php echo $pesanan_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
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

        <div class="row g-4">
            <!-- Left Column: Bukti Image -->
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-image text-primary"></i>
                            Bukti Transfer
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="bukti-image-container">
                            <img src="<?php echo htmlspecialchars($pesanan['bukti_image']); ?>" 
                                 alt="Bukti Pembayaran" 
                                 class="bukti-image mb-3"
                                 onclick="openImageModal(this.src)">
                            
                            <div class="d-flex gap-2 justify-content-center">
                                <button class="btn btn-outline-primary" onclick="openImageModal('<?php echo htmlspecialchars($pesanan['bukti_image']); ?>')">
                                    <i class="fas fa-expand me-2"></i>Perbesar
                                </button>
                                <a href="<?php echo htmlspecialchars($pesanan['bukti_image']); ?>" download class="btn btn-outline-secondary">
                                    <i class="fas fa-download me-2"></i>Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Info & Actions -->
            <div class="col-12 col-lg-4">
                <!-- Customer Info -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-user text-primary"></i>
                            Informasi Pembeli
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Nama Lengkap</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pesanan['nama_lengkap']); ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pesanan['email']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transfer Info -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-exchange-alt text-success"></i>
                            Detail Transfer
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Nama Pengirim</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pesanan['nama_pengirim']); ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Bank Pengirim</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pesanan['bank_pengirim'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($pesanan['nomor_rekening_pengirim'])) { ?>
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Nomor Rekening</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pesanan['nomor_rekening_pengirim']); ?></div>
                                </div>
                            </div>
                            <?php } ?>
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Tanggal Transfer</div>
                                    <div class="info-value">
                                        <?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_transfer'])); ?> WIB
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Jumlah Transfer</div>
                                    <div class="info-value amount">
                                        Rp <?php echo number_format($pesanan['jumlah_transfer'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($pesanan['catatan'])) { ?>
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Catatan</div>
                                    <div class="info-value" style="font-style: italic; font-weight: 400;">
                                        "<?php echo htmlspecialchars($pesanan['catatan']); ?>"
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                        <!-- Comparison -->
                        <div class="comparison-box">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Pesanan:</span>
                                <span class="fw-bold">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Jumlah Transfer:</span>
                                <span class="fw-bold">Rp <?php echo number_format($pesanan['jumlah_transfer'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Selisih:</span>
                                <span class="fw-bold" style="color: <?php echo ($pesanan['jumlah_transfer'] >= $pesanan['total_harga']) ? 'var(--secondary)' : 'var(--danger)'; ?>">
                                    Rp <?php echo number_format(abs($pesanan['jumlah_transfer'] - $pesanan['total_harga']), 0, ',', '.'); ?>
                                </span>
                            </div>
                            
                            <?php if ($pesanan['jumlah_transfer'] >= $pesanan['total_harga']) { ?>
                                <div class="match-indicator match">
                                    ✅ Nominal Sesuai
                                </div>
                            <?php } else { ?>
                                <div class="match-indicator not-match">
                                    ⚠️ Nominal Kurang Rp <?php echo number_format($pesanan['total_harga'] - $pesanan['jumlah_transfer'], 0, ',', '.'); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <?php if ($pesanan['status_verifikasi'] == 'pending') { ?>
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-tasks text-warning"></i>
                            Aksi Verifikasi
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="d-grid gap-2">
                                <button type="submit" name="verify_payment" class="btn btn-success btn-lg"
                                        onclick="return confirm('✅ Yakin ingin MENERIMA bukti pembayaran ini?')">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Terima & Proses
                                </button>
                                <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle me-2"></i>
                                    Tolak Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php } else { ?>
                <div class="card">
                    <div class="card-body text-center p-4">
                        <?php if ($pesanan['status_verifikasi'] == 'verified') { ?>
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h4 class="text-success mb-2">Sudah Diverifikasi</h4>
                            <p class="text-muted">
                            Diverifikasi pada <?php echo date('d M Y, H:i', strtotime($pesanan['verified_at'])); ?>
                        </p>
                    <?php } elseif ($pesanan['status_verifikasi'] == 'rejected') { ?>
                        <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                        <h4 class="text-danger mb-2">Bukti Ditolak</h4>
                        <p class="text-muted mb-0">
                            <strong>Alasan:</strong> <?php echo htmlspecialchars($pesanan['alasan_reject']); ?>
                        </p>
                        <p class="small text-muted mt-1">
                            Ditolak pada <?php echo date('d M Y, H:i', strtotime($pesanan['verified_at'])); ?>
                        </p>
                    <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div> </div> </main>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Tolak Bukti Pembayaran
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Alasan Penolakan <span class="text-danger">*</span></label>
                            <textarea name="alasan_reject" class="form-control" rows="3" required 
                                      placeholder="Contoh: Nominal transfer tidak sesuai, foto buram, atau rekening tujuan salah..."></textarea>
                            <div class="form-text text-muted">
                                Alasan ini akan dikirimkan kepada pembeli agar mereka dapat memperbaiki pembayaran.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="reject_payment" class="btn btn-danger">
                            <i class="fas fa-times-circle me-2"></i>Ya, Tolak Bukti
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <button class="image-modal-close" onclick="closeImageModal()">
            <i class="fas fa-times"></i>
        </button>
        <img id="modalImage" src="" alt="Bukti Pembayaran Fullscreen">
    </div>

    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar Toggle Logic
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Image Modal Logic
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('active');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        // Close image modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Close sidebar when clicking outside (Mobile view)
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