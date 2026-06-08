<?php
ob_start(); session_start();
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
$pageTitle = 'Kelola Pesanan';
include 'init.php';

// Update status pesanan
if (isset($_GET['status']) && isset($_GET['id'])) {
    $allowed   = ['Diproses', 'Selesai', 'Dibatalkan', 'Menunggu Konfirmasi'];
    $newStatus = $_GET['status'];
    if (in_array($newStatus, $allowed)) {
        $con->prepare("UPDATE orders SET status=? WHERE order_id=?")
            ->execute([$newStatus, intval($_GET['id'])]);
    }
    header('Location: orders_admin.php'); exit();
}

$filter = $_GET['filter'] ?? '';
$where  = $filter ? "WHERE o.status = ?" : "";
$params = $filter ? [$filter] : [];

$stmt = $con->prepare("
    SELECT o.*, u.Username, u.FullName
    FROM   orders o
    INNER  JOIN users u ON u.UserID = o.user_id
    $where
    ORDER  BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung per status untuk badge
$statusCounts = [];
try {
    $sc = $con->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
    foreach ($sc->fetchAll() as $r) $statusCounts[$r['status']] = $r['cnt'];
} catch (Exception $e) {}

$statusStyle = [
    'Menunggu Konfirmasi' => ['bg' => '#E0F2FE', 'color' => '#0369A1'],
    'Diproses'            => ['bg' => '#EDE9FE', 'color' => '#5B21B6'],
    'Selesai'             => ['bg' => '#EAF5ED', 'color' => '#1A5C2A'],
    'Dibatalkan'          => ['bg' => '#FDECEA', 'color' => '#9B1C1C'],
];

$allStatuses = ['', 'Menunggu Konfirmasi', 'Diproses', 'Selesai', 'Dibatalkan'];
// 'Belum Dibayar' dihapus — tidak dipakai lagi sejak checkout mewajibkan bukti bayar
?>

<div style="padding:0 0 40px;">

  <!-- ---- Filter bar ---- -->
  <div style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <div style="font-size:18px;font-weight:700;color:#1B2E5E;">Kelola Pesanan</div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <?php foreach ($allStatuses as $s):
        $cnt    = $s ? ($statusCounts[$s] ?? 0) : array_sum($statusCounts);
        $active = ($filter === $s);
      ?>
      <a href="orders_admin.php<?= $s ? '?filter='.urlencode($s) : '' ?>"
         style="font-size:12px;font-weight:600;padding:6px 14px;border-radius:20px;text-decoration:none;
                background:<?= $active ? '#1B2E5E' : '#E8ECF5' ?>;
                color:<?= $active ? '#fff' : '#1B2E5E' ?>;">
        <?= $s ?: 'Semua' ?>
        <?php if ($cnt > 0): ?>
          <span style="background:<?= $active ? 'rgba(255,255,255,.25)' : '#fff' ?>;border-radius:10px;padding:0 5px;margin-left:3px;font-size:10px;">
            <?= $cnt ?>
          </span>
        <?php endif; ?>
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
    $sc = $statusStyle[$order['status']] ?? ['bg' => '#F0F2F5', 'color' => '#4A4A6A'];

    // Ambil item-item pesanan
    $stmtI = $con->prepare("
        SELECT oi.*, items.Name, items.picture, items.Price
        FROM   order_items oi
        INNER  JOIN items ON items.Item_ID = oi.item_id
        WHERE  oi.order_id = ?
    ");
    $stmtI->execute([$order['order_id']]);
    $oItems = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    $customerName = $order['FullName'] ?: $order['Username'];
  ?>

  <!-- ============================================================
       KARTU PESANAN
       ============================================================ -->
  <div style="background:#fff;border-radius:14px;margin-bottom:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">

    <!-- Header kartu -->
    <div style="padding:12px 18px;border-bottom:1px solid #DDE1EC;display:flex;justify-content:space-between;align-items:center;background:#F7F8FA;flex-wrap:wrap;gap:8px;">
      <div style="font-size:13px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <strong style="color:#1B2E5E;font-size:14px;">#<?= $order['order_id'] ?></strong>
        <span style="color:#9A9AB0;"><i class="fa fa-user"></i> <?= htmlspecialchars($customerName) ?></span>
        <span style="color:#9A9AB0;"><i class="fa fa-clock-o"></i> <?= $order['created_at'] ?></span>
        <span style="color:#B5272A;font-weight:700;">Rp <?= number_format($order['total_harga'],0,',','.') ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;">
          <?= $order['status'] ?>
        </span>
        <!-- Tombol toggle detail -->
        <button onclick="toggleDetail(<?= $order['order_id'] ?>)"
                id="btn-<?= $order['order_id'] ?>"
                style="background:#1B2E5E;color:#fff;border:none;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
          <i class="fa fa-eye"></i> Rincian
        </button>
      </div>
    </div>

    <!-- Ringkasan item (selalu terlihat) -->
    <div style="padding:10px 18px;border-bottom:1px solid #F0F2F5;">
      <?php foreach ($oItems as $oi): ?>
      <span style="display:inline-flex;align-items:center;gap:5px;background:#F0F2F5;color:#1B2E5E;padding:3px 10px;border-radius:6px;margin:2px;font-size:12px;">
        <?php if (!empty($oi['picture'])): ?>
          <img src="../admin/uploads/items/<?= htmlspecialchars($oi['picture']) ?>" style="width:18px;height:18px;object-fit:cover;border-radius:3px;">
        <?php endif; ?>
        <?= htmlspecialchars($oi['Name']) ?> ×<?= $oi['qty'] ?>
      </span>
      <?php endforeach; ?>
    </div>

    <!-- ACTION BUTTONS (selalu terlihat) -->
    <div style="padding:10px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
      <div style="font-size:12px;color:#4A4A6A;">
        <i class="fa fa-credit-card" style="color:#1B2E5E;margin-right:4px;"></i>
        <?= htmlspecialchars($order['metode_bayar']) ?>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php if ($order['status'] === 'Menunggu Konfirmasi'): ?>
          <a href="?status=Diproses&id=<?= $order['order_id'] ?>"
             onclick="return confirm('Konfirmasi dan proses pesanan #<?= $order['order_id'] ?>?')"
             style="background:#1B2E5E;color:#fff;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;">
            <i class="fa fa-check"></i> Konfirmasi
          </a>
        <?php endif; ?>
        <?php if ($order['status'] === 'Diproses'): ?>
          <a href="?status=Selesai&id=<?= $order['order_id'] ?>"
             onclick="return confirm('Tandai pesanan #<?= $order['order_id'] ?> sebagai selesai?')"
             style="background:#1A5C2A;color:#fff;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;">
            <i class="fa fa-check-circle"></i> Selesai
          </a>
        <?php endif; ?>
        <?php if (!in_array($order['status'], ['Selesai', 'Dibatalkan'])): ?>
          <a href="?status=Dibatalkan&id=<?= $order['order_id'] ?>"
             onclick="return confirm('Batalkan pesanan #<?= $order['order_id'] ?>? Tindakan ini tidak dapat dibatalkan.')"
             style="background:#FDECEA;color:#9B1C1C;padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;">
            <i class="fa fa-times"></i> Batalkan
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============================================================
         PANEL RINCIAN (tersembunyi, tampil saat klik Rincian)
         ============================================================ -->
    <div id="detail-<?= $order['order_id'] ?>" style="display:none;border-top:2px solid #DDE1EC;background:#F7F9FF;">

      <div style="padding:20px 18px;">
        <div class="row">

          <!-- Kolom kiri: Detail item pesanan -->
          <div class="col-md-7">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9A9AB0;margin-bottom:12px;">
              Detail Item Pesanan
            </div>

            <?php foreach ($oItems as $oi):
              $subtotal = $oi['harga'] * $oi['qty'];
            ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px;background:#fff;border-radius:10px;margin-bottom:8px;border:1px solid #DDE1EC;">
              <img src="<?= empty($oi['picture']) ? '../admin/uploads/default.png' : '../admin/uploads/items/'.htmlspecialchars($oi['picture']) ?>"
                   style="width:52px;height:52px;object-fit:cover;border-radius:8px;background:#EEF0F6;flex-shrink:0;">
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;color:#1B2E5E;"><?= htmlspecialchars($oi['Name']) ?></div>
                <div style="font-size:12px;color:#9A9AB0;">
                  <?= $oi['qty'] ?> × Rp <?= number_format($oi['harga'],0,',','.') ?>
                </div>
              </div>
              <div style="font-size:13px;font-weight:700;color:#1B2E5E;white-space:nowrap;">
                Rp <?= number_format($subtotal,0,',','.') ?>
              </div>
            </div>
            <?php endforeach; ?>

            <!-- Subtotal & Total -->
            <div style="background:#fff;border-radius:10px;padding:12px 14px;border:1px solid #DDE1EC;margin-top:10px;">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:#4A4A6A;margin-bottom:4px;">
                <span>Subtotal</span>
                <span>Rp <?= number_format($order['total_harga'],0,',','.') ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;color:#B5272A;border-top:1px solid #EEF0F6;padding-top:8px;margin-top:6px;">
                <span>Total Bayar</span>
                <span>Rp <?= number_format($order['total_harga'],0,',','.') ?></span>
              </div>
            </div>
          </div>

          <!-- Kolom kanan: Info pelanggan & pembayaran -->
          <div class="col-md-5">

            <!-- Info Pelanggan -->
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9A9AB0;margin-bottom:12px;">
              Info Pelanggan
            </div>
            <div style="background:#fff;border-radius:10px;padding:14px;border:1px solid #DDE1EC;margin-bottom:14px;">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:#1B2E5E;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0;">
                  <?= strtoupper(substr($customerName, 0, 1)) ?>
                </div>
                <div>
                  <div style="font-size:13px;font-weight:600;color:#1B2E5E;"><?= htmlspecialchars($customerName) ?></div>
                  <div style="font-size:11px;color:#9A9AB0;">@<?= htmlspecialchars($order['Username']) ?></div>
                </div>
              </div>

              <?php if (!empty($order['alamat'])): ?>
              <div style="margin-bottom:8px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9A9AB0;margin-bottom:2px;">Alamat Pengiriman</div>
                <div style="font-size:13px;color:#4A4A6A;line-height:1.5;background:#F7F8FA;padding:8px 10px;border-radius:8px;">
                  <i class="fa fa-map-marker" style="color:#B5272A;margin-right:5px;"></i>
                  <?= nl2br(htmlspecialchars($order['alamat'])) ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!empty($order['catatan'])): ?>
              <div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#9A9AB0;margin-bottom:2px;">Catatan</div>
                <div style="font-size:13px;color:#4A4A6A;background:#FFFBEA;padding:8px 10px;border-radius:8px;border-left:3px solid #F4A261;">
                  <i class="fa fa-sticky-note-o" style="color:#F4A261;margin-right:5px;"></i>
                  <?= htmlspecialchars($order['catatan']) ?>
                </div>
              </div>
              <?php else: ?>
              <div style="font-size:12px;color:#C0C4D6;font-style:italic;">Tidak ada catatan.</div>
              <?php endif; ?>
            </div>

            <!-- Info Pembayaran -->
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9A9AB0;margin-bottom:12px;">
              Info Pembayaran
            </div>
            <div style="background:#fff;border-radius:10px;padding:14px;border:1px solid #DDE1EC;">
              <div style="font-size:13px;color:#1B2E5E;margin-bottom:10px;">
                <i class="fa fa-credit-card" style="margin-right:6px;color:#4A4A6A;"></i>
                <strong><?= htmlspecialchars($order['metode_bayar']) ?></strong>
              </div>

              <?php if (!empty($order['bukti_bayar'])): ?>
              <!-- Preview Bukti Bayar -->
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9A9AB0;margin-bottom:6px;">Bukti Pembayaran</div>
              <a href="../admin/uploads/items/<?= htmlspecialchars($order['bukti_bayar']) ?>" target="_blank">
                <img src="../admin/uploads/items/<?= htmlspecialchars($order['bukti_bayar']) ?>"
                     style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #DDE1EC;"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div style="display:none;background:#F0F2F5;border-radius:8px;padding:12px;text-align:center;font-size:12px;color:#4A4A6A;border:1px solid #DDE1EC;">
                  <i class="fa fa-file" style="font-size:24px;color:#9A9AB0;margin-bottom:6px;display:block;"></i>
                  Lihat Bukti Bayar (PDF/File)
                </div>
              </a>
              <a href="../admin/uploads/items/<?= htmlspecialchars($order['bukti_bayar']) ?>" target="_blank"
                 style="display:block;text-align:center;font-size:12px;color:#1B2E5E;margin-top:6px;font-weight:600;">
                <i class="fa fa-external-link"></i> Buka di Tab Baru
              </a>

              <?php else: ?>
              <div style="background:#FEF3C7;border-radius:8px;padding:10px 12px;font-size:12px;color:#92400E;border:1px solid #FDE68A;">
                <i class="fa fa-exclamation-triangle"></i>
                Bukti bayar belum diupload.
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- END PANEL RINCIAN -->

  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- JavaScript toggle detail panel -->
<script>
function toggleDetail(orderId) {
    var panel = document.getElementById('detail-' + orderId);
    var btn   = document.getElementById('btn-' + orderId);
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        btn.innerHTML = '<i class="fa fa-eye-slash"></i> Tutup';
        btn.style.background = '#4A4A6A';
    } else {
        panel.style.display = 'none';
        btn.innerHTML = '<i class="fa fa-eye"></i> Rincian';
        btn.style.background = '#1B2E5E';
    }
}
</script>

<?php include $tpl.'footer.php'; ob_end_flush(); ?>