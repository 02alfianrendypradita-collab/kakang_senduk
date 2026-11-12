
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// ==========================================
// PROTEKSI ADMIN
// ==========================================

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$namaAdmin = $_SESSION['admin_nama'] ?? 'Administrator';

// ==========================================
// CSRF TOKEN
// ==========================================

if (empty($_SESSION['csrf_admin_pembayaran'])) {
    $_SESSION['csrf_admin_pembayaran'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

// ==========================================
// PROSES KONFIRMASI / PENOLAKAN
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $idPembayaran = (int) ($_POST['id_pembayaran'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    if (!hash_equals($_SESSION['csrf_admin_pembayaran'], $token)) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif ($idPembayaran <= 0 || !in_array($aksi, ['setujui', 'tolak'], true)) {
        $error = "Data pembayaran tidak valid.";

    } else {

        try {

            $conn->begin_transaction();

            // Ambil data pembayaran dan kunci barisnya
            $stmt = mysqli_prepare(
                $conn,
                "SELECT *
                 FROM pembayaran
                 WHERE id = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $idPembayaran);
            mysqli_stmt_execute($stmt);

            $pembayaran = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt)
            );

            mysqli_stmt_close($stmt);

            if (!$pembayaran) {
                throw new Exception("Data pembayaran tidak ditemukan.");
            }

            if ($pembayaran['status'] !== 'Menunggu') {
                throw new Exception("Pembayaran ini sudah pernah diproses.");
            }

            if ($aksi === 'setujui') {

                // Pastikan suara belum diberikan kepada peserta berbeda
                $stmtCek = mysqli_prepare(
                    $conn,
                    "SELECT kontestan_id
                     FROM voting
                     WHERE user_id = ?
                     AND kategori = ?
                     AND periode_id = ?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $stmtCek,
                    "isi",
                    $pembayaran['user_id'],
                    $pembayaran['kategori'],
                    $pembayaran['periode_id']
                );

                mysqli_stmt_execute($stmtCek);

                $votingLama = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmtCek)
                );

                mysqli_stmt_close($stmtCek);

                // Jika belum ada voting, catat suara setelah pembayaran disetujui
                if (!$votingLama) {

                    $stmtVote = mysqli_prepare(
                        $conn,
                        "INSERT INTO voting
                         (user_id, kontestan_id, kategori, periode_id)
                         VALUES (?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmtVote,
                        "iisi",
                        $pembayaran['user_id'],
                        $pembayaran['kontestan_id'],
                        $pembayaran['kategori'],
                        $pembayaran['periode_id']
                    );

                    if (!mysqli_stmt_execute($stmtVote)) {
                        mysqli_stmt_close($stmtVote);
                        throw new Exception("Gagal mencatat suara pemilih.");
                    }

                    mysqli_stmt_close($stmtVote);

                } elseif (
                    (int) $votingLama['kontestan_id'] !==
                    (int) $pembayaran['kontestan_id']
                ) {
                    throw new Exception(
                        "Pemilih sudah memberikan suara kepada peserta lain pada kategori dan periode ini. Pembayaran tidak dapat disetujui."
                    );
                }

                // Ubah status menjadi Disetujui
                $statusBaru = 'Disetujui';

            } else {

                // Tolak pembayaran
                $statusBaru = 'Ditolak';
            }

            $stmtUpdate = mysqli_prepare(
                $conn,
                "UPDATE pembayaran
                 SET status = ?
                 WHERE id = ? AND status = 'Menunggu'"
            );

            mysqli_stmt_bind_param(
                $stmtUpdate,
                "si",
                $statusBaru,
                $idPembayaran
            );

            if (!mysqli_stmt_execute($stmtUpdate) ||
                mysqli_stmt_affected_rows($stmtUpdate) !== 1) {

                mysqli_stmt_close($stmtUpdate);
                throw new Exception("Gagal memperbarui status pembayaran.");
            }

            mysqli_stmt_close($stmtUpdate);

            $conn->commit();

            $pesan = $aksi === 'setujui'
                ? "Pembayaran berhasil disetujui dan suara telah diproses."
                : "Pembayaran berhasil ditolak.";

        } catch (Throwable $e) {

            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// ==========================================
// RINGKASAN PEMASUKAN
// ==========================================

$queryRingkasan = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(CASE
            WHEN status = 'Disetujui' THEN jumlah
            ELSE 0
        END), 0) AS total_pendapatan,
        COALESCE(SUM(CASE
            WHEN status = 'Menunggu' THEN jumlah
            ELSE 0
        END), 0) AS total_menunggu,
        SUM(CASE
            WHEN status = 'Disetujui' THEN 1
            ELSE 0
        END) AS jumlah_disetujui,
        SUM(CASE
            WHEN status = 'Menunggu' THEN 1
            ELSE 0
        END) AS jumlah_menunggu,
        SUM(CASE
            WHEN status = 'Ditolak' THEN 1
            ELSE 0
        END) AS jumlah_ditolak
     FROM pembayaran"
);

$ringkasan = mysqli_fetch_assoc($queryRingkasan);

// ==========================================
// AMBIL SEMUA DATA PEMBAYARAN
// ==========================================

$queryPembayaran = mysqli_query(
    $conn,
    "SELECT
        p.*,
        u.nama AS nama_pemilih,
        u.email AS email_pemilih,
        k.nama AS nama_kontestan,
        k.nomor_peserta
     FROM pembayaran p
     LEFT JOIN users u ON p.user_id = u.id
     LEFT JOIN kontestan k ON p.kontestan_id = k.id
     ORDER BY
        CASE
            WHEN p.status = 'Menunggu' THEN 1
            ELSE 2
        END,
        p.id DESC"
);

if (!$queryPembayaran) {
    die("Gagal mengambil data pembayaran: " . mysqli_error($conn));
}

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Pembayaran | Admin Kakang Senduk</title>

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
    width: 245px;
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
    font-size: 15px;
    letter-spacing: 1px;
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
    font-size: 12px;
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
}

