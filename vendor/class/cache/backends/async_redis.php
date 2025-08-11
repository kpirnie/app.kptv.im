<?php
// =============================================================================
// REDIS ASYNC TRAIT
// =============================================================================

if ( ! trait_exists( 'KPT_Cache_Redis_Async' ) ) {

    trait KPT_Cache_Redis_Async {
        
        /**
         * Async Redis get with connection pooling
         */
        public static function getRedisAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? '') . $key;
                    $value = $connection->get($prefixed_key);
                    
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
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
        public static function setRedisAsync(string $key, mixed $data, int $ttl): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? '') . $key;
                    $success = $connection->setex($prefixed_key, $ttl, serialize($data));
                    
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($success);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis delete with connection pooling
         */
        public static function deleteRedisAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? '') . $key;
                    $result = $connection->del($prefixed_key);
                    
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($result > 0);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis pipeline operations
         */
        public static function redisPipelineAsync(array $commands): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($commands) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
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
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($results ?: []);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis transaction
         */
        public static function redisTransactionAsync(array $commands): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($commands) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
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
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($results ?: []);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis multi-get
         */
        public static function redisMultiGetAsync(array $keys): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($keys) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefix = $config['prefix'] ?? '';
                    
                    // Prefix all keys
                    $prefixed_keys = array_map(function($key) use ($prefix) {
                        return $prefix . $key;
                    }, $keys);
                    
                    $values = $connection->mget($prefixed_keys);
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
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
        public static function redisMultiSetAsync(array $items, int $ttl = 3600): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($items, $ttl) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefix = $config['prefix'] ?? '';
                    
                    // Use pipeline for batch operations
                    $pipeline = $connection->pipeline();
                    
                    foreach ($items as $key => $value) {
                        $prefixed_key = $prefix . $key;
                        $pipeline->setex($prefixed_key, $ttl, serialize($value));
                    }
                    
                    $results = $pipeline->exec();
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
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
        public static function redisExistsAsync(array $keys): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($keys) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefix = $config['prefix'] ?? '';
                    
                    // Prefix all keys
                    $prefixed_keys = array_map(function($key) use ($prefix) {
                        return $prefix . $key;
                    }, $keys);
                    
                    $count = $connection->exists(...$prefixed_keys);
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($count);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
        
        /**
         * Async Redis TTL check
         */
        public static function redisTtlAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                try {
                    $connection = KPT_Cache_ConnectionPool::getConnection('redis');
                    if (!$connection) {
                        $reject(new Exception('No Redis connection available'));
                        return;
                    }
                    
                    $config = KPT_Cache_Config::get('redis');
                    $prefixed_key = ($config['prefix'] ?? '') . $key;
                    $ttl = $connection->ttl($prefixed_key);
                    
                    KPT_Cache_ConnectionPool::returnConnection('redis', $connection);
                    
                    $resolve($ttl);
                    
                } catch (RedisException $e) {
                    $reject($e);
                }
            });
        }
    }
}
