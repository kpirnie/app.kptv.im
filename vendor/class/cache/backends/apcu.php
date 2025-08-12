<?php
/**
 * KPT Cache - APCu Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'Cache_APCU' ) ) {

    trait Cache_APCU {

        /**
         * Test APCu connection
         */
        private static function testAPCuConnection(): bool {
            try {
                // Check if APCu is enabled
                if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                    return false;
                }

                // Test with a simple store/fetch operation
                $test_key = 'kpt_apcu_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                // Try to store and retrieve
                if (apcu_store($test_key, $test_value, 60)) {
                    $retrieved = apcu_fetch($test_key);
                    apcu_delete($test_key); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            } catch (Exception $e) {
                self::$_last_error = "APCu test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get item from APCu
         */
        private static function getFromAPCu(string $_key): mixed {
            // If APCu is not enabled, just return false
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }
            
            try {
                // Setup the prefixed key
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                // Fetch the value
                $success = false;
                $value = apcu_fetch($prefixed_key, $success);
                
                // If successful, return the value
                if ($success) {
                    return $value;
                }

            } catch (Exception $e) {
                self::$_last_error = "APCu get error: " . $e->getMessage();
            }
            
            return false;
        }

        /**
         * Set item to APCu
         */
        private static function setToAPCu(string $_key, mixed $_data, int $_length): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }
            
            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return apcu_store($prefixed_key, $_data, $_length);
                
            } catch (Exception $e) {
                self::$_last_error = "APCu set error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Delete item from APCu
         */
        private static function deleteFromAPCu(string $_key): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return true; // Consider it deleted if APCu not available
            }
            
            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return apcu_delete($prefixed_key);
                
            } catch (Exception $e) {
                self::$_last_error = "APCu delete error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Clear APCu cache (with prefix filtering)
         */
        private static function clearAPCu(): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return true;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();

                // Get cache info to iterate through keys
                if (function_exists('apcu_cache_info')) {
                    $cache_info = apcu_cache_info();
                    
                    if (isset($cache_info['cache_list'])) {
                        $deleted = 0;
                        
                        foreach ($cache_info['cache_list'] as $entry) {
                            $key = $entry['info'] ?? $entry['key'] ?? '';
                            
                            // Only delete keys with our prefix
                            if (strpos($key, $prefix) === 0) {
                                if (apcu_delete($key)) {
                                    $deleted++;
                                }
                            }
                        }
                        
                        return $deleted > 0;
                    }
                }
                
                // Fallback to clearing entire cache if we can't filter by prefix
                return function_exists('apcu_clear_cache') ? apcu_clear_cache() : false;
                
            } catch (Exception $e) {
                self::$_last_error = "APCu clear error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get APCu statistics
         */
        private static function getAPCuStats(): array {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return ['error' => 'APCu not available'];
            }

            try {
                $stats = [];

                // Get basic cache info
                if (function_exists('apcu_cache_info')) {
                    $cache_info = apcu_cache_info();
                    $stats['cache_info'] = $cache_info;
                }

                // Get SMA (Shared Memory Allocation) info
                if (function_exists('apcu_sma_info')) {
                    $sma_info = apcu_sma_info();
                    $stats['sma_info'] = $sma_info;
                }

                // Add our prefix-specific stats
                $config = Cache_Config::get('apcu');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                
                $our_keys = 0;
                $our_size = 0;

                if (isset($cache_info['cache_list'])) {
                    foreach ($cache_info['cache_list'] as $entry) {
                        $key = $entry['info'] ?? $entry['key'] ?? '';
                        
                        if (strpos($key, $prefix) === 0) {
                            $our_keys++;
                            $our_size += $entry['mem_size'] ?? 0;
                        }
                    }
                }

                $stats['kpt_cache_stats'] = [
                    'prefix' => $prefix,
                    'our_keys' => $our_keys,
                    'our_memory_usage' => $our_size,
                    'our_memory_usage_human' => KPT::formatBytes($our_size)
                ];

                return $stats;

            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            }
        }

        /**
         * Check if specific key exists in APCu
         */
        private static function apcuKeyExists(string $_key): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return apcu_exists($prefixed_key);
                
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Get APCu key TTL (time to live)
         */
        private static function getAPCuTTL(string $_key): int {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return -1;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;

                // APCu doesn't have a direct TTL function, so we need to check cache info
                if (function_exists('apcu_cache_info')) {
                    $cache_info = apcu_cache_info();
                    
                    if (isset($cache_info['cache_list'])) {
                        foreach ($cache_info['cache_list'] as $entry) {
                            $key = $entry['info'] ?? $entry['key'] ?? '';
                            
                            if ($key === $prefixed_key) {
                                $creation_time = $entry['creation_time'] ?? 0;
                                $ttl = $entry['ttl'] ?? 0;
                                
                                if ($ttl > 0) {
                                    $expires_at = $creation_time + $ttl;
                                    $remaining = $expires_at - time();
                                    return max(0, $remaining);
                                }
                                
                                return -1; // No TTL (permanent)
                            }
                        }
                    }
                }

                return -2; // Key not found
                
            } catch (Exception $e) {
                return -1;
            }
        }

        /**
         * Increment APCu value (atomic operation)
         */
        public static function apcuIncrement(string $_key, int $step = 1): int|bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return apcu_inc($prefixed_key, $step);
                
            } catch (Exception $e) {
                self::$_last_error = "APCu increment error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Decrement APCu value (atomic operation)
         */
        public static function apcuDecrement(string $_key, int $step = 1): int|bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return apcu_dec($prefixed_key, $step);
                
            } catch (Exception $e) {
                self::$_last_error = "APCu decrement error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * APCu compare and swap (atomic operation)
         */
        public static function apcuCAS(string $_key, mixed $old_value, mixed $new_value): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefixed_key = ($config['prefix'] ?? Cache_Config::getGlobalPrefix()) . $_key;
                
                return apcu_cas($prefixed_key, $old_value, $new_value);
                
            } catch (Exception $e) {
                self::$_last_error = "APCu CAS error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get multiple keys from APCu at once
         */
        public static function apcuMultiGet(array $keys): array {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return [];
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // Fetch all at once
                $results = apcu_fetch($prefixed_keys);
                
                if (!is_array($results)) {
                    return [];
                }

                // Remove prefix from results
                $clean_results = [];
                foreach ($results as $prefixed_key => $value) {
                    $original_key = substr($prefixed_key, strlen($prefix));
                    $clean_results[$original_key] = $value;
                }

                return $clean_results;
                
            } catch (Exception $e) {
                self::$_last_error = "APCu multi-get error: " . $e->getMessage();
                return [];
            }
        }

        /**
         * Set multiple keys in APCu at once
         */
        public static function apcuMultiSet(array $items, int $ttl = 3600): bool {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return false;
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();

                // Prefix all keys
                $prefixed_items = [];
                foreach ($items as $key => $value) {
                    $prefixed_items[$prefix . $key] = $value;
                }

                // Store all at once
                $failed_keys = apcu_store($prefixed_items, null, $ttl);
                
                // Return true if no keys failed
                return empty($failed_keys);
                
            } catch (Exception $e) {
                self::$_last_error = "APCu multi-set error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Delete multiple keys from APCu at once
         */
        public static function apcuMultiDelete(array $keys): array {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return [];
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();

                // Prefix all keys
                $prefixed_keys = array_map(function($key) use ($prefix) {
                    return $prefix . $key;
                }, $keys);

                // Delete all at once
                $result = apcu_delete($prefixed_keys);
                
                if (is_array($result)) {
                    // Remove prefix from failed keys
                    $failed_keys = [];
                    foreach ($result as $prefixed_key) {
                        $failed_keys[] = substr($prefixed_key, strlen($prefix));
                    }
                    return $failed_keys;
                }

                // If result is boolean, return empty array on success
                return $result ? [] : $keys;
                
            } catch (Exception $e) {
                self::$_last_error = "APCu multi-delete error: " . $e->getMessage();
                return $keys; // Return all keys as failed
            }
        }

        /**
         * Get list of APCu keys with our prefix
         */
        public static function getAPCuKeys(): array {
            if (!function_exists('apcu_enabled') || !apcu_enabled()) {
                return [];
            }

            try {
                $config = Cache_Config::get('apcu');
                $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
                $our_keys = [];

                if (function_exists('apcu_cache_info')) {
                    $cache_info = apcu_cache_info();
                    
                    if (isset($cache_info['cache_list'])) {
                        foreach ($cache_info['cache_list'] as $entry) {
                            $key = $entry['info'] ?? $entry['key'] ?? '';
                            
                            if (strpos($key, $prefix) === 0) {
                                $our_keys[] = [
                                    'key' => substr($key, strlen($prefix)),
                                    'full_key' => $key,
                                    'creation_time' => $entry['creation_time'] ?? 0,
                                    'ttl' => $entry['ttl'] ?? 0,
                                    'access_time' => $entry['access_time'] ?? 0,
                                    'ref_count' => $entry['ref_count'] ?? 0,
                                    'mem_size' => $entry['mem_size'] ?? 0
                                ];
                            }
                        }
                    }
                }

                return $our_keys;
                
            } catch (Exception $e) {
                return [];
            }
        }

    }
}