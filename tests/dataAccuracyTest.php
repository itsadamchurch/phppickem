<?php
require(__DIR__ . '/bootstrap.php');
require(__DIR__ . '/../includes/application_top.php');

// Data accuracy test for standings + results (current week).
// Usage:
// php tests/dataAccuracyTest.php --base=http://localhost:8080 --user=bob --pass=test1234

$args = array();
if (PHP_SAPI === 'cli') {
	foreach ($argv as $arg) {
		if (strpos($arg, '--') === 0) {
			$parts = explode('=', substr($arg, 2), 2);
			$args[$parts[0]] = isset($parts[1]) ? $parts[1] : '1';
		}
	}
}

$base = isset($args['base']) ? rtrim($args['base'], '/') : rtrim((getenv('SMOKE_BASE_URL') ?: 'http://localhost:8080'), '/');
$user = isset($args['user']) ? $args['user'] : (getenv('SMOKE_USER') ?: 'bob');
$pass = isset($args['pass']) ? $args['pass'] : (getenv('SMOKE_PASS') ?: 'test1234');

if ($base === '') {
	fwrite(STDERR, "Missing base URL.\n");
	exit(1);
}

$failures = array();
$verbose = true;

function request($method, $url, $data = null, $cookies = array(), $maxRedirects = 5) {
	$headers = array(
		'User-Agent: phppickem-data-accuracy/1.0'
	);
	if (!empty($cookies)) {
		$cookieParts = array();
		foreach ($cookies as $k => $v) {
			$cookieParts[] = $k . '=' . $v;
		}
		$headers[] = 'Cookie: ' . implode('; ', $cookieParts);
	}

	$options = array(
		'http' => array(
			'method' => $method,
			'header' => implode("\r\n", $headers) . "\r\n",
			'ignore_errors' => true
		)
	);
	if ($method === 'POST' && is_array($data)) {
		$body = http_build_query($data);
		$options['http']['content'] = $body;
		$options['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$options['http']['header'] .= "Content-Length: " . strlen($body) . "\r\n";
	}

	$context = stream_context_create($options);
	$body = @file_get_contents($url, false, $context);
	$headersOut = isset($http_response_header) ? $http_response_header : array();
	$status = 0;
	$newCookies = array();
	foreach ($headersOut as $headerLine) {
		if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $headerLine, $m)) {
			$status = (int)$m[1];
		}
		if (stripos($headerLine, 'Set-Cookie:') === 0) {
			$cookieStr = trim(substr($headerLine, strlen('Set-Cookie:')));
			$cookiePair = explode(';', $cookieStr, 2)[0];
			if (strpos($cookiePair, '=') !== false) {
				list($ck, $cv) = explode('=', $cookiePair, 2);
				$newCookies[$ck] = $cv;
			}
		}
	}

	// Follow redirects
	if (in_array($status, array(301, 302, 303, 307, 308), true) && $maxRedirects > 0) {
		$location = '';
		foreach ($headersOut as $headerLine) {
			if (stripos($headerLine, 'Location:') === 0) {
				$location = trim(substr($headerLine, strlen('Location:')));
				break;
			}
		}
		if ($location !== '') {
			if (strpos($location, 'http') !== 0) {
				$base = rtrim($url, '/');
				$location = $base . '/' . ltrim($location, '/');
			}
			$mergedCookies = array_merge($cookies, $newCookies);
			return request('GET', $location, null, $mergedCookies, $maxRedirects - 1);
		}
	}

	return array(
		'status' => $status,
		'body' => $body !== false ? $body : '',
		'cookies' => array_merge($cookies, $newCookies)
	);
}

function loginUser($base, $username, $password, $verbose, &$failures) {
	$cookies = array();
	request('GET', $base . '/logout.php', null, $cookies);
	$loginUrl = $base . '/login.php';
	$resp = request('GET', $loginUrl, null, $cookies);
	$cookies = $resp['cookies'];
	if ($verbose) {
		echo "[login page] status=" . $resp['status'] . " user=" . $username . PHP_EOL;
	}
	$resp = request('POST', $loginUrl, array('username' => $username, 'password' => $password), $cookies);
	$cookies = $resp['cookies'];
	if ($verbose) {
		echo "[login submit] status=" . $resp['status'] . " user=" . $username . PHP_EOL;
	}
	if (strpos($resp['body'], 'Login failed') !== false) {
		$failures[] = 'login failed for user ' . $username;
	}
	return $cookies;
}

function assertContains($name, $haystack, $needle, &$failures) {
	if (strpos($haystack, $needle) === false) {
		$failures[] = $name . ' did not contain expected text: ' . $needle;
	}
}

function assertMatches($name, $haystack, $pattern, &$failures) {
	if (!preg_match($pattern, $haystack)) {
		$failures[] = $name . ' did not match expected pattern';
	}
}

if ($verbose) {
	echo "Data accuracy test base: " . $base . PHP_EOL;
	echo "Login as: " . $user . PHP_EOL;
}

$cookies = loginUser($base, $user, $pass, $verbose, $failures);

// Compute expected standings values
$stats = $statsService->calculateStats();
$playerTotals = $stats['playerTotals'];
$possibleScoreTotal = $stats['possibleScoreTotal'];

