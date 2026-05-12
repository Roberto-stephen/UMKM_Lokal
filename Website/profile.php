<?php
ob_start();
session_start();
$pageTitle = 'Profil Saya';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

$getUser = $con->prepare("SELECT * FROM users WHERE Username = ?");
$getUser->execute([$_SESSION['user']]);
$info = $getUser->fetch();
$userid = $info['UserID'];

// Statistik
$totalProduk  = $con->prepare("SELECT COUNT(*) FROM items WHERE Member_ID=?"); $totalProduk->execute([$userid]);
$totalPesanan = $con->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");  $totalPesanan->execute([$userid]);
$totalUlasan  = $con->prepare("SELECT COUNT(*) FROM comments WHERE user_id=?"); $totalUlasan->execute([$userid]);
?>

<div class="page-banner"><div class="container">
  <h1>Profil Saya</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <span>Profil</span></div>
</div></div>

<div class="container" style="padding:40px 0;">
<div class="row">

  <!-- SIDEBAR PROFIL -->
  <div class="col-md-4">
    <div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;text-align:center;margin-bottom:20px;">
      <?php
        $avatar = $info['avatar'] ?? 'default.png';
        $avatarSrc = (!empty($avatar) && $avatar!='default.png') ? 'admin/uploads/avatars/'.htmlspecialchars($avatar) : null;
      ?>
      <?php if ($avatarSrc): ?>
        <img src="<?php echo $avatarSrc ?>" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid #E8ECF5;margin-bottom:12px;">
      <?php else: ?>
        <div style="width:88px;height:88px;border-radius:50%;background:#E8ECF5;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:36px;font-weight:700;color:#1B2E5E;">
          <?php echo strtoupper(substr($info['Username'],0,1)) ?>
        </div>
      <?php endif; ?>
      <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#1B2E5E;"><?php echo htmlspecialchars($info['FullName'] ?: $info['Username']) ?></div>
      <div style="font-size:13px;color:#9A9AB0;margin-top:2px;">@<?php echo htmlspecialchars($info['Username']) ?></div>
      <div style="margin-top:8px;">
        <?php
          $role = $info['GroupID'];
          $roleLabel = ['0'=>'Pembeli','1'=>'Admin','2'=>'Penjual'];
          $roleBg    = ['0'=>'#E8ECF5','1'=>'#FDECEA','2'=>'#EAF5ED'];
          $roleColor = ['0'=>'#1B2E5E','1'=>'#9B1C1C','2'=>'#1A5C2A'];
          $r = (string)$role;
        ?>
        <span style="background:<?php echo $roleBg[$r]??'#E8ECF5' ?>;color:<?php echo $roleColor[$r]??'#1B2E5E' ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;">
          <?php echo $roleLabel[$r]??'Pembeli' ?>
        </span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin:20px 0;text-align:center;">
        <div style="background:#F0F2F5;border-radius:10px;padding:10px;">
          <div style="font-size:20px;font-weight:700;color:#1B2E5E;"><?php echo $totalProduk->fetchColumn() ?></div>
          <div style="font-size:11px;color:#9A9AB0;">Produk</div>
        </div>
        <div style="background:#F0F2F5;border-radius:10px;padding:10px;">
          <div style="font-size:20px;font-weight:700;color:#1B2E5E;"><?php echo $totalPesanan->fetchColumn() ?></div>
          <div style="font-size:11px;color:#9A9AB0;">Pesanan</div>
        </div>
        <div style="background:#F0F2F5;border-radius:10px;padding:10px;">
          <div style="font-size:20px;font-weight:700;color:#1B2E5E;"><?php echo $totalUlasan->fetchColumn() ?></div>
          <div style="font-size:11px;color:#9A9AB0;">Ulasan</div>
        </div>
      </div>

      <a href="editProfil.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;margin-bottom:8px;">
        <i class="fa fa-edit"></i> Edit Profil
      </a>
      <a href="orders.php" style="display:block;text-align:center;font-size:13px;color:#1B2E5E;font-weight:600;padding:8px;border:1.5px solid #DDE1EC;border-radius:30px;text-decoration:none;">
        <i class="fa fa-clipboard"></i> Pesanan Saya
      </a>
      <?php if ($info['GroupID']==2): ?>
      <a href="seller_orders.php" style="display:block;text-align:center;font-size:13px;color:#1A5C2A;font-weight:600;padding:8px;border:1.5px solid #A3D4AE;border-radius:30px;text-decoration:none;margin-top:8px;background:#EAF5ED;">
        <i class="fa fa-inbox"></i> Pesanan Masuk
      </a>
      <?php endif; ?>
    </div>

    <!-- INFO -->
    <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <div style="font-size:12px;font-weight:700;color:#9A9AB0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Informasi Akun</div>
      <div style="font-size:13px;margin-bottom:10px;display:flex;gap:10px;">
        <i class="fa fa-envelope-o fa-fw" style="color:#1B2E5E;margin-top:2px;"></i>
        <span><?php echo htmlspecialchars($info['Email']) ?></span>
      </div>
      <div style="font-size:13px;margin-bottom:10px;display:flex;gap:10px;">
        <i class="fa fa-calendar fa-fw" style="color:#1B2E5E;margin-top:2px;"></i>
        <span>Bergabung <?php echo $info['Date'] ?></span>
      </div>
    </div>
  </div>

  <!-- KONTEN UTAMA -->
  <div class="col-md-8">

    <!-- PRODUK SAYA -->
    <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;margin-bottom:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;font-size:18px;color:#1B2E5E;">Produk Saya</h3>
        <a href="newad.php" style="font-size:13px;color:#1B2E5E;font-weight:600;"><i class="fa fa-plus"></i> Tambah</a>
      </div>
      <?php
        $myItemsStmt = $con->prepare("SELECT * FROM items WHERE Member_ID=? ORDER BY Item_ID DESC LIMIT 6");
        $myItemsStmt->execute([$userid]);
        $myItems2 = $myItemsStmt->fetchAll();
      ?>
      <?php if (empty($myItems2)): ?>
        <div style="text-align:center;padding:20px;color:#9A9AB0;font-size:13px;">
          Belum ada produk. <a href="newad.php" style="color:#1B2E5E;font-weight:600;">Tambah sekarang</a>
        </div>
      <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <?php foreach ($myItems2 as $prd): ?>
          <a href="items.php?itemid=<?php echo $prd['Item_ID'] ?>" style="text-decoration:none;">
            <div style="border-radius:10px;overflow:hidden;border:1px solid #DDE1EC;position:relative;">
              <img src="<?php echo empty($prd['picture'])?'admin/uploads/default.png':'admin/uploads/items/'.htmlspecialchars($prd['picture']) ?>" style="width:100%;height:90px;object-fit:cover;background:#EEF0F6;">
              <?php if ($prd['Approve']==0): ?>
              <div style="position:absolute;top:4px;left:4px;background:#FEF3C7;color:#92400E;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;">Menunggu</div>
              <?php endif; ?>
              <div style="padding:6px 8px;">
                <div style="font-size:12px;font-weight:600;color:#1B2E5E;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?php echo htmlspecialchars($prd['Name']) ?></div>
                <div style="font-size:11px;color:#B5272A;font-weight:700;">Rp <?php echo number_format($prd['Price'],0,',','.') ?></div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;text-align:right;"><a href="myItems.php" style="font-size:13px;color:#1B2E5E;font-weight:600;">Lihat semua produk →</a></div>
      <?php endif; ?>
    </div>

    <!-- ULASAN TERAKHIR -->
    <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">
      <h3 style="margin:0 0 16px;font-size:18px;color:#1B2E5E;">Ulasan Terakhir</h3>
      <?php
        $myComments = $con->prepare("SELECT comments.*, items.Name AS item_name FROM comments INNER JOIN items ON items.Item_ID=comments.item_id WHERE comments.user_id=? ORDER BY c_id DESC LIMIT 5");
        $myComments->execute([$userid]);
        $comments3 = $myComments->fetchAll();
      ?>
      <?php if (empty($comments3)): ?>
        <div style="text-align:center;padding:20px;color:#9A9AB0;font-size:13px;">Belum ada ulasan.</div>
      <?php else: ?>
        <?php foreach ($comments3 as $c): ?>
        <div style="padding:12px 0;border-bottom:1px solid #DDE1EC;">
          <div style="font-size:12px;color:#9A9AB0;margin-bottom:4px;"><i class="fa fa-tag" style="color:#1B2E5E;"></i> <?php echo htmlspecialchars($c['item_name']) ?></div>
          <div style="font-size:14px;color:#4A4A6A;"><?php echo htmlspecialchars($c['comment']) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>

<?php include $tpl.'footer.php'; ob_end_flush(); ?>