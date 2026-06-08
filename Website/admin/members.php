<?php
ob_start(); session_start();
$pageTitle = 'Members';
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
include 'init.php';
$do = isset($_GET['do']) ? $_GET['do'] : 'Manage';

/* ---- Helper: page header ---- */
function pageHeader($title, $sub = '') {
    echo '<div style="background:linear-gradient(135deg,#1B2E5E,#2A3F80);color:#fff;padding:20px 0;margin-bottom:24px;">
    <div class="container"><div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;">Admin Panel</div>
    <div style="font-family:\'Playfair Display\',serif;font-size:22px;font-weight:700;">'.$title.'</div>
    '.($sub?'<div style="font-size:12px;opacity:.6;margin-top:2px;">'.$sub.'</div>':'').'
    </div></div>';
}
function cardWrap($html) {
    return '<div class="container" style="padding-bottom:40px;"><div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">'.$html.'</div></div>';
}
function formCard($html) {
    echo '<div class="container" style="padding-bottom:40px;"><div class="row"><div class="col-md-7"><div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">'.$html.'</div></div></div></div>';
}

if ($do == 'Manage') {
    $query = isset($_GET['page']) && $_GET['page']=='Pending' ? 'AND RegStatus = 0' : '';
    $stmt = $con->prepare("SELECT * FROM users WHERE GroupID != 1 $query ORDER BY UserID DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Count pending
    $stmtP = $con->prepare("SELECT COUNT(*) FROM users WHERE GroupID!=1 AND RegStatus=0");
    $stmtP->execute(); $pendingCount = $stmtP->fetchColumn();

    pageHeader('Kelola Members', count($rows).' member terdaftar');
    echo '<div class="container" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">';
    echo '<div style="display:flex;gap:8px;">';
    echo '<a href="members.php" style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;background:'.(!isset($_GET['page'])?'#1B2E5E':'#E8ECF5').';color:'.(!isset($_GET['page'])?'#fff':'#1B2E5E').';">Semua ('.countItems('UserID','users').')</a>';
    echo '<a href="members.php?page=Pending" style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;background:'.(isset($_GET['page'])?'#1B2E5E':'#E8ECF5').';color:'.(isset($_GET['page'])?'#fff':'#1B2E5E').';">Belum Aktif ('.$pendingCount.')</a>';
    echo '</div>';
    echo '<a href="members.php?do=Add" style="background:#1B2E5E;color:#fff;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;"><i class="fa fa-plus"></i> New Member</a>';
    echo '</div>';

    if (!empty($rows)) {
        $tbl = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        $tbl .= '<thead><tr style="background:#1B2E5E;color:#fff;">';
        foreach (['Avatar','Username','Role','Email','Nama Lengkap','Tgl Daftar','Status','Aksi'] as $h)
            $tbl .= '<th style="padding:11px 14px;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;text-align:left;">'.$h.'</th>';
        $tbl .= '</tr></thead><tbody>';
        foreach ($rows as $i => $row) {
            $bg = $i%2==0 ? '#fff' : '#F7F8FA';
            $role = ['0'=>'Pembeli','2'=>'Penjual'][$row['GroupID']] ?? 'Pembeli';
            $roleBg = $row['GroupID']==2 ? '#EAF5ED' : '#E8ECF5';
            $roleColor = $row['GroupID']==2 ? '#1A5C2A' : '#1B2E5E';
            $av = empty($row['avatar'])||$row['avatar']=='default.png' ? 'uploads/avatars/default.png' : 'uploads/avatars/'.htmlspecialchars($row['avatar']);
            $tbl .= '<tr style="background:'.$bg.';border-bottom:1px solid #DDE1EC;">';
            $tbl .= '<td style="padding:10px 14px;"><img src="'.$av.'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #DDE1EC;"></td>';
            $tbl .= '<td style="padding:10px 14px;font-weight:600;color:#1B2E5E;">'.htmlspecialchars($row['Username']).'</td>';
            $tbl .= '<td style="padding:10px 14px;"><span style="background:'.$roleBg.';color:'.$roleColor.';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">'.$role.'</span></td>';
            $tbl .= '<td style="padding:10px 14px;color:#4A4A6A;">'.htmlspecialchars($row['Email']).'</td>';
            $tbl .= '<td style="padding:10px 14px;">'.htmlspecialchars($row['FullName']).'</td>';
            $tbl .= '<td style="padding:10px 14px;color:#9A9AB0;font-size:12px;">'.$row['Date'].'</td>';
            $tbl .= '<td style="padding:10px 14px;">';
            if ($row['RegStatus']==0) $tbl .= '<span style="background:#FEF3C7;color:#92400E;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">Non-Aktif</span>';
            else $tbl .= '<span style="background:#EAF5ED;color:#1A5C2A;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">Aktif</span>';
            $tbl .= '</td>';
            $tbl .= '<td style="padding:10px 14px;white-space:nowrap;">';
            if ($row['RegStatus']==0) $tbl .= '<a href="members.php?do=Activate&userid='.$row['UserID'].'" style="background:#EAF5ED;color:#1A5C2A;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;margin-right:4px;" onclick="return confirm(\'Aktifkan akun ini?\')"><i class="fa fa-check"></i> Aktifkan</a>';
            $tbl .= '<a href="members.php?do=Edit&userid='.$row['UserID'].'" style="background:#1B2E5E;color:#fff;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;margin-right:4px;"><i class="fa fa-edit"></i> Edit</a>';
            $tbl .= '<a href="members.php?do=Delete&userid='.$row['UserID'].'" onclick="return confirm(\'Hapus member ini?\')" style="background:#FDECEA;color:#9B1C1C;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-trash"></i></a>';
            $tbl .= '</td></tr>';
        }
        $tbl .= '</tbody></table>';
        echo cardWrap($tbl);
    } else {
        echo '<div class="container"><div class="nice-message">Belum ada member.</div></div>';
    }

} elseif ($do == 'Add') {
    pageHeader('Tambah Member Baru');
    formCard('
    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#1B2E5E;padding-bottom:8px;border-bottom:2px solid #DDE1EC;margin-bottom:16px;">Informasi Member</p>
    <form action="?do=Insert" method="POST" enctype="multipart/form-data">
      <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">USERNAME *</label><input type="text" name="username" class="form-control" required placeholder="Min. 4 karakter"></div>
      <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">PASSWORD *</label><input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
      <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">EMAIL *</label><input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">NAMA LENGKAP *</label><input type="text" name="full" class="form-control" required></div>
      <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">ROLE</label>
        <select name="group_id" class="form-control">
          <option value="0">Pembeli</option>
          <option value="2">Penjual</option>
        </select>
      </div>
      <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">FOTO PROFIL</label><input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.gif" style="padding:6px 12px;"></div>
      <button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-plus"></i> Tambah Member</button>
      <a href="members.php" style="margin-left:12px;color:#9A9AB0;font-size:14px;">Batal</a>
    </form>');

} elseif ($do == 'Insert' && $_SERVER['REQUEST_METHOD']=='POST') {
    $user=$_POST['username']; $pass=$_POST['password']; $email=$_POST['email']; $name=$_POST['full'];
    $groupId = intval($_POST['group_id'] ?? 0);
    $hashPass=sha1($pass); $formErrors=[];
    if (strlen($user)<4) $formErrors[]='Username minimal 4 karakter.';
    if (empty($pass))   $formErrors[]='Password wajib diisi.';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $formErrors[]='Format email tidak valid.';
    $avatar='default.png';
    if (!empty($_FILES['avatar']['name'])) {
        $ext=strtolower(pathinfo($_FILES['avatar']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','gif'])) {
            $avatar=rand(0,9999999).'_'.basename($_FILES['avatar']['name']);
            move_uploaded_file($_FILES['avatar']['tmp_name'],'uploads/avatars/'.$avatar);
        }
    }
    if (empty($formErrors)) {
        if (checkItem("Username","users",$user)) { $formErrors[]='Username sudah dipakai.'; }
        else {
            $con->prepare("INSERT INTO users(Username,Password,Email,FullName,GroupID,RegStatus,Date,avatar) VALUES(?,?,?,?,?,1,NOW(),?)")
                ->execute([$user,$hashPass,$email,$name,$groupId,$avatar]);
            header("refresh:2;url=members.php");
            pageHeader('Member Ditambahkan');
            echo '<div class="container"><div class="alert alert-success"><i class="fa fa-check-circle"></i> Member berhasil ditambahkan! Mengalihkan...</div></div>';
            include $tpl.'footer.php'; ob_end_flush(); exit();
        }
    }
    pageHeader('Tambah Member Baru');
    echo '<div class="container">';
    foreach ($formErrors as $e) echo '<div class="alert alert-danger">'.$e.'</div>';
    echo '</div>';

} elseif ($do == 'Edit') {
    $userid = isset($_GET['userid'])&&is_numeric($_GET['userid']) ? intval($_GET['userid']) : 0;
    $stmt=$con->prepare("SELECT * FROM users WHERE UserID=? LIMIT 1"); $stmt->execute([$userid]); $row=$stmt->fetch();
    if ($stmt->rowCount()>0) {
        pageHeader('Edit Member: '.htmlspecialchars($row['Username']));
        formCard('
        <form action="?do=Update" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="userid" value="'.$userid.'">
          <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">USERNAME *</label><input type="text" name="username" class="form-control" value="'.htmlspecialchars($row['Username']).'" required></div>
          <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">PASSWORD BARU <small style="color:#9A9AB0;">(kosongkan jika tidak diganti)</small></label>
            <input type="hidden" name="oldpassword" value="'.$row['Password'].'">
            <input type="password" name="newpassword" class="form-control" autocomplete="new-password" placeholder="Password baru...">
          </div>
          <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">EMAIL *</label><input type="email" name="email" class="form-control" value="'.htmlspecialchars($row['Email']).'" required></div>
          <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">NAMA LENGKAP *</label><input type="text" name="full" class="form-control" value="'.htmlspecialchars($row['FullName']).'" required></div>
          <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">ROLE</label>
            <select name="group_id" class="form-control">
              <option value="0" '.($row['GroupID']==0?'selected':'').'>Pembeli</option>
              <option value="2" '.($row['GroupID']==2?'selected':'').'>Penjual</option>
            </select>
          </div>
          <div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">STATUS AKUN</label>
            <select name="reg_status" class="form-control">
              <option value="1" '.($row['RegStatus']==1?'selected':'').'>Aktif</option>
              <option value="0" '.($row['RegStatus']==0?'selected':'').'>Non-Aktif</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
          <a href="members.php" style="margin-left:12px;color:#9A9AB0;font-size:14px;">Batal</a>
        </form>');
    }

} elseif ($do == 'Update' && $_SERVER['REQUEST_METHOD']=='POST') {
    $id=$_POST['userid']; $user=$_POST['username']; $email=$_POST['email']; $name=$_POST['full'];
    $groupId=intval($_POST['group_id']??0); $regStatus=intval($_POST['reg_status']??1);
    $pass=empty($_POST['newpassword']) ? $_POST['oldpassword'] : sha1($_POST['newpassword']);
    $con->prepare("UPDATE users SET Username=?,Email=?,FullName=?,Password=?,GroupID=?,RegStatus=? WHERE UserID=?")
        ->execute([$user,$email,$name,$pass,$groupId,$regStatus,$id]);
    header("refresh:2;url=members.php");
    pageHeader('Member Diperbarui');
    echo '<div class="container"><div class="alert alert-success"><i class="fa fa-check-circle"></i> Member berhasil diperbarui! Mengalihkan...</div></div>';
    include $tpl.'footer.php'; ob_end_flush(); exit();

} elseif ($do == 'Delete') {
    $userid=isset($_GET['userid'])&&is_numeric($_GET['userid'])?intval($_GET['userid']):0;
    if (checkItem('userid','users',$userid)) {
        $con->prepare("DELETE FROM users WHERE UserID=?")->execute([$userid]);
    }
    header('Location: members.php'); exit();

} elseif ($do == 'Activate') {
    $userid=isset($_GET['userid'])&&is_numeric($_GET['userid'])?intval($_GET['userid']):0;
    $con->prepare("UPDATE users SET RegStatus=1 WHERE UserID=?")->execute([$userid]);
    header('Location: members.php'); exit();
}

include $tpl.'footer.php';
ob_end_flush();
?>