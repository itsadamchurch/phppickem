<?php
//modify vars below
// Database
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
	$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || strpos($line, '#') === 0) {
			continue;
		}
		$parts = explode('=', $line, 2);
		if (count($parts) !== 2) {
			continue;
		}
		$key = trim($parts[0]);
		$value = trim($parts[1]);
		$quote = substr($value, 0, 1);
		if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
			$value = substr($value, 1, -1);
		}
		if ($key !== '' && getenv($key) === false) {
			putenv($key . '=' . $value);
			$_ENV[$key] = $value;
		}
	}
}

define('DB_HOSTNAME', getenv('DB_HOSTNAME') !== false ? getenv('DB_HOSTNAME') : 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : 'dbuser');
define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'dbpass');
define('DB_DATABASE', getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : 'nflpickem');
define('DB_PREFIX', getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'nflp_');
$dbPort = getenv('DB_PORT');
define('DB_PORT', ($dbPort !== false && $dbPort !== '') ? (int)$dbPort : 3306);

define('SITE_URL', getenv('SITE_URL') !== false ? getenv('SITE_URL') : 'http://localhost/personal/applications/phppickem/');
define('ALLOW_SIGNUP', true);
define('SHOW_SIGNUP_LINK', true);
define('USER_NAMES_DISPLAY', 3); // 1 = real names, 2 = usernames, 3 = usernames w/ real names on hover
define('COMMENTS_SYSTEM', 'basic'); // basic, disqus, or disabled
define('DISQUS_SHORTNAME', ''); // only needed if using Disqus for comments

define('SEASON_YEAR', '2025');
define('SERVER_TIMEZONE', 'America/Chicago'); // Your SERVER's timezone. NOTE: Game times will always be displayed in Eastern time, as they are on NFL.com. This setting makes sure cutoff times work properly.

// ***DO NOT EDIT ANYTHING BELOW THIS LINE***
error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT ^ E_DEPRECATED);

//automatically set timezone offset (hours difference between your server's timezone and eastern time)
date_default_timezone_set(SERVER_TIMEZONE);
/*$timeZoneCurrent = @date_default_timezone_get();
if (ini_get('date.timezone')) {
	$timeZoneCurrent = ini_get('date.timezone');
}*/
$dateTimeZoneCurrent = new DateTimeZone(SERVER_TIMEZONE);
$dateTimeZoneEastern = new DateTimeZone("America/New_York");
$dateTimeCurrent = new DateTime("now", $dateTimeZoneCurrent);
$dateTimeEastern = new DateTime("now", $dateTimeZoneEastern);
$offsetCurrent = $dateTimeCurrent->getOffset();
$offsetEastern = $dateTimeEastern->getOffset();
$offsetHours = ($offsetEastern - $offsetCurrent) / 3600;
define('SERVER_TIMEZONE_OFFSET', $offsetHours);
