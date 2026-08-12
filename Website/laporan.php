<?php
ob_start(); session_start();
$pageTitle = 'Laporan Penjualan';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';
requireSeller(); // khusus Penjual

$uid = (int)$_SESSION['uid'];

// Filter tanggal (opsional)
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$cond = "i.Member_ID = ? AND o.status = 'Selesai'";
$params = [$uid];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $cond .= " AND DATE(o.created_at) >= ?"; $params[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $cond .= " AND DATE(o.created_at) <= ?"; $params[] = $to; }

$stmt = $con->prepare("
    SELECT o.order_id, o.created_at, u.Username, u.FullName,
           oi.qty, oi.harga, i.Name AS item_name
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN items  i ON i.Item_ID  = oi.item_id
    JOIN users  u ON u.UserID   = o.user_id
    WHERE $cond
    ORDER BY o.created_at DESC, o.order_id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalRevenue = 0; $totalQty = 0; $orderSet = [];
foreach ($rows as $r) {
    $totalRevenue += $r['harga'] * $r['qty'];
    $totalQty     += (int)$r['qty'];
    $orderSet[$r['order_id']] = true;
}
$totalOrders = count($orderSet);
?>
<style>
@media print {
  .upper-bar, nav.navbar, .site-footer, .no-print, .page-banner { display:none !important; }
  body { background:#fff !important; }
  .container { width:100% !important; }
}
</style>

<div class="page-banner"><div class="container">
  <h1>Laporan Penjualan</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="myItems.php">Produk Saya</a> &rsaquo; <span>Laporan</span></div>
</div></div>

<div class="container" style="padding:36px 0;">

  <!-- Filter + Print -->
  <div class="no-print" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <div>
        <label style="font-size:11px;font-weight:700;color:#4A4A6A;text-transform:uppercase;display:block;margin-bottom:4px;">Dari Tanggal</label>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from) ?>" class="form-control" style="width:auto;">
      </div>
      <div>
        <label style="font-size:11px;font-weight:700;color:#4A4A6A;text-transform:uppercase;display:block;margin-bottom:4px;">Sampai Tanggal</label>
        <input type="date" name="to" value="<?php echo htmlspecialchars($to) ?>" class="form-control" style="width:auto;">
      </div>
      <button type="submit" style="background:#1B2E5E;color:#fff;border:0;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fa fa-filter"></i> Terapkan</button>
      <?php if ($from || $to): ?><a href="laporan.php" style="font-size:13px;color:#9A9AB0;padding:9px 4px;">Reset</a><?php endif; ?>
    </form>
    <button onclick="window.print()" style="background:#B5272A;color:#fff;border:0;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fa fa-print"></i> Cetak / PDF</button>
  </div>

  <!-- Ringkasan -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
    <div style="background:#fff;border:1px solid #DDE1EC;border-radius:12px;padding:18px;">
      <div style="font-size:12px;color:#9A9AB0;">Total Pendapatan (Selesai)</div>
      <div style="font-size:24px;font-weight:800;color:#1A5C2A;">Rp <?php echo number_format($totalRevenue,0,',','.') ?></div>
    </div>
    <div style="background:#fff;border:1px solid #DDE1EC;border-radius:12px;padding:18px;">
      <div style="font-size:12px;color:#9A9AB0;">Jumlah Transaksi</div>
      <div style="font-size:24px;font-weight:800;color:#1B2E5E;"><?php echo $totalOrders ?></div>
    </div>
    <div style="background:#fff;border:1px solid #DDE1EC;border-radius:12px;padding:18px;">
      <div style="font-size:12px;color:#9A9AB0;">Item Terjual</div>
      <div style="font-size:24px;font-weight:800;color:#1B2E5E;"><?php echo $totalQty ?></div>
    </div>
  </div>

  <!-- Tabel -->
  <?php if (empty($rows)): ?>
    <div class="empty-state"><i class="fa fa-bar-chart"></i><p>Belum ada transaksi selesai pada rentang ini.</p></div>
  <?php else: ?>
  <div style="background:#fff;border-radius:14px;border:1px solid #DDE1EC;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:#F0F2F5;">
        <th style="padding:11px 14px;text-align:left;color:#4A4A6A;">Tanggal</th>
        <th style="padding:11px 14px;text-align:left;color:#4A4A6A;">Order</th>
        <th style="padding:11px 14px;text-align:left;color:#4A4A6A;">Pembeli</th>
        <th style="padding:11px 14px;text-align:left;color:#4A4A6A;">Produk</th>
        <th style="padding:11px 14px;text-align:center;color:#4A4A6A;">Qty</th>
        <th style="padding:11px 14px;text-align:right;color:#4A4A6A;">Harga</th>
        <th style="padding:11px 14px;text-align:right;color:#4A4A6A;">Subtotal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr style="border-top:1px solid #EEF0F6;">
          <td style="padding:9px 14px;color:#9A9AB0;white-space:nowrap;"><?php echo htmlspecialchars($r['created_at']) ?></td>
          <td style="padding:9px 14px;">#<?php echo $r['order_id'] ?> <a href="invoice.php?order=<?php echo $r['order_id'] ?>" class="no-print" style="font-size:11px;color:#B5272A;">invoice</a></td>
          <td style="padding:9px 14px;color:#4A4A6A;"><?php echo htmlspecialchars($r['FullName'] ?: $r['Username']) ?></td>
          <td style="padding:9px 14px;color:#1B2E5E;font-weight:600;"><?php echo htmlspecialchars($r['item_name']) ?></td>
          <td style="padding:9px 14px;text-align:center;"><?php echo $r['qty'] ?></td>
          <td style="padding:9px 14px;text-align:right;">Rp <?php echo number_format($r['harga'],0,',','.') ?></td>
          <td style="padding:9px 14px;text-align:right;font-weight:700;color:#1B2E5E;">Rp <?php echo number_format($r['harga']*$r['qty'],0,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="border-top:2px solid #DDE1EC;background:#F7F8FA;">
          <td colspan="6" style="padding:12px 14px;text-align:right;font-weight:700;color:#1B2E5E;">TOTAL PENDAPATAN</td>
          <td style="padding:12px 14px;text-align:right;font-weight:800;color:#1A5C2A;">Rp <?php echo number_format($totalRevenue,0,',','.') ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>

</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>
