<?php
require_once('includes/application_top.php');
require('includes/classes/team.php');

$activeTab = 'playoffs';
$round = isset($_GET['round']) ? (int)$_GET['round'] : 1;

if ($_POST['action'] == 'Submit') {
	$round = (int)$_POST['round'];
	$sql = "select * from " . DB_PREFIX . "playoff_schedule where roundNum = " . $round . " and is_bye = 0";
	$query = $mysqli->query($sql);
	if ($query->num_rows > 0) {
		while ($row = $query->fetch_assoc()) {
			$sql = "delete from " . DB_PREFIX . "playoff_picks where userID = " . $user->userID . " and gameID = " . (int)$row['playoffGameID'];
			$mysqli->query($sql) or die('Error deleting playoff picks: ' . $mysqli->error);

			if (!empty($_POST['game' . $row['playoffGameID']])) {
				$sql = "insert into " . DB_PREFIX . "playoff_picks (userID, gameID, pickTeamID) values (" . $user->userID . ", " . (int)$row['playoffGameID'] . ", '" . $_POST['game' . $row['playoffGameID']] . "')";
				$mysqli->query($sql) or die('Error inserting playoff picks: ' . $mysqli->error);
			}
		}
	}
	$query->free;
	header('Location: results.php?type=playoffs&round=' . $round);
	exit;
}

