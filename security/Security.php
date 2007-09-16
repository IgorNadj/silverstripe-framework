<?php

/**
 * Implements a basic security model
 */
class Security extends Controller {

	/**
	 * @var $username String Only used in dev-mode by setDefaultAdmin()
	 */
	protected static $username;

	/**
	 * @var $password String Only used in dev-mode by setDefaultAdmin()
	 */
	protected static $password;

	/**
	 * If set to TRUE to prevent sharing of the session across several sites
	 * in the domain.
	 *
	 * @var bool
	 */
	protected static $strictPathChecking = false;

	/**
	 * Should passwords be stored encrypted?
	 *
	 * @var bool
	 */
	protected static $encryptPasswords = true;

	/**
	 * The password encryption algorithm to use if {@link $encryptPasswords}
	 * is set to TRUE.
	 *
	 * @var string
	 */
	protected static $encryptionAlgorithm = 'sha1';

	/**
	 * Should a salt be used for the password encryption?
	 *
	 * @var bool
	 */
	protected static $useSalt = true;


	/**
	 * Register that we've had a permission failure trying to view the given page
	 *
	 * This will redirect to a login page.
	 * If you don't provide a messageSet, a default will be used.
	 *
	 * @param $page The controller that you were on to cause the permission
	 *              failure.
	 * @param string|array $messageSet The message to show to the user. This
	 *                                  can be a string, or a map of different
	 *                                  messages for different contexts.
	 *                                  If you pass an array, you can use the
	 *                                  following keys:
	 *                                    - default: The default message
	 *                                    - logInAgain: The message to show
	 *                                                  if the user has just
	 *                                                  logged out and the
	 *                                    - alreadyLoggedIn: The message to
	 *                                                       show if the user
	 *                                                       is already logged
	 *                                                       in and lacks the
	 *                                                       permission to
	 *                                                       access the item.
	 */
	static function permissionFailure($page, $messageSet = null) {
		// Prepare the messageSet provided
		if(!$messageSet) {
			$messageSet = array(
				'default' => "That page is secured. Enter your credentials below and we will send you right along.",
				'alreadyLoggedIn' => "You don't have access to this page.  If you have another account that can access that page, you can log in below.",
				'logInAgain' => "You have been logged out.  If you would like to log in again, enter your credentials below.",
			);
		} else if(!is_array($messageSet)) {
			$messageSet = array('default' => $messageSet);
		}

		// Work out the right message to show
		if(Member::currentUserID()) {
			// user_error( 'PermFailure with member', E_USER_ERROR );

			$message = $messageSet['alreadyLoggedIn']
										? $messageSet['alreadyLoggedIn']
										: $messageSet['default'];

			if($member = Member::currentUser())
				$member->logout();

		} else if(substr(Director::history(),0,15) == 'Security/logout') {
			$message = $messageSet['logInAgain']
										? $messageSet['logInAgain']
										: $messageSet['default'];

		} else {
			$message = $messageSet['default'];
		}

		Session::set("Security.Message.message", $message);
		Session::set("Security.Message.type", 'warning');

		Session::set("BackURL", $_SERVER['REQUEST_URI']);
		
		if(Director::is_ajax()) {
			die('NOTLOGGEDIN:');
		} else {
			Director::redirect("Security/login");
		}
		return;
	}


  /**
	 * Get the login form to process according to the submitted data
	 */
	protected function LoginForm() {
		if(is_array($_REQUEST) && isset($_REQUEST['AuthenticationMethod']))
		{
			$authenticator = trim($_REQUEST['AuthenticationMethod']);

			$authenticators = Authenticator::get_authenticators();
			if(in_array($authenticator, $authenticators)) {
				return call_user_func(array($authenticator, 'get_login_form'),
															$this);
			}
		}

		user_error('Passed invalid authentication method', E_USER_ERROR);
	}


  /**
	 * Get the login forms for all available authentication methods
	 *
	 * @return array Returns an array of available login forms (array of Form
	 *               objects).
	 *
	 * @todo Check how to activate/deactivate authentication methods
	 */
	protected function GetLoginForms()
	{
		$forms = array();

		$authenticators = Authenticator::get_authenticators();
		foreach($authenticators as $authenticator) {
		  array_push($forms,
								 call_user_func(array($authenticator, 'get_login_form'),
																$this));
		}

		return $forms;
	}


