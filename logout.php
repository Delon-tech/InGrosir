<?php
session_start();

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan sesi
session_destroy();

// Alihkan pengguna ke halaman login
header("Location: login.php");
exit();
?>