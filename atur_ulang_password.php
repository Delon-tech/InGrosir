<?php
include 'config/koneksi.php';
$koneksi = connectDB();

$message = '';
$message_type = '';
$token = isset($_GET['token']) ? $_GET['token'] : null;

if (!$token) {
    $message = "Token tidak valid atau tidak ditemukan.";
    $message_type = 'error';
} else {
    $query = "SELECT email FROM password_resets WHERE token = ? AND created_at >= NOW() - INTERVAL 1 HOUR";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        $message = "Token tidak valid atau sudah kadaluarsa. Link reset password hanya berlaku 1 jam.";
        $message_type = 'error';
        $token = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token) {
    $email = $row['email'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 8) {
        $message = "Password harus minimal 8 karakter.";
        $message_type = 'error';
    } else if ($new_password !== $confirm_password) {
        $message = "Kata sandi tidak cocok. Pastikan kedua password sama.";
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = ? WHERE email = ?";
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
        mysqli_stmt_execute($update_stmt);

        $delete_query = "DELETE FROM password_resets WHERE email = ?";
        $delete_stmt = mysqli_prepare($koneksi, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "s", $email);
        mysqli_stmt_execute($delete_stmt);

        $message = "Kata sandi Anda berhasil diperbarui! Anda akan dialihkan ke halaman login...";
        $message_type = 'success';
        header("refresh:3; url=login.php");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Kata Sandi - InGrosir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><circle cx="30" cy="30" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
            animation: backgroundMove 20s linear infinite;
            pointer-events: none;
        }
        @keyframes backgroundMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }
        .reset-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 1.5rem;
            transition: var(--transition);
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            backdrop-filter: blur(10px);
        }
        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
            color: white;
        }
        .reset-container {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        .header { text-align: center; margin-bottom: 2rem; }
        .icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .icon-wrapper i { font-size: 2.5rem; color: white; }
        .header h1 {
            font-size: 1.75rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .header p { color: var(--text-secondary); font-size: 0.875rem; line-height: 1.5; }
        .alert {
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            animation: shake 0.5s ease;
            border: none;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--danger); }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid var(--success); }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid var(--warning); }
        .alert i { font-size: 1.25rem; margin-top: 0.125rem; flex-shrink: 0; }
        .form-label { font-weight: 600; color: var(--text-primary); font-size: 0.875rem; margin-bottom: 0.5rem; }
        .input-wrapper { position: relative; }
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
            padding: 0.875rem 3rem 0.875rem 3rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-control.is-invalid { border-color: var(--danger); }
        .form-control.is-valid { border-color: var(--success); }
        .password-toggle {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            transition: var(--transition);
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            width: 40px;
            height: 40px;
            border-radius: 6px;
        }
        .password-toggle:hover { color: var(--primary); background: rgba(37, 99, 235, 0.05); }
        .password-toggle:focus { outline: 2px solid var(--primary); outline-offset: 2px; }
        .password-toggle:active { transform: translateY(-50%) scale(0.95); }
        .password-strength { margin-top: 0.5rem; display: none; }
        .password-strength.show { display: block; }
        .strength-bar { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; margin-bottom: 0.5rem; }
        .strength-fill { height: 100%; width: 0; transition: all 0.3s ease; border-radius: 2px; }
        .strength-fill.weak { width: 33%; background: var(--danger); }
        .strength-fill.medium { width: 66%; background: var(--warning); }
        .strength-fill.strong { width: 100%; background: var(--success); }
        .strength-text { font-size: 0.75rem; font-weight: 500; }
        .strength-text.weak { color: var(--danger); }
        .strength-text.medium { color: var(--warning); }
        .strength-text.strong { color: var(--success); }
        .password-requirements {
            background: var(--bg-gray);
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 0.75rem;
        }
        .password-requirements p { font-size: 0.75rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 0.5rem; }
        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        .requirement i { font-size: 0.875rem; width: 16px; text-align: center; }
        .requirement.met { color: var(--success); }
        .requirement.met i { color: var(--success); }
        .match-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: none;
        }
        .match-indicator.show { display: flex; }
        .match-indicator.match { color: var(--success); }
        .match-indicator.no-match { color: var(--danger); }
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 1rem;
            font-weight: 600;
            border-radius: var(--radius);
            transition: var(--transition);
        }
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn.loading { position: relative; color: transparent; pointer-events: none; }
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
        @keyframes spin { to { transform: rotate(360deg); } }
        .success-message { text-align: center; padding: 2rem 0; }
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--success), #34d399);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease;
        }
        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .success-icon i { font-size: 2.5rem; color: white; }
        .security-tips {
            background: rgba(37, 99, 235, 0.05);
            border: 1px solid rgba(37, 99, 235, 0.1);
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1.5rem;
        }
        .security-tips h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .security-tips ul { list-style: none; padding: 0; margin: 0; }
        .security-tips li {
            font-size: 0.75rem;
            color: var(--text-secondary);
            padding-left: 1.25rem;
            position: relative;
            margin-bottom: 0.25rem;
        }
        .security-tips li:before {
            content: '•';
            position: absolute;
            left: 0.5rem;
            color: var(--primary);
        }
        @media (max-width: 576px) {
            .reset-container { padding: 1.5rem; }
            .header h1 { font-size: 1.5rem; }
            body { padding: 0.5rem; }
            .icon-wrapper { width: 70px; height: 70px; }
            .icon-wrapper i { font-size: 2rem; }
            .form-control { padding: 0.75rem 2.75rem 0.75rem 2.75rem; font-size: 0.9375rem; }
            .input-icon { left: 0.875rem; font-size: 0.9375rem; }
            .password-toggle { right: 0.375rem; width: 36px; height: 36px; font-size: 1rem; }
        }
        @media (min-width: 577px) and (max-width: 768px) {
            .reset-container { padding: 2.5rem; }
        }
        @media (min-width: 769px) {
            .reset-container { padding: 3rem; }
        }
    </style>
