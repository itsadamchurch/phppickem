<?php
require('includes/application_top.php');

$activeTab = 'standings';
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
$query->free();

$playoffPossibleTotal = 0;
$playoffPossibleQuery = $mysqli->query("select count(*) as cnt from " . DB_PREFIX . "playoff_schedule where is_bye = 0 and homeScore is not null and visitorScore is not null and (homeScore + visitorScore) > 0");
if ($playoffPossibleQuery && $playoffPossibleQuery->num_rows > 0) {
	$row = $playoffPossibleQuery->fetch_assoc();
	$playoffPossibleTotal = (int)$row['cnt'];
}
if ($playoffPossibleQuery) {
	$playoffPossibleQuery->free();
}

include('includes/header.php');
?>
<h1>Standings</h1>
<h2>User Stats</h2>
<div class="table-responsive">
	<table class="table table-striped table-modern" id="user-stats-table">
	<thead>
		<tr>
			<th align="left" data-sort="string">Player</th>
			<th align="center" data-sort="number">Games Won</th>
			<th align="center" data-sort="number">Weekly Wins</th>
			<th align="center" data-sort="number">Pick %</th>
			<th align="center" data-sort="number">Games Played</th>
		</tr>
	</thead>
	<tbody>
	<?php
if (isset($playerTotals)) {
	$i = 0;
	$ranking = array();
	foreach ($playerTotals as $playerID => $stats) {
		$playoffWins = isset($playoffTotals[$playerID]) ? $playoffTotals[$playerID] : 0;
		$totalCorrect = $stats['score'] + $playoffWins;
		$ranking[$playerID] = array(
			'gamesWon' => $totalCorrect,
			'weeklyWins' => isset($stats['wins']) ? $stats['wins'] : 0
		);
	}
	uasort($ranking, function($a, $b) {
		if ($a['gamesWon'] === $b['gamesWon']) {
			if ($a['weeklyWins'] === $b['weeklyWins']) return 0;
			return ($a['weeklyWins'] < $b['weeklyWins']) ? 1 : -1;
		}
		return ($a['gamesWon'] < $b['gamesWon']) ? 1 : -1;
	});
	$rankByUser = array();
	$rank = 0;
	foreach ($ranking as $userId => $row) {
		$rank++;
		$rankByUser[$userId] = $rank;
	}
	foreach($playerTotals as $playerID => $stats) {
		$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
		$playoffWins = isset($playoffTotals[$playerID]) ? $playoffTotals[$playerID] : 0;
		$totalCorrect = $stats['score'] + $playoffWins;
		$totalPossible = $possibleScoreTotal + $playoffPossibleTotal;
		$pickPercentage = ($totalPossible > 0) ? number_format((($totalCorrect / $totalPossible) * 100), 2) . '%' : '0.00%';
		$rankLabel = isset($rankByUser[$playerID]) ? $rankByUser[$playerID] : '';
		switch (USER_NAMES_DISPLAY) {
			case 1:
				$name = $stats['name'];
				break;
			case 2:
				$name = $stats['userName'];
				break;
			default: //3
				$name = '<abbr title="' . $stats['name'] . '">' . $stats['userName'] . '</abbr>';
				break;
		}
		if ($rankLabel !== '') {
			$name = '<span class="rank-label">#' . $rankLabel . '</span> ' . $name;
		}
		echo '	<tr' . $rowclass . '>' .
				'<td class="tiny" data-value="' . htmlspecialchars($stats['userName']) . '">' . $name . '</td>' .
				'<td class="tiny" align="center" data-value="' . (int)$totalCorrect . '">' . (int)$totalCorrect . '</td>' .
				'<td class="tiny" align="center" data-value="' . (int)$stats['wins'] . '">' . (int)$stats['wins'] . '</td>' .
				'<td class="tiny" align="center" data-value="' . ($totalPossible > 0 ? ($totalCorrect / $totalPossible) : 0) . '">' . $pickPercentage . '</td>' .
				'<td class="tiny" align="center" data-value="' . (int)$totalPossible . '">' . (int)$totalPossible . '</td>' .
				'</tr>';
			$i++;
		}
	} else {
		echo '	<tr><td colspan="5">No weeks have been completed yet.</td></tr>' . "\n";
	}
	?>
	</tbody>
	</table>
