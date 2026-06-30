<?php
ob_start();
session_start();
$pageTitle = 'Beranda';
include 'init.php';

// Handle clear search history
if (isset($_GET['clear_search'])) {
    unset($_SESSION['last_search'], $_SESSION['last_search_time']);
    header('Location: index.php'); exit();
}

// Ambil last search & generate rekomendasi
$lastSearch = function_exists('getLastSearch') ? getLastSearch() : null;
$searchRecs = [];

if ($lastSearch && function_exists('getRecommendationsByKeyword')) {
    $searchRecs = getRecommendationsByKeyword($lastSearch, 8);
}

// -------------------------------------------------------
// SORT — ala ShopeeFood: Relevansi(Terbaru) / Termurah / Termahal / Rating
// -------------------------------------------------------
$sortOptions = [
    'terbaru'  => 'Terbaru',
    'termurah' => 'Harga Termurah',
    'termahal' => 'Harga Termahal',
    'rating'   => 'Rating Tertinggi',
];
$sort = isset($_GET['sort']) && isset($sortOptions[$_GET['sort']]) ? $_GET['sort'] : 'terbaru';
?>

<!-- ==================== HERO ==================== -->
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
      foreach ($cats as $cat)
        echo '<a href="categories.php?pageid='.$cat['ID'].'" class="cat-pill">'.htmlspecialchars($cat['Name']).'</a>';
    ?>
  </div>

  <!-- ==================== CBF SECTION ==================== -->
  <?php if ($lastSearch && !empty($searchRecs)): ?>
  <div style="margin:32px 0 8px;">

    <!-- Header section -->
    <div style="background:linear-gradient(135deg,#111E40,#1B2E5E);border-radius:16px 16px 0 0;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:40px;height:40px;background:rgba(244,162,97,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa fa-magic" style="color:#F4A261;font-size:18px;"></i>
        </div>
        <div>
          <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px;">
            Rekomendasi untuk kamu
          </div>
          <div style="font-size:16px;font-weight:700;color:#fff;font-family:'Playfair Display',serif;">
            Karena kamu mencari
            <span style="color:#F4A261;">"<?= htmlspecialchars($lastSearch) ?>"</span>
          </div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <a href="search.php?q=<?= urlencode($lastSearch) ?>"
           style="background:rgba(255,255,255,.12);color:#fff;padding:7px 16px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,.2);">
          <i class="fa fa-search"></i> Lihat semua hasil
        </a>
        <a href="?clear_search=1"
           style="background:rgba(255,255,255,.07);color:rgba(255,255,255,.5);padding:7px 10px;border-radius:8px;font-size:12px;text-decoration:none;border:1px solid rgba(255,255,255,.1);"
           title="Hapus riwayat pencarian">
          <i class="fa fa-times"></i>
        </a>
      </div>
    </div>

    <!-- Grid rekomendasi -->
    <div style="background:linear-gradient(180deg,rgba(17,30,64,.04),transparent);border:1px solid #DDE1EC;border-top:none;border-radius:0 0 16px 16px;padding:20px 16px 24px;">
      <div class="product-grid">
        <?php foreach ($searchRecs as $i => $rec):
          // Tentukan label "alasan rekomendasi" berdasarkan data tersedia
          if (!empty($rec['cbf_rasa']))
            $reason = '<i class="fa fa-cutlery"></i> ' . htmlspecialchars($rec['cbf_rasa']);
          elseif (!empty($rec['category_name']))
            $reason = '<i class="fa fa-tag"></i> ' . htmlspecialchars($rec['category_name']);
          else
            $reason = '<i class="fa fa-magic"></i> Rekomendasi untukmu';

          // Highlight keyword dalam nama produk
          $nameHtml = htmlspecialchars($rec['Name']);
          foreach (array_filter(explode(' ', strtolower($lastSearch))) as $kw) {
            if (strlen($kw) > 1)
              $nameHtml = preg_replace('/(' . preg_quote($kw, '/') . ')/i',
                '<mark style="background:#FEF9C3;padding:0 1px;border-radius:2px;font-style:normal;">$1</mark>',
                $nameHtml);
          }
        ?>
        <div class="product-col">
          <div class="product-card" style="position:relative;">
            <!-- Badge rank -->
            <?php if ($i < 3): ?>
            <div style="position:absolute;top:8px;left:8px;z-index:2;background:<?= ['#B5272A','#1B2E5E','#4A4A6A'][$i] ?>;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;line-height:1.6;">
              #<?= $i+1 ?> Match
            </div>
            <?php endif; ?>

            <div class="card-img">
              <span class="price-badge" style="left:auto;right:8px;">Rp <?= number_format($rec['Price'],0,',','.') ?></span>
              <img src="<?= empty($rec['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($rec['picture']) ?>"
                   alt="<?= htmlspecialchars($rec['Name']) ?>">
            </div>
            <div class="card-body">
              <!-- Label alasan rekomendasi -->
              <div style="font-size:10px;color:#F4A261;font-weight:600;margin-bottom:4px;"><?= $reason ?></div>
              <div class="card-title">
                <a href="items.php?itemid=<?= $rec['Item_ID'] ?>"><?= $nameHtml ?></a>
              </div>
              <div class="card-desc"><?= htmlspecialchars(substr($rec['Description'],0,70)) ?>...</div>
              <div class="card-footer-row">
                <span style="font-size:11px;color:<?= ($rec['stok']??0)>0?'#1A5C2A':'#9B1C1C' ?>;">
                  <i class="fa fa-cubes"></i>
                  <?= ($rec['stok']??0)>0 ? 'Stok: '.($rec['stok']) : 'Habis' ?>
                </span>
                <a href="items.php?itemid=<?= $rec['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ==================== SEMUA PRODUK ==================== -->
  <div class="section-head" style="margin-top:<?= $lastSearch && !empty($searchRecs) ? '28px' : '0' ?>;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <h2 style="margin:0;"><?= $lastSearch ? 'Semua Produk' : 'Produk Tersedia' ?></h2>

    <!-- ============================================================
         SORT DROPDOWN — custom (bukan native select)
         ============================================================ -->
    <div style="position:relative;display:inline-block;" id="sort-dropdown-wrap">
      <button type="button" onclick="toggleSortMenu()" id="sort-trigger-btn"
              style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #DDE1EC;
                     border-radius:8px;padding:9px 14px;font-size:13px;font-weight:600;color:#1B2E5E;
                     cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap;">
        <i class="fa fa-sort" style="color:#9A9AB0;font-size:12px;"></i>
        Urutkan: <span style="color:#B5272A;"><?= $sortOptions[$sort] ?></span>
        <i class="fa fa-chevron-down" style="font-size:10px;color:#9A9AB0;margin-left:2px;"></i>
      </button>

      <div id="sort-menu" style="display:none;position:absolute;top:calc(100% + 6px);right:0;
           background:#fff;border:1px solid #DDE1EC;border-radius:10px;
           box-shadow:0 8px 24px rgba(27,46,94,.12);min-width:200px;z-index:50;overflow:hidden;">
        <?php foreach ($sortOptions as $key => $label):
          $url = 'index.php?sort=' . $key;
          $isActive = $sort === $key;
        ?>
        <a href="<?= htmlspecialchars($url) ?>"
           style="display:flex;align-items:center;justify-content:space-between;gap:10px;
                  padding:10px 16px;font-size:13px;text-decoration:none;
                  color:<?= $isActive ? '#B5272A' : '#1B2E5E' ?>;
                  font-weight:<?= $isActive ? '700' : '500' ?>;
                  background:<?= $isActive ? '#FDECEA' : 'transparent' ?>;
                  border-bottom:1px solid #F0F2F5;">
          <?= $label ?>
          <?php if ($isActive): ?><i class="fa fa-check" style="font-size:11px;"></i><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script>
  function toggleSortMenu() {
    var menu = document.getElementById('sort-menu');
    menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
  }
  document.addEventListener('click', function(e) {
    var wrap = document.getElementById('sort-dropdown-wrap');
    if (wrap && !wrap.contains(e.target)) {
      var menu = document.getElementById('sort-menu');
      if (menu) menu.style.display = 'none';
    }
  });
  </script>

  <?php
    $orderSql = match($sort) {
        'termurah' => 'i.Price ASC',
        'termahal' => 'i.Price DESC',
        'rating'   => 'i.Rating DESC',
        default    => 'i.Item_ID DESC',
    };
    try {
        $stmt = $con->prepare("
            SELECT i.*, c.Name AS category_name
            FROM   items i
            LEFT   JOIN categories c ON c.ID = i.Cat_ID
            WHERE  i.Approve = 1
            ORDER  BY $orderSql
        ");
        $stmt->execute();
        $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $allItems = getAllFrom('*','items','WHERE Approve = 1','','Item_ID');
    }
  ?>

  <?php if (!empty($allItems)): ?>
  <div class="product-grid">
    <?php foreach ($allItems as $item): ?>
    <div class="product-col">
      <div class="product-card">
        <div class="card-img">
          <span class="price-badge">Rp <?= number_format($item['Price'],0,',','.') ?></span>
          <img src="<?= empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>"
               alt="<?= htmlspecialchars($item['Name']) ?>">
        </div>
        <div class="card-body">
          <?php if (!empty($item['category_name'])): ?>
          <div style="font-size:10px;color:#9A9AB0;margin-bottom:3px;">
            <i class="fa fa-tag"></i> <?= htmlspecialchars($item['category_name']) ?>
          </div>
          <?php endif; ?>
          <div class="card-title">
            <a href="items.php?itemid=<?= $item['Item_ID'] ?>"><?= htmlspecialchars($item['Name']) ?></a>
          </div>
          <div class="card-desc"><?= htmlspecialchars(substr($item['Description'],0,80)) ?>...</div>
          <div class="card-footer-row">
            <span class="card-date">
              <?php if ($sort === 'rating'): ?>
                <i class="fa fa-star" style="color:#F4A261;"></i> <?= number_format($item['Rating']??0,1) ?>/5
              <?php else: ?>
                <i class="fa fa-calendar-o"></i> <?= $item['Add_Date'] ?>
              <?php endif; ?>
            </span>
            <a href="items.php?itemid=<?= $item['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
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