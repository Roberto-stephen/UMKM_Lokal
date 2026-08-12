<?php
ob_start(); session_start();
$pageTitle = 'Checkout';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';
requireBuyer(); // hanya Pembeli yang bisa checkout
if (empty($_SESSION['cart'])) { header('Location: cart.php'); exit(); }

$cart  = $_SESSION['cart'];
$total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
$errors = [];

// Alamat pembeli dari profil (untuk prefill). Wajib diisi sebelum memesan.
$myAlamat = '';
try {
    $ua = $con->prepare("SELECT alamat FROM users WHERE UserID=?");
    $ua->execute([$_SESSION['uid']]);
    $myAlamat = (string)($ua->fetchColumn() ?: '');
} catch (Exception $e) { $myAlamat = ''; }

// Pengambilan = self-pickup (pilih waktu). Alamat pembeli WAJIB sebagai kontak.
$pickupOptions = [
    'Secepatnya (ASAP)',
    'Siang (11:00-14:00)',
    'Sore (15:00-18:00)',
    'Malam (18:00-21:00)',
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $waktu_ambil  = trim($_POST['waktu_ambil'] ?? '');
    $catatan      = trim($_POST['catatan'] ?? '');
    $metode_bayar = trim($_POST['metode_bayar'] ?? '');
    $alamatUser   = trim($_POST['alamat'] ?? '');   // alamat pembeli (WAJIB)

    if ($alamatUser === '')                          $errors[] = 'Alamat wajib diisi sebelum memesan.';
    if (!in_array($waktu_ambil, $pickupOptions, true)) $errors[] = 'Waktu pengambilan wajib dipilih.';
    if (empty($metode_bayar)) $errors[] = 'Metode pembayaran wajib dipilih.';

    // orders.alamat = alamat pembeli; waktu ambil disimpan di catatan.
    $alamat  = $alamatUser;
    $catatan = 'Ambil: ' . $waktu_ambil . ($catatan !== '' ? ' — ' . $catatan : '');

    // Validasi ketersediaan (Habis) + BLOKIR produk milik sendiri
    foreach ($cart as $ci) {
        $stmtStok = $con->prepare("SELECT stok, Name, Member_ID FROM items WHERE Item_ID=?");
        $stmtStok->execute([$ci['item_id']]);
        $produk = $stmtStok->fetch();
        if ($produk && $produk['stok'] <= 0) {
            $errors[] = "Produk '{$produk['Name']}' sedang Habis. Silakan hapus dari keranjang.";
        }
        // Penjual tidak boleh membeli produknya sendiri
        if ($produk && (int)$produk['Member_ID'] === (int)($_SESSION['uid'] ?? 0)) {
            $errors[] = "Kamu tidak bisa membeli produkmu sendiri: '{$produk['Name']}'. Silakan hapus dari keranjang.";
        }
    }

    $bukti_bayar = '';
    if (!empty($_FILES['bukti_bayar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','pdf'])) {
            $errors[] = 'Format bukti bayar tidak didukung (JPG/PNG/PDF).';
        } else {
            $bukti_bayar = 'bukti_'.rand(1000,9999999).'.'.$ext;
            move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], 'admin/uploads/items/'.$bukti_bayar);
        }
    }

    if (empty($errors)) {
        // Tentukan status berdasarkan metode bayar
        $isCOD = ($metode_bayar === 'COD / Bayar di Tempat');
        if ($isCOD) {
            $status = 'Diproses'; // COD langsung diproses
        } elseif ($bukti_bayar) {
            $status = 'Menunggu Konfirmasi';
        } else {
            $status = 'Belum Dibayar';
        }

        // Simpan order
        $ins = $con->prepare("INSERT INTO orders (user_id,total_harga,status,metode_bayar,bukti_bayar,catatan,alamat) VALUES (?,?,?,?,?,?,?)");
        $ins->execute([$_SESSION['uid'], $total, $status, $metode_bayar, $bukti_bayar, $catatan, $alamat]);
        $order_id = $con->lastInsertId();

        // Simpan alamat ke profil pembeli agar otomatis terisi di pesanan berikutnya
        try { $con->prepare("UPDATE users SET alamat=? WHERE UserID=?")->execute([htmlspecialchars($alamatUser), $_SESSION['uid']]); }
        catch (Exception $e) { /* abaikan */ }

        // Simpan order items (stok tidak dikurangi — model ketersediaan Tersedia/Habis)
        $insItem = $con->prepare("INSERT INTO order_items (order_id,item_id,qty,harga) VALUES (?,?,?,?)");
        foreach ($cart as $ci) {
            $insItem->execute([$order_id, $ci['item_id'], $ci['qty'], $ci['price']]);
        }

        unset($_SESSION['cart']);
        header('Location: orders.php?success=1&cod='.($isCOD?'1':'0')); exit();
    }
}
?>
<div class="page-banner"><div class="container">
  <h1>Checkout</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="cart.php">Keranjang</a> &rsaquo; <span>Checkout</span></div>
</div></div>

<div class="container" style="padding:36px 0;">
<?php foreach ($errors as $e) echo '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> '.$e.'</div>'; ?>

