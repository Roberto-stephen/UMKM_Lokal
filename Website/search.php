<?php
session_start();
$pageTitle = 'Hasil Pencarian';
include 'init.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!empty($q) && function_exists('saveLastSearch')) {
    saveLastSearch($q);
}

// -------------------------------------------------------
// SORT — Relevansi / Termurah / Termahal / Terbaru / Rating
// -------------------------------------------------------
$sortOptions = [
    'relevansi' => 'Relevansi',
    'termurah'  => 'Harga Termurah',
    'termahal'  => 'Harga Termahal',
    'terbaru'   => 'Terbaru',
    'rating'    => 'Rating Tertinggi',
];
$sort = isset($_GET['sort']) && isset($sortOptions[$_GET['sort']]) ? $_GET['sort'] : 'relevansi';

// -------------------------------------------------------
// FILTER (facet) yang aktif dari URL: kepedasan[], rasa[], kategori[]
// -------------------------------------------------------
$filterKeys = ['kepedasan', 'rasa', 'kategori'];
$activeFilters = [];
foreach ($filterKeys as $fk) {
    $vals = isset($_GET[$fk]) ? (array)$_GET[$fk] : [];
    $vals = array_map(fn($v) => preg_replace('/[^a-z0-9]/', '', strtolower((string)$v)), $vals);
    $vals = array_values(array_unique(array_filter($vals, fn($v) => $v !== '')));
    $activeFilters[$fk] = $vals;
}
$hasActiveFilter = (bool)array_filter($activeFilters, fn($v) => !empty($v));

// -------------------------------------------------------
// Bangun URL dengan tetap membawa state (q, sort, filter)
// -------------------------------------------------------
function buildSearchUrl($q, $sort, $filters, $overrides = []) {
    $params = ['q' => $q];
    foreach ($filters as $k => $vals) {
        if (!empty($vals)) $params[$k] = array_values($vals);
    }
    if ($sort && $sort !== 'relevansi') $params['sort'] = $sort;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return 'search.php?' . http_build_query($params);
}

// -------------------------------------------------------
// PROSES PENCARIAN
// -------------------------------------------------------
$candidates = [];
$facets     = [];
$filtered   = [];
$labelMaps  = function_exists('_facetLabels') ? _facetLabels() : ['kepedasan' => [], 'rasa' => []];

if (!empty($q) && function_exists('searchCandidates')) {
    $candidates = searchCandidates($q);                       // semua produk relevan (kolam)
    attachRatings($con, $candidates);                          // suntik rata-rata rating (dari tabel comments)
    $facets     = getSearchFacets($candidates, $activeFilters); // opsi filter + jumlah
    $filtered   = filterItemsByFacets($candidates, $activeFilters);
    $filtered   = sortItems($filtered, $sort);
}
$totalCandidates = count($candidates);
$totalFound      = count($filtered);

// Rekomendasi CBF (produk lain yang mungkin disukai)
$cbfRecs = [];
if (!empty($q) && function_exists('getRecommendationsByKeyword')) {
    $excludeIds = array_column($candidates, 'Item_ID');
    $cbfRecs = getRecommendationsByKeyword($q, 4, $excludeIds);
    attachRatings($con, $cbfRecs);
}

// attachRatings() & ratingBadge() kini didefinisikan di functions.php
// (dipakai bersama oleh search.php, index.php, categories.php).

// Helper kecil untuk chip atribut pada kartu
function kepedasanChip($item) {
    $labels = _facetLabels()['kepedasan'];
    $colors = ['tidakpedas' => '#1A5C2A', 'sedang' => '#C46A10', 'pedas' => '#B5272A'];
    $toks   = _attrTokens($item['cbf_kepedasan'] ?? '');
    $tok    = $toks[0] ?? '';
    if ($tok === '') return '';
    $label  = $labels[$tok] ?? ucfirst($tok);
    $color  = $colors[$tok] ?? '#4A4A6A';
    return '<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;color:'
         . $color . ';background:' . $color . '14;padding:2px 8px;border-radius:20px;">'
         . '<i class="fa fa-fire"></i>' . htmlspecialchars($label) . '</span>';
}
?>

