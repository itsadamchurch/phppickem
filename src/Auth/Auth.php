<?php
namespace App\Auth;

class Auth {
	private $login;

	public function __construct($login) {
		$this->login = $login;
	}

	public function getAdminUser() {
		return $this->login->get_user('admin');
	}

	public function enforceLogin(array $okFiles) {
		if (!in_array(basename($_SERVER['PHP_SELF']), $okFiles)) {
			if (empty($_SESSION['logged']) || $_SESSION['logged'] !== 'yes') {
				header('Location: login.php');
				exit;
			} else if (!empty($_SESSION['loggedInUser'])) {
				return $this->login->get_user($_SESSION['loggedInUser']);
			}
		}

		return null;
	}
}
