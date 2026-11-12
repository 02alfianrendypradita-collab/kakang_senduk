
<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

// ==========================================
// PROTEKSI HALAMAN PEMILIH
// ==========================================

if (!isset($_SESSION['pemilih_id'])) {
    header("Location: login-pemilih.php");
    exit;
}

$idPemilih = (int) $_SESSION['pemilih_id'];
$namaPemilih = $_SESSION['pemilih_nama'] ?? 'Pemilih';

// ==========================================
// AMBIL PERIODE VOTING AKTIF
// ==========================================

$queryPeriode = mysqli_query(
    $conn,
    "SELECT *
     FROM periode_voting
     WHERE status = 'Aktif'
     AND CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai
     ORDER BY id DESC
     LIMIT 1"
);

$periode = mysqli_fetch_assoc($queryPeriode);

$idPeriode = $periode ? (int) $periode['id'] : 0;

// ==========================================
// HITUNG JUMLAH KONTESTAN
// ==========================================

$queryKakang = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM kontestan
     WHERE kategori = 'Kakang'"
);

$totalKakang = (int) mysqli_fetch_assoc($queryKakang)['total'];

$querySenduk = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM kontestan
     WHERE kategori = 'Senduk'"
);

$totalSenduk = (int) mysqli_fetch_assoc($querySenduk)['total'];

// ==========================================
// STATUS VOTING DAN PEMBAYARAN
// ==========================================

$sudahKakang = false;
$sudahSenduk = false;

$statusKakang = null;
$statusSenduk = null;

if ($periode) {

    // Periksa voting yang sudah tercatat
    $stmt = mysqli_prepare(
        $conn,
        "SELECT kategori
         FROM voting
         WHERE user_id = ? AND periode_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "ii", $idPemilih, $idPeriode);
    mysqli_stmt_execute($stmt);

    $resultVoting = mysqli_stmt_get_result($stmt);

    while ($vote = mysqli_fetch_assoc($resultVoting)) {

        if ($vote['kategori'] === 'Kakang') {
            $sudahKakang = true;
        }

        if ($vote['kategori'] === 'Senduk') {
            $sudahSenduk = true;
        }
    }

    mysqli_stmt_close($stmt);

    // Periksa status pembayaran
    $stmtBayar = mysqli_prepare(
        $conn,
        "SELECT kategori, status
         FROM pembayaran
         WHERE user_id = ? AND periode_id = ?
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param($stmtBayar, "ii", $idPemilih, $idPeriode);
    mysqli_stmt_execute($stmtBayar);

    $resultBayar = mysqli_stmt_get_result($stmtBayar);

    while ($bayar = mysqli_fetch_assoc($resultBayar)) {

        if ($bayar['kategori'] === 'Kakang' && $statusKakang === null) {
            $statusKakang = $bayar['status'];
        }

        if ($bayar['kategori'] === 'Senduk' && $statusSenduk === null) {
            $statusSenduk = $bayar['status'];
        }
    }

    mysqli_stmt_close($stmtBayar);
}

// ==========================================
// FUNGSI TAMPILAN STATUS PEMBAYARAN
// ==========================================

function tampilStatusPembayaran($status)
{
    if ($status === 'Menunggu') {
        return '<span class="payment-status waiting">● Menunggu Verifikasi</span>';
    }

    if ($status === 'Disetujui') {
        return '<span class="payment-status approved">✓ Pembayaran Disetujui</span>';
    }

    if ($status === 'Ditolak') {
        return '<span class="payment-status rejected">✕ Pembayaran Ditolak</span>';
    }

    return '<span class="payment-status empty">Belum Ada Pembayaran</span>';
}

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Pemilih | Kakang Senduk Ponorogo</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
}

/* SIDEBAR */

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 25px 18px;
    background: #111;
    border-right: 1px solid #493b20;
    overflow-y: auto;
}

.logo {
    text-align: center;
    padding: 15px 5px 30px;
    border-bottom: 1px solid #45371c;
}

.logo-icon {
    color: #e4c36b;
    font-size: 42px;
}

