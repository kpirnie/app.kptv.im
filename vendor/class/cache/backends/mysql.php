<?php
/**
 * KPT Cache - MySQL Database Backend
 * 
 * Provides MySQL database backend support for the KPT cache system.
 * Uses the existing Database class for PDO operations.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'Cache_MySQL' ) ) {

    /**
     * KPT Cache MySQL Backend Trait
     * 
     * Implements MySQL database backend for the KPT cache system.
     * Uses a dedicated cache table with automatic table creation and management.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait Cache_MySQL {

        /** @var Database|null MySQL database instance */
        private static ?Database $_mysql_db = null;
        
        /** @var string|null Last MySQL error message */
        private static ?string $_mysql_last_error = null;
        
        /** @var bool MySQL cache table initialized flag */
        private static bool $_mysql_table_initialized = false;

        /**
         * Get MySQL database instance
         * 
         * Creates or returns existing MySQL database connection for cache operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return Database|null Returns database instance or null if unavailable
         */
        private static function getMySQLDatabase( ): ?Database {

            // return existing connection if available
            if ( self::$_mysql_db !== null ) {
                return self::$_mysql_db;
            }
            
            // try to create new database instance
            try {
                
                // create new database instance
                self::$_mysql_db = new Database( );
                
                // ensure cache table exists
                if ( ! self::$_mysql_table_initialized ) {
                    self::initializeMySQLTable( );
                    self::$_mysql_table_initialized = true;
                }
                
                // return the database instance
                return self::$_mysql_db;
                
            // whoopsie... setup the error and return null
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "Failed to create MySQL connection: " . $e -> getMessage( );
                return null;
            }
        }

        /**
         * Initialize MySQL cache table
         * 
         * Creates the cache table if it doesn't exist with proper indexes
         * and structure for optimal cache performance.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if table was created/exists, false otherwise
         */
        private static function initializeMySQLTable( ): bool {

            // try to create the cache table
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // create cache table SQL
                $create_sql = "
                    CREATE TABLE IF NOT EXISTS `{$table_name}` (
                        `cache_key` VARCHAR(500) NOT NULL,
                        `cache_value` LONGTEXT NOT NULL,
                        `expires_at` TIMESTAMP NULL DEFAULT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`cache_key`),
                        INDEX `idx_expires_at` (`expires_at`),
                        INDEX `idx_created_at` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                // execute table creation
                return self::$_mysql_db -> raw( $create_sql ) !== false;
                
            // whoopsie... setup the error and return false
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "Failed to initialize MySQL cache table: " . $e -> getMessage( );
                return false;
            }
        }

        /**
         * Get item from MySQL cache
         * 
         * Retrieves a cached item from the MySQL database, checking
         * expiration and returning the unserialized data.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found/expired
         */
        private static function getFromMySQL( string $key ): mixed {

            // get the database instance
            $db = self::getMySQLDatabase( );
            if ( ! $db ) {
                return false;
            }
            
            // try to get item from mysql cache
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // setup the query sql
                $sql = "
                    SELECT cache_value, expires_at 
                    FROM `{$table_name}` 
                    WHERE cache_key = ? 
                    AND (expires_at IS NULL OR expires_at > NOW())
                ";
                
                // execute the query and get result
                $result = $db -> query( $sql )
                             -> bind( [$key] )
                             -> single( )
                             -> fetch( );
                
                // check if we have a result and cache value
                if ( $result && $result -> cache_value ) {

                    // unserialize the cached data
                    $data = unserialize( $result -> cache_value );
                    return $data !== false ? $data : false;
                }
                
                // no result found
                return false;
                
            // whoopsie... setup the error
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL get error: " . $e -> getMessage( );
            }
            
            // return false if not found or error
            return false;
        }

        /**
         * Set item to MySQL cache
         * 
         * Stores an item in the MySQL cache with the specified TTL.
         * Uses REPLACE INTO for efficient upsert operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToMySQL( string $key, mixed $data, int $ttl ): bool {

            // get the database instance
            $db = self::getMySQLDatabase( );
            if ( ! $db ) {
                return false;
            }
            
            // try to set item to mysql cache
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // serialize the data
                $serialized_data = serialize( $data );
                
                // calculate expiration time
                $expires_at = $ttl > 0 ? date( 'Y-m-d H:i:s', time( ) + $ttl ) : null;
                
                // setup the insert sql
                $sql = "
                    REPLACE INTO `{$table_name}` 
                    (cache_key, cache_value, expires_at) 
                    VALUES (?, ?, ?)
                ";
                
                // execute the query
                $result = $db -> query( $sql )
                             -> bind( [$key, $serialized_data, $expires_at] )
                             -> execute( );
                
                // return success status
                return $result !== false;
                
            // whoopsie... setup the error and return false
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL set error: " . $e -> getMessage( );
                return false;
            }
        }

        /**
         * Delete item from MySQL cache
         * 
         * Removes a cached item from the MySQL database.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to delete
         * @return bool Returns true if successful, false otherwise
         */
        private static function deleteFromMySQL( string $key ): bool {

            // get the database instance
            $db = self::getMySQLDatabase( );
            if ( ! $db ) {
                return false;
            }
            
            // try to delete the item from mysql cache
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // setup the delete sql
                $sql = "DELETE FROM `{$table_name}` WHERE cache_key = ?";
                
                // debug logging
                LOG::debug( 'Delete from MySQL cache', ['key' => $key] );
                
                // execute the delete query
                $result = $db -> query( $sql )
                             -> bind( [$key] )
                             -> execute( );
                
                // return success status
                return $result !== false;
                
            // whoopsie... setup the error and return false
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL delete error: " . $e -> getMessage( );
                LOG::error( "MySQL delete error", ['error' => $e -> getMessage( )] );
                return false;
            }
        }

        /**
         * Clear all items from MySQL cache
         * 
         * Removes all cached items from the MySQL cache table.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if successful, false otherwise
         */
        private static function clearMySQL( ): bool {

            // get the database instance
            $db = self::getMySQLDatabase( );
            if ( ! $db ) {
                return false;
            }
            
            // try to clear all mysql cache items
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // setup the truncate sql
                $sql = "TRUNCATE TABLE `{$table_name}`";
                
                // debug logging
                LOG::debug( 'Clearing MySQL cache' );
                
                // execute the truncate query
                $result = $db -> raw( $sql );
                
                // return success status
                return $result !== false;
                
            // whoopsie... setup the error and return false
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL clear error: " . $e -> getMessage( );
                LOG::error( "MySQL clear error", ['error' => $e -> getMessage( )] );
                return false;
            }
        }

        /**
         * Clean up expired MySQL cache items
         * 
         * Removes all expired items from the MySQL cache table
         * and returns the number of items cleaned.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return int Returns the number of expired items removed
         */
        private static function cleanupMySQL( ): int {

            // get the database instance
            $db = self::getMySQLDatabase( );
            if ( ! $db ) {
                return 0;
            }
            
            // try to cleanup expired mysql cache items
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // setup the cleanup sql
                $sql = "DELETE FROM `{$table_name}` WHERE expires_at IS NOT NULL AND expires_at <= NOW()";
                
                // debug logging
                LOG::debug( 'Cleaning up expired MySQL cache items' );
                
                // execute the cleanup query
                $result = $db -> raw( $sql );
                
                // return the count of cleaned items
                return is_numeric( $result ) ? (int)$result : 0;
                
            // whoopsie... setup the error and return zero
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL cleanup error: " . $e -> getMessage( );
                LOG::error( "MySQL cleanup error", ['error' => $e -> getMessage( )] );
                return 0;
            }
        }

        /**
         * Test MySQL cache availability and functionality
         * 
         * Performs a comprehensive test of the MySQL cache including
         * table creation, basic operations, and cleanup.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if MySQL cache is functional, false otherwise
         */
        private static function testMySQLConnection( ): bool {

            // try to test mysql cache functionality
            try {
                
                // get the database instance
                $db = self::getMySQLDatabase( );
                if ( ! $db ) {
                    return false;
                }
                
                // setup test data
                $test_key = 'mysql_test_' . uniqid( );
                $test_value = 'test_value_' . time( );
                
                // test set operation
                if ( ! self::setToMySQL( $test_key, $test_value, 60 ) ) {
                    return false;
                }
                
                // test get operation
                $retrieved = self::getFromMySQL( $test_key );
                
                // cleanup test data
                self::deleteFromMySQL( $test_key );
                
                // verify retrieved data matches
                return $retrieved === $test_value;
                
            // whoopsie... setup the error and return false
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL test failed: " . $e -> getMessage( );
                return false;
            }
        }

        /**
         * Get MySQL cache statistics
         * 
         * Returns statistics about the MySQL cache including
         * table size, expired entries, and performance metrics.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns statistics array
         */
        private static function getMySQLStats( ): array {

            // setup the stats array
            $stats = [
                'total_entries' => 0,
                'expired_entries' => 0,
                'valid_entries' => 0,
                'table_size_mb' => 0,
                'oldest_entry' => null,
                'newest_entry' => null
            ];
            
            // try to get mysql cache statistics
            try {
                
                // get the database instance
                $db = self::getMySQLDatabase( );
                if ( ! $db ) {
                    return $stats;
                }
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // get basic counts sql
                $count_sql = "
                    SELECT 
                        COUNT(*) as total_entries,
                        COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_entries,
                        COUNT(CASE WHEN expires_at IS NULL OR expires_at > NOW() THEN 1 END) as valid_entries,
                        MIN(created_at) as oldest_entry,
                        MAX(created_at) as newest_entry
                    FROM `{$table_name}`
                ";
                
                // execute count query
                $counts = $db -> raw( $count_sql );
                if ( $counts && count( $counts ) > 0 ) {

                    // get the count data
                    $count_data = $counts[0];
                    $stats['total_entries'] = (int)$count_data -> total_entries;
                    $stats['expired_entries'] = (int)$count_data -> expired_entries;
                    $stats['valid_entries'] = (int)$count_data -> valid_entries;
                    $stats['oldest_entry'] = $count_data -> oldest_entry;
                    $stats['newest_entry'] = $count_data -> newest_entry;
                }
                
                // get table size sql
                $size_sql = "
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS table_size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                ";
                
                // execute size query
                $size_result = $db -> query( $size_sql )
                                  -> bind( [$table_name] )
                                  -> single( )
                                  -> fetch( );
                
                // get the table size if available
                if ( $size_result ) {
                    $stats['table_size_mb'] = (float)$size_result -> table_size_mb;
                }
                
            // whoopsie... setup the error in stats
            } catch ( \Exception $e ) {
                $stats['error'] = $e -> getMessage( );
            }
            
            // return the stats array
            return $stats;
        }

        /**
         * Get the last MySQL error message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string|null Returns the last error message or null
         */
        private static function getMySQLLastError( ): ?string {

            // return the last mysql error
            return self::$_mysql_last_error;
        }

        /**
         * Close MySQL connections and cleanup resources
         * 
         * Closes the MySQL database connection and cleans up resources.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        private static function closeMySQL( ): void {

            // try to close mysql connection and cleanup
            try {
                
                // close the database connection if available
                if ( self::$_mysql_db ) {

                    // Database class destructor will handle cleanup
                    self::$_mysql_db = null;
                }
                
                // reset the table initialized flag
                self::$_mysql_table_initialized = false;
                
            // whoopsie... ignore close errors
            } catch ( \Exception $e ) {
                // ignore close errors
            }
        }

        /**
         * Optimize MySQL cache table
         * 
         * Performs table optimization to improve performance.
         * Should be called periodically for maintenance.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if optimization was successful
         */
        private static function optimizeMySQLTable( ): bool {

            // get the database instance
            $db = self::getMySQLDatabase( );
            if ( ! $db ) {
                return false;
            }
            
            // try to optimize the mysql cache table
            try {
                
                // get mysql configuration
                $config = Cache_Config::get( 'mysql' );
                $table_name = $config['table_name'] ?? 'kpt_cache';
                
                // setup the optimize sql
                $sql = "OPTIMIZE TABLE `{$table_name}`";
                
                // debug logging
                LOG::debug( 'Optimizing MySQL cache table' );
                
                // execute the optimize query
                $result = $db -> raw( $sql );
                
                // return success status
                return $result !== false;
                
            // whoopsie... setup the error and return false
            } catch ( \Exception $e ) {
                self::$_mysql_last_error = "MySQL optimize error: " . $e -> getMessage( );
                LOG::error( "MySQL optimize error", ['error' => $e -> getMessage( )] );
                return false;
            }
        }
    }
}