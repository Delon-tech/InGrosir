<?php
// Set Timezone agar waktu transaksi sesuai (WITA / Makassar)
date_default_timezone_set('Asia/Makassar');

function connectDB() {
    // Deteksi apakah sedang di Localhost (Laptop) atau Hosting
    $serverName = $_SERVER['SERVER_NAME'];

    if ($serverName == 'localhost' || $serverName == '127.0.0.1') {
        // === SETTINGAN LOKAL (LAPTOP) ===
        $host = 'localhost';
        $user = 'root';     // Default XAMPP
        $pass = '';         // Default XAMPP kosong
        $db   = 'ingrosir_db';
    } else {
        // === SETTINGAN HOSTING (RUMAHWEB) ===
        // Anda HARUS mengisi ini sesuai data dari cPanel Rumahweb
        $host = 'localhost'; // Di Rumahweb biasanya tetap 'localhost'
        
        // Contoh: u123456_useringrosir (Format hosting biasanya ada prefix user cpanel)
        $user = 'USERNAME_DATABASE_DARI_CPANEL'; 
        
        // Password yang Anda buat saat bikin user database di cPanel
        $pass = 'PASSWORD_DATABASE_ANDA'; 
        
        // Contoh: u123456_ingrosir_db
        $db   = 'NAMA_DATABASE_DARI_CPANEL'; 
    }

    // Melakukan Koneksi
    $koneksi = new mysqli($host, $user, $pass, $db);

    // Cek Koneksi
    if ($koneksi->connect_error) {
        // Jika di Laptop, tampilkan error lengkap untuk debugging
        if ($serverName == 'localhost' || $serverName == '127.0.0.1') {
            die("Koneksi Gagal (Local): " . $koneksi->connect_error);
        } else {
            // Jika di Hosting, JANGAN tampilkan error teknis ke pengunjung (Demi Keamanan)
            // Log error ke sistem server saja
            error_log("Database Connection Error: " . $koneksi->connect_error);
            // Tampilkan pesan ramah ke user
            die("Mohon maaf, sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.");
        }
    }

    // Set Charset ke UTF-8 agar emoji dan karakter khusus aman
    $koneksi->set_charset("utf8mb4");

    return $koneksi;
}
?>