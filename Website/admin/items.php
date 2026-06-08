<?php
ob_start();
session_start();

$pageTitle = 'Items';
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
include 'init.php';

$do = $_GET['do'] ?? 'Manage';

// -------------------------------------------------------
// Helper: Banner merah di atas konten halaman
// -------------------------------------------------------
function adminPageBanner($title, $sub = '') {
    echo '<div style="background:linear-gradient(135deg,#B5272A,#D44040);color:#fff;padding:20px 0;margin:-22px -22px 24px -22px;">
        <div style="padding:0 22px;">
            <div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Admin Panel</div>
            <div style="font-family:\'Playfair Display\',serif;font-size:22px;font-weight:700;">' . $title . '</div>
            ' . ($sub ? '<div style="font-size:12px;opacity:.7;margin-top:3px;">' . $sub . '</div>' : '') . '
        </div>
    </div>';
}

// -------------------------------------------------------
// MANAGE — Daftar semua produk
// -------------------------------------------------------
if ($do === 'Manage') {

    $filterApprove = $_GET['filter'] ?? '';
    $whereClause   = '';
    if ($filterApprove === 'pending') $whereClause = 'WHERE items.Approve = 0';
    elseif ($filterApprove === 'active') $whereClause = 'WHERE items.Approve = 1';

    $items = [];
    $pc    = 0;
    $queryError = '';

    try {
        $stmt = $con->prepare("
            SELECT   items.*, 
                     categories.Name AS category_name, 
                     users.Username
            FROM     items
            LEFT JOIN categories ON categories.ID     = items.Cat_ID
            LEFT JOIN users      ON users.UserID      = items.Member_ID
            $whereClause
            ORDER BY items.Item_ID DESC
        ");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtPending = $con->prepare("SELECT COUNT(*) FROM items WHERE Approve = 0");
        $stmtPending->execute();
        $pc = (int)$stmtPending->fetchColumn();

    } catch (Exception $e) {
        $queryError = $e->getMessage();
    }

    adminPageBanner('Kelola Produk', count($items) . ' produk ditemukan');

    // ---- Toolbar (filter + tombol tambah) ----
    echo '<div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
    $filters = ['' => 'Semua', 'pending' => 'Pending (' . $pc . ')', 'active' => 'Aktif'];
    foreach ($filters as $f => $label) {
        $active = ($filterApprove === $f);
        echo '<a href="items.php' . ($f ? '?filter=' . $f : '') . '" 
                 style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;
                        background:' . ($active ? '#B5272A' : '#E8ECF5') . ';
                        color:' . ($active ? '#fff' : '#1B2E5E') . ';
                        text-decoration:none;">' . $label . '</a>';
    }
    echo '</div>';
    echo '<a href="items.php?do=Add" style="background:#B5272A;color:#fff;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            <i class="fa fa-plus"></i> Tambah Produk
          </a>';
    echo '</div>';

    // ---- Error DB ----
    if ($queryError) {
        echo '<div style="background:#FDECEA;color:#9B1C1C;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;">
                <i class="fa fa-exclamation-triangle"></i> Gagal memuat data: ' . htmlspecialchars($queryError) . '
              </div>';
    }

    // ---- Tabel produk ----
    if (!empty($items)) {
        echo '<div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">';
        echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        echo '<thead><tr style="background:#B5272A;color:#fff;">';
        foreach (['Foto', 'Nama Produk', 'Harga', 'Stok', 'Kategori', 'Penjual', 'Tanggal', 'Status', 'Aksi'] as $h) {
            echo '<th style="padding:11px 14px;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;text-align:left;white-space:nowrap;">' . $h . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($items as $i => $item) {
            $bg   = ($i % 2 === 0) ? '#fff' : '#F7F8FA';
            $pic  = (!empty($item['picture']) && $item['picture'] !== 'default.png')
                        ? 'uploads/items/' . htmlspecialchars($item['picture'])
                        : 'uploads/items/default.png';
            $stok = $item['stok'] ?? null;
            $stokLabel = ($stok === null) ? '-' : $stok;
            if ($stok === null)   $stokColor = '#9A9AB0';
            elseif ($stok > 10)   $stokColor = '#1A5C2A';
            elseif ($stok > 0)    $stokColor = '#92400E';
            else                  $stokColor = '#9B1C1C';

            echo '<tr style="background:' . $bg . ';border-bottom:1px solid #EEF0F6;transition:background .1s;" onmouseover="this.style.background=\'#F0F4FF\'" onmouseout="this.style.background=\'' . $bg . '\'">';
            echo '<td style="padding:8px 14px;"><img src="' . $pic . '" style="width:44px;height:44px;object-fit:cover;border-radius:8px;background:#EEF0F6;border:1px solid #DDE1EC;" onerror="this.style.background=\'#EEF0F6\';this.src=\'uploads/items/default.png\'"></td>';
            echo '<td style="padding:8px 14px;font-weight:600;color:#1B2E5E;max-width:180px;"><span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px;" title="' . htmlspecialchars($item['Name']) . '">' . htmlspecialchars($item['Name']) . '</span></td>';
            echo '<td style="padding:8px 14px;font-weight:600;color:#B5272A;white-space:nowrap;">Rp ' . number_format($item['Price'], 0, ',', '.') . '</td>';
            echo '<td style="padding:8px 14px;font-weight:600;color:' . $stokColor . ';">' . $stokLabel . '</td>';
            echo '<td style="padding:8px 14px;color:#4A4A6A;">' . htmlspecialchars($item['category_name'] ?? '-') . '</td>';
            echo '<td style="padding:8px 14px;color:#4A4A6A;">' . htmlspecialchars($item['Username'] ?? '-') . '</td>';
            echo '<td style="padding:8px 14px;color:#9A9AB0;font-size:11px;white-space:nowrap;">' . htmlspecialchars($item['Add_Date'] ?? '') . '</td>';
            echo '<td style="padding:8px 14px;">';
            if ($item['Approve'] == 0)
                echo '<span style="background:#FEF3C7;color:#92400E;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;">Pending</span>';
            else
                echo '<span style="background:#EAF5ED;color:#1A5C2A;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;">Aktif</span>';
            echo '</td>';
            echo '<td style="padding:8px 14px;white-space:nowrap;">';
            if ($item['Approve'] == 0)
                echo '<a href="items.php?do=Approve&itemid=' . $item['Item_ID'] . '" style="background:#EAF5ED;color:#1A5C2A;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;margin-right:3px;text-decoration:none;display:inline-block;"><i class="fa fa-check"></i> Approve</a>';
            echo '<a href="items.php?do=Edit&itemid=' . $item['Item_ID'] . '" style="background:#1B2E5E;color:#fff;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;margin-right:3px;text-decoration:none;display:inline-block;"><i class="fa fa-edit"></i> Edit</a>';
            echo '<a href="items.php?do=Delete&itemid=' . $item['Item_ID'] . '" onclick="return confirm(\'Hapus produk ini? Tindakan tidak dapat dibatalkan.\')" style="background:#FDECEA;color:#9B1C1C;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;display:inline-block;"><i class="fa fa-trash"></i></a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

    } else {
        // Empty state
        echo '<div style="background:#fff;border-radius:14px;padding:60px 24px;text-align:center;border:1px solid #DDE1EC;">
                <i class="fa fa-tag" style="font-size:40px;color:#DDE1EC;margin-bottom:14px;display:block;"></i>
                <div style="font-size:16px;font-weight:600;color:#4A4A6A;margin-bottom:6px;">Belum ada produk</div>
                <div style="font-size:13px;color:#9A9AB0;margin-bottom:20px;">Mulai dengan menambahkan produk pertama</div>
                <a href="items.php?do=Add" style="background:#B5272A;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                  <i class="fa fa-plus"></i> Tambah Produk
                </a>
              </div>';
    }

// -------------------------------------------------------
// ADD — Form tambah produk baru
// -------------------------------------------------------
} elseif ($do === 'Add') {

    adminPageBanner('Tambah Produk Baru');

    try {
        $allCats    = $con->query("SELECT ID, Name FROM categories WHERE parent = 0 ORDER BY Name ASC")->fetchAll();
        $allMembers = $con->query("SELECT UserID, Username FROM users ORDER BY Username ASC")->fetchAll();
    } catch (Exception $e) {
        $allCats = $allMembers = [];
    }

    echo '<div style="max-width:760px;">';
    echo '<div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">';
    echo '<form action="items.php?do=Insert" method="POST" enctype="multipart/form-data">';

    // Row 1: Nama + Harga + Stok
    echo '<div class="row">';
    echo '<div class="col-md-6"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">NAMA PRODUK *</label>
            <input type="text" name="name" class="form-control" required placeholder="Nama produk">
          </div></div>';
    echo '<div class="col-md-3"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">HARGA *</label>
            <input type="number" name="price" class="form-control" required min="0" placeholder="0">
          </div></div>';
    echo '<div class="col-md-3"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">STOK</label>
            <input type="number" name="stok" class="form-control" min="0" value="0">
          </div></div>';
    echo '</div>';

    // Deskripsi
    echo '<div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">DESKRIPSI *</label>
            <textarea name="description" class="form-control" rows="3" required placeholder="Deskripsi produk..."></textarea>
          </div>';

    // Row 3: Kontak + Kategori + Pemilik
    echo '<div class="row">';
    echo '<div class="col-md-4"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">KONTAK</label>
            <input type="text" name="contact" class="form-control" placeholder="No. HP / WA">
          </div></div>';
    echo '<div class="col-md-4"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">KATEGORI</label>
            <select name="category" class="form-control">';
    foreach ($allCats as $cat)
        echo '<option value="' . $cat['ID'] . '">' . htmlspecialchars($cat['Name']) . '</option>';
    echo '</select></div></div>';
    echo '<div class="col-md-4"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">PEMILIK</label>
            <select name="member" class="form-control">';
    foreach ($allMembers as $u)
        echo '<option value="' . $u['UserID'] . '">' . htmlspecialchars($u['Username']) . '</option>';
    echo '</select></div></div>';
    echo '</div>';

    // Row 4: Status Approve + Foto
    echo '<div class="row">';
    echo '<div class="col-md-4"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">STATUS</label>
            <select name="approve" class="form-control">
              <option value="1">Langsung Aktif</option>
              <option value="0">Pending Review</option>
            </select>
          </div></div>';
    echo '<div class="col-md-8"><div class="form-group">
            <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">FOTO PRODUK</label>
            <input type="file" name="picture" class="form-control" accept="image/*">
            <small style="color:#9A9AB0;font-size:11px;">JPG / PNG, maks. 2MB</small>
          </div></div>';
    echo '</div>';

    echo '<hr style="border-color:#EEF0F6;margin:8px 0 20px;">';
    echo '<button type="submit" class="btn btn-danger"><i class="fa fa-plus"></i> Tambah Produk</button>
          &nbsp;
          <a href="items.php" style="color:#9A9AB0;font-size:14px;margin-left:8px;">Batal</a>';
    echo '</form>';
    echo '</div></div>';

// -------------------------------------------------------
// INSERT — Simpan produk baru ke DB
// -------------------------------------------------------
} elseif ($do === 'Insert' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name']        ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $stok        = (int)($_POST['stok']       ?? 0);
    $cat         = (int)($_POST['category']   ?? 0);
    $member      = (int)($_POST['member']     ?? 0);
    $contact     = trim($_POST['contact']     ?? '');
    $approve     = (int)($_POST['approve']    ?? 1);
    $country     = 'Indonesia';
    $status      = 1;
    $addDate     = date('Y-m-d H:i:s');
    $pictureName = 'default.png';

    // Upload foto
    if (!empty($_FILES['picture']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $_FILES['picture']['size'] <= 2097152) {
            $pictureName = uniqid('item_') . '.' . $ext;
            $uploadDir   = 'uploads/items/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['picture']['tmp_name'], $uploadDir . $pictureName);
        }
    }

    try {
        $con->prepare("
            INSERT INTO items
                (Name, Description, Price, Country_Made, Status, Cat_ID, Member_ID, contact, stok, Approve, Add_Date, picture)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$name, $desc, $price, $country, $status, $cat, $member, $contact, $stok, $approve, $addDate, $pictureName]);

        adminPageBanner('Produk Berhasil Ditambahkan');
        echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> Produk <strong>' . htmlspecialchars($name) . '</strong> berhasil ditambahkan. <a href="items.php">Kembali ke daftar &rarr;</a></div>';
    } catch (Exception $e) {
        adminPageBanner('Gagal Menambah Produk');
        echo '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

// -------------------------------------------------------
// EDIT — Form edit produk
// -------------------------------------------------------
} elseif ($do === 'Edit') {

    $itemid = (isset($_GET['itemid']) && is_numeric($_GET['itemid'])) ? (int)$_GET['itemid'] : 0;

    try {
        $stmt = $con->prepare("SELECT * FROM items WHERE Item_ID = ? LIMIT 1");
        $stmt->execute([$itemid]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $item = null;
    }

    if (!$item) {
        echo '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Produk tidak ditemukan. <a href="items.php">Kembali</a></div>';
    } else {
        adminPageBanner('Edit Produk: ' . htmlspecialchars($item['Name']));

        try {
            $allCats    = $con->query("SELECT ID, Name FROM categories WHERE parent = 0 ORDER BY Name ASC")->fetchAll();
            $allMembers = $con->query("SELECT UserID, Username FROM users ORDER BY Username ASC")->fetchAll();
        } catch (Exception $e) {
            $allCats = $allMembers = [];
        }

        echo '<div style="max-width:760px;">';
        echo '<div style="background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;">';
        echo '<form action="items.php?do=Update" method="POST" enctype="multipart/form-data">';
        echo '<input type="hidden" name="itemid" value="' . $itemid . '">';

        echo '<div class="row">';
        echo '<div class="col-md-6"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">NAMA PRODUK *</label>
                <input type="text" name="name" class="form-control" required value="' . htmlspecialchars($item['Name']) . '">
              </div></div>';
        echo '<div class="col-md-3"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">HARGA *</label>
                <input type="number" name="price" class="form-control" required value="' . $item['Price'] . '">
              </div></div>';
        echo '<div class="col-md-3"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">STOK</label>
                <input type="number" name="stok" class="form-control" min="0" value="' . ($item['stok'] ?? 0) . '">
              </div></div>';
        echo '</div>';

        echo '<div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">DESKRIPSI *</label>
                <textarea name="description" class="form-control" rows="3" required>' . htmlspecialchars($item['Description']) . '</textarea>
              </div>';

        echo '<div class="row">';
        echo '<div class="col-md-4"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">KONTAK</label>
                <input type="text" name="contact" class="form-control" value="' . htmlspecialchars($item['contact'] ?? '') . '">
              </div></div>';
        echo '<div class="col-md-4"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">KATEGORI</label>
                <select name="category" class="form-control">';
        foreach ($allCats as $cat)
            echo '<option value="' . $cat['ID'] . '"' . ($item['Cat_ID'] == $cat['ID'] ? ' selected' : '') . '>' . htmlspecialchars($cat['Name']) . '</option>';
        echo '</select></div></div>';
        echo '<div class="col-md-4"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">PEMILIK</label>
                <select name="member" class="form-control">';
        foreach ($allMembers as $u)
            echo '<option value="' . $u['UserID'] . '"' . ($item['Member_ID'] == $u['UserID'] ? ' selected' : '') . '>' . htmlspecialchars($u['Username']) . '</option>';
        echo '</select></div></div>';
        echo '</div>';

        echo '<div class="row">';
        echo '<div class="col-md-4"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">STATUS</label>
                <select name="approve" class="form-control">
                  <option value="1"' . ($item['Approve'] == 1 ? ' selected' : '') . '>Aktif</option>
                  <option value="0"' . ($item['Approve'] == 0 ? ' selected' : '') . '>Pending</option>
                </select>
              </div></div>';
        echo '<div class="col-md-8"><div class="form-group">
                <label class="control-label" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#4A4A6A;">GANTI FOTO (opsional)</label>
                <input type="file" name="picture" class="form-control" accept="image/*">
                <small style="color:#9A9AB0;font-size:11px;">Kosongkan jika tidak ingin mengubah foto</small>
              </div></div>';
        echo '</div>';

        echo '<hr style="border-color:#EEF0F6;margin:8px 0 20px;">';
        echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
              &nbsp;
              <a href="items.php" style="color:#9A9AB0;font-size:14px;margin-left:8px;">Batal</a>';
        echo '</form>';
        echo '</div></div>';
    }

// -------------------------------------------------------
// UPDATE — Simpan perubahan produk
// -------------------------------------------------------
} elseif ($do === 'Update' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $id      = (int)($_POST['itemid']      ?? 0);
    $name    = trim($_POST['name']         ?? '');
    $desc    = trim($_POST['description']  ?? '');
    $price   = (float)($_POST['price']     ?? 0);
    $stok    = (int)($_POST['stok']        ?? 0);
    $cat     = (int)($_POST['category']    ?? 0);
    $member  = (int)($_POST['member']      ?? 0);
    $contact = trim($_POST['contact']      ?? '');
    $approve = (int)($_POST['approve']     ?? 1);
    $country = 'Indonesia';
    $status  = 1;

    // Ambil foto lama kalau tidak ada upload baru
    $stmtOld = $con->prepare("SELECT picture FROM items WHERE Item_ID = ? LIMIT 1");
    $stmtOld->execute([$id]);
    $oldPic = $stmtOld->fetchColumn() ?: 'default.png';

    $pictureName = $oldPic;
    if (!empty($_FILES['picture']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $_FILES['picture']['size'] <= 2097152) {
            $pictureName = uniqid('item_') . '.' . $ext;
            $uploadDir   = 'uploads/items/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['picture']['tmp_name'], $uploadDir . $pictureName);
        }
    }

    try {
        $con->prepare("
            UPDATE items
            SET Name=?, Description=?, Price=?, Country_Made=?, Status=?,
                Cat_ID=?, Member_ID=?, contact=?, stok=?, Approve=?, picture=?
            WHERE Item_ID=?
        ")->execute([$name, $desc, $price, $country, $status, $cat, $member, $contact, $stok, $approve, $pictureName, $id]);

        adminPageBanner('Produk Diperbarui');
        echo '<div class="alert alert-success"><i class="fa fa-check-circle"></i> Produk berhasil diperbarui. <a href="items.php">Kembali ke daftar &rarr;</a></div>';
    } catch (Exception $e) {
        adminPageBanner('Gagal Memperbarui Produk');
        echo '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

// -------------------------------------------------------
// DELETE
// -------------------------------------------------------
} elseif ($do === 'Delete') {

    $itemid = (isset($_GET['itemid']) && is_numeric($_GET['itemid'])) ? (int)$_GET['itemid'] : 0;
    if ($itemid > 0) {
        try {
            $con->prepare("DELETE FROM items WHERE Item_ID = ?")->execute([$itemid]);
        } catch (Exception $e) { /* silent */ }
    }
    header('Location: items.php');
    exit();

// -------------------------------------------------------
// APPROVE
// -------------------------------------------------------
} elseif ($do === 'Approve') {

    $itemid = (isset($_GET['itemid']) && is_numeric($_GET['itemid'])) ? (int)$_GET['itemid'] : 0;
    if ($itemid > 0) {
        try {
            $con->prepare("UPDATE items SET Approve = 1 WHERE Item_ID = ?")->execute([$itemid]);
        } catch (Exception $e) { /* silent */ }
    }
    header('Location: items.php');
    exit();
}

include $tpl . 'footer.php';
ob_end_flush();
?>