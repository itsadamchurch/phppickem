<?php
require_once('includes/application_top.php');
require('includes/classes/team.php');

$type = (!empty($_GET['type']) && $_GET['type'] === 'playoffs') || (!empty($_POST['type']) && $_POST['type'] === 'playoffs') ? 'playoffs' : 'regular';
$round = (int)$_GET['round'];

if ($_POST['action'] == 'Submit') {
	if ($type === 'playoffs') {
		$round = (int)$_POST['round'];
		$sql = "select * from " . DB_PREFIX . "playoff_schedule where roundNum = " . $round . " and is_bye = 0";
		$query = $mysqli->query($sql);
		$nowEastern = new DateTime("now", new DateTimeZone("America/New_York"));
		if ($query->num_rows > 0) {
			while ($row = $query->fetch_assoc()) {
				if (!empty($row['gameTimeEastern'])) {
					$gameTime = new DateTime($row['gameTimeEastern'], new DateTimeZone("America/New_York"));
					if ($nowEastern >= $gameTime) {
						continue;
					}
				}
				$sql = "delete from " . DB_PREFIX . "playoff_picks where userID = " . $user->userID . " and gameID = " . (int)$row['playoffGameID'];
				$mysqli->query($sql) or die('Error deleting playoff picks: ' . $mysqli->error);

				if (!empty($_POST['game' . $row['playoffGameID']])) {
					$sql = "insert into " . DB_PREFIX . "playoff_picks (userID, gameID, pickTeamID) values (" . $user->userID . ", " . (int)$row['playoffGameID'] . ", '" . $_POST['game' . $row['playoffGameID']] . "')";
					$mysqli->query($sql) or die('Error inserting playoff picks: ' . $mysqli->error);
				}
			}
		}
		$query->free();
		header('Location: results.php?type=playoffs&round=' . $round);
		exit;
	}

	$week = $_POST['week'];

	//update summary table
	$sql = "delete from " . DB_PREFIX . "picksummary where weekNum = " . $_POST['week'] . " and userID = " . $user->userID . ";";
	$mysqli->query($sql) or die('Error updating picks summary: ' . $mysqli->error);
	$sql = "insert into " . DB_PREFIX . "picksummary (weekNum, userID, showPicks) values (" . $_POST['week'] . ", " . $user->userID . ", " . (int)$_POST['showPicks'] . ");";
	$mysqli->query($sql) or die('Error updating picks summary: ' . $mysqli->error);

	//loop through non-expire weeks and update picks
	$sql = "select * from " . DB_PREFIX . "schedule where weekNum = " . $_POST['week'] . " and (DATE_ADD(NOW(), INTERVAL " . SERVER_TIMEZONE_OFFSET . " HOUR) < gameTimeEastern);";
	$query = $mysqli->query($sql);
	if ($query->num_rows > 0) {
		while ($row = $query->fetch_assoc()) {
			$sql = "delete from " . DB_PREFIX . "picks where userID = " . $user->userID . " and gameID = " . $row['gameID'];
			$mysqli->query($sql) or die('Error deleting picks: ' . $mysqli->error);

			if (!empty($_POST['game' . $row['gameID']])) {
				$sql = "insert into " . DB_PREFIX . "picks (userID, gameID, pickID) values (" . $user->userID . ", " . $row['gameID'] . ", '" . $_POST['game' . $row['gameID']] . "')";
				$mysqli->query($sql) or die('Error inserting picks: ' . $mysqli->error);
			}
		}
	}
	$query->free();
	header('Location: results.php?week=' . $_POST['week']);
	exit;
} else {
	if ($type === 'playoffs') {
		if (empty($round)) {
			$sql = "select roundNum, min(gameTimeEastern) as firstGameTime, max(gameTimeEastern) as lastGameTime ";
			$sql .= "from " . DB_PREFIX . "playoff_schedule where is_bye = 0 group by roundNum order by roundNum asc";
			$query = $mysqli->query($sql);
			$nowEastern = new DateTime("now", new DateTimeZone("America/New_York"));
			if ($query->num_rows > 0) {
				while ($row = $query->fetch_assoc()) {
					if (empty($row['firstGameTime'])) {
						continue;
					}
					$firstGameTime = new DateTime($row['firstGameTime'], new DateTimeZone("America/New_York"));
					$lastGameTime = !empty($row['lastGameTime']) ? new DateTime($row['lastGameTime'], new DateTimeZone("America/New_York")) : $firstGameTime;
					if ($nowEastern < $firstGameTime) {
						$round = (int)$row['roundNum'];
						break;
					}
					if ($nowEastern >= $firstGameTime && $nowEastern <= $lastGameTime) {
						$round = (int)$row['roundNum'];
						break;
					}
					$round = (int)$row['roundNum'];
				}
			}
			$query->free();
		}
	} else {
		$week = (int)$_GET['week'];
		if (empty($week)) {
			//get current week
			$week = (int)$statsService->getCurrentWeek();
		}
		$firstGameTime = $statsService->getFirstGameTime($week);
	}
}

