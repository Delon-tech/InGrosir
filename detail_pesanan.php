<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pesanan_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$pesanan_id) {
    header("Location: pesanan.php");
    exit();
}

// Ambil data pesanan dengan info pembeli dan metode pembayaran
$query_pesanan = "SELECT p.*, u.nama_lengkap, u.email, u.nomor_telepon, 
                  mpp.account_number, mpp.account_name, mpp.bank_name, mpp.qr_image, mpp.notes as payment_notes,
                  mpt.nama_metode, mpt.deskripsi as deskripsi_metode, mpt.tipe_metode, mpt.icon,
                  ap.label_alamat, ap.nama_penerima, ap.nomor_telepon as telp_penerima, ap.alamat_lengkap, 
                  ap.kelurahan, ap.kecamatan, ap.kota, ap.provinsi, ap.kode_pos,
                  v.kode_voucher as voucher_code_full, v.tipe_diskon, v.nilai_diskon
                  FROM pesanan p 
                  JOIN users u ON p.user_id_pembeli = u.user_id 
                  LEFT JOIN metode_pembayaran_penjual mpp ON p.metode_penjual_id = mpp.metode_penjual_id
                  LEFT JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                  LEFT JOIN alamat_pengiriman ap ON p.alamat_id = ap.alamat_id
                  LEFT JOIN voucher_diskon v ON p.voucher_id = v.voucher_id
                  WHERE p.pesanan_id = ? AND p.user_id_penjual = ?";
$stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
mysqli_stmt_bind_param($stmt_pesanan, "ii", $pesanan_id, $user_id);
mysqli_stmt_execute($stmt_pesanan);
$result_pesanan = mysqli_stmt_get_result($stmt_pesanan);
$pesanan = mysqli_fetch_assoc($result_pesanan);

if (!$pesanan) {
    header("Location: pesanan.php?error=not_found");
    exit();
}

// Ambil detail produk pesanan
$query_detail = "SELECT dp.*, p.nama_produk, p.gambar_produk 
                 FROM detail_pesanan dp 
                 JOIN produk p ON dp.produk_id = p.produk_id 
                 WHERE dp.pesanan_id = ?";
$stmt_detail = mysqli_prepare($koneksi, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $pesanan_id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

// Handle update status
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $status_baru = $_POST['status_pesanan'];
    $nomor_resi = trim($_POST['nomor_resi']);
    
    $update_query = "UPDATE pesanan SET status_pesanan = ?, nomor_resi = ? WHERE pesanan_id = ? AND user_id_penjual = ?";
    $update_stmt = mysqli_prepare($koneksi, $update_query);
    mysqli_stmt_bind_param($update_stmt, "ssii", $status_baru, $nomor_resi, $pesanan_id, $user_id);

    // NOTIFIKASI: Kirim ke pembeli
    require_once 'includes/notification_helper.php';

    $pesan_notif = '';
    $icon_notif = 'info-circle';

    switch ($status_baru) {
        case 'diproses':
            $pesan_notif = "Pesanan #$pesanan_id Anda sedang dikemas oleh penjual.";
            $icon_notif = 'cog';
            break;
        case 'dikirim':
            $pesan_notif = "Pesanan #$pesanan_id sudah dikirim!";
            if (!empty($nomor_resi)) {
                $pesan_notif .= " Nomor resi: $nomor_resi";
            }
            $icon_notif = 'truck';
            break;
        case 'selesai':
            $pesan_notif = "Pesanan #$pesanan_id sudah selesai. Beri ulasan yuk!";
            $icon_notif = 'check-circle';
            break;
        case 'dibatalkan':
            $pesan_notif = "Pesanan #$pesanan_id telah dibatalkan.";
            $icon_notif = 'times-circle';
            break;
    }

    if (!empty($pesan_notif)) {
        kirim_notifikasi(
            $pesanan['user_id_pembeli'], 
            'Status Pesanan Diperbarui', 
            $pesan_notif,
            "detail_pesanan_pembeli.php?id=$pesanan_id",
            $icon_notif
        );
    }
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = 'Status pesanan berhasil diperbarui!';
        // Refresh data pesanan
        mysqli_stmt_execute($stmt_pesanan);
        $result_pesanan = mysqli_stmt_get_result($stmt_pesanan);
        $pesanan = mysqli_fetch_assoc($result_pesanan);
    } else {
        $error_message = 'Gagal memperbarui status pesanan.';
    }
}

