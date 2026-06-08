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
            Rekomendasi CBF untuk kamu
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
              <span class="price-badge">Rp <?= number_format($rec['Price'],0,',','.') ?></span>
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
  <div class="section-head" style="margin-top:<?= $lastSearch && !empty($searchRecs) ? '28px' : '0' ?>;">
    <h2><?= $lastSearch ? 'Semua Produk' : 'Produk Tersedia' ?></h2>
  </div>

  <?php
    try {
        $stmt = $con->prepare("
            SELECT i.*, c.Name AS category_name
            FROM   items i
            LEFT   JOIN categories c ON c.ID = i.Cat_ID
            WHERE  i.Approve = 1
            ORDER  BY i.Item_ID DESC
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
            <span class="card-date"><i class="fa fa-calendar-o"></i> <?= $item['Add_Date'] ?></span>
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