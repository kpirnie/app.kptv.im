<?php
/**
 * KPT Cache - Memcached Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_Memcached' ) ) {

    trait KPT_Cache_Memcached {
        
        // initial Memcached settings
        private static $_memcached_settings = [
            'host' => 'localhost',
            'port' => 11211,
            'prefix' => 'KPTV_APP:',
            'persistent' => true,
            'retry_attempts' => 2,
            'retry_delay' => 100,
        ];

        /**
         * testMemcachedConnection
         * 
         * Test if Memcached connection is actually working
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if Memcached is available and working
         */
        private static function testMemcachedConnection( ) : bool {
            
            // try to fire up and connect to the memcached server
            try {
                
                // set the class
                $memcached = new Memcached( );
                
                // add the server and attempt to connect
                $memcached -> addServer(
                    self::$_memcached_settings['host'],
                    self::$_memcached_settings['port']
                );
                
                // Test with the getStats function
                $stats = $memcached -> getStats( );
                
                // disconnect from the server
                $memcached -> quit( );
                
                // return the success of the connection
                return ! empty( $stats );
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "Memcached test failed: " . $e -> getMessage( );
                return false;
            }

        }


        /**
         * setMemcachedSettings
         * 
         * Configure Memcached connection settings
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings Memcached configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setMemcachedSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_memcached_settings = array_merge( self::$_memcached_settings, $settings );
            
            // Reset Memcached connection if already initialized
            if ( self::$_initialized && self::$_memcached !== null ) {
                self::$_memcached->quit( );
                self::$_memcached = null;
            }
            
            return true;
        }

        /**
         * getMemcached
         * 
         * Get Memcached connection with proper error handling
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return ?Memcached Returns Memcached object or null on failure
         */
        private static function getMemcached( ): ?Memcached {

            // are we not connected to memcached already?
            if ( self::$_memcached === null || ! self::isMemcachedConnected( ) ) {

                // reset the connection
                self::$_memcached = null;
                
                // set the initial attempts
                $attempts = 0;
                
                // setup the maximum attempts
                $max_attempts = self::$_memcached_settings['retry_attempts'];

                // loop from initial to max attempts
                while ( $attempts <= $max_attempts ) {

                    // try to connect to memcached
                    try {

                        // setup the new class
                        self::$_memcached = new Memcached( self::$_memcached_settings['persistent'] ? 'kpt_pool' : null );
                        
                        // Only add servers if not using persistent connections or if no servers exist
                        if ( ! self::$_memcached_settings['persistent'] || count( self::$_memcached->getServerList( ) ) === 0 ) {
                            self::$_memcached -> addServer(
                                self::$_memcached_settings['host'],
                                self::$_memcached_settings['port']
                            );
                        }
                        
                        // Set options
                        self::$_memcached->setOption( Memcached::OPT_LIBKETAMA_COMPATIBLE, true );
                        self::$_memcached->setOption( Memcached::OPT_BINARY_PROTOCOL, true );
                        
                        // Test connection
                        $stats = self::$_memcached -> getStats( );
                        
                        // if we do not have stats, throw an exeption
                        if ( empty( $stats ) ) {
                            throw new Exception( "Memcached connection test failed" );
                        }

                        // return the connection
                        return self::$_memcached;
                        
                    // whoopsie...
                    } catch ( Exception $e ) {

                        // set the last message and null out the object
                        self::$_last_error = $e -> getMessage( );
                        self::$_memcached = null;
                        
                        // if we're in between the attempts
                        if ( $attempts < $max_attempts ) {

                            // sleep for the configured number of seconds
                            usleep( self::$_memcached_settings['retry_delay'] * 1000 );
                        }

                        // increment the attempts
                        $attempts++;

                    }

                }
                
                // return null
                return null;

            }
            
            // return the connection
            return self::$_memcached;

        }

        /**
         * isMemcachedConnected
         * 
         * Check if Memcached connection is still alive
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if connected
         */
        private static function isMemcachedConnected( ) : bool {

            // if we do not have a connection, return false
            if ( self::$_memcached === null ) {
                return false;
            }
            
            // try the connection
            try {

                // utilize the memcached class getStats function
                $stats = self::$_memcached -> getStats( );
                return ! empty( $stats );
            
            // whoopsie... return false
            } catch ( Exception $e ) {
                return false;
            }

        }


        /**
         * getFromMemcached
         * 
         * Get item from Memcached
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromMemcached( string $_key ): mixed {

            // get the memecached object
            $memcached = self::getMemcached( );

            // if we don't have it, just return false
            if ( ! $memcached ) {
                return false;
            }
            
            // try to get the item
            try {

                //setup the key
                $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
                
                // retrieve the item
                $result = $memcached -> get( $prefixed_key );
                
                // as long as it was successfully retrieved, return it
                if ( $memcached -> getResultCode( ) === Memcached::RES_SUCCESS ) {
                    return $result;
                }

            // whoopsie...
            } catch ( Exception $e ) {
                self::$_last_error = $e->getMessage( );
                self::$_memcached = null; // Reset connection on error
            }
            
            // default return
            return false;

        }

        /**
         * setToMemcached
         * 
         * Set item to Memcached
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
        private static function setToMemcached( string $_key, mixed $_data, int $_length ): bool {
            $memcached = self::getMemcached( );
            if ( ! $memcached ) {
                return false;
            }
            
            try {
                $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
                return $memcached->set( $prefixed_key, $_data, time( ) + $_length );
            } catch ( Exception $e ) {
                self::$_last_error = $e->getMessage( );
                self::$_memcached = null; // Reset connection on error
                return false;
            }
        }

        
        
    }

}
        