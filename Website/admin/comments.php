<?php
ob_start(); session_start();
$pageTitle = 'Feedbacks';
if (!isset($_SESSION['Username'])) { header('Location: index.php'); exit(); }
include 'init.php';
$do = isset($_GET['do']) ? $_GET['do'] : 'Manage';

if ($do == 'Manage') {
    $filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
    $whereStr = '';
    if ($filterStatus === 'pending') $whereStr = 'WHERE comments.status=0';
    elseif ($filterStatus === 'approved') $whereStr = 'WHERE comments.status=1';

    $stmt = $con->prepare("SELECT comments.*, items.Name AS Item_Name, users.Username AS Member FROM comments INNER JOIN items ON items.Item_ID=comments.item_id INNER JOIN users ON users.UserID=comments.user_id $whereStr ORDER BY c_id DESC");
    $stmt->execute(); $comments=$stmt->fetchAll();

    $pendingC = $con->prepare("SELECT COUNT(*) FROM comments WHERE status=0"); $pendingC->execute(); $pc=$pendingC->fetchColumn();

    echo '<div style="background:linear-gradient(135deg,#1A5C2A,#27AE60);color:#fff;padding:20px 0;margin-bottom:24px;"><div class="container">
    <div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;">Admin Panel</div>
    <div style="font-family:\'Playfair Display\',serif;font-size:22px;font-weight:700;">Kelola Ulasan</div>
    </div></div>';

    echo '<div class="container" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">';
    foreach ([''=> 'Semua','pending'=>'Pending ('.$pc.')','approved'=>'Disetujui'] as $f=>$l) {
        $active=$filterStatus===$f;
        echo '<a href="comments.php'.($f?'?status='.$f:'').'" style="font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;background:'.($active?'#1A5C2A':'#E8ECF5').';color:'.($active?'#fff':'#1B2E5E').';">'.$l.'</a>';
    }
    echo '</div>';

    if (!empty($comments)) {
        echo '<div class="container" style="padding-bottom:40px;">';
        echo '<div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(27,46,94,.08);border:1px solid #DDE1EC;overflow:hidden;">';
        foreach ($comments as $i=>$comment) {
            $bg = $i%2==0?'#fff':'#F7F8FA';
            echo '<div style="padding:14px 18px;border-bottom:1px solid #DDE1EC;background:'.$bg.';display:flex;gap:12px;align-items:flex-start;">';
            echo '<div style="width:36px;height:36px;border-radius:50%;background:#E8ECF5;display:flex;align-items:center;justify-content:center;font-weight:700;color:#1B2E5E;font-size:13px;flex-shrink:0;">'.strtoupper(substr($comment['Member'],0,1)).'</div>';
            echo '<div style="flex:1;">';
            echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap;">';
            echo '<span style="font-size:13px;font-weight:600;color:#1B2E5E;">'.htmlspecialchars($comment['Member']).'</span>';
            if (isset($comment['rating'])) echo '<span style="color:#F4A261;font-size:12px;">'.str_repeat('★',$comment['rating']).str_repeat('☆',5-$comment['rating']).'</span>';
            echo '<span style="font-size:11px;color:#9A9AB0;">di <em>'.htmlspecialchars($comment['Item_Name']).'</em></span>';
            echo '<span style="font-size:11px;color:#9A9AB0;">'.$comment['comment_date'].'</span>';
            if ($comment['status']==0) echo '<span style="background:#FEF3C7;color:#92400E;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">Pending</span>';
            else echo '<span style="background:#EAF5ED;color:#1A5C2A;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">Disetujui</span>';
            echo '</div>';
            echo '<div style="font-size:13px;color:#4A4A6A;">'.htmlspecialchars($comment['comment']).'</div>';
            echo '</div>';
            echo '<div style="display:flex;gap:6px;flex-shrink:0;">';
            if ($comment['status']==0) echo '<a href="comments.php?do=Approve&comid='.$comment['c_id'].'" style="background:#EAF5ED;color:#1A5C2A;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-check"></i> Approve</a>';
            echo '<a href="comments.php?do=Delete&comid='.$comment['c_id'].'" onclick="return confirm(\'Hapus ulasan ini?\')" style="background:#FDECEA;color:#9B1C1C;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;"><i class="fa fa-trash"></i></a>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div></div>';
    } else {
        echo '<div class="container"><div class="nice-message">Belum ada ulasan.</div></div>';
    }

} elseif ($do == 'Delete') {
    $comid=isset($_GET['comid'])&&is_numeric($_GET['comid'])?intval($_GET['comid']):0;
    if (checkItem('c_id','comments',$comid)) $con->prepare("DELETE FROM comments WHERE c_id=?")->execute([$comid]);
    header('Location: comments.php'); exit();

} elseif ($do == 'Approve') {
    $comid=isset($_GET['comid'])&&is_numeric($_GET['comid'])?intval($_GET['comid']):0;
    $con->prepare("UPDATE comments SET status=1 WHERE c_id=?")->execute([$comid]);
    header('Location: comments.php'); exit();
}

include $tpl.'footer.php';
ob_end_flush();
?>