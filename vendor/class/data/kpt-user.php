<?php
/**
 * User Class
 * 
 * Handles all user-related operations
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// check if the class exists
if ( ! class_exists( 'KPT_User' ) ) {

    /** 
     * Class KPT_User
     * 
     * User Class
     * 
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Tasks
     * 
     * @property private HASH_ALGO: password hash algorithm
     * @property private HASH_OPTIONS: hashing config
     * @property private MAX_LOGIN_ATTEMPTS: maximum number of login attempts
     * @property private LOCKOUT_TIME: number of seconds to lockout the attempt
     * 
     */
    class KPT_User extends KPT_DB {

        // Password hashing configuration
        private const HASH_ALGO = PASSWORD_ARGON2ID;
        private const HASH_OPTIONS = [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ];
        
        // Account lockout settings
        private const MAX_LOGIN_ATTEMPTS = 5;
        private const LOCKOUT_TIME = 900; // 15 minutes

        // Constructor
        public function __construct() {
            parent::__construct();
        }
        
        /** 
         * register
         * 
         * Register a user
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        public function register( ) : void {

            // setup and hold our errors
            $errors = [];

            // sanitize all input
            $input = $this -> sanitizeRegistrationInput( $_POST );
            
            // Validate all fields
            $this -> validateNameFields( $input, $errors );
            $this -> validateUsername( $input, $errors );
            $this -> validateEmail( $input, $errors );
            $this -> validatePasswords( $input, $errors );
            
            // if we do have any errors
            if ( ! empty( $errors ) ) {

                // show the errors then dump out of the function
                $this -> processErrors( $errors );
                return;
            }
            
            // try to create an account
            try {

                // create the account
                $this -> createUserAccount( $input );

                // try to redirect home with a message
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Your account has been created, but there is one more step. Please check your email for your activation link.'
                );
            
            // whoopsie...
            } catch ( Exception $e ) {

                // dump to error log
                error_log( "Registration failed: " . $e -> getMessage( ) );
                
                // process the error
                $this -> processErrors( ["Registration failed: " . $e -> getMessage( )] );
            }
        }
        
        /** 
         * validate_user
         * 
         * Validate a users account
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        public function validate_user( ): void {

            // if the requests are empty
            if ( empty( $_GET['v'] ) || empty( $_GET['e'] ) ) {

                // show a message, and dump outta here
                KPT::show_message('danger', '<p>Please make sure you are clicking the link in the email you received.</p>');
                return;
            }
            
            // sanitize the hash & email string
            $hash = KPT::sanitize_string( $_GET['v'] );
            $email = KPT::sanitize_string( $_GET['e'] );
            
            // try to call the validate updater
            try {

                // First check if user exists and hash matches
                $user = $this -> select_single(
                    'SELECT id FROM kptv_users WHERE u_email = ? AND u_hash = ? AND u_active = 0',
                    [$email, $hash]
                );
                
                // if there is no user, throw an exception
                if ( ! $user ) {
                    throw new Exception( "Invalid validation request" );
                }

                // Activate the user account
                $success = $this -> execute(
                    'UPDATE kptv_users SET u_active = 1, u_hash = "", u_updated = NOW() WHERE id = ?',
                    [$user->id]
                );
                
                // if it's actually valid
                if ( $success ) {

                    // send out a "welcome" email
                    $this->sendWelcomeEmail( $email );

                    // try to redirect with a message
                    KPT::message_with_redirect(
                        '/users/login', 
                        'success', 
                        'Your account is now active, feel free to login.'
                    );
                
                // welp... not valid
                } else {

                    // throw an exception
                    throw new Exception( "Validation failed for hash: $hash, email: $email" );
                }

            // whoopsie...
            } catch ( Exception $e ) {

                // log the error
                error_log( "Account validation failed: " . $e -> getMessage( ) );

                // process the error
                $this -> processErrors( ["Account validation failed: " . $e -> getMessage( )] );
            }
        }
        
        /** 
         * login
         * 
         * Login a user
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        public function login( ) : void {

            // setup the variables we'll use in here
            $errors = [];
            $username = $_POST['frmUsername'] ?? '';
            $password = $_POST['frmPassword'] ?? '';

            // validate the username
            if ( ! KPT::validate_username( $username) ) {
                $errors[] = 'The username you have typed in is not valid.';
            }
            
            // validate the password
            if ( ! KPT::validate_password( $password ) ) {
                $errors[] = 'The password you typed is not valid.';
            }
            
            // if we do have errors
            if ( ! empty( $errors ) ) {
                // display the login errors, then dump outta here
                $this -> processErrors( $errors );
                return;
            }
            
            // try to authenticate the users login
            try {

                // do it!
                $this -> authenticateUser( $username, $password );

                // try to redirect with a message
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Thanks for logging in. You are all set to proceed.'
                );

            // whoopsie...
            } catch ( Exception $e ) {

                // log the error, and show a message
                error_log( "Login failed: " . $e -> getMessage( ) );

                // process the error
                $this -> processErrors( ["Login failed: " . $e -> getMessage( )] );
            }
        }
        
        /** 
         * logout
         * 
         * user logout
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        public function logout( ): void {

            // kill the session
            $this -> destroySession( );

            // try to redirect with a message
            KPT::message_with_redirect(
                '/', 
                'success', 
                'Thanks for logging out. To fully secure your account, please close your web browser.'
            );
        }
        
        /** 
         * forgot
         * 
         * Forgotten password
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        public function forgot( ): void {

            // hold our variables
            $errors = [];
            $username = $_POST['frmUsername'] ?? '';
            $email = $_POST['frmEmail'] ?? '';
            
            // Validate the username
            if ( ! KPT::validate_username( $username ) ) {
                $errors[] = 'The username you typed is not valid.';
            }
            
            // validate the email
            if ( ! KPT::validate_email( $email ) ) {
                $errors[] = 'The email address you typed is not valid.';
            }
            
            // if there are errors
            if ( ! empty( $errors ) ) {
                // show them and dump outta here
                $this->processErrors( $errors );
                return;
            }
            
            // try to process the reset
            try {
                
                // reset the password
                $this -> processPasswordReset( $username, $email );

                // try to redirect with a message
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Your password has been reset and emailed to you. Please change your password as soon as you can.'
                );
            
            // whoopsie...
            } catch ( Exception $e ) {

                // log the error and show a message
                error_log("Password reset failed: " . $e -> getMessage( ) );

                // process the error
                $this -> processErrors( ["Password reset failed: " . $e -> getMessage( )] );
            }
        }
        
        /** 
         * change_pass
         * 
         * Change a users password
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        public function change_pass( ): void {

            // hold our variables
            $errors = [];
            $user = self::get_current_user( );

            // if we don't have a user...
            if ( ! $user ) {
                // show a message and dump outta here
                KPT::show_message( 'danger', '<p>You must be logged in to change your password.</p>' );
                return;
            }
            
            // setup the form field variables
            $currentPass = $_POST['frmExistPassword'] ?? '';
            $newPass1 = $_POST['frmNewPassword1'] ?? '';
            $newPass2 = $_POST['frmNewPassword2'] ?? '';
            
            // Validate current password... first make sure it's structured right
            if ( ! KPT::validate_password( $currentPass ) ) {
                $errors[] = 'The current password you typed is not valid.';

            // now check if it matches what we have for the user
            } elseif ( ! $this -> verifyCurrentPassword( $user -> id, $currentPass ) ) {
                $errors[] = 'Your current password does not match what we have in our system.';
            }
            
            // Validate new password... first make sure it's structured right
            if ( ! KPT::validate_password( $newPass1 ) ) {
                $errors[] = 'The new password you typed is not valid.';

            // now see if they are equal
            } elseif ( $newPass1 !== $newPass2 ) {
                $errors[] = 'Your new passwords do not match each other.';
            }
            
            // if we have errors
            if ( ! empty( $errors ) ) {

                // show them, then dump outta here
                $this->processErrors( $errors );
                return;
            }
            
            // try to update the password
            try {

                // update the password
                $this -> updatePassword( $user -> id, $newPass1 );

                // shoot out a notice that it was changed... just in case...
                $this -> sendPasswordChangeNotification( $user -> email );
                
                // try to redirect with a message
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Your password has successfully been changed.'
                );

            // whoopsie...
            } catch ( Exception $e ) {

                // log the error and show a message
                error_log( "Password change failed: " . $e -> getMessage( ) );

                // process the error
                $this -> processErrors( ["Password change failed: " . $e -> getMessage( )] );
            }
        }
        
        /** 
         * is_user_logged_in
         * 
         * Change a users password
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return bool Returns if the user is logged in or not
         * 
         */
        public static function is_user_logged_in( ) : bool {

            // first check if the session is set
            if( isset( $_SESSION ) && isset( $_SESSION['user'] ) ) {

                // hold the user object
                $_uo = $_SESSION['user'];

                // it is, so let's make sure we have a user id in this, and it's actually greater than 0
                if( isset( $_uo ) && isset( $_uo -> id ) && $_uo -> id > 0 ) {

                    // we're all set
                    return true;
                }

            } 

            // default to false
            return false;
        }
        
        /** 
         * get_current_user
         * 
         * Change a users password
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return object|bool Returns the user object or false if not logged in
         * 
         */
        public static function get_current_user( ) : object|bool {
            
            // make sure the user is actually logged in
            if( self::is_user_logged_in( ) ) {

                // grab the user object from the session
                $_uo = $_SESSION['user'];

                // it is, so let's make sure we have a user id in this, and it's actually greater than 0
                if( isset( $_uo ) && isset( $_uo -> id ) && $_uo -> id > 0 ) {

                    // return the user object
                    return $_uo;
                }

            }

            // return 
            return false;
        }
        
        /** 
         * sanitizeRegistrationInput
         * 
         * Clean the registration data
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return array Returns the cleaned data
         * 
         */
        private function sanitizeRegistrationInput( array $input ) : array {
            
            // return an array of the cleaned data
            return [
                'firstName' => KPT::sanitize_string( $input['frmFirstName'] ?? '' ),
                'lastName' => KPT::sanitize_string( $input['frmLastName'] ?? '' ),
                'username' => KPT::sanitize_string( $input['frmUsername'] ?? '' ),
                'email' => KPT::sanitize_string( $input['frmMainEmail'] ?? '' ),
                'password1' => $input['frmPassword1'] ?? '',
                'password2' => $input['frmPassword2'] ?? ''
            ];
        }
        
        /** 
         * validateNameFields
         * 
         * validate name fields
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param array $input The input field
         * @param array &$errors The referenced error array
         * 
         * @return void Returns nothing
         * 
         */
        private function validateNameFields( array $input, array &$errors ) : void {
            
            // if the names do not validate
            if ( ! KPT::validate_name( $input['firstName'] ) || !KPT::validate_name( $input['lastName'] ) ) {
                
                // append a message to the referenced errors
                $errors[] = 'Are you sure your first and last name is correct?';
            }
        }
        
        /** 
         * validateUsername
         * 
         * validate the username field
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param array $input The input field
         * @param array &$errors The referenced error array
         * 
         * @return void Returns nothing
         * 
         */
        private function validateUsername( array $input, array &$errors ) : void {
            
            // if the username does not validate
            if ( ! KPT::validate_username( $input['username'] ) ) {
            
                // append a message to the referenced errors
                $errors[] = 'The username you have typed in is not valid.';

            // if it is, check to see if the username 
            } elseif ( $this -> check_username_exists($input['username'] ) ) {
            
                // append a message to the referenced errors
                $errors[] = 'The username you have typed in already exists.';
            }
        }
        
        /** 
         * validateEmail
         * 
         * validate the email field
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param array $input The input field
         * @param array &$errors The referenced error array
         * 
         * @return void Returns nothing
         * 
         */
        private function validateEmail( array $input, array &$errors ) : void {
            
            // if the email address does not validate
            if ( ! KPT::validate_email( $input['email'] ) ) {
            
                // append a message to the referenced errors
                $errors[] = 'The email address you have typed in is not valid.';

            // otherwise check if it already exists
            } elseif ( $this -> check_email_exists( $input['email'] ) ) {
            
                // append a message to the referenced errors
                $errors[] = 'The email address you have typed in already exists.';
            }
        }
        
        /** 
         * validatePasswords
         * 
         * validate the password fields
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param array $input The input field
         * @param array &$errors The referenced error array
         * 
         * @return void Returns nothing
         * 
         */
        private function validatePasswords( array $input, array &$errors ) : void {
            
            // if the password1 is not valid
            if ( ! KPT::validate_password( $input['password1'] ) ) {
            
                // append a message to our reference
                $errors[] = 'The password you typed is not valid.';

            // otherwise check if 1 & 2 are not equal
            } elseif ( $input['password1'] !== $input['password2'] ) {
            
                // append a message to our reference
                $errors[] = 'Your passwords do not match each other.';
            }
        }
        
        /** 
         * createUserAccount
         * 
         * create a users account
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param array $input The input fields
         * 
         * @return void Returns nothing
         * 
         */
        private function createUserAccount( array $input ) : void {
            
            // create a hash
            $hash = bin2hex( random_bytes( 32 ) );

            // encrypt the password
            $encryptedPass = KPT::encrypt( $input['password2'], KPT::get_setting( 'mainkey' ) );

            // now hash it
            $password = password_hash( $encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS );
            
            // Insert the new user
            $userId = $this -> execute(
                'INSERT INTO kptv_users (u_name, u_pass, u_hash, u_email, u_lname, u_fname, u_created) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [
                    $input['username'],
                    $password,
                    $hash,
                    $input['email'],
                    $input['lastName'],
                    $input['firstName']
                ]
            );
            
            // if there is no user id, throw an exception
            if ( ! $userId ) {
                throw new Exception( "Failed to create user account" );
            }
            
            // send out the activation email
            $this -> sendActivationEmail( $input['firstName'], $input['email'], $hash );
        }
        
        /** 
         * sendActivationEmail
         * 
         * send out the activation email
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $name The name os the user to send to
         * @param string $email The email address sending to
         * @param string $hash The hash
         * 
         * @return void Returns nothing
         * 
         */
        private function sendActivationEmail( string $name, string $email, string $hash ) : void {
            
            // format the activation link
            $activationLink = sprintf(
                '%svalidate?v=%s&e=%s',
                KPT_URI,
                urlencode($hash),
                urlencode($email)
            );
            
            // setup the email message
            $message = sprintf(
                "<h1>Welcome</h1>
                <p>Hey %s, thanks for signing up. There is one more step... you will need to activate your account.</p>
                <p>Please click this link to finalize your registration: <a href='%s'>%s</a></p>
                <p>Thanks,<br />Kevin</p>",
                htmlspecialchars( $name ),
                $activationLink,
                $activationLink
            );
            
            // send the email
            KPT::send_email( [$email, $name], 'There\'s One Last Step', $message );
        }
        
        /** 
         * sendWelcomeEmail
         * 
         * send out the welcome email
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $email The email address sending to
         * 
         * @return void Returns nothing
         * 
         */
        private function sendWelcomeEmail( string $email ) : void {
            
            // send out the email
            KPT::send_email(
                [$email, ''], 
                'Welcome', 
                '<h1>Welcome</h1><p>Your account is now active. Thanks for joining us.</p>'
            );
        }
        
        /** 
         * authenticateUser
         * 
         * authenticate's a user based on what they provide
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $username The account's username
         * @param string $password The account's password
         * 
         * @return void Returns nothing
         * 
         */
        private function authenticateUser( string $username, string $password ) : void {
            
            // get a user object
            $user = $this -> select_single(
                'SELECT id, u_pass, u_email, u_role, locked_until FROM kptv_users WHERE u_name = ?',
                [$username]
            );
            
            // if there is no user or the user is not an object, throw an exception
            if ( ! $user || ! is_object( $user ) ) {
                throw new Exception( "User not found: $username" );
            }
            
            // Check if account is locked
            if ( $user -> locked_until && strtotime( $user -> locked_until ) > time( ) ) {
                throw new Exception( "Account is temporarily locked. Please try again later." );
            }
            
            // encrypt the password as passed to this function
            $encryptedPass = KPT::encrypt( $password, KPT::get_setting( 'mainkey' ) );
            
            // now, if it does not verify... throw an error
            if ( ! password_verify( $encryptedPass, $user -> u_pass ) ) {

                // Increment failed login attempts
                $this -> incrementLoginAttempts( $user -> id );
                throw new Exception( "Invalid username or password" );
            }
            
            // Reset login attempts on successful login
            $this -> execute(
                'UPDATE kptv_users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?',
                [$user->id]
            );
            
            // rehash the password every time
            $this -> rehash_password( $user -> id, $password );

            // set the users session object
            $_SESSION['user'] = ( object ) array(
                'id' => $user -> id,
                'username' => $username,
                'email' => $user -> u_email,
                'role' => $user -> u_role,
            );

        }
        
        /** 
         * incrementLoginAttempts
         * 
         * Track failed login attempts and lock account if needed
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param int $userId The user ID
         * 
         * @return void Returns nothing
         * 
         */
        private function incrementLoginAttempts( int $userId ): void {

            // Get current attempts
            $user = $this->select_single(
                'SELECT login_attempts FROM kptv_users WHERE id = ?',
                [$userId]
            );
            
            // increment the attempt
            $attempts = $user ? $user -> login_attempts + 1 : 1;
            
            // if the attempts are greater than or = the configured max
            if ( $attempts >= self::MAX_LOGIN_ATTEMPTS ) {

                // Lock the account
                $lockTime = date( 'Y-m-d H:i:s', time( ) + self::LOCKOUT_TIME );
                
                // update the lockout count and locked out until
                $this->execute(
                    'UPDATE kptv_users SET login_attempts = ?, locked_until = ? WHERE id = ?',
                    [$attempts, $lockTime, $userId]
                );
            
            // otherwise
            } else {

                // Just increment attempts
                $this->execute(
                    'UPDATE kptv_users SET login_attempts = ? WHERE id = ?',
                    [$attempts, $userId]
                );

            }

        }
        
        /** 
         * processPasswordReset
         * 
         * user forgot their password, so we'll generate one and send it to them
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $username The account's username
         * @param string $email The account's email address
         * 
         * @return void Returns nothing
         * 
         */
        private function processPasswordReset( string $username, string $email ) : void {

            // generate a new password
            $newPassword = KPT::generate_password( );

            // encrypt the new password
            $encryptedPass = KPT::encrypt( $newPassword, KPT::get_setting( 'mainkey' ) );

            // hash it
            $passwordHash = password_hash( $encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS );
            
            // Update the password and reset login attempts
            $success = $this->execute(
                'UPDATE kptv_users SET u_pass = ?, login_attempts = 0, locked_until = NULL WHERE u_name = ? AND u_email = ?',
                [$passwordHash, $username, $email]
            );
            
            // if it wasn't successful, throw an error
            if ( ! $success ) {
                throw new Exception( "Password reset failed for user: $username" );
            }
            
            // send it in an email to the user
            $this -> sendPasswordResetEmail( $username, $email, $newPassword );
        }
        
        /** 
         * sendPasswordResetEmail
         * 
         * send the user an email with their new password and a note to change it asap
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $username The account's username
         * @param string $email The account's email address
         * @param string $newPassword The account's new password
         * 
         * @return void Returns nothing
         * 
         */
        private function sendPasswordResetEmail(string $username, string $email, string $newPassword) : void {

            // setup the email message
            $message = sprintf(
                "<p>Hey %s, Sorry you forgot your password.</p>
                <p>Here is a new one to get you back in: <strong>%s</strong></p>
                <p>Please make sure you change it to something you will remember as soon as you can.</p>
                <p>Thanks,<br />Kevin</p>",
                htmlspecialchars( $username ),
                htmlspecialchars( $newPassword )
            );
            
            // send the email
            KPT::send_email( [$email, ''], 'Password Reset', $message );
        }
        
        /** 
         * verifyCurrentPassword
         * 
         * verify's the user accounts password
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param int $userId The account's ID
         * @param string $password The account's password
         * 
         * @return bool Returns if the password verify's or not
         * 
         */
        private function verifyCurrentPassword( int $userId, string $password ): bool {

            // Get the user's password hash
            $result = $this -> select_single(
                'SELECT u_pass FROM kptv_users WHERE id = ?',
                [$userId]
            );
            
            // if we don't have a result, return false
            if ( ! $result ) {
                return false;
            }
            
            // encrypt the passed password
            $encryptedPass = KPT::encrypt( $password, KPT::get_setting( 'mainkey' ) );

            // return if it verify's against what we have on record
            return password_verify( $encryptedPass, $result -> u_pass );
        }
        
        /** 
         * updatePassword
         * 
         * update the user records password
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param int $userId The account's ID
         * @param string $newPassword The new password
         * 
         * @return void Returns nothing
         * 
         */
        private function updatePassword( int $userId, string $newPassword ) : void {

            // encrypt the password
            $encryptedPass = KPT::encrypt( $newPassword, KPT::get_setting( 'mainkey' ) );

            // now hash it
            $passwordHash = password_hash( $encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS );
            
            // Update the password
            $success = $this->execute(
                'UPDATE kptv_users SET u_pass = ?, u_updated = NOW() WHERE id = ?',
                [$passwordHash, $userId]
            );
            
            // if it wasn't successful, throw an error
            if ( ! $success ) {
                throw new Exception( "Failed to update password for user ID: $userId" );
            }
        }
        
        /** 
         * sendPasswordChangeNotification
         * 
         * send an email for the password changes
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $email The email address to send the notice to
         * 
         * @return void Returns nothing
         * 
         */
        private function sendPasswordChangeNotification( string $email ) : void {

            // send the email notice
            KPT::send_email(
                [$email, ''], 
                'Password Changed', 
                '<p>This message is to notify you that your password has been changed. If you did not initiate this, please go to the site and hit the "Forgot My Password" button.</p>'
            );
        }
        
        /** 
         * check_username_exists
         * 
         * check if the username already exists
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $username The username to check
         * 
         * @return bool Returns if the username exists or not
         * 
         */
        private function check_username_exists( string $username ): bool {

            // see if we have a result from querying the users table
            $result = $this -> select_single(
                'SELECT id FROM kptv_users WHERE u_name = ?', 
                [$username]
            );

            // return if we get a result or not
            return $result !== false;
        }
        
        /** 
         * check_email_exists
         * 
         * check if the email address already exists
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $email The email address to check
         * 
         * @return bool Returns if the email exists or not
         * 
         */
        private function check_email_exists (string $email ) : bool {

            // check if we've got a result
            $result = $this->select_single(
                'SELECT id FROM kptv_users WHERE u_email = ?', 
                [$email]
            );

            // return whether we do or not
            return $result !== false;
        }
        
        /** 
         * rehash_password
         * 
         * Rehash the password supplied
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param int $userId The account's ID
         * @param string $password The password to be rehashed
         * 
         * @return void Returns nothing
         * 
         */
        private function rehash_password( int $userId, string $password ) : void {

            // encrypt the passed password
            $encryptedPass = KPT::encrypt( $password, KPT::get_setting( 'mainkey' ) );

            // now hash it
            $passwordHash = password_hash( $encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS );
            
            // update the password we store with the new hashed version
            $this -> execute(
                'UPDATE kptv_users SET u_pass = ?, u_updated = NOW() WHERE id = ?',
                [$passwordHash, $userId]
            );
        }
        
        /** 
         * processErrors
         * 
         * Formats the errors to be displayed
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param array $errors The errors to show
         * 
         * @return void Returns nothing
         * 
         */
        private function processErrors( array $errors ) : void {

            // get the referrer
            $referrer = KPT::get_user_referer( );

            // start the error list
            $message = '<ul class="uk-list uk-list-disc">';

            // for every error added to the array
            foreach ( $errors as $error ) {
                // append it to the list
                $message .= "<li>" . htmlspecialchars( $error ) . "</li>";
            }
            $message .= '</ul>';
            
            // try to redirect with a message - FIXED: Changed 'success' to 'danger' for error messages
            KPT::message_with_redirect(
                $referrer ?? '/', 
                'danger', 
                $message        
            );        
        }
        
        /** 
         * destroySession
         * 
         * Destroy our session
         * 
         * @since 8.4
         * @access private
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @return void Returns nothing
         * 
         */
        private function destroySession( ) : void {
            
            // null out the session variable
            $_SESSION['user'] = null;

            // nice and simple, unset the user
            unset( $_SESSION['user'] );
        }
    }
}