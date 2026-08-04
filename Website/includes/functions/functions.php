<?php

/*
** ============================================================
** functions.php — MakanLokal UMKM
** ============================================================
*/

function getAllFrom($field, $table, $where = NULL, $and = NULL, $orderfield = 'ID', $ordering = "DESC") {
    global $con;
    $getAll = $con->prepare("SELECT $field FROM $table $where $and ORDER BY $orderfield $ordering");
    $getAll->execute();
    return $getAll->fetchAll();
}

function checkUserStatus($user) {
    global $con;
    $stmt = $con->prepare("SELECT Username, RegStatus FROM users WHERE Username = ? AND RegStatus = 0");
    $stmt->execute([$user]);
    return $stmt->rowCount();
}

function checkItem($select, $from, $value) {
    global $con;
    $stmt = $con->prepare("SELECT $select FROM $from WHERE $select = ?");
    $stmt->execute([$value]);
    return $stmt->rowCount();
}

function getRoleName($groupID) {
    switch ((int)$groupID) {
        case 1:  return 'Admin';
        case 2:  return 'Penjual';
        default: return 'Pembeli';
    }
}

function isAdminAuth() {
    if (!isset($_SESSION['user']) || (isset($_SESSION['GroupID']) && $_SESSION['GroupID'] != 1)) {
        header('Location: ../index.php'); exit();
    }
}

function isSellerAuth() {
    if (!isset($_SESSION['user']) || (isset($_SESSION['GroupID']) && $_SESSION['GroupID'] != 2)) {
        header('Location: index.php'); exit();
    }
}

function getTitle() {
    global $pageTitle;
    echo isset($pageTitle) ? $pageTitle : 'MakanLokal';
}

function redirectHome($theMsg, $url = null, $seconds = 3) {
    $url  = $url ?? 'index.php';
    $link = 'Homepage';
    echo $theMsg;
    echo "<div class='alert alert-info'>You Will Be Redirected to $link After $seconds Seconds.</div>";
    header("refresh:$seconds;url=$url");
    exit();
}

function countItems($item, $table) {
    global $con;
    $stmt = $con->prepare("SELECT COUNT($item) FROM $table");
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getLatest($select, $table, $order, $limit = 5) {
    global $con;
    $stmt = $con->prepare("SELECT $select FROM $table ORDER BY $order DESC LIMIT $limit");
    $stmt->execute();
    return $stmt->fetchAll();
}

/* ============================================================
** CBF — Content-Based Filtering
** ============================================================ */

// ------------------------------------------------------------
// Stopword bahasa Indonesia — kata umum yang harus diabaikan
// saat tokenisasi karena bukan atribut produk yang bermakna.
// Tanpa ini, kata seperti "sedang" (kata kerja bantu: "ketika
// sedang dahaga") bisa salah match dengan atribut kepedasan
// "sedang" (medium spicy) yang artinya berbeda sama sekali.
// ------------------------------------------------------------
function _cbfStopwords() {
    return [
        'yang','untuk','dengan','dan','atau','di','ke','dari','pada','ini','itu',
        'akan','adalah','dapat','bisa','juga','saja','saat','ketika','sedang',
        'sudah','telah','masih','agar','supaya','karena','sebab','jika','kalau',
        'tetapi','namun','serta','para','sang','si','nya','mu','ku','tak','tidak',
        'bukan','belum','pernah','sangat','sekali','lebih','paling','cukup',
        'banyak','sedikit','semua','setiap','beberapa','suatu','sebuah','satu',
        'dua','tiga','kita','kami','anda','saya','mereka','dia','ia',
        'cocok','enak','lezat','nikmat','khas','spesial','favorit','populer',
    ];
}

function _cbfTokenize($text) {
    $text = strtolower(trim($text));

    // ------------------------------------------------------------
    // FIX: Fusikan frasa negasi "tidak-X" / "tidak X" jadi 1 token
    // "tidakX". Tanpa ini, "tidak-pedas" akan ter-split jadi
    // ["tidak","pedas"] sehingga search "pedas" salah match dengan
    // item yang justru TIDAK pedas (karena hyphen jadi spasi).
    // ------------------------------------------------------------
    $text = preg_replace('/\btidak[-\s]+([a-z0-9]+)/u', 'tidak$1', $text);

    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

    $stopwords = array_flip(_cbfStopwords());

    return array_filter(
        preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY),
        fn($w) => strlen($w) > 1 && !isset($stopwords[$w])
    );
}

