<?php
require(__DIR__ . '/bootstrap.php');
require(__DIR__ . '/../includes/application_top.php');

// Verifies seeded picks exist for each game and computes correctness by winner.
// Usage:
// php tests/picksWinnerTest.php --user=bob

$args = array();
if (PHP_SAPI === 'cli') {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$userName = isset($args['user']) ? $args['user'] : (getenv('PICKS_USER') ?: 'bob');

$seedUsers = array('bob', 'sal', 'howie');
$userIndex = array_search($userName, $seedUsers, true);
if ($userIndex === false) {
	fwrite(STDERR, "User must be one of: " . implode(', ', $seedUsers) . "\n");
	exit(1);
}

$userId = null;
$query = $mysqli->query("select userID from " . DB_PREFIX . "users where userName = '" . $mysqli->real_escape_string($userName) . "'");
if ($query && $query->num_rows > 0) {
	$row = $query->fetch_assoc();
	$userId = (int)$row['userID'];
}
if ($query) {
	$query->free();
}
if (!$userId) {
	fwrite(STDERR, "User not found: " . $userName . "\n");
	exit(1);
}

$sql = "select gameID, weekNum, homeID, visitorID, homeScore, visitorScore from " . DB_PREFIX . "schedule order by weekNum, gameID";
$query = $mysqli->query($sql);
if (!$query) {
	fwrite(STDERR, "Error loading schedule: " . $mysqli->error . "\n");
	exit(1);
}

$missing = 0;
$total = 0;
$wins = 0;
$losses = 0;
$noScore = 0;

while ($game = $query->fetch_assoc()) {
	$total++;
	$expectedPick = (((int)$game['gameID'] + $userIndex) % 2 === 0) ? $game['homeID'] : $game['visitorID'];
	$pickQuery = $mysqli->query("select pickID from " . DB_PREFIX . "picks where userID = " . $userId . " and gameID = " . (int)$game['gameID']);
	$hasPick = ($pickQuery && $pickQuery->num_rows > 0);
	if ($pickQuery) {
		$pickQuery->free();
	}
	if (!$hasPick) {
		$missing++;
		continue;
	}

	if ((int)$game['homeScore'] > 0 || (int)$game['visitorScore'] > 0) {
		$winner = null;
		if ((int)$game['homeScore'] > (int)$game['visitorScore']) {
			$winner = $game['homeID'];
		} elseif ((int)$game['visitorScore'] > (int)$game['homeScore']) {
			$winner = $game['visitorID'];
		}
		if ($winner !== null) {
			if ($expectedPick === $winner) {
				$wins++;
			} else {
				$losses++;
			}
		} else {
			$noScore++;
		}
	} else {
		$noScore++;
	}
}
if ($query) {
	$query->free();
}

echo "Picks winner test for " . $userName . ":\n";
echo "- Total games: " . $total . "\n";
echo "- Missing picks: " . $missing . "\n";
echo "- With scores: " . ($wins + $losses) . "\n";
echo "- Wins: " . $wins . "\n";
echo "- Losses: " . $losses . "\n";
echo "- No score: " . $noScore . "\n";

if ($missing > 0) {
	fwrite(STDERR, "Missing picks detected.\n");
	exit(1);
}

echo "Picks winner test passed.\n";
