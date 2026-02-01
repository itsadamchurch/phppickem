<?php
require(__DIR__ . '/bootstrap.php');
require(__DIR__ . '/../includes/application_top.php');

// 2025 season pick flow test (18 regular season weeks).
// Usage:
// php tests/season2025PickFlowTest.php --apply=1 --weeks=18

$args = array();
if (PHP_SAPI === 'cli') {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$apply = isset($args['apply']) ? ($args['apply'] === '1') : true;
$weeks = isset($args['weeks']) ? (int)$args['weeks'] : 18;
$verbose = true;
$seed = isset($args['seed']) ? (int)$args['seed'] : 2025;
$useColor = function_exists('posix_isatty') ? @posix_isatty(STDOUT) : true;
$green = $useColor ? "\033[32m" : '';
$red = $useColor ? "\033[31m" : '';
$yellow = $useColor ? "\033[33m" : '';
$reset = $useColor ? "\033[0m" : '';

if ($weeks <= 0 || $weeks > 18) {
	fwrite(STDERR, "Weeks must be between 1 and 18.\n");
	exit(1);
}

$users = array(
	array('username' => 'bob', 'first' => 'Bob', 'last' => 'Loblaw', 'email' => 'bob@example.com'),
	array('username' => 'sal', 'first' => 'Sal', 'last' => 'Goodman', 'email' => 'sal@example.com'),
	array('username' => 'howie', 'first' => 'Howie', 'last' => 'Dewitt', 'email' => 'howie@example.com')
);
$plainPassword = 'test1234';

$errors = array();
$warnings = array();
$userIds = array();

function setTestNowEastern($value) {
	putenv('TEST_NOW_EASTERN=' . $value);
	$_ENV['TEST_NOW_EASTERN'] = $value;
}

function ensureUser($user, $plainPassword, &$userIds, &$errors, $apply) {
	global $mysqli;
	$username = $user['username'];
	$query = $mysqli->query("select userID from " . DB_PREFIX . "users where userName = '" . $mysqli->real_escape_string($username) . "'");
	$userId = null;
	if ($query && $query->num_rows > 0) {
		$row = $query->fetch_assoc();
		$userId = (int)$row['userID'];
	}
	if ($query) {
		$query->free();
	}
	if (!$userId) {
		if (!$apply) {
			$errors[] = 'User not found (dry run): ' . $username;
			return;
		}
		$hash = password_hash($plainPassword, PASSWORD_DEFAULT);
		$sql = "insert into " . DB_PREFIX . "users (userName, password, salt, firstname, lastname, email, status, is_admin) values (" .
			"'" . $mysqli->real_escape_string($username) . "', " .
			"'" . $mysqli->real_escape_string($hash) . "', " .
			"'" . $mysqli->real_escape_string('') . "', " .
			"'" . $mysqli->real_escape_string($user['first']) . "', " .
			"'" . $mysqli->real_escape_string($user['last']) . "', " .
			"'" . $mysqli->real_escape_string($user['email']) . "', " .
			"1, 0)";
		if (!$mysqli->query($sql)) {
			$errors[] = 'Error inserting user ' . $username . ': ' . $mysqli->error;
			return;
		}
		$userId = (int)$mysqli->insert_id;
	}
	$userIds[$username] = $userId;
}

function clearUserData($userId, &$errors, $apply) {
	global $mysqli;
	if (!$apply) {
		return;
	}
	if (!$mysqli->query("delete from " . DB_PREFIX . "picks where userID = " . (int)$userId)) {
		$errors[] = 'Error deleting picks for userID ' . $userId . ': ' . $mysqli->error;
	}
	if (!$mysqli->query("delete from " . DB_PREFIX . "picksummary where userID = " . (int)$userId)) {
		$errors[] = 'Error deleting picksummary for userID ' . $userId . ': ' . $mysqli->error;
	}
	if (!$mysqli->query("delete from " . DB_PREFIX . "playoff_picks where userID = " . (int)$userId)) {
		$errors[] = 'Error deleting playoff picks for userID ' . $userId . ': ' . $mysqli->error;
	}
}

function upsertPickSummary($userId, $week, $showPicks, &$errors, $apply) {
	global $mysqli;
	if (!$apply) {
		return;
	}
	$mysqli->query("delete from " . DB_PREFIX . "picksummary where weekNum = " . (int)$week . " and userID = " . (int)$userId);
	$sql = "insert into " . DB_PREFIX . "picksummary (weekNum, userID, showPicks) values (" . (int)$week . ", " . (int)$userId . ", " . (int)$showPicks . ")";
	if (!$mysqli->query($sql)) {
		$errors[] = 'Error inserting picksummary for userID ' . $userId . ', week ' . $week . ': ' . $mysqli->error;
	}
}

function submitWeekPicks($userId, $week, $picksByGameId, &$errors, $apply) {
	global $mysqli;
	$nowSql = phppickem_now_eastern_sql();
	$sql = "select gameID from " . DB_PREFIX . "schedule where weekNum = " . (int)$week . " and (" . $nowSql . " < gameTimeEastern)";
	$query = $mysqli->query($sql);
	if (!$query) {
		$errors[] = 'Error loading schedule for week ' . $week . ': ' . $mysqli->error;
		return;
	}
	while ($row = $query->fetch_assoc()) {
		$gameId = (int)$row['gameID'];
		if ($apply) {
			$mysqli->query("delete from " . DB_PREFIX . "picks where userID = " . (int)$userId . " and gameID = " . $gameId);
			if (isset($picksByGameId[$gameId]) && $picksByGameId[$gameId] !== '') {
				$pick = $mysqli->real_escape_string($picksByGameId[$gameId]);
				$sqlInsert = "insert into " . DB_PREFIX . "picks (userID, gameID, pickID) values (" . (int)$userId . ", " . $gameId . ", '" . $pick . "')";
				if (!$mysqli->query($sqlInsert)) {
					$errors[] = 'Error inserting pick for userID ' . $userId . ', gameID ' . $gameId . ': ' . $mysqli->error;
				}
			}
		}
	}
	$query->free();
}

function getUserPick($userId, $gameId) {
	global $mysqli;
	$query = $mysqli->query("select pickID from " . DB_PREFIX . "picks where userID = " . (int)$userId . " and gameID = " . (int)$gameId);
	$pick = null;
	if ($query && $query->num_rows > 0) {
		$row = $query->fetch_assoc();
		$pick = $row['pickID'];
	}
	if ($query) {
		$query->free();
	}
	return $pick;
}

function printPickResult($label, $ok, $serverTime, $gameTime, $details = '') {
	global $green, $red, $reset;
	$color = $ok ? $green : $red;
	echo $color . $label . $reset . " server=" . $serverTime . " ET, game=" . $gameTime . " ET";
	if ($details !== '') {
		echo " " . $details;
	}
	echo "\n";
}

function submitPlayoffPicks($userId, $round, $picksByGameId, &$errors, $apply) {
	global $mysqli;
	$nowEastern = phppickem_now_eastern_datetime();
	$sql = "select playoffGameID, gameTimeEastern from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$round . " and is_bye = 0 order by gameTimeEastern, playoffGameID";
	$query = $mysqli->query($sql);
	if (!$query) {
		$errors[] = 'Error loading playoff schedule for round ' . $round . ': ' . $mysqli->error;
		return;
	}
	while ($row = $query->fetch_assoc()) {
		$gameId = (int)$row['playoffGameID'];
		if (!empty($row['gameTimeEastern'])) {
			$gameTime = new DateTime($row['gameTimeEastern'], new DateTimeZone("America/New_York"));
			if ($nowEastern >= $gameTime) {
				continue;
			}
		}
		if ($apply) {
			$mysqli->query("delete from " . DB_PREFIX . "playoff_picks where userID = " . (int)$userId . " and gameID = " . $gameId);
			if (isset($picksByGameId[$gameId]) && $picksByGameId[$gameId] !== '') {
				$pick = $mysqli->real_escape_string($picksByGameId[$gameId]);
				$sqlInsert = "insert into " . DB_PREFIX . "playoff_picks (userID, gameID, pickTeamID) values (" . (int)$userId . ", " . $gameId . ", '" . $pick . "')";
				if (!$mysqli->query($sqlInsert)) {
					$errors[] = 'Error inserting playoff pick for userID ' . $userId . ', gameID ' . $gameId . ': ' . $mysqli->error;
				}
			}
		}
	}
	$query->free();
}

function getPlayoffPick($userId, $gameId) {
	global $mysqli;
	$query = $mysqli->query("select pickTeamID from " . DB_PREFIX . "playoff_picks where userID = " . (int)$userId . " and gameID = " . (int)$gameId);
	$pick = null;
	if ($query && $query->num_rows > 0) {
		$row = $query->fetch_assoc();
		$pick = $row['pickTeamID'];
	}
	if ($query) {
		$query->free();
	}
	return $pick;
}

if ($verbose) {
	echo "Season 2025 pick flow test (weeks=" . $weeks . ", apply=" . ($apply ? '1' : '0') . ", seed=" . $seed . ")\n";
}

foreach ($users as $user) {
	ensureUser($user, $plainPassword, $userIds, $errors, $apply);
}

foreach ($userIds as $userId) {
	clearUserData($userId, $errors, $apply);
}

$weekWinners = array(
	1 => 'bob',
	2 => 'sal',
	3 => 'howie',
	4 => 'bob',
	5 => 'sal',
	6 => 'bob',
	7 => 'howie',
	8 => 'sal',
	9 => 'bob',
	10 => 'sal',
	11 => 'howie',
	12 => 'bob',
	13 => 'sal',
	14 => 'bob',
	15 => 'howie',
	16 => 'sal',
	17 => 'bob',
	18 => 'howie'
);

$lastGameTime = null;
$expectedWins = array('bob' => 0, 'sal' => 0, 'howie' => 0);
$weeklyWinCounts = array();

function computeWeekCounts($games, $picksByUser, $outcomes) {
	$counts = array();
	foreach ($picksByUser as $username => $picks) {
		$counts[$username] = 0;
	}
	foreach ($games as $game) {
		$gameId = (int)$game['gameID'];
		if (!isset($outcomes[$gameId])) {
			continue;
		}
		$winnerId = $outcomes[$gameId];
		foreach ($picksByUser as $username => $picks) {
			if (isset($picks[$gameId]) && $picks[$gameId] === $winnerId) {
				$counts[$username] += 1;
			}
		}
	}
	return $counts;
}

function pickWeekOutcomes($games, $picksByUser, $winnerUser, $weekSeed, &$warnings) {
	$totalGames = count($games);
	if ($totalGames === 0) {
		return null;
	}
	$minWinner = (int)ceil($totalGames * 0.60);
	$maxWinner = (int)floor($totalGames * 0.85);
	if ($maxWinner < $minWinner) {
		$maxWinner = max($minWinner, $totalGames - 1);
	}
	$maxWinner = min($maxWinner, $totalGames - 1);

	for ($attempt = 0; $attempt < 2000; $attempt++) {
		mt_srand($weekSeed + $attempt);
		$outcomes = array();
		foreach ($games as $game) {
			$winner = (mt_rand(0, 1) === 0) ? $game['homeID'] : $game['visitorID'];
			$outcomes[(int)$game['gameID']] = $winner;
		}
		$counts = computeWeekCounts($games, $picksByUser, $outcomes);
		$winnerCount = $counts[$winnerUser];
		$others = $counts;
		unset($others[$winnerUser]);
		$maxOther = max($others);
		if ($winnerCount <= $maxOther) {
			continue;
		}
		if ($winnerCount >= $totalGames) {
			continue;
		}
		if ($winnerCount < $minWinner || $winnerCount > $maxWinner) {
			continue;
		}
		$hasOtherCorrect = false;
		foreach ($others as $count) {
			if ($count > 0) {
				$hasOtherCorrect = true;
				break;
			}
		}
		if (!$hasOtherCorrect) {
			continue;
		}
		return array($outcomes, $counts);
	}

	$warnings[] = 'Random outcome search failed; falling back to biased outcomes for ' . $winnerUser . '.';
	mt_srand($weekSeed + 9999);
	$outcomes = array();
	foreach ($games as $game) {
		$winner = (mt_rand(1, 100) <= 70) ? $picksByUser[$winnerUser][(int)$game['gameID']] : ((mt_rand(0, 1) === 0) ? $game['homeID'] : $game['visitorID']);
		$outcomes[(int)$game['gameID']] = $winner;
	}
	$counts = computeWeekCounts($games, $picksByUser, $outcomes);
	if ($counts[$winnerUser] >= $totalGames) {
		$flipGame = $games[0];
		$flipId = (int)$flipGame['gameID'];
		$outcomes[$flipId] = ($outcomes[$flipId] === $flipGame['homeID']) ? $flipGame['visitorID'] : $flipGame['homeID'];
		$counts = computeWeekCounts($games, $picksByUser, $outcomes);
	}
	return array($outcomes, $counts);
}

for ($week = 1; $week <= $weeks; $week++) {
	$weekSeed = $seed + ($week * 1000);
	if (!isset($weekWinners[$week])) {
		$errors[] = 'Missing week winner for week ' . $week;
		continue;
	}
	$winnerUser = $weekWinners[$week];
	$expectedWins[$winnerUser] += 1;
	$winnerPickSide = ($winnerUser === 'sal') ? 'visitor' : 'home';

	$games = array();
	$sql = "select gameID, gameTimeEastern, homeID, visitorID from " . DB_PREFIX . "schedule where weekNum = " . (int)$week . " order by gameTimeEastern, gameID";
	$query = $mysqli->query($sql);
	if (!$query || $query->num_rows === 0) {
		$errors[] = 'No games found for week ' . $week . '.';
		if ($query) {
			$query->free();
		}
		continue;
	}
	while ($row = $query->fetch_assoc()) {
		$games[] = $row;
	}
	$query->free();

	$firstGameTime = new DateTime($games[0]['gameTimeEastern'], new DateTimeZone("America/New_York"));
	$lastGameRow = $games[count($games) - 1];
	$lastGameTime = new DateTime($lastGameRow['gameTimeEastern'], new DateTimeZone("America/New_York"));
	if (!$lastGameTime) {
		$errors[] = 'Invalid last game time for week ' . $week;
	} else {
		$lastGameTime = $lastGameTime->format('Y-m-d H:i:s');
	}

	if ($firstGameTime->format('Y') !== SEASON_YEAR) {
		$warnings[] = 'Week ' . $week . ' schedule year is ' . $firstGameTime->format('Y') . ' (expected ' . SEASON_YEAR . ').';
	}

	$beforeKickoff = clone $firstGameTime;
	$beforeKickoff->modify('-1 hour');
	setTestNowEastern($beforeKickoff->format('Y-m-d H:i:s'));
	if ($verbose) {
		echo "Week " . $week . " start: test_time=" . $beforeKickoff->format('Y-m-d H:i:s') . " ET, first_game=" . $firstGameTime->format('Y-m-d H:i:s') . " ET\n";
	}

	$picksByUser = array();
	foreach ($users as $u) {
		$picksByUser[$u['username']] = array();
	}

	foreach ($games as $game) {
		$homeId = $game['homeID'];
		$visitorId = $game['visitorID'];
		foreach ($users as $u) {
			$username = $u['username'];
			mt_srand($weekSeed + (int)$game['gameID'] + strlen($username));
			$picksByUser[$username][(int)$game['gameID']] = (mt_rand(0, 1) === 0) ? $homeId : $visitorId;
		}
	}

	foreach ($users as $u) {
		$username = $u['username'];
		upsertPickSummary($userIds[$username], $week, 1, $errors, $apply);
		submitWeekPicks($userIds[$username], $week, $picksByUser[$username], $errors, $apply);
	}

	$outcomeResult = pickWeekOutcomes($games, $picksByUser, $winnerUser, $weekSeed, $warnings);
	if (!$outcomeResult) {
		$errors[] = 'Could not determine outcomes for week ' . $week;
		continue;
	}
	list($outcomes, $weekCounts) = $outcomeResult;
	$winnerCount = $weekCounts[$winnerUser];
	$maxOther = 0;
	foreach ($weekCounts as $name => $count) {
		if ($name === $winnerUser) {
			continue;
		}
		if ($count > $maxOther) {
			$maxOther = $count;
		}
	}
	if ($winnerCount <= $maxOther) {
		$errors[] = 'Week ' . $week . ' did not produce a unique winner for ' . $winnerUser;
	}
	if ($winnerCount >= count($games)) {
		$errors[] = 'Week ' . $week . ' produced a perfect week for ' . $winnerUser;
	}
	$weeklyWinCounts[$week] = $weekCounts;

	if ($apply) {
		$editUser = ($winnerUser !== 'bob') ? 'bob' : 'sal';
		$editUserId = $userIds[$editUser];
		$firstGameId = (int)$games[0]['gameID'];
		$currentPick = getUserPick($editUserId, $firstGameId);
		$expectedInitialPick = $picksByUser[$editUser][$firstGameId];
		if ($currentPick !== $expectedInitialPick) {
			$errors[] = 'Week ' . $week . ' initial pick mismatch for ' . $editUser;
		}
		$alternatePick = ($currentPick === $games[0]['homeID']) ? $games[0]['visitorID'] : $games[0]['homeID'];
		$picksByUser[$editUser][$firstGameId] = $alternatePick;
		submitWeekPicks($editUserId, $week, $picksByUser[$editUser], $errors, $apply);
		$updatedPick = getUserPick($editUserId, $firstGameId);
		$gameTimeStr = $games[0]['gameTimeEastern'];
		$serverTimeStr = $beforeKickoff->format('Y-m-d H:i:s');
		if ($updatedPick !== $alternatePick) {
			$errors[] = 'Week ' . $week . ' pre-kickoff edit failed for ' . $editUser;
			printPickResult("Week " . $week . " pre-kickoff edit BLOCKED", false, $serverTimeStr, $gameTimeStr, "user=" . $editUser);
		} else {
			printPickResult("Week " . $week . " pre-kickoff edit OK", true, $serverTimeStr, $gameTimeStr, "user=" . $editUser);
		}

		$lockedGames = array_slice($games, 0, min(3, count($games)));
		foreach ($lockedGames as $lockedGame) {
			$afterKickoff = new DateTime($lockedGame['gameTimeEastern'], new DateTimeZone("America/New_York"));
			$afterKickoff->modify('+1 minute');
			setTestNowEastern($afterKickoff->format('Y-m-d H:i:s'));

			$gameId = (int)$lockedGame['gameID'];
			$prevPick = getUserPick($editUserId, $gameId);
			$altPick = ($prevPick === $lockedGame['homeID']) ? $lockedGame['visitorID'] : $lockedGame['homeID'];
			$picksByUser[$editUser][$gameId] = $altPick;
			submitWeekPicks($editUserId, $week, $picksByUser[$editUser], $errors, $apply);
			$lockedPick = getUserPick($editUserId, $gameId);
			$serverTimeStr = $afterKickoff->format('Y-m-d H:i:s');
			$gameTimeStr = $lockedGame['gameTimeEastern'];
			if ($lockedPick === $prevPick) {
				printPickResult("Week " . $week . " post-kickoff edit BLOCKED", true, $serverTimeStr, $gameTimeStr, "gameID=" . $gameId . " user=" . $editUser);
			} else {
				$errors[] = 'Week ' . $week . ' post-kickoff edit should be locked for ' . $editUser . ' gameID ' . $gameId;
				printPickResult("Week " . $week . " post-kickoff edit ALLOWED", false, $serverTimeStr, $gameTimeStr, "gameID=" . $gameId . " user=" . $editUser);
			}
		}

		foreach ($games as $game) {
			$gameId = (int)$game['gameID'];
			$winnerId = $outcomes[$gameId];
			$homeScore = ($winnerId === $game['homeID']) ? 1 : 0;
			$visitorScore = ($winnerId === $game['visitorID']) ? 1 : 0;
			$updateSql = "update " . DB_PREFIX . "schedule set homeScore = " . $homeScore . ", visitorScore = " . $visitorScore . " where gameID = " . (int)$game['gameID'];
			if (!$mysqli->query($updateSql)) {
				$errors[] = 'Error updating scores for gameID ' . (int)$game['gameID'] . ': ' . $mysqli->error;
			}
		}
	}

	if ($verbose && isset($weeklyWinCounts[$week])) {
		$counts = $weeklyWinCounts[$week];
		echo "Week " . $week . " results: bob=" . $counts['bob'] . ", sal=" . $counts['sal'] . ", howie=" . $counts['howie'] . "\n";
	}
}

if ($apply) {
	if ($lastGameTime) {
		$finalTime = new DateTime($lastGameTime, new DateTimeZone("America/New_York"));
		$finalTime->modify('+1 day');
		setTestNowEastern($finalTime->format('Y-m-d H:i:s'));
		if ($verbose) {
			echo "Final test time set to " . $finalTime->format('Y-m-d H:i:s') . " ET\n";
		}
	}

	$stats = $statsService->calculateStats();
	$winCounts = array('bob' => 0, 'sal' => 0, 'howie' => 0);
	if (!empty($stats['weekStats'])) {
		foreach ($stats['weekStats'] as $week => $row) {
			if ((int)$week > $weeks) {
				continue;
			}
			if (!empty($row['winners'])) {
				foreach ($row['winners'] as $winnerId) {
					foreach ($userIds as $username => $userId) {
						if ((int)$winnerId === (int)$userId) {
							$winCounts[$username] += 1;
						}
					}
				}
			}
		}
	}

	foreach ($expectedWins as $username => $count) {
		if ($winCounts[$username] !== $count) {
			$errors[] = 'Unexpected win count for ' . $username . ' (expected ' . $count . ', got ' . $winCounts[$username] . ')';
		}
	}
	if (count(array_unique($winCounts)) !== count($winCounts)) {
		$errors[] = 'Win counts are not unique: ' . json_encode($winCounts);
	}
}

if (!$apply) {
	echo "Dry run complete. Use --apply=1 to write data.\n";
}

$playoffRounds = array();
$playoffQuery = $mysqli->query("select playoffGameID, roundNum, gameTimeEastern, homeID, visitorID from " . DB_PREFIX . "playoff_schedule where is_bye = 0 order by roundNum, gameTimeEastern, playoffGameID");
if ($playoffQuery) {
	while ($row = $playoffQuery->fetch_assoc()) {
		if (empty($row['gameTimeEastern'])) {
			continue;
		}
		$round = (int)$row['roundNum'];
		if (!isset($playoffRounds[$round])) {
			$playoffRounds[$round] = array();
		}
		$playoffRounds[$round][] = $row;
	}
	$playoffQuery->free();
}

if (empty($playoffRounds)) {
	echo $yellow . "No playoff games with times found; skipping playoff picks." . $reset . "\n";
} else {
	foreach ($playoffRounds as $roundNum => $roundGames) {
		$roundSeed = $seed + 50000 + ($roundNum * 1000);
		$firstPlayoffGameTime = new DateTime($roundGames[0]['gameTimeEastern'], new DateTimeZone("America/New_York"));
		$beforePlayoff = clone $firstPlayoffGameTime;
		$beforePlayoff->modify('-1 hour');
		setTestNowEastern($beforePlayoff->format('Y-m-d H:i:s'));
		echo "Playoff round " . $roundNum . " start: test_time=" . $beforePlayoff->format('Y-m-d H:i:s') . " ET, first_game=" . $firstPlayoffGameTime->format('Y-m-d H:i:s') . " ET\n";

		$playoffPicksByUser = array();
		foreach ($users as $u) {
			$playoffPicksByUser[$u['username']] = array();
		}
		foreach ($roundGames as $game) {
			$homeId = $game['homeID'];
			$visitorId = $game['visitorID'];
			foreach ($users as $u) {
				$username = $u['username'];
				mt_srand($roundSeed + (int)$game['playoffGameID'] + strlen($username));
				$playoffPicksByUser[$username][(int)$game['playoffGameID']] = (mt_rand(0, 1) === 0) ? $homeId : $visitorId;
			}
		}

		foreach ($users as $u) {
			$username = $u['username'];
			submitPlayoffPicks($userIds[$username], $roundNum, $playoffPicksByUser[$username], $errors, $apply);
		}

		if ($apply) {
			$editUser = 'bob';
			$editUserId = $userIds[$editUser];
			$firstPlayoffGameId = (int)$roundGames[0]['playoffGameID'];
			$currentPick = getPlayoffPick($editUserId, $firstPlayoffGameId);
			$expectedPick = $playoffPicksByUser[$editUser][$firstPlayoffGameId];
			if ($currentPick !== $expectedPick) {
				$errors[] = 'Playoff round ' . $roundNum . ' initial pick mismatch for ' . $editUser;
			}
			$alternatePick = ($currentPick === $roundGames[0]['homeID']) ? $roundGames[0]['visitorID'] : $roundGames[0]['homeID'];
			$playoffPicksByUser[$editUser][$firstPlayoffGameId] = $alternatePick;
			submitPlayoffPicks($editUserId, $roundNum, $playoffPicksByUser[$editUser], $errors, $apply);
			$updatedPick = getPlayoffPick($editUserId, $firstPlayoffGameId);
			$serverTimeStr = $beforePlayoff->format('Y-m-d H:i:s');
			$gameTimeStr = $roundGames[0]['gameTimeEastern'];
			if ($updatedPick !== $alternatePick) {
				$errors[] = 'Playoff round ' . $roundNum . ' pre-start edit failed for ' . $editUser;
				printPickResult("Playoff round " . $roundNum . " pre-start edit BLOCKED", false, $serverTimeStr, $gameTimeStr, "user=" . $editUser);
			} else {
				printPickResult("Playoff round " . $roundNum . " pre-start edit OK", true, $serverTimeStr, $gameTimeStr, "user=" . $editUser);
			}

			$lockedGames = array_slice($roundGames, 0, min(3, count($roundGames)));
			foreach ($lockedGames as $lockedGame) {
				$afterKickoff = new DateTime($lockedGame['gameTimeEastern'], new DateTimeZone("America/New_York"));
				$afterKickoff->modify('+1 minute');
				setTestNowEastern($afterKickoff->format('Y-m-d H:i:s'));
				$gameId = (int)$lockedGame['playoffGameID'];
				$prevPick = getPlayoffPick($editUserId, $gameId);
				$altPick = ($prevPick === $lockedGame['homeID']) ? $lockedGame['visitorID'] : $lockedGame['homeID'];
				$playoffPicksByUser[$editUser][$gameId] = $altPick;
				submitPlayoffPicks($editUserId, $roundNum, $playoffPicksByUser[$editUser], $errors, $apply);
				$lockedPick = getPlayoffPick($editUserId, $gameId);
				$serverTimeStr = $afterKickoff->format('Y-m-d H:i:s');
				$gameTimeStr = $lockedGame['gameTimeEastern'];
				if ($lockedPick === $prevPick) {
					printPickResult("Playoff round " . $roundNum . " post-start edit BLOCKED", true, $serverTimeStr, $gameTimeStr, "gameID=" . $gameId . " user=" . $editUser);
				} else {
					$errors[] = 'Playoff round ' . $roundNum . ' post-start edit should be locked for ' . $editUser . ' gameID ' . $gameId;
					printPickResult("Playoff round " . $roundNum . " post-start edit ALLOWED", false, $serverTimeStr, $gameTimeStr, "gameID=" . $gameId . " user=" . $editUser);
				}
			}

			foreach ($roundGames as $game) {
				$homeScore = (mt_rand(0, 1) === 0) ? 1 : 0;
				$visitorScore = $homeScore === 1 ? 0 : 1;
				$updateSql = "update " . DB_PREFIX . "playoff_schedule set homeScore = " . $homeScore . ", visitorScore = " . $visitorScore . " where playoffGameID = " . (int)$game['playoffGameID'];
				if (!$mysqli->query($updateSql)) {
					$errors[] = 'Error updating playoff scores for gameID ' . (int)$game['playoffGameID'] . ': ' . $mysqli->error;
				}
			}
		}
	}
}

if (!empty($warnings)) {
	echo "Warnings:\n";
	foreach ($warnings as $warning) {
		echo "- " . $warning . "\n";
	}
}
if (!empty($errors)) {
	fwrite(STDERR, "Season 2025 pick flow test failed:\n");
	foreach ($errors as $error) {
		fwrite(STDERR, "- " . $error . "\n");
	}
	exit(1);
}

echo "Season 2025 pick flow test passed.\n";
if ($apply) {
	echo "Win counts: bob=" . $winCounts['bob'] . ", sal=" . $winCounts['sal'] . ", howie=" . $winCounts['howie'] . "\n";
}
