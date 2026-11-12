
<?php
session_start();
require_once 'config/koneksi.php';

// Cek login pemilih
if (!isset($_SESSION['pemilih_id'])) {
    header("Location: login-pemilih.php");
    exit;
}

$id_pemilih = (int) $_SESSION['pemilih_id'];

// Ambil semua pembayaran milik pemilih
$stmt = mysqli_prepare($conn, "
    SELECT
        p.id,
        p.user_id,
        p.kontestan_id,
        p.periode_id,
        p.kategori,
        p.metode_pembayaran,
        p.jumlah,
        p.bukti_pembayaran,
        p.status,
        p.tanggal_pembayaran,
        k.nama AS nama_kontestan,
        k.nomor_peserta,
        pr.nama_periode
    FROM pembayaran p
    LEFT JOIN kontestan k ON p.kontestan_id = k.id
    LEFT JOIN periode_voting pr ON p.periode_id = pr.id
    WHERE p.user_id = ?
    ORDER BY p.id DESC
");

mysqli_stmt_bind_param($stmt, "i", $id_pemilih);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$pembayaran = [];

while ($row = mysqli_fetch_assoc($result)) {
    $pembayaran[] = $row;
}

mysqli_stmt_close($stmt);

// Hitung ringkasan status
$total = count($pembayaran);
$menunggu = 0;
$disetujui = 0;
$ditolak = 0;

foreach ($pembayaran as $data) {
    if ($data['status'] === 'Menunggu') {
        $menunggu++;
    } elseif ($data['status'] === 'Disetujui') {
        $disetujui++;
    } elseif ($data['status'] === 'Ditolak') {
        $ditolak++;
    }
}

// Fungsi keamanan output
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Format status
function statusClass($status) {
    if ($status === 'Disetujui') {
        return 'disetujui';
    }

    if ($status === 'Ditolak') {
        return 'ditolak';
    }

    return 'menunggu';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Status Pembayaran | Voting Kakang Senduk</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #090909;
    color: #eee;
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
}

.sidebar {
    width: 245px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #111;
    border-right: 1px solid rgba(212,175,55,.25);
    padding: 28px 18px;
}

.logo {
    color: #d4af37;
    font-family: 'Cinzel', serif;
    font-size: 19px;
    text-align: center;
    margin-bottom: 40px;
}

.menu-title {
    color: #777;
    font-size: 10px;
    margin: 20px 10px 12px;
    letter-spacing: 2px;
}

.menu a {
    display: block;
    color: #aaa;
    text-decoration: none;
    padding: 13px 14px;
    border-radius: 8px;
    margin-bottom: 8px;
    font-size: 12px;
    transition: .3s;
}

.menu a:hover,
.menu a.active {
    background: rgba(212,175,55,.12);
    color: #e4c36b;
    border: 1px solid rgba(212,175,55,.25);
}

.main {
    margin-left: 245px;
    padding: 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px;
    gap: 15px;
}

.topbar h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 19px;
}

.topbar span {
    color: #aaa;
    font-size: 12px;
}

.heading {
    margin-bottom: 25px;
}

.heading h1 {
    color: #d4af37;
    font-family: 'Cinzel', serif;
    font-size: 29px;
    margin-bottom: 10px;
}

.heading p {
    color: #999;
    font-size: 12px;
    line-height: 1.8;
}

.ringkasan {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 25px;
}

.box {
    background: #151515;
    border: 1px solid rgba(212,175,55,.2);
    padding: 20px;
    border-radius: 12px;
}

.box p {
    color: #999;
    font-size: 11px;
}

.box h2 {
    color: #e4c36b;
    font-size: 25px;
    margin-top: 8px;
}

.card {
    background: #151515;
    border: 1px solid rgba(212,175,55,.2);
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 20px;
}

.card h2 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 18px;
    margin-bottom: 20px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    color: #d4af37;
    text-align: left;
    font-size: 11px;
    padding: 14px 12px;
    border-bottom: 1px solid #39301a;
}

td {
    color: #ccc;
    font-size: 11px;
    padding: 15px 12px;
    border-bottom: 1px solid #292929;
}

.badge {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.menunggu {
    color: #f5cb65;
    background: rgba(245,203,101,.12);
    border: 1px solid rgba(245,203,101,.3);
}

.disetujui {
    color: #65df91;
    background: rgba(65,200,110,.12);
    border: 1px solid rgba(65,200,110,.3);
}

.ditolak {
    color: #ff8585;
    background: rgba(255,70,70,.12);
    border: 1px solid rgba(255,70,70,.3);
}

.bukti {
    color: #e4c36b;
    text-decoration: none;
}

.bukti:hover {
    text-decoration: underline;
}

.info-status {
    color: #aaa;
    font-size: 11px;
    line-height: 1.8;
    margin-top: 18px;
}

.kosong {
    text-align: center;
    padding: 45px 15px;
}

.kosong h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    margin-bottom: 10px;
}

