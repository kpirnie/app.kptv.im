<?php
/**
 * KPT Cache - File Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_File' ) ) {

    trait KPT_Cache_File {


        /**
         * createCacheDirectory
         * 
         * Attempt to create a cache directory with proper permissions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $path The directory path to create
         * @return bool Returns true if directory was created and is writable
         */
        private static function createCacheDirectory( string $path ): bool {
            
            // set the initial attempts
            $attempts = 0;
            $max_attempts = 3;

            // Normalize the path (ensure it ends with a slash)
            $path = rtrim( $path, '/' ) . '/';

            // while we haven't tried more than max attempts
            while ( $attempts < $max_attempts ) {
                
                try {
                    // Check if directory already exists and is writable
                    if ( file_exists( $path ) ) {
                        if ( is_dir( $path ) && is_writable( $path ) ) {
                            return true;
                        } elseif ( is_dir( $path ) ) {
                            // Try to fix permissions
                            if ( @chmod( $path, 0755 ) && is_writable( $path ) ) {
                                return true;
                            }
                        }
                        $attempts++;
                        continue;
                    }
                    
                    // Try to create the directory
                    if ( @mkdir( $path, 0755, true ) ) {
                        // Ensure it's writable
                        if ( is_writable( $path ) ) {
                            return true;
                        }
                        
                        // Try to fix permissions
                        if ( @chmod( $path, 0755 ) && is_writable( $path ) ) {
                            return true;
                        }
                        
                        // Try more permissive permissions
                        if ( @chmod( $path, 0777 ) && is_writable( $path ) ) {
                            return true;
                        }
                    }
                    
                } catch ( Exception $e ) {
                    self::$_last_error = "Cache directory creation failed: " . $e->getMessage( );
                }
                
                $attempts++;
                
                // Small delay between attempts
                if ( $attempts < $max_attempts ) {
                    usleep( 100000 ); // 100ms
                }
            }
            
            return false;
        }


        /**
         * setCachePath
         * 
         * Set a custom cache path for file-based caching
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_path The custom cache path
         * @return bool Returns true if path is valid and writable
         */
        public static function setCachePath( string $_path ) : bool {

            // Normalize the path (ensure it ends with a slash)
            $_path = rtrim( $_path, '/' ) . '/';
            
            // Try to create the cache directory with proper permissions
            if ( self::createCacheDirectory( $_path ) ) {
                self::$_configurable_cache_path = $_path;
                
                // If we're already initialized, update the fallback path immediately
                if ( self::$_initialized ) {
                    self::$_fallback_path = $_path;
                }
                
                return true;
            }
            
            return false;

        }

        /**
         * getCachePath
         * 
         * Get the current cache path being used
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns the current cache path
         */
        public static function getCachePath( ) : string {

            // return the cache path
            return self::$_fallback_path;

        }

        /**
         * getFromFile
         * 
         * Get item from file cache
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_key The cache key name
         * @return mixed Returns the cached value or false if not found
         */
        private static function getFromFile( string $_key ): mixed {

            // setup the cache file
            $file = self::$_fallback_path . md5( $_key );
            
            // if it exists
            if ( file_exists( $file ) ) {

                // get the data from the file's contents
                $data = file_get_contents( $file );

                // setup it's expiry
                $expires = substr( $data, 0, 10 );
                
                // is it supposed to expire
                if ( time( ) > $expires ) {

                    // delete it and return false
                    unlink( $file );
                    return false;
                }
                
                // return the unserialized data
                return unserialize( substr( $data, 10 ) );
            }
            
            // default return
            return false;

        }

        /**
         * setToFile
         * 
         * Set item to file cache
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
        private static function setToFile( string $_key, mixed $_data, int $_length ): bool {
            $file = self::$_fallback_path . md5( $_key );
            $expires = time( ) + $_length;
            $data = $expires . serialize( $_data );
            
            return file_put_contents( $file, $data, LOCK_EX ) !== false;
        }

        /**
         * getCachePathInfo
         * 
         * Get detailed information about the cache path and permissions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns cache path information
         */
        public static function getCachePathInfo( ): array {
            $path = self::$_fallback_path;
            
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
            
            if ( $path ) {
                $info['exists'] = file_exists( $path );
                $info['is_dir'] = is_dir( $path );
                $info['is_writable'] = is_writable( $path );
                $info['is_readable'] = is_readable( $path );
                
                if ( $info['exists'] ) {
                    $info['permissions'] = substr( sprintf( '%o', fileperms( $path ) ), -4 );
                    if ( function_exists( 'posix_getpwuid' ) && function_exists( 'fileowner' ) ) {
                        $owner_info = posix_getpwuid( fileowner( $path ) );
                        $info['owner'] = $owner_info ? $owner_info['name'] : fileowner( $path );
                    }
                }
                
                // Check if parent directory is writable
                $parent = dirname( rtrim( $path, '/' ) );
                $info['parent_writable'] = is_writable( $parent );
                $info['parent_path'] = $parent;
            }
            
            return $info;
        }

        /**
         * fixCachePermissions
         * 
         * Attempt to fix cache directory permissions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return bool Returns true if permissions were fixed
         */
        public static function fixCachePermissions( ): bool {
            
            self::ensureInitialized( );
            
            $path = self::$_fallback_path;
            
            if ( ! $path || ! file_exists( $path ) ) {
                return false;
            }
            
            try {
                // Try different permission levels
                $permission_levels = [ 0755, 0775, 0777 ];
                
                foreach ( $permission_levels as $perms ) {
                    if ( @chmod( $path, $perms ) ) {
                        if ( is_writable( $path ) ) {
                            return true;
                        }
                    }
                }
                
                // If chmod failed, try recreating the directory
                if ( is_dir( $path ) ) {
                    // Try to remove and recreate (only if empty or only contains cache files)
                    $files = glob( $path . '*' );
                    $safe_to_recreate = true;
                    
                    // Check if all files look like cache files (md5 hashes)
                    foreach ( $files as $file ) {
                        $basename = basename( $file );
                        if ( ! preg_match( '/^[a-f0-9]{32}$/', $basename ) ) {
                            $safe_to_recreate = false;
                            break;
                        }
                    }
                    
                    if ( $safe_to_recreate ) {
                        // Remove cache files
                        foreach ( $files as $file ) {
                            @unlink( $file );
                        }
                        
                        // Remove directory and recreate
                        if ( @rmdir( $path ) ) {
                            return self::createCacheDirectory( $path );
                        }
                    }
                }
                
            } catch ( Exception $e ) {
                self::$_last_error = "Permission fix failed: " . $e->getMessage( );
            }
            
            return false;
        }

        /**
         * getSuggestedCachePaths
         * 
         * Get suggested alternative cache paths for troubleshooting
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return array Returns suggested cache paths with their status
         */
        public static function getSuggestedCachePaths( ): array {
            
            $suggestions = [
                'current' => self::$_fallback_path,
                'alternatives' => []
            ];
            
            $test_paths = [
                sys_get_temp_dir( ) . '/kpt_cache_alt/',
                getcwd( ) . '/cache/',
                __DIR__ . '/cache/',
                '/tmp/kpt_cache_alt/',
                sys_get_temp_dir( ) . '/cache/',
            ];
            
            foreach ( $test_paths as $path ) {
                $status = [
                    'path' => $path,
                    'parent_exists' => file_exists( dirname( $path ) ),
                    'parent_writable' => is_writable( dirname( $path ) ),
                    'can_create' => false,
                    'recommended' => false
                ];
                
                // Test if we can create a test directory
                $test_dir = $path . 'test_' . uniqid( );
                if ( @mkdir( $test_dir, 0755, true ) ) {
                    $status['can_create'] = true;
                    $status['recommended'] = is_writable( $test_dir );
                    @rmdir( $test_dir );
                }
                
                $suggestions['alternatives'][] = $status;
            }
            
            return $suggestions;
        }

    }

}
        