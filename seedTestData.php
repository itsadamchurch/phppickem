<?php
require('includes/application_top.php');

// Usage (CLI):
// php seedTestData.php --apply=1
// Optional: --playoffs=1 to seed playoff picks too
// Browser:
// seedTestData.php?apply=1

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
$seedPlayoffs = isset($args['playoffs']) ? ($args['playoffs'] === '1') : (isset($_GET['playoffs']) && $_GET['playoffs'] === '1');

$users = array(
	array('username' => 'bob', 'first' => 'Bob', 'last' => 'Loblaw', 'email' => 'bob@example.com'),
	array('username' => 'sal', 'first' => 'Sal', 'last' => 'Goodman', 'email' => 'sal@example.com'),
	array('username' => 'howie', 'first' => 'Howie', 'last' => 'Dewitt', 'email' => 'howie@example.com')
);

$plainPassword = 'test1234';
$userIds = array();
$totalPicks = 0;
$totalPickSummary = 0;
$totalPlayoffPicks = 0;

foreach ($users as $idx => $u) {
	$existingId = null;
	$sql = "select userID from " . DB_PREFIX . "users where userName = '" . $mysqli->real_escape_string($u['username']) . "'";
	$query = $mysqli->query($sql);
	if ($query && $query->num_rows > 0) {
		$row = $query->fetch_assoc();
		$existingId = (int)$row['userID'];
	}
	if ($query) {
		$query->free;
	}

	if ($apply && $existingId) {
		$mysqli->query("delete from " . DB_PREFIX . "picks where userID = " . $existingId);
		$mysqli->query("delete from " . DB_PREFIX . "picksummary where userID = " . $existingId);
		$mysqli->query("delete from " . DB_PREFIX . "playoff_picks where userID = " . $existingId);
		$mysqli->query("delete from " . DB_PREFIX . "users where userID = " . $existingId);
	}

	$hash = password_hash($plainPassword, PASSWORD_DEFAULT);
	if ($apply) {
		$sql = "insert into " . DB_PREFIX . "users (userName, password, salt, firstname, lastname, email, status, is_admin) values (" .
			"'" . $mysqli->real_escape_string($u['username']) . "', " .
			"'" . $mysqli->real_escape_string($hash) . "', " .
			"'" . $mysqli->real_escape_string('') . "', " .
			"'" . $mysqli->real_escape_string($u['first']) . "', " .
			"'" . $mysqli->real_escape_string($u['last']) . "', " .
			"'" . $mysqli->real_escape_string($u['email']) . "', " .
			"1, 0)";
		$mysqli->query($sql) or die('Error inserting user: ' . $mysqli->error);
		$userIds[$u['username']] = (int)$mysqli->insert_id;
	} else {
		$userIds[$u['username']] = $existingId ? $existingId : 0;
	}
}

// schedule -> picks + picksummary
$schedule = array();
$sql = "select gameID, weekNum, homeID, visitorID from " . DB_PREFIX . "schedule order by weekNum, gameID";
$query = $mysqli->query($sql);
while ($row = $query->fetch_assoc()) {
	$schedule[] = $row;
}
$query->free;

$weeks = array();
foreach ($schedule as $game) {
	$weeks[(int)$game['weekNum']] = true;
}

foreach ($users as $uIndex => $u) {
	$userId = (int)$userIds[$u['username']];
	if ($apply && $userId > 0) {
		foreach ($weeks as $weekNum => $unused) {
			$sql = "insert into " . DB_PREFIX . "picksummary (weekNum, userID, showPicks) values (" . (int)$weekNum . ", " . $userId . ", 1)";
			$mysqli->query($sql);
			$totalPickSummary++;
		}
		foreach ($schedule as $game) {
			$pickTeam = (((int)$game['gameID'] + $uIndex) % 2 === 0) ? $game['homeID'] : $game['visitorID'];
			$sql = "insert into " . DB_PREFIX . "picks (userID, gameID, pickID) values (" . $userId . ", " . (int)$game['gameID'] . ", '" . $mysqli->real_escape_string($pickTeam) . "')";
			$mysqli->query($sql);
			$totalPicks++;
		}
	} else {
		$totalPickSummary += count($weeks);
		$totalPicks += count($schedule);
	}
}

if ($seedPlayoffs) {
	$playoffGames = array();
	$sql = "select playoffGameID, roundNum, homeID, visitorID from " . DB_PREFIX . "playoff_schedule where is_bye = 0 order by roundNum, playoffGameID";
	$query = $mysqli->query($sql);
	while ($row = $query->fetch_assoc()) {
		$playoffGames[] = $row;
	}
	$query->free;
	foreach ($users as $uIndex => $u) {
		$userId = (int)$userIds[$u['username']];
		if ($apply && $userId > 0) {
			foreach ($playoffGames as $game) {
				$pickTeam = (((int)$game['playoffGameID'] + $uIndex) % 2 === 0) ? $game['homeID'] : $game['visitorID'];
				$sql = "insert into " . DB_PREFIX . "playoff_picks (userID, gameID, pickTeamID) values (" . $userId . ", " . (int)$game['playoffGameID'] . ", '" . $mysqli->real_escape_string($pickTeam) . "')";
				$mysqli->query($sql);
				$totalPlayoffPicks++;
			}
		} else {
			$totalPlayoffPicks += count($playoffGames);
		}
	}
}

if (!$apply) {
	echo "Dry run complete. Use apply=1 to insert test data." . PHP_EOL;
}
echo "Seed complete. Users: " . count($users) . ", Picks: " . $totalPicks . ", Pick summaries: " . $totalPickSummary;
if ($seedPlayoffs) {
	echo ", Playoff picks: " . $totalPlayoffPicks;
}
echo PHP_EOL;
echo "Login password for test users: " . $plainPassword . PHP_EOL;
