<?php
/**
 * KPT Router - Handler Resolution Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure it doesn't already exist
if( ! trait_exists( 'Router_Response_Handler' ) ) {

    /**
     * KPT Router Response Handler Trait
     * 
     * Provides comprehensive response handling functionality including view rendering,
     * controller resolution, and template management for the router system.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait Router_Response_Handler {

        // views directory path
        private string $viewsPath = '';

        // shared view data
        private array $viewData = [ ];

        /**
         * Set the views directory path
         * 
         * Configures the base directory path where view template files
         * are located for rendering responses.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Path to views directory
         * @return self Returns the router instance for method chaining
         */
        public function setViewsPath( string $path ): self {

            // set the views path without trailing slash
            $this -> viewsPath = rtrim( $path, '/' );
            return $this;
        }

        /**
         * Render a view template with data
         * 
         * Loads and renders a view template file with the provided data,
         * using output buffering for clean content capture.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $template View file path (relative to views directory)
         * @param array $data Data to pass to the view
         * @return string Returns the rendered content
         * @throws RuntimeException If view file not found
         */
        public function view( string $template, array $data = [ ] ): string {

            // Create a cache key based on template path and data
            $cache_key = 'view_cache_' . md5( $template . serialize( $data ) );

            // check if we have a cachedel querystring
            if( isset( $_GET['cachedel'] ) || isset( $_POST ) ) {

                // delete the cached item first
                Cache::delete( $cache_key );
            }

            // should the view be cached?
            $should_cache = ( isset( $data['should_cache'] ) && $data['should_cache'] ) ?? false;

            // If caching is enabled, try to get from cache first
            if ( $should_cache ) {
                
                // Try to get cached content
                $cached_content = Cache::get( $cache_key );
                if ( $cached_content !== false ) {
                    LOG::debug( "View cache HIT for template: {$template}" );
                    return $cached_content;
                }
                
                // debug log for miss
                LOG::debug( "View cache MISS for template: {$template}" );
            }
            
            // build full template path
            $templatePath = $this -> viewsPath . '/' . ltrim( $template, '/' );
            
            // check if template file exists
            if ( ! file_exists( $templatePath ) ) {
                LOG::error( 'View template not found', ['file' => $templatePath] );
                throw new \RuntimeException( $error );
            }

            // extract view data and shared data
            extract( array_merge( $this -> viewData, $data ), EXTR_SKIP );
            ob_start( );

            // try to render the template
            try {

                // include template and capture output
                include $templatePath;
                $content = ob_get_clean( );

                // check of the cache data exists, and if it's true cache it
                if ( $should_cache ) {
                    Cache::set( $cache_key, $content, $data['cache_length'] );
                }

                return $content;

            // whoopsie... handle rendering errors
            } catch ( \Throwable $e ) {
                ob_end_clean( );
                LOG::error( "View rendering failed", ['error' => $e -> getMessage( )] );
                throw $e;
            }
        }

        /**
         * Share data with all views
         * 
         * Stores data that will be available to all view templates,
         * supporting both single key-value pairs and arrays of data.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string|array $key Data key or array of key-value pairs
         * @param mixed $value Value if key is string
         * @return self Returns the router instance for method chaining
         */
        public function share( $key, $value = null ): self {

            // handle array of data or single key-value pair
            if ( is_array( $key ) ) {
                $this -> viewData = array_merge( $this -> viewData, $key );
            } else {
                $this -> viewData[$key] = $value;
            }

            // return for chaining
            return $this;
        }

        /**
         * Resolve handler to callable
         * 
         * Converts various handler formats (strings, controller references, etc.)
         * into executable callable functions for route handling.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $handler Handler to resolve
         * @param array $data Additional handler data
         * @return callable Returns the resolved handler
         * @throws InvalidArgumentException If handler cannot be resolved
         */
        private function resolveHandler( $handler, array $data = [ ] ): callable {

            // return if already callable
            if ( is_callable( $handler ) ) {
                return $handler;
            }

            // handle string-based handlers
            if ( is_string( $handler ) ) {

                // check for type-prefixed handlers (view:, controller:)
                if ( strpos( $handler, ':' ) !== false ) {

                    // split type and target
                    list( $type, $target ) = explode( ':', $handler, 2 );
                    
                    // handle based on type
                    switch ( $type ) {
                        case 'view':
                            return $this -> createViewHandler( $target, $data );
                        case 'controller':
                            return $this -> createControllerHandler( $target, $data );
                        default:
                            LOG::error( "Unknown handler type", ['type' => $type] );
                            throw new \InvalidArgumentException( "Unknown handler type: {$type}" );
                    }
                }
                
                // Check if it's a controller format (Class@method)
                if ( strpos( $handler, '@' ) !== false ) {
                    return $this -> createControllerHandler( $handler );
                }
                
                // default to view handler
                return $this -> createViewHandler( $handler, $data );
            }

            // handler format not supported
            LOG::error( "Unknown handler type", ['Handler must be callable or string'] );
            throw new \InvalidArgumentException( 'Handler must be callable or string' );
        }

        /**
         * Create view handler
         * 
         * Creates a callable handler that renders a view template with
         * route parameters and additional data.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $viewPath Path to view file
         * @param array $data Additional view data
         * @return callable Returns the view handler function
         */
        private function createViewHandler( string $viewPath, array $data = [ ] ): callable {

            // return closure that handles view rendering
            return function( ...$params ) use ( $viewPath, $data ) {

                // setup view data array
                $viewData = [ ];
                
                // get current route and extract parameters
                $currentRoute = self::get_current_route( );
                foreach ( $currentRoute -> params as $key => $value ) {
                    $viewData[$key] = $value;
                }
                
                // include current route object if requested
                if ( isset( $data['currentRoute'] ) && $data['currentRoute'] ) {
                    $viewData['currentRoute'] = $currentRoute;
                }
                
                // merge with additional data and render view
                $viewData = array_merge( $viewData, $data );
                return $this -> view( $viewPath, $viewData );
            };
        }

        /**
         * Create controller handler
         * 
         * Creates a callable handler that instantiates a controller class
         * and calls the specified method with route parameters.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $controller Controller identifier (e.g., "UserController@show")
         * @param array $data Additional handler data
         * @return callable Returns the controller handler function
         * @throws InvalidArgumentException If controller format is invalid
         * @throws RuntimeException If controller class doesn't exist or method is not callable
         */
        private function createControllerHandler( string $controller, array $data = [ ] ): callable {

            // return closure that handles controller execution
            return function( ...$params ) use ( $controller, $data ) {

                // Create a cache key based on controller, method, and params
                $cache_key = 'controller_cache_' . md5( $controller . serialize( $params ) );

                // check if we have a cachedel querystring or post
                if( isset( $_GET['cachedel'] ) || isset( $_POST ) ) {

                    // delete the cached item first
                    Cache::delete( $cache_key );
                }

                // should the controller be cached?
                $should_cache = ( isset( $data['should_cache'] ) && $data['should_cache'] ) ?? false;

                // If caching is enabled, try to get from cache first
                if ( $should_cache ) {
                    
                    // Try to get cached content
                    $cached_content = Cache::get( $cache_key );
                    if ( $cached_content !== false ) {
                        LOG::debug( "Controller cache HIT for: {$controller}" );
                        return $cached_content;
                    }
                    
                    // debug log for miss
                    LOG::debug( "Controller cache MISS for: {$controller}" );
                }

                // validate controller format
                if ( ! strpos( $controller, '@' ) ) {
                    LOG::error( 'Invalid Controller Format', [$controller] );
                    throw new \InvalidArgumentException( "Controller format must be 'ClassName@methodName', got: {$controller}" );
                }

                // split class and method
                list( $class, $method ) = explode( '@', $controller, 2 );
                
                // Trim any whitespace
                $class = trim( $class );
                $method = trim( $method );
                
                // validate class and method names
                if ( empty( $class ) || empty( $method ) ) {
                    LOG::error( 'Missing Class or Method', [$controller] );
                    throw new \InvalidArgumentException( "Both controller class and method must be specified: {$controller}" );
                }
                
                // Check if class exists
                if ( ! class_exists( $class ) ) {
                    LOG::error( 'Missing Class', [$class] );
                    throw new \RuntimeException( "Controller class not found: {$class}" );
                }
                
                // Instantiate the controller
                $controllerInstance = new $class( );
                
                // Check if method exists and is callable
                if ( ! method_exists( $controllerInstance, $method ) ) {
                    LOG::error( 'Missing Method', [$method] );
                    throw new \RuntimeException( "Method '{$method}' not found in controller '{$class}'" );
                }
                
                // verify method is callable
                if ( ! is_callable( [ $controllerInstance, $method ] ) ) {
                    LOG::error( 'Method Not Callable', [$method] );
                    throw new \RuntimeException( "Method '{$method}' is not callable in controller '{$class}'" );
                }
                
                // Call the controller method with parameters
                $result = call_user_func_array( [ $controllerInstance, $method ], $params );
                
                // check of the cache data exists, and if it's true cache it
                if ( $should_cache ) {
                    Cache::set( $cache_key, $result, $data['cache_length'] ?? 3600 );
                }

                // Clean up
                unset( $controllerInstance );
                
                // return the result
                return $result;
            };
        }
        
    }
}