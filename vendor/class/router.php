<?php
/**
 * KPT Router Class
 * 
 * This class provides a comprehensive routing solution with middleware support, 
 * rate limiting, and view rendering capabilities.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// if the class does not exist already
if( ! class_exists( 'Router' ) ) {

    /**
     * KPT Router Class
     * 
     * Handles HTTP routing with support for all standard methods (GET, POST, etc.),
     * middleware pipelines, rate limiting, and view rendering.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Router {

        // inherit our traits
        use Router_RateLimiter;
        use Router_MiddlewareHandler;
        use Router_Route_Handler;
        use Router_Request_Processor;
        use Router_Response_Handler;

        /** @var string the routing base path */
        private string $basePath = '';

        /**
         * Constructor
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $basePath The base path for all routes
         */
        public function __construct( string $basePath = '' ) {

            // debug logging
            LOG::debug( "Router Constructor Started", [
                'base_path' => $basePath,
                'kpt_path_defined' => defined('KPT_PATH'),
                'kpt_path' => defined('KPT_PATH') ? KPT_PATH : null
            ] );

            // set the base paths
            $this -> basePath = KPT::sanitize_path( $basePath );
            $this -> viewsPath = defined('KPT_PATH') ? KPT_PATH . '/views' : '';

            // debug logging
            LOG::debug( "Router Paths Set", [
                'sanitized_base_path' => $this -> basePath,
                'views_path' => $this -> viewsPath,
                'rate_limit_path' => $this -> rateLimitPath ?? null
            ] );
            
            // if the file base rate limiter path doesnt exist, create it
            if ( ! file_exists( $this -> rateLimitPath ) ) {

                // try to create the directory
                try {

                    // create the directory
                    $result = mkdir( $this -> rateLimitPath, 0755, true );

                    // debug logging
                    LOG::debug( "Router Rate Limit Directory Created", [
                        'path' => $this -> rateLimitPath,
                        'success' => $result,
                        'permissions' => '0755'
                    ] );

                // whoopsie...
                } catch ( \Exception $e ) {

                    // error logging
                    LOG::error( "Router Rate Limit Directory Creation Failed", [
                        'path' => $this -> rateLimitPath,
                        'message' => $e -> getMessage( )
                    ] );
                }

            } else {

                // debug logging
                LOG::debug( "Router Rate Limit Directory Exists", [
                    'path' => $this -> rateLimitPath
                ] );
            }

            // debug logging
            LOG::debug( "Router Constructor Completed", [
                'base_path' => $this -> basePath,
                'views_path' => $this -> viewsPath
            ] );
        }

        /**
         * Destructor
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function __destruct( ) {

            // debug logging
            LOG::debug( "Router Destructor Started", [
                'has_redis' => isset( $this -> redis ) && $this -> redis !== null,
                'routes_count' => isset( $this -> routes ) ? count( $this -> routes ) : 0,
                'middlewares_count' => isset( $this -> middlewares ) ? count( $this -> middlewares ) : 0,
                'middleware_definitions_count' => isset( $this -> middlewareDefinitions ) ? count( $this -> middlewareDefinitions ) : 0
            ] );

            // clean up the arrays
            if ( isset( $this -> routes ) ) {
                $this -> routes = [];
                LOG::debug( "Router Routes Array Cleared" );
            }

            if ( isset( $this -> middlewares ) ) {
                $this -> middlewares = [];
                LOG::debug( "Router Middlewares Array Cleared" );
            }

            if ( isset( $this -> middlewareDefinitions ) ) {
                $this -> middlewareDefinitions = [];
                LOG::debug( "Router Middleware Definitions Array Cleared" );
            }

            // try to clean up the redis connection
            try {

                // if we have the object, close it
                if ( isset( $this -> redis ) && $this -> redis ) {

                    // close the redis connection
                    $this -> redis -> close( );

                    // debug logging
                    LOG::debug( "Router Redis Connection Closed", [
                        'success' => true
                    ] );
                }

            // whoopsie... log an error
            } catch ( \Throwable $e ) {

                // error logging
                LOG::error( "Router Redis Connection Close Error", [
                    'message' => $e -> getMessage( ),
                    'file' => $e -> getFile( ),
                    'line' => $e -> getLine( )
                ] );

            }

            // debug logging
            LOG::debug( "Router Destructor Completed" );
        }
    }
}