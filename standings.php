<?php
require('includes/application_top.php');

$stats = $statsService->calculateStats();
$weekStats = $stats['weekStats'];
$playerTotals = $stats['playerTotals'];
$possibleScoreTotal = $stats['possibleScoreTotal'];

foreach ($playerTotals as $playerID => $stats) {
	$playerTotals[$playerID]['userID'] = $playerID;
}

$playoffTotals = array();
$sql = "select p.userID, p.gameID, p.pickTeamID, s.homeID, s.visitorID, s.homeScore, s.visitorScore ";
$sql .= "from " . DB_PREFIX . "playoff_picks p ";
$sql .= "inner join " . DB_PREFIX . "playoff_schedule s on p.gameID = s.playoffGameID ";
$sql .= "where s.is_bye = 0 ";
$query = $mysqli->query($sql);
while ($row = $query->fetch_assoc()) {
	$winnerID = '';
	$homeScore = (int)$row['homeScore'];
	$visitorScore = (int)$row['visitorScore'];
	if ($homeScore + $visitorScore > 0) {
		if ($homeScore > $visitorScore) $winnerID = $row['homeID'];
		if ($visitorScore > $homeScore) $winnerID = $row['visitorID'];
	}
	if (!empty($winnerID) && $row['pickTeamID'] == $winnerID) {
		$playoffTotals[$row['userID']] += 1;
	} else {
		$playoffTotals[$row['userID']] += 0;
	}
}
$query->free;

// playoff stats by round
$playoffRoundNames = array(
	1 => 'Wild Card',
	2 => 'Divisional',
	3 => 'Conference Championships',
	4 => 'Super Bowl'
);
$playoffRoundOrder = array_keys($playoffRoundNames);
$playoffWeekStats = array();
$sql = "select s.roundNum, p.userID, p.pickTeamID, s.homeID, s.visitorID, s.homeScore, s.visitorScore ";
$sql .= "from " . DB_PREFIX . "playoff_picks p ";
$sql .= "inner join " . DB_PREFIX . "playoff_schedule s on p.gameID = s.playoffGameID ";
$sql .= "where s.is_bye = 0 ";
$query = $mysqli->query($sql);
while ($row = $query->fetch_assoc()) {
	$winnerID = '';
	$homeScore = (int)$row['homeScore'];
	$visitorScore = (int)$row['visitorScore'];
	if ($homeScore + $visitorScore > 0) {
		if ($homeScore > $visitorScore) $winnerID = $row['homeID'];
		if ($visitorScore > $homeScore) $winnerID = $row['visitorID'];
	}
	if (!isset($playoffWeekStats[$row['roundNum']])) {
		$playoffWeekStats[$row['roundNum']] = array('winners' => array(), 'highestScore' => 0, 'possibleScore' => 0);
	}
	$playoffWeekStats[$row['roundNum']]['possibleScore'] += 1;
	if (!empty($winnerID) && $row['pickTeamID'] == $winnerID) {
		$playoffWeekStats[$row['roundNum']]['scores'][$row['userID']] += 1;
	} else {
		$playoffWeekStats[$row['roundNum']]['scores'][$row['userID']] += 0;
	}
}
$query->free;
foreach ($playoffWeekStats as $roundNum => $stats) {
	if (!empty($stats['scores'])) {
		arsort($stats['scores']);
		$highestScore = reset($stats['scores']);
		$playoffWeekStats[$roundNum]['highestScore'] = $highestScore;
		foreach ($stats['scores'] as $userID => $score) {
			if ($score < $highestScore) break;
			$playoffWeekStats[$roundNum]['winners'][] = $userID;
			if (isset($playerTotals[$userID]['wins'])) {
				$playerTotals[$userID]['wins'] += 0;
			}
		}
	}
}

include('includes/header.php');
?>
<h1>Standings</h1>
<h2>Weekly Stats</h2>
<div class="table-responsive">
<table class="table table-striped">
	<tr><th align="left">Week</th><th align="left">Winner(s)</th><th>Score</th></tr>
<?php
if (isset($weekStats)) {
	$i = 0;
	foreach($weekStats as $week => $stats) {
		$winners = '';
		if (is_array($stats['winners'])) {
			foreach($stats['winners'] as $winner => $winnerID) {
				$tmpUser = $login->get_user_by_id($winnerID);
				switch (USER_NAMES_DISPLAY) {
					case 1:
						$winners .= ((strlen($winners) > 0) ? ', ' : '') . trim($tmpUser->firstname . ' ' . $tmpUser->lastname);
						break;
					case 2:
						$winners .= ((strlen($winners) > 0) ? ', ' : '') . $tmpUser->userName;
						break;
					default: //3
						$winners .= ((strlen($winners) > 0) ? ', ' : '') . '<abbr title="' . trim($tmpUser->firstname . ' ' . $tmpUser->lastname) . '">' . $tmpUser->userName . '</abbr>';
						break;
				}
			}
		}
		$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
		echo '	<tr' . $rowclass . '><td>' . $week . '</td><td>' . $winners . '</td><td align="center">' . $stats['highestScore'] . '/' . $stats['possibleScore'] . '</td></tr>';
		$i++;
	}
} else {
	echo '	<tr><td colspan="3">No weeks have been completed yet.</td></tr>' . "\n";
}
?>
</table>
</div>

