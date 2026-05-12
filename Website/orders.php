<?php
ob_start(); session_start();
$pageTitle = 'Riwayat Pesanan';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

$stmt = $con->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['uid']]);
$orders = $stmt->fetchAll();

$statusColor = [
    'Belum Dibayar'        => ['bg'=>'#FEF3C7','color'=>'#92400E'],
    'Menunggu Konfirmasi'  => ['bg'=>'#E0F2FE','color'=>'#0369A1'],
    'Diproses'             => ['bg'=>'#EDE9FE','color'=>'#5B21B6'],
    'Selesai'              => ['bg'=>'#EAF5ED','color'=>'#1A5C2A'],
    'Dibatalkan'           => ['bg'=>'#FDECEA','color'=>'#9B1C1C'],
];
?>
<div class="page-banner"><div class="container">
  <h1>Riwayat Pesanan</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <span>Pesanan Saya</span></div>
</div></div>

<div class="container" style="padding:36px 0;">
<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> Pesanan berhasil dibuat! Silakan selesaikan pembayaran.</div>
<?php endif; ?>

<?php if (empty($orders)): ?>
<div class="empty-state">
  <i class="fa fa-clipboard"></i>
  <p>Belum ada pesanan.</p>
  <a href="index.php" class="btn-detail" style="padding:10px 24px;font-size:14px;">Mulai Belanja</a>
</div>
<?php else: ?>
<?php foreach ($orders as $order):
  $stmtItems = $con->prepare("SELECT oi.*, items.Name, items.picture FROM order_items oi INNER JOIN items ON items.Item_ID=oi.item_id WHERE oi.order_id=?");
  $stmtItems->execute([$order['order_id']]);
  $oItems = $stmtItems->fetchAll();
  $sc = $statusColor[$order['status']] ?? ['bg'=>'#F0F2F5','color'=>'#4A4A6A'];
?>
<div style="background:#fff;border-radius:14px;margin-bottom:16px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
  <div style="padding:16px 20px;border-bottom:1px solid #DDE1EC;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div>
      <span style="font-size:13px;color:#9A9AB0;">Order #<?php echo $order['order_id'] ?></span>
      <span style="font-size:13px;color:#9A9AB0;margin-left:12px;"><i class="fa fa-clock-o"></i> <?php echo $order['created_at'] ?></span>
    </div>
    <span style="background:<?php echo $sc['bg'] ?>;color:<?php echo $sc['color'] ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;">
      <?php echo $order['status'] ?>
    </span>
  </div>
  <div style="padding:16px 20px;">
    <?php foreach ($oItems as $oi): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
      <img src="<?php echo empty($oi['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($oi['picture']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#EEF0F6;">
      <div style="flex:1;">
        <div style="font-size:14px;font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($oi['Name']) ?></div>
        <div style="font-size:12px;color:#9A9AB0;">x<?php echo $oi['qty'] ?> &times; Rp <?php echo number_format($oi['harga'],0,',','.') ?></div>
      </div>
      <div style="font-size:13px;font-weight:700;color:#1B2E5E;">Rp <?php echo number_format($oi['harga']*$oi['qty'],0,',','.') ?></div>
    </div>
    <?php endforeach; ?>
    <div style="border-top:1px solid #DDE1EC;margin-top:12px;padding-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
      <div style="font-size:13px;color:#4A4A6A;">
        <i class="fa fa-credit-card" style="color:#1B2E5E;"></i> <?php echo htmlspecialchars($order['metode_bayar']) ?>
        <?php if ($order['alamat']): ?>
        &nbsp;&middot;&nbsp; <i class="fa fa-map-marker" style="color:#1B2E5E;"></i> <?php echo htmlspecialchars(substr($order['alamat'],0,40)) ?>...
        <?php endif; ?>
      </div>
      <div style="font-size:16px;font-weight:700;color:#B5272A;">Total: Rp <?php echo number_format($order['total_harga'],0,',','.') ?></div>
    </div>

    <?php if ($order['status']=='Belum Dibayar'): ?>
    <div style="margin-top:12px;">
      <form method="POST" action="upload_bukti.php" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="order_id" value="<?php echo $order['order_id'] ?>">
        <input type="file" name="bukti" class="form-control" style="padding:6px 12px;flex:1;" accept=".jpg,.jpeg,.png,.pdf">
        <button type="submit" style="background:#1B2E5E;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">Upload Bukti Bayar</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>