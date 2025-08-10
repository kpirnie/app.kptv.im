<?php
/** 
 * Sanitizer Methods
 * 
 * This file contains the sanitizer methods for the app
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
*/

// We don't want to allow direct access to this
defined( 'KPT_PATH' ) || die( 'No direct script access allowed' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Sanitizers' ) ) {

    /** 
     * Trait API_Sanitizers
     * 
     * This trait contains static methods for data sanitization
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     * 
    */
    trait KPT_Sanitizers {

        /** 
         * sanitize_string
         * 
         * Static method for sanitizing a string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String to sanitize
         * 
         * @return string Returns a sanitized string
         * 
        */
        public static function sanitize_string( string $_val ) : string {

            // return the sanitized string, or empty
            return addslashes( $_val );

        }

        /** 
         * sanitize_numeric
         * 
         * Static method for sanitizing a number
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param var $_val Number to sanitize
         * 
         * @return var Returns a sanitized number
         * 
        */
        public static function sanitize_numeric( $_val ) {

            // return the sanitized string, or 0
            return filter_var( $_val, FILTER_SANITIZE_NUMBER_FLOAT );

        }

        /** 
         * sanitize_the_email
         * 
         * Static method for sanitizing an email address
         * 
         * @since 8.4
         * @access public
         * @static 
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val Email address to sanitize
         * 
         * @return string Returns a sanitized email address
         * 
        */
        public static function sanitize_the_email( string $_val ) : string {

            // return the sanitized email, or empty
            return ( empty( $_val ) ) ? '' : filter_var( $_val, FILTER_SANITIZE_EMAIL );

        }

        /** 
         * sanitize_url
         * 
         * Static method for sanitizing a url
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val URL to sanitize
         * 
         * @return string Returns a sanitized URL
         * 
        */
        public static function sanitize_url( string $_val ) : string {

            // return the sanitized url, or empty
            return ( empty( $_val ) ) ? '' : filter_var( $_val, FILTER_SANITIZE_URL );

        }

        /** 
         * sanitize_css_js
         * 
         * Static method for sanitizing a CSS or JS
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val CSS or JS to sanitize
         * 
         * @return string Returns sanitized CSS or JS
         * 
        */
        public static function sanitize_css_js( string $_val ) : string {

            // strip out script and style tags
            $string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $_val );

            // strip out all other tage
            $string = strip_tags( $string );

            // return the trimmed value
            return trim( $string );

        }

        /** 
         * sanitize_svg
         * 
         * Static method for sanitizing a svg's xml content
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_svg_xml SVG XML to sanitize
         * 
         * @return string Returns sanitized SVG XML
         * 
        */
        public static function sanitize_svg( string $_svg_xml ) : ?string  {

            // if the string is empty
            if( empty( $_svg_xml ) ) {

                // just return an empty string
                return '';
            }

            // return the clean xml
            return $_svg_xml;

        }

    }

}
