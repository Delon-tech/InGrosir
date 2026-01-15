<?php
session_start();
include 'config/koneksi.php';
$koneksi = connectDB();

if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'pembeli') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$produk_id = isset($_GET['produk_id']) ? intval($_GET['produk_id']) : null;
$pesanan_id = isset($_GET['pesanan_id']) ? intval($_GET['pesanan_id']) : null;
$error = '';
$success = '';

if (!$produk_id) {
    header("Location: riwayat_pesanan.php");
    exit();
}

// Cek apakah user sudah pernah membeli produk ini dan pesanan sudah selesai
$query_check = "SELECT dp.detail_id, p.status_pesanan, prod.nama_produk, prod.gambar_produk
                FROM detail_pesanan dp
                JOIN pesanan p ON dp.pesanan_id = p.pesanan_id
                JOIN produk prod ON dp.produk_id = prod.produk_id
                WHERE dp.produk_id = ? AND p.user_id_pembeli = ? AND p.status_pesanan = 'selesai'
                LIMIT 1";
$stmt_check = mysqli_prepare($koneksi, $query_check);
mysqli_stmt_bind_param($stmt_check, "ii", $produk_id, $user_id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);

if (mysqli_num_rows($result_check) == 0) {
    header("Location: riwayat_pesanan.php?error=" . urlencode("Anda harus menyelesaikan pesanan terlebih dahulu untuk memberikan ulasan"));
    exit();
}

$produk_data = mysqli_fetch_assoc($result_check);

// Cek apakah sudah pernah memberikan ulasan
$query_ulasan_check = "SELECT ulasan_id FROM ulasan_produk WHERE produk_id = ? AND user_id_pembeli = ?";
$stmt_ulasan_check = mysqli_prepare($koneksi, $query_ulasan_check);
mysqli_stmt_bind_param($stmt_ulasan_check, "ii", $produk_id, $user_id);
mysqli_stmt_execute($stmt_ulasan_check);
$result_ulasan = mysqli_stmt_get_result($stmt_ulasan_check);

