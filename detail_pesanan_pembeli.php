<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pesanan_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$pesanan_id) {
    header("Location: riwayat_pesanan.php");
    exit();
}

// Query pesanan dengan info penjual
$query_pesanan = "SELECT p.*, u.nama_grosir, u.alamat_grosir, u.nomor_telepon,
                  mpp.account_number, mpp.account_name, mpp.bank_name, mpp.qr_image, mpp.notes as payment_notes,
                  mpt.nama_metode, mpt.deskripsi as deskripsi_metode, mpt.tipe_metode, mpt.icon,
                  ap.label_alamat, ap.nama_penerima, ap.nomor_telepon as telp_penerima, 
                  ap.alamat_lengkap, ap.kelurahan, ap.kecamatan, ap.kota, ap.provinsi, ap.kode_pos,
                  v.kode_voucher as voucher_code_full, v.tipe_diskon, v.nilai_diskon, v.deskripsi as voucher_desc
                  FROM pesanan p 
                  JOIN users u ON p.user_id_penjual = u.user_id 
                  LEFT JOIN metode_pembayaran_penjual mpp ON p.metode_penjual_id = mpp.metode_penjual_id
                  LEFT JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                  LEFT JOIN alamat_pengiriman ap ON p.alamat_id = ap.alamat_id
                  LEFT JOIN voucher_diskon v ON p.voucher_id = v.voucher_id
                  WHERE p.pesanan_id = ? AND p.user_id_pembeli = ?";
$stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
mysqli_stmt_bind_param($stmt_pesanan, "ii", $pesanan_id, $user_id);
mysqli_stmt_execute($stmt_pesanan);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan);
$pesanan = mysqli_fetch_assoc($result_pesanan);

if (!$pesanan) {
    header("Location: riwayat_pesanan.php?error=" . urlencode("Pesanan tidak ditemukan"));
    exit();
}

// Query detail pesanan dengan info produk dan cek ulasan
$query_detail = "SELECT dp.*, p.nama_produk, p.produk_id, p.gambar_produk,
                 (SELECT COUNT(*) FROM ulasan_produk up WHERE up.produk_id = p.produk_id AND up.user_id_pembeli = ?) as sudah_ulasan
                 FROM detail_pesanan dp 
                 JOIN produk p ON dp.produk_id = p.produk_id 
                 WHERE dp.pesanan_id = ?";
