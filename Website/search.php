<?php
session_start();
$pageTitle = 'Hasil Pencarian';
include 'init.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!empty($q) && function_exists('saveLastSearch')) {
    saveLastSearch($q);
}

// -------------------------------------------------------
// Tokenize query: "Nasi Goreng" → ["nasi", "goreng"]
// -------------------------------------------------------
function tokenizeQuery($query) {
    $query = strtolower(trim($query));
    $query = preg_replace('/[^a-z0-9\s]/', ' ', $query);
    return array_filter(preg_split('/\s+/', $query), fn($t) => strlen($t) > 1);
}

$tokens = tokenizeQuery($q);

// -------------------------------------------------------
// SORT — ala ShopeeFood: Relevansi / Termurah / Termahal / Terbaru / Rating
// -------------------------------------------------------
$sortOptions = [
    'relevansi' => 'Relevansi',
    'termurah'  => 'Harga Termurah',
    'termahal'  => 'Harga Termahal',
    'terbaru'   => 'Terbaru',
    'rating'    => 'Rating Tertinggi',
];
$sort = isset($_GET['sort']) && isset($sortOptions[$_GET['sort']]) ? $_GET['sort'] : 'relevansi';
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

<div class="container" style="padding-bottom:40px;">

<?php if (empty($q)): ?>
  <div class="empty-state" style="padding:60px 0;">
    <i class="fa fa-search"></i>
    <p>Masukkan kata kunci untuk mencari produk.</p>
  </div>

<?php else: ?>

<?php
// -------------------------------------------------------
// FASE 0: Attribute Match — cari LANGSUNG ke kolom CBF
// (cbf_kepedasan, cbf_rasa, cbf_kategori, cbf_bahan)
// Ini yang membuat "tidak pedas" ketemu produk dengan
// cbf_kepedasan = "tidak-pedas", bukan hanya cocok ke Name.
// -------------------------------------------------------
$attrResults = [];
if (function_exists('searchByAttribute')) {
    $attrResults = searchByAttribute($q, 12);
}
$attrIds = array_column($attrResults, 'Item_ID');

