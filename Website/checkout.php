<?php
ob_start(); session_start();
$pageTitle = 'Checkout';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';
if (empty($_SESSION['cart'])) { header('Location: cart.php'); exit(); }

$cart  = $_SESSION['cart'];
$total = array_sum(array_map(fn($i) => $i['price']*$i['qty'], $cart));
$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $alamat       = trim($_POST['alamat']);
    $catatan      = trim($_POST['catatan']);
    $metode_bayar = trim($_POST['metode_bayar']);

    if (empty($alamat))       $errors[] = 'Alamat pengiriman wajib diisi.';
    if (empty($metode_bayar)) $errors[] = 'Metode pembayaran wajib dipilih.';

    $bukti_bayar = '';
    if (!empty($_FILES['bukti_bayar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','gif','pdf'])) {
            $errors[] = 'Format bukti bayar tidak didukung (JPG/PNG/PDF).';
        } else {
            $bukti_bayar = 'bukti_'.rand(1000,9999999).'.'.$ext;
            move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], 'admin/uploads/items/'.$bukti_bayar);
        }
    }

    if (empty($errors)) {
        // Simpan order
        $ins = $con->prepare("INSERT INTO orders (user_id,total_harga,status,metode_bayar,bukti_bayar,catatan,alamat) VALUES (?,?,?,?,?,?,?)");
        $status = $bukti_bayar ? 'Menunggu Konfirmasi' : 'Belum Dibayar';
        $ins->execute([$_SESSION['uid'], $total, $status, $metode_bayar, $bukti_bayar, $catatan, $alamat]);
        $order_id = $con->lastInsertId();

        // Simpan order items
        $insItem = $con->prepare("INSERT INTO order_items (order_id,item_id,qty,harga) VALUES (?,?,?,?)");
        foreach ($cart as $ci) {
            $insItem->execute([$order_id, $ci['item_id'], $ci['qty'], $ci['price']]);
        }

        // Kosongkan keranjang
        unset($_SESSION['cart']);
        header('Location: orders.php?success=1'); exit();
    }
}
?>
<div class="page-banner"><div class="container">
  <h1>Checkout</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="cart.php">Keranjang</a> &rsaquo; <span>Checkout</span></div>
</div></div>

<div class="container" style="padding:36px 0;">
<?php foreach ($errors as $e) echo '<div class="alert alert-danger">'.$e.'</div>'; ?>
<div class="row">
  <div class="col-md-7">
    <div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <h3 style="font-size:18px;color:#1B2E5E;margin:0 0 20px;">Detail Pesanan</h3>
      <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">ALAMAT PENGIRIMAN / AMBIL *</label>
          <textarea class="form-control" name="alamat" rows="3" placeholder="Tulis alamat lengkap atau tulis 'Ambil Sendiri' jika pickup" required style="resize:vertical;"><?php echo isset($_POST['alamat'])?htmlspecialchars($_POST['alamat']):'' ?></textarea>
        </div>

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">METODE PEMBAYARAN *</label>
          <select class="form-control" name="metode_bayar" required>
            <option value="">-- Pilih Metode --</option>
            <option value="Transfer BCA">Transfer BCA</option>
            <option value="Transfer BRI">Transfer BRI</option>
            <option value="Transfer Mandiri">Transfer Mandiri</option>
            <option value="GoPay">GoPay</option>
            <option value="OVO">OVO</option>
            <option value="DANA">DANA</option>
            <option value="COD / Bayar di Tempat">COD / Bayar di Tempat</option>
          </select>
        </div>

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">UPLOAD BUKTI BAYAR <span style="color:#9A9AB0;font-weight:400;">(opsional, bisa diupload nanti)</span></label>
          <input class="form-control" type="file" name="bukti_bayar" accept=".jpg,.jpeg,.png,.pdf" style="padding:6px 12px;">
          <small style="color:#9A9AB0;font-size:12px;">Format JPG/PNG/PDF, maks 5MB.</small>
        </div>

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">CATATAN UNTUK PENJUAL</label>
          <textarea class="form-control" name="catatan" rows="2" placeholder="Contoh: tanpa sambal, ekstra nasi..." style="resize:vertical;"><?php echo isset($_POST['catatan'])?htmlspecialchars($_POST['catatan']):'' ?></textarea>
        </div>

        <button type="submit" class="btn-submit"><i class="fa fa-check"></i> Buat Pesanan</button>
      </form>
    </div>
  </div>

  <div class="col-md-5">
    <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <h3 style="font-size:16px;color:#1B2E5E;margin:0 0 16px;">Ringkasan (<?php echo count($cart) ?> produk)</h3>
      <?php foreach ($cart as $ci): ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <img src="<?php echo empty($ci['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($ci['picture']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#EEF0F6;">
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($ci['name']) ?></div>
          <div style="font-size:12px;color:#9A9AB0;">x<?php echo $ci['qty'] ?> &times; Rp <?php echo number_format($ci['price'],0,',','.') ?></div>
        </div>
        <div style="font-size:13px;font-weight:700;color:#1B2E5E;">Rp <?php echo number_format($ci['price']*$ci['qty'],0,',','.') ?></div>
      </div>
      <?php endforeach; ?>
      <div style="border-top:1.5px solid #DDE1EC;margin:16px 0;"></div>
      <div style="display:flex;justify-content:space-between;font-weight:700;color:#1B2E5E;font-size:18px;">
        <span>Total Bayar</span>
        <span style="color:#B5272A;">Rp <?php echo number_format($total,0,',','.') ?></span>
      </div>
    </div>
  </div>
</div>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>