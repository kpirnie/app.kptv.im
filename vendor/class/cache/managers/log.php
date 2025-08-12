<?php
/**
 * KPT Cache Logger - Comprehensive Logging and Error Tracking
 * 
 * Provides structured logging, error tracking, performance monitoring,
 * and debugging capabilities for the cache system with support for
 * multiple log levels, log rotation, and external logging integration.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class doesn't exist
if ( ! class_exists( 'KPT_Cache_Logger' ) ) {

    /**
     * KPT Cache Logger
     * 
     * Centralized logging system for cache operations including error tracking,
     * performance monitoring, debug information, and structured logging with
     * support for log rotation and external logging system integration.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class KPT_Cache_Logger {

        /** @var int Emergency: system is unusable */
        const LEVEL_EMERGENCY = 0;
        
        /** @var int Alert: action must be taken immediately */
        const LEVEL_ALERT = 1;
        
        /** @var int Critical: critical conditions */
        const LEVEL_CRITICAL = 2;
        
        /** @var int Error: error conditions */
        const LEVEL_ERROR = 3;
        
        /** @var int Warning: warning conditions */
        const LEVEL_WARNING = 4;
        
        /** @var int Notice: normal but significant condition */
        const LEVEL_NOTICE = 5;
        
        /** @var int Info: informational messages */
        const LEVEL_INFO = 6;
        
        /** @var int Debug: debug-level messages */
        const LEVEL_DEBUG = 7;

        /** @var string Cache get operation */
        const OP_GET = 'get';
        
        /** @var string Cache set operation */
        const OP_SET = 'set';
        
        /** @var string Cache delete operation */
        const OP_DELETE = 'delete';
        
        /** @var string Cache clear operation */
        const OP_CLEAR = 'clear';
        
        /** @var string Cache hit event */
        const OP_HIT = 'hit';
        
        /** @var string Cache miss event */
        const OP_MISS = 'miss';
        
        /** @var string Tier promotion operation */
        const OP_PROMOTE = 'promote';
        
        /** @var string Connection operation */
        const OP_CONNECT = 'connect';
        
        /** @var string Health check operation */
        const OP_HEALTH = 'health';

        /** @var array Log level names mapping */
        private static array $_level_names = [
            self::LEVEL_EMERGENCY => 'EMERGENCY',
            self::LEVEL_ALERT => 'ALERT', 
            self::LEVEL_CRITICAL => 'CRITICAL',
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_NOTICE => 'NOTICE',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_DEBUG => 'DEBUG'
        ];

        /** @var int Current log level threshold */
        private static int $_log_level = self::LEVEL_WARNING;
        
        /** @var bool Whether logging is enabled */
        private static bool $_enabled = true;
        
        /** @var string|null Log file path */
        private static ?string $_log_file = null;
        
        /** @var int Maximum log file size before rotation */
        private static int $_max_file_size = 10485760; // 10MB
        
        /** @var int Number of rotated log files to keep */
        private static int $_max_files = 5;
        
        /** @var array In-memory log buffer */
        private static array $_log_buffer = [];
        
        /** @var int Maximum entries in memory buffer */
        private static int $_max_buffer_size = 1000;
        
        /** @var bool Whether to buffer logs in memory */
        private static bool $_buffer_enabled = true;
        
        /** @var array Performance tracking data */
        private static array $_performance_data = [];
        
        /** @var array Error count by category */
        private static array $_error_counts = [];
        
        /** @var callable|null External logger callback */
        private static $_external_logger = null;
        
        /** @var bool Whether to include stack traces in error logs */
        private static bool $_include_stack_trace = true;
        
        /** @var string Session ID for request tracking */
        private static string $_session_id;
        
        /** @var array Context data to include in all logs */
        private static array $_global_context = [];

        /**
         * Initialize the logger
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $config Logger configuration options
         * @return void
         */
        public static function initialize( array $config = [] ): void {

            // Set session ID for request tracking
            self::$_session_id = uniqid( 'cache_', true );
            
            // Apply configuration
            if ( isset( $config['enabled'] ) ) {
                self::$_enabled = (bool) $config['enabled'];
            }
            
            // set the log level if provided
            if ( isset( $config['log_level'] ) ) {
                self::setLogLevel( $config['log_level'] );
            }
            
            // set the log file if provided
            if ( isset( $config['log_file'] ) ) {
                self::setLogFile( $config['log_file'] );
            }
            
            // set max file size if provided
            if ( isset( $config['max_file_size'] ) ) {
                self::$_max_file_size = (int) $config['max_file_size'];
            }
            
            // set max files if provided
            if ( isset( $config['max_files'] ) ) {
                self::$_max_files = (int) $config['max_files'];
            }
            
            // set buffer enabled flag if provided
            if ( isset( $config['buffer_enabled'] ) ) {
                self::$_buffer_enabled = (bool) $config['buffer_enabled'];
            }
            
            // set max buffer size if provided
            if ( isset( $config['max_buffer_size'] ) ) {
                self::$_max_buffer_size = (int) $config['max_buffer_size'];
            }
            
            // set stack trace inclusion flag if provided
            if ( isset( $config['include_stack_trace'] ) ) {
                self::$_include_stack_trace = (bool) $config['include_stack_trace'];
            }
            
            // set global context if provided
            if ( isset( $config['global_context'] ) && is_array( $config['global_context'] ) ) {
                self::$_global_context = $config['global_context'];
            }
            
            // Set default log file if none specified
            if ( self::$_log_file === null ) {
                self::$_log_file = sys_get_temp_dir() . '/kpt_cache.log';
            }
        }

        /**
         * Log a cache operation
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $operation The cache operation type
         * @param string $tier The cache tier involved
         * @param string $key The cache key
         * @param array $context Additional context data
         * @param int $level Log level
         * @return void
         */
        public static function logOperation( string $operation, string $tier, string $key, array $context = [], int $level = self::LEVEL_INFO ): void {

            // check if we should log this level
            if ( ! self::shouldLog( $level ) ) {
                return;
            }
            
            // build the log data array
            $log_data = [
                'timestamp' => microtime( true ),
                'session_id' => self::$_session_id,
                'type' => 'operation',
                'operation' => $operation,
                'tier' => $tier,
                'key' => $key,
                'level' => $level,
                'level_name' => self::$_level_names[$level],
                'context' => array_merge( self::$_global_context, $context ),
                'memory_usage' => memory_get_usage( true ),
                'process_id' => getmypid()
            ];
            
            // write the log
            self::writeLog( $log_data );
        }

        /**
         * Log an error
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Error message
         * @param string $category Error category
         * @param array $context Additional context data
         * @param int $level Error level
         * @return void
         */
        public static function logError( string $message, string $category = 'general', array $context = [], int $level = self::LEVEL_ERROR ): void {

            // check if we should log this level
            if ( ! self::shouldLog( $level ) ) {
                return;
            }
            
            // Track error counts
            if ( ! isset( self::$_error_counts[$category] ) ) {
                self::$_error_counts[$category] = 0;
            }
            self::$_error_counts[$category]++;
            
            // build the log data array
            $log_data = [
                'timestamp' => microtime( true ),
                'session_id' => self::$_session_id,
                'type' => 'error',
                'message' => $message,
                'category' => $category,
                'level' => $level,
                'level_name' => self::$_level_names[$level],
                'context' => array_merge( self::$_global_context, $context ),
                'memory_usage' => memory_get_usage( true ),
                'process_id' => getmypid()
            ];
            
            // Add stack trace for errors
            if ( self::$_include_stack_trace && $level <= self::LEVEL_ERROR ) {
                $log_data['stack_trace'] = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
            }
            
            // write the log
            self::writeLog( $log_data );
        }

        /**
         * Log performance data
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $operation The operation being measured
         * @param float $duration Operation duration in seconds
         * @param string $tier The cache tier
         * @param array $metrics Additional performance metrics
         * @return void
         */
        public static function logPerformance( string $operation, float $duration, string $tier, array $metrics = [] ): void {

            // check if we should log debug level
            if ( ! self::shouldLog( self::LEVEL_DEBUG ) ) {
                return;
            }
            
            // Store performance data for analysis
            $perf_key = $tier . '_' . $operation;
            if ( ! isset( self::$_performance_data[$perf_key] ) ) {
                self::$_performance_data[$perf_key] = [
                    'count' => 0,
                    'total_duration' => 0,
                    'min_duration' => PHP_FLOAT_MAX,
                    'max_duration' => 0,
                    'avg_duration' => 0
                ];
            }
            
            // update performance statistics
            $perf = &self::$_performance_data[$perf_key];
            $perf['count']++;
            $perf['total_duration'] += $duration;
            $perf['min_duration'] = min( $perf['min_duration'], $duration );
            $perf['max_duration'] = max( $perf['max_duration'], $duration );
            $perf['avg_duration'] = $perf['total_duration'] / $perf['count'];
            
            // build the log data array
            $log_data = [
                'timestamp' => microtime( true ),
                'session_id' => self::$_session_id,
                'type' => 'performance',
                'operation' => $operation,
                'tier' => $tier,
                'duration' => $duration,
                'level' => self::LEVEL_DEBUG,
                'level_name' => 'DEBUG',
                'metrics' => $metrics,
                'context' => self::$_global_context,
                'memory_usage' => memory_get_usage( true )
            ];
            
            // write the log
            self::writeLog( $log_data );
        }

        /**
         * Log cache statistics
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $stats Cache statistics data
         * @param int $level Log level
         * @return void
         */
        public static function logStats( array $stats, int $level = self::LEVEL_INFO ): void {

            // check if we should log this level
            if ( ! self::shouldLog( $level ) ) {
                return;
            }
            
            // build the log data array
            $log_data = [
                'timestamp' => microtime( true ),
                'session_id' => self::$_session_id,
                'type' => 'statistics',
                'stats' => $stats,
                'level' => $level,
                'level_name' => self::$_level_names[$level],
                'context' => self::$_global_context,
                'memory_usage' => memory_get_usage( true )
            ];
            
            // write the log
            self::writeLog( $log_data );
        }

        /**
         * Log a debug message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Debug message
         * @param array $context Additional context data
         * @return void
         */
        public static function debug( string $message, array $context = [] ): void {

            self::log( $message, self::LEVEL_DEBUG, $context );
        }

        /**
         * Log an info message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Info message
         * @param array $context Additional context data
         * @return void
         */
        public static function info( string $message, array $context = [] ): void {

            self::log( $message, self::LEVEL_INFO, $context );
        }

        /**
         * Log a warning message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Warning message
         * @param array $context Additional context data
         * @return void
         */
        public static function warning( string $message, array $context = [] ): void {

            self::log( $message, self::LEVEL_WARNING, $context );
        }

        /**
         * Log an error message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Error message
         * @param array $context Additional context data
         * @return void
         */
        public static function error( string $message, array $context = [] ): void {

            self::logError( $message, 'general', $context, self::LEVEL_ERROR );
        }

        /**
         * Generic log method
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Log message
         * @param int $level Log level
         * @param array $context Additional context data
         * @return void
         */
        public static function log( string $message, int $level, array $context = [] ): void {

            // check if we should log this level
            if ( ! self::shouldLog( $level ) ) {
                return;
            }
            
            // build the log data array
            $log_data = [
                'timestamp' => microtime( true ),
                'session_id' => self::$_session_id,
                'type' => 'message',
                'message' => $message,
                'level' => $level,
                'level_name' => self::$_level_names[$level],
                'context' => array_merge( self::$_global_context, $context ),
                'memory_usage' => memory_get_usage( true ),
                'process_id' => getmypid()
            ];
            
            // write the log
            self::writeLog( $log_data );
        }

        /**
         * Set the log level threshold
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param int $level Log level threshold
         * @return void
         */
        public static function setLogLevel( int $level ): void {

            // only set if level is valid
            if ( isset( self::$_level_names[$level] ) ) {
                self::$_log_level = $level;
            }
        }

        /**
         * Set the log file path
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $file_path Log file path
         * @return bool Returns true if file is writable, false otherwise
         */
        public static function setLogFile( string $file_path ): bool {

            // get the directory path
            $dir = dirname( $file_path );
            
            // create directory if it doesn't exist
            if ( ! is_dir( $dir ) ) {
                if ( ! @mkdir( $dir, 0755, true ) ) {
                    return false;
                }
            }
            
            // check if directory is writable
            if ( ! is_writable( $dir ) ) {
                return false;
            }
            
            // set the log file path
            self::$_log_file = $file_path;
            
            // return success
            return true;
        }

        /**
         * Set external logger callback
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable|null $callback External logger function
         * @return void
         */
        public static function setExternalLogger( ?callable $callback ): void {

            self::$_external_logger = $callback;
        }

        /**
         * Enable or disable logging
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param bool $enabled Whether to enable logging
         * @return void
         */
        public static function setEnabled( bool $enabled ): void {

            self::$_enabled = $enabled;
        }

        /**
         * Set global context data
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $context Global context to include in all logs
         * @return void
         */
        public static function setGlobalContext( array $context ): void {

            self::$_global_context = $context;
        }

        /**
         * Get performance statistics
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns performance statistics
         */
        public static function getPerformanceStats(): array {

            return self::$_performance_data;
        }

        /**
         * Get error counts by category
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns error counts
         */
        public static function getErrorCounts(): array {

            return self::$_error_counts;
        }

        /**
         * Get log buffer contents
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param int|null $limit Optional limit on number of entries
         * @return array Returns buffered log entries
         */
        public static function getLogBuffer( ?int $limit = null ): array {

            // if no limit specified, return all entries
            if ( $limit === null ) {
                return self::$_log_buffer;
            }
            
            // return limited entries from the end
            return array_slice( self::$_log_buffer, -$limit );
        }

        /**
         * Search log buffer for specific criteria
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $criteria Search criteria
         * @return array Returns matching log entries
         */
        public static function searchLogs( array $criteria ): array {

            // initialize results array
            $results = [];
            
            // loop through each log entry
            foreach ( self::$_log_buffer as $entry ) {
                $match = true;
                
                // check each criteria
                foreach ( $criteria as $key => $value ) {
                    if ( ! isset( $entry[$key] ) || $entry[$key] !== $value ) {
                        $match = false;
                        break;
                    }
                }
                
                // if all criteria match, add to results
                if ( $match ) {
                    $results[] = $entry;
                }
            }
            
            // return matching results
            return $results;
        }

        /**
         * Get logging statistics
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Returns logging statistics
         */
        public static function getStats(): array {

            // initialize level counts
            $level_counts = [];
            foreach ( self::$_level_names as $level => $name ) {
                $level_counts[$name] = 0;
            }
            
            // count entries by level
            foreach ( self::$_log_buffer as $entry ) {
                if ( isset( $entry['level_name'] ) ) {
                    $level_counts[$entry['level_name']]++;
                }
            }
            
            // return comprehensive statistics
            return [
                'enabled' => self::$_enabled,
                'log_level' => self::$_log_level,
                'log_level_name' => self::$_level_names[self::$_log_level],
                'buffer_size' => count( self::$_log_buffer ),
                'max_buffer_size' => self::$_max_buffer_size,
                'buffer_utilization' => round( count( self::$_log_buffer ) / self::$_max_buffer_size * 100, 2 ),
                'log_file' => self::$_log_file,
                'log_file_size' => file_exists( self::$_log_file ) ? filesize( self::$_log_file ) : 0,
                'session_id' => self::$_session_id,
                'level_counts' => $level_counts,
                'total_errors' => array_sum( self::$_error_counts ),
                'error_categories' => count( self::$_error_counts )
            ];
        }

        /**
         * Rotate log files
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if rotation was successful
         */
        public static function rotateLogs(): bool {

            // if no log file or file doesn't exist, return success
            if ( ! self::$_log_file || ! file_exists( self::$_log_file ) ) {
                return true;
            }
            
            // check if rotation is needed
            $file_size = filesize( self::$_log_file );
            if ( $file_size < self::$_max_file_size ) {
                return true;
            }
            
            // Rotate existing files
            for ( $i = self::$_max_files - 1; $i > 0; $i-- ) {
                $old_file = self::$_log_file . '.' . $i;
                $new_file = self::$_log_file . '.' . ( $i + 1 );
                
                if ( file_exists( $old_file ) ) {
                    if ( $i === self::$_max_files - 1 ) {
                        @unlink( $old_file );
                    } else {
                        @rename( $old_file, $new_file );
                    }
                }
            }
            
            // Move current log to .1
            @rename( self::$_log_file, self::$_log_file . '.1' );
            
            // return success
            return true;
        }

        /**
         * Clear log buffer
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        public static function clearBuffer(): void {

            self::$_log_buffer = [];
        }

        /**
         * Clear performance data
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        public static function clearPerformanceData(): void {

            self::$_performance_data = [];
        }

        /**
         * Clear error counts
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        public static function clearErrorCounts(): void {

            self::$_error_counts = [];
        }

        /**
         * Flush buffered logs to file
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if flush was successful
         */
        public static function flush(): bool {

            // if no log file or empty buffer, return success
            if ( ! self::$_log_file || empty( self::$_log_buffer ) ) {
                return true;
            }
            
            // format all buffered entries
            $output = '';
            foreach ( self::$_log_buffer as $entry ) {
                $output .= self::formatLogEntry( $entry ) . PHP_EOL;
            }
            
            // write to file
            $result = @file_put_contents( self::$_log_file, $output, FILE_APPEND | LOCK_EX );
            
            // if successful, clear buffer
            if ( $result !== false ) {
                self::clearBuffer();
                return true;
            }
            
            // return failure
            return false;
        }

        /**
         * Determine if a log level should be logged
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param int $level Log level to check
         * @return bool Returns true if level should be logged
         */
        private static function shouldLog( int $level ): bool {

            return self::$_enabled && $level <= self::$_log_level;
        }

        /**
         * Write log data to storage
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $log_data Log entry data
         * @return void
         */
        private static function writeLog( array $log_data ): void {
            
            // Add to buffer if enabled
            if ( self::$_buffer_enabled ) {
                self::addToBuffer( $log_data );
            }
            
            // Write to file immediately for high priority logs
            if ( $log_data['level'] <= self::LEVEL_ERROR && self::$_log_file ) {
                $formatted = self::formatLogEntry( $log_data );
                @file_put_contents( self::$_log_file, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX );
                
                // Check if log rotation is needed
                self::checkLogRotation();
            }
            
            // Send to external logger if configured
            if ( self::$_external_logger ) {
                call_user_func( self::$_external_logger, $log_data );
            }
        }

        /**
         * Add entry to log buffer
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $log_data Log entry data
         * @return void
         */
        private static function addToBuffer( array $log_data ): void {

            // add entry to buffer
            self::$_log_buffer[] = $log_data;
            
            // Maintain buffer size limit
            if ( count( self::$_log_buffer ) > self::$_max_buffer_size ) {
                array_shift( self::$_log_buffer );
            }
        }

        /**
         * Format log entry for file output
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $log_data Log entry data
         * @return string Returns formatted log entry
         */
        private static function formatLogEntry( array $log_data ): string {

            // format timestamp with microseconds
            $timestamp = date( 'Y-m-d H:i:s', $log_data['timestamp'] );
            $microseconds = sprintf( '%06d', ( $log_data['timestamp'] - floor( $log_data['timestamp'] ) ) * 1000000 );
            
            // create formatted log entry
            $formatted = sprintf(
                '[%s.%s] %s.%s: %s',
                $timestamp,
                $microseconds,
                $log_data['level_name'],
                $log_data['session_id'],
                $log_data['message'] ?? $log_data['type'] ?? 'LOG'
            );
            
            // Add context as JSON if present
            if ( ! empty( $log_data['context'] ) ) {
                $formatted .= ' ' . json_encode( $log_data['context'], JSON_UNESCAPED_SLASHES );
            }
            
            // return the formatted entry
            return $formatted;
        }

        /**
         * Check if log rotation is needed
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return void
         */
        private static function checkLogRotation(): void {

            // check if log file exists and rotation is needed
            if ( self::$_log_file && file_exists( self::$_log_file ) ) {
                if ( filesize( self::$_log_file ) >= self::$_max_file_size ) {
                    self::rotateLogs();
                }
            }
        }
    }
}