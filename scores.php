<?php
require('includes/application_top.php');
require('includes/classes/team.php');

if (!$user->is_admin) {
	header('Location: ./');
	exit;
}

if ($_POST['action'] == 'Update') {
	$playoffRound = isset($_POST['playoff_round']) ? (int)$_POST['playoff_round'] : 0;
	foreach($_POST['game'] as $game) {
		$homeScore = ((strlen($game['homeScore']) > 0) ? $game['homeScore'] : 'NULL');
		$visitorScore = ((strlen($game['visitorScore']) > 0) ? $game['visitorScore'] : 'NULL');
		$overtime = ((!empty($game['OT'])) ? '1' : '0');
		if ($playoffRound > 0) {
			$sql = "update " . DB_PREFIX . "playoff_schedule ";
			$sql .= "set homeScore = " . $homeScore . ", visitorScore = " . $visitorScore . ", overtime = " . $overtime . " ";
			$sql .= "where playoffGameID = " . (int)$game['gameID'];
		} else {
			$sql = "update " . DB_PREFIX . "schedule ";
			$sql .= "set homeScore = " . $homeScore . ", visitorScore = " . $visitorScore . ", overtime = " . $overtime . " ";
			$sql .= "where gameID = " . (int)$game['gameID'];
		}
		$mysqli->query($sql) or die('Error updating score: ' . $mysqli->error);
	}
	if ($playoffRound > 0) {
		header('Location: ' . $_SERVER['PHP_SELF'] . '?playoff_round=' . $playoffRound);
	} else {
		header('Location: ' . $_SERVER['PHP_SELF'] . '?week=' . $week);
	}
	exit;
}

$week = (int)$_GET['week'];
$playoffRound = isset($_GET['playoff_round']) ? (int)$_GET['playoff_round'] : 0;
$updatedCount = isset($_GET['updated_count']) ? (int)$_GET['updated_count'] : 0;
if (empty($week)) {
	//get current week
	$week = (int)$statsService->getCurrentWeek();
}

include('includes/header.php');
?>
	<h1>Enter Scores - <?php echo ($playoffRound > 0) ? 'Playoff Round ' . $playoffRound : 'Week ' . $week; ?></h1>
<?php
//display week nav
$sql = "select distinct weekNum from " . DB_PREFIX . "schedule order by weekNum;";
$query = $mysqli->query($sql);
$weekNav = '<div class="navbar3"><b>Go to week:</b> ';
$i = 0;
while ($row = $query->fetch_assoc()) {
	if ($i > 0) $weekNav .= ' | ';
	if ($week !== (int)$row['weekNum'] || $playoffRound > 0) {
		$weekNav .= '<a href="scores.php?week=' . $row['weekNum'] . '">' . $row['weekNum'] . '</a>';
	} else {
		$weekNav .= $row['weekNum'];
	}
	$i++;
}
$query->free();
$playoffRoundNames = array(
	1 => 'WC',
	2 => 'DIV',
	3 => 'CONF',
	4 => 'SB'
);
foreach ($playoffRoundNames as $roundNum => $roundLabel) {
	$weekNav .= ' | ';
	if ($playoffRound === (int)$roundNum) {
		$weekNav .= $roundLabel;
	} else {
		$weekNav .= '<a href="scores.php?playoff_round=' . (int)$roundNum . '">' . $roundLabel . '</a>';
	}
}
$weekNav .= '</div>' . "\n";
echo $weekNav;
?>
<?php if ($updatedCount > 0) { ?>
<div class="bg-success" style="margin-bottom: 10px;">
	<b>Scores updated:</b> <?php echo $updatedCount; ?> games written.
</div>
<?php } ?>
<p>Select a Week:
<select name="weekSelect" onchange="handleWeekChange(this.value);">
<?php
	$sql = "select distinct weekNum from " . DB_PREFIX . "schedule order by weekNum;";
	$query = $mysqli->query($sql);
	if ($query->num_rows > 0) {
		while ($row = $query->fetch_assoc()) {
			echo '	<option value="' . $row['weekNum'] . '"' . ((!empty($week) && $week == $row['weekNum'] && $playoffRound === 0) ? ' selected="selected"' : '') . '>' . $row['weekNum'] . '</option>' . "\n";
		}
	}
	$query->free();
$playoffRoundNames = array(
	1 => 'WC',
	2 => 'DIV',
	3 => 'CONF',
	4 => 'SB'
);
	foreach ($playoffRoundNames as $roundNum => $roundLabel) {
		$selected = ($playoffRound === (int)$roundNum) ? ' selected="selected"' : '';
		echo '	<option value="P' . $roundNum . '"' . $selected . '>Playoffs - ' . $roundLabel . '</option>' . "\n";
	}
?>
</select></p>
<script type="text/javascript">
function getScores(weekNum, type, roundNum) {
	var params = {};
	if (type === 'playoffs') {
		params.type = 'playoffs';
		params.round = roundNum;
	} else {
		params.week = weekNum;
	}
	$.get("getHtmlScores.php", params, function(data) {
		var updated = 0;
		var missing = 0;
		for(var item in data) {
			visitorScoreField = document.getElementById('game[' + data[item].gameID + '][visitorScore]');
			homeScoreField = document.getElementById('game[' + data[item].gameID + '][homeScore]');
			OTField = document.getElementById('game[' + data[item].gameID + '][OT]');
			if (!visitorScoreField || !homeScoreField || !OTField) {
				missing++;
				continue;
			}
			if (visitorScoreField.value !== data[item].visitorScore) {
				visitorScoreField.value = data[item].visitorScore;
				visitorScoreField.className="fieldLoaded";
			}
			if (homeScoreField.value !== data[item].homeScore) {
				homeScoreField.value = data[item].homeScore;
				homeScoreField.className="fieldLoaded";
			}
			if (data[item].overtime == '1') {
				OTField.checked = true;
			}
			updated++;
		}
		if ($('#scoresStatus').length) {
			$('#scoresStatus').text('Loaded ' + updated + ' games. Missing ' + missing + ' game IDs on this page.');
		}
	},'json');
}

