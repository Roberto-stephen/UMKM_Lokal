<?php
session_start();
$pageTitle = 'Kategori';
include 'init.php';

if (isset($_GET['pageid']) && is_numeric($_GET['pageid'])):
	$category = intval($_GET['pageid']);
	$allItems = getAllFrom("*", "items", "where Cat_ID = {$category}", "AND Approve = 1", "Item_ID");

	$stmtCat = $con->prepare("SELECT Name FROM categories WHERE ID = ?");
	$stmtCat->execute([$category]);
	$myCategory = $stmtCat->fetchColumn();
?>

<!-- PAGE BANNER -->
<div class="page-banner">
	<div class="container">
		<h1><?php echo htmlspecialchars($myCategory) ?></h1>
		<div class="breadcrumb-custom">
			<a href="index.php">Beranda</a> &rsaquo; <span><?php echo htmlspecialchars($myCategory) ?></span>
		</div>
	</div>
</div>

<div class="container">

	<!-- CATEGORY PILLS -->
	<div class="cat-pills">
		<a href="index.php" class="cat-pill">Semua</a>
		<?php
			$cats = getAllFrom("*", "categories", "where parent = 0", "", "ID", "ASC");
			foreach ($cats as $cat) {
				$active = ($cat['ID'] == $category) ? ' active' : '';
				echo '<a href="categories.php?pageid=' . $cat['ID'] . '" class="cat-pill' . $active . '">' . htmlspecialchars($cat['Name']) . '</a>';
			}
		?>
	</div>

	<div class="section-head">
		<h2><?php echo htmlspecialchars($myCategory) ?></h2>
		<span style="font-size:13px;color:#A8A29E;"><?php echo count($allItems) ?> produk ditemukan</span>
	</div>

	<?php if (!empty($allItems)): ?>
	<div class="product-grid">
		<?php foreach ($allItems as $item): ?>
		<div class="product-col">
			<div class="product-card">
				<div class="card-img">
					<span class="price-badge">Rp <?php echo number_format($item['Price'], 0, ',', '.') ?></span>
					<?php if (empty($item['picture'])): ?>
						<img src="admin/uploads/default.png" alt="<?php echo htmlspecialchars($item['Name']) ?>">
					<?php else: ?>
						<img src="admin/uploads/items/<?php echo htmlspecialchars($item['picture']) ?>" alt="<?php echo htmlspecialchars($item['Name']) ?>">
					<?php endif; ?>
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
		<p>Belum ada produk di kategori ini.</p>
	</div>
	<?php endif; ?>

</div>

<?php else: ?>
<div class="container" style="padding:60px 0;">
	<div class="alert alert-danger">Kategori tidak ditemukan.</div>
</div>
<?php endif; ?>

<?php include $tpl . 'footer.php'; ?>