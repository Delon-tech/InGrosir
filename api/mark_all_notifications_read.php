<?php
session_start();
require_once '../config/koneksi.php';
require_once '../includes/notification_helper.php';

$koneksi = connectDB();

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Tandai semua sebagai dibaca
$result = tandai_semua_dibaca($user_id);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>