<?php
session_start();
$pageTitle = 'Hasil Pencarian';
include 'init.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Simpan keyword ke session — aman pakai function_exists
if (!empty($q) && function_exists('saveLastSearch')) {
    saveLastSearch($q);
}

$keyword = '%' . $q . '%';
?>

<div class="page-banner">
  <div class="container">
    <h1>Hasil Pencarian</h1>
    <div class="breadcrumb-custom">
      <a href="index.php">Beranda</a> &rsaquo;
      <span>Pencarian: "<?php echo htmlspecialchars($q) ?>"</span>
    </div>
  </div>
</div>

<div class="container">
<?php if (empty($q)): ?>
  <div class="empty-state" style="padding:60px 0;">
    <i class="fa fa-search"></i>
    <p>Masukkan kata kunci untuk mencari produk.</p>
  </div>

<?php else:
  $stmt = $con->prepare("SELECT * FROM items WHERE Approve = 1 AND (Name LIKE ? OR Description LIKE ? OR cbf_kategori LIKE ? OR cbf_rasa LIKE ? OR cbf_bahan LIKE ?) ORDER BY Item_ID DESC");
  $stmt->execute([$keyword, $keyword, $keyword, $keyword, $keyword]);
  $results = $stmt->fetchAll();
?>

  <div style="margin:24px 0 4px;font-size:14px;color:#4A4A6A;">
    Ditemukan <strong style="color:#1B2E5E;"><?php echo count($results) ?> produk</strong>
    untuk "<strong style="color:#B5272A;"><?php echo htmlspecialchars($q) ?></strong>"
    <a href="index.php" style="margin-left:12px;font-size:12px;color:#9A9AB0;">← Kembali ke beranda</a>
  </div>

  <?php if (!empty($results)): ?>
  <div class="product-grid" style="margin-top:16px;">
    <?php foreach ($results as $item): ?>
    <div class="product-col">
      <div class="product-card">
        <div class="card-img">
          <span class="price-badge">Rp <?php echo number_format($item['Price'],0,',','.') ?></span>
          <img src="<?php echo empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="">
        </div>
        <div class="card-body">
          <div class="card-title"><a href="items.php?itemid=<?php echo $item['Item_ID'] ?>"><?php echo htmlspecialchars($item['Name']) ?></a></div>
          <div class="card-desc"><?php echo htmlspecialchars($item['Description']) ?></div>
          <div class="card-footer-row">
            <span class="card-date"><i class="fa fa-calendar-o"></i> <?php echo $item['Add_Date'] ?></span>
            <a href="items.php?itemid=<?php echo $item['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- CBF: Rekomendasi dari keyword -->
  <?php if (function_exists('getRecommendationsByKeyword')):
    $shownIds = array_column($results, 'Item_ID');
    $cbfRecs  = getRecommendationsByKeyword($q, 4);
    $cbfRecs  = array_filter($cbfRecs, fn($r) => !in_array($r['Item_ID'], $shownIds));
  ?>
  <?php if (!empty($cbfRecs)): ?>
  <div class="rekomendasi-section" style="margin-top:32px;">
    <h3>Mungkin kamu juga suka...</h3>
    <div class="product-grid">
      <?php foreach ($cbfRecs as $rec): ?>
      <div class="product-col">
        <div class="product-card">
          <div class="card-img">
            <span class="price-badge">Rp <?php echo number_format($rec['Price'],0,',','.') ?></span>
            <img src="<?php echo empty($rec['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($rec['picture']) ?>" alt="">
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

  <?php else: ?>
  <div class="empty-state">
    <i class="fa fa-search"></i>
    <p>Tidak ada produk yang cocok dengan "<strong><?php echo htmlspecialchars($q) ?></strong>".</p>
    <a href="index.php" style="color:#1B2E5E;font-weight:600;">← Kembali ke beranda</a>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div>

<?php include $tpl . 'footer.php'; ?>