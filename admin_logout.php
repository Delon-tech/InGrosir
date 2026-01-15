<?php
session_start();

// Simpan info admin sebelum logout (untuk logging)
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Cek apakah user memang sedang login
if (!$admin_id) {
    header("Location: admin_login.php");
    exit();
}

// Opsional: Simpan log aktivitas logout
// include 'config/koneksi.php';
// $koneksi = connectDB();
// $log_query = "INSERT INTO activity_logs (user_id, action, timestamp) VALUES (?, 'logout', NOW())";
// $stmt = mysqli_prepare($koneksi, $log_query);
// mysqli_stmt_bind_param($stmt, "i", $admin_id);
// mysqli_stmt_execute($stmt);

// Hapus semua session variables
$_SESSION = array();

// Hapus session cookie jika ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Tampilkan halaman logout dengan redirect otomatis
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - InGrosir Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="refresh" content="3;url=admin_login.php">
    
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #10b981;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-lg: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><circle cx="30" cy="30" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
        }
        
        .logout-container {
            position: relative;
            z-index: 1;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            max-width: 500px;
            width: 100%;
            text-align: center;
            padding: 3rem 2rem;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 3rem;
            animation: bounce 0.6s ease;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        .logout-icon i {
            animation: rotate 0.6s ease;
        }
        
        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .logout-message {
            color: var(--text-secondary);
            font-size: 1.125rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .admin-name {
            font-weight: 700;
            color: var(--primary);
        }
        
        .loading-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin: 2rem 0;
        }
        
        .loading-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 10px;
            animation: loading 3s ease-in-out;
        }
        
        @keyframes loading {
            from {
                width: 0%;
            }
            to {
                width: 100%;
            }
        }
        
        .redirect-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e5e7eb;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 300ms ease;
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
            background: #1e40af;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .security-note {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
            text-align: left;
        }
        
        .security-note i {
            color: var(--primary);
            margin-top: 0.125rem;
        }
        
        .security-note p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        @media (max-width: 640px) {
            .logout-container {
                padding: 2rem 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .logout-message {
                font-size: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Logout Berhasil!</h1>
        
        <p class="logout-message">
            Terima kasih <span class="admin-name"><?php echo htmlspecialchars($admin_name); ?></span>,<br>
            Anda telah berhasil keluar dari panel administrasi.
        </p>
        
        <div class="loading-bar">
            <div class="loading-progress"></div>
        </div>
        
        <div class="redirect-info">
            <div class="spinner"></div>
            <span>Mengalihkan ke halaman login dalam 3 detik...</span>
        </div>
        
        <div class="action-buttons">
            <a href="admin_login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Login Kembali
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Ke Beranda
            </a>
        </div>
        
        <div class="security-note">
            <i class="fas fa-shield-alt"></i>
            <div>
                <p>
                    <strong>Tips Keamanan:</strong><br>
                    Pastikan untuk selalu logout setelah selesai menggunakan panel admin, 
                    terutama jika Anda menggunakan komputer bersama atau publik.
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Countdown timer
        let seconds = 3;
        const redirectInfo = document.querySelector('.redirect-info span');
        
        const countdown = setInterval(function() {
            seconds--;
            if (seconds > 0) {
                redirectInfo.textContent = `Mengalihkan ke halaman login dalam ${seconds} detik...`;
            } else {
                redirectInfo.textContent = 'Mengalihkan...';
                clearInterval(countdown);
            }
        }, 1000);
        
        // Konfirmasi jika user mencoba kembali
        window.addEventListener('popstate', function(e) {
            e.preventDefault();
            if (confirm('Anda sudah logout. Ingin login kembali?')) {
                window.location.href = 'admin_login.php';
            }
        });
        
        // Prevent back button after logout
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>