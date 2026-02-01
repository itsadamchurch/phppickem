<?php
require('includes/application_top.php');

if (!$user->is_admin) {
	header('Location: ./');
	exit;
}

//get vars
$week = (int)$statsService->getCurrentWeek();
$prevWeek = $week - 1;
$firstGameTime = $statsService->getFirstGameTime($week);

$stats = $statsService->calculateStats();
$weekStats = $stats['weekStats'];
$playerTotals = $stats['playerTotals'];
$possibleScoreTotal = $stats['possibleScoreTotal'];
$currentLeaders = '';
$bestPickRatios = '';
$addresses = '';
$subject = '';
$message = '';
$weekLabel = 'Week ' . $week;
$prevWeekLabel = 'Week ' . $prevWeek;
$playoffRoundLabel = '';
$seasonYear = defined('SEASON_YEAR') ? (int)SEASON_YEAR : (int)date('Y');
$finalWinnerName = '';
$finalWinningScore = 0;
$finalPickRatio = '';
$finalPickPercent = '';

$playoffTotals = array();
$playoffPossibleTotal = 0;
$playoffPossibleQuery = $mysqli->query("select count(*) as cnt from " . DB_PREFIX . "playoff_schedule where is_bye = 0 and homeScore is not null and visitorScore is not null and (homeScore + visitorScore) > 0");
if ($playoffPossibleQuery && $playoffPossibleQuery->num_rows > 0) {
	$row = $playoffPossibleQuery->fetch_assoc();
	$playoffPossibleTotal = (int)$row['cnt'];
}
if ($playoffPossibleQuery) {
	$playoffPossibleQuery->free();
}

$playoffTotalsQuery = $mysqli->query("select p.userID, p.pickTeamID, s.homeID, s.visitorID, s.homeScore, s.visitorScore " .
	"from " . DB_PREFIX . "playoff_picks p " .
	"inner join " . DB_PREFIX . "playoff_schedule s on p.gameID = s.playoffGameID " .
	"where s.is_bye = 0");
