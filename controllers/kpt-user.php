<?php
/**
 * User Management Class
 * 
 * Handles all aspects of user management including:
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// We don't want to allow direct access to this
defined( 'KPT_PATH' ) || die( 'No direct script access allowed' );

if( ! class_exists( 'KPT_User' ) ) {

    /**
     * User Management Class
     * 
     * Handles all aspects of user management including:
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class KPT_User extends KPT_Database {
        
        /**
         * Password hashing algorithm (Argon2ID)
         * @var string
         */
        private const HASH_ALGO = PASSWORD_ARGON2ID;
        
        /**
         * Hashing configuration options
         * Memory: 64MB, Iterations: 4, Threads: 2
         * @var array
         */
        private const HASH_OPTIONS = [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2
        ];
        
        /**
         * Maximum allowed failed login attempts before lockout
         * @var int
         */
        private const MAX_LOGIN_ATTEMPTS = 5;
        
        /**
         * Account lockout duration in seconds (15 minutes)
         * @var int
         */
        private const LOCKOUT_TIME = 900;

        /**
         * Constructor
         * Initializes parent database class
         */
        public function __construct( ) {
            parent::__construct( );
        }

        /**
         * Register a new user account
         *          
         * @return void
         * @throws Exception On validation failure or database error
         */
        public function register( ) : void {

            // hold our errors
            $errors = [];

            // sanitize the input
            $input = $this -> sanitizeRegistrationInput( $_POST );
            
            // Validate all registration fields
            $this -> validateNameFields( $input, $errors );
            $this -> validateUsername( $input, $errors );
            $this -> validateEmail( $input, $errors );
            $this -> validatePasswords( $input, $errors );
            
            // are there any errors from validating?
            if ( ! empty( $errors ) ) {

                // process them then return
                $this -> processErrors( $errors );
                return;
            }
            
            // try to create the user account
            try {

                // create
                $this -> createUserAccount( $input );
                
                // if no exceptions occurred, redirect with a message
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Your account has been created, but there is one more step. Please check your email for your activation link.'
                );
            
            // whoopsie... log the error then process it
            } catch ( Exception $e ) {
                error_log( "Registration failed: " . $e -> getMessage( ) );
                $this -> processErrors( ["Registration failed: " . $e -> getMessage( )] );
            }

        }

        /**
         * Validate a user's account via activation link
         * 
         * @return void
         * @throws Exception On invalid activation request or database error
         */
        public function validate_user( ) : void {

            // if the querystrings are empty
            if ( empty( $_GET['v'] ) || empty( $_GET['e'] ) ) {
                
                // show a message and go no further
                KPT::show_message( 'danger', '<p>Please make sure you are clicking the link in the email you received.</p>' );
                return;
            }
            
            // make sure the strings are sanitized
            $hash = KPT::sanitize_string( $_GET['v'] );
            $email = KPT::sanitize_string( $_GET['e'] );
            
            // try to validate the user
            try {
                
                // get the user record first
                $user = $this -> query( 'SELECT id FROM kptv_users WHERE u_email = ? AND u_hash = ? AND u_active = 0' )
                              -> bind( [$email, $hash] )
                              -> single( )
                              -> fetch( );
                
                // if we don't have a record
                if ( ! $user ) {
                    throw new Exception( "Invalid validation request" );
                }

                // made it here, so let's try to update the record.
                $success = $this -> query( 'UPDATE kptv_users SET u_active = 1, u_hash = "", u_updated = NOW() WHERE id = ?' )
                                 -> bind( [$user -> id] )
                                 -> execute( );
                
                // if it was successfully updated
                if ( $success ) {

                    // send out the welcome email
                    $this -> sendWelcomeEmail( $email );
                    
                    // show a message and redirect
                    KPT::message_with_redirect(
                        '/users/login', 
                        'success', 
                        'Your account is now active, feel free to login.'
                    );

                // whoops... something went wrong, so throw an exception
                } else {
                    throw new Exception( "Validation failed for hash: $hash, email: $email" );
                }

            // whoopsie...  
            } catch ( Exception $e ) {

                // log the error and process it
                error_log( "Account validation failed: " . $e -> getMessage( ) );
                $this -> processErrors( ["Account validation failed: " . $e -> getMessage( )] );
            }

        }

        /**
         * Authenticate and log in a user
         * 
         * @return void
         * @throws Exception On authentication failure
         */
        public function login( ) : void {

            // hold our errors
            $errors = [];
            $username = $_POST['frmUsername'] ?? '';
            $password = $_POST['frmPassword'] ?? '';

            // make sure the username and password are both valid
            if ( ! KPT::validate_username( $username ) ) {
                $errors[] = 'The username you have typed in is not valid.';
            }
            if ( ! KPT::validate_password( $password ) ) {
                $errors[] = 'The password you typed is not valid.';
            }
            
            // if the errors aren't empty
            if ( ! empty( $errors ) ) {

                // process the errors and return outta here
                $this -> processErrors( $errors );
                return;
            }
            
            // try to authenticate
            try {

                // authenticate the user
                $this -> authenticateUser( $username, $password );

                // throw a message with the redirect
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Thanks for logging in. You are all set to proceed.'
                );

            // whoopsie...
            } catch ( Exception $e ) {

                // log the error and process it
                error_log( "Login failed: " . $e -> getMessage( ) );
                $this -> processErrors( ["Login failed: " . $e -> getMessage( )] );
            }

        }

        /**
         * Destroy user session and log out
         * 
         * Clears all session data and provides confirmation message.
         * Recommends browser closure for complete session termination.
         * 
         * @return void
         */
        public function logout( ) : void {

            // destroy the user session
            $this -> destroySession( );
            
            KPT::message_with_redirect(
                '/', 
                'success', 
                'Thanks for logging out. To fully secure your account, please close your web browser.'
            );
        }

        /**
         * Process password reset request
         * 
         * @return void
         * @throws Exception On validation failure or reset processing error
         */
        public function forgot() : void {

            
            $errors = [];
            $username = $_POST['frmUsername'] ?? '';
            $email = $_POST['frmEmail'] ?? '';
            
            if (!KPT::validate_username($username)) {
                $errors[] = 'The username you typed is not valid.';
            }
            
            if (!KPT::validate_email($email)) {
                $errors[] = 'The email address you typed is not valid.';
            }
            
            if (!empty($errors)) {
                $this->processErrors($errors);
                return;
            }
            
            try {
                $this->processPasswordReset($username, $email);
                
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Your password has been reset and emailed to you. Please change your password as soon as you can.'
                );
            } catch (Exception $e) {
                error_log("Password reset failed: " . $e->getMessage());
                $this->processErrors(["Password reset failed: " . $e->getMessage()]);
            }
        }

        /**
         * Change current user's password
         * 
         * Process flow:
         * 1. Verify user is logged in
         * 2. Validate current password
         * 3. Validate new password meets requirements
         * 4. Update password in database
         * 5. Send change notification email
         * 
         * @return void
         * @throws Exception On validation failure or database error
         */
        public function change_pass() : void {
            $errors = [];
            $user = self::get_current_user();

            if (!$user) {
                KPT::show_message('danger', '<p>You must be logged in to change your password.</p>');
                return;
            }
            
            $currentPass = $_POST['frmExistPassword'] ?? '';
            $newPass1 = $_POST['frmNewPassword1'] ?? '';
            $newPass2 = $_POST['frmNewPassword2'] ?? '';
            
            // Validate current password matches stored hash
            if (!KPT::validate_password($currentPass)) {
                $errors[] = 'The current password you typed is not valid.';
            } elseif (!$this->verifyCurrentPassword($user->id, $currentPass)) {
                $errors[] = 'Your current password does not match what we have in our system.';
            }
            
            // Validate new password meets requirements and matches confirmation
            if (!KPT::validate_password($newPass1)) {
                $errors[] = 'The new password you typed is not valid.';
            } elseif ($newPass1 !== $newPass2) {
                $errors[] = 'Your new passwords do not match each other.';
            }
            
            if (!empty($errors)) {
                $this->processErrors($errors);
                return;
            }
            
            try {
                $this->updatePassword($user->id, $newPass1);
                $this->sendPasswordChangeNotification($user->email);
                
                KPT::message_with_redirect(
                    '/', 
                    'success', 
                    'Your password has successfully been changed.'
                );
            } catch (Exception $e) {
                error_log("Password change failed: " . $e->getMessage());
                $this->processErrors(["Password change failed: " . $e->getMessage()]);
            }
        }

        /**
         * Check if a user is currently logged in
         * 
         * Verifies:
         * 1. Session exists and contains user data
         * 2. User object contains valid ID
         * 
         * @static
         * @return bool True if valid user session exists
         */
        public static function is_user_logged_in() : bool {
            if (isset($_SESSION) && isset($_SESSION['user'])) {
                $_uo = $_SESSION['user'];
                
                if (isset($_uo) && isset($_uo->id) && $_uo->id > 0) {
                    return true;
                }
            } 
            
            return false;
        }

        /**
         * Get current logged in user object
         * 
         * Returns the complete user object from session if:
         * 1. User is logged in (verified)
         * 2. Session contains valid user data
         * 
         * @static
         * @return object|bool User object if valid session, false otherwise
         */
        public static function get_current_user() : object|bool {
            if (self::is_user_logged_in()) {
                $_uo = $_SESSION['user'];
                
                if (isset($_uo) && isset($_uo->id) && $_uo->id > 0) {
                    return $_uo;
                }
            }

            return false;
        }

        /**
         * Sanitize registration form input
         * 
         * Processes all registration form fields:
         * - First/last name
         * - Username
         * - Email
         * - Passwords
         * 
         * @param array $input Raw POST data
         * @return array Sanitized input data
         */
        private function sanitizeRegistrationInput(array $input) : array {
            return [
                'firstName' => KPT::sanitize_string($input['frmFirstName'] ?? ''),
                'lastName'  => KPT::sanitize_string($input['frmLastName'] ?? ''),
                'username'  => KPT::sanitize_string($input['frmUsername'] ?? ''),
                'email'     => KPT::sanitize_string($input['frmMainEmail'] ?? ''),
                'password1' => $input['frmPassword1'] ?? '',
                'password2' => $input['frmPassword2'] ?? ''
            ];
        }

        /**
         * Validate name fields
         * 
         * Checks both first and last names:
         * - Not empty
         * - Valid name format (letters, hyphens, apostrophes)
         * 
         * @param array $input Sanitized input data
         * @param array &$errors Reference to error collection array
         * @return void
         */
        private function validateNameFields(array $input, array &$errors) : void {
            if (!KPT::validate_name($input['firstName']) || !KPT::validate_name($input['lastName'])) {
                $errors[] = 'Are you sure your first and last name is correct?';
            }
        }

        /**
         * Validate username
         * 
         * Checks:
         * - Valid format (alphanumeric + underscores)
         * - Not already in use
         * 
         * @param array $input Sanitized input data
         * @param array &$errors Reference to error collection array
         * @return void
         */
        private function validateUsername(array $input, array &$errors) : void {
            if (!KPT::validate_username($input['username'])) {
                $errors[] = 'The username you have typed in is not valid.';
            } elseif ($this->check_username_exists($input['username'])) {
                $errors[] = 'The username you have typed in already exists.';
            }
        }

        /**
         * Validate email
         * 
         * Checks:
         * - Valid email format
         * - Not already registered
         * 
         * @param array $input Sanitized input data
         * @param array &$errors Reference to error collection array
         * @return void
         */
        private function validateEmail(array $input, array &$errors) : void {
            if (!KPT::validate_email($input['email'])) {
                $errors[] = 'The email address you have typed in is not valid.';
            } elseif ($this->check_email_exists($input['email'])) {
                $errors[] = 'The email address you have typed in already exists.';
            }
        }

        /**
         * Validate password fields
         * 
         * Checks:
         * - Password meets complexity requirements
         * - Both password fields match
         * 
         * @param array $input Sanitized input data
         * @param array &$errors Reference to error collection array
         * @return void
         */
        private function validatePasswords(array $input, array &$errors) : void {
            if (!KPT::validate_password($input['password1'])) {
                $errors[] = 'The password you typed is not valid.';
            } elseif ($input['password1'] !== $input['password2']) {
                $errors[] = 'Your passwords do not match each other.';
            }
        }

        /**
         * Create a new user account in database
         * 
         * Process:
         * 1. Generate activation hash
         * 2. Encrypt and hash password
         * 3. Insert new user record
         * 4. Send activation email
         * 
         * @param array $input Validated registration data
         * @return void
         * @throws Exception On database insertion failure
         */
        private function createUserAccount(array $input) : void {
            $hash = bin2hex(random_bytes(32));
            $encryptedPass = KPT::encrypt($input['password2'], KPT::get_setting('mainkey'));
            $password = password_hash($encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS);
            
            $userId = $this->query('INSERT INTO kptv_users (u_name, u_pass, u_hash, u_email, u_lname, u_fname, u_created) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())')
                           ->bind([
                               $input['username'],
                               $password,
                               $hash,
                               $input['email'],
                               $input['lastName'],
                               $input['firstName']
                           ])
                           ->execute();
            
            if (!$userId) {
                throw new Exception("Failed to create user account");
            }
            
            $this->sendActivationEmail($input['firstName'], $input['email'], $hash);
        }

        /**
         * Check if username exists in database
         * 
         * @param string $username Username to check
         * @return bool True if username exists
         */
        private function check_username_exists(string $username) : bool {
            $result = $this->query('SELECT id FROM kptv_users WHERE u_name = ?')
                           ->bind([$username])
                           ->single()
                           ->fetch();

            return $result !== false;
        }

        /**
         * Check if email exists in database
         * 
         * @param string $email Email to check
         * @return bool True if email exists
         */
        private function check_email_exists(string $email) : bool {
            $result = $this->query('SELECT id FROM kptv_users WHERE u_email = ?')
                           ->bind([$email])
                           ->single()
                           ->fetch();

            return $result !== false;
        }

        /**
         * Send account activation email
         * 
         * @param string $name User's first name
         * @param string $email User's email address
         * @param string $hash Activation hash
         * @return void
         */
        private function sendActivationEmail(string $name, string $email, string $hash) : void {
            $activationLink = sprintf(
                '%svalidate?v=%s&e=%s',
                KPT_URI,
                urlencode($hash),
                urlencode($email)
            );
            
            $message = sprintf(
                "<h1>Welcome</h1>
                <p>Hey %s, thanks for signing up. There is one more step... you will need to activate your account.</p>
                <p>Please click this link to finalize your registration: <a href='%s'>%s</a></p>
                <p>Thanks,<br />Kevin</p>",
                htmlspecialchars($name),
                $activationLink,
                $activationLink
            );
            
            KPT::send_email([$email, $name], 'There\'s One Last Step', $message);
        }

        /**
         * Send welcome email after successful activation
         * 
         * @param string $email User's email address
         * @return void
         */
        private function sendWelcomeEmail(string $email) : void {
            KPT::send_email(
                [$email, ''], 
                'Welcome', 
                '<h1>Welcome</h1><p>Your account is now active. Thanks for joining us.</p>'
            );
        }

        /**
         * Send password reset email with new temporary password
         * 
         * @param string $username User's username
         * @param string $email User's email address
         * @param string $newPassword The new temporary password
         * @return void
         */
        private function sendPasswordResetEmail(string $username, string $email, string $newPassword) : void {
            $message = sprintf(
                "<p>Hey %s, Sorry you forgot your password.</p>
                <p>Here is a new one to get you back in: <strong>%s</strong></p>
                <p>Please make sure you change it to something you will remember as soon as you can.</p>
                <p>Thanks,<br />Kevin</p>",
                htmlspecialchars($username),
                htmlspecialchars($newPassword)
            );
            
            KPT::send_email([$email, ''], 'Password Reset', $message);
        }

        /**
         * Send password change notification email
         * 
         * @param string $email User's email address
         * @return void
         */
        private function sendPasswordChangeNotification(string $email) : void {
            KPT::send_email(
                [$email, ''], 
                'Password Changed', 
                '<p>This message is to notify you that your password has been changed. If you did not initiate this, please go to the site and hit the "Forgot My Password" button.</p>'
            );
        }

        /**
         * Authenticate user credentials
         * 
         * Process:
         * 1. Retrieve user record by username
         * 2. Check account lock status
         * 3. Verify password against stored hash
         * 4. Reset failed attempts on success
         * 5. Rehash password if needed
         * 6. Create user session
         * 
         * @param string $username
         * @param string $password
         * @return void
         * @throws Exception On authentication failure
         */
        private function authenticateUser(string $username, string $password) : void {
            $user = $this->query('SELECT id, u_pass, u_email, u_role, locked_until FROM kptv_users WHERE u_name = ?')
                         ->bind([$username])
                         ->single()
                         ->fetch();
            
            if (!$user || !is_object($user)) {
                throw new Exception("User not found: $username");
            }
            
            // Check if account is temporarily locked
            if ($user->locked_until && strtotime($user->locked_until) > time()) {
                throw new Exception("Account is temporarily locked. Please try again later.");
            }
            
            $encryptedPass = KPT::encrypt($password, KPT::get_setting('mainkey'));
            
            if (!password_verify($encryptedPass, $user->u_pass)) {
                $this->incrementLoginAttempts($user->id);
                throw new Exception("Invalid username or password");
            }
            
            // Reset failed attempts on successful login
            $this->query('UPDATE kptv_users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')
                 ->bind([$user->id])
                 ->execute();
            
            $this->rehash_password($user->id, $password);

            // Create user session
            $_SESSION['user'] = (object) [
                'id'       => $user->id,
                'username' => $username,
                'email'    => $user->u_email,
                'role'     => $user->u_role,
            ];
        }

        /**
         * Increment failed login attempts and lock account if threshold reached
         * 
         * @param int $userId
         * @return void
         */
        private function incrementLoginAttempts(int $userId) : void {
            $user = $this->query('SELECT login_attempts FROM kptv_users WHERE id = ?')
                         ->bind([$userId])
                         ->single()
                         ->fetch();
            
            $attempts = $user ? $user->login_attempts + 1 : 1;
            
            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                $lockTime = date('Y-m-d H:i:s', time() + self::LOCKOUT_TIME);
                
                $this->query('UPDATE kptv_users SET login_attempts = ?, locked_until = ? WHERE id = ?')
                     ->bind([$attempts, $lockTime, $userId])
                     ->execute();
            } else {
                $this->query('UPDATE kptv_users SET login_attempts = ? WHERE id = ?')
                     ->bind([$attempts, $userId])
                     ->execute();
            }
        }

        /**
         * Process password reset for forgotten password
         * 
         * @param string $username
         * @param string $email
         * @return void
         * @throws Exception On reset failure
         */
        private function processPasswordReset(string $username, string $email) : void {
            $newPassword = KPT::generate_password();
            $encryptedPass = KPT::encrypt($newPassword, KPT::get_setting('mainkey'));
            $passwordHash = password_hash($encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS);
            
            $success = $this->query('UPDATE kptv_users SET u_pass = ?, login_attempts = 0, locked_until = NULL WHERE u_name = ? AND u_email = ?')
                            ->bind([$passwordHash, $username, $email])
                            ->execute();
            
            if (!$success) {
                throw new Exception("Password reset failed for user: $username");
            }
            
            $this->sendPasswordResetEmail($username, $email, $newPassword);
        }

        /**
         * Verify current password matches stored hash
         * 
         * @param int $userId
         * @param string $password
         * @return bool True if password matches
         */
        private function verifyCurrentPassword(int $userId, string $password) : bool {
            $result = $this->query('SELECT u_pass FROM kptv_users WHERE id = ?')
                           ->bind([$userId])
                           ->single()
                           ->fetch();
            
            if (!$result) {
                return false;
            }
            
            $encryptedPass = KPT::encrypt($password, KPT::get_setting('mainkey'));
            return password_verify($encryptedPass, $result->u_pass);
        }

        /**
         * Update user password in database
         * 
         * @param int $userId
         * @param string $newPassword
         * @return void
         * @throws Exception On update failure
         */
        private function updatePassword(int $userId, string $newPassword) : void {
            $encryptedPass = KPT::encrypt($newPassword, KPT::get_setting('mainkey'));
            $passwordHash = password_hash($encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS);
            
            $success = $this->query('UPDATE kptv_users SET u_pass = ?, u_updated = NOW() WHERE id = ?')
                            ->bind([$passwordHash, $userId])
                            ->execute();
            
            if (!$success) {
                throw new Exception("Failed to update password for user ID: $userId");
            }
        }

        /**
         * Rehash user password with current algorithm
         * 
         * Automatically updates password hash if:
         * - Default hashing algorithm changes
         * - Hashing options change
         * - Password needs rehashing for security
         * 
         * @param int $userId
         * @param string $password
         * @return void
         */
        private function rehash_password(int $userId, string $password) : void {
            $encryptedPass = KPT::encrypt($password, KPT::get_setting('mainkey'));
            $passwordHash = password_hash($encryptedPass, self::HASH_ALGO, self::HASH_OPTIONS);
            
            $this->query('UPDATE kptv_users SET u_pass = ?, u_updated = NOW() WHERE id = ?')
                 ->bind([$passwordHash, $userId])
                 ->execute();
        }

        /**
         * Process and display errors
         * 
         * Formats error messages as HTML list and redirects back
         * to referring page with error display.
         * 
         * @param array $errors Array of error messages
         * @return void
         */
        private function processErrors(array $errors) : void {
            $referrer = KPT::get_user_referer();
            $message = '<ul class="uk-list uk-list-disc">';

            foreach ($errors as $error) {
                $message .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $message .= '</ul>';
            
            KPT::message_with_redirect(
                $referrer ?? '/', 
                'danger', 
                $message        
            );        
        }

        /**
         * Destroy user session
         * 
         * Clears all session data by:
         * 1. Nullifying user session variable
         * 2. Unsetting the session variable
         * 
         * @return void
         */
        private function destroySession( ) : void {

            // try to set the session variables to null and unset it
            $_SESSION['user'] = null;
            unset( $_SESSION['user'] );

            // now try to really kill it
            session_destroy( );

        }

        /**
         * Get total count of users in system
         * 
         * @return int Number of registered users
         */
        public function get_total_users_count() : int {
            $result = $this->query('SELECT COUNT(id) as total FROM kptv_users')
                           ->single()
                           ->fetch();
            return $result ? (int)$result->total : 0;
        }

        /**
         * Get paginated list of users
         * 
         * @param int $limit Number of users per page
         * @param int $offset Pagination offset
         * @return array User records
         */
        public function get_users_paginated(int $limit, int $offset) : array {
            $query = 'SELECT * FROM kptv_users ORDER BY u_created DESC';
            
            if ($limit > 0) {
                $query .= " LIMIT $limit OFFSET $offset";
                return $this->query($query)->many()->fetch();
            }
            
            return $this->query($query)->many()->fetch();
        }

        /**
         * Toggle user active status
         * 
         * Prevents users from changing their own status
         * 
         * @param int $userId User ID to toggle
         * @param int $currentUserId Current admin's user ID
         * @return void
         * @throws Exception If attempting to change own status
         */
        private function toggle_user_active_status(int $userId, int $currentUserId) : void {
            if ($userId === $currentUserId) {
                throw new Exception('You cannot change your own status');
            }
            
            $current = $this->query('SELECT u_active FROM kptv_users WHERE id = ?')
                            ->bind([$userId])
                            ->single()
                            ->fetch();
            
            if ($current) {
                $newStatus = $current->u_active ? 0 : 1;
                $this->query('UPDATE kptv_users SET u_active = ? WHERE id = ?')
                     ->bind([$newStatus, $userId])
                     ->execute();
            }
        }

        /**
         * Unlock user account
         * 
         * Resets failed login attempts and clears lockout timer
         * 
         * @param int $userId User ID to unlock
         * @return void
         */
        private function unlock_user_account(int $userId) : void {
            $this->query('UPDATE kptv_users SET login_attempts = 0, locked_until = NULL WHERE id = ?')
                 ->bind([$userId])
                 ->execute();
        }

        /**
         * Delete user account and related data
         * 
         * Prevents users from deleting their own accounts
         * Deletes from all related tables:
         * - streams
         * - stream_filters
         * - stream_other
         * - stream_providers
         * 
         * @param int $userId User ID to delete
         * @param int $currentUserId Current admin's user ID
         * @return void
         * @throws Exception If attempting to delete own account
         */
        private function delete_user(int $userId, int $currentUserId) : void {
            if ($userId === $currentUserId) {
                throw new Exception('You cannot delete your own account');
            }
            
            $prefix = TBL_PREFIX;
            
            // Delete from all related tables
            $this->query("DELETE FROM {$prefix}streams WHERE id = ?")->bind([$userId])->execute();
            $this->query("DELETE FROM {$prefix}stream_filters WHERE id = ?")->bind([$userId])->execute();
            $this->query("DELETE FROM {$prefix}stream_other WHERE id = ?")->bind([$userId])->execute();
            $this->query("DELETE FROM {$prefix}stream_providers WHERE id = ?")->bind([$userId])->execute();
            
            // Delete the user
            $this->query("DELETE FROM {$prefix}users WHERE id = ?")->bind([$userId])->execute();
        }

        /**
         * Update user information
         * 
         * Validates and updates:
         * - First name
         * - Last name
         * - Email address
         * - Role/permissions
         * 
         * Prevents users from removing their own admin privileges
         * 
         * @param array $data {
         *     @type int $id User ID
         *     @type string $u_fname First name
         *     @type string $u_lname Last name
         *     @type string $u_email Email address
         *     @type int $u_role Role/permissions level
         * }
         * @param int $currentUserId Current admin's user ID
         * @return void
         * @throws Exception On validation failure or security violation
         */
        private function update_user(array $data, int $currentUserId) : void {
            // Validate email format
            if (!KPT::validate_email($data['u_email'])) {
                throw new Exception('Invalid email address');
            }
            
            // Prevent removing own admin privileges
            if ($data['id'] === $currentUserId && $data['u_role'] != 99) {
                throw new Exception('You cannot remove your own admin privileges');
            }
            
            // execute the update
            $this->query('UPDATE kptv_users SET u_fname = ?, u_lname = ?, u_email = ?, u_role = ?, u_updated = NOW() WHERE id = ?')
                 ->bind([
                     $data['u_fname'],
                     $data['u_lname'],
                     $data['u_email'],
                     $data['u_role'],
                     $data['id']
                 ])
                 ->execute();
        }

        /**
         * Handles the user management posts
         * 
         * @return void
         * @throws Exception On validation failure or security violation
         */
        public function handle_posts( ) : void {

            // grab the "global" items we'll need in here
            $action = $_POST['action'] ?? '';
            $userId = ( int )( $_POST['user_id'] ) ?? 0;
            $currentUser = KPT_User::get_current_user( );

            // Get pagination parameters from request
            $currentPage = $_GET['page'] ?? 1;
            $perPage = $_GET['per_page'] ?? 25;

            // switch the action we need to take
            switch ( $action ) {
                case 'toggle_active':
                    // toggle the user active status
                    $this -> toggle_user_active_status( $userId, $currentUser -> id );
                    KPT::message_with_redirect( '/admin/users?page=' . $currentPage . '&per_page=' . $perPage, 
                        'success', 'User status updated successfully.');
                    break;
                    
                case 'unlock':
                    // unlock/lock the user account
                    $this -> unlock_user_account( $userId );
                    KPT::message_with_redirect( '/admin/users?page=' . $currentPage . '&per_page=' . $perPage, 
                        'success', 'User account unlocked successfully.');
                    break;
                    
                case 'delete':
                    // delete a user
                    $this -> delete_user( $userId, $currentUser -> id );
                    KPT::message_with_redirect( '/admin/users?page=' . $currentPage . '&per_page=' . $perPage, 
                        'success', 'User deleted successfully.');
                    break;
                    
                case 'update':
                    // hold the data to update the user
                    $data = [
                        'u_fname' => KPT::sanitize_string( $_POST['u_fname'] ?? '' ),
                        'u_lname' => KPT::sanitize_string( $_POST['u_lname'] ?? '' ),
                        'u_email' => KPT::sanitize_string( $_POST['u_email'] ?? '' ),
                        'u_role' => ( int ) ( $_POST['u_role'] ?? 0 ),
                        'id' => $userId
                    ];
                    // update the user
                    $this -> update_user( $data, $currentUser -> id );
                    KPT::message_with_redirect( '/admin/users?page=' . $currentPage . '&per_page=' . $perPage, 
                        'success', 'User updated successfully.');
                    break;
            }

        }

    }

}
