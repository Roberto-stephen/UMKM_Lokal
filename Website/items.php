<?php
ob_start(); session_start();
$pageTitle = 'Detail Produk';
include 'init.php';

$itemid = isset($_GET['itemid']) && is_numeric($_GET['itemid']) ? intval($_GET['itemid']) : 0;
$uid    = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;

// -------------------------------------------------------
// Handle add to cart
// -------------------------------------------------------
if (isset($_POST['add_to_cart']) && $uid > 0) {
    $qty = max(1, intval($_POST['qty'] ?? 1));
    // Ambil stok + pemilik produk sekaligus
    $stokChk = $con->prepare("SELECT stok, Member_ID FROM items WHERE Item_ID=? AND Approve=1");
    $stokChk->execute([$itemid]);
    $stokData = $stokChk->fetch();
    if ($stokData) {
        // BLOKIR: penjual tidak boleh membeli produknya sendiri
        if ((int)$stokData['Member_ID'] === $uid) {
            header('Location: items.php?itemid='.$itemid.'&err=own'); exit();
        }
        if ($stokData['stok'] > 0) {
            $qtyMax = min($qty, $stokData['stok']);
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            if (isset($_SESSION['cart'][$itemid])) {
                $_SESSION['cart'][$itemid]['qty'] = min($_SESSION['cart'][$itemid]['qty'] + $qtyMax, $stokData['stok']);
            } else {
                $s = $con->prepare("SELECT * FROM items WHERE Item_ID=? AND Approve=1");
                $s->execute([$itemid]);
                $p = $s->fetch();
                if ($p) $_SESSION['cart'][$itemid] = ['item_id'=>$itemid,'name'=>$p['Name'],'price'=>$p['Price'],'picture'=>$p['picture'],'qty'=>$qtyMax];
            }
            header('Location: cart.php?added=1'); exit();
        }
    }
}

// Increment view count
$con->prepare("UPDATE items SET view_count = view_count + 1 WHERE Item_ID=?")->execute([$itemid]);

$stmt = $con->prepare("SELECT items.*, categories.Name AS category_name, users.Username FROM items INNER JOIN categories ON categories.ID=items.Cat_ID INNER JOIN users ON users.UserID=items.Member_ID WHERE Item_ID=? AND Approve=1");
$stmt->execute([$itemid]);

