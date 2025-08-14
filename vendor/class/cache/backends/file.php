<?php
/**
 * KPT Cache - File Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'Cache_File' ) ) {

    trait Cache_File {


        
        /**
         * Attempt to create a cache directory with proper permissions
         */
        private static function createCacheDirectory(string $path): bool {
            $attempts = 0;
            $max_attempts = 3;

            // Normalize the path (ensure it ends with a slash)
            $path = rtrim($path, '/') . '/';

            while ($attempts < $max_attempts) {
                try {
                    // Check if directory already exists and is writable
                    if (file_exists($path)) {
                        if (is_dir($path) && is_writable($path)) {
                            return true;
                        } elseif (is_dir($path)) {
                            // Try to fix permissions
                            if (@chmod($path, 0755) && is_writable($path)) {
                                return true;
                            }
                        }
                        $attempts++;
                        continue;
                    }
                    
                    // Try to create the directory
                    if (@mkdir($path, 0755, true)) {
                        // Ensure it's writable
                        if (is_writable($path)) {
                            return true;
                        }
                        
                        // Try to fix permissions
                        if (@chmod($path, 0755) && is_writable($path)) {
                            return true;
                        }
                        
                        // Try more permissive permissions
                        if (@chmod($path, 0777) && is_writable($path)) {
                            return true;
                        }
                    }
                    
                } catch (Exception $e) {
                    self::$_last_error = "Cache directory creation failed: " . $e->getMessage();
                }
                
                $attempts++;
                
                // Small delay between attempts
                if ($attempts < $max_attempts) {
                    usleep(100000); // 100ms
                }
            }
            
            return false;
        }

        /**
         * Set a custom cache path for file-based caching
         */
        public static function setCachePath(string $_path): bool {
            // Normalize the path (ensure it ends with a slash)
            $_path = rtrim($_path, '/') . '/';
            
            // Try to create the cache directory with proper permissions
            $config = Cache_Config::get('file');
            $permissions = $config['permissions'] ?? 0755;
            
            if (self::createCacheDirectory($_path, $permissions)) {
                // Update the configuration
                Cache_Config::set('file', array_merge($config, ['path' => $_path]));
                
                self::$_configurable_cache_path = $_path;
                
                // If we're already initialized, update the fallback path immediately
                if (self::$_initialized) {
                    self::$_fallback_path = $_path;
                }
                
                return true;
            }
            
            return false;
        }

        /**
         * Get the current cache path being used
         */
        public static function getCachePath(): string {
            return self::$_fallback_path ?? sys_get_temp_dir() . '/kpt_cache/';
        }

        /**
         * Get item from file cache
         */
        private static function getFromFile(string $_key): mixed {
            // Setup the cache file
            $file = self::getCachePath() . md5($_key);
            
            // If it exists
            if (file_exists($file)) {
                try {
                    // Get the data from the file's contents with lock
                    $handle = fopen($file, 'rb');
                    if ($handle === false) {
                        return false;
                    }

                    // Lock file for reading
                    if (!flock($handle, LOCK_SH)) {
                        fclose($handle);
                        return false;
                    }

                    $data = fread($handle, filesize($file));
                    flock($handle, LOCK_UN);
                    fclose($handle);

                    if ($data === false) {
                        return false;
                    }

                    // Setup its expiry
                    $expires = substr($data, 0, 10);
                    
                    // Is it supposed to expire
                    if (is_numeric($expires) && time() > (int)$expires) {
                        // Delete it and return false
                        @unlink($file);
                        return false;
                    }
                    
                    // Return the unserialized data
                    return unserialize(substr($data, 10));

                } catch (Exception $e) {
                    self::$_last_error = "File cache read error: " . $e->getMessage();
                    return false;
                }
            }
            
            return false;
        }

        /**
         * Set item to file cache
         */
        private static function setToFile(string $_key, mixed $_data, int $_length): bool {
            $file = self::getCachePath() . md5($_key);
            $expires = time() + $_length;
            $data = $expires . serialize($_data);
            
            try {
                // Write with exclusive lock
                $result = file_put_contents($file, $data, LOCK_EX);
                return $result !== false;

            } catch (Exception $e) {
                self::$_last_error = "File cache write error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Delete item from file cache
         */
        private static function deleteFromFile(string $_key): bool {
            $file = self::getCachePath() . md5($_key);
            
            if (file_exists($file)) {
                return @unlink($file);
            }
            
            return true; // File doesn't exist, consider it deleted
        }

        /**
         * Clear all file cache
         */
        private static function clearFileCache(): bool {
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            $success = true;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (!@unlink($file)) {
                        $success = false;
                    }
                }
            }
            
            return $success;
        }

        /**
         * Get detailed information about the cache path and permissions
         */
        public static function getCachePathInfo(): array {
            $path = self::getCachePath();
            
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
            
            if ($path) {
                $info['exists'] = file_exists($path);
                $info['is_dir'] = is_dir($path);
                $info['is_writable'] = is_writable($path);
                $info['is_readable'] = is_readable($path);
                
                if ($info['exists']) {
                    $info['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
                    if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
                        $owner_info = posix_getpwuid(fileowner($path));
                        $info['owner'] = $owner_info ? $owner_info['name'] : fileowner($path);
                    }
                }
                
                // Check if parent directory is writable
                $parent = dirname(rtrim($path, '/'));
                $info['parent_writable'] = is_writable($parent);
                $info['parent_path'] = $parent;
            }
            
            return $info;
        }

        /**
         * Attempt to fix cache directory permissions
         */
        public static function fixCachePermissions(): bool {
            $path = self::getCachePath();
            
            if (!$path || !file_exists($path)) {
                return false;
            }
            
            try {
                // Try different permission levels
                $permission_levels = [0755, 0775, 0777];
                
                foreach ($permission_levels as $perms) {
                    if (@chmod($path, $perms)) {
                        if (is_writable($path)) {
                            return true;
                        }
                    }
                }
                
                // If chmod failed, try recreating the directory
                if (is_dir($path)) {
                    // Try to remove and recreate (only if empty or only contains cache files)
                    $files = glob($path . '*');
                    $safe_to_recreate = true;
                    
                    // Check if all files look like cache files (md5 hashes)
                    foreach ($files as $file) {
                        $basename = basename($file);
                        if (!preg_match('/^[a-f0-9]{32}$/', $basename)) {
                            $safe_to_recreate = false;
                            break;
                        }
                    }
                    
                    if ($safe_to_recreate) {
                        // Remove cache files
                        foreach ($files as $file) {
                            @unlink($file);
                        }
                        
                        // Remove directory and recreate
                        if (@rmdir($path)) {
                            return self::createCacheDirectory($path);
                        }
                    }
                }
                
            } catch (Exception $e) {
                self::$_last_error = "Permission fix failed: " . $e->getMessage();
            }
            
            return false;
        }

        /**
         * Get suggested alternative cache paths for troubleshooting
         */
        public static function getSuggestedCachePaths(): array {
            $suggestions = [
                'current' => self::getCachePath(),
                'alternatives' => []
            ];
            
            $test_paths = [
                sys_get_temp_dir() . '/kpt_cache_alt/',
                getcwd() . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_alt/',
                sys_get_temp_dir() . '/cache/',
            ];
            
            foreach ($test_paths as $path) {
                $status = [
                    'path' => $path,
                    'parent_exists' => file_exists(dirname($path)),
                    'parent_writable' => is_writable(dirname($path)),
                    'can_create' => false,
                    'recommended' => false
                ];
                
                // Test if we can create a test directory
                $test_dir = $path . 'test_' . uniqid();
                if (@mkdir($test_dir, 0755, true)) {
                    $status['can_create'] = true;
                    $status['recommended'] = is_writable($test_dir);
                    @rmdir($test_dir);
                }
                
                $suggestions['alternatives'][] = $status;
            }
            
            return $suggestions;
        }

        /**
         * Get file cache statistics
         */
        private static function getFileCacheStats(): array {
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            
            $stats = [
                'path' => $cache_path,
                'total_files' => 0,
                'total_size' => 0,
                'total_size_human' => '0 B',
                'expired_files' => 0,
                'valid_files' => 0,
                'oldest_file' => null,
                'newest_file' => null
            ];

            if (!is_array($files)) {
                return $stats;
            }

            $stats['total_files'] = count($files);
            $now = time();
            $oldest = null;
            $newest = null;

            foreach ($files as $file) {
                if (!is_file($file)) continue;

                $size = filesize($file);
                $stats['total_size'] += $size;

                $mtime = filemtime($file);
                if ($oldest === null || $mtime < $oldest) {
                    $oldest = $mtime;
                }
                if ($newest === null || $mtime > $newest) {
                    $newest = $mtime;
                }

                // Check if file is expired by reading expiration timestamp
                try {
                    $handle = fopen($file, 'rb');
                    if ($handle) {
                        $expires_data = fread($handle, 10);
                        fclose($handle);

                        if (is_numeric($expires_data)) {
                            $expires = (int)$expires_data;
                            if ($now > $expires) {
                                $stats['expired_files']++;
                            } else {
                                $stats['valid_files']++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Skip files we can't read
                }
            }

            $stats['total_size_human'] = KPT::format_bytes($stats['total_size']);
            $stats['oldest_file'] = $oldest ? date('Y-m-d H:i:s', $oldest) : null;
            $stats['newest_file'] = $newest ? date('Y-m-d H:i:s', $newest) : null;

            return $stats;
        }

        /**
         * Clean up expired file cache entries
         */
        private static function cleanupExpiredFiles(): int {
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            $cleaned = 0;
            $now = time();

            if (!is_array($files)) {
                return 0;
            }

            foreach ($files as $file) {
                if (!is_file($file)) continue;

                try {
                    $handle = fopen($file, 'rb');
                    if (!$handle) continue;

                    $expires_data = fread($handle, 10);
                    fclose($handle);

                    if (is_numeric($expires_data)) {
                        $expires = (int)$expires_data;
                        if ($now > $expires) {
                            if (@unlink($file)) {
                                $cleaned++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Skip files we can't process
                }
            }

            return $cleaned;
        }

        /**
         * Get list of cache files with details
         */
        public static function getFileCacheList(): array {
            $cache_path = self::getCachePath();
            $files = glob($cache_path . '*');
            $file_list = [];
            $now = time();

            if (!is_array($files)) {
                return [];
            }

            foreach ($files as $file) {
                if (!is_file($file)) continue;

                $file_info = [
                    'filename' => basename($file),
                    'full_path' => $file,
                    'size' => filesize($file),
                    'size_human' => KPT::format_bytes(filesize($file)),
                    'created' => filectime($file),
                    'modified' => filemtime($file),
                    'expires' => null,
                    'expired' => false,
                    'ttl_remaining' => null,
                    'valid' => false
                ];

                // Try to read expiration info
                try {
                    $handle = fopen($file, 'rb');
                    if ($handle) {
                        $expires_data = fread($handle, 10);
                        fclose($handle);

                        if (is_numeric($expires_data)) {
                            $expires = (int)$expires_data;
                            $file_info['expires'] = $expires;
                            $file_info['expired'] = $now > $expires;
                            $file_info['ttl_remaining'] = max(0, $expires - $now);
                            $file_info['valid'] = true;
                        }
                    }
                } catch (Exception $e) {
                    $file_info['error'] = $e->getMessage();
                }

                $file_list[] = $file_info;
            }

            // Sort by modification time (newest first)
            usort($file_list, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });

            return $file_list;
        }

        /**
         * Test file cache functionality
         */
        private static function testFileCacheConnection(): bool {
            try {
                $test_key = 'file_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                // Try to store and retrieve
                if (self::setToFile($test_key, $test_value, 60)) {
                    $retrieved = self::getFromFile($test_key);
                    self::deleteFromFile($test_key); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            } catch (Exception $e) {
                self::$_last_error = "File cache test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Backup cache directory
         */
        public static function backupCache(string $backup_path): bool {
            $source_path = self::getCachePath();
            
            if (!is_dir($source_path)) {
                return false;
            }

            try {
                // Create backup directory
                if (!is_dir($backup_path)) {
                    if (!mkdir($backup_path, 0755, true)) {
                        return false;
                    }
                }

                $files = glob($source_path . '*');
                if (!is_array($files)) {
                    return true; // No files to backup
                }

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $filename = basename($file);
                        $destination = $backup_path . '/' . $filename;
                        
                        if (!copy($file, $destination)) {
                            return false;
                        }
                    }
                }

                return true;

            } catch (Exception $e) {
                self::$_last_error = "Cache backup failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Restore cache from backup
         */
        public static function restoreCache(string $backup_path): bool {
            $target_path = self::getCachePath();
            
            if (!is_dir($backup_path)) {
                return false;
            }

            try {
                // Ensure target directory exists
                if (!is_dir($target_path)) {
                    if (!self::createCacheDirectory($target_path)) {
                        return false;
                    }
                }

                $files = glob($backup_path . '/*');
                if (!is_array($files)) {
                    return true; // No files to restore
                }

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $filename = basename($file);
                        $destination = $target_path . $filename;
                        
                        if (!copy($file, $destination)) {
                            return false;
                        }
                    }
                }

                return true;

            } catch (Exception $e) {
                self::$_last_error = "Cache restore failed: " . $e->getMessage();
                return false;
            }
        }
    }
}