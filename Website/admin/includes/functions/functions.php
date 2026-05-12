<?php

/*
** ============================================================
** functions.php — MakanLokal UMKM
** Berisi semua fungsi utama: database, auth, CBF, search
** ============================================================
*/

/* ------------------------------------------------------------
** getAllFrom — ambil semua record dari tabel
** ------------------------------------------------------------ */
function getAllFrom($field, $table, $where = NULL, $and = NULL, $orderfield = 'ID', $ordering = "DESC") {
    global $con;
    $getAll = $con->prepare("SELECT $field FROM $table $where $and ORDER BY $orderfield $ordering");
    $getAll->execute();
    return $getAll->fetchAll();
}

/* ------------------------------------------------------------
** checkUserStatus — cek apakah user belum diaktifkan
** ------------------------------------------------------------ */
function checkUserStatus($user) {
    global $con;
    $stmt = $con->prepare("SELECT Username, RegStatus FROM users WHERE Username = ? AND RegStatus = 0");
    $stmt->execute(array($user));
    return $stmt->rowCount();
}

/* ------------------------------------------------------------
** checkItem — cek apakah nilai ada di tabel
** ------------------------------------------------------------ */
function checkItem($select, $from, $value) {
    global $con;
    $stmt = $con->prepare("SELECT $select FROM $from WHERE $select = ?");
    $stmt->execute(array($value));
    return $stmt->rowCount();
}

/* ------------------------------------------------------------
** getRoleName — konversi GroupID ke nama role
** 0 = Pembeli, 1 = Admin, 2 = Penjual
** ------------------------------------------------------------ */
function getRoleName($groupID) {
    switch ((int)$groupID) {
        case 1:  return 'Admin';
        case 2:  return 'Penjual';
        default: return 'Pembeli';
    }
}

/* ------------------------------------------------------------
** isAdminAuth — proteksi halaman admin
** ------------------------------------------------------------ */
function isAdminAuth() {
    if (!isset($_SESSION['user']) || (isset($_SESSION['GroupID']) && $_SESSION['GroupID'] != 1)) {
        header('Location: ../index.php');
        exit();
    }
}

/* ------------------------------------------------------------
** isSellerAuth — proteksi halaman penjual
** ------------------------------------------------------------ */
function isSellerAuth() {
    if (!isset($_SESSION['user']) || (isset($_SESSION['GroupID']) && $_SESSION['GroupID'] != 2)) {
        header('Location: index.php');
        exit();
    }
}

/* ------------------------------------------------------------
** getTitle — echo judul halaman
** ------------------------------------------------------------ */
function getTitle() {
    global $pageTitle;
    echo isset($pageTitle) ? $pageTitle : 'MakanLokal';
}

/* ------------------------------------------------------------
** redirectHome — redirect dengan pesan
** ------------------------------------------------------------ */
function redirectHome($theMsg, $url = null, $seconds = 3) {
    if ($url === null) {
        $url  = 'index.php';
        $link = 'Homepage';
    } else {
        if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== '') {
            $url  = $_SERVER['HTTP_REFERER'];
            $link = 'Previous Page';
        } else {
            $url  = 'index.php';
            $link = 'Homepage';
        }
    }
    echo $theMsg;
    echo "<div class='alert alert-info'>You Will Be Redirected to $link After $seconds Seconds.</div>";
    header("refresh:$seconds;url=$url");
    exit();
}

/* ------------------------------------------------------------
** countItems — hitung jumlah record di tabel
** ------------------------------------------------------------ */
function countItems($item, $table) {
    global $con;
    $stmt = $con->prepare("SELECT COUNT($item) FROM $table");
    $stmt->execute();
    return $stmt->fetchColumn();
}

/* ------------------------------------------------------------
** getLatest — ambil record terbaru
** ------------------------------------------------------------ */
function getLatest($select, $table, $order, $limit = 5) {
    global $con;
    $stmt = $con->prepare("SELECT $select FROM $table ORDER BY $order DESC LIMIT $limit");
    $stmt->execute();
    return $stmt->fetchAll();
}