	/**
	 * Get a link to a security action
	 *
	 * @param string $action Name of the action
	 * @return string Returns the link to the given action
	 */
	public static function Link($action = null) {
		return "Security/$action";
	}


	/**
	 * Log the currently logged in user out
	 *
	 * @param bool $redirect Redirect the user back to where they came.
	 *                         - If it's false, the code calling logout() is
	 *                           responsible for sending the user where-ever
	 *                           they should go.
	 */
	public function logout($redirect = true) {
		if($member = Member::currentUser())
			$member->logOut();

		if($redirect)
			Director::redirectBack();
	}


	/**
	 * Show the "login" page
	 *
	 * @return string Returns the "login" page as HTML code.
	 */
	public function login() {
		Requirements::javascript("jsparty/behaviour.js");
		Requirements::javascript("jsparty/loader.js");
		Requirements::javascript("jsparty/prototype.js");
		Requirements::javascript("jsparty/prototype_improvements.js");
		Requirements::javascript("jsparty/scriptaculous/effects.js");

		$customCSS = project() . '/css/tabs.css';
		if(Director::fileExists($customCSS)) {
			Requirements::css($customCSS);
		}

		$tmpPage = new Page();
		$tmpPage->Title = "Log in";
		$tmpPage->URLSegment = "Security";

		$controller = new Page_Controller($tmpPage);
		$controller->init();
		//Controller::$currentController = $controller;

		if(SSViewer::hasTemplate("Security_login")) {
			return $controller->renderWith(array("Security_login", "Page"));

		} else {
			$forms = $this->GetLoginForms();
			$content = '';
			foreach($forms as $form)
				$content .= $form->forTemplate();

			foreach($forms as $form) {
				$content .= "<li><a href=\"$link_base#{$form->FormName()}_tab\">{$form->getAuthenticator()->get_name()}</a></li>\n";
				$content_forms .= '<div class="tab" id="' . $form->FormName() . '_tab">' . $form->forTemplate() . "</div>\n";
			}

			$content .= "</ul>\n" . $content_forms . "\n</div>\n";

			if(strlen($message = Session::get('Security.Message.message')) > 0) {
				$message_type = Session::get('Security.Message.type');
				if($message_type == 'bad') {
					$message = "<p class=\"message $message_type\">$message</p>";
				} else {
					$message = "<p>$message</p>";
				}

				$customisedController = $controller->customise(array(
					"Content" => $message,
					"Form" => $content
				));
			} else {
				$customisedController = $controller->customise(array(
					"Content" => $content
				));
			}

			return $customisedController->renderWith("Page");
		}
	}


	/**
	 * Show the "lost password" page
	 *
	 * @return string Returns the "lost password" page as HTML code.
	 */
	public function lostpassword() {
		Requirements::javascript('jsparty/prototype.js');
		Requirements::javascript('jsparty/behaviour.js');
		Requirements::javascript('jsparty/loader.js');
		Requirements::javascript('jsparty/prototype_improvements.js');
		Requirements::javascript('jsparty/scriptaculous/effects.js');

		$tmpPage = new Page();
		$tmpPage->Title = 'Lost Password';
		$tmpPage->URLSegment = 'Security';
		$controller = new Page_Controller($tmpPage);

		$customisedController = $controller->customise(array(
			'Content' =>
				'<p>Enter your e-mail address and we will send you a link with ' .
				'which you can reset your password</p>',
			'Form' => $this->LostPasswordForm(),
		));
		
		//Controller::$currentController = $controller;
		return $customisedController->renderWith("Page");
	}


	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function LostPasswordForm() {
		return new MemberLoginForm($this, 'LostPasswordForm',
			new FieldSet(new EmailField('Email', 'E-mail address')),
			new FieldSet(new FormAction('forgotPassword',
																	'Send me the password reset link')),
			false);
	}


