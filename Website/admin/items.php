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

// =======================================================
// CATATAN PENTING (batasan hak admin):
// Admin HANYA boleh melakukan MODERASI atas produk penjual:
//   - Approve (aktifkan)   - Nonaktifkan (unapprove)   - Hapus
// Admin TIDAK bisa lagi menambah/mengedit isi & atribut produk
// (nama, harga, deskripsi, kategori, CBF rasa/pedas/bahan, pemilik).
// Aksi Add/Edit/Update sengaja dihapus. Kalau diakses via URL,
// dialihkan kembali ke daftar dengan pesan.
// =======================================================

// -------------------------------------------------------
// APPROVE — aktifkan produk (moderasi)
// -------------------------------------------------------
if ($do === 'Approve') {
    $itemid = (isset($_GET['itemid']) && is_numeric($_GET['itemid'])) ? (int)$_GET['itemid'] : 0;
    if ($itemid > 0) {
        try { $con->prepare("UPDATE items SET Approve = 1 WHERE Item_ID = ?")->execute([$itemid]); }
        catch (Exception $e) { /* silent */ }
    }
    header('Location: items.php'); exit();

// -------------------------------------------------------
// UNAPPROVE — nonaktifkan produk (moderasi, tanpa menghapus)
// -------------------------------------------------------
} elseif ($do === 'Unapprove') {
    $itemid = (isset($_GET['itemid']) && is_numeric($_GET['itemid'])) ? (int)$_GET['itemid'] : 0;
    if ($itemid > 0) {
        try { $con->prepare("UPDATE items SET Approve = 0 WHERE Item_ID = ?")->execute([$itemid]); }
        catch (Exception $e) { /* silent */ }
    }
    header('Location: items.php'); exit();

// -------------------------------------------------------
// DELETE — hapus produk (moderasi)
// -------------------------------------------------------
} elseif ($do === 'Delete') {
    $itemid = (isset($_GET['itemid']) && is_numeric($_GET['itemid'])) ? (int)$_GET['itemid'] : 0;
    if ($itemid > 0) {
        try { $con->prepare("DELETE FROM items WHERE Item_ID = ?")->execute([$itemid]); }
        catch (Exception $e) { /* silent */ }
    }
    header('Location: items.php'); exit();

// -------------------------------------------------------
// Blokir aksi lama yang mengubah isi produk (Add/Insert/Edit/Update)
// -------------------------------------------------------
} elseif (in_array($do, ['Add', 'Insert', 'Edit', 'Update'], true)) {
    header('Location: items.php?blocked=1'); exit();

// -------------------------------------------------------
// MANAGE — Daftar semua produk (moderasi)
// -------------------------------------------------------
} else {

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
            LEFT JOIN categories ON categories.ID = items.Cat_ID
            LEFT JOIN users      ON users.UserID  = items.Member_ID
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

    adminPageBanner('Moderasi Produk', count($items) . ' produk — admin hanya bisa approve, nonaktifkan, atau hapus');

    if (isset($_GET['blocked'])) {
        echo '<div class="alert alert-danger" style="margin-bottom:14px;"><i class="fa fa-ban"></i> Admin tidak dapat menambah/mengedit isi produk penjual. Hanya moderasi (approve/nonaktifkan/hapus) yang diizinkan.</div>';
    }

    // ---- Toolbar (filter) — TANPA tombol tambah ----
    echo '<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">';
    $filters = ['' => 'Semua', 'pending' => 'Pending (' . $pc . ')', 'active' => 'Aktif'];
    foreach ($filters as $f => $label) {
        $active = ($filterApprove === $f);
        echo '<a href="items.php' . ($f ? '?filter=' . $f : '') . '"
                 style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;
                        background:' . ($active ? '#B5272A' : '#E8ECF5') . ';
                        color:' . ($active ? '#fff' : '#1B2E5E') . ';text-decoration:none;">' . $label . '</a>';
    }
    echo '</div>';

    if ($queryError) {
        echo '<div style="background:#FDECEA;color:#9B1C1C;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;">
                <i class="fa fa-exclamation-triangle"></i> Gagal memuat data: ' . htmlspecialchars($queryError) . '</div>';
    }

    if (!empty($items)) {
        echo '<div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">';
        echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        echo '<thead><tr style="background:#B5272A;color:#fff;">';
        foreach (['Foto', 'Nama Produk', 'Harga', 'Ketersediaan', 'Kategori', 'Penjual', 'Tanggal', 'Status', 'Aksi'] as $h) {
            echo '<th style="padding:11px 14px;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;text-align:left;white-space:nowrap;">' . $h . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($items as $i => $item) {
            $bg  = ($i % 2 === 0) ? '#fff' : '#F7F8FA';
            $pic = (!empty($item['picture']) && $item['picture'] !== 'default.png')
                        ? 'uploads/items/' . htmlspecialchars($item['picture'])
                        : 'uploads/items/default.png';
            $tersedia = ((int)($item['stok'] ?? 0)) > 0;

            echo '<tr style="background:' . $bg . ';border-bottom:1px solid #EEF0F6;">';
            echo '<td style="padding:8px 14px;"><img src="' . $pic . '" style="width:44px;height:44px;object-fit:cover;border-radius:8px;background:#EEF0F6;border:1px solid #DDE1EC;" onerror="this.src=\'uploads/items/default.png\'"></td>';
            echo '<td style="padding:8px 14px;font-weight:600;color:#1B2E5E;max-width:180px;"><span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px;" title="' . htmlspecialchars($item['Name']) . '">' . htmlspecialchars($item['Name']) . '</span></td>';
            echo '<td style="padding:8px 14px;font-weight:600;color:#B5272A;white-space:nowrap;">Rp ' . number_format($item['Price'], 0, ',', '.') . '</td>';
            echo '<td style="padding:8px 14px;">' . ($tersedia
                    ? '<span style="background:#EAF5ED;color:#1A5C2A;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;">Tersedia</span>'
                    : '<span style="background:#FDECEA;color:#9B1C1C;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;">Habis</span>') . '</td>';
            echo '<td style="padding:8px 14px;color:#4A4A6A;">' . htmlspecialchars($item['category_name'] ?? '-') . '</td>';
            echo '<td style="padding:8px 14px;color:#4A4A6A;">' . htmlspecialchars($item['Username'] ?? '-') . '</td>';
            echo '<td style="padding:8px 14px;color:#9A9AB0;font-size:11px;white-space:nowrap;">' . htmlspecialchars($item['Add_Date'] ?? '') . '</td>';
            echo '<td style="padding:8px 14px;">';
            echo ($item['Approve'] == 0)
                ? '<span style="background:#FEF3C7;color:#92400E;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;">Pending</span>'
                : '<span style="background:#EAF5ED;color:#1A5C2A;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;">Aktif</span>';
            echo '</td>';

            // ---- Aksi: HANYA moderasi ----
            echo '<td style="padding:8px 14px;white-space:nowrap;">';
            if ($item['Approve'] == 0) {
                echo '<a href="items.php?do=Approve&itemid=' . $item['Item_ID'] . '" style="background:#EAF5ED;color:#1A5C2A;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;margin-right:3px;text-decoration:none;display:inline-block;"><i class="fa fa-check"></i> Approve</a>';
            } else {
                echo '<a href="items.php?do=Unapprove&itemid=' . $item['Item_ID'] . '" onclick="return confirm(\'Nonaktifkan produk ini? Produk akan disembunyikan dari toko.\')" style="background:#FEF3C7;color:#92400E;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;margin-right:3px;text-decoration:none;display:inline-block;"><i class="fa fa-eye-slash"></i> Nonaktifkan</a>';
            }
            echo '<a href="items.php?do=Delete&itemid=' . $item['Item_ID'] . '" onclick="return confirm(\'Hapus produk ini? Tindakan tidak dapat dibatalkan.\')" style="background:#FDECEA;color:#9B1C1C;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;display:inline-block;"><i class="fa fa-trash"></i></a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div style="background:#fff;border-radius:14px;padding:60px 24px;text-align:center;border:1px solid #DDE1EC;">
                <i class="fa fa-tag" style="font-size:40px;color:#DDE1EC;margin-bottom:14px;display:block;"></i>
                <div style="font-size:16px;font-weight:600;color:#4A4A6A;margin-bottom:6px;">Belum ada produk</div>
              </div>';
    }
}

include $tpl . 'footer.php';
ob_end_flush();
?>
