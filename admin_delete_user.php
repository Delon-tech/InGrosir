<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    $_SESSION['error'] = "ID pengguna tidak valid!";
    header("Location: admin_users.php");
    exit();
}

// Cek apakah user exists dan ambil datanya untuk logging
$check_query = "SELECT user_id, nama_lengkap, email, peran FROM users WHERE user_id = ?";
$stmt_check = mysqli_prepare($koneksi, $check_query);
mysqli_stmt_bind_param($stmt_check, "i", $user_id);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    $_SESSION['error'] = "Pengguna tidak ditemukan!";
    header("Location: admin_users.php");
    exit();
}

// Cegah admin menghapus dirinya sendiri
if ($user_id == $_SESSION['admin_id']) {
    $_SESSION['error'] = "Anda tidak dapat menghapus akun Anda sendiri!";
    header("Location: admin_users.php");
    exit();
}

// Konfirmasi final dengan GET parameter
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // Tampilkan halaman konfirmasi
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Hapus Pengguna - InGrosir Admin</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <style>
            :root {
                --primary: #2563eb;
                --danger: #ef4444;
                --text-primary: #1f2937;
                --text-secondary: #6b7280;
                --bg-gray: #f9fafb;
                --border: #e5e7eb;
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
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
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
            }
            
            .confirm-container {
                background: white;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-xl);
                max-width: 500px;
                width: 100%;
                overflow: hidden;
                animation: slideUp 0.3s ease;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .confirm-header {
                background: var(--danger);
                color: white;
                padding: 2rem;
                text-align: center;
            }
            
            .confirm-icon {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 2.5rem;
            }
            
            .confirm-header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .confirm-body {
                padding: 2rem;
            }
            
            .user-info {
                background: var(--bg-gray);
                padding: 1.5rem;
                border-radius: var(--radius);
                margin-bottom: 1.5rem;
            }
            
            .user-info-item {
                display: flex;
                justify-content: space-between;
                padding: 0.5rem 0;
                border-bottom: 1px solid var(--border);
            }
            
            .user-info-item:last-child {
                border-bottom: none;
            }
            
            .user-info-label {
                color: var(--text-secondary);
                font-size: 0.875rem;
            }
            
            .user-info-value {
                font-weight: 600;
                color: var(--text-primary);
            }
            
            .warning-box {
                background: #fef2f2;
                border: 2px solid #fecaca;
                border-radius: var(--radius);
                padding: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .warning-box ul {
                margin-left: 1.5rem;
                color: #991b1b;
                font-size: 0.875rem;
            }
            
            .warning-box ul li {
                margin: 0.5rem 0;
            }
            
            .confirm-actions {
                display: flex;
                gap: 1rem;
            }
            
            .btn {
                flex: 1;
                padding: 1rem;
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
                font-size: 0.9375rem;
            }
            
            .btn-danger {
                background: var(--danger);
                color: white;
            }
            
            .btn-danger:hover {
                background: #dc2626;
                transform: translateY(-2px);
                box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
            }
            
            .btn-secondary {
                background: var(--bg-gray);
                color: var(--text-primary);
            }
            
            .btn-secondary:hover {
                background: #e5e7eb;
            }
            
            @media (max-width: 640px) {
                .confirm-actions {
                    flex-direction: column-reverse;
                }
            }
        </style>
    </head>
    <body>
        <div class="confirm-container">
            <div class="confirm-header">
                <div class="confirm-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Konfirmasi Penghapusan</h1>
                <p>Tindakan ini tidak dapat dibatalkan!</p>
            </div>
            
            <div class="confirm-body">
                <div class="user-info">
                    <div class="user-info-item">
                        <span class="user-info-label">Nama</span>
                        <span class="user-info-value"><?php echo htmlspecialchars($user['nama_lengkap']); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="user-info-label">Email</span>
                        <span class="user-info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="user-info-label">Peran</span>
                        <span class="user-info-value"><?php echo ucfirst($user['peran']); ?></span>
                    </div>
                </div>
                
                <div class="warning-box">
                    <strong style="color: #991b1b; display: block; margin-bottom: 0.5rem;">
                        <i class="fas fa-exclamation-circle"></i> Peringatan:
                    </strong>
                    <ul>
                        <li>Semua data pengguna akan dihapus permanen</li>
                        <li>Riwayat aktivitas akan hilang</li>
                        <?php if ($user['peran'] == 'penjual') { ?>
                        <li>Produk yang terkait akan terpengaruh</li>
                        <?php } ?>
                        <li>Tindakan ini TIDAK DAPAT dibatalkan</li>
                    </ul>
                </div>
                
                <div class="confirm-actions">
                    <a href="admin_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Batal
                    </a>
                    <a href="?id=<?php echo $user_id; ?>&confirm=yes" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Ya, Hapus Pengguna
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Proses penghapusan setelah konfirmasi
// Simpan log aktivitas sebelum menghapus
$log_message = "Admin menghapus pengguna: " . $user['nama_lengkap'] . " (Email: " . $user['email'] . ", Peran: " . $user['peran'] . ")";
// Anda bisa menyimpan ke tabel activity_logs jika ada

// Hapus data terkait terlebih dahulu jika diperlukan
if ($user['peran'] == 'penjual') {
    // Opsional: Hapus produk penjual atau set user_id = NULL
    // mysqli_query($koneksi, "DELETE FROM produk WHERE user_id = $user_id");
    // atau
    mysqli_query($koneksi, "UPDATE produk SET user_id = NULL WHERE user_id = $user_id");
}

// Hapus user
$delete_query = "DELETE FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($koneksi, $delete_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "Pengguna <strong>" . htmlspecialchars($user['nama_lengkap']) . "</strong> berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus pengguna: " . mysqli_error($koneksi);
}

mysqli_stmt_close($stmt);
header("Location: admin_users.php");
exit();
?>