<?php
ob_start(); session_start();
$pageTitle = 'Keranjang Belanja';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Tambah ke keranjang
if (isset($_POST['add_to_cart'])) {
    $item_id = intval($_POST['item_id']);
    $qty     = max(1, intval($_POST['qty'] ?? 1));
    $stmt    = $con->prepare("SELECT * FROM items WHERE Item_ID=? AND Approve=1");
    $stmt->execute([$item_id]);
    if ($stmt->rowCount() > 0) {
        if (isset($_SESSION['cart'][$item_id])) {
            $_SESSION['cart'][$item_id]['qty'] += $qty;
        } else {
            $p = $stmt->fetch();
            $_SESSION['cart'][$item_id] = ['item_id'=>$item_id,'name'=>$p['Name'],'price'=>$p['Price'],'picture'=>$p['picture'],'qty'=>$qty];
        }
        header('Location: cart.php?added=1'); exit();
    }
}

// Update qty
if (isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $id => $q) {
        $id = intval($id); $q = intval($q);
        if ($q <= 0) unset($_SESSION['cart'][$id]);
        elseif (isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id]['qty'] = $q;
    }
    header('Location: cart.php'); exit();
}

// Hapus item
if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][intval($_GET['remove'])]);
    header('Location: cart.php'); exit();
}

$cart  = $_SESSION['cart'];
$total = array_sum(array_map(fn($i) => $i['price']*$i['qty'], $cart));
?>
<div class="page-banner"><div class="container">
  <h1>Keranjang Belanja</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <span>Keranjang</span></div>
</div></div>

<div class="container" style="padding:36px 0;">
<?php if (isset($_GET['added'])): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> Produk ditambahkan ke keranjang!</div>
<?php endif; ?>

<?php if (empty($cart)): ?>
<div class="empty-state">
  <i class="fa fa-shopping-basket"></i>
  <p>Keranjang kamu kosong.</p>
  <a href="index.php" class="btn-detail" style="padding:10px 24px;font-size:14px;">Mulai Belanja</a>
</div>
<?php else: ?>
<div class="row">
  <div class="col-md-8">
    <form method="POST">
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
    <?php foreach ($cart as $id => $ci): ?>
    <div style="display:flex;align-items:center;gap:16px;padding:16px;border-bottom:1px solid #DDE1EC;">
      <img src="<?php echo empty($ci['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($ci['picture']) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:10px;background:#EEF0F6;flex-shrink:0;">
      <div style="flex:1;">
        <div style="font-weight:600;color:#1B2E5E;font-size:15px;"><?php echo htmlspecialchars($ci['name']) ?></div>
        <div style="color:#B5272A;font-weight:700;font-size:14px;margin-top:2px;">Rp <?php echo number_format($ci['price'],0,',','.') ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <input type="number" name="qty[<?php echo $id ?>]" value="<?php echo $ci['qty'] ?>" min="1" max="99"
               style="width:60px;padding:6px 10px;border:1.5px solid #DDE1EC;border-radius:8px;text-align:center;font-size:14px;">
      </div>
      <div style="font-weight:700;color:#1B2E5E;min-width:100px;text-align:right;">
        Rp <?php echo number_format($ci['price']*$ci['qty'],0,',','.') ?>
      </div>
      <a href="cart.php?remove=<?php echo $id ?>" style="color:#B5272A;padding:4px 8px;" onclick="return confirm('Hapus item ini?')">
        <i class="fa fa-times"></i>
      </a>
    </div>
    <?php endforeach; ?>
    <div style="padding:16px;text-align:right;">
      <button type="submit" name="update_cart" style="background:#E8ECF5;color:#1B2E5E;border:none;padding:8px 20px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;">
        <i class="fa fa-refresh"></i> Update Keranjang
      </button>
    </div>
    </div>
    </form>
  </div>
  <div class="col-md-4">
    <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <h3 style="font-size:18px;color:#1B2E5E;margin:0 0 20px;">Ringkasan Pesanan</h3>
      <?php foreach ($cart as $ci): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;color:#4A4A6A;margin-bottom:8px;">
        <span><?php echo htmlspecialchars($ci['name']) ?> x<?php echo $ci['qty'] ?></span>
        <span>Rp <?php echo number_format($ci['price']*$ci['qty'],0,',','.') ?></span>
      </div>
      <?php endforeach; ?>
      <div style="border-top:1.5px solid #DDE1EC;margin:16px 0;"></div>
      <div style="display:flex;justify-content:space-between;font-weight:700;color:#1B2E5E;font-size:16px;margin-bottom:20px;">
        <span>Total</span>
        <span>Rp <?php echo number_format($total,0,',','.') ?></span>
      </div>
      <a href="checkout.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;">
        <i class="fa fa-lock"></i> Lanjut ke Pembayaran
      </a>
      <a href="index.php" style="display:block;text-align:center;font-size:13px;color:#9A9AB0;margin-top:12px;">← Lanjut Belanja</a>
    </div>
  </div>
</div>
<?php endif; ?>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>