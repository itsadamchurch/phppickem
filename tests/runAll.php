<?php
// Runs all test scripts except seed/cleanup.
// Usage:
// php tests/runAll.php --base=http://localhost:8080 --user=bob --pass=test1234 --admin_user=admin --admin_pass=admin --user2=sal --pass2=test1234

$args = $argv;
array_shift($args);

$tests = array(
	__DIR__ . '/smokeTest.php',
	__DIR__ . '/dataPresenceTest.php',
	__DIR__ . '/dataAccuracyTest.php',
	__DIR__ . '/picksWinnerTest.php'
);

$failures = array();
$useColor = function_exists('posix_isatty') ? @posix_isatty(STDOUT) : true;
$green = $useColor ? "\033[32m" : '';
$red = $useColor ? "\033[31m" : '';
$blue = $useColor ? "\033[34m" : '';
$yellow = $useColor ? "\033[33m" : '';
$reset = $useColor ? "\033[0m" : '';

foreach ($tests as $test) {
	$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test);
	foreach ($args as $arg) {
		$cmd .= ' ' . escapeshellarg($arg);
	}
	echo $blue . "==> " . basename($test) . $reset . PHP_EOL;
	passthru($cmd, $exitCode);
	if ($exitCode !== 0) {
		$failures[] = basename($test) . ' failed (exit ' . $exitCode . ')';
		echo $red . "❌ FAILED: " . basename($test) . $reset . PHP_EOL;
	} else {
		echo $green . "✅ PASSED: " . basename($test) . $reset . PHP_EOL;
	}
}

if (!empty($failures)) {
	fwrite(STDERR, $red . "❌ Run-all failed:" . $reset . "\n");
	foreach ($failures as $fail) {
		fwrite(STDERR, $yellow . "- " . $fail . $reset . "\n");
	}
	exit(1);
}

echo $green . "✅ All tests passed." . $reset . PHP_EOL;
