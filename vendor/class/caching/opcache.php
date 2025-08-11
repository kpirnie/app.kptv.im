<?php
/**
 * KPT Cache - OPCache Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Caching_OPCache' ) ) {

    trait KPT_Caching_OPCache {


        /**
         * isOPcacheEnabled
         * 
         * Check if OPcache is properly enabled
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if OPcache is enabled
         */
        private static function isOPcacheEnabled( ) : bool {

            // first check if the opcache functions exist
            if ( ! function_exists( 'opcache_get_status' ) ) {
                return false;
            }
            
            // just try to get the opcache status
            $status = opcache_get_status( false );

            // return the success of the opcache being enabled
            return is_array( $status ) && isset( $status['opcache_enabled'] ) && $status['opcache_enabled'];

        }

        /**
         * getFromOPcache
         * 
         * Get item from OPcache - Simplified implementation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromOPcache( string $_key ): mixed {

            // if opcache is not enabled, just return false
            if ( ! self::isOPcacheEnabled( ) ) {
                return false;
            }
            
            // setup the cache key file
            $opcache_key = self::$_opcache_prefix . md5( $_key );
            $temp_file = sys_get_temp_dir( ) . '/' . $opcache_key . '.php';
            
            // if the file does not exist, return false
            if ( ! file_exists( $temp_file ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // Include the file to get cached data
                $data = include $temp_file;
                
                // if the data is an array
                if ( is_array( $data ) && isset( $data['expires'], $data['value'] ) ) {

                    // if it isn't expired yet
                    if ( $data['expires'] > time( ) ) {

                        // return the cached value
                        return $data['value'];

                    // otherwise it's expired
                    } else {

                        // remove file
                        @unlink( $temp_file );
                        
                        // if the invalidation functionality exists, then invalidate it
                        if ( function_exists( 'opcache_invalidate' ) ) {
                            @opcache_invalidate( $temp_file, true );
                        }

                    }

                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "OPcache get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * setToOPcache
         * 
         * Set item to OPcache - Simplified implementation
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
        private static function setToOPcache( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! self::isOPcacheEnabled( ) ) {
                return false;
            }
            
            $opcache_key = self::$_opcache_prefix . md5( $_key );
            $temp_file = sys_get_temp_dir( ) . '/' . $opcache_key . '.php';
            $expires = time( ) + $_length;
            $content = "<?php return " . var_export( ['expires' => $expires, 'value' => $_data], true ) . ";";
            
            try {
                if ( file_put_contents( $temp_file, $content, LOCK_EX ) !== false ) {
                    // Try to compile to OPcache
                    if ( function_exists( 'opcache_compile_file' ) ) {
                        @opcache_compile_file( $temp_file );
                    }
                    return true;
                }
            } catch ( Exception $e ) {
                self::$_last_error = "OPcache set error: " . $e->getMessage( );
            }
            
            return false;
        }

        
        
    }

}
        