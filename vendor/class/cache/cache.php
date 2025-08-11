<?php
/**
 * KPT Cache - Modern Multi-tier Caching System
 * 
 * Features:
 * - Multi-tier hierarchical caching (OPcache, shmop, APCu, Yac, mmap, Redis, Memcached, File)
 * - Connection pooling for database backends
 * - Async/Promise support for non-blocking operations
 * - Cache warming system with multiple strategies
 * - Tier-specific operations for precise cache control
 * - Modern, clean API without legacy support
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! class_exists( 'KPT_Cache' ) ) {

    class KPT_Cache {

        // Import all cache backend traits
        use KPT_Cache_APCU, KPT_Cache_File, KPT_Cache_Memcached;
        use KPT_Cache_MMAP, KPT_Cache_OPCache, KPT_Cache_Redis;
        use KPT_Cache_SHMOP, KPT_Cache_YAC;
        use KPT_Cache_Async, KPT_Cache_Redis_Async, KPT_Cache_File_Async, KPT_Cache_Memcached_Async;
        use KPT_Cache_Mixed_Async, KPT_Cache_MMAP_Async, KPT_Cache_OPCache_Async;


        // Cache tier constants - ordered by priority (highest to lowest)
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_MMAP = 'mmap';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';

        // Valid tier names for validation
        private static array $_valid_tiers = [
            self::TIER_OPCACHE, self::TIER_SHMOP, self::TIER_APCU, 
            self::TIER_YAC, self::TIER_MMAP, self::TIER_REDIS, 
            self::TIER_MEMCACHED, self::TIER_FILE
        ];

        // Core system properties
        private static ?string $_last_error = null;
        private static array $_available_tiers = [];
        private static ?string $_fallback_path = null;
        private static bool $_initialized = false;
        private static ?string $_configurable_cache_path = null;
        private static array $_shmop_segments = [];
        private static array $_mmap_files = [];
        private static ?string $_last_used_tier = null;
        
        // New feature properties
        private static ?KPT_Cache_Warmer $_warmer = null;
        private static bool $_connection_pooling_enabled = true;
        private static bool $_async_enabled = false;
        private static ?object $_event_loop = null;

        // =====================================================================
        // INITIALIZATION AND CONFIGURATION
        // =====================================================================

        /**
         * Initialize the cache system
         */
        private static function init(): void {
            if (self::$_initialized) return;
            
            // Initialize configuration
            KPT_Cache_Config::initialize();
            
            // Initialize default path if not set
            if (self::$_fallback_path === null) {
                self::$_fallback_path = sys_get_temp_dir() . '/kpt_cache/';
            }
            
            // Discover available cache tiers
            self::discoverTiers();
            
            // Initialize file fallback
            self::initFallback();
            
            // Initialize connection pools for database backends
            if (self::$_connection_pooling_enabled) {
                self::initializeConnectionPools();
            }
            
            self::$_initialized = true;
        }

        /**
         * Ensure initialization
         */
        private static function ensureInitialized(): void {
            if (!self::$_initialized) {
                self::init();
            }
        }

        /**
         * Discover available cache tiers
         */
        private static function discoverTiers(): void {
            self::$_available_tiers = [];
            
            // Check each tier in priority order
            if (function_exists('opcache_get_status') && self::testOPcacheConnection()) {
                self::$_available_tiers[] = self::TIER_OPCACHE;
            }
            
            if (function_exists('shmop_open') && self::testShmopConnection()) {
                self::$_available_tiers[] = self::TIER_SHMOP;
            }
            
            if (function_exists('apcu_enabled') && self::testAPCuConnection()) {
                self::$_available_tiers[] = self::TIER_APCU;
            }
            
            if (extension_loaded('yac') && self::testYacConnection()) {
                self::$_available_tiers[] = self::TIER_YAC;
            }
            
            if (self::testMmapConnection()) {
                self::$_available_tiers[] = self::TIER_MMAP;
            }
            
            if (class_exists('Redis') && self::testRedisConnection()) {
                self::$_available_tiers[] = self::TIER_REDIS;
            }
            
            if (class_exists('Memcached') && self::testMemcachedConnection()) {
                self::$_available_tiers[] = self::TIER_MEMCACHED;
            }
            
            // File cache is always available (last fallback)
            self::$_available_tiers[] = self::TIER_FILE;
        }

        /**
         * Initialize fallback directory with proper permissions
         */
        private static function initFallback(): void {
            // Use configurable path if set, otherwise default
            $cache_path = self::$_configurable_cache_path ?: self::$_fallback_path;
            
            // Try to create and setup the cache directory
            if (self::createCacheDirectory($cache_path)) {
                self::$_fallback_path = $cache_path;
                return;
            }
            
            // If the preferred path failed, try alternative paths
            $fallback_paths = [
                sys_get_temp_dir() . '/kpt_cache_' . getmypid() . '/',
                getcwd() . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_' . getmypid() . '/',
            ];
            
            foreach ($fallback_paths as $alt_path) {
                if (self::createCacheDirectory($alt_path)) {
                    self::$_fallback_path = $alt_path;
                    return;
                }
            }
            
            // Last resort - use system temp with unique name
            $temp_path = sys_get_temp_dir() . '/kpt_' . uniqid() . '_' . getmypid() . '/';
            if (self::createCacheDirectory($temp_path)) {
                self::$_fallback_path = $temp_path;
            } else {
                // If all else fails, disable file caching
                self::$_last_error = "Unable to create writable cache directory";
                $key = array_search(self::TIER_FILE, self::$_available_tiers);
                if ($key !== false) {
                    unset(self::$_available_tiers[$key]);
                    self::$_available_tiers = array_values(self::$_available_tiers);
                }
            }
        }

        /**
         * Initialize connection pools
         */
        private static function initializeConnectionPools(): void {
            // Configure Redis pool
            if (in_array(self::TIER_REDIS, self::$_available_tiers)) {
                KPT_Cache_ConnectionPool::configurePool('redis', [
                    'min_connections' => 2,
                    'max_connections' => 10,
                    'idle_timeout' => 300
                ]);
            }
            
            // Configure Memcached pool
            if (in_array(self::TIER_MEMCACHED, self::$_available_tiers)) {
                KPT_Cache_ConnectionPool::configurePool('memcached', [
                    'min_connections' => 1,
                    'max_connections' => 5,
                    'idle_timeout' => 300
                ]);
            }
        }

        // =====================================================================
        // NEW FEATURE CONFIGURATION
        // =====================================================================

        /**
         * Enable async support
         */
        public static function enableAsync(?object $eventLoop = null): void {
            self::$_async_enabled = true;
            self::$_event_loop = $eventLoop;
            
            if (self::$_warmer) {
                self::$_warmer->enableAsync();
            }
        }

        /**
         * Enable/disable connection pooling
         */
        public static function setConnectionPooling(bool $enabled): void {
            self::$_connection_pooling_enabled = $enabled;
            
            if (!$enabled) {
                KPT_Cache_ConnectionPool::closeAll();
            } elseif (self::$_initialized) {
                self::initializeConnectionPools();
            }
        }

        /**
         * Get cache warmer instance
         */
        public static function getWarmer(): KPT_Cache_Warmer {
            if (self::$_warmer === null) {
                self::$_warmer = new KPT_Cache_Warmer();
                
                if (self::$_async_enabled) {
                    self::$_warmer->enableAsync();
                }
            }
            
            return self::$_warmer;
        }

        /**
         * Set cache warmer from configuration
         */
        public static function setWarmerFromConfig(array $config): void {
            self::$_warmer = KPT_Cache_Warmer::fromConfig($config);
            
            if (self::$_async_enabled) {
                self::$_warmer->enableAsync();
            }
        }

        /**
         * Warm cache using configured warmers
         */
        public static function warmCache(bool $parallel = false): array {
            self::ensureInitialized();
            
            if (!self::$_warmer) {
                return ['error' => 'No warmer configured'];
            }
            
            return self::$_warmer->warmAll($parallel);
        }

        // =====================================================================
        // CORE CACHE OPERATIONS (HIERARCHICAL)
        // =====================================================================

        /**
         * Get an item from cache using tier hierarchy
         */
        public static function get(string $key): mixed {
            self::ensureInitialized();
            
            foreach (self::$_available_tiers as $tier) {
                $result = self::getFromTierInternal($key, $tier);
                
                if ($result !== false) {
                    // Promote to higher tiers for faster future access
                    self::promoteToHigherTiers($key, $result, $tier);
                    self::$_last_used_tier = $tier;
                    return $result;
                }
            }
            
            return false;
        }

        /**
         * Set an item in cache using all available tiers
         */
        public static function set(string $key, mixed $data, int $ttl = 3600): bool {
            self::ensureInitialized();
            
            if (empty($data)) {
                return false;
            }
            
            $success = false;
            $primary_tier_used = null;
            
            foreach (self::$_available_tiers as $tier) {
                if (self::setToTierInternal($key, $data, $ttl, $tier)) {
                    $success = true;
                    
                    // Track the first (highest priority) successful tier as the primary
                    if ($primary_tier_used === null) {
                        $primary_tier_used = $tier;
                    }
                }
            }
            
            // Set the last used tier to the primary (first successful) tier
            if ($primary_tier_used !== null) {
                self::$_last_used_tier = $primary_tier_used;
            }
            
            return $success;
        }

        /**
         * Delete an item from all cache tiers
         */
        public static function delete(string $key): bool {
            self::ensureInitialized();
            
            $success = true;
            
            foreach (self::$_available_tiers as $tier) {
                if (!self::deleteFromTierInternal($key, $tier)) {
                    $success = false;
                }
            }
            
            return $success;
        }

        /**
         * Clear all cache from all tiers
         */
        public static function clear(): bool {
            self::ensureInitialized();
            
            $success = true;
            
            foreach (self::$_available_tiers as $tier) {
                if (!self::clearTier($tier)) {
                    $success = false;
                }
            }
            
            return $success;
        }

        // =====================================================================
        // TIER-SPECIFIC OPERATIONS
        // =====================================================================

        /**
         * Get from specific cache tier
         */
        public static function getFromTier(string $key, string $tier): mixed {
            self::ensureInitialized();
            
            if (!self::isTierValid($tier)) {
                self::$_last_error = "Invalid tier specified: {$tier}";
                return false;
            }
            
            if (!self::isTierAvailable($tier)) {
                self::$_last_error = "Tier not available: {$tier}";
                return false;
            }
            
            $result = self::getFromTierInternal($key, $tier);
            
            if ($result !== false) {
                self::$_last_used_tier = $tier;
            }
            
            return $result;
        }

        /**
         * Set to specific cache tier only
         */
        public static function setToTier(string $key, mixed $data, int $ttl, string $tier): bool {
            self::ensureInitialized();
            
            if (!self::isTierValid($tier)) {
                self::$_last_error = "Invalid tier specified: {$tier}";
                return false;
            }
            
            if (!self::isTierAvailable($tier)) {
                self::$_last_error = "Tier not available: {$tier}";
                return false;
            }
            
            if (empty($data)) {
                return false;
            }
            
            $success = self::setToTierInternal($key, $data, $ttl, $tier);
            
            if ($success) {
                self::$_last_used_tier = $tier;
            }
            
            return $success;
        }

        /**
         * Delete from specific cache tier only
         */
        public static function deleteFromTier(string $key, string $tier): bool {
            self::ensureInitialized();
            
            if (!self::isTierValid($tier)) {
                self::$_last_error = "Invalid tier specified: {$tier}";
                return false;
            }
            
            if (!self::isTierAvailable($tier)) {
                self::$_last_error = "Tier not available: {$tier}";
                return false;
            }
            
            $success = self::deleteFromTierInternal($key, $tier);
            
            if ($success) {
                self::$_last_used_tier = $tier;
            }
            
            return $success;
        }

        /**
         * Set to multiple specific tiers only
         */
        public static function setToTiers(string $key, mixed $data, int $ttl, array $tiers): array {
            self::ensureInitialized();
            
            if (empty($data)) {
                return [];
            }
            
            $results = [];
            $success_count = 0;
            
            foreach ($tiers as $tier) {
                if (!self::isTierValid($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Invalid tier'];
                    continue;
                }
                
                if (!self::isTierAvailable($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Tier not available'];
                    continue;
                }
                
                $success = self::setToTierInternal($key, $data, $ttl, $tier);
                $results[$tier] = ['success' => $success, 'error' => $success ? null : self::$_last_error];
                
                if ($success) {
                    $success_count++;
                    if (self::$_last_used_tier === null) {
                        self::$_last_used_tier = $tier;
                    }
                }
            }
            
            $results['_summary'] = [
                'total_tiers' => count($tiers),
                'successful' => $success_count,
                'failed' => count($tiers) - $success_count
            ];
            
            return $results;
        }

        /**
         * Delete from multiple specific tiers
         */
        public static function deleteFromTiers(string $key, array $tiers): array {
            self::ensureInitialized();
            
            $results = [];
            $success_count = 0;
            
            foreach ($tiers as $tier) {
                if (!self::isTierValid($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Invalid tier'];
                    continue;
                }
                
                if (!self::isTierAvailable($tier)) {
                    $results[$tier] = ['success' => false, 'error' => 'Tier not available'];
                    continue;
                }
                
                $success = self::deleteFromTierInternal($key, $tier);
                $results[$tier] = ['success' => $success, 'error' => $success ? null : self::$_last_error];
                
                if ($success) {
                    $success_count++;
                }
            }
            
            $results['_summary'] = [
                'total_tiers' => count($tiers),
                'successful' => $success_count,
                'failed' => count($tiers) - $success_count
            ];
            
            return $results;
        }

        /**
         * Get from specific tier with fallback to default hierarchy
         */
        public static function getWithTierPreference(string $key, string $preferred_tier, bool $fallback_on_failure = true): mixed {
            self::ensureInitialized();
            
            // Try preferred tier first
            if (self::isTierValid($preferred_tier) && self::isTierAvailable($preferred_tier)) {
                $result = self::getFromTierInternal($key, $preferred_tier);
                
                if ($result !== false) {
                    self::$_last_used_tier = $preferred_tier;
                    return $result;
                }
            }
            
            // Fallback to default hierarchy if enabled and preferred tier failed
            if ($fallback_on_failure) {
                return self::get($key); // Use default hierarchy
            }
            
            return false;
        }

        // =====================================================================
        // TIER VALIDATION AND UTILITY METHODS
        // =====================================================================

        /**
         * Check if a tier name is valid
         */
        public static function isTierValid(string $tier): bool {
            return in_array($tier, self::$_valid_tiers);
        }

        /**
         * Check if a tier is available for use
         */
        public static function isTierAvailable(string $tier): bool {
            self::ensureInitialized();
            return in_array($tier, self::$_available_tiers);
        }

        /**
         * Check if specific tier is healthy
         */
        public static function isTierHealthy(string $tier): bool {
            if (!self::isTierAvailable($tier)) {
                return false;
            }
            
            return match($tier) {
                self::TIER_OPCACHE => function_exists('opcache_get_status') && self::isOPcacheEnabled(),
                self::TIER_SHMOP => function_exists('shmop_open'),
                self::TIER_APCU => function_exists('apcu_enabled') && apcu_enabled(),
                self::TIER_YAC => extension_loaded('yac'),
                self::TIER_MMAP => true, // Always healthy if available
                self::TIER_REDIS => self::isRedisHealthy(),
                self::TIER_MEMCACHED => self::isMemcachedHealthy(),
                self::TIER_FILE => is_dir(self::$_fallback_path) && is_writable(self::$_fallback_path),
                default => false
            };
        }

        /**
         * Get list of valid tier names
         */
        public static function getValidTiers(): array {
            return self::$_valid_tiers;
        }

        /**
         * Get list of available (working) tiers
         */
        public static function getAvailableTiers(): array {
            self::ensureInitialized();
            return self::$_available_tiers;
        }

        /**
         * Get tier status information
         */
        public static function getTierStatus(): array {
            self::ensureInitialized();
            
            $status = [];
            
            foreach (self::$_valid_tiers as $tier) {
                $status[$tier] = [
                    'available' => self::isTierAvailable($tier),
                    'healthy' => self::isTierHealthy($tier),
                    'priority' => array_search($tier, self::$_available_tiers)
                ];
            }
            
            return $status;
        }

        /**
         * Get the tier that was used for the last cache operation
         */
        public static function getLastUsedTier(): ?string {
            return self::$_last_used_tier;
        }

        /**
         * Get the last error message
         */
        public static function getLastError(): ?string {
            return self::$_last_error;
        }

        // =====================================================================
        // INTERNAL TIER OPERATION METHODS
        // =====================================================================

        /**
         * Internal get from tier with connection pooling support
         */
        private static function getFromTierInternal(string $key, string $tier): mixed {
            $result = false;
            
            switch ($tier) {
                case self::TIER_REDIS:
                    if (self::$_connection_pooling_enabled) {
                        $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                        if ($connection) {
                            try {
                                $config = KPT_Cache_Config::get('redis');
                                $prefixed_key = ($config['prefix'] ?? '') . $key;
                                $value = $connection->get($prefixed_key);
                                $result = $value !== false ? unserialize($value) : false;
                            } finally {
                                KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                            }
                        }
                    } else {
                        $result = self::getFromRedis($key);
                    }
                    break;
                    
                case self::TIER_MEMCACHED:
                    if (self::$_connection_pooling_enabled) {
                        $connection = KPT_Cache_ConnectionPool::getConnection('memcached');
                        if ($connection) {
                            try {
                                $config = KPT_Cache_Config::get('memcached');
                                $prefixed_key = ($config['prefix'] ?? '') . $key;
                                $result = $connection->get($prefixed_key);
                                if ($connection->getResultCode() !== Memcached::RES_SUCCESS) {
                                    $result = false;
                                }
                            } finally {
                                KPT_Cache_ConnectionPool::returnConnection('memcached', $connection);
                            }
                        }
                    } else {
                        $result = self::getFromMemcached($key);
                    }
                    break;
                    
                case self::TIER_OPCACHE:
                    $result = self::getFromOPcache($key);
                    break;
                    
                case self::TIER_SHMOP:
                    $result = self::getFromShmop($key);
                    break;
                    
                case self::TIER_APCU:
                    $result = self::getFromAPCu($key);
                    break;
                    
                case self::TIER_YAC:
                    $result = self::getFromYac($key);
                    break;
                    
                case self::TIER_MMAP:
                    $result = self::getFromMmap($key);
                    break;
                    
                case self::TIER_FILE:
                    $result = self::getFromFile($key);
                    break;
                    
                default:
                    $result = false;
                    break;
            }
            
            return $result;
        }

        /**
         * Internal set to tier with connection pooling support
         */
        private static function setToTierInternal(string $key, mixed $data, int $ttl, string $tier): bool {
            return match($tier) {
                self::TIER_REDIS => self::setToRedisInternal($key, $data, $ttl),
                self::TIER_MEMCACHED => self::setToMemcachedInternal($key, $data, $ttl),
                self::TIER_OPCACHE => self::setToOPcache($key, $data, $ttl),
                self::TIER_SHMOP => self::setToShmop($key, $data, $ttl),
                self::TIER_APCU => self::setToAPCu($key, $data, $ttl),
                self::TIER_YAC => self::setToYac($key, $data, $ttl),
                self::TIER_MMAP => self::setToMmap($key, $data, $ttl),
                self::TIER_FILE => self::setToFile($key, $data, $ttl),
                default => false
            };
        }

        /**
         * Internal delete from tier with connection pooling support
         */
        private static function deleteFromTierInternal(string $key, string $tier): bool {
            return match($tier) {
                self::TIER_REDIS => self::deleteFromRedisInternal($key),
                self::TIER_MEMCACHED => self::deleteFromMemcachedInternal($key),
                self::TIER_OPCACHE => self::deleteFromOPcacheInternal($key),
                self::TIER_SHMOP => self::deleteFromShmopInternal($key),
                self::TIER_APCU => self::deleteFromAPCuInternal($key),
                self::TIER_YAC => self::deleteFromYacInternal($key),
                self::TIER_MMAP => self::deleteFromMmapInternal($key),
                self::TIER_FILE => self::deleteFromFileInternal($key),
                default => false
            };
        }

        // =====================================================================
        // CONNECTION POOLING HELPERS FOR DATABASE TIERS
        // =====================================================================

        private static function setToRedisInternal(string $key, mixed $data, int $ttl): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                if ($connection) {
                    try {
                        $config = KPT_Cache_Config::get('redis');
                        $prefixed_key = ($config['prefix'] ?? '') . $key;
                        return $connection->setex($prefixed_key, $ttl, serialize($data));
                    } catch (Exception $e) {
                        self::$_last_error = "Redis set error: " . $e->getMessage();
                        return false;
                    } finally {
                        KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    }
                }
            } else {
                return self::setToRedis($key, $data, $ttl);
            }
            return false;
        }

        private static function setToMemcachedInternal(string $key, mixed $data, int $ttl): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = KPT_Cache_ConnectionPool::getConnection('memcached');
                if ($connection) {
                    try {
                        $config = KPT_Cache_Config::get('memcached');
                        $prefixed_key = ($config['prefix'] ?? '') . $key;
                        return $connection->set($prefixed_key, $data, time() + $ttl);
                    } catch (Exception $e) {
                        self::$_last_error = "Memcached set error: " . $e->getMessage();
                        return false;
                    } finally {
                        KPT_Cache_ConnectionPool::returnConnection('memcached', $connection);
                    }
                }
            } else {
                return self::setToMemcached($key, $data, $ttl);
            }
            return false;
        }

        private static function deleteFromRedisInternal(string $key): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                if ($connection) {
                    try {
                        $config = KPT_Cache_Config::get('redis');
                        $prefixed_key = ($config['prefix'] ?? '') . $key;
                        return $connection->del($prefixed_key) > 0;
                    } catch (Exception $e) {
                        self::$_last_error = "Redis delete error: " . $e->getMessage();
                        return false;
                    } finally {
                        KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    }
                }
            } else {
                return self::deleteFromRedis($key);
            }
            return false;
        }

        private static function deleteFromMemcachedInternal(string $key): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = KPT_Cache_ConnectionPool::getConnection('memcached');
                if ($connection) {
                    try {
                        $config = KPT_Cache_Config::get('memcached');
                        $prefixed_key = ($config['prefix'] ?? '') . $key;
                        return $connection->delete($prefixed_key);
                    } catch (Exception $e) {
                        self::$_last_error = "Memcached delete error: " . $e->getMessage();
                        return false;
                    } finally {
                        KPT_Cache_ConnectionPool::returnConnection('memcached', $connection);
                    }
                }
            } else {
                return self::deleteFromMemcached($key);
            }
            return false;
        }

        // Delete methods for other tiers
        private static function deleteFromOPcacheInternal(string $key): bool {
            $config = KPT_Cache_Config::get('opcache');
            $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';
            
            $opcache_key = $prefix . md5($key);
            $cache_path = self::$_fallback_path ?? sys_get_temp_dir() . '/kpt_cache/';
            $temp_file = $cache_path . $opcache_key . '.php';
            
            if (file_exists($temp_file)) {
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($temp_file, true);
                }
                return @unlink($temp_file);
            }
            return true;
        }

        private static function deleteFromShmopInternal(string $key): bool {
            if (!function_exists('shmop_open')) return true;
            
            try {
                $shmop_key = self::generateShmopKey($key);
                $segment = @shmop_open($shmop_key, 'w', 0, 0);
                if ($segment !== false) {
                    $result = @shmop_delete($segment);
                    @shmop_close($segment);
                    unset(self::$_shmop_segments[$key]);
                    return $result;
                }
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
            }
            return true;
        }

        private static function deleteFromAPCuInternal(string $key): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) return true;
            
            try {
                $config = KPT_Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? '') . $key;
                return apcu_delete($prefixed_key);
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
            }
            return false;
        }

        private static function deleteFromYacInternal(string $key): bool {
            if (!extension_loaded('yac')) return true;
            
            try {
                $config = KPT_Cache_Config::get('yac');
                $prefixed_key = ($config['prefix'] ?? '') . $key;
                return yac_delete($prefixed_key);
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
            }
            return false;
        }

        private static function deleteFromMmapInternal(string $key): bool {
            try {
                $filename = self::generateMmapKey($key);
                $filepath = self::getMmapBasePath() . $filename;
                if (file_exists($filepath)) {
                    $success = @unlink($filepath);
                    unset(self::$_mmap_files[$key]);
                    return $success;
                }
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
            }
            return true;
        }

        private static function deleteFromFileInternal(string $key): bool {
            $file = self::$_fallback_path . md5($key);
            if (file_exists($file)) {
                return unlink($file);
            }
            return true;
        }

        // =====================================================================
        // HEALTH CHECKS FOR DATABASE TIERS
        // =====================================================================

        private static function isRedisHealthy(): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                if ($connection) {
                    try {
                        $result = $connection->ping();
                        return $result === true || $result === '+PONG';
                    } finally {
                        KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    }
                }
            } else {
                try {
                    $redis = new Redis();
                    $config = KPT_Cache_Config::get('redis');
                    
                    $connected = $redis->pconnect($config['host'], $config['port'], 2);
                    if (!$connected) return false;
                    
                    $result = $redis->ping();
                    $redis->close();
                    
                    return $result === true || $result === '+PONG';
                } catch (Exception $e) {
                    return false;
                }
            }
            return false;
        }

        private static function isMemcachedHealthy(): bool {
            if (self::$_connection_pooling_enabled) {
                $connection = KPT_Cache_ConnectionPool::getConnection('memcached');
                if ($connection) {
                    try {
                        $stats = $connection->getStats();
                        return !empty($stats);
                    } finally {
                        KPT_Cache_ConnectionPool::returnConnection('memcached', $connection);
                    }
                }
            } else {
                try {
                    $memcached = new Memcached();
                    $config = KPT_Cache_Config::get('memcached');
                    
                    $memcached->addServer($config['host'], $config['port']);
                    $stats = $memcached->getStats();
                    $memcached->quit();
                    
                    return !empty($stats);
                } catch (Exception $e) {
                    return false;
                }
            }
            return false;
        }

        // =====================================================================
        // TIER PROMOTION HELPER
        // =====================================================================

        /**
         * Promote cache item to higher tiers for faster future access
         */
        private static function promoteToHigherTiers(string $key, mixed $data, string $current_tier): void {
            $current_index = array_search($current_tier, self::$_available_tiers);
            
            // Promote to all higher tiers (lower index = higher priority)
            for ($i = 0; $i < $current_index; $i++) {
                self::setToTierInternal($key, $data, 3600, self::$_available_tiers[$i]); // Default 1 hour TTL for promotion
            }
        }

        // =====================================================================
        // TIER CLEARING
        // =====================================================================

        /**
         * Clear specific tier
         */
        private static function clearTier(string $tier): bool {
            switch ($tier) {
                case self::TIER_OPCACHE:
                    return self::clearOPcache();
                    
                case self::TIER_SHMOP:
                    $success = true;
                    foreach (self::$_shmop_segments as $cache_key => $shmop_key) {
                        if (!self::deleteFromTierInternal($cache_key, self::TIER_SHMOP)) {
                            $success = false;
                        }
                    }
                    self::$_shmop_segments = [];
                    return $success;
                    
                case self::TIER_APCU:
                    return function_exists('apcu_clear_cache') ? apcu_clear_cache() : false;
                    
                case self::TIER_YAC:
                    return extension_loaded('yac') ? yac_flush() : false;
                    
                case self::TIER_MMAP:
                    $success = true;
                    foreach (self::$_mmap_files as $cache_key => $filepath) {
                        if (file_exists($filepath)) {
                            if (!@unlink($filepath)) {
                                $success = false;
                            }
                        }
                    }
                    self::$_mmap_files = [];
                    return $success;
                    
                case self::TIER_REDIS:
                    if (self::$_connection_pooling_enabled) {
                        $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                        if ($connection) {
                            try {
                                return $connection->flushDB();
                            } finally {
                                KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                            }
                        }
                    } else {
                        try {
                            $redis = new Redis();
                            $config = KPT_Cache_Config::get('redis');
                            $redis->pconnect($config['host'], $config['port']);
                            $redis->select($config['database']);
                            return $redis->flushDB();
                        } catch (Exception $e) {
                            self::$_last_error = $e->getMessage();
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_MEMCACHED:
                    if (self::$_connection_pooling_enabled) {
                        $connection = KPT_Cache_ConnectionPool::getConnection('memcached');
                        if ($connection) {
                            try {
                                return $connection->flush();
                            } finally {
                                KPT_Cache_ConnectionPool::returnConnection('memcached', $connection);
                            }
                        }
                    } else {
                        try {
                            $memcached = new Memcached();
                            $config = KPT_Cache_Config::get('memcached');
                            $memcached->addServer($config['host'], $config['port']);
                            return $memcached->flush();
                        } catch (Exception $e) {
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_FILE:
                    $files = glob(self::$_fallback_path . '*');
                    $success = true;
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $success = $success && unlink($file);
                        }
                    }
                    return $success;
                    
                default:
                    return false;
            }
        }

        // =====================================================================
        // MAINTENANCE AND CLEANUP
        // =====================================================================

        /**
         * Remove expired cache files, shmop segments, and mmap files
         */
        public static function cleanupExpired(): int {
            $count = 0;
            
            // Clean up OPCache files
            if (in_array(self::TIER_OPCACHE, self::$_available_tiers)) {
                $count += self::cleanupOPcacheFiles();
            }
            
            // Clean up file cache
            $files = glob(self::getCachePath() . '*');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $expires = substr($content, 0, 10);
                        
                        if (is_numeric($expires) && time() > (int)$expires) {
                            if (unlink($file)) {
                                $count++;
                            }
                        }
                    }
                }
            }
            
            // Clean up expired shmop segments
            foreach (self::$_shmop_segments as $cache_key => $shmop_key) {
                try {
                    $segment = @shmop_open($shmop_key, 'a', 0, 0);
                    if ($segment !== false) {
                        $size = shmop_size($segment);
                        if ($size > 0) {
                            $data = shmop_read($segment, 0, $size);
                            $unserialized = @unserialize(trim($data, "\0"));
                            
                            if (is_array($unserialized) && isset($unserialized['expires'])) {
                                if ($unserialized['expires'] <= time()) {
                                    @shmop_delete($segment);
                                    unset(self::$_shmop_segments[$cache_key]);
                                    $count++;
                                }
                            }
                        }
                        @shmop_close($segment);
                    }
                } catch (Exception $e) {
                    unset(self::$_shmop_segments[$cache_key]);
                }
            }
            
            // Clean up expired mmap files
            foreach (self::$_mmap_files as $cache_key => $filepath) {
                try {
                    if (file_exists($filepath)) {
                        $file = fopen($filepath, 'rb');
                        if ($file !== false) {
                            if (flock($file, LOCK_SH)) {
                                $data = fread($file, filesize($filepath));
                                flock($file, LOCK_UN);
                                fclose($file);
                                
                                $unserialized = @unserialize(trim($data, "\0"));
                                
                                if (is_array($unserialized) && isset($unserialized['expires'])) {
                                    if ($unserialized['expires'] <= time()) {
                                        if (@unlink($filepath)) {
                                            unset(self::$_mmap_files[$cache_key]);
                                            $count++;
                                        }
                                    }
                                }
                            } else {
                                fclose($file);
                            }
                        }
                    } else {
                        unset(self::$_mmap_files[$cache_key]);
                    }
                } catch (Exception $e) {
                    unset(self::$_mmap_files[$cache_key]);
                }
            }
            
            return $count;
        }

        /**
         * Cleanup method - clean up connections and expired cache
         */
        public static function cleanup(): int {
            $count = self::cleanupExpired();
            
            if (self::$_connection_pooling_enabled) {
                KPT_Cache_ConnectionPool::cleanup();
            }
            
            return $count;
        }

        /**
         * Close all connections and clean up resources
         */
        public static function close(): void {
            if (self::$_connection_pooling_enabled) {
                KPT_Cache_ConnectionPool::closeAll();
            }
            
            // Clean up tracking arrays
            self::$_shmop_segments = [];
            self::$_mmap_files = [];
        }

        // =====================================================================
        // STATISTICS AND MONITORING
        // =====================================================================

        /**
         * Get enhanced statistics including pool stats
         */
        public static function getStats(): array {
            self::ensureInitialized();
            
            $stats = [];
            
            // Original cache tier stats
            if (function_exists('opcache_get_status')) {
                $stats[self::TIER_OPCACHE] = self::getOPcacheStats();
            }
            
            $stats[self::TIER_SHMOP] = [
                'segments_tracked' => count(self::$_shmop_segments)
            ];
            
            if (function_exists('apcu_cache_info')) {
                $stats[self::TIER_APCU] = apcu_cache_info();
            }
            
            if (extension_loaded('yac') && function_exists('yac_info')) {
                $stats[self::TIER_YAC] = yac_info();
            } else if (extension_loaded('yac')) {
                $stats[self::TIER_YAC] = ['extension_loaded' => true];
            }
            
            $mmap_files = glob(self::getMmapBasePath() . '*.mmap');
            $stats[self::TIER_MMAP] = [
                'files_tracked' => count(self::$_mmap_files),
                'files_on_disk' => count($mmap_files),
                'total_size' => array_sum(array_map('filesize', $mmap_files))
            ];
            
            // Add connection pool stats
            if (self::$_connection_pooling_enabled) {
                $stats['connection_pools'] = KPT_Cache_ConnectionPool::getPoolStats();
            }
            
            // Add warmer stats if available
            if (self::$_warmer) {
                $stats['warmer'] = self::$_warmer->getStats();
            }
            
            $files = glob(self::$_fallback_path . '*');
            $stats[self::TIER_FILE] = [
                'file_count' => count($files),
                'total_size' => array_sum(array_map('filesize', $files)),
                'path' => self::$_fallback_path
            ];
            
            return $stats;
        }

        /**
         * Check if cache system is healthy
         */
        public static function isHealthy(): array {
            self::ensureInitialized();
            
            $health = [];
            
            foreach (self::$_valid_tiers as $tier) {
                $health[$tier] = self::isTierHealthy($tier);
            }
            
            return $health;
        }

        // =====================================================================
        // CONFIGURATION AND SETTINGS
        // =====================================================================

        /**
         * Get current settings for all cache tiers
         */
        public static function getSettings(): array {
            return KPT_Cache_Config::getAll();
        }

        /**
         * Set a custom cache path for file-based caching
         */
        public static function setCachePath(string $path): bool {
            // Normalize the path (ensure it ends with a slash)
            $path = rtrim($path, '/') . '/';
            
            // Try to create the cache directory with proper permissions
            if (self::createCacheDirectory($path)) {
                self::$_configurable_cache_path = $path;
                
                // If we're already initialized, update the fallback path immediately
                if (self::$_initialized) {
                    self::$_fallback_path = $path;
                }
                
                return true;
            }
            
            return false;
        }

        /**
         * Get the current cache path being used
         */
        public static function getCachePath(): string {
            return self::$_fallback_path ?? sys_get_temp_dir() . '/kpt_cache/';
        }

        // =====================================================================
        // DEBUG AND TROUBLESHOOTING
        // =====================================================================

        /**
         * Debug method to see what's happening with cache tiers
         */
        public static function debug(): array {
            self::ensureInitialized();
            
            $debug_info = [
                'available_tiers' => self::$_available_tiers,
                'health_check' => self::isHealthy(),
                'last_error' => self::$_last_error,
                'last_used_tier' => self::$_last_used_tier,
                'connection_pooling_enabled' => self::$_connection_pooling_enabled,
                'async_enabled' => self::$_async_enabled,
                'warmer_configured' => self::$_warmer !== null,
                'cache_path' => self::$_fallback_path,
                'system_info' => [
                    'temp_dir' => sys_get_temp_dir(),
                    'current_user' => get_current_user(),
                    'process_id' => getmypid(),
                    'umask' => sprintf('%04o', umask()),
                ]
            ];
            
            return $debug_info;
        }
    }
}
