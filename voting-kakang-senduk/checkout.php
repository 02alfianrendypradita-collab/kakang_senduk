
<?php
session_start();
require_once 'config/koneksi.php';

// ========================================
// 1. PENGATURAN REKENING PEMBAYARAN
// GANTI DATA DI BAWAH DENGAN DATA ASLI
// ========================================

$rekening = [
    'Bank BRI' => [
        'nomor' => '8789765436',
        'pemilik' => 'ALFIAN RENDY P'
    ],

    'Bank BCA' => [
        'nomor' => '8769887609',
        'pemilik' => 'ALFIAN RENDY P'
    ],

    'Bank Mandiri' => [
        'nomor' => '4453467867',
        'pemilik' => 'ALFIAN RENDY P'
    ],

    'DANA' => [
        'nomor' => '089524953485',
        'pemilik' => 'ALFIAN RENDY P'
    ],

    'OVO' => [
        'nomor' => '089524953485',
        'pemilik' => 'ALFIAN RENDY P'
    ],

    'GoPay' => [
        'nomor' => '089524953485',
        'pemilik' => 'ALFIAN RENDY P'
    ],

    'ShopeePay' => [
        'nomor' => '089524953485',
        'pemilik' => 'ALFIAN RENDY P'
    ]
];

// Tarif minimum voting
$harga_voting = 10000;

// ========================================
// 2. CEK LOGIN
// ========================================

if (!isset($_SESSION['pemilih_id'])) {
    header("Location: login-pemilih.php");
    exit;
}

$id_pemilih = (int) $_SESSION['pemilih_id'];

if (empty($_SESSION['csrf_pembayaran'])) {
    $_SESSION['csrf_pembayaran'] = bin2hex(random_bytes(32));
}

$error = '';

$metode_valid = [
    'QRIS',
    'Bank BRI',
    'Bank BCA',
    'Bank Mandiri',
    'DANA',
    'OVO',
    'GoPay',
    'ShopeePay'
];

// ========================================
// 3. AMBIL DATA PESERTA
// ========================================

$id_kontestan = (int) (
    $_GET['id_kontestan']
    ?? $_GET['peserta']
    ?? 0
);

$kategori = $_GET['kategori'] ?? '';

if (
    !in_array($kategori, ['Kakang', 'Senduk'], true)
    || $id_kontestan <= 0
) {
    die("Data peserta tidak valid.");
}

// ========================================
// 4. AMBIL PERIODE AKTIF
// ========================================

$query_periode = mysqli_query($conn, "
    SELECT *
    FROM periode_voting
    WHERE status = 'Aktif'
    AND CURDATE() BETWEEN tanggal_mulai AND tanggal_selesai
    ORDER BY id DESC
    LIMIT 1
");

$periode = mysqli_fetch_assoc($query_periode);

if (!$periode) {
    die("Periode voting belum dibuka.");
}

$id_periode = (int) $periode['id'];

// ========================================
// 5. AMBIL PESERTA
// ========================================

$stmt = mysqli_prepare($conn, "
    SELECT *
    FROM kontestan
    WHERE id = ?
    AND kategori = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "is", $id_kontestan, $kategori);
mysqli_stmt_execute($stmt);

$peserta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

mysqli_stmt_close($stmt);

if (!$peserta) {
    die("Peserta tidak ditemukan.");
}

// ========================================
// 6. CEK PEMBAYARAN SEBELUMNYA
// ========================================

function cekPembayaran($conn, $user, $kategori, $periode)
{
    $sql = "
        SELECT id
        FROM voting
        WHERE user_id = ?
        AND kategori = ?
        AND periode_id = ?

        UNION

        SELECT id
        FROM pembayaran
        WHERE user_id = ?
        AND kategori = ?
        AND periode_id = ?
        AND status IN ('Menunggu', 'Disetujui')

        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isisis",
        $user,
        $kategori,
        $periode,
        $user,
        $kategori,
        $periode
    );

    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $ada = mysqli_num_rows($hasil) > 0;

    mysqli_stmt_close($stmt);

    return $ada;
}

