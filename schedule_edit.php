<?php
require('includes/application_top.php');
require('includes/classes/team.php');

if (!$user->is_admin) {
	header('Location: ./');
	exit;
}

$action = $_GET['action'];
switch ($action) {
	case 'edit_action':
		$gameID = $_POST['gameID'];
		$week = $_POST['weekNum'];
		$gameTimeEastern = date('Y-m-d G:i:00', strtotime($_POST['gameTimeEastern'] . ' ' . $_POST['gameTimeEastern2']));
		$homeID = $_POST['homeID'];
		$visitorID = $_POST['visitorID'];

		//make sure all required fields are filled in and valid
		if (empty($homeID) || empty($visitorID)) {
			die('error: missing home or visiting team.');
		}

		//delete all picks already entered for this game
		//IF teams or week num changed, and game is still in the future
		$sql = "select * from " . DB_PREFIX . "schedule where gameID = " . $gameID;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			if (date('U') < strtotime($row['gameTimeEastern'])) {
				if ($week !== $row['weekNum'] || $homeID !== $row['homeID'] || $visitorID !== $row['visitorID']) {
					//delete picks for current game
					$sql = "delete from " . DB_PREFIX . "picks where gameID = " . $gameID;
					$mysqli->query($sql) or die('error deleting picks');
				}
			}
		} else {
			die('ah, something isn\'t quite right here...');
		}
		$query->free();

		//update game and redirect to same week
		$sql = "update " . DB_PREFIX . "schedule ";
		$sql .= "set weekNum = " . $week . ", gameTimeEastern = '" . $gameTimeEastern . "', homeID = '" . $homeID . "', visitorID = '" . $visitorID . "' ";
		$sql .= "where gameID = " . $gameID;
		$mysqli->query($sql) or die($mysqli->error . '. Query:' . $sql);

		header('Location: ' . $_SERVER['PHP_SELF'] . '?week=' . $week);
		exit;
		break;
	case 'delete':
		$gameID = $_GET['id'];
		$week = $_GET['week'];

		//delete picks for current game
		$sql = "delete from " . DB_PREFIX . "picks where gameID = " . $gameID;
		$mysqli->query($sql) or die('error deleting picks');

		$sql = "delete from " . DB_PREFIX . "schedule where gameID = " . $gameID;
		$mysqli->query($sql) or die('error deleting game: ' . $sql);
		header('Location: ' . $_SERVER['PHP_SELF'] . '?week=' . $week);
		exit;
		break;
	case 'edit_playoff_action':
		$gameID = $_POST['gameID'];
		$round = $_POST['roundNum'];
		$gameTimeEastern = date('Y-m-d G:i:00', strtotime($_POST['gameTimeEastern'] . ' ' . $_POST['gameTimeEastern2']));
		$homeID = $_POST['homeID'];
		$visitorID = $_POST['visitorID'];

		if (empty($homeID) || empty($visitorID)) {
			die('error: missing home or visiting team.');
		}

		$sql = "update " . DB_PREFIX . "playoff_schedule ";
		$sql .= "set roundNum = " . (int)$round . ", gameTimeEastern = '" . $gameTimeEastern . "', homeID = '" . $homeID . "', visitorID = '" . $visitorID . "' ";
		$sql .= "where playoffGameID = " . (int)$gameID;
		$mysqli->query($sql) or die($mysqli->error . '. Query:' . $sql);

		header('Location: ' . $_SERVER['PHP_SELF'] . '?playoff_round=' . (int)$round);
		exit;
		break;
	case 'add_playoff_action':
		$round = $_POST['roundNum'];
		$gameTimeEastern = date('Y-m-d G:i:00', strtotime($_POST['gameTimeEastern'] . ' ' . $_POST['gameTimeEastern2']));
		$homeID = $_POST['homeID'];
		$visitorID = $_POST['visitorID'];

		if (empty($homeID) || empty($visitorID)) {
			die('error: missing home or visiting team.');
		}

		$sql = "insert into " . DB_PREFIX . "playoff_schedule (roundNum, gameTimeEastern, homeID, visitorID, homeScore, visitorScore, is_bye) values (" .
			(int)$round . ", '" . $mysqli->real_escape_string($gameTimeEastern) . "', '" . $mysqli->real_escape_string($homeID) . "', '" .
			$mysqli->real_escape_string($visitorID) . "', 0, 0, 0)";
		$mysqli->query($sql) or die($mysqli->error . '. Query:' . $sql);

		header('Location: ' . $_SERVER['PHP_SELF'] . '?playoff_round=' . (int)$round);
		exit;
		break;
	case 'delete_playoff':
		$gameID = $_GET['id'];
		$round = $_GET['playoff_round'];

		$sql = "delete from " . DB_PREFIX . "playoff_picks where gameID = " . (int)$gameID;
		$mysqli->query($sql) or die('error deleting playoff picks');

		$sql = "delete from " . DB_PREFIX . "playoff_schedule where playoffGameID = " . (int)$gameID;
		$mysqli->query($sql) or die('error deleting playoff game: ' . $sql);
		header('Location: ' . $_SERVER['PHP_SELF'] . '?playoff_round=' . (int)$round);
		exit;
		break;
	default:
		break;
}