function handleWeekChange(value) {
	if (value && value.charAt(0) === 'P') {
		var roundNum = value.substring(1);
		location.href = "<?php echo $_SERVER['PHP_SELF']; ?>?playoff_round=" + roundNum;
	} else {
		location.href = "<?php echo $_SERVER['PHP_SELF']; ?>?week=" + value;
	}
}
</script>
<?php if ($playoffRound > 0) { ?>
<?php
	$playoffButtonLabels = array(
		1 => 'WC',
		2 => 'DIV',
		3 => 'CONF',
		4 => 'SB'
	);
	$playoffButtonLabel = isset($playoffButtonLabels[$playoffRound]) ? $playoffButtonLabels[$playoffRound] : 'Playoff';
?>
<form method="get" action="updatePlayoffScores.php" style="margin-bottom: 10px;">
	<input type="hidden" name="round" value="<?php echo (int)$playoffRound; ?>" />
	<input type="hidden" name="year" value="<?php echo (int)SEASON_YEAR; ?>" />
	<input type="hidden" name="apply" value="1" />
	<input type="hidden" name="return" value="<?php echo $_SERVER['PHP_SELF'] . '?playoff_round=' . (int)$playoffRound; ?>" />
	<input type="submit" class="btn btn-info" value="Update <?php echo $playoffButtonLabel; ?> Scores" />
</form>
<?php } else { ?>
<form method="get" action="updateRegularSeasonScores.php" style="margin-bottom: 10px;">
	<input type="hidden" name="week" value="<?php echo (int)$week; ?>" />
	<input type="hidden" name="year" value="<?php echo (int)SEASON_YEAR; ?>" />
	<input type="hidden" name="apply" value="1" />
	<input type="hidden" name="return" value="<?php echo $_SERVER['PHP_SELF'] . '?week=' . (int)$week; ?>" />
	<input type="submit" class="btn btn-info" value="Update Week <?php echo (int)$week; ?> Scores" />
</form>
<?php } ?>
<div id="scoresStatus" style="margin-bottom: 10px;"></div>
<form id="scoresForm" name="scoresForm" action="scores.php" method="post">
<input type="hidden" name="week" value="<?php echo $week; ?>" />
<input type="hidden" name="playoff_round" value="<?php echo $playoffRound; ?>" />
<div class="table-responsive">
<?php
$sql = "select s.*, ht.city, ht.team, ht.displayName, vt.city, vt.team, vt.displayName ";
if ($playoffRound > 0) {
	$sql .= "from " . DB_PREFIX . "playoff_schedule s ";
	$sql .= "inner join " . DB_PREFIX . "teams ht on s.homeID = ht.teamID ";
	$sql .= "inner join " . DB_PREFIX . "teams vt on s.visitorID = vt.teamID ";
	$sql .= "where roundNum = " . (int)$playoffRound . " and is_bye = 0 ";
} else {
	$sql .= "from " . DB_PREFIX . "schedule s ";
	$sql .= "inner join " . DB_PREFIX . "teams ht on s.homeID = ht.teamID ";
	$sql .= "inner join " . DB_PREFIX . "teams vt on s.visitorID = vt.teamID ";
	$sql .= "where weekNum = " . $week . " ";
}
$sql .= "order by gameTimeEastern";
$query = $mysqli->query($sql);
if ($query->num_rows > 0) {
	echo '<table class="table table-striped">' . "\n";
	echo '	<tr><th colspan="6" align="left">' . (($playoffRound > 0) ? 'Playoff Round ' . $playoffRound : 'Week ' . $week) . '</th></tr>' . "\n";
	$i = 0;
	while ($row = $query->fetch_assoc()) {
		$homeTeam = new team($row['homeID']);
		$visitorTeam = new team($row['visitorID']);
		$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
		$gameIdValue = ($playoffRound > 0) ? $row['playoffGameID'] : $row['gameID'];
		echo '		<tr' . $rowclass . '>' . "\n";
		echo '			<td><input type="hidden" name="game[' . $gameIdValue . '][gameID]" value="' . $gameIdValue . '" />' . date('D n/j g:i a', strtotime($row['gameTimeEastern'])) . ' ET</td>' . "\n";
		echo '			<td align="right">' . $visitorTeam->teamName . '</td>' . "\n";
		echo '			<td><input type="text" name="game[' . $gameIdValue . '][visitorScore]" id="game[' . $gameIdValue . '][visitorScore]" value="' . $row['visitorScore'] . '" size="3" /></td>' . "\n";
		echo '			<td align="right">at ' . $homeTeam->teamName . '</td>' . "\n";
		echo '			<td><input type="text" name="game[' . $gameIdValue . '][homeScore]" id="game[' . $gameIdValue . '][homeScore]" value="' . $row['homeScore'] . '" size="3" /></td>' . "\n";
		echo '			<td>OT <input type="checkbox" name="game[' . $gameIdValue . '][OT]" id="game[' . $gameIdValue . '][OT]" value="1"' . (($row['overtime']) ? ' checked="checked"' : '') . '" /></td>' . "\n";
		echo '		</tr>' . "\n";
		$i++;
	}
	echo '</table>' . "\n";
}
$query->free();
?>
</div>
<input type="submit" name="action" value="Update" class="btn btn-info" />
</form>
<?php
include('includes/footer.php');