if (cekPembayaran(
    $conn,
    $id_pemilih,
    $kategori,
    $id_periode
)) {
    die("
        Anda sudah memiliki pengajuan pembayaran atau suara
        yang disetujui untuk kategori ini.
        <br><br>
        <a href='dashboard-pemilih.php'>Kembali ke Dashboard</a>
    ");
}

// ========================================
// 7. PROSES FORM PEMBAYARAN
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $metode = $_POST['metode_pembayaran'] ?? '';

    $nominal = filter_var(
        $_POST['nominal'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!hash_equals($_SESSION['csrf_pembayaran'], $token)) {

        $error = "Permintaan tidak valid.";

    } elseif (!in_array($metode, $metode_valid, true)) {

        $error = "Pilih metode pembayaran.";

    } elseif (
        $nominal === false ||
        $nominal === null ||
        $nominal < $harga_voting ||
        $nominal > 100000000
    ) {

        $error = "Nominal minimal Rp " .
            number_format($harga_voting, 0, ',', '.') .
            " dan maksimal Rp 100.000.000.";

    } elseif (
        !isset($_FILES['bukti']) ||
        $_FILES['bukti']['error'] !== UPLOAD_ERR_OK
    ) {

        $error = "Silakan unggah bukti pembayaran.";

    } elseif ($_FILES['bukti']['size'] > 2 * 1024 * 1024) {

        $error = "Ukuran bukti maksimal 2 MB.";

    } elseif (!is_uploaded_file($_FILES['bukti']['tmp_name'])) {

        $error = "File upload tidak valid.";

    } else {

        // Periksa tipe file
        $tmp = $_FILES['bukti']['tmp_name'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);

        $tipe_valid = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($tipe_valid[$mime])) {

            $error = "Format file harus JPG, PNG, atau WEBP.";

        } elseif (cekPembayaran(
            $conn,
            $id_pemilih,
            $kategori,
            $id_periode
        )) {

            $error = "Pengajuan pembayaran sudah pernah dikirim.";

        } else {

            $folder = __DIR__ . '/uploads/pembayaran/';

            if (
                !is_dir($folder) &&
                !mkdir($folder, 0755, true)
            ) {
                $error = "Folder upload gagal dibuat.";
            } else {

                $nama_file = bin2hex(random_bytes(16))
                    . '.'
                    . $tipe_valid[$mime];

                $lokasi = $folder . $nama_file;

                if (move_uploaded_file($tmp, $lokasi)) {

                    $stmt = mysqli_prepare($conn, "
                        INSERT INTO pembayaran (
                            user_id,
                            kontestan_id,
                            periode_id,
                            kategori,
                            metode_pembayaran,
                            jumlah,
                            bukti_pembayaran,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Menunggu')
                    ");

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iiissds",
                        $id_pemilih,
                        $id_kontestan,
                        $id_periode,
                        $kategori,
                        $metode,
                        $nominal,
                        $nama_file
                    );

                    if (mysqli_stmt_execute($stmt)) {

                        mysqli_stmt_close($stmt);

                        $_SESSION['csrf_pembayaran'] =
                            bin2hex(random_bytes(32));

                        header("Location: status-pembayaran.php");
                        exit;

                    } else {

                        mysqli_stmt_close($stmt);

                        if (file_exists($lokasi)) {
                            unlink($lokasi);
                        }

                        $error = "Gagal menyimpan pembayaran.";
                    }

                } else {

                    $error = "Gagal mengunggah bukti pembayaran.";
                }
            }
        }
    }
}

// ========================================
// 8. FOTO PESERTA
// ========================================

$foto = !empty($peserta['foto'])
    ? 'uploads/' . basename($peserta['foto'])
    : 'https://placehold.co/300x350/171717/d4af37?text=Peserta';

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Checkout Voting Kakang Senduk</title>

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
    padding: 35px 15px;
}

.container {
    width: 100%;
    max-width: 700px;
    margin: auto;
}

.heading {
    text-align: center;
    margin-bottom: 30px;
}

.heading h1 {
    color: #d4af37;
    font-family: 'Cinzel', serif;
    font-size: 28px;
    margin-bottom: 10px;
}

.heading p {
    color: #999;
    font-size: 12px;
}

.card {
    background: #151515;
    border: 1px solid rgba(212,175,55,.3);
    border-radius: 15px;
    padding: 24px;
    margin-bottom: 20px;
}

