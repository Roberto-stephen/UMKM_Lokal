<?php
ob_start(); session_start();
$pageTitle = 'Produk Saya';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

// Hapus produk
if (isset($_GET['do']) && $_GET['do']=='delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $chk = $con->prepare("SELECT Item_ID FROM items WHERE Item_ID=? AND Member_ID=?");
    $chk->execute([$id, $_SESSION['uid']]);
    if ($chk->rowCount() > 0) {
        $con->prepare("DELETE FROM items WHERE Item_ID=?")->execute([$id]);
        header('Location: myItems.php?deleted=1'); exit();
    }
}

$stmt = $con->prepare("SELECT items.*, categories.Name AS cat_name FROM items INNER JOIN categories ON categories.ID=items.Cat_ID WHERE Member_ID=? ORDER BY Item_ID DESC");
$stmt->execute([$_SESSION['uid']]);
$myItems = $stmt->fetchAll();
?>
<div class="page-banner"><div class="container">
  <h1>Produk Saya</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <span>Produk Saya</span></div>
</div></div>

<div class="container" style="padding:36px 0;">

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> Produk berhasil dihapus.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
  <div class="alert alert-success"><i class="fa fa-check-circle"></i> Produk berhasil diperbarui.</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h2 style="margin:0;font-size:22px;color:#1B2E5E;">Daftar Produk (<?php echo count($myItems) ?>)</h2>
  <a href="newad.php" class="btn-submit" style="padding:10px 24px;text-decoration:none;display:inline-block;">
    <i class="fa fa-plus"></i> Tambah Produk
  </a>
</div>

<?php if (empty($myItems)): ?>
<div class="empty-state">
  <i class="fa fa-box-open"></i>
  <p>Belum ada produk. <a href="newad.php" style="color:#1B2E5E;font-weight:600;">Tambah produk pertamamu!</a></p>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">
<table style="width:100%;border-collapse:collapse;font-size:13px;">
  <thead>
    <tr style="background:#F0F2F5;">
      <th style="padding:12px 16px;text-align:left;font-weight:600;color:#4A4A6A;">Produk</th>
      <th style="padding:12px 16px;text-align:left;font-weight:600;color:#4A4A6A;">Kategori</th>
      <th style="padding:12px 16px;text-align:right;font-weight:600;color:#4A4A6A;">Harga</th>
      <th style="padding:12px 16px;text-align:center;font-weight:600;color:#4A4A6A;">Status</th>
      <th style="padding:12px 16px;text-align:center;font-weight:600;color:#4A4A6A;">Aksi</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($myItems as $item): ?>
  <tr style="border-top:1px solid #DDE1EC;">
    <td style="padding:12px 16px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <img src="<?php echo empty($item['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($item['picture']) ?>"
             style="width:48px;height:48px;object-fit:cover;border-radius:8px;background:#EEF0F6;border:1px solid #DDE1EC;">
        <div>
          <div style="font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($item['Name']) ?></div>
          <div style="font-size:11px;color:#9A9AB0;"><?php echo $item['Add_Date'] ?></div>
        </div>
      </div>
    </td>
    <td style="padding:12px 16px;color:#4A4A6A;"><?php echo htmlspecialchars($item['cat_name']) ?></td>
    <td style="padding:12px 16px;text-align:right;font-weight:600;color:#1B2E5E;">Rp <?php echo number_format($item['Price'],0,',','.') ?></td>
    <td style="padding:12px 16px;text-align:center;">
      <?php if ($item['Approve']==1): ?>
        <span style="background:#EAF5ED;color:#1A5C2A;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Aktif</span>
      <?php else: ?>
        <span style="background:#FEF3C7;color:#92400E;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Menunggu</span>
      <?php endif; ?>
    </td>
    <td style="padding:12px 16px;text-align:center;">
      <a href="editItem.php?id=<?php echo $item['Item_ID'] ?>" style="background:#E8ECF5;color:#1B2E5E;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;margin-right:4px;"><i class="fa fa-edit"></i> Edit</a>
      <a href="myItems.php?do=delete&id=<?php echo $item['Item_ID'] ?>" onclick="return confirm('Yakin hapus produk ini?')" style="background:#FDECEA;color:#9B1C1C;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;"><i class="fa fa-trash"></i></a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>