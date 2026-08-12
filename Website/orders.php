<?php
ob_start(); session_start();
$pageTitle = 'Riwayat Pesanan';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

$uid = (int)($_SESSION['uid'] ?? 0);

// =========================================================
// HANDLER: kirim ulasan langsung dari halaman ini (modal).
// Aturan kuota = "satu ulasan per pembelian":
//   boleh ulas jika (jumlah order 'Selesai' yg memuat item) > (jumlah ulasan user utk item).
// Beli lagi (order Selesai baru) -> dapat jatah ulas lagi.
// Gate dijalankan DI SERVER, tidak sekadar mengandalkan tombol.
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $uid > 0) {
    $riid     = intval($_POST['item_id'] ?? 0);
    $rating   = max(1, min(5, intval($_POST['rating'] ?? 5)));
    $rcomment = htmlspecialchars(strip_tags($_POST['comment'] ?? ''));

    // hitung kuota
    $pc = $con->prepare("SELECT COUNT(DISTINCT o.order_id) c
                         FROM order_items oi JOIN orders o ON o.order_id=oi.order_id
                         WHERE oi.item_id=? AND o.user_id=? AND o.status='Selesai'");
    $pc->execute([$riid, $uid]); $pcC = (int)($pc->fetch()['c'] ?? 0);

    $rc = $con->prepare("SELECT COUNT(*) c FROM comments WHERE item_id=? AND user_id=?");
    $rc->execute([$riid, $uid]); $rcC = (int)($rc->fetch()['c'] ?? 0);

    $nm = $con->prepare("SELECT Name FROM items WHERE Item_ID=?");
    $nm->execute([$riid]); $nmS = ($nm->fetch()['Name'] ?? 'produk');

    if ($rcomment === '') {
        $_SESSION['review_flash'] = ['type'=>'danger','msg'=>'Ulasan tidak boleh kosong.'];
    } elseif ($pcC <= 0) {
        $_SESSION['review_flash'] = ['type'=>'danger','msg'=>'Hanya pembeli (order Selesai) yang bisa memberi ulasan.'];
    } elseif ($pcC > $rcC) {
        $ins = $con->prepare("INSERT INTO comments(comment,rating,status,comment_date,item_id,user_id) VALUES(?,?,1,NOW(),?,?)");
        $ins->execute([$rcomment, $rating, $riid, $uid]);
        $_SESSION['review_flash'] = ['type'=>'success','msg'=>'Ulasan untuk "'.$nmS.'" berhasil dikirim. Terima kasih!'];
    } else {
        $_SESSION['review_flash'] = ['type'=>'danger','msg'=>'Kamu sudah mengulas semua pembelianmu untuk "'.$nmS.'". Beli lagi untuk memberi ulasan baru.'];
    }
    header('Location: orders.php'); exit(); // PRG: cegah submit ganda saat refresh
}

$stmt = $con->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$uid]);
$orders = $stmt->fetchAll();