.profil {
    display: flex;
    gap: 20px;
    align-items: center;
}

.profil img {
    width: 110px;
    height: 130px;
    object-fit: cover;
    border-radius: 10px;
}

.profil h3 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    margin-bottom: 10px;
}

.profil p {
    color: #aaa;
    font-size: 12px;
    margin-top: 5px;
}

.judul {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    font-size: 17px;
    margin-bottom: 18px;
}

label {
    display: block;
    color: #e4c36b;
    font-size: 12px;
    margin: 17px 0 8px;
}

select,
input[type="file"],
input[type="number"] {
    width: 100%;
    padding: 13px;
    background: #090909;
    color: #eee;
    border: 1px solid #393939;
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
}

select:focus,
input:focus {
    outline: none;
    border-color: #d4af37;
}

.info-pembayaran {
    display: none;
    padding: 18px;
    margin-top: 18px;
    background: rgba(212,175,55,.07);
    border: 1px solid rgba(212,175,55,.3);
    border-radius: 10px;
}

.info-pembayaran h3 {
    color: #e4c36b;
    font-size: 14px;
    margin-bottom: 12px;
}

.info-pembayaran p {
    color: #bbb;
    font-size: 12px;
    line-height: 2;
}

.info-pembayaran strong {
    color: #fff;
}

.qris {
    display: block;
    width: 230px;
    max-width: 100%;
    margin: 15px auto;
    background: #fff;
    padding: 10px;
    border-radius: 10px;
}

.nominal-box {
    padding: 15px;
    background: rgba(212,175,55,.08);
    border: 1px solid rgba(212,175,55,.25);
    border-radius: 10px;
    margin-top: 15px;
}

.nominal-box span {
    color: #aaa;
    font-size: 12px;
}

.nominal-box h2 {
    color: #e4c36b;
    margin-top: 7px;
}