</div>

<script type="text/javascript">
(function() {
	var table = document.getElementById('user-stats-table');
	if (!table) return;
	var headers = table.querySelectorAll('th[data-sort]');
	var tbody = table.tBodies[0];
	var sortState = {};

	function sortTable(index, type) {
		var rows = Array.prototype.slice.call(tbody.rows, 0);
		var dir = sortState[index] === 'asc' ? 'desc' : 'asc';
		sortState[index] = dir;
		rows.sort(function(a, b) {
			var aCell = a.cells[index];
			var bCell = b.cells[index];
			var aVal = aCell.getAttribute('data-value') || aCell.textContent.trim();
			var bVal = bCell.getAttribute('data-value') || bCell.textContent.trim();
			if (type === 'number') {
				aVal = parseFloat(aVal) || 0;
				bVal = parseFloat(bVal) || 0;
			}
			if (aVal < bVal) return dir === 'asc' ? -1 : 1;
			if (aVal > bVal) return dir === 'asc' ? 1 : -1;
			return 0;
		});
		rows.forEach(function(row) { tbody.appendChild(row); });
	}

	function sortInitial() {
		var rows = Array.prototype.slice.call(tbody.rows, 0);
		rows.sort(function(a, b) {
			var aGamesWon = parseFloat(a.cells[1].getAttribute('data-value') || a.cells[1].textContent.trim()) || 0;
			var bGamesWon = parseFloat(b.cells[1].getAttribute('data-value') || b.cells[1].textContent.trim()) || 0;
			if (aGamesWon !== bGamesWon) {
				return bGamesWon - aGamesWon;
			}
			var aWeeklyWins = parseFloat(a.cells[2].getAttribute('data-value') || a.cells[2].textContent.trim()) || 0;
			var bWeeklyWins = parseFloat(b.cells[2].getAttribute('data-value') || b.cells[2].textContent.trim()) || 0;
			if (aWeeklyWins !== bWeeklyWins) {
				return bWeeklyWins - aWeeklyWins;
			}
			return 0;
		});
		rows.forEach(function(row) { tbody.appendChild(row); });
	}

	Array.prototype.forEach.call(headers, function(th, idx) {
		th.style.cursor = 'pointer';
		th.addEventListener('click', function() {
			sortTable(idx, th.getAttribute('data-sort'));
		});
	});

	sortInitial();
})();
</script>

<h2>Weekly Stats</h2>
<div class="table-responsive">
<table class="table table-striped table-modern">
	<tr><th align="left">Week</th><th align="left">Winner(s)</th><th>Score</th></tr>
<?php
if (isset($weekStats)) {
	$sortedWeekStats = $weekStats;
	uasort($sortedWeekStats, function($a, $b) {
		$orderA = isset($a['sortOrder']) ? (int)$a['sortOrder'] : 0;
		$orderB = isset($b['sortOrder']) ? (int)$b['sortOrder'] : 0;
		if ($orderA == $orderB) return 0;
		return ($orderA < $orderB) ? 1 : -1;
	});
	$i = 0;
	foreach($sortedWeekStats as $week => $stats) {
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
		$label = isset($stats['label']) ? $stats['label'] : $week;
		echo '	<tr' . $rowclass . '><td>' . $label . '</td><td>' . $winners . '</td><td align="center">' . $stats['highestScore'] . '/' . $stats['possibleScore'] . '</td></tr>';
		$i++;
	}
} else {
	echo '	<tr><td colspan="3">No weeks have been completed yet.</td></tr>' . "\n";
}
?>
</table>
</div>


<?php
include('includes/comments.php');

include('includes/footer.php');
?>
