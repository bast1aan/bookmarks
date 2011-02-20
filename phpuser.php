<?php

abstract class phpuser {
	
	private static $dbLink;
	
	const HASH_SALT = 'MWwQjQV9qNLR4GIzsPrJDcVVY1YQeDDY';
	
	private static function setupDB() {
		if (self::$dbLink === null)
		{
			require_once('phpuser.secret.php');
			self::$dbLink = mysql_connect($db_host, $db_user, $db_password);
			mysql_select_db($db_database, self::$dbLink);
		}
	}
	
	
	/**
	 * Check the incoming requests for new password request, etcetera
	 *
	 */
	private static function checkRequests() {
		
		if (isset($_REQUEST['requestreset']) && $_REQUEST['requestreset'] == '1')
		{
			if (!isset($_REQUEST['username']) && !isset($_REQUEST['email'])) {
				self::showRequestResetForm();
			} else {
				self::setupDB();
				if (isset($_REQUEST['username']) && $_REQUEST['username'] != '' )
					$query = "SELECT username, email FROM users WHERE username = '" 
						. mysql_real_escape_string($_REQUEST['username'], self::$dbLink) .
						"'";
				else 
					$query = "SELECT username, email FROM users WHERE email = '" 
						. mysql_real_escape_string($_REQUEST['email'], self::$dbLink) .
						"'";
				
				$result = mysql_query($query, self::$dbLink);
				if (mysql_num_rows($result) != 0)
				{
					$row = mysql_fetch_assoc($result);
					
					if ($row['email'] != '') {
						$confirmcode = md5(self::genData(128));
						$salt = md5(self::genData(128));
						$query = "INSERT INTO users_newpassword (username, salt, confirmcode) VALUES ('" . 
								mysql_real_escape_string($row['username'], self::$dbLink) . 
								"', '$salt', '$confirmcode')";
						mysql_query($query, self::$dbLink);
						self::mailPasswordConfirm($row['email'], $row['username'], $confirmcode);
					}
				}
				self::showMessage("An mail has been sent to the corresponding email address of the user.");
			}
			exit;
		}
		
		if (isset($_REQUEST['setnewpassword']) && $_REQUEST['setnewpassword'] == '1') {
			self::setupDB();
			$confirmcode = mysql_real_escape_string($_REQUEST['confirmstring'], self::$dbLink);
			$query = "SELECT username, salt FROM users_newpassword WHERE confirmcode = '$confirmcode'";
			$result = mysql_query($query, self::$dbLink);
			if (mysql_num_rows($result) != 0)
			{
				if (isset($_REQUEST['newpassword']) && $_REQUEST['newpassword'] != '' 
				 && isset($_REQUEST['newpassword_confirm']) && $_REQUEST['newpassword_confirm'] == $_REQUEST['newpassword']) {
					$row = mysql_fetch_assoc($result);
					$username = mysql_real_escape_string($row['username'], self::$dbLink);
					$password = md5($_REQUEST['newpassword'] . $row['salt'] . self::HASH_SALT);
					$query = "UPDATE users SET password = '$password', salt = '{$row['salt']}' WHERE username = '$username'";
					
					if (mysql_query($query, self::$dbLink)) {
						self::showMessage('Password updated. You can now login with your new password.');
						// clean up confirm request
						mysql_query("DELETE FROM users_newpassword WHERE username = '$username'", self::$dbLink);
					} else
						self::showError('Error updating password');
				} else {
					if (isset($_REQUEST['newpassword']) && isset($_REQUEST['newpassword_confirm']) && $_REQUEST['newpassword_confirm'] != $_REQUEST['newpassword'])
						self::showError('Passwords don\'t match');
					self::showNewPasswordForm($confirmcode);
					exit;
				}
			}
		}
	}
	
	/**
	 * Generate random ASCII data with length $length
	 * @param int length = 8
	 * @return string data
	 */
	private static function genData($length = 8)
	{
		$data = '';
		
		for ($i = 0; $i < $length; $i++)
			$data .= chr(rand(33, 126));
		
		return $data;
	}
	
