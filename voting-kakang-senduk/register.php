
<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

// Jika sudah login sebagai pemilih
if (isset($_SESSION['pemilih_id'])) {
    header("Location: dashboard-pemilih.php");
    exit;
}

$error = '';
$success = '';

$nama = '';
$email = '';
$no_hp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    // Validasi
    if ($nama === '' || $email === '' || $no_hp === '' ||
        $password === '' || $konfirmasi === '') {

        $error = "Semua kolom wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";

    } elseif (strlen($password) < 8) {

        $error = "Password minimal 8 karakter.";

    } elseif ($password !== $konfirmasi) {

        $error = "Konfirmasi password tidak sesuai.";

    } else {

        // Cek apakah email sudah terdaftar
        $cek = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($cek, "s", $email);
        mysqli_stmt_execute($cek);

        $hasilCek = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasilCek) > 0) {

            $error = "Email sudah terdaftar. Silakan gunakan email lain.";

        } else {

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Role selalu pemilih, tidak bisa ditentukan dari form
            $role = 'pemilih';

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (nama, email, no_hp, password, role)
                 VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $nama,
                $email,
                $no_hp,
                $passwordHash,
                $role
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);
                mysqli_stmt_close($cek);

                header("Location: login-pemilih.php?register=berhasil");
                exit;

            } else {

                $error = "Pendaftaran gagal. Silakan coba kembali.";

            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Register Pemilih | Kakang Senduk Ponorogo</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px 15px;
    background:
        radial-gradient(circle at top, rgba(212,175,55,.13), transparent 40%),
        #090909;
    color: #fff;
    font-family: 'Poppins', sans-serif;
}

.register-container {
    width: 100%;
    max-width: 480px;
    padding: 38px;
    background: rgba(20,20,20,.97);
    border: 1px solid #59451e;
    border-radius: 18px;
    box-shadow: 0 15px 50px rgba(0,0,0,.5);
}

.logo {
    text-align: center;
    margin-bottom: 28px;
}

.logo-icon {
    color: #e4c36b;
    font-size: 53px;
}

.logo h1 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 23px;
    letter-spacing: 2px;
    margin-top: 8px;
}

.logo p {
    color: #a88b4b;
    font-size: 10px;
    letter-spacing: 4px;
    margin-top: 7px;
}

.heading {
    text-align: center;
    margin-bottom: 25px;
}

.heading h2 {
    font-size: 20px;
}

.heading p {
    color: #999;
    font-size: 12px;
    margin-top: 7px;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    color: #d4af37;
    font-size: 12px;
    margin-bottom: 8px;
}

.form-group input {
    width: 100%;
    padding: 13px 14px;
    background: #0d0d0d;
    border: 1px solid #44371e;
    border-radius: 9px;
    color: white;
    outline: none;
    font-family: inherit;
    transition: .3s;
}

.form-group input:focus {
    border-color: #d4af37;
    box-shadow: 0 0 10px rgba(212,175,55,.15);
}

.btn-register {
    width: 100%;
    padding: 14px;
    margin-top: 8px;
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
    border: none;
    border-radius: 9px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: .3s;
}

.btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 20px rgba(212,175,55,.2);
}

.error {
    padding: 12px;
    margin-bottom: 18px;
    background: rgba(180,40,40,.12);
    border: 1px solid #843838;
    color: #ff8585;
    border-radius: 8px;
    font-size: 12px;
}

.login-link {
    text-align: center;
    margin-top: 22px;
    color: #999;
    font-size: 12px;
}

.login-link a {
    color: #e4c36b;
    text-decoration: none;
    font-weight: 600;
}

.login-link a:hover {
    text-decoration: underline;
}

.footer {
    color: #666;
    font-size: 10px;
    text-align: center;
    margin-top: 27px;
}

@media(max-width: 480px) {
    .register-container {
        padding: 27px 22px;
    }
}
</style>
</head>

<body>

<div class="register-container">

    <div class="logo">
        <div class="logo-icon">♛</div>
        <h1>KAKANG SENDUK</h1>
        <p>PONOROGO</p>
    </div>

    <div class="heading">
        <h2>Daftar Akun Pemilih</h2>
        <p>Buat akun untuk mengikuti pemilihan Kakang Senduk</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Nama Lengkap</label>
            <input
                type="text"
                name="nama"
                placeholder="Masukkan nama lengkap"
                value="<?= htmlspecialchars($nama) ?>"
                maxlength="150"
                required
            >
        </div>

        <div class="form-group">
            <label>Email</label>
            <input
                type="email"
                name="email"
                placeholder="contoh@email.com"
                value="<?= htmlspecialchars($email) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Nomor HP</label>
            <input
                type="tel"
                name="no_hp"
                placeholder="08xxxxxxxxxx"
                value="<?= htmlspecialchars($no_hp) ?>"
                maxlength="20"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="Minimal 8 karakter"
                minlength="8"
                required
            >
        </div>

        <div class="form-group">
            <label>Konfirmasi Password</label>
            <input
                type="password"
                name="konfirmasi_password"
                placeholder="Ulangi password"
                minlength="8"
                required
            >
        </div>

        <button type="submit" class="btn-register">
            DAFTAR SEKARANG
        </button>

    </form>

    <div class="login-link">
        Sudah memiliki akun?
        <a href="login-pemilih.php">Login di sini</a>
    </div>

    <div class="footer">
        © <?= date('Y') ?> Pemilihan Kakang Senduk Ponorogo
    </div>

</div>

</body>
</html>
