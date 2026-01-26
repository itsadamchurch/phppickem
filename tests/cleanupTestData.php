<?php
require(__DIR__ . '/bootstrap.php');
require(__DIR__ . '/../includes/application_top.php');

// Usage (CLI):
// php tests/cleanupTestData.php --apply=1
// Browser:
// tests/cleanupTestData.php?apply=1

$args = array();
if (PHP_SAPI === 'cli') {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$apply = isset($args['apply']) ? ($args['apply'] === '1') : (isset($_GET['apply']) && $_GET['apply'] === '1');

$usernames = array('bob', 'sal', 'howie');
$errors = array();
$deletedUsers = 0;
$deletedPicks = 0;
$deletedPickSummary = 0;
$deletedPlayoffPicks = 0;

foreach ($usernames as $username) {
	$sql = "select userID from " . DB_PREFIX . "users where userName = '" . $mysqli->real_escape_string($username) . "'";
	$query = $mysqli->query($sql);
	if ($query && $query->num_rows > 0) {
		$row = $query->fetch_assoc();
		$userId = (int)$row['userID'];
		if ($apply) {
			if ($mysqli->query("delete from " . DB_PREFIX . "picks where userID = " . $userId)) {
				$deletedPicks += $mysqli->affected_rows;
			} else {
				$errors[] = 'Error deleting picks for userID ' . $userId . ': ' . $mysqli->error;
			}
			if ($mysqli->query("delete from " . DB_PREFIX . "picksummary where userID = " . $userId)) {
				$deletedPickSummary += $mysqli->affected_rows;
			} else {
				$errors[] = 'Error deleting picksummary for userID ' . $userId . ': ' . $mysqli->error;
			}
			if ($mysqli->query("delete from " . DB_PREFIX . "playoff_picks where userID = " . $userId)) {
				$deletedPlayoffPicks += $mysqli->affected_rows;
			} else {
				$errors[] = 'Error deleting playoff picks for userID ' . $userId . ': ' . $mysqli->error;
			}
			if ($mysqli->query("delete from " . DB_PREFIX . "users where userID = " . $userId)) {
				$deletedUsers += $mysqli->affected_rows;
			} else {
				$errors[] = 'Error deleting userID ' . $userId . ': ' . $mysqli->error;
			}
		}
	}
	if ($query) {
		$query->free;
	}
}

if (!$apply) {
	echo "Dry run complete. Use apply=1 to delete test data." . PHP_EOL;
}
if (count($errors) > 0) {
	echo "Errors:" . PHP_EOL;
	foreach ($errors as $error) {
		echo "- " . $error . PHP_EOL;
	}
}
echo "Cleanup complete. Users deleted: " . $deletedUsers . ", Picks deleted: " . $deletedPicks . ", Pick summaries deleted: " . $deletedPickSummary . ", Playoff picks deleted: " . $deletedPlayoffPicks . PHP_EOL;
