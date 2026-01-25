<?php
namespace App\Auth;

class Login {
	function Login(){
	}

	function new_user($user_name, $password, $confirm) {
		$confirm = $this->no_injections($confirm);
		$password = $this->no_injections($password);
		$user_name = $this->no_injections($user_name);
		if($confirm === $password && $this->confirm_user($user_name)){
			$this->salt = '';
			$this->secure_password = password_hash($password, PASSWORD_DEFAULT);
			$this->store_user($user_name);
		}
	}

	function store_user($user_name) {
		global $mysqli;
		$salty = $this->salt;
		$user_password_SQL_raw = "INSERT INTO " . DB_PREFIX . "users SET userName = '".$user_name."', password = '".$this->secure_password."', salt = '".$salty."'";
		$user_password_SQL_result = $mysqli->query($user_password_SQL_raw);
	}

	function validate_password() {
		$user_name = $this->no_injections($_POST['username']);
		$password = $this->no_injections($_POST['password']);
		$user = $this->get_user($user_name);
		if (empty($user) || empty($password)) {
			$_SESSION = array();
			header('Location: '.SITE_URL.'login.php?login=failed');
			exit;
		}

		$stored_hash = $user->password;
		$valid = false;
		$needs_rehash = false;

		if ($this->is_password_hash($stored_hash)) {
			$valid = password_verify($password, $stored_hash);
			if ($valid && password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
				$needs_rehash = true;
			}
		} else {
			$legacy_hash = $this->legacy_password_hash($password, $user->salt);
			if (hash_equals($stored_hash, $legacy_hash)) {
				$valid = true;
				$needs_rehash = true;
			}
		}

		if ($valid) {
			if ($needs_rehash) {
				$new_hash = password_hash($password, PASSWORD_DEFAULT);
				$sql = "update " . DB_PREFIX . "users set password = '" . $new_hash . "', salt = '' where userID = " . (int)$user->userID;
				$mysqli = $GLOBALS['mysqli'];
				$mysqli->query($sql);
			}
			$_SESSION['logged'] = 'yes';
			$_SESSION['loggedInUser'] = $user->userName;
			$_SESSION['is_admin'] = $user->is_admin;
			header('Location: '.SITE_URL);
			exit;
		} else {
			$_SESSION = array();
			header('Location: '.SITE_URL.'login.php?login=failed');
			exit;
		}
	}

	function get_user($user_name) {
		global $mysqli;
		$sql = "SELECT * FROM " . DB_PREFIX . "users WHERE userName = '" . $user_name . "' and status = 1";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$user_info = $query->fetch_object() or die('Mysql error: '.$mysqli->error.', $sql: '.$sql);
			return $user_info;
		}
		$query->free;
		return false;
	}

	function get_user_by_id($user_id) {
		global $mysqli;
		$sql = "SELECT * FROM " . DB_PREFIX . "users WHERE userID = '" . $user_id . "' and status = 1";
		$query = $mysqli->query($sql);
		$user_info = $query->fetch_object();
		$query->free;
		return $user_info;
	}

	function confirm_user($old_user){
		$new_user = $this->get_user($old_user);
		if($new_user == null){
			return true;
		}else{
			return false;
		}
	}

	function no_injections($username){
		$injections = array('/(\n+)/i','/(\r+)/i','/(\t+)/i','/(%0A+)/i','/(%0D+)/i','/(%08+)/i','/(%09+)/i');
		$username = preg_replace($injections,'',$username);
		$username = trim($username);
		return $username;
	}

	function logout(){
		$_SESSION = array();
	}

	private function is_password_hash($hash) {
		$info = password_get_info($hash);
		return !empty($info['algo']);
	}

	private function legacy_password_hash($password, $salt) {
		return $this->legacy_encrypt($salt . $this->legacy_encrypt($password));
	}

	private function legacy_encrypt($plain_string) {
		$key = substr(md5('a843l?nv89rjfd}O(jdnsleken0'), 0, 24);
		$block_size = 8;
		$remainder = strlen($plain_string) % $block_size;
		if ($remainder !== 0) {
			$plain_string .= str_repeat("\0", $block_size - $remainder);
		}
		$encrypted = openssl_encrypt($plain_string, 'des-ede3', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
		return base64_encode($encrypted);
	}
}
