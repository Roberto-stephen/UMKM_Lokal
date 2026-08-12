<?php
ob_start(); session_start();
$pageTitle = 'Invoice';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

$uid   = (int)$_SESSION['uid'];
$group = currentGroup();
$oid   = isset($_GET['order']) && is_numeric($_GET['order']) ? (int)$_GET['order'] : 0;

// Ambil order + data pembeli
$stmt = $con->prepare("SELECT o.*, u.Username AS buyer_user, u.FullName AS buyer_name FROM orders o JOIN users u ON u.UserID=o.user_id WHERE o.order_id=?");
$stmt->execute([$oid]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="container" style="padding:60px 0;"><div class="alert alert-danger">Invoice tidak ditemukan.</div></div>';
    include $tpl.'footer.php'; ob_end_flush(); exit();
}

// Tentukan hak akses
$isBuyerOwner = ((int)$order['user_id'] === $uid);
$isAdmin      = ($group === 1);
// apakah penjual ini punya item di order ini?
$sChk = $con->prepare("SELECT COUNT(*) FROM order_items oi JOIN items i ON i.Item_ID=oi.item_id WHERE oi.order_id=? AND i.Member_ID=?");
$sChk->execute([$oid, $uid]);
$sellerHasItem = ((int)$sChk->fetchColumn()) > 0;

if (!($isBuyerOwner || $isAdmin || $sellerHasItem)) {
    echo '<div class="container" style="padding:60px 0;"><div class="alert alert-danger">Kamu tidak punya akses ke invoice ini.</div></div>';
    include $tpl.'footer.php'; ob_end_flush(); exit();
}

// View penjual = hanya item miliknya; selain itu = seluruh order
$sellerView = ($sellerHasItem && !$isBuyerOwner && !$isAdmin);
if ($sellerView) {
    $iStmt = $con->prepare("SELECT oi.*, i.Name AS item_name FROM order_items oi JOIN items i ON i.Item_ID=oi.item_id WHERE oi.order_id=? AND i.Member_ID=?");
    $iStmt->execute([$oid, $uid]);
} else {
    $iStmt = $con->prepare("SELECT oi.*, i.Name AS item_name FROM order_items oi JOIN items i ON i.Item_ID=oi.item_id WHERE oi.order_id=?");
    $iStmt->execute([$oid]);
}
$items = $iStmt->fetchAll();
$subtotal = 0;
foreach ($items as $it) $subtotal += $it['harga'] * $it['qty'];
// Untuk pembeli/admin, total = total order (semua penjual). Untuk penjual, total = subtotal item-nya.
$grandTotal = $sellerView ? $subtotal : (int)$order['total_harga'];
?>
<style>
@media print {
  .upper-bar, nav.navbar, .site-footer, .no-print, .page-banner { display:none !important; }
  body { background:#fff !important; }
}
</style>

<div class="page-banner no-print"><div class="container">
  <h1>Invoice</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <span>Invoice #<?php echo $order['order_id'] ?></span></div>
</div></div>

<div class="container" style="padding:30px 0;">

  <div class="no-print" style="text-align:right;margin-bottom:14px;">
    <button onclick="window.print()" style="background:#B5272A;color:#fff;border:0;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fa fa-print"></i> Cetak / Simpan PDF</button>
  </div>

  <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #DDE1EC;border-radius:14px;padding:32px;">
    <!-- Header invoice -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;border-bottom:2px solid #1B2E5E;padding-bottom:16px;margin-bottom:20px;">
      <div>
        <div style="font-family:'Playfair Display',serif;font-size:24px;font-weight:900;color:#1B2E5E;">Makan<span style="color:#B5272A;">Lokal</span></div>
        <div style="font-size:12px;color:#9A9AB0;">UMKM Makanan Lokal Tangerang</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:20px;font-weight:800;color:#1B2E5E;">INVOICE</div>
        <div style="font-size:13px;color:#4A4A6A;">No. #<?php echo $order['order_id'] ?></div>
        <div style="font-size:12px;color:#9A9AB0;"><?php echo htmlspecialchars($order['created_at']) ?></div>
        <?php if ($sellerView): ?><div style="font-size:11px;color:#B5272A;font-weight:700;margin-top:2px;">(Rincian produk Anda)</div><?php endif; ?>
      </div>
    </div>

    <!-- Info -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;font-size:13px;">
      <div>
        <div style="font-size:11px;color:#9A9AB0;text-transform:uppercase;font-weight:700;margin-bottom:4px;">Pembeli</div>
        <div style="color:#1B2E5E;font-weight:600;"><?php echo htmlspecialchars($order['buyer_name'] ?: $order['buyer_user']) ?></div>
      </div>
      <div>
        <div style="font-size:11px;color:#9A9AB0;text-transform:uppercase;font-weight:700;margin-bottom:4px;">Pembayaran & Pengambilan</div>
        <div style="color:#4A4A6A;"><?php echo htmlspecialchars($order['metode_bayar']) ?></div>
        <div style="color:#4A4A6A;"><?php echo htmlspecialchars($order['alamat'] ?: '-') ?></div>
        <div style="margin-top:4px;"><span style="background:#EAF5ED;color:#1A5C2A;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?php echo htmlspecialchars($order['status']) ?></span></div>
      </div>
    </div>

    <!-- Items -->
    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;">
      <thead><tr style="background:#F0F2F5;">
        <th style="padding:9px 12px;text-align:left;color:#4A4A6A;">Produk</th>
        <th style="padding:9px 12px;text-align:center;color:#4A4A6A;">Qty</th>
        <th style="padding:9px 12px;text-align:right;color:#4A4A6A;">Harga</th>
        <th style="padding:9px 12px;text-align:right;color:#4A4A6A;">Subtotal</th>
      </tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr style="border-top:1px solid #EEF0F6;">
          <td style="padding:9px 12px;color:#1B2E5E;font-weight:600;"><?php echo htmlspecialchars($it['item_name']) ?></td>
          <td style="padding:9px 12px;text-align:center;"><?php echo $it['qty'] ?></td>
          <td style="padding:9px 12px;text-align:right;">Rp <?php echo number_format($it['harga'],0,',','.') ?></td>
          <td style="padding:9px 12px;text-align:right;font-weight:700;color:#1B2E5E;">Rp <?php echo number_format($it['harga']*$it['qty'],0,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid #DDE1EC;">
          <td colspan="3" style="padding:12px;text-align:right;font-weight:700;color:#1B2E5E;">TOTAL</td>
          <td style="padding:12px;text-align:right;font-weight:800;font-size:16px;color:#B5272A;">Rp <?php echo number_format($grandTotal,0,',','.') ?></td>
        </tr>
      </tfoot>
    </table>

    <?php if ($order['catatan']): ?>
    <div style="font-size:12px;color:#9A9AB0;margin-bottom:8px;"><strong>Catatan:</strong> <?php echo htmlspecialchars($order['catatan']) ?></div>
    <?php endif; ?>

    <div style="text-align:center;font-size:12px;color:#9A9AB0;border-top:1px dashed #DDE1EC;padding-top:14px;margin-top:14px;">
      Terima kasih telah bertransaksi di MakanLokal &mdash; Ambil Sendiri (Self-Pickup).
    </div>
  </div>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>
