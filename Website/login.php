<?php
ob_start();
session_start();
$pageTitle = 'Masuk / Daftar';
if (isset($_SESSION['user'])) { header('Location: index.php'); exit(); }
include 'init.php';

$formErrors = [];
$succesMsg  = '';
$activeTab  = 'login';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  /* ---- LOGIN ---- */
  if (isset($_POST['login'])) {
    $user       = $_POST['username'];
    $hashedPass = sha1($_POST['password']);

    // RegStatus = 1 dicek untuk kompatibilitas data lama.
    // Semua akun baru otomatis RegStatus = 1 (lihat bagian SIGNUP).
    $stmt = $con->prepare("SELECT UserID, Username, Password, avatar, GroupID FROM users WHERE Username = ? AND Password = ? AND RegStatus = 1");
    $stmt->execute([$user, $hashedPass]);
    $get  = $stmt->fetch();

    if ($stmt->rowCount() > 0) {
      $_SESSION['user']    = $get['Username'];
      $_SESSION['uid']     = $get['UserID'];
      $_SESSION['avatar']  = $get['avatar'];
      $_SESSION['GroupID'] = $get['GroupID'];

      if ($get['GroupID'] == 1) { header('Location: admin/dashboard.php'); }
      else                      { header('Location: index.php'); }
      exit();
    } else {
      $formErrors[] = 'Username atau password salah.';
      $activeTab = 'login';
    }

  /* ---- SIGNUP ---- */
  } else {
    $activeTab = 'signup';
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $password2 = $_POST['password2'];
    $email     = trim($_POST['email']);
    $fullname  = trim($_POST['fullname']);

    if (strlen($username) < 4)                    $formErrors[] = 'Username minimal 4 karakter.';
    if (empty($password))                          $formErrors[] = 'Password tidak boleh kosong.';
    if (sha1($password) !== sha1($password2))      $formErrors[] = 'Password tidak cocok.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $formErrors[] = 'Format email tidak valid.';

    if (empty($formErrors)) {
      if (checkItem("Username","users",$username) > 0) {
        $formErrors[] = 'Username sudah dipakai, coba yang lain.';
      } else {
        /* Handle avatar upload */
        $avatar = 'default.png';
        if (!empty($_FILES['pictures']['name'])) {
          $ext    = strtolower(pathinfo($_FILES['pictures']['name'], PATHINFO_EXTENSION));
          $allowed = ['jpg','jpeg','png','gif'];
          if (in_array($ext, $allowed) && $_FILES['pictures']['size'] < 2000000) {
            $avatar = rand(0,9999999) . '_' . basename($_FILES['pictures']['name']);
            move_uploaded_file($_FILES['pictures']['tmp_name'], "admin/uploads/avatars/" . $avatar);
          }
        }

        // ------------------------------------------------------------
        // FIX: RegStatus langsung 1 (aktif) — tidak perlu approval admin.
        // Dulu: RegStatus = 0 (harus diaktifkan admin dulu sebelum bisa login)
        // ------------------------------------------------------------
        $ins = $con->prepare("INSERT INTO users(Username,Password,Email,FullName,RegStatus,Date,avatar) VALUES(?,?,?,?,1,NOW(),?)");
        $ins->execute([htmlspecialchars($username), sha1($password), htmlspecialchars($email), htmlspecialchars($fullname), $avatar]);

        // Langsung login-kan user setelah daftar (auto-login)
        $newUserId = $con->lastInsertId();
        $_SESSION['user']    = $username;
        $_SESSION['uid']     = $newUserId;
        $_SESSION['avatar']  = $avatar;
        $_SESSION['GroupID'] = 0; // default: Pembeli

        header('Location: index.php?welcome=1');
        exit();
      }
    }
  }
}
?>

<div class="login-page-wrap">
  <div class="login-card">
    <div class="brand">Makan<span>Lokal</span></div>
    <div class="sub">Platform UMKM Makanan Lokal Tangerang</div>

    <!-- TABS -->
    <div class="login-tabs">
      <div class="login-tab <?php echo $activeTab=='login'?'active':'' ?>" onclick="switchTab('login')">
        <i class="fa fa-sign-in"></i> Masuk
      </div>
      <div class="login-tab <?php echo $activeTab=='signup'?'active':'' ?>" onclick="switchTab('signup')">
        <i class="fa fa-user-plus"></i> Daftar
      </div>
    </div>

    <!-- PESAN ERROR / SUCCESS -->
    <?php if (!empty($formErrors)): ?>
      <?php foreach ($formErrors as $e): ?>
        <div class="alert alert-danger" style="margin-bottom:12px;"><i class="fa fa-exclamation-circle"></i> <?php echo $e ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($succesMsg): ?>
      <div class="alert alert-success" style="margin-bottom:12px;"><i class="fa fa-check-circle"></i> <?php echo $succesMsg ?></div>
    <?php endif; ?>

    <!-- TAB LOGIN -->
    <div id="tab-login" class="tab-pane <?php echo $activeTab=='login'?'active':'' ?>">
      <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" placeholder="Masukkan username" required autocomplete="off">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input class="form-control" type="password" name="password" placeholder="Masukkan password" required>
        </div>
        <button type="submit" name="login" class="btn-submit">Masuk</button>
      </form>
      <div class="login-switch">Belum punya akun? <a onclick="switchTab('signup')">Daftar sekarang</a></div>
    </div>

    <!-- TAB SIGNUP -->
    <div id="tab-signup" class="tab-pane <?php echo $activeTab=='signup'?'active':'' ?>">
      <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" placeholder="Min. 4 karakter" required pattern=".{4,}">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input class="form-control" type="password" name="password" placeholder="Min. 6 karakter" required minlength="6">
        </div>
        <div class="form-group">
          <label>Konfirmasi Password</label>
          <input class="form-control" type="password" name="password2" placeholder="Ulangi password" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input class="form-control" type="email" name="email" placeholder="contoh@email.com" required>
        </div>
        <div class="form-group">
          <label>Nama Lengkap</label>
          <input class="form-control" type="text" name="fullname" placeholder="Nama lengkap kamu" required>
        </div>
        <div class="form-group">
          <label>Foto Profil <span style="color:#9A9AB0;font-weight:400;">(opsional)</span></label>
          <input class="form-control" type="file" name="pictures" accept=".jpg,.jpeg,.png,.gif" style="padding:6px 14px;">
        </div>
        <button type="submit" name="signup" class="btn-submit">Buat Akun</button>
      </form>
      <div class="login-switch">Sudah punya akun? <a onclick="switchTab('login')">Masuk di sini</a></div>
    </div>

  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelector('[onclick="switchTab(\''+tab+'\')"]').classList.add('active');
  document.getElementById('tab-'+tab).classList.add('active');
}
</script>

<?php include $tpl . 'footer.php'; ob_end_flush(); ?>