/* ============================================================
** BAGIAN CBF (Content-Based Filtering)
** ============================================================ */

/* ------------------------------------------------------------
** _cbfTokenize — pecah teks jadi array kata
** ------------------------------------------------------------ */
function _cbfTokenize($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    return preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
}

/* ------------------------------------------------------------
** _cbfBuildTFIDF — hitung vector TF-IDF dari array dokumen
** $documents = [ id => 'teks dokumen', ... ]
** ------------------------------------------------------------ */
function _cbfBuildTFIDF($documents) {
    // Hitung TF
    $tf = [];
    foreach ($documents as $id => $doc) {
        $words = _cbfTokenize($doc);
        $total = count($words);
        if ($total === 0) { $tf[$id] = []; continue; }
        $freq = array_count_values($words);
        foreach ($freq as $word => $count) {
            $tf[$id][$word] = $count / $total;
        }
    }

    // Kumpulkan semua kata unik
    $allWords = [];
    foreach ($tf as $docTf) {
        $allWords = array_merge($allWords, array_keys($docTf));
    }
    $allWords  = array_unique($allWords);
    $totalDocs = count($documents);

    // Hitung IDF
    $idf = [];
    foreach ($allWords as $word) {
        $n = 0;
        foreach ($tf as $docTf) {
            if (isset($docTf[$word])) $n++;
        }
        $idf[$word] = log(($totalDocs + 1) / ($n + 1)) + 1;
    }

    // Hitung TF-IDF
    $tfidf = [];
    foreach ($tf as $id => $docTf) {
        foreach ($docTf as $word => $tfVal) {
            $tfidf[$id][$word] = $tfVal * ($idf[$word] ?? 1);
        }
    }

    return $tfidf;
}

/* ------------------------------------------------------------
** _cbfCosineSim — hitung cosine similarity antara dua vector
** ------------------------------------------------------------ */
function _cbfCosineSim($vecA, $vecB) {
    $dot  = 0;
    $magA = 0;
    $magB = 0;

    foreach ($vecA as $word => $val) {
        if (isset($vecB[$word])) $dot += $val * $vecB[$word];
        $magA += $val * $val;
    }
    foreach ($vecB as $val) {
        $magB += $val * $val;
    }

    $magA = sqrt($magA);
    $magB = sqrt($magB);

    return ($magA * $magB > 0) ? $dot / ($magA * $magB) : 0;
}

/* ------------------------------------------------------------
** getRecommendations($currentItemId, $limit)
** Rekomendasi berdasarkan Item ID
** Dipakai di halaman detail produk (items.php)
** ------------------------------------------------------------ */
function getRecommendations($currentItemId, $limit = 4) {
    global $con;

    $stmt = $con->prepare("SELECT Item_ID, Name, cbf_kategori, cbf_rasa, cbf_bahan, cbf_kepedasan FROM items WHERE Approve = 1");
    $stmt->execute();
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($allItems)) return [];

    // Bangun dokumen dari atribut CBF
    $documents = [];
    foreach ($allItems as $item) {
        $documents[$item['Item_ID']] = implode(' ', [
            $item['Name'],
            $item['cbf_kategori'],
            $item['cbf_rasa'],
            $item['cbf_bahan'],
            $item['cbf_kepedasan'],
        ]);
    }

    $tfidf = _cbfBuildTFIDF($documents);
    if (!isset($tfidf[$currentItemId])) return [];

    $currentVec = $tfidf[$currentItemId];
    $scores     = [];
    foreach ($tfidf as $id => $vec) {
        if ($id == $currentItemId) continue;
        $scores[$id] = _cbfCosineSim($currentVec, $vec);
    }

    arsort($scores);
    $topIds = array_slice(array_keys($scores), 0, $limit);
    if (empty($topIds)) return [];

    $placeholders = implode(',', array_fill(0, count($topIds), '?'));
    $stmtRec = $con->prepare("SELECT * FROM items WHERE Item_ID IN ($placeholders) AND Approve = 1");
    $stmtRec->execute($topIds);
    return $stmtRec->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------
