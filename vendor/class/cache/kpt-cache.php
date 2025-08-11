<?php
/**
 * Cache
 * 
 * Multi-tier caching class with OPcache, shmop, APCu, Yac, mmap, Redis, Memcached, and File fallbacks
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

        // Cache tier constants - ordered by priority (highest to lowest)
        const TIER_OPCACHE = 'opcache';
        const TIER_SHMOP = 'shmop';
        const TIER_APCU = 'apcu';
        const TIER_YAC = 'yac';
        const TIER_MMAP = 'mmap';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';

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

        // initial Memcached settings
        private static $_memcached_settings = [
            'host' => 'localhost',
            'port' => 11211,
            'prefix' => 'KPTV_APP:',
            'persistent' => true,
            'retry_attempts' => 2,
            'retry_delay' => 100,
        ];

        // APCu settings
        private static $_apcu_settings = [
            'prefix' => 'KPTV_APP:',
            'ttl_default' => 3600,
        ];

        // Yac settings
        private static $_yac_settings = [
            'prefix' => 'KPTV_APP:',
            'ttl_default' => 3600,
        ];

        // mmap settings
        private static $_mmap_settings = [
            'prefix' => 'KPTV_APP:',
            'base_path' => null, // Will use temp dir if null
            'file_size' => 1048576, // 1MB default file size
            'max_files' => 1000, // Maximum number of mmap files
        ];

        // shmop settings
        private static $_shmop_settings = [
            'prefix' => 'KPTV_APP:',
            'segment_size' => 1048576, // 1MB default segment size
            'base_key' => 0x12345000, // Base key for shared memory segments
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
        private static $_shmop_segments = []; // Track shmop segments
        private static $_mmap_files = []; // Track mmap files
        private static $_last_used_tier = null; // Track last tier used for operations

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
         * testShmopConnection
         * 
         * Test if shmop shared memory operations are working
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if shmop is available and working
         */
        private static function testShmopConnection( ) : bool {
            
            // try to use shmop functionality
            try {

                // Generate a test key
                $test_key = self::$_shmop_settings['base_key'] + 1;
                $test_data = 'test_' . time( );
                $serialized_data = serialize( ['expires' => time( ) + 60, 'data' => $test_data] );
                $data_size = strlen( $serialized_data );
                
                // Try to create a shared memory segment
                $segment = @shmop_open( $test_key, 'c', 0644, max( $data_size, 1024 ) );
                
                if ( $segment === false ) {
                    return false;
                }
                
                // Test write operation
                $written = @shmop_write( $segment, str_pad( $serialized_data, 1024, "\0" ), 0 );
                
                if ( $written === false ) {
                    @shmop_close( $segment );
                    @shmop_delete( $segment );
                    return false;
                }
                
                // Test read operation
                $read_data = @shmop_read( $segment, 0, 1024 );
                
                // Clean up
                @shmop_close( $segment );
                @shmop_delete( $segment );
                
                if ( $read_data === false ) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize( trim( $read_data, "\0" ) );
                return is_array( $unserialized ) && isset( $unserialized['data'] ) && $unserialized['data'] === $test_data;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "shmop test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * testAPCuConnection
         * 
         * Test if APCu is actually working
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if APCu is available and working
         */
        private static function testAPCuConnection( ) : bool {
            
            // try to use APCu functionality
            try {

                // Check if APCu is enabled
                if ( ! apcu_enabled( ) ) {
                    return false;
                }

                // Test with a simple store/fetch operation
                $test_key = 'kpt_test_' . uniqid( );
                $test_value = 'test_value_' . time( );
                
                // Try to store and retrieve
                if ( apcu_store( $test_key, $test_value, 60 ) ) {
                    $retrieved = apcu_fetch( $test_key );
                    apcu_delete( $test_key ); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "APCu test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * testYacConnection
         * 
         * Test if Yac cache is actually working
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if Yac is available and working
         */
        private static function testYacConnection( ) : bool {
            
            // try to use Yac functionality
            try {

                // Test with a simple store/fetch operation
                $test_key = self::$_yac_settings['prefix'] . 'test_' . uniqid( );
                $test_value = 'test_value_' . time( );
                
                // Try to store and retrieve
                if ( yac_add( $test_key, $test_value, 60 ) ) {
                    $retrieved = yac_get( $test_key );
                    yac_delete( $test_key ); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "Yac test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * testMmapConnection
         * 
         * Test if memory-mapped file operations are working
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if mmap is available and working
         */
        private static function testMmapConnection( ) : bool {
            
            // try to use mmap functionality
            try {

                // Get mmap base path
                $base_path = self::getMmapBasePath( );
                
                // Test file path
                $test_file = $base_path . 'test_' . uniqid( ) . '.mmap';
                $test_data = 'test_' . time( );
                $serialized_data = serialize( ['expires' => time( ) + 60, 'data' => $test_data] );
                
                // Try to create and write to memory-mapped file
                $file = fopen( $test_file, 'c+b' );
                if ( $file === false ) {
                    return false;
                }
                
                // Lock file for exclusive access
                if ( ! flock( $file, LOCK_EX ) ) {
                    fclose( $file );
                    return false;
                }
                
                // Write data
                fwrite( $file, str_pad( $serialized_data, 1024, "\0" ) );
                
                // Read back
                fseek( $file, 0 );
                $read_data = fread( $file, 1024 );
                
                // Release lock and close
                flock( $file, LOCK_UN );
                fclose( $file );
                
                // Clean up test file
                @unlink( $test_file );
                
                if ( $read_data === false ) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize( trim( $read_data, "\0" ) );
                return is_array( $unserialized ) && isset( $unserialized['data'] ) && $unserialized['data'] === $test_data;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "mmap test failed: " . $e -> getMessage( );
                return false;
            }

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
         * getMmapBasePath
         * 
         * Get the base path for mmap files
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns the mmap base path
         */
        private static function getMmapBasePath( ) : string {

            // Use configured path or default to temp directory
            $base_path = self::$_mmap_settings['base_path'] ?: sys_get_temp_dir( ) . '/kpt_mmap/';
            
            // Ensure path ends with slash
            $base_path = rtrim( $base_path, '/' ) . '/';
            
            // Create directory if it doesn't exist
            if ( ! file_exists( $base_path ) ) {
                @mkdir( $base_path, 0755, true );
            }
            
            return $base_path;

        }

        /**
         * generateShmopKey
         * 
         * Generate a unique shmop key for a cache key
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key
         * @return int Returns the shmop key
         */
        private static function generateShmopKey( string $_key ) : int {

            // Create a hash of the key and convert to integer
            $hash = crc32( self::$_shmop_settings['prefix'] . $_key );
            
            // Ensure it's positive and within a reasonable range
            $key = self::$_shmop_settings['base_key'] + abs( $hash % 100000 );
            
            return $key;

        }

        /**
         * generateMmapKey
         * 
         * Generate a unique mmap filename for a cache key
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key
         * @return string Returns the mmap filename
         */
        private static function generateMmapKey( string $_key ) : string {

            // Create a hash of the key for filename
            $hash = md5( self::$_mmap_settings['prefix'] . $_key );
            
            return $hash . '.mmap';

        }

        /**
         * initFallback
         * 
         * Initialize the fallback directory with proper permissions
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
         * createCacheDirectory
         * 
         * Attempt to create a cache directory with proper permissions
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $path The directory path to create
         * @return bool Returns true if directory was created and is writable
         */
        private static function createCacheDirectory( string $path ): bool {
            
            // set the initial attempts
            $attempts = 0;
            $max_attempts = 3;

            // Normalize the path (ensure it ends with a slash)
            $path = rtrim( $path, '/' ) . '/';

            // while we haven't tried more than max attempts
            while ( $attempts < $max_attempts ) {
                
                try {
                    // Check if directory already exists and is writable
                    if ( file_exists( $path ) ) {
                        if ( is_dir( $path ) && is_writable( $path ) ) {
                            return true;
                        } elseif ( is_dir( $path ) ) {
                            // Try to fix permissions
                            if ( @chmod( $path, 0755 ) && is_writable( $path ) ) {
                                return true;
                            }
                        }
                        $attempts++;
                        continue;
                    }
                    
                    // Try to create the directory
                    if ( @mkdir( $path, 0755, true ) ) {
                        // Ensure it's writable
                        if ( is_writable( $path ) ) {
                            return true;
                        }
                        
                        // Try to fix permissions
                        if ( @chmod( $path, 0755 ) && is_writable( $path ) ) {
                            return true;
                        }
                        
                        // Try more permissive permissions
                        if ( @chmod( $path, 0777 ) && is_writable( $path ) ) {
                            return true;
                        }
                    }
                    
                } catch ( Exception $e ) {
                    self::$_last_error = "Cache directory creation failed: " . $e->getMessage( );
                }
                
                $attempts++;
                
                // Small delay between attempts
                if ( $attempts < $max_attempts ) {
                    usleep( 100000 ); // 100ms
                }
            }
            
            return false;
        }

        /**
         * setRedisSettings
         * 
         * Configure Redis connection settings
         * 
         * @since 8.5
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
         * setMemcachedSettings
         * 
         * Configure Memcached connection settings
         * 
         * @since 8.5
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
         * setAPCuSettings
         * 
         * Configure APCu settings
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings APCu configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setAPCuSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_apcu_settings = array_merge( self::$_apcu_settings, $settings );
            
            return true;
        }

        /**
         * setYacSettings
         * 
         * Configure Yac settings
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings Yac configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setYacSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_yac_settings = array_merge( self::$_yac_settings, $settings );
            
            return true;
        }

        /**
         * setMmapSettings
         * 
         * Configure mmap settings
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings mmap configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setMmapSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_mmap_settings = array_merge( self::$_mmap_settings, $settings );
            
            return true;
        }

        /**
         * setShmopSettings
         * 
         * Configure shmop shared memory settings
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings shmop configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setShmopSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_shmop_settings = array_merge( self::$_shmop_settings, $settings );
            
            return true;
        }

        /**
         * getSettings
         * 
         * Get current settings for all cache tiers
         * 
         * @since 8.5
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
            
            // Try to create the cache directory with proper permissions
            if ( self::createCacheDirectory( $_path ) ) {
                self::$_configurable_cache_path = $_path;
                
                // If we're already initialized, update the fallback path immediately
                if ( self::$_initialized ) {
                    self::$_fallback_path = $_path;
                }
                
                return true;
            }
            
            return false;

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

            // return the cache path
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
            
            // return which tiers of cache are available
            return self::$_available_tiers;

        }

        /**
         * getLastUsedTier
         * 
         * Get the tier that was used for the last cache operation
         * 
         * @since 8.5
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
         * @since 8.5
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
         * @since 8.5
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
         * @since 8.5
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
         * @since 8.5
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
         * getFromShmop
         * 
         * Get item from shmop shared memory
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromShmop( string $_key ): mixed {

            // if shmop functions don't exist, just return false
            if ( ! function_exists( 'shmop_open' ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // generate the shmop key
                $shmop_key = self::generateShmopKey( $_key );
                
                // try to open the shared memory segment
                $segment = @shmop_open( $shmop_key, 'a', 0, 0 );
                
                if ( $segment === false ) {
                    return false;
                }
                
                // get the size of the segment
                $size = shmop_size( $segment );
                
                if ( $size === 0 ) {
                    shmop_close( $segment );
                    return false;
                }
                
                // read the data
                $data = shmop_read( $segment, 0, $size );
                shmop_close( $segment );
                
                if ( $data === false ) {
                    return false;
                }
                
                // unserialize and check expiration
                $unserialized = @unserialize( trim( $data, "\0" ) );
                
                if ( is_array( $unserialized ) && isset( $unserialized['expires'], $unserialized['data'] ) ) {
                    
                    // check if expired
                    if ( $unserialized['expires'] > time( ) ) {
                        return $unserialized['data'];
                    } else {
                        // expired, delete the segment
                        self::delFromTier( $_key, self::TIER_SHMOP );
                    }
                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "shmop get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * getFromAPCu
         * 
         * Get item from APCu
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromAPCu( string $_key ): mixed {

            // if APCu is not enabled, just return false
            if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled( ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // setup the prefixed key
                $prefixed_key = self::$_apcu_settings['prefix'] . $_key;
                
                // fetch the value
                $success = false;
                $value = apcu_fetch( $prefixed_key, $success );
                
                // if successful, return the value
                if ( $success ) {
                    return $value;
                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "APCu get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * getFromYac
         * 
         * Get item from Yac cache
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromYac( string $_key ): mixed {

            // if Yac is not loaded, just return false
            if ( ! extension_loaded( 'yac' ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // setup the prefixed key
                $prefixed_key = self::$_yac_settings['prefix'] . $_key;
                
                // fetch the value
                $value = yac_get( $prefixed_key );
                
                // Yac returns false for non-existent keys
                return $value !== false ? $value : false;

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "Yac get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * getFromMmap
         * 
         * Get item from memory-mapped file
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromMmap( string $_key ): mixed {

            // try to retrieve the data
            try {
            
                // generate the mmap filename
                $filename = self::generateMmapKey( $_key );
                $filepath = self::getMmapBasePath( ) . $filename;
                
                // if the file doesn't exist, return false
                if ( ! file_exists( $filepath ) ) {
                    return false;
                }
                
                // open the file for reading
                $file = fopen( $filepath, 'rb' );
                if ( $file === false ) {
                    return false;
                }
                
                // acquire shared lock for reading
                if ( ! flock( $file, LOCK_SH ) ) {
                    fclose( $file );
                    return false;
                }
                
                // get file size and read data
                $size = filesize( $filepath );
                if ( $size > 0 ) {
                    $data = fread( $file, $size );
                } else {
                    $data = false;
                }
                
                // release lock and close file
                flock( $file, LOCK_UN );
                fclose( $file );
                
                if ( $data === false ) {
                    return false;
                }
                
                // unserialize and check expiration
                $unserialized = @unserialize( trim( $data, "\0" ) );
                
                if ( is_array( $unserialized ) && isset( $unserialized['expires'], $unserialized['data'] ) ) {
                    
                    // check if expired
                    if ( $unserialized['expires'] > time( ) ) {
                        return $unserialized['data'];
                    } else {
                        // expired, delete the file
                        @unlink( $filepath );
                        unset( self::$_mmap_files[$_key] );
                    }
                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "mmap get error: " . $e->getMessage( );
            }
            
            // return false
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

            // setup the cache file
            $file = self::$_fallback_path . md5( $_key );
            
            // if it exists
            if ( file_exists( $file ) ) {

                // get the data from the file's contents
                $data = file_get_contents( $file );

                // setup it's expiry
                $expires = substr( $data, 0, 10 );
                
                // is it supposed to expire
                if ( time( ) > $expires ) {

                    // delete it and return false
                    unlink( $file );
                    return false;
                }
                
                // return the unserialized data
                return unserialize( substr( $data, 10 ) );
            }
            
            // default return
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
         * setToShmop
         * 
         * Set item to shmop shared memory
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
        private static function setToShmop( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! function_exists( 'shmop_open' ) ) {
                return false;
            }
            
            try {
                // Generate the shmop key
                $shmop_key = self::generateShmopKey( $_key );
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time( ) + $_length,
                    'data' => $_data
                ];
                
                $serialized_data = serialize( $cache_data );
                $data_size = strlen( $serialized_data );
                
                // Use configured segment size or data size, whichever is larger
                $segment_size = max( $data_size + 100, self::$_shmop_settings['segment_size'] );
                
                // Try to open existing segment first
                $segment = @shmop_open( $shmop_key, 'w', 0, 0 );
                
                // If doesn't exist, create new segment
                if ( $segment === false ) {
                    $segment = @shmop_open( $shmop_key, 'c', 0644, $segment_size );
                }
                
                if ( $segment === false ) {
                    return false;
                }
                
                // Pad data to prevent issues with reading
                $padded_data = str_pad( $serialized_data, $segment_size, "\0" );
                
                // Write data
                $written = @shmop_write( $segment, $padded_data, 0 );
                @shmop_close( $segment );
                
                if ( $written !== false ) {
                    // Keep track of this segment for cleanup
                    self::$_shmop_segments[$_key] = $shmop_key;
                    return true;
                }
                
            } catch ( Exception $e ) {
                self::$_last_error = "shmop set error: " . $e->getMessage( );
            }
            
            return false;
        }

        /**
         * setToAPCu
         * 
         * Set item to APCu
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
        private static function setToAPCu( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! function_exists( 'apcu_enabled' ) || ! apcu_enabled( ) ) {
                return false;
            }
            
            try {
                $prefixed_key = self::$_apcu_settings['prefix'] . $_key;
                return apcu_store( $prefixed_key, $_data, $_length );
            } catch ( Exception $e ) {
                self::$_last_error = "APCu set error: " . $e->getMessage( );
                return false;
            }
        }

        /**
         * setToYac
         * 
         * Set item to Yac
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
        private static function setToYac( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! extension_loaded( 'yac' ) ) {
                return false;
            }
            
            try {
                $prefixed_key = self::$_yac_settings['prefix'] . $_key;
                return yac_set( $prefixed_key, $_data, $_length );
            } catch ( Exception $e ) {
                self::$_last_error = "Yac set error: " . $e->getMessage( );
                return false;
            }
        }

        /**
         * setToMmap
         * 
         * Set item to memory-mapped file
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
        private static function setToMmap( string $_key, mixed $_data, int $_length ): bool {
            
            try {
                // Generate the mmap filename
                $filename = self::generateMmapKey( $_key );
                $filepath = self::getMmapBasePath( ) . $filename;
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time( ) + $_length,
                    'data' => $_data
                ];
                
                $serialized_data = serialize( $cache_data );
                $data_size = strlen( $serialized_data );
                
                // Use configured file size or data size, whichever is larger
                $file_size = max( $data_size + 100, self::$_mmap_settings['file_size'] );
                
                // Open file for writing (create if doesn't exist)
                $file = fopen( $filepath, 'c+b' );
                if ( $file === false ) {
                    return false;
                }
                
                // Acquire exclusive lock
                if ( ! flock( $file, LOCK_EX ) ) {
                    fclose( $file );
                    return false;
                }
                
                // Truncate and write data
                ftruncate( $file, $file_size );
                fseek( $file, 0 );
                $padded_data = str_pad( $serialized_data, $file_size, "\0" );
                $written = fwrite( $file, $padded_data );
                
                // Release lock and close
                flock( $file, LOCK_UN );
                fclose( $file );
                
                if ( $written !== false ) {
                    // Keep track of this file for cleanup
                    self::$_mmap_files[$_key] = $filepath;
                    return true;
                }
                
            } catch ( Exception $e ) {
                self::$_last_error = "mmap set error: " . $e->getMessage( );
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
         * @since 8.5
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
            
            // Clean up tracking arrays
            self::$_shmop_segments = [];
            self::$_mmap_files = [];
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
         * @since 8.5
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

        /**
         * getCachePathInfo
         * 
         * Get detailed information about the cache path and permissions
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns cache path information
         */
        public static function getCachePathInfo( ): array {
            $path = self::$_fallback_path;
            
            $info = [
                'path' => $path,
                'exists' => false,
                'is_dir' => false,
                'is_writable' => false,
                'is_readable' => false,
                'permissions' => null,
                'owner' => null,
                'parent_writable' => false,
            ];
            
            if ( $path ) {
                $info['exists'] = file_exists( $path );
                $info['is_dir'] = is_dir( $path );
                $info['is_writable'] = is_writable( $path );
                $info['is_readable'] = is_readable( $path );
                
                if ( $info['exists'] ) {
                    $info['permissions'] = substr( sprintf( '%o', fileperms( $path ) ), -4 );
                    if ( function_exists( 'posix_getpwuid' ) && function_exists( 'fileowner' ) ) {
                        $owner_info = posix_getpwuid( fileowner( $path ) );
                        $info['owner'] = $owner_info ? $owner_info['name'] : fileowner( $path );
                    }
                }
                
                // Check if parent directory is writable
                $parent = dirname( rtrim( $path, '/' ) );
                $info['parent_writable'] = is_writable( $parent );
                $info['parent_path'] = $parent;
            }
            
            return $info;
        }

        /**
         * fixCachePermissions
         * 
         * Attempt to fix cache directory permissions
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if permissions were fixed
         */
        public static function fixCachePermissions( ): bool {
            
            self::ensureInitialized( );
            
            $path = self::$_fallback_path;
            
            if ( ! $path || ! file_exists( $path ) ) {
                return false;
            }
            
            try {
                // Try different permission levels
                $permission_levels = [ 0755, 0775, 0777 ];
                
                foreach ( $permission_levels as $perms ) {
                    if ( @chmod( $path, $perms ) ) {
                        if ( is_writable( $path ) ) {
                            return true;
                        }
                    }
                }
                
                // If chmod failed, try recreating the directory
                if ( is_dir( $path ) ) {
                    // Try to remove and recreate (only if empty or only contains cache files)
                    $files = glob( $path . '*' );
                    $safe_to_recreate = true;
                    
                    // Check if all files look like cache files (md5 hashes)
                    foreach ( $files as $file ) {
                        $basename = basename( $file );
                        if ( ! preg_match( '/^[a-f0-9]{32}$/', $basename ) ) {
                            $safe_to_recreate = false;
                            break;
                        }
                    }
                    
                    if ( $safe_to_recreate ) {
                        // Remove cache files
                        foreach ( $files as $file ) {
                            @unlink( $file );
                        }
                        
                        // Remove directory and recreate
                        if ( @rmdir( $path ) ) {
                            return self::createCacheDirectory( $path );
                        }
                    }
                }
                
            } catch ( Exception $e ) {
                self::$_last_error = "Permission fix failed: " . $e->getMessage( );
            }
            
            return false;
        }

        /**
         * getSuggestedCachePaths
         * 
         * Get suggested alternative cache paths for troubleshooting
         * 
         * @since 8.5
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns suggested cache paths with their status
         */
        public static function getSuggestedCachePaths( ): array {
            
            $suggestions = [
                'current' => self::$_fallback_path,
                'alternatives' => []
            ];
            
            $test_paths = [
                sys_get_temp_dir( ) . '/kpt_cache_alt/',
                getcwd( ) . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_alt/',
                sys_get_temp_dir( ) . '/cache/',
            ];
            
            foreach ( $test_paths as $path ) {
                $status = [
                    'path' => $path,
                    'parent_exists' => file_exists( dirname( $path ) ),
                    'parent_writable' => is_writable( dirname( $path ) ),
                    'can_create' => false,
                    'recommended' => false
                ];
                
                // Test if we can create a test directory
                $test_dir = $path . 'test_' . uniqid( );
                if ( @mkdir( $test_dir, 0755, true ) ) {
                    $status['can_create'] = true;
                    $status['recommended'] = is_writable( $test_dir );
                    @rmdir( $test_dir );
                }
                
                $suggestions['alternatives'][] = $status;
            }
            
            return $suggestions;
        }
    }
}