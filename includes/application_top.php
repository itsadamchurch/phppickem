<?php
// application_top.php -- included first on all pages
require('includes/config.php');
require('includes/functions.php');
require('includes/classes/class.phpmailer.php');
require('includes/htmlpurifier/HTMLPurifier.auto.php');

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (is_readable($autoloadPath)) {
	require_once $autoloadPath;
}

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); //turns off caching

//filter for cross-site scripting attacks
$purifier = new HTMLPurifier($purifier_config);
foreach($_POST as $key=>$value) {
	if (!is_array($_POST[$key])) {
		$_POST[$key] = $purifier->purify($value);
	}
}

foreach($_GET as $key=>$value){
	$_GET[$key] = $purifier->purify($value);
}

$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT) or die('error connecting to db');
$mysqli->set_charset('utf8');
if ($mysqli) {
	//check for presence of install folder
	if (is_dir('install')) {
		//do a query to see if db installed
		//$testQueryOK = false;
		$sql = "select * from  " . DB_PREFIX . "teams";
		//die($sql);
		if ($query = $mysqli->query($sql)) {
			//query is ok, display warning
			$warnings[] = 'For security, please delete or rename the install folder.';
		} else {
			//tables not not present, redirect to installer
			header('location: ./install/');
			exit;
		}
		$query->free();
	}
} else {
	die('Database not connected.  Please check your config file for proper installation.');
}

if (!class_exists('App\\Auth\\Login') && is_readable(__DIR__ . '/../src/Auth/Login.php')) {
	require_once __DIR__ . '/../src/Auth/Login.php';
}
if (!class_exists('App\\Auth\\UserService') && is_readable(__DIR__ . '/../src/Auth/UserService.php')) {
	require_once __DIR__ . '/../src/Auth/UserService.php';
}

if (class_exists('App\\Auth\\Login')) {
	$login = new \App\Auth\Login();
} else {
	require('includes/classes/login.php');
	$login = new Login;
}
$auth = class_exists('App\\Auth\\Auth') ? new \App\Auth\Auth($login) : null;
$adminUser = $auth ? $auth->getAdminUser() : $login->get_user('admin');

$okFiles = array('login.php', 'signup.php', 'password_reset.php', 'buildSchedule.php', 'logout.php');
if ($auth) {
	$user = $auth->enforceLogin($okFiles);
} else if (!in_array(basename($_SERVER['PHP_SELF']), $okFiles)) {
	if (empty($_SESSION['logged']) || $_SESSION['logged'] !== 'yes') {
		header( 'Location: login.php' );
		exit;
	} else if (!empty($_SESSION['loggedInUser'])) {
		$user = $login->get_user($_SESSION['loggedInUser']);
	}
}

if ($_SESSION['loggedInUser'] === 'admin' && $_SESSION['logged'] === 'yes') {
	//$isAdmin = 1;
} else {
	//$isAdmin = 0;
	//get current week
	$currentWeek = getCurrentWeek();

	$cutoffDateTime = getCutoffDateTime($currentWeek);
	$firstGameTime = getFirstGameTime($currentWeek);

	$firstGameExpired = ((date("U", time()+(SERVER_TIMEZONE_OFFSET * 3600)) > strtotime($firstGameTime)) ? true : false);
	$weekExpired = ((date("U", time()+(SERVER_TIMEZONE_OFFSET * 3600)) > strtotime($cutoffDateTime)) ? true : false);
}