** getRecommendationsByKeyword($keyword, $limit)
** Rekomendasi berdasarkan kata kunci pencarian
** Dipakai di homepage untuk section "Karena kamu mencari..."
** ------------------------------------------------------------ */
function getRecommendationsByKeyword($keyword, $limit = 8) {
    global $con;

    if (empty(trim($keyword))) return [];

    $stmt = $con->prepare("SELECT * FROM items WHERE Approve = 1");
    $stmt->execute();
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($allItems)) return [];

    // Bangun dokumen dari semua atribut + nama + deskripsi
    $documents = [];
    foreach ($allItems as $item) {
        $documents[$item['Item_ID']] = implode(' ', [
            $item['Name'],
            $item['cbf_kategori'],
            $item['cbf_rasa'],
            $item['cbf_bahan'],
            $item['cbf_kepedasan'],
            $item['Description'],
        ]);
    }

    // Tambahkan keyword sebagai dokumen query
    $queryId             = 'QUERY_KEY';
    $documents[$queryId] = $keyword;

    $tfidf = _cbfBuildTFIDF($documents);

    if (!isset($tfidf[$queryId])) {
        // Fallback LIKE search
        $like = '%' . $keyword . '%';
        $fb   = $con->prepare("SELECT * FROM items WHERE Approve = 1 AND (Name LIKE ? OR cbf_bahan LIKE ? OR cbf_rasa LIKE ? OR cbf_kategori LIKE ?) ORDER BY Item_ID DESC LIMIT $limit");
        $fb->execute([$like, $like, $like, $like]);
        return $fb->fetchAll(PDO::FETCH_ASSOC);
    }

    $queryVec = $tfidf[$queryId];
    $scores   = [];
    foreach ($tfidf as $id => $vec) {
        if ($id === $queryId) continue;
        $sim = _cbfCosineSim($queryVec, $vec);
        if ($sim > 0) $scores[$id] = $sim;
    }

    if (empty($scores)) {
        // Fallback LIKE search jika semua similarity = 0
        $like = '%' . $keyword . '%';
        $fb   = $con->prepare("SELECT * FROM items WHERE Approve = 1 AND (Name LIKE ? OR cbf_bahan LIKE ? OR cbf_rasa LIKE ? OR cbf_kategori LIKE ?) ORDER BY Item_ID DESC LIMIT $limit");
        $fb->execute([$like, $like, $like, $like]);
        return $fb->fetchAll(PDO::FETCH_ASSOC);
    }

    arsort($scores);
    $topIds = array_slice(array_keys($scores), 0, $limit);

    $placeholders = implode(',', array_fill(0, count($topIds), '?'));
    $stmtRec = $con->prepare("SELECT * FROM items WHERE Item_ID IN ($placeholders) AND Approve = 1");
    $stmtRec->execute($topIds);
    return $stmtRec->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------
** saveLastSearch($keyword)
** Simpan kata kunci pencarian terakhir ke session
** ------------------------------------------------------------ */
function saveLastSearch($keyword) {
    if (!empty(trim($keyword))) {
        $_SESSION['last_search']      = trim($keyword);
        $_SESSION['last_search_time'] = time();
    }
}

/* ------------------------------------------------------------
** getLastSearch()
** Ambil kata kunci pencarian terakhir (kadaluarsa 30 menit)
** Return: string keyword atau null
** ------------------------------------------------------------ */
function getLastSearch() {
    if (isset($_SESSION['last_search'], $_SESSION['last_search_time'])) {
        // Kadaluarsa setelah 30 menit
        if ((time() - $_SESSION['last_search_time']) < 1800) {
            return $_SESSION['last_search'];
        }
        // Sudah kadaluarsa, hapus
        unset($_SESSION['last_search'], $_SESSION['last_search_time']);
    }
    return null;
}