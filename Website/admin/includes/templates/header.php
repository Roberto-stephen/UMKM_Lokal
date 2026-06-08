<?php
// ============================================================
// GUARD: Jika $loginPage = true, skip seluruh layout ini.
// index.php (login) menyediakan HTML-nya sendiri.
// ============================================================
if (!empty($loginPage)) return;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php getTitle() ?> — Admin MakanLokal</title>
  <link rel="stylesheet" href="<?php echo $css ?>bootstrap.min.css"/>
  <link rel="stylesheet" href="<?php echo $css ?>font-awesome.min.css"/>
  <link rel="stylesheet" href="<?php echo $css ?>jquery-ui.css"/>
  <link rel="stylesheet" href="<?php echo $css ?>backend.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#F0F2F5;">

<div class="admin-wrapper" style="display:flex;min-height:100vh;width:100%;">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar"
    style="background-color:#111E40 !important;background:#111E40 !important;
           width:240px;min-width:240px;max-width:240px;
           min-height:100vh;height:100vh;
           position:sticky;top:0;
           display:flex;flex-direction:column;
           overflow-y:auto;overflow-x:hidden;
           z-index:100;flex-shrink:0;">

    <div class="sidebar-brand" style="padding:20px 18px 14px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;">
      <a href="dashboard.php" style="font-family:'Playfair Display',serif;font-size:20px;font-weight:900;color:#fff !important;display:block;text-decoration:none;">
        Makan<span style="color:#F4A261;">Lokal</span>
      </a>
      <div style="font-size:10px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.8px;margin-top:3px;">Admin Panel</div>
    </div>

    <nav style="flex:1;overflow-y:auto;padding:10px 0;">
      <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.3);padding:16px 18px 6px;">Menu Utama</div>

      <?php
        $page = basename($_SERVER['PHP_SELF']);
        $navItems = [
          ['dashboard.php',    'fa-tachometer',  'Dashboard'],
          ['categories.php',   'fa-th-large',    'Kategori'],
          ['items.php',        'fa-tag',          'Produk'],
          ['members.php',      'fa-users',        'Members'],
          ['comments.php',     'fa-comments',     'Ulasan'],
          ['orders_admin.php', 'fa-clipboard',    'Pesanan'],
        ];
        foreach ($navItems as $n) {
          $isActive = ($page == $n[0]);
          $bg       = $isActive ? 'rgba(255,255,255,.12)' : 'transparent';
          $border   = $isActive ? '#F4A261' : 'transparent';
          $fw       = $isActive ? '600' : '500';
          echo '<a href="'.$n[0].'" style="display:flex;align-items:center;gap:11px;padding:10px 18px;color:#fff !important;font-size:13px;font-weight:'.$fw.';border-left:3px solid '.$border.';background:'.$bg.';text-decoration:none;transition:all .15s;">
            <i class="fa '.$n[1].'" style="width:17px;text-align:center;font-size:14px;flex-shrink:0;"></i>
            <span>'.$n[2].'</span>
          </a>';
        }

        // Badge pesanan pending
        try {
          $stmtBadge = $con->prepare("SELECT COUNT(*) FROM orders WHERE status='Menunggu Konfirmasi'");
          $stmtBadge->execute();
          $badge = (int)$stmtBadge->fetchColumn();
          if ($badge > 0) {
            echo '<script>document.addEventListener("DOMContentLoaded",function(){
              var l=document.querySelector(\'a[href="orders_admin.php"]\');
              if(l){var b=document.createElement("span");b.textContent='.$badge.';
              b.style.cssText="background:#B5272A;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;margin-left:auto;";
              l.appendChild(b);}
            });</script>';
          }
        } catch(Exception $e) { /* tabel orders mungkin belum ada */ }
      ?>

      <div style="height:1px;background:rgba(255,255,255,.07);margin:8px 14px;"></div>
      <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.3);padding:16px 18px 6px;">Lainnya</div>

      <a href="../index.php" target="_blank"
         style="display:flex;align-items:center;gap:11px;padding:10px 18px;color:rgba(255,255,255,.6) !important;font-size:13px;font-weight:500;border-left:3px solid transparent;text-decoration:none;">
        <i class="fa fa-external-link" style="width:17px;text-align:center;font-size:14px;"></i>
        <span>Lihat Website</span>
      </a>
      <a href="logout.php"
         style="display:flex;align-items:center;gap:11px;padding:10px 18px;color:rgba(255,255,255,.6) !important;font-size:13px;font-weight:500;border-left:3px solid transparent;text-decoration:none;">
        <i class="fa fa-sign-out" style="width:17px;text-align:center;font-size:14px;"></i>
        <span>Logout</span>
      </a>
    </nav>

    <!-- User info di bawah sidebar -->
    <div style="padding:14px 18px;border-top:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:10px;flex-shrink:0;">
      <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
        <?php echo strtoupper(substr($_SESSION['Username'] ?? 'A', 0, 1)) ?>
      </div>
      <div>
        <div style="font-size:13px;font-weight:600;color:#fff;"><?php echo htmlspecialchars($_SESSION['Username'] ?? 'Admin') ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.4);">Administrator</div>
      </div>
    </div>

  </aside>
  <!-- ===== END SIDEBAR ===== -->

  <!-- ===== MAIN CONTENT ===== -->
  <div class="main-content" id="main-content"
    style="flex:1;min-width:0;display:flex;flex-direction:column;background:#F0F2F5;min-height:100vh;">

    <!-- TOPBAR -->
    <div style="height:54px;background:#fff;border-bottom:1px solid #DDE1EC;display:flex;align-items:center;gap:14px;padding:0 22px;position:sticky;top:0;z-index:99;box-shadow:0 1px 4px rgba(27,46,94,.05);flex-shrink:0;">
      <button onclick="toggleSidebar()" style="background:none;border:none;cursor:pointer;color:#4A4A6A;font-size:18px;padding:4px 8px;border-radius:6px;line-height:1;">
        <i class="fa fa-bars"></i>
      </button>
      <div style="font-size:15px;font-weight:600;color:#1B2E5E;flex:1;font-family:'DM Sans',sans-serif;">
        <?php
          $titles = [
            'dashboard.php'    => 'Dashboard',
            'categories.php'   => 'Kelola Kategori',
            'items.php'        => 'Kelola Produk',
            'members.php'      => 'Kelola Members',
            'comments.php'     => 'Kelola Ulasan',
            'orders_admin.php' => 'Kelola Pesanan',
          ];
          echo $titles[$page] ?? getTitle();
        ?>
      </div>
      <a href="../index.php" target="_blank"
         style="width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#4A4A6A;background:#F0F2F5;text-decoration:none;" title="Lihat Website">
        <i class="fa fa-external-link"></i>
      </a>
      <a href="logout.php"
         style="width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#9B1C1C;background:#FDECEA;text-decoration:none;" title="Logout">
        <i class="fa fa-sign-out"></i>
      </a>
    </div>

    <!-- PAGE CONTENT (ditutup oleh footer.php) -->
    <div class="page-content" style="flex:1;padding:22px;">