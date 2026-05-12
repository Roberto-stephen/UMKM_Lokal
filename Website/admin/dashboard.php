<?php
ob_start();
session_start();

if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }

$pageTitle = 'Dashboard';
include 'init.php';

$numUsers    = 6;
$numItems    = 6;
$numComments = 4;

$latestUsers    = getLatest("*", "users", "UserID", $numUsers);
$latestItems    = getLatest("*", "items", "Item_ID", $numItems);

// Hitung pesanan pending
$stmtPending = $con->prepare("SELECT COUNT(*) FROM orders WHERE status='Menunggu Konfirmasi'");
$stmtPending->execute();
$pendingOrders = $stmtPending->fetchColumn();
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
  <div class="row" style="margin-bottom:8px;">
    <div class="col-md-3 col-sm-6">
      <div class="stat st-members" style="padding:20px 18px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#1B2E5E,#2A3F80);box-shadow:0 4px 20px rgba(27,46,94,.2);margin-bottom:20px;position:relative;overflow:hidden;">
        <i class="fa fa-users" style="position:absolute;font-size:60px;right:-10px;top:10px;opacity:.15;"></i>
        <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;">Total Members</div>
        <div style="font-size:44px;font-family:'Playfair Display',serif;font-weight:700;line-height:1.1;margin:4px 0;">
          <a href="members.php" style="color:#fff;"><?php echo countItems('UserID','users') ?></a>
        </div>
        <div style="font-size:11px;opacity:.6;">Pengguna terdaftar</div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
      <div style="padding:20px 18px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#B5272A,#D44040);box-shadow:0 4px 20px rgba(181,39,42,.2);margin-bottom:20px;position:relative;overflow:hidden;">
        <i class="fa fa-tag" style="position:absolute;font-size:60px;right:-10px;top:10px;opacity:.15;"></i>
        <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;">Total Produk</div>
        <div style="font-size:44px;font-family:'Playfair Display',serif;font-weight:700;line-height:1.1;margin:4px 0;">
          <a href="items.php" style="color:#fff;"><?php echo countItems('Item_ID','items') ?></a>
        </div>
        <div style="font-size:11px;opacity:.6;">Produk terdaftar</div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
      <div style="padding:20px 18px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#1A5C2A,#27AE60);box-shadow:0 4px 20px rgba(26,92,42,.2);margin-bottom:20px;position:relative;overflow:hidden;">
        <i class="fa fa-comments" style="position:absolute;font-size:60px;right:-10px;top:10px;opacity:.15;"></i>
        <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;">Total Ulasan</div>
        <div style="font-size:44px;font-family:'Playfair Display',serif;font-weight:700;line-height:1.1;margin:4px 0;">
          <a href="comments.php" style="color:#fff;"><?php echo countItems('c_id','comments') ?></a>
        </div>
        <div style="font-size:11px;opacity:.6;">Feedback masuk</div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6">
      <div style="padding:20px 18px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#92400E,#D97706);box-shadow:0 4px 20px rgba(146,64,14,.2);margin-bottom:20px;position:relative;overflow:hidden;">
        <i class="fa fa-clock-o" style="position:absolute;font-size:60px;right:-10px;top:10px;opacity:.15;"></i>
        <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;">Pesanan Pending</div>
        <div style="font-size:44px;font-family:'Playfair Display',serif;font-weight:700;line-height:1.1;margin:4px 0;">
          <a href="orders_admin.php" style="color:#fff;"><?php echo $pendingOrders ?></a>
        </div>
        <div style="font-size:11px;opacity:.6;">Menunggu konfirmasi</div>
      </div>
    </div>
  </div>

  <!-- LATEST TABLES -->
  <div class="row">
    <div class="col-md-6">
      <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;margin-bottom:24px;">
        <div style="background:#1B2E5E;color:#fff;padding:14px 18px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
          <span><i class="fa fa-users"></i> <?php echo $numUsers ?> User Terbaru</span>
          <a href="members.php" style="font-size:11px;color:rgba(255,255,255,.6);">Lihat semua →</a>
        </div>
        <ul style="margin:0;padding:0;list-style:none;">
          <?php if (!empty($latestUsers)): ?>
            <?php foreach ($latestUsers as $user): ?>
            <li style="padding:10px 16px;border-bottom:1px solid #DDE1EC;display:flex;align-items:center;justify-content:space-between;font-size:13px;">
              <span style="color:#1B2E5E;font-weight:500;"><?php echo htmlspecialchars($user['Username']) ?>
                <span style="font-size:11px;color:#9A9AB0;margin-left:6px;"><?php echo ['0'=>'Pembeli','1'=>'Admin','2'=>'Penjual'][$user['GroupID']]??'Pembeli' ?></span>
              </span>
              <div style="display:flex;gap:4px;">
                <?php if ($user['RegStatus']==0): ?>
                <a href="members.php?do=Activate&userid=<?php echo $user['UserID'] ?>" style="background:#E8ECF5;color:#1B2E5E;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-check"></i> Aktifkan</a>
                <?php endif; ?>
                <a href="members.php?do=Edit&userid=<?php echo $user['UserID'] ?>" style="background:#1B2E5E;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-edit"></i> Edit</a>
              </div>
            </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li style="padding:16px;text-align:center;color:#9A9AB0;font-size:13px;">Belum ada member.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="col-md-6">
      <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;margin-bottom:24px;">
        <div style="background:#B5272A;color:#fff;padding:14px 18px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
          <span><i class="fa fa-tag"></i> <?php echo $numItems ?> Produk Terbaru</span>
          <a href="items.php" style="font-size:11px;color:rgba(255,255,255,.6);">Lihat semua →</a>
        </div>
        <ul style="margin:0;padding:0;list-style:none;">
          <?php if (!empty($latestItems)): ?>
            <?php foreach ($latestItems as $item): ?>
            <li style="padding:10px 16px;border-bottom:1px solid #DDE1EC;display:flex;align-items:center;justify-content:space-between;font-size:13px;">
              <span style="color:#1B2E5E;font-weight:500;"><?php echo htmlspecialchars($item['Name']) ?>
                <?php if ($item['Approve']==0): ?>
                <span style="background:#FEF3C7;color:#92400E;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;margin-left:4px;">Pending</span>
                <?php endif; ?>
              </span>
              <div style="display:flex;gap:4px;">
                <?php if ($item['Approve']==0): ?>
                <a href="items.php?do=Approve&itemid=<?php echo $item['Item_ID'] ?>" style="background:#EAF5ED;color:#1A5C2A;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-check"></i> Approve</a>
                <?php endif; ?>
                <a href="items.php?do=Edit&itemid=<?php echo $item['Item_ID'] ?>" style="background:#B5272A;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-edit"></i> Edit</a>
              </div>
            </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li style="padding:16px;text-align:center;color:#9A9AB0;font-size:13px;">Belum ada produk.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- LATEST COMMENTS -->
  <?php
    $stmtC = $con->prepare("SELECT comments.*, users.Username AS Member FROM comments INNER JOIN users ON users.UserID=comments.user_id ORDER BY c_id DESC LIMIT $numComments");
    $stmtC->execute(); $comments = $stmtC->fetchAll();
  ?>
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;margin-bottom:24px;">
    <div style="background:#1A5C2A;color:#fff;padding:14px 18px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
      <span><i class="fa fa-comments"></i> <?php echo $numComments ?> Ulasan Terbaru</span>
      <a href="comments.php" style="font-size:11px;color:rgba(255,255,255,.6);">Lihat semua →</a>
    </div>
    <?php if (!empty($comments)): ?>
      <?php foreach ($comments as $comment): ?>
      <div style="padding:12px 16px;border-bottom:1px solid #DDE1EC;display:flex;gap:12px;align-items:flex-start;">
        <div style="width:32px;height:32px;border-radius:50%;background:#E8ECF5;display:flex;align-items:center;justify-content:center;font-weight:700;color:#1B2E5E;font-size:13px;flex-shrink:0;">
          <?php echo strtoupper(substr($comment['Member'],0,1)) ?>
        </div>
        <div>
          <div style="font-size:12px;font-weight:600;color:#1B2E5E;margin-bottom:2px;"><?php echo htmlspecialchars($comment['Member']) ?></div>
          <div style="font-size:13px;color:#4A4A6A;"><?php echo htmlspecialchars($comment['comment']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="padding:16px;text-align:center;color:#9A9AB0;font-size:13px;">Belum ada ulasan.</div>
    <?php endif; ?>
  </div>

</div>
</div>

<?php include $tpl.'footer.php'; ob_end_flush(); ?>