<?php
$playoffStatsRows = array();
foreach ($playoffRoundNames as $roundNum => $label) {
	if (!isset($playoffWeekStats[$roundNum])) {
		$playoffWeekStats[$roundNum] = array('winners' => array(), 'highestScore' => 0, 'possibleScore' => 0);
	}
	$playoffStatsRows[$roundNum] = $playoffWeekStats[$roundNum];
}
?>
<?php if (!empty($playoffStatsRows)) { ?>
<h2>Playoff Weekly Stats</h2>
<div class="table-responsive">
<table class="table table-striped">
	<tr><th align="left">Round</th><th align="left">Winner(s)</th><th>Score</th></tr>
<?php
	$i = 0;
	foreach($playoffStatsRows as $round => $stats) {
		$winners = '';
		if (!empty($stats['winners'])) {
			foreach($stats['winners'] as $winnerID) {
				$tmpUser = $login->get_user_by_id($winnerID);
				switch (USER_NAMES_DISPLAY) {
					case 1:
						$winners .= ((strlen($winners) > 0) ? ', ' : '') . trim($tmpUser->firstname . ' ' . $tmpUser->lastname);
						break;
					case 2:
						$winners .= ((strlen($winners) > 0) ? ', ' : '') . $tmpUser->userName;
						break;
					default: //3
						$winners .= ((strlen($winners) > 0) ? ', ' : '') . '<abbr title="' . trim($tmpUser->firstname . ' ' . $tmpUser->lastname) . '">' . $tmpUser->userName . '</abbr>';
						break;
				}
			}
		}
		$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
		$roundLabel = isset($playoffRoundNames[$round]) ? $playoffRoundNames[$round] : $round;
		echo '	<tr' . $rowclass . '><td>' . $roundLabel . '</td><td>' . $winners . '</td><td align="center">' . $stats['highestScore'] . '/' . $stats['possibleScore'] . '</td></tr>';
		$i++;
	}
?>
</table>
</div>
<?php } ?>

<h2>Playoff Stats</h2>
<div class="row">
	<div class="col-md-4 col-xs-12">
		<b>By Wins</b><br />
		<div class="table-responsive">
			<table class="table table-striped">
				<tr><th align="left">Player</th><th align="left">Wins</th></tr>
			<?php
			if (!empty($playoffTotals)) {
				arsort($playoffTotals);
				$i = 0;
				foreach($playoffTotals as $userID => $wins) {
					$tmpUser = $login->get_user_by_id($userID);
					$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
					switch (USER_NAMES_DISPLAY) {
						case 1:
							$name = trim($tmpUser->firstname . ' ' . $tmpUser->lastname);
							break;
						case 2:
							$name = trim($tmpUser->userName);
							break;
						default: //3
							$name = '<abbr title="' . trim($tmpUser->firstname . ' ' . $tmpUser->lastname) . '">' . trim($tmpUser->userName) . '</abbr>';
							break;
					}
					echo '	<tr' . $rowclass . '><td class="tiny">' . $name . '</td><td class="tiny" align="center">' . $wins . '</td></tr>';
					$i++;
				}
			} else {
				echo '	<tr><td colspan="2">No playoff picks yet.</td></tr>' . "\n";
			}
			?>
			</table>
		</div>
	</div>
</div>

