<?php

	/*
	** Get All Function v2.0 - FIXED (parameter order diperbaiki)
	** $orderfield dipindah sebelum parameter opsional
	*/
	function getAllFrom($field, $table, $where = NULL, $and = NULL, $orderfield = 'ID', $ordering = "DESC") {

		global $con;

		$getAll = $con->prepare("SELECT $field FROM $table $where $and ORDER BY $orderfield $ordering");

		$getAll->execute();

		$all = $getAll->fetchAll();

		return $all;

	}

	/*
	** Check If User Is Not Activated
	*/
	function checkUserStatus($user) {

		global $con;

		$stmtx = $con->prepare("SELECT Username, RegStatus FROM users WHERE Username = ? AND RegStatus = 0");

		$stmtx->execute(array($user));

		return $stmtx->rowCount();

	}

	/*
	** Check Items Function v1.0
	*/
	function checkItem($select, $from, $value) {

		global $con;

		$statement = $con->prepare("SELECT $select FROM $from WHERE $select = ?");

		$statement->execute(array($value));

		return $statement->rowCount();

	}

	/*
	** Get Role Name
	** Mengembalikan nama role berdasarkan GroupID
	** 0 = Pembeli, 1 = Admin, 2 = Penjual
	*/
	function getRoleName($groupID) {
		switch ((int)$groupID) {
			case 1:  return 'Admin';
			case 2:  return 'Penjual';
			default: return 'Pembeli';
		}
	}

	/*
	** Check Seller Auth
	** Cek apakah user yang login adalah Penjual (GroupID = 2)
	*/
	function isSellerAuth() {
		if (!isset($_SESSION['Username']) || $_SESSION['GroupID'] != 2) {
			header('Location: index.php');
			exit();
		}
	}

	/*
	** Check Admin Auth
	** Cek apakah user yang login adalah Admin (GroupID = 1)
	*/
	function isAdminAuth() {
		if (!isset($_SESSION['Username']) || $_SESSION['GroupID'] != 1) {
			header('Location: index.php');
			exit();
		}
	}

	/*
	** Title Function v1.0
	*/
	function getTitle() {

		global $pageTitle;

		if (isset($pageTitle)) {
			echo $pageTitle;
		} else {
			echo 'E-Commerce UMKM';
		}
	}

	/*
	** Home Redirect Function v2.0
	*/
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

	/*
	** Count Number Of Items Function v1.0
	*/
	function countItems($item, $table) {

		global $con;

		$stmt2 = $con->prepare("SELECT COUNT($item) FROM $table");

		$stmt2->execute();

		return $stmt2->fetchColumn();

	}

	/*
	** Get Latest Records Function v1.0
	*/
	function getLatest($select, $table, $order, $limit = 5) {

		global $con;

		$getStmt = $con->prepare("SELECT $select FROM $table ORDER BY $order DESC LIMIT $limit");

		$getStmt->execute();

		return $getStmt->fetchAll();

	}

	/*
	** CBF: TF-IDF + Cosine Similarity
	** Menghitung rekomendasi produk berdasarkan kemiripan atribut
	** $currentItemId = ID produk yang sedang dilihat
	** $limit         = jumlah rekomendasi yang ditampilkan
	*/
	function getRecommendations($currentItemId, $limit = 4) {

		global $con;

		// Ambil semua produk yang sudah diapprove
		$stmt = $con->prepare("SELECT Item_ID, Name, cbf_kategori, cbf_rasa, cbf_bahan, cbf_kepedasan FROM items WHERE Approve = 1");
		$stmt->execute();
		$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (empty($allItems)) return [];

		// Gabungkan atribut CBF menjadi satu string dokumen per produk
		$documents = [];
		foreach ($allItems as $item) {
			$text = implode(' ', [
				$item['cbf_kategori'],
				$item['cbf_rasa'],
				$item['cbf_bahan'],
				$item['cbf_kepedasan'],
			]);
			$documents[$item['Item_ID']] = strtolower(trim($text));
		}

		// Hitung TF untuk setiap dokumen
		$tf = [];
		foreach ($documents as $id => $doc) {
			$words = preg_split('/\s+/', $doc, -1, PREG_SPLIT_NO_EMPTY);
			$total = count($words);
			if ($total === 0) { $tf[$id] = []; continue; }
			$freq = array_count_values($words);
			foreach ($freq as $word => $count) {
				$tf[$id][$word] = $count / $total;
			}
		}

		// Hitung IDF
		$allWords  = array_unique(array_merge(...array_map('array_keys', $tf)));
		$totalDocs = count($documents);
		$idf = [];
		foreach ($allWords as $word) {
			$docsWithWord = 0;
			foreach ($tf as $docTf) {
				if (isset($docTf[$word])) $docsWithWord++;
			}
			$idf[$word] = log($totalDocs / ($docsWithWord + 1)) + 1;
		}

		// Hitung TF-IDF vector per dokumen
		$tfidf = [];
		foreach ($tf as $id => $docTf) {
			foreach ($docTf as $word => $tfVal) {
				$tfidf[$id][$word] = $tfVal * $idf[$word];
			}
		}

		// Cosine Similarity antara produk saat ini dan semua produk lain
		if (!isset($tfidf[$currentItemId])) return [];

		$currentVec = $tfidf[$currentItemId];
		$scores     = [];

		foreach ($tfidf as $id => $vec) {
			if ($id == $currentItemId) continue;

			// Dot product
			$dot = 0;
			foreach ($currentVec as $word => $val) {
				if (isset($vec[$word])) $dot += $val * $vec[$word];
			}

			// Magnitude
			$magA = sqrt(array_sum(array_map(fn($v) => $v * $v, $currentVec)));
			$magB = sqrt(array_sum(array_map(fn($v) => $v * $v, $vec)));

			$scores[$id] = ($magA * $magB > 0) ? $dot / ($magA * $magB) : 0;
		}

		// Urutkan dari similarity tertinggi
		arsort($scores);
		$topIds = array_slice(array_keys($scores), 0, $limit);

		if (empty($topIds)) return [];

		// Ambil data produk hasil rekomendasi
		$placeholders = implode(',', array_fill(0, count($topIds), '?'));
		$stmtRec = $con->prepare("SELECT * FROM items WHERE Item_ID IN ($placeholders) AND Approve = 1");
		$stmtRec->execute($topIds);

		return $stmtRec->fetchAll(PDO::FETCH_ASSOC);

	}