$stmt_detail = mysqli_prepare($koneksi, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "ii", $user_id, $pesanan_id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $pesanan_id; ?> - InGrosir</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-white: #ffffff;
            --bg-gray: #f9fafb;
            --border: #e5e7eb;
            
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 300ms ease;
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
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }
        
        .logo {
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            width: fit-content;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .status-diproses {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .status-dikirim {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent);
        }
        
        .status-selesai {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-dibatalkan {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Cancelled Alert */
        .cancelled-alert {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid var(--danger);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }
        
        .cancelled-alert-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--danger);
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1rem;
        }
        
        .cancelled-alert-content {
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .cancelled-alert-content strong {
            color: var(--danger);
        }
        
        .cancelled-reason {
            background: white;
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
            border-left: 4px solid var(--danger);
        }
        
        .reason-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .reason-text {
            color: var(--text-primary);
            font-style: italic;
        }
        
        /* Order Status Timeline */
        .status-timeline {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .timeline-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-top: 2rem;
        }
        
        .timeline-container::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }
        
        .timeline-container.cancelled::before {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .timeline-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .timeline-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--border);
            margin: 0 auto 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text-secondary);
            transition: var(--transition);
        }
        
        .timeline-step.completed .timeline-circle {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }
        
        .timeline-step.active .timeline-circle {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
            animation: pulse 2s infinite;
        }
        
        .timeline-step.cancelled .timeline-circle {
            border-color: var(--danger);
            background: var(--danger);
            color: white;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
        }
        
        .timeline-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .timeline-step.completed .timeline-label,
        .timeline-step.active .timeline-label,
        .timeline-step.cancelled .timeline-label {
            color: var(--text-primary);
        }
        
        /* Info Card */
        .info-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* Products Table */
        .products-table {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            background: var(--bg-gray);
            border-bottom: 1px solid var(--border);
        }
        
        .table-header h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            margin: 0;
        }
        
        .product-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            border-radius: var(--radius);
            object-fit: cover;
            background: var(--bg-gray);
            flex-shrink: 0;
        }
        
        .product-content {
            flex: 1;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .product-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }
        
        .product-price {
            text-align: right;
            flex-shrink: 0;
        }
        
        .product-price .price {
            font-weight: 700;
            color: var(--success);
            font-size: 1.125rem;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .product-price .subtotal-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .product-actions {
            display: flex;
            justify-content: flex-end;
            padding: 1.5rem;
            background: var(--bg-gray);
        }
        
        /* Review Button */
        .review-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
            margin-top: 0.75rem;
        }
        
        .review-btn-primary {
            background: linear-gradient(135deg, var(--warning), #ff9500);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .review-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            color: white;
        }
        
        .review-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.75rem;
        }
        
        .badge-reviewed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        /* Sidebar Summary */
        .sidebar-summary {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            position: sticky;
            top: 100px;
        }
        
        .sidebar-summary h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            font-size: 0.875rem;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--border);
            margin-top: 1rem;
            padding-top: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .summary-row.total .amount {
            color: var(--success);
        }
        
        .summary-row.total.cancelled .amount {
            color: var(--danger);
            text-decoration: line-through;
        }
        
        .seller-info {
            background: var(--bg-gray);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-top: 1.5rem;
        }
        
        .seller-info h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .seller-detail {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }
        
        .seller-detail i {
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-gray);
            color: var(--text-primary);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-info {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .mobile-toggle span {
            width: 24px;
            height: 2.5px;
            background: white;
            border-radius: 2px;
            transition: var(--transition);
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar-summary {
                position: static;
                order: -1;
                margin-bottom: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                padding: 0.75rem 0;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .nav {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                flex-direction: column;
                padding: 1rem;
                box-shadow: var(--shadow-lg);
            }
            
            .nav.active {
                display: flex;
            }
            
            .nav a {
                width: 100%;
                text-align: left;
            }
            
            .mobile-toggle {
                display: flex;
            }
            
            .timeline-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .timeline-container::before {
                display: none;
            }
            
            .timeline-step {
                display: flex;
                align-items: center;
                gap: 1rem;
                text-align: left;
            }
            
            .timeline-circle {
                margin: 0;
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
            
            .product-content {
                flex-direction: column;
            }
            
            .product-price {
                text-align: left;
            }
            
            .product-item {
                flex-direction: column;
            }
            
            .product-image {
                width: 100%;
                height: 150px;
            }
        }
        
        @media (max-width: 576px) {
            .info-card,
            .status-timeline,
            .products-table,
            .sidebar-summary {
                padding: 1rem;
            }
            
            .logo {
                font-size: 1.25rem;
            }
            
            .breadcrumb {
                font-size: 0.75rem;
            }
            
            .timeline-circle {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            
            .timeline-label {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-shopping-bag"></i>
                    InGrosir
                </a>
                
                <button class="mobile-toggle" onclick="toggleNav()" type="button" aria-label="Toggle Navigation">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                
                <nav class="nav" id="mainNav">
                    <a href="index.php"><i class="fas fa-home"></i> Beranda</a>
                    <a href="cart.php"><i class="fas fa-shopping-cart"></i> Keranjang</a>
                    <a href="riwayat_pesanan.php"><i class="fas fa-history"></i> Riwayat</a>
                    <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Beranda</a>
            <i class="fas fa-chevron-right"></i>
            <a href="riwayat_pesanan.php">Riwayat Pesanan</a>
            <i class="fas fa-chevron-right"></i>
            <span>Detail Pesanan #<?php echo $pesanan_id; ?></span>
        </div>

        <!-- Alert untuk pesanan dibatalkan -->
        <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
        <div class="cancelled-alert">
            <div class="cancelled-alert-header">
                <i class="fas fa-times-circle"></i>
                Pesanan Dibatalkan
            </div>
            <div class="cancelled-alert-content">
                <p>Pesanan ini telah <strong>dibatalkan</strong> oleh <strong><?php echo ucfirst($pesanan['dibatalkan_oleh'] ?? 'penjual'); ?></strong>
                <?php if (!empty($pesanan['tanggal_batal'])) { ?>
                    pada <strong><?php echo date('d F Y, H:i', strtotime($pesanan['tanggal_batal'])); ?> WIB</strong>
                <?php } ?>
                .</p>
                
                <?php if (!empty($pesanan['alasan_batal'])) { ?>
                <div class="cancelled-reason">
                    <div class="reason-label">Alasan Pembatalan</div>
                    <div class="reason-text"><?php echo htmlspecialchars($pesanan['alasan_batal']); ?></div>
                </div>
                <?php } ?>
                
                <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
                    <i class="fas fa-info-circle"></i> Jika Anda sudah melakukan pembayaran, dana akan dikembalikan dalam 3-7 hari kerja.
                </p>
            </div>
        </div>
        <?php } ?>

        <!-- Status Timeline -->
        <div class="status-timeline">
            <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-tasks"></i>
                Status Pesanan
            </h3>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem;">
                <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                    Pesanan ini telah dibatalkan
                <?php } else { ?>
                    Lacak progress pesanan Anda secara real-time
                <?php } ?>
            </p>
            
            <div class="timeline-container <?php echo ($pesanan['status_pesanan'] == 'dibatalkan') ? 'cancelled' : ''; ?>">
                <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                    <!-- Timeline untuk pesanan dibatalkan -->
                    <div class="timeline-step completed">
                        <div class="timeline-circle">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-label">Pesanan Dibuat</div>
                    </div>
                    
                    <div class="timeline-step cancelled">
                        <div class="timeline-circle">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="timeline-label">Dibatalkan</div>
                    </div>
                    
                    <div class="timeline-step">
                        <div class="timeline-circle">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="timeline-label">Tidak Diproses</div>
                    </div>
                <?php } else { ?>
                    <!-- Timeline normal -->
                    <div class="timeline-step completed">
                        <div class="timeline-circle">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-label">Pesanan Dibuat</div>
                    </div>
                    
                    <div class="timeline-step <?php echo in_array($pesanan['status_pesanan'], ['diproses', 'dikirim', 'selesai']) ? 'completed' : ($pesanan['status_pesanan'] == 'pending' ? 'active' : ''); ?>">
                        <div class="timeline-circle">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="timeline-label">Dikonfirmasi</div>
                    </div>
                    
                    <div class="timeline-step <?php echo in_array($pesanan['status_pesanan'], ['dikirim', 'selesai']) ? 'completed' : ($pesanan['status_pesanan'] == 'diproses' ? 'active' : ''); ?>">
                        <div class="timeline-circle">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="timeline-label">Diproses</div>
                    </div>
                    
                    <div class="timeline-step <?php echo $pesanan['status_pesanan'] == 'selesai' ? 'completed' : ($pesanan['status_pesanan'] == 'dikirim' ? 'active' : ''); ?>">
                        <div class="timeline-circle">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="timeline-label">Dikirim</div>
                    </div>
                    
                    <div class="timeline-step <?php echo $pesanan['status_pesanan'] == 'selesai' ? 'active completed' : ''; ?>">
                        <div class="timeline-circle">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="timeline-label">Selesai</div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sidebar - Will appear first on mobile -->
            <div class="col-lg-4 order-lg-2">
                <div class="sidebar-summary">
                    <h3>Ringkasan Pembayaran</h3>
                    
                    <?php 
                    $total_items = 0;
                    $products = [];
                    mysqli_data_seek($result_detail, 0);
                    while ($item = mysqli_fetch_assoc($result_detail)) { 
                        $products[] = $item;
                        $total_items++;
                    }
                    ?>
                    
                    <div class="summary-row">
                        <span>Total Produk</span>
                        <span><?php echo $total_items; ?> item</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>Rp <?php 
                            $subtotal_before = $pesanan['total_harga'] + ($pesanan['diskon_voucher'] ?? 0);
                            echo number_format($subtotal_before, 0, ',', '.'); 
                        ?></span>
                    </div>

                    <?php if (!empty($pesanan['diskon_voucher']) && $pesanan['diskon_voucher'] > 0) { ?>
                    <div class="summary-row" style="color: var(--success);">
                        <span>
                            <i class="fas fa-ticket-alt me-1"></i>
                            Diskon Voucher
                            <?php if (!empty($pesanan['kode_voucher'])) { ?>
                                <small>(<?php echo htmlspecialchars($pesanan['kode_voucher']); ?>)</small>
                            <?php } ?>
                        </span>
                        <span>- Rp <?php echo number_format($pesanan['diskon_voucher'], 0, ',', '.'); ?></span>
                    </div>
                    <?php } ?>

                    <div class="summary-row">
                        <span>Ongkos Kirim</span>
                        <span>Rp 0</span>
                    </div>

                    <div class="summary-row total <?php echo ($pesanan['status_pesanan'] == 'dibatalkan') ? 'cancelled' : ''; ?>">
                        <span>Total Bayar</span>
                        <span class="amount">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                    </div>
                    
                    <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                    <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: var(--radius); margin-top: 1rem; font-size: 0.875rem;">
                        <i class="fas fa-info-circle" style="color: var(--danger);"></i>
                        <span style="color: var(--text-primary);">Pembayaran tidak akan diproses atau akan dikembalikan.</span>
                    </div>
                    <?php } ?>
                    
                    <div class="seller-info">
                        <h4>
                            <i class="fas fa-store"></i>
                            Informasi Penjual
                        </h4>
                        <div class="seller-detail">
                            <i class="fas fa-building"></i>
                            <span><strong><?php echo htmlspecialchars($pesanan['nama_grosir']); ?></strong></span>
                        </div>
                        <?php if (!empty($pesanan['alamat_grosir'])) { ?>
                        <div class="seller-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($pesanan['alamat_grosir']); ?></span>
                        </div>
                        <?php } ?>
                        <?php if (!empty($pesanan['nomor_telepon'])) { ?>
                        <div class="seller-detail">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($pesanan['nomor_telepon']); ?></span>
                        </div>
                        <?php } ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="riwayat_pesanan.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Riwayat
                        </a>
                        <?php if ($pesanan['status_pesanan'] == 'dikirim' && !empty($pesanan['nomor_resi'])) { ?>
                        <button class="btn btn-primary" onclick="alert('Fitur tracking pengiriman akan segera hadir!')">
                            <i class="fas fa-truck"></i>
                            Lacak Pengiriman
                        </button>
                        <?php } ?>
                        <?php if ($pesanan['status_pesanan'] == 'selesai') { ?>
                        <a href="store.php?id=<?php echo $pesanan['user_id_penjual']; ?>" class="btn btn-success">
                            <i class="fas fa-redo"></i>
                            Pesan Lagi
                        </a>
                        <?php } ?>
                        <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                        <a href="store.php?id=<?php echo $pesanan['user_id_penjual']; ?>" class="btn btn-primary">
                            <i class="fas fa-shopping-bag"></i>
                            Lihat Toko
                        </a>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8 order-lg-1">
                <!-- Order Info -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Informasi Pesanan
                    </h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">ID Pesanan</span>
                                <div class="info-value">#<?php echo htmlspecialchars($pesanan['pesanan_id']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Tanggal Pesanan</span>
                                <div class="info-value"><?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Status Pesanan</span>
                                <?php
                                $status_class = 'status-' . $pesanan['status_pesanan'];
                                $status_text = ucfirst($pesanan['status_pesanan']);
                                $status_icon = 'fa-clock';
                                
                                switch($pesanan['status_pesanan']) {
                                    case 'pending':
                                        $status_icon = 'fa-clock';
                                        $status_text = 'Menunggu Konfirmasi';
                                        break;
                                    case 'diproses':
                                        $status_icon = 'fa-cog';
                                        $status_text = 'Sedang Diproses';
                                        break;
                                    case 'dikirim':
                                        $status_icon = 'fa-shipping-fast';
                                        $status_text = 'Dalam Pengiriman';
                                        break;
                                    case 'selesai':
                                        $status_icon = 'fa-check-circle';
                                        $status_text = 'Selesai';
                                        break;
                                    case 'dibatalkan':
                                        $status_icon = 'fa-times-circle';
                                        $status_text = 'Dibatalkan';
                                        break;
                                }
                                ?>
                                <div>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($pesanan['nomor_resi']) && $pesanan['status_pesanan'] != 'dibatalkan') { ?>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Nomor Resi</span>
                                <div class="info-value" style="color: var(--primary);"><?php echo htmlspecialchars($pesanan['nomor_resi']); ?></div>
                            </div>
                        </div>
                        <?php } ?>
                        
                        <!-- Payment Status Actions -->
                        <?php if ($pesanan['payment_status'] == 'waiting_for_payment' && $pesanan['status_pesanan'] != 'dibatalkan') { ?>
                        <div class="col-12">
                            <div class="d-grid gap-2" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <a href="upload_bukti_bayar.php?id=<?php echo $pesanan['pesanan_id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-upload"></i>
                                    Upload Bukti Pembayaran
                                </a>
                                <p style="text-align: center; font-size: 0.75rem; color: var(--text-secondary); margin: 0;">
                                    Segera upload bukti setelah transfer
                                </p>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if ($pesanan['payment_status'] == 'payment_uploaded') { ?>
                        <div class="col-12">
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <div style="background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--warning); margin-bottom: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <i class="fas fa-clock" style="color: var(--warning); font-size: 1.5rem;"></i>
                                        <div>
                                            <strong style="color: var(--warning);">Menunggu Verifikasi</strong>
                                            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                                                Bukti pembayaran sedang diverifikasi
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <a href="upload_bukti_bayar.php?id=<?php echo $pesanan['pesanan_id']; ?>" 
                                       class="btn btn-outline">
                                        <i class="fas fa-sync-alt"></i>
                                        Upload Ulang Bukti
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if ($pesanan['payment_status'] == 'payment_rejected') { ?>
                        <div class="col-12">
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--danger); margin-bottom: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <i class="fas fa-times-circle" style="color: var(--danger); font-size: 1.5rem;"></i>
                                        <div style="flex: 1;">
                                            <strong style="color: var(--danger);">Bukti Ditolak</strong>
                                            <?php
                                            $query_bukti = "SELECT alasan_reject FROM bukti_pembayaran WHERE pesanan_id = ? ORDER BY created_at DESC LIMIT 1";
                                            $stmt_bukti = mysqli_prepare($koneksi, $query_bukti);
                                            mysqli_stmt_bind_param($stmt_bukti, "i", $pesanan['pesanan_id']);
                                            mysqli_stmt_execute($stmt_bukti);
                                            $bukti_reject = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bukti));
                                            ?>
                                            <?php if ($bukti_reject) { ?>
                                                <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.5rem 0 0 0; font-style: italic;">
                                                    "<?php echo htmlspecialchars($bukti_reject['alasan_reject']); ?>"
                                                </p>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <a href="upload_bukti_bayar.php?id=<?php echo $pesanan['pesanan_id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-redo"></i>
                                        Upload Bukti Baru
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <?php if ($pesanan['payment_status'] == 'payment_verified') { ?>
                        <div class="col-12">
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--secondary); text-align: center;">
                                    <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                    <div>
                                        <strong style="color: var(--secondary); font-size: 1.125rem;">Pembayaran Terverifikasi ✓</strong>
                                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0.25rem 0 0 0;">
                                            Pesanan sedang diproses
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <?php if (!empty($pesanan['voucher_id']) && !empty($pesanan['diskon_voucher'])) { ?>
                <div class="info-card" data-aos="fade-up" data-aos-delay="100">
                    <h3>
                        <i class="fas fa-gift"></i>
                        Voucher yang Kamu Gunakan
                    </h3>
                    
                    <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(16, 185, 129, 0.1)); 
                                padding: 1.5rem; border-radius: var(--radius-lg); 
                                border: 2px dashed var(--success);">
                        <div style="text-align: center; margin-bottom: 1rem;">
                            <div style="font-size: 1.75rem; font-weight: 800; color: var(--success); 
                                        letter-spacing: 3px; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($pesanan['kode_voucher']); ?>
                            </div>
                            <?php if (!empty($pesanan['voucher_desc'])) { ?>
                                <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                                    <?php echo htmlspecialchars($pesanan['voucher_desc']); ?>
                                </p>
                            <?php } ?>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                            <div style="background: white; padding: 1rem; border-radius: var(--radius); text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                    Hemat
                                </div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--success);">
                                    Rp <?php echo number_format($pesanan['diskon_voucher'], 0, ',', '.'); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($pesanan['tipe_diskon'])) { ?>
                            <div style="background: white; padding: 1rem; border-radius: var(--radius); text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                    Diskon
                                </div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                                    <?php 
                                    if ($pesanan['tipe_diskon'] == 'persentase') {
                                        echo number_format($pesanan['nilai_diskon'], 0) . '%';
                                    } else {
                                        echo 'Rp ' . number_format($pesanan['nilai_diskon'], 0, ',', '.');
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <small style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                Voucher berhasil diterapkan pada pesanan ini
                            </small>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <!-- Products List -->
                <div class="products-table">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-box"></i>
                            Produk yang Dipesan
                        </h3>
                    </div>
                    
                    <?php 
                    foreach ($products as $item) {
                        $subtotal = $item['jumlah'] * $item['harga_per_unit'];
                    ?>
                        <div class="product-item">
                            <?php if (!empty($item['gambar_produk'])) { ?>
                                <img src="uploads/<?php echo htmlspecialchars($item['gambar_produk']); ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" class="product-image">
                            <?php } else { ?>
                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
                                    <i class="fas fa-box" style="font-size: 2rem;"></i>
                                </div>
                            <?php } ?>
                            
                            <div class="product-content">
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($item['nama_produk']); ?></div>
                                    <div class="product-meta">
                                        <?php echo number_format($item['jumlah']); ?> pcs × Rp <?php echo number_format($item['harga_per_unit'], 0, ',', '.'); ?>
                                    </div>
                                    
                                    <?php if ($pesanan['status_pesanan'] == 'selesai') { ?>
                                        <?php if ($item['sudah_ulasan'] > 0) { ?>
                                            <span class="review-badge badge-reviewed">
                                                <i class="fas fa-check-circle"></i>
                                                Sudah diulas
                                            </span>
                                        <?php } else { ?>
                                            <a href="ulasan.php?produk_id=<?php echo $item['produk_id']; ?>&pesanan_id=<?php echo $pesanan_id; ?>" class="review-btn review-btn-primary">
                                                <i class="fas fa-star"></i>
                                                Beri Ulasan
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                                
                                <div class="product-price">
                                    <span class="price">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                                    <span class="subtotal-label">Subtotal</span>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    
                    <?php if ($pesanan['status_pesanan'] == 'selesai') { ?>
                    <div class="product-actions">
                        <div class="alert alert-success" style="margin: 0; flex: 1;">
                            <i class="fas fa-heart"></i>
                            <span>Pesanan selesai! Bantu kami dengan memberikan ulasan untuk produk yang Anda beli.</span>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile Navigation Toggle
        function toggleNav() {
            const nav = document.getElementById('mainNav');
            const toggle = document.querySelector('.mobile-toggle');
            
            nav.classList.toggle('active');
            
            const spans = toggle.querySelectorAll('span');
            if (nav.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                spans[0].style.transform = '';
                spans[1].style.opacity = '';
                spans[2].style.transform = '';
            }
        }

        // Close nav when clicking outside
        document.addEventListener('click', function(e) {
            const nav = document.getElementById('mainNav');
            const toggle = document.querySelector('.mobile-toggle');
            if (!nav.contains(e.target) && !toggle.contains(e.target) && nav.classList.contains('active')) {
                toggleNav();
            }
        });

        // Smooth scroll animation
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to product items
            const productItems = document.querySelectorAll('.product-item');
            productItems.forEach((item, index) => {
                item.style.animation = `fadeInUp 0.5s ease ${index * 0.1}s both`;
            });
        });

        // Add CSS for animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>