.main {
    margin-left: 245px;
    padding: 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar h1 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 25px;
}

.topbar p {
    color: #888;
    font-size: 12px;
    margin-top: 7px;
}

.admin-info {
    color: #e4c36b;
    background: #17140d;
    border: 1px solid #59451e;
    padding: 12px 17px;
    border-radius: 9px;
    font-size: 12px;
}

/* NOTIFIKASI */

.alert {
    padding: 14px 17px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 12px;
}

.success {
    color: #75df9b;
    background: rgba(40,160,90,.12);
    border: 1px solid #286b42;
}

.danger {
    color: #ff9999;
    background: rgba(180,50,40,.12);
    border: 1px solid #813c3c;
}

/* STATISTIK */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 17px;
    margin-bottom: 30px;
}

.stat-card {
    background: #141414;
    border: 1px solid #493b20;
    border-radius: 13px;
    padding: 22px;
}

.stat-card .icon {
    font-size: 25px;
    color: #d4af37;
}

.stat-card h2 {
    color: #e4c36b;
    font-size: 23px;
    margin: 12px 0 5px;
    overflow-wrap: anywhere;
}

.stat-card p {
    color: #999;
    font-size: 11px;
}

.section-title {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 19px;
    margin-bottom: 17px;
}

/* TABEL */

