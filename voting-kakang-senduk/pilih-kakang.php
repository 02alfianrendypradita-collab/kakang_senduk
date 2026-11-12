
<?php
session_start();
require_once 'config/koneksi.php';

// Cek login pemilih
if (!isset($_SESSION['pemilih_id'])) {
    header("Location: login-pemilih.php");
    exit;
}

$id_pemilih = (int) $_SESSION['pemilih_id'];

// Ambil periode voting aktif
$query_periode = mysqli_query($conn, "
    SELECT *
    FROM periode_voting
    WHERE status = 'Aktif'
    AND CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai
    ORDER BY id DESC
    LIMIT 1
");

$periode = mysqli_fetch_assoc($query_periode);

// Jika periode belum aktif
if (!$periode) {
    $pesan_error = "Voting Kakang saat ini belum dibuka.";
} else {
    $id_periode = (int) $periode['id'];

    // Cek apakah pemilih sudah voting atau mengirim pembayaran
    $stmtCek = mysqli_prepare($conn, "
        SELECT id FROM voting
        WHERE user_id = ?
        AND kategori = 'Kakang'
        AND periode_id = ?

        UNION

        SELECT id FROM pembayaran
        WHERE user_id = ?
        AND kategori = 'Kakang'
        AND periode_id = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param(
        $stmtCek,
        "iiii",
        $id_pemilih,
        $id_periode,
        $id_pemilih,
        $id_periode
    );

    mysqli_stmt_execute($stmtCek);
    $sudah_memilih = mysqli_stmt_get_result($stmtCek)->num_rows > 0;
    mysqli_stmt_close($stmtCek);

    if ($sudah_memilih) {
        $pesan_error = "Anda sudah memiliki voting atau pengajuan pembayaran untuk kategori Kakang pada periode ini.";
    }
}

// Ambil seluruh peserta Kakang
$query_kakang = mysqli_query($conn, "
    SELECT *
    FROM kontestan
    WHERE kategori = 'Kakang'
    ORDER BY nomor_peserta ASC
");

$total_kakang = mysqli_num_rows($query_kakang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Voting Kakang - Kakang Senduk Ponorogo</title>

    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #090909;
            color: #eee;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #111;
            border-right: 1px solid rgba(212,175,55,.25);
            padding: 30px 20px;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo h2 {
            font-family: 'Cinzel', serif;
            color: #d4af37;
            font-size: 19px;
        }

        .logo p {
            font-size: 10px;
            color: #999;
            letter-spacing: 2px;
            margin-top: 7px;
        }

        .menu a {
            display: block;
            text-decoration: none;
            color: #bbb;
            padding: 14px 15px;
            margin-bottom: 10px;
            border-radius: 9px;
            font-size: 13px;
            transition: .3s;
        }

        .menu a:hover,
        .menu a.active {
            background: rgba(212,175,55,.12);
            color: #e4c36b;
            border: 1px solid rgba(212,175,55,.25);
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
            margin-bottom: 35px;
        }

        .topbar h2 {
            font-family: 'Cinzel', serif;
            color: #e4c36b;
            font-size: 22px;
        }

        .user {
            color: #bbb;
            font-size: 12px;
        }

        .heading {
            margin-bottom: 25px;
        }

        .heading h1 {
            font-family: 'Cinzel', serif;
            color: #d4af37;
            font-size: 30px;
            margin-bottom: 10px;
        }

        .heading p {
            color: #999;
            font-size: 13px;
            line-height: 1.7;
        }

        .info {
            background: rgba(212,175,55,.08);
            border: 1px solid rgba(212,175,55,.25);
            border-radius: 10px;
            padding: 17px;
            margin-bottom: 25px;
            color: #d9c27b;
            font-size: 12px;
            line-height: 1.8;
        }

        .error {
            background: rgba(180,50,50,.12);
            border: 1px solid rgba(220,80,80,.4);
            color: #ffaaaa;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 25px;
            font-size: 13px;
            line-height: 1.8;
        }

        .statistik {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            flex: 1;
            background: #151515;
            border: 1px solid rgba(212,175,55,.25);
            border-radius: 12px;
            padding: 20px;
        }

        .stat-card span {
            color: #999;
            font-size: 12px;
        }

        .stat-card h3 {
            color: #e4c36b;
            font-family: 'Cinzel', serif;
            font-size: 27px;
            margin-top: 8px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 22px;
        }

        .card {
            background: linear-gradient(145deg, #171717, #101010);
            border: 1px solid rgba(212,175,55,.2);
            border-radius: 14px;
            overflow: hidden;
            transition: .3s;
            position: relative;
        }

        .card:hover {
            transform: translateY(-5px);
            border-color: #d4af37;
            box-shadow: 0 12px 30px rgba(212,175,55,.12);
        }

        .card.selected {
            border: 2px solid #d4af37;
            box-shadow: 0 0 20px rgba(212,175,55,.15);
        }

        .foto {
            width: 100%;
            height: 290px;
            object-fit: cover;
            display: block;
            background: #222;
        }

        .card-body {
            padding: 20px;
        }

        .nomor {
            display: inline-block;
            padding: 6px 12px;
            background: rgba(212,175,55,.12);
            color: #e4c36b;
            border-radius: 20px;
            font-size: 10px;
            margin-bottom: 12px;
        }

        .card h3 {
            color: #e4c36b;
            font-family: 'Cinzel', serif;
            font-size: 17px;
            margin-bottom: 8px;
        }

        .instansi {
            color: #999;
            font-size: 11px;
            margin-bottom: 15px;
        }

        .detail {
            border-top: 1px solid #292929;
            padding-top: 12px;
            margin-top: 12px;
        }

        .detail h4 {
            color: #d4af37;
            font-size: 10px;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        .detail p {
            color: #aaa;
            font-size: 11px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .pilihan {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            padding: 13px;
            background: rgba(212,175,55,.08);
            border: 1px solid rgba(212,175,55,.25);
            border-radius: 8px;
            cursor: pointer;
            color: #e4c36b;
            font-size: 12px;
        }

        .pilihan input {
            accent-color: #d4af37;
            width: 17px;
            height: 17px;
        }

        .btn-voting {
            display: block;
            width: 100%;
            max-width: 400px;
            margin: 35px auto;
            padding: 15px;
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #b58a24, #f0d77a);
            color: #17120a;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: .3s;
        }

        .btn-voting:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212,175,55,.2);
        }

        .btn-voting:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }

        .kosong {
            grid-column: 1 / -1;
            padding: 35px 20px;
            text-align: center;
            color: #999;
            background: #151515;
            border: 1px solid #292929;
            border-radius: 12px;
            font-size: 13px;
        }

        @media(max-width: 800px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 18px;
            }

            .logo {
                margin-bottom: 15px;
            }

            .menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .menu a {
                margin: 0;
                padding: 9px 11px;
                font-size: 11px;
            }

            .main {
                margin-left: 0;
                padding: 22px 15px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .heading h1 {
                font-size: 24px;
            }

            .statistik {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <h2>KAKANG SENDUK</h2>
        <p>KABUPATEN PONOROGO</p>
    </div>

    <nav class="menu">
        <a href="dashboard-pemilih.php">🏠 Dashboard</a>
        <a href="semua-peserta.php">👥 Semua Peserta</a>
        <a href="pilih-kakang.php" class="active">👑 Voting Kakang</a>
        <a href="pilih-senduk.php">🌸 Voting Senduk</a>
        <a href="logout-pemilih.php">↪ Keluar</a>
    </nav>

</aside>

<main class="main">

    <div class="topbar">
        <h2>Voting Kakang</h2>

        <div class="user">
            Selamat datang,
            <?= htmlspecialchars($_SESSION['pemilih_nama'] ?? 'Pemilih') ?>
        </div>
    </div>

    <section class="heading">
        <h1>Pemilihan Kakang Ponorogo</h1>

        <p>
            Pilih satu peserta Kakang terbaik menurut Anda.
            Kenali profil dan visi misi setiap peserta sebelum menentukan pilihan.
        </p>
    </section>

    <?php if (isset($pesan_error)): ?>

        <div class="error">
            <?= htmlspecialchars($pesan_error) ?>
            <br>
            Silakan kembali ke dashboard atau hubungi administrator.
        </div>

    <?php else: ?>

        <div class="info">
            <strong>Periode Voting:</strong>
            <?= htmlspecialchars($periode['nama_periode']) ?>
            <br>
            Anda hanya dapat memilih satu peserta Kakang dalam periode ini.
            Setelah memilih, Anda akan diarahkan ke halaman pembayaran.
        </div>

        <div class="statistik">
            <div class="stat-card">
                <span>Total Peserta Kakang</span>
                <h3><?= $total_kakang ?></h3>
            </div>

            <div class="stat-card">
                <span>Status Voting</span>
                <h3 style="font-size:18px;">TERBUKA</h3>
            </div>
        </div>

        <?php if ($total_kakang > 0): ?>

            <form method="GET" action="checkout.php" id="formVoting">

                <input type="hidden" name="kategori" value="Kakang">

                <div class="grid">

                    <?php while ($peserta = mysqli_fetch_assoc($query_kakang)): ?>

                        <?php
                        $foto = !empty($peserta['foto'])
                            ? 'uploads/' . $peserta['foto']
                            : 'https://placehold.co/500x600/171717/d4af37?text=Foto+Kakang';
                        ?>

                        <div class="card">

                            <img
                                src="<?= htmlspecialchars($foto) ?>"
                                class="foto"
                                alt="Foto <?= htmlspecialchars($peserta['nama']) ?>"
                            >

                            <div class="card-body">

                                <span class="nomor">
                                    NOMOR PESERTA
                                    <?= htmlspecialchars($peserta['nomor_peserta']) ?>
                                </span>

                                <h3><?= htmlspecialchars($peserta['nama']) ?></h3>

                                <p class="instansi">
                                    <?= htmlspecialchars($peserta['asal_instansi'] ?? 'Asal instansi belum diisi') ?>
                                </p>

                                <div class="detail">
                                    <h4>TEMPAT, TANGGAL LAHIR</h4>
                                    <p>
                                        <?= htmlspecialchars($peserta['tempat_lahir'] ?? '-') ?>,
                                        <?= !empty($peserta['tanggal_lahir'])
                                            ? date('d-m-Y', strtotime($peserta['tanggal_lahir']))
                                            : '-' ?>
                                    </p>
                                </div>

                                <div class="detail">
                                    <h4>VISI</h4>
                                    <p><?= nl2br(htmlspecialchars($peserta['visi'] ?? '-')) ?></p>
                                </div>

                                <div class="detail">
                                    <h4>MISI</h4>
                                    <p><?= nl2br(htmlspecialchars($peserta['misi'] ?? '-')) ?></p>
                                </div>

                                <div class="detail">
                                    <h4>PRESTASI</h4>
                                    <p><?= nl2br(htmlspecialchars($peserta['prestasi'] ?? '-')) ?></p>
                                </div>

                                <label class="pilihan">
                                    <input
                                        type="radio"
                                        name="id_kontestan"
                                        value="<?= (int) $peserta['id'] ?>"
                                        required
                                    >
                                    Pilih peserta ini
                                </label>

                            </div>
                        </div>

                    <?php endwhile; ?>

                </div>

                <button type="submit" class="btn-voting">
                    LANJUT KE PEMBAYARAN →
                </button>

            </form>

        <?php else: ?>

            <div class="kosong">
                Belum ada peserta Kakang yang ditambahkan oleh administrator.
            </div>

        <?php endif; ?>

    <?php endif; ?>

</main>

<script>
const formVoting = document.getElementById('formVoting');

if (formVoting) {
    formVoting.addEventListener('submit', function(event) {
        const pilihan = document.querySelector(
            'input[name="id_kontestan"]:checked'
        );

        if (!pilihan) {
            event.preventDefault();
            alert('Silakan pilih salah satu peserta Kakang terlebih dahulu.');
            return;
        }

        const konfirmasi = confirm(
            'Apakah Anda yakin ingin melanjutkan pembayaran untuk peserta ini?'
        );

        if (!konfirmasi) {
            event.preventDefault();
        }
    });
}

// Efek kartu ketika peserta dipilih
document.querySelectorAll('input[name="id_kontestan"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.card').forEach(function(card) {
            card.classList.remove('selected');
        });

        this.closest('.card').classList.add('selected');
    });
});
</script>

</body>
</html>
