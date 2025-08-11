<?php
/**
 * KPT Cache - Fixed OPCache Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_OPCache' ) ) {

    trait KPT_Cache_OPCache {

        /**
         * Check if OPcache is properly enabled
         */
        private static function isOPcacheEnabled(): bool {
            // first check if the opcache functions exist
            if (!function_exists('opcache_get_status')) {
                return false;
            }
            
            // just try to get the opcache status
            $status = opcache_get_status(false);

            // return the success of the opcache being enabled
            return is_array($status) && isset($status['opcache_enabled']) && $status['opcache_enabled'];
        }

        /**
         * Get OPcache file path for a given key
         */
        private static function getOPcacheFilePath(string $key): string {
            $config = KPT_Cache_Config::get('opcache');
            $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';
            
            $opcache_key = $prefix . md5($key);
            
            // Use configured path from global config
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
            
            // Ensure cache path exists with proper error handling
            if (!self::ensureOPcacheDirectory($cache_path)) {
                // Fallback to system temp with unique subdirectory
                $cache_path = sys_get_temp_dir() . '/kpt_opcache_' . getmypid() . '/';
                self::ensureOPcacheDirectory($cache_path);
            }
            
            return $cache_path . $opcache_key . '.php';
        }

        /**
         * Ensure OPcache directory exists and is writable
         */
        private static function ensureOPcacheDirectory(string $path): bool {
            try {
                // If directory already exists and is writable, we're good
                if (is_dir($path) && is_writable($path)) {
                    return true;
                }
                
                // Try to create the directory
                if (!is_dir($path)) {
                    if (!mkdir($path, 0755, true)) {
                        return false;
                    }
                }
                
                // Check if it's writable
                if (!is_writable($path)) {
                    // Try to fix permissions
                    @chmod($path, 0755);
                    return is_writable($path);
                }
                
                return true;
                
            } catch (Exception $e) {
                self::$_last_error = "OPcache directory creation failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get item from OPcache
         */
        private static function getFromOPcache(string $_key): mixed {
            // if opcache is not enabled, just return false
            if (!self::isOPcacheEnabled()) {
                return false;
            }
            
            // setup the cache key file using configured path
            $temp_file = self::getOPcacheFilePath($_key);
            
            // if the file does not exist, return false
            if (!file_exists($temp_file)) {
                return false;
            }
            
            // try to retrieve the data
            try {
                // Include the file to get cached data
                $data = include $temp_file;
                
                // if the data is an array
                if (is_array($data) && isset($data['expires'], $data['value'])) {
                    // if it isn't expired yet
                    if ($data['expires'] > time()) {
                        // return the cached value
                        return $data['value'];
                    } else {
                        // otherwise it's expired - remove file
                        @unlink($temp_file);
                        
                        // if the invalidation functionality exists, then invalidate it
                        if (function_exists('opcache_invalidate')) {
                            @opcache_invalidate($temp_file, true);
                        }
                    }
                }
            } catch (Exception $e) {
                // whoopsie... set the last error
                self::$_last_error = "OPcache get error: " . $e->getMessage();
            }
            
            // return false
            return false;
        }

        /**
         * Set item to OPcache with improved error handling
         */
        private static function setToOPcache(string $_key, mixed $_data, int $_length): bool {
            if (!self::isOPcacheEnabled()) {
                return false;
            }
            
            $temp_file = self::getOPcacheFilePath($_key);
            $expires = time() + $_length;
            
            // Ensure the directory exists
            $dir = dirname($temp_file);
            if (!self::ensureOPcacheDirectory($dir)) {
                self::$_last_error = "OPcache: Cannot create or write to directory: {$dir}";
                return false;
            }
            
            // Create the PHP content with proper escaping
            $content = "<?php return " . var_export(['expires' => $expires, 'value' => $_data], true) . ";";
            
            try {
                // Try to write with exclusive lock first
                $result = @file_put_contents($temp_file, $content, LOCK_EX);
                
                // If locking failed, try without lock (some filesystems don't support it)
                if ($result === false) {
                    $result = @file_put_contents($temp_file, $content);
                    
                    if ($result === false) {
                        // Last resort: try with manual locking
                        $result = self::writeOPcacheFileManual($temp_file, $content);
                    }
                }
                
                if ($result !== false) {
                    // Try to compile to OPcache
                    if (function_exists('opcache_compile_file')) {
                        @opcache_compile_file($temp_file);
                    }
                    return true;
                } else {
                    self::$_last_error = "OPcache: Failed to write file: {$temp_file}";
                    return false;
                }
                
            } catch (Exception $e) {
                self::$_last_error = "OPcache set error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Manual file writing with fopen/fwrite as fallback
         */
        private static function writeOPcacheFileManual(string $filepath, string $content): bool {
            try {
                $handle = fopen($filepath, 'w');
                if ($handle === false) {
                    return false;
                }
                
                // Try to get exclusive lock
                $locked = flock($handle, LOCK_EX);
                
                $result = fwrite($handle, $content);
                
                if ($locked) {
                    flock($handle, LOCK_UN);
                }
                
                fclose($handle);
                
                return $result !== false;
                
            } catch (Exception $e) {
                self::$_last_error = "OPcache manual write error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Delete item from OPcache
         */
        private static function deleteFromOPcache(string $_key): bool {
            $temp_file = self::getOPcacheFilePath($_key);
            
            if (file_exists($temp_file)) {
                // Invalidate from OPcache first
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($temp_file, true);
                }
                
                // Remove the file
                return @unlink($temp_file);
            }
            
            return true; // File doesn't exist, consider it deleted
        }

        /**
         * Clear all OPcache files for this application
         */
        private static function clearOPcache(): bool {
            $config = KPT_Cache_Config::get('opcache');
            $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';
            
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
            
            // Find all files with our prefix
            $pattern = $cache_path . $prefix . '*.php';
            $files = glob($pattern);
            
            if ($files === false) {
                return true; // No files found or glob failed
            }
            
            $success = true;
            
            foreach ($files as $file) {
                if (!is_file($file)) continue;
                
                // Invalidate from OPcache
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($file, true);
                }
                
                // Remove the file
                if (!@unlink($file)) {
                    $success = false;
                }
            }
            
            // Also try global OPcache reset if available
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            return $success;
        }

        /**
         * Get OPcache statistics with error handling
         */
        private static function getOPcacheStats(): array {
            if (!function_exists('opcache_get_status')) {
                return ['error' => 'OPcache not available'];
            }
            
            try {
                $stats = opcache_get_status(true);
                
                if (!$stats) {
                    return ['error' => 'OPcache not enabled'];
                }
                
                // Add our specific file count
                $config = KPT_Cache_Config::get('opcache');
                $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';
                $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
                $pattern = $cache_path . $prefix . '*.php';
                $our_files = glob($pattern);
                
                $stats['kpt_cache_files'] = [
                    'count' => is_array($our_files) ? count($our_files) : 0,
                    'total_size' => is_array($our_files) ? array_sum(array_map('filesize', array_filter($our_files, 'is_file'))) : 0,
                    'prefix' => $prefix,
                    'path' => $cache_path,
                    'path_writable' => is_writable($cache_path),
                    'path_exists' => is_dir($cache_path)
                ];
                
                return $stats;
                
            } catch (Exception $e) {
                return ['error' => 'Failed to get OPcache stats: ' . $e->getMessage()];
            }
        }

        /**
         * Test OPcache functionality with improved diagnostics
         */
        private static function testOPcacheConnection(): bool {
            if (!self::isOPcacheEnabled()) {
                return false;
            }
            
            try {
                $test_key = 'opcache_test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                // Try to store and retrieve
                if (self::setToOPcache($test_key, $test_value, 60)) {
                    $retrieved = self::getFromOPcache($test_key);
                    self::deleteFromOPcache($test_key); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            } catch (Exception $e) {
                self::$_last_error = "OPcache test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Clean up expired OPcache files with better error handling
         */
        private static function cleanupOPcacheFiles(): int {
            $config = KPT_Cache_Config::get('opcache');
            $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
            
            $pattern = $cache_path . $prefix . '*.php';
            $files = glob($pattern);
            $cleaned = 0;
            
            if (!is_array($files)) {
                return 0;
            }
            
            foreach ($files as $file) {
                if (!is_file($file)) continue;
                
                try {
                    // Include the file to check expiration
                    $data = @include $file;
                    
                    if (is_array($data) && isset($data['expires'])) {
                        if ($data['expires'] <= time()) {
                            // Expired - invalidate and remove
                            if (function_exists('opcache_invalidate')) {
                                @opcache_invalidate($file, true);
                            }
                            
                            if (@unlink($file)) {
                                $cleaned++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If we can't read the file, it might be corrupted - remove it
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($file, true);
                    }
                    
                    if (@unlink($file)) {
                        $cleaned++;
                    }
                }
            }
            
            return $cleaned;
        }

        /**
         * Get OPcache file list with details and error handling
         */
        private static function getOPcacheFileList(): array {
            $config = KPT_Cache_Config::get('opcache');
            $prefix = $config['prefix'] ?? 'KPT_OPCACHE_';
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
            
            $pattern = $cache_path . $prefix . '*.php';
            $files = glob($pattern);
            $file_details = [];
            
            if (!is_array($files)) {
                return $file_details;
            }
            
            foreach ($files as $file) {
                if (!is_file($file)) continue;
                
                try {
                    $data = @include $file;
                    $file_info = [
                        'file' => basename($file),
                        'full_path' => $file,
                        'size' => filesize($file),
                        'created' => filectime($file),
                        'modified' => filemtime($file),
                        'expires' => null,
                        'expired' => false,
                        'valid' => false,
                        'readable' => is_readable($file),
                        'writable' => is_writable($file)
                    ];
                    
                    if (is_array($data) && isset($data['expires'])) {
                        $file_info['expires'] = $data['expires'];
                        $file_info['expired'] = $data['expires'] <= time();
                        $file_info['valid'] = true;
                    }
                    
                    $file_details[] = $file_info;
                    
                } catch (Exception $e) {
                    $file_details[] = [
                        'file' => basename($file),
                        'full_path' => $file,
                        'size' => filesize($file),
                        'created' => filectime($file),
                        'modified' => filemtime($file),
                        'error' => $e->getMessage(),
                        'valid' => false,
                        'readable' => is_readable($file),
                        'writable' => is_writable($file)
                    ];
                }
            }
            
            return $file_details;
        }

        /**
         * Diagnostic method for OPcache issues
         */
        private static function diagnoseOPcache(): array {
            $config = KPT_Cache_Config::get('opcache');
            $cache_path = $config['path'] ?? sys_get_temp_dir() . '/kpt_cache/';
            
            $diagnosis = [
                'opcache_available' => function_exists('opcache_get_status'),
                'opcache_enabled' => self::isOPcacheEnabled(),
                'cache_path' => $cache_path,
                'path_exists' => false,
                'path_writable' => false,
                'path_readable' => false,
                'php_version' => PHP_VERSION,
                'issues' => [],
                'recommendations' => []
            ];
            
            // Check path status
            $diagnosis['path_exists'] = is_dir($cache_path);
            $diagnosis['path_writable'] = is_writable($cache_path);
            $diagnosis['path_readable'] = is_readable($cache_path);
            
            // Identify issues
            if (!$diagnosis['opcache_available']) {
                $diagnosis['issues'][] = 'OPcache extension not loaded';
                $diagnosis['recommendations'][] = 'Install and enable PHP OPcache extension';
            }
            
            if (!$diagnosis['opcache_enabled']) {
                $diagnosis['issues'][] = 'OPcache not enabled';
                $diagnosis['recommendations'][] = 'Enable OPcache in php.ini with opcache.enable=1';
            }
            
            if (!$diagnosis['path_exists']) {
                $diagnosis['issues'][] = 'Cache directory does not exist';
                $diagnosis['recommendations'][] = "Create directory: {$cache_path}";
            }
            
            if (!$diagnosis['path_writable']) {
                $diagnosis['issues'][] = 'Cache directory not writable';
                $diagnosis['recommendations'][] = "Fix permissions: chmod 755 {$cache_path}";
            }
            
            return $diagnosis;
        }
    }
}