// Handle pembatalan pesanan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batalkan_pesanan'])) {
    $alasan_batal = trim($_POST['alasan_batal']);
    
    if (in_array($pesanan['status_pesanan'], ['pending', 'diproses'])) {
        $batal_query = "UPDATE pesanan 
                        SET status_pesanan = 'dibatalkan', 
                            dibatalkan_oleh = 'penjual',
                            alasan_batal = ?,
                            tanggal_batal = NOW()
                        WHERE pesanan_id = ? AND user_id_penjual = ?";
        $batal_stmt = mysqli_prepare($koneksi, $batal_query);
        mysqli_stmt_bind_param($batal_stmt, "sii", $alasan_batal, $pesanan_id, $user_id);
        
        if (mysqli_stmt_execute($batal_stmt)) {
            $detail_query = "SELECT produk_id, jumlah FROM detail_pesanan WHERE pesanan_id = ?";
            $detail_stmt = mysqli_prepare($koneksi, $detail_query);
            mysqli_stmt_bind_param($detail_stmt, "i", $pesanan_id);
            mysqli_stmt_execute($detail_stmt);
            $detail_result = mysqli_stmt_get_result($detail_stmt);
            
            while ($item = mysqli_fetch_assoc($detail_result)) {
                $update_stok_query = "UPDATE produk SET stok = stok + ? WHERE produk_id = ?";
                $update_stok_stmt = mysqli_prepare($koneksi, $update_stok_query);
                mysqli_stmt_bind_param($update_stok_stmt, "ii", $item['jumlah'], $item['produk_id']);
                mysqli_stmt_execute($update_stok_stmt);
            }
            
            $success_message = 'Pesanan berhasil dibatalkan dan stok produk telah dikembalikan.';
            
            mysqli_stmt_execute($stmt_pesanan);
            $result_pesanan = mysqli_stmt_get_result($stmt_pesanan);
            $pesanan = mysqli_fetch_assoc($result_pesanan);
        } else {
            $error_message = 'Gagal membatalkan pesanan.';
        }
    } else {
        $error_message = 'Pesanan dengan status "' . $pesanan['status_pesanan'] . '" tidak dapat dibatalkan.';
    }
}

