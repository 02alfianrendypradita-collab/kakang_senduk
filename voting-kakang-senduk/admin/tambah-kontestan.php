
<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Permintaan tidak valid. Silakan coba kembali.";
    } else {

        $nomor = trim($_POST['nomor_peserta'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $kategori = $_POST['kategori'] ?? '';
        $tempat = trim($_POST['tempat_lahir'] ?? '');
        $tanggal = $_POST['tanggal_lahir'] ?? '';
        $instansi = trim($_POST['asal_instansi'] ?? '');
        $visi = trim($_POST['visi'] ?? '');
        $misi = trim($_POST['misi'] ?? '');
        $prestasi = trim($_POST['prestasi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        $foto = null;

        if (
            $nomor === '' || $nama === '' ||
            !in_array($kategori, ['Kakang', 'Senduk'], true)
        ) {
            $error = "Nomor, nama, dan kategori wajib diisi dengan benar.";
        }

        // Validasi tanggal
        if ($error === '' && $tanggal !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $tanggal);

            if (!$date || $date->format('Y-m-d') !== $tanggal) {
                $error = "Tanggal lahir tidak valid.";
            }
        }

        // Validasi dan upload foto
        if ($error === '' && isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $error = "Upload foto gagal.";
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                $error = "Ukuran foto maksimal 2 MB.";
            } else {
                $tmp = $_FILES['foto']['tmp_name'];
                $mime = mime_content_type($tmp);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed[$mime]) || getimagesize($tmp) === false) {
                    $error = "Foto harus berformat JPG, PNG, atau WEBP.";
                } else {
                    $uploadDir = __DIR__ . '/../uploads/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $foto = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

                    if (!move_uploaded_file($tmp, $uploadDir . $foto)) {
                        $error = "Foto gagal disimpan.";
                    }
                }
            }
        }

        if ($error === '') {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO kontestan
                (nomor_peserta, nama, kategori, foto,
                 tempat_lahir, tanggal_lahir, asal_instansi,
                 visi, misi, prestasi, deskripsi)
                VALUES (?, ?, ?, ?, ?, NULLIF(?, ''),
                        ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssss",
                $nomor,
                $nama,
                $kategori,
                $foto,
                $tempat,
                $tanggal,
                $instansi,
                $visi,
                $misi,
                $prestasi,
                $deskripsi
            );

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: kontestan.php?status=berhasil");
                exit;
            } else {
                if ($foto && file_exists(__DIR__ . '/../uploads/' . $foto)) {
                    unlink(__DIR__ . '/../uploads/' . $foto);
                }

                $error = mysqli_errno($conn) === 1062
                    ? "Nomor peserta sudah digunakan."
                    : "Gagal menyimpan data kontestan.";
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

<title>Tambah Kontestan | Kakang Senduk</title>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #0b0b0b;
    color: #eee;
    font-family: 'Poppins', sans-serif;
}

.container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 20px;
}

.header {
    margin-bottom: 25px;
}

.header h2 {
    font-family: 'Cinzel', serif;
    color: #e4c36b;
    font-size: 25px;
}

.header p {
    color: #999;
    font-size: 12px;
    margin-top: 8px;
}

.form-card {
    background: #151515;
    border: 1px solid #493b20;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 15px 40px rgba(0,0,0,.3);
}

.section-title {
    color: #e2c16c;
    font-family: 'Cinzel', serif;
    font-size: 16px;
    padding-bottom: 15px;
    margin-bottom: 20px;
    border-bottom: 1px solid #39301d;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.group {
    margin-bottom: 20px;
}

.full {
    grid-column: 1 / -1;
}

label {
    display: block;
    color: #d6bd80;
    font-size: 12px;
    margin-bottom: 9px;
}

input, select, textarea {
    width: 100%;
    background: #1d1d1d;
    border: 1px solid #493b20;
    border-radius: 8px;
    padding: 13px;
    color: white;
    outline: none;
    font-family: inherit;
    font-size: 12px;
}

input:focus, select:focus, textarea:focus {
    border-color: #d4af37;
    box-shadow: 0 0 12px rgba(212,175,55,.12);
}

textarea {
    resize: vertical;
    min-height: 100px;
}

input[type=file] {
    padding: 10px;
}

