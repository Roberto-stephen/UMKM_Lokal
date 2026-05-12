<?php
ob_start();
session_start();
$pageTitle = 'Beranda';
include 'init.php';

// Ambil riwayat pencarian — cek dulu apakah fungsinya tersedia
$lastSearch   = function_exists('getLastSearch') ? getLastSearch() : null;
$searchRecs   = [];
$searchRecIds = [];

if ($lastSearch && function_exists('getRecommendationsByKeyword')) {
    $searchRecs   = getRecommendationsByKeyword($lastSearch, 8);
    $searchRecIds = array_column($searchRecs, 'Item_ID');
}

// Handle clear search
if (isset($_GET['clear_search'])) {
    unset($_SESSION['last_search'], $_SESSION['last_search_time']);
    header('Location: index.php'); exit();
}
?>

<!-- HERO -->
<div class="hero-section">
  <div class="container">
    <div class="row">
      <div class="col-md-8">
        <h1>Temukan <em>Makanan Lokal</em><br>Favorit Kamu</h1>
        <p>Platform belanja produk makanan UMKM lokal Tangerang. Dukung pengusaha lokal, nikmati cita rasa autentik.</p>
        <div class="hero-tags">
          <span class="hero-tag"><i class="fa fa-map-marker"></i> Tangerang</span>
          <span class="hero-tag"><i class="fa fa-star"></i> Produk Lokal</span>
          <span class="hero-tag"><i class="fa fa-shield"></i> Terpercaya</span>
          <span class="hero-tag"><i class="fa fa-truck"></i> Antar / Ambil</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container">

  <!-- CATEGORY PILLS -->
  <div class="cat-pills" style="margin-top:28px;">
    <a href="index.php" class="cat-pill active">Semua</a>
    <?php
      $cats = getAllFrom("*","categories","where parent = 0","","ID","ASC");
      foreach ($cats as $cat) {
        echo '<a href="categories.php?pageid='.$cat['ID'].'" class="cat-pill">'.htmlspecialchars($cat['Name']).'</a>';
      }
    ?>
  </div>

  <!-- CBF SECTION: REKOMENDASI BERDASAR PENCARIAN TERAKHIR -->
  <?php if ($lastSearch && !empty($searchRecs)): ?>
  <div class="cbf-search-section">

    <div class="cbf-search-header">
      <div class="cbf-search-icon"><i class="fa fa-magic"></i></div>
      <div>
        <div class="cbf-search-label">Rekomendasi untuk kamu</div>
        <div class="cbf-search-title">
          Karena kamu mencari
          <span class="cbf-keyword">"<?php echo htmlspecialchars($lastSearch) ?>"</span>
        </div>
      </div>
      <a href="search.php?q=<?php echo urlencode($lastSearch) ?>" class="cbf-see-all">
        Lihat semua <i class="fa fa-arrow-right"></i>
      </a>
    </div>

    <div class="product-grid">
      <?php foreach ($searchRecs as $rec): ?>
      <div class="product-col">
        <div class="product-card">
          <div class="card-img">
            <span class="price-badge">Rp <?php echo number_format($rec['Price'],0,',','.') ?></span>
            <img src="<?php echo empty($rec['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($rec['picture']) ?>" alt="<?php echo htmlspecialchars($rec['Name']) ?>">
          </div>
          <div class="card-body">
            <div class="card-title"><a href="items.php?itemid=<?php echo $rec['Item_ID'] ?>"><?php echo htmlspecialchars($rec['Name']) ?></a></div>
            <div class="card-desc"><?php echo htmlspecialchars($rec['Description']) ?></div>
            <div class="card-footer-row">
              <span class="card-date"><i class="fa fa-calendar-o"></i> <?php echo $rec['Add_Date'] ?></span>
              <a href="items.php?itemid=<?php echo $rec['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="cbf-dismiss">
      <a href="?clear_search=1">
        <i class="fa fa-times"></i> Hapus riwayat pencarian
      </a>
    </div>

  </div>
  <?php endif; ?>

  <!-- SEMUA PRODUK -->
  <div class="section-head">
    <h2><?php echo $lastSearch ? 'Semua Produk' : 'Produk Tersedia' ?></h2>
  </div>

  <?php $allItems = getAllFrom('*','items','WHERE Approve = 1','','Item_ID'); ?>
  <?php if (!empty($allItems)): ?>
  <div class="product-grid">
    <?php foreach ($allItems as $item): ?>
    <div class="product-col">
      <div class="product-card">
        <div class="card-img">
          <span class="price-badge">Rp <?php echo number_format($item['Price'],0,',','.') ?></span>
          <img src="<?php echo empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="<?php echo htmlspecialchars($item['Name']) ?>">
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
  <?php else: ?>
  <div class="empty-state">
    <i class="fa fa-shopping-basket"></i>
    <p>Belum ada produk tersedia.</p>
  </div>
  <?php endif; ?>

</div>

<?php include $tpl . 'footer.php'; ob_end_flush(); ?>