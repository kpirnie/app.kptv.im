<?php
/**
 * KPT Cache - Memcached Caching Trait
 * Enhanced Memcached support with connection pooling
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'Cache_Memcached' ) ) {

    trait Cache_Memcached {
        
        // Keep direct connection for non-pooled usage
        private static ?Memcached $_memcached = null;
        
        /**
         * Test Memcached connection
         */
        private static function testMemcachedConnection(): bool {
            try {
                $config = Cache_Config::get('memcached');
                
                $memcached = new \Memcached();
                $memcached->addServer($config['host'], $config['port']);
                
                // Set basic options for testing
                $memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 1000);
                $memcached->setOption(\Memcached::OPT_POLL_TIMEOUT, 1000);
                
                // Test with a simple operation
                $test_key = 'kpt_memcached_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                $success = $memcached->set($test_key, $test_value, 60);
                if ($success) {
                    $retrieved = $memcached->get($test_key);
                    $memcached->delete($test_key); // Clean up
                    $memcached->quit();
                    return $retrieved === $test_value;
                }
                
                $memcached->quit();
                return false;
                
            } catch (Exception $e) {
                self::$_last_error = "Memcached test failed: " . $e->getMessage();
                return false;
            }
        }
        
        /**
         * Get Memcached connection (backward compatibility)
         * Uses connection pool if available, falls back to direct connection
         */
        private static function getMemcached(): ?Memcached {
            // Try connection pool first
            if (self::$_connection_pooling_enabled ?? true) {
                $connection = Cache_ConnectionPool::getConnection('memcached');
                if ($connection) {
                    return $connection;
                }
            }
            
            // Fallback to direct connection
            if (self::$_memcached === null || !self::isMemcachedConnected()) {
                self::$_memcached = self::createDirectMemcachedConnection();
            }
            
            return self::$_memcached;
        }
        
        /**
         * Create direct Memcached connection (non-pooled)
         */
        private static function createDirectMemcachedConnection(): ?Memcached {
            $config = Cache_Config::get('memcached');
            $attempts = 0;
            $max_attempts = $config['retry_attempts'] ?? 2;
            
            while ($attempts <= $max_attempts) {
                try {
                    $memcached = new \Memcached($config['persistent'] ? 'kpt_pool' : null);
                    
                    // Only add servers if not using persistent connections or if no servers exist
                    if (!$config['persistent'] || count($memcached->getServerList()) === 0) {
                        $memcached->addServer($config['host'], $config['port']);
                    }
                    
                    // Set options
                    $memcached->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
                    $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                    $memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, ($config['connection_timeout'] ?? 5) * 1000);
                    $memcached->setOption(\Memcached::OPT_POLL_TIMEOUT, 1000);
                    
                    // Test connection
                    $stats = $memcached->getStats();
                    if (empty($stats)) {
                        throw new Exception("Memcached connection test failed");
                    }
                    
                    return $memcached;
                    
                } catch (Exception $e) {
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
         * Check if Memcached connection is alive
         */
        private static function isMemcachedConnected(): bool {
            if (self::$_memcached === null) return false;
            
            try {
                $stats = self::$_memcached->getStats();
                return !empty($stats);
            } catch (Exception $e) {
                return false;
            }
        }
        
        /**
         * Get from Memcached with pool-aware connection handling
         */
        private static function getFromMemcached(string $_key): mixed {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                $result = $connection->get($prefixed_key);
                
                if ($connection->getResultCode() === \Memcached::RES_SUCCESS) {
                    return $result;
                }
                
                return false;
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                if (!$use_pool) {
                    self::$_memcached = null; // Reset direct connection on error
                }
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Set to Memcached with pool-aware connection handling
         */
        private static function setToMemcached(string $_key, mixed $_data, int $_length): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->set($prefixed_key, $_data, time() + $_length);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                if (!$use_pool) {
                    self::$_memcached = null;
                }
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Delete from Memcached with pool-aware connection handling
         */
        private static function deleteFromMemcached(string $_key): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->delete($prefixed_key);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                if (!$use_pool) {
                    self::$_memcached = null;
                }
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Enhanced Memcached batch get operations for pooled connections
         */
        public static function memcachedMultiGet(array $keys): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return [];
                
                $config = Cache_Config::get('memcached');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                
                // Prefix all keys
                $prefixed_keys = array_map(function($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);
                
                $results = $connection->getMulti($prefixed_keys);
                
                // Remove prefix from results
                if ($prefix && $results) {
                    $unprefixed_results = [];
                    foreach ($results as $prefixed_key => $value) {
                        $original_key = substr($prefixed_key, strlen($prefix));
                        $unprefixed_results[$original_key] = $value;
                    }
                    return $unprefixed_results;
                }
                
                return $results ?: [];
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return [];
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Enhanced Memcached batch set operations
         */
        public static function memcachedMultiSet(array $items, int $ttl = 3600): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                
                // Prefix all keys
                $prefixed_items = [];
                foreach ($items as $key => $value) {
                    $prefixed_items[$prefix . $key] = $value;
                }
                
                return $connection->setMulti($prefixed_items, time() + $ttl);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Enhanced Memcached batch delete operations
         */
        public static function memcachedMultiDelete(array $keys): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return [];
                
                $config = Cache_Config::get('memcached');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                
                // Prefix all keys
                $prefixed_keys = array_map(function($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);
                
                $results = $connection->deleteMulti($prefixed_keys);
                
                // Process results - deleteMulti returns array of result codes
                $failed_keys = [];
                if (is_array($results)) {
                    foreach ($results as $prefixed_key => $result) {
                        if ($result !== true) {
                            $original_key = substr($prefixed_key, strlen($prefix));
                            $failed_keys[] = $original_key;
                        }
                    }
                }
                
                return [
                    'total' => count($keys),
                    'successful' => count($keys) - count($failed_keys),
                    'failed' => count($failed_keys),
                    'failed_keys' => $failed_keys
                ];
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return [
                    'total' => count($keys),
                    'successful' => 0,
                    'failed' => count($keys),
                    'failed_keys' => $keys,
                    'error' => $e->getMessage()
                ];
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Increment Memcached value (atomic operation)
         */
        public static function memcachedIncrement(string $_key, int $offset = 1, int $initial_value = 0, int $expiry = 0): int|bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->increment($prefixed_key, $offset, $initial_value, $expiry);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Decrement Memcached value (atomic operation)
         */
        public static function memcachedDecrement(string $_key, int $offset = 1, int $initial_value = 0, int $expiry = 0): int|bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->decrement($prefixed_key, $offset, $initial_value, $expiry);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Add item to Memcached (only if it doesn't exist)
         */
        public static function memcachedAdd(string $_key, mixed $_data, int $_length): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->add($prefixed_key, $_data, time() + $_length);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Replace item in Memcached (only if it exists)
         */
        public static function memcachedReplace(string $_key, mixed $_data, int $_length): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->replace($prefixed_key, $_data, time() + $_length);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Append data to existing Memcached item
         */
        public static function memcachedAppend(string $_key, string $_data): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->append($prefixed_key, $_data);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Prepend data to existing Memcached item
         */
        public static function memcachedPrepend(string $_key, string $_data): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->prepend($prefixed_key, $_data);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Touch Memcached item (update expiration time)
         */
        public static function memcachedTouch(string $_key, int $_length): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return $connection->touch($prefixed_key, time() + $_length);
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Get Memcached statistics
         */
        private static function getMemcachedStats(): array {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return ['error' => 'No connection'];
                
                $stats = $connection->getStats();
                
                // Add connection pool stats if using pooled connections
                if ($use_pool) {
                    $pool_stats = Cache_ConnectionPool::getPoolStats();
                    $stats['pool_stats'] = $pool_stats['memcached'] ?? [];
                }
                
                // Add server list
                $stats['servers'] = $connection->getServerList();
                
                // Add version information
                $versions = $connection->getVersion();
                if ($versions) {
                    $stats['versions'] = $versions;
                }
                
                return $stats;
                
            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Clear Memcached cache (flush all)
         */
        private static function clearMemcached(): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                return $connection->flush();
                
            } catch (Exception $e) {
                self::$_last_error = $e->getMessage();
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
        
        /**
         * Get last result code from Memcached
         */
        public static function getMemcachedResultCode(): int {
            $connection = self::getMemcached();
            if (!$connection) return -1;
            
            return $connection->getResultCode();
        }
        
        /**
         * Get last result message from Memcached
         */
        public static function getMemcachedResultMessage(): string {
            $connection = self::getMemcached();
            if (!$connection) return 'No connection';
            
            return $connection->getResultMessage();
        }
        
        /**
         * Check if Memcached key exists
         */
        public static function memcachedKeyExists(string $_key): bool {
            $connection = null;
            $use_pool = self::$_connection_pooling_enabled ?? true;
            
            try {
                if ($use_pool) {
                    $connection = Cache_ConnectionPool::getConnection('memcached');
                } else {
                    $connection = self::getMemcached();
                }
                
                if (!$connection) return false;
                
                $config = Cache_Config::get('memcached');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                // Try to get the key
                $connection->get($prefixed_key);
                
                // Check if the result code indicates success
                return $connection->getResultCode() === \Memcached::RES_SUCCESS;
                
            } catch (Exception $e) {
                return false;
            } finally {
                if ($use_pool && $connection) {
                    Cache_ConnectionPool::returnConnection('memcached', $connection);
                }
            }
        }
    }
}