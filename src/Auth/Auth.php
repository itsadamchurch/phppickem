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

	public function validateLogin($username, $password) {
		return $this->login->validate_credentials($username, $password);
	}

	public function loginUser($user) {
		$_SESSION['logged'] = 'yes';
		$_SESSION['loggedInUser'] = $user->userName;
		$_SESSION['is_admin'] = $user->is_admin;
	}

	public function clearSession() {
		$_SESSION = array();
	}

	public function logout() {
		unset($_SESSION['logged']);
		unset($_SESSION['loggedInUser']);
		unset($_SESSION['is_admin']);
	}
}
