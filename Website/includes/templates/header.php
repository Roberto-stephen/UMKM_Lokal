<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php getTitle() ?> — MakanLokal</title>
  <link rel="stylesheet" href="<?php echo $css ?>bootstrap.min.css"/>
  <link rel="stylesheet" href="<?php echo $css ?>font-awesome.min.css"/>
  <link rel="stylesheet" href="<?php echo $css ?>jquery-ui.css"/>
  <link rel="stylesheet" href="<?php echo $css ?>front.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<!-- TOPBAR -->
<div class="upper-bar">
  <div class="container">
    <a href="index.php" class="site-brand">Makan<span>Lokal</span></a>

    <div class="search-bar">
      <form action="search.php" method="GET">
        <input type="text" name="q" placeholder="Cari makanan..." value="<?php echo isset($_GET['q'])?htmlspecialchars($_GET['q']):'' ?>" autocomplete="off">
        <button type="submit"><i class="fa fa-search"></i></button>
      </form>
    </div>

    <div class="top-right">
      <?php if (isset($_SESSION['user'])): ?>
        <!-- Cart Icon -->
        <?php $cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'],'qty')) : 0; ?>
        <a href="cart.php" style="position:relative;color:#1B2E5E;font-size:20px;padding:4px 8px;">
          <i class="fa fa-shopping-basket"></i>
          <?php if ($cartCount > 0): ?>
          <span style="position:absolute;top:-4px;right:-4px;background:#B5272A;color:#fff;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;"><?php echo $cartCount ?></span>
          <?php endif; ?>
        </a>
        <div class="btn-group">
          <span class="btn btn-default dropdown-toggle" style="border:1.5px solid #DDE1EC;font-size:13px;border-radius:30px;padding:6px 16px;color:#1B2E5E;font-weight:600;background:#fff;" data-toggle="dropdown">
            <img src="admin/uploads/avatars/<?php echo htmlspecialchars($sessionAvatar ?? 'default.png') ?>" class="user-avatar" style="width:22px;height:22px;margin-right:6px;">
            <?php echo htmlspecialchars($sessionUser ?? '') ?> <span class="caret"></span>
          </span>
          <ul class="dropdown-menu dropdown-menu-right" style="border-radius:10px;margin-top:6px;box-shadow:0 8px 32px rgba(27,46,94,.14);border:1px solid #DDE1EC;padding:6px 0;min-width:170px;">
            <li><a href="profile.php"  style="padding:9px 18px;font-size:13px;color:#1A1A2E;"><i class="fa fa-user fa-fw" style="color:#1B2E5E;"></i> Profil Saya</a></li>
            <li><a href="newad.php"    style="padding:9px 18px;font-size:13px;color:#1A1A2E;"><i class="fa fa-plus fa-fw"  style="color:#1B2E5E;"></i> Tambah Produk</a></li>
            <li><a href="myItems.php"  style="padding:9px 18px;font-size:13px;color:#1A1A2E;"><i class="fa fa-tag fa-fw"   style="color:#1B2E5E;"></i> Produk Saya</a></li>
            <li><a href="orders.php"   style="padding:9px 18px;font-size:13px;color:#1A1A2E;"><i class="fa fa-clipboard fa-fw" style="color:#1B2E5E;"></i> Pesanan Saya</a></li>
            <li role="separator" style="border-top:1px solid #DDE1EC;margin:4px 0;"></li>
            <li><a href="logout.php"   style="padding:9px 18px;font-size:13px;color:#9B1C1C;"><i class="fa fa-sign-out fa-fw"></i> Keluar</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="login.php" class="btn-login">Masuk / Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-inverse">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-nav">
        <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse" id="main-nav">
      <ul class="nav navbar-nav">
        <li<?php echo (basename($_SERVER['PHP_SELF'])=='index.php')?' class="active"':'' ?>><a href="index.php"><i class="fa fa-home"></i> Beranda</a></li>
        <?php
          $allCats = getAllFrom("*","categories","where parent = 0","","ID","ASC");
          foreach ($allCats as $cat) {
            $active = (isset($_GET['pageid']) && $_GET['pageid']==$cat['ID']) ? ' class="active"' : '';
            echo '<li'.$active.'><a href="categories.php?pageid='.$cat['ID'].'">'.htmlspecialchars($cat['Name']).'</a></li>';
          }
        ?>
      </ul>
    </div>
  </div>
</nav>