<h2>User Stats</h2>
<div class="row">
	<div class="col-md-4 col-xs-12">
		<b>By Name</b><br />
		<div class="table-responsive">
			<table class="table table-striped">
				<tr><th align="left">Player</th><th align="left">Wins</th><th>Pick Ratio</th></tr>
			<?php
			if (isset($playerTotals)) {
				//arsort($playerTotals);
				$i = 0;
				foreach($playerTotals as $playerID => $stats) {
					$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
					$playoffWins = isset($playoffTotals[$playerID]) ? $playoffTotals[$playerID] : 0;
					$totalWins = $stats['wins'] + $playoffWins;
					$pickRatio = $stats['score'] . '/' . $possibleScoreTotal;
					$pickPercentage = number_format((($stats['score'] / $possibleScoreTotal) * 100), 2) . '%';
					switch (USER_NAMES_DISPLAY) {
						case 1:
							echo '	<tr' . $rowclass . '><td class="tiny">' . $stats['name'] . '</td><td class="tiny" align="center">' . $totalWins . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
						case 2:
							echo '	<tr' . $rowclass . '><td class="tiny">' . $stats['userName'] . '</td><td class="tiny" align="center">' . $totalWins . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
						default: //3
							echo '	<tr' . $rowclass . '><td class="tiny"><abbr title="' . $stats['name'] . '">' . $stats['userName'] . '<abbr></td><td class="tiny" align="center">' . $totalWins . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
					}
					$i++;
				}
			} else {
				echo '	<tr><td colspan="3">No weeks have been completed yet.</td></tr>' . "\n";
			}
			?>
			</table>
		</div>
	</div>
	<div class="col-md-4 col-xs-12">
		<b>By Wins</b><br />
		<div class="table-responsive">
			<table class="table table-striped">
				<tr><th align="left">Player</th><th align="left">Wins</th><th>Pick Ratio</th></tr>
			<?php
			if (isset($playerTotals)) {
				// sort by total wins (regular + playoffs)
				$sortedTotals = $playerTotals;
				uasort($sortedTotals, function($a, $b) use ($playoffTotals) {
					$winsA = $a['wins'] + (isset($playoffTotals[$a['userID']]) ? $playoffTotals[$a['userID']] : 0);
					$winsB = $b['wins'] + (isset($playoffTotals[$b['userID']]) ? $playoffTotals[$b['userID']] : 0);
					if ($winsA == $winsB) return 0;
					return ($winsA < $winsB) ? 1 : -1;
				});
				$i = 0;
				foreach($sortedTotals as $playerID => $stats) {
					$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
					$playoffWins = isset($playoffTotals[$playerID]) ? $playoffTotals[$playerID] : 0;
					$totalWins = $stats['wins'] + $playoffWins;
					$pickRatio = $stats['score'] . '/' . $possibleScoreTotal;
					$pickPercentage = number_format((($stats['score'] / $possibleScoreTotal) * 100), 2) . '%';
					switch (USER_NAMES_DISPLAY) {
						case 1:
							echo '	<tr' . $rowclass . '><td class="tiny">' . $stats['name'] . '</td><td class="tiny" align="center">' . $totalWins . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
						case 2:
							echo '	<tr' . $rowclass . '><td class="tiny">' . $stats['userName'] . '</td><td class="tiny" align="center">' . $totalWins . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
						default: //3
							echo '	<tr' . $rowclass . '><td class="tiny"><abbr title="' . $stats['name'] . '">' . $stats['userName'] . '</abbr></td><td class="tiny" align="center">' . $totalWins . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
					}
					$i++;
				}
			} else {
				echo '	<tr><td colspan="3">No weeks have been completed yet.</td></tr>' . "\n";
			}
			?>
			</table>
		</div>
	</div>
	<div class="col-md-4 col-xs-12">
		<b>By Pick Ratio</b><br />
		<div class="table-responsive">
			<table class="table table-striped">
				<tr><th align="left">Player</th><th align="left">Wins</th><th>Pick Ratio</th></tr>
			<?php
			if (isset($playerTotals)) {
				$playerTotals = $statsService->sort2d($playerTotals, 'score', 'desc');
				$i = 0;
				foreach($playerTotals as $playerID => $stats) {
					$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
					$pickRatio = $stats['score'] . '/' . $possibleScoreTotal;
					$pickPercentage = number_format((($stats['score'] / $possibleScoreTotal) * 100), 2) . '%';
					switch (USER_NAMES_DISPLAY) {
						case 1:
							echo '	<tr' . $rowclass . '><td class="tiny">' . $stats['name'] . '</td><td class="tiny" align="center">' . $stats['wins'] . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
						case 2:
							echo '	<tr' . $rowclass . '><td class="tiny">' . $stats['userName'] . '</td><td class="tiny" align="center">' . $stats['wins'] . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
						default: //3
							echo '	<tr' . $rowclass . '><td class="tiny"><abbr title="' . $stats['name'] . '">' . $stats['userName'] . '</abbr></td><td class="tiny" align="center">' . $stats['wins'] . '</td><td class="tiny" align="center">' . $pickRatio . ' (' . $pickPercentage . ')</td></tr>';
							break;
					}
					$i++;
				}
			} else {
				echo '	<tr><td colspan="3">No weeks have been completed yet.</td></tr>' . "\n";
			}
			?>
			</table>
		</div>
	</div>
</div>

<?php
include('includes/comments.php');

include('includes/footer.php');
?>