if ($stmt->rowCount() > 0):
    $item = $stmt->fetch();
    $pageTitle = $item['Name'];

    // -------------------------------------------------------
    // Status user terhadap produk ini
    // -------------------------------------------------------
    $isOwner = $uid > 0 && (int)$item['Member_ID'] === $uid;

    // -------------------------------------------------------
    // KUOTA ULASAN = "satu ulasan per pembelian".
    //   $purchaseCount = berapa kali user beli produk ini (order 'Selesai')
    //   $reviewCount   = berapa ulasan yang sudah user tulis untuk produk ini
    //   Boleh ulas jika: purchaseCount > reviewCount
    //   -> Sudah ulas 1x & beli 1x  = tidak bisa lagi.
    //   -> Beli lagi (order Selesai baru) = dapat jatah ulas lagi.
    // Basis 'Selesai' saja (barang sudah diterima). Ubah di sini kalau perlu.
    // -------------------------------------------------------
    $purchaseCount = 0;
    if ($uid > 0 && !$isOwner) {
        $chk = $con->prepare("
            SELECT COUNT(DISTINCT o.order_id) AS c
            FROM   order_items oi
            JOIN   orders o ON o.order_id = oi.order_id
            WHERE  oi.item_id = ? AND o.user_id = ? AND o.status = 'Selesai'
        ");
        $chk->execute([$itemid, $uid]);
        $purchaseCount = (int)($chk->fetch()['c'] ?? 0);
    }

    $reviewCount = 0;
    if ($uid > 0) {
        $rv = $con->prepare("SELECT COUNT(*) AS c FROM comments WHERE item_id=? AND user_id=?");
        $rv->execute([$itemid, $uid]);
        $reviewCount = (int)($rv->fetch()['c'] ?? 0);
    }
    $canReview = $purchaseCount > $reviewCount;

    // -------------------------------------------------------
    // Proses kirim ulasan — GATE di sisi server (tidak percaya form saja)
    // -------------------------------------------------------
    $reviewMsg = ''; $reviewMsgType = '';
    if (isset($_POST['add_comment']) && $uid > 0) {
        if ($isOwner) {
            $reviewMsg = 'Kamu tidak bisa mengulas produk milik sendiri.'; $reviewMsgType = 'danger';
        } elseif ($purchaseCount === 0) {
            $reviewMsg = 'Hanya pembeli yang bisa memberi ulasan. Selesaikan pembelian produk ini dulu ya.'; $reviewMsgType = 'danger';
        } elseif (!$canReview) {
            $reviewMsg = 'Kamu sudah mengulas semua pembelianmu untuk produk ini. Beli lagi untuk memberi ulasan baru.'; $reviewMsgType = 'danger';
        } else {
            $comment = htmlspecialchars(strip_tags($_POST['comment'] ?? ''));
            $rating  = max(1, min(5, intval($_POST['rating'] ?? 5)));
            if ($comment !== '') {
                $ins = $con->prepare("INSERT INTO comments(comment,rating,status,comment_date,item_id,user_id) VALUES(?,?,1,NOW(),?,?)");
                $ins->execute([$comment, $rating, $item['Item_ID'], $uid]);
                $reviewCount++;                       // pakai satu jatah
                $canReview = $purchaseCount > $reviewCount;
                $reviewMsg = 'Ulasan berhasil ditambahkan! Terima kasih.'; $reviewMsgType = 'success';
            } else {
                $reviewMsg = 'Ulasan tidak boleh kosong.'; $reviewMsgType = 'danger';
            }
        }
    }

    // -------------------------------------------------------
    // Rata-rata rating (dihitung SETELAH kemungkinan insert di atas)
    // -------------------------------------------------------
    $ratingStmt = $con->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM comments WHERE item_id=? AND status=1");
    $ratingStmt->execute([$itemid]);
    $ratingData  = $ratingStmt->fetch();
    $avgRating   = round($ratingData['avg_rating'] ?? 0, 1);
    $totalUlasan = (int)($ratingData['total'] ?? 0);
?>

<div class="page-banner"><div class="container">
  <h1><?php echo htmlspecialchars($item['Name']) ?></h1>
  <div class="breadcrumb-custom">
    <a href="index.php">Beranda</a> &rsaquo;
    <a href="categories.php?pageid=<?php echo $item['Cat_ID'] ?>"><?php echo htmlspecialchars($item['category_name']) ?></a> &rsaquo;
    <span><?php echo htmlspecialchars($item['Name']) ?></span>
  </div>
</div></div>

<div class="container item-detail-wrap">
<div class="row">
  <div class="col-md-4">
    <div class="item-detail-img">
      <img src="<?php echo empty($item['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="<?php echo htmlspecialchars($item['Name']) ?>">
    </div>
  </div>

  <div class="col-md-8 item-detail-info">
    <div class="item-price-big">Rp <?php echo number_format($item['Price'],0,',','.') ?></div>

    <!-- ============================================================
         RATING BINTANG (tampilan) — selalu terlihat, nilai jelas.
         Bintang penuh = oranye, kosong = abu-abu. Kalau belum ada
         ulasan, tampilkan "Belum ada rating".
         ============================================================ -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
      <div style="font-size:18px;letter-spacing:3px;line-height:1;">
        <?php
          $full = (int)round($avgRating);
          for ($i = 1; $i <= 5; $i++) {
              echo '<span style="color:' . ($i <= $full ? '#F4A261' : '#DDE1EC') . ';">&#9733;</span>';
          }
        ?>
      </div>
      <?php if ($totalUlasan > 0): ?>
        <span style="font-size:14px;color:#1B2E5E;font-weight:700;"><?php echo number_format($avgRating,1) ?></span>
        <span style="font-size:13px;color:#9A9AB0;">/ 5 &middot; <?php echo $totalUlasan ?> ulasan</span>
      <?php else: ?>
        <span style="font-size:13px;color:#9A9AB0;">Belum ada rating</span>
      <?php endif; ?>
      <span style="font-size:12px;color:#9A9AB0;margin-left:6px;"><i class="fa fa-eye"></i> <?php echo number_format($item['view_count'],0,',','.') ?> dilihat</span>
    </div>

    <p style="color:#57534E;font-size:15px;line-height:1.7;"><?php echo nl2br(htmlspecialchars($item['Description'])) ?></p>

    <?php if (!empty($item['cbf_rasa']) || !empty($item['cbf_bahan'])): ?>
    <div class="cbf-tags">
      <?php if (!empty($item['cbf_kategori'])) echo '<span class="cbf-tag"><i class="fa fa-tag"></i> '.htmlspecialchars($item['cbf_kategori']).'</span>'; ?>
      <?php if (!empty($item['cbf_rasa']))     echo '<span class="cbf-tag"><i class="fa fa-cutlery"></i> '.htmlspecialchars($item['cbf_rasa']).'</span>'; ?>
      <?php if (!empty($item['cbf_kepedasan']))echo '<span class="cbf-tag"><i class="fa fa-fire"></i> '.htmlspecialchars($item['cbf_kepedasan']).'</span>'; ?>
      <?php if (!empty($item['cbf_bahan']))    echo '<span class="cbf-tag"><i class="fa fa-leaf"></i> '.htmlspecialchars($item['cbf_bahan']).'</span>'; ?>
    </div>
    <?php endif; ?>

    <ul class="item-meta-list">
      <li><i class="fa fa-calendar fa-fw"></i><span class="meta-label">Tanggal</span><?php echo $item['Add_Date'] ?></li>
      <li><i class="fa fa-tags fa-fw"></i><span class="meta-label">Kategori</span><a href="categories.php?pageid=<?php echo $item['Cat_ID'] ?>"><?php echo htmlspecialchars($item['category_name']) ?></a></li>
      <li><i class="fa fa-user fa-fw"></i><span class="meta-label">Penjual</span><?php echo htmlspecialchars($item['Username']) ?></li>
      <!-- STOK -->
      <li>
        <i class="fa fa-cubes fa-fw"></i>
        <span class="meta-label">Stok</span>
        <?php if ($item['stok'] > 10): ?>
          <span style="color:#1A5C2A;font-weight:600;"><?php echo $item['stok'] ?> tersedia</span>
        <?php elseif ($item['stok'] > 0): ?>
          <span style="color:#92400E;font-weight:600;">Hampir habis (<?php echo $item['stok'] ?> tersisa)</span>
        <?php else: ?>
          <span style="color:#9B1C1C;font-weight:600;">Stok habis</span>
        <?php endif; ?>
      </li>
      <?php if (!empty($item['contact'])): ?>
      <li><i class="fa fa-phone fa-fw"></i><span class="meta-label">Kontak</span><?php echo htmlspecialchars($item['contact']) ?></li>
      <?php endif; ?>
    </ul>

    <!-- ============================================================
         ADD TO CART — penjual TIDAK bisa membeli produk sendiri
         ============================================================ -->
    <?php if (isset($_GET['err']) && $_GET['err'] === 'own'): ?>
      <div class="alert alert-danger" style="margin-top:16px;"><i class="fa fa-info-circle"></i> Ini produk milikmu sendiri — kamu tidak bisa membelinya.</div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user'])): ?>
      <div class="nice-message" style="margin-top:16px;"><a href="login.php" style="color:#1B2E5E;font-weight:700;">Login</a> untuk menambahkan ke keranjang.</div>
    <?php elseif ($isOwner): ?>
      <div class="alert" style="margin-top:16px;background:#E8ECF5;border:1px solid #C5CEE0;color:#1B2E5E;padding:14px 16px;border-radius:10px;">
        <i class="fa fa-user-circle"></i> Ini <strong>produk milikmu</strong>. Penjual tidak bisa membeli produk sendiri.
        <a href="myItems.php" style="color:#B5272A;font-weight:700;margin-left:6px;">Kelola produk &rarr;</a>
      </div>
    <?php elseif ($item['stok'] > 0): ?>
      <form method="POST" style="display:flex;align-items:center;gap:12px;margin-top:16px;">
        <input type="number" name="qty" value="1" min="1" max="<?php echo $item['stok'] ?>"
               style="width:70px;padding:10px;border:1.5px solid #DDE1EC;border-radius:8px;text-align:center;font-size:16px;font-weight:600;color:#1B2E5E;">
        <button type="submit" name="add_to_cart" class="btn-submit" style="flex:1;">
          <i class="fa fa-shopping-basket"></i> Tambah ke Keranjang
        </button>
      </form>
    <?php else: ?>
      <div class="alert alert-danger" style="margin-top:16px;"><i class="fa fa-times-circle"></i> Stok habis, produk tidak tersedia saat ini.</div>
    <?php endif; ?>
  </div>
</div>

<!-- CBF REKOMENDASI -->
<?php if (function_exists('getRecommendations')): ?>
<?php $recommendations = getRecommendations($itemid); ?>
<?php if (!empty($recommendations)): ?>
<div class="rekomendasi-section">
  <h3>Rekomendasi Makanan Serupa</h3>
  <div class="product-grid">
    <?php foreach ($recommendations as $rec): ?>
    <div class="product-col">
      <div class="product-card">
        <div class="card-img">
          <span class="price-badge">Rp <?php echo number_format($rec['Price'],0,',','.') ?></span>
          <img src="<?php echo empty($rec['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($rec['picture']) ?>" alt="">
        </div>
        <div class="card-body">
          <div class="card-title"><a href="items.php?itemid=<?php echo $rec['Item_ID'] ?>"><?php echo htmlspecialchars($rec['Name']) ?></a></div>
          <div class="card-desc"><?php echo htmlspecialchars($rec['Description']) ?></div>
          <div class="card-footer-row">
            <span class="card-date"><?php echo $rec['Add_Date'] ?></span>
            <a href="items.php?itemid=<?php echo $rec['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<hr class="custom-hr">

<!-- ============================================================
     KOMENTAR + RATING — hanya pembeli yang bisa memberi ulasan
     ============================================================ -->
<div class="comment-section" id="ulasan">
  <h3><i class="fa fa-comments" style="color:#B5272A;"></i> Ulasan & Rating</h3>

  <?php if ($reviewMsg): ?>
    <div class="alert alert-<?php echo $reviewMsgType ?>" style="margin-bottom:16px;">
      <i class="fa fa-<?php echo $reviewMsgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?php echo $reviewMsg ?>
    </div>
  <?php endif; ?>

  <?php if (!isset($_SESSION['user'])): ?>
    <div class="nice-message" style="margin-bottom:24px;"><a href="login.php" style="color:#1B2E5E;font-weight:700;">Login</a> untuk memberikan ulasan.</div>

  <?php elseif ($isOwner): ?>
    <div class="nice-message" style="margin-bottom:24px;"><i class="fa fa-user-circle"></i> Ini produk milikmu — kamu tidak bisa mengulas produk sendiri.</div>

  <?php elseif ($purchaseCount === 0): ?>
    <div class="nice-message" style="margin-bottom:24px;">
      <i class="fa fa-lock" style="color:#B5272A;"></i>
      Hanya <strong>pembeli</strong> yang bisa memberi ulasan. Kamu belum pernah menyelesaikan pembelian produk ini.
    </div>

  <?php elseif (!$canReview): ?>
    <div class="nice-message" style="margin-bottom:24px;"><i class="fa fa-check" style="color:#1A5C2A;"></i> Kamu sudah mengulas produk ini. <strong>Beli lagi</strong> untuk memberi ulasan baru. Terima kasih!</div>

  <?php else: ?>
    <!-- FORM ULASAN (hanya untuk pembeli yang masih punya jatah ulasan) -->
    <div class="comment-form" style="margin-bottom:28px;">
      <form action="items.php?itemid=<?php echo $item['Item_ID'] ?>" method="POST">

        <!-- ============================================================
             STAR PICKER (diperbaiki)
             Sebelumnya <input> diletakkan DI DALAM <label>, sedangkan
             CSS-nya pakai selector saudara (input:checked ~ label), jadi
             bintang terpilih tidak pernah berwarna. Sekarang <input> dan
             <label> jadi SAUDARA + urutan dibalik (row-reverse), sehingga
             bintang yang dipilih (dan yang bernilai lebih kecil) menyala.
             ============================================================ -->
        <div style="margin-bottom:12px;">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Rating Produk *</label>
          <div class="star-rating" role="radiogroup" aria-label="Pilih rating">
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <input type="radio" name="rating" id="star<?php echo $i ?>" value="<?php echo $i ?>" <?php echo $i === 5 ? 'checked' : '' ?>>
              <label for="star<?php echo $i ?>" title="<?php echo $i ?> bintang">&#9733;</label>
            <?php endfor; ?>
          </div>
          <style>
            .star-rating { display:inline-flex; flex-direction:row-reverse; gap:6px; }
            .star-rating input { position:absolute; opacity:0; width:0; height:0; }
            .star-rating label { font-size:30px; line-height:1; color:#DDE1EC; cursor:pointer; transition:color .15s; }
            .star-rating label:hover,
            .star-rating label:hover ~ label { color:#F4A261; }
            .star-rating input:checked ~ label { color:#F4A261; }
          </style>
        </div>

        <textarea name="comment" placeholder="Bagikan pengalamanmu tentang produk ini..." required style="width:100%;border:1.5px solid #DDE1EC;border-radius:10px;padding:12px 16px;font-size:14px;font-family:'DM Sans',sans-serif;resize:vertical;min-height:100px;background:#F0F2F5;"></textarea>
        <button type="submit" name="add_comment" class="btn-primary-custom" style="margin-top:10px;">Kirim Ulasan</button>
      </form>
    </div>
  <?php endif; ?>

  <?php
    $stmtC = $con->prepare("SELECT comments.*, users.Username AS Member, users.avatar FROM comments INNER JOIN users ON users.UserID=comments.user_id WHERE item_id=? AND status=1 ORDER BY c_id DESC");
    $stmtC->execute([$item['Item_ID']]);
    $comments = $stmtC->fetchAll();
  ?>
  <?php if (!empty($comments)): ?>
    <?php foreach ($comments as $comment): ?>
    <div class="comment-item">
      <div class="comment-avatar">
        <?php if (!empty($comment['avatar']) && $comment['avatar']!='default.png'): ?>
          <img src="admin/uploads/avatars/<?php echo htmlspecialchars($comment['avatar']) ?>" alt="">
        <?php else: ?>
          <?php echo strtoupper(substr($comment['Member'],0,1)) ?>
        <?php endif; ?>
      </div>
      <div class="comment-content" style="flex:1;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span class="comment-author"><?php echo htmlspecialchars($comment['Member']) ?></span>
          <span style="font-size:14px;letter-spacing:2px;">
            <?php for ($i=1;$i<=5;$i++) echo '<span style="color:'.($i<=$comment['rating']?'#F4A261':'#DDE1EC').';">&#9733;</span>'; ?>
          </span>
          <span class="comment-date"><i class="fa fa-clock-o"></i> <?php echo $comment['comment_date'] ?></span>
        </div>
        <div class="comment-text"><?php echo htmlspecialchars($comment['comment']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:30px 0;"><i class="fa fa-comment-o"></i><p style="font-size:14px;">Belum ada ulasan.</p></div>
  <?php endif; ?>
</div>
</div>

<?php else: ?>
<div class="container" style="padding:60px 0;"><div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Produk tidak ditemukan.</div></div>
<?php endif; ?>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>
