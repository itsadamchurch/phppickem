<?php
require('includes/application_top.php');

if (class_exists('App\\Auth\\Auth')) {
	$auth = new \App\Auth\Auth($login);
	$auth->logout();
} else {
	unset($_SESSION['logged']);
	unset($_SESSION['loggedInUser']);
}

header('Location: login.php');
exit;