.preview {
    width: 130px;
    height: 155px;
    margin-top: 15px;
    border: 1px dashed #96742e;
    border-radius: 10px;
    display: none;
    object-fit: cover;
}

.error {
    padding: 13px;
    border-radius: 8px;
    background: #351b1b;
    color: #ffaaaa;
    border: 1px solid #743c3c;
    font-size: 12px;
    margin-bottom: 20px;
}

.buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 25px;
}

.btn {
    padding: 13px 22px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 600;
    font-size: 12px;
    text-decoration: none;
}

.save {
    background: linear-gradient(135deg,#f0d58a,#b68a2c);
    color: #171006;
}

.back {
    background: #28231a;
    border: 1px solid #57451f;
    color: #e3c36e;
}

@media(max-width:650px) {
    .grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .form-card {
        padding: 20px;
    }

    .buttons {
        flex-direction: column;
    }

    .btn {
        text-align: center;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <h2>Tambah Kontestan</h2>
        <p>Tambahkan peserta baru Pemilihan Kakang Senduk Ponorogo.</p>
    </div>

    <div class="form-card">

        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <h3 class="section-title">Informasi Kontestan</h3>

            <div class="grid">

                <div class="group">
                    <label>Nomor Peserta *</label>
                    <input type="text"
                           name="nomor_peserta"
                           placeholder="Contoh: K001"
                           maxlength="10"
                           value="<?= htmlspecialchars($_POST['nomor_peserta'] ?? '') ?>"
                           required>
                </div>

                <div class="group">
                    <label>Nama Lengkap *</label>
                    <input type="text"
                           name="nama"
                           placeholder="Nama lengkap kontestan"
                           maxlength="100"
                           value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                           required>
                </div>

                <div class="group">
                    <label>Kategori *</label>
                    <select name="kategori" required>
                        <option value="">Pilih kategori</option>
                        <option value="Kakang">Kakang</option>
                        <option value="Senduk">Senduk</option>
                    </select>
                </div>

                <div class="group">
                    <label>Asal Sekolah / Instansi</label>
                    <input type="text"
                           name="asal_instansi"
                           placeholder="Nama sekolah atau instansi"
                           maxlength="150">
                </div>

                <div class="group">
                    <label>Tempat Lahir</label>
                    <input type="text"
                           name="tempat_lahir"
                           placeholder="Contoh: Ponorogo">
                </div>

                <div class="group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir">
                </div>

                <div class="group full">
                    <label>Foto Kontestan</label>
                    <input type="file"
                           name="foto"
                           id="foto"
                           accept="image/jpeg,image/png,image/webp">

                    <img id="preview" class="preview" alt="Preview foto">
                    <small style="color:#888;font-size:10px">
                        JPG, PNG, WEBP. Maksimal 2 MB.
                    </small>
                </div>

            </div>

            <h3 class="section-title" style="margin-top:20px">
                Profil dan Informasi
            </h3>

            <div class="group">
                <label>Visi</label>
                <textarea name="visi"
                          placeholder="Masukkan visi kontestan..."></textarea>
            </div>

            <div class="group">
                <label>Misi</label>
                <textarea name="misi"
                          placeholder="Masukkan misi kontestan..."></textarea>
            </div>

            <div class="group">
                <label>Prestasi</label>
                <textarea name="prestasi"
                          placeholder="Masukkan prestasi kontestan..."></textarea>
            </div>

            <div class="group">
                <label>Deskripsi Profil</label>
                <textarea name="deskripsi"
                          placeholder="Ceritakan profil lengkap kontestan..."></textarea>
            </div>

            <div class="buttons">
                <a href="kontestan.php" class="btn back">
                    Kembali
                </a>

                <button type="submit" class="btn save">
                    + SIMPAN KONTESTAN
                </button>
            </div>

        </form>

    </div>

</div>

<script>
const inputFoto = document.getElementById('foto');
const preview = document.getElementById('preview');

inputFoto.addEventListener('change', function() {
    const file = this.files[0];

    if (!file) {
        preview.style.display = 'none';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran foto maksimal 2 MB!');
        this.value = '';
        preview.style.display = 'none';
        return;
    }

    const url = URL.createObjectURL(file);
    preview.src = url;
    preview.style.display = 'block';
});
</script>

</body>
</html>
