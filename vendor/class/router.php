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

            // set the base paths
            $this -> basePath = KPT::sanitize_path( $basePath );
            $this -> viewsPath = defined('KPT_PATH') ? KPT_PATH . '/views' : '';

            // if the file base rate limiter path doesnt exist, create it
            if ( ! file_exists( $this -> rateLimitPath ) ) {

                // try to create the directory
                try {

                    // create the directory
                    mkdir( $this -> rateLimitPath, 0755, true );

                // whoopsie...
                } catch ( \Exception $e ) {

                    // error logging
                    LOG::error( "Router Rate Limit Directory Creation Failed", [
                        'path' => $this -> rateLimitPath,
                        'message' => $e -> getMessage( )
                    ] );
                }

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

            // clean up the arrays
            if ( isset( $this -> routes ) ) {
                $this -> routes = [];
            }
            if ( isset( $this -> middlewares ) ) {
                $this -> middlewares = [];
            }
            if ( isset( $this -> middlewareDefinitions ) ) {
                $this -> middlewareDefinitions = [];
            }

            // try to clean up the redis connection
            try {

                // if we have the object, close it
                if ( isset( $this -> redis ) && $this -> redis ) {

                    // close the redis connection
                    $this -> redis -> close( );
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
        }
    }
}