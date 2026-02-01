<?php
require('includes/application_top.php');

$user = isset($user) ? $user : null;
if (!$user || !$user->is_admin) {
	header('Location: ./');
	exit;
}

$round = isset($_GET['round']) ? (int)$_GET['round'] : 0;
$apply = (isset($_GET['apply']) && $_GET['apply'] === '1');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)SEASON_YEAR;
$returnUrl = isset($_GET['return']) ? $_GET['return'] : '';
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($round <= 0) {
	die('Missing round. Use ?round=1..4&apply=1');
}

$teamMap = array(
	'LV' => 'OAK',
	'LAC' => 'SD',
	'LAR' => 'LA',
	'WSH' => 'WAS'
);

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

function fetchPlayoffEvents($year, $round, &$usedUrl = null) {
	$events = array();
	$roundNames = array(
		1 => 'Wild Card',
		2 => 'Divisional',
		3 => 'Conference',
		4 => 'Super Bowl'
	);
	$weeksUrl = "https://sports.core.api.espn.com/v2/sports/football/leagues/nfl/seasons/" . $year . "/types/3/weeks";
	$weeksJson = @file_get_contents($weeksUrl);
	if ($weeksJson) {
		$weeksDecoded = json_decode($weeksJson, true);
		if (!empty($weeksDecoded['items']) && is_array($weeksDecoded['items'])) {
			$weekRef = null;
			foreach ($weeksDecoded['items'] as $idx => $item) {
				if (empty($item['$ref'])) {
					continue;
				}
				$weekJson = @file_get_contents($item['$ref']);
				if (!$weekJson) {
					continue;
				}
				$weekDecoded = json_decode($weekJson, true);
				$text = isset($weekDecoded['text']) ? $weekDecoded['text'] : '';
				if ($text !== '' && stripos($text, $roundNames[$round]) !== false) {
					$weekRef = $item['$ref'];
					break;
				}
			}
			if (!$weekRef) {
				$index = $round - 1;
				if (isset($weeksDecoded['items'][$index]['$ref'])) {
					$weekRef = $weeksDecoded['items'][$index]['$ref'];
				}
			}
			if ($weekRef) {
				$eventsUrl = rtrim($weekRef, '/') . "/events";
				$eventsJson = @file_get_contents($eventsUrl);
				if ($eventsJson) {
					$eventsDecoded = json_decode($eventsJson, true);
					if (!empty($eventsDecoded['items']) && is_array($eventsDecoded['items'])) {
						foreach ($eventsDecoded['items'] as $eventItem) {
							if (isset($eventItem['$ref'])) {
								$eventJson = @file_get_contents($eventItem['$ref']);
								if ($eventJson) {
									$eventDecoded = json_decode($eventJson, true);
									if (!empty($eventDecoded['competitions'][0])) {
										if ($usedUrl === null) {
											$usedUrl = $eventItem['$ref'];
										}
										$events[] = $eventDecoded;
									}
								}
							}
						}
					}
				}
			}
		}
	}

	// Fallback to scoreboard endpoint
	if (empty($events)) {
		$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=" . $year . "&seasontype=3&week=" . $round;
		$json = @file_get_contents($url);
		if ($json) {
			$decoded = json_decode($json, true);
			$events = isset($decoded['events']) ? $decoded['events'] : array();
			if (!empty($events) && $usedUrl === null) {
				$usedUrl = $url;
			}
		}
	}
	if (empty($events)) {
		$fallbackUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=3&week=" . $round;
		$json = @file_get_contents($fallbackUrl);
		if ($json) {
			$decoded = json_decode($json, true);
			$events = isset($decoded['events']) ? $decoded['events'] : array();
			if (!empty($events) && $usedUrl === null) {
				$usedUrl = $fallbackUrl;
			}
		}
	}
	// Super Bowl sometimes only available via early-February date range (calendar year following season)
	if (empty($events) && $round === 4) {
		$sbYear = $year + 1;
		$dateRange = $sbYear . "0201-" . $sbYear . "0215";
		$rangeUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=3&dates=" . $dateRange;
		$rangeJson = @file_get_contents($rangeUrl);
		if ($rangeJson) {
			$decoded = json_decode($rangeJson, true);
			$events = isset($decoded['events']) ? $decoded['events'] : array();
			if (!empty($events) && $usedUrl === null) {
				$usedUrl = $rangeUrl;
			}
		}
		if (empty($events)) {
			$singleDateUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?seasontype=3&dates=" . $sbYear . "0208";
			$singleJson = @file_get_contents($singleDateUrl);
			if ($singleJson) {
				$decoded = json_decode($singleJson, true);
				$events = isset($decoded['events']) ? $decoded['events'] : array();
				if (!empty($events) && $usedUrl === null) {
					$usedUrl = $singleDateUrl;
				}
			}
		}
	}
	return $events;
}

