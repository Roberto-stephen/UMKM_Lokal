<?php
ob_start(); session_start();
$pageTitle = 'Pesanan Masuk';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

// Aksi konfirmasi / selesai
if (isset($_GET['action']) && isset($_GET['id'])) {
    $oid = intval($_GET['id']);
    $action = $_GET['action'];

    // Pastikan pesanan ini mengandung produk milik penjual ini
    $chk = $con->prepare("SELECT DISTINCT o.order_id FROM orders o INNER JOIN order_items oi ON oi.order_id=o.order_id INNER JOIN items i ON i.Item_ID=oi.item_id WHERE o.order_id=? AND i.Member_ID=?");
    $chk->execute([$oid, $_SESSION['uid']]);

    if ($chk->rowCount() > 0) {
        if ($action == 'konfirmasi') {
            $con->prepare("UPDATE orders SET status='Diproses' WHERE order_id=? AND status='Menunggu Konfirmasi'")->execute([$oid]);
        } elseif ($action == 'selesai') {
            $con->prepare("UPDATE orders SET status='Selesai' WHERE order_id=? AND status IN ('Diproses')")->execute([$oid]);
        } elseif ($action == 'tolak') {
            // Kembalikan stok
            $oItems = $con->prepare("SELECT * FROM order_items WHERE order_id=?");
            $oItems->execute([$oid]);
            foreach ($oItems->fetchAll() as $oi) {
                $con->prepare("UPDATE items SET stok=stok+? WHERE Item_ID=?")->execute([$oi['qty'], $oi['item_id']]);
            }
            $con->prepare("UPDATE orders SET status='Dibatalkan' WHERE order_id=?")->execute([$oid]);
        }
    }
    header('Location: seller_orders.php?msg=1'); exit();
}

// Filter status
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$whereExtra = $filter ? "AND o.status=?" : "";
$params = [$_SESSION['uid']];
if ($filter) $params[] = $filter;

