<?php
/**
 * Cache
 * 
 * Redis caching class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class isn't already in userspace
if ( ! class_exists( 'KPT_Cache' ) ) {

    /**
     * KPT_Cache
     * 
     * Redis caching class
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Tasks
     */
    class KPT_Cache {

        // Redis settings
        private static $_redis_settings = [
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'KPTV_APP:',
            'read_timeout' => 0,
            'connect_timeout' => 0,
            'persistent' => true,
            'retry_attempts' => 2,
            'retry_delay' => 100,
        ];

        // setup the internal properties
        private static $_redis = null;
        private static $_last_error = null;
        private static $_use_fallback = false;
        private static $_fallback_path = '/tmp/kpt_cache/';

        /**
         * Initialize the fallback directory
         * 
         * @return void Returns nothing
         */
        private static function initFallback( ) : void {

            // if the fallback path does not exist already
            if ( ! file_exists( self::$_fallback_path ) ) {

                // make the fallback path
                mkdir( self::$_fallback_path, 0755, true );
            }
        }

        /**
         * Try to fire up and configure redis for caching
         * 
         * @return ?object Returns a possible nullable redis object
         */
        private static function getRedis( ) : ?object {

            // if it's not already fired up
            if ( self::$_redis === null ) {
                
                // setup the retry attempts
                $attempts = 0;
                $max_attempts = self::$_redis_settings['retry_attempts'];

                // while we are not at the maximum attempts
                while ( $attempts <= $max_attempts ) {
                    
                    // try to fire up redis
                    try {

                        // setup the redis object
                        self::$_redis = new Redis( );
                        
                        // set the connection
                        $connected = self::$_redis -> pconnect(
                            self::$_redis_settings['host'],
                            self::$_redis_settings['port'],
                            self::$_redis_settings['connect_timeout']
                        );
                        
                        // if we are not connected, set the error and throw an exception
                        if ( !$connected ) {
                            self::$_last_error = "Redis connection failed";
                            self::$_redis = null;
                            throw new RedisException( "Connection failed" );
                        }
                        
                        // Select database
                        self::$_redis -> select( self::$_redis_settings['database'] );
                        
                        // Set prefix if needed
                        if (self::$_redis_settings['prefix'] ) {
                            self::$_redis -> setOption( Redis::OPT_PREFIX, self::$_redis_settings['prefix'] );
                        }
                        
                        // Set read timeout if needed
                        if ( isset( self::$_redis_settings['read_timeout'] ) ) {
                            self::$_redis -> setOption( Redis::OPT_READ_TIMEOUT, self::$_redis_settings['read_timeout'] );
                        }
                        
                        // Connection successful, return the redis object
                        self::$_use_fallback = false;
                        return self::$_redis;
                        
                    // whoopsie...
                    } catch ( RedisException $e ) {
                        self::$_last_error = $e -> getMessage( );
                        self::$_redis = null;
                        
                        // if we arent at max attempts
                        if ( $attempts < $max_attempts ) {

                            // sleep for the configured delay
                            usleep( self::$_redis_settings['retry_delay'] * 1000 );
                        }

                        // increment the attempts
                        $attempts++;
                    }

                }
                
                // All attempts failed, switch to fallback
                self::$_use_fallback = true;
                self::initFallback( );
                return false;
            }
            
            // return the redis object or false
            return self::$_redis;

        }

        /**
         * Check if Redis is healthy
         * 
         * @return bool If redis is healthy or not
         */
        public static function isHealthy( ) : bool {

            // get the redis object
            $redis = self::getRedis( );

            // if we don't have it, return false
            if ( ! $redis ) return false;
            
            // try to ping the redis server and return if it's ok
            try {
                return $redis -> ping( ) === true;

            // whoopsie
            } catch ( RedisException $e ) {

                // grab the error message and return false
                self::$_last_error = $e -> getMessage( );
                return false;
            }

        }

        /**
         * Get the last error message
         * 
         * @return ?string Returns a nullable string of the last error
         */
        public static function getLastError( ) : ?string {

            // return the last error if there is one
            return self::$_last_error;
        }

        /**
         * Get an item from cache
         * 
         * @param string $_key The key name
         * 
         * @return mixed Returns the item from cache if it exists
         */
        public static function get( string $_key ) : mixed {

            // First try Redis if available
            if ( ! self::$_use_fallback ) {

                // try to get the redis object
                $redis = self::getRedis( );

                // if we have it
                if ( $redis ) {

                    // try to get the object from redis
                    try {

                        // grab the value
                        $_val = $redis -> get( $_key );

                        // if we have it
                        if ( $_val !== false ) {

                            // unserialize it and return it
                            return unserialize( $_val );
                        }

                    // whoopsie...
                    } catch ( RedisException $e ) {
                        self::$_last_error = $e -> getMessage( );
                    }

                }

            }
            
            // Fallback to filesystem
            $file = self::$_fallback_path . md5( $_key );
            
            // if the file exists
            if ( file_exists( $file ) ) {

                // get the contents of it
                $data = file_get_contents( $file );
                
                // check when it expires
                $expires = substr( $data, 0, 10 );
                
                // if it's expired
                if ( time( ) > $expires ) {

                    // delete it and return false
                    unlink( $file );
                    return false;
                }
                
                // otherwise, return the unserialized content
                return unserialize( substr( $data, 10 ) );
            }
            
            // default return
            return false;

        }

        /**
         * Set an item in cache
         * 
         * @param string $_key The key name
         * @param mixed $_data The data to set to cache
         * @param int $_length How long does the data need to be cached for, defaults to 1 hour
         * @return bool Returns if the item was successfully set to cache or not
         */
        public static function set( string $_key, mixed $_data, int $_length = 3600 ) : bool {

            // make sure we have data, otherwise return false
            if ( ! $_data || empty( $_data ) ) {
                return false;
            }

            // First try Redis if available
            if ( ! self::$_use_fallback ) {

                // get the redis object
                $redis = self::getRedis( );

                // if we have it
                if ( $redis ) {

                    // try to delete the object if necessary and set a new one
                    try {
                        $redis -> del( $_key );
                        return $redis -> setex( $_key, $_length, serialize( $_data ) );

                    // whoopsie...
                    } catch ( RedisException $e ) {

                        // set the last error message
                        self::$_last_error = $e -> getMessage( );
                    }

                }

            }
            
            // Fallback to filesystem and grab the expiry
            $file = self::$_fallback_path . md5( $_key );
            $expires = time( ) + $_length;

            // serialize the data
            $data = $expires . serialize( $_data );
            
            // try to to write it to a file and return if it was true or not
            return file_put_contents( $file, $data ) !== false;

        }

        /**
         * Delete an item from cache
         * 
         * @param string $_key The key name
         * @return bool Returns if the item was successfully deleted or not
         */
        public static function del( string $_key ) : bool {

            // default success
            $success = true;
            
            // Try Redis if available
            if ( ! self::$_use_fallback ) {

                // get the redis object
                $redis = self::getRedis( );

                // if it's available
                if ( $redis ) {

                    // try to delete the object
                    try {

                        // was it successful?
                        $success = ( bool ) $redis -> del( $_key );

                    // whoopsie...
                    } catch ( RedisException $e ) {

                        // set the error message and false...
                        self::$_last_error = $e -> getMessage( );
                        $success = false;
                    }

                }

            }
            
            // Also delete from filesystem fallback
            $file = self::$_fallback_path . md5( $_key );

            // if the file exists
            if ( file_exists( $file ) ) {

                // return if it was successfully deleted or not
                $success = $success && unlink( $file );
            }
            
            // return the success 
            return $success;

        }

        /**
         * Clear all cache
         * 
         * @return bool Returns if the item was successfully cleared or not
         */
        public static function clear( ) : bool {

            // default to true
            $success = true;
            
            // Try Redis if available
            if ( ! self::$_use_fallback ) {

                // get the redis object
                $redis = self::getRedis( );

                // if we have it
                if ($redis) {

                    // try to flush the whole cache
                    try {
                        $success = $redis -> flushAll( );
                        
                        // if it wasn't successful
                        if ( ! $success ) {
                            self::$_last_error = "Redis flush failed";
                        }

                        // return if it was successful or not
                        return $success;

                    // whoopsie...
                    } catch ( RedisException $e ) {

                        // get the last error message and if it was successful or not
                        self::$_last_error = $e -> getMessage( );
                        $success = false;

                    }

                }

            }
            
            // Clear filesystem fallback
            $files = glob( self::$_fallback_path . '* ');
            
            // loop through the files
            foreach ( $files as $file ) {

                // if it exists
                if ( is_file( $file ) ) {

                    // delete the file and return the success rate...
                    $success = $success && unlink( $file );
                }
            }
            
            // return if it was successful or not
            return $success;

        }

        /**
         * Close the Redis connection
         * 
         * @return void Returns nothing
         */
        public static function close( ) : void {

            // if we have a redis instance
            if ( self::$_redis instanceof Redis ) {

                // try to close the connection
                try {
                    self::$_redis -> close( );

                // whoopsie...
                } catch ( RedisException $e ) {

                    // get the last error message
                    self::$_last_error = $e -> getMessage( );
                }

                // nullify the redis object
                self::$_redis = null;

            }

        }

    }

}
