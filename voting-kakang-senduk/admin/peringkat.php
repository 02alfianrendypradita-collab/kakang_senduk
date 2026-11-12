
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// AMBIL PERIODE
$queryPeriode = mysqli_query(
    $conn,
    "SELECT * FROM periode_voting ORDER BY id DESC"
);

$periodeList = [];

while ($p = mysqli_fetch_assoc($queryPeriode)) {
    $periodeList[] = $p;
}

// PILIH PERIODE AKTIF ATAU TERBARU
$periodeId = (int)($_GET['periode_id'] ?? 0);

if ($periodeId === 0) {
    foreach ($periodeList as $p) {
        if ($p['status'] === 'Aktif') {
            $periodeId = (int)$p['id'];
            break;
        }
    }

    if ($periodeId === 0 && count($periodeList) > 0) {
        $periodeId = (int)$periodeList[0]['id'];
    }
}

$periodeTerpilih = null;

foreach ($periodeList as $p) {
    if ((int)$p['id'] === $periodeId) {
        $periodeTerpilih = $p;
        break;
    }
}

$hasil = [
    'Kakang' => [],
    'Senduk' => []
];

$totalSuara = [
    'Kakang' => 0,
    'Senduk' => 0
];

// AMBIL HASIL SUARA
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
            COUNT(v.id) AS jumlah_suara
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
         ORDER BY jumlah_suara DESC, k.nama ASC"
    );

    mysqli_stmt_bind_param($stmt, "i", $periodeId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {

        $kategori = $row['kategori'];

        if (!isset($hasil[$kategori])) {
            continue;
        }

        $row['jumlah_suara'] = (int)$row['jumlah_suara'];

        $hasil[$kategori][] = $row;
        $totalSuara[$kategori] += $row['jumlah_suara'];
    }

    mysqli_stmt_close($stmt);
}

// AMBIL TOP 3 DENGAN PERINGKAT SERI JIKA SUARA SAMA
function ambilJuara($data) {

    $juara = [];
    $peringkat = 0;
    $suaraSebelumnya = null;

    foreach ($data as $row) {

        if ($suaraSebelumnya === null ||
            $row['jumlah_suara'] !== $suaraSebelumnya) {
            $peringkat = count($juara) + 1;
        }

        if ($peringkat > 3) {
            break;
        }

        $row['peringkat'] = $peringkat;
        $juara[] = $row;

        $suaraSebelumnya = $row['jumlah_suara'];
    }

    return $juara;
}

$juaraKakang = ambilJuara($hasil['Kakang']);
$juaraSenduk = ambilJuara($hasil['Senduk']);

function fotoKontestan($foto) {
    $nama = basename($foto ?? '');

    if ($nama === '') {
        return null;
    }

    $path = __DIR__ . '/../uploads/' . $nama;

    if (!is_file($path)) {
        return null;
    }

    return '../uploads/' . rawurlencode($nama);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Peringkat Juara | Kakang Senduk Ponorogo</title>

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

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 18px;
    background: #111;
    border-right: 1px solid #493b20;
}

.logo {
    text-align: center;
    padding: 15px 5px 30px;
    border-bottom: 1px solid #45371c;
}

