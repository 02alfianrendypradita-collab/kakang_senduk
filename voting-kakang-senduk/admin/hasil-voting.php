
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// CEK LOGIN ADMIN
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// AMBIL DAFTAR PERIODE
$queryPeriode = mysqli_query(
    $conn,
    "SELECT * FROM periode_voting ORDER BY id DESC"
);

$daftarPeriode = [];

while ($p = mysqli_fetch_assoc($queryPeriode)) {
    $daftarPeriode[] = $p;
}

// PILIH PERIODE
$periodeId = (int)($_GET['periode_id'] ?? 0);

if ($periodeId === 0 && count($daftarPeriode) > 0) {
    foreach ($daftarPeriode as $p) {
        if ($p['status'] === 'Aktif') {
            $periodeId = (int)$p['id'];
            break;
        }
    }

    if ($periodeId === 0) {
        $periodeId = (int)$daftarPeriode[0]['id'];
    }
}

// VALIDASI PERIODE
$periodeTerpilih = null;

foreach ($daftarPeriode as $p) {
    if ((int)$p['id'] === $periodeId) {
        $periodeTerpilih = $p;
        break;
    }
}

if (!$periodeTerpilih && count($daftarPeriode) > 0) {
    $periodeId = (int)$daftarPeriode[0]['id'];
    $periodeTerpilih = $daftarPeriode[0];
}

// DATA HASIL VOTING
$dataHasil = [];
$totalKakang = 0;
$totalSenduk = 0;
$totalSuara = 0;

if ($periodeTerpilih) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            k.id,
            k.nomor_peserta,
            k.nama,
            k.kategori,
            k.foto,
            k.asal_instansi,
            COUNT(v.id) AS total_suara
         FROM kontestan k
         LEFT JOIN voting v
            ON v.kontestan_id = k.id
            AND v.periode_id = ?
         GROUP BY
            k.id,
            k.nomor_peserta,
            k.nama,
            k.kategori,
            k.foto,
            k.asal_instansi
         ORDER BY
            k.kategori ASC,
            total_suara DESC,
            k.nama ASC"
    );

    mysqli_stmt_bind_param($stmt, "i", $periodeId);
    mysqli_stmt_execute($stmt);

    $resultHasil = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($resultHasil)) {

        $row['total_suara'] = (int)$row['total_suara'];

        $dataHasil[] = $row;

        if ($row['kategori'] === 'Kakang') {
            $totalKakang += $row['total_suara'];
        } elseif ($row['kategori'] === 'Senduk') {
            $totalSenduk += $row['total_suara'];
        }
    }

    mysqli_stmt_close($stmt);
}

$totalSuara = $totalKakang + $totalSenduk;

// HITUNG JUMLAH PEMILIH
$totalPemilih = 0;

if ($periodeTerpilih) {

    $stmtPemilih = mysqli_prepare(
        $conn,
        "SELECT COUNT(DISTINCT user_id) AS total
         FROM voting
         WHERE periode_id = ?"
    );

    mysqli_stmt_bind_param($stmtPemilih, "i", $periodeId);
    mysqli_stmt_execute($stmtPemilih);

    $resultPemilih = mysqli_stmt_get_result($stmtPemilih);
    $dataPemilih = mysqli_fetch_assoc($resultPemilih);

    $totalPemilih = (int)($dataPemilih['total'] ?? 0);

    mysqli_stmt_close($stmtPemilih);
}

// PERSENTASE
$persenKakang = $totalSuara > 0
    ? round(($totalKakang / $totalSuara) * 100, 1)
    : 0;

$persenSenduk = $totalSuara > 0
    ? round(($totalSenduk / $totalSuara) * 100, 1)
    : 0;

