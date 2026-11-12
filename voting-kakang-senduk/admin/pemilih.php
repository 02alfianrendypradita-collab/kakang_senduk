
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// CEK LOGIN ADMIN
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// PENCARIAN
$cari = trim($_GET['cari'] ?? '');

// QUERY DATA PEMILIH
$sql = "
    SELECT id, nama, email, no_hp, created_at
    FROM users
    WHERE role = 'pemilih'
";

$params = [];
$types = "";

if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR email LIKE ? OR no_hp LIKE ?)";

    $keyword = "%$cari%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types = "sss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$totalTampil = mysqli_num_rows($result);

// HITUNG TOTAL PEMILIH
$queryTotal = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'pemilih'"
);

$dataTotal = mysqli_fetch_assoc($queryTotal);
$totalPemilih = $dataTotal['total'] ?? 0;

// HITUNG PEMILIH HARI INI
$queryHariIni = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'pemilih'
     AND DATE(created_at) = CURDATE()"
);

$dataHariIni = mysqli_fetch_assoc($queryHariIni);
$totalHariIni = $dataHariIni['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Pemilih | Kakang Senduk Ponorogo</title>

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
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #111;
    border-right: 1px solid rgba(212,175,55,.25);
    padding: 25px 18px;
}

.logo {
    text-align: center;
    padding: 15px 5px 30px;
    border-bottom: 1px solid #45371c;
}

.logo div {
    color: #d4af37;
    font-size: 42px;
    margin-bottom: 8px;
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
    display: block;
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
    background: rgba(212,175,55,.12);
    border-left: 3px solid #d4af37;
    padding-left: 18px;
}

/* MAIN */
.main {
    margin-left: 260px;
    padding: 35px;
}

/* TOPBAR */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar h2 {
    color: #e2c16c;
    font-family: 'Cinzel', serif;
    font-size: 25px;
}

.topbar p {
    color: #888;
    font-size: 12px;
    margin-top: 7px;
}

.admin {
    color: #e4c36b;
    background: #17140d;
    border: 1px solid #59451e;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 12px;
}

/* STATISTIK */
.stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: linear-gradient(135deg,#19160e,#111);
    border: 1px solid #51401f;
    border-radius: 13px;
    padding: 23px;
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: "";
    position: absolute;
    width: 100px;
    height: 100px;
    right: -30px;
    top: -30px;
    border-radius: 50%;
    background: rgba(212,175,55,.08);
}

.stat-card .icon {
    font-size: 25px;
    margin-bottom: 12px;
}

.stat-card p {
    color: #999;
    font-size: 12px;
}

.stat-card h3 {
    color: #e4c36b;
    font-size: 29px;
    margin-top: 7px;
}

/* TOOLBAR */
.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.search {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

input {
    background: #181818;
    border: 1px solid #493b20;
    padding: 12px 14px;
    border-radius: 9px;
    color: white;
    outline: none;
    font-family: inherit;
    font-size: 12px;
    min-width: 280px;
}

input:focus {
    border-color: #d4af37;
    box-shadow: 0 0 10px rgba(212,175,55,.1);
}

button, .btn {
    border: none;
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
    padding: 12px 17px;
    border-radius: 9px;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    font-size: 12px;
    transition: .3s;
}

button:hover, .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(212,175,55,.2);
}

.btn-secondary {
    background: #27231a;
    border: 1px solid #57451f;
    color: #e3c36e;
}

/* TABEL */
.table-wrap {
    background: #131313;
    border: 1px solid #332b1b;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,.25);
}

.table-head {
    padding: 23px;
    border-bottom: 1px solid #332b1b;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.table-head h3 {
    font-family: 'Cinzel', serif;
    color: #e3c36e;
    font-size: 17px;
}

.table-head span {
    font-size: 12px;
    color: #aaa;
    background: #211c11;
    padding: 8px 13px;
    border-radius: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    color: #d4af37;
    font-size: 11px;
    letter-spacing: 1px;
    text-align: left;
    padding: 18px;
    background: #1d1a13;
    white-space: nowrap;
}

td {
    padding: 17px 18px;
    border-bottom: 1px solid #29251d;
    color: #ccc;
    font-size: 12px;
    vertical-align: middle;
}