include('includes/header.php');
?>
	<script type="text/javascript">
	function checkform() {
		//make sure all picks have a checked value
		var f = document.entryForm;
		var allChecked = true;
		var allR = document.getElementsByTagName('input');
		for (var i=0; i < allR.length; i++) {
			if(allR[i].type == 'radio') {
				if (!radioIsChecked(allR[i].name)) {
					allChecked = false;
				}
			}
	    }
	    if (!allChecked) {
			return confirm('One or more picks are missing for the current week.  Do you wish to submit anyway?');
		}
		return true;
	}
	function radioIsChecked(elmName) {
		var elements = document.getElementsByName(elmName);
		for (var i = 0; i < elements.length; i++) {
			if (elements[i].checked) {
				return true;
			}
		}
		return false;
	}
	function checkRadios() {
	  $('input[type=radio]').each(function(){
	   //alert($(this).attr('checked'));
	    var targetLabel = $('label[for="'+$(this).attr('id')+'"]');
	    console.log($(this).attr('id')+': '+$(this).is(':checked'));
	    if ($(this).is(':checked')) {
	      //console.log(targetLabel);
	     targetLabel.addClass('highlight');
	    } else {
	      targetLabel.removeClass('highlight');
	    }
	  });
	}
	$(function() {
		checkRadios();
		$('input[type=radio]').click(function(){
		  checkRadios();
		});
		$('label').click(function(){
		  checkRadios();
		});
	});
	</script>
	<style type="text/css">
	.team-logo.locked-pick-pending { border: 2px solid #f0ad4e; border-radius: 6px; }
	.team-logo.locked-pick-correct { border: 2px solid #5cb85c; border-radius: 6px; }
	.team-logo.locked-pick-wrong { border: 2px solid #d9534f; border-radius: 6px; }
	</style>
<?php
//display nav
$weekNav = '<div id="weekNav" class="row">';
if ($type === 'playoffs') {
	$weekNav .= '	<div class="navbar3 col-xs-12"><b>Go to round:</b> ';
	$playoffRoundNames = array(
		1 => 'Wild Card',
		2 => 'Divisional',
		3 => 'Conference Championships',
		4 => 'Super Bowl'
	);
	$sql = "select distinct roundNum from " . DB_PREFIX . "playoff_schedule order by roundNum;";
	$query = $mysqli->query($sql);
	$i = 0;
	while ($row = $query->fetch_assoc()) {
		if ($i > 0) $weekNav .= ' | ';
		$roundLabel = isset($playoffRoundNames[(int)$row['roundNum']]) ? $playoffRoundNames[(int)$row['roundNum']] : $row['roundNum'];
		if ($round !== (int)$row['roundNum']) {
			$weekNav .= '<a href="entry_form.php?type=playoffs&round=' . $row['roundNum'] . '">' . $roundLabel . '</a>';
		} else {
			$weekNav .= $roundLabel;
		}
		$i++;
	}
	$query->free();
	$weekNav .= '	</div>' . "\n";
} else {
	$weekNav .= '	<div class="navbar3 col-xs-12"><b>Go to week:</b> ';
	$sql = "select distinct weekNum from " . DB_PREFIX . "schedule order by weekNum;";
	$query = $mysqli->query($sql);
	$i = 0;
	if ($query->num_rows > 0) {
		while ($row = $query->fetch_assoc()) {
			if ($i > 0) $weekNav .= ' | ';
			if ($week !== (int)$row['weekNum']) {
				$weekNav .= '<a href="entry_form.php?week=' . $row['weekNum'] . '">' . $row['weekNum'] . '</a>';
			} else {
				$weekNav .= $row['weekNum'];
			}
			$i++;
		}
	}
	$query->free();
	$weekNav .= '	</div>' . "\n";
}
$weekNav .= '</div>' . "\n";
echo $weekNav;
?>
		<div class="row">
			<div class="col-md-4 col-xs-12 col-right">
<?php
include('includes/column_right.php');
?>
			</div>
			<div id="content" class="col-md-8 col-xs-12">
				<?php
				$roundLabel = $round;
				if ($type === 'playoffs') {
					$playoffRoundNames = array(
						1 => 'Wild Card',
						2 => 'Divisional',
						3 => 'Conference Championships',
						4 => 'Super Bowl'
					);
					$roundLabel = isset($playoffRoundNames[$round]) ? $playoffRoundNames[$round] : $round;
				}
				?>
				<h2><?php echo ($type === 'playoffs') ? 'Playoffs - ' . $roundLabel : 'Week ' . $week; ?> - Make Your Picks:</h2>
				<p>Please make your picks below for each game.</p>
	<?php
	//get existing picks
	if ($type === 'playoffs') {
		$picks = array();
		$sql = "select * from " . DB_PREFIX . "playoff_picks where userID = " . $user->userID;
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			$picks[$row['gameID']] = $row['pickTeamID'];
		}
		$query->free();
	} else {
		$picks = $statsService->getUserPicks($week, $user->userID);
	}

	//get show picks status (regular season only)
	if ($type !== 'playoffs') {
		$sql = "select * from " . DB_PREFIX . "picksummary where weekNum = " . $week . " and userID = " . $user->userID . ";";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			$showPicks = (int)$row['showPicks'];
		} else {
			$showPicks = 1;
		}
		$query->free();
	}

	//display schedule for week
	if ($type === 'playoffs') {
		$sql = "select s.* ";
		$sql .= "from " . DB_PREFIX . "playoff_schedule s ";
		$sql .= "inner join " . DB_PREFIX . "teams ht on s.homeID = ht.teamID ";
		$sql .= "inner join " . DB_PREFIX . "teams vt on s.visitorID = vt.teamID ";
		$sql .= "where s.roundNum = " . $round . " and s.is_bye = 0 ";
		$sql .= "order by s.gameTimeEastern, s.playoffGameID";
	} else {
		$sql = "select s.*, (DATE_ADD(NOW(), INTERVAL " . SERVER_TIMEZONE_OFFSET . " HOUR) > gameTimeEastern)  as expired ";
		$sql .= "from " . DB_PREFIX . "schedule s ";
		$sql .= "inner join " . DB_PREFIX . "teams ht on s.homeID = ht.teamID ";
		$sql .= "inner join " . DB_PREFIX . "teams vt on s.visitorID = vt.teamID ";
		$sql .= "where s.weekNum = " . $week . " ";
		$sql .= "order by s.gameTimeEastern, s.gameID";
	}
	//echo $sql;
	$query = $mysqli->query($sql) or die($mysqli->error);
	if ($query->num_rows > 0) {
		$nowEastern = new DateTime("now", new DateTimeZone("America/New_York"));
		echo '<form name="entryForm" action="entry_form.php" method="post" onsubmit="return checkform();">' . "\n";
		if ($type === 'playoffs') {
			echo '<input type="hidden" name="type" value="playoffs" />' . "\n";
			echo '<input type="hidden" name="round" value="' . $round . '" />' . "\n";
		} else {
			echo '<input type="hidden" name="week" value="' . $week . '" />' . "\n";
		}
		//echo '<table cellpadding="4" cellspacing="0" class="table1">' . "\n";
		//echo '	<tr><th>Home</th><th>Visitor</th><th align="left">Game</th><th>Time / Result</th><th>Your Pick</th></tr>' . "\n";
		echo '		<div class="row">'."\n";
		echo '			<div class="col-xs-12">'."\n";
		$i = 0;
		while ($row = $query->fetch_assoc()) {
			$expired = false;
			if ($type === 'playoffs' && !empty($row['gameTimeEastern'])) {
				$gameTime = new DateTime($row['gameTimeEastern'], new DateTimeZone("America/New_York"));
				$expired = ($nowEastern >= $gameTime);
			} else {
				$expired = (bool)$row['expired'];
			}
			$scoreEntered = false;
			$homeTeam = new team($row['homeID']);
			$visitorTeam = new team($row['visitorID']);
			$homeScore = (int)$row['homeScore'];
			$visitorScore = (int)$row['visitorScore'];
			$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
			echo '				<div class="matchup">' . "\n";
			echo '					<div class="row bg-row1">'."\n";
			if (!empty($homeScore) || !empty($visitorScore)) {
				//if score is entered, show score
				$scoreEntered = true;
				if ($homeScore > $visitorScore) {
					$winnerID = $row['homeID'];
				} else if ($visitorScore > $homeScore) {
					$winnerID = $row['visitorID'];
				};
				//$winnerID will be null if tie, which is ok
				echo '					<div class="col-xs-12 center"><b>Final: ' . $row['visitorScore'] . ' - ' . $row['homeScore'] . '</b></div>' . "\n";
			} else {
				//else show time of game
				echo '					<div class="col-xs-12 center">' . date('D n/j g:i a', strtotime($row['gameTimeEastern'])) . ' ET</div>' . "\n";
			}
			echo '					</div>'."\n";
			$gameId = ($type === 'playoffs') ? $row['playoffGameID'] : $row['gameID'];
			$pickValue = '';
			if ($type === 'playoffs') {
				$pickValue = isset($picks[$gameId]) ? $picks[$gameId] : '';
			} else {
				$pickValue = isset($picks[$gameId]['pickID']) ? $picks[$gameId]['pickID'] : '';
			}
			$lockedPickTeamId = ($expired && !empty($pickValue)) ? $pickValue : '';
			$lockedPickClass = ' locked-pick-pending';
			if ($expired && $scoreEntered && !empty($pickValue)) {
				if ($pickValue == $winnerID) {
					$lockedPickClass = ' locked-pick-correct';
				} else {
					$lockedPickClass = ' locked-pick-wrong';
				}
			}
			echo '					<div class="row versus">' . "\n";
			echo '						<div class="col-xs-1"></div>' . "\n";
			echo '						<div class="col-xs-4">'."\n";
			$visitorLockedClass = (!empty($lockedPickTeamId) && $lockedPickTeamId === $visitorTeam->teamID) ? $lockedPickClass : '';
			echo '							<label for="' . $gameId . $visitorTeam->teamID . '" class="label-for-check"><div class="team-logo ' . $visitorLockedClass . '"><img src="images/logos/'.$visitorTeam->teamID.'.svg" onclick="document.entryForm.game'.$gameId.'[0].checked=true;" /></div></label>' . "\n";
			echo '						</div>'."\n";
			echo '						<div class="col-xs-2">@</div>' . "\n";
			echo '						<div class="col-xs-4">'."\n";
			$homeLockedClass = (!empty($lockedPickTeamId) && $lockedPickTeamId === $homeTeam->teamID) ? $lockedPickClass : '';
			echo '							<label for="' . $gameId . $homeTeam->teamID . '" class="label-for-check"><div class="team-logo ' . $homeLockedClass . '"><img src="images/logos/'.$homeTeam->teamID.'.svg" onclick="document.entryForm.game' . $gameId . '[1].checked=true;" /></div></label>'."\n";
			echo '						</div>' . "\n";
			echo '						<div class="col-xs-1"></div>' . "\n";
			echo '					</div>' . "\n";
			if (!$expired) {
				echo '					<div class="row bg-row2">'."\n";
				echo '						<div class="col-xs-1"></div>' . "\n";
				echo '						<div class="col-xs-4 center">'."\n";
				echo '							<input type="radio" class="check-with-label" name="game' . $gameId . '" value="' . $visitorTeam->teamID . '" id="' . $gameId . $visitorTeam->teamID . '"' . (($pickValue == $visitorTeam->teamID) ? ' checked' : '') . ' />'."\n";
				echo '						</div>'."\n";
				//echo '						<div class="col-xs-2 center" style="font-size: 0.8em;">&#9664; Choose &#9654;</div>' . "\n";
				echo '						<div class="col-xs-2"></div>' . "\n";
				echo '						<div class="col-xs-4 center">'."\n";
				echo '							<input type="radio" class="check-with-label" name="game' . $gameId . '" value="' . $homeTeam->teamID . '" id="' . $gameId . $homeTeam->teamID . '"' . (($pickValue == $homeTeam->teamID) ? ' checked' : '') . ' />' . "\n";
				echo '						</div>' . "\n";
				echo '						<div class="col-xs-1"></div>' . "\n";
				echo '					</div>' . "\n";
			}
			echo '					<div class="row bg-row3">'."\n";
			echo '						<div class="col-xs-6 center">'."\n";
			echo '							<div class="team">' . $visitorTeam->city . ' ' . $visitorTeam->team . '</div>'."\n";
			$teamRecord = trim($statsService->getTeamRecord($visitorTeam->teamID));
			if (!empty($teamRecord)) {
				echo '							<div class="record">Record: ' . $teamRecord . '</div>'."\n";
			}
			$teamStreak = trim($statsService->getTeamStreak($visitorTeam->teamID));
			if (!empty($teamStreak)) {
				echo '							<div class="streak">Streak: ' . $teamStreak . '</div>'."\n";
			}
			echo '						</div>'."\n";
			echo '						<div class="col-xs-6 center">' . "\n";
			echo '							<div class="team">' . $homeTeam->city . ' ' . $homeTeam->team . '</div>'."\n";
			$teamRecord = trim($statsService->getTeamRecord($homeTeam->teamID));
			if (!empty($teamRecord)) {
				echo '							<div class="record">Record: ' . $teamRecord . '</div>'."\n";
			}
			$teamStreak = trim($statsService->getTeamStreak($homeTeam->teamID));
			if (!empty($teamStreak)) {
				echo '							<div class="streak">Streak: ' . $teamStreak . '</div>'."\n";
			}
			echo '						</div>' . "\n";
			echo '					</div>'."\n";
			if ($expired) {
				//else show locked pick
				echo '					<div class="row bg-row4">'."\n";
				$pickID = $pickValue;
				if (!empty($pickID)) {
					$pickTeam = new team($pickID);
					$pickLabel = $pickTeam->teamName;
				} else {
					$pickLabel = 'None';
				}
				$statusImg = '';
				if ($scoreEntered && !empty($pickID)) {
					//set status of pick (correct, incorrect)
					if ($pickID == $winnerID) {
						$statusImg = '<img src="images/check_16x16.png" width="16" height="16" alt="" />';
					} else {
						$statusImg = '<img src="images/cross_16x16.png" width="16" height="16" alt="" />';
					}
				}
				echo '						<div class="col-xs-12 center your-pick"><b>Pick locked:</b></br />';
				echo $statusImg . ' ' . $pickLabel;
				echo '</div>' . "\n";
				echo '					</div>' . "\n";
			}
			echo '				</div>'."\n";
			$i++;
		}
		echo '		</div>' . "\n";
		echo '		</div>' . "\n";
		echo '<p class="noprint"><input type="checkbox" name="showPicks" id="showPicks" value="1"' . (($showPicks) ? ' checked="checked"' : '') . ' /> <label for="showPicks">Allow others to see my picks</label></p>' . "\n";
		echo '<p class="noprint"><input type="submit" name="action" value="Submit" /></p>' . "\n";
		echo '</form>' . "\n";
	}

echo '	</div>'."\n"; // end col
echo '	</div>'."\n"; // end entry-form row

//echo '<div id="comments" class="row">';
include('includes/comments.php');
//echo '</div>';

include('includes/footer.php');
