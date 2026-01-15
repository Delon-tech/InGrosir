<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

// Redirect jika sudah login
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        $query = "SELECT * FROM users WHERE email = ? AND is_admin = 1";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['user_id'];
                $_SESSION['admin_name'] = $user['nama_lengkap'];
                $_SESSION['admin_email'] = $user['email'];
                
                if ($remember) {
                    setcookie('admin_email', $email, time() + (86400 * 30), "/");
                }
                
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Password yang Anda masukkan salah!";
            }
        } else {
            $error = "Akun admin tidak ditemukan atau tidak memiliki akses!";
        }
        mysqli_stmt_close($stmt);
    }
}

$remembered_email = isset($_COOKIE['admin_email']) ? $_COOKIE['admin_email'] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - InGrosir</title>
    
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
        
        .login-container {
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease;
        }
        
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .login-icon {
            width: clamp(60px, 15vw, 80px);
            height: clamp(60px, 15vw, 80px);
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            backdrop-filter: blur(10px);
        }
        
        .login-icon i {
            font-size: clamp(1.75rem, 5vw, 2.5rem);
        }
        
        .login-header h1 {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: clamp(0.8rem, 2vw, 0.9375rem);
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
            font-size: 1.125rem;
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
        
        .btn-login {
            background: var(--primary);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            color: white;
        }

        .btn-login:disabled {
            opacity: 0.6;
            transform: none;
        }
        
        .security-notice {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-size: clamp(0.8rem, 2vw, 1rem);
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
            color: white;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }
        
        .btn-login.loading .spinner {
            display: inline-block;
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 576px) {
            .back-link {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .login-header {
                padding: 2rem 1.5rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-11 col-md-8 col-lg-6 col-xl-5">
                <div class="mb-3 mb-md-4">
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Beranda
                    </a>
                </div>
                
                <div class="login-container">
                    <div class="login-card">
                        <div class="login-header text-center py-4 py-md-5 px-3 px-md-4">
                            <div class="login-icon">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <h1>Admin Panel</h1>
                            <p>Masuk ke Dashboard Administrasi</p>
                        </div>
                        
                        <div class="p-4 p-md-5">
                            <?php if ($error) { ?>
                            <div class="alert alert-danger d-flex align-items-start" role="alert">
                                <i class="fas fa-exclamation-circle me-2 mt-1"></i>
                                <div><?php echo $error; ?></div>
                            </div>
                            <?php } ?>
                            
                            <?php if ($success) { ?>
                            <div class="alert alert-success d-flex align-items-start" role="alert">
                                <i class="fas fa-check-circle me-2 mt-1"></i>
                                <div><?php echo $success; ?></div>
                            </div>
                            <?php } ?>
                            
                            <form method="POST" action="" id="loginForm">
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-semibold">Email Admin</label>
                                    <div class="position-relative">
                                        <i class="fas fa-envelope input-icon"></i>
                                        <input 
                                            type="email" 
                                            class="form-control form-control-lg"
                                            id="email" 
                                            name="email" 
                                            placeholder="admin@ingrosir.com" 
                                            value="<?php echo htmlspecialchars($remembered_email); ?>"
                                            required
                                            autocomplete="email"
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
                                            placeholder="••••••••" 
                                            required
                                            autocomplete="current-password"
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility" tabindex="-1">
                                            <i class="fas fa-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember" <?php echo $remembered_email ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="remember">
                                            Ingat saya
                                        </label>
                                    </div>
                                    <a href="#" class="text-decoration-none fw-semibold" style="color: var(--primary);">Lupa password?</a>
                                </div>
                                
                                <button type="submit" class="btn btn-login btn-lg w-100 d-flex align-items-center justify-content-center gap-2" id="loginBtn">
                                    <span class="btn-text">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Masuk ke Dashboard
                                    </span>
                                    <div class="spinner"></div>
                                </button>
                            </form>
                            
                            <div class="security-notice d-flex align-items-start gap-3 mt-4 p-3">
                                <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                                <p class="mb-0 small text-muted">
                                    Halaman ini dilindungi. Hanya administrator yang memiliki akses ke panel administrasi.
                                    Pastikan Anda merahasiakan kredensial login Anda.
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-center p-3 p-md-4 border-top" style="background: var(--bg-gray);">
                            <p class="mb-0 small text-muted">
                                Bukan admin? <a href="login.php" class="text-decoration-none fw-semibold" style="color: var(--primary);">Login sebagai pengguna</a>
                            </p>
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

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        window.addEventListener('load', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else {
                passwordInput.focus();
            }
        });

        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>