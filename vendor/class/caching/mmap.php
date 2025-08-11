<?php
/**
 * KPT Cache - MMAP Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Caching_MMAP' ) ) {

    trait KPT_Caching_MMAP {

        // mmap settings
        private static $_mmap_settings = [
            'prefix' => 'KPTV_APP:',
            'base_path' => null, // Will use temp dir if null
            'file_size' => 1048576, // 1MB default file size
            'max_files' => 1000, // Maximum number of mmap files
        ];

        /**
         * testMmapConnection
         * 
         * Test if memory-mapped file operations are working
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if mmap is available and working
         */
        private static function testMmapConnection( ) : bool {
            
            // try to use mmap functionality
            try {

                // Get mmap base path
                $base_path = self::getMmapBasePath( );
                
                // Test file path
                $test_file = $base_path . 'test_' . uniqid( ) . '.mmap';
                $test_data = 'test_' . time( );
                $serialized_data = serialize( ['expires' => time( ) + 60, 'data' => $test_data] );
                
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
                
                if ( $read_data === false ) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize( trim( $read_data, "\0" ) );
                return is_array( $unserialized ) && isset( $unserialized['data'] ) && $unserialized['data'] === $test_data;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "mmap test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * getMmapBasePath
         * 
         * Get the base path for mmap files
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns the mmap base path
         */
        private static function getMmapBasePath( ) : string {

            // Use configured path or default to temp directory
            $base_path = self::$_mmap_settings['base_path'] ?: sys_get_temp_dir( ) . '/kpt_mmap/';
            
            // Ensure path ends with slash
            $base_path = rtrim( $base_path, '/' ) . '/';
            
            // Create directory if it doesn't exist
            if ( ! file_exists( $base_path ) ) {
                @mkdir( $base_path, 0755, true );
            }
            
            return $base_path;

        }

        /**
         * generateMmapKey
         * 
         * Generate a unique mmap filename for a cache key
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key
         * @return string Returns the mmap filename
         */
        private static function generateMmapKey( string $_key ) : string {

            // Create a hash of the key for filename
            $hash = md5( self::$_mmap_settings['prefix'] . $_key );
            
            return $hash . '.mmap';

        }

        /**
         * setMmapSettings
         * 
         * Configure mmap settings
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings mmap configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setMmapSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_mmap_settings = array_merge( self::$_mmap_settings, $settings );
            
            return true;
        }

        /**
         * getFromMmap
         * 
         * Get item from memory-mapped file
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromMmap( string $_key ): mixed {

            // try to retrieve the data
            try {
            
                // generate the mmap filename
                $filename = self::generateMmapKey( $_key );
                $filepath = self::getMmapBasePath( ) . $filename;
                
                // if the file doesn't exist, return false
                if ( ! file_exists( $filepath ) ) {
                    return false;
                }
                
                // open the file for reading
                $file = fopen( $filepath, 'rb' );
                if ( $file === false ) {
                    return false;
                }
                
                // acquire shared lock for reading
                if ( ! flock( $file, LOCK_SH ) ) {
                    fclose( $file );
                    return false;
                }
                
                // get file size and read data
                $size = filesize( $filepath );
                if ( $size > 0 ) {
                    $data = fread( $file, $size );
                } else {
                    $data = false;
                }
                
                // release lock and close file
                flock( $file, LOCK_UN );
                fclose( $file );
                
                if ( $data === false ) {
                    return false;
                }
                
                // unserialize and check expiration
                $unserialized = @unserialize( trim( $data, "\0" ) );
                
                if ( is_array( $unserialized ) && isset( $unserialized['expires'], $unserialized['data'] ) ) {
                    
                    // check if expired
                    if ( $unserialized['expires'] > time( ) ) {
                        return $unserialized['data'];
                    } else {
                        // expired, delete the file
                        @unlink( $filepath );
                        unset( self::$_mmap_files[$_key] );
                    }
                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "mmap get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * setToMmap
         * 
         * Set item to memory-mapped file
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @param mixed $_data The data to cache
         * @param int $_length TTL in seconds
         * @return bool Returns true if successful
         */
        private static function setToMmap( string $_key, mixed $_data, int $_length ): bool {
            
            try {
                // Generate the mmap filename
                $filename = self::generateMmapKey( $_key );
                $filepath = self::getMmapBasePath( ) . $filename;
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time( ) + $_length,
                    'data' => $_data
                ];
                
                $serialized_data = serialize( $cache_data );
                $data_size = strlen( $serialized_data );
                
                // Use configured file size or data size, whichever is larger
                $file_size = max( $data_size + 100, self::$_mmap_settings['file_size'] );
                
                // Open file for writing (create if doesn't exist)
                $file = fopen( $filepath, 'c+b' );
                if ( $file === false ) {
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
                
                if ( $written !== false ) {
                    // Keep track of this file for cleanup
                    self::$_mmap_files[$_key] = $filepath;
                    return true;
                }
                
            } catch ( Exception $e ) {
                self::$_last_error = "mmap set error: " . $e->getMessage( );
            }
            
            return false;
        }        
        
    }

}
        