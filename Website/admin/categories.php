<?php
ob_start(); session_start();
$pageTitle = 'Categories';
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
include 'init.php';
$do = isset($_GET['do']) ? $_GET['do'] : 'Manage';

function catBanner($title) {
    echo '<div style="background:linear-gradient(135deg,#1A5C2A,#27AE60);color:#fff;padding:20px 0;margin-bottom:24px;">
    <div class="container"><div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;">Admin Panel</div>
    <div style="font-family:\'Playfair Display\',serif;font-size:22px;font-weight:700;">'.$title.'</div>
    </div></div>';
}

if ($do == 'Manage') {
    $sort = (isset($_GET['sort']) && in_array($_GET['sort'],['asc','desc'])) ? $_GET['sort'] : 'asc';
    $stmt2=$con->prepare("SELECT * FROM categories WHERE parent=0 ORDER BY Ordering $sort"); $stmt2->execute(); $cats=$stmt2->fetchAll();

    catBanner('Kelola Kategori');
    echo '<div class="container" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">';
    echo '<div style="display:flex;gap:8px;align-items:center;font-size:13px;color:#4A4A6A;">
      Urutan:
      <a href="?sort=asc" style="font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px;background:'.($sort=='asc'?'#1A5C2A':'#E8ECF5').';color:'.($sort=='asc'?'#fff':'#1B2E5E').';">A→Z</a>
      <a href="?sort=desc" style="font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px;background:'.($sort=='desc'?'#1A5C2A':'#E8ECF5').';color:'.($sort=='desc'?'#fff':'#1B2E5E').';">Z→A</a>
    </div>';
    echo '<a href="categories.php?do=Add" style="background:#1A5C2A;color:#fff;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;"><i class="fa fa-plus"></i> Tambah Kategori</a>';
    echo '</div>';

    echo '<div class="container" style="padding-bottom:40px;">';
    if (!empty($cats)) {
        echo '<div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">';
        foreach ($cats as $i=>$cat) {
            $bg = $i%2==0?'#fff':'#F7F8FA';
            $prodCount = $con->prepare("SELECT COUNT(*) FROM items WHERE Cat_ID=?"); $prodCount->execute([$cat['ID']]); $pc=$prodCount->fetchColumn();
            echo '<div style="padding:16px 20px;border-bottom:1px solid #DDE1EC;background:'.$bg.';display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
            echo '<div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1A5C2A,#27AE60);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0;"><i class="fa fa-tag"></i></div>';
            echo '<div style="flex:1;">';
            echo '<div style="font-weight:700;color:#1B2E5E;font-size:15px;">'.htmlspecialchars($cat['Name']).'</div>';
            echo '<div style="font-size:12px;color:#9A9AB0;margin-top:2px;">'.($cat['Description']?htmlspecialchars($cat['Description']):'Tidak ada deskripsi').' &nbsp;·&nbsp; <strong style="color:#1A5C2A;">'.$pc.' produk</strong></div>';
            echo '</div>';
            echo '<div style="display:flex;gap:6px;">';
            echo '<a href="categories.php?do=Edit&catid='.$cat['ID'].'" style="background:#1B2E5E;color:#fff;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;"><i class="fa fa-edit"></i> Edit</a>';
            echo '<a href="categories.php?do=Delete&catid='.$cat['ID'].'" onclick="return confirm(\'Hapus kategori ini? Produk di dalamnya juga akan terhapus!\')" style="background:#FDECEA;color:#9B1C1C;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;"><i class="fa fa-trash"></i></a>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="nice-message">Belum ada kategori.</div>';
    }
    echo '</div>';

} elseif ($do == 'Add') {
    catBanner('Tambah Kategori Baru');
    $allCats=getAllFrom("*","categories","where parent = 0","","ID","ASC");
    echo '<div class="container" style="padding-bottom:40px;"><div class="row"><div class="col-md-6"><div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">';
    echo '<form action="?do=Insert" method="POST">';
    echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">NAMA KATEGORI *</label><input type="text" name="name" class="form-control" required placeholder="Contoh: Makanan Berat"></div>';
    echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">DESKRIPSI</label><input type="text" name="description" class="form-control" placeholder="Deskripsi singkat kategori"></div>';
    echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">URUTAN</label><input type="number" name="ordering" class="form-control" value="1"></div>';
    echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">PARENT KATEGORI</label><select name="parent" class="form-control"><option value="0">-- Tidak Ada (Kategori Utama) --</option>';
    foreach ($allCats as $cat) echo '<option value="'.$cat['ID'].'">'.htmlspecialchars($cat['Name']).'</option>';
    echo '</select></div>';
    echo '<input type="hidden" name="visibility" value="0"><input type="hidden" name="commenting" value="0"><input type="hidden" name="ads" value="0">';
    echo '<button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah Kategori</button> <a href="categories.php" style="color:#9A9AB0;font-size:14px;margin-left:10px;">Batal</a>';
    echo '</form></div></div></div></div>';

} elseif ($do == 'Insert' && $_SERVER['REQUEST_METHOD']=='POST') {
    $name=$_POST['name']; $desc=$_POST['description']; $parent=$_POST['parent']; $order=$_POST['ordering'];
    $visible=$_POST['visibility']??0; $comment=$_POST['commenting']??0; $ads=$_POST['ads']??0;
    if (checkItem("Name","categories",$name)) {
        catBanner('Kategori Gagal Ditambahkan');
        echo '<div class="container"><div class="alert alert-danger">Kategori "'.$name.'" sudah ada!</div></div>';
    } else {
        $con->prepare("INSERT INTO categories(Name,Description,parent,Ordering,Visibility,Allow_Comment,Allow_Ads) VALUES(?,?,?,?,?,?,?)")
            ->execute([$name,$desc,$parent,$order,$visible,$comment,$ads]);
        header("refresh:2;url=categories.php");
        catBanner('Kategori Ditambahkan');
        echo '<div class="container"><div class="alert alert-success"><i class="fa fa-check-circle"></i> Kategori berhasil ditambahkan!</div></div>';
        include $tpl.'footer.php'; ob_end_flush(); exit();
    }

} elseif ($do == 'Edit') {
    $catid=isset($_GET['catid'])&&is_numeric($_GET['catid'])?intval($_GET['catid']):0;
    $stmt=$con->prepare("SELECT * FROM categories WHERE ID=?"); $stmt->execute([$catid]); $cat=$stmt->fetch();
    if ($stmt->rowCount()>0) {
        catBanner('Edit Kategori: '.htmlspecialchars($cat['Name']));
        $allCats=getAllFrom("*","categories","where parent = 0","","ID","ASC");
        echo '<div class="container" style="padding-bottom:40px;"><div class="row"><div class="col-md-6"><div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">';
        echo '<form action="?do=Update" method="POST"><input type="hidden" name="catid" value="'.$catid.'">';
        echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">NAMA *</label><input type="text" name="name" class="form-control" required value="'.htmlspecialchars($cat['Name']).'"></div>';
        echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">DESKRIPSI</label><input type="text" name="description" class="form-control" value="'.htmlspecialchars($cat['Description']).'"></div>';
        echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">URUTAN</label><input type="number" name="ordering" class="form-control" value="'.$cat['Ordering'].'"></div>';
        echo '<div class="form-group"><label class="control-label" style="font-size:12px;font-weight:600;color:#4A4A6A;">PARENT</label><select name="parent" class="form-control"><option value="0">-- Tidak Ada --</option>';
        foreach ($allCats as $c) echo '<option value="'.$c['ID'].'"'.($cat['parent']==$c['ID']?' selected':'').'>'.htmlspecialchars($c['Name']).'</option>';
        echo '</select></div>';
        echo '<input type="hidden" name="visibility" value="'.$cat['Visibility'].'"><input type="hidden" name="commenting" value="'.$cat['Allow_Comment'].'"><input type="hidden" name="ads" value="'.$cat['Allow_Ads'].'">';
        echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button> <a href="categories.php" style="color:#9A9AB0;font-size:14px;margin-left:10px;">Batal</a>';
        echo '</form></div></div></div></div>';
    }

} elseif ($do == 'Update' && $_SERVER['REQUEST_METHOD']=='POST') {
    $id=$_POST['catid']; $name=$_POST['name']; $desc=$_POST['description'];
    $order=$_POST['ordering']; $parent=$_POST['parent'];
    $visible=$_POST['visibility']; $comment=$_POST['commenting']; $ads=$_POST['ads'];
    $con->prepare("UPDATE categories SET Name=?,Description=?,Ordering=?,parent=?,Visibility=?,Allow_Comment=?,Allow_Ads=? WHERE ID=?")
        ->execute([$name,$desc,$order,$parent,$visible,$comment,$ads,$id]);
    header('Location: categories.php'); exit();

} elseif ($do == 'Delete') {
    $catid=isset($_GET['catid'])&&is_numeric($_GET['catid'])?intval($_GET['catid']):0;
    if (checkItem('ID','categories',$catid)) {
        $con->prepare("DELETE FROM categories WHERE ID=?")->execute([$catid]);
    }
    header('Location: categories.php'); exit();
}

include $tpl.'footer.php';
ob_end_flush();
?>