include('includes/header.php');
?>
	<script type="text/javascript">
	function checkform() {
		//make sure all picks have a checked value
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
			return confirm('One or more picks are missing for the current round.  Do you wish to submit anyway?');
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
	    var targetLabel = $('label[for="'+$(this).attr('id')+'"]');
	    if ($(this).is(':checked')) {
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
<?php

// Round nav
$roundNav = '<div id="roundNav" class="row">';
$roundNav .= '	<div class="navbar3 col-xs-12"><b>Go to round:</b> ';
$playoffRoundNames = array(
	1 => 'Wild Card',
	2 => 'Divisional',
	3 => 'Conference Championships',
	4 => 'Super Bowl'
);
$sql = "select distinct roundNum from " . DB_PREFIX . "playoff_schedule order by roundNum";
$query = $mysqli->query($sql);
$i = 0;
while ($row = $query->fetch_assoc()) {
	if ($i > 0) $roundNav .= ' | ';
	$roundLabel = isset($playoffRoundNames[(int)$row['roundNum']]) ? $playoffRoundNames[(int)$row['roundNum']] : $row['roundNum'];
	if ($round !== (int)$row['roundNum']) {
		$roundNav .= '<a href="playoff_entry.php?round=' . $row['roundNum'] . '">' . $roundLabel . '</a>';
	} else {
		$roundNav .= $roundLabel;
	}
	$i++;
}
$query->free;
$roundNav .= '	</div>' . "\n";
$roundNav .= '</div>' . "\n";
echo $roundNav;
?>
	<div class="row">
		<div class="col-md-4 col-xs-12 col-right">
<?php include('includes/column_right.php'); ?>
		</div>
		<div id="content" class="col-md-8 col-xs-12">
			<?php
			$roundLabel = isset($playoffRoundNames[$round]) ? $playoffRoundNames[$round] : $round;
			?>
			<h2>Playoffs - <?php echo $roundLabel; ?> - Make Your Picks:</h2>
			<p>Please make your picks below for each game.</p>
<?php
$picks = array();
$sql = "select * from " . DB_PREFIX . "playoff_picks where userID = " . $user->userID;
$query = $mysqli->query($sql);
while ($row = $query->fetch_assoc()) {
	$picks[$row['gameID']] = $row['pickTeamID'];
}
$query->free;

$sql = "select s.* from " . DB_PREFIX . "playoff_schedule s where s.roundNum = " . $round . " order by s.gameTimeEastern, s.playoffGameID";
$query = $mysqli->query($sql) or die($mysqli->error);
if ($query->num_rows > 0) {
	$nowEastern = new DateTime("now", new DateTimeZone("America/New_York"));
	echo '<form name="entryForm" action="playoff_entry.php" method="post" onsubmit="return checkform();">' . "\n";
	echo '<input type="hidden" name="round" value="' . $round . '" />' . "\n";
	echo '		<div class="row">'."\n";
	echo '			<div class="col-xs-12">'."\n";
	while ($row = $query->fetch_assoc()) {
		$expired = false;
		if (!empty($row['gameTimeEastern'])) {
			$gameTime = new DateTime($row['gameTimeEastern'], new DateTimeZone("America/New_York"));
			$expired = ($nowEastern >= $gameTime);
		}
		if ((int)$row['is_bye'] === 1) {
			echo '<div class="matchup">';
			echo '	<div class="row bg-row1"><div class="col-xs-12 center"><b>Bye Week Placeholder</b></div></div>';
			echo '</div>';
			continue;
		}

		$homeTeam = new team($row['homeID']);
		$visitorTeam = new team($row['visitorID']);
		$homeScore = (int)$row['homeScore'];
		$visitorScore = (int)$row['visitorScore'];
		echo '				<div class="matchup">' . "\n";
		echo '					<div class="row bg-row1">'."\n";
		if (!empty($homeScore) || !empty($visitorScore)) {
			echo '					<div class="col-xs-12 center"><b>Final: ' . $row['visitorScore'] . ' - ' . $row['homeScore'] . '</b></div>' . "\n";
		} else if (!empty($row['gameTimeEastern'])) {
			echo '					<div class="col-xs-12 center">' . date('D n/j g:i a', strtotime($row['gameTimeEastern'])) . ' ET</div>' . "\n";
		} else {
			echo '					<div class="col-xs-12 center">TBD</div>' . "\n";
		}
		echo '					</div>'."\n";
		echo '					<div class="row versus">' . "\n";
		echo '						<div class="col-xs-1"></div>' . "\n";
		echo '						<div class="col-xs-4">'."\n";
		echo '							<label for="' . $row['playoffGameID'] . $visitorTeam->teamID . '" class="label-for-check"><div class="team-logo"><img src="images/logos/'.$visitorTeam->teamID.'.svg" /></div></label>' . "\n";
		echo '						</div>'."\n";
		echo '						<div class="col-xs-2">@</div>' . "\n";
		echo '						<div class="col-xs-4">'."\n";
		echo '							<label for="' . $row['playoffGameID'] . $homeTeam->teamID . '" class="label-for-check"><div class="team-logo"><img src="images/logos/'.$homeTeam->teamID.'.svg" /></div></label>'."\n";
		echo '						</div>' . "\n";
		echo '						<div class="col-xs-1"></div>' . "\n";
		echo '					</div>' . "\n";
		if (!$expired) {
			echo '					<div class="row bg-row2">'."\n";
			echo '						<div class="col-xs-1"></div>' . "\n";
			echo '						<div class="col-xs-4 center">'."\n";
			echo '							<input type="radio" class="check-with-label" name="game' . $row['playoffGameID'] . '" value="' . $visitorTeam->teamID . '" id="' . $row['playoffGameID'] . $visitorTeam->teamID . '"' . (($picks[$row['playoffGameID']] == $visitorTeam->teamID) ? ' checked' : '') . ' />'."\n";
			echo '						</div>'."\n";
			echo '						<div class="col-xs-2"></div>' . "\n";
			echo '						<div class="col-xs-4 center">'."\n";
			echo '							<input type="radio" class="check-with-label" name="game' . $row['playoffGameID'] . '" value="' . $homeTeam->teamID . '" id="' . $row['playoffGameID'] . $homeTeam->teamID . '"' . (($picks[$row['playoffGameID']] == $homeTeam->teamID) ? ' checked' : '') . ' />' . "\n";
			echo '						</div>' . "\n";
			echo '						<div class="col-xs-1"></div>' . "\n";
			echo '					</div>' . "\n";
		}
		echo '					<div class="row bg-row3">'."\n";
		echo '						<div class="col-xs-6 center">'."\n";
		echo '							<div class="team">' . $visitorTeam->city . ' ' . $visitorTeam->team . '</div>'."\n";
		echo '						</div>'."\n";
		echo '						<div class="col-xs-6 center">' . "\n";
		echo '							<div class="team">' . $homeTeam->city . ' ' . $homeTeam->team . '</div>'."\n";
		echo '						</div>'."\n";
		echo '					</div>' . "\n";
		echo '				</div>' . "\n";
	}
	echo '				<div style="margin-top: 10px;"><input type="submit" name="action" value="Submit" class="btn btn-primary" /></div>';
	echo '			</div>'."\n";
	echo '		</div>'."\n";
	echo '</form>' . "\n";
} else {
	echo '<p>No playoff games found for this round.</p>';
}
$query->free;
?>
		</div>
	</div>

<?php include('includes/footer.php'); ?>
