<?php
namespace App\Auth;

use PHPMailer;

class UserService {
	public function createUser($username, $password, $firstname, $lastname, $email) {
		global $mysqli;
		$username = $mysqli->real_escape_string(str_replace(' ', '_', $username));

		$sql = "SELECT userName FROM " . DB_PREFIX . "users WHERE userName='".$username."';";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			return array(false, 'User already exists, please try another username.');
		}

		$sql = "SELECT email FROM " . DB_PREFIX . "users WHERE email='".$mysqli->real_escape_string($email)."';";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			return array(false, 'Email address already exists.  If this is your email account, please log in or reset your password.');
		}

		$secure_password = password_hash($password, PASSWORD_DEFAULT);
		$sql = "INSERT INTO " . DB_PREFIX . "users (userName, password, salt, firstname, lastname, email, status)
			VALUES ('".$username."', '".$secure_password."', '', '".$firstname."', '".$lastname."', '".$mysqli->real_escape_string($email)."', 1);";
		$mysqli->query($sql) or die($mysqli->error);

		return array(true, null);
	}

	public function adminCreateUser($userName, $password, $firstname, $lastname, $email) {
		global $mysqli;
		$sql = "SELECT userName FROM " . DB_PREFIX . "users WHERE userName='".$userName."';";
		$query = $mysqli->query($sql);
		if ($query->num_rows != 0) {
			return array(false, 'User already exists, please try another username.');
		}

		$secure_password = password_hash($password, PASSWORD_DEFAULT);
		$sql = "INSERT INTO " . DB_PREFIX . "users (userName, password, salt, firstname, lastname, email, status)
			VALUES ('".$userName."', '".$secure_password."', '', '".$firstname."', '".$lastname."', '".$email."', 1);";
		$mysqli->query($sql) or die($mysqli->error);
		return array(true, null);
	}

	public function adminUpdateUser($userID, $firstname, $lastname, $email, $userName) {
		global $mysqli;
		$sql = "update " . DB_PREFIX . "users ";
		$sql .= "set firstname = '" . $firstname . "', lastname = '" . $lastname . "', email = '" . $email . "', userName = '" . $userName . "' ";
		$sql .= "where userID = " . (int)$userID . ";";
		$mysqli->query($sql) or die('error updating user');
		return true;
	}

	public function adminDeleteUser($userID) {
		global $mysqli;
		$userID = (int)$userID;
		$sql = "delete from " . DB_PREFIX . "users where userID = " . $userID;
		$mysqli->query($sql) or die('error deleting user: ' . $sql);
		$sql = "delete from " . DB_PREFIX . "picks where userID = " . $userID;
		$mysqli->query($sql) or die('error deleting user picks: ' . $sql);
		$sql = "delete from " . DB_PREFIX . "picksummary where userID = " . $userID;
		$mysqli->query($sql) or die('error deleting user picks summary: ' . $sql);
		return true;
	}

	public function adminGetUser($userID) {
		global $mysqli;
		$sql = "select * from " . DB_PREFIX . "users where userID = " . (int)$userID;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			return $query->fetch_assoc();
		}
		return null;
	}

	public function adminListUsers() {
		global $mysqli;
		$sql = "select * from " . DB_PREFIX . "users order by lastname, firstname";
		$query = $mysqli->query($sql);
		$rows = array();
		if ($query->num_rows > 0) {
			while ($row = $query->fetch_assoc()) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	public function resetPassword($firstname, $email) {
		global $mysqli;
		$sql = "SELECT * FROM " . DB_PREFIX . "users WHERE firstname='".$firstname."' and email = '".$email."';";
		$query = $mysqli->query($sql);
		if ($query->num_rows <= 0) {
			return array(false, 'No account matched, please try again.', null);
		}

		$row = $query->fetch_assoc();
		$password = $this->randomString(10);
		$secure_password = password_hash($password, PASSWORD_DEFAULT);
		$sql = "update " . DB_PREFIX . "users set salt = '', password = '".$secure_password."' where firstname='".$firstname."' and email = '".$email."';";
		$mysqli->query($sql) or die($mysqli->error);

		return array(true, null, $row, $password);
	}

	public function sendPasswordResetEmail($email, $username, $password, $fromEmail) {
		$mail = new PHPMailer();
		$mail->IsHTML(true);
		$mail->From = $fromEmail;
		$mail->FromName = 'NFL Pick \'Em Admin';
		$mail->AddAddress($email);
		$mail->Subject = 'NFL Pick \'Em Password';

		$msg = '<p>Your new password for NFL Pick \'Em has been generated.  Your username is: ' . $username . '</p>' . "\n\n";
		$msg .= '<p>Your new password is: ' . $password . '</p>' . "\n\n";
		$msg .= '<a href="' . SITE_URL . 'login.php">Click here to sign in</a>.</p>';

		$mail->Body = $msg;
		$mail->AltBody = strip_tags($msg);
		$mail->Send();
	}

	public function sendSignupConfirmation($email, $fromEmail, $confirmToken) {
		$mail = new PHPMailer();
		$mail->IsHTML(true);
		$mail->From = $fromEmail;
		$mail->FromName = 'NFL Pick \'Em Admin';
		$mail->AddAddress($email);
		$mail->Subject = 'NFL Pick \'Em Confirmation';

		$mail->Body = '<p>Thank you for signing up for the NFL Pick \'Em Pool.  Please click the below link to confirm your account:<br />' . "\n" .
			SITE_URL . 'signup.php?confirm=' . $confirmToken . '</p>';
		$mail->Send();
	}

	public function updateUserProfile($userID, $firstname, $lastname, $email, $password = '') {
		global $mysqli;
		$fields = array(
			"firstname = '" . $firstname . "'",
			"lastname = '" . $lastname . "'",
			"email = '" . $email . "'"
		);

		if (!empty($password)) {
			$secure_password = password_hash($password, PASSWORD_DEFAULT);
			$fields[] = "password = '" . $secure_password . "'";
			$fields[] = "salt = ''";
		}

		$sql = "update " . DB_PREFIX . "users ";
		$sql .= "set " . implode(', ', $fields) . " ";
		$sql .= "where userID = " . (int)$userID . ";";
		$mysqli->query($sql) or die($mysqli->error);
		return true;
	}

	private function randomString($length) {
		$string = md5(time());
		$highest_startpoint = 32-$length;
		return substr($string,rand(0,$highest_startpoint),$length);
	}
}
