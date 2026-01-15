<?php
include 'config/koneksi.php';
include 'config/email_config.php';
$koneksi = connectDB();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    $query = "SELECT user_id, nama_lengkap FROM users WHERE email = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_bind_result($stmt, $user_id, $nama_lengkap);
        mysqli_stmt_fetch($stmt);
        
        $token = bin2hex(random_bytes(50));

        $delete_old = "DELETE FROM password_resets WHERE email = ?";
        $delete_stmt = mysqli_prepare($koneksi, $delete_old);
        mysqli_stmt_bind_param($delete_stmt, "s", $email);
        mysqli_stmt_execute($delete_stmt);

        $insert_query = "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())";
        $insert_stmt = mysqli_prepare($koneksi, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ss", $email, $token);
        mysqli_stmt_execute($insert_stmt);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $path_project = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
        $reset_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $path_project . "atur_ulang_password.php?token=" . $token;
        
        $email_sent = sendResetPasswordEmail($email, $nama_lengkap, $reset_link);
        
        if ($email_sent) {
            $message = "Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
            $message_type = 'success';
        } else {
            $message = "Email gagal terkirim. Untuk sementara, gunakan link berikut: <br><br><strong>" . $reset_link . "</strong><br><br>Link ini akan kedaluwarsa dalam 1 jam.";
            $message_type = 'warning';
        }
        
    } else {
        $message = "Email tidak terdaftar dalam sistem kami.";
        $message_type = 'error';
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - InGrosir</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-gray: #f9fafb;
            --border: #e5e7eb;
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
            position: relative;
            overflow-x: hidden;
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
            animation: backgroundMove 20s linear infinite;
        }
        
        @keyframes backgroundMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
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
        
        .forgot-wrapper {
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: clamp(0.8rem, 2vw, 0.875rem);
        }
        
        .back-link:hover {
            gap: 0.75rem;
            color: white;
        }
        
        .forgot-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .logo-icon {
            width: clamp(60px, 15vw, 80px);
            height: clamp(60px, 15vw, 80px);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: clamp(1.75rem, 5vw, 2rem);
        }
        
        .logo h1 {
            font-size: clamp(1.75rem, 5vw, 2rem);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .logo p {
            color: var(--text-secondary);
            font-size: clamp(0.8rem, 2vw, 0.875rem);
            margin: 0;
        }
        
        .alert {
            animation: slideDown 0.3s ease;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .form-control {
            padding-left: 3rem;
            border: 2px solid var(--border);
            font-family: 'Inter', sans-serif;
            font-size: clamp(0.875rem, 2vw, 1rem);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        
        .info-box {
            background: var(--bg-gray);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .step-item {
            background: var(--bg-gray);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .step-item:hover {
            background: rgba(37, 99, 235, 0.05);
            transform: translateX(5px);
        }
        
        .step-number {
            min-width: 32px;
            min-height: 32px;
            width: clamp(28px, 7vw, 32px);
            height: clamp(28px, 7vw, 32px);
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: clamp(0.75rem, 2vw, 0.875rem);
            flex-shrink: 0;
        }
        
        .step-content h4 {
            font-size: clamp(0.8rem, 2vw, 0.875rem);
            margin-bottom: 0.25rem;
        }
        
        .step-content p {
            font-size: clamp(0.7rem, 1.8vw, 0.75rem);
            color: var(--text-secondary);
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-11 col-md-8 col-lg-6 col-xl-5">
                <div class="forgot-wrapper">
                    <div class="mb-3 mb-md-4">
                        <a href="login.php" class="back-link">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Login
                        </a>
                    </div>
                    
                    <div class="forgot-container p-4 p-md-5">
                        <div class="logo text-center mb-4">
                            <div class="logo-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h1>Lupa Password?</h1>
                            <p>Jangan khawatir, kami akan membantu Anda</p>
                        </div>

                        <?php if ($message) { ?>
                            <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : ($message_type == 'error' ? 'danger' : 'warning'); ?> d-flex align-items-start" role="alert">
                                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'error' ? 'exclamation-circle' : 'exclamation-triangle'); ?> me-2 mt-1"></i>
                                <div><?php echo $message; ?></div>
                            </div>
                        <?php } else { ?>
                            <div class="info-box p-3 mb-4">
                                <h6 class="mb-2 small fw-semibold"><i class="fas fa-info-circle me-1"></i> Cara Reset Password</h6>
                                <p class="mb-0 small text-muted">Masukkan alamat email yang terdaftar. Kami akan mengirimkan link untuk mereset password Anda.</p>
                            </div>
                        <?php } ?>
                        
                        <?php if (!$message || $message_type == 'error') { ?>
                        <form action="lupa_password.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Alamat Email</label>
                                <div class="position-relative">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input 
                                        type="email" 
                                        class="form-control form-control-lg"
                                        id="email" 
                                        name="email" 
                                        placeholder="nama@email.com" 
                                        required 
                                        autofocus
                                    >
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-paper-plane"></i>
                                <span>Kirim Link Reset</span>
                            </button>
                        </form>
                        <?php } ?>

                        <?php if ($message_type == 'success') { ?>
                        <div class="mt-4">
                            <h6 class="mb-3 small fw-semibold">Langkah Selanjutnya:</h6>
                            
                            <div class="step-item d-flex align-items-start gap-3 p-3 mb-2">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4>Cek Email Anda</h4>
                                    <p>Periksa inbox atau folder spam untuk email dari InGrosir</p>
                                </div>
                            </div>
                            
                            <div class="step-item d-flex align-items-start gap-3 p-3 mb-2">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4>Klik Link Reset</h4>
                                    <p>Buka email dan klik tombol/link "Reset Password"</p>
                                </div>
                            </div>
                            
                            <div class="step-item d-flex align-items-start gap-3 p-3 mb-4">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4>Buat Password Baru</h4>
                                    <p>Masukkan password baru yang kuat dan mudah Anda ingat</p>
                                </div>
                            </div>
                            
                            <a href="login.php" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-sign-in-alt"></i>
                                Kembali ke Login
                            </a>
                        </div>
                        <?php } ?>

                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="mb-2 small text-muted">Ingat password Anda? <a href="login.php" class="text-decoration-none fw-semibold" style="color: var(--primary);">Masuk di sini</a></p>
                            <p class="mb-0 small text-muted">Belum punya akun? <a href="register.php" class="text-decoration-none fw-semibold" style="color: var(--primary);">Daftar sekarang</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>