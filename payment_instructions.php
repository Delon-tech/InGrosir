<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validasi parameter - GANTI 'metode' jadi 'template'
if (!isset($_GET['order_id']) || !isset($_GET['template']) || !isset($_GET['total'])) {
    header("Location: riwayat_pesanan.php");
    exit();
}

$order_id = trim($_GET['order_id']);
$template_id = intval($_GET['template']); // GANTI dari metode_id
$total_harga = floatval($_GET['total']);
$user_id = $_SESSION['user_id'];

// Validasi nominal pembayaran
if ($total_harga <= 0) {
    header("Location: riwayat_pesanan.php");
    exit();
}

// Ambil pesanan IDs dan validasi ownership
$order_ids_array = array_filter(array_map('trim', explode(',', $order_id)));
if (empty($order_ids_array)) {
    header("Location: riwayat_pesanan.php");
    exit();
}

$pesanan_data = [];
$total_pesanan = 0;
$seller_payment_details = []; // Untuk menyimpan detail pembayaran per seller

foreach ($order_ids_array as $oid) {
    // Ambil data pesanan dan detail metode pembayaran penjual
    $query_pesanan = "SELECT p.pesanan_id, p.total_harga, p.tanggal_pesanan, p.user_id_penjual,
                      u.nama_grosir, u.nomor_telepon as no_hp_penjual,
                      mpp.account_number, mpp.account_name, mpp.bank_name, mpp.qr_image, mpp.notes as payment_notes,
                      mpt.nama_metode, mpt.tipe_metode, mpt.icon
                      FROM pesanan p
                      JOIN users u ON p.user_id_penjual = u.user_id
                      JOIN metode_pembayaran_penjual mpp ON p.metode_penjual_id = mpp.metode_penjual_id
                      JOIN metode_pembayaran_template mpt ON mpp.template_id = mpt.template_id
                      WHERE p.transaction_id = ? AND p.user_id_pembeli = ?";
    $stmt_pesanan = mysqli_prepare($koneksi, $query_pesanan);
    mysqli_stmt_bind_param($stmt_pesanan, "si", $oid, $user_id);
    mysqli_stmt_execute($stmt_pesanan);
    $result = mysqli_stmt_get_result($stmt_pesanan);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $pesanan_data[] = $row;
        $total_pesanan += $row['total_harga'];
        
        // Simpan detail pembayaran per seller (deduplikasi)
        if (!isset($seller_payment_details[$row['user_id_penjual']])) {
            $seller_payment_details[$row['user_id_penjual']] = [
                'nama_grosir' => $row['nama_grosir'],
                'no_hp' => $row['no_hp_penjual'],
                'nama_metode' => $row['nama_metode'],
                'tipe_metode' => $row['tipe_metode'],
                'icon' => $row['icon'],
                'account_number' => $row['account_number'],
                'account_name' => $row['account_name'],
                'bank_name' => $row['bank_name'],
                'qr_image' => $row['qr_image'],
                'payment_notes' => $row['payment_notes']
            ];
        }
    }
}

// Validasi: pastikan ada pesanan
if (empty($pesanan_data)) {
    header("Location: riwayat_pesanan.php");
    exit();
}

// Ambil data pembeli
$query_pembeli = "SELECT nama_lengkap, email, nomor_telepon as no_hp FROM users WHERE user_id = ?";
$stmt_pembeli = mysqli_prepare($koneksi, $query_pembeli);
mysqli_stmt_bind_param($stmt_pembeli, "i", $user_id);
mysqli_stmt_execute($stmt_pembeli);
$pembeli = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pembeli));

// Function untuk mendapatkan icon metode
function getPaymentIcon($tipe_metode) {
    switch($tipe_metode) {
        case 'transfer_bank': return 'fa-university';
        case 'qris': return 'fa-qrcode';
        case 'ewallet': return 'fa-wallet';
        case 'cod': return 'fa-hand-holding-usd';
        default: return 'fa-credit-card';
    }
}

