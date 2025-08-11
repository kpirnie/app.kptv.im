<?php
/**
 * KPT Cache - APCu Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Caching_APCU' ) ) {

    trait KPT_Caching_APCU {

        // APCu settings
        private static $_apcu_settings = [
            'prefix' => 'KPTV_APP:',
            'ttl_default' => 3600,
        ];

        /**
         * testAPCuConnection
         * 
         * Test if APCu is actually working
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if APCu is available and working
         */
        private static function testAPCuConnection( ) : bool {
            
            // try to use APCu functionality
            try {

                // Check if APCu is enabled
                if ( ! apcu_enabled( ) ) {
                    return false;
                }

                // Test with a simple store/fetch operation
                $test_key = 'kpt_test_' . uniqid( );
                $test_value = 'test_value_' . time( );
                
                // Try to store and retrieve
                if ( apcu_store( $test_key, $test_value, 60 ) ) {
                    $retrieved = apcu_fetch( $test_key );
                    apcu_delete( $test_key ); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "APCu test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * setAPCuSettings
         * 
         * Configure APCu settings
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings APCu configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setAPCuSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_apcu_settings = array_merge( self::$_apcu_settings, $settings );
            
            return true;
        }

        /**
         * getFromAPCu
         * 
         * Get item from APCu
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromAPCu( string $_key ): mixed {

            // if APCu is not enabled, just return false
            if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled( ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // setup the prefixed key
                $prefixed_key = self::$_apcu_settings['prefix'] . $_key;
                
                // fetch the value
                $success = false;
                $value = apcu_fetch( $prefixed_key, $success );
                
                // if successful, return the value
                if ( $success ) {
                    return $value;
                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "APCu get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * setToAPCu
         * 
         * Set item to APCu
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
        private static function setToAPCu( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled( ) ) {
                return false;
            }
            
            try {
                $prefixed_key = self::$_apcu_settings['prefix'] . $_key;
                return apcu_store( $prefixed_key, $_data, $_length );
            } catch ( Exception $e ) {
                self::$_last_error = "APCu set error: " . $e->getMessage( );
                return false;
            }
        }

        

    }

}
        