$usedUrl = null;
$events = fetchPlayoffEvents($year, $round, $usedUrl);
$yearUsed = $year;
if (empty($events)) {
	$usedUrl = null;
	$events = fetchPlayoffEvents($year + 1, $round, $usedUrl);
	if (!empty($events)) {
		$yearUsed = $year + 1;
	}
}
if (empty($events)) {
	$usedUrl = null;
	$events = fetchPlayoffEvents($year - 1, $round, $usedUrl);
	if (!empty($events)) {
		$yearUsed = $year - 1;
	}
}

// Fallback to scoreboard endpoint
if (empty($events)) {
	die('No playoff games returned from ESPN for round ' . $round . '. Tried seasons ' . $year . ', ' . ($year + 1) . ', ' . ($year - 1) . '.');
}

$games = array();
foreach ($events as $event) {
	if (empty($event['competitions'][0])) {
		continue;
	}
	$competition = $event['competitions'][0];
	$dateStr = isset($competition['date']) ? $competition['date'] : (isset($event['date']) ? $event['date'] : null);
	if (!$dateStr) {
		continue;
	}
	$date = new DateTime($dateStr);
	$date->setTimezone(new DateTimeZone('America/New_York'));
	$gameTimeEastern = $date->format('Y-m-d H:i:00');

	$home_team = null;
	$away_team = null;
	$home_score = 0;
	$away_score = 0;
	if (!empty($competition['competitors'])) {
		foreach ($competition['competitors'] as $team) {
			$abbr = isset($team['team']['abbreviation']) ? $team['team']['abbreviation'] : null;
			$score = isset($team['score']) ? (int)$team['score'] : 0;
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

	if ($home_team === 'AFC') {
		ensurePlaceholderTeam('AFC', 'AFC Champion');
	}
	if ($home_team === 'NFC') {
		ensurePlaceholderTeam('NFC', 'NFC Champion');
	}
	if ($away_team === 'AFC') {
		ensurePlaceholderTeam('AFC', 'AFC Champion');
	}
	if ($away_team === 'NFC') {
		ensurePlaceholderTeam('NFC', 'NFC Champion');
	}

	$games[] = array(
		'roundNum' => $round,
		'gameTimeEastern' => $gameTimeEastern,
		'homeID' => $home_team,
		'homeScore' => $home_score,
		'visitorID' => $away_team,
		'visitorScore' => $away_score
	);
}

if ($apply) {
	$mysqli->query("delete from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$round . " and is_bye = 0") or die($mysqli->error);
	foreach ($games as $game) {
		$sql = "insert into " . DB_PREFIX . "playoff_schedule (roundNum, gameTimeEastern, homeID, homeScore, visitorID, visitorScore, is_bye) values (" .
			(int)$game['roundNum'] . ", '" . $mysqli->real_escape_string($game['gameTimeEastern']) . "', '" . $mysqli->real_escape_string($game['homeID']) . "', " .
			(int)$game['homeScore'] . ", '" . $mysqli->real_escape_string($game['visitorID']) . "', " . (int)$game['visitorScore'] . ", 0)";
		$mysqli->query($sql) or die($mysqli->error);
	}
	$inserted = array();
	$listQuery = $mysqli->query("select roundNum, gameTimeEastern, homeID, visitorID, homeScore, visitorScore from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$round . " and is_bye = 0 order by gameTimeEastern, playoffGameID");
	if ($listQuery) {
		while ($row = $listQuery->fetch_assoc()) {
			$inserted[] = $row;
		}
		$listQuery->free();
	}
	if (!empty($returnUrl)) {
		if ($debug) {
			$_SESSION['playoff_debug'] = array(
				'round' => (int)$round,
				'yearRequested' => (int)$year,
				'yearUsed' => (int)$yearUsed,
				'used_url' => $usedUrl ? $usedUrl : '',
				'games' => $games,
				'eventsCount' => count($events)
			);
		}
		$sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
		$summary = $sep . "updated_round=" . (int)$round . "&updated_count=" . count($inserted);
		if ($debug) {
			$summary .= "&debug=1";
		}
		header('Location: ' . $returnUrl . $summary);
		exit;
	}
	echo "Imported playoff schedule for round " . $round . " (" . count($games) . " games).";
	echo "<pre>" . htmlspecialchars(json_encode($inserted, JSON_PRETTY_PRINT)) . "</pre>";
	exit;
}

header('Content-Type: application/json; charset=utf-8');
if ($debug) {
	echo json_encode(array(
		'round' => $round,
		'year' => $year,
		'used_url' => $usedUrl ? $usedUrl : '',
		'games' => $games,
		'events' => $events
	));
} else {
	echo json_encode($games);
}