	/**
	 * Show the "password sent" page
	 *
	 * @return string Returns the "password sent" page as HTML code.
	 */
	public function passwordsent() {
		Requirements::javascript('jsparty/behaviour.js');
		Requirements::javascript('jsparty/loader.js');
		Requirements::javascript('jsparty/prototype.js');
		Requirements::javascript('jsparty/prototype_improvements.js');
		Requirements::javascript('jsparty/scriptaculous/effects.js');

		$tmpPage = new Page();
		$tmpPage->Title = 'Lost Password';
		$tmpPage->URLSegment = 'Security';
		$controller = new Page_Controller($tmpPage);

		$email = Convert::raw2xml($this->urlParams['ID']);
		$customisedController = $controller->customise(array(
			'Title' => "Password reset link sent to '$email'",
			'Content' =>
				"<p>Thank you! The password reset link has been sent to '$email'.</p>",
		));
		
		//Controller::$currentController = $controller;
		return $customisedController->renderWith("Page");
	}


	/**
	 * Create a link to the password reset form
	 *
	 * @param string $autoLoginHash The auto login hash
	 */
	public static function getPasswordResetLink($autoLoginHash) {
		$autoLoginHash = urldecode($autoLoginHash);
		return self::Link('changepassword') . "?h=$autoLoginHash";
	}

	/**
	 * Show the "change password" page
	 *
	 * @return string Returns the "change password" page as HTML code.
	 */
	public function changepassword() {
		$tmpPage = new Page();
		$tmpPage->Title = 'Change your password';
		$tmpPage->URLSegment = 'Security';
		$controller = new Page_Controller($tmpPage);

		if(isset($_REQUEST['h']) && Member::autoLoginHash($_REQUEST['h'])) {
			// The auto login hash is valid, store it for the change password form
			Session::set('AutoLoginHash', $_REQUEST['h']);

			$customisedController = $controller->customise(array(
				'Content' =>
					'<p>Please enter a new password.</p>',
				'Form' => $this->ChangePasswordForm(),
			));

		} elseif(Member::currentUser()) {
			// let a logged in user change his password
			$customisedController = $controller->customise(array(
				'Content' => '<p>You can change your password below.</p>',
				'Form' => $this->ChangePasswordForm()));

		} else {
			// show an error message if the auto login hash is invalid and the
			// user is not logged in
			if(isset($_REQUEST['h'])) {
				$customisedController = $controller->customise(array('Content' =>
					"<p>The password reset link is invalid or expired.</p>\n" .
						'<p>You can request a new one <a href="' .
						$this->Link('lostpassword') .
						'">here</a> or change your password after you <a href="' .
						$this->link('login') . '">logged in</a>.</p>'));
			} else {
				self::permissionFailure($this,
					'You must be logged in in order to change your password!');
				return;
			}
		}

		Controller::$currentController = $controller;
		return $customisedController->renderWith('Page');
	}


	/**
	 * Factory method for the lost password form
	 *
	 * @return Form Returns the lost password form
	 */
	public function ChangePasswordForm() {
		return new ChangePasswordForm($this, 'ChangePasswordForm');
	}


	/**
	 * Authenticate using the given email and password, returning the
	 * appropriate member object if
	 *
	 * @return bool|Member Returns FALSE if authentication fails, otherwise
	 *                     the member object
	 */
	public static function authenticate($RAW_email, $RAW_password) {
		$SQL_email = Convert::raw2sql($RAW_email);
		$SQL_password = Convert::raw2sql($RAW_password);

		// Default login (see {@setDetaultAdmin()})
		if(($RAW_email == self::$username) && ($RAW_password == self::$password)
				&& !empty(self::$username) && !empty(self::$password)) {
			$member = self::findAnAdministrator();
		} else {
			$member = DataObject::get_one("Member",
				"Email = '$SQL_email' And Password = '$SQL_password'");
		}

		return $member;
	}


	/**
	 * Return a member with administrator privileges
	 *
	 * @return Member Returns a member object that has administrator
	 *                privileges.
	 */
	static function findAnAdministrator($username = 'admin', $password = 'password') {
		$permission = DataObject::get_one("Permission", "`Code` = 'ADMIN'", true, "ID");

		$adminGroup = null;
		if($permission) $adminGroup = DataObject::get_one("Group", "`ID` = '{$permission->GroupID}'", true, "ID");
		
		if($adminGroup) {
			if($adminGroup->Members()->First()) {
				$member = $adminGroup->Members()->First();
			}
		}

		if(!$adminGroup) {
			$adminGroup = Object::create('Group');
			$adminGroup->Title = 'Administrators';
			$adminGroup->Code = "administrators";
			$adminGroup->write();
			Permission::grant($adminGroup->ID, "ADMIN");
		}
		
		if(!isset($member)) {
			$member = Object::create('Member');
			$member->FirstName = $member->Surname = 'Admin';
			$member->Email = $username;
			$member->Password = $password;
			$member->write();
			$member->Groups()->add($adminGroup);
		}

		return $member;
	}