include('includes/header.php');

if ($action == 'add' || $action == 'edit' || $action == 'edit_playoff' || $action == 'add_playoff') {
	//display add/edit screen
	if ($action =='add') {
		$week = $_GET['week'];
		if (empty($week)) {
			$week = $statsService->getCurrentWeek();
		}
	} else if ($action == 'edit') {
		$sql = "select * from " . DB_PREFIX . "schedule where gameID = " . (int)$_GET['id'];
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			$week = $row['weekNum'];
			$gameTimeEastern = $row['gameTimeEastern'];
			$homeID = $row['homeID'];
			$visitorID = $row['visitorID'];
		} else {
			header('Location: ' . $_SERVER['PHP_SELF']);
			exit;
		}
		$query->free();
	} else if ($action == 'edit_playoff') {
		$sql = "select * from " . DB_PREFIX . "playoff_schedule where playoffGameID = " . (int)$_GET['id'];
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			$round = $row['roundNum'];
			$gameTimeEastern = $row['gameTimeEastern'];
			$homeID = $row['homeID'];
			$visitorID = $row['visitorID'];
		} else {
			header('Location: ' . $_SERVER['PHP_SELF']);
			exit;
		}
		$query->free();
	} else if ($action == 'add_playoff') {
		$round = isset($_GET['playoff_round']) ? (int)$_GET['playoff_round'] : 1;
		$gameTimeEastern = phppickem_now_eastern_datetime();
		$homeID = '';
		$visitorID = '';
	}
?>
<script type="text/javascript" src="js/ui.core.js"></script>
<script type="text/javascript" src="js/ui.datepicker.js"></script>
<link href="css/jquery-ui-themeroller.css" rel="stylesheet" type="text/css" media="screen" />
<!-- timePicker found at http://labs.perifer.se/timedatepicker/ -->
<script type="text/javascript" src="js/jquery.timePicker.js"></script>
<link href="css/timePicker.css" rel="stylesheet" type="text/css" media="screen" />

<h1><?php echo (($action === 'edit_playoff' || $action === 'add_playoff') ? (($action === 'edit_playoff') ? 'Edit Playoff Game' : 'Add Playoff Game') : ucfirst($action) . ' Game'); ?></h1>
<div class="warning">Warning: Changes made to future games will erase picks entered for games affected.</div>
<form name="addeditgame" action="<?php echo $_SERVER['PHP_SELF']; ?>?action=<?php echo $action; ?>_action" method="post">
<input type="hidden" name="gameID" value="<?php echo isset($_GET['id']) ? (int)$_GET['id'] : 0; ?>" />