</head>
<body>
    <div class="reset-wrapper">
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Login
        </a>
        <div class="reset-container">
            <div class="header">
                <div class="icon-wrapper">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Atur Ulang Kata Sandi</h1>
                <p>Masukkan kata sandi baru Anda untuk mengamankan akun</p>
            </div>
            <?php if ($message) { 
                $alertClass = $message_type == 'success' ? 'alert-success' : ($message_type == 'warning' ? 'alert-warning' : 'alert-danger');
                $iconClass = $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle');
            ?>
                <div class="alert <?php echo $alertClass; ?>" role="alert">
                    <i class="fas <?php echo $iconClass; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php } ?>
            <?php if ($token && $message_type != 'success') { ?>
                <form action="atur_ulang_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" id="resetForm">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Kata Sandi Baru</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Masukkan kata sandi baru" required autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')" aria-label="Toggle password visibility" tabindex="-1">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <span class="strength-text" id="strengthText"></span>
                        </div>
                        <div class="password-requirements">
                            <p class="mb-2">Password harus memenuhi:</p>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle"></i>
                                <span>Minimal 8 karakter</span>
                            </div>
                            <div class="requirement" id="req-uppercase">
                                <i class="fas fa-circle"></i>
                                <span>Minimal 1 huruf besar</span>
                            </div>
                            <div class="requirement" id="req-lowercase">
                                <i class="fas fa-circle"></i>
                                <span>Minimal 1 huruf kecil</span>
                            </div>
                            <div class="requirement" id="req-number">
                                <i class="fas fa-circle"></i>
                                <span>Minimal 1 angka</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Kata Sandi</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Ulangi kata sandi baru" required autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')" aria-label="Toggle password visibility" tabindex="-1">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                        <div class="match-indicator" id="matchIndicator">
                            <i class="fas fa-circle"></i>
                            <span id="matchText"></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="submitBtn" disabled>
                        <i class="fas fa-check me-2"></i>
                        Ubah Kata Sandi
                    </button>
                </form>
                <div class="security-tips">
                    <h3><i class="fas fa-shield-alt"></i> Tips Keamanan</h3>
                    <ul>
                        <li>Gunakan kombinasi huruf besar, kecil, angka, dan simbol</li>
                        <li>Jangan gunakan informasi pribadi yang mudah ditebak</li>
                        <li>Gunakan password yang berbeda untuk setiap akun</li>
                        <li>Perbarui password secara berkala</li>
                    </ul>
                </div>
            <?php } else if (!$token) { ?>
                <div class="success-message">
                    <div class="success-icon" style="background: linear-gradient(135deg, var(--danger), #f87171);">
                        <i class="fas fa-times"></i>
                    </div>
                    <p class="text-secondary mb-4">Link tidak valid. Silakan ajukan reset password ulang.</p>
                    <a href="lupa_password.php" class="btn btn-primary w-100">
                        <i class="fas fa-redo me-2"></i>
                        Reset Password Ulang
                    </a>
                </div>
            <?php } else if ($message_type == 'success') { ?>
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="text-secondary mb-0">Mengarahkan ke halaman login...</p>
                </div>
            <?php } ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
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
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            document.getElementById('req-length').classList.toggle('met', requirements.length);
            document.getElementById('req-uppercase').classList.toggle('met', requirements.uppercase);
            document.getElementById('req-lowercase').classList.toggle('met', requirements.lowercase);
            document.getElementById('req-number').classList.toggle('met', requirements.number);
            if (requirements.length) strength++;
            if (requirements.uppercase) strength++;
            if (requirements.lowercase) strength++;
            if (requirements.number) strength++;
            if (requirements.special) strength++;
            return { strength, requirements };
        }
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthIndicator = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const matchIndicator = document.getElementById('matchIndicator');
        const matchText = document.getElementById('matchText');
        const submitBtn = document.getElementById('submitBtn');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                if (password.length > 0) {
                    strengthIndicator.classList.add('show');
                    const { strength, requirements } = checkPasswordStrength(password);
                    strengthFill.className = 'strength-fill';
                    strengthText.className = 'strength-text';
                    if (strength <= 2) {
                        strengthFill.classList.add('weak');
                        strengthText.classList.add('weak');
                        strengthText.textContent = 'Lemah';
                    } else if (strength <= 4) {
                        strengthFill.classList.add('medium');
                        strengthText.classList.add('medium');
                        strengthText.textContent = 'Sedang';
                    } else {
                        strengthFill.classList.add('strong');
                        strengthText.classList.add('strong');
                        strengthText.textContent = 'Kuat';
                    }
                    if (requirements.length && requirements.uppercase && requirements.lowercase && requirements.number) {
                        newPasswordInput.classList.remove('is-invalid');
                        newPasswordInput.classList.add('is-valid');
                    } else {
                        newPasswordInput.classList.remove('is-valid');
                    }
                } else {
                    strengthIndicator.classList.remove('show');
                    newPasswordInput.classList.remove('is-valid', 'is-invalid');
                }
                checkPasswordMatch();
            });
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            if (confirmPassword.length > 0) {
                matchIndicator.classList.add('show');
                if (password === confirmPassword) {
                    matchIndicator.classList.remove('no-match');
                    matchIndicator.classList.add('match');
                    matchText.textContent = 'Password cocok';
                    confirmPasswordInput.classList.remove('is-invalid');
                    confirmPasswordInput.classList.add('is-valid');
                } else {
                    matchIndicator.classList.remove('match');
                    matchIndicator.classList.add('no-match');
                    matchText.textContent = 'Password tidak cocok';
                    confirmPasswordInput.classList.remove('is-valid');
                    confirmPasswordInput.classList.add('is-invalid');
                }
            } else {
                matchIndicator.classList.remove('show');
                confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
            }
            validateForm();
        }
        function validateForm() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const { requirements } = checkPasswordStrength(password);
            const isValid = requirements.length && requirements.uppercase && requirements.lowercase && requirements.number && password === confirmPassword && password.length > 0 && confirmPassword.length > 0;
            submitBtn.disabled = !isValid;
        }
        if (document.getElementById('resetForm')) {
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Password tidak cocok!');
                    return false;
                }
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password harus minimal 8 karakter!');
                    return false;
                }
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            });
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !submitBtn.disabled) {
                    e.preventDefault();
                    document.getElementById('resetForm').submit();
                }
            });
        }
        window.addEventListener('load', function() {
            if (newPasswordInput) {
                newPasswordInput.focus();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                togglePassword('new_password', 'toggleIcon1');
            }
            if (e.altKey && e.key === 'c') {
                e.preventDefault();
                togglePassword('confirm_password', 'toggleIcon2');
            }
        });
        function checkCapsLock(e) {
            const isCapsLock = e.getModifierState('CapsLock');
            const warningId = e.target.id + '_capslock_warning';
            let warning = document.getElementById(warningId);
            if (isCapsLock) {
                if (!warning) {
                    warning = document.createElement('div');
                    warning.id = warningId;
                    warning.className = 'capslock-warning';
                    warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>Caps Lock aktif</span>';
                    e.target.parentElement.parentElement.appendChild(warning);
                }
            } else {
                if (warning) {
                    warning.remove();
                }
            }
        }
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('keyup', checkCapsLock);
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('keyup', checkCapsLock);
        }
        
        window.addEventListener('beforeunload', function() {
            if (newPasswordInput) newPasswordInput.value = '';
            if (confirmPasswordInput) confirmPasswordInput.value = '';
        });
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('focus', function() {
                if (this.value.length > 0) {
                    strengthIndicator.classList.add('show');
                }
            });
        }
        
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (!alert.classList.contains('alert-success')) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 500);
                }, 10000);
            }
        });
        
        let announceTimeout;
        function announcePasswordStrength(text) {
            clearTimeout(announceTimeout);
            announceTimeout = setTimeout(() => {
                const announcement = document.createElement('div');
                announcement.setAttribute('role', 'status');
                announcement.setAttribute('aria-live', 'polite');
                announcement.style.position = 'absolute';
                announcement.style.left = '-10000px';
                announcement.style.width = '1px';
                announcement.style.height = '1px';
                announcement.style.overflow = 'hidden';
                announcement.textContent = 'Kekuatan password: ' + text;
                document.body.appendChild(announcement);
                setTimeout(() => announcement.remove(), 1000);
            }, 1000);
        }
        
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.id === 'strengthText' && mutation.target.textContent) {
                    announcePasswordStrength(mutation.target.textContent);
                }
            });
        });
        
        if (strengthText) {
            observer.observe(strengthText, { childList: true, characterData: true, subtree: true });
        }
        
        console.log('%c✅ InGrosir - Password Reset Page Loaded Successfully!', 'color: #10b981; font-size: 16px; font-weight: bold;');
        console.log('%c🔐 Security Features Active', 'color: #2563eb; font-size: 14px;');
        console.log('%c📱 Responsive Design: Mobile, Tablet, Desktop', 'color: #8b5cf6; font-size: 14px;');
    </script>
</body>
</html>