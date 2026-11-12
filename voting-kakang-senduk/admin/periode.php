
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

$pesan = '';
$jenisPesan = '';

// PROSES FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Token keamanan tidak valid.");
    }

    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH ATAU EDIT PERIODE
    if ($aksi === 'simpan') {

        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_periode'] ?? '');
        $mulai = $_POST['tanggal_mulai'] ?? '';
        $selesai = $_POST['tanggal_selesai'] ?? '';
        $status = $_POST['status'] ?? 'Nonaktif';

        if (
            $nama === '' ||
            $mulai === '' ||
            $selesai === '' ||
            !in_array($status, ['Aktif', 'Nonaktif'], true)
        ) {
            $pesan = "Semua data periode wajib diisi.";
            $jenisPesan = 'error';

        } elseif ($selesai < $mulai) {
            $pesan = "Tanggal selesai tidak boleh sebelum tanggal mulai.";
            $jenisPesan = 'error';

        } else {

            mysqli_begin_transaction($conn);

            try {

                // Hanya satu periode yang boleh aktif
                if ($status === 'Aktif') {
                    $queryNonaktif = mysqli_prepare(
                        $conn,
                        "UPDATE periode_voting SET status = 'Nonaktif'"
                    );

                    if (!$queryNonaktif || !mysqli_stmt_execute($queryNonaktif)) {
                        throw new Exception("Gagal mengatur periode aktif.");
                    }

                    mysqli_stmt_close($queryNonaktif);
                }

                if ($id > 0) {

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE periode_voting
                         SET nama_periode = ?,
                             tanggal_mulai = ?,
                             tanggal_selesai = ?,
                             status = ?
                         WHERE id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssi",
                        $nama,
                        $mulai,
                        $selesai,
                        $status,
                        $id
                    );

                } else {

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO periode_voting
                         (nama_periode, tanggal_mulai, tanggal_selesai, status)
                         VALUES (?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssss",
                        $nama,
                        $mulai,
                        $selesai,
                        $status
                    );
                }

                if (!$stmt || !mysqli_stmt_execute($stmt)) {
                    throw new Exception("Gagal menyimpan periode voting.");
                }

                mysqli_stmt_close($stmt);
                mysqli_commit($conn);

                header("Location: periode.php?status=berhasil");
                exit;

            } catch (Throwable $e) {

                mysqli_rollback($conn);
                $pesan = "Gagal menyimpan periode. Silakan periksa data dan database.";
                $jenisPesan = 'error';
            }
        }
    }

    // HAPUS PERIODE
    if ($aksi === 'hapus') {

        $idHapus = (int)($_POST['id'] ?? 0);

        if ($idHapus > 0) {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM periode_voting WHERE id = ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $idHapus);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                header("Location: periode.php?status=dihapus");
                exit;

            } else {
                mysqli_stmt_close($stmt);

                $pesan = "Periode tidak dapat dihapus karena mungkin sudah memiliki data voting.";
                $jenisPesan = 'error';
            }
        }
    }
}

// NOTIFIKASI
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'berhasil') {
        $pesan = "Periode voting berhasil disimpan.";
        $jenisPesan = 'success';
    } elseif ($_GET['status'] === 'dihapus') {
        $pesan = "Periode voting berhasil dihapus.";
        $jenisPesan = 'success';
    }
}

// DATA EDIT
$edit = null;

if (isset($_GET['edit'])) {

    $idEdit = (int)$_GET['edit'];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM periode_voting WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $idEdit);
    mysqli_stmt_execute($stmt);

    $resultEdit = mysqli_stmt_get_result($stmt);
    $edit = mysqli_fetch_assoc($resultEdit);

    mysqli_stmt_close($stmt);
}

// DATA PERIODE
$result = mysqli_query(
    $conn,
    "SELECT * FROM periode_voting ORDER BY id DESC"
);

$totalPeriode = mysqli_num_rows($result);

// HITUNG PERIODE AKTIF
$queryAktif = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM periode_voting
     WHERE status = 'Aktif'"
);

$dataAktif = mysqli_fetch_assoc($queryAktif);
$totalAktif = $dataAktif['total'] ?? 0;