/* ------------------------------------------------------------
** _cbfNormalizeAttr — normalisasi value atribut CBF jadi 1 token
** "tidak-pedas" → "tidakpedas", "Pedas Sedang" → "pedassedang"
** Dipakai untuk membandingkan langsung dengan query yang
** sudah di-fusi oleh _cbfTokenize.
** ------------------------------------------------------------ */
function _cbfNormalizeAttr($value) {
    $value = strtolower(trim($value));
    $value = preg_replace('/\btidak[-\s]+([a-z0-9]+)/u', 'tidak$1', $value);
    $value = preg_replace('/[^a-z0-9]/', '', $value); // gabung semua jadi 1 token
    return $value;
}

/* ============================================================
** searchByAttribute($query, $limit)
** Pencarian LANGSUNG ke kolom atribut CBF
** (cbf_kepedasan, cbf_rasa, cbf_kategori, cbf_bahan).
** Ini yang membuat query "tidak pedas" bisa langsung
** menemukan produk dengan cbf_kepedasan = "tidak-pedas",
** bukan cuma mengandalkan TF-IDF similarity terhadap Name.
** ============================================================ */
function searchByAttribute($query, $limit = 12) {
    global $con;
    if (empty(trim($query))) return [];

    // Tokenize query dengan fusi negasi yang sama seperti CBF
    $rawQuery   = strtolower(trim($query));
    $rawQuery   = preg_replace('/\btidak[-\s]+([a-z0-9]+)/u', 'tidak$1', $rawQuery);
    $queryToken = preg_replace('/[^a-z0-9]/', '', $rawQuery); // query jadi 1 token utuh, cth "tidakpedas"

    $allItems = _cbfFetchItems();
    if (empty($allItems)) return [];

    // ------------------------------------------------------------
    // FIX BUG: dulu pakai substring (strpos) sehingga query "pedas"
    // salah match ke atribut "tidak-pedas" (karena "tidakpedas"
    // MENGANDUNG potongan "pedas"). Sekarang pakai pencocokan
    // TOKEN UTUH: "pedas" != "tidakpedas", jadi item yang justru
    // tidak pedas tidak lagi muncul saat mencari "pedas".
    // ------------------------------------------------------------
    $queryTokens = _attrTokens($query);
    $matchedIds  = [];
    if (!empty($queryTokens)) {
        foreach ($allItems as $item) {
            $attrTokens = array_merge(
                _attrTokens($item['cbf_kepedasan'] ?? ''),
                _attrTokens($item['cbf_rasa']      ?? ''),
                _attrTokens($item['cbf_kategori']  ?? ''),
                _attrTokens($item['cbf_bahan']     ?? '')
            );
            if (!empty(array_intersect($queryTokens, $attrTokens))) {
                $matchedIds[] = $item['Item_ID'];
            }
        }
    }

    if (empty($matchedIds)) return [];

    $ph = implode(',', array_fill(0, count($matchedIds), '?'));
    try {
        $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Item_ID IN ($ph) AND i.Approve=1 ORDER BY i.Item_ID DESC LIMIT $limit");
        $s->execute($matchedIds);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/* ============================================================
** ==============  FACETED SEARCH (FILTER ATRIBUT)  ============
** Blok fungsi di bawah ini menambahkan kemampuan:
**   1. searchCandidates()   -> ambil semua produk relevan + skor
**   2. getSearchFacets()     -> hitung opsi filter + jumlahnya
**   3. filterItemsByFacets() -> saring hasil sesuai filter aktif
** Semua exact-token, jadi "pedas" tidak nyangkut ke "tidak-pedas".
** ============================================================ */

/* ------------------------------------------------------------
** _attrTokens — pecah value atribut jadi array token bersih.
**   "tidak-pedas"  -> ["tidakpedas"]      (negasi disatukan dulu)
**   "pedas gurih"  -> ["pedas","gurih"]
**   "daging-sapi"  -> ["daging","sapi"]   (hyphen biasa dipisah)
** Urutan penting: satukan negasi DULU, baru ubah hyphen jadi spasi,
** supaya "tidak-pedas" tidak ikut terpecah jadi ["tidak","pedas"].
** ------------------------------------------------------------ */
function _attrTokens($value) {
    $value = strtolower(trim((string)$value));
    if ($value === '') return [];
    // 1) satukan negasi: "tidak pedas" / "tidak-pedas" -> "tidakpedas"
    $value = preg_replace('/\btidak[-\s]+([a-z0-9]+)/u', 'tidak$1', $value);
    // 2) sisa hyphen -> spasi  ("daging-sapi" -> "daging sapi")
    $value = str_replace('-', ' ', $value);
    // 3) buang karakter selain huruf/angka/spasi
    $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
    $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique($tokens));
}

/* ------------------------------------------------------------
** Label bawaan untuk value atribut agar tampil rapi di UI
** ------------------------------------------------------------ */
function _facetLabels() {
    return [
        'kepedasan' => [
            'tidakpedas' => 'Tidak Pedas',
            'sedang'     => 'Pedas Sedang',
            'pedas'      => 'Pedas',
        ],
        'rasa' => [
            'manis' => 'Manis', 'gurih' => 'Gurih', 'pedas' => 'Pedas',
            'asam'  => 'Asam',  'pahit' => 'Pahit', 'asin'  => 'Asin',
        ],
    ];
}

/* ------------------------------------------------------------
** _facetValuesForItem — nilai facet milik satu item, per grup.
**   kepedasan -> dari cbf_kepedasan (1 token)
**   rasa      -> dari cbf_rasa (bisa banyak token)
**   kategori  -> dari Cat_ID (kategori asli di tabel categories)
** ------------------------------------------------------------ */
function _facetValuesForItem($item) {
    return [
        'kepedasan' => _attrTokens($item['cbf_kepedasan'] ?? ''),
        'rasa'      => _attrTokens($item['cbf_rasa'] ?? ''),
        'kategori'  => (isset($item['Cat_ID']) && $item['Cat_ID'] !== '')
                        ? [(string)$item['Cat_ID']] : [],
    ];
}

/* _itemMatchesFacet — apakah item punya salah satu value pada grup ini (OR) */
function _itemMatchesFacet($item, $group, $vals) {
    if (empty($vals)) return true;
    $itemVals = _facetValuesForItem($item)[$group] ?? [];
    return count(array_intersect($vals, $itemVals)) > 0;
}

/* ------------------------------------------------------------
** searchCandidates($q) — kumpulkan SEMUA produk yang relevan
** dengan keyword, lengkap dengan skor relevansi. Dipakai sebagai
** "kolam" yang nanti disaring oleh filter facet.
**   - nama cocok frasa penuh        : +100
**   - token cocok di nama           : +12
**   - token cocok di deskripsi      : +3
**   - atribut cocok (exact token)   : +40  (mis. cari "pedas")
** ------------------------------------------------------------ */
function searchCandidates($q, $limit = 300) {
    $q = trim($q);
    if ($q === '') return [];

    $items = _cbfFetchItems();
    if (empty($items)) return [];

    $ql = strtolower($q);
    // token untuk pencocokan nama/deskripsi (substring biasa)
    $rawTokens = array_values(array_filter(
        preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', ' ', $ql), -1, PREG_SPLIT_NO_EMPTY),
        fn($t) => strlen($t) > 1
    ));
    // token untuk pencocokan atribut (exact, negasi menyatu)
    $qAttrTokens = _attrTokens($q);

    $out = [];
    foreach ($items as $it) {
        $name  = strtolower($it['Name'] ?? '');
        $desc  = strtolower($it['Description'] ?? '');
        $score = 0;
        $matchedAttr = '';

        if ($ql !== '' && strpos($name, $ql) !== false) $score += 100;

        foreach ($rawTokens as $t) {
            if (strpos($name, $t) !== false)       $score += 12;
            elseif (strpos($desc, $t) !== false)   $score += 3;
        }

        foreach (['cbf_kepedasan','cbf_rasa','cbf_kategori','cbf_bahan'] as $field) {
            $toks = _attrTokens($it[$field] ?? '');
            if (!empty(array_intersect($qAttrTokens, $toks))) {
                $score += 40;
                if ($matchedAttr === '' && !empty($it[$field])) $matchedAttr = $it[$field];
            }
        }

        if ($score > 0) {
            $it['_score']        = $score;
            $it['_matched_attr'] = $matchedAttr;
            $out[] = $it;
        }
    }

    // urutan default (relevansi): skor -> rating -> terbaru
    usort($out, function($a, $b) {
        if ($a['_score'] !== $b['_score']) return $b['_score'] <=> $a['_score'];
        $ra = (float)($a['Rating'] ?? 0); $rb = (float)($b['Rating'] ?? 0);
        if ($ra !== $rb) return $rb <=> $ra;
        return ((int)($b['Item_ID'] ?? 0)) <=> ((int)($a['Item_ID'] ?? 0));
    });

    return array_slice($out, 0, $limit);
}

/* ------------------------------------------------------------
** getSearchFacets($items, $activeFilters) — susun opsi filter
** beserta jumlahnya. Jumlah dihitung "cross-filter": untuk tiap
** grup, item dihitung setelah filter grup LAIN diterapkan, tapi
** filter grup itu sendiri diabaikan. Ini mencegah opsi jadi 0
** (dead-end) dan sesuai perilaku faceted search yang benar.
** ------------------------------------------------------------ */
function getSearchFacets($items, $activeFilters = []) {
    $labels = _facetLabels();
    $groups = ['kepedasan', 'rasa', 'kategori'];
    $result = [];

    foreach ($groups as $g) {
        // item yang lolos semua filter KECUALI grup $g
        $subset = array_filter($items, function($it) use ($activeFilters, $g) {
            foreach ($activeFilters as $fg => $vals) {
                if ($fg === $g || empty($vals)) continue;
                if (!_itemMatchesFacet($it, $fg, $vals)) return false;
            }
            return true;
        });

        $counts   = [];
        $labelMap = [];
        foreach ($subset as $it) {
            $vals = _facetValuesForItem($it)[$g] ?? [];
            foreach ($vals as $v) {
                $v = (string)$v;
                $counts[$v] = ($counts[$v] ?? 0) + 1;
                if ($g === 'kategori') {
                    $labelMap[$v] = $it['category_name'] ?? ('Kategori ' . $v);
                } else {
                    $labelMap[$v] = $labels[$g][$v] ?? ucfirst($v);
                }
            }
        }

        // urutan tampilan
        if ($g === 'kepedasan') {
            $order = ['tidakpedas' => 0, 'sedang' => 1, 'pedas' => 2];
            uksort($counts, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        } elseif ($g === 'kategori') {
            uksort($counts, fn($a, $b) => strcmp($labelMap[$a] ?? '', $labelMap[$b] ?? ''));
        } else {
            arsort($counts);
        }

        $opts = [];
        foreach ($counts as $val => $cnt) {
            $opts[] = [
                'value' => (string)$val,
                'label' => $labelMap[$val] ?? ucfirst((string)$val),
                'count' => $cnt,
            ];
        }
        $result[$g] = $opts;
    }
    return $result;
}

/* ------------------------------------------------------------
** filterItemsByFacets — saring $items sesuai filter aktif.
** Dalam 1 grup = OR (mis. Pedas ATAU Sedang).
** Antar grup      = AND (Kepedasan DAN Rasa DAN Kategori).
** ------------------------------------------------------------ */
function filterItemsByFacets($items, $activeFilters = []) {
    return array_values(array_filter($items, function($it) use ($activeFilters) {
        foreach ($activeFilters as $g => $vals) {
            if (empty($vals)) continue;
            if (!_itemMatchesFacet($it, $g, $vals)) return false;
        }
        return true;
    }));
}

/* ------------------------------------------------------------
** sortItems — urutkan array item sesuai pilihan sort.
** 'relevansi' membiarkan urutan dari searchCandidates apa adanya.
** ------------------------------------------------------------ */
function sortItems($items, $sort) {
    switch ($sort) {
        case 'termurah':
            usort($items, fn($a, $b) => ((int)$a['Price']) <=> ((int)$b['Price'])); break;
        case 'termahal':
            usort($items, fn($a, $b) => ((int)$b['Price']) <=> ((int)$a['Price'])); break;
        case 'terbaru':
            usort($items, fn($a, $b) => ((int)$b['Item_ID']) <=> ((int)$a['Item_ID'])); break;
        case 'rating':
            usort($items, fn($a, $b) => ((float)($b['_avg_rating'] ?? $b['Rating'] ?? 0)) <=> ((float)($a['_avg_rating'] ?? $a['Rating'] ?? 0))); break;
        // 'relevansi' -> biarkan
    }
    return $items;
}

/* ------------------------------------------------------------
** attachRatings — ambil rata-rata rating + jumlah ulasan untuk
** SEMUA item sekaligus (1 query), lalu tempel ke tiap baris sebagai
** _avg_rating & _review_count. Dipakai kartu produk di semua halaman.
** ------------------------------------------------------------ */
function attachRatings($con, &$items) {
    if (empty($items)) return;
    $ids = array_values(array_unique(array_map(fn($x) => (int)$x['Item_ID'], $items)));
    if (empty($ids)) return;
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $con->prepare("SELECT item_id, AVG(rating) a, COUNT(*) c FROM comments WHERE status=1 AND item_id IN ($ph) GROUP BY item_id");
    $stmt->execute($ids);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[(int)$r['item_id']] = ['a' => round((float)$r['a'], 1), 'c' => (int)$r['c']];
    }
    foreach ($items as &$it) {
        $id = (int)$it['Item_ID'];
        $it['_avg_rating']   = $map[$id]['a'] ?? 0;
        $it['_review_count'] = $map[$id]['c'] ?? 0;
    }
    unset($it);
}

/* ------------------------------------------------------------
** ratingBadge — potongan HTML bintang + angka untuk kartu produk.
**   Sudah dinilai  -> bintang oranye + "4.5 (12)"
**   Belum dinilai  -> bintang abu  + "Belum ada rating"
** ------------------------------------------------------------ */
function ratingBadge($item) {
    $avg  = (float)($item['_avg_rating'] ?? 0);
    $cnt  = (int)($item['_review_count'] ?? 0);
    $full = (int)round($avg);
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<span style="color:' . ($cnt > 0 && $i <= $full ? '#F4A261' : '#DDE1EC') . ';">&#9733;</span>';
    }
    $out = '<div style="display:flex;align-items:center;gap:5px;margin:2px 0 6px;font-size:12px;">'
         . '<span style="letter-spacing:1px;line-height:1;">' . $stars . '</span>';
    if ($cnt > 0) {
        $out .= '<span style="color:#1B2E5E;font-weight:700;">' . number_format($avg, 1) . '</span>'
              . '<span style="color:#9A9AB0;">(' . $cnt . ')</span>';
    } else {
        $out .= '<span style="color:#9A9AB0;">Belum ada rating</span>';
    }
    return $out . '</div>';
}

