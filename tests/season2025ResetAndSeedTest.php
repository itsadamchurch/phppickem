<?php
require(__DIR__ . '/bootstrap.php');
require(__DIR__ . '/../includes/application_top.php');

// Usage:
// php tests/season2025ResetAndSeedTest.php --apply=1 --year=2025

$args = array();
if (PHP_SAPI === 'cli') {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$apply = isset($args['apply']) ? ($args['apply'] === '1') : false;
$year = isset($args['year']) ? (int)$args['year'] : (int)SEASON_YEAR;
$weeks = isset($args['weeks']) ? (int)$args['weeks'] : 18;

$teamMap = array(
	'LV' => 'OAK',
	'LAC' => 'SD',
	'LAR' => 'LA',
	'WSH' => 'WAS'
);

$errors = array();

function logLine($message) {
	echo $message . PHP_EOL;
}

function ensurePlaceholderTeam($teamId, $displayName) {
	global $mysqli;
	$check = $mysqli->query("select teamID from " . DB_PREFIX . "teams where teamID = '" . $mysqli->real_escape_string($teamId) . "'");
	if ($check && $check->num_rows > 0) {
		if ($check) {
			$check->free();
		}
		return;
	}
	if ($check) {
		$check->free();
	}
	$sql = "insert into " . DB_PREFIX . "teams (teamID, divisionID, city, team, displayName) values (" .
		"'" . $mysqli->real_escape_string($teamId) . "', 0, '', '', '" . $mysqli->real_escape_string($displayName) . "')";
	$mysqli->query($sql);
}

function fetchRegularSeasonSchedule($year, $weeks, $teamMap) {
	$schedule = array();
	for ($week = 1; $week <= $weeks; $week++) {
		$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=" . $year . "&seasontype=2&week=" . $week;
		$json = @file_get_contents($url);
		if (!$json) {
			throw new Exception('Error getting schedule from ESPN for week ' . $week);
		}
		$decoded = json_decode($json, true);
		$events = isset($decoded['events']) ? $decoded['events'] : array();
		foreach ($events as $event) {
			if (empty($event['competitions'][0]['competitors'])) {
				continue;
			}
			$date = new DateTime($event['date']);
			$date->setTimezone(new DateTimeZone('America/New_York'));
			$gameTimeEastern = $date->format('Y-m-d H:i:00');
			$home_team = null;
			$away_team = null;
			foreach ($event['competitions'][0]['competitors'] as $team) {
				if ($team['homeAway'] === 'home') {
					$home_team = $team['team']['abbreviation'];
				} else {
					$away_team = $team['team']['abbreviation'];
				}
			}
			if (!$home_team || !$away_team) {
				continue;
			}
			if (isset($teamMap[$home_team])) $home_team = $teamMap[$home_team];
			if (isset($teamMap[$away_team])) $away_team = $teamMap[$away_team];
			$schedule[] = array(
				'weekNum' => $week,
				'gameTimeEastern' => $gameTimeEastern,
				'homeID' => $home_team,
				'visitorID' => $away_team
			);
		}
	}
	return $schedule;
}

function fetchPlayoffEvents($year, $round) {
	$events = array();
	$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=" . $year . "&seasontype=3&week=" . $round;
	$json = @file_get_contents($url);
	if ($json) {
		$decoded = json_decode($json, true);
		$events = isset($decoded['events']) ? $decoded['events'] : array();
	}
	if (empty($events) && $round === 4) {
		$sbYear = $year + 1;
		$dateRange = $sbYear . "0201-" . $sbYear . "0215";
		$rangeUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=3&dates=" . $dateRange;
		$rangeJson = @file_get_contents($rangeUrl);
		if ($rangeJson) {
			$decoded = json_decode($rangeJson, true);
			$events = isset($decoded['events']) ? $decoded['events'] : array();
		}
		if (empty($events)) {
			$singleDateUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=3&dates=" . $sbYear . "0208";
			$singleJson = @file_get_contents($singleDateUrl);
			if ($singleJson) {
				$decoded = json_decode($singleJson, true);
				$events = isset($decoded['events']) ? $decoded['events'] : array();
			}
		}
	}
	return $events;
}

function fetchPlayoffSchedule($year, $rounds, $teamMap) {
	$schedule = array();
	foreach ($rounds as $round) {
		$events = fetchPlayoffEvents($year, $round);
		foreach ($events as $event) {
			if (empty($event['competitions'][0]['competitors'])) {
				continue;
			}
			$date = new DateTime($event['date']);
			$date->setTimezone(new DateTimeZone('America/New_York'));
			$gameTimeEastern = $date->format('Y-m-d H:i:00');
			$home_team = null;
			$away_team = null;
			foreach ($event['competitions'][0]['competitors'] as $team) {
				if ($team['homeAway'] === 'home') {
					$home_team = $team['team']['abbreviation'];
				} else {
					$away_team = $team['team']['abbreviation'];
				}
			}
			if (!$home_team || !$away_team) {
				continue;
			}
			if (isset($teamMap[$home_team])) $home_team = $teamMap[$home_team];
			if (isset($teamMap[$away_team])) $away_team = $teamMap[$away_team];
			$schedule[] = array(
				'roundNum' => $round,
				'gameTimeEastern' => $gameTimeEastern,
				'homeID' => $home_team,
				'visitorID' => $away_team
			);
		}
	}
	return $schedule;
}

function updateRegularSeasonScores($year, $apply, $teamMap, &$errors) {
	global $mysqli;
	$weeks = array();
	$query = $mysqli->query("select distinct weekNum from " . DB_PREFIX . "schedule order by weekNum");
	while ($row = $query->fetch_assoc()) {
		$weeks[] = (int)$row['weekNum'];
	}
	$query->free();
	$updates = 0;
	$skipped = 0;
	foreach ($weeks as $week) {
		$dateParam = '';
		$minMaxQuery = $mysqli->query("select min(gameTimeEastern) as minTime, max(gameTimeEastern) as maxTime from " . DB_PREFIX . "schedule where weekNum = " . (int)$week);
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
		$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=2&week=" . $week . "&dates=" . $year;
		if (!empty($dateParam)) {
			$url .= "&dates=" . $dateParam;
		}
		$json = @file_get_contents($url);
		if (!$json) {
			$errors[] = "Error fetching ESPN data for week " . $week;
			continue;
		}
		$decoded = json_decode($json, true);
		$events = isset($decoded['events']) ? $decoded['events'] : array();
		$weekCompletedCount = 0;
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
				continue;
			}
			$weekCompletedCount++;
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
				continue;
			}
			if ($homeScore === null || $awayScore === null) {
				$skipped++;
				continue;
			}
			$updateSql = "update " . DB_PREFIX . "schedule set homeScore = " . $homeScore . ", visitorScore = " . $awayScore . ", overtime = " . (int)$overtime . " ";
			$updateSql .= "where weekNum = " . (int)$week . " and homeID = '" . $mysqli->real_escape_string($homeTeam) . "' and visitorID = '" . $mysqli->real_escape_string($awayTeam) . "'";
			if ($apply) {
				$mysqli->query($updateSql);
				if ($mysqli->affected_rows === 0) {
					$updateSqlSwap = "update " . DB_PREFIX . "schedule set homeScore = " . $awayScore . ", visitorScore = " . $homeScore . ", overtime = " . (int)$overtime . " ";
					$updateSqlSwap .= "where weekNum = " . (int)$week . " and homeID = '" . $mysqli->real_escape_string($awayTeam) . "' and visitorID = '" . $mysqli->real_escape_string($homeTeam) . "'";
					$mysqli->query($updateSqlSwap);
					if ($mysqli->affected_rows > 0) {
						$updates++;
					} else {
						$skipped++;
					}
				} else {
					$updates++;
				}
			} else {
				$updates++;
			}
		}
		if ($weekCompletedCount === 0) {
			break;
		}
	}
	return array($updates, $skipped);
}

