<?php
ob_start();
session_start();
$pageTitle = 'Tambah Produk';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name      = trim($_POST['name']);
    $desc      = trim($_POST['description']);
    $price     = intval($_POST['price']);
    $contact   = trim($_POST['contact']);
    $cat_id    = intval($_POST['cat_id']);
    $cbf_kat   = trim($_POST['cbf_kategori']);
    $cbf_rasa  = trim($_POST['cbf_rasa']);
    $cbf_bahan = trim($_POST['cbf_bahan']);
    $cbf_pedas = trim($_POST['cbf_kepedasan']);

    if (empty($name))    $errors[] = 'Nama produk wajib diisi.';
    if (empty($desc))    $errors[] = 'Deskripsi wajib diisi.';
    if ($price <= 0)     $errors[] = 'Harga harus lebih dari 0.';
    if ($cat_id <= 0)    $errors[] = 'Kategori wajib dipilih.';
    if (empty($cbf_kat)) $errors[] = 'Jenis produk wajib dipilih.';
    if (empty($cbf_bahan)) $errors[] = 'Bahan utama wajib diisi.';

    $picture = 'default.png';
    if (!empty($_FILES['picture']['name'])) {
        $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $errors[] = 'Format gambar tidak didukung.';
        } elseif ($_FILES['picture']['size'] > 3000000) {
            $errors[] = 'Ukuran gambar maksimal 3MB.';
        } else {
            $picture = rand(1000,9999999).'_'.basename($_FILES['picture']['name']);
            move_uploaded_file($_FILES['picture']['tmp_name'], 'admin/uploads/items/'.$picture);
        }
    }

    if (empty($errors)) {
        $stmt = $con->prepare("INSERT INTO items (Name,Description,Price,Add_Date,Country_Made,Status,Rating,Approve,Cat_ID,Member_ID,picture,contact,cbf_kategori,cbf_rasa,cbf_bahan,cbf_kepedasan) VALUES (?,?,?,NOW(),'Indonesia','1',0,0,?,?,?,?,?,?,?,?)");
        $stmt->execute([htmlspecialchars($name),htmlspecialchars($desc),$price,$cat_id,$_SESSION['uid'],$picture,htmlspecialchars($contact),$cbf_kat,$cbf_rasa,$cbf_bahan,$cbf_pedas]);
        $success = 'Produk berhasil ditambahkan! Menunggu persetujuan admin.';
    }
}
$allCats = getAllFrom("*","categories","where parent = 0","","ID","ASC");
?>
<div class="page-banner"><div class="container">
  <h1>Tambah Produk Baru</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <span>Tambah Produk</span></div>
</div></div>

<div class="container" style="padding:40px 0;">
<div class="row"><div class="col-md-8 col-md-offset-2">

<?php foreach ($errors as $e) echo '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> '.$e.'</div>'; ?>
<?php if ($success) echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> '.$success.'</div>'; ?>

<div style="background:#fff;border-radius:14px;padding:32px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
<form method="POST" enctype="multipart/form-data">

<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#1B2E5E;padding-bottom:8px;border-bottom:2px solid #DDE1EC;">Informasi Produk</p>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">NAMA PRODUK *</label>
  <input class="form-control" type="text" name="name" placeholder="Contoh: Ayam Geprek Sambal Bawang" required value="<?php echo isset($_POST['name'])?htmlspecialchars($_POST['name']):'' ?>">
</div>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">DESKRIPSI *</label>
  <textarea class="form-control" name="description" rows="4" placeholder="Jelaskan produk kamu secara detail..." required style="resize:vertical;"><?php echo isset($_POST['description'])?htmlspecialchars($_POST['description']):'' ?></textarea>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">HARGA (Rp) *</label>
      <input class="form-control" type="number" name="price" placeholder="25000" min="100" required value="<?php echo isset($_POST['price'])?$_POST['price']:'' ?>">
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">KATEGORI *</label>
      <select class="form-control" name="cat_id" required>
        <option value="">-- Pilih Kategori --</option>
        <?php foreach ($allCats as $cat): ?>
        <option value="<?php echo $cat['ID'] ?>" <?php echo (isset($_POST['cat_id'])&&$_POST['cat_id']==$cat['ID'])?'selected':'' ?>><?php echo htmlspecialchars($cat['Name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">NO. KONTAK / WHATSAPP</label>
  <input class="form-control" type="text" name="contact" placeholder="08123456789" value="<?php echo isset($_POST['contact'])?htmlspecialchars($_POST['contact']):'' ?>">
</div>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">FOTO PRODUK</label>
  <input class="form-control" type="file" name="picture" accept=".jpg,.jpeg,.png,.gif,.webp" style="padding:6px 12px;">
  <small style="color:#9A9AB0;font-size:12px;">Format JPG/PNG/GIF/WEBP, maks 3MB.</small>
</div>

<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#1B2E5E;margin-top:24px;padding-bottom:8px;border-bottom:2px solid #DDE1EC;">
  <i class="fa fa-magic" style="color:#B5272A;"></i> Atribut Rekomendasi CBF
  <span style="font-weight:400;color:#9A9AB0;font-size:10px;margin-left:6px;">Digunakan sistem untuk merekomendasikan produk serupa</span>
</p>

<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">JENIS PRODUK *</label>
      <select class="form-control" name="cbf_kategori" required>
        <option value="">-- Pilih Jenis --</option>
        <?php foreach (['makanan-berat'=>'Makanan Berat','snack'=>'Snack / Camilan','kue-dessert'=>'Kue & Dessert','sambal-bumbu'=>'Sambal & Bumbu','minuman'=>'Minuman'] as $v=>$l): ?>
        <option value="<?php echo $v ?>" <?php echo (isset($_POST['cbf_kategori'])&&$_POST['cbf_kategori']==$v)?'selected':'' ?>><?php echo $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">TINGKAT KEPEDASAN *</label>
      <select class="form-control" name="cbf_kepedasan" required>
        <option value="">-- Pilih Level --</option>
        <?php foreach (['tidak-pedas'=>'Tidak Pedas','sedang'=>'Sedang','pedas'=>'Pedas'] as $v=>$l): ?>
        <option value="<?php echo $v ?>" <?php echo (isset($_POST['cbf_kepedasan'])&&$_POST['cbf_kepedasan']==$v)?'selected':'' ?>><?php echo $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">PROFIL RASA *</label>
  <input class="form-control" type="text" name="cbf_rasa" placeholder="Contoh: gurih manis pedas" required value="<?php echo isset($_POST['cbf_rasa'])?htmlspecialchars($_POST['cbf_rasa']):'' ?>">
  <small style="color:#9A9AB0;font-size:12px;">Kata kunci: manis, gurih, pedas, asam, asin, pahit, segar, hangat</small>
</div>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">BAHAN UTAMA *</label>
  <input class="form-control" type="text" name="cbf_bahan" placeholder="Contoh: ayam cabai bawang tepung" required value="<?php echo isset($_POST['cbf_bahan'])?htmlspecialchars($_POST['cbf_bahan']):'' ?>">
  <small style="color:#9A9AB0;font-size:12px;">Pisahkan dengan spasi, huruf kecil</small>
</div>

<button type="submit" class="btn-submit"><i class="fa fa-plus"></i> Tambah Produk</button>
<a href="myItems.php" style="margin-left:12px;font-size:14px;color:#9A9AB0;">Batal</a>

</form></div></div></div></div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>