.btn {
    width: 100%;
    padding: 15px;
    margin-top: 22px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #b58a24, #f0d77a);
    color: #17120a;
    font-weight: 700;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.error {
    padding: 13px;
    margin-bottom: 15px;
    background: rgba(180,50,50,.15);
    border: 1px solid #a44;
    color: #ff9999;
    border-radius: 8px;
    font-size: 12px;
}

.catatan {
    color: #999;
    font-size: 11px;
    line-height: 1.8;
    margin-top: 12px;
}

.kembali {
    display: inline-block;
    color: #d4af37;
    text-decoration: none;
    font-size: 12px;
    margin-bottom: 20px;
}

@media(max-width:500px) {
    .profil {
        flex-direction: column;
        align-items: flex-start;
    }

    .profil img {
        width: 100%;
        height: 230px;
    }
}

</style>

</head>

<body>

<div class="container">

<a href="dashboard-pemilih.php" class="kembali">
    ← Kembali ke Dashboard
</a>

<div class="heading">
    <h1>Checkout Voting</h1>
    <p>Pemilihan Kakang Senduk Kabupaten Ponorogo</p>
</div>

<?php if ($error): ?>
<div class="error">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- DATA PESERTA -->

<div class="card">

    <div class="profil">

        <img src="<?= htmlspecialchars($foto) ?>" alt="Foto peserta">

        <div>

            <h3><?= htmlspecialchars($peserta['nama']) ?></h3>

            <p>Kategori: <?= htmlspecialchars($kategori) ?></p>

            <p>Nomor Peserta:
                <?= htmlspecialchars($peserta['nomor_peserta']) ?>
            </p>

            <p>Periode:
                <?= htmlspecialchars($periode['nama_periode']) ?>
            </p>

        </div>

    </div>

</div>

<form method="POST" enctype="multipart/form-data" class="card">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_pembayaran']) ?>"
    >

    <h2 class="judul">01. Pilih Metode Pembayaran</h2>

    <label>Metode Pembayaran</label>

    <select name="metode_pembayaran" id="metode" required>

        <option value="">-- Pilih Pembayaran --</option>

        <?php foreach ($metode_valid as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>">
                <?= htmlspecialchars($m) ?>
            </option>
        <?php endforeach; ?>

    </select>

    <!-- INFORMASI QRIS -->

    <div class="info-pembayaran" id="infoQRIS">

        <h3>Pembayaran QRIS</h3>

        <p>
            Silakan pindai QRIS resmi penyelenggara menggunakan
            aplikasi pembayaran yang mendukung QRIS.
        </p>

        <img
            src="uploads/qris.png"
            alt="QRIS resmi penyelenggara"
            class="qris"
            onerror="this.style.display='none'; document.getElementById('qrisBelumAda').style.display='block';"
        >

        <p id="qrisBelumAda" style="display:none;color:#ffaaaa;text-align:center;">
            QRIS belum diunggah. Admin harus memasukkan gambar QRIS resmi
            ke uploads/qris.png terlebih dahulu.
        </p>

    </div>

    <!-- INFORMASI BANK -->

    <div class="info-pembayaran" id="infoBank">

        <h3>Informasi Rekening Bank</h3>

        <p>Bank: <strong id="bankNama"></strong></p>
        <p>Nomor Rekening: <strong id="bankNomor"></strong></p>
        <p>Atas Nama: <strong id="bankPemilik"></strong></p>

    </div>

    <!-- INFORMASI E-WALLET -->

    <div class="info-pembayaran" id="infoEwallet">

        <h3>Informasi E-Wallet</h3>

        <p>Metode: <strong id="ewalletNama"></strong></p>
        <p>Nomor Tujuan: <strong id="ewalletNomor"></strong></p>
        <p>Atas Nama: <strong id="ewalletPemilik"></strong></p>

    </div>

    <h2 class="judul" style="margin-top:30px;">
        02. Isi Nominal Pembayaran
    </h2>

    <label>Nominal Uang (Rupiah)</label>

    <input
        type="number"
        name="nominal"
        id="nominal"
        min="<?= $harga_voting ?>"
        max="100000000"
        step="1"
        value="<?= $harga_voting ?>"
        required
    >

    <div class="nominal-box">
        <span>Minimal pembayaran</span>
        <h2>
            Rp <?= number_format($harga_voting, 0, ',', '.') ?>
        </h2>
    </div>

    <h2 class="judul" style="margin-top:30px;">
        03. Upload Bukti Pembayaran
    </h2>

    <label>Bukti Transfer / Pembayaran</label>

    <input
        type="file"
        name="bukti"
        accept=".jpg,.jpeg,.png,.webp"
        required
    >

    <p class="catatan">
        Format JPG, PNG, WEBP. Maksimal 2 MB.
        Pastikan nominal dan penerima pada bukti pembayaran sesuai.
        Admin akan memeriksa pembayaran sebelum suara dihitung.
    </p>

    <button type="submit" class="btn">
        KIRIM BUKTI PEMBAYARAN
    </button>

</form>

</div>

<script>

// Data rekening dari PHP
const rekening = <?= json_encode(
    $rekening,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const metode = document.getElementById('metode');

const infoQRIS = document.getElementById('infoQRIS');
const infoBank = document.getElementById('infoBank');
const infoEwallet = document.getElementById('infoEwallet');

function tampilkanPembayaran() {

    const pilihan = metode.value;

    infoQRIS.style.display = 'none';
    infoBank.style.display = 'none';
    infoEwallet.style.display = 'none';

    if (pilihan === 'QRIS') {

        infoQRIS.style.display = 'block';

    } else if (
        pilihan === 'Bank BRI' ||
        pilihan === 'Bank BCA' ||
        pilihan === 'Bank Mandiri'
    ) {

        const data = rekening[pilihan];

        document.getElementById('bankNama').textContent = pilihan;
        document.getElementById('bankNomor').textContent = data.nomor;
        document.getElementById('bankPemilik').textContent = data.pemilik;

        infoBank.style.display = 'block';

    } else if (rekening[pilihan]) {

        const data = rekening[pilihan];

        document.getElementById('ewalletNama').textContent = pilihan;
        document.getElementById('ewalletNomor').textContent = data.nomor;
        document.getElementById('ewalletPemilik').textContent = data.pemilik;

        infoEwallet.style.display = 'block';
    }
}

metode.addEventListener('change', tampilkanPembayaran);

</script>

</body>
</html>