.logo h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 16px;
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
    display: block;
    padding: 13px 15px;
    margin-bottom: 7px;
    border-radius: 8px;
    color: #aaa;
    text-decoration: none;
    font-size: 13px;
    transition: .3s;
}

.menu a:hover,
.menu a.active {
    color: #e9cb78;
    background: rgba(212,175,55,.12);
    border-left: 3px solid #d4af37;
}

.logout {
    display: block;
    padding: 12px;
    margin-top: 35px;
    border: 1px solid #713b32;
    border-radius: 8px;
    color: #ff8276;
    text-decoration: none;
    text-align: center;
    font-size: 12px;
    transition: .3s;
}

.logout:hover {
    background: rgba(180,50,40,.12);
}

/* MAIN */

.main {
    margin-left: 250px;
    padding: 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar h2 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 24px;
}

.topbar p {
    color: #888;
    font-size: 12px;
    margin-top: 7px;
}

.user-info {
    padding: 12px 17px;
    background: #17140d;
    border: 1px solid #59451e;
    border-radius: 9px;
    color: #e4c36b;
    font-size: 12px;
}

/* WELCOME */

.welcome {
    padding: 35px;
    background:
        linear-gradient(100deg,rgba(0,0,0,.95),rgba(35,27,12,.88)),
        #17130b;
    border: 1px solid #59451e;
    border-radius: 16px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}

.welcome::after {
    content: "♛";
    position: absolute;
    right: 35px;
    top: 5px;
    color: rgba(212,175,55,.10);
    font-size: 130px;
}

.welcome small {
    color: #d4af37;
    letter-spacing: 2px;
    font-size: 10px;
}

.welcome h1 {
    color: #f0d58a;
    font-family: 'Cinzel', serif;
    font-size: 27px;
    margin: 12px 0;
}

.welcome p {
    color: #aaa;
    font-size: 13px;
    max-width: 600px;
    line-height: 1.8;
}

/* STATISTIK */

