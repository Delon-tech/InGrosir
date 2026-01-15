<?php
session_start();
require_once '../config/koneksi.php';
require_once '../includes/notification_helper.php';

$koneksi = connectDB();

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

// Hitung notifikasi belum dibaca
$count = hitung_notifikasi_belum_dibaca($_SESSION['user_id']);

echo json_encode(['count' => $count]);
?>