	public static function getDbLink() {
		return self::$dbLink;
	}
	
	public static function logout() {
		session_start();
		unset($_SESSION['username']);
		self::refresh();
	}
	
	// entry point to be called by the using script
	public static function requireUser()
	{
		
		session_start();
		
		if (!isset($_SESSION['username']))
		{
			self::checkRequests();
			
			if (!isset($_REQUEST['username'])) {
				self::showForm();
			} else {
				if (self::login($_REQUEST['username'], $_REQUEST['password']))
					self::refresh();
				else {
					self::showError('Login failed');
					self::showForm();
				}
			}
			exit;
		}
	}
	
	private static function refresh() {
		header('Location: ' . $_SERVER['PHP_SELF']);
		exit;
	}
	
	private static function login($username, $password) {
		self::setupDB();
		$query = "SELECT * FROM users WHERE username = '" . mysql_real_escape_string($username, self::$dbLink) . "'";
		$result = mysql_query($query, self::$dbLink);
		if (mysql_num_rows($result) == 0)
			return false;
		else
		{
			$row = mysql_fetch_assoc($result);
			if (md5($password . $row['salt'] . self::HASH_SALT) == $row['password']) { // password OK
				$_SESSION['username'] = $username;
				$_SESSION['fullname'] = $row['fullname'];
				return true;
			} else
				return false;
		}
	}
	
	private static function showForm() {
		?>
		<form action="<?php print($_SERVER['PHP_SELF']); ?>" method="post" id="loginform">
			<table>
			<tr><td>Username</td><td><input type="text" name="username" /></td></tr>
			<tr><td>Password</td><td><input type="password" name="password" /></td></tr>
			<tr><td colspan="2"><input type="submit" /></td></tr>
			</table>
			<a href="<?php print($_SERVER['PHP_SELF']); ?>?requestreset=1">Reset password</a>
		</form>
		<?php
	}
	
	private static function showError($error) {
		print "<div class=\"login_error\" style=\"color: red;\">$error</div>";
	}
	
	private static function showMessage($message) {
		print "<div class=\"login_message\">$message</div>";
	}
	
	private static function showRequestResetForm() {
		?>
		<form action="<?php print($_SERVER['PHP_SELF']); ?>" method="post" id="requestresetform">
			<input type="hidden" name="requestreset" value="1" />
			<table>
			<tr><td>Username</td><td><input type="text" name="username" /></td></tr>
			<tr><td colspan="2">or</td></tr>
			<tr><td>E-mail</td><td><input type="text" name="email" /></td></tr>
			<tr><td colspan="2"><input type="submit" /></td></tr>
			</table>
		</form>
		<?php
	}
	
	private static function showNewPasswordForm($confirmcode) {
		?>
		<form action="<?php print($_SERVER['PHP_SELF']); ?>" method="post" id="setnewpasswordformform">
			<input type="hidden" name="setnewpassword" value="1" />
			<input type="hidden" name="confirmstring" value="<?php print $confirmcode ; ?>" />
			<table>
			<tr><td>Choose a new password:</td><td><input type="password" name="newpassword" /></td></tr>
			<tr><td>Confirm new password:</td><td><input type="password" name="newpassword_confirm" /></td></tr>
			<tr><td colspan="2"><input type="submit" /></td></tr>
			</table>
		</form>
		<?php
	}
	
	private static function mailPasswordConfirm($to, $username, $confirmstring) {
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
		$subject = "Registration confirmation for user $username";
		$body = <<<EOF
Hi,

You, or someone else, requested from IP {$_SERVER['REMOTE_ADDR']}
a new password, for user:

$username

Use the following address to set a new password:

$protocol://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}?setnewpassword=1&confirmstring=$confirmstring

EOF;
		mail($to, $subject, $body);
	}
	
}

