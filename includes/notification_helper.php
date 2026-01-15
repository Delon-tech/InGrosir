<?php
/**
 * Fungsi utama untuk kirim notifikasi ke user
 * 
 * @param int $user_id - ID user yang menerima notifikasi
 * @param string $judul - Judul notifikasi (contoh: "Pesanan Baru")
 * @param string $pesan - Isi pesan notifikasi
 * @param string $link - URL tujuan (optional)
 * @param string $icon - Icon notifikasi (optional, default: bell)
 * @return bool - True jika berhasil, False jika gagal
 */
function kirim_notifikasi($user_id, $judul, $pesan, $link = null, $icon = 'bell') {
    global $koneksi;
    
    // Validasi input
    if (empty($user_id) || empty($judul) || empty($pesan)) {
        return false;
    }
    
    // Escape string untuk keamanan
    $user_id = intval($user_id);
    $judul = mysqli_real_escape_string($koneksi, $judul);
    $pesan = mysqli_real_escape_string($koneksi, $pesan);
    $link = $link ? mysqli_real_escape_string($koneksi, $link) : NULL;
    $icon = mysqli_real_escape_string($koneksi, $icon);
    
    // Query insert notifikasi
    $query = "INSERT INTO notifications (user_id, judul, pesan, link, icon) 
              VALUES ($user_id, '$judul', '$pesan', " . ($link ? "'$link'" : "NULL") . ", '$icon')";
    
    $result = mysqli_query($koneksi, $query);
    
    if ($result) {
        // Log success (optional, untuk debugging)
        error_log("✅ Notifikasi berhasil dikirim ke user #$user_id: $judul");
        return true;
    } else {
        // Log error
        error_log("❌ Gagal kirim notifikasi: " . mysqli_error($koneksi));
        return false;
    }
}

/**
 * Ambil jumlah notifikasi yang belum dibaca
 * 
 * @param int $user_id - ID user
 * @return int - Jumlah notifikasi belum dibaca
 */
function hitung_notifikasi_belum_dibaca($user_id) {
    global $koneksi;
    
    $user_id = intval($user_id);
    $query = "SELECT COUNT(*) as total FROM notifications 
              WHERE user_id = $user_id AND sudah_dibaca = 0";
    
    $result = mysqli_query($koneksi, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return intval($row['total']);
    }
    
    return 0;
}

/**
 * Ambil daftar notifikasi user (untuk dropdown)
 * 
 * @param int $user_id - ID user
 * @param int $limit - Jumlah notifikasi yang ditampilkan (default: 5)
 * @return array - Array notifikasi
 */
function ambil_notifikasi_terbaru($user_id, $limit = 5) {
    global $koneksi;
    
    $user_id = intval($user_id);
    $limit = intval($limit);
    
    $query = "SELECT * FROM notifications 
              WHERE user_id = $user_id 
              ORDER BY dibuat_pada DESC 
              LIMIT $limit";
    
    $result = mysqli_query($koneksi, $query);
    $notifications = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    }
    
    return $notifications;
}

/**
 * Tandai notifikasi sebagai sudah dibaca
 * 
 * @param int $notification_id - ID notifikasi
 * @return bool - True jika berhasil
 */
function tandai_dibaca($notification_id) {
    global $koneksi;
    
    $notification_id = intval($notification_id);
    $query = "UPDATE notifications 
              SET sudah_dibaca = 1, dibaca_pada = NOW() 
              WHERE notification_id = $notification_id";
    
    return mysqli_query($koneksi, $query);
}

/**
 * Tandai SEMUA notifikasi user sebagai sudah dibaca
 * 
 * @param int $user_id - ID user
 * @return bool - True jika berhasil
 */
function tandai_semua_dibaca($user_id) {
    global $koneksi;
    
    $user_id = intval($user_id);
    $query = "UPDATE notifications 
              SET sudah_dibaca = 1, dibaca_pada = NOW() 
              WHERE user_id = $user_id AND sudah_dibaca = 0";
    
    return mysqli_query($koneksi, $query);
}

/**
 * Format waktu notifikasi menjadi relatif (contoh: "5 menit yang lalu")
 * 
 * @param string $datetime - Timestamp dari database
 * @return string - Waktu relatif dalam bahasa Indonesia
 */
function format_waktu_relatif($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam yang lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' hari yang lalu';
    } else {
        return date('d M Y, H:i', $timestamp);
    }
}

/**
 * Ambil icon class berdasarkan nama icon
 * 
 * @param string $icon - Nama icon
 * @return string - Font Awesome class
 */
function get_icon_class($icon) {
    $icons = [
        'bell' => 'fa-bell',
        'shopping-cart' => 'fa-shopping-cart',
        'credit-card' => 'fa-credit-card',
        'check-circle' => 'fa-check-circle',
        'truck' => 'fa-truck',
        'times-circle' => 'fa-times-circle',
        'info-circle' => 'fa-info-circle',
        'star' => 'fa-star',
        'gift' => 'fa-gift',
        'exclamation-triangle' => 'fa-exclamation-triangle'
    ];
    
    return isset($icons[$icon]) ? $icons[$icon] : 'fa-bell';
}

?>