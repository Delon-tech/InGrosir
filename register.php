<?php
include 'config/koneksi.php';
$koneksi = connectDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $peran = $_POST['peran'];
    $gambar_toko_path = null;

    $check_email = "SELECT user_id FROM users WHERE email = ?";
    $stmt_check = mysqli_prepare($koneksi, $check_email);
    mysqli_stmt_bind_param($stmt_check, "s", $email);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $error = "Email sudah terdaftar. Silakan gunakan email lain.";
    }
    mysqli_stmt_close($stmt_check);

    if (!$error && $peran == 'penjual') {
        $nama_grosir = trim($_POST['nama_grosir']);
        $alamat_grosir = trim($_POST['alamat_grosir']);
        $nomor_telepon = trim($_POST['nomor_telepon']);

        if (isset($_FILES['gambar_toko']) && $_FILES['gambar_toko']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "assets/images/stores/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['gambar_toko']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid('toko_', true) . '.' . $file_extension;
            $target_file = $target_dir . $file_name;

            $max_file_size = 5 * 1024 * 1024;
            if ($_FILES['gambar_toko']['size'] > $max_file_size) {
                $error = "Ukuran file terlalu besar. Maksimum 5MB.";
            }

            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($file_extension, $allowed_types)) {
                $error = "Hanya file JPG, JPEG, PNG, GIF & WEBP yang diizinkan.";
            }
            
            $check = getimagesize($_FILES['gambar_toko']['tmp_name']);
            if ($check === false) {
                $error = "File yang diunggah bukan gambar.";
            }

            if (empty($error)) {
                if (move_uploaded_file($_FILES['gambar_toko']['tmp_name'], $target_file)) {
                    $gambar_toko_path = $target_file;
                } else {
                    $error = "Gagal mengunggah file. Periksa permission folder.";
                }
            }
        } else {
            $error = "Gambar toko wajib diunggah.";
        }
    } else if (!$error) {
        $nama_grosir = null;
        $alamat_grosir = null;
        $nomor_telepon = null;
    }

    if (!$error) {
        $tanggal_registrasi = date('Y-m-d H:i:s');
        $status_verifikasi = ($peran == 'penjual') ? 'pending' : 'approved';

        $query = "INSERT INTO users (nama_lengkap, email, password, peran, status_verifikasi, nama_grosir, alamat_grosir, nomor_telepon, gambar_toko, tanggal_registrasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ssssssssss", $nama_lengkap, $email, $password, $peran, $status_verifikasi, $nama_grosir, $alamat_grosir, $nomor_telepon, $gambar_toko_path, $tanggal_registrasi);

        if (mysqli_stmt_execute($stmt)) {
            if ($peran == 'penjual') {
                $success = "Registrasi berhasil! Akun Anda akan diverifikasi oleh admin dalam 1-3 hari kerja. Silakan cek email Anda secara berkala.";
            } else {
                $success = "Registrasi berhasil! Silakan login.";
            }
            header("refresh:3;url=login.php");
        } else {
            $error = "Gagal mendaftar: " . mysqli_error($koneksi);
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
    <title>Daftar - InGrosir</title>
    
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
            overflow-x: hidden;
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .register-wrapper {
            animation: slideUp 0.5s ease;
        }
        
        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .logo h1 {
            font-size: clamp(1.75rem, 5vw, 2rem);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }
        
        .logo p {
            color: var(--text-secondary);
            font-size: clamp(0.8rem, 2vw, 0.875rem);
            margin: 0;
        }
        
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .role-card {
            padding: 1.5rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 140px;
        }
        
        .role-card i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .role-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .role-card p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .role-option input:checked + .role-card {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .role-option input:checked + .role-card i {
            color: var(--primary);
        }
        
        .role-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .form-section.active {
            display: block;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--border);
            font-family: 'Inter', sans-serif;
            font-size: clamp(0.875rem, 2vw, 1rem);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.1);
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: clamp(0.75rem, 2vw, 0.875rem) 1rem;
            border: 2px dashed var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--bg-gray);
            color: var(--text-secondary);
        }
        
        .file-input-label:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            color: var(--primary);
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .password-strength-bar.weak { 
            width: 33%; 
            background: var(--danger); 
        }
        
        .password-strength-bar.medium { 
            width: 66%; 
            background: var(--warning); 
        }
        
        .password-strength-bar.strong { 
            width: 100%; 
            background: var(--success); 
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: clamp(0.8rem, 2vw, 1rem);
        }
        
        .back-link:hover {
            gap: 0.75rem;
            color: white;
        }

        .required {
            color: var(--danger);
        }

        @media (max-width: 576px) {
            .role-selector {
                gap: 0.75rem;
            }
            
            .role-card {
                padding: 1.25rem 0.75rem;
                min-height: 130px;
            }
            
            .role-card i {
                font-size: 2rem;
            }
            
            .role-card h3 {
                font-size: 1rem;
            }
            
            .role-card p {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-11 col-md-9 col-lg-7 col-xl-6">
                <div class="register-wrapper">
                    <div class="mb-3 mb-md-4">
                        <a href="index.php" class="back-link">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Beranda
                        </a>
                    </div>
                    
                    <div class="register-container p-4 p-md-5">
                        <div class="logo text-center mb-4">
                            <h1>InGrosir</h1>
                            <p>Daftar dan mulai kembangkan bisnis Anda</p>
                        </div>

                        <?php if ($success) { ?>
                            <div class="alert alert-success d-flex align-items-start" role="alert">
                                <i class="fas fa-check-circle me-2 mt-1"></i>
                                <div><?php echo $success; ?></div>
                            </div>
                        <?php } ?>
                        
                        <?php if ($error) { ?>
                            <div class="alert alert-danger d-flex align-items-start" role="alert">
                                <i class="fas fa-exclamation-circle me-2 mt-1"></i>
                                <div><?php echo $error; ?></div>
                            </div>
                        <?php } ?>
                        
                        <form id="registerForm" method="POST" enctype="multipart/form-data">
                            <!-- Role Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Pilih Peran <span class="required">*</span></label>
                                <div class="role-selector">
                                    <div class="role-option">
                                        <input type="radio" name="peran" value="pembeli" id="role_pembeli" required>
                                        <label for="role_pembeli" class="role-card">
                                            <i class="fas fa-shopping-cart"></i>
                                            <h3>Pembeli</h3>
                                            <p>Cari produk grosir</p>
                                        </label>
                                    </div>
                                    
                                    <div class="role-option">
                                        <input type="radio" name="peran" value="penjual" id="role_penjual" required>
                                        <label for="role_penjual" class="role-card">
                                            <i class="fas fa-store"></i>
                                            <h3>Penjual</h3>
                                            <p>Jual produk grosir</p>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Common Fields -->
                            <div class="mb-3">
                                <label for="nama_lengkap" class="form-label fw-semibold">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Alamat Email <span class="required">*</span></label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="nama@email.com" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password <span class="required">*</span></label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Minimal 8 karakter" required minlength="8">
                                <div class="password-strength">
                                    <div class="password-strength-bar" id="strengthBar"></div>
                                </div>
                            </div>

                            <!-- Seller Additional Fields -->
                            <div id="penjual_fields" class="form-section">
                                <div class="info-box d-flex align-items-start gap-3 p-3 mb-3">
                                    <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                                    <div>
                                        <h6 class="mb-1" style="color: var(--primary); font-size: 0.875rem;">Verifikasi Diperlukan</h6>
                                        <p class="mb-0 small text-muted">Akun penjual akan diverifikasi oleh admin dalam 1-3 hari kerja untuk memastikan keaslian toko Anda. Anda akan menerima notifikasi via email.</p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="nama_grosir" class="form-label fw-semibold">Nama Usaha Grosir <span class="required">*</span></label>
                                    <input type="text" class="form-control form-control-lg" id="nama_grosir" name="nama_grosir" placeholder="Nama toko/usaha Anda">
                                </div>

                                <div class="mb-3">
                                    <label for="alamat_grosir" class="form-label fw-semibold">Alamat Lengkap Usaha <span class="required">*</span></label>
                                    <textarea class="form-control" id="alamat_grosir" name="alamat_grosir" rows="3" placeholder="Alamat lengkap toko/gudang"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="nomor_telepon" class="form-label fw-semibold">Nomor Telepon <span class="required">*</span></label>
                                    <input type="tel" class="form-control form-control-lg" id="nomor_telepon" name="nomor_telepon" placeholder="08xxxxxxxxxx">
                                </div>

                                <div class="mb-3">
                                    <label for="gambar_toko" class="form-label fw-semibold">Gambar Toko <span class="required">*</span></label>
                                    <input type="file" class="d-none" id="gambar_toko" name="gambar_toko" accept="image/*" onchange="displayFileName(this)">
                                    <label for="gambar_toko" class="file-input-label w-100">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Pilih gambar toko Anda</span>
                                    </label>
                                    <div id="file_name" class="small text-muted mt-2"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-user-plus"></i>
                                <span>Daftar Sekarang</span>
                            </button>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="mb-0 small text-muted">Sudah punya akun? <a href="login.php" class="text-decoration-none fw-semibold" style="color: var(--primary);">Masuk di sini</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const roleRadios = document.querySelectorAll('input[name="peran"]');
        const penjualFields = document.getElementById('penjual_fields');
        
        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'penjual') {
                    penjualFields.classList.add('active');
                    document.getElementById('nama_grosir').setAttribute('required', 'required');
                    document.getElementById('alamat_grosir').setAttribute('required', 'required');
                    document.getElementById('nomor_telepon').setAttribute('required', 'required');
                    document.getElementById('gambar_toko').setAttribute('required', 'required');
                } else {
                    penjualFields.classList.remove('active');
                    document.getElementById('nama_grosir').removeAttribute('required');
                    document.getElementById('alamat_grosir').removeAttribute('required');
                    document.getElementById('nomor_telepon').removeAttribute('required');
                    document.getElementById('gambar_toko').removeAttribute('required');
                }
            });
        });

        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 1) {
                strengthBar.classList.add('weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });

        function displayFileName(input) {
            const fileNameDiv = document.getElementById('file_name');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = '📎 File dipilih: ' + input.files[0].name;
                fileNameDiv.style.color = 'var(--success)';
            }
        }

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const role = document.querySelector('input[name="peran"]:checked');
            
            if (!role) {
                e.preventDefault();
                alert('Silakan pilih peran terlebih dahulu!');
                return false;
            }
            
            if (role.value === 'penjual') {
                const gambarToko = document.getElementById('gambar_toko');
                if (!gambarToko.files || !gambarToko.files[0]) {
                    e.preventDefault();
                    alert('Gambar toko harus diunggah!');
                    return false;
                }
            }
        });
    </script>
</body>
</html>