<?php if ($action === 'edit_playoff' || $action === 'add_playoff') { ?>
<p>Round:<br />
<input type="text" name="roundNum" value="<?php echo $round; ?>" size="5" /></p>
<?php } else { ?>
<p>Week:<br />
<input type="text" name="weekNum" value="<?php echo $week; ?>" size="5" /></p>
<?php } ?>

<p>Date/Time:<br />
<input type="date" id="gameTimeEastern" name="gameTimeEastern" value="<?php echo date('Y-m-d', strtotime($gameTimeEastern)); ?>" size="10" />&nbsp;
<input type="time" id="gameTimeEastern2" name="gameTimeEastern2" value="<?php echo date('H:i', strtotime($gameTimeEastern)); ?>" size="10" />
<?php /*
			<script type="text/javascript">
			$("#gameTimeEastern").datepicker({
			    dateFormat: $.datepicker.W3C,
			    showOn: "both",
			    buttonImage: "images/icons/calendar_16x16.png",
			    buttonImageOnly: true
			});
			$("#gameTimeEastern2").timePicker({
				show24Hours:false,
				step: 15
			});
			</script>
*/ ?>
</p>

<p>Home Team:<br />
<select name="homeID">
	<option value=""></option>
<?php
$sql = "select * from " . DB_PREFIX . "teams order by city, team";
$query = $mysqli->query($sql);
if ($query->num_rows > 0) {
	while ($row = $query->fetch_assoc()) {
		if ($homeID == $row['teamID']) {
			echo '	<option value="' . $row['teamID'] . '" selected="selected">' . $row['city'] . ' ' . $row['team'] . '</option>';
		} else {
			echo '	<option value="' . $row['teamID'] . '">' . $row['city'] . ' ' . $row['team'] . '</option>';
		}
	}
}
$query->free();
?>
</select></p>

<p>Visiting Team:<br />
<select name="visitorID">
	<option value=""></option>
<?php
$sql = "select * from " . DB_PREFIX . "teams order by city, team";
$query = $mysqli->query($sql);
if ($query->num_rows > 0) {
	while ($row = $query->fetch_assoc()) {
		if ($visitorID == $row['teamID']) {
			echo '	<option value="' . $row['teamID'] . '" selected="selected">' . $row['city'] . ' ' . $row['team'] . '</option>';
		} else {
			echo '	<option value="' . $row['teamID'] . '">' . $row['city'] . ' ' . $row['team'] . '</option>';
		}
	}
}
$query->free();
?>
</select></p>

<p><input type="submit" name="submit" value="<?php echo ($action === 'edit_playoff') ? 'Edit' : (($action === 'add_playoff') ? 'Add' : ucfirst($action)); ?>" class="btn btn-info" />&nbsp;
<a href="<?php echo $_SERVER['PHP_SELF']; ?><?php echo (($action === 'edit_playoff' || $action === 'add_playoff') ? '?playoff_round=' . (int)$round : '?week=' . $week); ?>" />cancel</a></p>

