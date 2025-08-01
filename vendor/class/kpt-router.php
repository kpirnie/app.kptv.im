<?php
/**
 * KPT Router Class
 * 
 * Controls the site routing and manages the views to render
 */
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// if the class does not exist in userspace yet
if( ! class_exists( 'KPT_Router' ) ) {

    /**
     * KPT Router Class
     * 
     * Controls the site routing and manages the views to render
     * GET, POST, PUT, PATCH, DELETE, HEAD, TRACE, and CONNECT Methods
     */
    class KPT_Router {

        // Routing properties
        private array $routes = [];
        private string $basePath = '';
        private $notFoundCallback;
        private array $middlewares = [];
        private array $routeCache = [];

        // static current route data
        private static string $currentMethod = '';
        private static string $currentPath = '';
        private static array $currentParams = [];

        // Rate limiting properties
        private bool $rateLimitingEnabled = false;
        private array $rateLimits = [
            'global' => [
                'limit' => 100,
                'window' => 60,
                'storage' => 'redis'
            ]
        ];
        private ?Redis $redis = null;
        private string $rateLimitPath = '/tmp/kpt_rate_limits';

        // View properties
        private string $viewsPath = '';
        private array $viewData = [];

        // Cache properties
        private bool $should_cache = false;
        private int $cache_ttl = KPT::HOUR_IN_SECONDS;

        // fire up the routing class
        public function __construct( string $basePath = '' ) {
            $this -> basePath = $this -> sanitizePath( $basePath );
            $this -> viewsPath = defined( 'KPT_PATH' ) ? KPT_PATH . '/views' : '';
            
            // if the rate limit path does not exist
            if ( ! file_exists( $this -> rateLimitPath ) ) {
                mkdir( $this -> rateLimitPath, 0755, true );
            }
        }

        /* CACHE CONTROL METHODS */

        /**
         * Enable view caching
         * 
         * @param int $ttl Cache time-to-live in seconds
         * @return self
         */
        public function enableCaching( int $ttl = KPT::HOUR_IN_SECONDS ) : self {
            
            // set the caching and return it
            $this -> should_cache = true;
            $this -> cache_ttl = $ttl;
            return $this;
        }

        /**
         * Disable view caching
         * 
         * @return self
         */
        public function disableCaching( ) : self {

            // disable caching
            $this -> should_cache = false;
            return $this;
        }

        /**
         * Generate a cache key for the view
         * 
         * @param string $template View template path
         * @param array $data View data
         * @return string Cache key
         */
        private function generateCacheKey( string $template, array $data ) : string {

            // return the cache key for the view to be rendered
            return 'view_' . md5( $template . serialize( $data ) );
        }

        /* VIEW RENDERING METHODS */

        /**
         * Set the views directory path
         * 
         * @param string $path Path to views directory
         * @return self
         */
        public function setViewsPath( string $path ) : self {

            // format the view path
            $this -> viewsPath = rtrim( $path, '/' );
            return $this;
        }

        /**
         * Render a view template with data
         * 
         * @param string $template View file path (relative to views directory)
         * @param array $data Data to pass to the view
         * @return string Rendered content
         * @throws RuntimeException If view file not found
         */
        public function view( string $template, array $data = [] ) : string {

            // setup the view template path
            $templatePath = $this -> viewsPath . '/' . ltrim( $template, '/' );
            
            // if the view template doesn't exist, log the error and throw the exception
            if ( ! file_exists( $templatePath ) ) {
                $error = "View template not found: $templatePath";
                error_log( $error );
                throw new RuntimeException( $error );
            }

            // Check cache if enabled
            if ( $this -> should_cache ) {

                // get the cache key
                $cache_key = $this -> generateCacheKey( $template, $data );
                
                // get teh cached content
                $cached_content = KPT_Cache::get( $cache_key );
                
                // if it exists, return it
                if ($cached_content !== false) {
                    return $cached_content;
                }

            }

            // setup the data necessary for the view
            extract( array_merge( $this -> viewData, $data ), EXTR_SKIP );
            
            // fire up the output bufferring
            ob_start( );

            // try to get the content of the view
            try {

                // include the view template and grab the rendered content
                include $templatePath;
                $content = ob_get_clean( );
                
                // Store in cache if enabled
                if ( $this -> should_cache ) {
                    KPT_Cache::set( $cache_key, $content, $this -> cache_ttl );
                }
                
                // return the content
                return $content;

            // whoopsie...end the output buffer, log the error and throw an exception
            } catch ( Throwable $e ) {
                ob_end_clean( );
                error_log("View rendering failed: " . $e -> getMessage( ) );
                throw $e;
            }

        }

        /**
         * Share data with all views
         * 
         * @param string|array $key Data key or array of key-value pairs
         * @param mixed $value Value if key is string
         * @return self
         */
        public function share( $key, $value = null ) : self {
            
            // if the key is an array
            if ( is_array( $key ) ) {

                // setup the view data
                $this -> viewData = array_merge( $this -> viewData, $key );
            // otherwise
            } else {

                // setup the view data
                $this -> viewData[$key] = $value;
            }

            // return
            return $this;
        }

        /* ROUTE REGISTRATION METHODS */

        /**
         * setup the GET routes
         * 
         * @return self
         */
        public function get( string $path, callable $callback ) : self {
            $this -> addRoute( 'GET', $path, $callback );
            return $this;
        }

        /**
         * setup the POST routes
         * 
         * @return self
         */
        public function post( string $path, callable $callback ) : self {
            $this -> addRoute( 'POST', $path, $callback );
            return $this;
        }

        /**
         * setup the PUT routes
         * 
         * @return self
         */
        public function put( string $path, callable $callback ) : self {
            $this -> addRoute( 'PUT', $path, $callback );
            return $this;
        }

        /**
         * setup the PATCH routes
         * 
         * @return self
         */
        public function patch( string $path, callable $callback ) : self {
            $this -> addRoute( 'PATCH', $path, $callback );
            return $this;
        }

        /**
         * setup the DELETE routes
         * 
         * @return self
         */
        public function delete( string $path, callable $callback ) : self {
            $this -> addRoute( 'DELETE', $path, $callback );
            return $this;
        }

        /**
         * setup the HEAD routes
         * 
         * @return self
         */
        public function head( string $path, callable $callback ) : self {
            $this -> addRoute( 'HEAD', $path, $callback );
            return $this;
        }

        /**
         * setup the TRACE routes
         * 
         * @return self
         */
        public function trace( string $path, callable $callback ) : self {
            $this -> addRoute( 'TRACE', $path, $callback );
            return $this;
        }

        /**
         * setup the CONNECT routes
         * 
         * @return self
         */
        public function connect( string $path, callable $callback ) : self {
            $this -> addRoute( 'CONNECT', $path, $callback );
            return $this;
        }

        /**
         * Get all registered routes
         * 
         * @return array Returns an array of all registered routes
         */
        public function getRoutes( ) : array {
            return [
                'GET' => array_keys( $this -> routes['GET'] ?? [] ),
                'POST' => array_keys( $this -> routes['POST'] ?? [] ),
                'PUT' => array_keys( $this -> routes['PUT'] ?? [] ),
                'PATCH' => array_keys( $this -> routes['PATCH'] ?? [] ),
                'DELETE' => array_keys( $this -> routes['DELETE'] ?? [] ),
                'HEAD'  => array_keys( $this -> routes['HEAD'] ?? [] ),
                'TRACE' => array_keys( $this -> routes['TRACE'] ?? [] ),
                'CONNECT' => array_keys( $this -> routes['CONNECT'] ?? [] ),
            ];
        }

        /**
         * Get information about the current matched route
         * 
         * @return object Structure:
         * [
         *     'method' => string,  // HTTP method (GET, POST, etc)
         *     'path' => string,    // Clean request path
         *     'params' => array,   // Route parameters
         *     'matched' => bool    // Whether a route was matched
         * ]
         */
        public static function get_current_route( ) : object {
            return ( object )[
                'method' => self::$currentMethod,
                'path' => self::$currentPath,
                'params' => self::$currentParams,
                'matched' => ! empty( self::$currentMethod ) && self::$currentPath !== ''
            ];
        }

        /* MIDDLEWARE AND ERROR HANDLING */

        /**
         * runs callable functions before routes are dispatched
         * ex... modifying the request
         * 
         * @return self
         */
        public function addMiddleware( callable $middleware ) : self {
            $this -> middlewares[] = $middleware;
            return $this;
        }

        /**
         * runs callable functions when a route is not found
         * ex... modifying the request
         * 
         * @return self
         */
        public function notFound( callable $callback ) : self {
            $this -> notFoundCallback = $callback;
            return $this;
        }

        /* RATE LIMITING METHODS */

        /**
         * initialize redis rate limiting
         * 
         * @param array $config Pass the redis configuration necessary 
         * @return bool Returns success or not
         */
        public function initRedisRateLimiting( array $config = ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.0, 'password' => null] ) : bool {
            
            // try to connect to the redis server
            try {
                
                // setup the redis object
                $this -> redis = new Redis( );

                // connect to it
                $connected = $this -> redis -> connect(
                    $config['host'],
                    $config['port'],
                    $config['timeout'],
                );

                // Select database
                $this -> redis -> select( 1 );
                
                // Set prefix if needed
                $this -> redis -> setOption( Redis::OPT_PREFIX, 'KPTV_RL:' );

                // if redis is not connected
                if ( ! $connected ) {
                    throw new RuntimeException('Failed to connect to Redis');
                }

                // if the password is not empty
                if ( ! empty( $config['password'] ) ) {
                    $this -> redis -> auth( $config['password'] );
                }

                // Test the connection
                $this -> redis -> ping( );
                
                // setup the ratelimit config
                $this -> rateLimits['global']['storage'] = 'redis';
                $this -> rateLimitingEnabled = true;
                return true;
            
            // whoopsie...
            } catch ( Throwable $e ) {

                // log the error
                error_log( 'Redis connection failed: ' . $e -> getMessage( ) );
                $this -> rateLimitingEnabled = false;
                return false;
            }

        }

        /**
         * enable file based rate limiting
         * 
         * @return void Returns nothing
         */
        public function enableFileRateLimiting( ) : void {
            $this -> rateLimits['global']['storage'] = 'file';
            $this -> rateLimitingEnabled = true;
        }

        /**
         * disable the rate limiting
         * 
         * @return void Returns nothing
         */
        public function disableRateLimiting( ) : void {
            $this -> rateLimitingEnabled = false;
        }

        /**
         * Apply rate limiting to current request
         * 
         * @return void Returns nothing
         * @throws RuntimeException When rate limit is exceeded
         */
        private function applyRateLimiting( ) : void {

            // get the client IP, and set a cache key
            $clientIp = KPT::get_user_ip( );
            $cacheKey = 'rate_limit_' . md5( $clientIp );

            // setup the rate limiting config
            $limit = $this -> rateLimits['global']['limit'];
            $window = $this -> rateLimits['global']['window'];
            $storageType = $this -> rateLimits['global']['storage'];

            // try to handle the rate limiting
            try {

                // if we should utilize redis, let redis handle it
                if ( $storageType === 'redis' && $this -> redis !== null ) {
                    $current = $this -> handleRedisRateLimit( $cacheKey, $limit, $window );

                // otherwise, utilize the file rate limiter
                } else {
                    $current = $this -> handleFileRateLimit( $cacheKey, $limit, $window );
                }

                // if we're at the limit, set a retry header and throw an exception
                if ( $current >= $limit ) {
                    header( 'Retry-After: ' . $window );
                    throw new RuntimeException( 'Rate limit exceeded', 429 );
                }

                // set the rate limiting headers
                header( 'X-RateLimit-Limit: ' . $limit );
                header( 'X-RateLimit-Remaining: ' . max( 0, $limit - $current - 1 ) );
                header( 'X-RateLimit-Reset: ' . ( time( ) + $window ) );

            // whoopsie...
            } catch ( Exception $e ) {

                // log an error, and throw an exception
                error_log( 'Rate limiting error: ' . $e -> getMessage( ) );
                if ( $this -> rateLimits['global']['strict_mode'] ?? false ) {
                    throw new RuntimeException( 'Rate limit service unavailable', 503 );
                }
            }

        }

        /**
         * Handle Redis rate limiting using native Redis extension
         * 
         * @param string $key The key of the ratelimiter cache object
         * @param int The current limit
         * @param int The window of time for the rate limiter
         * @return int Returns the number of hits at the moment
         */
        private function handleRedisRateLimit( string $key, int $limit, int $window ) : int {
        
            // the current count
            $current = $this -> redis -> get( $key );
            
            // if it doesnt exist already
            if ( $current !== false ) {

                // if we are at the limit return it
                if ( ( int ) $current >= $limit ) {
                    return ( int )$current;
                }

                // incremement the rate limit
                $this -> redis -> incr( $key );
                return ( int ) $current + 1;
            }

            // return the default count
            $this -> redis -> setex( $key, $window, 1 );
            return 1;
        }

        /**
         * Handle file-based rate limiting
         * 
         * @param string $key The key of the ratelimiter cache object
         * @param int The current limit
         * @param int The window of time for the rate limiter
         * @return int Returns the number of hits at the moment
         */
        private function handleFileRateLimit( string $key, int $limit, int $window ) : int {

            // setup the file and time
            $file = $this -> rateLimitPath . '/' . $key;
            $now = time( );
            
            // if the file exists
            if ( file_exists( $file ) ) {

                // decode the content
                $data = json_decode( file_get_contents( $file ), true );

                // if it has expired
                if ( $data['expires'] > $now ) {
                    
                    // setup the current count and write it to the file
                    $current = $data['count'] + 1;
                    file_put_contents( $file, json_encode( [
                        'count' => $current,
                        'expires' => $data['expires']
                    ] ), LOCK_EX );

                    // return the current count
                    return $current;
                }
            }
            
            // setup the initial file data
            file_put_contents( $file, json_encode( [
                'count' => 1,
                'expires' => $now + $window
            ] ), LOCK_EX );
            
            // return 1
            return 1;
        }

        /* CORE ROUTER FUNCTIONALITY */

        /**
         * Dispatch the router to handle current request
         * 
         * @return void Returns nothing
         */
        public function dispatch( ) : void {

            // try to dispatch the router
            try {

                // setup the current method and path
                self::$currentMethod = $this -> getRequestMethod( );
                self::$currentPath = $this -> getRequestUri( );

                // execute middlewares if there are any
                if ( $this -> executeMiddlewares( $this -> middlewares ) === false) {
                    return;
                }

                // if we are supposed to ratelimit, apply it
                if ( $this -> rateLimitingEnabled ) {
                    $this -> applyRateLimiting( );
                }

                // setup the route handler
                $handler = $this -> findRouteHandler( self::$currentMethod, self::$currentPath );
                
                // if we actually have the handler
                if ( $handler ) {

                    // hold the parameters and execute it
                    self::$currentParams = $handler['params'];
                    $this -> executeHandler( $handler['callback'], $handler['params']) ;
                
                // otherwise the route it not found
                } elseif ( $this -> notFoundCallback ) {
                    $this -> executeHandler( $this -> notFoundCallback );

                // otherwise, even the notFound handler was not found
                } else {

                    // log the error and send the response
                    error_log( "No handler found for " . self::$currentMethod . " " . self::$currentPath );
                    $this -> sendNotFoundResponse( );
                }

            // whoopsie...
            } catch ( Throwable $e ) {

                // log the error and handle it
                error_log( "Dispatch error: " . $e -> getMessage( ) );
                $this -> handleError( $e );
            }
        }

        /* PRIVATE HELPER METHODS */

        /**
         * Add a route to render or work
         * 
         * @param string $method What HTTP method should we utilize
         * @param string $path What path does the route need
         * @param callback Run a function to be performed with the route
         * @return void Returns nothing
         */
        private function addRoute( string $method, string $path, callable $callback ) : void {

            // the routes full path
            $path = $this -> sanitizePath( $path );
            $fullPath = $this -> basePath === '/' ? $path : $this -> sanitizePath( $this -> basePath . $path );
            $fullPath = preg_replace( '#/+#', '/', $fullPath );
            
            // if we don't already gave the routes full path, set it up
            if ( ! isset( $this -> routes[$method][$fullPath] ) ) {
                $this -> routes[$method][$fullPath] = $callback;
            }

        }

        /**
         * Execute the routing middleware
         * 
         * @param array $middlewares Registered middlewares to be run
         * @return bool Returns if it was successful or not
         */
        private function executeMiddlewares( array $middlewares ) : bool {
            
            // loop the middleware and execute them
            foreach ( $middlewares as $middleware ) {
                if ( $middleware( ) === false ) {
                    return false;
                }
            }

            // return a true execution
            return true;

        }

        /**
         * Get the uri we're requesting
         * 
         * @return string Returns the string of the requested uri
         */
        private function getRequestUri( ) : string {

            // parse the requested URI and sanitize the path
            $uri = parse_url( ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
            return $this -> sanitizePath( $uri );
        }

        /**
         * Get the request method
         * 
         * @return string Returns the request method
         */
        private function getRequestMethod( ) : string {

            // get the request method, or default to GET
            $method = ( $_SERVER['REQUEST_METHOD'] ) ?? 'GET';

            // check if it's valid, then return it
            return in_array( $method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'] )
                ? $method
                : 'GET';
        }

        /**
         * Find the correct route handlers
         * 
         * @param string The request method
         * @param string The URI to match
         * @return ?array A nullable array of the route handlers
         */
        private function findRouteHandler( string $method, string $uri ) : ?array {

            // fix up and format the uri
            $uri = strtok( $uri, '?' );
            $uri = rtrim( $uri, '/' ) ?: '/';
            
            // if the routes uri is set, return the array by setting the route method as the callback
            if ( isset( $this -> routes[$method][$uri] ) ) {
                return [
                    'callback' => $this -> routes[$method][$uri],
                    'params' => []
                ];
            }

            // loop over the routes
            foreach ( $this -> routes[$method] ?? [] as $routePath => $callback ) {

                // convert the route to a pattern
                $pattern = $this -> convertRouteToPattern( $routePath );

                // if the pattern matches, return the array of the callback with parameters
                if ( preg_match( $pattern, $uri, $matches ) ) {
                    return [
                        'callback' => $callback,
                        'params' => array_filter( $matches, 'is_string', ARRAY_FILTER_USE_KEY )
                    ];
                }
            }

            // if the request is a POST
            if ( $method === 'POST' && isset( $_POST['_method'] ) ) {

                // override the method
                $overrideMethod = strtoupper( $_POST['_method'] );

                // if the route is set
                if ( isset( $this -> routes[$overrideMethod][$uri] ) ) {
                    return [
                        'callback' => $this -> routes[$overrideMethod][$uri],
                        'params' => []
                    ];
                }
            }

            // return nothing
            return null;

        }

        /**
         * Convert a route path to a regex pattern
         * 
         * @param string The route path
         * @return string The path converted to a pattern
         */
        private function convertRouteToPattern( string $routePath ) : string {

            // if the route path is set
            if ( ! isset( $this -> routeCache[$routePath] ) ) {
                
                // set the route cache
                $this -> routeCache[$routePath] = '#^' . 
                    preg_replace( '/\{([a-z][a-z0-9_]*)\}/i', '(?P<$1>[^/]+)', $routePath ) . 
                    '$#i';
            }

            // return the patther
            return $this -> routeCache[$routePath];
        }

        /**
         * Execute the handler
         * 
         * @param callable The request handler
         * @param array The parameters to pass to the handler
         * @return void Return nothing
         */
        private function executeHandler( callable $handler, array $params = [] ) : void {

            // try to execute the handler
            try {

                // get the current route
                $currentRoute = self::get_current_route( );
                
                // setup the result from the handler
                $result = call_user_func_array( $handler, $params );
                
                // make sure its a string thats returned
                if ( is_string( $result ) ) {

                    // write it out
                    echo $result;

                // otherwise, log an error
                } elseif ( $result !== null ) {
                    error_log( "Unexpected return type from handler: " . gettype( $result ) );
                }

            // whoopsie... log the error and handle it
            } catch ( Throwable $e ) {
                error_log( "Handler execution failed: " . $e -> getMessage( ) );
                $this -> handleError( $e );
            }

        }

        /**
         * Sanitize the path
         * 
         * @param ?string The requested path
         * @return string Return the sanitized path
         */
        private function sanitizePath( ?string $path ) : string {
            
            // if the path is empty return /
            if ( empty( $path ) ) return '/';

            // setup the sanitized path to be returned
            $path = parse_url( $path, PHP_URL_PATH ) ?? '';
            $path = trim( str_replace( ['../', './'], '', $path ), '/' );
            
            // return the sanitized path
            return $path === '' ? '/' : '/' . $path;
        }

        /**
         * Send the 404 not found response
         * 
         * @return void Return nothing
         */
        private function sendNotFoundResponse( ) : void {

            // make sure to set the response code and proper header
            http_response_code( 404 );
            header( 'Content-Type: text/html' );

            // write out a message then exit
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        /**
         * Handle our errors
         * 
         * @param Throwable The exception to handle
         * @return void Return nothing
         */
        private function handleError( Throwable $e ) : void {

            // log the error
            error_log( 'Router error: ' . $e -> getMessage( ) );

            // get the error code and set the response properly
            $code = $e -> getCode( ) >= 400 && $e -> getCode( ) < 600 ? $e -> getCode( ) : 500;
            http_response_code( $code );
            
            // if we are configured to display errors
            if ( ini_get( 'display_errors' ) ) {
                echo "Error {$code}: " . $e -> getMessage( );
            } else {
                echo "An error occurred. Please try again later.";
            }
            
            // exit
            exit;
        }

        // destroy the routing class, cleaning out the arrays and attempting to clear out redis
        public function __destruct() {
            $this->routes = [];
            $this->middlewares = [];
            $this->routeCache = [];

            // try to close redis
            try {
                // if redis is set...
                if ( $this -> redis ) {
                    $this -> redis -> close( );
                }

            // whoopsie...log the error
            } catch ( Throwable $e ) {
                error_log( 'Router destructor error: ' . $e -> getMessage( ) );
            }
        }

    }

}
