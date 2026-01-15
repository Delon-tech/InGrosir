<?php
session_start();
include 'config/koneksi.php';
include 'config/helpers.php';
$koneksi = connectDB();

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['peran'] == 'penjual') {
        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Token CSRF tidak valid. Silakan coba lagi.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
    
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            if (password_verify($password, $user['password'])) {
                if ($user['peran'] == 'penjual' && $user['status_verifikasi'] != 'approved') {
                    if ($user['status_verifikasi'] == 'pending') {
                        $error = "Akun Anda masih menunggu verifikasi dari admin. Kami akan meninjau akun Anda dalam 1-3 hari kerja. Silakan cek email Anda secara berkala untuk update status verifikasi.";
                    } else if ($user['status_verifikasi'] == 'rejected') {
                        $catatan = !empty($user['catatan_verifikasi']) ? $user['catatan_verifikasi'] : "Tidak ada catatan dari admin.";
                        $error = "Maaf, pendaftaran toko Anda ditolak oleh admin. Alasan: " . $catatan;
                    }
                } else {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['nama_grosir'] = $user['nama_grosir'];
                    $_SESSION['peran'] = $user['peran'];
                    $_SESSION['gambar_toko'] = $user['gambar_toko'];

                    if ($user['peran'] == 'penjual') {
                        header("Location: dashboard.php");
                    } else { 
                        header("Location: index.php");
                    }
                    exit();
                }
            } else {
                $error = "Password yang Anda masukkan salah. Silakan periksa kembali.";
            }
        } else {
            $error = "Email tidak terdaftar. Pastikan Anda sudah mendaftar terlebih dahulu.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - InGrosir</title>
    
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

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .login-wrapper {
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
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
            color: white;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        .logo h1 {
            font-size: clamp(2rem, 5vw, 2.5rem);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }
        
        .logo p {
            color: var(--text-secondary);
            font-size: clamp(0.8rem, 2vw, 0.875rem);
        }
        
        .alert {
            animation: shake 0.5s ease;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
            pointer-events: none;
            z-index: 1;
        }
        
        .form-control {
            padding-left: 3rem;
            padding-right: 3.5rem;
            border: 2px solid var(--border);
            font-family: 'Inter', sans-serif;
            font-size: clamp(0.875rem, 2vw, 1rem);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.3s ease;
            font-size: 1.125rem;
            z-index: 2;
            border-radius: 4px;
        }
        
        .password-toggle:hover {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            transform: none;
        }
        
        .admin-login-section {
            border-top: 2px dashed var(--border);
        }
        
        .admin-login-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: clamp(0.8rem, 2vw, 0.875rem);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: rgba(37, 99, 235, 0.05);
            border: 2px solid transparent;
        }
        
        .admin-login-link:hover {
            background: rgba(37, 99, 235, 0.1);
            border-color: var(--primary);
            transform: translateY(-2px);
            color: var(--primary);
        }
        
        .feature-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-gray);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-3px);
        }
        
        .feature-item i {
            font-size: clamp(1.25rem, 3vw, 1.5rem);
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .feature-item p {
            font-size: clamp(0.7rem, 2vw, 0.75rem);
            color: var(--text-secondary);
            font-weight: 500;
            margin: 0;
        }

        .btn.loading {
            position: relative;
            color: transparent;
            pointer-events: none;
        }
        
        .btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .back-link {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }

            .admin-login-link {
                padding: 0.6rem 1rem;
            }

            .feature-item {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-11 col-md-8 col-lg-6 col-xl-5">
                <div class="login-wrapper">
                    <div class="mb-3 mb-md-4">
                        <a href="index.php" class="back-link">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Beranda
                        </a>
                    </div>
                    
                    <div class="login-container p-4 p-md-5">
                        <div class="logo text-center mb-4">
                            <h1>InGrosir</h1>
                            <p>Masuk untuk melanjutkan</p>
                        </div>

                        <?php if ($error) { 
                            $isWarning = strpos($error, 'menunggu verifikasi') !== false;
                        ?>
                            <div class="alert alert-<?php echo $isWarning ? 'warning' : 'danger'; ?> d-flex align-items-start" role="alert">
                                <i class="fas <?php echo $isWarning ? 'fa-clock' : 'fa-exclamation-circle'; ?> me-2 mt-1"></i>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        <?php } ?>
                        
                        <form action="login.php" method="POST" id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
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

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="position-relative">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input 
                                        type="password" 
                                        class="form-control form-control-lg" 
                                        id="password" 
                                        name="password" 
                                        placeholder="Masukkan password Anda" 
                                        required
                                    >
                                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility" tabindex="-1">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                    <label class="form-check-label" for="remember">
                                        Ingat saya
                                    </label>
                                </div>
                                <a href="lupa_password.php" class="text-decoration-none fw-semibold" style="color: var(--primary);">Lupa password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                <span>Masuk Sekarang</span>
                            </button>
                        </form>

                        <!-- ADMIN LOGIN SECTION -->
                        <div class="admin-login-section pt-3 mt-3 text-center">
                            <a href="admin_login.php" class="admin-login-link">
                                <i class="fas fa-shield-halved"></i>
                                <span>Login sebagai Administrator</span>
                            </a>
                        </div>

                        <div class="row g-2 g-md-3 mt-3 mt-md-4">
                            <div class="col-4">
                                <div class="feature-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>Aman & Terpercaya</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="feature-item">
                                    <i class="fas fa-bolt"></i>
                                    <p>Akses Cepat</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="feature-item">
                                    <i class="fas fa-headset"></i>
                                    <p>Support 24/7</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="text-muted mb-0 small">Belum punya akun? <a href="register.php" class="text-decoration-none fw-semibold" style="color: var(--primary);">Daftar gratis sekarang</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        const rememberCheckbox = document.getElementById('remember');
        const emailInput = document.getElementById('email');
        
        window.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('rememberedEmail')) {
                emailInput.value = localStorage.getItem('rememberedEmail');
                rememberCheckbox.checked = true;
            }
        });
        
        document.getElementById('loginForm').addEventListener('submit', function() {
            if (rememberCheckbox.checked) {
                localStorage.setItem('rememberedEmail', emailInput.value);
            } else {
                localStorage.removeItem('rememberedEmail');
            }
        });

        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('loginForm').submit();
            }
        });

        window.addEventListener('load', function() {
            if (!emailInput.value) {
                emailInput.focus();
            } else {
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>