	/**
	 * This will set a static default-admin (e.g. "td") which is not existing
	 * as a database-record. By this workaround we can test pages in dev-mode
	 * with a unified login. Submitted login-credentials are first checked
	 * against this static information in {@authenticate()}.
	 *
	 * @param $username String
	 * @param $password String (Cleartext)
	 */
	public static function setDefaultAdmin($username, $password) {
		if( self::$username || self::$password )
			return;

		self::$username = $username;
		self::$password = $password;
	}


	/**
	 * Set strict path checking
	 *
	 * This prevents sharing of the session across several sites in the
	 * domain.
	 *
	 * @param boolean $strictPathChecking To enable or disable strict patch
	 *                                    checking.
	 */
	public static function setStrictPathChecking($strictPathChecking) {
		self::$strictPathChecking = $strictPathChecking;
	}


	/**
	 * Get strict path checking
	 *
	 * @return boolean Status of strict path checking
	 */
	public static function getStrictPathChecking() {
		return self::$strictPathChecking;
	}


	/**
	 * Set if passwords should be encrypted or not
	 *
	 * @param bool $encrypt Set to TRUE if you want that all (new) passwords
	 *                      will be stored encrypted, FALSE if you want to
	 *                      store the passwords in clear text.
	 */
	public static function encrypt_passwords($encrypt) {
		self::$encryptPasswords = (bool)$encrypt;
	}


	/**
	 * Get a list of all available encryption algorithms
	 *
	 * This method tries to use PHP's hash_algos() function. If it is not
	 * supported or it returns no algorithms, as a failback mechanism it tries
	 * to use the md5() and sha1() function and returns them.
	 *
	 * @return array Returns an array of strings containing all supported
	 *               encryption algorithms.
	 */
	public static function get_encryption_algorithms() {
		$result = function_exists('hash_algos')
			? hash_algos()
			: array();

		if(count($result) == 0) {
			if(function_exists('md5'))
				$result[] = 'md5';

			if(function_exists('sha1'))
				$result[] = 'sha1';
		}

		return $result;
	}


	/**
	 * Set the password encryption algorithm
	 *
	 * @param string $algorithm One of the available password encryption
	 *                          algorithms determined by
	 *                          {@link Security::get_encryption_algorithms()}
	 * @param bool $use_salt Set to TRUE if a random salt should be used to
	 *                       encrypt the passwords, otherwise FALSE
	 * @return bool Returns TRUE if the passed algorithm was valid, otherwise
	 *              FALSE.
	 */
	public static function set_password_encryption_algorithm($algorithm,
																													 $use_salt) {
		if(in_array($algorithm, self::get_encryption_algorithms()) == false)
		  return false;

		self::$encryptionAlgorithm = $algorithm;
		self::$useSalt = (bool)$use_salt;

		return true;
	}


	/**
	 * Get the the password encryption details
	 *
	 * The return value is an array of the following form:
	 * <code>
	 *   array('encrypt_passwords' => bool,
	 *         'algorithm' => string,
	 *         'use_salt' => bool)
	 * </code>
	 *
	 * @return array Returns an associative array containing all the
	 *               password encryption relevant information.
	 */
	public static function get_password_encryption_details() {
		return array('encrypt_passwords' => self::$encryptPasswords,
								 'algorithm' => self::$encryptionAlgorithm,
								 'use_salt' => self::$useSalt);
	}


