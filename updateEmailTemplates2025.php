<?php
require('includes/application_top.php');

// Usage (CLI):
// php updateEmailTemplates2025.php --apply=1
// Or via browser:
// updateEmailTemplates2025.php?apply=1

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
	$user = isset($user) ? $user : null;
	if (!$user || !$user->is_admin) {
		header('Location: ./');
		exit;
	}
}

$args = array();
if ($isCli) {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$apply = isset($args['apply']) ? ($args['apply'] === '1') : (isset($_GET['apply']) && $_GET['apply'] === '1');

$updates = array(
	'WEEKLY_PICKS_REMINDER' => array(
		'title' => 'Weekly Picks Reminder',
		'subject' => "NFL Pick 'Em {week_label} Reminder",
		'message' => "Hello {player},<br /><br />You are receiving this email because you do not yet have all of your picks in for {week_label}.&nbsp; This is your reminder.&nbsp; The first game is {first_game} (Eastern), so to receive credit for that game, you'll have to make your pick before then.<br /><br />Links:<br />&nbsp;- NFL Pick 'Em URL: {site_url}<br />&nbsp;- Help/Rules: {rules_url}<br /><br />Good Luck!<br />"
	),
	'WEEKLY_RESULTS_REMINDER' => array(
		'title' => 'Last Week Results/Reminder',
		'subject' => "NFL Pick 'Em {previousWeekLabel} Standings/Reminder",
		'message' => "Congratulations this week go to {winners} for winning {previousWeekLabel}.  The winner(s) had {winningScore} out of {possibleScore} picks correct.<br /><br />The current leaders are:<br />{currentLeaders}<br /><br />The most accurate players are:<br />{bestPickRatios}<br /><br />*Reminder* - Please make your picks for {week_label} before {first_game} (Eastern).<br /><br />Links:<br />&nbsp;- NFL Pick 'Em URL: {site_url}<br />&nbsp;- Help/Rules: {rules_url}<br /><br />Good Luck!<br />"
	)
	,
	'FINAL_RESULTS' => array(
		'title' => 'Final Results',
		'subject' => "NFL Pick 'Em {season_year} Final Results",
		'message' => "Congratulations this week go to {winners} for winning week\r\n{previousWeek}. The winner(s) had {winningScore} out of {possibleScore}\r\npicks correct.<br /><br /><span style=\"font-weight: bold;\">Congratulations to {final_winner}</span> for winning NFL Pick 'Em {season_year}!&nbsp; {final_winner} had {final_winningScore} wins and had a pick ratio of {picks}/{possible} ({pickpercent}%).<br /><br />Top Wins:<br />{currentLeaders}<br /><br />The most accurate players are:<br />{bestPickRatios}<br /><br />Thanks for playing, and I hope to see you all again for NFL Pick 'Em {season_year}!"
	)
);

$updatedCount = 0;
foreach ($updates as $key => $data) {
	$sql = "select email_template_key from " . DB_PREFIX . "email_templates where email_template_key = '" . $mysqli->real_escape_string($key) . "'";
	$query = $mysqli->query($sql);
	$exists = ($query && $query->num_rows > 0);
	if ($query) {
		$query->free();
	}

	if ($apply) {
		if ($exists) {
			$updateSql = "update " . DB_PREFIX . "email_templates set " .
				"email_template_title = '" . $mysqli->real_escape_string($data['title']) . "', " .
				"default_subject = '" . $mysqli->real_escape_string($data['subject']) . "', " .
				"default_message = '" . $mysqli->real_escape_string($data['message']) . "', " .
				"subject = '" . $mysqli->real_escape_string($data['subject']) . "', " .
				"message = '" . $mysqli->real_escape_string($data['message']) . "' " .
				"where email_template_key = '" . $mysqli->real_escape_string($key) . "'";
			if (!$mysqli->query($updateSql)) {
				echo "Error updating template " . $key . ": " . $mysqli->error . PHP_EOL;
				continue;
			}
		} else {
			$insertSql = "insert into " . DB_PREFIX . "email_templates (email_template_key, email_template_title, default_subject, default_message, subject, message) values (" .
				"'" . $mysqli->real_escape_string($key) . "', " .
				"'" . $mysqli->real_escape_string($data['title']) . "', " .
				"'" . $mysqli->real_escape_string($data['subject']) . "', " .
				"'" . $mysqli->real_escape_string($data['message']) . "', " .
				"'" . $mysqli->real_escape_string($data['subject']) . "', " .
				"'" . $mysqli->real_escape_string($data['message']) . "')";
			if (!$mysqli->query($insertSql)) {
				echo "Error inserting template " . $key . ": " . $mysqli->error . PHP_EOL;
				continue;
			}
		}
	}
	$updatedCount++;
}

if (!$apply) {
	echo "Dry run complete. Use apply=1 to update templates." . PHP_EOL;
}
echo "Email templates update complete. Templates processed: " . $updatedCount . PHP_EOL;
