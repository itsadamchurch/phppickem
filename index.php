<?php
require_once('includes/application_top.php');
require('includes/classes/team.php');

$activeTab = 'home';

include('includes/header.php');

if ($user->userName == 'admin') {
?>
	<img src="images/art_holst_nfl.jpg" width="192" height="295" alt="ref" style="float: right; padding-left: 10px;" />
	<h1>Welcome, Admin!</h1>
	<p><b>If you feel that the work I've done has value to you,</b> I would greatly appreciate a paypal donation (click button below).  I have spent many hours working on this project, and I will continue its development as I find the time.  Again, I am very grateful for any and all contributions.</p>
<?php
	include('includes/donate_button.inc.php');
} else {
	if ($weekExpired) {
		//current week is expired, show message
		$nowEastern = new DateTime("now", new DateTimeZone("America/New_York"));
		$playoffOpen = false;
		$currentPlayoffRound = 0;
		$playoffRoundNames = array(
			1 => 'Wild Card',
			2 => 'Divisional',
			3 => 'Conference Championships',
			4 => 'Super Bowl'
		);
		$sql = "select roundNum, min(gameTimeEastern) as firstGameTime, max(gameTimeEastern) as lastGameTime from " . DB_PREFIX . "playoff_schedule where is_bye = 0 group by roundNum order by roundNum asc";
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			if (!empty($row['firstGameTime'])) {
				$firstGameTime = new DateTime($row['firstGameTime'], new DateTimeZone("America/New_York"));
				$lastGameTime = !empty($row['lastGameTime']) ? new DateTime($row['lastGameTime'], new DateTimeZone("America/New_York")) : $firstGameTime;
				if ($firstGameTime <= $nowEastern) {
					$currentPlayoffRound = (int)$row['roundNum'];
					if ($nowEastern <= $lastGameTime) {
						$playoffOpen = true;
					}
				} else {
					break;
				}
			}
		}
		$query->free;
		if (!$playoffOpen) {
			if ($currentPlayoffRound > 0) {
				$roundLabel = isset($playoffRoundNames[$currentPlayoffRound]) ? $playoffRoundNames[$currentPlayoffRound] : ('Round ' . $currentPlayoffRound);
				echo '	<div class="bg-warning">The current week is locked.  <a href="results.php?type=playoffs&round=' . $currentPlayoffRound . '">Check ' . $roundLabel . ' Results &gt;&gt;</a></div>' . "\n";
			} else {
				echo '	<div class="bg-warning">The current week is locked.  <a href="results.php">Check the Results &gt;&gt;</a></div>' . "\n";
			}
		}
	} else {
		//if all picks not submitted yet for current week
		$picks = $statsService->getUserPicks($currentWeek, $user->userID);
		$gameTotal = $statsService->getGameTotal($currentWeek);
		if (sizeof($picks) < $gameTotal) {
			echo '	<div class="bg-warning">You have NOT yet made all of your picks for week ' . $currentWeek . '.  <a href="entry_form.php">Make Your Picks &gt;&gt;</a></div>' . "\n";
		}
	}
	//include('includes/column_right.php');
?>
	<div class="row">
		<div class="col-md-4 col-xs-12 col-right">