// Peta kuota per item (untuk user ini)
$purchaseCount = []; // item_id => jumlah order 'Selesai'
$reviewCount   = []; // item_id => jumlah ulasan
$pcAll = $con->prepare("SELECT oi.item_id iid, COUNT(DISTINCT o.order_id) c
                        FROM order_items oi JOIN orders o ON o.order_id=oi.order_id
                        WHERE o.user_id=? AND o.status='Selesai' GROUP BY oi.item_id");
$pcAll->execute([$uid]);
foreach ($pcAll->fetchAll() as $r) $purchaseCount[(int)$r['iid']] = (int)$r['c'];
$rcAll = $con->prepare("SELECT item_id iid, COUNT(*) c FROM comments WHERE user_id=? GROUP BY item_id");
$rcAll->execute([$uid]);
foreach ($rcAll->fetchAll() as $r) $reviewCount[(int)$r['iid']] = (int)$r['c'];
$reviewSlotsShown = []; // biar jumlah tombol tidak melebihi sisa kuota

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
<?php if (!empty($_SESSION['review_flash'])): ?>
  <div class="alert alert-<?php echo $_SESSION['review_flash']['type'] ?>">
    <i class="fa fa-<?php echo $_SESSION['review_flash']['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?php echo htmlspecialchars($_SESSION['review_flash']['msg']) ?>
  </div>
  <?php unset($_SESSION['review_flash']); ?>
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
  $isSelesai = ($order['status'] === 'Selesai');
?>
<div style="background:#fff;border-radius:14px;margin-bottom:16px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
  <div style="padding:16px 20px;border-bottom:1px solid #DDE1EC;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div>
      <span style="font-size:13px;color:#9A9AB0;">Order #<?php echo $order['order_id'] ?></span>
      <span style="font-size:13px;color:#9A9AB0;margin-left:12px;"><i class="fa fa-clock-o"></i> <?php echo $order['created_at'] ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
      <a href="invoice.php?order=<?php echo $order['order_id'] ?>" style="font-size:12px;font-weight:600;color:#1B2E5E;background:#E8ECF5;padding:5px 12px;border-radius:8px;text-decoration:none;"><i class="fa fa-file-text-o"></i> Invoice</a>
      <span style="background:<?php echo $sc['bg'] ?>;color:<?php echo $sc['color'] ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;">
        <?php echo $order['status'] ?>
      </span>
    </div>
  </div>
  <div style="padding:16px 20px;">
    <?php foreach ($oItems as $oi):
      $iid = (int)$oi['item_id'];
      // Tentukan status ulasan item ini (khusus order Selesai)
      $remaining  = ($purchaseCount[$iid] ?? 0) - ($reviewCount[$iid] ?? 0);
      $shown      = $reviewSlotsShown[$iid] ?? 0;
      $showBtn    = $isSelesai && (($remaining - $shown) > 0);
      if ($showBtn) $reviewSlotsShown[$iid] = $shown + 1;
    ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
      <img src="<?php echo empty($oi['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($oi['picture']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#EEF0F6;">
      <div style="flex:1;">
        <div style="font-size:14px;font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($oi['Name']) ?></div>
        <div style="font-size:12px;color:#9A9AB0;">x<?php echo $oi['qty'] ?> &times; Rp <?php echo number_format($oi['harga'],0,',','.') ?></div>
      </div>

      <?php if ($isSelesai): ?>
        <?php if ($showBtn): ?>
          <button type="button" class="btn-review"
                  data-itemid="<?php echo $iid ?>"
                  data-itemname="<?php echo htmlspecialchars($oi['Name'], ENT_QUOTES) ?>"
                  onclick="openReview(this)">
            <i class="fa fa-star"></i> Beri Ulasan
          </button>
        <?php else: ?>
          <span class="review-done"><i class="fa fa-check"></i> Sudah diulas</span>
        <?php endif; ?>
      <?php endif; ?>

      <div style="font-size:13px;font-weight:700;color:#1B2E5E;min-width:90px;text-align:right;">Rp <?php echo number_format($oi['harga']*$oi['qty'],0,',','.') ?></div>
    </div>
    <?php endforeach; ?>
    <div style="border-top:1px solid #DDE1EC;margin-top:12px;padding-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
      <div style="font-size:13px;color:#4A4A6A;">
        <i class="fa fa-credit-card" style="color:#1B2E5E;"></i> <?php echo htmlspecialchars($order['metode_bayar']) ?>
        <?php if ($order['alamat']): ?>
        &nbsp;&middot;&nbsp; <i class="fa fa-shopping-bag" style="color:#1B2E5E;"></i> <?php echo htmlspecialchars(mb_strlen($order['alamat'])>40 ? mb_substr($order['alamat'],0,40).'...' : $order['alamat']) ?>
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

<!-- ========================================================
     MODAL BERI ULASAN (dipakai ulang untuk semua item)
     ======================================================== -->
<div id="reviewModal" class="review-modal-overlay" onclick="if(event.target===this)closeReview()">
  <div class="review-modal">
    <div class="review-modal-head">
      <h3><i class="fa fa-star" style="color:#F4A261;"></i> Beri Ulasan</h3>
      <button type="button" class="review-close" onclick="closeReview()">&times;</button>
    </div>
    <div class="review-modal-sub">Produk: <strong id="reviewItemName">-</strong></div>
    <form method="POST" action="orders.php">
      <input type="hidden" name="item_id" id="reviewItemId" value="">
      <div style="margin-bottom:14px;">
        <label class="review-label">Rating *</label>
        <div class="star-rating" role="radiogroup" aria-label="Pilih rating">
          <?php for ($i=5;$i>=1;$i--): ?>
            <input type="radio" name="rating" id="mstar<?php echo $i ?>" value="<?php echo $i ?>" <?php echo $i===5?'checked':'' ?>>
            <label for="mstar<?php echo $i ?>" title="<?php echo $i ?> bintang">&#9733;</label>
          <?php endfor; ?>
        </div>
      </div>
      <div style="margin-bottom:14px;">
        <label class="review-label">Ulasan *</label>
        <textarea name="comment" required placeholder="Bagikan pengalamanmu tentang produk ini..."
                  style="width:100%;border:1.5px solid #DDE1EC;border-radius:10px;padding:12px 14px;font-size:14px;font-family:inherit;resize:vertical;min-height:90px;background:#F7F8FB;"></textarea>
      </div>
      <button type="submit" name="submit_review" class="btn-submit" style="width:100%;">
        <i class="fa fa-paper-plane"></i> Kirim Ulasan
      </button>
    </form>
  </div>
</div>

<style>
  .btn-review {
    background:#B5272A; color:#fff; border:0; border-radius:8px; padding:7px 14px;
    font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; font-family:inherit;
    transition:background .15s;
  }
  .btn-review:hover { background:#8f1e21; }
  .review-done {
    font-size:12px; font-weight:700; color:#1A5C2A; background:#EAF5ED;
    border:1px solid #A3D4AE; padding:6px 12px; border-radius:8px; white-space:nowrap;
  }
  /* modal */
  .review-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(17,30,64,.55);
    z-index:9999; align-items:center; justify-content:center; padding:20px;
  }
  .review-modal-overlay.open { display:flex; }
  .review-modal {
    background:#fff; border-radius:16px; width:100%; max-width:440px; padding:24px;
    box-shadow:0 20px 60px rgba(17,30,64,.3); animation:reviewPop .18s ease;
  }
  @keyframes reviewPop { from{opacity:0;transform:translateY(10px) scale(.98);} to{opacity:1;transform:none;} }
  .review-modal-head { display:flex; justify-content:space-between; align-items:center; }
  .review-modal-head h3 { margin:0; font-size:18px; color:#1B2E5E; }
  .review-close { background:none; border:0; font-size:26px; line-height:1; color:#9A9AB0; cursor:pointer; }
  .review-modal-sub { font-size:13px; color:#4A4A6A; margin:6px 0 18px; }
  .review-label { font-size:12px; font-weight:600; color:#4A4A6A; display:block; margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
  /* star picker (struktur benar: input & label bersaudara + row-reverse) */
  .star-rating { display:inline-flex; flex-direction:row-reverse; gap:6px; }
  .star-rating input { position:absolute; opacity:0; width:0; height:0; }
  .star-rating label { font-size:32px; line-height:1; color:#DDE1EC; cursor:pointer; transition:color .15s; }
  .star-rating label:hover, .star-rating label:hover ~ label { color:#F4A261; }
  .star-rating input:checked ~ label { color:#F4A261; }
</style>

<script>
  function openReview(btn) {
    document.getElementById('reviewItemId').value   = btn.getAttribute('data-itemid');
    document.getElementById('reviewItemName').textContent = btn.getAttribute('data-itemname');
    // reset ke 5 bintang tiap kali dibuka
    var five = document.getElementById('mstar5'); if (five) five.checked = true;
    document.getElementById('reviewModal').classList.add('open');
  }
  function closeReview() {
    document.getElementById('reviewModal').classList.remove('open');
  }
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeReview(); });
</script>

<?php include $tpl.'footer.php'; ob_end_flush(); ?>
