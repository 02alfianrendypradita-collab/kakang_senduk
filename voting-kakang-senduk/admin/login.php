
<?php
session_start();
require_once '../config/koneksi.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, nama, email, password, role
         FROM users
         WHERE email = ? AND role = 'admin'
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_nama'] = $admin['nama'];
        $_SESSION['admin_email'] = $admin['email'];

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Email atau password administrator salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Administrator | Kakang Senduk</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    background:
        radial-gradient(ellipse at 15% 20%, rgba(173,124,32,.18), transparent 40%),
        radial-gradient(ellipse at 85% 80%, rgba(173,124,32,.15), transparent 40%),
        #090909;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.wrapper {
    width: 100%;
    max-width: 1050px;
    min-height: 620px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    border: 1px solid rgba(212,175,55,.35);
    border-radius: 20px;
    overflow: hidden;
    background: #111;
    box-shadow: 0 20px 80px rgba(0,0,0,.7),
                0 0 30px rgba(212,175,55,.08);
}

.left {
    padding: 55px 40px;
    background:
        linear-gradient(rgba(0,0,0,.35),rgba(0,0,0,.85)),
        repeating-linear-gradient(
            45deg,
            #20190c 0px,
            #20190c 2px,
            #100e09 2px,
            #100e09 8px
        );
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
    border-right: 1px solid rgba(212,175,55,.3);
    position: relative;
}

.ornament {
    font-size: 85px;
    color: #d4af37;
    text-shadow: 0 0 30px rgba(212,175,55,.5);
    margin-bottom: 15px;
}

.brand {
    font-family: 'Cinzel', serif;
    font-size: 31px;
    letter-spacing: 4px;
    color: #e5c46a;
}

.subtitle {
    color: #c6a75c;
    font-size: 12px;
    letter-spacing: 4px;
    margin-top: 12px;
}

.description {
    color: #aaa;
    font-size: 13px;
    line-height: 2;
    margin-top: 35px;
}

.right {
    padding: 65px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.label-top {
    color: #c9a34a;
    letter-spacing: 3px;
    font-size: 11px;
    font-weight: 600;
}

h2 {
    font-family: 'Cinzel', serif;
    font-size: 28px;
    margin-top: 13px;
    color: #f0d58a;
}

.intro {
    font-size: 12px;
    color: #999;
    margin-top: 10px;
    margin-bottom: 35px;
}

.form-group {
    margin-bottom: 22px;
}

label {
    display: block;
    font-size: 12px;
    margin-bottom: 10px;
    color: #d5c28f;
}

input {
    width: 100%;
    padding: 15px;
    background: #1b1b1b;
    border: 1px solid #393126;
    border-radius: 9px;
    color: white;
    outline: none;
    transition: .3s;
}

input:focus {
    border-color: #d4af37;
    box-shadow: 0 0 15px rgba(212,175,55,.15);
}

button {
    width: 100%;
    padding: 15px;
    margin-top: 8px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
    font-weight: 700;
    letter-spacing: 2px;
    cursor: pointer;
    transition: .3s;
}

button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(212,175,55,.25);
}

.error {
    padding: 12px;
    background: rgba(180,40,40,.15);
    border: 1px solid #8f3838;
    color: #ff9b9b;
    border-radius: 8px;
    font-size: 12px;
    margin-bottom: 20px;
}

.footer {
    text-align: center;
    margin-top: 35px;
    font-size: 10px;
    color: #666;
    letter-spacing: 1px;
}

@media(max-width:750px) {
    .wrapper {
        grid-template-columns: 1fr;
    }

    .left {
        padding: 35px 20px;
        min-height: 250px;
        border-right: none;
        border-bottom: 1px solid #55431e;
    }

    .ornament {
        font-size: 55px;
    }

    .brand {
        font-size: 23px;
    }

    .description {
        margin-top: 15px;
    }

    .right {
        padding: 40px 25px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

    <div class="left">
        <div class="ornament">♛</div>

        <div class="brand">KAKANG SENDUK</div>

        <div class="subtitle">KOTA PONOROGO</div>

        <div class="description">
            SISTEM INFORMASI PEMILIHAN<br>
            DUTA WISATA DAERAH<br><br>
            Melestarikan budaya, mengembangkan
            pariwisata, dan memperkenalkan
            keindahan Ponorogo.
        </div>
    </div>

    <div class="right">

        <div class="label-top">ADMINISTRATOR PANEL</div>

        <h2>Selamat Datang</h2>

        <p class="intro">
            Silakan login untuk mengelola sistem pemilihan.
        </p>

        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label>Email Administrator</label>
                <input
                    type="email"
                    name="email"
                    placeholder="Masukkan email"
                    required
                >
            </div>

            <div class="form-group">
                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    placeholder="Masukkan password"
                    required
                >
            </div>

            <button type="submit">
                LOGIN ADMINISTRATOR
            </button>

        </form>

        <div class="footer">
            © 2026 KAKANG SENDUK PONOROGO
            <br>TRADITION • CULTURE • TOURISM
        </div>

    </div>

</div>

</body>
</html>