</form>
<?php
} else {
	//display listing
$week = $_GET['week'];
$playoffRound = isset($_GET['playoff_round']) ? (int)$_GET['playoff_round'] : 0;
$updatedRound = isset($_GET['updated_round']) ? (int)$_GET['updated_round'] : 0;
$updatedCount = isset($_GET['updated_count']) ? (int)$_GET['updated_count'] : 0;
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
	if (empty($week)) {
		$week = $statsService->getCurrentWeek();
	}
?>
<h1>Edit Schedule</h1>
<?php if ($updatedRound > 0) { ?>
<div class="bg-success">
	<b>Playoff round updated:</b> Round <?php echo $updatedRound; ?> — <?php echo $updatedCount; ?> games written.
</div>
<?php } ?>
<?php if ($debug && $playoffRound > 0) { ?>
<div class="bg-warning" style="white-space: pre-wrap; max-height: 320px; overflow: auto;">
	<b>ESPN debug (round <?php echo $playoffRound; ?>):</b>
	<?php
		if (!empty($_SESSION['playoff_debug']) && (int)$_SESSION['playoff_debug']['round'] === (int)$playoffRound) {
			echo htmlspecialchars(json_encode($_SESSION['playoff_debug'], JSON_PRETTY_PRINT)) . "\n";
			unset($_SESSION['playoff_debug']);
		}
		$debugUrl = 'buildPlayoffSchedule.php?round=' . (int)$playoffRound . '&debug=1&year=' . (int)SEASON_YEAR;
		$debugJson = @file_get_contents($debugUrl);
		if ($debugJson) {
			echo htmlspecialchars($debugJson);
		} else {
			echo 'Unable to fetch debug data.';
		}
	?>
</div>
<?php } ?>
<p>Select a Week:
<select name="week" onchange="handleWeekChange(this.value);">
<?php
	$sql = "select distinct weekNum from " . DB_PREFIX . "schedule order by weekNum;";
	$query = $mysqli->query($sql);
	if ($query->num_rows > 0) {
		while ($row = $query->fetch_assoc()) {
			echo '	<option value="' . $row['weekNum'] . '"' . ((!empty($week) && $week == $row['weekNum']) ? ' selected="selected"' : '') . '>' . $row['weekNum'] . '</option>' . "\n";
		}
	}
	$query->free();
	// playoff rounds
	$playoffRoundNames = array(
		1 => 'Wild Card',
		2 => 'Divisional',
		3 => 'Conference Championships',
		4 => 'Super Bowl'
	);
	foreach ($playoffRoundNames as $roundNum => $roundLabel) {
		$selected = ($playoffRound === (int)$roundNum) ? ' selected="selected"' : '';
		echo '	<option value="P' . $roundNum . '"' . $selected . '>Playoffs - ' . $roundLabel . '</option>' . "\n";
	}
?>
</select></p>
<?php if ($playoffRound > 0) { ?>
<form method="get" action="buildPlayoffSchedule.php" style="margin-bottom: 10px;">
	<input type="hidden" name="round" value="<?php echo (int)$playoffRound; ?>" />
	<input type="hidden" name="year" value="<?php echo (int)SEASON_YEAR; ?>" />
	<input type="hidden" name="apply" value="1" />
	<input type="hidden" name="return" value="<?php echo $_SERVER['PHP_SELF'] . '?playoff_round=' . (int)$playoffRound . ($debug ? '&debug=1' : ''); ?>" />
	<?php if ($debug) { ?>
	<input type="hidden" name="debug" value="1" />
	<?php } ?>
	<input type="submit" class="btn btn-info" value="Update Playoff Round" />
</form>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=add_playoff&playoff_round=<?php echo (int)$playoffRound; ?>"><img src="images/icons/add_16x16.png" width="16" height="16" alt="Add Game" /></a>&nbsp;<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=add_playoff&playoff_round=<?php echo (int)$playoffRound; ?>">Add Playoff Game</a></p>
<?php } ?>
<!--p><a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=add&week=<?php echo $week; ?>"><img src="images/icons/add_16x16.png" width="16" height="16" alt="Add Game" /></a>&nbsp;<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=add">Add Game</a></p-->
<div class="table-responsive">
<?php
	if ($playoffRound > 0) {
		$sql = "select s.*, ht.city, ht.team, ht.displayName, vt.city, vt.team, vt.displayName from " . DB_PREFIX . "playoff_schedule s ";
		$sql .= "inner join " . DB_PREFIX . "teams ht on s.homeID = ht.teamID ";
		$sql .= "inner join " . DB_PREFIX . "teams vt on s.visitorID = vt.teamID ";
		$sql .= " where roundNum = " . $playoffRound . " and is_bye = 0 order by gameTimeEastern";
	} else {
		$sql = "select s.*, ht.city, ht.team, ht.displayName, vt.city, vt.team, vt.displayName from " . DB_PREFIX . "schedule s ";
		$sql .= "inner join " . DB_PREFIX . "teams ht on s.homeID = ht.teamID ";
		$sql .= "inner join " . DB_PREFIX . "teams vt on s.visitorID = vt.teamID ";
		$where .= " where weekNum = " . $week;
		$sql .= $where . " order by gameTimeEastern";
	}
	$query = $mysqli->query($sql);
	if ($query->num_rows > 0) {
		echo '<table class="table table-striped">' . "\n";
		echo '	<tr><th>Home</th><th>Visitor</th><th align="left">Game</th><th>Time / Result</th><th>&nbsp;</th></tr>' . "\n";
		$i = 0;
		$prevWeek = 0;
		while ($row = $query->fetch_assoc()) {
			if ($playoffRound === 0) {
				if ($prevWeek !== $row['weekNum'] && empty($team)) {
					echo '		<tr class="info"><td colspan="5">Week ' . $row['weekNum'] . '</td></tr>' . "\n";
				}
			}
			$homeTeam = new team($row['homeID']);
			$visitorTeam = new team($row['visitorID']);
			$rowclass = (($i % 2 == 0) ? ' class="altrow"' : '');
			echo '		<tr' . $rowclass . '>' . "\n";
			echo '			<td><img src="images/logos/' . $homeTeam->teamID . '.svg" /></td>' . "\n";
			echo '			<td><img src="images/logos/' . $visitorTeam->teamID . '.svg" /></td>' . "\n";
			echo '			<td>' . $visitorTeam->teamName . ' @ ' . $homeTeam->teamName . '</td>' . "\n";
			if (is_numeric($row['homeScore']) && is_numeric($row['visitorScore'])) {
				//if score is entered, show result
				echo '			<td></td>' . "\n";
			} else {
				//show time
				echo '			<td>' . date('D n/j g:i a', strtotime($row['gameTimeEastern'])) . ' ET</td>' . "\n";
			}
			if ($playoffRound === 0) {
				echo '			<td><a href="' . $_SERVER['PHP_SELF'] . '?action=edit&id=' . $row['gameID'] . '"><img src="images/icons/edit_16x16.png" width="16" height="16" alt="edit" /></a>&nbsp;<a href="javascript:confirmDelete(\'' . $row['gameID'] . '\');"><img src="images/icons/delete_16x16.png" width="16" height="16" alt="delete" /></a></td>' . "\n";
			} else {
				echo '			<td><a href="' . $_SERVER['PHP_SELF'] . '?action=edit_playoff&id=' . $row['playoffGameID'] . '"><img src="images/icons/edit_16x16.png" width="16" height="16" alt="edit" /></a>&nbsp;<a href="javascript:confirmDeletePlayoff(\'' . $row['playoffGameID'] . '\', \'' . $playoffRound . '\');"><img src="images/icons/delete_16x16.png" width="16" height="16" alt="delete" /></a></td>' . "\n";
			}
			echo '		</tr>' . "\n";
			if ($playoffRound === 0) {
				$prevWeek = $row['weekNum'];
			}
			$i++;
		}
		echo '</table>' . "\n";
	}
}
?>
<script type="text/javascript">
function confirmDelete(id) {
	//confirm delete
	if (confirm('Are you sure you want to delete this game? This action cannot be undone.')) {
		location.href = "<?php echo $_SERVER['PHP_SELF']; ?>?action=delete&id=" + id;
	}
}

function confirmDeletePlayoff(id, round) {
	if (confirm('Are you sure you want to delete this playoff game? This action cannot be undone.')) {
		location.href = "<?php echo $_SERVER['PHP_SELF']; ?>?action=delete_playoff&id=" + id + "&playoff_round=" + round;
	}
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
<?php
include('includes/footer.php');
