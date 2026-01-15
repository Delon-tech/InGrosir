<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

// Cek login pembeli
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'pembeli') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pesanan_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$pesanan_id) {
    header("Location: riwayat_pesanan.php?error=" . urlencode("ID pesanan tidak valid"));
    exit();
}

// Ambil data pesanan
$query_pesanan = "SELECT p.*, u.nama_grosir, u.nomor_telepon as no_hp_penjual,
                  mpp.account_number, mpp.account_name, mpp.bank_name, mpp.qr_image,
                  mpt.nama_metode, mpt.tipe_metode, mpt.icon
                  FROM pesanan p
                  JOIN users u ON p.user_id_penjual = u.user_id
                  LEFT JOIN metode_pembayaran_penjual mpp ON p.metode_penjual_id = mpp.metode_penjual_id
                  LEFT JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                  WHERE p.pesanan_id = ? AND p.user_id_pembeli = ?";
$stmt = mysqli_prepare($koneksi, $query_pesanan);
mysqli_stmt_bind_param($stmt, "ii", $pesanan_id, $user_id);
mysqli_stmt_execute($stmt);
$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$pesanan) {
    header("Location: riwayat_pesanan.php?error=" . urlencode("Pesanan tidak ditemukan"));
    exit();
}

// Cek apakah sudah upload bukti
$query_bukti = "SELECT * FROM bukti_pembayaran WHERE pesanan_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt_bukti = mysqli_prepare($koneksi, $query_bukti);
mysqli_stmt_bind_param($stmt_bukti, "i", $pesanan_id);
mysqli_stmt_execute($stmt_bukti);
$bukti_existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bukti));

// Validasi status pesanan
$allowed_statuses = ['pending', 'diproses'];
if ($pesanan['payment_status'] == 'payment_verified' || $pesanan['status_pesanan'] == 'dibatalkan') {
    header("Location: detail_pesanan_pembeli.php?id=$pesanan_id&error=" . urlencode("Pesanan ini tidak dapat diupload bukti pembayaran"));
    exit();
}

$success = '';
$error = '';

// Proses upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_bukti'])) {
    $nama_pengirim = trim($_POST['nama_pengirim']);
    $bank_pengirim = trim($_POST['bank_pengirim']);
    $nomor_rekening = trim($_POST['nomor_rekening']);
    $tanggal_transfer = $_POST['tanggal_transfer'] . ' ' . $_POST['waktu_transfer'];
    $jumlah_transfer = floatval(str_replace([',', '.'], '', $_POST['jumlah_transfer']));
    $catatan = trim($_POST['catatan']);
    
    // Validasi file upload
    if (!isset($_FILES['bukti_image']) || $_FILES['bukti_image']['error'] != UPLOAD_ERR_OK) {
        $error = "Silakan upload bukti pembayaran!";
    } else {
        $file = $_FILES['bukti_image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validasi tipe file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $error = "Format file tidak valid. Hanya JPG, PNG, GIF yang diperbolehkan.";
        } elseif ($file['size'] > $max_size) {
            $error = "Ukuran file terlalu besar. Maksimal 5MB.";
        } else {
            // Upload file
            $upload_dir = 'uploads/bukti_pembayaran/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_name = 'bukti_' . $pesanan_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Hapus bukti lama jika ada
                if ($bukti_existing && file_exists($bukti_existing['bukti_image'])) {
                    unlink($bukti_existing['bukti_image']);
                }
                
                mysqli_begin_transaction($koneksi);
                try {
                    // Insert bukti pembayaran
                    $query_insert = "INSERT INTO bukti_pembayaran 
                                    (pesanan_id, user_id, nama_pengirim, bank_pengirim, nomor_rekening_pengirim, 
                                     tanggal_transfer, jumlah_transfer, bukti_image, catatan, status_verifikasi) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt_insert = mysqli_prepare($koneksi, $query_insert);
                    mysqli_stmt_bind_param($stmt_insert, "iissssdss", 
                        $pesanan_id, $user_id, $nama_pengirim, $bank_pengirim, $nomor_rekening,
                        $tanggal_transfer, $jumlah_transfer, $file_path, $catatan
                    );
                    mysqli_stmt_execute($stmt_insert);
                    $bukti_id = mysqli_insert_id($koneksi);
                    
                    // Update status pesanan
                    $query_update = "UPDATE pesanan 
                                    SET payment_status = 'payment_uploaded', 
                                        payment_proof_id = ?,
                                        is_notified = 0
                                    WHERE pesanan_id = ?";
                    $stmt_update = mysqli_prepare($koneksi, $query_update);
                    mysqli_stmt_bind_param($stmt_update, "ii", $bukti_id, $pesanan_id);
                    mysqli_stmt_execute($stmt_update);

                    // Update status pesanan
                    mysqli_query($koneksi, "UPDATE pesanan SET payment_status='payment_verified' WHERE pesanan_id=$pesanan_id");

                    // NOTIFIKASI: Kirim ke pembeli
                    require_once 'includes/notification_helper.php';
                    kirim_notifikasi(
                        $pesanan['user_id_pembeli'], 
                        '✅ Pembayaran Dikonfirmasi', 
                        "Pembayaran pesanan #$pesanan_id sudah diverifikasi. Pesanan Anda sedang diproses!",
                        "detail_pesanan_pembeli.php?id=$pesanan_id",
                        'check-circle'
                    );
                    
                    // Log payment
                    $query_log = "INSERT INTO payment_log (pesanan_id, bukti_id, action_type, new_status, action_by, notes)
                                 VALUES (?, ?, 'upload', 'payment_uploaded', ?, 'Pembeli mengupload bukti pembayaran')";
                    $stmt_log = mysqli_prepare($koneksi, $query_log);
                    mysqli_stmt_bind_param($stmt_log, "iii", $pesanan_id, $bukti_id, $user_id);
                    mysqli_stmt_execute($stmt_log);
                    
                    // NOTIFIKASI: Kirim ke penjual
                    require_once 'includes/notification_helper.php';
                    kirim_notifikasi(
                        $pesanan['user_id_penjual'], 
                        '💰 Bukti Pembayaran Diupload', 
                        "Pembeli telah mengupload bukti transfer untuk pesanan #$pesanan_id. Segera verifikasi!",
                        "verifikasi_bukti_pembayaran.php?id=$pesanan_id",
                        'credit-card'
                    );

                    mysqli_commit($koneksi);
                    
                    $success = "Bukti pembayaran berhasil diupload! Menunggu verifikasi dari penjual.";
                    
                    // Redirect after 2 seconds
                    header("refresh:2;url=detail_pesanan_pembeli.php?id=$pesanan_id");
                    
                } catch (Exception $e) {
                    mysqli_rollback($koneksi);
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                    $error = "Gagal upload bukti pembayaran: " . $e->getMessage();
                }
            } else {
                $error = "Gagal mengupload file. Periksa permission folder.";
            }
        }
    }
}