.kosong p {
    color: #999;
    font-size: 12px;
}

.btn {
    display: inline-block;
    margin-top: 18px;
    padding: 11px 18px;
    background: linear-gradient(135deg, #b58a24, #f0d77a);
    border-radius: 7px;
    color: #17120a;
    text-decoration: none;
    font-weight: 700;
    font-size: 11px;
}

@media(max-width: 900px) {
    .sidebar {
        width: 190px;
    }

    .main {
        margin-left: 190px;
        padding: 25px;
    }

    .ringkasan {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 600px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 18px;
    }

    .logo {
        margin-bottom: 15px;
    }

    .menu-title {
        display: none;
    }

    .menu {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .menu a {
        margin: 0;
        padding: 10px;
        font-size: 10px;
    }

    .main {
        margin-left: 0;
        padding: 20px 15px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .heading h1 {
        font-size: 23px;
    }

    .ringkasan {
        grid-template-columns: repeat(2, 1fr);
    }
}

</style>

</head>

<body>

<aside class="sidebar">

    <div class="logo">VOTING KAKANG</div>

    <div class="menu-title">MENU PEMILIH</div>

    <nav class="menu">

        <a href="dashboard-pemilih.php">
            🏠 Dashboard
        </a>

        <a href="semua-peserta.php">
            👥 Semua Peserta
        </a>

        <a href="pilih-kakang.php">
            👑 Voting Kakang
        </a>

        <a href="pilih-senduk.php">
            🌸 Voting Senduk
        </a>

        <a href="status-pembayaran.php" class="active">
            💳 Status Pembayaran
        </a>

        <a href="logout-pemilih.php">
            🚪 Logout
        </a>

    </nav>

</aside>

<main class="main">

    <div class="topbar">

        <h3>STATUS PEMBAYARAN</h3>

        <span>
            Selamat datang, <?= e($_SESSION['pemilih_nama'] ?? 'Pemilih') ?>
        </span>

    </div>

    <div class="heading">

        <h1>Status Pembayaran</h1>

        <p>
            Pantau seluruh pengajuan pembayaran voting Kakang dan Senduk Anda.
        </p>

    </div>

    <!-- RINGKASAN -->

    <div class="ringkasan">

        <div class="box">
            <p>Total Pengajuan</p>
            <h2><?= $total ?></h2>
        </div>

        <div class="box">
            <p>Menunggu Verifikasi</p>
            <h2><?= $menunggu ?></h2>
        </div>

        <div class="box">
            <p>Pembayaran Disetujui</p>
            <h2><?= $disetujui ?></h2>
        </div>

        <div class="box">
            <p>Pembayaran Ditolak</p>
            <h2><?= $ditolak ?></h2>
        </div>

    </div>

    <!-- DAFTAR PEMBAYARAN -->

    <div class="card">

        <h2>Riwayat Pembayaran</h2>

        <?php if (count($pembayaran) > 0): ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Peserta</th>
                            <th>Kategori</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Tanggal</th>
                            <th>Bukti</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($pembayaran as $i => $data): ?>

                        <tr>

                            <td><?= $i + 1 ?></td>

                            <td>
                                <strong><?= e($data['nama_kontestan'] ?? 'Peserta tidak ditemukan') ?></strong>
                                <br>
                                <span style="color:#888">
                                    <?= e($data['nomor_peserta'] ?? '-') ?>
                                </span>
                            </td>

                            <td><?= e($data['kategori']) ?></td>

                            <td><?= e($data['metode_pembayaran']) ?></td>

                            <td style="color:#e4c36b;font-weight:600">
                                Rp <?= number_format((float)$data['jumlah'], 0, ',', '.') ?>
                            </td>

                            <td>
                                <?= e($data['tanggal_pembayaran']) ?>
                            </td>

                            <td>
                                <?php if (!empty($data['bukti_pembayaran'])): ?>

                                    <a
                                        class="bukti"
                                        href="uploads/pembayaran/<?= rawurlencode(basename($data['bukti_pembayaran'])) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Lihat Bukti
                                    </a>

                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge <?= statusClass($data['status']) ?>">
                                    <?= e($data['status']) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <p class="info-status">
                Pembayaran berstatus Menunggu sedang diperiksa oleh administrator.
                Suara hanya boleh dihitung setelah pembayaran disetujui dan
                proses pencatatan suara dilakukan oleh sistem.
            </p>

        <?php else: ?>

            <div class="kosong">

                <h3>Belum Ada Pembayaran</h3>

                <p>
                    Kamu belum mengirim pengajuan pembayaran voting.
                </p>

                <a href="semua-peserta.php" class="btn">
                    LIHAT SEMUA PESERTA
                </a>

            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