$sudah_ulasan = mysqli_num_rows($result_ulasan) > 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$sudah_ulasan) {
    $rating = intval($_POST['rating']);
    $komentar = trim($_POST['komentar']);

    if ($rating < 1 || $rating > 5) {
        $error = "Rating harus antara 1-5 bintang";
    } elseif (strlen($komentar) < 10) {
        $error = "Komentar minimal 10 karakter";
    } else {
        $query_ulasan = "INSERT INTO ulasan_produk (produk_id, user_id_pembeli, rating, komentar, tanggal_ulasan) VALUES (?, ?, ?, ?, NOW())";
        $stmt_ulasan = mysqli_prepare($koneksi, $query_ulasan);
        mysqli_stmt_bind_param($stmt_ulasan, "iiis", $produk_id, $user_id, $rating, $komentar);

        if (mysqli_stmt_execute($stmt_ulasan)) {
            $success = "Terima kasih! Ulasan Anda berhasil dikirim";
            header("refresh:2;url=produk_detail.php?id=" . $produk_id);
        } else {
            $error = "Gagal mengirim ulasan. Silakan coba lagi";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Ulasan - InGrosir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --warning: #f59e0b;
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
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .review-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .review-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .review-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            color: var(--warning);
        }
        
        .review-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .product-info {
            display: flex;
            gap: 1.5rem;
            padding: 2rem;
            background: var(--bg-gray);
            align-items: center;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            border-radius: var(--radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }
        
        .product-details h3 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }
        
        .product-details p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .review-form {
            padding: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
        }
        
        .star-rating {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .star {
            cursor: pointer;
            color: #d1d5db;
            transition: 0.2s;
        }
        
        .star:hover,
        .star.active {
            color: var(--warning);
            transform: scale(1.2);
        }
        
        .rating-text {
            text-align: center;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1.5rem;
            min-height: 30px;
        }
        
        textarea {
            width: 100%;
            min-height: 150px;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            resize: vertical;
            transition: 0.3s;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .char-count {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            flex: 1;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-primary:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-gray);
        }
        
        @media (max-width: 640px) {
            .product-info {
                flex-direction: column;
                text-align: center;
            }
            
            .star-rating {
                font-size: 2.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="review-container">
        <div class="review-header">
            <div class="review-icon">
                <i class="fas fa-star"></i>
            </div>
            <h1>Beri Ulasan Produk</h1>
            <p>Bagikan pengalaman Anda dengan produk ini</p>
        </div>

        <div class="product-info">
            <?php 
            $img_src = !empty($produk_data['gambar_produk']) 
                ? "uploads/" . htmlspecialchars($produk_data['gambar_produk']) 
                : "https://via.placeholder.com/100x100/667eea/ffffff?text=Produk";
            ?>
            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($produk_data['nama_produk']); ?>" class="product-image">
            <div class="product-details">
                <h3><?php echo htmlspecialchars($produk_data['nama_produk']); ?></h3>
                <p>Berikan penilaian Anda untuk membantu pembeli lain</p>
            </div>
        </div>

        <div class="review-form">
            <?php if ($sudah_ulasan) { ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <span>Anda sudah memberikan ulasan untuk produk ini</span>
                </div>
                <a href="produk_detail.php?id=<?php echo $produk_id; ?>" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-eye"></i>
                    Lihat Ulasan Saya
                </a>
            <?php } else { ?>
                <?php if ($success) { ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php } ?>

                <?php if ($error) { ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php } ?>

                <form method="POST" id="reviewForm">
                    <div class="form-group">
                        <label>Berikan Rating</label>
                        <div class="star-rating" id="starRating">
                            <span class="star" data-rating="1"><i class="far fa-star"></i></span>
                            <span class="star" data-rating="2"><i class="far fa-star"></i></span>
                            <span class="star" data-rating="3"><i class="far fa-star"></i></span>
                            <span class="star" data-rating="4"><i class="far fa-star"></i></span>
                            <span class="star" data-rating="5"><i class="far fa-star"></i></span>
                        </div>
                        <div class="rating-text" id="ratingText">Pilih rating Anda</div>
                        <input type="hidden" name="rating" id="ratingInput" required>
                    </div>

                    <div class="form-group">
                        <label for="komentar">Tulis Ulasan Anda</label>
                        <textarea 
                            id="komentar" 
                            name="komentar" 
                            placeholder="Ceritakan pengalaman Anda dengan produk ini... (minimal 10 karakter)"
                            required
                            minlength="10"
                            maxlength="500"
                        ></textarea>
                        <div class="char-count">
                            <span id="charCount">0</span>/500 karakter
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="riwayat_pesanan.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Batal
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-paper-plane"></i>
                            Kirim Ulasan
                        </button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>

    <script>
        let selectedRating = 0;
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('ratingInput');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        const komentarInput = document.getElementById('komentar');
        const charCount = document.getElementById('charCount');
        
        const ratingLabels = {
            1: '⭐ Buruk',
            2: '⭐⭐ Kurang',
            3: '⭐⭐⭐ Cukup',
            4: '⭐⭐⭐⭐ Baik',
            5: '⭐⭐⭐⭐⭐ Sangat Baik'
        };

        stars.forEach(star => {
            star.addEventListener('click', function() {
                selectedRating = this.dataset.rating;
                ratingInput.value = selectedRating;
                updateStars(selectedRating);
                ratingText.textContent = ratingLabels[selectedRating];
                checkFormValid();
            });

            star.addEventListener('mouseenter', function() {
                updateStars(this.dataset.rating);
            });
        });

        document.getElementById('starRating').addEventListener('mouseleave', function() {
            updateStars(selectedRating);
        });

        function updateStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                    star.innerHTML = '<i class="fas fa-star"></i>';
                } else {
                    star.classList.remove('active');
                    star.innerHTML = '<i class="far fa-star"></i>';
                }
            });
        }

        komentarInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            checkFormValid();
        });

        function checkFormValid() {
            const isValid = selectedRating > 0 && komentarInput.value.length >= 10;
            submitBtn.disabled = !isValid;
        }

        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            if (selectedRating === 0) {
                e.preventDefault();
                alert('Silakan pilih rating terlebih dahulu');
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
        });
    </script>
</body>
</html>