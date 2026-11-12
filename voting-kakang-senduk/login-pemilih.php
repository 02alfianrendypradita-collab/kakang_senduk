
<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

// Jika sudah login sebagai pemilih
if (isset($_SESSION['pemilih_id'])) {
    header("Location: dashboard-pemilih.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Email dan password wajib diisi.";
    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, nama, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (
            $user &&
            $user['role'] === 'pemilih' &&
            password_verify($password, $user['password'])
        ) {
            session_regenerate_id(true);

            $_SESSION['pemilih_id'] = $user['id'];
            $_SESSION['pemilih_nama'] = $user['nama'];
            $_SESSION['pemilih_email'] = $user['email'];

            header("Location: dashboard-pemilih.php");
            exit;

        } else {
            $error = "Email atau password salah, atau akun bukan akun pemilih.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login Pemilih | Kakang Senduk Ponorogo</title>

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
    padding: 20px;
    background:
        radial-gradient(circle at top, rgba(212,175,55,.12), transparent 40%),
        #090909;
    color: #fff;
    font-family: 'Poppins', sans-serif;
}

.login-container {
    width: 100%;
    max-width: 420px;
    padding: 40px 35px;
    background: rgba(20,20,20,.96);
    border: 1px solid #59451e;
    border-radius: 18px;
    box-shadow: 0 15px 50px rgba(0,0,0,.5);
}

.logo {
    text-align: center;
    margin-bottom: 30px;
}

.logo-icon {
    color: #e4c36b;
    font-size: 55px;
}

.logo h1 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 23px;
    letter-spacing: 2px;
    margin-top: 10px;
}

.logo p {
    color: #a88b4b;
    font-size: 11px;
    letter-spacing: 4px;
    margin-top: 7px;
}

.heading {
    text-align: center;
    margin-bottom: 25px;
}

.heading h2 {
    font-size: 20px;
    color: #fff;
}

.heading p {
    color: #999;
    font-size: 12px;
    margin-top: 7px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    color: #d4af37;
    font-size: 12px;
    margin-bottom: 8px;
}

.form-group input {
    width: 100%;
    padding: 14px;
    background: #0d0d0d;
    border: 1px solid #44371e;
    border-radius: 9px;
    color: white;
    outline: none;
    font-family: inherit;
}

.form-group input:focus {
    border-color: #d4af37;
    box-shadow: 0 0 10px rgba(212,175,55,.15);
}

.btn-login {
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

.btn-login:hover {
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

.back {
    text-align: center;
    margin-top: 22px;
}

.back a {
    color: #d4af37;
    font-size: 12px;
    text-decoration: none;
}

.back a:hover {
    text-decoration: underline;
}

.footer {
    color: #666;
    font-size: 10px;
    text-align: center;
    margin-top: 30px;
}
</style>
</head>

<body>

<div class="login-container">

    <div class="logo">
        <div class="logo-icon">♛</div>
        <h1>KAKANG SENDUK</h1>
        <p>PONOROGO</p>
    </div>

    <div class="heading">
        <h2>Login Pemilih</h2>
        <p>Silakan masuk untuk mengikuti pemilihan</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

    <?php if (isset($_GET['register']) && $_GET['register'] === 'berhasil'): ?>
        <div style="padding:12px;margin-bottom:18px;background:rgba(40,150,80,.12);border:1px solid #39834e;color:#80d99a;border-radius:8px;font-size:12px;">
            Pendaftaran berhasil! Silakan login menggunakan akun Anda.
        </div>
    <?php endif; ?>

        <div class="form-group">
            <label>Email</label>
            <input
                type="email"
                name="email"
                placeholder="Masukkan email Anda"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="Masukkan password Anda"
                required
            >
        </div>

        <button type="submit" class="btn-login">
            MASUK SEKARANG
        </button>

    </form>

    <div class="back">
        <p style="color:#999;font-size:12px;margin-bottom:12px;">
            Belum memiliki akun?
            <a href="register.php" style="color:#e4c36b;text-decoration:none;">
                Daftar sekarang
            </a>
        </p>

        <a href="index.php">← Kembali ke halaman utama</a>
    </div>

    <div class="footer">
        © <?= date('Y') ?> Pemilihan Kakang Senduk Ponorogo
    </div>

</div>

</body>
</html>
