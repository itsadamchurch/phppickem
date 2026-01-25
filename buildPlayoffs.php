<?php
require('includes/application_top.php');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)SEASON_YEAR;
$apply = (isset($_GET['apply']) && $_GET['apply'] === '1');
$includeByes = !isset($_GET['bye']) || $_GET['bye'] !== '0';

$rounds = array(
	1 => 'Wild Card',
	2 => 'Divisional',
	3 => 'Conference Championships',
	4 => 'Super Bowl'
);

$teamMap = array(
	'LV' => 'OAK',
	'LAC' => 'SD',
	'LAR' => 'LA',
	'WSH' => 'WAS'
);

$schedule = array();

foreach (array_keys($rounds) as $round) {
	$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=".$year."&seasontype=3&week=".$round;
	if ($json = @file_get_contents($url)) {
		$jsonDecoded = json_decode($json, true);
		$events = isset($jsonDecoded['events']) ? $jsonDecoded['events'] : array();
	} else {
		die('Error getting playoff schedule from espn.com.');
	}

	foreach ($events as $event) {
		if (empty($event['competitions'][0]['competitors'])) {
			continue;
		}

		$date = new DateTime($event['date']);
		$date->setTimezone(new DateTimeZone('America/New_York'));
		$gameTimeEastern = $date->format('Y-m-d H:i:00');

		$home_team = null;
		$away_team = null;
		$home_score = 0;
		$away_score = 0;
		foreach ($event['competitions'][0]['competitors'] as $team) {
			$abbr = $team['team']['abbreviation'];
			if (isset($teamMap[$abbr])) {
				$abbr = $teamMap[$abbr];
			}
			if ($team['homeAway'] === 'home') {
				$home_team = $abbr;
				$home_score = (int)$team['score'];
			} else {
				$away_team = $abbr;
				$away_score = (int)$team['score'];
			}
		}

		if (!$home_team || !$away_team) {
			continue;
		}

		$schedule[] = array(
			'round_num' => $round,
			'game_time_eastern' => $gameTimeEastern,
			'home_id' => $home_team,
			'visitor_id' => $away_team,
			'home_score' => $home_score,
			'visitor_score' => $away_score,
			'is_bye' => 0
		);
	}
}

if ($includeByes) {
	// Placeholder byes for Wild Card round
	$schedule[] = array(
		'round_num' => 1,
		'game_time_eastern' => null,
		'home_id' => 'BYE',
		'visitor_id' => 'BYE',
		'home_score' => 0,
		'visitor_score' => 0,
		'is_bye' => 1
	);
	$schedule[] = array(
		'round_num' => 1,
		'game_time_eastern' => null,
		'home_id' => 'BYE',
		'visitor_id' => 'BYE',
		'home_score' => 0,
		'visitor_score' => 0,
		'is_bye' => 1
	);
}

if ($apply) {
	$mysqli->query("create table if not exists " . DB_PREFIX . "playoff_schedule (
		playoffGameID int(11) not null auto_increment,
		roundNum int(11) not null,
		gameTimeEastern datetime null,
		homeID varchar(10) not null,
		homeScore int(11) default null,
		visitorID varchar(10) not null,
		visitorScore int(11) default null,
		overtime tinyint(1) not null default '0',
		is_bye tinyint(1) not null default '0',
		primary key (playoffGameID),
		key PlayoffGameID (playoffGameID)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC") or die($mysqli->error);

	$mysqli->query("create table if not exists " . DB_PREFIX . "playoff_picks (
		pickID int(11) not null auto_increment,
		userID int(11) not null,
		gameID int(11) not null,
		pickTeamID varchar(10) not null,
		points int(11) default 0,
		primary key (pickID),
		key UserID (userID),
		key GameID (gameID)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC") or die($mysqli->error);

	$mysqli->query("truncate table " . DB_PREFIX . "playoff_schedule") or die($mysqli->error);

	foreach ($schedule as $row) {
		$gameTime = $row['game_time_eastern'] ? "'" . $row['game_time_eastern'] . "'" : "NULL";
		$sql = "insert into " . DB_PREFIX . "playoff_schedule (roundNum, gameTimeEastern, homeID, homeScore, visitorID, visitorScore, overtime, is_bye) values (" .
			(int)$row['round_num'] . ", " . $gameTime . ", '" . $row['home_id'] . "', " . (int)$row['home_score'] . ", '" . $row['visitor_id'] . "', " . (int)$row['visitor_score'] . ", 0, " . (int)$row['is_bye'] . ")";
		$mysqli->query($sql) or die($mysqli->error);
	}

	echo "Imported playoff schedule for " . $year . " (" . count($schedule) . " entries).";
	exit;
}

//output to csv
$fp = fopen('php://output', 'w');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=nfl_playoffs_'.$year.'.csv');
fputcsv($fp, array_keys($schedule[0]));
foreach ($schedule as $row) {
	fputcsv($fp, $row);
}
fclose($fp);
