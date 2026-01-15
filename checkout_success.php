<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil parameter dari URL jika ada
$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : null;
$total = isset($_GET['total']) ? floatval($_GET['total']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - InGrosir</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .success-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-header {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .success-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            position: relative;
            z-index: 1;
            animation: scaleIn 0.5s ease 0.3s backwards;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .success-icon i {
            font-size: 3rem;
            color: var(--success);
            animation: checkMark 0.5s ease 0.5s backwards;
        }
        
        @keyframes checkMark {
            0% {
                transform: scale(0) rotate(-45deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(-45deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }
        
        .success-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .success-header p {
            font-size: 1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .success-body {
            padding: 2rem;
        }
        
        .order-info {
            background: var(--bg-gray);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .order-info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .order-info-row:last-child {
            border-bottom: none;
        }
        
        .order-info-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .order-info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .order-info-value.highlight {
            color: var(--success);
            font-size: 1.25rem;
        }
        
        .info-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            text-align: center;
            padding: 1.5rem 1rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
            transition: var(--transition);
        }
        
        .info-card:hover {
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-5px);
        }
        
        .info-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .info-card-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .info-card-value {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .next-steps {
            background: rgba(37, 99, 235, 0.05);
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .next-steps h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .next-steps ol {
            margin-left: 1.5rem;
            line-height: 1.8;
        }
        
        .next-steps li {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
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
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-gray);
        }
        
        .support-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }
        
        .support-info p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .support-info a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .support-info a:hover {
            text-decoration: underline;
        }
        
        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: var(--primary);
            position: absolute;
            animation: confetti-fall 3s linear;
        }
        
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .success-header {
                padding: 2rem 1.5rem;
            }
            
            .success-header h1 {
                font-size: 1.5rem;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
            }
            
            .success-icon i {
                font-size: 2.5rem;
            }
            
            .success-body {
                padding: 1.5rem;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1>Pesanan Berhasil Dibuat!</h1>
            <p>Terima kasih atas kepercayaan Anda berbelanja di InGrosir</p>
        </div>
        
        <div class="success-body">
            <?php if ($order_id) { ?>
            <div class="order-info">
                <div class="order-info-row">
                    <span class="order-info-label">ID Transaksi</span>
                    <span class="order-info-value"><?php echo $order_id; ?></span>
                </div>
                <div class="order-info-row">
                    <span class="order-info-label">Tanggal Pesanan</span>
                    <span class="order-info-value"><?php echo date('d M Y, H:i'); ?></span>
                </div>
                <?php if ($total > 0) { ?>
                <div class="order-info-row">
                    <span class="order-info-label">Total Pembayaran</span>
                    <span class="order-info-value highlight">Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
            
            <div class="info-cards">
                <div class="info-card">
                    <i class="fas fa-clock"></i>
                    <div class="info-card-label">Status</div>
                    <div class="info-card-value">Menunggu</div>
                </div>
                <div class="info-card">
                    <i class="fas fa-bell"></i>
                    <div class="info-card-label">Notifikasi</div>
                    <div class="info-card-value">Aktif</div>
                </div>
                <div class="info-card">
                    <i class="fas fa-shield-alt"></i>
                    <div class="info-card-label">Keamanan</div>
                    <div class="info-card-value">Terjamin</div>
                </div>
            </div>
            
            <div class="next-steps">
                <h3>
                    <i class="fas fa-list-check"></i>
                    Langkah Selanjutnya
                </h3>
                <ol>
                    <li>Pesanan Anda sedang menunggu konfirmasi dari penjual</li>
                    <li>Anda akan menerima notifikasi setelah pesanan dikonfirmasi</li>
                    <li>Pantau status pesanan Anda di halaman Riwayat Pesanan</li>
                    <li>Hubungi penjual jika ada pertanyaan terkait pesanan Anda</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <a href="riwayat_pesanan.php" class="btn btn-primary">
                    <i class="fas fa-history"></i>
                    Lihat Riwayat Pesanan
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-store"></i>
                    Jelajahi Toko Lain
                </a>
            </div>
            
            <div class="support-info">
                <p>Butuh bantuan? Hubungi kami di:</p>
                <a href="mailto:support@ingrosir.com">
                    <i class="fas fa-envelope"></i> support@ingrosir.com
                </a>
                <span style="margin: 0 0.5rem; color: var(--border);">|</span>
                <a href="tel:+6281234567890">
                    <i class="fas fa-phone"></i> +62 812-3456-7890
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Confetti effect
        function createConfetti() {
            const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * window.innerWidth + 'px';
                    confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                    confetti.style.animationDelay = (Math.random() * 0.5) + 's';
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 5000);
                }, i * 30);
            }
        }
        
        // Play confetti on load
        window.addEventListener('load', () => {
            createConfetti();
        });
        
        // Auto redirect after 10 seconds (optional)
        // setTimeout(() => {
        //     window.location.href = 'riwayat_pesanan.php';
        // }, 10000);
    </script>
</body>
</html>