.stats {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.stat-card {
    padding: 23px;
    background: #141414;
    border: 1px solid #493b20;
    border-radius: 13px;
}

.stat-icon {
    color: #d4af37;
    font-size: 25px;
}

.stat-card h3 {
    color: #e4c36b;
    font-size: 27px;
    margin-top: 12px;
}

.stat-card p {
    color: #999;
    font-size: 12px;
}

/* JUDUL */

.section-title {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 20px;
    margin-bottom: 17px;
}

/* PERIODE */

.period-card {
    padding: 22px;
    background: linear-gradient(120deg,#211b0e,#121212);
    border: 1px solid #59451e;
    border-radius: 13px;
    margin-bottom: 30px;
}

.period-card h3 {
    color: #e4c36b;
    font-size: 16px;
}

.period-card p {
    color: #999;
    font-size: 12px;
    margin-top: 9px;
}

.status-active,
.status-inactive {
    display: inline-block;
    padding: 6px 12px;
    margin-top: 13px;
    border-radius: 20px;
    font-size: 10px;
}

.status-active {
    background: rgba(40,160,90,.12);
    color: #72d49a;
}

.status-inactive {
    background: rgba(180,50,40,.12);
    color: #ff8585;
}

/* KARTU VOTING */

.vote-grid {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.vote-card {
    padding: 25px;
    background: #141414;
    border: 1px solid #493b20;
    border-radius: 15px;
    transition: .3s;
}

.vote-card:hover {
    border-color: #d4af37;
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(212,175,55,.08);
}

.vote-icon {
    font-size: 37px;
    color: #e4c36b;
    margin-bottom: 15px;
}

.vote-card h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 19px;
}

.vote-card p {
    color: #999;
    font-size: 12px;
    line-height: 1.7;
    margin: 10px 0 17px;
}

.vote-status {
    color: #aaa;
    font-size: 11px;
    margin-bottom: 15px;
}

/* STATUS PEMBAYARAN */

.payment-status {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 7px;
    font-size: 11px;
    margin: 5px 0 15px;
}

.waiting {
    color: #f5cb65;
    background: rgba(245,203,101,.10);
    border: 1px solid rgba(245,203,101,.3);
}

.approved {
    color: #65df91;
    background: rgba(40,160,90,.10);
    border: 1px solid rgba(40,160,90,.3);
}

.rejected {
    color: #ff8585;
    background: rgba(180,50,40,.10);
    border: 1px solid rgba(180,50,40,.3);
}

.empty {
    color: #aaa;
    background: #222;
    border: 1px solid #444;
}

/* BUTTON */

.btn-vote {
    display: block;
    padding: 12px;
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: .3s;
}

.btn-vote:hover {
    box-shadow: 0 5px 18px rgba(212,175,55,.2);
    transform: translateY(-2px);
}

.btn-vote.disabled {
    background: #292929;
    color: #999;
    cursor: not-allowed;
}

/* CATATAN */

.notice {
    margin-top: 25px;
    padding: 17px;
    background: rgba(212,175,55,.07);
    border: 1px solid #59451e;
    border-radius: 10px;
    color: #c7b17b;
    font-size: 12px;
    line-height: 1.8;
}

/* RESPONSIVE */

@media(max-width:900px) {
    .stats {
        grid-template-columns: 1fr 1fr;
    }

    .vote-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width:700px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .welcome {
        padding: 25px;
    }

    .welcome h1 {
        font-size: 21px;
    }

    .stats {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="logo">
        <div class="logo-icon">♛</div>
        <h3>KAKANG SENDUK</h3>
        <p>PONOROGO</p>
    </div>

    <div class="menu-title">MENU PEMILIH</div>

    <nav class="menu">

        <a href="dashboard-pemilih.php" class="active">
            🏠 Dashboard
        </a>

        <a href="semua-peserta.php">
            👥 Lihat Semua Peserta
        </a>

        <a href="pilih-kakang.php">
            👑 Voting Kakang
        </a>

        <a href="pilih-senduk.php">
            🌸 Voting Senduk
        </a>

        <a href="status-pembayaran.php">
            💳 Status Pembayaran
        </a>

    </nav>

    <a href="logout-pemilih.php" class="logout">
        ↪ Keluar dari Akun
    </a>

</aside>

<!-- KONTEN UTAMA -->

<main class="main">

    <!-- TOPBAR -->

    <div class="topbar">

        <div>
            <h2>Dashboard Pemilih</h2>
            <p>Selamat datang di sistem pemilihan Kakang Senduk Ponorogo</p>
        </div>

        <div class="user-info">
            ♙ <?= htmlspecialchars($namaPemilih) ?>
        </div>

    </div>

    <!-- WELCOME -->

    <section class="welcome">

        <small>SELAMAT DATANG, PEMILIH</small>

        <h1>
            Sugeng Rawuh,<br>
            <?= htmlspecialchars($namaPemilih) ?>!
        </h1>

        <p>
            Gunakan hak suara Anda untuk menentukan generasi muda
            terbaik yang akan menjadi representasi budaya dan pariwisata
            Kabupaten Ponorogo.
        </p>

    </section>

    <!-- STATISTIK -->

    <section class="stats">

        <div class="stat-card">
            <div class="stat-icon">♛</div>
            <h3><?= $totalKakang ?></h3>
            <p>Total Kontestan Kakang</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">♕</div>
            <h3><?= $totalSenduk ?></h3>
            <p>Total Kontestan Senduk</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <h3><?= (int)$sudahKakang + (int)$sudahSenduk ?>/2</h3>
            <p>Suara yang Sudah Diberikan</p>
        </div>

    </section>

    <!-- STATUS PEMBAYARAN -->

    <h2 class="section-title">Status Pembayaran Anda</h2>

    <section class="vote-grid">

        <article class="vote-card">

            <div class="vote-icon">👑</div>

            <h3>Pembayaran Kakang</h3>

            <p>
                Informasi pembayaran untuk kategori Kakang.
            </p>

            <?= tampilStatusPembayaran($statusKakang) ?>

            <a href="status-pembayaran.php" class="btn-vote">
                LIHAT DETAIL PEMBAYARAN
            </a>

        </article>

        <article class="vote-card">

            <div class="vote-icon">🌸</div>

            <h3>Pembayaran Senduk</h3>

            <p>
                Informasi pembayaran untuk kategori Senduk.
            </p>

            <?= tampilStatusPembayaran($statusSenduk) ?>

            <a href="status-pembayaran.php" class="btn-vote">
                LIHAT DETAIL PEMBAYARAN
            </a>

        </article>

    </section>

    <!-- PERIODE PEMILIHAN -->

    <h2 class="section-title">Periode Pemilihan</h2>

    <section class="period-card">

        <?php if ($periode): ?>

            <h3><?= htmlspecialchars($periode['nama_periode']) ?></h3>

            <p>
                Tanggal:
                <?= date('d F Y', strtotime($periode['tanggal_mulai'])) ?>
                sampai
                <?= date('d F Y', strtotime($periode['tanggal_selesai'])) ?>
            </p>

            <span class="status-active">
                ● VOTING SEDANG BERLANGSUNG
            </span>

        <?php else: ?>

            <h3>Belum Ada Periode Voting Aktif</h3>

            <p>
                Saat ini belum tersedia periode pemilihan yang sedang berlangsung.
                Silakan kembali lagi nanti.
            </p>

            <span class="status-inactive">
                ● VOTING BELUM DIBUKA
            </span>

        <?php endif; ?>

    </section>

    <!-- PILIH KONTESTAN -->

    <h2 class="section-title">Pilih Kontestan Favorit Anda</h2>

    <section class="vote-grid">

        <!-- KAKANG -->

        <article class="vote-card">

            <div class="vote-icon">♛</div>

            <h3>Pemilihan Kakang</h3>

            <p>
                Pilih satu kandidat Kakang terbaik dari seluruh
                peserta yang mengikuti pemilihan.
            </p>

            <div class="vote-status">

                <?php if ($sudahKakang): ?>

                    ✓ Anda sudah memilih Kakang.

                <?php else: ?>

                    ○ Anda belum memilih Kakang.

                <?php endif; ?>

            </div>

            <?php if (!$periode): ?>

                <span class="btn-vote disabled">
                    Voting Belum Dibuka
                </span>

            <?php elseif ($sudahKakang): ?>

                <span class="btn-vote disabled">
                    Sudah Memilih
                </span>

            <?php else: ?>

                <a href="pilih-kakang.php" class="btn-vote">
                    PILIH KAKANG SEKARANG
                </a>

            <?php endif; ?>

        </article>

        <!-- SENDUK -->

        <article class="vote-card">

            <div class="vote-icon">♕</div>

            <h3>Pemilihan Senduk</h3>

            <p>
                Pilih satu kandidat Senduk terbaik dari seluruh
                peserta yang mengikuti pemilihan.
            </p>

            <div class="vote-status">

                <?php if ($sudahSenduk): ?>

                    ✓ Anda sudah memilih Senduk.

                <?php else: ?>

                    ○ Anda belum memilih Senduk.

                <?php endif; ?>

            </div>

            <?php if (!$periode): ?>

                <span class="btn-vote disabled">
                    Voting Belum Dibuka
                </span>

            <?php elseif ($sudahSenduk): ?>

                <span class="btn-vote disabled">
                    Sudah Memilih
                </span>

            <?php else: ?>

                <a href="pilih-senduk.php" class="btn-vote">
                    PILIH SENDUK SEKARANG
                </a>

            <?php endif; ?>

        </article>

    </section>

    <!-- CATATAN -->

    <div class="notice">

        <strong>Perhatian:</strong>

        Setiap pemilih hanya memiliki satu suara untuk kategori Kakang
        dan satu suara untuk kategori Senduk dalam satu periode pemilihan.

        Pastikan pilihan Anda sudah benar sebelum melakukan konfirmasi.

    </div>

</main>

</body>
</html>
