<?php
ob_start(); session_start();
$pageTitle = 'Pesanan Masuk';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

// Konfirmasi pesanan
if (isset($_GET['confirm']) && is_numeric($_GET['confirm'])) {
    $con->prepare("UPDATE orders SET status='Diproses' WHERE order_id=?")->execute([intval($_GET['confirm'])]);
    header('Location: seller_orders.php?confirmed=1'); exit();
}
if (isset($_GET['done']) && is_numeric($_GET['done'])) {
    $con->prepare("UPDATE orders SET status='Selesai' WHERE order_id=?")->execute([intval($_GET['done'])]);
    header('Location: seller_orders.php'); exit();
}

// Ambil semua pesanan yang mengandung produk milik penjual ini
$stmt = $con->prepare("
    SELECT DISTINCT o.*, u.Username, u.FullName
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.order_id
    INNER JOIN items i ON i.Item_ID = oi.item_id
    INNER JOIN users u ON u.UserID = o.user_id
    WHERE i.Member_ID = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['uid']]);
$orders = $stmt->fetchAll();

$statusColor = [
    'Belum Dibayar'       => ['bg'=>'#FEF3C7','color'=>'#92400E'],
    'Menunggu Konfirmasi' => ['bg'=>'#E0F2FE','color'=>'#0369A1'],
    'Diproses'            => ['bg'=>'#EDE9FE','color'=>'#5B21B6'],
    'Selesai'             => ['bg'=>'#EAF5ED','color'=>'#1A5C2A'],
    'Dibatalkan'          => ['bg'=>'#FDECEA','color'=>'#9B1C1C'],
];
?>
<div class="page-banner"><div class="container">
  <h1>Pesanan Masuk</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="myItems.php">Produk Saya</a> &rsaquo; <span>Pesanan Masuk</span></div>
</div></div>

<div class="container" style="padding:36px 0;">
<?php if (isset($_GET['confirmed'])): ?>
<div class="alert alert-success"><i class="fa fa-check-circle"></i> Pesanan dikonfirmasi, status diubah ke Diproses.</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h2 style="margin:0;font-size:22px;color:#1B2E5E;">Semua Pesanan (<?php echo count($orders) ?>)</h2>
  <a href="myItems.php" style="font-size:13px;color:#1B2E5E;font-weight:600;"><i class="fa fa-arrow-left"></i> Produk Saya</a>
</div>

<?php if (empty($orders)): ?>
<div class="empty-state"><i class="fa fa-inbox"></i><p>Belum ada pesanan masuk.</p></div>
<?php else: ?>
<?php foreach ($orders as $order):
  $stmtItems = $con->prepare("SELECT oi.*, items.Name, items.picture FROM order_items oi INNER JOIN items ON items.Item_ID=oi.item_id WHERE oi.order_id=? AND items.Member_ID=?");
  $stmtItems->execute([$order['order_id'], $_SESSION['uid']]);
  $oItems = $stmtItems->fetchAll();
  $sc = $statusColor[$order['status']] ?? ['bg'=>'#F0F2F5','color'=>'#4A4A6A'];
?>
<div style="background:#fff;border-radius:14px;margin-bottom:16px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
  <div style="padding:14px 20px;border-bottom:1px solid #DDE1EC;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;background:#F7F8FA;">
    <div>
      <strong style="color:#1B2E5E;">Order #<?php echo $order['order_id'] ?></strong>
      <span style="font-size:12px;color:#9A9AB0;margin-left:10px;"><i class="fa fa-user"></i> <?php echo htmlspecialchars($order['FullName']?:$order['Username']) ?></span>
      <span style="font-size:12px;color:#9A9AB0;margin-left:10px;"><i class="fa fa-clock-o"></i> <?php echo $order['created_at'] ?></span>
    </div>
    <span style="background:<?php echo $sc['bg'] ?>;color:<?php echo $sc['color'] ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;"><?php echo $order['status'] ?></span>
  </div>
  <div style="padding:16px 20px;">
    <?php foreach ($oItems as $oi): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
      <img src="<?php echo empty($oi['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($oi['picture']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#EEF0F6;">
      <div style="flex:1;font-size:14px;">
        <div style="font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($oi['Name']) ?></div>
        <div style="color:#9A9AB0;font-size:12px;">x<?php echo $oi['qty'] ?> &times; Rp <?php echo number_format($oi['harga'],0,',','.') ?></div>
      </div>
    </div>
    <?php endforeach; ?>

    <div style="background:#F0F2F5;border-radius:8px;padding:12px;margin-top:8px;font-size:13px;color:#4A4A6A;">
      <div><i class="fa fa-map-marker" style="color:#1B2E5E;width:16px;"></i> <?php echo htmlspecialchars($order['alamat'] ?: '-') ?></div>
      <div style="margin-top:4px;"><i class="fa fa-credit-card" style="color:#1B2E5E;width:16px;"></i> <?php echo htmlspecialchars($order['metode_bayar']) ?></div>
      <?php if ($order['catatan']): ?>
      <div style="margin-top:4px;"><i class="fa fa-comment" style="color:#1B2E5E;width:16px;"></i> <?php echo htmlspecialchars($order['catatan']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($order['bukti_bayar']): ?>
    <div style="margin-top:8px;">
      <a href="admin/uploads/items/<?php echo htmlspecialchars($order['bukti_bayar']) ?>" target="_blank" style="font-size:13px;color:#1B2E5E;font-weight:600;">
        <i class="fa fa-file-image-o"></i> Lihat Bukti Bayar
      </a>
    </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid #DDE1EC;">
      <div style="font-size:16px;font-weight:700;color:#B5272A;">Total: Rp <?php echo number_format($order['total_harga'],0,',','.') ?></div>
      <div style="display:flex;gap:8px;">
        <?php if ($order['status']=='Menunggu Konfirmasi'): ?>
          <a href="seller_orders.php?confirm=<?php echo $order['order_id'] ?>" style="background:#1B2E5E;color:#fff;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;" onclick="return confirm('Konfirmasi pesanan ini?')">
            <i class="fa fa-check"></i> Konfirmasi
          </a>
        <?php endif; ?>
        <?php if ($order['status']=='Diproses'): ?>
          <a href="seller_orders.php?done=<?php echo $order['order_id'] ?>" style="background:#1A5C2A;color:#fff;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;" onclick="return confirm('Tandai pesanan selesai?')">
            <i class="fa fa-check-circle"></i> Selesai
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>