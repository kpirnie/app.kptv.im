<?php
/**
 * KPT Logger - Simple Universal Application Logger
 * 
 * Provides basic logging capabilities for any application with support for
 * four log levels and configurable output destinations (system log or file).
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class doesn't exist
if ( ! class_exists( 'Logger' ) ) {

    /**
     * KPT Logger
     * 
     * Simple, focused logging system for applications with configurable
     * output destinations and four standard log levels.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Logger {

        /** @var int Error: error conditions */
        const LEVEL_ERROR = 1;
        
        /** @var int Warning: warning conditions */
        const LEVEL_WARNING = 2;
        
        /** @var int Info: informational messages */
        const LEVEL_INFO = 3;
        
        /** @var int Debug: debug-level messages */
        const LEVEL_DEBUG = 4;

        /** @var array Log level names mapping */
        private static array $_level_names = [
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_DEBUG => 'DEBUG'
        ];

        /** @var bool Whether logging is enabled (errors always log) */
        private static bool $_enabled = false;
        private static ?string $_log_file = null;
        private static bool $_include_stack_trace = true;

        public function __construct( bool $enabled, bool $show_stack = true) {

            self::$_enabled = $enabled;
            self::$_include_stack_trace = $show_stack;
        }

        /**
         * Log an error message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Error message
         * @param array $context Additional context data
         * @param bool|null $include_stack Whether to include stack trace (null = use global setting)
         * @return void
         */
        public static function error( string $message, array $context = [], ?bool $include_stack = null ): void {

            // errors always log, even when disabled
            self::writeLog( $message, self::LEVEL_ERROR, $context, $include_stack );
        }

        /**
         * Log a warning message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Warning message
         * @param array $context Additional context data
         * @param bool|null $include_stack Whether to include stack trace (null = use global setting)
         * @return void
         */
        public static function warning( string $message, array $context = [], ?bool $include_stack = null ): void {

            // only log if enabled
            if ( ! self::$_enabled ) {
                return;
            }

            self::writeLog( $message, self::LEVEL_WARNING, $context, $include_stack );
        }

        /**
         * Log an info message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Info message
         * @param array $context Additional context data
         * @param bool|null $include_stack Whether to include stack trace (null = use global setting)
         * @return void
         */
        public static function info( string $message, array $context = [], ?bool $include_stack = null ): void {

            // only log if enabled
            if ( ! self::$_enabled ) {
                return;
            }

            self::writeLog( $message, self::LEVEL_INFO, $context, $include_stack );
        }

        /**
         * Log a debug message
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Debug message
         * @param array $context Additional context data
         * @param bool|null $include_stack Whether to include stack trace (null = use global setting)
         * @return void
         */
        public static function debug( string $message, array $context = [], ?bool $include_stack = null ): void {

            // only log if enabled
            if ( ! self::$_enabled ) {
                return;
            }

            self::writeLog( $message, self::LEVEL_DEBUG, $context, $include_stack );
        }

        /**
         * Set the log file path
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string|null $file_path Log file path (null to use system log)
         * @return bool Returns true if file is writable or null, false otherwise
         */
        public static function setLogFile( ?string $file_path ): bool {

            // if null, use system log
            if ( $file_path === null ) {
                self::$_log_file = null;
                return true;
            }

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
         * Write log data to configured destination
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Log message
         * @param int $level Log level
         * @param array $context Additional context data
         * @param bool|null $include_stack Whether to include stack trace (null = use global setting)
         * @return void
         */
        private static function writeLog( string $message, int $level, array $context = [], ?bool $include_stack = null ): void {

            // format the log entry
            $formatted_message = self::formatLogEntry( $message, $level, $context, $include_stack );
            
            // write to appropriate destination
            if ( self::$_log_file === null ) {
                // use PHP's built-in error_log to write to system log
                error_log( $formatted_message );
            } else {
                // write to specified file
                @file_put_contents( self::$_log_file, $formatted_message . PHP_EOL, FILE_APPEND | LOCK_EX );
            }
        }

        /**
         * Format log entry for output
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $message Log message
         * @param int $level Log level
         * @param array $context Additional context data
         * @param bool|null $include_stack Whether to include stack trace (null = use global setting)
         * @return string Returns formatted log entry
         */
        private static function formatLogEntry( string $message, int $level, array $context = [], ?bool $include_stack = null ): string {

            // format timestamp
            $timestamp = date( 'Y-m-d H:i:s' );
            
            // get level name
            $level_name = self::$_level_names[$level];
            
            // build base log message
            $formatted = "[{$timestamp}] {$level_name}: {$message}";
            
            // add context if present
            if ( ! empty( $context ) ) {
                $formatted .= ' | Context: ' . json_encode( $context, JSON_UNESCAPED_SLASHES );
            }
            
            // determine whether to include stack trace
            $should_include_stack = $include_stack !== null ? $include_stack : self::$_include_stack_trace;
            
            // add stack trace if enabled
            if ( $should_include_stack ) {
                $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
                // remove the first few entries that are this class
                $trace = array_slice( $trace, 3 ); // increased from 2 to 3 to account for extra method call
                $formatted .= ' | Stack: ' . json_encode( $trace, JSON_UNESCAPED_SLASHES );
            }
            
            // return the formatted entry
            return $formatted;
        }

    }
    
}

// create our fake alias if it doesn't already exist
if( ! class_exists( 'LOG' ) ) {

    // redeclare this
    class LOG extends Logger {}

}