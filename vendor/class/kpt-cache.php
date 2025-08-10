<?php
/**
 * Cache
 * 
 * Multi-tier caching class with OPcache, Redis, Memcached, and File fallbacks
 * 
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class isn't already in userspace
if ( ! class_exists( 'KPT_Cache' ) ) {

    /**
     * KPT_Cache
     * 
     * Multi-tier caching class with hierarchical fallbacks
     * 
     * @since 8.5
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class KPT_Cache {

        // Cache tier constants
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_OPCACHE = 'opcache';
        const TIER_FILE = 'file';

        // Redis settings
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

        // Memcached settings
        private static $_memcached_settings = [
            'host' => 'localhost',
            'port' => 11211,
            'prefix' => 'KPTV_APP:',
            'persistent' => true,
            'retry_attempts' => 2,
            'retry_delay' => 100,
        ];

        // setup the internal properties
        private static $_redis = null;
        private static $_memcached = null;
        private static $_last_error = null;
        private static $_available_tiers = [];
        private static $_fallback_path = null;
        private static $_opcache_prefix = 'KPT_OPCACHE_';
        private static $_initialized = false;
        private static $_configurable_cache_path = null;

        /**
         * init
         * 
         * Initialize the cache system and determine available tiers
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        private static function init( ) : void {

            // if we're already initialized, dump out of the function
            if ( self::$_initialized ) {
                return;
            }
            
            // Initialize default path if not set
            if ( self::$_fallback_path === null ) {
                self::$_fallback_path = sys_get_temp_dir( ) . '/kpt_cache/';
            }
            
            // hold the available cache tiers
            self::$_available_tiers = [];
            
            // Check Redis availability (first priority) - Test actual connection
            if ( class_exists( 'Redis' ) && self::testRedisConnection( ) ) {
                self::$_available_tiers[] = self::TIER_REDIS;
            }
            
            // Check Memcached availability (second priority) - Test actual connection
            if ( class_exists( 'Memcached' ) && self::testMemcachedConnection( ) ) {
                self::$_available_tiers[] = self::TIER_MEMCACHED;
            }
            
            // Check OPcache availability (third priority) - Simplified check
            if ( function_exists( 'opcache_get_status' ) && self::isOPcacheEnabled( ) ) {
                self::$_available_tiers[] = self::TIER_OPCACHE;
            }
            
            // File cache is always available (last fallback)
            self::$_available_tiers[] = self::TIER_FILE;
            self::initFallback( );
            
            // we are initialized at this point
            self::$_initialized = true;
        }

        /**
         * testRedisConnection
         * 
         * Test if Redis connection is actually working
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if Redis is available and working
         */
        private static function testRedisConnection( ) : bool {
            try {
                $redis = new Redis( );
                $connected = $redis->pconnect(
                    self::$_redis_settings['host'],
                    self::$_redis_settings['port'],
                    self::$_redis_settings['connect_timeout']
                );
                
                if ( ! $connected ) {
                    return false;
                }
                
                // Test with a simple ping
                $result = $redis->ping( );
                $redis->close( );
                
                return $result === true || $result === '+PONG';
                
            } catch ( Exception $e ) {
                self::$_last_error = "Redis test failed: " . $e->getMessage( );
                return false;
            }
        }

        /**
         * testMemcachedConnection
         * 
         * Test if Memcached connection is actually working
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if Memcached is available and working
         */
        private static function testMemcachedConnection( ) : bool {
            try {
                $memcached = new Memcached( );
                $memcached->addServer(
                    self::$_memcached_settings['host'],
                    self::$_memcached_settings['port']
                );
                
                // Test with getStats
                $stats = $memcached->getStats( );
                $memcached->quit( );
                
                return ! empty( $stats );
                
            } catch ( Exception $e ) {
                self::$_last_error = "Memcached test failed: " . $e->getMessage( );
                return false;
            }
        }

        /**
         * isOPcacheEnabled
         * 
         * Check if OPcache is properly enabled
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if OPcache is enabled
         */
        private static function isOPcacheEnabled( ) : bool {
            if ( ! function_exists( 'opcache_get_status' ) ) {
                return false;
            }
            
            $status = opcache_get_status( false );
            return is_array( $status ) && isset( $status['opcache_enabled'] ) && $status['opcache_enabled'];
        }

        /**
         * setCachePath
         * 
         * Set a custom cache path for file-based caching
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_path The custom cache path
         * @return bool Returns true if path is valid and writable
         */
        public static function setCachePath( string $_path ) : bool {
            // Normalize the path (ensure it ends with a slash)
            $_path = rtrim( $_path, '/' ) . '/';
            
            // Check if path is writable or can be created
            if ( ! file_exists( $_path ) ) {
                if ( ! mkdir( $_path, 0755, true ) ) {
                    return false;
                }
            }
            
            if ( ! is_writable( $_path ) ) {
                return false;
            }
            
            self::$_configurable_cache_path = $_path;
            return true;
        }

        /**
         * getCachePath
         * 
         * Get the current cache path being used
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns the current cache path
         */
        public static function getCachePath( ) : string {
            return self::$_fallback_path;
        }

        /**
         * getAvailableTiers
         * 
         * Get available cache tiers
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns array of available cache tiers
         */
        public static function getAvailableTiers( ) : array {
            // make sure we're initialized
            self::ensureInitialized( );
            return self::$_available_tiers;
        }

        /**
         * ensureInitialized
         * 
         * Ensure the cache system is initialized
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        private static function ensureInitialized( ) : void {
            // if we are not initialized yet, initialize us!
            if ( ! self::$_initialized ) {
                self::init( );
            }
        }

        /**
         * initFallback
         * 
         * Initialize the fallback directory
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        private static function initFallback( ) : void {
            // Use configurable path if set, otherwise default
            $cache_path = self::$_configurable_cache_path ?: self::$_fallback_path;
            
            $attempts = 0;
            while ( ! file_exists( $cache_path ) && $attempts < 3 ) {
                if ( ! mkdir( $cache_path, 0755, true ) ) {
                    $attempts++;
                    usleep( 100000 ); // 100ms delay between attempts
                    continue;
                }
                break;
            }
            
            // Update the fallback path
            self::$_fallback_path = $cache_path;
        }

        /**
         * getRedis
         * 
         * Get Redis connection with proper error handling
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return ?Redis Returns Redis object or null on failure
         */
        private static function getRedis( ) : ?Redis {
            // if we do not have redis as of yet or connection was lost...
            if ( self::$_redis === null || ! self::isRedisConnected( ) ) {
                self::$_redis = null; // Reset connection
                
                $attempts = 0;
                $max_attempts = self::$_redis_settings['retry_attempts'];

                while ( $attempts <= $max_attempts ) {
                    try {
                        self::$_redis = new Redis( );
                        
                        $connected = self::$_redis->pconnect(
                            self::$_redis_settings['host'],
                            self::$_redis_settings['port'],
                            self::$_redis_settings['connect_timeout']
                        );
                        
                        if ( ! $connected ) {
                            throw new RedisException( "Connection failed" );
                        }
                        
                        // select the configured database
                        self::$_redis->select( self::$_redis_settings['database'] );
                        
                        // if we have a prefix, set it
                        if ( self::$_redis_settings['prefix'] ) {
                            self::$_redis->setOption( Redis::OPT_PREFIX, self::$_redis_settings['prefix'] );
                        }
                        
                        // Test the connection
                        $ping_result = self::$_redis->ping( );
                        if ( $ping_result !== true && $ping_result !== '+PONG' ) {
                            throw new RedisException( "Ping test failed" );
                        }
                        
                        return self::$_redis;
                        
                    } catch ( RedisException $e ) {
                        self::$_last_error = $e->getMessage( );
                        self::$_redis = null;
                        
                        if ( $attempts < $max_attempts ) {
                            usleep( self::$_redis_settings['retry_delay'] * 1000 );
                        }
                        $attempts++;
                    }
                }
                
                return null;
            }
            
            return self::$_redis;
        }

        /**
         * isRedisConnected
         * 
         * Check if Redis connection is still alive
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if connected
         */
        private static function isRedisConnected( ) : bool {
            if ( self::$_redis === null ) {
                return false;
            }
            
            try {
                $result = self::$_redis->ping( );
                return $result === true || $result === '+PONG';
            } catch ( RedisException $e ) {
                return false;
            }
        }

        /**
         * getMemcached
         * 
         * Get Memcached connection with proper error handling
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return ?Memcached Returns Memcached object or null on failure
         */
        private static function getMemcached( ): ?Memcached {
            if ( self::$_memcached === null || ! self::isMemcachedConnected( ) ) {
                self::$_memcached = null; // Reset connection
                
                $attempts = 0;
                $max_attempts = self::$_memcached_settings['retry_attempts'];

                while ( $attempts <= $max_attempts ) {
                    try {
                        self::$_memcached = new Memcached( self::$_memcached_settings['persistent'] ? 'kpt_pool' : null );
                        
                        // Only add servers if not using persistent connections or if no servers exist
                        if ( ! self::$_memcached_settings['persistent'] || count( self::$_memcached->getServerList( ) ) === 0 ) {
                            self::$_memcached->addServer(
                                self::$_memcached_settings['host'],
                                self::$_memcached_settings['port']
                            );
                        }
                        
                        // Set options
                        self::$_memcached->setOption( Memcached::OPT_LIBKETAMA_COMPATIBLE, true );
                        self::$_memcached->setOption( Memcached::OPT_BINARY_PROTOCOL, true );
                        
                        // Test connection
                        $stats = self::$_memcached->getStats( );
                        if ( empty( $stats ) ) {
                            throw new Exception( "Memcached connection test failed" );
                        }
                        
                        return self::$_memcached;
                        
                    } catch ( Exception $e ) {
                        self::$_last_error = $e->getMessage( );
                        self::$_memcached = null;
                        
                        if ( $attempts < $max_attempts ) {
                            usleep( self::$_memcached_settings['retry_delay'] * 1000 );
                        }
                        $attempts++;
                    }
                }
                
                return null;
            }
            
            return self::$_memcached;
        }

        /**
         * isMemcachedConnected
         * 
         * Check if Memcached connection is still alive
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if connected
         */
        private static function isMemcachedConnected( ) : bool {
            if ( self::$_memcached === null ) {
                return false;
            }
            
            try {
                $stats = self::$_memcached->getStats( );
                return ! empty( $stats );
            } catch ( Exception $e ) {
                return false;
            }
        }

        /**
         * isHealthy
         * 
         * Check if cache system is healthy
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns health status of all cache tiers
         */
        public static function isHealthy( ): array {
            self::ensureInitialized( );
            
            $health = [];
            
            // Check Redis (first priority)
            $health[self::TIER_REDIS] = in_array( self::TIER_REDIS, self::$_available_tiers ) && self::isRedisConnected( );
            
            // Check Memcached (second priority)
            $health[self::TIER_MEMCACHED] = in_array( self::TIER_MEMCACHED, self::$_available_tiers ) && self::isMemcachedConnected( );
            
            // Check OPcache (third priority)
            $health[self::TIER_OPCACHE] = in_array( self::TIER_OPCACHE, self::$_available_tiers ) && self::isOPcacheEnabled( );
            
            // File cache is always healthy if directory exists
            $health[self::TIER_FILE] = is_dir( self::$_fallback_path ) && is_writable( self::$_fallback_path );
            
            return $health;
        }

        /**
         * getLastError
         * 
         * Get the last error message
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return ?string Returns a nullable string of the last error
         */
        public static function getLastError( ): ?string {
            return self::$_last_error;
        }

        /**
         * get
         * 
         * Get an item from cache using tier hierarchy
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        public static function get( string $_key ): mixed {
            self::ensureInitialized( );
            
            $tiers = self::$_available_tiers;
            
            foreach ( $tiers as $tier ) {
                $result = self::getFromTier( $_key, $tier );
                if ( $result !== false ) {
                    // Promote to higher tiers for faster future access
                    self::promoteToHigherTiers( $_key, $result, $tier );
                    return $result;
                }
            }
            
            return false;
        }

        /**
         * getFromTier
         * 
         * Get item from specific cache tier
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param string $tier The cache tier to retrieve from
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromTier( string $_key, string $tier ): mixed {
            switch ( $tier ) {
                case self::TIER_REDIS:
                    return self::getFromRedis( $_key );
                    
                case self::TIER_MEMCACHED:
                    return self::getFromMemcached( $_key );
                    
                case self::TIER_OPCACHE:
                    return self::getFromOPcache( $_key );
                    
                case self::TIER_FILE:
                    return self::getFromFile( $_key );
                    
                default:
                    return false;
            }
        }

        /**
         * getFromOPcache
         * 
         * Get item from OPcache - Simplified implementation
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromOPcache( string $_key ): mixed {
            if ( ! self::isOPcacheEnabled( ) ) {
                return false;
            }
            
            $opcache_key = self::$_opcache_prefix . md5( $_key );
            $temp_file = sys_get_temp_dir( ) . '/' . $opcache_key . '.php';
            
            if ( ! file_exists( $temp_file ) ) {
                return false;
            }
            
            try {
                // Include the file to get cached data
                $data = include $temp_file;
                
                if ( is_array( $data ) && isset( $data['expires'], $data['value'] ) ) {
                    if ( $data['expires'] > time( ) ) {
                        return $data['value'];
                    } else {
                        // Expired, remove file
                        @unlink( $temp_file );
                        if ( function_exists( 'opcache_invalidate' ) ) {
                            @opcache_invalidate( $temp_file, true );
                        }
                    }
                }
            } catch ( Exception $e ) {
                self::$_last_error = "OPcache get error: " . $e->getMessage( );
            }
            
            return false;
        }

        /**
         * getFromRedis
         * 
         * Get item from Redis
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromRedis( string $_key ): mixed {
            $redis = self::getRedis( );
            if ( ! $redis ) {
                return false;
            }
            
            try {
                $_val = $redis->get( $_key );
                if ( $_val !== false ) {
                    return unserialize( $_val );
                }
            } catch ( RedisException $e ) {
                self::$_last_error = $e->getMessage( );
                self::$_redis = null; // Reset connection on error
            }
            
            return false;
        }

        /**
         * getFromMemcached
         * 
         * Get item from Memcached
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromMemcached( string $_key ): mixed {
            $memcached = self::getMemcached( );
            if ( ! $memcached ) {
                return false;
            }
            
            try {
                $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
                $result = $memcached->get( $prefixed_key );
                
                if ( $memcached->getResultCode( ) === Memcached::RES_SUCCESS ) {
                    return $result;
                }
            } catch ( Exception $e ) {
                self::$_last_error = $e->getMessage( );
                self::$_memcached = null; // Reset connection on error
            }
            
            return false;
        }

        /**
         * getFromFile
         * 
         * Get item from file cache
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromFile( string $_key ): mixed {
            $file = self::$_fallback_path . md5( $_key );
            
            if ( file_exists( $file ) ) {
                $data = file_get_contents( $file );
                $expires = substr( $data, 0, 10 );
                
                if ( time( ) > $expires ) {
                    unlink( $file );
                    return false;
                }
                
                return unserialize( substr( $data, 10 ) );
            }
            
            return false;
        }

        /**
         * promoteToHigherTiers
         * 
         * Promote cache item to higher tiers for faster future access
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to promote
         * @param string $current_tier The tier where data was found
         * @return void
         */
        private static function promoteToHigherTiers( string $_key, mixed $_data, string $current_tier ): void {
            $tiers = self::$_available_tiers;
            $current_index = array_search( $current_tier, $tiers );
            
            // Promote to all higher tiers
            for ( $i = 0; $i < $current_index; $i++ ) {
                self::setToTier( $_key, $_data, 3600, $tiers[$i] ); // Default 1 hour TTL for promotion
            }
        }

        /**
         * set
         * 
         * Set an item in cache using all available tiers
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds (defaults to 1 hour)
         * @return bool Returns true if item was successfully set to at least one tier
         */
        public static function set( string $_key, mixed $_data, int $_length = 3600 ): bool {
            self::ensureInitialized( );
            
            if ( ! $_data || empty( $_data ) ) {
                return false;
            }
            
            $success = false;
            $tiers = self::$_available_tiers;
            
            foreach ( $tiers as $tier ) {
                if ( self::setToTier( $_key, $_data, $_length, $tier ) ) {
                    $success = true;
                }
            }
            
            return $success;
        }

        /**
         * setToTier
         * 
         * Set item to specific cache tier
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds
         * @param string $tier The cache tier to set to
         * @return bool Returns true if successful
         */
        private static function setToTier( string $_key, mixed $_data, int $_length, string $tier ): bool {
            switch ( $tier ) {
                case self::TIER_REDIS:
                    return self::setToRedis( $_key, $_data, $_length );
                    
                case self::TIER_MEMCACHED:
                    return self::setToMemcached( $_key, $_data, $_length );
                    
                case self::TIER_OPCACHE:
                    return self::setToOPcache( $_key, $_data, $_length );
                    
                case self::TIER_FILE:
                    return self::setToFile( $_key, $_data, $_length );
                    
                default:
                    return false;
            }
        }

        /**
         * setToOPcache
         * 
         * Set item to OPcache - Simplified implementation
         * 
         * @since 8.5
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

        /**
         * setToRedis
         * 
         * Set item to Redis
         * 
         * @since 8.5
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

        /**
         * setToMemcached
         * 
         * Set item to Memcached
         * 
         * @since 8.5
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

        /**
         * setToFile
         * 
         * Set item to file cache
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds
         * @return bool Returns true if successful
         */
        private static function setToFile( string $_key, mixed $_data, int $_length ): bool {
            $file = self::$_fallback_path . md5( $_key );
            $expires = time( ) + $_length;
            $data = $expires . serialize( $_data );
            
            return file_put_contents( $file, $data, LOCK_EX ) !== false;
        }

        /**
         * del
         * 
         * Delete an item from all cache tiers
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return bool Returns true if item was successfully deleted from all tiers
         */
        public static function del( string $_key ): bool {
            self::ensureInitialized( );
            
            $success = true;
            $tiers = self::$_available_tiers;
            
            foreach ( $tiers as $tier ) {
                if ( ! self::delFromTier( $_key, $tier ) ) {
                    $success = false;
                }
            }
            
            return $success;
        }

        /**
         * delFromTier
         * 
         * Delete item from specific tier
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param string $tier The cache tier to delete from
         * @return bool Returns true if successful
         */
        private static function delFromTier( string $_key, string $tier ): bool {
            switch ( $tier ) {
                case self::TIER_OPCACHE:
                    $opcache_key = self::$_opcache_prefix . md5( $_key );
                    $temp_file = sys_get_temp_dir( ) . '/' . $opcache_key . '.php';
                    if ( file_exists( $temp_file ) ) {
                        if ( function_exists( 'opcache_invalidate' ) ) {
                            @opcache_invalidate( $temp_file, true );
                        }
                        return @unlink( $temp_file );
                    }
                    return true;
                    
                case self::TIER_REDIS:
                    $redis = self::getRedis( );
                    if ( $redis ) {
                        try {
                            return (bool) $redis->del( $_key );
                        } catch ( RedisException $e ) {
                            self::$_last_error = $e->getMessage( );
                            self::$_redis = null;
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_MEMCACHED:
                    $memcached = self::getMemcached( );
                    if ( $memcached ) {
                        try {
                            $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
                            return $memcached->delete( $prefixed_key );
                        } catch ( Exception $e ) {
                            self::$_last_error = $e->getMessage( );
                            self::$_memcached = null;
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_FILE:
                    $file = self::$_fallback_path . md5( $_key );
                    if ( file_exists( $file ) ) {
                        return unlink( $file );
                    }
                    return true;
                    
                default:
                    return false;
            }
        }

        /**
         * clear
         * 
         * Clear all cache from all tiers
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if all caches were successfully cleared
         */
        public static function clear( ): bool {
            self::ensureInitialized( );
            
            $success = true;
            $tiers = self::$_available_tiers;
            
            foreach ( $tiers as $tier ) {
                if ( ! self::clearTier( $tier ) ) {
                    $success = false;
                }
            }
            
            return $success;
        }

        /**
         * clearTier
         * 
         * Clear specific tier
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $tier The cache tier to clear
         * @return bool Returns true if successful
         */
        private static function clearTier( string $tier ): bool {
            switch ( $tier ) {
                case self::TIER_OPCACHE:
                    return function_exists( 'opcache_reset' ) ? opcache_reset( ) : false;
                    
                case self::TIER_REDIS:
                    $redis = self::getRedis( );
                    if ( $redis ) {
                        try {
                            return $redis->flushAll( );
                        } catch ( RedisException $e ) {
                            self::$_last_error = $e->getMessage( );
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_MEMCACHED:
                    $memcached = self::getMemcached( );
                    if ( $memcached ) {
                        return $memcached->flush( );
                    }
                    return true;
                    
                case self::TIER_FILE:
                    $files = glob( self::$_fallback_path . '*' );
                    $success = true;
                    foreach ( $files as $file ) {
                        if ( is_file( $file ) ) {
                            $success = $success && unlink( $file );
                        }
                    }
                    return $success;
                    
                default:
                    return false;
            }
        }

        /**
         * cleanupCache
         * 
         * Remove expired cache files
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return int Number of files deleted
         */
        public static function cleanupCache( ): int {
            $count = 0;
            $files = glob( self::getCachePath( ) . '*' );
            
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    $content = file_get_contents( $file );
                    $expires = substr( $content, 0, 10 );
                    
                    if ( time( ) > $expires ) {
                        if ( unlink( $file ) ) {
                            $count++;
                        }
                    }
                }
            }
            
            return $count;
        }

        /**
         * close
         * 
         * Close all connections
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        public static function close( ): void {
            if ( self::$_redis instanceof Redis ) {
                try {
                    self::$_redis->close( );
                } catch ( RedisException $e ) {
                    self::$_last_error = $e->getMessage( );
                }
                self::$_redis = null;
            }
            
            if ( self::$_memcached instanceof Memcached ) {
                self::$_memcached->quit( );
                self::$_memcached = null;
            }
        }

        /**
         * getStats
         * 
         * Get cache statistics
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns statistics for all cache tiers
         */
        public static function getStats( ): array {
            self::ensureInitialized( );
            
            $stats = [];
            
            // OPcache stats
            if ( function_exists( 'opcache_get_status' ) ) {
                $stats[self::TIER_OPCACHE] = opcache_get_status( );
            }
            
            // Redis stats
            $redis = self::getRedis( );
            if ( $redis ) {
                try {
                    $stats[self::TIER_REDIS] = $redis->info( );
                } catch ( RedisException $e ) {
                    $stats[self::TIER_REDIS] = ['error' => $e->getMessage( )];
                }
            }
            
            // Memcached stats
            $memcached = self::getMemcached( );
            if ( $memcached ) {
                $stats[self::TIER_MEMCACHED] = $memcached->getStats( );
            }
            
            // File cache stats
            $files = glob( self::$_fallback_path . '*' );
            $stats[self::TIER_FILE] = [
                'file_count' => count( $files ),
                'total_size' => array_sum( array_map( 'filesize', $files ) ),
                'path' => self::$_fallback_path
            ];
            
            return $stats;
        }

        /**
         * debug
         * 
         * Debug method to see what's happening with cache tiers
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns debug information
         */
        public static function debug( ): array {
            self::ensureInitialized( );
            
            return [
                'available_tiers' => self::$_available_tiers,
                'health_check' => self::isHealthy( ),
                'last_error' => self::$_last_error,
                'redis_available' => class_exists( 'Redis' ),
                'memcached_available' => class_exists( 'Memcached' ),
                'opcache_available' => function_exists( 'opcache_get_status' ),
                'opcache_enabled' => self::isOPcacheEnabled( ),
                'cache_path' => self::$_fallback_path,
            ];
        }
    }
}