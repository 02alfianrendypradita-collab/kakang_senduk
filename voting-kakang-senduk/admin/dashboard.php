
<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

function totalData($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['total'] ?? 0;
}

$total_kontestan = totalData(
    $conn,
    "SELECT COUNT(*) AS total FROM kontestan"
);

$total_pemilih = totalData(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role='pemilih'"
);

$total_kakang = totalData(
    $conn,
    "SELECT COUNT(*) AS total FROM voting WHERE kategori='Kakang'"
);

$total_senduk = totalData(
    $conn,
    "SELECT COUNT(*) AS total FROM voting WHERE kategori='Senduk'"
);

$total_suara = $total_kakang + $total_senduk;

$periode = mysqli_query(
    $conn,
    "SELECT * FROM periode_voting ORDER BY id DESC LIMIT 1"
);

$data_periode = mysqli_fetch_assoc($periode);

$nama_admin = htmlspecialchars($_SESSION['admin_nama']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Admin - Kakang Senduk</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #0b0b0b;
    color: #eee;
    font-family: 'Poppins', sans-serif;
}

.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #111;
    border-right: 1px solid #45371c;
    padding: 25px 18px;
}

.logo {
    text-align: center;
    padding: 15px 5px 30px;
    border-bottom: 1px solid #45371c;
}

.logo .crown {
    font-size: 38px;
    color: #d4af37;
}

.logo h3 {
    font-family: 'Cinzel', serif;
    color: #e4c36b;
    font-size: 17px;
    letter-spacing: 2px;
}

.logo p {
    color: #9b824a;
    font-size: 10px;
    letter-spacing: 3px;
    margin-top: 7px;
}

.menu-title {
    color: #927c4c;
    font-size: 10px;
    margin: 30px 10px 15px;
    letter-spacing: 2px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 15px;
    text-decoration: none;
    color: #aaa;
    font-size: 13px;
    border-radius: 8px;
    margin-bottom: 7px;
    transition: .3s;
}

.menu a:hover,
.menu a.active {
    color: #e9cb78;
    background: linear-gradient(
        90deg,
        rgba(212,175,55,.18),
        rgba(212,175,55,.03)
    );
    border-left: 3px solid #d4af37;
}

.main {
    margin-left: 260px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px;
    gap: 15px;
}

.topbar h2 {
    font-family: 'Cinzel', serif;
    color: #e2c16c;
    font-size: 23px;
}

.topbar p {
    color: #888;
    font-size: 12px;
    margin-top: 5px;
}

.admin {
    border: 1px solid #59451e;
    background: #17140e;
    padding: 12px 18px;
    border-radius: 10px;
    color: #e4c36b;
    font-size: 12px;
}

