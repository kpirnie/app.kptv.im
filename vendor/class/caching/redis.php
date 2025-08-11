<?php
/**
 * KPT Cache - Redis Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Caching_Redis' ) ) {

    trait KPT_Caching_Redis {

        // initial Redis settings
        private static $_redis_settings = [
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'KPTV_APP:',
            'read_timeout' => 0,
            'connect_timeout' => 2,
            'persistent' => true,
            'retry_attempts' => 2,
            'retry_delay' => 100,
        ];

        /**
         * testRedisConnection
         * 
         * Test if Redis connection is actually working
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if Redis is available and working
         */
        private static function testRedisConnection( ) : bool {
            
            // try to fire up and connect to the redis server
            try {

                // set the class
                $redis = new Redis( );

                // connect it
                $connected = $redis -> pconnect(
                    self::$_redis_settings['host'],
                    self::$_redis_settings['port'],
                    self::$_redis_settings['connect_timeout']
                );
                
                // if we are not connected just return fals
                if ( ! $connected ) {
                    return false;
                }
                
                // Test with a simple ping
                $result = $redis -> ping( );
                
                // close the connection
                $redis -> close( );
                
                // return the success of the connection
                return $result === true || $result === '+PONG';
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "Redis test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * setRedisSettings
         * 
         * Configure Redis connection settings
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings Redis configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setRedisSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_redis_settings = array_merge( self::$_redis_settings, $settings );
            
            // Reset Redis connection if already initialized
            if ( self::$_initialized && self::$_redis !== null ) {
                try {
                    self::$_redis->close( );
                } catch ( Exception $e ) {
                    // Ignore close errors
                }
                self::$_redis = null;
            }
            
            return true;
        }

        /**
         * getRedis
         * 
         * Get Redis connection with proper error handling
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return ?Redis Returns Redis object or null on failure
         */
        private static function getRedis( ) : ?Redis {

            // if we do not have redis as of yet or connection was lost...
            if ( self::$_redis === null || ! self::isRedisConnected( ) ) {

                // reset the redis connection
                self::$_redis = null;
                
                // hold the initial attempt count
                $attempts = 0;

                // hold the maximum number of attempts we can make
                $max_attempts = self::$_redis_settings['retry_attempts'];

                // loop our initial and max attempts
                while ( $attempts <= $max_attempts ) {

                    // try to connect
                    try {

                        // hold the class
                        self::$_redis = new Redis( );
                        
                        // setup the connection
                        $connected = self::$_redis -> pconnect(
                            self::$_redis_settings['host'],
                            self::$_redis_settings['port'],
                            self::$_redis_settings['connect_timeout']
                        );
                        
                        // if the connection fails throw an exception
                        if ( ! $connected ) {
                            throw new RedisException( "Connection failed" );
                        }
                        
                        // select the configured database
                        self::$_redis -> select( self::$_redis_settings['database'] );
                        
                        // if we have a prefix, set it
                        if ( self::$_redis_settings['prefix'] ) {
                            self::$_redis -> setOption( Redis::OPT_PREFIX, self::$_redis_settings['prefix'] );
                        }
                        
                        // Test the connection
                        $ping_result = self::$_redis -> ping( );

                        // if the connection fails throw an exception
                        if ( $ping_result !== true && $ping_result !== '+PONG' ) {
                            throw new RedisException( "Ping test failed" );
                        }
                        
                        // return the redis connection
                        return self::$_redis;

                    // whoopsie...
                    } catch ( RedisException $e ) {

                        // set the error message, and set redis to null
                        self::$_last_error = $e->getMessage( );
                        self::$_redis = null;
                        
                        // if we're under the max attempts
                        if ( $attempts < $max_attempts ) {

                            // sleep for the configured length of seconds
                            usleep( self::$_redis_settings['retry_delay'] * 1000 );
                        }

                        // incremement the attempts
                        $attempts++;

                    }

                }
                
                // return nothing
                return null;
            }
            
            // return the redis connection
            return self::$_redis;

        }

        /**
         * isRedisConnected
         * 
         * Check if Redis connection is still alive
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if connected
         */
        private static function isRedisConnected( ) : bool {

            // do we have a redis connection?
            if ( self::$_redis === null ) {
                return false;
            }

            // try to ping the server
            try {

                // hold the result and return if it was successful or now
                $result = self::$_redis -> ping( );
                return $result === true || $result === '+PONG';

            // whoopsie... return false
            } catch ( RedisException $e ) {
                return false;
            }

        }

        /**
         * getFromRedis
         * 
         * Get item from Redis
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromRedis( string $_key ): mixed {

            // setup redis
            $redis = self::getRedis( );
            
            // if we're not connected return false
            if ( ! $redis ) {
                return false;
            }
            
            // try to retrieve an item
            try {

                // get the value
                $_val = $redis -> get( $_key );

                // if we have something, unserialize it and return it
                if ( $_val !== false ) {
                    return unserialize( $_val );
                }

            // whoopsie... set the error message and reset the connection
            } catch ( RedisException $e ) {
                self::$_last_error = $e -> getMessage( );
                self::$_redis = null; // Reset connection on error
            }
            
            // default return
            return false;

        }

        /**
         * setToRedis
         * 
         * Set item to Redis
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
        private static function setToRedis( string $_key, mixed $_data, int $_length ): bool {
            $redis = self::getRedis( );
            if ( ! $redis ) {
                return false;
            }
            
            try {
                $redis->del( $_key );
                return $redis->setex( $_key, $_length, serialize( $_data ) );
            } catch ( RedisException $e ) {
                self::$_last_error = $e->getMessage( );
                self::$_redis = null; // Reset connection on error
                return false;
            }
        }
        
    }

}
        