<?php
include('includes/column_right.php');
?>
		</div>
		<div id="content" class="col-md-8 col-xs-12">
	<h3>Your Picks At A Glance:</h3>
	<?php
	$lastCompletedWeek = $statsService->getLastCompletedWeek();
	$glanceItems = array();
	$nowEastern = new DateTime("now", new DateTimeZone("America/New_York"));

	// regular season weeks
	$sql = "select s.weekNum, count(s.gameID) as gamesTotal,";
	$sql .= " min(s.gameTimeEastern) as firstGameTime,";
	$sql .= " max(s.gameTimeEastern) as lastGameTime,";
	$sql .= " (DATE_ADD(NOW(), INTERVAL " . SERVER_TIMEZONE_OFFSET . " HOUR) > max(s.gameTimeEastern)) as expired ";
	$sql .= "from " . DB_PREFIX . "schedule s ";
	$sql .= "group by s.weekNum ";
	$sql .= "order by s.weekNum;";
	$query = $mysqli->query($sql);
	while ($row = $query->fetch_assoc()) {
		if ((int)$row['weekNum'] > (int)$currentWeek) {
			continue;
		}
		$glanceItems[] = array(
			'type' => 'week',
			'key' => (int)$row['weekNum'],
			'label' => 'Week ' . $row['weekNum'],
			'displayWeek' => (int)$row['weekNum'],
			'gamesTotal' => (int)$row['gamesTotal'],
			'firstGameTime' => $row['firstGameTime'],
			'lastGameTime' => $row['lastGameTime'],
			'expired' => (int)$row['expired']
		);
	}
	$query->free;

	// playoffs if schedule exists
	$playoffRoundNames = array(
		1 => 'Wild Card',
		2 => 'Divisional',
		3 => 'Conference Championships',
		4 => 'Super Bowl'
	);
	$sql = "select roundNum, count(playoffGameID) as gamesTotal, min(gameTimeEastern) as firstGameTime, max(gameTimeEastern) as lastGameTime ";
	$sql .= "from " . DB_PREFIX . "playoff_schedule where is_bye = 0 group by roundNum;";
	$query = $mysqli->query($sql);
	while ($row = $query->fetch_assoc()) {
		$roundNum = (int)$row['roundNum'];
		if (empty($row['firstGameTime'])) {
			continue;
		}
		$firstGameTime = new DateTime($row['firstGameTime'], new DateTimeZone("America/New_York"));
		if ($firstGameTime > $nowEastern) {
			continue;
		}
		$label = isset($playoffRoundNames[$roundNum]) ? $playoffRoundNames[$roundNum] : ('Round ' . $roundNum);
		$lastGameTime = !empty($row['lastGameTime']) ? new DateTime($row['lastGameTime'], new DateTimeZone("America/New_York")) : null;
		$expired = 0;
		if (!empty($lastGameTime)) {
			$expired = ($nowEastern > $lastGameTime) ? 1 : 0;
		}
		$glanceItems[] = array(
			'type' => 'playoffs',
			'key' => $roundNum,
			'label' => 'Week ' . (18 + $roundNum) . ' - ' . $label,
			'displayWeek' => 18 + $roundNum,
			'gamesTotal' => (int)$row['gamesTotal'],
			'firstGameTime' => $row['firstGameTime'],
			'lastGameTime' => $row['lastGameTime'],
			'expired' => $expired
		);
	}
	$query->free;

	$hasPlayoffs = false;
	foreach ($glanceItems as $item) {
		if ($item['type'] === 'playoffs') {
			$hasPlayoffs = true;
			break;
		}
	}

	// sort with current week first (regular season only), then most recent
	usort($glanceItems, function($a, $b) use ($currentWeek, $hasPlayoffs) {
		if (!$hasPlayoffs) {
			if ($a['type'] === 'week' && $a['key'] == $currentWeek) return -1;
			if ($b['type'] === 'week' && $b['key'] == $currentWeek) return 1;
		}
		$aw = isset($a['displayWeek']) ? $a['displayWeek'] : 0;
		$bw = isset($b['displayWeek']) ? $b['displayWeek'] : 0;
		if ($aw != $bw) return ($aw < $bw) ? 1 : -1;
		$at = !empty($a['firstGameTime']) ? strtotime($a['firstGameTime']) : 0;
		$bt = !empty($b['firstGameTime']) ? strtotime($b['firstGameTime']) : 0;
		if ($at == $bt) return 0;
		return ($at < $bt) ? 1 : -1;
	});

	$hasOpenPicks = false;
	$hasOpenPlayoffs = false;
	foreach ($glanceItems as $item) {
		$openUntil = '';
		if (!empty($item['lastGameTime'])) {
			$openUntil = $item['lastGameTime'];
		} elseif (!empty($item['firstGameTime'])) {
			$openUntil = $item['firstGameTime'];
		}
		if (!empty($openUntil) && strtotime($openUntil) > $nowEastern->getTimestamp()) {
			$hasOpenPicks = true;
			if ($item['type'] === 'playoffs') {
				$hasOpenPlayoffs = true;
			}
		}
	}

	foreach ($glanceItems as $item) {
		echo '		<div class="row-week">' . "\n";
		echo '			<p><b>' . $item['label'] . '</b><br />' . "\n";
		if (!empty($item['firstGameTime'])) {
			echo '			First game: ' . date('n/j g:i a', strtotime($item['firstGameTime'])) . '<br />' . "\n";
		}
		echo '</p>' . "\n";

		if ($item['type'] === 'week') {
			if ($item['expired']) {
				if ($lastCompletedWeek >= (int)$item['key']) {
					$weekTotal = $statsService->getGameTotal($item['key']);
					$userScore = $statsService->getUserScore($item['key'], $user->userID);
					echo '			<div class="bg-info"><b>Score: ' . $userScore . '/' . $weekTotal . ' (' . number_format(($userScore / $weekTotal) * 100, 2) . '%)</b><br /><a href="results.php?week='.$item['key'].'">See Results &raquo;</a></div>' . "\n";
				} else {
					echo '			<div class="bg-info">Week is closed,</b> but scores have not yet been entered.<br /><a href="results.php?week='.$item['key'].'">See Results &raquo;</a></div>' . "\n";
				}
			} else {
				$picks = $statsService->getUserPicks($item['key'], $user->userID);
				if (sizeof($picks) < (int)$item['gamesTotal']) {
					$tmpStyle = '';
					if ((int)$currentWeek == (int)$item['key']) {
						$tmpStyle = ' style="color: red;"';
					}
					echo '			<div class="bg-warning"'.$tmpStyle.'><b>Missing ' . ((int)$item['gamesTotal'] - sizeof($picks)) . ' / ' . $item['gamesTotal'] . ' picks.</b><br /><a href="entry_form.php?week=' . $item['key'] . '">Enter now &raquo;</a></div>' . "\n";
				} else {
					echo '			<div class="bg-info" style="color: green;"><b>All picks entered.</b><br /><a href="entry_form.php?week=' . $item['key'] . '">Change your picks &raquo;</a></div>' . "\n";
				}
			}
		} else {
			// playoffs
			$sql = "select p.gameID, p.pickTeamID, s.homeID, s.visitorID, s.homeScore, s.visitorScore ";
			$sql .= "from " . DB_PREFIX . "playoff_picks p ";
			$sql .= "inner join " . DB_PREFIX . "playoff_schedule s on p.gameID = s.playoffGameID ";
			$sql .= "where p.userID = " . $user->userID . " and s.roundNum = " . (int)$item['key'] . " and s.is_bye = 0";
			$query = $mysqli->query($sql);
			$userScore = 0;
			$pickCount = 0;
			while ($row = $query->fetch_assoc()) {
				$pickCount++;
				$winnerID = '';
				$homeScore = (int)$row['homeScore'];
				$visitorScore = (int)$row['visitorScore'];
				if ($homeScore + $visitorScore > 0) {
					if ($homeScore > $visitorScore) $winnerID = $row['homeID'];
					if ($visitorScore > $homeScore) $winnerID = $row['visitorID'];
				}
				if (!empty($winnerID) && $row['pickTeamID'] == $winnerID) {
					$userScore += 1;
				}
			}
			$query->free;
			if ($item['expired']) {
				echo '			<div class="bg-info"><b>Score: ' . $userScore . '/' . $item['gamesTotal'] . '</b><br /><a href="results.php?type=playoffs&round=' . $item['key'] . '">See Results &raquo;</a></div>' . "\n";
			} else {
				if ($pickCount < (int)$item['gamesTotal']) {
					echo '			<div class="bg-warning"><b>Missing ' . ((int)$item['gamesTotal'] - $pickCount) . ' / ' . $item['gamesTotal'] . ' picks.</b><br /><a href="entry_form.php?type=playoffs&round=' . $item['key'] . '">Enter now &raquo;</a></div>' . "\n";
				} else {
					echo '			<div class="bg-info" style="color: green;"><b>All picks entered.</b><br /><a href="entry_form.php?type=playoffs&round=' . $item['key'] . '">Change your picks &raquo;</a></div>' . "\n";
				}
			}
		}
		echo '		</div>'."\n";
	}
	?>
		</div><!-- end col -->
	</div><!-- end entry-form -->
<?php
	include('includes/comments.php');
}

require('includes/footer.php');