$stmt = $con->prepare("
    SELECT DISTINCT o.*, u.Username, u.FullName
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id=o.order_id
    INNER JOIN items i ON i.Item_ID=oi.item_id
    INNER JOIN users u ON u.UserID=o.user_id
    WHERE i.Member_ID=? $whereExtra
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusColor = [
    'Belum Dibayar'       => ['bg'=>'#FEF3C7','color'=>'#92400E','icon'=>'fa-clock-o'],
    'Menunggu Konfirmasi' => ['bg'=>'#E0F2FE','color'=>'#0369A1','icon'=>'fa-hourglass-half'],
    'Diproses'            => ['bg'=>'#EDE9FE','color'=>'#5B21B6','icon'=>'fa-cog'],
    'Selesai'             => ['bg'=>'#EAF5ED','color'=>'#1A5C2A','icon'=>'fa-check-circle'],
    'Dibatalkan'          => ['bg'=>'#FDECEA','color'=>'#9B1C1C','icon'=>'fa-times-circle'],
];

// Hitung per status untuk tab counter
$stmtCount = $con->prepare("SELECT o.status, COUNT(DISTINCT o.order_id) as jml FROM orders o INNER JOIN order_items oi ON oi.order_id=o.order_id INNER JOIN items i ON i.Item_ID=oi.item_id WHERE i.Member_ID=? GROUP BY o.status");
$stmtCount->execute([$_SESSION['uid']]);
$counts = [];
foreach ($stmtCount->fetchAll() as $c) $counts[$c['status']] = $c['jml'];
$totalAll = array_sum($counts);
?>

<div class="page-banner"><div class="container">
  <h1>Pesanan Masuk</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="myItems.php">Produk Saya</a> &rsaquo; <span>Pesanan Masuk</span></div>
</div></div>

<div class="container" style="padding:36px 0;">
<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success"><i class="fa fa-check-circle"></i> Status pesanan berhasil diperbarui.</div>
<?php endif; ?>

<!-- FILTER TABS -->
<div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
  <h2 style="margin:0;font-size:20px;color:#1B2E5E;">Total <?php echo count($orders) ?> pesanan</h2>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <a href="seller_orders.php" style="font-size:12px;font-weight:600;padding:6px 14px;border-radius:20px;text-decoration:none;background:<?php echo !$filter?'#1B2E5E':'#E8ECF5' ?>;color:<?php echo !$filter?'#fff':'#1B2E5E' ?>;">
      Semua <span style="opacity:.7;">(<?php echo $totalAll ?>)</span>
    </a>
    <?php foreach ($statusColor as $s=>$sc): ?>
    <a href="seller_orders.php?filter=<?php echo urlencode($s) ?>" style="font-size:12px;font-weight:600;padding:6px 14px;border-radius:20px;text-decoration:none;background:<?php echo $filter==$s?'#1B2E5E':'#E8ECF5' ?>;color:<?php echo $filter==$s?'#fff':'#1B2E5E' ?>;">
      <?php echo $s ?> <?php if (isset($counts[$s])): ?><span style="opacity:.7;">(<?php echo $counts[$s] ?>)</span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- INFO COD -->
<div style="background:#E8ECF5;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1B2E5E;">
  <i class="fa fa-info-circle" style="color:#B5272A;"></i>
  <strong>COD / Bayar di Tempat:</strong> Pesanan COD langsung berstatus <em>Diproses</em>. Klik <strong>Selesai</strong> setelah pembeli membayar dan menerima barang.
</div>

<?php if (empty($orders)): ?>
<div class="empty-state"><i class="fa fa-inbox"></i><p>Belum ada pesanan masuk.</p></div>
<?php else: ?>
<?php foreach ($orders as $order):
  $sc = $statusColor[$order['status']] ?? ['bg'=>'#F0F2F5','color'=>'#4A4A6A','icon'=>'fa-question'];
  $stmtI = $con->prepare("SELECT oi.*, items.Name, items.picture FROM order_items oi INNER JOIN items ON items.Item_ID=oi.item_id WHERE oi.order_id=? AND items.Member_ID=?");
  $stmtI->execute([$order['order_id'], $_SESSION['uid']]);
  $oItems = $stmtI->fetchAll();
  $isCOD = str_contains($order['metode_bayar'], 'COD');
?>
<div style="background:#fff;border-radius:14px;margin-bottom:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
  <!-- HEADER PESANAN -->
  <div style="padding:12px 18px;border-bottom:1px solid #DDE1EC;display:flex;justify-content:space-between;align-items:center;background:#F7F8FA;flex-wrap:wrap;gap:8px;">
    <div style="font-size:13px;">
      <strong style="color:#1B2E5E;">Order #<?php echo $order['order_id'] ?></strong>
      <span style="color:#9A9AB0;margin-left:10px;"><i class="fa fa-user"></i> <?php echo htmlspecialchars($order['FullName']?:$order['Username']) ?></span>
      <span style="color:#9A9AB0;margin-left:10px;"><i class="fa fa-clock-o"></i> <?php echo $order['created_at'] ?></span>
      <?php if ($isCOD): ?>
      <span style="background:#EDE9FE;color:#5B21B6;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;margin-left:6px;">COD</span>
      <?php endif; ?>
    </div>
    <span style="background:<?php echo $sc['bg'] ?>;color:<?php echo $sc['color'] ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;">
      <i class="fa <?php echo $sc['icon'] ?>"></i> <?php echo $order['status'] ?>
    </span>
  </div>

  <!-- DETAIL PESANAN -->
  <div style="padding:14px 18px;">
    <!-- Item-item -->
    <div style="margin-bottom:12px;">
      <?php foreach ($oItems as $oi): ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
        <img src="<?php echo empty($oi['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($oi['picture']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;background:#EEF0F6;">
        <div>
          <div style="font-size:13px;font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($oi['Name']) ?></div>
          <div style="font-size:12px;color:#9A9AB0;">x<?php echo $oi['qty'] ?> &times; Rp <?php echo number_format($oi['harga'],0,',','.') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Info tambahan -->
    <div style="background:#F0F2F5;border-radius:8px;padding:10px 14px;font-size:13px;color:#4A4A6A;margin-bottom:12px;">
      <div><i class="fa fa-map-marker" style="color:#1B2E5E;width:16px;"></i> <?php echo htmlspecialchars($order['alamat']?:'Tidak ada alamat') ?></div>
      <div style="margin-top:4px;"><i class="fa fa-credit-card" style="color:#1B2E5E;width:16px;"></i> <?php echo htmlspecialchars($order['metode_bayar']) ?></div>
      <?php if ($order['catatan']): ?>
      <div style="margin-top:4px;"><i class="fa fa-comment" style="color:#1B2E5E;width:16px;"></i> <?php echo htmlspecialchars($order['catatan']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($order['bukti_bayar']): ?>
    <div style="margin-bottom:10px;">
      <a href="admin/uploads/items/<?php echo htmlspecialchars($order['bukti_bayar']) ?>" target="_blank" style="font-size:13px;color:#1B2E5E;font-weight:600;background:#E8ECF5;padding:6px 14px;border-radius:8px;text-decoration:none;">
        <i class="fa fa-file-image-o"></i> Lihat Bukti Bayar
      </a>
    </div>
    <?php endif; ?>

    <!-- TOTAL + AKSI -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;padding-top:10px;border-top:1px solid #DDE1EC;">
      <div style="font-size:16px;font-weight:700;color:#B5272A;">Total: Rp <?php echo number_format($order['total_harga'],0,',','.') ?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">

        <!-- Konfirmasi (untuk transfer yang sudah upload bukti) -->
        <?php if ($order['status']=='Menunggu Konfirmasi'): ?>
        <a href="seller_orders.php?action=konfirmasi&id=<?php echo $order['order_id'] ?>" onclick="return confirm('Konfirmasi pembayaran dan proses pesanan ini?')"
           style="background:#1B2E5E;color:#fff;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;">
          <i class="fa fa-check"></i> Konfirmasi Bayar
        </a>
        <?php endif; ?>

        <!-- Selesai (untuk COD yang sudah diproses ATAU transfer yang sudah dikonfirmasi) -->
        <?php if ($order['status']=='Diproses'): ?>
        <a href="seller_orders.php?action=selesai&id=<?php echo $order['order_id'] ?>" onclick="return confirm('<?php echo $isCOD?'Pembeli sudah bayar COD? Tandai pesanan selesai?':'Tandai pesanan selesai?' ?>')"
           style="background:#1A5C2A;color:#fff;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;">
          <i class="fa fa-check-circle"></i> <?php echo $isCOD ? 'COD Diterima — Selesai' : 'Tandai Selesai' ?>
        </a>
        <?php endif; ?>

        <!-- Tolak (untuk yang belum diproses) -->
        <?php if (in_array($order['status'],['Belum Dibayar','Menunggu Konfirmasi'])): ?>
        <a href="seller_orders.php?action=tolak&id=<?php echo $order['order_id'] ?>" onclick="return confirm('Tolak & batalkan pesanan ini? Stok akan dikembalikan.')"
           style="background:#FDECEA;color:#9B1C1C;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;">
          <i class="fa fa-times"></i> Tolak
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