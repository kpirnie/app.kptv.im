<?php
/**
 * KPT Cache - SHMOP Caching Trait
 * Shared memory segment caching implementation
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'Cache_SHMOP' ) ) {

    trait Cache_SHMOP {

        /**
         * Test if shmop shared memory operations are working
         */
        private static function testShmopConnection(): bool {
            
            try {
                $config = Cache_Config::get('shmop');
                
                // Generate a test key
                $test_key = ($config['base_key'] ?? 0x12345000) + 1;
                $test_data = 'test_' . time();
                $serialized_data = serialize([
                    'expires' => time() + 60, 
                    'data' => $test_data
                ]);
                $data_size = strlen($serialized_data);
                
                // Try to create a shared memory segment
                $segment = @shmop_open($test_key, 'c', 0644, max($data_size, 1024));
                
                if ($segment === false) {
                    return false;
                }
                
                // Test write operation
                $written = @shmop_write($segment, str_pad($serialized_data, 1024, "\0"), 0);
                
                if ($written === false) {
                    @shmop_close($segment);
                    @shmop_delete($segment);
                    return false;
                }
                
                // Test read operation
                $read_data = @shmop_read($segment, 0, 1024);
                
                // Clean up
                @shmop_close($segment);
                @shmop_delete($segment);
                
                if ($read_data === false) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize(trim($read_data, "\0"));
                return is_array($unserialized) 
                    && isset($unserialized['data']) 
                    && $unserialized['data'] === $test_data;
                
            } catch (Exception $e) {
                self::$_last_error = "SHMOP test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Generate a unique shmop key for a cache key
         */
        private static function generateShmopKey(string $key): int {
            $config = Cache_Config::get('shmop');
            $prefix = $config['prefix'] ?? Cache_Config::getGlobalPrefix();
            $base_key = $config['base_key'] ?? 0x12345000;
            
            // Create a hash of the key and convert to integer
            $hash = crc32($prefix . $key);
            
            // Ensure it's positive and within a reasonable range
            $shmop_key = $base_key + abs($hash % 100000);
            
            return $shmop_key;
        }

        /**
         * Get item from shmop shared memory
         */
        private static function getFromShmop(string $key): mixed {
            
            // If shmop functions don't exist, just return false
            if (!function_exists('shmop_open')) {
                return false;
            }
            
            try {
                // Generate the shmop key
                $shmop_key = self::generateShmopKey($key);
                
                // Try to open the shared memory segment
                $segment = @shmop_open($shmop_key, 'a', 0, 0);
                
                if ($segment === false) {
                    return false;
                }
                
                // Get the size of the segment
                $size = shmop_size($segment);
                
                if ($size === 0) {
                    @shmop_close($segment);
                    return false;
                }
                
                // Read the data
                $data = shmop_read($segment, 0, $size);
                @shmop_close($segment);
                
                if ($data === false) {
                    return false;
                }
                
                // Unserialize and check expiration
                $unserialized = @unserialize(trim($data, "\0"));
                
                if (is_array($unserialized) && isset($unserialized['expires'], $unserialized['data'])) {
                    // Check if expired
                    if ($unserialized['expires'] > time()) {
                        return $unserialized['data'];
                    } else {
                        // Expired, delete the segment
                        self::deleteFromTierInternal($key, self::TIER_SHMOP);
                    }
                }

            } catch (Exception $e) {
                self::$_last_error = "SHMOP get error: " . $e->getMessage();
            }
            
            return false;
        }

        /**
         * Set item to shmop shared memory
         */
        private static function setToShmop(string $key, mixed $data, int $ttl): bool {
            
            if (!function_exists('shmop_open')) {
                return false;
            }
            
            try {
                $config = Cache_Config::get('shmop');
                
                // Generate the shmop key
                $shmop_key = self::generateShmopKey($key);
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time() + $ttl,
                    'data' => $data
                ];
                
                $serialized_data = serialize($cache_data);
                $data_size = strlen($serialized_data);
                
                // Use configured segment size or data size, whichever is larger
                $segment_size = max($data_size + 100, $config['segment_size'] ?? 1048576);
                
                // Try to open existing segment first
                $segment = @shmop_open($shmop_key, 'w', 0, 0);
                
                // If doesn't exist, create new segment
                if ($segment === false) {
                    $segment = @shmop_open($shmop_key, 'c', 0644, $segment_size);
                }
                
                if ($segment === false) {
                    return false;
                }
                
                // Pad data to prevent issues with reading
                $padded_data = str_pad($serialized_data, $segment_size, "\0");
                
                // Write data
                $written = @shmop_write($segment, $padded_data, 0);
                @shmop_close($segment);
                
                if ($written !== false) {
                    // Keep track of this segment for cleanup
                    self::$_shmop_segments[$key] = $shmop_key;
                    return true;
                }
                
            } catch (Exception $e) {
                self::$_last_error = "SHMOP set error: " . $e->getMessage();
            }
            
            return false;
        }
    }
}