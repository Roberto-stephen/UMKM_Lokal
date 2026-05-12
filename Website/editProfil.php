<?php
ob_start();
session_start();
$pageTitle = 'Edit Profil';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

function getSingleValue($con, $sql, $parameters) {
    $q = $con->prepare($sql);
    $q->execute($parameters);
    return $q->fetchColumn();
}
$userid = getSingleValue($con, "SELECT UserID FROM users WHERE Username=?", [$_SESSION['user']]);

$stmt = $con->prepare("SELECT * FROM users WHERE UserID=? LIMIT 1");
$stmt->execute([$userid]);
$row = $stmt->fetch();

$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $fullname = trim($_POST['fullname']);
    $newpass  = $_POST['newpassword'];

    if (strlen($username) < 4) $errors[] = 'Username minimal 4 karakter.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';

    // Cek username duplikat (kecuali milik sendiri)
    $chk = $con->prepare("SELECT UserID FROM users WHERE Username=? AND UserID!=?");
    $chk->execute([$username, $userid]);
    if ($chk->rowCount() > 0) $errors[] = 'Username sudah dipakai user lain.';

    $avatar = $row['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','gif'])) {
            $errors[] = 'Format foto tidak didukung.';
        } elseif ($_FILES['avatar']['size'] > 2000000) {
            $errors[] = 'Ukuran foto maksimal 2MB.';
        } else {
            $avatar = rand(10000,9999999).'_'.basename($_FILES['avatar']['name']);
            move_uploaded_file($_FILES['avatar']['tmp_name'], 'admin/uploads/avatars/'.$avatar);
        }
    }

    if (empty($errors)) {
        $passVal = !empty($newpass) ? sha1($newpass) : $row['Password'];
        $upd = $con->prepare("UPDATE users SET Username=?,Email=?,FullName=?,Password=?,avatar=? WHERE UserID=?");
        $upd->execute([htmlspecialchars($username), htmlspecialchars($email), htmlspecialchars($fullname), $passVal, $avatar, $userid]);
        $_SESSION['user']   = $username;
        $_SESSION['avatar'] = $avatar;
        $success = 'Profil berhasil diperbarui!';
        // Refresh data
        $stmt->execute([$userid]);
        $row = $stmt->fetch();
    }
}
?>

<div class="page-banner"><div class="container">
  <h1>Edit Profil</h1>
  <div class="breadcrumb-custom"><a href="index.php">Beranda</a> &rsaquo; <a href="profile.php">Profil</a> &rsaquo; <span>Edit</span></div>
</div></div>

<div class="container" style="padding:40px 0;">
<div class="row"><div class="col-md-7 col-md-offset-2">

<?php foreach ($errors as $e) echo '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> '.$e.'</div>'; ?>
<?php if ($success) echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> '.$success.'</div>'; ?>

<div style="background:#fff;border-radius:14px;padding:32px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">

  <!-- FOTO PROFIL -->
  <div style="text-align:center;margin-bottom:28px;">
    <?php
      $av = $row['avatar'] ?? 'default.png';
      $avSrc = (!empty($av) && $av!='default.png') ? 'admin/uploads/avatars/'.htmlspecialchars($av) : null;
    ?>
    <?php if ($avSrc): ?>
      <img src="<?php echo $avSrc ?>" id="preview-avatar" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid #E8ECF5;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;">
    <?php else: ?>
      <div id="preview-avatar-text" style="width:88px;height:88px;border-radius:50%;background:#E8ECF5;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:36px;font-weight:700;color:#1B2E5E;">
        <?php echo strtoupper(substr($row['Username'],0,1)) ?>
      </div>
    <?php endif; ?>
    <div style="font-size:13px;color:#9A9AB0;">Klik untuk ganti foto profil</div>
    <label for="avatar-input" style="cursor:pointer;display:inline-block;margin-top:8px;background:#E8ECF5;color:#1B2E5E;padding:7px 18px;border-radius:20px;font-size:13px;font-weight:600;">
      <i class="fa fa-camera"></i> Pilih Foto
    </label>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <input type="file" id="avatar-input" name="avatar" accept=".jpg,.jpeg,.png,.gif" style="display:none;" onchange="previewAvatar(this)">

    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">USERNAME *</label>
      <input class="form-control" type="text" name="username" value="<?php echo htmlspecialchars($row['Username']) ?>" required minlength="4">
    </div>

    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">NAMA LENGKAP</label>
      <input class="form-control" type="text" name="fullname" value="<?php echo htmlspecialchars($row['FullName']) ?>">
    </div>

    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">EMAIL *</label>
      <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($row['Email']) ?>" required>
    </div>

    <div style="border-top:1px solid #DDE1EC;margin:20px 0;"></div>

    <div class="form-group">
      <label style="font-size:12px;font-weight:600;color:#4A4A6A;display:block;margin-bottom:5px;">PASSWORD BARU <span style="color:#9A9AB0;font-weight:400;">(kosongkan jika tidak ingin mengganti)</span></label>
      <input class="form-control" type="password" name="newpassword" placeholder="Masukkan password baru" autocomplete="new-password">
    </div>

    <div style="display:flex;gap:12px;align-items:center;margin-top:24px;">
      <button type="submit" class="btn-submit"><i class="fa fa-save"></i> Simpan Perubahan</button>
      <a href="profile.php" style="font-size:14px;color:#9A9AB0;">Batal</a>
    </div>
  </form>
</div>

</div></div></div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var prev = document.getElementById('preview-avatar');
      if (!prev) {
        document.getElementById('preview-avatar-text').outerHTML = '<img id="preview-avatar" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid #E8ECF5;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;">';
        prev = document.getElementById('preview-avatar');
      }
      prev.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include $tpl.'footer.php'; ob_end_flush(); ?>