.welcome {
    padding: 30px;
    background:
        radial-gradient(circle at 90% 20%,rgba(212,175,55,.17),transparent 40%),
        linear-gradient(120deg,#201a0e,#111);
    border: 1px solid #59451e;
    border-radius: 15px;
    margin-bottom: 30px;
}

.welcome h1 {
    font-family: 'Cinzel', serif;
    color: #e9cd7d;
    font-size: 24px;
}

.welcome p {
    color: #aaa;
    font-size: 13px;
    margin-top: 12px;
    line-height: 1.8;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 18px;
}

.card {
    background: #151515;
    padding: 23px;
    border: 1px solid #332b1b;
    border-radius: 13px;
    transition: .3s;
}

.card:hover {
    transform: translateY(-5px);
    border-color: #b8953d;
    box-shadow: 0 8px 25px rgba(212,175,55,.08);
}

.card .icon {
    font-size: 25px;
    margin-bottom: 17px;
}

.card p {
    font-size: 12px;
    color: #999;
}

.card h2 {
    color: #e6c66d;
    font-size: 30px;
    margin-top: 8px;
}

.content {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
    margin-top: 25px;
}

.panel {
    background: #151515;
    border: 1px solid #332b1b;
    border-radius: 13px;
    padding: 25px;
}

.panel h3 {
    color: #e3c36e;
    font-family: 'Cinzel', serif;
    font-size: 17px;
    margin-bottom: 20px;
}

.panel p {
    color: #999;
    font-size: 12px;
    line-height: 2;
}

.status {
    display: inline-block;
    margin-top: 15px;
    padding: 7px 15px;
    border-radius: 30px;
    background: #302815;
    color: #e9c76b;
    font-size: 11px;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 12px;
}

.quick-links a {
    background: #211c11;
    border: 1px solid #57451f;
    border-radius: 8px;
    padding: 15px;
    color: #e3c36e;
    font-size: 12px;
    text-decoration: none;
    text-align: center;
}

.quick-links a:hover {
    background: #493817;
}

.logout {
    margin-top: 30px;
}

.logout a {
    display: block;
    color: #e58b80;
    border: 1px solid #61342e;
    padding: 12px;
    text-decoration: none;
    text-align: center;
    border-radius: 8px;
    font-size: 12px;
}

@media(max-width:1000px) {
    .stats {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:750px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .menu {
        display: grid;
        grid-template-columns: repeat(2,1fr);
        gap: 5px;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .content {
        grid-template-columns: 1fr;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media(max-width:450px) {
    .stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <div class="crown">♛</div>
        <h3>KAKANG SENDUK</h3>
        <p>PONOROGO</p>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <nav class="menu">
        <a href="dashboard.php" class="active">▣ Dashboard</a>
        <a href="kontestan.php">♛ Kontestan</a>
        <a href="pemilih.php">♙ Data Pemilih</a>
        <a href="hasil-voting.php">▤ Hasil Voting</a>
        <a href="peringkat.php">🏆 Peringkat Juara</a>
        <a href="periode.php">◷ Periode Voting</a>
        <a href="pembayaran.php">💳 Manajemen Pembayaran</a>
    </nav>

    <div class="logout">
        <a href="logout.php">↪ Keluar dari Admin</a>
    </div>

</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h2>Dashboard Administrator</h2>
            <p>Sistem Informasi Pemilihan Kakang Senduk Ponorogo</p>
        </div>

        <div class="admin">
            ♙ <?= $nama_admin ?>
        </div>
    </div>

    <section class="welcome">
        <h1>Sugeng Rawuh, <?= $nama_admin ?>!</h1>
        <p>
            Selamat datang di panel administrator.
            Kelola kontestan, pemilih, periode pemilihan,
            dan hasil voting Kakang Senduk Ponorogo
            melalui halaman ini.
        </p>
    </section>

    <section class="stats">

        <div class="card">
            <div class="icon">♛</div>
            <p>Total Kontestan</p>
            <h2><?= number_format($total_kontestan) ?></h2>
        </div>

        <div class="card">
            <div class="icon">♙</div>
            <p>Total Pemilih</p>
            <h2><?= number_format($total_pemilih) ?></h2>
        </div>

        <div class="card">
            <div class="icon">♕</div>
            <p>Suara Kakang</p>
            <h2><?= number_format($total_kakang) ?></h2>
        </div>

        <div class="card">
            <div class="icon">♛</div>
            <p>Suara Senduk</p>
            <h2><?= number_format($total_senduk) ?></h2>
        </div>

    </section>

    <section class="content">

        <div class="panel">
            <h3>Informasi Periode Voting</h3>

            <?php if ($data_periode): ?>

                <p>
                    Nama kegiatan:
                    <?= htmlspecialchars($data_periode['nama_periode']) ?>
                </p>

                <p>
                    Mulai:
                    <?= date('d-m-Y H:i', strtotime($data_periode['tanggal_mulai'])) ?>
                </p>

                <p>
                    Selesai:
                    <?= date('d-m-Y H:i', strtotime($data_periode['tanggal_selesai'])) ?>
                </p>

                <span class="status">
                    <?= htmlspecialchars($data_periode['status']) ?>
                </span>

            <?php else: ?>

                <p>Belum ada periode voting yang dibuat.</p>

            <?php endif; ?>
        </div>

        <div class="panel">
            <h3>Akses Cepat</h3>

            <div class="quick-links">
                <a href="kontestan.php">+ Kontestan</a>
                <a href="pemilih.php">Data Pemilih</a>
                <a href="hasil-voting.php">Hasil Voting</a>
                <a href="periode.php">Atur Periode</a>
            </div>
        </div>

    </section>

</main>

</body>
</html>