function getPaymentIcon($tipe_metode, $nama_metode = '') {
    if (!empty($tipe_metode)) {
        switch($tipe_metode) {
            case 'transfer_bank': return 'fa-university';
            case 'qris': return 'fa-qrcode';
            case 'ewallet': return 'fa-wallet';
            case 'cod': return 'fa-hand-holding-usd';
        }
    }
    if (stripos($nama_metode, 'transfer') !== false || stripos($nama_metode, 'bank') !== false) {
        return 'fa-university';
    } elseif (stripos($nama_metode, 'qris') !== false) {
        return 'fa-qrcode';
    } elseif (stripos($nama_metode, 'wallet') !== false || stripos($nama_metode, 'e-wallet') !== false) {
        return 'fa-wallet';
    } elseif (stripos($nama_metode, 'cod') !== false || stripos($nama_metode, 'bayar di tempat') !== false) {
        return 'fa-hand-holding-usd';
    }
    return 'fa-credit-card';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $pesanan_id; ?> - InGrosir</title>
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
            
            /* Variabel lama untuk kompatibilitas konten detail */
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            color: var(--text-primary);
        }
        
        /* --- SIDEBAR STYLES (Diperbarui agar sama dengan pesanan.php) --- */
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
        
        /* --- MAIN CONTENT & PAGE STYLES --- */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 2rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .page-title .order-id { color: var(--primary); font-weight: 700; }
        .page-title p { color: var(--text-secondary); font-size: 0.875rem; }
        .header-actions { display: flex; gap: 0.75rem; }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .status-diproses { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .status-dikirim { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
        .status-selesai { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-dibatalkan { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        /* Detail Grid */
        .detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: none;
        }
        .card-header {
            padding: 1.5rem;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
        }
        .card-header h3 { font-size: 1.125rem; display: flex; align-items: center; gap: 0.5rem; margin:0; font-weight: 600;}
        .card-body { padding: 1.5rem; }
        
        /* Product Items */
        .product-item { display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border); }
        .product-item:last-child { border-bottom: none; }
        .product-image {
            width: 80px; height: 80px; border-radius: var(--radius); object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .product-info { flex: 1; }
        .product-name { font-weight: 600; margin-bottom: 0.25rem; }
        .product-meta { font-size: 0.875rem; color: var(--text-secondary); }
        .product-price { text-align: right; }
        .product-unit-price { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .product-total-price { font-size: 1.125rem; font-weight: 700; color: var(--primary); }
        
        /* Summary */
        .order-summary { display: flex; flex-direction: column; gap: 0.75rem; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.75rem 0; font-size: 0.875rem; }
        .summary-row.total { border-top: 2px solid var(--border); font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        
        /* Info Box */
        .info-box { padding: 1rem; background: var(--bg-gray); border-radius: var(--radius); margin-bottom: 1rem; }
        .info-label { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem; }
        .info-value { font-size: 0.9375rem; color: var(--text-primary); }
        
        /* Payment Method Box */
        .payment-method-box {
            padding: 1rem; background: var(--bg-gray); border-radius: var(--radius);
            margin-bottom: 1rem; display: flex; gap: 1rem; align-items: flex-start;
        }
        .payment-method-icon {
            width: 40px; height: 40px; background: white; border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; color: var(--primary); flex-shrink: 0;
        }
        .payment-method-content h4 { font-size: 0.9375rem; margin-bottom: 0.25rem; color: var(--text-primary); font-weight: 600; }
        .payment-method-content p { font-size: 0.8125rem; color: var(--text-secondary); margin: 0; }
        
        /* Timeline */
        .timeline { position: relative; padding-left: 2rem; }
        .timeline-item { position: relative; padding-bottom: 1.5rem; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-item::before {
            content: ''; position: absolute; left: -1.875rem; top: 0.5rem;
            width: 12px; height: 12px; border-radius: 50%;
            background: var(--primary); border: 3px solid white; box-shadow: 0 0 0 2px var(--primary);
        }
        .timeline-item.cancelled::before { background: var(--danger); box-shadow: 0 0 0 2px var(--danger); }
        .timeline-item::after {
            content: ''; position: absolute; left: -1.3125rem; top: 1.5rem;
            width: 2px; height: calc(100% - 1rem); background: var(--border);
        }
        .timeline-item:last-child::after { display: none; }
        .timeline-content { background: var(--bg-gray); padding: 1rem; border-radius: var(--radius); }
        .timeline-title { font-weight: 600; margin-bottom: 0.25rem; }
        .timeline-date { font-size: 0.75rem; color: var(--text-secondary); }
        
        /* Form */
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary); }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 0.75rem; border: 2px solid var(--border);
            border-radius: var(--radius); font-size: 0.9375rem; transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius);
            font-weight: 600; cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.5rem;
            text-decoration: none; font-size: 0.875rem; justify-content: center;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        
        /* Warning Box */
        .warning-box {
            background: rgba(245, 158, 11, 0.1); border: 2px solid var(--warning);
            padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;
            display: flex; gap: 0.75rem;
        }
        .warning-box i { color: var(--warning); font-size: 1.25rem; }
        .warning-box-content p { font-size: 0.875rem; color: var(--text-primary); margin-bottom: 0.5rem; }
        .warning-box-content ul { font-size: 0.875rem; color: var(--text-secondary); margin-left: 1.25rem; }
        
        /* Disabled State */
        .disabled-overlay { position: relative; pointer-events: none; opacity: 0.6; }
        .disabled-overlay::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.7); border-radius: var(--radius-lg);
        }

        /* Mobile Toggle (Consistent with Pesanan.php) */
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
        .mobile-toggle:hover { transform: scale(1.1); }
        
        /* Modal - Adjusted to match style */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content {
            background: white; padding: 2rem; border-radius: 16px;
            max-width: 500px; width: 90%; animation: slideUp 0.3s ease;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; background: transparent; padding:0; border:none; }
        .modal-footer { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        
        /* Print Styles */
        @media print {
            .sidebar, .breadcrumb, .header-actions, .btn, .form-group, .modal, .mobile-toggle { display: none !important; }
            .main-content { margin-left: 0; }
            .card { box-shadow: none; border: 1px solid var(--border); }
        }
        
        @media (max-width: 1024px) { .detail-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .page-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
            .header-actions { width: 100%; flex-direction: column; }
            .btn { width: 100%; }
            .product-item { flex-direction: column; }
            .product-price { text-align: left; }
            .modal-content { padding: 1.5rem; }
            .modal-footer { flex-direction: column; }
        }
    </style>
</head>
<body>
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

    <main class="main-content">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb bg-white p-3 rounded shadow-sm">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="pesanan.php">Kelola Pesanan</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detail Pesanan</li>
            </ol>
        </nav>

        <div class="page-header">
            <div class="page-title">
                <h1>Detail Pesanan <span class="order-id">#<?php echo $pesanan_id; ?></span></h1>
                <p><?php echo date('d F Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?> WIB</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="fas fa-print"></i>
                    Cetak Invoice
                </button>
                <a href="pesanan.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <?php if ($success_message) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Berhasil!</strong> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <?php if ($error_message) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php } ?>

        <div class="detail-grid">
            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-boxes"></i>
                            Produk yang Dipesan
                        </h3>
                        <span style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?php echo mysqli_num_rows($result_detail); ?> Item
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="product-table">
                            <?php 
                            $subtotal = 0;
                            while ($item = mysqli_fetch_assoc($result_detail)) { 
                                $item_total = $item['jumlah'] * $item['harga_per_unit'];
                                $subtotal += $item_total;
                                $gambar_path = !empty($item['gambar_produk']) 
                                    ? "uploads/" . htmlspecialchars($item['gambar_produk']) 
                                    : "https://via.placeholder.com/80x80/667eea/ffffff?text=Produk";
                            ?>
                            <div class="product-item">
                                <img src="<?php echo $gambar_path; ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" class="product-image">
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($item['nama_produk']); ?></div>
                                    <div class="product-meta">
                                        <span><?php echo $item['jumlah']; ?> × Rp <?php echo number_format($item['harga_per_unit'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                                <div class="product-price">
                                    <div class="product-unit-price">@ Rp <?php echo number_format($item['harga_per_unit'], 0, ',', '.'); ?></div>
                                    <div class="product-total-price">Rp <?php echo number_format($item_total, 0, ',', '.'); ?></div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        
                        <div class="summary-row">
                            <span>Subtotal Produk</span>
                            <span>Rp <?php 
                                $subtotal_before_discount = $pesanan['total_harga'] + ($pesanan['diskon_voucher'] ?? 0);
                                echo number_format($subtotal_before_discount, 0, ',', '.'); 
                            ?></span>
                        </div>

                        <?php if (!empty($pesanan['diskon_voucher']) && $pesanan['diskon_voucher'] > 0) { ?>
                        <div class="summary-row" style="color: var(--success);">
                            <span>
                                <i class="fas fa-ticket-alt me-1"></i>
                                Diskon Voucher
                                <?php if (!empty($pesanan['kode_voucher'])) { ?>
                                    <small style="font-weight: 600;">(<?php echo htmlspecialchars($pesanan['kode_voucher']); ?>)</small>
                                <?php } ?>
                            </span>
                            <span>- Rp <?php echo number_format($pesanan['diskon_voucher'], 0, ',', '.'); ?></span>
                        </div>
                        <?php } ?>

                        <div class="summary-row">
                            <span>Ongkos Kirim</span>
                            <span>Rp 0</span>
                        </div>

                        <div class="summary-row total">
                            <span>Total Bayar</span>
                            <span class="amount">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="card <?php echo ($pesanan['status_pesanan'] == 'dibatalkan' || $pesanan['status_pesanan'] == 'selesai') ? 'disabled-overlay' : ''; ?>">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-edit"></i>
                            Kelola Status Pesanan
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                            <div class="warning-box" style="border-color: var(--danger); background: rgba(239, 68, 68, 0.1);">
                                <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                <div class="warning-box-content">
                                    <p style="font-weight: 600; color: var(--danger);">Pesanan Telah Dibatalkan</p>
                                    <p style="margin-bottom: 0;">Status pesanan tidak dapat diubah karena sudah dibatalkan.</p>
                                </div>
                            </div>
                        <?php } elseif ($pesanan['status_pesanan'] == 'selesai') { ?>
                            <div class="warning-box" style="border-color: var(--success); background: rgba(16, 185, 129, 0.1);">
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                <div class="warning-box-content">
                                    <p style="font-weight: 600; color: var(--success);">Pesanan Telah Selesai</p>
                                    <p style="margin-bottom: 0;">Status pesanan tidak dapat diubah karena sudah selesai.</p>
                                </div>
                            </div>
                        <?php } else { ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-shipping-fast"></i>
                                    Nomor Resi Pengiriman
                                </label>
                                <input type="text" name="nomor_resi" class="form-input" 
                                       value="<?php echo htmlspecialchars($pesanan['nomor_resi']); ?>" 
                                       placeholder="Contoh: JNE1234567890">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tasks"></i>
                                    Status Pesanan
                                </label>
                                <select name="status_pesanan" class="form-select">
                                    <option value="pending" <?php echo ($pesanan['status_pesanan'] == 'pending') ? 'selected' : ''; ?>>
                                        🕐 Pending - Menunggu Konfirmasi
                                    </option>
                                    <option value="diproses" <?php echo ($pesanan['status_pesanan'] == 'diproses') ? 'selected' : ''; ?>>
                                        ⚙️ Diproses - Sedang Dikemas
                                    </option>
                                    <option value="dikirim" <?php echo ($pesanan['status_pesanan'] == 'dikirim') ? 'selected' : ''; ?>>
                                        🚚 Dikirim - Dalam Perjalanan
                                    </option>
                                    <option value="selesai" <?php echo ($pesanan['status_pesanan'] == 'selesai') ? 'selected' : ''; ?>>
                                        ✅ Selesai - Pesanan Diterima
                                    </option>
                                </select>
                            </div>

                            <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-save"></i>
                                Simpan Perubahan
                            </button>
                        </form>
                        
                        <?php if (in_array($pesanan['status_pesanan'], ['pending', 'diproses'])) { ?>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <button type="button" onclick="openCancelModal()" class="btn btn-danger" style="width: 100%;">
                                <i class="fas fa-times-circle"></i>
                                Batalkan Pesanan
                            </button>
                        </div>
                        <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Status Pesanan
                        </h3>
                    </div>
                    <div class="card-body">

                    <?php 
                                // Cek apakah ada bukti pembayaran yang perlu diverifikasi
                            $query_bukti_pending = "SELECT bp.*, u.nama_lengkap 
                                                    FROM bukti_pembayaran bp
                                                    JOIN users u ON bp.user_id = u.user_id
                                                    WHERE bp.pesanan_id = ? AND bp.status_verifikasi = 'pending'
                                                    ORDER BY bp.created_at DESC LIMIT 1";
                            $stmt_bukti = mysqli_prepare($koneksi, $query_bukti_pending);
                            mysqli_stmt_bind_param($stmt_bukti, "i", $pesanan_id);
                            mysqli_stmt_execute($stmt_bukti);
                            $bukti_pending = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bukti));
                            ?>

                            <?php if ($bukti_pending) { ?>
                            <div class="card" style="margin-bottom: 1.5rem; border: 3px solid var(--warning); animation: pulse 2s ease infinite;">
                                <div class="card-body" style="padding: 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                        <div style="width: 60px; height: 60px; background: var(--warning); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fas fa-exclamation-triangle" style="font-size: 1.75rem; color: white;"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <h3 style="font-size: 1.25rem; margin-bottom: 0.25rem; color: var(--warning);">
                                                ⚠️ Bukti Pembayaran Menunggu Verifikasi!
                                            </h3>
                                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0;">
                                                Pembeli <strong><?php echo htmlspecialchars($bukti_pending['nama_lengkap']); ?></strong> 
                                                telah mengupload bukti pembayaran
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                        <div style="background: var(--bg-gray); padding: 1rem; border-radius: var(--radius);">
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Jumlah Transfer</div>
                                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--secondary);">
                                                Rp <?php echo number_format($bukti_pending['jumlah_transfer'], 0, ',', '.'); ?>
                                            </div>
                                        </div>
                                        <div style="background: var(--bg-gray); padding: 1rem; border-radius: var(--radius);">
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Bank</div>
                                            <div style="font-size: 1rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($bukti_pending['bank_pengirim'] ?? '-'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <a href="verifikasi_bukti_pembayaran.php?id=<?php echo $pesanan_id; ?>" 
                                    class="btn btn-primary" 
                                    style="width: 100%; padding: 1rem; font-size: 1rem;">
                                        <i class="fas fa-check-circle"></i>
                                        Verifikasi Bukti Pembayaran Sekarang
                                    </a>
                                </div>
                            </div>

                            <style>
                            @keyframes pulse {
                                0%, 100% { opacity: 1; }
                                50% { opacity: 0.8; }
                            }
                            </style>
                            <?php } ?>

                            <?php if ($pesanan['payment_status'] == 'payment_verified') { ?>
                            <div class="card" style="margin-bottom: 1.5rem;">
                                <div class="card-body" style="padding: 1.5rem; text-align: center;">
                                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--secondary); margin-bottom: 1rem;"></i>
                                    <h3 style="color: var(--secondary); margin-bottom: 0.5rem;">Pembayaran Terverifikasi ✓</h3>
                                    <p style="color: var(--text-secondary); font-size: 0.875rem;">
                                        Diverifikasi pada <?php echo date('d M Y, H:i', strtotime($pesanan['payment_verified_at'])); ?>
                                    </p>
                                    
                                    <a href="verifikasi_bukti_pembayaran.php?id=<?php echo $pesanan_id; ?>" 
                                    class="btn btn-outline" 
                                    style="margin-top: 1rem; padding: 0.75rem 1.5rem; width: auto; display: inline-flex;">
                                        <i class="fas fa-eye"></i>
                                        Lihat Bukti Pembayaran
                                    </a>
                                </div>
                            </div>
                            <?php } ?>

                        <div style="text-align: center; margin-bottom: 1.5rem;">
                            <?php
                            $status_icon = '';
                            $status_class = '';
                            switch($pesanan['status_pesanan']) {
                                case 'pending':
                                    $status_icon = 'fa-clock';
                                    $status_class = 'status-pending';
                                    $status_text = 'Menunggu Konfirmasi';
                                    break;
                                case 'diproses':
                                    $status_icon = 'fa-cog';
                                    $status_class = 'status-diproses';
                                    $status_text = 'Sedang Diproses';
                                    break;
                                case 'dikirim':
                                    $status_icon = 'fa-truck';
                                    $status_class = 'status-dikirim';
                                    $status_text = 'Dalam Pengiriman';
                                    break;
                                case 'selesai':
                                    $status_icon = 'fa-check-circle';
                                    $status_class = 'status-selesai';
                                    $status_text = 'Selesai';
                                    break;
                                case 'dibatalkan':
                                    $status_icon = 'fa-times-circle';
                                    $status_class = 'status-dibatalkan';
                                    $status_text = 'Dibatalkan';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?php echo $status_class; ?>" style="font-size: 1rem; padding: 0.75rem 1.5rem;">
                                <i class="fas <?php echo $status_icon; ?>"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                        
                        <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                        <div class="info-box" style="background: rgba(239, 68, 68, 0.1); border: 2px solid var(--danger);">
                            <div class="info-label" style="color: var(--danger);">
                                <i class="fas fa-exclamation-triangle"></i> PESANAN DIBATALKAN
                            </div>
                            <div class="info-value" style="color: var(--danger); font-weight: 600;">
                                Oleh: <?php echo ucfirst($pesanan['dibatalkan_oleh'] ?? 'Penjual'); ?>
                            </div>
                            <?php if (!empty($pesanan['tanggal_batal'])) { ?>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                Dibatalkan pada: <?php echo date('d F Y, H:i', strtotime($pesanan['tanggal_batal'])); ?> WIB
                            </div>
                            <?php } ?>
                            <?php if (!empty($pesanan['alasan_batal'])) { ?>
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--danger);">
                                <div class="info-label" style="color: var(--danger);">Alasan Pembatalan</div>
                                <div style="font-size: 0.875rem; color: var(--text-primary); margin-top: 0.25rem;">
                                    <?php echo htmlspecialchars($pesanan['alasan_batal']); ?>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                        
                        <?php if (!empty($pesanan['nomor_resi'])) { ?>
                        <div class="info-box">
                            <div class="info-label">Nomor Resi</div>
                            <div class="info-value" style="font-weight: 600; color: var(--primary);">
                                <?php echo htmlspecialchars($pesanan['nomor_resi']); ?>
                            </div>
                        </div>
                        <?php } ?>
                        
                        <div class="info-box">
                            <div class="info-label">ID Pesanan</div>
                            <div class="info-value">#<?php echo $pesanan['pesanan_id']; ?></div>
                        </div>
                        
                        <div class="info-box">
                            <div class="info-label">Tanggal Pesanan</div>
                            <div class="info-value"><?php echo date('d F Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?> WIB</div>
                        </div>
                        
                        <div class="payment-method-box">
                            <div class="payment-method-icon">
                                <i class="fas <?php echo getPaymentIcon($pesanan['tipe_metode'] ?? '', $pesanan['nama_metode'] ?? ''); ?>"></i>
                            </div>
                            <div class="payment-method-content">
                                <h4>Metode Pembayaran</h4>
                                <p style="font-weight: 600; color: var(--primary);">
                                    <?php echo htmlspecialchars($pesanan['nama_metode'] ?? 'Belum dipilih'); ?>
                                </p>
                                
                                <?php if (!empty($pesanan['account_number'])) { ?>
                                    <p style="font-size: 0.8125rem; margin-top: 0.5rem;">
                                        <strong>No. Akun:</strong> <?php echo htmlspecialchars($pesanan['account_number']); ?>
                                    </p>
                                <?php } ?>
                                
                                <?php if (!empty($pesanan['account_name'])) { ?>
                                    <p style="font-size: 0.8125rem;">
                                        <strong>A.n:</strong> <?php echo htmlspecialchars($pesanan['account_name']); ?>
                                    </p>
                                <?php } ?>
                                
                                <?php if (!empty($pesanan['bank_name'])) { ?>
                                    <p style="font-size: 0.8125rem;">
                                        <strong>Bank:</strong> <?php echo htmlspecialchars($pesanan['bank_name']); ?>
                                    </p>
                                <?php } ?>
                                
                                <?php if (!empty($pesanan['qr_image'])) { ?>
                                    <div style="margin-top: 0.75rem;">
                                        <img src="uploads/qris/<?php echo htmlspecialchars($pesanan['qr_image']); ?>" 
                                            alt="QR Code" 
                                            style="max-width: 150px; border-radius: var(--radius); border: 2px solid var(--border);">
                                    </div>
                                <?php } ?>
                                
                                <?php if (!empty($pesanan['payment_notes'])) { ?>
                                    <p style="font-size: 0.75rem; margin-top: 0.5rem; color: var(--text-secondary); font-style: italic;">
                                        <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($pesanan['payment_notes']); ?>
                                    </p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($pesanan['voucher_id']) && !empty($pesanan['diskon_voucher'])) { ?>
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3>
            <i class="fas fa-ticket-alt"></i>
            Informasi Voucher Digunakan
        </h3>
    </div>
    <div class="card-body">
        <div class="info-box" style="background: rgba(16, 185, 129, 0.1); border: 2px solid var(--secondary);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div style="width: 50px; height: 50px; background: var(--secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-ticket-alt" style="font-size: 1.5rem; color: white;"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--secondary); letter-spacing: 2px;">
                        <?php echo htmlspecialchars($pesanan['kode_voucher']); ?>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                        Kode voucher yang digunakan
                    </div>
                </div>
            </div>
            
            <div class="row g-2">
                <?php if (!empty($pesanan['tipe_diskon'])) { ?>
                <div class="col-md-4">
                    <div style="background: white; padding: 0.75rem; border-radius: var(--radius);">
                        <small style="color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">Tipe Diskon</small>
                        <strong>
                            <?php 
                            if ($pesanan['tipe_diskon'] == 'persentase') {
                                echo number_format($pesanan['nilai_diskon'], 0) . '%';
                            } else {
                                echo 'Rp ' . number_format($pesanan['nilai_diskon'], 0, ',', '.');
                            }
                            ?>
                        </strong>
                    </div>
                </div>
                <?php } ?>
                                <div class="col-md-4">
                                    <div style="background: white; padding: 0.75rem; border-radius: var(--radius);">
                                        <small style="color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">Potongan Harga</small>
                                        <strong style="color: var(--secondary);">
                                            Rp <?php echo number_format($pesanan['diskon_voucher'], 0, ',', '.'); ?>
                                        </strong>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div style="background: white; padding: 0.75rem; border-radius: var(--radius);">
                                        <small style="color: var(--text-secondary); display: block; margin-bottom: 0.25rem;">Total Hemat</small>
                                        <strong style="color: var(--secondary);">
                                            Rp <?php echo number_format($pesanan['diskon_voucher'], 0, ',', '.'); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-user"></i>
                            Informasi Pembeli
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <div class="info-label">Nama Lengkap</div>
                            <div class="info-value"><?php echo htmlspecialchars($pesanan['nama_lengkap']); ?></div>
                        </div>
                        
                        <div class="info-box">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($pesanan['email']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-shipping-fast"></i>
                            Informasi Pengiriman
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <div class="info-label">Metode Pengiriman</div>
                            <div class="info-value">
                                <?php 
                                if ($pesanan['metode_pengiriman'] == 'ambil_sendiri') {
                                    echo '<i class="fas fa-store" style="color: var(--secondary);"></i> Ambil di Toko';
                                } else {
                                    echo '<i class="fas fa-truck" style="color: var(--primary);"></i> Dikirim ke Alamat';
                                }
                                ?>
                            </div>
                        </div>

                        <?php if ($pesanan['metode_pengiriman'] == 'kurir' && !empty($pesanan['alamat_lengkap'])) { ?>
                            <div class="info-box">
                                <div class="info-label">Alamat Pengiriman</div>
                                <div class="info-value">
                                    <?php if (!empty($pesanan['label_alamat'])) { ?>
                                        <span style="display: inline-block; background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius); font-size: 0.75rem; margin-bottom: 0.5rem;">
                                            <i class="fas fa-home"></i> <?php echo htmlspecialchars($pesanan['label_alamat']); ?>
                                        </span>
                                    <?php } ?>
                                    
                                    <div style="margin-top: 0.5rem;">
                                        <strong><?php echo htmlspecialchars($pesanan['nama_penerima']); ?></strong>
                                    </div>
                                    
                                    <?php if (!empty($pesanan['telp_penerima'])) { ?>
                                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($pesanan['telp_penerima']); ?>
                                        </div>
                                    <?php } ?>
                                    
                                    <div style="margin-top: 0.75rem; line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($pesanan['alamat_lengkap'])); ?><br>
                                        <?php 
                                        $location_parts = array_filter([
                                            $pesanan['kelurahan'],
                                            $pesanan['kecamatan'],
                                            $pesanan['kota'],
                                            $pesanan['provinsi'],
                                            $pesanan['kode_pos']
                                        ]);
                                        if (!empty($location_parts)) {
                                            echo htmlspecialchars(implode(', ', $location_parts));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php } elseif ($pesanan['metode_pengiriman'] == 'ambil_sendiri') { ?>
                            <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--secondary);">
                                <div style="font-weight: 600; color: var(--secondary); margin-bottom: 0.5rem;">
                                    <i class="fas fa-info-circle"></i> Pembeli akan mengambil pesanan di toko Anda
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Pastikan pesanan sudah dikemas dan siap diambil. Hubungi pembeli jika ada informasi tambahan.
                                </div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($pesanan['catatan_pengiriman'])) { ?>
                            <div class="info-box" style="background: rgba(245, 158, 11, 0.1); border: 2px solid var(--warning);">
                                <div class="info-label" style="color: var(--warning);">
                                    <i class="fas fa-sticky-note"></i> Catatan dari Pembeli
                                </div>
                                <div class="info-value" style="font-style: italic; color: var(--text-primary);">
                                    "<?php echo nl2br(htmlspecialchars($pesanan['catatan_pengiriman'])); ?>"
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-history"></i>
                            Timeline Pesanan
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-title">Pesanan Dibuat</div>
                                    <div class="timeline-date"><?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?> WIB</div>
                                </div>
                            </div>
                            
                            <?php if ($pesanan['status_pesanan'] == 'dibatalkan') { ?>
                            <div class="timeline-item cancelled">
                                <div class="timeline-content" style="background: rgba(239, 68, 68, 0.1); border: 2px solid var(--danger);">
                                    <div class="timeline-title" style="color: var(--danger);">
                                        <i class="fas fa-times-circle"></i> Pesanan Dibatalkan
                                    </div>
                                    <div class="timeline-date">
                                        <?php 
                                        if (!empty($pesanan['tanggal_batal'])) {
                                            echo date('d M Y, H:i', strtotime($pesanan['tanggal_batal'])) . ' WIB';
                                        } else {
                                            echo 'Dibatalkan oleh ' . ucfirst($pesanan['dibatalkan_oleh'] ?? 'penjual');
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php } else { ?>
                            
                            <?php if (in_array($pesanan['status_pesanan'], ['diproses', 'dikirim', 'selesai'])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-title">Pesanan Diproses</div>
                                    <div class="timeline-date">Sedang dikemas oleh penjual</div>
                                </div>
                            </div>
                            <?php } ?>
                            
                            <?php if (in_array($pesanan['status_pesanan'], ['dikirim', 'selesai'])) { ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-title">Pesanan Dikirim</div>
                                    <div class="timeline-date">
                                        <?php echo !empty($pesanan['nomor_resi']) ? 'Resi: ' . $pesanan['nomor_resi'] : 'Dalam perjalanan'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            
                            <?php if ($pesanan['status_pesanan'] == 'selesai') { ?>
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <div class="timeline-title">Pesanan Selesai</div>
                                    <div class="timeline-date">Diterima oleh pembeli</div>
                                </div>
                            </div>
                            <?php } ?>
                            
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Batalkan Pesanan</h3>
                <button type="button" class="modal-close" onclick="closeCancelModal()">&times;</button>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="warning-box-content">
                    <p style="font-weight: 600;">Perhatian!</p>
                    <p>Tindakan ini akan membatalkan pesanan dan:</p>
                    <ul>
                        <li>Mengembalikan stok produk</li>
                        <li>Mengubah status menjadi "Dibatalkan"</li>
                        <li>Tidak dapat diubah kembali</li>
                    </ul>
                </div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-comment-alt"></i>
                        Alasan Pembatalan <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea name="alasan_batal" class="form-textarea" required placeholder="Jelaskan alasan pembatalan pesanan ini..."></textarea>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">
                        Alasan pembatalan akan dikirimkan ke pembeli
                    </small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCancelModal()" style="flex: 1;">
                        <i class="fas fa-times"></i>
                        Batal
                    </button>
                    <button type="submit" name="batalkan_pesanan" class="btn btn-danger" style="flex: 1;">
                        <i class="fas fa-check"></i>
                        Ya, Batalkan Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>
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
        
        // Modal functions
        function openCancelModal() {
            document.getElementById('cancelModal').classList.add('active');
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target == modal) {
                closeCancelModal();
            }
        }
        
        // Auto-hide alert messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideDown 0.3s ease reverse';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>