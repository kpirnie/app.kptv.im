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

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

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
                mkdir( $this -> rateLimitPath, 0755, true );
            }
        }

        /**
         * Destructor
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function __destruct( ) {

            // clean up the arrays
            $this -> routes = [];
            $this -> middlewares = [];
            $this -> middlewareDefinitions = [];

            // try to clean up the redis connection
            try {

                // if we have the object, close it
                if ($this -> redis) {
                    $this -> redis -> close( );
                }

            // whoopsie... log an error
            } catch ( Throwable $e ) {
                error_log( 'Router destructor error: ' . $e -> getMessage( ) );
            }
        }
    }
}
