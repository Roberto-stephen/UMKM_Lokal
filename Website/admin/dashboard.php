<?php
ob_start(); session_start();
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
$pageTitle = 'Dashboard';
include 'init.php';

$latestUsers = getLatest("*","users","UserID",6);
$latestItems = getLatest("*","items","Item_ID",6);

// FIX: Hitung comments yang sudah approved saja
$stmtComCount = $con->prepare("SELECT COUNT(*) FROM comments WHERE status=1");
$stmtComCount->execute();
$totalComments = $stmtComCount->fetchColumn();

$stmtPending = $con->prepare("SELECT COUNT(*) FROM orders WHERE status='Menunggu Konfirmasi'");
$stmtPending->execute();
$pendingOrders = $stmtPending->fetchColumn();

// Produk pending approve
$stmtPendingItems = $con->prepare("SELECT COUNT(*) FROM items WHERE Approve=0");
$stmtPendingItems->execute();
$pendingItems = $stmtPendingItems->fetchColumn();
?>

<div style="padding:28px 0 0;">
<div class="container">

  <!-- WELCOME BAR -->
  <div style="background:linear-gradient(135deg,#1B2E5E 0%,#2A3F80 100%);border-radius:14px;padding:24px 28px;color:#fff;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <div style="font-size:12px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Selamat datang kembali</div>
      <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;"><?php echo htmlspecialchars($_SESSION['Username']) ?> <span style="color:#F4A261;">✦</span></div>
    </div>
    <div style="font-size:12px;opacity:.6;"><?php echo date('l, d F Y') ?></div>
  </div>

  <!-- STAT CARDS -->
  <div class="row">
    <?php
    $stats = [
      ['label'=>'Total Members','value'=>countItems('UserID','users'),'link'=>'members.php','icon'=>'fa-users','grad'=>'#1B2E5E,#2A3F80','sub'=>'Pengguna terdaftar'],
      ['label'=>'Total Produk','value'=>countItems('Item_ID','items'),'link'=>'items.php','icon'=>'fa-tag','grad'=>'#B5272A,#D44040','sub'=>'Produk aktif'],
      ['label'=>'Total Ulasan','value'=>$totalComments,'link'=>'comments.php','icon'=>'fa-comments','grad'=>'#1A5C2A,#27AE60','sub'=>'Ulasan disetujui'],
      ['label'=>'Pesanan Pending','value'=>$pendingOrders,'link'=>'orders_admin.php','icon'=>'fa-clock-o','grad'=>'#92400E,#D97706','sub'=>'Menunggu konfirmasi'],
    ];
    foreach ($stats as $st): ?>
    <div class="col-md-3 col-sm-6">
      <div style="padding:20px 18px;border-radius:14px;color:#fff;background:linear-gradient(135deg,<?php echo $st['grad'] ?>);box-shadow:0 4px 20px rgba(0,0,0,.12);margin-bottom:20px;position:relative;overflow:hidden;">
        <i class="fa <?php echo $st['icon'] ?>" style="position:absolute;font-size:60px;right:-10px;top:10px;opacity:.15;"></i>
        <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;"><?php echo $st['label'] ?></div>
        <div style="font-size:44px;font-family:'Playfair Display',serif;font-weight:700;line-height:1.1;margin:4px 0;">
          <a href="<?php echo $st['link'] ?>" style="color:#fff;"><?php echo $st['value'] ?></a>
        </div>
        <div style="font-size:11px;opacity:.6;"><?php echo $st['sub'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ALERT PRODUK PENDING -->
  <?php if ($pendingItems > 0): ?>
  <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <span style="font-size:13px;color:#92400E;"><i class="fa fa-exclamation-triangle"></i> Ada <strong><?php echo $pendingItems ?> produk</strong> menunggu persetujuan.</span>
    <a href="items.php" style="background:#92400E;color:#fff;padding:5px 14px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;">Review Sekarang</a>
  </div>
  <?php endif; ?>

  <!-- LATEST TABLES -->
  <div class="row">
    <div class="col-md-6">
      <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;margin-bottom:24px;">
        <div style="background:#1B2E5E;color:#fff;padding:14px 18px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
          <span><i class="fa fa-users"></i> User Terbaru</span>
          <a href="members.php" style="font-size:11px;color:rgba(255,255,255,.6);">Lihat semua →</a>
        </div>
        <ul style="margin:0;padding:0;list-style:none;">
          <?php foreach ($latestUsers as $user): ?>
          <li style="padding:10px 16px;border-bottom:1px solid #DDE1EC;display:flex;align-items:center;justify-content:space-between;font-size:13px;">
            <span style="color:#1B2E5E;font-weight:500;"><?php echo htmlspecialchars($user['Username']) ?>
              <span style="font-size:11px;color:#9A9AB0;margin-left:6px;"><?php echo ['0'=>'Pembeli','1'=>'Admin','2'=>'Penjual'][$user['GroupID']]??'Pembeli' ?></span>
              <?php if ($user['RegStatus']==0): ?><span style="background:#FEF3C7;color:#92400E;padding:1px 7px;border-radius:10px;font-size:10px;margin-left:4px;">Non-aktif</span><?php endif; ?>
            </span>
            <div style="display:flex;gap:4px;">
              <?php if ($user['RegStatus']==0): ?>
              <a href="members.php?do=Activate&userid=<?php echo $user['UserID'] ?>" style="background:#EAF5ED;color:#1A5C2A;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-check"></i> Aktifkan</a>
              <?php endif; ?>
              <a href="members.php?do=Edit&userid=<?php echo $user['UserID'] ?>" style="background:#1B2E5E;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-edit"></i></a>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="col-md-6">
      <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;margin-bottom:24px;">
        <div style="background:#B5272A;color:#fff;padding:14px 18px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
          <span><i class="fa fa-tag"></i> Produk Terbaru</span>
          <a href="items.php" style="font-size:11px;color:rgba(255,255,255,.6);">Lihat semua →</a>
        </div>
        <ul style="margin:0;padding:0;list-style:none;">
          <?php foreach ($latestItems as $item): ?>
          <li style="padding:10px 16px;border-bottom:1px solid #DDE1EC;display:flex;align-items:center;justify-content:space-between;font-size:13px;">
            <span style="color:#1B2E5E;font-weight:500;"><?php echo htmlspecialchars($item['Name']) ?>
              <?php if ($item['Approve']==0): ?><span style="background:#FEF3C7;color:#92400E;padding:1px 7px;border-radius:10px;font-size:10px;margin-left:4px;">Pending</span><?php endif; ?>
              <span style="font-size:11px;color:#9A9AB0;margin-left:6px;">Stok: <?php echo $item['stok'] ?? '-' ?></span>
            </span>
            <div style="display:flex;gap:4px;">
              <?php if ($item['Approve']==0): ?>
              <a href="items.php?do=Approve&itemid=<?php echo $item['Item_ID'] ?>" style="background:#EAF5ED;color:#1A5C2A;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-check"></i> Approve</a>
              <?php endif; ?>
              <a href="items.php?do=Edit&itemid=<?php echo $item['Item_ID'] ?>" style="background:#B5272A;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-edit"></i></a>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- LATEST ULASAN -->
  <?php
    $stmtC = $con->prepare("SELECT comments.*, users.Username AS Member, items.Name AS item_name FROM comments INNER JOIN users ON users.UserID=comments.user_id INNER JOIN items ON items.Item_ID=comments.item_id ORDER BY c_id DESC LIMIT 4");
    $stmtC->execute(); $comments = $stmtC->fetchAll();
  ?>
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;margin-bottom:24px;">
    <div style="background:#1A5C2A;color:#fff;padding:14px 18px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
      <span><i class="fa fa-comments"></i> Ulasan Terbaru</span>
      <a href="comments.php" style="font-size:11px;color:rgba(255,255,255,.6);">Lihat semua →</a>
    </div>
    <?php if (!empty($comments)): ?>
      <?php foreach ($comments as $comment): ?>
      <div style="padding:12px 16px;border-bottom:1px solid #DDE1EC;display:flex;gap:10px;align-items:flex-start;">
        <div style="width:32px;height:32px;border-radius:50%;background:#E8ECF5;display:flex;align-items:center;justify-content:center;font-weight:700;color:#1B2E5E;font-size:12px;flex-shrink:0;">
          <?php echo strtoupper(substr($comment['Member'],0,1)) ?>
        </div>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px;">
            <span style="font-size:12px;font-weight:600;color:#1B2E5E;"><?php echo htmlspecialchars($comment['Member']) ?></span>
            <?php if (isset($comment['rating'])): ?>
            <span style="color:#F4A261;font-size:12px;"><?php for($i=1;$i<=5;$i++) echo $i<=$comment['rating']?'★':'☆'; ?></span>
            <?php endif; ?>
            <?php if ($comment['status']==0): ?><span style="background:#FEF3C7;color:#92400E;padding:1px 7px;border-radius:10px;font-size:10px;">Pending</span><?php endif; ?>
          </div>
          <div style="font-size:12px;color:#9A9AB0;margin-bottom:2px;">di <em><?php echo htmlspecialchars($comment['item_name']) ?></em></div>
          <div style="font-size:13px;color:#4A4A6A;"><?php echo htmlspecialchars($comment['comment']) ?></div>
        </div>
        <?php if ($comment['status']==0): ?>
        <a href="../admin/comments.php?do=Approve&comid=<?php echo $comment['c_id'] ?>" style="background:#EAF5ED;color:#1A5C2A;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;"><i class="fa fa-check"></i> Approve</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="padding:20px;text-align:center;color:#9A9AB0;font-size:13px;">Belum ada ulasan.</div>
    <?php endif; ?>
  </div>

</div>
</div>
<?php include $tpl.'footer.php'; ob_end_flush(); ?>