<?php
/**
 * KPT Cache - YAC Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Caching_YAC' ) ) {

    trait KPT_Caching_YAC {
        
        // Yac settings
        private static $_yac_settings = [
            'prefix' => 'KPTV_APP:',
            'ttl_default' => 3600,
        ];

        /**
         * testYacConnection
         * 
         * Test if Yac cache is actually working
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if Yac is available and working
         */
        private static function testYacConnection( ) : bool {
            
            // try to use Yac functionality
            try {

                // Test with a simple store/fetch operation
                $test_key = self::$_yac_settings['prefix'] . 'test_' . uniqid( );
                $test_value = 'test_value_' . time( );
                
                // Try to store and retrieve
                if ( yac_add( $test_key, $test_value, 60 ) ) {
                    $retrieved = yac_get( $test_key );
                    yac_delete( $test_key ); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "Yac test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * setYacSettings
         * 
         * Configure Yac settings
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings Yac configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setYacSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_yac_settings = array_merge( self::$_yac_settings, $settings );
            
            return true;
        }

        /**
         * getFromYac
         * 
         * Get item from Yac cache
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromYac( string $_key ): mixed {

            // if Yac is not loaded, just return false
            if ( ! extension_loaded( 'yac' ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // setup the prefixed key
                $prefixed_key = self::$_yac_settings['prefix'] . $_key;
                
                // fetch the value
                $value = yac_get( $prefixed_key );
                
                // Yac returns false for non-existent keys
                return $value !== false ? $value : false;

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "Yac get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * setToYac
         * 
         * Set item to Yac
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds
         * @return bool Returns true if successful
         */
        private static function setToYac( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! extension_loaded( 'yac' ) ) {
                return false;
            }
            
            try {
                $prefixed_key = self::$_yac_settings['prefix'] . $_key;
                return yac_set( $prefixed_key, $_data, $_length );
            } catch ( Exception $e ) {
                self::$_last_error = "Yac set error: " . $e->getMessage( );
                return false;
            }
        }        

    }

}
        