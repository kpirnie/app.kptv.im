<?php
/**
 * KPT Cache - Redis Caching Traits
 * Enhanced Redis support with connection pooling and async operations
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// =============================================================================
// MAIN REDIS TRAIT
// =============================================================================

if ( ! trait_exists( 'KPT_Cache_Redis' ) ) {

    trait KPT_Cache_Redis {
        
        // Keep direct connection for non-pooled usage
        private static ?Redis $_redis = null;
        
        /**
         * Test Redis connection
         */
        private static function testRedisConnection(): bool {
            try {
                $config = KPT_Cache_Config::get('redis');
                
                $redis = new Redis();
                $connected = $redis->pconnect(
                    $config['host'],
                    $config['port'],
                    $config['connect_timeout'] ?? 2
                );
                
                if (!$connected) return false;
                
                $redis->select($config['database'] ?? 0);
                $result = $redis->ping();
                $redis->close();
                
                return $result === true || $result === '+PONG';
                
            } catch (Exception $e) {
                self::$_last_error = "Redis test failed: " . $e->getMessage();
                return false;
            }
        }
        
        /**
         * Get Redis connection (backward compatibility)
         * Uses connection pool if available, falls back to direct connection
         */
        private static function getRedis(): ?Redis {
            // Try connection pool first
            if (self::$_connection_pooling_enabled ?? true) {
                $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                if ($connection) {
                    return $connection;
                }
            }
            
            // Fallback to direct connection
            if (self::$_redis === null || !self::isRedisConnected()) {
                self::$_redis = self::createDirectRedisConnection();
            }
            
            return self::$_redis;
        }
        
        /**
         * Create direct Redis connection (non-pooled)
         */
        private static function createDirectRedisConnection(): ?Redis {
            $config = KPT_Cache_Config::get('redis');
            $attempts = 0;
            $max_attempts = $config['retry_attempts'] ?? 2;
            
            while ($attempts <= $max_attempts) {
                try {
                    $redis = new Redis();
                    
                    $connected = $redis->pconnect(
                        $config['host'],
                        $config['port'],
                        $config['connect_timeout'] ?? 2
                    );
                    
                    if (!$connected) {
                        throw new RedisException("Connection failed");
                    }
                    
                    $redis->select($config['database'] ?? 0);
                    
                    if (!empty($config['prefix'])) {
                        $redis->setOption(Redis::OPT_PREFIX, $config['prefix']);
                    }
                    
                    $ping_result = $redis->ping();
                    if ($ping_result !== true && $ping_result !== '+PONG') {
                        throw new RedisException("Ping test failed");
                    }
                    
                    return $redis;
                    
                } catch (RedisException $e) {
                    self::$_last_error = $e->getMessage();
                    
                    if ($attempts < $max_attempts) {
                        usleep(($config['retry_delay'] ?? 100) * 1000);
                    }
                    $attempts++;
                }
            }
            
            return null;
        }
        
        /**
         * Check if Redis connection is alive
         */
        private static function isRedisConnected(): bool {
            if (self::$_redis === null) return false;
            
            try {
                $result = self::$_redis->ping();
                return $result === true || $result === '+PONG';
            } catch (RedisException $e) {
                return false;
            }
        }
        
        /**
         * Get from Redis with pool-aware connection handling
         */
        private static function getFromRedis(string $_key): mixed {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return false;
                
                $config = KPT_Cache_Config::get('redis');
                $prefixed_key = ($config['prefix'] ?? '') . $_key;
                $value = $connection->get($prefixed_key);
                
                return $value !== false ? unserialize($value) : false;
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                if (!$use_pool) {
                    self::$_redis = null; // Reset direct connection on error
                }
                return false;
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Set to Redis with pool-aware connection handling
         */
        private static function setToRedis(string $_key, mixed $_data, int $_length): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return false;
                
                $config = KPT_Cache_Config::get('redis');
                $prefixed_key = ($config['prefix'] ?? '') . $_key;
                
                return $connection->setex($prefixed_key, $_length, serialize($_data));
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                if (!$use_pool) {
                    self::$_redis = null;
                }
                return false;
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Delete from Redis with pool-aware connection handling
         */
        private static function deleteFromRedis(string $_key): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return false;
                
                $config = KPT_Cache_Config::get('redis');
                $prefixed_key = ($config['prefix'] ?? '') . $_key;
                
                return $connection->del($prefixed_key) > 0;
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                if (!$use_pool) {
                    self::$_redis = null;
                }
                return false;
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Enhanced Redis transaction operations for pooled connections
         */
        public static function redisTransaction(array $commands): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return [];
                
                $multi = $connection->multi();
                
                foreach ($commands as $command) {
                    $method = $command['method'];
                    $args = $command['args'] ?? [];
                    $multi->$method(...$args);
                }
                
                return $multi->exec() ?: [];
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                return [];
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Enhanced Redis pipeline operations
         */
        public static function redisPipeline(array $commands): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return [];
                
                $pipeline = $connection->pipeline();
                
                foreach ($commands as $command) {
                    $method = $command['method'];
                    $args = $command['args'] ?? [];
                    $pipeline->$method(...$args);
                }
                
                return $pipeline->exec() ?: [];
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                return [];
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Enhanced Redis batch operations
         */
        public static function redisMultiGet(array $keys): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return [];
                
                $config = KPT_Cache_Config::get('redis');
                $prefix = $config['prefix'] ?? '';
                
                // Prefix all keys
                $prefixed_keys = array_map(function($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);
                
                $values = $connection->mget($prefixed_keys);
                
                if (!$values) return [];
                
                // Unserialize values and combine with original keys
                $results = [];
                foreach ($keys as $i => $key) {
                    $value = $values[$i] ?? false;
                    $results[$key] = $value !== false ? unserialize($value) : false;
                }
                
                return $results;
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                return [];
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Enhanced Redis batch set operations
         */
        public static function redisMultiSet(array $items, int $ttl = 3600): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return false;
                
                $config = KPT_Cache_Config::get('redis');
                $prefix = $config['prefix'] ?? '';
                
                // Use pipeline for batch operations
                $pipeline = $connection->pipeline();
                
                foreach ($items as $key => $value) {
                    $prefixed_key = $prefix . $key;
                    $pipeline->setex($prefixed_key, $ttl, serialize($value));
                }
                
                $results = $pipeline->exec();
                
                // Check if all operations succeeded
                return !in_array(false, $results ?: []);
                
            } catch (RedisException $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
        
        /**
         * Get Redis statistics
         */
        private static function getRedisStats(): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                } else {
                    $connection = self::getRedis();
                }
                
                if (!$connection) return ['error' => 'No connection'];
                
                $info = $connection->info();
                
                // Add connection pool stats if using pooled connections
                if ($use_pool) {
                    $pool_stats = KPT_Cache_ConnectionPool::getPoolStats();
                    $info['pool_stats'] = $pool_stats['redis'] ?? [];
                }
                
                return $info;
                
            } catch (RedisException $e) {
                return ['error' => $e->getMessage()];
            } finally {
                if ($use_pool && $connection) {
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                }
            }
        }
    }
}
