<nav class="navbar navbar-inverse">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#admin-nav">
        <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="dashboard.php">Makan<span>Lokal</span> <small style="font-size:10px;opacity:.6;font-family:'DM Sans',sans-serif;font-weight:400;">Admin Panel</small></a>
    </div>
    <div class="collapse navbar-collapse" id="admin-nav">
      <ul class="nav navbar-nav">
        <li class="<?php echo basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
          <a href="dashboard.php"><i class="fa fa-tachometer"></i> Dashboard</a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF'])=='categories.php'?'active':'' ?>">
          <a href="categories.php"><i class="fa fa-th-large"></i> <?php echo lang('CATEGORIES') ?></a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF'])=='items.php'?'active':'' ?>">
          <a href="items.php"><i class="fa fa-tag"></i> <?php echo lang('ITEMS') ?></a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF'])=='members.php'?'active':'' ?>">
          <a href="members.php"><i class="fa fa-users"></i> <?php echo lang('MEMBERS') ?></a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF'])=='comments.php'?'active':'' ?>">
          <a href="comments.php"><i class="fa fa-comments"></i> <?php echo lang('FEEDBACKS') ?></a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF'])=='orders_admin.php'?'active':'' ?>">
          <a href="orders_admin.php"><i class="fa fa-clipboard"></i> Pesanan</a>
        </li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <li class="dropdown">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <i class="fa fa-user-circle"></i> <?php echo isset($_SESSION['Username']) ? htmlspecialchars($_SESSION['Username']) : 'Admin' ?> <span class="caret"></span>
          </a>
          <ul class="dropdown-menu">
            <li><a href="../index.php"><i class="fa fa-home fa-fw" style="color:#1B2E5E;"></i> Lihat Website</a></li>
            <li role="separator" style="border-top:1px solid #DDE1EC;margin:4px 0;"></li>
            <li><a href="logout.php" style="color:#9B1C1C;"><i class="fa fa-sign-out fa-fw"></i> Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>