function getPaymentIcon($tipe_metode) {
    switch($tipe_metode) {
        case 'transfer_bank': return 'fa-university';
        case 'qris': return 'fa-qrcode';
        case 'ewallet': return 'fa-wallet';
        case 'cod': return 'fa-hand-holding-usd';
        default: return 'fa-credit-card';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Bukti Pembayaran - InGrosir</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-white: #ffffff;
            --bg-gray: #f9fafb;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
        }
        
        .upload-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .upload-container {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .upload-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2.5rem 1.5rem;
            text-align: center;
        }
        
        .upload-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            color: var(--primary);
            animation: pulse 2s ease infinite;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .upload-header h1 {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            margin-bottom: 0.5rem;
            font-weight: 800;
        }
        
        .upload-header p {
            opacity: 0.95;
            font-size: 1rem;
        }
        
        .upload-content {
            padding: 2rem 1.5rem;
        }
        
        .payment-info-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow);
        }
        
        .payment-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-method-icon {
            width: 56px;
            height: 56px;
            background: white;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--primary);
            box-shadow: var(--shadow);
            flex-shrink: 0;
        }
        
        .payment-info-header h3 {
            font-size: clamp(1rem, 2.5vw, 1.125rem);
            margin-bottom: 0.25rem;
            font-weight: 700;
        }
        
        .payment-info-header p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .detail-item {
            background: white;
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .detail-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1rem;
            word-break: break-word;
        }
        
        .detail-value.highlight {
            color: var(--secondary);
            font-size: 1.5rem;
        }
        
        .qr-code-display {
            text-align: center;
            margin: 1.5rem 0;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
        }
        
        .qr-code-display img {
            max-width: 200px;
            width: 100%;
            height: auto;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .section-title {
            font-size: clamp(1rem, 2.5vw, 1.125rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
        }
        
        .section-title i {
            font-size: 1.25rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control,
        .form-select {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-help {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .file-upload-area {
            border: 3px dashed var(--border);
            border-radius: var(--radius-lg);
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-gray);
        }
        
        .file-upload-area:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: var(--secondary);
            background: rgba(16, 185, 129, 0.05);
            transform: scale(1.02);
        }
        
        .file-upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .file-upload-text {
            font-size: clamp(0.875rem, 2vw, 1rem);
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .file-upload-hint {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .file-preview {
            display: none;
            margin-top: 1rem;
            padding: 1.25rem;
            background: white;
            border-radius: var(--radius-lg);
            border: 2px solid var(--secondary);
            box-shadow: var(--shadow);
        }
        
        .file-preview.active {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .preview-image {
            max-width: 150px;
            width: 100%;
            height: auto;
            max-height: 150px;
            border-radius: var(--radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }
        
        .preview-info {
            flex: 1;
            min-width: 150px;
        }
        
        .preview-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            word-break: break-word;
        }
        
        .preview-size {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .btn-remove-file {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.875rem;
        }
        
        .btn-remove-file:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.9375rem;
            transition: var(--transition);
            width: 100%;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-primary:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-gray);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid var(--warning);
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .warning-box i {
            color: var(--warning);
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .warning-content {
            flex: 1;
        }
        
        .warning-content strong {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--warning);
            font-size: 1rem;
        }
        
        .warning-content ul {
            margin-left: 1.25rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            line-height: 1.7;
        }
        
        .warning-content ul li {
            margin-bottom: 0.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 0.5rem;
            }
            
            .upload-wrapper {
                padding: 1rem 0;
            }
            
            .upload-header {
                padding: 2rem 1rem;
            }
            
            .upload-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
            
            .upload-content {
                padding: 1.5rem 1rem;
            }
            
            .payment-info-box {
                padding: 1.25rem;
            }
            
            .payment-method-icon {
                width: 48px;
                height: 48px;
                font-size: 1.5rem;
            }
            
            .file-upload-area {
                padding: 2rem 1rem;
            }
            
            .file-upload-icon {
                font-size: 2.5rem;
            }
            
            .file-preview.active {
                flex-direction: column;
                text-align: center;
            }
            
            .preview-info {
                text-align: center;
            }
            
            .btn-remove-file {
                width: 100%;
            }
            
            .warning-box {
                flex-direction: column;
                text-align: center;
            }
            
            .warning-content ul {
                text-align: left;
            }
        }
        
        @media (max-width: 576px) {
            .upload-header h1 {
                font-size: 1.25rem;
            }
            
            .detail-value.highlight {
                font-size: 1.25rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="upload-wrapper">
        <div class="upload-container">
            <div class="upload-header">
                <div class="upload-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h1>Upload Bukti Pembayaran</h1>
                <p>Pesanan #<?php echo $pesanan_id; ?></p>
            </div>

            <div class="upload-content">
                <?php if ($success) { ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <span><?php echo $success; ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <?php if ($error) { ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span><?php echo $error; ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <!-- Payment Info -->
                <div class="payment-info-box">
                    <div class="payment-info-header">
                        <div class="payment-method-icon">
                            <i class="fas <?php echo getPaymentIcon($pesanan['tipe_metode']); ?>"></i>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($pesanan['nama_metode'] ?? 'Metode Pembayaran'); ?></h3>
                            <p>Transfer ke rekening berikut</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <div class="detail-item">
                                <div class="detail-label">Total Pembayaran</div>
                                <div class="detail-value highlight">
                                    Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($pesanan['account_number'])) { ?>
                        <div class="col-md-6 col-12">
                            <div class="detail-item">
                                <div class="detail-label">Nomor Rekening</div>
                                <div class="detail-value"><?php echo htmlspecialchars($pesanan['account_number']); ?></div>
                            </div>
                        </div>
                        <?php } ?>
                        
                        <?php if (!empty($pesanan['account_name'])) { ?>
                        <div class="col-md-6 col-12">
                            <div class="detail-item">
                                <div class="detail-label">Atas Nama</div>
                                <div class="detail-value"><?php echo htmlspecialchars($pesanan['account_name']); ?></div>
                            </div>
                        </div>
                        <?php } ?>
                        
                        <?php if (!empty($pesanan['bank_name'])) { ?>
                        <div class="col-md-6 col-12">
                            <div class="detail-item">
                                <div class="detail-label">Bank</div>
                                <div class="detail-value"><?php echo htmlspecialchars($pesanan['bank_name']); ?></div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    
                    <?php if (!empty($pesanan['qr_image'])) { ?>
                    <div class="qr-code-display">
                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
                            <strong>Atau scan QR Code di bawah ini:</strong>
                        </p>
                        <img src="uploads/qris/<?php echo htmlspecialchars($pesanan['qr_image']); ?>" alt="QR Code" class="img-fluid">
                    </div>
                    <?php } ?>
                </div>

                <!-- Warning -->
                <div class="warning-box">
                    <i class="fas fa-info-circle"></i>
                    <div class="warning-content">
                        <strong>⚠️ Perhatian Penting!</strong>
                        <ul class="mb-0">
                            <li>Transfer sesuai <strong>NOMINAL EXACT</strong> yang tertera</li>
                            <li>Upload bukti transfer yang <strong>JELAS dan TERBACA</strong></li>
                            <li>Pastikan informasi rekening pengirim <strong>BENAR</strong></li>
                            <li>Bukti akan diverifikasi maksimal <strong>1x24 jam</strong></li>
                        </ul>
                    </div>
                </div>

                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="upload_bukti" value="1">
                    
                    <!-- Informasi Transfer -->
                    <div class="mb-4">
                        <div class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informasi Transfer
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Nama Pengirim <span class="required">*</span>
                                    </label>
                                    <input type="text" name="nama_pengirim" class="form-control" 
                                           placeholder="Nama sesuai rekening pengirim" required
                                           value="<?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>">
                                    <div class="form-help">Nama pemilik rekening yang transfer</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Metode Pembayaran <span class="required">*</span>
                                    </label>
                                    <select name="bank_pengirim" class="form-select" required>
                                        <option value="">Pilih Metode Pembayaran</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BRI">BRI</option>
                                        <option value="BNI">BNI</option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                                        <option value="Qris">QRIS</option>
                                        <option value="COD">COD</option>
                                        <option value="Lainnya">Bank Lainnya</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Nomor Rekening Pengirim
                                    </label>
                                    <input type="text" name="nomor_rekening" class="form-control" 
                                           placeholder="1234567890">
                                    <div class="form-help">Opsional - untuk verifikasi</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Jumlah Transfer <span class="required">*</span>
                                    </label>
                                    <input type="text" name="jumlah_transfer" class="form-control" 
                                           placeholder="<?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?>"
                                           value="<?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?>"
                                           required>
                                    <div class="form-help">Jumlah yang ditransfer</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Tanggal Transfer <span class="required">*</span>
                                    </label>
                                    <input type="date" name="tanggal_transfer" class="form-control" 
                                           max="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        Waktu Transfer <span class="required">*</span>
                                    </label>
                                    <input type="time" name="waktu_transfer" class="form-control" 
                                           value="<?php echo date('H:i'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Catatan (Opsional)</label>
                                    <textarea name="catatan" class="form-control" rows="3" 
                                              placeholder="Catatan tambahan (jika ada)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Bukti -->
                    <div class="mb-4">
                        <div class="section-title">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Upload Bukti Transfer
                        </div>
                        
                        <input type="file" name="bukti_image" id="buktiFile" accept="image/*" style="display: none;" required>
                        
                        <div class="file-upload-area" id="uploadArea" onclick="document.getElementById('buktiFile').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-upload-text">Klik atau Drag & Drop untuk Upload</div>
                            <div class="file-upload-hint">Format: JPG, PNG, GIF (Maksimal 5MB)</div>
                        </div>
                        
                        <div class="file-preview" id="filePreview">
                            <img id="previewImage" class="preview-image" src="" alt="Preview">
                            <div class="preview-info">
                                <div class="preview-name" id="fileName"></div>
                                <div class="preview-size" id="fileSize"></div>
                            </div>
                            <button type="button" class="btn-remove-file" onclick="removeFile()">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="detail_pesanan_pembeli.php?id=<?php echo $pesanan_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Batal
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-check"></i>
                                Upload Bukti
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('buktiFile');
        const filePreview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const submitBtn = document.getElementById('submitBtn');

        // Drag & Drop Events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        // File Input Change
        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (!file) return;

            // Validate file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Format file tidak valid! Hanya JPG, PNG, GIF yang diperbolehkan.');
                fileInput.value = '';
                return;
            }

            // Validate file size (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Ukuran file terlalu besar! Maksimal 5MB.');
                fileInput.value = '';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('active');
                uploadArea.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }

        function removeFile() {
            fileInput.value = '';
            filePreview.classList.remove('active');
            uploadArea.style.display = 'block';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Format currency input
        const jumlahInput = document.querySelector('input[name="jumlah_transfer"]');
        jumlahInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = formatRupiah(value);
        });

        function formatRupiah(angka) {
            return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Form validation before submit
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('⚠️ Silakan upload bukti pembayaran terlebih dahulu!');
                return false;
            }

            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
            
            return true;
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>