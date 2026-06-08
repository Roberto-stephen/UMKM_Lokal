<?php
ob_start(); session_start();
$pageTitle = 'Edit Produk';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $con->prepare("SELECT * FROM items WHERE Item_ID=? AND Member_ID=?");
$stmt->execute([$id, $_SESSION['uid']]);
if ($stmt->rowCount() == 0) {
    echo '<div class="container"><div class="alert alert-danger" style="margin-top:30px;">Produk tidak ditemukan.</div></div>';
    include $tpl.'footer.php'; exit();
}
$item = $stmt->fetch();

$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $price     = intval($_POST['price'] ?? 0);
    $contact   = trim($_POST['contact'] ?? '');
    $cat_id    = intval($_POST['cat_id'] ?? 0);
    $stok      = intval($_POST['stok'] ?? 0);
    $cbf_kat   = trim($_POST['cbf_kategori'] ?? '');
    $cbf_rasa  = trim($_POST['cbf_rasa'] ?? '');
    $cbf_bahan = trim($_POST['cbf_bahan'] ?? '');
    $cbf_pedas = trim($_POST['cbf_kepedasan'] ?? '');

    if (empty($name))  $errors[] = 'Nama produk wajib diisi.';
    if (empty($desc))  $errors[] = 'Deskripsi wajib diisi.';
    if ($price <= 0)   $errors[] = 'Harga harus lebih dari 0.';
    if ($cat_id <= 0)  $errors[] = 'Kategori wajib dipilih.';

    $picture = $item['picture'];
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
        $upd = $con->prepare("UPDATE items SET Name=?,Description=?,Price=?,Cat_ID=?,contact=?,picture=?,stok=?,cbf_kategori=?,cbf_rasa=?,cbf_bahan=?,cbf_kepedasan=?,Approve=0 WHERE Item_ID=? AND Member_ID=?");
        $upd->execute([
            htmlspecialchars($name), htmlspecialchars($desc),
            $price, $cat_id, htmlspecialchars($contact),
            $picture, $stok, $cbf_kat, $cbf_rasa, $cbf_bahan, $cbf_pedas,
            $id, $_SESSION['uid']
        ]);
        header('Location: myItems.php?updated=1'); exit();
    }
}
$allCats = getAllFrom("*","categories","where parent = 0","","ID","ASC");
?>
<div class="page-banner"><div class="container">
  <h1>Edit Produk</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="myItems.php">Produk Saya</a> &rsaquo; <span>Edit</span></div>
</div></div>

<div class="container" style="padding:40px 0;">
<div class="row"><div class="col-md-8 col-md-offset-2">
<?php foreach ($errors as $e) echo '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> '.$e.'</div>'; ?>

<div style="background:#fff;border-radius:14px;padding:32px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
<form method="POST" enctype="multipart/form-data">

<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#1B2E5E;padding-bottom:8px;border-bottom:2px solid #DDE1EC;">Informasi Produk</p>

<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">NAMA PRODUK *</label>
  <input class="form-control" type="text" name="name" required value="<?php echo htmlspecialchars($item['Name']) ?>">
</div>
<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">DESKRIPSI *</label>
  <textarea class="form-control" name="description" rows="4" required style="resize:vertical;"><?php echo htmlspecialchars($item['Description']) ?></textarea>
</div>
<div class="row">
  <div class="col-md-4">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">HARGA (Rp) *</label>
      <input class="form-control" type="number" name="price" min="100" required value="<?php echo $item['Price'] ?>">
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">STOK</label>
      <input class="form-control" type="number" name="stok" min="0" value="<?php echo $item['stok'] ?? 0 ?>">
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">KATEGORI *</label>
      <select class="form-control" name="cat_id" required>
        <?php foreach ($allCats as $cat): ?>
        <option value="<?php echo $cat['ID'] ?>" <?php echo $cat['ID']==$item['Cat_ID']?'selected':'' ?>>
          <?php echo htmlspecialchars($cat['Name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>
<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">NO. KONTAK</label>
  <input class="form-control" type="text" name="contact" value="<?php echo htmlspecialchars($item['contact']) ?>">
</div>
<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">FOTO PRODUK <span style="color:#9A9AB0;font-weight:400;">(kosongkan jika tidak diganti)</span></label>
  <?php if ($item['picture'] && $item['picture']!='default.png'): ?>
  <img src="admin/uploads/items/<?php echo htmlspecialchars($item['picture']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;margin-bottom:8px;display:block;border:1px solid #DDE1EC;">
  <?php endif; ?>
  <input class="form-control" type="file" name="picture" accept=".jpg,.jpeg,.png,.gif,.webp" style="padding:6px 12px;">
</div>

<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#1B2E5E;margin-top:24px;padding-bottom:8px;border-bottom:2px solid #DDE1EC;">
  <i class="fa fa-magic" style="color:#B5272A;"></i> Atribut CBF
</p>
<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">JENIS PRODUK</label>
      <select class="form-control" name="cbf_kategori">
        <?php foreach (['makanan-berat'=>'Makanan Berat','snack'=>'Snack / Camilan','kue-dessert'=>'Kue & Dessert','sambal-bumbu'=>'Sambal & Bumbu','minuman'=>'Minuman'] as $v=>$l): ?>
        <option value="<?php echo $v ?>" <?php echo $item['cbf_kategori']==$v?'selected':'' ?>><?php echo $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">KEPEDASAN</label>
      <select class="form-control" name="cbf_kepedasan">
        <?php foreach (['tidak-pedas'=>'Tidak Pedas','sedang'=>'Sedang','pedas'=>'Pedas'] as $v=>$l): ?>
        <option value="<?php echo $v ?>" <?php echo $item['cbf_kepedasan']==$v?'selected':'' ?>><?php echo $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>
<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">PROFIL RASA</label>
  <input class="form-control" type="text" name="cbf_rasa" value="<?php echo htmlspecialchars($item['cbf_rasa']) ?>">
</div>
<div class="form-group">
  <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">BAHAN UTAMA</label>
  <input class="form-control" type="text" name="cbf_bahan" value="<?php echo htmlspecialchars($item['cbf_bahan']) ?>">
</div>

<div style="margin-top:8px;background:#FEF3C7;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400E;margin-bottom:16px;">
  <i class="fa fa-info-circle"></i> Setelah diedit, produk akan kembali menunggu persetujuan admin.
</div>

<button type="submit" class="btn-submit"><i class="fa fa-save"></i> Simpan Perubahan</button>
<a href="myItems.php" style="margin-left:12px;font-size:14px;color:#9A9AB0;">Batal</a>
</form>
</div>
</div></div></div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>