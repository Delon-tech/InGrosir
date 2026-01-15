<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode = strtoupper(trim($_POST['kode_voucher']));
    $tipe = $_POST['tipe_diskon'];
    $nilai = floatval($_POST['nilai_diskon']);
    $min_pembelian = floatval($_POST['min_pembelian']);
    $max_diskon = !empty($_POST['max_diskon']) ? floatval($_POST['max_diskon']) : NULL;
    $kuota = !empty($_POST['kuota_total']) ? intval($_POST['kuota_total']) : NULL;
    $tgl_mulai = $_POST['tanggal_mulai'];
    $tgl_berakhir = $_POST['tanggal_berakhir'];
    $deskripsi = trim($_POST['deskripsi']);
    
    // Validasi
    if (empty($kode) || empty($tipe) || $nilai <= 0) {
        $error = 'Semua field wajib diisi dengan benar!';
    } elseif ($tipe == 'persentase' && $nilai > 100) {
        $error = 'Persentase diskon tidak boleh lebih dari 100%!';
    } elseif (strtotime($tgl_berakhir) <= strtotime($tgl_mulai)) {
        $error = 'Tanggal berakhir harus lebih dari tanggal mulai!';
    } else {
        // Cek kode voucher sudah ada
        $check_query = "SELECT voucher_id FROM voucher_diskon WHERE kode_voucher = ?";
        $check_stmt = mysqli_prepare($koneksi, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $kode);
        mysqli_stmt_execute($check_stmt);
        
        if (mysqli_stmt_get_result($check_stmt)->num_rows > 0) {
            $error = 'Kode voucher sudah digunakan! Gunakan kode lain.';
        } else {
            // Insert voucher
            $insert_query = "INSERT INTO voucher_diskon 
                            (user_id_penjual, kode_voucher, tipe_diskon, nilai_diskon, 
                             min_pembelian, max_diskon, kuota_total, tanggal_mulai, 
                             tanggal_berakhir, deskripsi) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($koneksi, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "issdddisss", 
                $user_id, $kode, $tipe, $nilai, $min_pembelian, 
                $max_diskon, $kuota, $tgl_mulai, $tgl_berakhir, $deskripsi
            );
            
            if (mysqli_stmt_execute($insert_stmt)) {
                header("Location: kelola_voucher.php?success=" . urlencode("Voucher berhasil dibuat!"));
                exit();
            } else {
                $error = 'Gagal menyimpan voucher: ' . mysqli_error($koneksi);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Voucher - InGrosir</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --sidebar-width: 280px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        
        /* Sidebar - Same style */
        .sidebar {
            position: fixed; left: 0; top: 0; width: var(--sidebar-width); height: 100vh;
            background: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); z-index: 1000;
            overflow-y: auto; transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 2rem; border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
        }
        .sidebar-logo {
            font-size: 1.75rem; font-weight: 800; margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .sidebar-user {
            display: flex; align-items: center; gap: 1rem; padding: 1rem;
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .user-avatar {
            width: 50px; height: 50px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 1.25rem; flex-shrink: 0;
        }
        .user-info h3 { font-size: 0.95rem; margin-bottom: 0.25rem; font-weight: 600; color: #1f2937; }
        .user-info p { font-size: 0.8rem; color: #6b7280; margin: 0; }
        .sidebar-menu { padding: 1rem 0; }
        .menu-item {
            display: flex; align-items: center; gap: 1rem; padding: 0.875rem 2rem;
            color: #6b7280; text-decoration: none; transition: all 0.3s ease; position: relative;
        }
        .menu-item:hover, .menu-item.active { background: rgba(37, 99, 235, 0.08); color: var(--primary); }
        .menu-item.active::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px; background: var(--primary);
        }
        .menu-item i { width: 20px; text-align: center; font-size: 1.1rem; }
        
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; padding: 2rem; }
        
        .form-card {
            background: white; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 2rem;
            max-width: 800px; margin: 0 auto;
        }
        
        .mobile-toggle {
            display: none; position: fixed; bottom: 2rem; right: 2rem;
            width: 60px; height: 60px; background: var(--primary); color: white;
            border: none; border-radius: 50%; font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); z-index: 999;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">InGrosir</div>
            <div class="sidebar-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['nama_grosir'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['nama_grosir']); ?></h3>
                    <p>Penjual</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <a href="produk_list.php" class="menu-item">
                <i class="fas fa-box"></i><span>Kelola Produk</span>
            </a>
            <a href="pesanan.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i><span>Kelola Pesanan</span>
            </a>
            <a href="kelola_voucher.php" class="menu-item active">
                <i class="fas fa-ticket-alt"></i><span>Kelola Voucher</span>
            </a>
            <a href="kelola_metode_pembayaran.php" class="menu-item">
                <i class="fas fa-credit-card"></i><span>Metode Pembayaran</span>
            </a>
            <a href="laporan_penjualan.php" class="menu-item">
                <i class="fas fa-chart-line"></i><span>Laporan Penjualan</span>
            </a>
            <a href="profil.php" class="menu-item">
                <i class="fas fa-user"></i><span>Profil Saya</span>
            </a>
            <a href="index.php" class="menu-item">
                <i class="fas fa-globe"></i><span>Halaman Utama</span>
            </a>
            <a href="logout.php" class="menu-item text-danger">
                <i class="fas fa-sign-out-alt"></i><span>Keluar</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="kelola_voucher.php">Kelola Voucher</a></li>
                <li class="breadcrumb-item active">Tambah Voucher</li>
            </ol>
        </nav>

        <div class="form-card">
            <h2 class="mb-4">
                <i class="fas fa-ticket-alt text-primary me-2"></i>
                Buat Voucher Baru
            </h2>

            <?php if ($error) { ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <form method="POST" onsubmit="return validateForm()">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-tag me-1"></i>Kode Voucher <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="kode_voucher" class="form-control" 
                               placeholder="Contoh: DISKON50K" required 
                               pattern="[A-Z0-9]+" maxlength="50"
                               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                        <small class="text-muted">Huruf kapital dan angka saja, tanpa spasi</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-percentage me-1"></i>Tipe Diskon <span class="text-danger">*</span>
                        </label>
                        <select name="tipe_diskon" id="tipe_diskon" class="form-select" required onchange="updateDiskonLabel()">
                            <option value="persentase">Persentase (%)</option>
                            <option value="nominal">Nominal (Rp)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-gift me-1"></i>
                            <span id="diskon_label">Nilai Diskon (%)</span> <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="nilai_diskon" id="nilai_diskon" class="form-control" 
                               placeholder="Contoh: 10" required min="1" step="0.01">
                        <small class="text-muted" id="diskon_hint">Maksimal 100%</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-shopping-cart me-1"></i>Minimal Pembelian (Rp) <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="min_pembelian" class="form-control" 
                               placeholder="Contoh: 100000" required min="0" step="1000" value="0">
                        <small class="text-muted">Minimal belanja untuk gunakan voucher</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-coins me-1"></i>Maksimal Diskon (Rp)
                        </label>
                        <input type="number" name="max_diskon" class="form-control" 
                               placeholder="Kosongkan jika tidak ada batas" min="0" step="1000">
                        <small class="text-muted">Opsional, untuk batasi diskon maksimal</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-users me-1"></i>Kuota Penggunaan
                        </label>
                        <input type="number" name="kuota_total" class="form-control" 
                               placeholder="Kosongkan untuk unlimited" min="1">
                        <small class="text-muted">Jumlah maksimal voucher bisa digunakan</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-check me-1"></i>Tanggal Mulai <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" name="tanggal_mulai" class="form-control" 
                               required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-times me-1"></i>Tanggal Berakhir <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" name="tanggal_berakhir" class="form-control" 
                               required value="<?php echo date('Y-m-d\TH:i', strtotime('+30 days')); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">
                            <i class="fas fa-comment-alt me-1"></i>Deskripsi Voucher
                        </label>
                        <textarea name="deskripsi" class="form-control" rows="3" 
                                  placeholder="Contoh: Diskon spesial untuk pembelian pertama"></textarea>
                        <small class="text-muted">Deskripsi akan ditampilkan ke pembeli</small>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Tips Membuat Voucher:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Gunakan kode yang mudah diingat (misal: DISKON50K, NEWUSER20)</li>
                        <li>Tentukan minimal pembelian agar tetap untung</li>
                        <li>Batasi kuota untuk urgency (limited stock effect)</li>
                    </ul>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Voucher
                    </button>
                    <a href="kelola_voucher.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Batal
                    </a>
                </div>
            </form>
        </div>
    </main>

    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function updateDiskonLabel() {
            const tipe = document.getElementById('tipe_diskon').value;
            const label = document.getElementById('diskon_label');
            const hint = document.getElementById('diskon_hint');
            const input = document.getElementById('nilai_diskon');
            
            if (tipe === 'persentase') {
                label.textContent = 'Nilai Diskon (%)';
                hint.textContent = 'Maksimal 100%';
                input.max = 100;
                input.placeholder = 'Contoh: 10';
            } else {
                label.textContent = 'Nilai Diskon (Rp)';
                hint.textContent = 'Nominal potongan harga';
                input.removeAttribute('max');
                input.placeholder = 'Contoh: 50000';
            }
        }

        function validateForm() {
            const tipe = document.getElementById('tipe_diskon').value;
            const nilai = parseFloat(document.getElementById('nilai_diskon').value);
            
            if (tipe === 'persentase' && nilai > 100) {
                alert('Persentase diskon tidak boleh lebih dari 100%!');
                return false;
            }
            
            const tglMulai = new Date(document.querySelector('[name="tanggal_mulai"]').value);
            const tglBerakhir = new Date(document.querySelector('[name="tanggal_berakhir"]').value);
            
            if (tglBerakhir <= tglMulai) {
                alert('Tanggal berakhir harus lebih dari tanggal mulai!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>