function updatePlayoffScores($year, $apply, $teamMap, &$errors) {
	global $mysqli;
	$rounds = array(1, 2, 3, 4);
	$updates = 0;
	$skipped = 0;
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
				continue;
			}
			if ($homeScore === null || $awayScore === null) {
				$skipped++;
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
					} else {
						$skipped++;
					}
				} else {
					$updates++;
				}
			} else {
				$updates++;
			}
		}
		if ($roundCompletedCount === 0) {
			break;
		}
	}
	return array($updates, $skipped);
}

logLine("Reset + seed test (year=" . $year . ", weeks=" . $weeks . ", apply=" . ($apply ? '1' : '0') . ")");

if ($apply) {
	$tables = array('picks', 'playoff_picks', 'picksummary', 'schedule', 'playoff_schedule');
	foreach ($tables as $table) {
		$sql = "truncate table " . DB_PREFIX . $table;
		if (!$mysqli->query($sql)) {
			$errors[] = 'Error truncating ' . $table . ': ' . $mysqli->error;
		}
	}
	$mysqli->query("delete from " . DB_PREFIX . "users where is_admin = 0");
	logLine("Cleared picks and schedules.");
} else {
	logLine("Dry run: not clearing tables.");
}

try {
	$regularSchedule = fetchRegularSeasonSchedule($year, $weeks, $teamMap);
	logLine("Fetched regular season schedule: " . count($regularSchedule) . " games.");
	if ($apply) {
		foreach ($regularSchedule as $row) {
			$sql = "insert into " . DB_PREFIX . "schedule (weekNum, gameTimeEastern, homeID, homeScore, visitorID, visitorScore, overtime) values (" .
				(int)$row['weekNum'] . ", '" . $mysqli->real_escape_string($row['gameTimeEastern']) . "', '" . $mysqli->real_escape_string($row['homeID']) . "', 0, '" .
				$mysqli->real_escape_string($row['visitorID']) . "', 0, 0)";
			if (!$mysqli->query($sql)) {
				$errors[] = 'Error inserting schedule game: ' . $mysqli->error;
			}
		}
		logLine("Inserted regular season schedule.");
	}
} catch (Exception $e) {
	$errors[] = $e->getMessage();
}

