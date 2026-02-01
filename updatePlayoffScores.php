<?php
require('includes/application_top.php');

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
	$user = isset($user) ? $user : null;
	if (!$user || !$user->is_admin) {
		header('Location: ./');
		exit;
	}
}

// Usage (CLI):
// php updatePlayoffScores.php --apply=1 --year=2025 --round=1
// Or via browser:
// updatePlayoffScores.php?apply=1&year=2025&round=1

$args = array();
if ($isCli) {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$year = isset($args['year']) ? (int)$args['year'] : (isset($_GET['year']) ? (int)$_GET['year'] : (int)SEASON_YEAR);
$apply = isset($args['apply']) ? ($args['apply'] === '1') : (isset($_GET['apply']) && $_GET['apply'] === '1');
$roundFilter = isset($args['round']) ? (int)$args['round'] : (isset($_GET['round']) ? (int)$_GET['round'] : 0);
$debug = isset($args['debug']) ? ($args['debug'] === '1') : (isset($_GET['debug']) && $_GET['debug'] === '1');
$returnUrl = isset($args['return']) ? $args['return'] : (isset($_GET['return']) ? $_GET['return'] : '');

$rounds = array(1, 2, 3, 4);
if ($roundFilter > 0) {
	$rounds = array($roundFilter);
}

$teamMap = array(
	'LV' => 'OAK',
	'LAC' => 'SD',
	'LAR' => 'LA',
	'WSH' => 'WAS'
);

$updates = 0;
$skipped = 0;
$errors = array();
$debugOutput = array();

foreach ($rounds as $round) {
	$dateParam = '';
	$minMaxQuery = $mysqli->query("select min(gameTimeEastern) as minTime, max(gameTimeEastern) as maxTime from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$round);
	if ($minMaxQuery && $minMaxQuery->num_rows > 0) {
		$row = $minMaxQuery->fetch_assoc();
		if (!empty($row['minTime']) && !empty($row['maxTime'])) {
			$start = date('Ymd', strtotime($row['minTime']));
			$end = date('Ymd', strtotime($row['maxTime']));
			if (!empty($start) && !empty($end)) {
				$dateParam = ($start === $end) ? $start : ($start . '-' . $end);
			}
		}
		$minMaxQuery->free();
	}
	$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=3&week=" . $round . "&dates=" . $year;
	if (!empty($dateParam)) {
		$url .= "&dates=" . $dateParam;
	}
	$json = @file_get_contents($url);
	if (!$json) {
		$errors[] = "Error fetching ESPN data for round " . $round;
		continue;
	}
	$decoded = json_decode($json, true);
	$events = isset($decoded['events']) ? $decoded['events'] : array();
	$roundUpdates = 0;
	$roundSkipped = 0;
	$roundCompletedCount = 0;
	foreach ($events as $event) {
		if (empty($event['competitions'][0]['competitors'])) {
			continue;
		}

		$homeTeam = null;
		$awayTeam = null;
		$homeScore = null;
		$awayScore = null;
		$overtime = 0;

		$status = isset($event['competitions'][0]['status']) ? $event['competitions'][0]['status'] : array();
		$completed = !empty($status['type']['completed']);
		if (!$completed) {
			$skipped++;
			$roundSkipped++;
			continue;
		}
		$roundCompletedCount++;
		if (!empty($status['period']) && (int)$status['period'] > 4) {
			$overtime = 1;
		}

		foreach ($event['competitions'][0]['competitors'] as $team) {
			if (empty($team['team']['abbreviation'])) {
				continue;
			}
			$abbr = $team['team']['abbreviation'];
			if (isset($teamMap[$abbr])) {
				$abbr = $teamMap[$abbr];
			}
			if ($team['homeAway'] === 'home') {
				$homeTeam = $abbr;
				$homeScore = is_numeric($team['score']) ? (int)$team['score'] : null;
			} else {
				$awayTeam = $abbr;
				$awayScore = is_numeric($team['score']) ? (int)$team['score'] : null;
			}
		}

		if (!$homeTeam || !$awayTeam) {
			$skipped++;
			$roundSkipped++;
			continue;
		}

		if ($homeScore === null || $awayScore === null) {
			$skipped++;
			$roundSkipped++;
			continue;
		}

		$updateSql = "update " . DB_PREFIX . "playoff_schedule set homeScore = " . $homeScore . ", visitorScore = " . $awayScore . ", overtime = " . (int)$overtime . " ";
		$updateSql .= "where roundNum = " . (int)$round . " and homeID = '" . $mysqli->real_escape_string($homeTeam) . "' and visitorID = '" . $mysqli->real_escape_string($awayTeam) . "' and is_bye = 0";

		if ($apply) {
			$mysqli->query($updateSql);
			if ($mysqli->affected_rows === 0) {
				$updateSqlSwap = "update " . DB_PREFIX . "playoff_schedule set homeScore = " . $awayScore . ", visitorScore = " . $homeScore . ", overtime = " . (int)$overtime . " ";
				$updateSqlSwap .= "where roundNum = " . (int)$round . " and homeID = '" . $mysqli->real_escape_string($awayTeam) . "' and visitorID = '" . $mysqli->real_escape_string($homeTeam) . "' and is_bye = 0";
				$mysqli->query($updateSqlSwap);
				if ($mysqli->affected_rows > 0) {
					$updates++;
					$roundUpdates++;
				} else {
					$skipped++;
					$roundSkipped++;
				}
			} else {
				$updates++;
				$roundUpdates++;
			}
		} else {
			$updates++;
			$roundUpdates++;
		}
	}
	if ($debug) {
		$debugOutput[] = array(
			'round' => (int)$round,
			'url' => $url,
			'dateParam' => $dateParam,
			'eventsFound' => count($events),
			'updated' => $roundUpdates,
			'skipped' => $roundSkipped
		);
	}
	if ($roundCompletedCount === 0) {
		break;
	}
}

if ($debug && !$isCli) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array(
		'year' => $year,
		'apply' => $apply ? 1 : 0,
		'updated' => $updates,
		'skipped' => $skipped,
		'errors' => $errors,
		'rounds' => $debugOutput
	));
	exit;
}

if (!$apply) {
	echo "Dry run complete. Use apply=1 to update scores." . PHP_EOL;
}
echo "Playoff score update complete. Updated: " . $updates . ", Skipped: " . $skipped . PHP_EOL;
if (count($errors) > 0) {
	echo "Errors:" . PHP_EOL;
	foreach ($errors as $error) {
		echo "- " . $error . PHP_EOL;
	}
}

if (!$isCli && !empty($returnUrl)) {
	$sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
	header('Location: ' . $returnUrl . $sep . 'updated_count=' . $updates);
	exit;
}