	/**
	 * Encrypt a password
	 *
	 * Encrypt a password according to the current password encryption
	 * settings.
	 * Use {@link Security::get_password_encryption_details()} to retrieve the
	 * current settings.
	 * If the settings are so that passwords shouldn't be encrypted, the
	 * result is simple the clear text password with an empty salt except when
	 * a custom algorithm ($algorithm parameter) was passed.
	 *
	 * @param string $password The password to encrypt
	 * @param string $salt Optional: The salt to use. If it is not passed, but
	 *                     needed, the method will automatically create a
	 *                     random salt that will then be returned as return
	 *                     value.
	 * @param string $algorithm Optional: Use another algorithm to encrypt the
	 *                          password (so that the encryption algorithm can
	 *                          be changed over the time).
	 * @return mixed Returns an associative array containing the encrypted
	 *               password and the used salt in the form
	 *               <i>array('encrypted_password' => string, 'salt' =>
	 *               string, 'algorithm' => string)</i>.
	 *               If the passed algorithm is invalid, FALSE will be
	 *               returned.
	 *
	 * @see encrypt_passwords()
	 * @see set_password_encryption_algorithm()
	 * @see get_password_encryption_details()
	 */
	public static function encrypt_password($password, $salt = null,
																					$algorithm = null) {
		if(strlen(trim($password)) == 0) {
			// An empty password was passed, return an empty password an salt!
			return array('password' => null,
									 'salt' => null,
									 'algorithm' => 'none');

		} elseif((self::$encryptPasswords == false) || ($algorithm == 'none')) {
			// The password should not be encrypted
			return array('password' => substr($password, 0, 64),
									 'salt' => null,
									 'algorithm' => 'none');

		} elseif(strlen(trim($algorithm)) != 0) {
			// A custom encryption algorithm was passed, check if we can use it
			if(in_array($algorithm, self::get_encryption_algorithms()) == false)
				return false;

		} else {
			// Just use the default encryption algorithm
			$algorithm = self::$encryptionAlgorithm;
		}


		// If no salt was provided but we need one we just generate a random one
		if(strlen(trim($salt)) == 0)
			 $salt = null;

		if((self::$useSalt == true) && is_null($salt)) {
			$salt = sha1(mt_rand()) . time();
			$salt = substr(base_convert($salt, 16, 36), 0, 50);
		}


    // Encrypt the password
		if(function_exists('hash')) {
			$password = hash($algorithm, $password . $salt);
		} else {
			$password = call_user_func($algorithm, $password . $salt);
		}

		// Convert the base of the hexadecimal password to 36 to make it shorter
		// In that way we can store also a SHA256 encrypted password in just 64
		// letters.
		$password = substr(base_convert($password, 16, 36), 0, 64);


		return array('password' => $password,
								 'salt' => $salt,
								 'algorithm' => $algorithm);
	}


	/**
	 * Encrypt all passwords
	 *
	 * Action to encrypt all *clear text* passwords in the database according
	 * to the current settings.
	 * If the current settings are so that passwords shouldn't be encrypted,
	 * an explanation will be printed out.
	 *
	 * To run this action, the user needs to have administrator rights!
	 */
	public function encryptallpasswords() {
		// Only administrators can run this method
		if(!Member::currentUser() || !Member::currentUser()->isAdmin()) {
			Security::permissionFailure($this,
				"This page is secured and you need administrator rights to access it. " .
				"Enter your credentials below and we will send you right along.");
			return;
		}


		if(self::$encryptPasswords == false) {
			print "<h1>Password encryption disabled!</h1>\n";
			print "<p>To encrypt your passwords change your password settings by adding\n";
			print "<pre>Security::encrypt_passwords(true);</pre>\nto mysite/_config.php</p>";

			return;
		}


		// Are there members with a clear text password?
		$members = DataObject::get("Member",
			"PasswordEncryption = 'none' AND Password IS NOT NULL");

		if(!$members) {
			print "<h1>No passwords to encrypt</h1>\n";
			print "<p>There are no members with a clear text password that could be encrypted!</p>\n";

			return;
		}

		// Encrypt the passwords...
		print "<h1>Encrypting all passwords</h1>";
		print '<p>The passwords will be encrypted using the &quot;' .
			htmlentities(self::$encryptionAlgorithm) . '&quot; algorithm ';

		print (self::$useSalt)
			? "with a salt to increase the security.</p>\n"
			: "without using a salt to increase the security.</p><p>\n";

		foreach($members as $member) {
			// Force the update of the member record, as new passwords get
			// automatically encrypted according to the settings, this will do all
			// the work for us
			$member->forceChange();
			$member->write();

			print "  Encrypted credentials for member &quot;";
			print htmlentities($member->getTitle()) . '&quot; (ID: ' . $member->ID .
				'; E-Mail: ' . htmlentities($member->Email) . ")<br />\n";
		}

		print '</p>';
	}

}


?>