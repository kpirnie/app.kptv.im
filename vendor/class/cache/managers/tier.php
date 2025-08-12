<?php
/**
 * KPT Cache Tier Manager - Cache Tier Discovery and Management
 * 
 * Handles discovery, validation, and management of cache tiers including
 * availability testing, health checks, and tier status reporting.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class doesn't exist
if ( ! class_exists( 'KPT_Cache_TierManager' ) ) {

    /**
     * KPT Cache Tier Manager
     * 
     * Responsible for discovering available cache tiers, validating tier names,
     * checking tier availability and health, and providing tier status information.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class KPT_Cache_TierManager {

        // =====================================================================
        // CACHE TIER CONSTANTS
        // =====================================================================

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

        // =====================================================================
        // CLASS PROPERTIES
        // =====================================================================

        /** @var array Valid tier names for validation - ordered by priority (highest to lowest) */
        private static array $_valid_tiers = [
            self::TIER_OPCACHE, self::TIER_SHMOP, self::TIER_APCU, 
            self::TIER_YAC, self::TIER_MMAP, self::TIER_REDIS, 
            self::TIER_MEMCACHED, self::TIER_FILE
        ];

        /** @var array Available cache tiers discovered during initialization */
        private static array $_available_tiers = [];
        
        /** @var bool Discovery completion status flag */
        private static bool $_discovery_complete = false;
        
        /** @var string|null Last error message from tier operations */
        private static ?string $_last_error = null;
        
        /** @var array Cache of tier test results to avoid repeated checks */
        private static array $_tier_test_cache = [];
        
        /** @var int Cache duration for tier test results (seconds) */
        private static int $_test_cache_duration = 300; // 5 minutes

        // =====================================================================
        // TIER DISCOVERY METHODS
        // =====================================================================

        /**
         * Discover and validate available cache tiers
         * 
         * Automatically discovers which cache backends are available on the current
         * system by testing each tier's functionality. Populates the available tiers
         * array in priority order.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param bool $force_rediscovery Force rediscovery even if already completed
         * @return array Returns array of discovered available tiers
         */
        public static function discoverTiers( bool $force_rediscovery = false ): array {

            // Skip if already discovered and not forcing rediscovery
            if ( self::$_discovery_complete && ! $force_rediscovery ) {
                return self::$_available_tiers;
            }
            
            // Clear previous results
            self::$_available_tiers = [];
            self::$_last_error = null;
            
            // Test each tier in priority order
            foreach ( self::$_valid_tiers as $tier ) {
                if ( self::testTierAvailability( $tier ) ) {
                    self::$_available_tiers[] = $tier;
                }
            }
            
            // Mark discovery as complete
            self::$_discovery_complete = true;
            
            return self::$_available_tiers;
        }

        /**
         * Test availability of a specific cache tier
         * 
         * Performs availability testing for the specified tier type by checking
         * if required extensions/functions exist and basic connectivity works.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier name to test
         * @return bool Returns true if tier is available, false otherwise
         */
        public static function testTierAvailability( string $tier ): bool {

            // Validate tier name first
            if ( ! self::isTierValid( $tier ) ) {
                self::$_last_error = "Invalid tier specified: {$tier}";
                return false;
            }
            
            // Check cache first
            $cache_key = $tier . '_availability';
            if ( isset( self::$_tier_test_cache[$cache_key] ) ) {
                $cached_result = self::$_tier_test_cache[$cache_key];
                if ( time() - $cached_result['timestamp'] < self::$_test_cache_duration ) {
                    return $cached_result['available'];
                }
            }
            
            // Perform actual availability test
            $available = match( $tier ) {
                self::TIER_OPCACHE => self::testOPcacheAvailability(),
                self::TIER_SHMOP => self::testShmopAvailability(),
                self::TIER_APCU => self::testAPCuAvailability(),
                self::TIER_YAC => self::testYacAvailability(),
                self::TIER_MMAP => self::testMmapAvailability(),
                self::TIER_REDIS => self::testRedisAvailability(),
                self::TIER_MEMCACHED => self::testMemcachedAvailability(),
                self::TIER_FILE => self::testFileAvailability(),
                default => false
            };
            
            // Cache the result
            self::$_tier_test_cache[$cache_key] = [
                'available' => $available,
                'timestamp' => time()
            ];
            
            return $available;
        }

        /**
         * Force refresh of tier discovery and clear test cache
         * 
         * Clears all cached test results and forces a complete rediscovery
         * of available cache tiers.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns array of newly discovered available tiers
         */
        public static function refreshTierDiscovery(): array {
            self::$_tier_test_cache = [];
            self::$_discovery_complete = false;
            return self::discoverTiers( true );
        }

        // =====================================================================
        // TIER VALIDATION METHODS
        // =====================================================================

        /**
         * Validate if a tier name is valid
         * 
         * Checks if the provided tier name exists in the list of valid tier constants.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier name to validate
         * @return bool Returns true if the tier name is valid, false otherwise
         */
        public static function isTierValid( string $tier ): bool {
            return in_array( $tier, self::$_valid_tiers, true );
        }

        /**
         * Check if a tier is available for use
         * 
         * Determines if the specified tier was discovered during initialization
         * and is available for cache operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $tier The tier name to check availability for
         * @return bool Returns true if the tier is available, false otherwise
         */
        public static function isTierAvailable( string $tier ): bool {
            
            // Ensure discovery has been performed
            if ( ! self::$_discovery_complete ) {
                self::discoverTiers();
            }
            
            return in_array( $tier, self::$_available_tiers, true );
        }

        /**
         * Check if multiple tiers are available
         * 
         * Batch check for multiple tier availability, useful for validation
         * before performing multi-tier operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $tiers Array of tier names to check
         * @return array Returns associative array of tier => availability status
         */
        public static function aretiersAvailable( array $tiers ): array {
            $results = [];
            
            foreach ( $tiers as $tier ) {
                $results[$tier] = self::isTierAvailable( $tier );
            }
            
            return $results;
        }

        // =====================================================================
        // TIER STATUS AND INFORMATION METHODS
        // =====================================================================

        /**
         * Get list of all valid tier names
         * 
         * Returns the complete list of tier names that the cache system recognizes,
         * regardless of their availability on the current system.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns array of all valid tier names in priority order
         */
        public static function getValidTiers(): array {
            return self::$_valid_tiers;
        }

        /**
         * Get list of available (discovered) tiers
         * 
         * Returns the list of tiers that were successfully discovered and are
         * available for use on the current system.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns array of available tier names in priority order
         */
        public static function getAvailableTiers(): array {
            
            // Ensure discovery has been performed
            if ( ! self::$_discovery_complete ) {
                self::discoverTiers();
            }
            
            return self::$_available_tiers;
        }

        /**
         * Get comprehensive status information for all tiers
         * 
         * Provides detailed status information including availability, health,
         * priority, and last test time for all valid tiers.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns associative array with tier status information
         */
        public static function getTierStatus(): array {
            
            // Ensure discovery has been performed
            if ( ! self::$_discovery_complete ) {
                self::discoverTiers();
            }
            
            $status = [];
            
            foreach ( self::$_valid_tiers as $index => $tier ) {
                
                $available = self::isTierAvailable( $tier );
                $priority_index = array_search( $tier, self::$_available_tiers );
                
                $status[$tier] = [
                    'valid' => true,
                    'available' => $available,
                    'priority_order' => $index, // Order in valid tiers array
                    'availability_priority' => $priority_index !== false ? $priority_index : null,
                    'last_test_time' => self::$_tier_test_cache[$tier . '_availability']['timestamp'] ?? null,
                ];
            }
            
            return $status;
        }

        /**
         * Get tier priority information
         * 
         * Returns priority information for tiers, useful for understanding
         * the order in which tiers will be attempted.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string|null $tier Optional specific tier to get priority for
         * @return array|int Returns priority array for all tiers or specific tier priority
         */
        public static function getTierPriority( ?string $tier = null ): array|int {
            
            if ( $tier !== null ) {
                if ( ! self::isTierValid( $tier ) ) {
                    return -1;
                }
                
                $priority = array_search( $tier, self::$_available_tiers );
                return $priority !== false ? $priority : -1;
            }
            
            $priorities = [];
            foreach ( self::$_available_tiers as $index => $tier_name ) {
                $priorities[$tier_name] = $index;
            }
            
            return $priorities;
        }

        /**
         * Get the highest priority available tier
         * 
         * Returns the tier with the highest priority (lowest index) that is
         * currently available for use.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string|null Returns the highest priority tier name or null if none available
         */
        public static function getHighestPriorityTier(): ?string {
            $available = self::getAvailableTiers();
            return !empty( $available ) ? $available[0] : null;
        }

        /**
         * Get the lowest priority available tier
         * 
         * Returns the tier with the lowest priority (highest index) that is
         * currently available for use. Usually the file tier.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string|null Returns the lowest priority tier name or null if none available
         */
        public static function getLowestPriorityTier(): ?string {
            $available = self::getAvailableTiers();
            return !empty( $available ) ? end( $available ) : null;
        }

        // =====================================================================
        // TIER-SPECIFIC AVAILABILITY TESTING METHODS
        // =====================================================================

        /**
         * Test OPcache availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if OPcache is available and functional
         */
        private static function testOPcacheAvailability(): bool {
            try {
                // Check if function exists and OPcache is enabled
                if ( ! function_exists( 'opcache_get_status' ) ) {
                    return false;
                }
                
                $status = opcache_get_status( false );
                if ( ! $status || ! isset( $status['opcache_enabled'] ) ) {
                    return false;
                }
                
                return $status['opcache_enabled'] === true;
                
            } catch ( Exception $e ) {
                self::$_last_error = "OPcache test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test SHMOP availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if SHMOP is available and functional
         */
        private static function testShmopAvailability(): bool {
            try {
                if ( ! function_exists( 'shmop_open' ) ) {
                    return false;
                }
                
                // Try to create a small test segment
                $test_key = ftok( __FILE__, 't' );
                $test_size = 1024;
                
                $segment = @shmop_open( $test_key, 'c', 0644, $test_size );
                if ( $segment === false ) {
                    return false;
                }
                
                // Test write and read
                $test_data = "test";
                $written = @shmop_write( $segment, $test_data, 0 );
                
                if ( $written !== strlen( $test_data ) ) {
                    @shmop_close( $segment );
                    return false;
                }
                
                $read_data = @shmop_read( $segment, 0, strlen( $test_data ) );
                
                // Cleanup
                @shmop_delete( $segment );
                @shmop_close( $segment );
                
                return $read_data === $test_data;
                
            } catch ( Exception $e ) {
                self::$_last_error = "SHMOP test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test APCu availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if APCu is available and functional
         */
        private static function testAPCuAvailability(): bool {
            try {
                if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled() ) {
                    return false;
                }
                
                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                // Test store
                if ( ! apcu_store( $test_key, $test_value, 60 ) ) {
                    return false;
                }
                
                // Test fetch
                $retrieved = apcu_fetch( $test_key );
                
                // Cleanup
                apcu_delete( $test_key );
                
                return $retrieved === $test_value;
                
            } catch ( Exception $e ) {
                self::$_last_error = "APCu test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test YAC availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if YAC is available and functional
         */
        private static function testYacAvailability(): bool {
            try {
                if ( ! extension_loaded( 'yac' ) ) {
                    return false;
                }
                
                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                // Test store
                if ( ! yac_store( $test_key, $test_value, 60 ) ) {
                    return false;
                }
                
                // Test fetch
                $retrieved = yac_get( $test_key );
                
                // Cleanup
                yac_delete( $test_key );
                
                return $retrieved === $test_value;
                
            } catch ( Exception $e ) {
                self::$_last_error = "YAC test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test MMAP availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if MMAP is available and functional
         */
        private static function testMmapAvailability(): bool {
            try {
                // MMAP support is generally available on most systems
                // Test by trying to create a temporary file and map it
                
                $temp_file = tempnam( sys_get_temp_dir(), 'kpt_mmap_test' );
                if ( ! $temp_file ) {
                    return false;
                }
                
                // Create a small test file
                $test_data = str_repeat( 'A', 1024 );
                if ( file_put_contents( $temp_file, $test_data ) === false ) {
                    @unlink( $temp_file );
                    return false;
                }
                
                // Test file operations that MMAP will use
                $file = fopen( $temp_file, 'r+b' );
                if ( ! $file ) {
                    @unlink( $temp_file );
                    return false;
                }
                
                // Test locking (required for safe MMAP operations)
                $lock_success = flock( $file, LOCK_EX );
                if ( $lock_success ) {
                    flock( $file, LOCK_UN );
                }
                
                fclose( $file );
                unlink( $temp_file );
                
                return $lock_success;
                
            } catch ( Exception $e ) {
                self::$_last_error = "MMAP test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test Redis availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if Redis is available and functional
         */
        private static function testRedisAvailability(): bool {
            try {
                if ( ! class_exists( 'Redis' ) ) {
                    return false;
                }
                
                $redis = new Redis();
                $config = KPT_Cache_Config::get( 'redis' );
                
                // Test connection with timeout
                $connected = $redis->pconnect(
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 6379,
                    2 // 2 second timeout
                );
                
                if ( ! $connected ) {
                    return false;
                }
                
                // Test ping
                $ping_result = $redis->ping();
                if ( $ping_result !== true && $ping_result !== '+PONG' ) {
                    $redis->close();
                    return false;
                }
                
                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                $set_result = $redis->setex( $test_key, 60, $test_value );
                if ( ! $set_result ) {
                    $redis->close();
                    return false;
                }
                
                $get_result = $redis->get( $test_key );
                $redis->del( $test_key );
                $redis->close();
                
                return $get_result === $test_value;
                
            } catch ( Exception $e ) {
                self::$_last_error = "Redis test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test Memcached availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if Memcached is available and functional
         */
        private static function testMemcachedAvailability(): bool {
            try {
                if ( ! class_exists( 'Memcached' ) ) {
                    return false;
                }
                
                $memcached = new Memcached();
                $config = KPT_Cache_Config::get( 'memcached' );
                
                // Add server
                $memcached->addServer(
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 11211
                );
                
                // Test connection by getting stats
                $stats = $memcached->getStats();
                if ( empty( $stats ) ) {
                    return false;
                }
                
                // Test basic operations
                $test_key = '__kpt_cache_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                $set_result = $memcached->set( $test_key, $test_value, time() + 60 );
                if ( ! $set_result ) {
                    return false;
                }
                
                $get_result = $memcached->get( $test_key );
                $memcached->delete( $test_key );
                $memcached->quit();
                
                return $get_result === $test_value;
                
            } catch ( Exception $e ) {
                self::$_last_error = "Memcached test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Test File cache availability and basic functionality
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if file caching is available and functional
         */
        private static function testFileAvailability(): bool {
            try {
                // Get cache path from cache config or use temp directory
                $cache_path = KPT_Cache_Config::get( 'file' )['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
                
                // Ensure directory exists and is writable
                if ( ! is_dir( $cache_path ) ) {
                    if ( ! @mkdir( $cache_path, 0755, true ) ) {
                        return false;
                    }
                }
                
                if ( ! is_writable( $cache_path ) ) {
                    return false;
                }
                
                // Test file operations
                $test_file = $cache_path . 'test_' . uniqid() . '.tmp';
                $test_data = 'test_data_' . time();
                
                // Test write
                if ( file_put_contents( $test_file, $test_data ) === false ) {
                    return false;
                }
                
                // Test read
                $read_data = file_get_contents( $test_file );
                
                // Cleanup
                @unlink( $test_file );
                
                return $read_data === $test_data;
                
            } catch ( Exception $e ) {
                self::$_last_error = "File cache test failed: " . $e->getMessage();
                return false;
            }
        }

        // =====================================================================
        // ERROR HANDLING AND UTILITY METHODS
        // =====================================================================

        /**
         * Get the last error message encountered during tier operations
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string|null Returns the last error message or null if none
         */
        public static function getLastError(): ?string {
            return self::$_last_error;
        }

        /**
         * Clear the last error message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        public static function clearLastError(): void {
            self::$_last_error = null;
        }

        /**
         * Get discovery status information
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns discovery status and statistics
         */
        public static function getDiscoveryInfo(): array {
            return [
                'discovery_complete' => self::$_discovery_complete,
                'total_valid_tiers' => count( self::$_valid_tiers ),
                'total_available_tiers' => count( self::$_available_tiers ),
                'availability_ratio' => count( self::$_valid_tiers ) > 0 
                    ? round( count( self::$_available_tiers ) / count( self::$_valid_tiers ) * 100, 2 )
                    : 0,
                'cached_tests' => count( self::$_tier_test_cache ),
                'last_error' => self::$_last_error
            ];
        }

        /**
         * Reset the tier manager state
         * 
         * Clears all cached data and resets the manager to initial state.
         * Useful for testing or when configuration changes.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        public static function reset(): void {
            self::$_available_tiers = [];
            self::$_discovery_complete = false;
            self::$_last_error = null;
            self::$_tier_test_cache = [];
        }
    }
}
