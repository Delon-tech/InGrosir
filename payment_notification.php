<?php
// ... Logika untuk memverifikasi request dari Payment Gateway ...

// Ambil data dari request
// $data = file_get_contents('php://input');
// $order_id = $data['order_id'];
// $payment_status = $data['transaction_status'];

// Logika untuk mengupdate database
// if ($payment_status == 'settlement' || $payment_status == 'capture') {
//     // Update status pesanan di database menjadi 'diproses' atau 'paid'
//     $update_query = "UPDATE pesanan SET status_pesanan = 'diproses', payment_status = 'paid' WHERE order_id = ?";
// } elseif ($payment_status == 'expire' || $payment_status == 'cancel') {
//     // Update status pesanan menjadi 'cancelled'
// }

// Kirim response 200 OK ke Payment Gateway
http_response_code(200);
?>