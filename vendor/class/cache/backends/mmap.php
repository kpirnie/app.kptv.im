<?php
/**
 * KPT Cache - MMAP Caching Trait
 * Memory-mapped file caching implementation
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't already exist
if ( ! trait_exists( 'Cache_MMAP' ) ) {

    /**
     * KPT Cache MMAP Trait
     * 
     * Provides memory-mapped file caching functionality for high-performance
     * caching with direct memory access and file-based persistence.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait Cache_MMAP {

        /**
         * Test if memory-mapped file operations are working
         * 
         * Performs a comprehensive test of memory-mapped file operations
         * to ensure the system supports and can perform MMAP caching.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if MMAP operations work, false otherwise
         */
        private static function testMmapConnection( ): bool {
            
            // try to test mmap functionality
            try {

                // ✅ Use KeyManager for consistent key generation
                $test_file = Cache_KeyManager::generateSpecialKey( 'test_' . time(), 'mmap' );
                $test_data = 'test_' . time();
                $serialized_data = serialize( [
                    'expires' => time( ) + 60, 
                    'data' => $test_data
                ] );
                
                // Try to create and write to memory-mapped file
                $file = fopen( $test_file, 'c+b' );
                if ( $file === false ) {
                    return false;
                }
                
                // Lock file for exclusive access
                if ( ! flock( $file, LOCK_EX ) ) {
                    fclose( $file );
                    return false;
                }
                
                // Write data
                fwrite( $file, str_pad( $serialized_data, 1024, "\0" ) );
                
                // Read back
                fseek( $file, 0 );
                $read_data = fread( $file, 1024 );
                
                // Release lock and close
                flock( $file, LOCK_UN );
                fclose( $file );
                
                // Clean up test file
                @unlink( $test_file );
                
                // check if data was read successfully
                if ( $read_data === false ) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize( trim( $read_data, "\0" ) );
                return is_array( $unserialized ) 
                    && isset( $unserialized['data'] ) 
                    && $unserialized['data'] === $test_data;
                
            // whoopsie... setup the error and return false
            } catch ( Exception $e ) {
                self::$_last_error = "MMAP test failed: " . $e -> getMessage( );
                return false;
            }
        }

        /**
         * Get item from memory-mapped file
         * 
         * Retrieves a cached item from a memory-mapped file with proper
         * file locking and expiration checking.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromMmap( string $key ): mixed {

            // try to get item from mmap
            try {

                // Generate the mmap filename
                $filepath = Cache_KeyManager::generateSpecialKey( $key, 'mmap' );
                
                // ✅ FIX: Ensure directory exists before trying to open file
                $dir = dirname( $filepath );
                if ( ! is_dir( $dir ) ) {
                    // Directory doesn't exist, so file definitely doesn't exist
                    return false;
                }
                
                // If the file doesn't exist, return false
                if ( ! file_exists( $filepath ) ) {
                    return false;
                }
                
                // Open the file for reading
                $file = fopen( $filepath, 'rb' );
                if ( $file === false ) {
                    return false;
                }
                
                // Acquire shared lock for reading
                if ( ! flock( $file, LOCK_SH ) ) {
                    fclose( $file );
                    return false;
                }
                
                // Get file size and read data
                $size = filesize( $filepath );
                if ( $size > 0 ) {
                    $data = fread( $file, $size );
                } else {
                    $data = false;
                }
                
                // Release lock and close file
                flock( $file, LOCK_UN );
                fclose( $file );
                
                // check if data was read successfully
                if ( $data === false ) {
                    return false;
                }
                
                // Unserialize and check expiration
                $unserialized = @unserialize( trim( $data, "\0" ) );
                
                // check if we have valid cached data
                if ( is_array( $unserialized ) && isset( $unserialized['expires'], $unserialized['data'] ) ) {

                    // Check if expired
                    if ( $unserialized['expires'] > time( ) ) {
                        return $unserialized['data'];
                    } else {

                        // Expired, delete the file
                        @unlink( $filepath );
                        unset( self::$_mmap_files[$key] );
                    }
                }

            // whoopsie... setup the error
            } catch ( Exception $e ) {
                self::$_last_error = "MMAP get error: " . $e -> getMessage( );
            }
            
            // return false if not found or error
            return false;
        }

        /**
         * Set item to memory-mapped file
         * 
         * Stores an item in a memory-mapped file with expiration timestamp
         * and proper file locking for data integrity.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToMmap( string $key, mixed $data, int $ttl ): bool {

            // try to set item to mmap
            try {

                // get mmap configuration
                $config = Cache_Config::get( 'mmap' );
                
                // Generate the mmap filename
                $filepath = Cache_KeyManager::generateSpecialKey( $key, 'mmap' );
                
                // ✅ IMPROVED: Ensure directory exists with better error handling
                $dir = dirname( $filepath );
                if ( ! is_dir( $dir ) ) {
                    if ( ! @mkdir( $dir, 0755, true ) ) {
                        LOG::error( "MMAP failed to create directory", ['dir' => $dir, 'key' => $key] );
                        return false;
                    }
                }
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time( ) + $ttl,
                    'data' => $data
                ];
                
                // serialize the cache data
                $serialized_data = serialize( $cache_data );
                $data_size = strlen( $serialized_data );
                
                // Use configured file size or data size, whichever is larger
                $file_size = max( $data_size + 100, $config['file_size'] ?? 1048576 );
                
                // Open file for writing (create if doesn't exist)
                $file = fopen( $filepath, 'c+b' );
                if ( $file === false ) {
                    LOG::error( "MMAP failed to open file", ['filepath' => $filepath, 'key' => $key] );
                    return false;
                }
                
                // Acquire exclusive lock
                if ( ! flock( $file, LOCK_EX ) ) {
                    fclose( $file );
                    return false;
                }
                
                // Truncate and write data
                ftruncate( $file, $file_size );
                fseek( $file, 0 );
                $padded_data = str_pad( $serialized_data, $file_size, "\0" );
                $written = fwrite( $file, $padded_data );
                
                // Release lock and close
                flock( $file, LOCK_UN );
                fclose( $file );
                
                // check if write was successful
                if ( $written !== false ) {
                    LOG::debug( "MMAP file created successfully", ['filepath' => $filepath, 'key' => $key] );
                    return true;
                }
                
            // whoopsie... setup the error
            } catch ( Exception $e ) {
                self::$_last_error = "MMAP set error: " . $e -> getMessage( );
                LOG::error( "MMAP set exception", ['error' => $e->getMessage(), 'key' => $key] );
            }
            
            // return false on failure
            return false;
        }

        /**
         * Delete item from MMAP file
         * Updated to use Cache_KeyManager for key generation
         */
        private static function deleteFromMmap( string $key ): bool {
            try {
                // Use KeyManager for consistent key generation
                $file_path = Cache_KeyManager::generateSpecialKey( $key, 'mmap' );
                
                if ( file_exists( $file_path ) ) {
                    return @unlink( $file_path );
                }
                
                return true; // File doesn't exist, consider it deleted
                
            } catch ( Exception $e ) {
                self::$_last_error = "MMAP delete error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Clear all MMAP files
         * Updated to use the same path hierarchy as key generation
         */
        private static function clearMmap( ): bool {
            try {
                $base_path = self::getMmapBasePath( );
                
                if ( ! is_dir( $base_path ) ) {
                    return true; // Directory doesn't exist, nothing to clear
                }
                
                $files = glob( $base_path . '*.mmap' );
                $success = true;
                
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        if ( ! @unlink( $file ) ) {
                            $success = false;
                        }
                    }
                }
                
                return $success;
                
            } catch ( Exception $e ) {
                self::$_last_error = "MMAP clear error: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get MMAP base path for operations
         * Uses the same path hierarchy as the main cache system
         */
        private static function getMmapBasePath(): string {
            // ✅ FIXED: Use the same path hierarchy as generateMmapKey()
            // Priority: 1) Global cache path, 2) MMAP config path, 3) Default temp dir
            
            // Check if there's a global cache path set
            $global_path = Cache_Config::getGlobalPath();
            
            if ( $global_path !== null ) {
                // Use global cache path + mmap subdirectory
                return rtrim( $global_path, '/' ) . '/mmap/';
            } else {
                // Fallback to MMAP-specific config or temp dir
                $config = Cache_Config::get( 'mmap' );
                return $config['path'] ?? sys_get_temp_dir() . '/kpt_mmap/';
            }
        }

    }
}