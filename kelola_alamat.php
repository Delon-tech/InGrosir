<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Deteksi apakah dibuka dari checkout
$from_checkout = isset($_GET['from']) && $_GET['from'] === 'checkout';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add' || $action == 'edit') {
        $alamat_id = $action == 'edit' ? intval($_POST['alamat_id']) : null;
        $label_alamat = trim($_POST['label_alamat']);
        $nama_penerima = trim($_POST['nama_penerima']);
        $nomor_telepon = trim($_POST['nomor_telepon']);
        $alamat_lengkap = trim($_POST['alamat_lengkap']);
        $kelurahan = trim($_POST['kelurahan']);
        $kecamatan = trim($_POST['kecamatan']);
        $kota = trim($_POST['kota']);
        $provinsi = trim($_POST['provinsi']);
        $kode_pos = trim($_POST['kode_pos']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validation
        if (empty($label_alamat) || empty($nama_penerima) || empty($nomor_telepon) || 
            empty($alamat_lengkap) || empty($kota) || empty($provinsi)) {
            $error = "Semua field wajib diisi kecuali kelurahan dan kode pos!";
        } else {
            mysqli_begin_transaction($koneksi);
            try {
                // If set as default, unset other defaults
                if ($is_default == 1) {
                    $query_unset = "UPDATE alamat_pengiriman SET is_default = 0 WHERE user_id = ?";
                    $stmt_unset = mysqli_prepare($koneksi, $query_unset);
                    mysqli_stmt_bind_param($stmt_unset, "i", $user_id);
                    mysqli_stmt_execute($stmt_unset);
                }
                
                if ($action == 'add') {
                    $query = "INSERT INTO alamat_pengiriman (user_id, label_alamat, nama_penerima, nomor_telepon, alamat_lengkap, kelurahan, kecamatan, kota, provinsi, kode_pos, is_default) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($koneksi, $query);
                    mysqli_stmt_bind_param($stmt, "isssssssssi", $user_id, $label_alamat, $nama_penerima, $nomor_telepon, $alamat_lengkap, $kelurahan, $kecamatan, $kota, $provinsi, $kode_pos, $is_default);
                } else {
                    $query = "UPDATE alamat_pengiriman SET label_alamat=?, nama_penerima=?, nomor_telepon=?, alamat_lengkap=?, kelurahan=?, kecamatan=?, kota=?, provinsi=?, kode_pos=?, is_default=? 
                             WHERE alamat_id=? AND user_id=?";
                    $stmt = mysqli_prepare($koneksi, $query);
                    mysqli_stmt_bind_param($stmt, "sssssssssiis", $label_alamat, $nama_penerima, $nomor_telepon, $alamat_lengkap, $kelurahan, $kecamatan, $kota, $provinsi, $kode_pos, $is_default, $alamat_id, $user_id);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_commit($koneksi);
                    $message = $action == 'add' ? "Alamat berhasil ditambahkan!" : "Alamat berhasil diperbarui!";
                    
                    // Jika dari checkout, refresh parent dan close window
                    if ($from_checkout) {
                        echo "<script>
                            if (window.opener) {
                                window.opener.location.reload();
                                setTimeout(function() {
                                    window.close();
                                }, 1500);
                            }
                        </script>";
                    }
                } else {
                    throw new Exception("Gagal menyimpan alamat");
                }
            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $error = "Error: " . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $alamat_id = intval($_POST['alamat_id']);
        $query = "DELETE FROM alamat_pengiriman WHERE alamat_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ii", $alamat_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Alamat berhasil dihapus!";
            
            // Jika dari checkout, refresh parent
            if ($from_checkout) {
                echo "<script>
                    if (window.opener) {
                        window.opener.location.reload();
                    }
                </script>";
            }
        } else {
            $error = "Gagal menghapus alamat!";
        }
    } elseif ($action == 'set_default') {
        $alamat_id = intval($_POST['alamat_id']);
        
        mysqli_begin_transaction($koneksi);
        try {
            // Unset all defaults
            $query_unset = "UPDATE alamat_pengiriman SET is_default = 0 WHERE user_id = ?";
            $stmt_unset = mysqli_prepare($koneksi, $query_unset);
            mysqli_stmt_bind_param($stmt_unset, "i", $user_id);
            mysqli_stmt_execute($stmt_unset);
            
            // Set new default
            $query_set = "UPDATE alamat_pengiriman SET is_default = 1 WHERE alamat_id = ? AND user_id = ?";
            $stmt_set = mysqli_prepare($koneksi, $query_set);
            mysqli_stmt_bind_param($stmt_set, "ii", $alamat_id, $user_id);
            mysqli_stmt_execute($stmt_set);
            
            mysqli_commit($koneksi);
            $message = "Alamat utama berhasil diubah!";
            
            // Jika dari checkout, refresh parent
            if ($from_checkout) {
                echo "<script>
                    if (window.opener) {
                        window.opener.location.reload();
                    }
                </script>";
            }
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error = "Gagal mengubah alamat utama!";
        }
    }
}

