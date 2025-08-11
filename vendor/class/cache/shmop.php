<?php
/**
 * KPT Cache - SHMOP Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_SHMOP' ) ) {

    trait KPT_Cache_SHMOP {

        // shmop settings
        private static $_shmop_settings = [
            'prefix' => 'KPTV_APP:',
            'segment_size' => 1048576, // 1MB default segment size
            'base_key' => 0x12345000, // Base key for shared memory segments
        ];

        /**
         * testShmopConnection
         * 
         * Test if shmop shared memory operations are working
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if shmop is available and working
         */
        private static function testShmopConnection( ) : bool {
            
            // try to use shmop functionality
            try {

                // Generate a test key
                $test_key = self::$_shmop_settings['base_key'] + 1;
                $test_data = 'test_' . time( );
                $serialized_data = serialize( ['expires' => time( ) + 60, 'data' => $test_data] );
                $data_size = strlen( $serialized_data );
                
                // Try to create a shared memory segment
                $segment = @shmop_open( $test_key, 'c', 0644, max( $data_size, 1024 ) );
                
                if ( $segment === false ) {
                    return false;
                }
                
                // Test write operation
                $written = @shmop_write( $segment, str_pad( $serialized_data, 1024, "\0" ), 0 );
                
                if ( $written === false ) {
                    @shmop_close( $segment );
                    @shmop_delete( $segment );
                    return false;
                }
                
                // Test read operation
                $read_data = @shmop_read( $segment, 0, 1024 );
                
                // Clean up
                @shmop_close( $segment );
                @shmop_delete( $segment );
                
                if ( $read_data === false ) {
                    return false;
                }
                
                // Verify data integrity
                $unserialized = @unserialize( trim( $read_data, "\0" ) );
                return is_array( $unserialized ) && isset( $unserialized['data'] ) && $unserialized['data'] === $test_data;
                
            // whoopsie... no good; set a message and return false
            } catch ( Exception $e ) {
                self::$_last_error = "shmop test failed: " . $e -> getMessage( );
                return false;
            }

        }

        /**
         * generateShmopKey
         * 
         * Generate a unique shmop key for a cache key
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key
         * @return int Returns the shmop key
         */
        private static function generateShmopKey( string $_key ) : int {

            // Create a hash of the key and convert to integer
            $hash = crc32( self::$_shmop_settings['prefix'] . $_key );
            
            // Ensure it's positive and within a reasonable range
            $key = self::$_shmop_settings['base_key'] + abs( $hash % 100000 );
            
            return $key;

        }

        /**
         * setShmopSettings
         * 
         * Configure shmop shared memory settings
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $settings shmop configuration array
         * @return bool Returns true if settings were applied successfully
         */
        public static function setShmopSettings( array $settings ): bool {
            
            // Merge with defaults
            self::$_shmop_settings = array_merge( self::$_shmop_settings, $settings );
            
            return true;
        }

        /**
         * getFromShmop
         * 
         * Get item from shmop shared memory
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromShmop( string $_key ): mixed {

            // if shmop functions don't exist, just return false
            if ( ! function_exists( 'shmop_open' ) ) {
                return false;
            }
            
            // try to retrieve the data
            try {
            
                // generate the shmop key
                $shmop_key = self::generateShmopKey( $_key );
                
                // try to open the shared memory segment
                $segment = @shmop_open( $shmop_key, 'a', 0, 0 );
                
                if ( $segment === false ) {
                    return false;
                }
                
                // get the size of the segment
                $size = shmop_size( $segment );
                
                if ( $size === 0 ) {
                    shmop_close( $segment );
                    return false;
                }
                
                // read the data
                $data = shmop_read( $segment, 0, $size );
                shmop_close( $segment );
                
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
                        // expired, delete the segment
                        self::delFromTier( $_key, self::TIER_SHMOP );
                    }
                }

            // whoopsie... set the last error
            } catch ( Exception $e ) {
                self::$_last_error = "shmop get error: " . $e->getMessage( );
            }
            
            // return false
            return false;

        }

        /**
         * setToShmop
         * 
         * Set item to shmop shared memory
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
        private static function setToShmop( string $_key, mixed $_data, int $_length ): bool {
            
            if ( ! function_exists( 'shmop_open' ) ) {
                return false;
            }
            
            try {
                // Generate the shmop key
                $shmop_key = self::generateShmopKey( $_key );
                
                // Prepare data with expiration
                $cache_data = [
                    'expires' => time( ) + $_length,
                    'data' => $_data
                ];
                
                $serialized_data = serialize( $cache_data );
                $data_size = strlen( $serialized_data );
                
                // Use configured segment size or data size, whichever is larger
                $segment_size = max( $data_size + 100, self::$_shmop_settings['segment_size'] );
                
                // Try to open existing segment first
                $segment = @shmop_open( $shmop_key, 'w', 0, 0 );
                
                // If doesn't exist, create new segment
                if ( $segment === false ) {
                    $segment = @shmop_open( $shmop_key, 'c', 0644, $segment_size );
                }
                
                if ( $segment === false ) {
                    return false;
                }
                
                // Pad data to prevent issues with reading
                $padded_data = str_pad( $serialized_data, $segment_size, "\0" );
                
                // Write data
                $written = @shmop_write( $segment, $padded_data, 0 );
                @shmop_close( $segment );
                
                if ( $written !== false ) {
                    // Keep track of this segment for cleanup
                    self::$_shmop_segments[$_key] = $shmop_key;
                    return true;
                }
                
            } catch ( Exception $e ) {
                self::$_last_error = "shmop set error: " . $e->getMessage( );
            }
            
            return false;
        }
        
    }

}
        