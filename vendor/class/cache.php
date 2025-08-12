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
        use Cache_APCU, Cache_File, Cache_Memcached;
        use Cache_MMAP, Cache_OPCache, Cache_Redis;
        use Cache_SHMOP, Cache_YAC;
        use Cache_Async, Cache_Redis_Async, Cache_File_Async, Cache_Memcached_Async;
        use Cache_Mixed_Async, Cache_MMAP_Async, Cache_OPCache_Async;

        /** @var string OPcache tier - Highest performance, memory-based opcache tier */
        const TIER_OPCACHE = 'opcache';
        
        /** @var string SHMOP tier - Shared memory operations tier */
        const TIER_SHMOP = 'shmop';
        
        /** @var string APCu tier - Alternative PHP Cache user data tier */
        const TIER_APCU = 'apcu';
        
        /** @var string YAC tier - Yet Another Cache tier */
        const TIER_YAC = 'yac';
        
        /** @var string MMAP tier - Memory-mapped file tier */
        const TIER_MMAP = 'mmap';
        
        /** @var string Redis tier - Redis database tier */
        const TIER_REDIS = 'redis';
        
        /** @var string Memcached tier - Memcached distributed memory tier */
        const TIER_MEMCACHED = 'memcached';
        
        /** @var string File tier - File-based caching tier (lowest priority fallback) */
        const TIER_FILE = 'file';

        /** @var string|null Fallback cache directory path for file-based caching */
        private static ?string $_fallback_path = null;
        
        /** @var bool Initialization status flag */
        private static bool $_initialized = false;
        
        /** @var string|null User-configurable cache path override */
        private static ?string $_configurable_cache_path = null;
        
        /** @var array Tracking array for SHMOP memory segments */
        private static array $_shmop_segments = [];
        
        /** @var array Tracking array for MMAP file handles */
        private static array $_mmap_files = [];
        
        /** @var string|null The cache tier used for the last operation */
        private static ?string $_last_used_tier = null;
        
        /** @var bool Connection pooling feature toggle */
        private static bool $_connection_pooling_enabled = true;
        
        /** @var bool Asynchronous operations feature toggle */
        private static bool $_async_enabled = false;
        
        /** @var object|null Event loop instance for async operations */
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
            
            // Initialize the fallback path if not set
            if ( self::$_fallback_path === null ) {
                self::$_fallback_path = sys_get_temp_dir( ) . '/kpt_cache/';
            }
            
            // Initialize file fallback
            self::initFallback( );
            
            // Initialize connection pools for database backends
            if ( self::$_connection_pooling_enabled ) {
                self::initializeConnectionPools( );
            }
            
            // mark us as initialized
            self::$_initialized = true;
            
            // Log initialization
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
            
            // Initialize Key Manager
            if ( isset( $config['key_manager'] ) ) {
                $km_config = $config['key_manager'];
                
                if ( isset( $km_config['global_namespace'] ) ) {
                    Cache_KeyManager::setGlobalNamespace( $km_config['global_namespace'] );
                }
                
                if ( isset( $km_config['key_separator'] ) ) {
                    Cache_KeyManager::setKeySeparator( $km_config['key_separator'] );
                }
                
                if ( isset( $km_config['auto_hash_long_keys'] ) ) {
                    Cache_KeyManager::setAutoHashLongKeys( $km_config['auto_hash_long_keys'] );
                }
                
                if ( isset( $km_config['hash_algorithm'] ) ) {
                    Cache_KeyManager::setHashAlgorithm( $km_config['hash_algorithm'] );
                }
            }
            
            // Initialize Health Monitor
            $health_config = $config['health_monitor'] ?? [];
            Cache_HealthMonitor::initialize( $health_config );
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

            $available_tiers = self::getAvailableTiers( );

            // Configure Redis pool if it's available as a tier
            if ( in_array( self::TIER_REDIS, $available_tiers ) ) {

                // configure the pool
                Cache_ConnectionPool::configurePool( 'redis', [
                    'min_connections' => 2,
                    'max_connections' => 10,
                    'idle_timeout' => 300
                ] );
            }
            
            // Configure Memcached pool
            if ( in_array( self::TIER_MEMCACHED, $available_tiers ) ) {
                Cache_ConnectionPool::configurePool( 'memcached', [
                    'min_connections' => 1,
                    'max_connections' => 5,
                    'idle_timeout' => 300
                ] );
            }
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

            // Use configurable path if set, otherwise default
            $cache_path = self::$_configurable_cache_path ?: self::$_fallback_path;
            
            // Try to create and setup the cache directory
            if ( self::createCacheDirectory( $cache_path ) ) {
                self::$_fallback_path = $cache_path;
                return;
            }
            
            // If the preferred path failed, try alternative paths
            $fallback_paths = [
                sys_get_temp_dir( ) . '/kpt_cache_' . getmypid( ) . '/',
                getcwd( ) . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_' . getmypid( ) . '/',
            ];
            
            // for each path
            foreach ( $fallback_paths as $alt_path ) {

                // if we can create the path, set it as the fallback path and return
                if ( self::createCacheDirectory( $alt_path ) ) {
                    self::$_fallback_path = $alt_path;
                    return;
                }
            }
            
            // Last resort - use system temp with unique name
            $temp_path = sys_get_temp_dir( ) . '/kpt_' . uniqid( ) . '_' . getmypid( ) . '/';
            
            // if we can create it, set the fallback path
            if ( self::createCacheDirectory( $temp_path ) ) {
                self::$_fallback_path = $temp_path;
            } else {

                // If all else fails, disable file caching
                LOG::error( "Unable to create writable cache directory", 'initialization' );
                
                // Remove file tier from available tiers if it was discovered
                $available_tiers = self::getAvailableTiers();
                $key = array_search( self::TIER_FILE, $available_tiers );

                // it is available, remove it
                if ( $key !== false ) {

                    // Note: We can't directly modify TierManager's internal state
                    // This would need to be handled by the TierManager
                    LOG::warning( "File tier disabled due to directory creation failure", 'initialization' );
                }
            }
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
                    LOG::debug( "Cache Hit", [$key] );

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

            // hold the arguments for the logging
            $args = func_get_args( );
            
            // if there's no data, then there's nothing to do here... just return
            if ( empty( $data ) ) {
                LOG::error( "Attempted to cache empty data", [$args] );
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
                    LOG::debug( "Cache item set", $args );
                } else {

                    // Log failed set operation
                    LOG::error( "Failed to set cache item in tier {$tier}", $args );
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
                    LOG::error( "Failed to delete cache item from tier {$tier}", 'cache_operation', [
                        'key' => $key,
                        'tier' => $tier
                    ] );
                } else {
                    LOG::debug( "Cache item deleted", [$tier, $key]);
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
            
            $available_tiers = self::getAvailableTiers( );
            
            // loop through each tier
            foreach ( $available_tiers as $tier ) {

                // if clearing it was not successful
                if ( ! self::clearTier( $tier ) ) {
                    $success = false;
                    LOG::error( "Failed to clear tier {$tier}", 'cache_operation', ['tier' => $tier] );
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
                LOG::error( "Invalid tier specified: {$tier}", 'tier_validation', ['key' => $key] );
                return false;
            }
            
            // now... is the tier actually available?
            if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                LOG::error( "Tier not available: {$tier}", 'tier_availability', ['key' => $key] );
                return false;
            }
            
            // get the item from the tier
            $result = self::getFromTierInternal( $key, $tier );
            
            // if we have a result
            if ( $result !== false ) {

                // set the last used and return the item
                self::$_last_used_tier = $tier;
                LOG::debug( "Cache Hit", [$tier, $key] );
                return $result;
            }

            // Fallback to default hierarchy if enabled and tier failed
            LOG::debug( "Cache Miss", [$tier, $key] );
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
                LOG::error( "Invalid tier specified: {$tier}", 'tier_validation', ['key' => $key] );
                return false;
            }
            
            // if the tier is not available
            if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                LOG::error( "Tier not available: {$tier}", 'tier_availability', ['key' => $key] );
                return false;
            }
            
            // if we have not data
            if ( empty( $data ) ) {
                LOG::warning( "Attempted to cache empty data to tier {$tier}", 'operation', ['key' => $key] );
                return false;
            }
            
            // set the data to the tier
            $success = self::setToTierInternal( $key, $data, $ttl, $tier );
            
            // if it was successfully set
            if ( $success ) {
                self::$_last_used_tier = $tier;
                LOG::debug( "Cache Set", [$tier, $key] );
            } else {
                LOG::error( "Failed to set cache item to tier {$tier}", 'cache_operation', [
                    'key' => $key,
                    'tier' => $tier
                ] );
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
                LOG::error( "Invalid tier specified: {$tier}", 'tier_validation', ['key' => $key] );
                return false;
            }
            
            // is the tier available
            if ( ! Cache_TierManager::isTierAvailable( $tier ) ) {
                LOG::error( "Tier not available: {$tier}", 'tier_availability', ['key' => $key] );
                return false;
            }
            
            // delete from the tier
            $success = self::deleteFromTierInternal( $key, $tier );
            
            // if it was successful
            if ( $success ) {
                self::$_last_used_tier = $tier;
                LOG::debug( "Cache Deleted", [$tier, $key] );
            } else {
                LOG::error( "Failed to delete cache item from tier {$tier}", 'cache_operation', [
                    'key' => $key,
                    'tier' => $tier
                ] );
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
                LOG::warning( "Attempted to cache empty data to multiple tiers", 'operation', [
                    'key' => $key,
                    'tiers' => $tiers
                ] );
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
                    
                    LOG::debug( "Cache Set", [$tier, $key] );
                } else {
                    LOG::error( "Failed to set cache item to tier {$tier} in multi-tier operation", 'cache_operation', [
                        'key' => $key,
                        'tier' => $tier
                    ] );
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
                    LOG::debug( "Cache Deleted", [$tier, $key] );
                } else {
                    LOG::error( "Failed to delete cache item from tier {$tier} in multi-tier operation", 'cache_operation', [
                        'key' => $key,
                        'tier' => $tier
                    ] );
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
        private static function getFromTierInternal(string $key, string $tier): mixed {

            // Generate the appropriate key for this tier
            $tier_key = Cache_KeyManager::generateKey( $key, $tier );
            
            // default results
            $result = false;
            
            try {
                switch ($tier) {
                    case self::TIER_REDIS:
                        if (self::$_connection_pooling_enabled) {
                            $connection = Cache_ConnectionPool::getConnection('redis');
                            if ($connection) {
                                try {
                                    $value = $connection->get($tier_key);
                                    $result = $value !== false ? unserialize($value) : false;
                                } finally {
                                    Cache_ConnectionPool::returnConnection('redis', $connection);
                                }
                            }
                        } else {
                            $result = self::getFromRedis($tier_key);
                        }
                        break;
                        
                    case self::TIER_MEMCACHED:
                        if (self::$_connection_pooling_enabled) {
                            $connection = Cache_ConnectionPool::getConnection('memcached');
                            if ($connection) {
                                try {
                                    $result = $connection->get($tier_key);
                                    if ($connection->getResultCode() !== \Memcached::RES_SUCCESS) {
                                        $result = false;
                                    }
                                } finally {
                                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                                }
                            }
                        } else {
                            $result = self::getFromMemcached($tier_key);
                        }
                        break;
                        
                    case self::TIER_OPCACHE:
                        $result = self::getFromOPcache($tier_key);
                        break;
                        
                    case self::TIER_SHMOP:
                        $shmop_key = Cache_KeyManager::generateSpecialKey( $key, self::TIER_SHMOP );
                        $result = self::getFromShmop($shmop_key);
                        break;
                        
                    case self::TIER_APCU:
                        $result = self::getFromAPCu($tier_key);
                        break;
                        
                    case self::TIER_YAC:
                        $result = self::getFromYac($tier_key);
                        break;
                        
                    case self::TIER_MMAP:
                        $mmap_path = Cache_KeyManager::generateSpecialKey( $key, self::TIER_MMAP );
                        $result = self::getFromMmap($mmap_path);
                        break;
                        
                    case self::TIER_FILE:
                        $file_key = Cache_KeyManager::generateSpecialKey( $key, self::TIER_FILE );
                        $result = self::getFromFile($file_key);
                        break;
                        
                    default:
                        $result = false;
                        break;
                }
            } catch ( \Exception $e ) {
                LOG::error( "Error getting from tier {$tier}: " . $e->getMessage(), 'tier_operation', [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key
                ] );
                $result = false;
            }
            
            return $result;
        }

        /**
         * Internal method to set data to a specific tier with connection pooling
         * 
         * Handles the actual storage of data to cache tiers with support for
         * connection pooling on database-based tiers (Redis, Memcached).
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
        private static function setToTierInternal(string $key, mixed $data, int $ttl, string $tier): bool {
            
            // Generate the appropriate key for this tier
            $tier_key = Cache_KeyManager::generateKey( $key, $tier );
            
            try {
                $result = match($tier) {
                    self::TIER_REDIS => self::setToRedisInternal($tier_key, $data, $ttl),
                    self::TIER_MEMCACHED => self::setToMemcachedInternal($tier_key, $data, $ttl),
                    self::TIER_OPCACHE => self::setToOPcache($tier_key, $data, $ttl),
                    self::TIER_SHMOP => self::setToShmop(Cache_KeyManager::generateSpecialKey( $key, self::TIER_SHMOP ), $data, $ttl),
                    self::TIER_APCU => self::setToAPCu($tier_key, $data, $ttl),
                    self::TIER_YAC => self::setToYac($tier_key, $data, $ttl),
                    self::TIER_MMAP => self::setToMmap(Cache_KeyManager::generateSpecialKey( $key, self::TIER_MMAP ), $data, $ttl),
                    self::TIER_FILE => self::setToFile(Cache_KeyManager::generateSpecialKey( $key, self::TIER_FILE ), $data, $ttl),
                    default => false
                };
            } catch ( Exception $e ) {
                LOG::error( "Error setting to tier {$tier}: " . $e->getMessage(), 'tier_operation', [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key,
                    'ttl' => $ttl
                ] );
                $result = false;
            }
            
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
        private static function deleteFromTierInternal(string $key, string $tier): bool {
            
            // Generate the appropriate key for this tier
            $tier_key = Cache_KeyManager::generateKey( $key, $tier );
            
            try {
                $result = match($tier) {
                    self::TIER_REDIS => self::deleteFromRedisInternal($tier_key),
                    self::TIER_MEMCACHED => self::deleteFromMemcachedInternal($tier_key),
                    self::TIER_OPCACHE => self::deleteFromOPcacheInternal($tier_key),
                    self::TIER_SHMOP => self::deleteFromShmopInternal(Cache_KeyManager::generateSpecialKey( $key, self::TIER_SHMOP )),
                    self::TIER_APCU => self::deleteFromAPCuInternal($tier_key),
                    self::TIER_YAC => self::deleteFromYacInternal($tier_key),
                    self::TIER_MMAP => self::deleteFromMmapInternal(Cache_KeyManager::generateSpecialKey( $key, self::TIER_MMAP )),
                    self::TIER_FILE => self::deleteFromFileInternal(Cache_KeyManager::generateSpecialKey( $key, self::TIER_FILE )),
                    default => false
                };
            } catch ( Exception $e ) {
                LOG::error( "Error deleting from tier {$tier}: " . $e->getMessage(), 'tier_operation', [
                    'tier' => $tier,
                    'key' => $key,
                    'tier_key' => $tier_key
                ] );
                $result = false;
            }
            
            return $result;
        }

        /**
         * Internal Redis set operation with connection pooling support
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successfully stored, false otherwise
         */
        private static function setToRedisInternal(string $key, mixed $data, int $ttl): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = Cache_ConnectionPool::getConnection('redis');
                if ($connection) {
                    try {
                        return $connection->setex($key, $ttl, serialize($data));
                    } catch ( \Exception $e ) {
                        LOG::error( "Redis set error: " . $e->getMessage(), 'redis_operation' );
                        return false;
                    } finally {
                        Cache_ConnectionPool::returnConnection('redis', $connection);
                    }
                }
            } else {
                return self::setToRedis($key, $data, $ttl);
            }
            return false;
        }

        /**
         * Internal Memcached set operation with connection pooling support
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to store
         * @param mixed $data The data to store
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successfully stored, false otherwise
         */
        private static function setToMemcachedInternal(string $key, mixed $data, int $ttl): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = Cache_ConnectionPool::getConnection('memcached');
                if ($connection) {
                    try {
                        return $connection->set($key, $data, time() + $ttl);
                    } catch ( \Exception $e ) {
                        LOG::error( "Memcached set error: " . $e->getMessage(), 'memcached_operation' );
                        return false;
                    } finally {
                        Cache_ConnectionPool::returnConnection('memcached', $connection);
                    }
                }
            } else {
                return self::setToMemcached($key, $data, $ttl);
            }
            return false;
        }

        /**
         * Internal Redis delete operation with connection pooling support
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromRedisInternal( string $key ): bool {

            // see if pooling is enabled
            if ( self::$_connection_pooling_enabled ) {

                // get the connection
                $connection = Cache_ConnectionPool::getConnection( 'redis' );

                // do we have one?
                if ( $connection ) {

                    // try to delete the item
                    try {
                        return $connection -> del( $key ) > 0;

                    // whoopsie...
                    } catch ( \Exception $e ) {

                        // log the error and return false
                        LOG::error( "Redis delete error: " . $e -> getMessage( ), 'redis_operation' );
                        return false;

                    // finally...
                    } finally {

                        // setup the connection
                        Cache_ConnectionPool::returnConnection( 'redis', $connection );
                    }
                }

            // otherwise
            } else {

                // return deleting the item
                return self::deleteFromRedis( $key );
            }

            // default return
            return false;
        }

        /**
         * Internal Memcached delete operation with connection pooling support
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromMemcachedInternal( string $key ): bool {

            // see if pooling is enabled
            if ( self::$_connection_pooling_enabled ) {

                // get the connection
                $connection = Cache_ConnectionPool::getConnection( 'memcached' );

                // do we have one?
                if ( $connection ) {

                    // try to delete the item
                    try {
                        return $connection -> delete( $key );

                    // whoopsie...
                    } catch ( \Exception $e ) {

                        // log the error and return false
                        LOG::error( "Memcached delete error: " . $e -> getMessage( ), 'memcached_operation' );
                        return false;

                    // finally...
                    } finally {

                        // setup the connection
                        Cache_ConnectionPool::returnConnection( 'memcached', $connection );
                    }
                }

            // otherwise
            } else {

                // return deleting the item
                return self::deleteFromMemcached( $key );
            }

            // default return
            return false;
        }

        /**
         * Internal OPcache delete operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier_key The tier-specific key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromOPcacheInternal( string $tier_key ): bool {

            // get the cache path
            $cache_path = self::$_fallback_path ?? sys_get_temp_dir( ) . '/kpt_cache/';

            // build the temp file path
            $temp_file = $cache_path . $tier_key . '.php';
            
            // if the file exists
            if ( file_exists( $temp_file ) ) {

                // if we have the opcache invalidate function
                if ( function_exists( 'opcache_invalidate' ) ) {

                    // invalidate the opcache for the file
                    @opcache_invalidate( $temp_file, true );
                }

                // return if we can unlink the file
                return @unlink( $temp_file );
            }

            // default return
            return true;
        }

        /**
         * Internal SHMOP delete operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param int $shmop_key The SHMOP key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromShmopInternal( int $shmop_key ): bool {

            // if we don't have the function, just return true
            if ( ! function_exists( 'shmop_open' ) ) return true;
            
            // try to delete the segment
            try {

                // open the segment
                $segment = @shmop_open( $shmop_key, 'w', 0, 0 );

                // if we have a segment
                if ( $segment !== false ) {

                    // delete it
                    $result = @shmop_delete( $segment );

                    // close it
                    @shmop_close( $segment );

                    // return the result
                    return $result;
                }

            // whoopsie...
            } catch ( \Exception $e ) {

                // log the error
                LOG::error( "SHMOP delete error: " . $e -> getMessage( ), 'shmop_operation' );
            }

            // default return
            return true;
        }

        /**
         * Internal APCu delete operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier_key The tier-specific key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromAPCuInternal( string $tier_key ): bool {

            // if we don't have the function or it's not enabled, just return true
            if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled( ) ) return true;
            
            // try to delete the item
            try {

                // return deleting the item
                return apcu_delete( $tier_key );

            // whoopsie...
            } catch ( \Exception $e ) {

                // log the error
                LOG::error( "APCu delete error: " . $e -> getMessage( ), 'apcu_operation' );
            }

            // default return
            return false;
        }

        /**
         * Internal YAC delete operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier_key The tier-specific key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromYacInternal( string $tier_key ): bool {

            // if the extension isn't loaded, just return true
            if ( ! extension_loaded( 'yac' ) ) return true;
            
            // try to delete the item
            try {

                // return deleting the item
                return yac_delete( $tier_key );

            // whoopsie...
            } catch ( \Exception $e ) {

                // log the error
                LOG::error( "YAC delete error: " . $e -> getMessage( ), 'yac_operation' );
            }

            // default return
            return false;
        }

        /**
         * Internal MMAP delete operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $mmap_path The MMAP file path to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromMmapInternal( string $mmap_path ): bool {

            // try to delete the file
            try {

                // if the file exists
                if ( file_exists( $mmap_path ) ) {

                    // return if we can unlink it
                    return @unlink( $mmap_path );
                }

            // whoopsie...
            } catch ( \Exception $e ) {

                // log the error
                LOG::error( "MMAP delete error: " . $e -> getMessage( ), 'mmap_operation' );
            }

            // default return
            return true;
        }

        /**
         * Internal file cache delete operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $file_key The file key to delete
         * @return bool Returns true if successfully deleted, false otherwise
         */
        private static function deleteFromFileInternal( string $file_key ): bool {

            // build the file path
            $file = self::$_fallback_path . $file_key;

            // if the file exists
            if ( file_exists( $file ) ) {

                // return if we can unlink it
                return unlink( $file );
            }

            // default return
            return true;
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
                    LOG::debug( "Cache Promoted", [$available_tiers[$i], $key, [
                        'from_tier' => $current_tier,
                        'to_tier' => $available_tiers[$i]
                    ]] );
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
        private static function clearTier( string $tier ): bool {

            // try to clear based on the tier type
            try {

                // switch on the tier
                switch ( $tier ) {

                    // opcache
                    case self::TIER_OPCACHE:

                        // return clearing the opcache
                        return self::clearOPcache( );
                        
                    // shmop
                    case self::TIER_SHMOP:

                        // default success
                        $success = true;

                        // loop over each segment
                        foreach ( self::$_shmop_segments as $cache_key => $shmop_key ) {

                            // if we can't delete it, set success to false
                            if ( ! self::deleteFromTierInternal( $cache_key, self::TIER_SHMOP ) ) {
                                $success = false;
                            }
                        }

                        // reset the segments array and return success
                        self::$_shmop_segments = [];
                        return $success;
                        
                    // apcu
                    case self::TIER_APCU:

                        // return clearing the apcu cache
                        return function_exists( 'apcu_clear_cache' ) ? apcu_clear_cache( ) : false;
                        
                    // yac
                    case self::TIER_YAC:

                        // return flushing the yac cache
                        return extension_loaded( 'yac' ) ? yac_flush( ) : false;
                        
                    // mmap
                    case self::TIER_MMAP:

                        // default success
                        $success = true;

                        // loop over each file
                        foreach ( self::$_mmap_files as $cache_key => $filepath ) {

                            // if the file exists
                            if ( file_exists( $filepath ) ) {

                                // if we can't unlink it, set success to false
                                if ( ! @unlink( $filepath ) ) {
                                    $success = false;
                                }
                            }
                        }

                        // reset the files array and return success
                        self::$_mmap_files = [];
                        return $success;
                        
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

                                // finally...
                                } finally {

                                    // setup the connection
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

                                // finally...
                                } finally {

                                    // setup the connection
                                    Cache_ConnectionPool::returnConnection( 'memcached', $connection );
                                }
                            }

                        // otherwise
                        } else {

                            // try to flush the memcached cache directly
                            try {

                                // create a new memcached instance
                                $memcached = \Memcached( );
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

            // whoopsie...
            } catch ( \Exception $e ) {

                // log the error and return false
                LOG::error( "Error clearing tier {$tier}: " . $e -> getMessage( ), 'tier_operation', ['tier' => $tier] );
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
            LOG::info( "Cleanup completed", 'maintenance', ['expired_items_removed' => $count] );
            
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
            LOG::info( "Cache system closed", 'system' );
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
            $stats['logger'] = LOG::getStats( );
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
        public static function isHealthy(): array {
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
        public static function getSettings(): array {
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

                // set the configurable cache path
                self::$_configurable_cache_path = $path;
                
                // If we're already initialized, update the fallback path immediately
                if ( self::$_initialized ) {

                    // update the fallback path
                    self::$_fallback_path = $path;
                }
                
                // log the path update
                LOG::info( "Cache path updated", 'configuration', ['new_path' => $path] );

                // return true
                return true;
            }
            
            // log the error
            LOG::error( "Failed to set cache path", 'configuration', ['attempted_path' => $path] );

            // return false
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
        public static function getCachePath(): string {
            return self::$_fallback_path ?? sys_get_temp_dir() . '/kpt_cache/';
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
    }
}
