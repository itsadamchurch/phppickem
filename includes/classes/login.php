<?php
if (is_readable(__DIR__ . '/../../vendor/autoload.php')) {
	require_once __DIR__ . '/../../vendor/autoload.php';
}

if (!class_exists('App\\Auth\\Login') && is_readable(__DIR__ . '/../../src/Auth/Login.php')) {
	require_once __DIR__ . '/../../src/Auth/Login.php';
}

if (class_exists('App\\Auth\\Login')) {
	class_alias('App\\Auth\\Login', 'Login');
}
