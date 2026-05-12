<footer class="site-footer">
	<div class="container">
		<div class="row">
			<div class="col-md-4">
				<div class="footer-brand">Makan<span>Lokal</span></div>
				<p>Platform e-commerce khusus UMKM makanan lokal Tangerang. Temukan cita rasa autentik di satu tempat.</p>
			</div>
			<div class="col-md-4">
				<h4>Kategori Makanan</h4>
				<?php
					$footerCats = getAllFrom("*", "categories", "where parent = 0", "", "ID", "ASC");
					echo '<ul style="list-style:none;padding:0;margin:0;">';
					foreach ($footerCats as $fc) {
						echo '<li><a href="categories.php?pageid=' . $fc['ID'] . '">' . htmlspecialchars($fc['Name']) . '</a></li>';
					}
					echo '</ul>';
				?>
			</div>
			<div class="col-md-4">
				<h4>Informasi</h4>
				<ul style="list-style:none;padding:0;margin:0;">
					<li><a href="index.php">Beranda</a></li>
					<li><a href="login.php">Login / Daftar</a></li>
					<li><a href="newad.php">Jual Produk</a></li>
				</ul>
			</div>
		</div>
		<hr class="footer-divider">
		<div class="footer-bottom">
			<span>&copy; <?php echo date('Y') ?> MakanLokal &mdash; UMKM Makanan Lokal Tangerang</span>
			<span>Dibuat untuk Skripsi &mdash; Universitas Buddhi Dharma</span>
		</div>
	</div>
</footer>

<script src="<?php echo $js ?>jquery-1.12.1.min.js"></script>
<script src="<?php echo $js ?>jquery-ui.min.js"></script>
<script src="<?php echo $js ?>bootstrap.min.js"></script>
<script src="<?php echo $js ?>jquery.selectBoxIt.min.js"></script>
<script src="<?php echo $js ?>front.js"></script>
</body>
</html>