// HITUNG PERIODE NONAKTIF
$queryNonaktif = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM periode_voting
     WHERE status = 'Nonaktif'"
);

$dataNonaktif = mysqli_fetch_assoc($queryNonaktif);
$totalNonaktif = $dataNonaktif['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Periode Voting | Kakang Senduk</title>

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
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 25px;
}

.stat-card {
    background: linear-gradient(135deg,#19160e,#111);
    border: 1px solid #51401f;
    border-radius: 13px;
    padding: 22px;
}

.stat-card p {
    color: #999;
    font-size: 12px;
}

.stat-card h3 {
    color: #e4c36b;
    font-size: 28px;
    margin-top: 8px;
}

/* FORM */
.content-grid {
    display: grid;
    grid-template-columns: minmax(280px, .85fr) minmax(400px, 1.5fr);
    gap: 20px;
    align-items: start;
}

.card {
    background: #131313;
    border: 1px solid #332b1b;
    border-radius: 15px;
    overflow: hidden;
}

.card-head {
    padding: 21px;
    border-bottom: 1px solid #332b1b;
}

.card-head h3 {
    color: #e3c36e;
    font-family: 'Cinzel', serif;
    font-size: 16px;
}

.card-body {
    padding: 22px;
}

label {
    display: block;
    color: #e4c36b;
    font-size: 12px;
    margin: 15px 0 8px;
}

input, select {
    width: 100%;
    padding: 12px 13px;
    background: #1d1d1d;
    border: 1px solid #493b20;
    border-radius: 8px;
    color: white;
    font-family: inherit;
    font-size: 12px;
    outline: none;
}

input:focus, select:focus {
    border-color: #d4af37;
    box-shadow: 0 0 10px rgba(212,175,55,.1);
}

.btn {
    display: inline-block;
    padding: 11px 16px;
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

.btn-secondary {
    background: #29251c;
    border: 1px solid #57451f;
    color: #e3c36e;
}

.form-actions {
    display: flex;
    gap: 9px;
    margin-top: 22px;
    flex-wrap: wrap;
}

/* TABEL */
.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

th {
    background: #1d1a13;
    color: #d4af37;
    padding: 16px;
    text-align: left;
    font-size: 10px;
    letter-spacing: 1px;
}

td {
    padding: 16px;
    border-bottom: 1px solid #29251d;
    font-size: 11px;
    color: #ccc;
}

tbody tr:hover {
    background: rgba(212,175,55,.04);
}

.nama {
    color: #e9cb78;
    font-weight: 600;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.aktif {
    color: #8ee0a6;
    background: rgba(45,150,90,.13);
    border: 1px solid rgba(45,150,90,.25);
}

.nonaktif {
    color: #aaa;
    background: #292929;
    border: 1px solid #444;
}

.aksi {
    display: flex;
    gap: 7px;
    align-items: center;
}

.aksi a,
.aksi button {
    padding: 8px 11px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 10px;
    font-family: inherit;
    font-weight: 600;
    cursor: pointer;
}

.edit {
    background: #332a15;
    color: #e4c36b;
    border: 1px solid #66521f;
}

.hapus {
    background: #351b1b;
    color: #ff9999;
    border: 1px solid #633333;
}

.alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 12px;
}

.alert.success {
    background: rgba(45,150,90,.12);
    border: 1px solid #28683e;
    color: #8ee0a6;
}

.alert.error {
    background: rgba(190,55,55,.12);
    border: 1px solid #803b3b;
    color: #ff9999;
}

.empty {
    text-align: center;
    padding: 40px;
    color: #888;
}

@media(max-width:1100px) {
    .content-grid {
        grid-template-columns: 1fr;
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
        grid-template-columns: 1fr;
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
        <a href="peringkat.php">🏆 Peringkat Juara</a>
        <a href="periode.php" class="active">◷ Periode Voting</a>
        <a href="pembayaran.php">💳 Manajemen Pembayaran</a>
    </nav>

</aside>

<main class="main">

    <div class="topbar">
        <div>
            <h2>Periode Voting</h2>
            <p>Atur jadwal pemilihan Kakang Senduk Ponorogo</p>
        </div>

        <div class="admin">
            ♙ <?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator') ?>
        </div>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= $jenisPesan === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <section class="stats">

        <div class="stat-card">
            <p>Total Periode</p>
            <h3><?= number_format($totalPeriode) ?></h3>
        </div>

        <div class="stat-card">
            <p>Periode Aktif</p>
            <h3><?= number_format((int)$totalAktif) ?></h3>
        </div>

        <div class="stat-card">
            <p>Periode Nonaktif</p>
            <h3><?= number_format((int)$totalNonaktif) ?></h3>
        </div>

    </section>

    <div class="content-grid">

        <!-- FORM TAMBAH / EDIT -->
        <section class="card">

            <div class="card-head">
                <h3><?= $edit ? 'Edit Periode Voting' : 'Tambah Periode Voting' ?></h3>
            </div>

            <div class="card-body">

                <form method="POST">

                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <input type="hidden" name="aksi" value="simpan">

                    <input type="hidden" name="id"
                           value="<?= (int)($edit['id'] ?? 0) ?>">

                    <label>Nama Periode</label>
                    <input
                        type="text"
                        name="nama_periode"
                        placeholder="Contoh: Pemilihan Kakang Senduk 2026"
                        required
                        value="<?= htmlspecialchars($edit['nama_periode'] ?? '') ?>"
                    >

                    <label>Tanggal Mulai</label>
                    <input
                        type="date"
                        name="tanggal_mulai"
                        required
                        value="<?= htmlspecialchars($edit['tanggal_mulai'] ?? '') ?>"
                    >

                    <label>Tanggal Selesai</label>
                    <input
                        type="date"
                        name="tanggal_selesai"
                        required
                        value="<?= htmlspecialchars($edit['tanggal_selesai'] ?? '') ?>"
                    >

                    <label>Status Periode</label>
                    <select name="status" required>
                        <option value="Nonaktif"
                            <?= ($edit['status'] ?? 'Nonaktif') === 'Nonaktif' ? 'selected' : '' ?>>
                            Nonaktif
                        </option>

                        <option value="Aktif"
                            <?= ($edit['status'] ?? '') === 'Aktif' ? 'selected' : '' ?>>
                            Aktif
                        </option>
                    </select>

                    <div class="form-actions">

                        <button type="submit" class="btn">
                            <?= $edit ? 'Simpan Perubahan' : '+ Simpan Periode' ?>
                        </button>

                        <?php if ($edit): ?>
                            <a href="periode.php" class="btn btn-secondary">
                                Batal
                            </a>
                        <?php endif; ?>

                    </div>

                </form>

            </div>

        </section>

        <!-- DAFTAR PERIODE -->
        <section class="card">

            <div class="card-head">
                <h3>Daftar Periode Voting</h3>
            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>NAMA PERIODE</th>
                            <th>TANGGAL</th>
                            <th>STATUS</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($totalPeriode > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td class="nama">
                                    <?= htmlspecialchars($row['nama_periode']) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?>
                                    <br>
                                    <span style="color:#888;">sampai</span>
                                    <br>
                                    <?= date('d/m/Y', strtotime($row['tanggal_selesai'])) ?>
                                </td>

                                <td>
                                    <?php if ($row['status'] === 'Aktif'): ?>
                                        <span class="status aktif">● Aktif</span>
                                    <?php else: ?>
                                        <span class="status nonaktif">● Nonaktif</span>
                                    <?php endif; ?>
                                </td>

                                <td>

                                    <div class="aksi">

                                        <a
                                            href="periode.php?edit=<?= (int)$row['id'] ?>"
                                            class="edit"
                                        >
                                            ✎ Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus periode ini?')"
                                        >

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                            <input type="hidden" name="aksi" value="hapus">

                                            <input type="hidden" name="id"
                                                   value="<?= (int)$row['id'] ?>">

                                            <button type="submit" class="hapus">
                                                🗑 Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="empty">
                                Belum ada periode voting.
                                <br>
                                Silakan tambahkan periode pertama.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </div>

</main>

</body>
</html>
