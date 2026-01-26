<?php
// Basic CLI smoke test that logs in and checks key pages + admin visibility.
// Usage:
// php tests/smokeTest.php --base=http://localhost:8080 --user=bob --pass=test1234 --admin_user=admin --admin_pass=admin --user2=sal --pass2=test1234

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
$adminUser = isset($args['admin_user']) ? $args['admin_user'] : (getenv('SMOKE_ADMIN_USER') ?: 'admin');
$adminPass = isset($args['admin_pass']) ? $args['admin_pass'] : (getenv('SMOKE_ADMIN_PASS') ?: 'admin123');
$nonAdminUser = isset($args['user2']) ? $args['user2'] : (getenv('SMOKE_USER2') ?: 'sal');
$nonAdminPass = isset($args['pass2']) ? $args['pass2'] : (getenv('SMOKE_PASS2') ?: 'test1234');

if ($base === '') {
	fwrite(STDERR, "Missing base URL.\n");
	exit(1);
}

$cookies = array();
$failures = array();
$verbose = true;

function request($method, $url, $data = null, $cookies = array(), $maxRedirects = 5) {
	$headers = array(
		'User-Agent: phppickem-smoke/1.0'
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

function assertPage($name, $resp, $needle, &$failures) {
	if ($resp['status'] < 200 || $resp['status'] >= 400) {
		$failures[] = $name . ' returned status ' . $resp['status'];
		return;
	}
	if ($needle && strpos($resp['body'], $needle) === false) {
		$failures[] = $name . ' did not contain expected text: ' . $needle;
	}
}

function assertContains($name, $resp, $needle, &$failures) {
	if (strpos($resp['body'], $needle) === false) {
		$failures[] = $name . ' did not contain expected text: ' . $needle;
	}
}

function assertNotContains($name, $resp, $needle, &$failures) {
	if (strpos($resp['body'], $needle) !== false) {
		$failures[] = $name . ' should not contain text: ' . $needle;
	}
}

function loginUser($base, $username, $password, $verbose, &$failures) {
	$cookies = array();
	// ensure clean session
	request('GET', $base . '/logout.php', null, $cookies);
	$loginUrl = $base . '/login.php';
	$resp = request('GET', $loginUrl, null, $cookies);
	$cookies = $resp['cookies'];
	if ($verbose) {
		echo "[login page] status=" . $resp['status'] . " user=" . $username . PHP_EOL;
	}
	assertPage('login page', $resp, 'NFL Pick', $failures);

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

if ($verbose) {
	echo "Smoke test base: " . $base . PHP_EOL;
}

// Login (default user)
$cookies = loginUser($base, $user, $pass, $verbose, $failures);

$pages = array(
	array('Home', $base . '/', 'Your Picks At A Glance'),
	array('Entry Form', $base . '/entry_form.php', 'Make Your Picks'),
	array('Results', $base . '/results.php', 'Go to week'),
	array('Standings', $base . '/standings.php', 'Standings')
);

foreach ($pages as $page) {
	$resp = request('GET', $page[1], null, $cookies);
	$cookies = $resp['cookies'];
	if ($verbose) {
		echo "[" . $page[0] . "] " . $page[1] . " status=" . $resp['status'] . PHP_EOL;
	}
	assertPage($page[0], $resp, $page[2], $failures);
}

// Admin nav should be visible for admin user
$adminCookies = loginUser($base, $adminUser, $adminPass, $verbose, $failures);
$resp = request('GET', $base . '/', null, $adminCookies);
if ($verbose) {
	echo "[admin home] status=" . $resp['status'] . PHP_EOL;
}
assertContains('admin nav', $resp, 'Admin', $failures);
assertContains('admin nav', $resp, 'scores.php', $failures);
assertContains('admin nav', $resp, 'send_email.php', $failures);
assertContains('admin nav', $resp, 'users.php', $failures);
assertContains('admin nav', $resp, 'schedule_edit.php', $failures);
assertContains('admin nav', $resp, 'email_templates.php', $failures);

// Admin nav should not be visible for non-admin user
$adminCookies = request('GET', $base . '/logout.php', null, $adminCookies)['cookies'];
$nonAdminCookies = loginUser($base, $nonAdminUser, $nonAdminPass, $verbose, $failures);
$resp = request('GET', $base . '/', null, $nonAdminCookies);
if ($verbose) {
	echo "[user2 home] status=" . $resp['status'] . PHP_EOL;
}
assertNotContains('non-admin nav', $resp, 'Admin', $failures);
assertNotContains('non-admin nav', $resp, 'scores.php', $failures);
assertNotContains('non-admin nav', $resp, 'send_email.php', $failures);
assertNotContains('non-admin nav', $resp, 'users.php', $failures);
assertNotContains('non-admin nav', $resp, 'schedule_edit.php', $failures);
assertNotContains('non-admin nav', $resp, 'email_templates.php', $failures);

// Verify non-admin cannot access admin pages
$adminPages = array(
	array('scores.php', 'Enter Scores'),
	array('send_email.php', 'Send Email'),
	array('users.php', 'Update Users'),
	array('schedule_edit.php', 'Edit Schedule'),
	array('email_templates.php', 'Email Templates')
);
foreach ($adminPages as $adminPage) {
	$resp = request('GET', $base . '/' . $adminPage[0], null, $nonAdminCookies);
	if ($verbose) {
		echo "[user2 admin check] " . $adminPage[0] . " status=" . $resp['status'] . PHP_EOL;
	}
	if (strpos($resp['body'], $adminPage[1]) !== false) {
		$failures[] = 'user2 should not access ' . $adminPage[0];
	}
}

if (!empty($failures)) {
	fwrite(STDERR, "Smoke test failed:\n");
	foreach ($failures as $fail) {
		fwrite(STDERR, "- " . $fail . "\n");
	}
	exit(1);
}

echo "Smoke test passed.\n";
