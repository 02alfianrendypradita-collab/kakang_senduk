
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

// CEK LOGIN ADMIN — DISESUAIKAN DENGAN kontestan.php
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// TOKEN KEAMANAN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// AMBIL ID KONTESTAN
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: kontestan.php");
    exit;
}

// AMBIL DATA KONTESTAN
$stmt = mysqli_prepare($conn, "SELECT * FROM kontestan WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kontestan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$kontestan) {
    die("Data kontestan tidak ditemukan.");
}

$pesan = "";

// PROSES UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Token keamanan tidak valid.");
    }

    $nomor = trim($_POST['nomor_peserta'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $kategori = $_POST['kategori'] ?? '';
    $instansi = trim($_POST['asal_instansi'] ?? '');
    $tempat = trim($_POST['tempat_lahir'] ?? '');
    $tanggal = $_POST['tanggal_lahir'] ?? '';
    $visi = trim($_POST['visi'] ?? '');
    $misi = trim($_POST['misi'] ?? '');
    $prestasi = trim($_POST['prestasi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (
        $nomor === '' ||
        $nama === '' ||
        !in_array($kategori, ['Kakang', 'Senduk'], true)
    ) {
        $pesan = "Nomor peserta, nama, dan kategori wajib diisi.";
    } else {

        $foto = $kontestan['foto'];
        $fotoBaru = null;
        $errorUpload = '';

        // CEK FOTO BARU
        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $errorUpload = "Upload foto gagal.";
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                $errorUpload = "Ukuran foto maksimal 2 MB.";
            } else {

                $mime = mime_content_type($_FILES['foto']['tmp_name']);

                $ekstensi = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($ekstensi[$mime])) {
                    $errorUpload = "Foto harus JPG, PNG, atau WEBP.";
                } else {

                    $folder = __DIR__ . '/../uploads/';

                    if (!is_dir($folder)) {
                        mkdir($folder, 0755, true);
                    }

                    $fotoBaru = bin2hex(random_bytes(16)) . '.' . $ekstensi[$mime];

                    if (
                        move_uploaded_file(
                            $_FILES['foto']['tmp_name'],
                            $folder . $fotoBaru
                        )
                    ) {
                        $foto = $fotoBaru;
                    } else {
                        $errorUpload = "Foto gagal disimpan.";
                        $fotoBaru = null;
                    }
                }
            }
        }

        if ($errorUpload !== '') {
            $pesan = $errorUpload;
        } else {

            $stmt = mysqli_prepare($conn, "
                UPDATE kontestan SET
                    nomor_peserta = ?,
                    nama = ?,
                    kategori = ?,
                    asal_instansi = ?,
                    tempat_lahir = ?,
                    tanggal_lahir = NULLIF(?, ''),
                    foto = ?,
                    visi = ?,
                    misi = ?,
                    prestasi = ?,
                    deskripsi = ?
                WHERE id = ?
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssssi",
                $nomor,
                $nama,
                $kategori,
                $instansi,
                $tempat,
                $tanggal,
                $foto,
                $visi,
                $misi,
                $prestasi,
                $deskripsi,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                // HAPUS FOTO LAMA JIKA FOTO DIGANTI
                if ($fotoBaru !== null && !empty($kontestan['foto'])) {
                    $fotoLama = __DIR__ . '/../uploads/' . basename($kontestan['foto']);

                    if (is_file($fotoLama)) {
                        unlink($fotoLama);
                    }
                }

                header("Location: kontestan.php?status=diubah");
                exit;

            } else {

                $pesan = "Gagal memperbarui data. Nomor peserta mungkin sudah digunakan.";

                mysqli_stmt_close($stmt);

                if ($fotoBaru !== null) {
                    $pathBaru = __DIR__ . '/../uploads/' . $fotoBaru;

                    if (is_file($pathBaru)) {
                        unlink($pathBaru);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Kontestan | Kakang Senduk</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #0b0b0b;
    color: #eee;
    font-family: 'Poppins', sans-serif;
    padding: 35px 15px;
}

.container {
    max-width: 850px;
    margin: auto;
    padding: 30px;
    background: #151515;
    border: 1px solid #9c7b28;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(212,175,55,.08);
}

h2 {
    color: #e4c36b;
    font-family: 'Cinzel', serif;
    margin-bottom: 25px;
}

label {
    display: block;
    margin: 17px 0 7px;
    color: #e4c36b;
    font-size: 13px;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    background: #222;
    color: white;
    border: 1px solid #66521f;
    border-radius: 8px;
    font-family: inherit;
    outline: none;
}

input:focus, select:focus, textarea:focus {
    border-color: #d4af37;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.foto-preview {
    width: 130px;
    height: 150px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #d4af37;
}

button, .back {
    display: inline-block;
    margin-top: 23px;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #f0d58a, #b68a2c);
    color: #111;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    text-decoration: none;
}

.back {
    background: #333;
    color: white;
    margin-left: 8px;
}

.alert {
    background: #492222;
    color: #ffcaca;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 18px;
}

@media(max-width: 600px) {
    .row {
        grid-template-columns: 1fr;
    }

    .container {
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="container">

    <h2>Edit Data Kontestan</h2>

    <?php if ($pesan !== ''): ?>
        <div class="alert">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <div class="row">

            <div>
                <label>Nomor Peserta</label>
                <input
                    type="text"
                    name="nomor_peserta"
                    required
                    value="<?= htmlspecialchars($_POST['nomor_peserta'] ?? $kontestan['nomor_peserta']) ?>"
                >
            </div>

            <div>
                <label>Nama Lengkap</label>
                <input
                    type="text"
                    name="nama"
                    required
                    value="<?= htmlspecialchars($_POST['nama'] ?? $kontestan['nama']) ?>"
                >
            </div>

        </div>

        <label>Kategori</label>

        <?php $kategoriTerpilih = $_POST['kategori'] ?? $kontestan['kategori']; ?>

        <select name="kategori" required>
            <option value="Kakang" <?= $kategoriTerpilih === 'Kakang' ? 'selected' : '' ?>>
                Kakang
            </option>

            <option value="Senduk" <?= $kategoriTerpilih === 'Senduk' ? 'selected' : '' ?>>
                Senduk
            </option>
        </select>

        <label>Asal Instansi</label>
        <input
            type="text"
            name="asal_instansi"
            value="<?= htmlspecialchars($_POST['asal_instansi'] ?? $kontestan['asal_instansi'] ?? '') ?>"
        >

        <div class="row">

            <div>
                <label>Tempat Lahir</label>
                <input
                    type="text"
                    name="tempat_lahir"
                    value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? $kontestan['tempat_lahir'] ?? '') ?>"
                >
            </div>

            <div>
                <label>Tanggal Lahir</label>
                <input
                    type="date"
                    name="tanggal_lahir"
                    value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? $kontestan['tanggal_lahir'] ?? '') ?>"
                >
            </div>

        </div>

        <label>Foto Saat Ini</label>

        <?php if (!empty($kontestan['foto']) && is_file(__DIR__ . '/../uploads/' . basename($kontestan['foto']))): ?>

            <br>
            <img
                class="foto-preview"
                src="../uploads/<?= rawurlencode(basename($kontestan['foto'])) ?>"
                alt="Foto kontestan"
            >

        <?php else: ?>

            <p style="color:#999;">Belum ada foto.</p>

        <?php endif; ?>

        <label>Ganti Foto (Opsional, maksimal 2 MB)</label>
        <input
            type="file"
            name="foto"
            accept="image/jpeg,image/png,image/webp"
        >

        <label>Visi</label>
        <textarea name="visi"><?= htmlspecialchars($_POST['visi'] ?? $kontestan['visi'] ?? '') ?></textarea>

        <label>Misi</label>
        <textarea name="misi"><?= htmlspecialchars($_POST['misi'] ?? $kontestan['misi'] ?? '') ?></textarea>

        <label>Prestasi</label>
        <textarea name="prestasi"><?= htmlspecialchars($_POST['prestasi'] ?? $kontestan['prestasi'] ?? '') ?></textarea>

        <label>Deskripsi</label>
        <textarea name="deskripsi"><?= htmlspecialchars($_POST['deskripsi'] ?? $kontestan['deskripsi'] ?? '') ?></textarea>

        <button type="submit">
            Simpan Perubahan
        </button>

        <a href="kontestan.php" class="back">
            Kembali
        </a>

    </form>

</div>

</body>
</html>
