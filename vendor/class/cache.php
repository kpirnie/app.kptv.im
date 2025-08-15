<?php
/**
 * KPT Cache - Modern Multi-tier Caching System (Refactored)
 * 
 * A comprehensive caching solution that provides multiple tiers of caching
 * including OPcache, SHMOP, APCu, YAC, MMAP, Redis, Memcached, and File-based
 * caching with automatic tier discovery, connection pooling, and failover support.
 * 
 * This refactored version delegates specialized functionality to dedicated managers
 * while maintaining the same public API for backward compatibility.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class doesn't exist
if ( ! class_exists( 'Cache' ) ) {

    /**
     * KPT Cache - Modern Multi-tier Caching System (Refactored)
     * 
     * This refactored version maintains the same public API while delegating
     * specialized functionality to dedicated manager classes for better
     * maintainability and single responsibility adherence.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Cache {

        // Import all cache backend traits
        use Cache_Array, Cache_APCU, Cache_File, Cache_Memcached;
        use Cache_MMAP, Cache_OPCache, Cache_Redis, Cache_SHMOP, Cache_YAC;
        use Cache_Async, Cache_Redis_Async, Cache_File_Async, Cache_Memcached_Async;
        use Cache_Mixed_Async, Cache_MMAP_Async, Cache_OPCache_Async;

        // tier contstants
        const TIER_ARRAY = 'array';
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_MMAP = 'mmap';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';

        // internal configs
        private static ?string $_fallback_path = null;
        private static bool $_initialized = false;
        private static ?string $_configurable_cache_path = null;
        private static array $_shmop_segments = [];
        private static array $_mmap_files = [];
        private static ?string $_last_used_tier = null;
        private static bool $_connection_pooling_enabled = true;
        private static bool $_async_enabled = false;
        private static ?object $_event_loop = null;

        /**
         * Initialize the cache system
         * 
         * Performs complete initialization of the cache system including all
         * manager classes and subsystems.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $config Optional configuration array
         * @return void Returns nothing
         */
        private static function init( array $config = [] ): void {

            // if we're already initialized we don't need to do it again
            if ( self::$_initialized ) return;
            
            // Initialize core configuration
            Cache_Config::initialize( );
            
            // Initialize all manager classes
            self::initializeManagers( $config );
            
            // Initialize the fallback path from global config if available
            if ( self::$_fallback_path === null ) {
                $global_path = Cache_Config::getGlobalPath( );
                if ( $global_path !== null ) {
                    self::$_fallback_path = $global_path;
                } else {
                    self::$_fallback_path = sys_get_temp_dir( ) . '/kpt_cache/';
                }
            }
            
            // Initialize file fallback
            self::initFallback( );
            
            // Initialize connection pools for database backends
            if ( self::$_connection_pooling_enabled ) {
                self::initializeConnectionPools( );
            }
            
            // mark us as initialized
            self::$_initialized = true;
            
            // Log initialization if debug is set
            LOG::debug( 'KPT Cache system initialized', [
                'available_tiers' => self::getAvailableTiers( ),
                'connection_pooling' => self::$_connection_pooling_enabled,
                'async_enabled' => self::$_async_enabled
            ] );
        }

        /**
         * Initialize all manager classes
         * 
         * Sets up the tier manager, key manager, logger, and health monitor
         * with appropriate configuration.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $config Configuration array
         * @return void Returns nothing
         */
        private static function initializeManagers( array $config ): void {
            
            // hold the key manager
            $km_config = [];

            // Initialize Key Manager
            if ( isset( $config['key_manager'] ) ) {
                $km_config = $config['key_manager'];
                
                // see if we need/want to config the global namespace
                if ( isset( $km_config['global_namespace'] ) ) {
                    Cache_KeyManager::setGlobalNamespace( $km_config['global_namespace'] );
                }
                
                // see if we need to set the key separator
                if ( isset( $km_config['key_separator'] ) ) {
                    Cache_KeyManager::setKeySeparator( $km_config['key_separator'] );
                }
                
                // see if we need to automagically hash long keys
                if ( isset( $km_config['auto_hash_long_keys'] ) ) {
                    Cache_KeyManager::setAutoHashLongKeys( $km_config['auto_hash_long_keys'] );
                }
                
                // see what hashing algo we'll be using
                if ( isset( $km_config['hash_algorithm'] ) ) {
                    Cache_KeyManager::setHashAlgorithm( $km_config['hash_algorithm'] );
                }
            }
            
            // Initialize Health Monitor
            $health_config = $config['health_monitor'] ?? [];
            Cache_HealthMonitor::initialize( $health_config );

            // debug
            LOG::debug( "Cache Managers Initialized", ['key_manager' => $km_config, 'health_config' => $health_config] );
        }

        /**
         * Ensure the cache system is properly initialized
         * 
         * Lazy initialization check - calls init() if the system hasn't been initialized yet.
         * This method is called by all public methods to ensure the cache system is ready.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $config Optional configuration for initialization
         * @return void Returns nothing
         */
        private static function ensureInitialized( array $config = [] ): void {
            
            // if we aren't currently, do it!
            if ( ! self::$_initialized ) {
                self::init( $config );
            }
        }
    
        /**
         * Initialize connection pools for database-based cache tiers
         * 
         * Sets up connection pooling for Redis and Memcached tiers to improve
         * performance and resource management. Configures minimum/maximum connections
         * and idle timeout settings.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void Returns nothing
         */
        private static function initializeConnectionPools( ): void {

            // hold the available tiers
            $available_tiers = self::getAvailableTiers( );

            // Configure Redis pool if it's available as a tier
            if ( in_array( self::TIER_REDIS, $available_tiers ) ) {

                // configure the pool
                Cache_ConnectionPool::configurePool( 'redis', [
                    'min_connections' => 1,
                    'max_connections' => 16,
                    'idle_timeout' => 300
                ] );
            }
            
            // Configure Memcached pool
            if ( in_array( self::TIER_MEMCACHED, $available_tiers ) ) {

                // configure the pool
                Cache_ConnectionPool::configurePool( 'memcached', [
                    'min_connections' => 1,
                    'max_connections' => 16,
                    'idle_timeout' => 300
                ] );
            }

            // debug logging
            LOG::debug( "Redis/Memcached Connection Pools Initialized", [] );

        }

        /**
         * Initialize fallback caching directory
         * 
         * Creates and validates the cache directory for file-based caching. Tries multiple
         * fallback paths if the preferred path fails, ensuring at least one working
         * cache directory is available.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void Returns nothing
         */
        private static function initFallback( ): void {

            // Check for configurable path first, then global config, then fallback
            $cache_path = self::$_configurable_cache_path;
            
            // if the path does not exist
            if ( $cache_path === null ) {

                // hold the global setting
                $global_path = Cache_Config::getGlobalPath( );

                // if it exists, set the cache path to it, otherwise fallback
                if ( $global_path !== null ) {
                    $cache_path = $global_path;
                } else {
                    $cache_path = self::$_fallback_path;
                }
            }
            
            // Try to create and setup the cache directory
            if ( self::createCacheDirectory( $cache_path ) ) {
                self::$_fallback_path = $cache_path;
                return;
            }
            
            // Rest of the method remains the same...
            $fallback_paths = [
                sys_get_temp_dir( ) . '/kpt_cache_' . getmypid( ) . '/',
                getcwd( ) . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_' . getmypid( ) . '/',
            ];
            
            // loop the fallback paths and try to create the directories
            foreach ( $fallback_paths as $alt_path ) {
                if ( self::createCacheDirectory( $alt_path ) ) {
                    self::$_fallback_path = $alt_path;
                    return;
                }
            }
            
            // Last resort
            $temp_path = sys_get_temp_dir( ) . '/kpt_' . uniqid( ) . '_' . getmypid( ) . '/';

            // if the path successfully created
            if ( self::createCacheDirectory( $temp_path ) ) {
                self::$_fallback_path = $temp_path;
            } else {
                LOG::error( "Unable to create writable cache directory", ['initialization'] );
                $available_tiers = self::getAvailableTiers( );
                $key = array_search( self::TIER_FILE, $available_tiers );
                if ( $key !== false ) {
                    LOG::warning( "File tier disabled due to directory creation failure", ['initialization'] );
                }
            }
            
            // debug logging
            LOG::debug( "Cache Fallback Initialized", ['path' => self::$_fallback_path] );
        }

        /**
         * Refresh the cache path
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void Returns nothing
         */
        public static function refreshCachePathFromGlobal( ): bool {
            $global_path = Cache_Config::getGlobalPath( );
            if ( $global_path !== null ) {
                return self::setCachePath( $global_path );
            }
            return false;
        }

        /**
         * Retrieve an item from cache using tier hierarchy
         * 
         * Searches through available cache tiers in priority order to find the requested
         * item. If found in a lower tier, automatically promotes it to higher tiers for
         * faster future access.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cached item
         * @return mixed Returns the cached data if found, false otherwise
         */
        public static function get( string $key ): mixed {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // get the available tiers
            $available_tiers = self::getAvailableTiers( );
            
            // loop through the tiers
            foreach ( $available_tiers as $tier ) {

                // get the cached item from the tier
                $result = self::getFromTierInternal( $key, $tier );
                
                // if it was found
                if ( $result !== false ) {

                    // Log cache hit
                    LOG::debug( "Cache Hit", ['tier' => $tier, 'key' => $key] );

                    // Promote to higher tiers for faster future access
                    self::promoteToHigherTiers( $key, $result, $tier );
                    self::$_last_used_tier = $tier;

                    // return the item
                    return $result;
                }
            }
            
            // Log cache miss
            LOG::debug( "Cache Miss", [$key] );
            
            // default return
            return false;
        }

        /**
         * Store an item in cache across all available tiers
         * 
         * Attempts to store the provided data in all available cache tiers
         * for maximum redundancy and performance. Uses the highest priority
         * tier that succeeds as the primary tier.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cache item
         * @param mixed $data The data to store in cache
         * @param int $ttl Time to live in seconds (default: 1 hour)
         * @return bool Returns true if stored in at least one tier, false otherwise
         */
        public static function set( string $key, mixed $data, int $ttl = KPT::HOUR_IN_SECONDS ): bool {

            // make sure we're initialized
            self::ensureInitialized( );

            // if there's no data, then there's nothing to do here... just return
            if ( empty( $data ) ) {
                LOG::error( "Attempted to cache empty data", ['key' => $key] );
                return false;
            }
            
            // default success
            $success = false;

            // hold the primary tier used
            $primary_tier_used = null;

            // get the available tiers
            $available_tiers = self::getAvailableTiers( );

            // loop through each available caching tier
            foreach ( $available_tiers as $tier ) {

                // if we can successfully set it
                if ( self::setToTierInternal( $key, $data, $ttl, $tier ) ) {

                    // set our success to true
                    $success = true;
                    
                    // Track the first (highest priority) successful tier as the primary
                    if ( $primary_tier_used === null ) {
                        $primary_tier_used = $tier;
                    }
                    
                    // Log successful set operation
                    LOG::debug( "Cache item set", ['key' => $key, 'ttl' => $ttl, 'tier' => $tier] );
                } else {

                    // Log failed set operation
                    LOG::error( "Failed to set cache item", ['key' => $key, 'ttl' => $ttl, 'tier' => $tier] );
                }
            }
            
            // Set the last used tier to the primary (first successful) tier
            if ( $primary_tier_used !== null ) {
                self::$_last_used_tier = $primary_tier_used;
            }
            
            // return if we're successful
            return $success;
        }

        /**
         * Delete an item from all cache tiers
         * 
         * Removes the specified cache item from all available tiers to ensure
         * complete removal and prevent stale data from being served.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cache item to delete
         * @return bool Returns true if deleted from all tiers successfully, false if any failed
         */
        public static function delete( string $key ): bool {
            
            // make sure we're initialized
            self::ensureInitialized( );
            
            // default success
            $success = true;
            
            // get the available tiers
            $available_tiers = self::getAvailableTiers( );
            
            // loop through each tier
            foreach ( $available_tiers as $tier ) {
                
                // if the delete from the tier was not successful
                if ( ! self::deleteFromTierInternal( $key, $tier ) ) {
                    $success = false;
                    LOG::error( "Failed to delete cache item", ['key' => $key, 'tier' => $tier] );

                // otherwise, debug log it
                } else {
                    LOG::debug( "Cache item deleted", ['key' => $key, 'tier' => $tier] );
                }
            }
            
            // return if we're successful or not
            return $success;
        }

        /**
         * Clear all cached data from all tiers
         * 
         * Performs a complete cache flush across all available tiers. This is a
         * destructive operation that removes all cached data.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if all tiers cleared successfully, false if any failed
         */
        public static function clear( ): bool {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // default success
            $success = true;
            
            // grab all available tiers
            $available_tiers = self::getAvailableTiers( );
            
            // loop through each tier
            foreach ( $available_tiers as $tier ) {

                // if clearing it was not successful
                if ( ! self::clearTier( $tier ) ) {
                    $success = false;
                    LOG::error( "Failed to clear tier", ['tier' => $tier] );

                // otherwise, debug log it
                } else {
                    LOG::debug( "Cache cleared", [$tier, 'all_keys'] );
                }
            }
            
            // return the success
            return $success;
        }

        /**
         * Retrieve an item from a specific cache tier
         * 
         * Attempts to get data from the specified tier only. If the tier fails,
         * falls back to the standard hierarchy search as a safety measure.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cached item
         * @param string $tier The specific cache tier to retrieve from
         * @return mixed Returns the cached data if found, false otherwise
         */
        public static function getFromTier( string $key, string $tier ): mixed {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // do we have a valid tier?
            if ( ! Cache_TierManager::isTierValid( $tier ) ) {
                LOG::error( "Invalid tier specified", ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // now... is the tier actually available?
            if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                LOG::error( "Tier not available", ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // get the item from the tier
            $result = self::getFromTierInternal( $key, $tier );
            
            // if we have a result
            if ( $result !== false ) {

                // set the last used and return the item
                self::$_last_used_tier = $tier;
                LOG::debug( "Cache Hit", ['tier' => $tier,'key' => $key] );
                return $result;
            }

            // Fallback to default hierarchy if enabled and tier failed
            LOG::debug( "Cache Miss", ['tier' => $tier,'key' => $key] );
            return self::get( $key );

        }

        /**
         * Store an item in a specific cache tier only
         * 
         * Stores data in the specified tier exclusively, without attempting
         * to replicate to other tiers.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cache item
         * @param mixed $data The data to store in cache
         * @param int $ttl Time to live in seconds
         * @param string $tier The specific cache tier to store in
         * @return bool Returns true if successfully stored, false otherwise
         */
        public static function setToTier( string $key, mixed $data, int $ttl, string $tier) : bool {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // if it's a not valid tier
            if ( ! Cache_TierManager::isTierValid( $tier ) ) {
                LOG::error( "Invalid tier specified", ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // if the tier is not available
            if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                LOG::error( "Tier not available", ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // if we have no data
            if ( empty( $data ) ) {
                LOG::warning( "Attempted to cache empty data", ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // set the data to the tier
            $success = self::setToTierInternal( $key, $data, $ttl, $tier );
            
            // if it was successfully set
            if ( $success ) {
                self::$_last_used_tier = $tier;
                LOG::debug( "Cache Set", ['tier' => $tier,'key' => $key] );

            // otherwise, log the error
            } else {
                LOG::error( "Failed to set cache item", ['tier' => $tier,'key' => $key] );
            }
            
            // return if it was true or not
            return $success;
        }

        /**
         * Delete an item from a specific cache tier only
         * 
         * Removes data from the specified tier exclusively, leaving other
         * tiers unchanged.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cache item to delete
         * @param string $tier The specific cache tier to delete from
         * @return bool Returns true if successfully deleted, false otherwise
         */
        public static function deleteFromTier( string $key, string $tier ): bool {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // if the tier is valid
            if ( ! Cache_TierManager::isTierValid( $tier ) ) {
                LOG::error( "Invalid tier specified", ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // is the tier available
            if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                LOG::error( "Tier not available", 'tier_availability', ['tier' => $tier,'key' => $key] );
                return false;
            }
            
            // delete from the tier
            $success = self::deleteFromTierInternal( $key, $tier );
            
            // if it was successful
            if ( $success ) {
                self::$_last_used_tier = $tier;
                LOG::debug( "Cache Deleted", ['tier' => $tier,'key' => $key] );
            } else {
                LOG::error( "Failed to delete cache item", ['tier' => $tier,'key' => $key] );
            }
            
            // return if it was successful or not
            return $success;
        }

        /**
         * Store an item in multiple specific tiers
         * 
         * Attempts to store data in the specified tiers only, providing detailed
         * results for each tier attempted. Useful for selective tier management.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cache item
         * @param mixed $data The data to store in cache
         * @param int $ttl Time to live in seconds
         * @param array $tiers Array of tier names to store the item in
         * @return array Returns detailed results for each tier plus summary statistics
         */
        public static function setToTiers( string $key, mixed $data, int $ttl, array $tiers ): array {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // if we have no data, return an empty array
            if (empty($data)) {
                LOG::warning( "Attempted to cache empty data to multiple tiers", ['tiers' => $tiers,'key' => $key] );
                return [];
            }
            
            // setup the results and success count
            $results = [];
            $success_count = 0;
            
            // loop over the tiers specified
            foreach ( $tiers as $tier ) {

                // if the tier is valid
                if ( ! Cache_TierManager::isTierValid( $tier ) ) {
                    $results[$tier] = ['success' => false, 'error' => 'Invalid tier'];
                    continue;
                }
                
                // if the tier available?
                if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                    $results[$tier] = ['success' => false, 'error' => 'Tier not available'];
                    continue;
                }
                
                // set the item to the tier
                $success = self::setToTierInternal( $key, $data, $ttl, $tier );

                // setup the results
                $error_msg = $success ? null : LOG::getLastError();
                $results[$tier] = ['success' => $success, 'error' => $error_msg];
                
                // if it was successful
                if ( $success ) {
                    
                    // increment the count
                    $success_count++;

                    // setup the last tier used
                    if ( self::$_last_used_tier === null ) {
                        self::$_last_used_tier = $tier;
                    }
                    
                    LOG::debug( "Cache Set", ['tier' => $tier,'key' => $key] );
                } else {
                    LOG::error( "Failed to set cache item to tier in multi-tier operation", ['tier' => $tier,'key' => $key] );
                }
            }
            
            // setup the results
            $results['_summary'] = [
                'total_tiers' => count( $tiers ),
                'successful' => $success_count,
                'failed' => count( $tiers ) - $success_count
            ];
            
            // return the results
            return $results;
        }

        /**
         * Delete an item from multiple specific tiers
         * 
         * Attempts to delete data from the specified tiers only, providing detailed
         * results for each tier attempted. Useful for selective cache invalidation.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The unique identifier for the cache item to delete
         * @param array $tiers Array of tier names to delete the item from
         * @return array Returns detailed results for each tier plus summary statistics
         */
        public static function deleteFromTiers( string $key, array $tiers ): array {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // setup the results and count
            $results = [];
            $success_count = 0;
            
            // loop over each tier
            foreach ( $tiers as $tier ) {
                
                // is the tier valid?
                if ( ! Cache_TierManager::isTierValid( $tier ) ) {
                    $results[$tier] = ['success' => false, 'error' => 'Invalid tier'];
                    continue;
                }
                
                // is the tier available?
                if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                    $results[$tier] = ['success' => false, 'error' => 'Tier not available'];
                    continue;
                }
                
                // delete from the tier
                $success = self::deleteFromTierInternal( $key, $tier );

                // throw the results in the return array
                $error_msg = $success ? null : LOG::getLastError();
                $results[$tier] = ['success' => $success, 'error' => $error_msg];
                
                // if it was sucessful, increment the count
                if ($success) {
                    $success_count++;
                    LOG::debug( "Cache Deleted", ['tier' => $tier,'key' => $key] );
                } else {
                    LOG::error( "Failed to delete cache item from tier in multi-tier operation", ['tier' => $tier,'key' => $key]);
                }

            }
            
            // setup the results
            $results['_summary'] = [
                'total_tiers' => count( $tiers ),
                'successful' => $success_count,
                'failed' => count( $tiers ) - $success_count
            ];
            
            // return the results
            return $results;
        }

        /**
         * Validate if a tier name is valid
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier name to validate
         * @return bool Returns true if the tier name is valid, false otherwise
         */
        public static function isTierValid( string $tier ): bool {
            return Cache_TierManager::isTierValid( $tier );
        }

        /**
         * Check if a tier is available for use
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier name to check availability for
         * @return bool Returns true if the tier is available, false otherwise
         */
        public static function isTierAvailable( string $tier ): bool {
            return Cache_TierManager::isTierAvailable( $tier );
        }

        /**
         * Check if a specific tier is healthy and functioning
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier name to check health for
         * @return bool Returns true if the tier is healthy, false otherwise
         */
        public static function isTierHealthy( string $tier ): bool {
            $health_status = Cache_HealthMonitor::checkTierHealth( $tier );
            return $health_status['status'] === Cache_HealthMonitor::STATUS_HEALTHY;
        }

        /**
         * Get list of all valid tier names
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns array of all valid tier names
         */
        public static function getValidTiers( ): array {
            return Cache_TierManager::getValidTiers( );
        }

        /**
         * Get list of available (discovered) tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns array of available tier names in priority order
         */
        public static function getAvailableTiers( ): array {
            return Cache_TierManager::getAvailableTiers( );
        }

        /**
         * Get comprehensive status information for all tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns associative array with tier status information
         */
        public static function getTierStatus( ): array {
            return Cache_TierManager::getTierStatus( );
        }

        /**
         * Get the tier used for the last cache operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string|null Returns the last used tier name or null if none
         */
        public static function getLastUsedTier( ): ?string {
            return self::$_last_used_tier;
        }

        /**
         * Get the last error message encountered
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string|null Returns the last error message or null if none
         */
        public static function getLastError( ): ?string {
            return LOG::getLastError( );
        }

        /**
         * Internal method to get data from a specific tier with connection pooling
         * 
         * Handles the actual retrieval of data from cache tiers with support for
         * connection pooling on database-based tiers (Redis, Memcached).
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to retrieve
         * @param string $tier The tier to retrieve from
         * @return mixed Returns the cached data or false if not found
         */
        private static function getFromTierInternal( string $key, string $tier ): mixed {

            // Generate the appropriate key for this tier
            $tier_key = Cache_KeyManager::generateKey( $key, $tier );
            
            // default results
            $result = false;
            
            // try to get a result from a tier
            try {

                // match the tier
                $result = match( $tier ) {
                    self::TIER_ARRAY => self::getFromArray( $tier_key ),
                    self::TIER_MMAP => self::getFromMmap( $key ),
                    self::TIER_SHMOP => self::getFromShmop( $key ),
                    self::TIER_REDIS => self::getFromRedis( $tier_key ),
                    self::TIER_MEMCACHED => self::getFromMemcached( $tier_key ),
                    self::TIER_OPCACHE => self::getFromOPcache( $tier_key ),
                    self::TIER_APCU => self::getFromAPCu( $tier_key ),
                    self::TIER_YAC => self::getFromYac( $tier_key ),
                    self::TIER_FILE => self::getFromFile( $tier_key ),
                    default => false
                };

                // debug log
                LOG::debug( 'Cache Hit', ['tier' => $tier, 'key' => $key, 'tier_key' => $tier_key] );
            
            // whoopsie... log the error and return set the result to false
            } catch ( \Exception $e ) {
                LOG::error( "Error getting from tier", [
                    'error' => $e -> getMessage( ),
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key
                ] );
                $result = false;
            }
            
            // return the result
            return $result;
        }

        /**
         * Internal method to set data to a specific tier with connection pooling
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds
         * @param string $tier The tier to store to
         * @return bool Returns true if successfully stored, false otherwise
         */
        private static function setToTierInternal( string $key, mixed $data, int $ttl, string $tier) : bool {
            
            // Generate the appropriate key for this tier
            $tier_key = Cache_KeyManager::generateKey( $key, $tier );
            
            // try to match the tier to the internal method
            try {

                // match the tier
                $result = match( $tier ) {
                    self::TIER_MMAP => self::setToMmap( $key, $data, $ttl ),
                    self::TIER_SHMOP => self::setToShmop( $key, $data, $ttl ),
                    self::TIER_REDIS => self::setToRedis( $tier_key, $data, $ttl ),
                    self::TIER_MEMCACHED => self::setToMemcached( $tier_key, $data, $ttl ),
                    self::TIER_OPCACHE => self::setToOPcache( $tier_key, $data, $ttl ),
                    self::TIER_APCU => self::setToAPCu( $tier_key, $data, $ttl ),
                    self::TIER_YAC => self::setToYac( $tier_key, $data, $ttl ),
                    self::TIER_FILE => self::setToFile( Cache_KeyManager::generateSpecialKey( $key, self::TIER_FILE ), $data, $ttl ),
                    default => false
                };

                // debug logging
                LOG::debug( 'Set to Tier', [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key,
                    'ttl' => $ttl
                ] );

            // whoopsie... log the error set false
            } catch ( Exception $e ) {
                LOG::error( "Error setting to tier {$tier}: " . $e -> getMessage( ), [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key,
                    'ttl' => $ttl
                ] );
                $result = false;
            }
            
            // return the result
            return $result;
        }

        /**
         * Internal method to delete data from a specific tier with connection pooling
         * 
         * Handles the actual deletion of data from cache tiers with support for
         * connection pooling on database-based tiers (Redis, Memcached).
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to delete
         * @param string $tier The tier to delete from
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromTierInternal( string $key, string $tier ): bool {
            
            // Generate the appropriate key for this tier
            $tier_key = Cache_KeyManager::generateKey( $key, $tier );
            
            // try to match the tier to the internal method
            try {
                $result = match( $tier ) {
                    self::TIER_REDIS => self::deleteFromRedis( $tier_key ),
                    self::TIER_MEMCACHED => self::deleteFromMemcached( $tier_key ),
                    self::TIER_OPCACHE => self::deleteFromOPcache( $tier_key ),
                    self::TIER_MMAP => self::deleteFromMmap( $key ),
                    self::TIER_SHMOP => self::deleteFromShmop( $key ),
                    self::TIER_APCU => self::deleteFromAPCu( $tier_key ),
                    self::TIER_YAC => self::deleteFromYac( $tier_key ),
                    self::TIER_FILE => self::deleteFromFile( $tier_key ),
                    default => false
                };

            // debug log
            LOG::debug( 'Delete From Tier', ['tier' => $tier, 'key' => $key, 'tier_key' => $tier_key] );

            // whoopsie... log the error and set the result
            } catch ( Exception $e ) {
                LOG::error( "Error deleting from tier", [
                    'error' => $e -> getMessage( ),
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key
                ] );
                $result = false;
            }

            // return the result
            return $result;
        }

        /**
         * Promote cache item to higher priority tiers
         * 
         * When an item is found in a lower-priority tier, this method automatically
         * copies it to all higher-priority tiers for faster future access.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to promote
         * @param mixed $data The cached data to promote
         * @param string $current_tier The tier where the data was found
         * @return void Returns nothing
         */
        private static function promoteToHigherTiers( string $key, mixed $data, string $current_tier ): void {

            // get the available tiers
            $available_tiers = self::getAvailableTiers( );

            // get the index of the current tier
            $current_index = array_search( $current_tier, $available_tiers );
            
            // if we couldn't find the tier in the available list, just return
            if ( $current_index === false ) return;
            
            // Promote to all higher tiers (lower index = higher priority)
            for ( $i = 0; $i < $current_index; $i++ ) {

                // try to set the item to the higher priority tier
                $promote_success = self::setToTierInternal( $key, $data, 3600, $available_tiers[$i] ); // Default 1 hour TTL for promotion
                
                // if it was successful
                if ( $promote_success ) {

                    // log the promotion
                    LOG::debug( "Cache Promoted", [
                        'from_tier' => $current_tier,
                        'to_tier' => $available_tiers[$i]
                    ] );
                }
            }
        }

        /**
         * Clear all data from a specific cache tier
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier to clear
         * @return bool Returns true if successfully cleared, false otherwise
         */
        public static function clearTier( string $tier ): bool {

            // try to clear based on the tier type
            try {

                // switch on the tier
                switch ( $tier ) {

                    // array
                    case self::TIER_ARRAY:

                        // return clearing the array
                        return self::clearArray( );

                    // opcache
                    case self::TIER_OPCACHE:

                        // return clearing the opcache
                        return self::clearOPcache( );
                        
                    // shmop
                    case self::TIER_SHMOP:

                        $success = true;
                        
                        // Use the trait's clearShmop method instead of manual tracking
                        $success = self::clearShmop();

                        // Clear the tracking array
                        self::$_shmop_segments = [];
                        return $success;

                    // mmap
                    case self::TIER_MMAP:
                        $success = true;
                        
                        // Use the trait's clearMmap method instead of manual tracking
                        $success = self::clearMmap( );
                        
                        // Clear the tracking array
                        self::$_mmap_files = [];
                        return $success;
                        
                    // apcu
                    case self::TIER_APCU:

                        // return clearing the apcu cache
                        return self::clearAPCu( );
                        
                    // yac
                    case self::TIER_YAC:

                        // return flushing the yac cache
                        return extension_loaded( 'yac' ) ? yac_flush( ) : false;
                                                
                    // redis
                    case self::TIER_REDIS:

                        // see if we are utilizing connection pooling
                        if ( self::$_connection_pooling_enabled ) {

                            // get the connection
                            $connection = Cache_ConnectionPool::getConnection( 'redis' );

                            // if we have a connection
                            if ( $connection ) {

                                // try to flush the db
                                try {
                                    return $connection -> flushDB( );

                                // finally... return the connection
                                } finally {
                                    Cache_ConnectionPool::returnConnection( 'redis', $connection );
                                }
                            }

                        // otherwise
                        } else {

                            // try to flush the redis db directly
                            try {

                                // create a redis connection
                                $redis = new \Redis( );
                                $config = Cache_Config::get( 'redis' );

                                // connect to redis
                                $redis -> pconnect( $config['host'], $config['port'] );

                                // select the database
                                $redis -> select( $config['database'] );

                                // return flushing the db
                                return $redis -> flushDB( );

                            // whoopsie...
                            } catch ( \Exception $e ) {

                                // log the error and return false
                                LOG::error( "Redis clear error: " . $e -> getMessage( ), 'redis_operation' );
                                return false;
                            }
                        }

                        // default return
                        return true;
                        
                    // memcached
                    case self::TIER_MEMCACHED:

                        // see if we're utilizing connection pooling
                        if ( self::$_connection_pooling_enabled ) {

                            // get the connection
                            $connection = Cache_ConnectionPool::getConnection( 'memcached' );

                            // if we have a connection
                            if ( $connection ) {

                                // try to flush
                                try {
                                    return $connection -> flush( );

                                // finally... return the connection
                                } finally {
                                    Cache_ConnectionPool::returnConnection( 'memcached', $connection );
                                }
                            }

                        // otherwise
                        } else {

                            // try to flush the memcached cache directly
                            try {

                                // create a new memcached instance
                                $memcached = new \Memcached( );
                                $config = Cache_Config::get( 'memcached' );

                                // add the server
                                $memcached -> addServer( $config['host'], $config['port'] );

                                // return flushing the cache
                                return $memcached -> flush( );

                            // whoopsie...
                            } catch ( \Exception $e ) {

                                // log the error and return false
                                LOG::error( "Memcached clear error: " . $e -> getMessage( ), 'memcached_operation' );
                                return false;
                            }
                        }

                        // default return
                        return true;
                        
                    // file
                    case self::TIER_FILE:

                        // get all the files in the fallback path
                        $files = glob( self::$_fallback_path . '*' );

                        // default success
                        $success = true;

                        // loop over each file
                        foreach ( $files as $file ) {

                            // if it's actually a file
                            if ( is_file( $file ) ) {

                                // unlink it and update success
                                $success = $success && unlink( $file );
                            }
                        }

                        // return the success
                        return $success;
                        
                    // default
                    default:

                        // return false
                        return false;
                }

                // debug logging
                LOG::debug( 'Clear Tier Cache', ['tier' => $tier] );

            // whoopsie...
            } catch ( \Exception $e ) {

                // log the error and return false
                LOG::error( "Error clearing tier", ['tier' => $tier, 'error' => $e -> getMessage( )] );
                return false;
            }
        }

        /**
         * Remove expired cache entries from all tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return int Returns the number of expired items removed
         */
        public static function cleanupExpired( ): int {

            // default count
            $count = 0;
            
            // Clean up OPCache files
            $available_tiers = self::getAvailableTiers( );

            // if opcache is in the available tiers
            if ( in_array( self::TIER_OPCACHE, $available_tiers ) ) {

                // add to the count
                $count += self::cleanupOPcacheFiles( );
            }
            
            // Clean up file cache
            $files = glob( self::getCachePath( ) . '*' );
            
            // loop over each file
            foreach ( $files as $file ) {

                // if it's a real file
                if ( is_file( $file ) ) {

                    // get the file contents
                    $content = file_get_contents( $file );

                    // if we have content
                    if ( $content !== false ) {

                        // get the expiry time
                        $expires = substr( $content, 0, 10 );
                        
                        // if it's numeric and expired
                        if ( is_numeric( $expires ) && time( ) > (int)$expires ) {

                            // if we can unlink it, increment the count
                            if ( unlink( $file ) ) {
                                $count++;
                            }
                        }
                    }
                }
            }
            
            // Clean up expired shmop segments
            foreach ( self::$_shmop_segments as $cache_key => $shmop_key ) {

                // try to cleanup the segment
                try {

                    // try to open the segment
                    $segment = @shmop_open( $shmop_key, 'a', 0, 0 );

                    // if we have a segment
                    if ( $segment !== false ) {

                        // get the size
                        $size = shmop_size( $segment );

                        // if we have a size
                        if ( $size > 0 ) {

                            // read the data
                            $data = shmop_read( $segment, 0, $size );

                            // try to unserialize the data
                            $unserialized = @unserialize( trim( $data, "\0" ) );
                            
                            // if it's an array with an expiry
                            if ( is_array( $unserialized ) && isset( $unserialized['expires'] ) ) {

                                // if it's expired
                                if ( $unserialized['expires'] <= time( ) ) {

                                    // delete the segment
                                    @shmop_delete( $segment );

                                    // remove from the tracking array
                                    unset( self::$_shmop_segments[$cache_key] );

                                    // increment the count
                                    $count++;
                                }
                            }
                        }

                        // close the segment
                        @shmop_close( $segment );
                    }

                // whoopsie...
                } catch ( \Exception $e ) {

                    // remove it from the tracking array
                    unset( self::$_shmop_segments[$cache_key] );
                }
            }
            
            // Clean up expired mmap files
            foreach ( self::$_mmap_files as $cache_key => $filepath ) {

                // try to clean up the file
                try {

                    // if the file exists
                    if ( file_exists( $filepath ) ) {

                        // open the file
                        $file = fopen( $filepath, 'rb' );

                        // if we have a file handle
                        if ( $file !== false ) {

                            // if we can lock the file for reading
                            if ( flock( $file, LOCK_SH ) ) {

                                // read the file data
                                $data = fread( $file, filesize( $filepath ) );

                                // unlock the file
                                flock( $file, LOCK_UN );

                                // close the file
                                fclose( $file );
                                
                                // try to unserialize the data
                                $unserialized = @unserialize( trim( $data, "\0" ) );
                                
                                // if it's an array and has an expiry
                                if ( is_array( $unserialized ) && isset( $unserialized['expires'] ) ) {

                                    // if it's expired
                                    if ( $unserialized['expires'] <= time( ) ) {

                                        // if we can unlink the file
                                        if ( @unlink( $filepath ) ) {

                                            // remove from the tracking array
                                            unset( self::$_mmap_files[$cache_key] );

                                            // increment the count
                                            $count++;
                                        }
                                    }
                                }

                            // otherwise
                            } else {

                                // just close the file
                                fclose( $file );
                            }
                        }

                    // otherwise
                    } else {

                        // remove from the tracking array
                        unset( self::$_mmap_files[$cache_key] );
                    }

                // whoopsie...
                } catch ( \Exception $e ) {

                    // remove from the tracking array
                    unset( self::$_mmap_files[$cache_key] );
                }
            }
            
            // log the completion
            LOG::info( "Cleanup completed", ['expired_items_removed' => $count] );
            
            // return the count
            return $count;
        }

        /**
         * Perform comprehensive cache system cleanup
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return int Returns the number of expired items removed
         */
        public static function cleanup( ): int {

            // cleanup expired items
            $count = self::cleanupExpired( );
            
            // if connection pooling is enabled
            if ( self::$_connection_pooling_enabled ) {

                // cleanup the connection pools
                Cache_ConnectionPool::cleanup( );
            }
            
            // return the count
            return $count;
        }

        /**
         * Close all connections and clean up system resources
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void Returns nothing
         */
        public static function close( ): void {

            // if connection pooling is enabled
            if ( self::$_connection_pooling_enabled ) {

                // close all connection pools
                Cache_ConnectionPool::closeAll( );
            }
            
            // Clean up tracking arrays
            self::$_shmop_segments = [];
            self::$_mmap_files = [];
            
            // log the close
            LOG::info( "Cache system closed", ['system'] );
        }

        /**
         * Get comprehensive cache system statistics
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns comprehensive statistics array
         */
        public static function getStats( ): array {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // default stats array
            $stats = [];
            
            // Get tier-specific stats using traits
            if ( function_exists( 'opcache_get_status' ) ) {

                // get the opcache stats
                $stats[self::TIER_OPCACHE] = self::getOPcacheStats( );
            }
            
            // get the shmop stats
            $stats[self::TIER_SHMOP] = [
                'segments_tracked' => count( self::$_shmop_segments )
            ];
            
            // if we have the apcu cache info function
            if ( function_exists( 'apcu_cache_info' ) ) {

                // get the apcu stats
                $stats[self::TIER_APCU] = apcu_cache_info( );
            }
            
            // if yac is loaded and has the info function
            if ( extension_loaded( 'yac' ) && function_exists( 'yac_info' ) ) {

                // get the yac stats
                $stats[self::TIER_YAC] = yac_info( );

            // otherwise if yac is loaded
            } else if ( extension_loaded( 'yac' ) ) {

                // just note that the extension is loaded
                $stats[self::TIER_YAC] = ['extension_loaded' => true];
            }
            
            // Get MMAP stats
            $mmap_base_path = self::getMmapBasePath( );
            $mmap_files = glob( $mmap_base_path . '*.mmap' );

            // set the mmap stats
            $stats[self::TIER_MMAP] = [
                'files_tracked' => count( self::$_mmap_files ),
                'files_on_disk' => count( $mmap_files ),
                'total_size' => array_sum( array_map( 'filesize', $mmap_files ) )
            ];
            
            // Add connection pool stats
            if ( self::$_connection_pooling_enabled ) {

                // get the pool stats
                $stats['connection_pools'] = Cache_ConnectionPool::getPoolStats( );
            }
            
            // File cache stats
            $files = glob( self::$_fallback_path . '*' );

            // set the file tier stats
            $stats[self::TIER_FILE] = [
                'file_count' => count( $files ),
                'total_size' => array_sum( array_map( 'filesize', $files ) ),
                'path' => self::$_fallback_path
            ];
            
            // Add manager statistics
            $stats['tier_manager'] = Cache_TierManager::getDiscoveryInfo( );
            $stats['key_manager'] = Cache_KeyManager::getCacheStats( );
            $stats['health_monitor'] = Cache_HealthMonitor::getMonitoringStats( );
            
            // return the stats
            return $stats;
        }

        /**
         * Check overall cache system health
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns health status for all tiers
         */
        public static function isHealthy( ): array {
            return Cache_HealthMonitor::checkAllTiers();
        }

        /**
         * Get current configuration settings for all cache tiers
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns complete configuration settings
         */
        public static function getSettings( ): array {
            return Cache_Config::getAll();
        }

        /**
         * Set a custom cache path for file-based caching
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path The custom cache directory path
         * @return bool Returns true if path was set successfully, false otherwise
         */
        public static function setCachePath( string $path ): bool {
            
            // Normalize the path (ensure it ends with a slash)
            $path = rtrim( $path, '/' ) . '/';
            
            // Try to create the cache directory with proper permissions
            if ( self::createCacheDirectory( $path ) ) {
                self::$_configurable_cache_path = $path;
                
                // If we're already initialized, update the fallback path immediately
                if ( self::$_initialized ) {
                    self::$_fallback_path = $path;
                }
                
                // ✅ FIX: Update the global config so other tiers can access it
                Cache_Config::setGlobalPath( $path );
                
                // Also update the file backend config to match
                Cache_Config::setBackendPath( 'file', $path );
                
                LOG::debug( "Cache path updated", ['new_path' => $path] );
                return true;
            }
            
            LOG::error( "Failed to set cache path", ['attempted_path' => $path] );
            return false;
        }

        /**
         * Get the current cache path being used
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string Returns the current cache directory path
         */
        public static function getCachePath( ): string {
            return self::$_fallback_path ?? sys_get_temp_dir( ) . '/kpt_cache/';
        }

        /**
         * Get comprehensive debug information
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns comprehensive debug information array
         */
        public static function debug( ): array {

            // make sure we're initialized
            self::ensureInitialized( );
            
            // build the debug info
            $debug_info = [
                'cache_system' => [
                    'initialized' => self::$_initialized,
                    'last_used_tier' => self::$_last_used_tier,
                    'connection_pooling_enabled' => self::$_connection_pooling_enabled,
                    'async_enabled' => self::$_async_enabled,
                    'cache_path' => self::$_fallback_path,
                ],
                'tier_manager' => Cache_TierManager::getDiscoveryInfo( ),
                'key_manager' => [
                    'cache_stats' => Cache_KeyManager::getCacheStats( ),
                    'global_namespace' => Cache_KeyManager::getGlobalNamespace( ),
                    'key_separator' => Cache_KeyManager::getKeySeparator( ),
                    'tier_limitations' => Cache_KeyManager::getTierLimitations( )
                ],
                'logger' => LOG::getStats( ),
                'health_monitor' => [
                    'monitoring_stats' => Cache_HealthMonitor::getMonitoringStats( ),
                    'health_status' => Cache_HealthMonitor::getHealthStatus( )
                ],
                'system_info' => [
                    'temp_dir' => sys_get_temp_dir( ),
                    'current_user' => get_current_user( ),
                    'process_id' => getmypid( ),
                    'umask' => sprintf( '%04o', umask( ) ),
                ]
            ];
            
            // return the debug info
            return $debug_info;
        }

        /**
         * Create cache directory with proper permissions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Directory path to create
         * @return bool Returns true if directory was created or already exists and is writable
         */
        private static function createCacheDirectory( string $path ): bool {

            // if the directory doesn't exist
            if ( ! is_dir( $path ) ) {

                // if we can't make the directory, return false
                if ( ! @mkdir( $path, 0755, true ) ) {
                    return false;
                }
            }
            
            // return if the path is writable
            return is_writable( $path );
        }

        /**
         * Set maximum items for array cache
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param int $max_items Maximum number of items
         * @return void
         */
        public static function setArrayCacheMaxItems( int $max_items ): void {
            self::setArrayCacheMaxItems( $max_items );
        }

        /**
         * Get array cache contents for debugging
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param bool $include_data Whether to include data
         * @return array Cache contents
         */
        public static function getArrayCacheContents( bool $include_data = false ): array {
            return self::getArrayCacheContents( $include_data );
        }

        /**
         * Clean up expired array cache items
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return int Number of items cleaned up
         */
        public static function cleanupArrayExpired( ): int {
            return self::cleanupArrayExpired( );
        }

        // FIX 4: In cache.php - Add new methods for tracked deletion
        private static function deleteFromShmopTracked( string $key ): bool {
            // Check if we have this key tracked
            if ( isset( self::$_shmop_segments[$key] ) ) {
                $shmop_key = self::$_shmop_segments[$key];
                $result = self::deleteFromShmopInternal( $shmop_key );
                
                // Remove from tracking regardless of success
                unset( self::$_shmop_segments[$key] );
                
                LOG::debug( "SHMOP tracked deletion", [
                    'cache_key' => $key, 
                    'shmop_key' => $shmop_key, 
                    'success' => $result
                ] );
                
                return $result;
            } else {
                // Fallback to generating the key if not tracked
                $shmop_key = Cache_KeyManager::generateSpecialKey( $key, self::TIER_SHMOP );
                LOG::warning( "SHMOP key not tracked, using generated key", [
                    'cache_key' => $key,
                    'generated_shmop_key' => $shmop_key
                ] );
                return self::deleteFromShmopInternal( $shmop_key );
            }
        }

        private static function deleteFromMmapTracked( string $key ): bool {
            // Check if we have this key tracked
            if ( isset( self::$_mmap_files[$key] ) ) {
                $mmap_path = self::$_mmap_files[$key];
                $result = self::deleteFromMmapInternal( $mmap_path );
                
                // Remove from tracking regardless of success
                unset( self::$_mmap_files[$key] );
                
                LOG::debug( "MMAP tracked deletion", [
                    'cache_key' => $key, 
                    'mmap_path' => $mmap_path, 
                    'success' => $result
                ] );
                
                return $result;
            } else {
                // Fallback to generating the path if not tracked
                $mmap_path = Cache_KeyManager::generateSpecialKey( $key, self::TIER_MMAP );
                LOG::warning( "MMAP path not tracked, using generated path", [
                    'cache_key' => $key,
                    'generated_mmap_path' => $mmap_path
                ] );
                return self::deleteFromMmapInternal( $mmap_path );
            }
        }

        /**
         * Debug method to check tracking arrays
         */
        public static function debugTrackingArrays(): array {
            return [
                'shmop_segments' => self::$_shmop_segments,
                'mmap_files' => self::$_mmap_files,
                'shmop_count' => count( self::$_shmop_segments ),
                'mmap_count' => count( self::$_mmap_files )
            ];
        }

        /**
         * Testing method to verify fixes work
         */
        public static function testClearingFixes(): array {
            $results = [];
            
            // Test SHMOP
            $shmop_key = 'test_shmop_' . time();
            $results['shmop_set'] = Cache::setToTier( $shmop_key, 'test_data', 3600, 'shmop' );
            $results['shmop_tracking_after_set'] = isset( self::$_shmop_segments[$shmop_key] );
            $results['shmop_get'] = Cache::getFromTier( $shmop_key, 'shmop' );
            $results['shmop_clear'] = Cache::clearTier( 'shmop' );
            $results['shmop_get_after_clear'] = Cache::getFromTier( $shmop_key, 'shmop' );
            
            // Test MMAP
            $mmap_key = 'test_mmap_' . time();
            $results['mmap_set'] = Cache::setToTier( $mmap_key, 'test_data', 3600, 'mmap' );
            $results['mmap_tracking_after_set'] = isset( self::$_mmap_files[$mmap_key] );
            $results['mmap_get'] = Cache::getFromTier( $mmap_key, 'mmap' );
            $results['mmap_clear'] = Cache::clearTier( 'mmap' );
            $results['mmap_get_after_clear'] = Cache::getFromTier( $mmap_key, 'mmap' );
            
            // Test path configuration
            $original_path = Cache::getCachePath();
            $custom_path = '/tmp/test_cache_' . time() . '/';
            $results['path_set'] = Cache::setCachePath( $custom_path );
            $results['global_path_updated'] = Cache_Config::getGlobalPath() === $custom_path;
            
            $mmap_key_custom = 'test_mmap_custom_' . time();
            Cache::setToTier( $mmap_key_custom, 'test_data', 3600, 'mmap' );
            $mmap_path = Cache_KeyManager::generateSpecialKey( $mmap_key_custom, 'mmap' );
            $results['mmap_uses_custom_path'] = strpos( $mmap_path, $custom_path . 'mmap/' ) === 0;
            
            // Cleanup
            Cache::clearTier( 'mmap' );
            Cache::setCachePath( $original_path );
            
            return $results;
        }

    }
}