.logo div {
    color: #d4af37;
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

.main {
    margin-left: 250px;
    padding: 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.topbar h2 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 25px;
}

.topbar p {
    color: #888;
    font-size: 12px;
    margin-top: 7px;
}

.admin {
    background: #17140d;
    border: 1px solid #59451e;
    color: #e4c36b;
    padding: 12px 17px;
    border-radius: 9px;
    font-size: 12px;
}

.filter {
    padding: 20px;
    background: #141414;
    border: 1px solid #493b20;
    border-radius: 13px;
    margin-bottom: 25px;
}

.filter label {
    display: block;
    color: #d4af37;
    font-size: 12px;
    margin-bottom: 10px;
}

.filter form {
    display: flex;
    gap: 10px;
}

select {
    flex: 1;
    min-width: 0;
    padding: 12px;
    background: #1d1d1d;
    border: 1px solid #493b20;
    border-radius: 8px;
    color: white;
    font-family: inherit;
}

button {
    padding: 12px 18px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
    font-family: inherit;
    font-weight: 600;
    cursor: pointer;
}

.period-info {
    background: linear-gradient(120deg,#211b0e,#121212);
    border: 1px solid #59451e;
    border-radius: 13px;
    padding: 20px;
    margin-bottom: 25px;
}

.period-info h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
}

.period-info p {
    color: #aaa;
    font-size: 12px;
    margin-top: 8px;
}

.category-title {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 20px;
    margin: 30px 0 15px;
}

.juara-grid {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 17px;
}

.juara-card {
    background: linear-gradient(145deg,#1b180f,#111);
    border: 1px solid #51401f;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: .3s;
}

.juara-card:hover {
    transform: translateY(-5px);
    border-color: #d4af37;
    box-shadow: 0 10px 25px rgba(212,175,55,.1);
}

.medal {
    font-size: 34px;
    margin-bottom: 12px;
}

.posisi {
    color: #d4af37;
    font-family: 'Cinzel', serif;
    font-size: 13px;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.foto {
    width: 105px;
    height: 125px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid #a78334;
    margin-bottom: 13px;
}

.foto-kosong {
    width: 105px;
    height: 125px;
    border-radius: 12px;
    background: #29251c;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #d4af37;
    font-size: 35px;
    margin-bottom: 13px;
}

.juara-card h3 {
    color: #f0d58a;
    font-size: 15px;
    margin-bottom: 6px;
}

.nomor {
    color: #999;
    font-size: 11px;
}

.jumlah-suara {
    color: #e4c36b;
    font-size: 23px;
    font-weight: 700;
    margin-top: 15px;
}

.label-suara {
    color: #999;
    font-size: 10px;
}

.total-box {
    display: flex;
    gap: 10px;
    margin-top: 18px;
    padding: 13px;
    background: #211c11;
    border-radius: 9px;
    color: #e4c36b;
    font-size: 12px;
}

.empty {
    background: #141414;
    border: 1px dashed #59451e;
    border-radius: 13px;
    padding: 35px;
    text-align: center;
    color: #aaa;
}

.empty strong {
    color: #e4c36b;
}

@media(max-width:1050px) {
    .juara-grid {
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
        padding: 18px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .juara-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .sidebar,
    .filter,
    .admin {
        display: none;
    }

    .main {
        margin: 0;
    }

    body {
        background: white;
        color: black;
    }

    .juara-card {
        background: white;
        border: 1px solid #aaa;
    }
}
</style>
</head>

<body>

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
        <a href="hasil-voting.php">▤ Hasil Voting</a>
        <a href="peringkat.php" class="active">🏆 Peringkat Juara</a>
        <a href="periode.php">◷ Periode Voting</a>
        <a href="pembayaran.php">💳 Manajemen Pembayaran</a>
    </nav>

</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h2>Peringkat Juara</h2>
            <p>Top 3 Kakang dan Top 3 Senduk berdasarkan suara terbanyak</p>
        </div>

        <div class="admin">
            ♙ <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator') ?>
        </div>
    </div>

    <?php if ($periodeTerpilih): ?>

        <section class="filter">

            <label>Pilih Periode Voting</label>

            <form method="GET">

                <select name="periode_id">

                    <?php foreach ($periodeList as $p): ?>

                        <option
                            value="<?= (int)$p['id'] ?>"
                            <?= (int)$p['id'] === $periodeId ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($p['nama_periode']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button type="submit">Tampilkan</button>
                <button type="button" onclick="window.print()">Cetak</button>

            </form>

        </section>

        <section class="period-info">
            <h3><?= htmlspecialchars($periodeTerpilih['nama_periode']) ?></h3>
            <p>
                <?= date('d/m/Y', strtotime($periodeTerpilih['tanggal_mulai'])) ?>
                -
                <?= date('d/m/Y', strtotime($periodeTerpilih['tanggal_selesai'])) ?>
            </p>
        </section>

        <!-- JUARA KAKANG -->
        <h2 class="category-title">♛ Peringkat Kakang</h2>

        <section class="juara-grid">

            <?php if (count($juaraKakang) > 0): ?>

                <?php foreach ($juaraKakang as $row): ?>

                    <article class="juara-card">

                        <div class="medal">
                            <?= $row['peringkat'] === 1 ? '🥇' : ($row['peringkat'] === 2 ? '🥈' : '🥉') ?>
                        </div>

                        <div class="posisi">
                            PERINGKAT <?= $row['peringkat'] ?>
                        </div>

                        <?php $foto = fotoKontestan($row['foto']); ?>

                        <?php if ($foto): ?>
                            <img class="foto"
                                 src="<?= htmlspecialchars($foto) ?>"
                                 alt="Foto Kakang">
                        <?php else: ?>
                            <div class="foto-kosong">♙</div>
                        <?php endif; ?>

                        <h3><?= htmlspecialchars($row['nama']) ?></h3>

                        <div class="nomor">
                            <?= htmlspecialchars($row['nomor_peserta']) ?>
                        </div>

                        <div class="jumlah-suara">
                            <?= number_format($row['jumlah_suara']) ?>
                        </div>

                        <div class="label-suara">TOTAL SUARA</div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    Belum ada kontestan Kakang.
                </div>

            <?php endif; ?>

        </section>

        <div class="total-box">
            Total suara Kakang: <strong><?= number_format($totalSuara['Kakang']) ?></strong>
        </div>

        <!-- JUARA SENDUK -->
        <h2 class="category-title">♕ Peringkat Senduk</h2>

        <section class="juara-grid">

            <?php if (count($juaraSenduk) > 0): ?>

                <?php foreach ($juaraSenduk as $row): ?>

                    <article class="juara-card">

                        <div class="medal">
                            <?= $row['peringkat'] === 1 ? '🥇' : ($row['peringkat'] === 2 ? '🥈' : '🥉') ?>
                        </div>

                        <div class="posisi">
                            PERINGKAT <?= $row['peringkat'] ?>
                        </div>

                        <?php $foto = fotoKontestan($row['foto']); ?>

                        <?php if ($foto): ?>
                            <img class="foto"
                                 src="<?= htmlspecialchars($foto) ?>"
                                 alt="Foto Senduk">
                        <?php else: ?>
                            <div class="foto-kosong">♙</div>
                        <?php endif; ?>

                        <h3><?= htmlspecialchars($row['nama']) ?></h3>

                        <div class="nomor">
                            <?= htmlspecialchars($row['nomor_peserta']) ?>
                        </div>

                        <div class="jumlah-suara">
                            <?= number_format($row['jumlah_suara']) ?>
                        </div>

                        <div class="label-suara">TOTAL SUARA</div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    Belum ada kontestan Senduk.
                </div>

            <?php endif; ?>

        </section>

        <div class="total-box">
            Total suara Senduk: <strong><?= number_format($totalSuara['Senduk']) ?></strong>
        </div>

    <?php else: ?>

        <div class="empty">
            <strong>Belum Ada Periode Voting</strong>
            <p>Silakan buat periode voting terlebih dahulu.</p>
            <br>
            <a href="periode.php" style="color:#e4c36b;">Buat Periode Voting</a>
        </div>

    <?php endif; ?>

</main>

</body>
</html>
