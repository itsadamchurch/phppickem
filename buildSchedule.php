<?php
require('includes/application_top.php');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)SEASON_YEAR;
$apply = (isset($_GET['apply']) && $_GET['apply'] === '1');

$weeks = 18;
$schedule = array();
$teamMap = array(
	'LV' => 'OAK',
	'LAC' => 'SD',
	'LAR' => 'LA',
	'WSH' => 'WAS'
);

for ($week = 1; $week <= $weeks; $week++) {
	$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=".$year."&seasontype=2&week=".$week;
	if ($json = @file_get_contents($url)) {
		$jsonDecoded = json_decode($json, true);
		$events = isset($jsonDecoded['events']) ? $jsonDecoded['events'] : array();
	} else {
		die('Error getting schedule from espn.com.');
	}

	//build scores array, to group teams and scores together in games
	foreach ($events as $event) {
		$gameID = $event['id'];

		//get game time (UTC) and set to eastern
		$date = new DateTime($event['date']);
		$date->setTimezone(new DateTimeZone('America/New_York'));
		$gameTimeEastern = $date->format('Y-m-d H:i:00');

		$home_team = null;
		$away_team = null;
		$home_score = 0;
		$away_score = 0;

		if (!empty($event['competitions'][0]['competitors'])) {
			foreach ($event['competitions'][0]['competitors'] as $team) {
				if ($team['homeAway'] == 'home') {
					$home_team = $team['team']['abbreviation'];
					$home_score = (int)$team['score'];
				} else {
					$away_team = $team['team']['abbreviation'];
					$away_score = (int)$team['score'];
				}
			}
		}

		if (!$home_team || !$away_team) {
			continue;
		}

		if (isset($teamMap[$home_team])) $home_team = $teamMap[$home_team];
		if (isset($teamMap[$away_team])) $away_team = $teamMap[$away_team];

		$schedule[] = array(
			'game_id' => $gameID,
			'season' => $year,
			'season_type' => 'REG',
			'week_num' => $week,
			'game_time_eastern' => $gameTimeEastern,
			'home_id' => $home_team,
			'visitor_id' => $away_team,
			'home_score' => $home_score,
			'visitor_score' => $away_score,
			'tv' => ''
		);
	}
}

if ($apply) {
	$mysqli->query("truncate table " . DB_PREFIX . "schedule") or die($mysqli->error);
	foreach ($schedule as $row) {
		$sql = "insert into " . DB_PREFIX . "schedule (weekNum, gameTimeEastern, homeID, homeScore, visitorID, visitorScore, overtime) values (" .
			(int)$row['week_num'] . ", '" . $row['game_time_eastern'] . "', '" . $row['home_id'] . "', 0, '" . $row['visitor_id'] . "', 0, 0)";
		$mysqli->query($sql) or die($mysqli->error);
	}
	echo "Imported schedule for " . $year . " regular season (" . count($schedule) . " games).";
	exit;
}

//output to csv
// create a file pointer connected to the output stream
$fp = fopen('php://output', 'w');

// output headers so that the file is downloaded rather than displayed
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=nfl_schedule_'.$year.'.csv');

// output the column headings
fputcsv($fp, array_keys($schedule[0]));

//output the data
foreach ($schedule as $row) {
	fputcsv($fp, $row);
}

fclose($fp);
