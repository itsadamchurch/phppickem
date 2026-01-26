<?php
function phppickem_is_docker() {
	if (file_exists('/.dockerenv')) {
		return true;
	}
	if (is_readable('/proc/1/cgroup')) {
		$cgroup = file_get_contents('/proc/1/cgroup');
		if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'containerd') !== false) {
			return true;
		}
	}
	return false;
}

if (!phppickem_is_docker()) {
	if (!getenv('DB_HOSTNAME') || getenv('DB_HOSTNAME') === 'db') {
		putenv('DB_HOSTNAME=127.0.0.1');
		$_ENV['DB_HOSTNAME'] = '127.0.0.1';
	}
	if (!getenv('DB_PORT')) {
		putenv('DB_PORT=3307');
		$_ENV['DB_PORT'] = '3307';
	}
}