// Function untuk format currency
function formatCurrency($value) {
    return 'Rp ' . number_format($value, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Pembayaran - InGrosir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-white: #ffffff;
            --bg-gray: #f9fafb;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --radius-lg: 12px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .payment-container {
            max-width: 700px;
            width: 100%;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .payment-header {
            background: var(--secondary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            color: var(--secondary);
            animation: scaleIn 0.5s ease 0.3s backwards;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        
        .payment-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .payment-header p {
            opacity: 0.9;
        }
        
        .payment-content {
            padding: 2rem;
        }
        
        .amount-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .amount-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .amount-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            word-break: break-word;
        }
        
        .copy-btn {
            background: white;
            color: var(--primary);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid var(--warning);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .warning-box strong {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--warning);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .warning-box p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .timer {
            text-align: center;
            font-size: 0.875rem;
            color: var(--danger);
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .seller-payment-section {
            background: var(--bg-gray);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .seller-payment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        
        .seller-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .seller-info-header {
            flex: 1;
        }
        
        .seller-name-title {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .seller-contact {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .payment-method-info {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        
        .method-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .method-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-gray);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary);
        }
        
        .method-name {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .payment-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }
        
        .payment-detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .qris-image {
            max-width: 250px;
            margin: 1rem auto;
            display: block;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .payment-notes {
            background: rgba(59, 130, 246, 0.1);
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .order-info {
            background: var(--bg-gray);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }
        
        .order-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }
        
        .order-info-row:last-child {
            margin-bottom: 0;
        }
        
        .order-info-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .order-ids {
            color: var(--primary);
            font-weight: 600;
        }
        
        .action-buttons {
            display: grid;
            gap: 1rem;
        }
        
        .btn {
            width: 100%;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
            transition: 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-gray);
        }
        
        .print-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            margin-top: 1rem;
            background: var(--bg-gray);
        }
        
        @media (max-width: 640px) {
            .payment-container {
                margin: 0;
            }
            
            .payment-header h1 {
                font-size: 1.5rem;
            }
            
            .amount-value {
                font-size: 2rem;
            }
        }
        
        @media print {
            body {
                background: white;
            }
            .payment-container {
                box-shadow: none;
            }
            .action-buttons {
                display: none;
            }
            .timer {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <!-- Header -->
        <div class="payment-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1>Pesanan Berhasil Dibuat!</h1>
            <p>Silakan selesaikan pembayaran Anda</p>
        </div>

        <!-- Content -->
        <div class="payment-content">
            <!-- Amount Card -->
            <div class="amount-card">
                <div class="amount-label">Total Pembayaran</div>
                <div class="amount-value" id="totalAmount">
                    <?php echo formatCurrency($total_harga); ?>
                </div>
                <button class="copy-btn" onclick="copyAmount()" title="Salin nominal pembayaran">
                    <i class="fas fa-copy"></i>
                    <span id="copyText">Salin Nominal</span>
                </button>
            </div>

            <!-- Warning -->
            <div class="warning-box">
                <strong>
                    <i class="fas fa-clock"></i>
                    Batas Waktu Pembayaran
                </strong>
                <p>Selesaikan pembayaran dalam <strong>24 jam</strong>. Pesanan akan dibatalkan otomatis jika batas waktu terlampaui.</p>
                <div class="timer" id="timer">
                    <i class="fas fa-hourglass-end"></i>
                    <span id="countdown">Sedang menghitung...</span>
                </div>
            </div>

            <!-- Payment Details per Seller -->
            <?php foreach ($seller_payment_details as $seller_id => $payment_detail) { ?>
                <div class="seller-payment-section">
                    <div class="seller-payment-header">
                        <div class="seller-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="seller-info-header">
                            <div class="seller-name-title"><?php echo htmlspecialchars($payment_detail['nama_grosir']); ?></div>
                            <?php if (!empty($payment_detail['no_hp'])) { ?>
                                <div class="seller-contact">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($payment_detail['no_hp']); ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    
                    <div class="payment-method-info">
                        <div class="method-header">
                            <div class="method-icon">
                                <i class="fas <?php echo getPaymentIcon($payment_detail['tipe_metode']); ?>"></i>
                            </div>
                            <div class="method-name"><?php echo htmlspecialchars($payment_detail['nama_metode']); ?></div>
                        </div>
                        
                        <?php if ($payment_detail['tipe_metode'] == 'qris' && !empty($payment_detail['qr_image'])) { ?>
                            <img src="uploads/qris/<?php echo htmlspecialchars($payment_detail['qr_image']); ?>" 
                                 alt="QRIS Code" 
                                 class="qris-image"
                                 onerror="this.style.display='none'">
                            <div style="text-align: center; font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                <i class="fas fa-mobile-alt"></i> Scan QR code di atas
                            </div>
                        <?php } ?>
                        
                        <?php if (!empty($payment_detail['account_number'])) { ?>
                            <div class="payment-detail-row">
                                <span class="detail-label">
                                    <?php echo $payment_detail['tipe_metode'] == 'transfer_bank' ? 'Nomor Rekening' : 'Nomor Akun'; ?>
                                </span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment_detail['account_number']); ?></span>
                            </div>
                        <?php } ?>
                        
                        <?php if (!empty($payment_detail['account_name'])) { ?>
                            <div class="payment-detail-row">
                                <span class="detail-label">Atas Nama</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment_detail['account_name']); ?></span>
                            </div>
                        <?php } ?>
                        
                        <?php if (!empty($payment_detail['bank_name'])) { ?>
                            <div class="payment-detail-row">
                                <span class="detail-label">Bank</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment_detail['bank_name']); ?></span>
                            </div>
                        <?php } ?>
                        
                        <?php if (!empty($payment_detail['payment_notes'])) { ?>
                            <div class="payment-notes">
                                <strong><i class="fas fa-info-circle"></i> Catatan:</strong><br>
                                <?php echo nl2br(htmlspecialchars($payment_detail['payment_notes'])); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <!-- Order Info -->
            <div class="order-info">
                <div class="order-info-row">
                    <span class="order-info-label"><i class="fas fa-receipt"></i> ID Pesanan:</span>
                    <span class="order-ids"><?php echo implode(', ', array_column($pesanan_data, 'pesanan_id')); ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-info-label"><i class="fas fa-calendar"></i> Tanggal:</span>
                    <span><?php echo date('d M Y, H:i'); ?> WIB</span>
                </div>
                <div class="order-info-row">
                    <span class="order-info-label"><i class="fas fa-user"></i> Pembeli:</span>
                    <span><?php echo htmlspecialchars($pembeli['nama_lengkap']); ?></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="riwayat_pesanan.php" class="btn btn-primary">
                    <i class="fas fa-history"></i>
                    Lihat Riwayat Pesanan
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Kembali ke Beranda
                </a>
                <button class="btn print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Cetak Halaman
                </button>
            </div>
        </div>
    </div>

    <script>
        // Copy amount function
        function copyAmount() {
            const amount = '<?php echo (int)$total_harga; ?>';
            navigator.clipboard.writeText(amount).then(function() {
                const copyBtn = document.getElementById('copyText');
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = 'Tersalin!';
                
                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                }, 2000);
            }).catch(function() {
                alert('Gagal menyalin. Silakan salin manual: ' + amount);
            });
        }

        // Countdown timer (24 jam)
        function startCountdown() {
            const endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
            
            const timer = setInterval(function() {
                const now = new Date().getTime();
                const distance = endTime - now;
                
                if (distance < 0) {
                    clearInterval(timer);
                    document.getElementById('countdown').innerHTML = 'Waktu pembayaran telah habis';
                    return;
                }
                
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').innerHTML = 
                    hours + 'j ' + minutes + 'm ' + seconds + 'd tersisa';
            }, 1000);
        }

        // Start countdown on page load
        startCountdown();
    </script>
</body>
</html>