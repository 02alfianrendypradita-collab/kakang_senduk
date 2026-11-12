
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// CEK LOGIN ADMIN
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// TOKEN KEAMANAN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// PENCARIAN DAN FILTER
$cari = trim($_GET['cari'] ?? '');
$kategori = $_GET['kategori'] ?? '';

$sql = "SELECT * FROM kontestan WHERE 1=1";
$params = [];
$types = "";

if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR nomor_peserta LIKE ? OR asal_instansi LIKE ?)";
    $keyword = "%$cari%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "sss";
}

if (in_array($kategori, ['Kakang', 'Senduk'], true)) {
    $sql .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}

$sql .= " ORDER BY kategori ASC, nomor_peserta ASC";

$stmt = mysqli_prepare($conn, $sql);

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Kontestan | Kakang Senduk Ponorogo</title>

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
    margin-bottom: 32px;
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

/* TOOLBAR */
.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 23px;
}

.search {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

input,
select {
    background: #181818;
    border: 1px solid #493b20;
    padding: 12px 14px;
    border-radius: 9px;
    color: white;
    outline: none;
    font-family: inherit;
    font-size: 12px;
}

input:focus,
select:focus {
    border-color: #d4af37;
    box-shadow: 0 0 10px rgba(212,175,55,.1);
}

button,
.btn {
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

button:hover,
.btn:hover {
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
    min-width: 850px;
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

/* FOTO */
.foto {
    width: 53px;
    height: 63px;
    object-fit: cover;
    border-radius: 9px;
    border: 1px solid #a78334;
    display: block;
}

.badge {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 10px;
    background: #332a15;
    color: #e5c46a;
    white-space: nowrap;
}

/* TOMBOL AKSI */
.actions {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 9px;
    white-space: nowrap;
}

.actions form {
    margin: 0;
}

.actions a,
.actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 88px;
    padding: 10px 13px;
    border-radius: 9px;
    font-family: 'Poppins', sans-serif;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all .3s ease;
}

/* EDIT */
.actions .btn-edit {
    background: linear-gradient(135deg,#f3d98b,#c49a36);
    color: #171006;
    border: 1px solid #e4c36b;
    box-shadow: 0 3px 12px rgba(212,175,55,.1);
}

.actions .btn-edit:hover {
    background: linear-gradient(135deg,#ffe9a8,#d4af37);
    transform: translateY(-3px);
    box-shadow: 0 5px 18px rgba(212,175,55,.3);
}

/* HAPUS */
.actions .delete {
    background: rgba(190,55,55,.12);
    color: #ff8585;
    border: 1px solid rgba(255,100,100,.3);
}

.actions .delete:hover {
    background: #b83232;
    color: white;
    border-color: #e05252;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(190,55,55,.2);
}

/* DATA KOSONG */
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

    .toolbar {
        align-items: stretch;
    }

    .search {
        width: 100%;
    }

    .search input,
    .search select {
        flex: 1;
        min-width: 120px;
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
        <a href="kontestan.php" class="active">♛ Kontestan</a>
        <a href="pemilih.php">♙ Data Pemilih</a>
        <a href="hasil-voting.php">▤ Hasil Voting</a>
        <a href="peringkat.php">🏆 Peringkat Juara</a>
        <a href="periode.php">◷ Periode Voting</a>
        <a href="pembayaran.php">💳 Manajemen Pembayaran</a>
    </nav>

</aside>

<!-- MAIN CONTENT -->
<main class="main">

    <div class="topbar">

        <div>
            <h2>Data Kontestan</h2>
            <p>Kelola peserta Kakang dan Senduk Ponorogo</p>
        </div>

        <div class="admin">
            ♙ <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator') ?>
        </div>

    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">

        <form method="GET" class="search">

            <input
                type="text"
                name="cari"
                placeholder="Cari nama / nomor / instansi..."
                value="<?= htmlspecialchars($cari) ?>"
            >

            <select name="kategori">
                <option value="">Semua Kategori</option>

                <option value="Kakang" <?= $kategori === 'Kakang' ? 'selected' : '' ?>>
                    Kakang
                </option>

                <option value="Senduk" <?= $kategori === 'Senduk' ? 'selected' : '' ?>>
                    Senduk
                </option>
            </select>

            <button type="submit">⌕ Cari</button>

            <a href="kontestan.php" class="btn btn-secondary">
                ↻ Reset
            </a>

        </form>

        <a href="tambah-kontestan.php" class="btn">
            + Tambah Kontestan
        </a>

    </div>

    <!-- TABEL KONTESTAN -->
    <section class="table-wrap">

        <div class="table-head">
            <h3>Daftar Peserta</h3>
            <span>Total: <?= $total ?> Kontestan</span>
        </div>

        <table>

            <thead>
                <tr>
                    <th>NO</th>
                    <th>FOTO</th>
                    <th>NOMOR</th>
                    <th>NAMA</th>
                    <th>KATEGORI</th>
                    <th>INSTANSI</th>
                    <th>AKSI</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($total > 0): ?>

                <?php $no = 1; ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td>
                            <?php
                            $namaFoto = basename($row['foto'] ?? '');
                            $pathFoto = __DIR__ . '/../uploads/' . $namaFoto;
                            ?>

                            <?php if ($namaFoto !== '' && is_file($pathFoto)): ?>

                                <img
                                    class="foto"
                                    src="../uploads/<?= rawurlencode($namaFoto) ?>"
                                    alt="Foto kontestan"
                                >

                            <?php else: ?>

                                <span class="badge">Belum ada foto</span>

                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['nomor_peserta']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['nama']) ?>
                        </td>

                        <td>
                            <span class="badge">
                                <?= htmlspecialchars($row['kategori']) ?>
                            </span>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['asal_instansi'] ?? '-') ?>
                        </td>

                        <td>

                            <div class="actions">

                                <a
                                    href="edit-kontestan.php?id=<?= (int)$row['id'] ?>"
                                    class="btn-edit"
                                >
                                    ✎ Edit
                                </a>

                                <form
                                    method="POST"
                                    action="hapus-kontestan.php"
                                    onsubmit="return confirm('Yakin ingin menghapus kontestan ini?')"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$row['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="delete"
                                    >
                                        🗑 Hapus
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="empty">
                        <div style="font-size:35px;margin-bottom:10px;">♛</div>
                        <strong>Belum Ada Data Kontestan</strong>
                        <br>
                        Silakan tambahkan kontestan baru.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>

</body>
</html>
