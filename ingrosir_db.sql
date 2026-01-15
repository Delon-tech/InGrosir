-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 06:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ingrosir_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `alamat_pengiriman`
--

CREATE TABLE `alamat_pengiriman` (
  `alamat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label_alamat` varchar(50) NOT NULL,
  `nama_penerima` varchar(255) NOT NULL,
  `nomor_telepon` varchar(15) NOT NULL,
  `alamat_lengkap` text NOT NULL,
  `kelurahan` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kota` varchar(100) NOT NULL,
  `provinsi` varchar(100) NOT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bukti_pembayaran`
--

CREATE TABLE `bukti_pembayaran` (
  `bukti_id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_pengirim` varchar(255) NOT NULL,
  `bank_pengirim` varchar(100) DEFAULT NULL,
  `nomor_rekening_pengirim` varchar(50) DEFAULT NULL,
  `tanggal_transfer` datetime NOT NULL,
  `jumlah_transfer` decimal(15,2) NOT NULL,
  `bukti_image` varchar(255) NOT NULL,
  `catatan` text DEFAULT NULL,
  `status_verifikasi` enum('pending','verified','rejected') DEFAULT 'pending',
  `alasan_reject` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `detail_id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_per_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `log_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `tipe_perubahan` enum('masuk','keluar') NOT NULL,
  `jumlah_perubahan` int(11) NOT NULL,
  `stok_sekarang` int(11) NOT NULL,
  `waktu_perubahan` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori_produk`
--

CREATE TABLE `kategori_produk` (
  `kategori_id` int(11) NOT NULL,
  `nama_kategori` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori_produk`
--

INSERT INTO `kategori_produk` (`kategori_id`, `nama_kategori`) VALUES
(1, 'Makanan & Minuman'),
(2, 'Pakaian & Fashion'),
(3, 'Elektronik & Gadget');

-- --------------------------------------------------------

--
-- Table structure for table `keranjang`
--

CREATE TABLE `keranjang` (
  `keranjang_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `tanggal_ditambah` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `keranjang`
--

INSERT INTO `keranjang` (`keranjang_id`, `user_id`, `produk_id`, `jumlah`, `tanggal_ditambah`) VALUES
(38, 7, 10, 1, '0000-00-00 00:00:00'),
(45, 16, 10, 1, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `metode_pembayaran_penjual`
--

CREATE TABLE `metode_pembayaran_penjual` (
  `metode_penjual_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `account_number` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `metode_pembayaran_penjual`
--

INSERT INTO `metode_pembayaran_penjual` (`metode_penjual_id`, `user_id`, `template_id`, `is_active`, `account_number`, `account_name`, `bank_name`, `qr_image`, `notes`, `created_at`, `updated_at`) VALUES
(1, 7, 1, 1, NULL, NULL, NULL, NULL, 'COD sesuai jam operasional', '2025-11-17 06:17:42', '2025-11-17 06:17:42'),
(2, 7, 2, 1, NULL, NULL, NULL, 'qris_7_1763360333.jpg', '', '2025-11-17 06:18:53', '2025-11-17 06:18:53'),
(3, 7, 5, 1, '0896879779', 'agil', NULL, NULL, 'oke', '2025-11-17 06:19:17', '2025-11-17 06:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `metode_pembayaran_template`
--

CREATE TABLE `metode_pembayaran_template` (
  `template_id` int(11) NOT NULL,
  `nama_metode` varchar(100) NOT NULL,
  `tipe_metode` enum('cod','qris','ewallet','transfer_bank') NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-credit-card',
  `deskripsi` text DEFAULT NULL,
  `requires_account_number` tinyint(1) DEFAULT 0,
  `requires_account_name` tinyint(1) DEFAULT 0,
  `requires_image` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `metode_pembayaran_template`
--

INSERT INTO `metode_pembayaran_template` (`template_id`, `nama_metode`, `tipe_metode`, `icon`, `deskripsi`, `requires_account_number`, `requires_account_name`, `requires_image`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'COD (Bayar di Tempat)', 'cod', 'fa-hand-holding-usd', 'Bayar langsung saat barang tiba/diambil', 0, 0, 0, 1, 1, '2025-11-16 15:02:47'),
(2, 'QRIS', 'qris', 'fa-qrcode', 'Scan QR Code untuk pembayaran instan', 0, 0, 1, 1, 2, '2025-11-16 15:02:47'),
(3, 'GoPay', 'ewallet', 'fa-wallet', 'Transfer via aplikasi GoPay', 1, 1, 0, 1, 3, '2025-11-16 15:02:47'),
(4, 'OVO', 'ewallet', 'fa-wallet', 'Transfer via aplikasi OVO', 1, 1, 0, 1, 4, '2025-11-16 15:02:47'),
(5, 'DANA', 'ewallet', 'fa-wallet', 'Transfer via aplikasi DANA', 1, 1, 0, 1, 5, '2025-11-16 15:02:47'),
(6, 'ShopeePay', 'ewallet', 'fa-wallet', 'Transfer via ShopeePay', 1, 1, 0, 1, 6, '2025-11-16 15:02:47'),
(7, 'Transfer BCA', 'transfer_bank', 'fa-university', 'Transfer ke rekening Bank BCA', 1, 1, 0, 1, 7, '2025-11-16 15:02:47'),
(8, 'Transfer Mandiri', 'transfer_bank', 'fa-university', 'Transfer ke rekening Bank Mandiri', 1, 1, 0, 1, 8, '2025-11-16 15:02:47'),
(9, 'Transfer BRI', 'transfer_bank', 'fa-university', 'Transfer ke rekening Bank BRI', 1, 1, 0, 1, 9, '2025-11-16 15:02:47'),
(10, 'Transfer BNI', 'transfer_bank', 'fa-university', 'Transfer ke rekening Bank BNI', 1, 1, 0, 1, 10, '2025-11-16 15:02:47');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ID user yang menerima notifikasi',
  `judul` varchar(255) NOT NULL COMMENT 'Judul notifikasi (contoh: Pesanan Baru)',
  `pesan` text NOT NULL COMMENT 'Isi pesan notifikasi',
  `link` varchar(255) DEFAULT NULL COMMENT 'URL tujuan saat notif diklik',
  `icon` varchar(50) DEFAULT 'bell' COMMENT 'Icon notifikasi (bell, shopping-cart, check-circle, dll)',
  `sudah_dibaca` tinyint(1) DEFAULT 0 COMMENT '0=belum dibaca, 1=sudah dibaca',
  `dibaca_pada` datetime DEFAULT NULL COMMENT 'Waktu notifikasi dibaca',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu notifikasi dibuat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel notifikasi in-app untuk user';

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ID user',
  `notif_pesanan_baru` tinyint(1) DEFAULT 1 COMMENT 'Notif saat ada pesanan baru (untuk penjual)',
  `notif_pembayaran` tinyint(1) DEFAULT 1 COMMENT 'Notif terkait pembayaran',
  `notif_pengiriman` tinyint(1) DEFAULT 1 COMMENT 'Notif terkait pengiriman',
  `notif_promosi` tinyint(1) DEFAULT 0 COMMENT 'Notif promosi/promo (default off)',
  `diperbarui_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Pengaturan notifikasi per user';

--
-- Dumping data for table `notification_settings`
--

INSERT INTO `notification_settings` (`setting_id`, `user_id`, `notif_pesanan_baru`, `notif_pembayaran`, `notif_pengiriman`, `notif_promosi`, `diperbarui_pada`) VALUES
(3, 7, 1, 1, 1, 0, '2025-11-24 21:20:23');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`email`, `token`, `created_at`) VALUES
('delon@gmail.com', '88e80db753e61507b0d0f52d1c4ef55083ba52eee0e27041a7c34ab23f13a8c3ae6b535790345672345b4a1b98915eaf9ff2', '2025-10-16 15:45:36'),
('nathan@gmail.com', 'e5fbd1c249798d61ef06c75dc942a7de4d3a125466c8e26db79a0369bc4a025353e6510d09a3d65953959549a5ee7ee9771c', '2025-12-03 15:47:15');

-- --------------------------------------------------------

--
-- Table structure for table `payment_log`
--

CREATE TABLE `payment_log` (
  `log_id` int(11) NOT NULL,
  `pesanan_id` int(11) NOT NULL,
  `bukti_id` int(11) DEFAULT NULL,
  `action_type` enum('upload','verify','reject','delete') NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `action_by` int(11) NOT NULL,
  `action_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `pesanan_id` int(11) NOT NULL,
  `user_id_penjual` int(11) NOT NULL,
  `metode_penjual_id` int(11) DEFAULT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `kode_voucher` varchar(50) DEFAULT NULL,
  `diskon_voucher` decimal(10,2) DEFAULT 0.00,
  `tanggal_pesanan` datetime NOT NULL,
  `status_pesanan` enum('pending','diproses','dikirim','selesai') NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `user_id_pembeli` int(11) DEFAULT NULL,
  `metode_pengiriman` enum('kurir','ambil_sendiri') DEFAULT 'kurir',
  `alamat_id` int(11) DEFAULT NULL,
  `catatan_pengiriman` text DEFAULT NULL,
  `is_notified` tinyint(1) NOT NULL,
  `payment_status` enum('waiting_for_payment','payment_uploaded','payment_verified','payment_rejected','paid') DEFAULT 'waiting_for_payment',
  `payment_proof_id` int(11) DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `nomor_resi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `produk_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `nama_produk` varchar(255) NOT NULL,
  `deskripsi_produk` text NOT NULL,
  `harga_grosir` decimal(10,2) NOT NULL,
  `stok` int(11) NOT NULL,
  `tanggal_dibuat` datetime NOT NULL,
  `terakhir_diupdate` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `gambar_produk` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`produk_id`, `user_id`, `kategori_id`, `nama_produk`, `deskripsi_produk`, `harga_grosir`, `stok`, `tanggal_dibuat`, `terakhir_diupdate`, `is_active`, `gambar_produk`) VALUES
(10, 7, 2, 'desa', 'dsds', 123121.00, 10, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, 'i6.png');

-- --------------------------------------------------------

--
-- Table structure for table `ulasan_produk`
--

CREATE TABLE `ulasan_produk` (
  `ulasan_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `user_id_pembeli` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `komentar` text NOT NULL,
  `tanggal_ulasan` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `nama_lengkap` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_grosir` varchar(255) DEFAULT NULL,
  `alamat_grosir` text DEFAULT NULL,
  `gambar_toko` varchar(255) DEFAULT NULL,
  `nomor_telepon` varchar(15) DEFAULT NULL,
  `tanggal_registrasi` datetime NOT NULL,
  `peran` varchar(20) NOT NULL DEFAULT 'penjual',
  `status_verifikasi` enum('pending','approved','rejected') DEFAULT 'approved',
  `tanggal_verifikasi` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `catatan_verifikasi` text DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `nama_lengkap`, `email`, `password`, `nama_grosir`, `alamat_grosir`, `gambar_toko`, `nomor_telepon`, `tanggal_registrasi`, `peran`, `status_verifikasi`, `tanggal_verifikasi`, `verified_by`, `catatan_verifikasi`, `is_admin`) VALUES
(7, 'Muh Agil Zakaria', 'muhagil282004@gmail.com', '$2y$10$v.uSggmO.tuun2J1I9Pi7un1IGq3OwbYDeIydBGXs5DbgF/CEsX2C', 'cahya merdeka', 'Kab. Maros, Kec. Mandai, BTN DHUTALONG PERMAI', 'assets/images/stores/toko (1).png', '093739882', '2025-09-03 14:00:04', 'penjual', 'approved', NULL, NULL, NULL, 1),
(16, 'nathan', 'nathan@gmail.com', '$2y$10$TlnNvGKkVyXIY9eSfo6ACu64SaMIKnxeYk9FPgtmfMZx20gmJ0DQy', NULL, NULL, NULL, NULL, '2025-12-07 22:27:51', 'pembeli', 'approved', NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_diskon`
--

CREATE TABLE `voucher_diskon` (
  `voucher_id` int(11) NOT NULL,
  `user_id_penjual` int(11) NOT NULL,
  `kode_voucher` varchar(50) NOT NULL,
  `tipe_diskon` enum('persentase','nominal') NOT NULL DEFAULT 'persentase',
  `nilai_diskon` decimal(10,2) NOT NULL,
  `min_pembelian` decimal(10,2) DEFAULT 0.00,
  `max_diskon` decimal(10,2) DEFAULT NULL,
  `kuota_total` int(11) DEFAULT NULL,
  `kuota_terpakai` int(11) DEFAULT 0,
  `tanggal_mulai` datetime NOT NULL,
  `tanggal_berakhir` datetime NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_diskon`
--

INSERT INTO `voucher_diskon` (`voucher_id`, `user_id_penjual`, `kode_voucher`, `tipe_diskon`, `nilai_diskon`, `min_pembelian`, `max_diskon`, `kuota_total`, `kuota_terpakai`, `tanggal_mulai`, `tanggal_berakhir`, `deskripsi`, `is_active`, `created_at`) VALUES
(2, 7, 'DELON', 'persentase', 5.00, 200000.00, NULL, 1, 0, '2025-12-31 18:02:00', '2026-01-30 18:02:00', 'voucher new year', 1, '2025-12-31 10:03:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alamat_pengiriman`
--
ALTER TABLE `alamat_pengiriman`
  ADD PRIMARY KEY (`alamat_id`),
  ADD KEY `idx_user_default` (`user_id`,`is_default`);

--
-- Indexes for table `bukti_pembayaran`
--
ALTER TABLE `bukti_pembayaran`
  ADD PRIMARY KEY (`bukti_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_pesanan` (`pesanan_id`),
  ADD KEY `idx_status` (`status_verifikasi`);

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `kategori_produk`
--
ALTER TABLE `kategori_produk`
  ADD PRIMARY KEY (`kategori_id`);

--
-- Indexes for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`keranjang_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `metode_pembayaran_penjual`
--
ALTER TABLE `metode_pembayaran_penjual`
  ADD PRIMARY KEY (`metode_penjual_id`),
  ADD UNIQUE KEY `unique_metode_per_penjual` (`user_id`,`template_id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `metode_pembayaran_template`
--
ALTER TABLE `metode_pembayaran_template`
  ADD PRIMARY KEY (`template_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_dibaca` (`user_id`,`sudah_dibaca`),
  ADD KEY `idx_dibuat` (`dibuat_pada`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_log`
--
ALTER TABLE `payment_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `bukti_id` (`bukti_id`),
  ADD KEY `action_by` (`action_by`),
  ADD KEY `idx_pesanan_log` (`pesanan_id`),
  ADD KEY `idx_action_date` (`action_at`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`pesanan_id`),
  ADD KEY `user_id_penjual` (`user_id_penjual`),
  ADD KEY `user_id_pembeli` (`user_id_pembeli`),
  ADD KEY `alamat_id` (`alamat_id`),
  ADD KEY `fk_pesanan_metode_penjual` (`metode_penjual_id`),
  ADD KEY `fk_payment_proof` (`payment_proof_id`),
  ADD KEY `voucher_id` (`voucher_id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`produk_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indexes for table `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  ADD PRIMARY KEY (`ulasan_id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `user_id_pembeli` (`user_id_pembeli`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_status_verifikasi` (`status_verifikasi`),
  ADD KEY `idx_peran_status` (`peran`,`status_verifikasi`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `voucher_diskon`
--
ALTER TABLE `voucher_diskon`
  ADD PRIMARY KEY (`voucher_id`),
  ADD UNIQUE KEY `kode_voucher` (`kode_voucher`),
  ADD KEY `user_id_penjual` (`user_id_penjual`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alamat_pengiriman`
--
ALTER TABLE `alamat_pengiriman`
  MODIFY `alamat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bukti_pembayaran`
--
ALTER TABLE `bukti_pembayaran`
  MODIFY `bukti_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kategori_produk`
--
ALTER TABLE `kategori_produk`
  MODIFY `kategori_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `keranjang_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `metode_pembayaran_penjual`
--
ALTER TABLE `metode_pembayaran_penjual`
  MODIFY `metode_penjual_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `metode_pembayaran_template`
--
ALTER TABLE `metode_pembayaran_template`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_log`
--
ALTER TABLE `payment_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `pesanan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `produk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  MODIFY `ulasan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `voucher_diskon`
--
ALTER TABLE `voucher_diskon`
  MODIFY `voucher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alamat_pengiriman`
--
ALTER TABLE `alamat_pengiriman`
  ADD CONSTRAINT `alamat_pengiriman_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `bukti_pembayaran`
--
ALTER TABLE `bukti_pembayaran`
  ADD CONSTRAINT `bukti_pembayaran_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`pesanan_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bukti_pembayaran_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bukti_pembayaran_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `metode_pembayaran_penjual`
--
ALTER TABLE `metode_pembayaran_penjual`
  ADD CONSTRAINT `metode_pembayaran_penjual_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `metode_pembayaran_penjual_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `metode_pembayaran_template` (`template_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_log`
--
ALTER TABLE `payment_log`
  ADD CONSTRAINT `payment_log_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`pesanan_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_log_ibfk_2` FOREIGN KEY (`bukti_id`) REFERENCES `bukti_pembayaran` (`bukti_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payment_log_ibfk_3` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_payment_proof` FOREIGN KEY (`payment_proof_id`) REFERENCES `bukti_pembayaran` (`bukti_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pesanan_metode_penjual` FOREIGN KEY (`metode_penjual_id`) REFERENCES `metode_pembayaran_penjual` (`metode_penjual_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`alamat_id`) REFERENCES `alamat_pengiriman` (`alamat_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`voucher_id`) REFERENCES `voucher_diskon` (`voucher_id`) ON DELETE SET NULL;

--
-- Constraints for table `voucher_diskon`
--
ALTER TABLE `voucher_diskon`
  ADD CONSTRAINT `voucher_diskon_ibfk_1` FOREIGN KEY (`user_id_penjual`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