$playoffRounds = array(1, 2, 3, 4);
$playoffSchedule = fetchPlayoffSchedule($year, $playoffRounds, $teamMap);
logLine("Fetched playoff schedule: " . count($playoffSchedule) . " games.");
if ($apply) {
	foreach ($playoffRounds as $round) {
		$mysqli->query("delete from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$round . " and is_bye = 0");
	}
	foreach ($playoffSchedule as $row) {
		if ($row['homeID'] === 'AFC') {
			ensurePlaceholderTeam('AFC', 'AFC Champion');
		}
		if ($row['homeID'] === 'NFC') {
			ensurePlaceholderTeam('NFC', 'NFC Champion');
		}
		if ($row['visitorID'] === 'AFC') {
			ensurePlaceholderTeam('AFC', 'AFC Champion');
		}
		if ($row['visitorID'] === 'NFC') {
			ensurePlaceholderTeam('NFC', 'NFC Champion');
		}
		$sql = "insert into " . DB_PREFIX . "playoff_schedule (roundNum, gameTimeEastern, homeID, homeScore, visitorID, visitorScore, overtime, is_bye) values (" .
			(int)$row['roundNum'] . ", '" . $mysqli->real_escape_string($row['gameTimeEastern']) . "', '" . $mysqli->real_escape_string($row['homeID']) . "', 0, '" .
			$mysqli->real_escape_string($row['visitorID']) . "', 0, 0, 0)";
		if (!$mysqli->query($sql)) {
			$errors[] = 'Error inserting playoff game: ' . $mysqli->error;
		}
	}
	logLine("Inserted playoff schedule.");
}

list($regUpdated, $regSkipped) = updateRegularSeasonScores($year, $apply, $teamMap, $errors);
logLine("Regular season score update complete. Updated: " . $regUpdated . ", Skipped: " . $regSkipped);

list($poUpdated, $poSkipped) = updatePlayoffScores($year, $apply, $teamMap, $errors);
logLine("Playoff score update complete. Updated: " . $poUpdated . ", Skipped: " . $poSkipped);

if (!empty($errors)) {
	logLine("Errors:");
	foreach ($errors as $error) {
		logLine("- " . $error);
	}
}
