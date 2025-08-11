<?php
/**
 * KPT Cache - MMAP Caching Trait
 * Memory-mapped file caching implementation
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'KPT_Cache_MMAP' ) ) {

    trait KPT_Cache_MMAP {

        /**
         * Test if memory-mapped file operations are working
         */
        private static function testMmapConnection(): bool {
            
            try {
                // Get mmap base path
                $base_path = self::getMmapBasePath();
                
                // Test file path
                $test_file = $base_path . 'test_' . uniqid() . '.mmap';
                $test_data = 'test_' . time();
                $serialized_data = serialize([
                    'expires' => time() + 60, 
                    'data' => $test_data
                ]);
                
                // Try to create and write to memory-mapped file
                $file = fopen($test_file, 'c+b');
                if ($file === false) {
                    return false;
                }
                
                // Lock file for exclusive access
                if (!flock($file, LOCK_EX)) {
                    fclose($file);
                    return false;
                }
                
                // Write data
                fwrite($file, str_pad($serialized_data, 1024, "\0"));
                
                // Read back
                fseek($file, 0);
                $read_data = fread($file, 1024);
                
                // Release lock and close
                flock($file, LOCK_UN);
                fclose($file);
                
                // Clean up test file
                @unlink($test_file);
                
                if ($read_data === false) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize(trim($read_data, "\0"));
                return is_array($unserialized) 
                    && isset($unserialized['data']) 
                    && $unserialized['data'] === $test_data;
                
            } catch (Exception $e) {
                self::$_last_error = "MMAP test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get the base path for mmap files
         */
        private static function getMmapBasePath(): string {
            $config = KPT_Cache_Config::get('mmap');
            
            // Use configured path or default to temp directory
            $base_path = $config['base_path'] ?: sys_get_temp_dir() . '/kpt_mmap/';
            
            // Ensure path ends with slash
            $base_path = rtrim($base_path, '/') . '/';
            
            // Create directory if it doesn't exist
            if (!file_exists($base_path)) {
                @mkdir($base_path, 0755, true);
            }
            
            return $base_path;
        }

        /**
         * Generate a unique mmap filename for a cache key
         */
        private static function generateMmapKey(string $key): string {
            $config = KPT_Cache_Config::get('mmap');
            $prefix = $config['prefix'] ?? 'KPTV_APP:';
            
            // Create a hash of the key for filename
            $hash = md5($prefix . $key);
            
            return $hash . '.mmap';
        }

        /**
         * Get item from memory-mapped file
         */
        private static function getFromMmap(string $key): mixed {
            try {
                // Generate the mmap filename
                $filename = self::generateMmapKey($key);
                $filepath = self::getMmapBasePath() . $filename;
                
                // If the file doesn't exist, return false
                if (!file_exists($filepath)) {
                    return false;
                }
                
                // Open the file for reading
                $file = fopen($filepath, 'rb');
                if ($file === false) {
                    return false;
                }
                
                // Acquire shared lock for reading
                if (!flock($file, LOCK_SH)) {
                    fclose($file);
                    return false;
                }
                
                // Get file size and read data
                $size = filesize($filepath);
                if ($size > 0) {
                    $data = fread($file, $size);
                } else {
                    $data = false;
                }
                
                // Release lock and close file
                flock($file, LOCK_UN);
                fclose($file);
                
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
                        // Expired, delete the file
                        @unlink($filepath);
                        unset(self::$_mmap_files[$key]);
                    }
                }

            } catch (Exception $e) {
                self::$_last_error = "MMAP get error: " . $e->getMessage();
            }
            
            return false;
        }

        /**
         * Set item to memory-mapped file
         */
        private static function setToMmap(string $key, mixed $data, int $ttl): bool {
            try {
                $config = KPT_Cache_Config::get('mmap');
                
                // Generate the mmap filename
                $filename = self::generateMmapKey($key);
                $filepath = self::getMmapBasePath() . $filename;
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time() + $ttl,
                    'data' => $data
                ];
                
                $serialized_data = serialize($cache_data);
                $data_size = strlen($serialized_data);
                
                // Use configured file size or data size, whichever is larger
                $file_size = max($data_size + 100, $config['file_size'] ?? 1048576);
                
                // Open file for writing (create if doesn't exist)
                $file = fopen($filepath, 'c+b');
                if ($file === false) {
                    return false;
                }
                
                // Acquire exclusive lock
                if (!flock($file, LOCK_EX)) {
                    fclose($file);
                    return false;
                }
                
                // Truncate and write data
                ftruncate($file, $file_size);
                fseek($file, 0);
                $padded_data = str_pad($serialized_data, $file_size, "\0");
                $written = fwrite($file, $padded_data);
                
                // Release lock and close
                flock($file, LOCK_UN);
                fclose($file);
                
                if ($written !== false) {
                    // Keep track of this file for cleanup
                    self::$_mmap_files[$key] = $filepath;
                    return true;
                }
                
            } catch (Exception $e) {
                self::$_last_error = "MMAP set error: " . $e->getMessage();
            }
            
            return false;
        }
    }
}