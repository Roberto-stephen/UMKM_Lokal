<?php
ob_start(); session_start();
$pageTitle = 'Detail Produk';
include 'init.php';

$itemid = isset($_GET['itemid']) && is_numeric($_GET['itemid']) ? intval($_GET['itemid']) : 0;

// Handle add to cart
if (isset($_POST['add_to_cart']) && isset($_SESSION['user'])) {
    $qty = max(1, intval($_POST['qty'] ?? 1));
    // Cek stok
    $stokChk = $con->prepare("SELECT stok FROM items WHERE Item_ID=? AND Approve=1");
    $stokChk->execute([$itemid]);
    $stokData = $stokChk->fetch();
    if ($stokData && $stokData['stok'] > 0) {
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

// Increment view count
$con->prepare("UPDATE items SET view_count = view_count + 1 WHERE Item_ID=?")->execute([$itemid]);

$stmt = $con->prepare("SELECT items.*, categories.Name AS category_name, users.Username FROM items INNER JOIN categories ON categories.ID=items.Cat_ID INNER JOIN users ON users.UserID=items.Member_ID WHERE Item_ID=? AND Approve=1");
$stmt->execute([$itemid]);

if ($stmt->rowCount() > 0):
    $item = $stmt->fetch();
    $pageTitle = $item['Name'];

    // Hitung rata-rata rating
    $ratingStmt = $con->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM comments WHERE item_id=? AND status=1");
    $ratingStmt->execute([$itemid]);
    $ratingData = $ratingStmt->fetch();
    $avgRating  = round($ratingData['avg_rating'] ?? 0, 1);
    $totalUlasan = $ratingData['total'];
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

    <!-- RATING BINTANG -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
      <div style="color:#F4A261;font-size:16px;">
        <?php for ($i=1;$i<=5;$i++) echo $i<=$avgRating ? '★' : '☆'; ?>
      </div>
      <span style="font-size:13px;color:#4A4A6A;"><?php echo $avgRating ?>/5 &nbsp;(<?php echo $totalUlasan ?> ulasan)</span>
      <span style="font-size:12px;color:#9A9AB0;margin-left:8px;"><i class="fa fa-eye"></i> <?php echo number_format($item['view_count'],0,',','.') ?> dilihat</span>
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

    <!-- ADD TO CART -->
    <?php if (isset($_SESSION['user'])): ?>
      <?php if ($item['stok'] > 0): ?>
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
    <?php else: ?>
    <div class="nice-message" style="margin-top:16px;"><a href="login.php" style="color:#1B2E5E;font-weight:700;">Login</a> untuk menambahkan ke keranjang.</div>
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

<!-- KOMENTAR + RATING -->
<div class="comment-section">
  <h3><i class="fa fa-comments" style="color:#B5272A;"></i> Ulasan & Rating</h3>

  <?php if (isset($_SESSION['user'])): ?>
  <div class="comment-form" style="margin-bottom:28px;">
    <form action="<?php echo $_SERVER['PHP_SELF'].'?itemid='.$item['Item_ID'] ?>" method="POST">

      <!-- BINTANG RATING -->
      <div style="margin-bottom:12px;">
        <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Rating Produk *</label>
        <div class="star-rating" style="display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end;">
          <?php for ($i=5;$i>=1;$i--): ?>
          <label style="cursor:pointer;font-size:28px;color:#DDE1EC;transition:color .15s;" for="star<?php echo $i ?>">★
            <input type="radio" name="rating" id="star<?php echo $i ?>" value="<?php echo $i ?>" style="display:none;" <?php echo $i==5?'checked':'' ?>>
          </label>
          <?php endfor; ?>
        </div>
        <style>
          .star-rating label:hover, .star-rating label:hover ~ label,
          .star-rating input:checked ~ label { color: #F4A261; }
          .star-rating { flex-direction: row-reverse; }
          .star-rating label { color: #DDE1EC; }
          .star-rating input[type="radio"]:checked + label,
          .star-rating label:hover { color: #F4A261; }
        </style>
      </div>

      <textarea class="comment-form textarea" name="comment" placeholder="Bagikan pengalamanmu tentang produk ini..." required style="width:100%;border:1.5px solid #DDE1EC;border-radius:10px;padding:12px 16px;font-size:14px;font-family:'DM Sans',sans-serif;resize:vertical;min-height:100px;background:#F0F2F5;"></textarea>
      <button type="submit" name="add_comment" class="btn-primary-custom" style="margin-top:10px;">Kirim Ulasan</button>
    </form>

    <?php if (isset($_POST['add_comment'])) {
        $comment = htmlspecialchars(strip_tags($_POST['comment']));
        $rating  = intval($_POST['rating'] ?? 5);
        $rating  = max(1, min(5, $rating));
        if (!empty($comment)) {
            $ins = $con->prepare("INSERT INTO comments(comment,rating,status,comment_date,item_id,user_id) VALUES(?,?,1,NOW(),?,?)");
            $ins->execute([$comment, $rating, $item['Item_ID'], $_SESSION['uid']]);
            echo '<div class="alert alert-success" style="margin-top:12px;"><i class="fa fa-check"></i> Ulasan berhasil ditambahkan!</div>';
        }
    } ?>
  </div>
  <?php else: ?>
  <div class="nice-message" style="margin-bottom:24px;"><a href="login.php" style="color:#1B2E5E;font-weight:700;">Login</a> untuk memberikan ulasan.</div>
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
          <span style="color:#F4A261;font-size:14px;">
            <?php for ($i=1;$i<=5;$i++) echo $i<=$comment['rating']?'★':'☆'; ?>
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