// FUNGSI FORMAT TANGGAL
function tanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';

    return date('d/m/Y', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Hasil Voting | Kakang Senduk Ponorogo</title>

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

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 28px;
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

/* PERIODE */
.filter-box {
    background: #131313;
    border: 1px solid #493b20;
    padding: 20px;
    border-radius: 13px;
    margin-bottom: 22px;
}

.filter-box label {
    display: block;
    color: #d4af37;
    font-size: 12px;
    margin-bottom: 10px;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-form select {
    flex: 1;
    min-width: 220px;
    background: #1d1d1d;
    border: 1px solid #493b20;
    color: white;
    padding: 12px;
    border-radius: 8px;
    font-family: inherit;
    outline: none;
}

.btn {
    display: inline-block;
    padding: 12px 17px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
    text-decoration: none;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: .3s;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(212,175,55,.2);
}

/* INFO PERIODE */
.period-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    padding: 20px;
    background: linear-gradient(120deg,#211b0e,#121212);
    border: 1px solid #59451e;
    border-radius: 13px;
    margin-bottom: 22px;
}

.period-info h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 16px;
}

.period-info p {
    color: #aaa;
    font-size: 11px;
    margin-top: 8px;
}

.period-status {
    color: #e4c36b;
    border: 1px solid #80652a;
    background: rgba(212,175,55,.1);
    border-radius: 20px;
    padding: 8px 13px;
    font-size: 10px;
}

/* STATISTIK */
.stats {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 15px;
    margin-bottom: 22px;
}

.stat-card {
    background: linear-gradient(135deg,#19160e,#111);
    border: 1px solid #51401f;
    border-radius: 13px;
    padding: 20px;
}

.stat-card .icon {
    color: #d4af37;
    font-size: 23px;
    margin-bottom: 12px;
}

.stat-card p {
    color: #999;
    font-size: 11px;
}

.stat-card h3 {
    color: #e4c36b;
    font-size: 27px;
    margin-top: 7px;
}

/* GRAFIK */
.chart-card {
    background: #131313;
    border: 1px solid #332b1b;
    border-radius: 15px;
    padding: 23px;
    margin-bottom: 22px;
}

.chart-card h3,
.table-head h3 {
    color: #e3c36e;
    font-family: 'Cinzel', serif;
    font-size: 16px;
    margin-bottom: 20px;
}

.chart-item {
    margin-bottom: 20px;
}

.chart-label {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #ccc;
    font-size: 12px;
    margin-bottom: 9px;
}

.chart-track {
    width: 100%;
    height: 13px;
    background: #292929;
    border-radius: 20px;
    overflow: hidden;
}

.chart-fill {
    height: 100%;
    border-radius: 20px;
    transition: width .5s;
}

.gold-fill {
    background: linear-gradient(90deg,#927027,#e5c36c);
}

.bronze-fill {
    background: linear-gradient(90deg,#79512d,#d6a16a);
}

/* TABEL */
.table-wrap {
    background: #131313;
    border: 1px solid #332b1b;
    border-radius: 15px;
    overflow: hidden;
}

.table-head {
    padding: 23px;
    border-bottom: 1px solid #332b1b;
}

.table-head h3 {
    margin: 0;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th {
    color: #d4af37;
    font-size: 10px;
    letter-spacing: 1px;
    text-align: left;
    padding: 17px;
    background: #1d1a13;
    white-space: nowrap;
}

td {
    padding: 16px 17px;
    border-bottom: 1px solid #29251d;
    color: #ccc;
    font-size: 11px;
}

tbody tr:hover {
    background: rgba(212,175,55,.04);
}

.foto {
    width: 43px;
    height: 52px;
    object-fit: cover;
    border-radius: 7px;
    border: 1px solid #a78334;
}

.nama {
    color: #e9cb78;
    font-weight: 600;
}

.kategori {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #332a15;
    color: #e5c46a;
    font-size: 10px;
}

.suara {
    color: #e4c36b;
    font-weight: 700;
    font-size: 13px;
}

.persen {
    color: #aaa;
}

.ranking {
    color: #d4af37;
    font-weight: 700;
    font-size: 15px;
}

.empty {
    padding: 40px;
    text-align: center;
    color: #888;
    font-size: 12px;
}

/* RESPONSIVE */
@media(max-width:1100px) {
    .stats {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:900px) {
    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;
        padding: 22px;
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
        padding: 18px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width:450px) {
    .stats {
        grid-template-columns: 1fr;
    }
}

/* CETAK */
@media print {
    body {
        background: white;
        color: black;
    }

    .sidebar,
    .topbar .admin,
    .filter-box,
    .no-print {
        display: none !important;
    }

    .main {
        margin: 0;
        padding: 10px;
    }

    .stat-card,
    .chart-card,
    .table-wrap,
    .period-info {
        background: white;
        color: black;
        border: 1px solid #ccc;
        break-inside: avoid;
    }

    .stat-card h3,
    .chart-card h3,
    .table-head h3,
    .period-info h3,
    .nama,
    .suara {
        color: black;
    }

    th {
        background: #eee;
        color: black;
    }

    td {
        color: black;
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
        <a href="pemilih.php">♙ Data Pemilih</a>
        <a href="hasil-voting.php" class="active">▤ Hasil Voting</a>
        <a href="peringkat.php">🏆 Peringkat Juara</a>
        <a href="periode.php">◷ Periode Voting</a>
        <a href="pembayaran.php">💳 Manajemen Pembayaran</a>
    </nav>

</aside>

<!-- MAIN -->
<main class="main">

    <div class="topbar">
        <div>
            <h2>Hasil Voting</h2>
            <p>Pantau perolehan suara kontestan Kakang dan Senduk</p>
        </div>

        <div class="admin">
            ♙ <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator') ?>
        </div>
    </div>

    <!-- FILTER PERIODE -->
    <section class="filter-box no-print">

        <label>Pilih Periode Voting</label>

        <form method="GET" class="filter-form">

            <select name="periode_id" required>

                <?php foreach ($daftarPeriode as $p): ?>

                    <option
                        value="<?= (int)$p['id'] ?>"
                        <?= (int)$p['id'] === $periodeId ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($p['nama_periode']) ?>
                        (<?= htmlspecialchars($p['status']) ?>)
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn">
                Tampilkan Hasil
            </button>

            <button type="button" class="btn" onclick="window.print()">
                Cetak Hasil
            </button>

        </form>

    </section>

    <?php if ($periodeTerpilih): ?>

        <!-- INFORMASI PERIODE -->
        <section class="period-info">

            <div>
                <h3>
                    <?= htmlspecialchars($periodeTerpilih['nama_periode']) ?>
                </h3>

                <p>
                    <?= tanggalIndonesia($periodeTerpilih['tanggal_mulai']) ?>
                    sampai
                    <?= tanggalIndonesia($periodeTerpilih['tanggal_selesai']) ?>
                </p>
            </div>

            <span class="period-status">
                ● <?= htmlspecialchars($periodeTerpilih['status']) ?>
            </span>

        </section>

        <!-- STATISTIK -->
        <section class="stats">

            <div class="stat-card">
                <div class="icon">♛</div>
                <p>Total Suara Masuk</p>
                <h3><?= number_format($totalSuara) ?></h3>
            </div>

            <div class="stat-card">
                <div class="icon">♙</div>
                <p>Pemilih yang Sudah Voting</p>
                <h3><?= number_format($totalPemilih) ?></h3>
            </div>

            <div class="stat-card">
                <div class="icon">♔</div>
                <p>Total Suara Kakang</p>
                <h3><?= number_format($totalKakang) ?></h3>
            </div>

            <div class="stat-card">
                <div class="icon">♕</div>
                <p>Total Suara Senduk</p>
                <h3><?= number_format($totalSenduk) ?></h3>
            </div>

        </section>

        <!-- GRAFIK -->
        <section class="chart-card">

            <h3>Perbandingan Suara</h3>

            <div class="chart-item">

                <div class="chart-label">
                    <span>Kakang</span>
                    <strong><?= $persenKakang ?>% (<?= number_format($totalKakang) ?> suara)</strong>
                </div>

                <div class="chart-track">
                    <div
                        class="chart-fill gold-fill"
                        style="width:<?= $persenKakang ?>%"
                    ></div>
                </div>

            </div>

            <div class="chart-item">

                <div class="chart-label">
                    <span>Senduk</span>
                    <strong><?= $persenSenduk ?>% (<?= number_format($totalSenduk) ?> suara)</strong>
                </div>

                <div class="chart-track">
                    <div
                        class="chart-fill bronze-fill"
                        style="width:<?= $persenSenduk ?>%"
                    ></div>
                </div>

            </div>

        </section>

        <!-- TABEL HASIL -->
        <section class="table-wrap">

            <div class="table-head">
                <h3>Perolehan Suara Kontestan</h3>
            </div>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th>PERINGKAT</th>
                            <th>FOTO</th>
                            <th>NOMOR</th>
                            <th>NAMA KONTESTAN</th>
                            <th>KATEGORI</th>
                            <th>JUMLAH SUARA</th>
                            <th>PERSENTASE</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($dataHasil) > 0): ?>

                        <?php
                        $peringkatKategori = [
                            'Kakang' => 0,
                            'Senduk' => 0
                        ];
                        ?>

                        <?php foreach ($dataHasil as $row): ?>

                            <?php
                            $kategoriRow = $row['kategori'];

                            if (!isset($peringkatKategori[$kategoriRow])) {
                                $peringkatKategori[$kategoriRow] = 0;
                            }

                            $peringkatKategori[$kategoriRow]++;

                            $persen = $kategoriRow === 'Kakang'
                                ? ($totalKakang > 0
                                    ? round(($row['total_suara'] / $totalKakang) * 100, 1)
                                    : 0)
                                : ($totalSenduk > 0
                                    ? round(($row['total_suara'] / $totalSenduk) * 100, 1)
                                    : 0);

                            $namaFoto = basename($row['foto'] ?? '');
                            $pathFoto = __DIR__ . '/../uploads/' . $namaFoto;
                            ?>

                            <tr>

                                <td class="ranking">
                                    #<?= $peringkatKategori[$kategoriRow] ?>
                                </td>

                                <td>
                                    <?php if ($namaFoto !== '' && is_file($pathFoto)): ?>

                                        <img
                                            class="foto"
                                            src="../uploads/<?= rawurlencode($namaFoto) ?>"
                                            alt="Foto kontestan"
                                        >

                                    <?php else: ?>

                                        <span>-</span>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['nomor_peserta']) ?>
                                </td>

                                <td class="nama">
                                    <?= htmlspecialchars($row['nama']) ?>
                                </td>

                                <td>
                                    <span class="kategori">
                                        <?= htmlspecialchars($row['kategori']) ?>
                                    </span>
                                </td>

                                <td class="suara">
                                    <?= number_format($row['total_suara']) ?>
                                </td>

                                <td class="persen">
                                    <?= $persen ?>%
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty">
                                Belum ada data kontestan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php else: ?>

        <section class="table-wrap">
            <div class="empty">
                <h3>Belum Ada Periode Voting</h3>
                <p>Silakan buat periode voting terlebih dahulu.</p>
                <br>
                <a href="periode.php" class="btn">
                    + Buat Periode
                </a>
            </div>
        </section>

    <?php endif; ?>

</main>

</body>
</html>