function _cbfBuildTFIDF($documents) {
    $tf = [];    foreach ($documents as $id => $doc) {
        $words = array_values(_cbfTokenize($doc));
        $total = count($words);
        if ($total === 0) { $tf[$id] = []; continue; }
        $freq = array_count_values($words);
        foreach ($freq as $word => $count) {
            $tf[$id][$word] = $count / $total;
        }
    }

    // FIX PHP 8.1+: array_values() dulu sebelum di-spread (...), karena
    // $tf punya key string (mis. "__QUERY__") yang akan dianggap named
    // argument oleh array_merge() jika di-spread langsung -> ArgumentCountError.
    $allWords  = array_unique(array_merge(...array_values(array_map('array_keys', $tf))));
    $totalDocs = count($documents);
    $idf       = [];
    foreach ($allWords as $word) {
        $n = count(array_filter($tf, fn($d) => isset($d[$word])));
        $idf[$word] = log(($totalDocs + 1) / ($n + 1)) + 1;
    }

    $tfidf = [];
    foreach ($tf as $id => $docTf) {
        foreach ($docTf as $word => $tfVal) {
            $tfidf[$id][$word] = $tfVal * ($idf[$word] ?? 1);
        }
    }
    return $tfidf;
}

function _cbfCosineSim($vecA, $vecB) {
    $dot = $magA = $magB = 0;
    foreach ($vecA as $word => $val) {
        if (isset($vecB[$word])) $dot += $val * $vecB[$word];
        $magA += $val * $val;
    }
    foreach ($vecB as $val) $magB += $val * $val;
    $magA = sqrt($magA); $magB = sqrt($magB);
    return ($magA * $magB > 0) ? $dot / ($magA * $magB) : 0;
}