<div class="row">
  <div class="col-md-7">
    <div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <h3 style="font-size:18px;color:#1B2E5E;margin:0 0 20px;">Detail Pesanan</h3>

      <!-- INFO PICKUP -->
      <div style="background:#EAF5ED;border:1px solid #A3D4AE;border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:13px;color:#1A5C2A;">
        <i class="fa fa-shopping-bag" style="color:#1A5C2A;"></i>
        <strong>Ambil Sendiri (Self-Pickup):</strong> Pesanan diambil di tempat penjual. Isi <strong>alamat</strong> kamu sebagai data kontak, lalu pilih waktu pengambilan.
      </div>

      <!-- INFO COD -->
      <div style="background:#E8ECF5;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1B2E5E;">
        <i class="fa fa-info-circle" style="color:#B5272A;"></i>
        <strong>COD / Bayar di Tempat:</strong> Pesanan langsung diproses tanpa perlu upload bukti bayar. Pembayaran dilakukan saat pengambilan.
      </div>

      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">ALAMAT *</label>
          <textarea class="form-control" name="alamat" rows="2" required placeholder="Contoh: Jl. Melati No. 10, Karawaci, Tangerang" style="resize:vertical;"><?php echo htmlspecialchars($_POST['alamat'] ?? $myAlamat) ?></textarea>
          <small style="color:#9A9AB0;font-size:12px;">Wajib diisi. Alamat tersimpan ke profil untuk pesanan berikutnya.</small>
        </div>

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">WAKTU PENGAMBILAN *</label>
          <select class="form-control" name="waktu_ambil" required>
            <option value="">-- Pilih Waktu Ambil --</option>
            <?php foreach ($pickupOptions as $opt): ?>
              <option value="<?php echo htmlspecialchars($opt) ?>" <?php echo (($_POST['waktu_ambil'] ?? '')===$opt)?'selected':'' ?>><?php echo htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
          <small style="color:#9A9AB0;font-size:12px;">Barang diambil langsung di tempat penjual sesuai waktu yang dipilih.</small>
        </div>

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">METODE PEMBAYARAN *</label>
          <select class="form-control" name="metode_bayar" required id="metode_select" onchange="toggleBukti(this.value)">
            <option value="">-- Pilih Metode --</option>
            <optgroup label="Transfer Bank">
              <option value="Transfer BCA">Transfer BCA</option>
              <option value="Transfer BRI">Transfer BRI</option>
              <option value="Transfer Mandiri">Transfer Mandiri</option>
              <option value="Transfer BNI">Transfer BNI</option>
            </optgroup>
            <optgroup label="Dompet Digital">
              <option value="GoPay">GoPay</option>
              <option value="OVO">OVO</option>
              <option value="DANA">DANA</option>
              <option value="ShopeePay">ShopeePay</option>
            </optgroup>
            <optgroup label="Tunai">
              <option value="COD / Bayar di Tempat">COD / Bayar di Tempat</option>
            </optgroup>
          </select>
        </div>

        <!-- Bukti bayar (disembunyikan jika COD) -->
        <div class="form-group" id="bukti-wrap">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">
            UPLOAD BUKTI BAYAR <span style="color:#9A9AB0;font-weight:400;">(opsional, bisa diupload nanti)</span>
          </label>
          <input class="form-control" type="file" name="bukti_bayar" accept=".jpg,.jpeg,.png,.pdf" style="padding:6px 12px;">
          <small style="color:#9A9AB0;font-size:12px;">Format JPG/PNG/PDF, maks 5MB.</small>
        </div>

        <!-- Notif COD -->
        <div id="cod-info" style="display:none;background:#EAF5ED;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#1A5C2A;border:1px solid #A3D4AE;">
          <i class="fa fa-check-circle"></i> <strong>COD dipilih.</strong> Pesanan akan langsung berstatus <strong>Diproses</strong>. Bayar saat barang tiba/diambil.
        </div>

        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">CATATAN UNTUK PENJUAL</label>
          <textarea class="form-control" name="catatan" rows="2" placeholder="Contoh: tanpa sambal, ekstra nasi..." style="resize:vertical;"><?php echo isset($_POST['catatan'])?htmlspecialchars($_POST['catatan']):'' ?></textarea>
        </div>

        <button type="submit" class="btn-submit"><i class="fa fa-check"></i> Buat Pesanan</button>
      </form>
    </div>
  </div>

  <div class="col-md-5">
    <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <h3 style="font-size:16px;color:#1B2E5E;margin:0 0 16px;">Ringkasan (<?php echo count($cart) ?> produk)</h3>
      <?php foreach ($cart as $ci): ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <img src="<?php echo empty($ci['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($ci['picture']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#EEF0F6;">
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($ci['name']) ?></div>
          <div style="font-size:12px;color:#9A9AB0;">x<?php echo $ci['qty'] ?> &times; Rp <?php echo number_format($ci['price'],0,',','.') ?></div>
        </div>
        <div style="font-size:13px;font-weight:700;color:#1B2E5E;">Rp <?php echo number_format($ci['price']*$ci['qty'],0,',','.') ?></div>
      </div>
      <?php endforeach; ?>
      <div style="border-top:1.5px solid #DDE1EC;margin:16px 0;"></div>
      <div style="display:flex;justify-content:space-between;font-weight:700;color:#1B2E5E;font-size:18px;">
        <span>Total Bayar</span>
        <span style="color:#B5272A;">Rp <?php echo number_format($total,0,',','.') ?></span>
      </div>
    </div>
  </div>
</div>
</div>

<script>
function toggleBukti(val) {
    var isCOD = val === 'COD / Bayar di Tempat';
    document.getElementById('bukti-wrap').style.display = isCOD ? 'none' : 'block';
    document.getElementById('cod-info').style.display  = isCOD ? 'block' : 'none';
}
</script>

<?php include $tpl.'footer.php'; ob_end_flush(); ?>