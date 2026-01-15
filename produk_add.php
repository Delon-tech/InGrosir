<?php
session_start();
include 'config/koneksi.php';
include 'config/helpers.php';
$csrf_token = generateCsrfToken();

$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'penjual') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Terjadi kesalahan keamanan. Silakan coba lagi.";
    } else {
        $nama_produk = trim($_POST['nama_produk']);
        $deskripsi_produk = trim($_POST['deskripsi_produk']);
        $harga_grosir = floatval($_POST['harga_grosir']);
        $stok = intval($_POST['stok']);
        $kategori_id = intval($_POST['kategori_id']);
        $gambar_produk = '';
        $is_active = 1;

        // Upload dan optimasi gambar
        if (isset($_FILES['gambar_produk']) && $_FILES['gambar_produk']['error'] == 0) {
            $target_dir = "uploads/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $new_filename = uniqid('prod_', true) . '.jpg';
            $destination_path = $target_dir . $new_filename;

            $source_path = $_FILES['gambar_produk']['tmp_name'];
            $image_info = getimagesize($source_path);
            
            if ($image_info) {
                $image_width = $image_info[0];
                $image_height = $image_info[1];
                $new_width = 800;
                $new_height = ($image_height / $image_width) * $new_width;
                $temp_image = imagecreatetruecolor($new_width, $new_height);
                $source_image = imagecreatefromstring(file_get_contents($source_path));
                imagecopyresampled($temp_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $image_width, $image_height);
                
                imagejpeg($temp_image, $destination_path, 85);
                imagedestroy($temp_image);
                imagedestroy($source_image);
                
                $gambar_produk = $new_filename;
            } else {
                $error = "File yang diupload bukan gambar valid.";
            }
        }

        if (!$error) {
            $query = "INSERT INTO produk (user_id, kategori_id, nama_produk, deskripsi_produk, harga_grosir, stok, gambar_produk, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, "iissdisi", $user_id, $kategori_id, $nama_produk, $deskripsi_produk, $harga_grosir, $stok, $gambar_produk, $is_active);
            
            if (mysqli_stmt_execute($stmt)) {
                header("Location: produk_list.php?success=added");
                exit();
            } else {
                $error = "Gagal menambah produk: " . mysqli_error($koneksi);
            }
        }
    }
}

// Ambil kategori
$kategori_query = "SELECT * FROM kategori_produk ORDER BY nama_kategori ASC";
$kategori_result = mysqli_query($koneksi, $kategori_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk Baru - InGrosir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --danger: #ef4444;
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
            background: var(--bg-gray);
            color: var(--text-primary);
            padding: 2rem 1rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        label .required {
            color: var(--danger);
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-hint {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        /* File Upload */
        .file-upload-wrapper {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            background: var(--bg-gray);
        }
        
        .file-upload-wrapper:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-wrapper.dragover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.1);
        }
        
        .file-upload-icon {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .file-upload-wrapper input[type="file"] {
            display: none;
        }
        
        .file-upload-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .file-upload-text strong {
            color: var(--primary);
        }
        
        /* Image Preview */
        .image-preview {
            margin-top: 1rem;
            display: none;
        }
        
        .image-preview.active {
            display: block;
        }
        
        .preview-container {
            position: relative;
            display: inline-block;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .remove-preview {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--danger);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .remove-preview:hover {
            transform: scale(1.1);
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }
        
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-secondary);
        }
        
        .btn-secondary:hover {
            border-color: var(--text-primary);
            color: var(--text-primary);
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            .form-card {
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-plus-circle"></i> Tambah Produk Baru</h1>
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <span>/</span>
                <a href="produk_list.php">Kelola Produk</a>
                <span>/</span>
                <span>Tambah Produk</span>
            </div>
        </div>

        <?php if ($error) { ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php } ?>

        <form action="produk_add.php" method="POST" enctype="multipart/form-data" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <!-- Informasi Dasar -->
            <div class="form-section">
                <h3 class="section-title">Informasi Dasar</h3>
                
                <div class="form-group">
                    <label for="nama_produk">Nama Produk <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="nama_produk" 
                        name="nama_produk" 
                        placeholder="Contoh: Beras Premium 5kg" 
                        required
                    >
                    <div class="form-hint">Gunakan nama yang jelas dan deskriptif</div>
                </div>

                <div class="form-group">
                    <label for="kategori_id">Kategori <span class="required">*</span></label>
                    <select id="kategori_id" name="kategori_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php while ($kategori = mysqli_fetch_assoc($kategori_result)) { ?>
                            <option value="<?php echo $kategori['kategori_id']; ?>">
                                <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deskripsi_produk">Deskripsi Produk <span class="required">*</span></label>
                    <textarea 
                        id="deskripsi_produk" 
                        name="deskripsi_produk" 
                        placeholder="Jelaskan detail produk Anda..." 
                        required
                    ></textarea>
                    <div class="form-hint">Minimal 50 karakter</div>
                </div>
            </div>

            <!-- Harga & Stok -->
            <div class="form-section">
                <h3 class="section-title">Harga & Stok</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="harga_grosir">Harga Grosir <span class="required">*</span></label>
                        <input 
                            type="number" 
                            id="harga_grosir" 
                            name="harga_grosir" 
                            step="0.01" 
                            min="0"
                            placeholder="100000" 
                            required
                        >
                        <div class="form-hint">Dalam Rupiah</div>
                    </div>

                    <div class="form-group">
                        <label for="stok">Stok Tersedia <span class="required">*</span></label>
                        <input 
                            type="number" 
                            id="stok" 
                            name="stok" 
                            min="0"
                            placeholder="100" 
                            required
                        >
                        <div class="form-hint">Jumlah unit</div>
                    </div>
                </div>
            </div>

            <!-- Gambar Produk -->
            <div class="form-section">
                <h3 class="section-title">Gambar Produk</h3>
                
                <div class="form-group">
                    <label>Upload Gambar</label>
                    <div class="file-upload-wrapper" id="fileUploadArea">
                        <div class="file-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="file-upload-text">
                            <strong>Klik untuk upload</strong> atau drag & drop gambar di sini<br>
                            <small>PNG, JPG, GIF hingga 5MB</small>
                        </div>
                        <input 
                            type="file" 
                            id="gambar_produk" 
                            name="gambar_produk" 
                            accept="image/*"
                            onchange="previewImage(this)"
                        >
                    </div>
                    
                    <div class="image-preview" id="imagePreview">
                        <div class="preview-container">
                            <img id="previewImg" class="preview-image" src="" alt="Preview">
                            <button type="button" class="remove-preview" onclick="removePreview()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="produk_list.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>

    <script>
        // File upload area click handler
        document.getElementById('fileUploadArea').addEventListener('click', function() {
            document.getElementById('gambar_produk').click();
        });

        // Drag and drop handlers
        const uploadArea = document.getElementById('fileUploadArea');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            }, false);
        });

        uploadArea.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length) {
                document.getElementById('gambar_produk').files = files;
                previewImage(document.getElementById('gambar_produk'));
            }
        });

        // Image preview
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.add('active');
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Remove preview
        function removePreview() {
            document.getElementById('gambar_produk').value = '';
            document.getElementById('imagePreview').classList.remove('active');
            document.getElementById('previewImg').src = '';
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const deskripsi = document.getElementById('deskripsi_produk').value;
            
            if (deskripsi.length < 50) {
                e.preventDefault();
                alert('Deskripsi produk harus minimal 50 karakter!');
                return false;
            }
        });

        // Format harga input
        const hargaInput = document.getElementById('harga_grosir');
        hargaInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>
</html>