tbody tr {
    transition: .25s;
}

tbody tr:hover {
    background: rgba(212,175,55,.045);
}

tbody tr:last-child td {
    border-bottom: none;
}

.nama-pemilih {
    color: #e9cb78;
    font-weight: 600;
}

.email {
    color: #aaa;
}

.nomor {
    color: #ccc;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 10px;
    background: rgba(45,150,90,.12);
    color: #80d6a2;
    border: 1px solid rgba(45,150,90,.2);
}

.empty {
    text-align: center;
    padding: 55px 20px;
    color: #888;
    line-height: 2;
}

/* RESPONSIVE */
@media(max-width: 900px) {
    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;
        padding: 22px;
    }
}

@media(max-width: 750px) {
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
        padding: 18px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .toolbar,
    .search {
        width: 100%;
    }

    .search input {
        min-width: 0;
        flex: 1;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">

    <div class="logo">
        <div>♛</div>
        <h3>KAKANG SENDUK</h3>
        <p>PONOROGO</p>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <nav class="menu">
        <a href="dashboard.php">▣ Dashboard</a>
        <a href="kontestan.php">♛ Kontestan</a>
        <a href="pemilih.php" class="active">♙ Data Pemilih</a>
        <a href="hasil-voting.php">▤ Hasil Voting</a>
        <a href="peringkat.php">🏆 Peringkat Juara</a>
        <a href="periode.php">◷ Periode Voting</a>
        <a href="pembayaran.php">💳 Manajemen Pembayaran</a>
    </nav>

</aside>

<!-- MAIN -->
<main class="main">

    <div class="topbar">

        <div>
            <h2>Data Pemilih</h2>
            <p>Kelola dan pantau pengguna yang terdaftar sebagai pemilih</p>
        </div>

        <div class="admin">
            ♙ <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator') ?>
        </div>

    </div>

    <!-- STATISTIK -->
    <section class="stats">

        <div class="stat-card">
            <div class="icon">♙</div>
            <p>Total Seluruh Pemilih</p>
            <h3><?= number_format((int)$totalPemilih) ?></h3>
        </div>

        <div class="stat-card">
            <div class="icon">✦</div>
            <p>Pemilih Mendaftar Hari Ini</p>
            <h3><?= number_format((int)$totalHariIni) ?></h3>
        </div>

    </section>

    <!-- PENCARIAN -->
    <div class="toolbar">

        <form method="GET" class="search">

            <input
                type="text"
                name="cari"
                placeholder="Cari nama, email, atau nomor HP..."
                value="<?= htmlspecialchars($cari) ?>"
            >

            <button type="submit">⌕ Cari</button>

            <a href="pemilih.php" class="btn btn-secondary">
                ↻ Reset
            </a>

        </form>

    </div>

    <!-- TABEL PEMILIH -->
    <section class="table-wrap">

        <div class="table-head">
            <h3>Daftar Pemilih Terdaftar</h3>
            <span><?= number_format((int)$totalTampil) ?> Data Ditampilkan</span>
        </div>

        <table>

            <thead>
                <tr>
                    <th>NO</th>
                    <th>NAMA PEMILIH</th>
                    <th>EMAIL</th>
                    <th>NOMOR HP</th>
                    <th>TANGGAL DAFTAR</th>
                    <th>STATUS</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($totalTampil > 0): ?>

                <?php $no = 1; ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td class="nama-pemilih">
                            <?= htmlspecialchars($row['nama'] ?? '-') ?>
                        </td>

                        <td class="email">
                            <?= htmlspecialchars($row['email'] ?? '-') ?>
                        </td>

                        <td class="nomor">
                            <?= htmlspecialchars($row['no_hp'] ?? '-') ?>
                        </td>

                        <td>
                            <?php
                            if (!empty($row['created_at'])) {
                                echo date(
                                    'd M Y, H:i',
                                    strtotime($row['created_at'])
                                );
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>

                        <td>
                            <span class="status">● Terdaftar</span>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        <div style="font-size:35px;margin-bottom:10px;">♙</div>
                        <strong>Data Pemilih Belum Ditemukan</strong>
                        <br>
                        Belum ada pemilih yang terdaftar atau pencarian tidak ditemukan.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>

</body>
</html>
