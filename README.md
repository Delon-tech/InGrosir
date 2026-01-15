# InGrosir: Integrated Wholesale Information System 🛒

**Sistem Informasi Terpadu untuk Meningkatkan Strategi Branding dan Efektivitas Komunikasi pada Usaha Grosir di Indonesia.**

![Banner InGrosir](https://via.placeholder.com/1000x300?text=InGrosir+System+Banner)
*(Ganti link di atas dengan screenshot halaman depan website InGrosir Anda)*

## 📖 Tentang Projek
**InGrosir** adalah platform berbasis web yang dirancang untuk membantu pemilik usaha grosir dalam mengelola branding dan komunikasi dengan pelanggan secara lebih efektif. Sistem ini dikembangkan sebagai bagian dari penelitian tugas akhir untuk mengatasi kendala pemasaran konvensional di industri grosir.

Projek ini bertujuan untuk:
1.  **Digital Branding:** Memperkuat citra usaha grosir di ranah digital.
2.  **Efektivitas Komunikasi:** Mempermudah interaksi antara penjual grosir dan pengecer/pelanggan.
3.  **Manajemen Terpadu:** Mengelola katalog produk dan informasi usaha dalam satu pintu.

## ⭐ Fitur Unggulan

* **E-Catalog Management**: Pengelolaan data produk grosir yang terstruktur (Kategori, Harga Bertingkat, Stok).
* **Branding Tools**: Fitur untuk kustomisasi profil usaha agar terlihat lebih profesional.
* **Communication Hub**: Fitur pesan/chat atau notifikasi untuk mempermudah komunikasi dengan pelanggan.
* **Responsive Design**: Tampilan website yang optimal di Desktop maupun Mobile.
* **Admin Dashboard**: Panel kontrol untuk pemilik usaha memantau aktivitas sistem.

## 🛠️ Teknologi yang Digunakan

* **Frontend**: HTML5, CSS3, JavaScript (Bootstrap/Tailwind).
* **Backend**: PHP Native / [Laravel/CodeIgniter - Sesuaikan].
* **Database**: MySQL.
* **Server**: Apache (XAMPP).

## 🚀 Instalasi & Penggunaan (Lokal)

Ikuti langkah ini untuk menjalankan InGrosir di komputer Anda:

### Prasyarat
* Install **XAMPP** (atau web server sejenis).
* Web Browser (Chrome/Edge).

### Langkah Instalasi
1.  **Clone/Download Repo**:
    Simpan folder `InGrosir` ke dalam folder `htdocs` di XAMPP.
    `C:\xampp\htdocs\InGrosir`

2.  **Setup Database**:
    * Buka `localhost/phpmyadmin`.
    * Buat database baru bernama `ingrosir_db` (atau sesuaikan dengan file koneksi Anda).
    * Import file database `.sql` yang tersedia di folder `database` (jika ada).

3.  **Konfigurasi Koneksi**:
    Cek file koneksi (biasanya `koneksi.php` atau `config.php`) dan pastikan settingan database sudah benar:
    ```php
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "ingrosir_db";
    ```

4.  **Jalankan Aplikasi**:
    Buka browser dan akses: `http://localhost/InGrosir`

## 👥 Tim Pengembang (Author)

* **Muh Agil Zakaria** - *Lead Developer & Researcher*
* Universitas Negeri Makassar

---
*Dibuat untuk memenuhi tugas akhir/skripsi dengan judul: "Rancang Bangun Sistem Informasi Terpadu untuk Meningkatkan Strategi Branding dan Efektivitas Komunikasi pada Usaha Grosir di Indonesia".*