/* ------------------------------------------------------------
** _cbfFetchItems — ambil semua item dengan kolom CBF + kategori
** ------------------------------------------------------------ */
function _cbfFetchItems() {
    global $con;
    try {
        $stmt = $con->prepare("
            SELECT i.*, c.Name AS category_name,
                   COALESCE(i.cbf_kategori,'')  AS cbf_kategori,
                   COALESCE(i.cbf_rasa,'')      AS cbf_rasa,
                   COALESCE(i.cbf_bahan,'')     AS cbf_bahan,
                   COALESCE(i.cbf_kepedasan,'') AS cbf_kepedasan
            FROM   items i
            LEFT   JOIN categories c ON c.ID = i.Cat_ID
            WHERE  i.Approve = 1
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        try {
            $stmt = $con->prepare("SELECT *, '' AS category_name, '' AS cbf_kategori, '' AS cbf_rasa, '' AS cbf_bahan, '' AS cbf_kepedasan FROM items WHERE Approve = 1");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) { return []; }
    }
}

/* _cbfBuildDoc — buat string dokumen dari satu item */
function _cbfBuildDoc($item) {
    $cbfData = trim(implode(' ', [
        $item['cbf_kategori'] ?? '',
        $item['cbf_rasa']     ?? '',
        $item['cbf_bahan']    ?? '',
        $item['cbf_kepedasan']?? '',
    ]));

    // Kalau cbf fields kosong, pakai nama + kategori + deskripsi sebagai fallback
    if (empty($cbfData)) {
        return implode(' ', [
            str_repeat(($item['Name']          ?? '') . ' ', 3),  // bobot nama 3x
            str_repeat(($item['category_name'] ?? '') . ' ', 2),  // bobot kategori 2x
            $item['Description'] ?? '',
        ]);
    }

    // Kalau ada cbf data: nama + cbf fields + deskripsi
    return implode(' ', [
        str_repeat(($item['Name'] ?? '') . ' ', 2),
        $cbfData,
        $item['Description'] ?? '',
    ]);
}

/* ============================================================
** getRecommendations($currentItemId)
** Rekomendasi di halaman detail produk (items.php)
** 4-phase fallback agar selalu ada hasil
** ============================================================ */
function getRecommendations($currentItemId, $limit = 4) {
    global $con;
    $allItems = _cbfFetchItems();
    if (empty($allItems)) return [];

    // --- Phase 1: TF-IDF CBF ---
    $documents = [];
    foreach ($allItems as $item) {
        $documents[$item['Item_ID']] = _cbfBuildDoc($item);
    }

    $tfidf = _cbfBuildTFIDF($documents);
    $scores = [];
    if (isset($tfidf[$currentItemId])) {
        foreach ($tfidf as $id => $vec) {
            if ($id == $currentItemId) continue;
            $sim = _cbfCosineSim($tfidf[$currentItemId], $vec);
            if ($sim > 0.05) $scores[$id] = $sim; // threshold kecil
        }
    }

    if (!empty($scores)) {
        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $limit);
        $ph     = implode(',', array_fill(0, count($topIds), '?'));
        try {
            $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Item_ID IN ($ph) AND i.Approve=1");
            $s->execute($topIds);
            $res = $s->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($res)) return $res;
        } catch (Exception $e) {}
    }

    // --- Phase 2: Same category ---
    $currentCatId = null;
    foreach ($allItems as $item) {
        if ($item['Item_ID'] == $currentItemId) { $currentCatId = $item['Cat_ID']; break; }
    }
    if ($currentCatId) {
        try {
            $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Cat_ID=? AND i.Item_ID!=? AND i.Approve=1 ORDER BY RAND() LIMIT $limit");
            $s->execute([$currentCatId, $currentItemId]);
            $res = $s->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($res)) return $res;
        } catch (Exception $e) {}
    }

    // --- Phase 3: Latest items ---
    try {
        $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Item_ID!=? AND i.Approve=1 ORDER BY i.Item_ID DESC LIMIT $limit");
        $s->execute([$currentItemId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}

/* ============================================================
** getRecommendationsByKeyword($keyword)
** Rekomendasi berdasar keyword pencarian
** Dipakai di search.php dan index.php (beranda)
** 4-phase fallback — SELALU return hasil jika DB tidak kosong
** ============================================================ */
function getRecommendationsByKeyword($keyword, $limit = 8, $excludeIds = []) {
    global $con;
    if (empty(trim($keyword))) return [];

    $allItems = _cbfFetchItems();
    if (empty($allItems)) return [];

    $results    = [];
    $usedIds    = $excludeIds;

    /* ---- Phase 1: TF-IDF CBF ---- */
    $documents  = [];
    foreach ($allItems as $item) {
        $documents[$item['Item_ID']] = _cbfBuildDoc($item);
    }
    $queryId             = '__QUERY__';
    $documents[$queryId] = $keyword;

    $tfidf = _cbfBuildTFIDF($documents);

    if (isset($tfidf[$queryId])) {
        $queryVec = $tfidf[$queryId];
        $scores   = [];
        foreach ($tfidf as $id => $vec) {
            if ($id === $queryId || in_array($id, $usedIds)) continue;
            $sim = _cbfCosineSim($queryVec, $vec);
            if ($sim > 0) $scores[$id] = $sim;
        }
        if (!empty($scores)) {
            arsort($scores);
            $topIds = array_slice(array_keys($scores), 0, $limit);
            $ph = implode(',', array_fill(0, count($topIds), '?'));
            try {
                $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Item_ID IN ($ph) AND i.Approve=1");
                $s->execute($topIds);
                $results = $s->fetchAll(PDO::FETCH_ASSOC);
                $usedIds = array_merge($usedIds, array_column($results, 'Item_ID'));
            } catch (Exception $e) {}
        }
    }

    if (count($results) >= $limit) return array_slice($results, 0, $limit);

    /* ---- Phase 2: Token LIKE per kata ---- */
    $tokens = array_values(_cbfTokenize($keyword));
    if (!empty($tokens)) {
        $likeClauses = array_map(fn($t) => 'i.Name LIKE ?', $tokens);
        $params      = array_map(fn($t) => "%$t%", $tokens);
        if (!empty($usedIds)) {
            $ph = implode(',', array_fill(0, count($usedIds), '?'));
            $excludeSql = "AND i.Item_ID NOT IN ($ph)";
            $params = array_merge($params, $usedIds);
        } else {
            $excludeSql = '';
        }
        $need = $limit - count($results);
        try {
            $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Approve=1 AND (" . implode(' OR ', $likeClauses) . ") $excludeSql ORDER BY i.Item_ID DESC LIMIT $need");
            $s->execute($params);
            $more    = $s->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $more);
            $usedIds = array_merge($usedIds, array_column($more, 'Item_ID'));
        } catch (Exception $e) {}
    }

    if (count($results) >= $limit) return array_slice($results, 0, $limit);

    /* ---- Phase 3: Same category sebagai keyword hits ---- */
    $catIds = [];
    foreach ($allItems as $item) {
        foreach (_cbfTokenize($keyword) as $tok) {
            if (stripos($item['Name'], $tok) !== false && !empty($item['Cat_ID'])) {
                $catIds[] = $item['Cat_ID'];
            }
        }
    }
    $catIds = array_unique($catIds);

    if (!empty($catIds)) {
        $need = $limit - count($results);
        $phCat = implode(',', array_fill(0, count($catIds), '?'));
        $params = $catIds;
        if (!empty($usedIds)) {
            $phEx = implode(',', array_fill(0, count($usedIds), '?'));
            $excludeSql = "AND i.Item_ID NOT IN ($phEx)";
            $params = array_merge($params, $usedIds);
        } else { $excludeSql = ''; }
        try {
            $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Cat_ID IN ($phCat) AND i.Approve=1 $excludeSql ORDER BY RAND() LIMIT $need");
            $s->execute($params);
            $more    = $s->fetchAll(PDO::FETCH_ASSOC);
            $results = array_merge($results, $more);
            $usedIds = array_merge($usedIds, array_column($more, 'Item_ID'));
        } catch (Exception $e) {}
    }

    if (count($results) >= $limit) return array_slice($results, 0, $limit);

    /* ---- Phase 4: Latest items (last resort) ---- */
    $need = $limit - count($results);
    if (!empty($usedIds)) {
        $phEx = implode(',', array_fill(0, count($usedIds), '?'));
        $excludeSql = "AND i.Item_ID NOT IN ($phEx)";
        $params = $usedIds;
    } else { $excludeSql = ''; $params = []; }
    try {
        $s = $con->prepare("SELECT i.*, c.Name AS category_name FROM items i LEFT JOIN categories c ON c.ID=i.Cat_ID WHERE i.Approve=1 $excludeSql ORDER BY i.Item_ID DESC LIMIT $need");
        $s->execute($params);
        $more    = $s->fetchAll(PDO::FETCH_ASSOC);
        $results = array_merge($results, $more);
    } catch (Exception $e) {}

    return array_slice($results, 0, $limit);
}

/* ------------------------------------------------------------
** saveLastSearch / getLastSearch
** ------------------------------------------------------------ */
function saveLastSearch($keyword) {
    if (!empty(trim($keyword))) {
        $_SESSION['last_search']      = trim($keyword);
        $_SESSION['last_search_time'] = time();
    }
}

function getLastSearch() {
    if (isset($_SESSION['last_search'], $_SESSION['last_search_time'])) {
        if ((time() - $_SESSION['last_search_time']) < 1800) {
            return $_SESSION['last_search'];
        }
        unset($_SESSION['last_search'], $_SESSION['last_search_time']);
    }
    return null;
}