.table-card {
    background: #141414;
    border: 1px solid #493b20;
    border-radius: 13px;
    padding: 20px;
    overflow: hidden;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th {
    background: #211b0e;
    color: #e4c36b;
    font-size: 11px;
    text-align: left;
    padding: 15px 12px;
    white-space: nowrap;
}

td {
    color: #ccc;
    font-size: 11px;
    padding: 14px 12px;
    border-bottom: 1px solid #292929;
    vertical-align: middle;
}

tr:hover td {
    background: rgba(212,175,55,.035);
}

.bukti {
    width: 65px;
    height: 75px;
    object-fit: cover;
    border-radius: 7px;
    border: 1px solid #59451e;
    cursor: pointer;
}

.no-bukti {
    color: #888;
    font-size: 10px;
}

/* STATUS */

.badge {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 20px;
    font-size: 10px;
    white-space: nowrap;
}

.menunggu {
    color: #f5cb65;
    background: rgba(245,203,101,.10);
}

.disetujui {
    color: #65df91;
    background: rgba(40,160,90,.12);
}

.ditolak {
    color: #ff8585;
    background: rgba(180,50,40,.12);
}

/* TOMBOL */

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.btn {
    border: none;
    padding: 9px 11px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.btn-approve {
    color: #081c10;
    background: #65df91;
}

.btn-reject {
    color: #fff;
    background: #a83c3c;
}

.btn:hover {
    opacity: .8;
}

/* PETUNJUK */

.note {
    margin-top: 20px;
    padding: 16px;
    background: rgba(212,175,55,.07);
    border: 1px solid #59451e;
    border-radius: 9px;
    color: #c7b17b;
    font-size: 11px;
    line-height: 1.8;
}

/* RESPONSIVE */

@media(max-width:1100px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
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
        <p>ADMINISTRATOR</p>
    </div>

    <div class="menu-title">MENU ADMIN</div>

    <nav class="menu">

        <a href="dashboard.php">🏠 Dashboard</a>

        <a href="kontestan.php">👥 Data Kontestan</a>

        <a href="pemilih.php">👤 Data Pemilih</a>

        <a href="hasil-voting.php">📊 Hasil Voting</a>

        <a href="peringkat.php">🏆 Peringkat Juara</a>

        <a href="periode.php">📅 Periode Voting</a>

        <a href="pembayaran.php" class="active">💳 Manajemen Pembayaran</a>

    </nav>

    <a href="logout.php" class="logout">↪ Keluar</a>

</aside>

<!-- KONTEN UTAMA -->

<main class="main">

    <div class="topbar">

        <div>
            <h1>Manajemen Pembayaran</h1>
            <p>Kelola konfirmasi pembayaran pemilihan Kakang Senduk Ponorogo.</p>
        </div>

        <div class="admin-info">
            ♙ <?= htmlspecialchars($namaAdmin) ?>
        </div>

    </div>

    <?php if ($pesan): ?>
        <div class="alert success">
            ✓ <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert danger">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN KEUANGAN -->

    <h2 class="section-title">Ringkasan Keuangan</h2>

    <section class="stats">

        <div class="stat-card">
            <div class="icon">💰</div>
            <h2>
                Rp <?= number_format((float)$ringkasan['total_pendapatan'], 0, ',', '.') ?>
            </h2>
            <p>Total Uang Diterima</p>
        </div>

        <div class="stat-card">
            <div class="icon">⏳</div>
            <h2>
                Rp <?= number_format((float)$ringkasan['total_menunggu'], 0, ',', '.') ?>
            </h2>
            <p>Uang Menunggu Konfirmasi</p>
        </div>

        <div class="stat-card">
            <div class="icon">✓</div>
            <h2><?= (int)$ringkasan['jumlah_disetujui'] ?></h2>
            <p>Pembayaran Disetujui</p>
        </div>

        <div class="stat-card">
            <div class="icon">📋</div>
            <h2><?= (int)$ringkasan['jumlah_menunggu'] ?></h2>
            <p>Menunggu Verifikasi</p>
        </div>

    </section>

    <!-- DATA PEMBAYARAN -->

    <h2 class="section-title">Daftar Pengajuan Pembayaran</h2>

    <section class="table-card">

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Pemilih</th>
                        <th>Kontestan</th>
                        <th>Kategori</th>
                        <th>Metode</th>
                        <th>Nominal</th>
                        <th>Bukti Pembayaran</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (mysqli_num_rows($queryPembayaran) > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($queryPembayaran)): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                <strong><?= htmlspecialchars($row['nama_pemilih'] ?? 'Tidak ditemukan') ?></strong>
                                <br>
                                <span style="color:#888">
                                    <?= htmlspecialchars($row['email_pemilih'] ?? '-') ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['nama_kontestan'] ?? 'Tidak ditemukan') ?>
                                <br>
                                <span style="color:#888">
                                    Nomor: <?= htmlspecialchars($row['nomor_peserta'] ?? '-') ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars($row['kategori']) ?></td>

                            <td><?= htmlspecialchars($row['metode_pembayaran']) ?></td>

                            <td style="color:#e4c36b;font-weight:600">
                                Rp <?= number_format((float)$row['jumlah'], 0, ',', '.') ?>
                            </td>

                            <td>

                                <?php if (!empty($row['bukti_pembayaran'])): ?>

                                    <a
                                        href="../uploads/pembayaran/<?= rawurlencode(basename($row['bukti_pembayaran'])) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <img
                                            class="bukti"
                                            src="../uploads/pembayaran/<?= rawurlencode(basename($row['bukti_pembayaran'])) ?>"
                                            alt="Bukti pembayaran"
                                        >
                                    </a>

                                <?php else: ?>

                                    <span class="no-bukti">Tidak ada bukti</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($row['status'] === 'Menunggu'): ?>

                                    <span class="badge menunggu">Menunggu</span>

                                <?php elseif ($row['status'] === 'Disetujui'): ?>

                                    <span class="badge disetujui">Disetujui</span>

                                <?php else: ?>

                                    <span class="badge ditolak">Ditolak</span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= !empty($row['tanggal_pembayaran'])
                                    ? date('d-m-Y H:i', strtotime($row['tanggal_pembayaran']))
                                    : '-' ?>
                            </td>

                            <td>

                                <?php if ($row['status'] === 'Menunggu'): ?>

                                    <div class="actions">

                                        <form method="POST" onsubmit="return confirm('Yakin ingin menyetujui pembayaran ini?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION['csrf_admin_pembayaran']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_pembayaran"
                                                value="<?= (int)$row['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="setujui"
                                            >

                                            <button type="submit" class="btn btn-approve">
                                                ✓ Setujui
                                            </button>

                                        </form>

                                        <form method="POST" onsubmit="return confirm('Yakin ingin menolak pembayaran ini?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION['csrf_admin_pembayaran']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_pembayaran"
                                                value="<?= (int)$row['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="tolak"
                                            >

                                            <button type="submit" class="btn btn-reject">
                                                ✕ Tolak
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span style="color:#888">Sudah diproses</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" style="text-align:center;padding:35px;color:#888">
                            Belum ada pengajuan pembayaran.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <div class="note">
        <strong>Informasi:</strong><br>
        Total uang diterima hanya menghitung pembayaran dengan status
        <strong>Disetujui</strong>. Pembayaran berstatus Menunggu belum
        dianggap sebagai pemasukan. Saat admin menyetujui pembayaran,
        sistem juga akan mencatat suara pemilih jika suara tersebut belum ada.
    </div>

</main>

</body>
</html>
