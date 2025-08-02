<?php
/**
 * Cache
 * 
 * Multi-tier caching class with OPcache, Redis, Memcached, and File fallbacks
 * 
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
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
     * @package KP Tasks
     */
    class KPT_Cache {

        // Cache tier constants
        const TIER_OPCACHE = 'opcache';
        const TIER_REDIS = 'redis';
        const TIER_MEMCACHED = 'memcached';
        const TIER_FILE = 'file';

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
        private static $_fallback_path = '/tmp/kpt_cache/';
        private static $_opcache_prefix = 'KPT_OPCACHE_';
        private static $_initialized = false;

        /**
         * Initialize the cache system and determine available tiers
         * 
         * @return void Returns nothing
         */
        private static function init( ) : void {

            // if we're already initialized, dump out of the function
            if ( self::$_initialized ) {
                return;
            }
            
            // hold the available cache tiers
            self::$_available_tiers = [];
            
            // Check OPcache availability
            if ( function_exists( 'opcache_get_status') && opcache_get_status( ) !== false ) {
                self::$_available_tiers[] = self::TIER_OPCACHE;
            }
            
            // Check Redis availability
            if ( class_exists( 'Redis' ) && self::getRedis( ) ) {
                self::$_available_tiers[] = self::TIER_REDIS;
            }
            
            // Check Memcached availability
            if ( class_exists( 'Memcached' ) && self::getMemcached( ) ) {
                self::$_available_tiers[] = self::TIER_MEMCACHED;
            }
            
            // File cache is always available
            self::$_available_tiers[] = self::TIER_FILE;
            self::initFallback( );
            
            // we are initialized at this point
            self::$_initialized = true;
        }

        /**
         * Get available cache tiers
         * 
         * @return array Returns array of available cache tiers
         */
        public static function getAvailableTiers( ) : array {

            // make sure we're initialized
            self::ensureInitialized( );
            return self::$_available_tiers;
        }

        /**
         * Ensure the cache system is initialized
         * 
         * @return void Returns nothing
         */
        private static function ensureInitialized( ) : void {

            // if we are not initialized yet, initialize us!
            if ( ! self::$_initialized ) {
                self::init( );
            }
        }

        /**
         * Initialize the fallback directory
         * 
         * @return void Returns nothing
         */
        private static function initFallback( ) : void {

            // if the cache fallback path does not exist
            if ( ! file_exists( self::$_fallback_path ) ) {
                mkdir( self::$_fallback_path, 0755, true );
            }
        }

        /**
         * Try to fire up and configure redis for caching
         * 
         * @return ?object Returns a possible nullable redis object
         */
        private static function getRedis( ) : ?object {

            // if we do not have redis as of yet...
            if ( self::$_redis === null ) {

                // set the retries to 0, and get the max number of attemps
                $attempts = 0;
                $max_attempts = self::$_redis_settings['retry_attempts'];

                // while we are in range...
                while ( $attempts <= $max_attempts ) {

                    // try to connect to the redis server
                    try {
                        self::$_redis = new Redis( );
                        
                        // get the connected flag by trying to connect
                        $connected = self::$_redis -> pconnect(
                            self::$_redis_settings['host'],
                            self::$_redis_settings['port'],
                            self::$_redis_settings['connect_timeout']
                        );
                        
                        // if we are not connected, set the error and throw the exception
                        if ( ! $connected ) {
                            self::$_last_error = "Redis connection failed";
                            self::$_redis = null;
                            throw new RedisException( "Connection failed" );
                        }
                        
                        // select the configured database
                        self::$_redis -> select( self::$_redis_settings['database'] );
                        
                        // if we have a prefix, set it
                        if ( self::$_redis_settings['prefix'] ) {
                            self::$_redis -> setOption( Redis::OPT_PREFIX, self::$_redis_settings['prefix'] );
                        }
                        
                        // if we have a timeout
                        if ( isset( self::$_redis_settings['read_timeout'] ) ) {
                            self::$_redis -> setOption( Redis::OPT_READ_TIMEOUT, self::$_redis_settings['read_timeout'] );
                        }
                        
                        // return the redis object
                        return self::$_redis;
                        
                    // whoopsie... set the error, log the message and try again
                    } catch ( RedisException $e ) {
                        self::$_last_error = $e->getMessage( );
                        self::$_redis = null;
                        
                        // as long as we aren't beyond our retries, sleep a little bit
                        if ( $attempts < $max_attempts ) {
                            usleep( self::$_redis_settings['retry_delay'] * 1000 );
                        }

                        // increment the attempts
                        $attempts++;

                    }

                }
                
                // return nothing here
                return null;
            }
            
            // return the redis object
            return self::$_redis;

        }

        /**
         * Try to fire up and configure memcached for caching
         * 
         * @return ?object Returns a possible nullable memcached object
         */
        private static function getMemcached( ): ?object {

            
            if (self::$_memcached === null) {
                $attempts = 0;
                $max_attempts = self::$_memcached_settings['retry_attempts'];

                while ($attempts <= $max_attempts) {
                    try {
                        self::$_memcached = new Memcached(self::$_memcached_settings['persistent'] ? 'kpt_pool' : null);
                        
                        // Only add servers if not using persistent connections or if no servers exist
                        if (!self::$_memcached_settings['persistent'] || count(self::$_memcached->getServerList()) === 0) {
                            self::$_memcached->addServer(
                                self::$_memcached_settings['host'],
                                self::$_memcached_settings['port']
                            );
                        }
                        
                        // Set options
                        self::$_memcached->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
                        self::$_memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
                        
                        // Test connection
                        $stats = self::$_memcached->getStats();
                        if (empty($stats)) {
                            throw new Exception("Memcached connection failed");
                        }
                        
                        return self::$_memcached;
                        
                    } catch (Exception $e) {
                        self::$_last_error = $e->getMessage();
                        self::$_memcached = null;
                        
                        if ($attempts < $max_attempts) {
                            usleep(self::$_memcached_settings['retry_delay'] * 1000);
                        }
                        $attempts++;
                    }
                }
                
                return null;
            }
            
            return self::$_memcached;
        }

        /**
         * Check if cache system is healthy
         * 
         * @return array Returns health status of all cache tiers
         */
        public static function isHealthy(): array {
            self::ensureInitialized();
            
            $health = [];
            
            // Check OPcache
            $health[self::TIER_OPCACHE] = function_exists('opcache_get_status') && opcache_get_status() !== false;
            
            // Check Redis
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $health[self::TIER_REDIS] = $redis->ping() === true;
                } catch (RedisException $e) {
                    self::$_last_error = $e->getMessage();
                    $health[self::TIER_REDIS] = false;
                }
            } else {
                $health[self::TIER_REDIS] = false;
            }
            
            // Check Memcached
            $memcached = self::getMemcached();
            if ($memcached) {
                $stats = $memcached->getStats();
                $health[self::TIER_MEMCACHED] = !empty($stats);
            } else {
                $health[self::TIER_MEMCACHED] = false;
            }
            
            // File cache is always healthy if directory exists
            $health[self::TIER_FILE] = is_dir(self::$_fallback_path) && is_writable(self::$_fallback_path);
            
            return $health;
        }

        /**
         * Get the last error message
         * 
         * @return ?string Returns a nullable string of the last error
         */
        public static function getLastError(): ?string {
            return self::$_last_error;
        }

        /**
         * Get an item from cache using tier hierarchy
         * 
         * @param string $_key The key name
         * @return mixed Returns the item from cache if it exists
         */
        public static function get(string $_key): mixed {
            self::ensureInitialized();
            
            $tiers = self::$_available_tiers;
            
            foreach ($tiers as $tier) {
                $result = self::getFromTier($_key, $tier);
                if ($result !== false) {
                    // Promote to higher tiers for faster future access
                    self::promoteToHigherTiers($_key, $result, $tier);
                    return $result;
                }
            }
            
            return false;
        }

        /**
         * Get item from specific cache tier
         * 
         * @param string $_key The key name
         * @param string $tier The cache tier
         * @return mixed Returns the item from cache if it exists
         */
        private static function getFromTier(string $_key, string $tier): mixed {
            switch ($tier) {
                case self::TIER_OPCACHE:
                    return self::getFromOPcache($_key);
                    
                case self::TIER_REDIS:
                    return self::getFromRedis($_key);
                    
                case self::TIER_MEMCACHED:
                    return self::getFromMemcached($_key);
                    
                case self::TIER_FILE:
                    return self::getFromFile($_key);
                    
                default:
                    return false;
            }
        }

        /**
         * Get item from OPcache
         * 
         * @param string $_key The key name
         * @return mixed Returns the item from cache if it exists
         */
        private static function getFromOPcache(string $_key): mixed {
            $opcache_key = self::$_opcache_prefix . $_key;
            
            // Create a temporary file to store in OPcache
            $temp_file = sys_get_temp_dir() . '/' . $opcache_key . '.php';
            
            if (file_exists($temp_file)) {
                // Check if file is in OPcache
                $status = opcache_get_status(true);
                if (isset($status['scripts'][$temp_file])) {
                    $data = include $temp_file;
                    if (is_array($data) && isset($data['expires'], $data['value'])) {
                        if ($data['expires'] > time()) {
                            return $data['value'];
                        } else {
                            // Expired, remove from OPcache and filesystem
                            opcache_invalidate($temp_file, true);
                            unlink($temp_file);
                        }
                    }
                }
            }
            
            return false;
        }

        /**
         * Get item from Redis
         * 
         * @param string $_key The key name
         * @return mixed Returns the item from cache if it exists
         */
        private static function getFromRedis(string $_key): mixed {
            $redis = self::getRedis();
            if (!$redis) return false;
            
            try {
                $_val = $redis->get($_key);
                if ($_val !== false) {
                    return unserialize($_val);
                }
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
            }
            
            return false;
        }

        /**
         * Get item from Memcached
         * 
         * @param string $_key The key name
         * @return mixed Returns the item from cache if it exists
         */
        private static function getFromMemcached(string $_key): mixed {
            $memcached = self::getMemcached();
            if (!$memcached) return false;
            
            $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
            $result = $memcached->get($prefixed_key);
            
            if ($memcached->getResultCode() === Memcached::RES_SUCCESS) {
                return $result;
            }
            
            return false;
        }

        /**
         * Get item from file cache
         * 
         * @param string $_key The key name
         * @return mixed Returns the item from cache if it exists
         */
        private static function getFromFile(string $_key): mixed {
            $file = self::$_fallback_path . md5($_key);
            
            if (file_exists($file)) {
                $data = file_get_contents($file);
                $expires = substr($data, 0, 10);
                
                if (time() > $expires) {
                    unlink($file);
                    return false;
                }
                
                return unserialize(substr($data, 10));
            }
            
            return false;
        }

        /**
         * Promote cache item to higher tiers for faster future access
         * 
         * @param string $_key The key name
         * @param mixed $_data The data to promote
         * @param string $current_tier The tier where data was found
         * @return void Returns nothing
         */
        private static function promoteToHigherTiers(string $_key, mixed $_data, string $current_tier): void {
            $tiers = self::$_available_tiers;
            $current_index = array_search($current_tier, $tiers);
            
            // Promote to all higher tiers
            for ($i = 0; $i < $current_index; $i++) {
                self::setToTier($_key, $_data, 3600, $tiers[$i]); // Default 1 hour TTL for promotion
            }
        }

        /**
         * Set an item in cache using all available tiers
         * 
         * @param string $_key The key name
         * @param mixed $_data The data to set to cache
         * @param int $_length How long does the data need to be cached for, defaults to 1 hour
         * @return bool Returns if the item was successfully set to at least one tier
         */
        public static function set(string $_key, mixed $_data, int $_length = 3600): bool {
            self::ensureInitialized();
            
            if (!$_data || empty($_data)) {
                return false;
            }
            
            $success = false;
            $tiers = self::$_available_tiers;
            
            foreach ($tiers as $tier) {
                if (self::setToTier($_key, $_data, $_length, $tier)) {
                    $success = true;
                }
            }
            
            return $success;
        }

        /**
         * Set item to specific cache tier
         * 
         * @param string $_key The key name
         * @param mixed $_data The data to set
         * @param int $_length TTL in seconds
         * @param string $tier The cache tier
         * @return bool Returns if successful
         */
        private static function setToTier(string $_key, mixed $_data, int $_length, string $tier): bool {
            switch ($tier) {
                case self::TIER_OPCACHE:
                    return self::setToOPcache($_key, $_data, $_length);
                    
                case self::TIER_REDIS:
                    return self::setToRedis($_key, $_data, $_length);
                    
                case self::TIER_MEMCACHED:
                    return self::setToMemcached($_key, $_data, $_length);
                    
                case self::TIER_FILE:
                    return self::setToFile($_key, $_data, $_length);
                    
                default:
                    return false;
            }
        }

        /**
         * Set item to OPcache
         */
        private static function setToOPcache(string $_key, mixed $_data, int $_length): bool {
            $opcache_key = self::$_opcache_prefix . $_key;
            $temp_file = sys_get_temp_dir() . '/' . $opcache_key . '.php';
            
            $expires = time() + $_length;
            $content = "<?php return " . var_export(['expires' => $expires, 'value' => $_data], true) . ";";
            
            if (file_put_contents($temp_file, $content) !== false) {
                opcache_compile_file($temp_file);
                return true;
            }
            
            return false;
        }

        /**
         * Set item to Redis
         */
        private static function setToRedis(string $_key, mixed $_data, int $_length): bool {
            $redis = self::getRedis();
            if (!$redis) return false;
            
            try {
                $redis->del($_key);
                return $redis->setex($_key, $_length, serialize($_data));
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                return false;
            }
        }

        /**
         * Set item to Memcached
         */
        private static function setToMemcached(string $_key, mixed $_data, int $_length): bool {
            $memcached = self::getMemcached();
            if (!$memcached) return false;
            
            $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
            return $memcached->set($prefixed_key, $_data, time() + $_length);
        }

        /**
         * Set item to file cache
         */
        private static function setToFile(string $_key, mixed $_data, int $_length): bool {
            $file = self::$_fallback_path . md5($_key);
            $expires = time() + $_length;
            $data = $expires . serialize($_data);
            
            return file_put_contents($file, $data) !== false;
        }

        /**
         * Delete an item from all cache tiers
         * 
         * @param string $_key The key name
         * @return bool Returns if the item was successfully deleted from all tiers
         */
        public static function del(string $_key): bool {
            self::ensureInitialized();
            
            $success = true;
            $tiers = self::$_available_tiers;
            
            foreach ($tiers as $tier) {
                if (!self::delFromTier($_key, $tier)) {
                    $success = false;
                }
            }
            
            return $success;
        }

        /**
         * Delete item from specific tier
         */
        private static function delFromTier(string $_key, string $tier): bool {
            switch ($tier) {
                case self::TIER_OPCACHE:
                    $opcache_key = self::$_opcache_prefix . $_key;
                    $temp_file = sys_get_temp_dir() . '/' . $opcache_key . '.php';
                    if (file_exists($temp_file)) {
                        opcache_invalidate($temp_file, true);
                        return unlink($temp_file);
                    }
                    return true;
                    
                case self::TIER_REDIS:
                    $redis = self::getRedis();
                    if ($redis) {
                        try {
                            return (bool) $redis->del($_key);
                        } catch (RedisException $e) {
                            self::$_last_error = $e->getMessage();
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_MEMCACHED:
                    $memcached = self::getMemcached();
                    if ($memcached) {
                        $prefixed_key = self::$_memcached_settings['prefix'] . $_key;
                        return $memcached->delete($prefixed_key);
                    }
                    return true;
                    
                case self::TIER_FILE:
                    $file = self::$_fallback_path . md5($_key);
                    if (file_exists($file)) {
                        return unlink($file);
                    }
                    return true;
                    
                default:
                    return false;
            }
        }

        /**
         * Clear all cache from all tiers
         * 
         * @return bool Returns if all caches were successfully cleared
         */
        public static function clear(): bool {
            self::ensureInitialized();
            
            $success = true;
            $tiers = self::$_available_tiers;
            
            foreach ($tiers as $tier) {
                if (!self::clearTier($tier)) {
                    $success = false;
                }
            }
            
            return $success;
        }

        /**
         * Clear specific tier
         */
        private static function clearTier(string $tier): bool {
            switch ($tier) {
                case self::TIER_OPCACHE:
                    return opcache_reset();
                    
                case self::TIER_REDIS:
                    $redis = self::getRedis();
                    if ($redis) {
                        try {
                            return $redis->flushAll();
                        } catch (RedisException $e) {
                            self::$_last_error = $e->getMessage();
                            return false;
                        }
                    }
                    return true;
                    
                case self::TIER_MEMCACHED:
                    $memcached = self::getMemcached();
                    if ($memcached) {
                        return $memcached->flush();
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

        /**
         * Close all connections
         * 
         * @return void Returns nothing
         */
        public static function close(): void {
            if (self::$_redis instanceof Redis) {
                try {
                    self::$_redis->close();
                } catch (RedisException $e) {
                    self::$_last_error = $e->getMessage();
                }
                self::$_redis = null;
            }
            
            if (self::$_memcached instanceof Memcached) {
                self::$_memcached->quit();
                self::$_memcached = null;
            }
        }

        /**
         * Get cache statistics
         * 
         * @return array Returns statistics for all cache tiers
         */
        public static function getStats(): array {
            self::ensureInitialized();
            
            $stats = [];
            
            // OPcache stats
            if (function_exists('opcache_get_status')) {
                $stats[self::TIER_OPCACHE] = opcache_get_status();
            }
            
            // Redis stats
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $stats[self::TIER_REDIS] = $redis->info();
                } catch (RedisException $e) {
                    $stats[self::TIER_REDIS] = ['error' => $e->getMessage()];
                }
            }
            
            // Memcached stats
            $memcached = self::getMemcached();
            if ($memcached) {
                $stats[self::TIER_MEMCACHED] = $memcached->getStats();
            }
            
            // File cache stats
            $files = glob(self::$_fallback_path . '*');
            $stats[self::TIER_FILE] = [
                'file_count' => count($files),
                'total_size' => array_sum(array_map('filesize', $files)),
                'path' => self::$_fallback_path
            ];
            
            return $stats;
        }
    }
}
