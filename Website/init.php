<?php

	// Error Reporting

	ini_set('display_errors', 'On');
	error_reporting(E_ALL);

	include 'admin/connect.php';

	$sessionUser = '';
	$sessionAvatar = '';
	
	if (isset($_SESSION['user'])) {
		$sessionUser = $_SESSION['user'];
		$sessionAvatar = $_SESSION['avatar'];

		// Segarkan role (GroupID) & avatar dari database tiap load, supaya
		// perubahan role (mis. penjual lama di-set GroupID=2) langsung berlaku
		// tanpa perlu login ulang.
		if (isset($_SESSION['uid'])) {
			try {
				$__g = $con->prepare("SELECT GroupID, avatar FROM users WHERE UserID = ?");
				$__g->execute([$_SESSION['uid']]);
				if ($__row = $__g->fetch()) {
					$_SESSION['GroupID'] = (int)$__row['GroupID'];
					$sessionAvatar = $__row['avatar'];
				}
			} catch (Exception $e) { /* abaikan */ }
		}
	}

	// Routes

	$tpl 	= 'includes/templates/'; // Template Directory
	$lang 	= 'includes/languages/'; // Language Directory
	$func	= 'includes/functions/'; // Functions Directory
	$css 	= 'layout/css/'; // Css Directory
	$js 	= 'layout/js/'; // Js Directory

	// Include The Important Files

	include $func . 'functions.php';
	include $lang . 'english.php';
	include $tpl . 'header.php';
	

	