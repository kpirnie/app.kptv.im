<?php
/**
 * API Cache
 * 
 * Enhanced caching class with Redis primary storage, filesystem fallback,
 * health checks, and retry logic
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

if (!class_exists('KPT_Cache')) {

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
            'retry_attempts' => 2, // Number of retry attempts
            'retry_delay' => 100, // Delay between retries in milliseconds
        ];

        private static $_redis = null;
        private static $_last_error = null;
        private static $_use_fallback = false;
        private static $_fallback_path = '/tmp/kpt_cache/';

        /**
         * Initialize the fallback directory
         */
        private static function initFallback() {
            if (!file_exists(self::$_fallback_path)) {
                mkdir(self::$_fallback_path, 0755, true);
            }
        }

        /**
         * Get or create a Redis connection with retry logic
         */
        private static function getRedis() {
            if (self::$_redis === null) {
                $attempts = 0;
                $max_attempts = self::$_redis_settings['retry_attempts'];

                while ($attempts <= $max_attempts) {
                    try {
                        self::$_redis = new Redis();
                        
                        $connected = self::$_redis->pconnect(
                            self::$_redis_settings['host'],
                            self::$_redis_settings['port'],
                            self::$_redis_settings['connect_timeout']
                        );
                        
                        if (!$connected) {
                            self::$_last_error = "Redis connection failed";
                            self::$_redis = null;
                            throw new RedisException("Connection failed");
                        }
                        
                        // Select database
                        self::$_redis->select(self::$_redis_settings['database']);
                        
                        // Set prefix if needed
                        if (self::$_redis_settings['prefix']) {
                            self::$_redis->setOption(Redis::OPT_PREFIX, self::$_redis_settings['prefix']);
                        }
                        
                        // Set read timeout if needed
                        if (isset(self::$_redis_settings['read_timeout'])) {
                            self::$_redis->setOption(Redis::OPT_READ_TIMEOUT, self::$_redis_settings['read_timeout']);
                        }
                        
                        // Connection successful
                        self::$_use_fallback = false;
                        return self::$_redis;
                        
                    } catch (RedisException $e) {
                        self::$_last_error = $e->getMessage();
                        self::$_redis = null;
                        
                        if ($attempts < $max_attempts) {
                            usleep(self::$_redis_settings['retry_delay'] * 1000);
                        }
                        $attempts++;
                    }
                }
                
                // All attempts failed, switch to fallback
                self::$_use_fallback = true;
                self::initFallback();
                return false;
            }
            
            return self::$_redis;
        }

        /**
         * Check if Redis is healthy
         */
        public static function isHealthy(): bool {
            $redis = self::getRedis();
            if (!$redis) return false;
            
            try {
                return $redis->ping() === true;
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                return false;
            }
        }

        /**
         * Get the last error message
         */
        public static function getLastError(): ?string {
            return self::$_last_error;
        }

        /**
         * Get an item from cache (Redis or fallback)
         */
        public static function get(string $_key) {
            // First try Redis if available
            if (!self::$_use_fallback) {
                $redis = self::getRedis();
                if ($redis) {
                    try {
                        $_val = $redis->get($_key);
                        if ($_val !== false) {
                            return unserialize($_val);
                        }
                    } catch (RedisException $e) {
                        self::$_last_error = $e->getMessage();
                    }
                }
            }
            
            // Fallback to filesystem
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
         * Set an item in cache (Redis or fallback)
         */
        public static function set(string $_key, $_data, int $_length = 3600): bool {
            if (!$_data || empty($_data)) {
                return false;
            }

            // First try Redis if available
            if (!self::$_use_fallback) {
                $redis = self::getRedis();
                if ($redis) {
                    try {
                        $redis->del($_key);
                        return $redis->setex($_key, $_length, serialize($_data));
                    } catch (RedisException $e) {
                        self::$_last_error = $e->getMessage();
                    }
                }
            }
            
            // Fallback to filesystem
            $file = self::$_fallback_path . md5($_key);
            $expires = time() + $_length;
            $data = $expires . serialize($_data);
            
            return file_put_contents($file, $data) !== false;
        }

        /**
         * Delete an item from cache (Redis and fallback)
         */
        public static function del(string $_key): bool {
            $success = true;
            
            // Try Redis if available
            if (!self::$_use_fallback) {
                $redis = self::getRedis();
                if ($redis) {
                    try {
                        $success = (bool)$redis->del($_key);
                    } catch (RedisException $e) {
                        self::$_last_error = $e->getMessage();
                        $success = false;
                    }
                }
            }
            
            // Also delete from filesystem fallback
            $file = self::$_fallback_path . md5($_key);
            if (file_exists($file)) {
                $success = $success && unlink($file);
            }
            
            return $success;
        }

        /**
         * Clear all cache (Redis and fallback)
         */
        public static function clear(): bool {
            $success = true;
            
            // Try Redis if available
            if (!self::$_use_fallback) {
                $redis = self::getRedis();
                if ($redis) {
                    try {
                        $success = $redis->flushAll();
                    if (!$success) {
                        self::$_last_error = "Redis flush failed";
                    }
                    return $success;
                    } catch (RedisException $e) {
                        self::$_last_error = $e->getMessage();
                        $success = false;
                    }
                }
            }
            
            // Clear filesystem fallback
            $files = glob(self::$_fallback_path . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $success = $success && unlink($file);
                }
            }
            
            return $success;
        }

        /**
         * Close the Redis connection
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
        }
    }
}