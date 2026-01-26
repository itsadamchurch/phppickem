<?php
require(__DIR__ . '/bootstrap.php');
// Data presence test for standings + results pages.
// Usage:
// php tests/dataPresenceTest.php --base=http://localhost:8080 --user=bob --pass=test1234

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

$cookies = array();
$failures = array();
$verbose = true;

function request($method, $url, $data = null, $cookies = array(), $maxRedirects = 5) {
	$headers = array(
		'User-Agent: phppickem-data-test/1.0'
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

function assertContainsAny($name, $resp, $needles, &$failures) {
	foreach ($needles as $needle) {
		if ($needle && strpos($resp['body'], $needle) !== false) {
			return;
		}
	}
	$failures[] = $name . ' did not contain any expected text: ' . implode(', ', $needles);
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

if ($verbose) {
	echo "Data presence test base: " . $base . PHP_EOL;
	echo "Login as: " . $user . PHP_EOL;
}

$cookies = loginUser($base, $user, $pass, $verbose, $failures);

$resp = request('GET', $base . '/standings.php', null, $cookies);
if ($verbose) {
	echo "[standings data] status=" . $resp['status'] . PHP_EOL;
}
assertContainsAny('standings data', $resp, array($user, 'Bob Loblaw'), $failures);

$resp = request('GET', $base . '/results.php', null, $cookies);
if ($verbose) {
	echo "[results data] status=" . $resp['status'] . PHP_EOL;
}
assertContainsAny('results data', $resp, array($user, 'Bob Loblaw'), $failures);

if (!empty($failures)) {
	fwrite(STDERR, "Data presence test failed:\n");
	foreach ($failures as $fail) {
		fwrite(STDERR, "- " . $fail . "\n");
	}
	exit(1);
}

echo "Data presence test passed.\n";