$userRow = null;
foreach ($playerTotals as $playerID => $row) {
	if ($row['userName'] === $user) {
		$userRow = $row;
		$userRow['userID'] = $playerID;
		break;
	}
}
if (!$userRow) {
	$failures[] = 'user not found in player totals: ' . $user;
}

$playoffWins = 0;
$playoffPickCount = 0;
$sql = "select p.pickTeamID, s.homeID, s.visitorID, s.homeScore, s.visitorScore ";
$sql .= "from " . DB_PREFIX . "playoff_picks p ";
$sql .= "inner join " . DB_PREFIX . "playoff_schedule s on p.gameID = s.playoffGameID ";
$sql .= "where p.userID = " . (int)$userRow['userID'] . " and s.is_bye = 0";
$query = $mysqli->query($sql);
while ($row = $query->fetch_assoc()) {
	$playoffPickCount++;
	$winnerID = '';
	$homeScore = (int)$row['homeScore'];
	$visitorScore = (int)$row['visitorScore'];
	if ($homeScore + $visitorScore > 0) {
		if ($homeScore > $visitorScore) $winnerID = $row['homeID'];
		if ($visitorScore > $homeScore) $winnerID = $row['visitorID'];
	}
	if (!empty($winnerID) && $row['pickTeamID'] == $winnerID) {
		$playoffWins += 1;
	}
}
if ($query) {
	$query->free();
}

$totalWins = $userRow ? ($userRow['wins'] + $playoffWins) : 0;
$pickRatio = $userRow ? ($userRow['score'] . '/' . $possibleScoreTotal) : '0/0';
$pickPercentage = $userRow ? number_format((($userRow['score'] / max($possibleScoreTotal, 1)) * 100), 2) . '%' : '0.00%';

// Standings page validations
$standings = request('GET', $base . '/standings.php', null, $cookies);
if ($verbose) {
	echo "[standings] status=" . $standings['status'] . PHP_EOL;
}
assertContains('standings', $standings['body'], $user, $failures);
assertContains('standings', $standings['body'], $pickRatio . ' (' . $pickPercentage . ')', $failures);
assertContains('standings', $standings['body'], '>' . $totalWins . '<', $failures);

// Weekly stats (completed weeks only)
$lastCompletedWeek = $statsService->getLastCompletedWeek();
if ($lastCompletedWeek > 0 && !empty($stats['weekStats'])) {
	foreach ($stats['weekStats'] as $week => $weekRow) {
		if ((int)$week > (int)$lastCompletedWeek) {
			continue;
		}
		$scoreStr = $weekRow['highestScore'] . '/' . $weekRow['possibleScore'];
		assertContains('weekly stats week ' . $week, $standings['body'], $scoreStr, $failures);
	}
}

// Playoff stats (if seeded)
if ($playoffPickCount > 0) {
	assertContains('playoff stats', $standings['body'], 'Playoff Stats', $failures);
	assertContains('playoff stats', $standings['body'], '>' . $playoffWins . '<', $failures);
}

// Results page validations (current week)
$currentWeek = (int)$statsService->getCurrentWeek();
$results = request('GET', $base . '/results.php?week=' . $currentWeek, null, $cookies);
if ($verbose) {
	echo "[results week " . $currentWeek . "] status=" . $results['status'] . PHP_EOL;
}
assertContains('results', $results['body'], $user, $failures);

$games = array();
$sql = "select gameID, homeID, visitorID, homeScore, visitorScore from " . DB_PREFIX . "schedule where weekNum = " . $currentWeek;
$query = $mysqli->query($sql);
while ($row = $query->fetch_assoc()) {
	$games[] = $row;
}
if ($query) {
	$query->free();
}

$correct = 0;
$gameCount = count($games);
if ($gameCount > 0) {
	foreach ($games as $game) {
		$pickQuery = $mysqli->query("select pickID from " . DB_PREFIX . "picks where userID = " . (int)$userRow['userID'] . " and gameID = " . (int)$game['gameID']);
		$pickID = null;
		if ($pickQuery && $pickQuery->num_rows > 0) {
			$pickRow = $pickQuery->fetch_assoc();
			$pickID = $pickRow['pickID'];
		}
		if ($pickQuery) {
			$pickQuery->free();
		}
		$winnerID = null;
		if ((int)$game['homeScore'] > (int)$game['visitorScore']) {
			$winnerID = $game['homeID'];
		} elseif ((int)$game['visitorScore'] > (int)$game['homeScore']) {
			$winnerID = $game['visitorID'];
		}
		if ($winnerID !== null && $pickID === $winnerID) {
			$correct++;
		}
	}
	$percent = number_format((($correct / $gameCount) * 100), 2);
	$scoreText = $correct . '/' . $gameCount . ' (' . $percent . '%)';
	assertContains('results', $results['body'], $scoreText, $failures);
}

if (!empty($failures)) {
	fwrite(STDERR, "Data accuracy test failed:\n");
	foreach ($failures as $fail) {
		fwrite(STDERR, "- " . $fail . "\n");
	}
	exit(1);
}

echo "Data accuracy test passed.\n";
