<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
include 'init.php';

if ($_SERVER['REQUEST_METHOD']=='POST') {
    $order_id = intval($_POST['order_id']);
    $chk = $con->prepare("SELECT order_id FROM orders WHERE order_id=? AND user_id=?");
    $chk->execute([$order_id, $_SESSION['uid']]);
    if ($chk->rowCount() > 0 && !empty($_FILES['bukti']['name'])) {
        $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','pdf'])) {
            $fname = 'bukti_'.$order_id.'_'.rand(100,9999).'.'.$ext;
            move_uploaded_file($_FILES['bukti']['tmp_name'], 'admin/uploads/items/'.$fname);
            $con->prepare("UPDATE orders SET bukti_bayar=?, status='Menunggu Konfirmasi' WHERE order_id=?")->execute([$fname, $order_id]);
        }
    }
}
header('Location: orders.php'); exit();