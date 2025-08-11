<?php
/**
 * Cache
 * 
 * Multi-tier caching class with OPcache, shmop, APCu, Yac, mmap, Redis, Memcached, and File fallbacks
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class isn't already in userspace
if ( ! class_exists( 'KPT_Caching' ) ) {

    /**
     * KPT_Caching
     * 
     * Multi-tier caching class with hierarchical fallbacks
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class KPT_Caching {

        // toss in our modules
        use KPT_Caching_APCU;
        use KPT_Caching_File;
        use KPT_Caching_Memcached;
        use KPT_Caching_MMAP;
        use KPT_Caching_OPCache;
        use KPT_Caching_Redis;
        use KPT_Caching_SHMOP;
        use KPT_Caching_YAC;


        // Cache tier constants - ordered by priority (highest to lowest)
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_MMAP = 'mmap';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';

        // setup the internal properties
        private static $_redis = null;
        private static $_memcached = null;
        private static $_last_error = null;
        private static $_available_tiers = [];
        private static $_fallback_path = null;
        private static $_opcache_prefix = 'KPT_OPCACHE_';
        private static $_initialized = false;
        private static $_configurable_cache_path = null;
        private static $_shmop_segments = []; // Track shmop segments
        private static $_mmap_files = []; // Track mmap files
        private static $_last_used_tier = null; // Track last tier used for operations

        /**
         * init
         * 
         * Initialize the cache system and determine available tiers
         * 
         * @since 8.4
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
                self::$_fallback_path = sys_get_temp_dir( ) . '/KPT_Caching/';
            }
            
            // hold the available cache tiers
            self::$_available_tiers = [];
            
            // Check OPcache availability (first priority) - Simplified check
            if ( function_exists( 'opcache_get_status' ) && self::isOPcacheEnabled( ) ) {
                self::$_available_tiers[] = self::TIER_OPCACHE;
            }
            
            // Check shmop availability (second priority) - Test shared memory functionality
            if ( function_exists( 'shmop_open' ) && self::testShmopConnection( ) ) {
                self::$_available_tiers[] = self::TIER_SHMOP;
            }
            
            // Check APCu availability (third priority) - Test actual functionality
            if ( function_exists( 'apcu_enabled' ) && self::testAPCuConnection( ) ) {
                self::$_available_tiers[] = self::TIER_APCU;
            }
            
            // Check Yac availability (fourth priority) - Test Yac functionality
            if ( extension_loaded( 'yac' ) && self::testYacConnection( ) ) {
                self::$_available_tiers[] = self::TIER_YAC;
            }
            
            // Check mmap availability (fifth priority) - Test memory mapping functionality
            if ( self::testMmapConnection( ) ) {
                self::$_available_tiers[] = self::TIER_MMAP;
            }
            
            // Check Redis availability (sixth priority) - Test actual connection
            if ( class_exists( 'Redis' ) && self::testRedisConnection( ) ) {
                self::$_available_tiers[] = self::TIER_REDIS;
            }
            
            // Check Memcached availability (seventh priority) - Test actual connection
            if ( class_exists( 'Memcached' ) && self::testMemcachedConnection( ) ) {
                self::$_available_tiers[] = self::TIER_MEMCACHED;
            }
            
            // File cache is always available (last fallback)
            self::$_available_tiers[] = self::TIER_FILE;
            self::initFallback( );
            
            // we are initialized at this point
            self::$_initialized = true;
        }

        /**
         * ensureInitialized
         * 
         * Ensure the cache system is initialized
         * 
         * @since 8.4
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
         * Initialize the fallback directory with proper permissions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void
         */
        private static function initFallback( ) : void {

            // Use configurable path if set, otherwise default
            $cache_path = self::$_configurable_cache_path ?: self::$_fallback_path;
            
            // Try to create and setup the cache directory
            if ( self::createCacheDirectory( $cache_path ) ) {
                self::$_fallback_path = $cache_path;
                return;
            }
            
            // If the preferred path failed, try alternative paths
            $fallback_paths = [
                sys_get_temp_dir( ) . '/KPT_Caching_' . getmypid( ) . '/',
                getcwd( ) . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/KPT_Caching_' . getmypid( ) . '/',
            ];
            
            foreach ( $fallback_paths as $alt_path ) {
                if ( self::createCacheDirectory( $alt_path ) ) {
                    self::$_fallback_path = $alt_path;
                    return;
                }
            }
            
            // Last resort - use system temp with unique name
            $temp_path = sys_get_temp_dir( ) . '/kpt_' . uniqid( ) . '_' . getmypid( ) . '/';
            if ( self::createCacheDirectory( $temp_path ) ) {
                self::$_fallback_path = $temp_path;
            } else {
                // If all else fails, disable file caching
                self::$_last_error = "Unable to create writable cache directory";
                // Remove file tier from available tiers
                $key = array_search( self::TIER_FILE, self::$_available_tiers );
                if ( $key !== false ) {
                    unset( self::$_available_tiers[$key] );
                    self::$_available_tiers = array_values( self::$_available_tiers );
                }
            }
        
        }

        /**
         * getSettings
         * 
         * Get current settings for all cache tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns current configuration for all tiers
         */
        public static function getSettings( ): array {
            
            return [
                'redis' => self::$_redis_settings,
                'memcached' => self::$_memcached_settings,
                'apcu' => self::$_apcu_settings,
                'yac' => self::$_yac_settings,
                'mmap' => self::$_mmap_settings,
                'shmop' => self::$_shmop_settings,
                'file_cache_path' => self::$_fallback_path,
                'opcache_prefix' => self::$_opcache_prefix,
            ];
        }

        /**
         * getAvailableTiers
         * 
         * Get available cache tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns array of available cache tiers
         */
        public static function getAvailableTiers( ) : array {
            
            // make sure we're initialized
            self::ensureInitialized( );
            
            // return which tiers of cache are available
            return self::$_available_tiers;

        }

        /**
         * getLastUsedTier
         * 
         * Get the tier that was used for the last cache operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return ?string Returns the last used cache tier or null
         */
        public static function getLastUsedTier( ) : ?string {
            return self::$_last_used_tier;
        }

        /**
         * isHealthy
         * 
         * Check if cache system is healthy
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns health status of all cache tiers
         */
        public static function isHealthy( ): array {

            // make sure we're fully initialized
            self::ensureInitialized( );
            
            // hold the health array
            $health = [];
            
            // Check OPcache (first priority)
            $health[self::TIER_OPCACHE] = in_array( self::TIER_OPCACHE, self::$_available_tiers ) && self::isOPcacheEnabled( );
            
            // Check shmop (second priority)
            $health[self::TIER_SHMOP] = in_array( self::TIER_SHMOP, self::$_available_tiers ) && function_exists( 'shmop_open' );
            
            // Check APCu (third priority)
            $health[self::TIER_APCU] = in_array( self::TIER_APCU, self::$_available_tiers ) && function_exists( 'apcu_enabled' ) && apcu_enabled( );
            
            // Check Yac (fourth priority)
            $health[self::TIER_YAC] = in_array( self::TIER_YAC, self::$_available_tiers ) && extension_loaded( 'yac' );
            
            // Check mmap (fifth priority)
            $health[self::TIER_MMAP] = in_array( self::TIER_MMAP, self::$_available_tiers );
            
            // Check Redis (sixth priority)
            $health[self::TIER_REDIS] = in_array( self::TIER_REDIS, self::$_available_tiers ) && self::isRedisConnected( );
            
            // Check Memcached (seventh priority)
            $health[self::TIER_MEMCACHED] = in_array( self::TIER_MEMCACHED, self::$_available_tiers ) && self::isMemcachedConnected( );
            
            // File cache is always healthy if directory exists
            $health[self::TIER_FILE] = is_dir( self::$_fallback_path ) && is_writable( self::$_fallback_path );
            
            // return the health status
            return $health;

        }

        /**
         * getLastError
         * 
         * Get the last error message
         * 
         * @since 8.4
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
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        public static function get( string $_key ): mixed {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // grab the cache tiers
            $tiers = self::$_available_tiers;
            
            // loop over all available
            foreach ( $tiers as $tier ) {

                // get the result
                $result = self::getFromTier( $_key, $tier );
                
                // if there isn't one
                if ( $result !== false ) {

                    // Promote to higher tiers for faster future access
                    self::promoteToHigherTiers( $_key, $result, $tier );
                    return $result;
                }

            }
            
            // default return
            return false;

        }

        /**
         * getFromTier
         * 
         * Get item from specific cache tier
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param string $tier The cache tier to retrieve from
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromTier( string $_key, string $tier ): mixed {

            $result = false;

            // switch the cache tier
            switch ( $tier ) {

                // opcache
                case self::TIER_OPCACHE:
                    $result = self::getFromOPcache( $_key );
                    break;
                    
                // shmop
                case self::TIER_SHMOP:
                    $result = self::getFromShmop( $_key );
                    break;
                    
                // apcu
                case self::TIER_APCU:
                    $result = self::getFromAPCu( $_key );
                    break;
                    
                // yac
                case self::TIER_YAC:
                    $result = self::getFromYac( $_key );
                    break;
                    
                // mmap
                case self::TIER_MMAP:
                    $result = self::getFromMmap( $_key );
                    break;

                // redis
                case self::TIER_REDIS:
                    $result = self::getFromRedis( $_key );
                    break;
                    
                // memcached
                case self::TIER_MEMCACHED:
                    $result = self::getFromMemcached( $_key );
                    break;
                    
                // file
                case self::TIER_FILE:
                    $result = self::getFromFile( $_key );
                    break;
                    
                // everything else
                default:
                    $result = false;
                    break;

            }

            // Track which tier was used on successful operations
            if ( $result !== false ) {
                self::$_last_used_tier = $tier;
            }

            return $result;

        }

        /**
         * promoteToHigherTiers
         * 
         * Promote cache item to higher tiers for faster future access
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to promote
         * @param string $current_tier The tier where data was found
         * @return void
         */
        private static function promoteToHigherTiers( string $_key, mixed $_data, string $current_tier ): void {

            // hold the available tiers
            $tiers = self::$_available_tiers;

            // which index are we on
            $current_index = array_search( $current_tier, $tiers );
            
            // Promote to all higher tiers (use internal method to avoid tracking interference)
            for ( $i = 0; $i < $current_index; $i++ ) {
                self::setToTierInternal( $_key, $_data, 3600, $tiers[$i] ); // Default 1 hour TTL for promotion
            }
        }

        /**
         * set
         * 
         * Set an item in cache using all available tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds (defaults to 1 hour)
         * @return bool Returns true if item was successfully set to at least one tier
         */
        public static function set( string $_key, mixed $_data, int $_length = 3600 ): bool {

            // make we're initialized
            self::ensureInitialized( );
            
            // if we have not data, just return
            if ( ! $_data || empty( $_data ) ) {
                return false;
            }
            
            // by default
            $success = false;
            $primary_tier_used = null;

            // what tiers do we have to us
            $tiers = self::$_available_tiers;
            
            // loop over each tier
            foreach ( $tiers as $tier ) {

                // set it then return the success if true
                if ( self::setToTierInternal( $_key, $_data, $_length, $tier ) ) {
                    $success = true;
                    
                    // Track the first (highest priority) successful tier as the primary
                    if ( $primary_tier_used === null ) {
                        $primary_tier_used = $tier;
                    }
                }

            }
            
            // Set the last used tier to the primary (first successful) tier
            if ( $primary_tier_used !== null ) {
                self::$_last_used_tier = $primary_tier_used;
            }
            
            // return the success
            return $success;

        }

        /**
         * setToTierInternal
         * 
         * Set item to specific cache tier (internal, no usage tracking)
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds
         * @param string $tier The cache tier to set to
         * @return bool Returns true if successful
         */
        private static function setToTierInternal( string $_key, mixed $_data, int $_length, string $tier ): bool {

            // which tier do we want to set the cache item to
            switch ( $tier ) {

                // opcache
                case self::TIER_OPCACHE:
                    return self::setToOPcache( $_key, $_data, $_length );
                    
                // shmop
                case self::TIER_SHMOP:
                    return self::setToShmop( $_key, $_data, $_length );
                    
                // apcu
                case self::TIER_APCU:
                    return self::setToAPCu( $_key, $_data, $_length );
                    
                // yac
                case self::TIER_YAC:
                    return self::setToYac( $_key, $_data, $_length );
                    
                // mmap
                case self::TIER_MMAP:
                    return self::setToMmap( $_key, $_data, $_length );

                // redis
                case self::TIER_REDIS:
                    return self::setToRedis( $_key, $_data, $_length );
                    
                // memcached
                case self::TIER_MEMCACHED:
                    return self::setToMemcached( $_key, $_data, $_length );
                    
                // file
                case self::TIER_FILE:
                    return self::setToFile( $_key, $_data, $_length );
                    
                // default
                default:
                    return false;
            
            }

        }

        /**
         * del
         * 
         * Delete an item from all cache tiers
         * 
         * @since 8.4
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
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param string $tier The cache tier to delete from
         * @return bool Returns true if successful
         */
        private static function delFromTier( string $_key, string $tier ): bool {
            
            $success = false;
            
            switch ( $tier ) {
                case self::TIER_OPCACHE:
                    $opcache_key = self::$_opcache_prefix . md5( $_key );
                    $temp_file = sys_get_temp_dir( ) . '/' . $opcache_key . '.php';
                    if ( file_exists( $temp_file ) ) {
                        if ( function_exists( 'opcache_invalidate' ) ) {
                            @opcache_invalidate( $temp_file, true );
                        }
                        $success = @unlink( $temp_file );
                    } else {
                        $success = true; // File doesn't exist, consider it deleted
                    }
                    break;
                    
                case self::TIER_SHMOP:
                    if ( function_exists( 'shmop_open' ) ) {
                        try {
                            $shmop_key = self::generateShmopKey( $_key );
                            $segment = @shmop_open( $shmop_key, 'w', 0, 0 );
                            if ( $segment !== false ) {
                                $result = @shmop_delete( $segment );
                                @shmop_close( $segment );
                                // Remove from tracking
                                unset( self::$_shmop_segments[$_key] );
                                $success = $result;
                            } else {
                                $success = true; // Segment doesn't exist, consider it deleted
                            }
                        } catch ( Exception $e ) {
                            self::$_last_error = $e->getMessage( );
                            $success = false;
                        }
                    } else {
                        $success = true; // shmop not available, consider it deleted
                    }
                    break;
                    
                case self::TIER_APCU:
                    if ( function_exists( 'apcu_enabled' ) && apcu_enabled( ) ) {
                        try {
                            $prefixed_key = self::$_apcu_settings['prefix'] . $_key;
                            $success = apcu_delete( $prefixed_key );
                        } catch ( Exception $e ) {
                            self::$_last_error = $e->getMessage( );
                            $success = false;
                        }
                    } else {
                        $success = true; // APCu not available, consider it deleted
                    }
                    break;
                    
                case self::TIER_YAC:
                    if ( extension_loaded( 'yac' ) ) {
                        try {
                            $prefixed_key = self::$_yac_settings['prefix'] . $_key;
                            $success = yac_delete( $prefixed_key );
                        } catch ( Exception $e ) {
                            self::$_last_error = $e->getMessage( );
                            $success = false;
                        }
                    } else {
                        $success = true; // Yac not available, consider it deleted
                    }
                    break;
                    
                case self::TIER_MMAP:
                    try {
                        $filename = self::generateMmapKey( $_key );
                        $filepath = self::getMmapBasePath( ) . $filename;
                        if ( file_exists( $filepath ) ) {
                            $success = @unlink( $filepath );
                            // Remove from tracking
                            unset( self::$_mmap_files[$_key] );
                        } else {
                            $success = true; // File doesn't exist, consider it deleted
                        }
                    } catch ( Exception $e ) {
                        self::$_last_error = $e->getMessage( );
                        $success = false;
                    }
                    break;
                    
                case self::TIER_REDIS:
                    $redis = self::getRedis( );
                    if ( $redis ) {
                        try {
                            $success = (bool) $redis->del( $_key );
                        } catch ( RedisException $e ) {
                            self::$_last_error = $e->getMessage( );
                            self::$_redis = null;
                            $success = false;
                        }
                    } else {
                        $success = true; // Redis not available, consider it deleted
                    }
                    break;
                    
                case self::TIER_MEMCACHED:
                    $memcached = self::getMemcached( );
                    if ( $memcached ) {
                        try {
                            $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
                            $success = $memcached->delete( $prefixed_key );
                        } catch ( Exception $e ) {
                            self::$_last_error = $e->getMessage( );
                            self::$_memcached = null;
                            $success = false;
                        }
                    } else {
                        $success = true; // Memcached not available, consider it deleted
                    }
                    break;
                    
                case self::TIER_FILE:
                    $file = self::$_fallback_path . md5( $_key );
                    if ( file_exists( $file ) ) {
                        $success = unlink( $file );
                    } else {
                        $success = true; // File doesn't exist, consider it deleted
                    }
                    break;
                    
                default:
                    $success = false;
                    break;
            }
            
            // Track which tier was used on successful operations
            if ( $success ) {
                self::$_last_used_tier = $tier;
            }
            
            return $success;
        }

        /**
         * clear
         * 
         * Clear all cache from all tiers
         * 
         * @since 8.4
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
         * @since 8.4
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
                    
                case self::TIER_SHMOP:
                    // Clear all tracked shmop segments
                    $success = true;
                    foreach ( self::$_shmop_segments as $cache_key => $shmop_key ) {
                        if ( ! self::delFromTier( $cache_key, self::TIER_SHMOP ) ) {
                            $success = false;
                        }
                    }
                    self::$_shmop_segments = [];
                    return $success;
                    
                case self::TIER_APCU:
                    return function_exists( 'apcu_clear_cache' ) ? apcu_clear_cache( ) : false;
                    
                case self::TIER_YAC:
                    return extension_loaded( 'yac' ) ? yac_flush( ) : false;
                    
                case self::TIER_MMAP:
                    // Clear all tracked mmap files
                    $success = true;
                    foreach ( self::$_mmap_files as $cache_key => $filepath ) {
                        if ( file_exists( $filepath ) ) {
                            if ( ! @unlink( $filepath ) ) {
                                $success = false;
                            }
                        }
                    }
                    self::$_mmap_files = [];
                    return $success;
                    
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
         * Remove expired cache files, shmop segments, and mmap files
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return int Number of items deleted
         */
        public static function cleanupCache( ): int {
            $count = 0;
            
            // Clean up file cache
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
            
            // Clean up expired shmop segments
            foreach ( self::$_shmop_segments as $cache_key => $shmop_key ) {
                try {
                    $segment = @shmop_open( $shmop_key, 'a', 0, 0 );
                    if ( $segment !== false ) {
                        $size = shmop_size( $segment );
                        if ( $size > 0 ) {
                            $data = shmop_read( $segment, 0, $size );
                            $unserialized = @unserialize( trim( $data, "\0" ) );
                            
                            if ( is_array( $unserialized ) && isset( $unserialized['expires'] ) ) {
                                if ( $unserialized['expires'] <= time( ) ) {
                                    @shmop_delete( $segment );
                                    unset( self::$_shmop_segments[$cache_key] );
                                    $count++;
                                }
                            }
                        }
                        @shmop_close( $segment );
                    }
                } catch ( Exception $e ) {
                    // If there's an error, remove from tracking
                    unset( self::$_shmop_segments[$cache_key] );
                }
            }
            
            // Clean up expired mmap files
            foreach ( self::$_mmap_files as $cache_key => $filepath ) {
                try {
                    if ( file_exists( $filepath ) ) {
                        $file = fopen( $filepath, 'rb' );
                        if ( $file !== false ) {
                            if ( flock( $file, LOCK_SH ) ) {
                                $data = fread( $file, filesize( $filepath ) );
                                flock( $file, LOCK_UN );
                                fclose( $file );
                                
                                $unserialized = @unserialize( trim( $data, "\0" ) );
                                
                                if ( is_array( $unserialized ) && isset( $unserialized['expires'] ) ) {
                                    if ( $unserialized['expires'] <= time( ) ) {
                                        if ( @unlink( $filepath ) ) {
                                            unset( self::$_mmap_files[$cache_key] );
                                            $count++;
                                        }
                                    }
                                }
                            } else {
                                fclose( $file );
                            }
                        }
                    } else {
                        // File doesn't exist, remove from tracking
                        unset( self::$_mmap_files[$cache_key] );
                    }
                } catch ( Exception $e ) {
                    // If there's an error, remove from tracking
                    unset( self::$_mmap_files[$cache_key] );
                }
            }
            
            return $count;
        }

        /**
         * close
         * 
         * Close all connections and clean up resources
         * 
         * @since 8.4
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
            
            // Clean up tracking arrays
            self::$_shmop_segments = [];
            self::$_mmap_files = [];
        }

        /**
         * getStats
         * 
         * Get cache statistics
         * 
         * @since 8.4
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
            
            // shmop stats
            $stats[self::TIER_SHMOP] = [
                'segments_tracked' => count( self::$_shmop_segments ),
                'base_key' => self::$_shmop_settings['base_key'],
                'segment_size' => self::$_shmop_settings['segment_size']
            ];
            
            // APCu stats
            if ( function_exists( 'apcu_cache_info' ) ) {
                $stats[self::TIER_APCU] = apcu_cache_info( );
            }
            
            // Yac stats
            if ( extension_loaded( 'yac' ) && function_exists( 'yac_info' ) ) {
                $stats[self::TIER_YAC] = yac_info( );
            } else if ( extension_loaded( 'yac' ) ) {
                $stats[self::TIER_YAC] = ['extension_loaded' => true];
            }
            
            // mmap stats
            $mmap_files = glob( self::getMmapBasePath( ) . '*.mmap' );
            $stats[self::TIER_MMAP] = [
                'files_tracked' => count( self::$_mmap_files ),
                'files_on_disk' => count( $mmap_files ),
                'total_size' => array_sum( array_map( 'filesize', $mmap_files ) ),
                'base_path' => self::getMmapBasePath( ),
                'file_size' => self::$_mmap_settings['file_size']
            ];
            
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
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns debug information
         */
        public static function debug( ): array {
            self::ensureInitialized( );
            
            $debug_info = [
                'available_tiers' => self::$_available_tiers,
                'health_check' => self::isHealthy( ),
                'last_error' => self::$_last_error,
                'last_used_tier' => self::$_last_used_tier,
                'opcache_available' => function_exists( 'opcache_get_status' ),
                'opcache_enabled' => self::isOPcacheEnabled( ),
                'shmop_available' => function_exists( 'shmop_open' ),
                'shmop_segments_tracked' => count( self::$_shmop_segments ),
                'apcu_available' => function_exists( 'apcu_enabled' ),
                'apcu_enabled' => function_exists( 'apcu_enabled' ) && apcu_enabled( ),
                'yac_available' => extension_loaded( 'yac' ),
                'mmap_available' => true, // Always available since it uses standard file functions
                'mmap_files_tracked' => count( self::$_mmap_files ),
                'redis_available' => class_exists( 'Redis' ),
                'memcached_available' => class_exists( 'Memcached' ),
                'cache_path' => self::$_fallback_path,
                'cache_path_info' => self::getCachePathInfo( ),
                'system_info' => [
                    'temp_dir' => sys_get_temp_dir( ),
                    'current_user' => get_current_user( ),
                    'process_id' => getmypid( ),
                    'umask' => sprintf( '%04o', umask( ) ),
                ]
            ];
            
            return $debug_info;
        }

    }

}