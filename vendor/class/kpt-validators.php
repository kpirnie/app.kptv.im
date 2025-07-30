<?php
/** 
 * Validator Methods
 * 
 * This file contains the validator methods for the app
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 * 
*/

// We don't want to allow direct access to this
defined( 'KPT_PATH' ) || die( 'No direct script access allowed' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Validators' ) ) {

    /** 
     * Trait API_Validators
     * 
     * This trait contains static methods for data validation
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Tasks
     * 
    */
    trait KPT_Validators {

        /** 
         * validate_string
         * 
         * Static method for validating a string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid string
         * 
        */
        public static function validate_string( string $_val ) : bool {

            // check if the value is empty, then check if it's a string 
            return ( empty( $_val ) ) ? false : is_string( $_val );

        }

        /** 
         * validate_number
         * 
         * Static method for validating a number
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param var $_val Variable input to validate
         * 
         * @return bool Returns a true/false if the input is a valid number.
         * This includes float, decimal, integer, etc...
         * 
        */
        public static function validate_number( $_val ) : bool {

            // check if the value is empty, then check if it's a number 
            return ( empty( $_val ) ) ? false : is_numeric( $_val );
            
        }

        /** 
         * validate_alphanum
         * 
         * Static method for validating an alpha-numeric string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid alpha-numeric string
         * 
        */
        public static function validate_alphanum( string $_val ) : bool {

            // check if the value is empty, then check if it's alpha numeric or space, _, -
            return ( empty( $_val ) ) ? false : preg_match( '/^[\p{L}\p{N} ._-]+$/', $_val );

        }

        /** 
         * validate_username
         * 
         * Static method for validating an username string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid username string
         * 
        */
        public static function validate_username( string $_val ) : bool {

            // check if the value is empty, then check if it has alpha numeric characters, _, or - in it 
            return ( empty( $_val ) ) ? false : preg_match( '/^[\p{L}\p{N}._-]+$/', $_val );

        }

        /** 
         * validate_name
         * 
         * Static method for validating a name
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid name string
         * 
        */
        public static function validate_name( string $_value ) : bool {

            // validate the string
            if( ! preg_match( '/((^(?(?![^,]+?,)((.*?) )?(([A-Za-zà-üÀ-Ü\']*?) )?(([A-Za-zà-üÀ-Ü\']*?) )?)([A-ZÀ-Ü\']((\'|[a-z]{1,2})[A-ZÀ-Ü\'])?[a-zà-ü\']+))(?(?=,)(, ([A-Za-zà-üÀ-Ü\']*?))?( ([A-Za-zà-üÀ-Ü\']*?))?( ([A-Za-zà-üÀ-Ü\']*?))?)$)/', $_value ) ) {
                return false;
            }

            // otherwise, it all validates return true
            return true;

        }

        /** 
         * validate_email
         * 
         * Static method for validating an email address
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_val String email address to validate
         * 
         * @return bool Returns a true/false if the input is a valid string
         * 
        */
        public static function validate_email( string $_val ) : bool {

            // check if the value is empty, then check if it's an email address
            return ( empty( $_val ) ) ? false : filter_var( $_val, FILTER_VALIDATE_EMAIL );

        }

        /** 
         * validate_url
         * 
         * Static method for validating a URL
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_val String URL to validate
         * 
         * @return bool Returns a true/false if the input is a valid URL
         * 
        */
        public static function validate_url( string $_val ) : bool {

            // check if the value is empty
            if( empty( $_val ) ) {

                // it is, so return false
                return false;
            }

            // parse the URL
            $_url = parse_url( $_val );

            // we need a scheme at the least
            if( $_url['scheme'] != 'http' && $_url['scheme'] != 'https' ) {

                // we don't have a scheme, return false
                return false;
            }

            // we have made it this far, return the domain validation
            return filter_var( $_url['host'], FILTER_VALIDATE_DOMAIN );

        }

        /** 
         * validate_password
         * 
         * Static method for validating a password
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Tasks
         * 
         * @param string $_value String to validate
         * 
         * @return bool Returns a true/false if the input is a valid strong password
         *              Password Rules: 6-64 alphanumeric characters plus at least 1 !@#$%*
         * 
        */
        public static function validate_password( string $_value ) : bool {

            // validate the PW
            if( ! preg_match( '/(?=^.{8,64}$)(?=.[a-zA-Z\d])(?=.*[!@#$%*])(?!.*\s).*$/', $_value ) ) {
                return false;
            }
    
            // otherwise, it all validates return true
            return true;
        }

    }

}