if ($playoffTotalsQuery) {
	while ($row = $playoffTotalsQuery->fetch_assoc()) {
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
	$playoffTotalsQuery->free();
}

$nowEastern = phppickem_now_eastern_datetime();
if ($nowEastern instanceof DateTime) {
	$nowTime = $nowEastern;
} else {
	$nowTime = new DateTime($nowEastern, new DateTimeZone('America/New_York'));
}
$lastRegularGame = $statsService->getLastGameTime(18);
if (!empty($lastRegularGame)) {
	$lastRegularTime = new DateTime($lastRegularGame, new DateTimeZone('America/New_York'));
	if ($nowTime > $lastRegularTime) {
		$roundQuery = $mysqli->query("select roundNum, min(gameTimeEastern) as firstTime from " . DB_PREFIX . "playoff_schedule where is_bye = 0 group by roundNum order by roundNum");
		if ($roundQuery && $roundQuery->num_rows > 0) {
			$playoffRoundNames = array(
				1 => 'WC',
				2 => 'DIV',
				3 => 'CONF',
				4 => 'SB'
			);
			$lastRound = 0;
			$nextRound = 0;
			while ($row = $roundQuery->fetch_assoc()) {
				$lastRound = (int)$row['roundNum'];
				if (!empty($row['firstTime'])) {
					$roundTime = new DateTime($row['firstTime'], new DateTimeZone('America/New_York'));
					if ($roundTime >= $nowTime && $nextRound === 0) {
						$nextRound = (int)$row['roundNum'];
					}
				}
			}
			$roundQuery->free();
			$useRound = ($nextRound > 0) ? $nextRound : $lastRound;
			if ($useRound > 0) {
				$playoffRoundLabel = isset($playoffRoundNames[$useRound]) ? $playoffRoundNames[$useRound] : ('Round ' . $useRound);
				$weekLabel = 'Playoffs - ' . $playoffRoundLabel;
				$prevWeekLabel = $weekLabel;
				$firstPlayoffQuery = $mysqli->query("select min(gameTimeEastern) as firstTime from " . DB_PREFIX . "playoff_schedule where roundNum = " . (int)$useRound . " and is_bye = 0");
				if ($firstPlayoffQuery && $firstPlayoffQuery->num_rows > 0) {
					$row = $firstPlayoffQuery->fetch_assoc();
					if (!empty($row['firstTime'])) {
						$firstGameTime = $row['firstTime'];
					}
				}
				if ($firstPlayoffQuery) {
					$firstPlayoffQuery->free();
				}
			}
		}
	}
}

$winners = '';
if (sizeof($weekStats) > 0 && isset($weekStats[$prevWeek]['winners'])) {
	foreach($weekStats[$prevWeek]['winners'] as $winner => $winnerID) {
		$tmpUser = $login->get_user_by_id($winnerID);
		switch (USER_NAMES_DISPLAY) {
			case 1:
				$winners .= ((strlen($winners) > 0) ? ', ' : '') . trim($tmpUser->firstname . ' ' . $tmpUser->lastname);
				break;
			case 2:
				$winners .= ((strlen($winners) > 0) ? ', ' : '') . $tmpUser->userName;
				break;
			default: //3
				$winners .= ((strlen($winners) > 0) ? ', ' : '') . trim($tmpUser->firstname . ' ' . $tmpUser->lastname) . ' (' . $tmpUser->userName . ')';
				break;
		}
	}
}

$tmpWins = 0;
$i = 1;
if (isset($playerTotals)) {
	//show top 3 leaders by total wins (games won), tie-breaker weekly wins
	$sortedTotals = $playerTotals;
	uasort($sortedTotals, function($a, $b) use ($playoffTotals) {
		$winsA = (isset($a['score']) ? (int)$a['score'] : 0) + (isset($playoffTotals[$a['userID']]) ? $playoffTotals[$a['userID']] : 0);
		$winsB = (isset($b['score']) ? (int)$b['score'] : 0) + (isset($playoffTotals[$b['userID']]) ? $playoffTotals[$b['userID']] : 0);
		if ($winsA === $winsB) {
			$weeklyA = isset($a['wins']) ? (int)$a['wins'] : 0;
			$weeklyB = isset($b['wins']) ? (int)$b['wins'] : 0;
			if ($weeklyA === $weeklyB) return 0;
			return ($weeklyA < $weeklyB) ? 1 : -1;
		}
		return ($winsA < $winsB) ? 1 : -1;
	});
	foreach($sortedTotals as $playerID => $stats) {
		$totalWins = (isset($stats['score']) ? (int)$stats['score'] : 0) + (isset($playoffTotals[$playerID]) ? $playoffTotals[$playerID] : 0);
		$weeklyWins = isset($stats['wins']) ? (int)$stats['wins'] : 0;
		if ($finalWinnerName === '') {
			$finalWinningScore = $totalWins;
			$totalPossible = $possibleScoreTotal + $playoffPossibleTotal;
			$finalPickRatio = $totalWins . '/' . $totalPossible;
			$finalPickPercent = ($totalPossible > 0) ? number_format((($totalWins / $totalPossible) * 100), 2) . '%' : '0.00%';
			switch (USER_NAMES_DISPLAY) {
				case 1:
					$finalWinnerName = $stats['name'];
					break;
				case 2:
					$finalWinnerName = $stats['userName'];
					break;
				default: //3
					$finalWinnerName = $stats['name'] . ' (' . $stats['userName'] . ')';
					break;
			}
		}
		if ($tmpWins < $totalWins) $tmpWins = $totalWins; //set initial number of wins
		if ($totalWins < $tmpWins ) $i++;
		if ($totalWins == 0 || $i > 3) break;
		$winsLabel = $totalWins . (($totalWins > 1) ? ' wins' : ' win');
		$weeklyLabel = $weeklyWins . (($weeklyWins > 1) ? ' weekly wins' : ' weekly win');
		switch (USER_NAMES_DISPLAY) {
			case 1:
				$currentLeaders .= $i . '. ' . $stats['name'] . ' - ' . $winsLabel . ' (' . $weeklyLabel . ')<br />';
				break;
			case 2:
				$currentLeaders .= $i . '. ' . $stats['userName'] . ' - ' . $winsLabel . ' (' . $weeklyLabel . ')<br />';
				break;
			default: //3
				$currentLeaders .= $i . '. ' . $stats['name'] . ' (' . $stats['userName'] . ') - ' . $winsLabel . ' (' . $weeklyLabel . ')<br />';
				break;
		}
		$tmpWins = $totalWins; //set last # wins
	}
}

$tmpScore = 0;
$i = 1;
if (isset($playerTotals)) {
	//show top 3 pick ratios
	$playerTotals = $statsService->sort2d($playerTotals, 'score', 'desc');
	foreach($playerTotals as $playerID => $stats) {
		if ($tmpScore < $stats['score']) $tmpScore = $stats['score']; //set initial top score
		//if next lowest score is reached, increase counter
		if ($stats['score'] < $tmpScore ) $i++;
		//if score is zero or counter is 3 or higher, break
		if ($stats['score'] == 0 || $i > 3) break;
		$pickRatio = $stats['score'] . '/' . $possibleScoreTotal;
		$pickPercentage = number_format((($stats['score'] / $possibleScoreTotal) * 100), 2) . '%';
		switch (USER_NAMES_DISPLAY) {
			case 1:
				$bestPickRatios .= $i . '. ' . $stats['name'] . ' - ' . $pickRatio . ' (' . $pickPercentage . ')<br />';
				break;
			case 2:
				$bestPickRatios .= $i . '. ' . $stats['userName'] . ' - ' . $pickRatio . ' (' . $pickPercentage . ')<br />';
				break;
			default: //3
				$bestPickRatios .= $i . '. ' . $stats['name'] . ' (' . $stats['userName'] . ') - ' . $pickRatio . ' (' . $pickPercentage . ')<br />';
				break;
		}
		$tmpScore = $stats['score']; //set last # wins
	}
}

if ($_POST['action'] == 'Select' && isset($_POST['cannedMsg'])) {
	$cannedMsg = $_POST['cannedMsg'];

	$sql = "select * from " . DB_PREFIX . "email_templates where email_template_key = '" . $cannedMsg . "'";
	$query = $mysqli->query($sql);
	$row = $query->fetch_assoc();
	$subjectTemplate = $row['subject'];
	$messageTemplate = $row['message'];

	//replace variables
	$template_vars = array('{week}', '{week_label}', '{first_game}', '{site_url}', '{rules_url}', '{winners}', '{previousWeek}', '{previousWeekLabel}', '{winningScore}', '{possibleScore}', '{currentLeaders}', '{bestPickRatios}', '{playoff_round_label}', '{season_year}', '{final_winner}', '{final_winningScore}', '{picks}', '{possible}', '{pickpercent}');
	$prevWeekHigh = isset($weekStats[$prevWeek]['highestScore']) ? $weekStats[$prevWeek]['highestScore'] : 0;
	$replacement_values = array($week, $weekLabel, date('l F j, g:i a', strtotime($firstGameTime)), SITE_URL, SITE_URL . 'rules.php', $winners, $prevWeek, $prevWeekLabel, $prevWeekHigh, $statsService->getGameTotal($prevWeek), $currentLeaders, $bestPickRatios, $playoffRoundLabel, $seasonYear, $finalWinnerName, $finalWinningScore, $finalPickRatio ? explode('/', $finalPickRatio)[0] : '', $finalPickRatio ? explode('/', $finalPickRatio)[1] : '', $finalPickPercent);
	$subject = stripslashes(str_replace($template_vars, $replacement_values, $subjectTemplate));
	$message = stripslashes(str_replace($template_vars, $replacement_values, $messageTemplate));
}

if ($_POST['action'] == 'Send Message') {
	$totalGames = $statsService->getGameTotal($week);
	//get users to send message to
	if ($_POST['cannedMsg'] == 'WEEKLY_PICKS_REMINDER') {
		//select only users missing picks for the current week
		$sql = "select u.firstname, u.email,";
		$sql .= "(select count(p.pickID) from nflp_picks p inner join nflp_schedule s on p.gameID = s.gameID where userID = u.userID and s.weekNum = " . $week . ") as userPicks ";
		$sql .= "from " . DB_PREFIX . "users u ";
		$sql .= "where u.`status` = 1 and u.userName <> 'admin' ";
		$sql .= "group by u.firstname, u.email ";
		$sql .= "having userPicks < " . $totalGames;
	} else {
		//select all users
		$sql = "select firstname, email from " . DB_PREFIX . "users where `status` = 1 and userName <> 'admin'";
	}
	$query = $mysqli->query($sql);
	if ($query->num_rows > 0) {
		while ($row = $query->fetch_assoc()) {
			//fire it off!
			$subject = stripslashes($_POST['subject']);
			$message = stripslashes($_POST['message']);
			$message = str_replace('{player}', $row['firstname'], $message);

			$mail = new PHPMailer();
			$mail->IsHTML(true);

			$mail->From = $adminUser->email; // the email field of the form
			$mail->FromName = 'NFL Pick \'Em Admin'; // the name field of the form

			$addresses .= ((strlen($addresses) > 0) ? ', ' : '') . $row['email'];
			$mail->AddAddress($row['email']); // the form will be sent to this address
			$mail->Subject = $subject; // the subject of email

			// html text block
			$mail->Body = $message;
			$mail->Send();
			//echo $subject . '<br />';
			//echo $message;
		}
		$display = '<div class="responseOk">Message successfully sent to: ' . $addresses . '.</div><br/>';
		//header('Location: send_email.php');
		//exit;
	}
		if ($query) {
			$query->free();
		}
}

include('includes/header.php');

if(isset($display)) {
	echo $display;
} else {
?>
<script language="JavaScript" type="text/javascript" src="js/cbrte/html2xhtml.js"></script>
<script language="JavaScript" type="text/javascript" src="js/cbrte/richtext_compressed.js"></script>
<script language="JavaScript" type="text/javascript">
function submitForm() {
	//make sure hidden and iframe values are in sync for all rtes before submitting form
	updateRTEs();

	return true;
}

//Usage: initRTE(imagesPath, includesPath, cssFile, genXHTML, encHTML)
initRTE("js/cbrte/images/", "js/cbrte/", "", true);
</script>
<noscript><p><b>Javascript must be enabled to use this form.</b></p></noscript>
<form name="cannedmsgform" action="send_email.php" method="post" onsubmit="return submitForm();">

<p>Select Email Template:<br />
<select name="cannedMsg">
	<option value=""></option>
	<?php
	$sql = "select * from " . DB_PREFIX . "email_templates";
	$query = $mysqli->query($sql);
	while ($row = $query->fetch_assoc()) {
		echo '<option value="' . $row['email_template_key'] . '"' . (($_POST['cannedMsg'] == $row['email_template_key']) ? ' selected="selected"' : '') . '>' . $row['email_template_title'] . '</option>' . "\n";
	}
	$query->free();
	?>
</select>&nbsp;<input type="submit" name="action" value="Select" class="btn btn-info" /></p>

<p>Subject:<br />
<input type="text" name="subject" value="<?php echo $subject; ?>" size="40"></p>

<p>Message:<br />
<script language="JavaScript" type="text/javascript">
//build new richTextEditor
var message = new richTextEditor('message');
<?php
//format content for preloading
if (!empty($message)) {
	$message = $statsService->rteSafe($message);
}
?>
message.html = '<?php echo $message; ?>';
//rte1.toggleSrc = false;
message.build();
</script>
</p>

<p><input name="action" type="submit" value="Send Message" class="btn btn-info" /></p>
</form>
<?php
}

include('includes/footer.php');