// Get all addresses
$query_alamat = "SELECT * FROM alamat_pengiriman WHERE user_id = ? ORDER BY is_default DESC, alamat_id DESC";
$stmt_alamat = mysqli_prepare($koneksi, $query_alamat);
mysqli_stmt_bind_param($stmt_alamat, "i", $user_id);
mysqli_stmt_execute($stmt_alamat);
$result_alamat = mysqli_stmt_get_result($stmt_alamat);
$alamat_list = mysqli_fetch_all($result_alamat, MYSQLI_ASSOC);

// Get alamat for edit if requested
$edit_alamat = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    foreach ($alamat_list as $alamat) {
        if ($alamat['alamat_id'] == $edit_id) {
            $edit_alamat = $alamat;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Alamat - InGrosir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --bg-white: #ffffff;
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
            background: var(--bg-gray);
            color: var(--text-primary);
        }
        
        .header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        /* Checkout Mode Banner */
        .checkout-banner {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .checkout-banner i {
            margin-right: 0.5rem;
        }

        .checkout-banner strong {
            font-weight: 700;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--secondary);
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--primary);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        .address-list {
            display: grid;
            gap: 1rem;
        }
        
        .address-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            position: relative;
            transition: var(--transition);
        }
        
        .address-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .address-label {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .address-default {
            background: var(--secondary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .address-body h3 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }
        
        .address-phone {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .address-detail {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-gray);
            border-radius: var(--radius);
        }
        
        .address-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            text-decoration: none;
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
            background: var(--text-secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-back-checkout {
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            box-shadow: var(--shadow);
        }

        .btn-back-checkout:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .form-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .form-group label .required {
            color: var(--danger);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.875rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .form-actions .btn {
            flex: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .back-to-checkout-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
            border: 2px solid var(--secondary);
        }

        .back-to-checkout-section p {
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                position: static;
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .address-actions {
                flex-direction: column;
            }
            
            .address-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php if ($from_checkout) { ?>
        <!-- Checkout Mode Banner -->
        <div class="checkout-banner">
            <i class="fas fa-shopping-cart"></i>
            <strong>Mode Checkout:</strong> Anda sedang menambahkan alamat dari proses checkout
        </div>
    <?php } ?>

    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">InGrosir</a>
            <?php if ($from_checkout) { ?>
                <button onclick="window.close()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Tutup
                </button>
            <?php } ?>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-map-marked-alt"></i>
                Kelola Alamat Pengiriman
            </h1>
            <p class="page-subtitle">Tambah, edit, atau hapus alamat pengiriman Anda</p>
        </div>

        <?php if ($message) { ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>

            <?php if ($from_checkout && !$edit_alamat) { ?>
                <!-- Back to Checkout Button After Success -->
                <div class="back-to-checkout-section">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--secondary); margin-bottom: 1rem;"></i>
                    <h3 style="margin-bottom: 0.5rem;">Alamat Berhasil Disimpan!</h3>
                    <p>Halaman checkout akan direfresh otomatis. Jika tidak, klik tombol di bawah:</p>
                    <button onclick="refreshAndClose()" class="btn btn-back-checkout">
                        <i class="fas fa-arrow-left"></i> Kembali ke Checkout
                    </button>
                </div>
            <?php } ?>
        <?php } ?>

        <?php if ($error) { ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php } ?>

        <?php if ($from_checkout && !$message) { ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span><strong>Info:</strong> Setelah menambahkan alamat, halaman checkout akan direfresh otomatis dan window ini akan tertutup.</span>
            </div>
        <?php } ?>

        <div class="content-grid">
            <div>
                <?php if (empty($alamat_list)) { ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Belum Ada Alamat</h3>
                        <p>Tambahkan alamat pengiriman pertama Anda menggunakan form di samping</p>
                    </div>
                <?php } else { ?>
                    <div class="address-list">
                        <?php foreach ($alamat_list as $alamat) { ?>
                            <div class="address-card">
                                <div class="address-header">
                                    <span class="address-label">
                                        <i class="fas fa-home"></i>
                                        <?php echo htmlspecialchars($alamat['label_alamat']); ?>
                                    </span>
                                    <?php if ($alamat['is_default'] == 1) { ?>
                                        <span class="address-default">
                                            <i class="fas fa-star"></i> Utama
                                        </span>
                                    <?php } ?>
                                </div>
                                
                                <div class="address-body">
                                    <h3><?php echo htmlspecialchars($alamat['nama_penerima']); ?></h3>
                                    <div class="address-phone">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($alamat['nomor_telepon']); ?>
                                    </div>
                                    <div class="address-detail">
                                        <?php echo nl2br(htmlspecialchars($alamat['alamat_lengkap'])); ?><br>
                                        <?php 
                                        $location_parts = array_filter([
                                            $alamat['kelurahan'],
                                            $alamat['kecamatan'],
                                            $alamat['kota'],
                                            $alamat['provinsi'],
                                            $alamat['kode_pos']
                                        ]);
                                        echo htmlspecialchars(implode(', ', $location_parts));
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="address-actions">
                                    <?php if ($alamat['is_default'] == 0) { ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="alamat_id" value="<?php echo $alamat['alamat_id']; ?>">
                                            <button type="submit" class="btn btn-outline">
                                                <i class="fas fa-star"></i> Jadikan Utama
                                            </button>
                                        </form>
                                    <?php } ?>
                                    
                                    <a href="?edit=<?php echo $alamat['alamat_id']; ?><?php echo $from_checkout ? '&from=checkout' : ''; ?>" class="btn btn-secondary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    
                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus alamat ini?')" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="alamat_id" value="<?php echo $alamat['alamat_id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($from_checkout && !empty($alamat_list)) { ?>
                    <!-- Additional Back Button at Bottom -->
                    <div class="back-to-checkout-section" style="margin-top: 2rem;">
                        <p><i class="fas fa-arrow-left"></i> Sudah selesai mengelola alamat?</p>
                        <button onclick="refreshAndClose()" class="btn btn-back-checkout">
                            Kembali ke Checkout
                        </button>
                    </div>
                <?php } ?>
            </div>

            <!-- Form Add/Edit -->
            <div class="form-card">
                <h3>
                    <i class="fas fa-<?php echo $edit_alamat ? 'edit' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_alamat ? 'Edit Alamat' : 'Tambah Alamat Baru'; ?>
                </h3>
                
                <form method="POST" id="addressForm">
                    <input type="hidden" name="action" value="<?php echo $edit_alamat ? 'edit' : 'add'; ?>">
                    <?php if ($edit_alamat) { ?>
                        <input type="hidden" name="alamat_id" value="<?php echo $edit_alamat['alamat_id']; ?>">
                    <?php } ?>
                    
                    <div class="form-group">
                        <label>Label Alamat <span class="required">*</span></label>
                        <input type="text" name="label_alamat" class="form-control" 
                               placeholder="Contoh: Rumah, Kantor, Kos" 
                               value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['label_alamat']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Penerima <span class="required">*</span></label>
                        <input type="text" name="nama_penerima" class="form-control" 
                               placeholder="Nama lengkap penerima" 
                               value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['nama_penerima']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Telepon <span class="required">*</span></label>
                        <input type="tel" name="nomor_telepon" class="form-control" 
                               placeholder="08xxxxxxxxxx" 
                               value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['nomor_telepon']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Lengkap <span class="required">*</span></label>
                        <textarea name="alamat_lengkap" class="form-control" 
                                  placeholder="Nama jalan, nomor rumah, RT/RW, patokan" 
                                  required><?php echo $edit_alamat ? htmlspecialchars($edit_alamat['alamat_lengkap']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Kelurahan</label>
                            <input type="text" name="kelurahan" class="form-control" 
                                   placeholder="Kelurahan" 
                                   value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['kelurahan']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Kecamatan</label>
                            <input type="text" name="kecamatan" class="form-control" 
                                   placeholder="Kecamatan" 
                                   value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['kecamatan']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Kota <span class="required">*</span></label>
                            <input type="text" name="kota" class="form-control" 
                                   placeholder="Kota" 
                                   value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['kota']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Provinsi <span class="required">*</span></label>
                            <input type="text" name="provinsi" class="form-control" 
                                   placeholder="Provinsi" 
                                   value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['provinsi']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Kode Pos</label>
                        <input type="text" name="kode_pos" class="form-control" 
                               placeholder="12345" 
                               maxlength="10"
                               value="<?php echo $edit_alamat ? htmlspecialchars($edit_alamat['kode_pos']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_default" id="is_default" 
                                   <?php echo ($edit_alamat && $edit_alamat['is_default'] == 1) ? 'checked' : ''; ?>>
                            <label for="is_default" style="margin: 0; font-weight: normal;">
                                Jadikan alamat utama
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <?php if ($edit_alamat) { ?>
                            <a href="kelola_alamat.php<?php echo $from_checkout ? '?from=checkout' : ''; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        <?php } ?>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i>
                            <?php echo $edit_alamat ? 'Update Alamat' : 'Simpan Alamat'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to refresh parent and close window
        function refreshAndClose() {
            if (window.opener) {
                window.opener.location.reload();
                setTimeout(function() {
                    window.close();
                }, 500);
            } else {
                window.location.href = 'checkout.php';
            }
        }

        // Auto close after successful save (if from checkout)
        <?php if ($from_checkout && $message && !$edit_alamat) { ?>
            setTimeout(function() {
                refreshAndClose();
            }, 2000);
        <?php } ?>

        // Handle form submission
        document.getElementById('addressForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        });

        // Warning when trying to close window with unsaved changes
        let formChanged = false;
        const formInputs = document.querySelectorAll('#addressForm input, #addressForm textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged && !document.getElementById('addressForm').submitted) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.getElementById('addressForm').addEventListener('submit', function() {
            this.submitted = true;
            formChanged = false;
        });

        // ESC key to close (only if from checkout)
        <?php if ($from_checkout) { ?>
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !formChanged) {
                    if (confirm('Tutup halaman ini dan kembali ke checkout?')) {
                        refreshAndClose();
                    }
                }
            });
        <?php } ?>
    </script>
</body>
</html>