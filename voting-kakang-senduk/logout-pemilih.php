
<?php
session_start();

// Hapus sesi khusus pemilih
unset($_SESSION['pemilih_id']);
unset($_SESSION['pemilih_nama']);
unset($_SESSION['pemilih_email']);

session_regenerate_id(true);

header("Location: login-pemilih.php");
exit;
