<?php
require('includes/application_top.php');

if (!$user->is_admin) {
	header('HTTP/1.1 403 Forbidden');
	exit;
}

header('Content-Type: application/json; charset=utf-8');

$week = (int)$_GET['week'];
$round = isset($_GET['round']) ? (int)$_GET['round'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'regular';
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

$teamMap = array(
	'LV' => 'OAK',
	'LAC' => 'SD',
	'LAR' => 'LA',
	'WSH' => 'WAS'
);

$dateParam = '';
$scheduleTable = ($type === 'playoffs') ? 'playoff_schedule' : 'schedule';
$scheduleFilter = ($type === 'playoffs') ? ("roundNum = " . (int)$round) : ("weekNum = " . (int)$week);
$minMaxQuery = $mysqli->query("select min(gameTimeEastern) as minTime, max(gameTimeEastern) as maxTime from " . DB_PREFIX . $scheduleTable . " where " . $scheduleFilter);
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

$seasonYear = defined('SEASON_YEAR') ? (int)SEASON_YEAR : (int)date('Y');
$seasonType = ($type === 'playoffs') ? 3 : 2;
$weekParam = ($type === 'playoffs') ? $round : $week;
$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=" . $seasonType . "&week=" . $weekParam . "&dates=" . $seasonYear;
if (!empty($dateParam)) {
	$url .= "&dates=" . $dateParam;
}
$json = @file_get_contents($url);
$events = array();
if ($json) {
	$decoded = json_decode($json, true);
	$events = isset($decoded['events']) ? $decoded['events'] : array();
}

$scores = array();
foreach ($events as $event) {
	if (empty($event['competitions'][0])) {
		continue;
	}
	$competition = $event['competitions'][0];
	$status = isset($competition['status']['type']) ? $competition['status']['type'] : array();
	$completed = !empty($status['completed']);
	if (!$completed) {
		continue;
	}
	$home_team = null;
	$away_team = null;
	$home_score = null;
	$away_score = null;
	if (!empty($competition['competitors'])) {
		foreach ($competition['competitors'] as $team) {
			$abbr = isset($team['team']['abbreviation']) ? $team['team']['abbreviation'] : null;
			$score = isset($team['score']) ? (int)$team['score'] : null;
			if ($team['homeAway'] === 'home') {
				$home_team = $abbr;
				$home_score = $score;
			} else {
				$away_team = $abbr;
				$away_score = $score;
			}
		}
	}
	if (!$home_team || !$away_team) {
		continue;
	}
	if (isset($teamMap[$home_team])) $home_team = $teamMap[$home_team];
	if (isset($teamMap[$away_team])) $away_team = $teamMap[$away_team];

	if ($type === 'playoffs') {
		$gameID = 0;
		$gameQuery = $mysqli->query("select playoffGameID from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$round .
			" and ((homeID = '" . $mysqli->real_escape_string($home_team) . "' and visitorID = '" . $mysqli->real_escape_string($away_team) . "') " .
			"or (homeID = '" . $mysqli->real_escape_string($away_team) . "' and visitorID = '" . $mysqli->real_escape_string($home_team) . "')) and is_bye = 0");
		if ($gameQuery && $gameQuery->num_rows > 0) {
			$gameRow = $gameQuery->fetch_assoc();
			$gameID = (int)$gameRow['playoffGameID'];
		}
		if ($gameQuery) {
			$gameQuery->free();
		}
		if (!$gameID) {
			continue;
		}
	} else {
		$gameID = $statsService->getGameIDByTeamID($week, $home_team);
		if (!$gameID) {
			$gameID = $statsService->getGameIDByTeamID($week, $away_team);
		}
		if (!$gameID) {
			continue;
		}
	}

	$overtime = 0;
	if (!empty($status['shortDetail']) && stripos($status['shortDetail'], 'OT') !== false) {
		$overtime = 1;
	}

	$winner = ($away_score > $home_score) ? $away_team : $home_team;
	$scores[] = array(
		'gameID' => $gameID,
		'awayteam' => $away_team,
		'visitorScore' => $away_score,
		'hometeam' => $home_team,
		'homeScore' => $home_score,
		'overtime' => $overtime,
		'winner' => $winner
	);
}

if ($debug) {
	echo json_encode(array(
		'type' => $type,
		'round' => $round,
		'week' => $week,
		'url' => $url,
		'dateParam' => $dateParam,
		'eventsFound' => count($events),
		'scoresFound' => count($scores),
		'scores' => $scores
	));
	exit;
}

echo json_encode($scores);

//game results and winning teams can now be accessed from the scores array
//e.g. $scores[0]['awayteam'] contains the name of the away team (['awayteam'] part) from the first game on the page ([0] part)
