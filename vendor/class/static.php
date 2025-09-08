<?php
/**
 * Static Functions
 * 
 * This is our primary static object class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// throw it under my namespace
namespace KPT;

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class does not already exist
if( ! class_exists( 'KStatic' ) ) {

    /** 
     * Class Static
     * 
     * OTV Static Objects
     * 
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     * 
     * @var int MINUTE_IN_SECONDS Constant defining 60 seconds
     * @var int HOUR_IN_SECONDS Constant defining 3600 seconds
     * @var int DAY_IN_SECONDS Constant defining 86400 seconds
     * @var int WEEK_IN_SECONDS Constant defining 604800 seconds based on 7 days
     * @var int MONTH_IN_SECONDS Constant defining 2592000 seconds based on 30 days
     * @var int YEAR_IN_SECONDS Constant defining 31536000 seconds based on 365 days
     * 
    */
    class KStatic {

        // Reusable static utilities (sanitization/validation)
        use \KPT\Validators, \KPT\Sanitizers;

        /**
         * These are our static time constants
         * They look familiar, because we're mimicing Wordpress's
         * time constants
         */
        const MINUTE_IN_SECONDS = 60;
        const HOUR_IN_SECONDS = ( self::MINUTE_IN_SECONDS * 60 );
        const DAY_IN_SECONDS = ( self::HOUR_IN_SECONDS * 24 );
        const WEEK_IN_SECONDS = ( self::DAY_IN_SECONDS * 7 );
        const MONTH_IN_SECONDS = ( self::DAY_IN_SECONDS * 30 );
        const YEAR_IN_SECONDS = ( self::DAY_IN_SECONDS * 365 );

        /** 
         * days_in_between
         * 
         * Populate a message with our redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_date1 The first date
         * @param string $_date2 The second date
         * 
         * @return int This method returns the number of days between 2 dates
         * 
        */
        public static function days_in_between( $_date1, $_date2 ) : int {

            //return the difference between the 2 dates
            return date_diff( date_create( $_date1 ), date_create( $_date2 ) ) -> format( '%a' );
        
        }

        /** 
         * manage_the_session
         * 
         * Attempt to manage our session
         * 
         * @since 8.3
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void This method returns nothing
         * 
        */
        public static function manage_the_session( ) : void {
            
            // check if the session has been started
            if( session_status( ) !== PHP_SESSION_ACTIVE ) {
                session_start( );
            }
            
            // Force session write and close to prevent locks
            register_shutdown_function( function( ) {
                if ( session_status( ) === PHP_SESSION_ACTIVE ) {
                    session_write_close( );
                }
            } );

        }

        /** 
         * selected
         * 
         * Output "selected" for a drop-down
         * 
         * @since 8.3
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param mixed $current The current item
         * @param mixed $expected The expected item
         * 
         * @return string Returns the string "selected" or empty
         * 
        */
        public static function selected( $current, $expected ) : string {

            // if they are equal, return selected
            return $current == $expected ? 'selected' : '';
        
        }
        
        /** 
         * message_with_redirect
         * 
         * Populate a message with our redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_location The page we want to try to redirect to
         * @param string $_msg_type The type os message we should be showing
         * @param string $_msg The message content
         * 
         * @return void This method returns nothing
         * 
        */
        public static function message_with_redirect( string $_location, string $_msg_type, string $_msg ) : void {

            // setup the message
            $_SESSION['page_msg']['type'] = $_msg_type;
            $_SESSION['page_msg']['msg'] = sprintf( '<p>%s</p>', $_msg );

            // redirect
            KPT::try_redirect( $_location );

        }

        /** 
         * try_redirect
         * 
         * Try to redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_location The page we want to try to redirect to
         * @param int $_status The HTTP status code used for the redirection: default 301 permanent
         * 
         * @return void This method returns nothing
         * 
        */
        public static function try_redirect( string $_location, int $_status = 301 ) : void {

            // setup an error handler to handle the possible PHP warning you could get for modifying headers after output
            set_error_handler( function( $errno, $errstr, $errfile, $errline ) {

                // make sure it throws an exception here
                throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );

            }, E_WARNING );

            // now we can setup a trap to catch the warning
            try {

                // try to redirect
                header( "Location: $_location", true, $_status );

            // caught it!
            } catch ( \ErrorException $e ) {

                // use javascript to do the redirect instead, as a fallback
                echo '<script type="text/javascript">setTimeout( function( ) { window.location.href="' . $_location . '"; }, 100 );</script>';

            }

            // return the default error handler
            restore_error_handler( );

            // now we need to kill anything extra after we do all of this
            exit;

        }

        /** 
         * get_image_path
         * 
         * Static method for formatting the path to the image we need
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_name The name of the image
         * @param string $_which Which image size do we need
         * 
         * @return string Returns the formatted path to the image
         * 
        */
        public static function get_image_path( string $_name, string $_which = 'header' ) : string {

            // return the path to the image
            return sprintf( "/assets/images/%s-%s.jpg", $_name, $_which );

        }

        /** 
         * get_full_config
         * 
         * Get our full app config
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return object This method returns a standard class object of our applications configuration
         * 
        */
        public static function get_full_config( ) {

            // hold the returnable object
            $_ret = new \stdClass( );

            // hold hte cache key
            $_cache_key = 'KPTV_config';

            // check the cache
            $_cached = Cache::get( $_cache_key );

            // if we do have this object
            if( $_cached ) {
                
                // just return it
                return $_cached;

            }

            // read the file
            $_conf = file_get_contents( KPT_PATH . 'assets/config.json' );

            // if there is nothing here, return an error object
            if( $_conf ) {

                // otherwise parse the json
                $_ret = json_decode( $_conf );

            }

            // set the config to cache, for 1 week
            Cache::set( $_cache_key, $_ret, self::WEEK_IN_SECONDS );

            // return the object
            return $_ret;

        }

        /** 
         * get_setting
         * 
         * Get a single setting value from our config object
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return mixed This method returns a variable value of the setting requested
         * 
        */
        public static function get_setting( string $_name ) {

            // get all our options
            $_all_opts = self::get_full_config( );

            // get the single option based on the shortname passed
            if( isset( $_all_opts -> {$_name} ) ) {
                
                // return the property
                return $_all_opts -> {$_name};
            }

            // default to returning null
            return null;

        }

        /** 
         * get_ordinal
         * 
         * Static method for formatting a number as an ordinal number string
         * ie: 1st, 32nd, 100th, etc...
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param int $_num The number to be formatted
         * 
         * @return string Returns the ordinal formatted number string
         * 
        */
        public static function get_oridinal( int $_num ) : ?string {

            // return the ordinal formatted number
            return $_num . substr( date( 'jS', mktime( 0, 0, 0, 1, ( $_num % 10 == 0 ? 9 : ( $_num % 100 > 20 ? $_num % 10 : $_num % 100 ) ), 2000 ) ), -2 );
        
        }

        /** 
         * find_in_array
         * 
         * Static method for determining if a string is in an array
         * search is case-insensitive
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_needle The string to find
         * @param array $_haystack The array to search
         * 
         * @return bool Returns if the string was found or not
         * 
        */
        public static function find_in_array( string $_needle, array $_haystack ) : bool {
            
            // loop the array
            foreach ( $_haystack as $_item ) {

                // if the item contains the string
                if ( false !== stripos( $_item, $_needle ) ) {
                    
                    // return true
                    return true;
                }
            }
        
            // default return
            return false;

        }

        /** 
         * multidim_array_sort
         * 
         * Static method for sorting a multi-dimensional array
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array &$_array ByRef array to be sorted
         * @param string $_subkey String to sort the array by
         * @param bool $_sort_asc Boolean to determine the sort order
         * 
        */
        public static function multidim_array_sort( array &$_array, string $_subkey = "id", bool $_sort_asc = false ) {

            // make sure there is at least 1 item
            if ( count( $_array ) )
                $temp_array[key( $_array )] = array_shift( $_array );
        
            // loop the array
            foreach( $_array as $key => $val ){

                // hold the offset
                $offset = 0;
                
                // hold the "found"
                $found = false;
                
                // loop over the inner keys
                foreach( $temp_array as $tmp_key => $tmp_val ) {

                    // if found and the orignating key equals the found key
                    if( ! $found and strtolower( $val[$_subkey] ) > strtolower( $tmp_val[$_subkey] ) ) {

                        // merge the arrays
                        $temp_array = array_merge( ( array ) array_slice( $temp_array, 0, $offset ), array( $key => $val ), array_slice( $temp_array, $offset ) );
                        
                        // return true
                        $found = true;
                    }

                    // increment the offset
                    $offset++;
                }

                // if not found, merge
                if( ! $found ) $temp_array = array_merge( $temp_array, array( $key => $val ) );
            }
        
            // if asc, reverse the sort
            if ( $_sort_asc ) $_array = array_reverse( $temp_array );
        
            // otherwise we're good to go
            else $_array = $temp_array;

        }

        /** 
         * object_to_array
         * 
         * Static method for converting an object to an array
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param object $_val The object to be converted
         * 
         * @return array Returns the converted array
         * 
        */
        public static function object_to_array( object $_val ) : array {

            // hold the returnable array
            $result = array( );
            
            // if there is an object to be converted
            if( $_val && is_object( $_val ) ) {

                // loop over the object properties
                foreach ( $_val as $key => $value ) {

                    // if the value is an array or object, convert it
                    $result[$key] = ( is_array( $value ) || is_object( $value ) ) ? self::object_to_array( $value ) : $value;

                }

            }
            
            // return the converted array
            return $result;

        }

        /** 
         * encrypt
         * 
         * Static method for encrypting a string utilizing openssl libraries
         * if openssl is not found, will simply base64_encode the string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val The string to be encrypted
         * 
         * @return string Returns the encrypted or encoded string
         * 
        */
        public static function encrypt( string $_val ) : string {

            // hold our return
            $_ret = '';

            // compress our value
            $_val = gzcompress( $_val );

            // make sure the openssl library exists
            if( ! function_exists( 'openssl_encrypt' ) ) {

                // it does not, so all we can really do is base64encode the string
                $_ret = base64_encode( $_val );

            // otherwise
            } else {

                // the encryption method
                $_enc_method = "AES-256-CBC";

                // generate a key based on the _key
                $_the_key = hash( 'sha256', self::get_setting( 'mainkey' ) );

                // generate an initialization vector based on the _secret
                $_iv = substr( hash( 'sha256', self::get_setting( 'mainsecret' ) ), 0, 16 );

                // return the base64 encoded version of our encrypted string
                $_ret = base64_encode( openssl_encrypt( $_val, $_enc_method, $_the_key, 0, $_iv ) );

            }

            // return our string
            return $_ret;

        }

        /** 
         * decrypt
         * 
         * Static method for decryption a string utilizing openssl libraries
         * if openssl is not found, will simply base64_decode the string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val The string to be encrypted
         * 
         * @return string Returns the decrypted or decoded string
         * 
        */
        public static function decrypt( string $_val ) : string {

            // hold our return
            $_ret = '';

            // make sure the openssl library exists
            if( ! function_exists( 'openssl_decrypt' ) ) {

                // it does not, so all we can really do is base64decode the string
                $_ret = base64_decode( $_val );

            // otherwise
            } else {

                // the encryption method
                $_enc_method = "AES-256-CBC";

                // generate a key based on the _key
                $_the_key = hash( 'sha256', self::get_setting( 'mainkey' ) );

                // generate an initialization vector based on the _secret
                $_iv = substr( hash( 'sha256', self::get_setting( 'mainsecret' ) ), 0, 16 );

                // return the decrypted string
                $_ret = openssl_decrypt( base64_decode( $_val ), $_enc_method, $_the_key, 0, $_iv );

            }

            // return our string
            return ( $_ret ) ? gzuncompress( $_ret ) : '';

        }

        /** 
         * generate_password
         * 
         * Generates a random "password" string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param int $_min_length The minimum lenght string to generate. Default 32
         * 
         * @return string The randomly generated string
         * 
        */
        public static function generate_password( int $_min_length = 32 ) : string {

            // hold the character set
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%*';
            
            // setup the returnable array
            $_ret = array( );

            // hold the length of the character set
            $alphaLength = strlen( $alphabet ) - 1; //put the length -1 in cache

            // get a random length
            $_length = rand( $_min_length, 64 );
            
            // loop over the alphabet string
            for ( $i = 0; $i < $_length; ++$i ) {
    
                // generate a random character
                $n = rand( 0, $alphaLength );
    
                // add it to the outputting array
                $_ret[] = $alphabet[ $n ];
            }
    
            // return the string
            return implode( $_ret ); //turn the array into a string   
    
        }

        /** 
         * generate_rand_string
         * 
         * Generates a random "password" string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param int $_min_length The minimum lenght string to generate. Default 8
         * 
         * @return string The randomly generated string
         * 
        */
        public static function generate_rand_string( int $_min_length = 8 ) : string {

            // hold the character set
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

            // setup the returnable array
            $_ret = array( );

            // hold the length of the character set
            $alphaLength = strlen( $alphabet ) - 1;

            // get a random length
            $_length = rand( $_min_length, 64 );
            
            // loop over the alphabet string
            for ( $i = 0; $i < $_length; ++$i ) {
    
                // generate a random character
                $n = rand( 0, $alphaLength );
    
                // add it to the outputting array
                $_ret[] = $alphabet[ $n ];
            }
    
            // return the string
            return implode( $_ret ); //turn the array into a string   
            
        }

        /** 
         * get_user_uri
         * 
         * Gets the current users URI that was attempted
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the URI
         * 
        */
        public static function get_user_uri( ) : string {

            // return the current URL
            return filter_var( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL );

        }

        /** 
         * get_user_ip
         * 
         * Gets the current users public IP address
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users public IP address
         * 
        */
        public static function get_user_ip( ) : string {

            // check if we've got a client ip header, and if it's valid
            if( isset( $_SERVER['HTTP_CLIENT_IP'] ) && filter_var( $_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {

                // return it
                return filter_var( $_SERVER['HTTP_CLIENT_IP'], FILTER_SANITIZE_URL );

            // maybe they're proxying?
            } elseif( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && filter_var( $_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {

                // return it
                return filter_var( $_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_SANITIZE_URL );

            // if all else fails, this should exist!
            } elseif( isset( $_SERVER['REMOTE_ADDR'] ) && filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {

                // return it
                return filter_var( $_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_URL );

            }

            // default return
            return '';

        }

        /** 
         * cidrMatch
         * 
         * match a possible cidr address
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users public IP address
         * 
        */
        public static function cidrMatch( $ip, $cidr ) {

            // Simple IP comparison if no CIDR mask
            if ( strpos( $cidr, '/' ) === false ) {
                return $ip === $cidr;
            }

            // CIDR range comparison
            list( $subnet, $mask ) = explode( '/', $cidr );
            $ipLong = ip2long( $ip );
            $subnetLong = ip2long( $subnet );
            $maskLong = ~( ( 1 << ( 32 - $mask ) ) - 1 );

            // return if it's in range or not
            return ( $ipLong & $maskLong ) === ( $subnetLong & $maskLong );
        }

        /** 
         * get_user_agent
         * 
         * Gets the current users browsers User Agent
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users browsers User Agent
         * 
        */
        public static function get_user_agent( ) : string {

            // possible browser info
            $_browser = @get_browser( );

            // let's see if the user agent header exists
            if( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {

                // return the user agent
                return htmlspecialchars( $_SERVER['HTTP_USER_AGENT'] );

            // let's see if we have browser data
            } elseif( $_browser ) {

                // return the browser name pattern
                return htmlspecialchars( $_browser -> browser_name_pattern );

            }

            // default return
            return '';
                
        }
    
        /** 
         * get_user_referer
         * 
         * Gets the current users referer
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users referer
         * 
        */
        public static function get_user_referer( ) : string {

            // return the referer if it exists
            return isset( $_SERVER['HTTP_REFERER'] ) ? filter_var( $_SERVER['HTTP_REFERER'], FILTER_SANITIZE_URL ) : '';

        }

        /** 
         * str_contains_any
         * 
         * Does a string contain any other string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_to_search The string we're searching
         * @param array $_searching The string we're searching for
         * 
         * @return bool This method returns true or false
         * 
        */
        public static function str_contains_any( string $_to_search, array $_searching ) : bool {

            // filter down the string
            return array_reduce( $_searching, fn( $a, $n ) => $a || str_contains( strtolower( $_to_search ), strtolower( $n ) ), false );

        }

        /** 
         * str_contains_any_re
         * 
         * Does a string contain any other string searching via regex
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_to_search The string we're searching
         * @param array $_searching The string we're searching for
         * 
         * @return bool This method returns truw or false
         * 
        */
        public static function str_contains_any_re( string $_to_search, array $_searching ) : bool {

            // default
            $_ret = false;

            // loop over what we're searching for
            foreach( $_searching as $_i ) {
                
                // setup out regex pattern
                $_pattern = '~' . $_i . '~i';

                // see if we have a match
                $_matched = filter_var( preg_match( $_pattern, $_to_search ), FILTER_VALIDATE_BOOLEAN );

                // append the regex match
                $_ret = $_ret || $_matched;

            }

            // return
            return $_ret;

        }

        /** 
         * send_email
         * 
         * Send an email through SMTP
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $_to Who is the email going to: email, name?
         * @param string $_subj What is the emails subject
         * @param string $_msg What is the emails message
         * 
         * @return bool Returns success or not
         * 
        */
        public static function send_email( array $_to, string $_subj, string $_msg ) : bool {

            //Create a new PHPMailer instance
            $mail = new \PHPMailer\PHPMailer\PHPMailer( );

            //Tell PHPMailer to use SMTP
            $mail -> isSMTP( );

            // if we want to debug
            if( filter_var( self::get_setting( 'smtp' ) -> debug, FILTER_VALIDATE_BOOLEAN ) ) {

                // set it to client and server debug
                $mail -> SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            }
            
            //Set the hostname of the mail server
            $mail -> Host = self::get_setting( 'smtp' ) -> server;

            // setup the type of SMTP security we'll use
            if( self::get_setting( 'smtp' ) -> security && 'tls' === self::get_setting( 'smtp' ) -> security ) {

                // set to TLS
                $mail -> SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            // just default to SSL
            } else {
                $mail -> SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            //Set the SMTP port number - likely to be 25, 465 or 587
            $mail -> Port = ( self::get_setting( 'smtp' ) -> port ) ?? 25;

            //Whether to use SMTP authentication
            $mail -> SMTPAuth = true;

            //Username to use for SMTP authentication
            $mail -> Username = self::get_setting( 'smtp' ) -> username;

            //Password to use for SMTP authentication
            $mail -> Password = self::get_setting( 'smtp' ) -> password;

            //Set who the message is to be sent from
            $mail -> setFrom( self::get_setting( 'smtp' ) -> fromemail, self::get_setting( 'smtp' ) -> fromname ); // email, name

            //Set who the message is to be sent to
            $mail -> addAddress( $_to[0], $_to[1] );

            // set if the email s)hould be HTML or not
            $mail -> isHTML( filter_var( self::get_setting( 'smtp' ) -> forcehtml, FILTER_VALIDATE_BOOLEAN ) );

            //Set the subject line
            $mail -> Subject = $_subj;

            // set the mail body
            $mail -> Body = $_msg;

            //send the message, check for errors
            if ( ! $mail -> send( ) ) {
                var_dump( $mail -> ErrorInfo );
                return false;
            } else {
                return true;
            }

        }

        /** 
         * mask_email_address
         * 
         * Mask an email address from bots
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_value The email address to mask
         * 
         * @return string The masked email address
         * 
        */
        public static function mask_email_address( string $_value ) : string {

            // hold the returnable string
            $_ret = '';

            // get the string length
            $_sl = strlen( $_value );

            // loop over the string
            for( $_i = 0; $_i < $_sl; $_i++ ) {

                // apppend the ascii val to the returnable string
                $_ret .= '&#' . ord( $_value[$_i] ) . ';';

            }

            // return it
            return $_ret;
            
        }

        /** 
         * show_message
         * 
         * Show a UIKit based message
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_type The type of message we need to show
         * @param string $_msg The message to show
         * 
         * @return void This method returns nothing
         * 
        */
        public static function show_message( string $_type, string $_msg ) : void {

            // build out our HTML for the alerts
            ?>
            <div class="dark-version uk-alert uk-alert-<?php echo $_type; ?> uk-padding-small">
                <?php
                    switch( $_type ) {
                        case 'success':
                            echo '<h5 class="me uk-margin-remove-bottom"><span uk-icon="icon: check"></span> Yahoo!</h5>';
                            break;
                        case 'warning':
                            echo '<h5 class="me uk-margin-remove-bottom"><span uk-icon="icon: question"></span> Hmm...</h5>';
                            break;
                        case 'danger':
                            echo '<h5 class="me uk-margin-remove-bottom"><span uk-icon="icon: warning"></span> Uh Ohhh!</h5>';
                            break;
                        case 'info':
                            echo '<h5 class="me uk-margin-remove-bottom"><span uk-icon="icon: info"></span> Heads Up</h5>';
                            break;
                    }
                ?>
                <?php echo $_msg; ?>
            </div>

            <?php
        }

        /** 
         * bool_to_icon
         * 
         * Just converts a boolean value to a UIKit icon
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param bool $_val The value to convert, default false
         * 
         * @return string Returns the inline icon
         * 
        */
        public static function bool_to_icon( bool $_val = false ) : string {

            // if the value is true
            if( $_val ) {

                // return a check mark icon
                return '<span uk-icon="check"></span>';

            }

            // return an X icon
            return '<span uk-icon="close"></span>';

        }

        /** 
         * array_key_contains_Subset
         * 
         * Checks if any key in an array contains a given subset string and returns the array item if found.
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $array The array to search through.
         * @param string $subset The subset string to look for in the keys.
         * @param bool $caseSensitive Whether the search should be case-sensitive. Default is true.
         * 
         * @return array|bool Returns an array if the passed array contains the subset in any key, otherwise returns false
         * 
        */
        public static function array_key_contains_Subset( array $array, string $subset, bool $caseSensitive = true ) : array|bool {

            // Return false immediately if the array is empty.
            if ( empty( $array ) ) {
                return false;
            }

            // Loop through the array keys
            foreach ( array_keys($array ) as $key ) {

                // If the key is not a string, convert it to a string
                if ( ! is_string( $key ) ) {
                    $key = ( string ) $key;
                }

                // if we need to check case sensitivity
                if ( $caseSensitive ) {

                    // check if the key contains the subset
                    if ( str_contains( $key, $subset ) ) {

                        // return the array item
                        return $array[$key];

                    }

                // otherwise, do a case-insensitive check
                } else {

                    // check if the key contains the subset, case-insensitive
                    if ( stripos( $key, $subset ) !== false ) {

                        // return the array item
                        return $array[$key];

                    }

                }

            }

            // default return
            return false;

        }

        /** 
         * is_page
         * 
         * Static method for determining if the current page is the one passed
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param mixed $_the_page The page to check against, can be a string or an array of strings
         * 
         * @return bool Returns true if the current page is the one passed, otherwise false
         * 
        **/
        public static function is_page( mixed $_the_page ) : bool {

            // set the data for the page we're on
            $_data = self::setup_page_data( );

            // if the passed is an array
            if( is_array( $_the_page ) ) {

                // see if the page we're on is in the array passed
                return in_array( $_data -> page, $_the_page );

            // otherwise, it's a string
            } elseif( is_string( $_the_page ) ) {

                // return whether we are on this page or not
                return ( $_data -> page === $_the_page );
            }

            // default return
            return false;

        }

        /** 
         * format_bytes
         * 
         * Static method for creating a human readable string from the number of bytes
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Human readable string for the bytes
         * 
        **/
        public static function format_bytes( int $size, int $precision = 2 ): string {
            
            // if the size is empty
            if ( $size <= 0 ) return '0 B';
            
            // base size for the calculation
            $base = log( $size, 1024 );
            $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
            
            // return the value
            return round( pow( 1024, $base - floor( $base ) ), $precision ) . ' ' . $suffixes[floor( $base )];
        }

        /** 
         * get_cache_prefix
         * 
         * Static method for creating a normalized global cache prefix
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string A formatted cache key based on the uri the user browsed
         * 
        **/
        public static function get_cache_prefix( ): string {

            // set the uri
            $uri = self::get_user_uri( );

            // Remove protocol and www prefix
            $clean_uri = preg_replace( '/^(https?:\/\/)?(www\.)?/', '', $uri );
            
            // Remove trailing slashes and paths
            $clean_uri = preg_replace( '/\/.*$/', '', $clean_uri );
            
            // replace non-alphanumeric with underscores
            $clean_uri = preg_replace( '/[^a-zA-Z0-9]/', '_', $clean_uri );
            
            // Remove consecutive underscores
            $clean_uri = preg_replace( '/_+/', '_', $clean_uri );
            
            // Trim underscores from ends
            $clean_uri = trim( $clean_uri, '_' );
            
            // Ensure it starts with a letter (some cache backends require this)
            if ( ! preg_match( '/^[A-Za-z]/', $clean_uri ) ) {
                $clean_uri = 'S_' . $clean_uri;
            }
            
            // Limit length for cache key compatibility
            $clean_uri = substr( $clean_uri, 0, 20 );
            
            // Always end with colon separator
            return $clean_uri . ':';
        }

        /** 
         * get_redirect_url
         * 
         * Static method for getting the redirect url for crud actions
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string The full URL
         * 
        **/
        public static function get_redirect_url( ) : string {

            // parse out the querystring
            $query_string = parse_url( KPT::get_user_uri( ), PHP_URL_QUERY ) ?? '';
            
            // parse out the actual URL including the path browsed
            $url = parse_url( KPT::get_user_uri( ), PHP_URL_PATH ) ?? '/';

            // return the formatted string
            return sprintf( '%s?%s', $url, $query_string );

        }

        /**
         * Includes a view file with passed data
         * 
         * @param string $view_name Name of the view file (without extension)
         * @param array $data Associative array of data to pass to the view
         */
        public static function include_view( string $view_name, array $data = [] ) : void {

            // Extract data to variables
            extract( $data );
            
            // Include the view file
            include KPT_PATH . "/views/{$view_name}.php";
        
        }

        /** 
         * pull_header
         * 
         * Static method for pulling the sites header
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $data Associative array of data to pass to the view
         * 
         * @return void Returns nothing
         * 
        */
        public static function pull_header( array $data = [] ) : void {

            // include the header and pass data if any
            self::include_view( 'wrapper/header', $data );

        }

        /** 
         * pull_footer
         * 
         * Static method for pulling the sites footer
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $data Associative array of data to pass to the view
         * 
         * @return void Returns nothing
         * 
        */
        public static function pull_footer( array $data = [] ) {

            // include the header and pass data if any
            self::include_view( 'wrapper/footer', $data );

        }


        public static function moveFromOther( $database, $selectedIds, $which ) : bool {

            // Use transaction for multiple operations
            $database -> transaction( );
            try {
                
                // loop the IDs
                foreach($selectedIds as $id) {
                    
                    // Call stored procedure for each ID
                    $result = $database
                        -> query( 'CALL Streams_Move_From_Other(?, ?)' )
                        -> bind( [$id, $which] )
                        -> execute( );
                    
                    // Check if sproc failed
                    if ( $result === false ) {
                        $database -> rollback( );
                        return false;
                    }
                }
                
                // Commit if all successful
                $database -> commit( );
                return true;
                
            } catch ( \Exception $e ) {
                // Rollback on error
                $database -> rollback( );
                return false;
            }

        }

        public static function moveToType( $database, $id, $type, $which = 'toother' ) : bool {

            // Use transaction for multiple operations
            $database -> transaction( );
            try {
                
                // do we want to move to other?
                if( $which === 'toother' ) {

                    // Call stored procedure for each ID
                    $result = $database
                        -> query( 'CALL Streams_Move_To_Other(?)' )
                        -> bind( [$id] )
                        -> execute( );
                }
                // do we want to move from other?
                elseif( $which === 'fromother' ) {

                    // Call stored procedure for each ID
                    $result = $database
                        -> query( 'CALL Streams_Move_From_Other(?, ?)' )
                        -> bind( [$id, $type] )  // Fixed: was using $which instead of $type
                        -> execute( );
                }
                // move live or series
                elseif( $which === 'liveorseries' ) {

                    // update the streams type
                    $result = $database
                        -> query( 'UPDATE `kptv_streams` SET `s_type_id` = ? WHERE `id` = ?' )
                        -> bind( [$type, $id] )
                        -> execute( );
                    
                }
                
                // Check if operation failed
                if ( $result === false ) {
                    $database -> rollback( );
                    return false;
                }

                // Commit if all successful
                $database -> commit( );
                return true;
                
            } catch ( \Exception $e ) {
                // Rollback on error
                $database -> rollback( );
                return false;
            }

        }

    }

}

// create our fake alias if it doesn't already exist
if( ! class_exists( 'KPT' ) ) {

    // redeclare this
    class KPT extends KStatic {}

}
