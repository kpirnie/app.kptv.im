<?php
/**
 * Async Cache Traits for I/O-intensive cache backends
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'Cache_Redis_Async' ) ) {

    trait Cache_Redis_Async {
        
        /**
         * Async Redis get with connection pooling
         */
        public static function getRedisAsync(string $key): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($key) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $key;
                    $value = $connection->get($prefixed_key);
                    
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $result = $value !== false ? unserialize($value) : false;
                    $resolve($result);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis set with connection pooling
         */
        public static function setRedisAsync(string $key, mixed $data, int $ttl): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $key;
                    $success = $connection->setex($prefixed_key, $ttl, serialize($data));
                    
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($success);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis delete with connection pooling
         */
        public static function deleteRedisAsync(string $key): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($key) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $key;
                    $result = $connection->del($prefixed_key);
                    
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($result > 0);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis pipeline operations
         */
        public static function redisPipelineAsync(array $commands): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($commands) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $pipeline = $connection->pipeline();
                    
                    foreach ($commands as $command) {
                        $method = $command['method'];
                        $args = $command['args'] ?? [];
                        $pipeline->$method(...$args);
                    }
                    
                    $results = $pipeline->exec();
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($results ?: []);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis transaction
         */
        public static function redisTransactionAsync(array $commands): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($commands) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $multi = $connection->multi();
                    
                    foreach ($commands as $command) {
                        $method = $command['method'];
                        $args = $command['args'] ?? [];
                        $multi->$method(...$args);
                    }
                    
                    $results = $multi->exec();
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($results ?: []);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis multi-get
         */
        public static function redisMultiGetAsync(array $keys): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($keys) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                    
                    // Prefix all keys
                    $prefixed_keys = array_map(function($key) use ($prefix) {
                        return $prefix . $key;
                    }, $keys);
                    
                    $values = $connection->mget($prefixed_keys);
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    if (!$values) {
                        $resolve([]);
                        return;
                    }
                    
                    // Unserialize values and combine with original keys
                    $results = [];
                    foreach ($keys as $i => $key) {
                        $value = $values[$i] ?? false;
                        $results[$key] = $value !== false ? unserialize($value) : false;
                    }
                    
                    $resolve($results);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis multi-set
         */
        public static function redisMultiSetAsync(array $items, int $ttl = 3600): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($items, $ttl) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                    
                    // Use pipeline for batch operations
                    $pipeline = $connection->pipeline();
                    
                    foreach ($items as $key => $value) {
                        $prefixed_key = $prefix . $key;
                        $pipeline->setex($prefixed_key, $ttl, serialize($value));
                    }
                    
                    $results = $pipeline->exec();
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    // Check if all operations succeeded
                    $success = !in_array(false, $results ?: []);
                    $resolve($success);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis exists check
         */
        public static function redisExistsAsync(array $keys): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($keys) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                    
                    // Prefix all keys
                    $prefixed_keys = array_map(function($key) use ($prefix) {
                        return $prefix . $key;
                    }, $keys);
                    
                    $count = $connection->exists(...$prefixed_keys);
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($count);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis TTL check
         */
        public static function redisTtlAsync(string $key): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($key) {
                try {
                    $connection = Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $key;
                    $ttl = $connection->ttl($prefixed_key);
                    
                    Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($ttl);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
    }
}
