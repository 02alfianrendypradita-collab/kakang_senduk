
<?php
session_start();
require_once 'config/koneksi.php';

// Pastikan hanya pemilih yang sudah login
if (!isset($_SESSION['pemilih_id'])) {
    header("Location: login-pemilih.php");
    exit;
}

// Ambil seluruh peserta
$query_peserta = mysqli_query($conn, "
    SELECT *
    FROM kontestan
    ORDER BY kategori ASC, nomor_peserta ASC
");

if (!$query_peserta) {
    die("Gagal mengambil data peserta: " . mysqli_error($conn));
}

$peserta = [];
$total_kakang = 0;
$total_senduk = 0;

while ($row = mysqli_fetch_assoc($query_peserta)) {
    $peserta[] = $row;

    if ($row['kategori'] === 'Kakang') {
        $total_kakang++;
    } elseif ($row['kategori'] === 'Senduk') {
        $total_senduk++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Peserta - Kakang Senduk Ponorogo</title>

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
            line-height: 1.8;
        }

        .statistik {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin: 25px 0;
        }

        .stat-card {
            background: linear-gradient(145deg, #191919, #101010);
            border: 1px solid rgba(212,175,55,.2);
            border-radius: 12px;
            padding: 22px;
        }

        .stat-card span {
            color: #999;
            font-size: 12px;
        }

        .stat-card h2 {
            color: #e4c36b;
            font-family: 'Cinzel', serif;
            font-size: 28px;
            margin-top: 8px;
        }

        .filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 25px 0;
        }

        .filter button {
            border: 1px solid rgba(212,175,55,.3);
            background: #151515;
            color: #bbb;
            padding: 11px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            transition: .3s;
        }

        .filter button:hover,
        .filter button.active {
            background: linear-gradient(135deg, #b58a24, #f0d77a);
            color: #17120a;
            border-color: #d4af37;
            font-weight: 700;
        }

        .search {
            width: 100%;
            max-width: 400px;
            padding: 13px 16px;
            background: #151515;
            border: 1px solid rgba(212,175,55,.25);
            border-radius: 8px;
            color: white;
            outline: none;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            margin-bottom: 25px;
        }

        .search:focus {
            border-color: #d4af37;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 22px;
        }

        .card {
            background: linear-gradient(145deg, #191919, #101010);
            border: 1px solid rgba(212,175,55,.2);
            border-radius: 15px;
            overflow: hidden;
            transition: .3s;
        }

        .card:hover {
            transform: translateY(-5px);
            border-color: #d4af37;
            box-shadow: 0 12px 30px rgba(212,175,55,.12);
        }

        .foto {
            width: 100%;
            height: 270px;
            object-fit: cover;
            display: block;
            background: #222;
        }

        .card-body {
            padding: 20px;
        }

        .kategori {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(212,175,55,.12);
            color: #e4c36b;
            font-size: 10px;
            margin-bottom: 12px;
        }

        .nomor {
            color: #999;
            font-size: 11px;
            margin-bottom: 8px;
        }

        .card h3 {
            font-family: 'Cinzel', serif;
            color: #e4c36b;
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
            font-size: 11px;
            margin-bottom: 6px;
        }

        .detail p {
            color: #aaa;
            font-size: 11px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .kosong {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            color: #999;
            background: #151515;
            border: 1px solid #292929;
            border-radius: 12px;
        }

        .btn-kembali {
            display: inline-block;
            text-decoration: none;
            color: #17120a;
            background: linear-gradient(135deg, #b58a24, #f0d77a);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 30px;
        }

        @media (max-width: 800px) {
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

            .statistik {
                grid-template-columns: 1fr;
            }

            .heading h1 {
                font-size: 24px;
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
        <a href="semua-peserta.php" class="active">👥 Lihat Semua Peserta</a>
        <a href="pilih-kakang.php">👑 Voting Kakang</a>
        <a href="pilih-senduk.php">🌸 Voting Senduk</a>
        <a href="logout-pemilih.php">↪ Keluar</a>
    </nav>
</aside>

<main class="main">

    <div class="topbar">
        <h2>Data Peserta</h2>
        <div class="user">
            Selamat datang, <?= htmlspecialchars($_SESSION['pemilih_nama'] ?? 'Pemilih') ?>
        </div>
    </div>

    <section class="heading">
        <h1>Seluruh Peserta Kakang Senduk</h1>
        <p>
            Kenali seluruh calon Kakang dan Senduk Ponorogo.
            Pelajari profil, visi, misi, serta prestasi setiap peserta
            sebelum menentukan pilihan Anda.
        </p>
    </section>

    <section class="statistik">
        <div class="stat-card">
            <span>Seluruh Peserta</span>
            <h2><?= count($peserta) ?></h2>
        </div>

        <div class="stat-card">
            <span>Peserta Kakang</span>
            <h2><?= $total_kakang ?></h2>
        </div>

        <div class="stat-card">
            <span>Peserta Senduk</span>
            <h2><?= $total_senduk ?></h2>
        </div>
    </section>

    <div class="filter">
        <button type="button" class="active" data-filter="semua">
            Semua Peserta
        </button>

        <button type="button" data-filter="Kakang">
            👑 Kakang
        </button>

        <button type="button" data-filter="Senduk">
            🌸 Senduk
        </button>
    </div>

    <input
        type="text"
        id="searchPeserta"
        class="search"
        placeholder="Cari nama atau nomor peserta..."
    >

    <section class="grid" id="daftarPeserta">

        <?php if (count($peserta) > 0): ?>

            <?php foreach ($peserta as $row): ?>

                <?php
                $foto = !empty($row['foto'])
                    ? 'uploads/' . $row['foto']
                    : 'https://placehold.co/500x600/171717/d4af37?text=Foto+Peserta';

                $teks_pencarian = strtolower(
                    $row['nama'] . ' ' .
                    $row['nomor_peserta'] . ' ' .
                    $row['asal_instansi']
                );
                ?>

                <article
                    class="card"
                    data-kategori="<?= htmlspecialchars($row['kategori']) ?>"
                    data-search="<?= htmlspecialchars($teks_pencarian) ?>"
                >

                    <img
                        src="<?= htmlspecialchars($foto) ?>"
                        alt="Foto <?= htmlspecialchars($row['nama']) ?>"
                        class="foto"
                    >

                    <div class="card-body">

                        <span class="kategori">
                            <?= htmlspecialchars($row['kategori']) ?>
                        </span>

                        <p class="nomor">
                            NOMOR PESERTA:
                            <?= htmlspecialchars($row['nomor_peserta']) ?>
                        </p>

                        <h3><?= htmlspecialchars($row['nama']) ?></h3>

                        <p class="instansi">
                            <?= htmlspecialchars($row['asal_instansi'] ?: 'Asal instansi belum diisi') ?>
                        </p>

                        <div class="detail">
                            <h4>TEMPAT, TANGGAL LAHIR</h4>
                            <p>
                                <?= htmlspecialchars($row['tempat_lahir'] ?: '-') ?>,
                                <?= htmlspecialchars($row['tanggal_lahir'] ?: '-') ?>
                            </p>
                        </div>

                        <div class="detail">
                            <h4>VISI</h4>
                            <p>
                                <?= nl2br(htmlspecialchars($row['visi'] ?: '-')) ?>
                            </p>
                        </div>

                        <div class="detail">
                            <h4>MISI</h4>
                            <p>
                                <?= nl2br(htmlspecialchars($row['misi'] ?: '-')) ?>
                            </p>
                        </div>

                        <div class="detail">
                            <h4>PRESTASI</h4>
                            <p>
                                <?= nl2br(htmlspecialchars($row['prestasi'] ?: '-')) ?>
                            </p>
                        </div>

                        <?php if (!empty($row['deskripsi'])): ?>
                            <div class="detail">
                                <h4>TENTANG PESERTA</h4>
                                <p>
                                    <?= nl2br(htmlspecialchars($row['deskripsi'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>
                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="kosong">
                <h3>Belum Ada Peserta</h3>
                <p>Data peserta belum ditambahkan oleh administrator.</p>
            </div>

        <?php endif; ?>

        <div class="kosong" id="hasilKosong" style="display:none;">
            <h3>Peserta Tidak Ditemukan</h3>
            <p>Coba gunakan kata pencarian atau kategori lainnya.</p>
        </div>

    </section>

    <a href="dashboard-pemilih.php" class="btn-kembali">
        ← Kembali ke Dashboard
    </a>

</main>

<script>
    const tombolFilter = document.querySelectorAll('.filter button');
    const kartuPeserta = document.querySelectorAll('.card');
    const inputCari = document.getElementById('searchPeserta');
    const hasilKosong = document.getElementById('hasilKosong');

    let kategoriAktif = 'semua';

    function tampilkanPeserta() {
        const kataKunci = inputCari.value.toLowerCase().trim();
        let jumlahTampil = 0;

        kartuPeserta.forEach(card => {
            const kategori = card.dataset.kategori;
            const teks = card.dataset.search;

            const cocokKategori =
                kategoriAktif === 'semua' || kategori === kategoriAktif;

            const cocokPencarian = teks.includes(kataKunci);

            if (cocokKategori && cocokPencarian) {
                card.style.display = '';
                jumlahTampil++;
            } else {
                card.style.display = 'none';
            }
        });

        hasilKosong.style.display = jumlahTampil === 0 ? 'block' : 'none';
    }

    tombolFilter.forEach(tombol => {
        tombol.addEventListener('click', function () {
            tombolFilter.forEach(item => item.classList.remove('active'));

            this.classList.add('active');
            kategoriAktif = this.dataset.filter;

            tampilkanPeserta();
        });
    });

    inputCari.addEventListener('input', tampilkanPeserta);
</script>

</body>
</html>
