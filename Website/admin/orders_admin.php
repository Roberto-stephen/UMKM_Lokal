<?php
ob_start(); session_start();
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
$pageTitle = 'Kelola Pesanan';
include 'init.php';

// Update status
if (isset($_GET['status']) && isset($_GET['id'])) {
    $allowed = ['Diproses','Selesai','Dibatalkan','Menunggu Konfirmasi'];
    $newStatus = $_GET['status'];
    if (in_array($newStatus, $allowed)) {
        $con->prepare("UPDATE orders SET status=? WHERE order_id=?")->execute([$newStatus, intval($_GET['id'])]);
    }
    header('Location: orders_admin.php'); exit();
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$where  = $filter ? "WHERE o.status=?" : "";
$params = $filter ? [$filter] : [];

$stmt = $con->prepare("SELECT o.*, u.Username, u.FullName FROM orders o INNER JOIN users u ON u.UserID=o.user_id $where ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusColor = [
    'Belum Dibayar'       => ['bg'=>'#FEF3C7','color'=>'#92400E'],
    'Menunggu Konfirmasi' => ['bg'=>'#E0F2FE','color'=>'#0369A1'],
    'Diproses'            => ['bg'=>'#EDE9FE','color'=>'#5B21B6'],
    'Selesai'             => ['bg'=>'#EAF5ED','color'=>'#1A5C2A'],
    'Dibatalkan'          => ['bg'=>'#FDECEA','color'=>'#9B1C1C'],
];

$allStatuses = ['','Belum Dibayar','Menunggu Konfirmasi','Diproses','Selesai','Dibatalkan'];
?>

<div style="padding:28px 0 40px;">
<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h1 style="margin:0;font-size:24px;">Kelola Pesanan</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach ($allStatuses as $s): ?>
      <a href="orders_admin.php<?php echo $s?'?filter='.urlencode($s):'' ?>"
         style="font-size:12px;font-weight:600;padding:6px 14px;border-radius:20px;text-decoration:none;
                background:<?php echo $filter==$s?'#1B2E5E':'#E8ECF5' ?>;
                color:<?php echo $filter==$s?'#fff':'#1B2E5E' ?>;">
        <?php echo $s ?: 'Semua' ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($orders)): ?>
  <div style="text-align:center;padding:60px;color:#9A9AB0;background:#fff;border-radius:14px;border:1px solid #DDE1EC;">
    <i class="fa fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:#DDE1EC;"></i>
    Tidak ada pesanan ditemukan.
  </div>
  <?php else: ?>
  <?php foreach ($orders as $order):
    $sc = $statusColor[$order['status']] ?? ['bg'=>'#F0F2F5','color'=>'#4A4A6A'];
    $stmtI = $con->prepare("SELECT oi.*, items.Name FROM order_items oi INNER JOIN items ON items.Item_ID=oi.item_id WHERE oi.order_id=?");
    $stmtI->execute([$order['order_id']]);
    $oItems = $stmtI->fetchAll();
  ?>
  <div style="background:#fff;border-radius:14px;margin-bottom:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
    <div style="padding:12px 18px;border-bottom:1px solid #DDE1EC;display:flex;justify-content:space-between;align-items:center;background:#F7F8FA;flex-wrap:wrap;gap:8px;">
      <div style="font-size:13px;">
        <strong style="color:#1B2E5E;">#<?php echo $order['order_id'] ?></strong>
        <span style="color:#9A9AB0;margin-left:10px;"><i class="fa fa-user"></i> <?php echo htmlspecialchars($order['FullName']?:$order['Username']) ?></span>
        <span style="color:#9A9AB0;margin-left:10px;"><i class="fa fa-clock-o"></i> <?php echo $order['created_at'] ?></span>
      </div>
      <span style="background:<?php echo $sc['bg'] ?>;color:<?php echo $sc['color'] ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;"><?php echo $order['status'] ?></span>
    </div>
    <div style="padding:14px 18px;">
      <div style="font-size:13px;margin-bottom:10px;">
        <?php foreach ($oItems as $oi): ?>
        <span style="display:inline-block;background:#F0F2F5;color:#1B2E5E;padding:3px 10px;border-radius:6px;margin:2px;font-size:12px;">
          <?php echo htmlspecialchars($oi['Name']) ?> x<?php echo $oi['qty'] ?>
        </span>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <div style="font-size:13px;color:#4A4A6A;">
          <i class="fa fa-credit-card" style="color:#1B2E5E;"></i> <?php echo htmlspecialchars($order['metode_bayar']) ?>
          &nbsp;·&nbsp;
          <strong style="color:#B5272A;">Rp <?php echo number_format($order['total_harga'],0,',','.') ?></strong>
          <?php if ($order['bukti_bayar']): ?>
          &nbsp;·&nbsp; <a href="../admin/uploads/items/<?php echo htmlspecialchars($order['bukti_bayar']) ?>" target="_blank" style="color:#1B2E5E;font-size:12px;"><i class="fa fa-file-image-o"></i> Bukti Bayar</a>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php if ($order['status']=='Menunggu Konfirmasi'): ?>
          <a href="?status=Diproses&id=<?php echo $order['order_id'] ?>" style="background:#1B2E5E;color:#fff;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;" onclick="return confirm('Konfirmasi pesanan ini?')"><i class="fa fa-check"></i> Konfirmasi</a>
          <?php endif; ?>
          <?php if ($order['status']=='Diproses'): ?>
          <a href="?status=Selesai&id=<?php echo $order['order_id'] ?>" style="background:#1A5C2A;color:#fff;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;" onclick="return confirm('Tandai selesai?')"><i class="fa fa-check-circle"></i> Selesai</a>
          <?php endif; ?>
          <?php if (!in_array($order['status'],['Selesai','Dibatalkan'])): ?>
          <a href="?status=Dibatalkan&id=<?php echo $order['order_id'] ?>" style="background:#FDECEA;color:#9B1C1C;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;" onclick="return confirm('Batalkan pesanan ini?')"><i class="fa fa-times"></i> Batalkan</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>