<style>
/* ====== Faceted search layout (scoped .srch-*) ====== */
.srch-wrap { display:flex; gap:24px; align-items:flex-start; padding-bottom:40px; }
.srch-sidebar {
    flex:0 0 260px; width:260px; background:#fff; border:1px solid #E4E7F0;
    border-radius:14px; padding:18px 18px 8px; position:sticky; top:20px;
}
.srch-main { flex:1 1 auto; min-width:0; }
.srch-facet-group { border-bottom:1px solid #F0F2F5; padding:14px 0; }
.srch-facet-group:last-child { border-bottom:0; }
.srch-facet-title {
    font-size:12px; font-weight:800; letter-spacing:.4px; text-transform:uppercase;
    color:#1B2E5E; margin:0 0 10px; display:flex; align-items:center; gap:7px;
}
.srch-facet-title i { color:#B5272A; font-size:12px; }
.srch-opt {
    display:flex; align-items:center; gap:9px; padding:6px 6px; border-radius:8px;
    cursor:pointer; font-size:13px; color:#4A4A6A; transition:background .12s;
}
.srch-opt:hover { background:#F6F7FB; }
.srch-opt input { accent-color:#B5272A; width:15px; height:15px; cursor:pointer; }
.srch-opt .srch-cnt { margin-left:auto; font-size:11px; color:#9A9AB0; font-weight:600; }
.srch-opt.is-active { color:#B5272A; font-weight:700; }
.srch-sidebar-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
.srch-sidebar-head h3 { font-size:15px; margin:0; color:#1B2E5E; font-weight:800; }
.srch-reset { font-size:12px; color:#B5272A; text-decoration:none; font-weight:700; }
.srch-reset:hover { text-decoration:underline; }
.srch-chip {
    display:inline-flex; align-items:center; gap:6px; background:#FDECEA; color:#B5272A;
    border:1px solid #F3C6C7; font-size:12px; font-weight:600; padding:4px 10px;
    border-radius:20px; text-decoration:none;
}
.srch-chip i { font-size:10px; }
.srch-chip:hover { background:#f9d9da; }
.srch-toggle-btn { display:none; }

/* mobile */
@media (max-width: 860px) {
    .srch-wrap { flex-direction:column; }
    .srch-sidebar { position:static; width:100%; flex-basis:auto; display:none; }
    .srch-sidebar.is-open { display:block; }
    .srch-toggle-btn {
        display:inline-flex; align-items:center; gap:8px; background:#1B2E5E; color:#fff;
        border:0; border-radius:10px; padding:11px 16px; font-size:14px; font-weight:700;
        cursor:pointer; margin-bottom:14px; font-family:inherit;
    }
}
</style>

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

<?php else: ?>

  <!-- Tombol buka filter (khusus mobile) -->
  <button type="button" class="srch-toggle-btn" onclick="document.getElementById('srchSidebar').classList.toggle('is-open')">
    <i class="fa fa-sliders"></i> Filter<?php if ($hasActiveFilter) echo ' &bull; aktif'; ?>
  </button>

  <div class="srch-wrap">

    <!-- ============ SIDEBAR FILTER ============ -->
    <aside class="srch-sidebar" id="srchSidebar">
      <div class="srch-sidebar-head">
        <h3><i class="fa fa-filter" style="color:#B5272A;"></i> Filter</h3>
        <?php if ($hasActiveFilter): ?>
          <a class="srch-reset" href="<?= htmlspecialchars(buildSearchUrl($q, 'relevansi', [])) ?>">Reset</a>
        <?php endif; ?>
      </div>

      <form method="get" id="filterForm" action="search.php">
        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
        <?php if ($sort !== 'relevansi'): ?>
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <?php endif; ?>

        <?php
          $facetTitles = [
            'kepedasan' => ['Tingkat Kepedasan', 'fa-fire'],
            'rasa'      => ['Rasa', 'fa-cutlery'],
            'kategori'  => ['Kategori', 'fa-tag'],
          ];
          foreach ($facetTitles as $gkey => $meta):
            $opts = $facets[$gkey] ?? [];
            if (empty($opts)) continue;
        ?>
        <div class="srch-facet-group">
          <div class="srch-facet-title"><i class="fa <?= $meta[1] ?>"></i><?= $meta[0] ?></div>
          <?php foreach ($opts as $opt):
            $checked = in_array($opt['value'], $activeFilters[$gkey], true);
          ?>
          <label class="srch-opt<?= $checked ? ' is-active' : '' ?>">
            <input type="checkbox" name="<?= $gkey ?>[]" value="<?= htmlspecialchars($opt['value']) ?>"
                   <?= $checked ? 'checked' : '' ?>
                   onchange="document.getElementById('filterForm').submit()">
            <span><?= htmlspecialchars($opt['label']) ?></span>
            <span class="srch-cnt"><?= $opt['count'] ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty(array_filter($facets, fn($o) => !empty($o)))): ?>
          <p style="font-size:12px;color:#9A9AB0;padding:8px 0;">Tidak ada atribut untuk difilter.</p>
        <?php endif; ?>
      </form>
    </aside>

    <!-- ============ HASIL ============ -->
    <div class="srch-main">

      <!-- Baris info + sort -->
      <div style="margin:0 0 6px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div style="font-size:14px;color:#4A4A6A;">
          Ditemukan <strong style="color:#1B2E5E;"><?= $totalFound ?> produk</strong>
          untuk "<strong style="color:#B5272A;"><?= htmlspecialchars($q) ?></strong>"
          <?php if ($hasActiveFilter && $totalFound !== $totalCandidates): ?>
            <span style="color:#9A9AB0;">(dari <?= $totalCandidates ?> hasil)</span>
          <?php endif; ?>
        </div>

        <?php if ($totalCandidates > 0): ?>
        <div style="position:relative;display:inline-block;" id="sort-dropdown-wrap">
          <button type="button" onclick="toggleSortMenu()" id="sort-trigger-btn"
                  style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #DDE1EC;
                         border-radius:8px;padding:9px 14px;font-size:13px;font-weight:600;color:#1B2E5E;
                         cursor:pointer;white-space:nowrap;">
            <i class="fa fa-sort" style="color:#9A9AB0;font-size:12px;"></i>
            Urutkan: <span style="color:#B5272A;"><?= $sortOptions[$sort] ?></span>
            <i class="fa fa-chevron-down" style="font-size:10px;color:#9A9AB0;margin-left:2px;"></i>
          </button>
          <div id="sort-menu" style="display:none;position:absolute;top:calc(100% + 6px);right:0;
               background:#fff;border:1px solid #DDE1EC;border-radius:10px;
               box-shadow:0 8px 24px rgba(27,46,94,.12);min-width:200px;z-index:50;overflow:hidden;">
            <?php foreach ($sortOptions as $key => $label):
              $url = buildSearchUrl($q, $key, $activeFilters);
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

      <!-- Chip filter aktif -->
      <?php if ($hasActiveFilter): ?>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 4px;align-items:center;">
        <span style="font-size:12px;color:#9A9AB0;font-weight:600;">Filter aktif:</span>
        <?php
          foreach ($activeFilters as $g => $vals):
            foreach ($vals as $val):
              // cari label yang sesuai dari facets
              $lbl = $val;
              foreach (($facets[$g] ?? []) as $o) { if ($o['value'] === $val) { $lbl = $o['label']; break; } }
              if ($lbl === $val) {
                  if ($g === 'kepedasan') $lbl = $labelMaps['kepedasan'][$val] ?? ucfirst($val);
                  elseif ($g === 'rasa')  $lbl = $labelMaps['rasa'][$val] ?? ucfirst($val);
              }
              $newVals = array_values(array_diff($activeFilters[$g], [$val]));
              $rmUrl = buildSearchUrl($q, $sort, array_merge($activeFilters, [$g => $newVals]));
        ?>
          <a class="srch-chip" href="<?= htmlspecialchars($rmUrl) ?>">
            <?= htmlspecialchars($lbl) ?> <i class="fa fa-times"></i>
          </a>
        <?php endforeach; endforeach; ?>
        <a class="srch-reset" style="margin-left:4px;" href="<?= htmlspecialchars(buildSearchUrl($q, 'relevansi', [])) ?>">Hapus semua</a>
      </div>
      <?php endif; ?>

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

      <!-- Grid hasil -->
      <?php if ($totalFound === 0): ?>
        <div class="empty-state" style="padding:50px 0;text-align:center;">
          <i class="fa fa-search" style="font-size:38px;color:#DDE1EC;"></i>
          <?php if ($hasActiveFilter): ?>
            <p>Tidak ada produk yang cocok dengan filter untuk "<strong><?= htmlspecialchars($q) ?></strong>".</p>
            <a href="<?= htmlspecialchars(buildSearchUrl($q, 'relevansi', [])) ?>" style="color:#1B2E5E;font-weight:600;">← Hapus filter</a>
          <?php else: ?>
            <p>Tidak ada produk yang cocok dengan "<strong><?= htmlspecialchars($q) ?></strong>".</p>
            <a href="index.php" style="color:#1B2E5E;font-weight:600;">← Kembali ke beranda</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="product-grid" style="margin-top:14px;">
          <?php foreach ($filtered as $item): ?>
          <div class="product-col">
            <div class="product-card">
              <div class="card-img">
                <span class="price-badge">Rp <?= number_format($item['Price'], 0, ',', '.') ?></span>
                <img src="<?= empty($item['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/' . htmlspecialchars($item['picture']) ?>" alt="">
              </div>
              <div class="card-body">
                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px;">
                  <?= kepedasanChip($item) ?>
                  <?php if (!empty($item['category_name'])): ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;color:#9A9AB0;background:#F0F2F5;padding:2px 8px;border-radius:20px;">
                      <i class="fa fa-tag"></i><?= htmlspecialchars($item['category_name']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="card-title">
                  <a href="items.php?itemid=<?= $item['Item_ID'] ?>">
                    <?php
                      $name = htmlspecialchars($item['Name']);
                      if ($q !== '') {
                        $name = preg_replace('/(' . preg_quote($q, '/') . ')/i',
                          '<mark style="background:#FEF3C7;padding:0 2px;border-radius:2px;">$1</mark>', $name);
                      }
                      echo $name;
                    ?>
                  </a>
                </div>
                <?= ratingBadge($item) ?>
                <div class="card-desc"><?= htmlspecialchars(substr($item['Description'], 0, 80)) ?>...</div>
                <div class="card-footer-row">
                  <span class="card-date">
                    <?php if (($item['stok'] ?? 0) > 0): ?>
                      <i class="fa fa-check-circle" style="color:#1A5C2A;"></i> <span style="color:#1A5C2A;">Tersedia</span>
                    <?php else: ?>
                      <i class="fa fa-times-circle" style="color:#9B1C1C;"></i> <span style="color:#9B1C1C;">Habis</span>
                    <?php endif; ?>
                  </span>
                  <a href="items.php?itemid=<?= $item['Item_ID'] ?>" class="btn-detail">Lihat &rarr;</a>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Rekomendasi CBF -->
      <?php if (!empty($cbfRecs)): ?>
      <div class="rekomendasi-section" style="margin-top:40px;padding-top:26px;border-top:2px dashed #DDE1EC;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
          <div style="background:linear-gradient(135deg,#111E40,#1B2E5E);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
            <i class="fa fa-magic"></i> Rekomendasi CBF
          </div>
          <span style="font-size:13px;color:#4A4A6A;">Produk lain yang mungkin kamu suka...</span>
        </div>
        <div class="product-grid">
          <?php foreach ($cbfRecs as $rec): ?>
          <div class="product-col">
            <div class="product-card">
              <div class="card-img">
                <span class="price-badge">Rp <?= number_format($rec['Price'], 0, ',', '.') ?></span>
                <img src="<?= empty($rec['picture']) ? 'admin/uploads/default.png' : 'admin/uploads/items/' . htmlspecialchars($rec['picture']) ?>" alt="">
              </div>
              <div class="card-body">
                <div class="card-title">
                  <a href="items.php?itemid=<?= $rec['Item_ID'] ?>"><?= htmlspecialchars($rec['Name']) ?></a>
                </div>
                <?= ratingBadge($rec) ?>
                <div class="card-desc"><?= htmlspecialchars(substr($rec['Description'], 0, 80)) ?>...</div>
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

    </div><!-- /.srch-main -->
  </div><!-- /.srch-wrap -->

<?php endif; // end !empty($q) ?>

</div>

<?php include $tpl . 'footer.php'; ?>