// -------------------------------------------------------
// FASE 1: Exact phrase match (highest relevance)
// Contoh: "Nasi Goreng" → cari Name LIKE '%Nasi Goreng%'
// -------------------------------------------------------
$exactResults = [];
try {
    $excludeAttrClause = '';
    $execParams = ['%' . $q . '%'];
    if (!empty($attrIds)) {
        $ph = implode(',', array_fill(0, count($attrIds), '?'));
        $excludeAttrClause = "AND Item_ID NOT IN ($ph)";
        $execParams = array_merge($execParams, $attrIds);
    }
    $stmtExact = $con->prepare("
        SELECT * FROM items
        WHERE  Approve = 1
        AND    Name LIKE ?
        $excludeAttrClause
        ORDER  BY Name ASC
    ");
    $stmtExact->execute($execParams);
    $exactResults = $stmtExact->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$exactIds = array_column($exactResults, 'Item_ID');

// -------------------------------------------------------
// FASE 2: Per-token match (secondary relevance)
// Contoh: "Nasi Goreng" → cari Name LIKE '%Nasi%' OR Name LIKE '%Goreng%'
// Kemudian exclude yang sudah ada di exactResults
// -------------------------------------------------------
$tokenResults = [];
if (!empty($tokens)) {
    $likeClauses = [];
    $tokenParams = [];
    foreach ($tokens as $tok) {
        $likeClauses[] = 'Name LIKE ?';
        $tokenParams[]  = '%' . $tok . '%';
    }
    $tokenSql = implode(' OR ', $likeClauses);

    $excludeClause = '';
    $excludedSoFar = array_merge($attrIds, $exactIds);
    if (!empty($excludedSoFar)) {
        $ph = implode(',', array_fill(0, count($excludedSoFar), '?'));
        $excludeClause = "AND Item_ID NOT IN ($ph)";
        $tokenParams   = array_merge($tokenParams, $excludedSoFar);
    }

    try {
        $stmtToken = $con->prepare("
            SELECT * FROM items
            WHERE  Approve = 1
            AND    ($tokenSql)
            $excludeClause
            ORDER  BY Name ASC
        ");
        $stmtToken->execute($tokenParams);
        $tokenResults = $stmtToken->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$tokenIds = array_column($tokenResults, 'Item_ID');
$allShownIds = array_merge($attrIds, $exactIds, $tokenIds);
$totalFound  = count($allShownIds);

// -------------------------------------------------------
// SORTED VIEW — jika user pilih sort selain Relevansi,
// gabungkan semua hasil match (atribut+nama+token) jadi
// satu list flat, lalu urutkan sesuai pilihan.
// -------------------------------------------------------
$sortedResults = [];
if ($sort !== 'relevansi' && !empty($allShownIds)) {
    $ph = implode(',', array_fill(0, count($allShownIds), '?'));
    $orderSql = match($sort) {
        'termurah' => 'i.Price ASC',
        'termahal' => 'i.Price DESC',
        'terbaru'  => 'i.Item_ID DESC',
        'rating'   => 'i.Rating DESC',
        default    => 'i.Item_ID DESC',
    };
    try {
        $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Item_ID IN ($ph) AND i.Approve=1 ORDER BY $orderSql");
        $s->execute($allShownIds);
        $sortedResults = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// -------------------------------------------------------
// FASE 3: CBF Recommendations (hanya yang belum tampil)
// -------------------------------------------------------
$cbfRecs = [];
if (function_exists('getRecommendationsByKeyword')) {
    $cbfRecs = getRecommendationsByKeyword($q, 6, $allShownIds); // $allShownIds sudah termasuk attrIds
}
?>

<div style="margin:20px 0 4px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
  <div style="font-size:14px;color:#4A4A6A;">
    Ditemukan <strong style="color:#1B2E5E;"><?= $totalFound ?> produk</strong>
    untuk "<strong style="color:#B5272A;"><?= htmlspecialchars($q) ?></strong>"
    <a href="index.php" style="margin-left:12px;font-size:12px;color:#9A9AB0;">← Kembali ke beranda</a>
  </div>

  <?php if ($totalFound > 0): ?>
  <!-- ============================================================
       SORT DROPDOWN — custom (bukan native <select>) supaya tidak
       bentrok dengan CSS global situs dan tampilan lebih konsisten
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
        $url = 'search.php?q=' . urlencode($q) . '&sort=' . $key;
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
  <?php endif; ?>
</div>

<script>
function toggleSortMenu() {
  var menu = document.getElementById('sort-menu');
  menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
}
// Tutup dropdown saat klik di luar
document.addEventListener('click', function(e) {
  var wrap = document.getElementById('sort-dropdown-wrap');
  if (wrap && !wrap.contains(e.target)) {
    var menu = document.getElementById('sort-menu');
    if (menu) menu.style.display = 'none';
  }
});
</script>


<?php if ($totalFound === 0 && empty($attrResults) && empty($cbfRecs)): ?>
<!-- ---- Empty State ---- -->
<div class="empty-state" style="padding:60px 0;">
  <i class="fa fa-search"></i>
  <p>Tidak ada produk yang cocok dengan "<strong><?= htmlspecialchars($q) ?></strong>".</p>
  <a href="index.php" style="color:#1B2E5E;font-weight:600;">← Kembali ke beranda</a>
</div>

<?php else: ?>

<?php if ($sort !== 'relevansi'): ?>
<!-- ============================================================
     SORTED FLAT VIEW — saat user pilih sort selain Relevansi
     ============================================================ -->
<div class="product-grid">
  <?php foreach ($sortedResults as $item): ?>
  <div class="product-col">
    <div class="product-card">
      <div class="card-img">
        <span class="price-badge">Rp <?= number_format($item['Price'],0,',','.') ?></span>
        <img src="<?= empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="">
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
        <div class="card-desc"><?= htmlspecialchars(substr($item['Description'], 0, 80)) ?>...</div>
        <div class="card-footer-row">
          <span class="card-date">
            <?php if ($sort === 'rating'): ?>
              <i class="fa fa-star" style="color:#F4A261;"></i> <?= number_format($item['Rating']??0,1) ?>/5
            <?php else: ?>
              <i class="fa fa-cubes"></i>
              <?php if (($item['stok']??0) > 0) echo '<span style="color:#1A5C2A;">Stok: '.$item['stok'].'</span>';
                    else echo '<span style="color:#9B1C1C;">Habis</span>'; ?>
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
<!-- ============================================================
     RELEVANSI VIEW (default) — fase-based seperti sebelumnya
     ============================================================ -->

<?php if (!empty($attrResults)): ?>
<!-- ============================================================
     SECTION 0: ATRIBUT MATCH — cocok langsung dengan atribut CBF
     (kepedasan, rasa, kategori, bahan)
     ============================================================ -->
<div style="margin:20px 0 10px;display:flex;align-items:center;gap:10px;">
  <div style="background:linear-gradient(135deg,#1A5C2A,#2D8A45);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
    <i class="fa fa-bullseye"></i> Cocok Atribut "<?= htmlspecialchars($q) ?>"
  </div>
  <span style="font-size:13px;color:#4A4A6A;"><?= count($attrResults) ?> produk dengan karakteristik yang kamu cari</span>
</div>

<div style="background:linear-gradient(135deg,rgba(26,92,42,.06),rgba(45,138,69,.06));border:1.5px solid rgba(26,92,42,.2);border-radius:16px;padding:20px 16px;margin-bottom:8px;">
  <div class="product-grid">
    <?php foreach ($attrResults as $item):
      // Tentukan atribut mana yang match, untuk ditampilkan sebagai badge
      $matchedAttr = '';
      foreach (['cbf_kepedasan'=>'fa-fire','cbf_rasa'=>'fa-cutlery','cbf_kategori'=>'fa-tag','cbf_bahan'=>'fa-leaf'] as $field=>$icon) {
        if (!empty($item[$field])) { $matchedAttr = $item[$field]; $matchedIcon = $icon; break; }
      }
    ?>
    <div class="product-col">
      <div class="product-card" style="border:2px solid rgba(26,92,42,.2);position:relative;">
        <div style="position:absolute;top:8px;left:8px;z-index:2;background:#1A5C2A;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;">
          <i class="fa fa-bullseye"></i> Atribut Cocok
        </div>
        <div class="card-img">
          <span class="price-badge" style="left:auto;right:8px;">Rp <?= number_format($item['Price'],0,',','.') ?></span>
          <img src="<?= empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="">
        </div>
        <div class="card-body">
          <?php if ($matchedAttr): ?>
          <div style="font-size:10px;color:#1A5C2A;font-weight:600;margin-bottom:4px;">
            <i class="fa <?= $matchedIcon ?>"></i> <?= htmlspecialchars(str_replace('-', ' ', $matchedAttr)) ?>
          </div>
          <?php endif; ?>
          <div class="card-title">
            <a href="items.php?itemid=<?= $item['Item_ID'] ?>"><?= htmlspecialchars($item['Name']) ?></a>
          </div>
          <div class="card-desc"><?= htmlspecialchars(substr($item['Description'], 0, 80)) ?>...</div>
          <div class="card-footer-row">
            <span class="card-date"><i class="fa fa-cubes"></i>
              <?php if (($item['stok']??0) > 0) echo '<span style="color:#1A5C2A;">Stok: '.$item['stok'].'</span>';
                    else echo '<span style="color:#9B1C1C;">Habis</span>'; ?>
            </span>
            <a href="items.php?itemid=<?= $item['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($exactResults)): ?>
<!-- ============================================================
     SECTION 1: TOP RESULTS — exact phrase match (highlighted)
     ============================================================ -->
<div style="margin:20px 0 10px;display:flex;align-items:center;gap:10px;">
  <div style="background:#B5272A;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
    <i class="fa fa-star"></i> Top <?= count($exactResults) ?> Hasil
  </div>
  <span style="font-size:13px;color:#4A4A6A;">Produk paling sesuai dengan "<strong><?= htmlspecialchars($q) ?></strong>"</span>
</div>

<div style="background:linear-gradient(135deg,rgba(181,39,42,.06),rgba(244,162,97,.06));border:1.5px solid rgba(181,39,42,.2);border-radius:16px;padding:20px 16px;">
  <div class="product-grid">
    <?php foreach ($exactResults as $item): ?>
    <div class="product-col">
      <div class="product-card" style="border:2px solid rgba(181,39,42,.15);position:relative;">
        <!-- Badge "Top Match" -->
        <div style="position:absolute;top:8px;left:8px;z-index:2;background:#B5272A;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;">
          <i class="fa fa-star"></i> Top Match
        </div>
        <div class="card-img">
          <span class="price-badge" style="left:auto;right:8px;">Rp <?= number_format($item['Price'],0,',','.') ?></span>
          <img src="<?= empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="">
        </div>
        <div class="card-body">
          <div class="card-title">
            <a href="items.php?itemid=<?= $item['Item_ID'] ?>">
              <?php
                // Highlight keyword dalam nama produk
                $highlighted = preg_replace(
                  '/(' . preg_quote(htmlspecialchars($q), '/') . ')/i',
                  '<mark style="background:#FEF3C7;padding:0 2px;border-radius:2px;">$1</mark>',
                  htmlspecialchars($item['Name'])
                );
                echo $highlighted;
              ?>
            </a>
          </div>
          <div class="card-desc"><?= htmlspecialchars(substr($item['Description'], 0, 80)) ?>...</div>
          <div class="card-footer-row">
            <span class="card-date"><i class="fa fa-cubes"></i>
              <?php if ($item['stok'] > 0) echo '<span style="color:#1A5C2A;">Stok: '.$item['stok'].'</span>';
                    else echo '<span style="color:#9B1C1C;">Habis</span>'; ?>
            </span>
            <a href="items.php?itemid=<?= $item['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($tokenResults)): ?>
<!-- ============================================================
     SECTION 2: Produk Terkait — per-token match
     ============================================================ -->
<div style="margin:28px 0 12px;display:flex;align-items:center;gap:10px;">
  <div style="background:#E8ECF5;color:#1B2E5E;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
    <i class="fa fa-list"></i> Produk Terkait
  </div>
  <span style="font-size:13px;color:#4A4A6A;"><?= count($tokenResults) ?> produk lainnya yang mengandung kata kunci</span>
</div>

<div class="product-grid">
  <?php foreach ($tokenResults as $item): ?>
  <div class="product-col">
    <div class="product-card">
      <div class="card-img">
        <span class="price-badge">Rp <?= number_format($item['Price'],0,',','.') ?></span>
        <img src="<?= empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>" alt="">
      </div>
      <div class="card-body">
        <div class="card-title">
          <a href="items.php?itemid=<?= $item['Item_ID'] ?>">
            <?php
              // Highlight token-token dalam nama produk
              $name = htmlspecialchars($item['Name']);
              foreach ($tokens as $tok) {
                  $name = preg_replace(
                      '/(' . preg_quote($tok, '/') . ')/i',
                      '<mark style="background:#FEF3C7;padding:0 2px;border-radius:2px;">$1</mark>',
                      $name
                  );
              }
              echo $name;
            ?>
          </a>
        </div>
        <div class="card-desc"><?= htmlspecialchars(substr($item['Description'], 0, 80)) ?>...</div>
        <div class="card-footer-row">
          <span class="card-date"><i class="fa fa-calendar-o"></i> <?= $item['Add_Date'] ?></span>
          <a href="items.php?itemid=<?= $item['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($cbfRecs)): ?>
<!-- ============================================================
     SECTION 3: CBF Recommendations
     ============================================================ -->
<div class="rekomendasi-section" style="margin-top:36px;padding-top:28px;border-top:2px dashed #DDE1EC;">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
    <div style="background:linear-gradient(135deg,#111E40,#1B2E5E);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
      <i class="fa fa-magic"></i> Rekomendasi CBF
    </div>
    <span style="font-size:13px;color:#4A4A6A;">Mungkin kamu juga suka...</span>
  </div>
  <div class="product-grid">
    <?php foreach ($cbfRecs as $rec): ?>
    <div class="product-col">
      <div class="product-card">
        <div class="card-img">
          <span class="price-badge">Rp <?= number_format($rec['Price'],0,',','.') ?></span>
          <img src="<?= empty($rec['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/'.htmlspecialchars($rec['picture']) ?>" alt="">
        </div>
        <div class="card-body">
          <div class="card-title">
            <a href="items.php?itemid=<?= $rec['Item_ID'] ?>"><?= htmlspecialchars($rec['Name']) ?></a>
          </div>
          <div class="card-desc"><?= htmlspecialchars(substr($rec['Description'],0,80)) ?>...</div>
          <div class="card-footer-row">
            <span class="card-date"><?= $rec['Add_Date'] ?></span>
            <a href="items.php?itemid=<?= $rec['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; // end relevansi vs sorted branch ?>

<?php endif; // end $totalFound > 0 ?>
<?php endif; // end !empty($q) ?>

</div>

<?php include $tpl . 'footer.php'; ?>