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
     * GET, POST, PUT, PATCH, DELETE Methods
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
                'storage' => 'file'
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
         * @throws RuntimeException When rate limit is exceeded
         */
        private function applyRateLimiting(): void {
            $clientIp = $this->getClientIp();
            $cacheKey = 'rate_limit_' . md5($clientIp);
            $limit = $this->rateLimits['global']['limit'];
            $window = $this->rateLimits['global']['window'];
            $storageType = $this->rateLimits['global']['storage'];

            try {
                if ($storageType === 'redis' && $this->redis !== null) {
                    $current = $this->handleRedisRateLimit($cacheKey, $limit, $window);
                } else {
                    $current = $this->handleFileRateLimit($cacheKey, $limit, $window);
                }

                if ($current >= $limit) {
                    header('Retry-After: ' . $window);
                    throw new RuntimeException('Rate limit exceeded', 429);
                }

                header('X-RateLimit-Limit: ' . $limit);
                header('X-RateLimit-Remaining: ' . max(0, $limit - $current - 1));
                header('X-RateLimit-Reset: ' . (time() + $window));

            } catch (Exception $e) {
                error_log('Rate limiting error: ' . $e->getMessage());
                if ($this->rateLimits['global']['strict_mode'] ?? false) {
                    throw new RuntimeException('Rate limit service unavailable', 503);
                }
            }
        }

        /**
         * Handle Redis rate limiting using native Redis extension
         */
        private function handleRedisRateLimit(string $key, int $limit, int $window): int {
            $current = $this->redis->get($key);
            
            if ($current !== false) {
                if ((int)$current >= $limit) {
                    return (int)$current;
                }
                $this->redis->incr($key);
                return (int)$current + 1;
            }

            $this->redis->setex($key, $window, 1);
            return 1;
        }

        /**
         * Handle file-based rate limiting
         */
        private function handleFileRateLimit(string $key, int $limit, int $window): int {
            $file = $this->rateLimitPath . '/' . $key;
            $now = time();
            
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data['expires'] > $now) {
                    $current = $data['count'] + 1;
                    file_put_contents($file, json_encode([
                        'count' => $current,
                        'expires' => $data['expires']
                    ]), LOCK_EX);
                    return $current;
                }
            }
            
            file_put_contents($file, json_encode([
                'count' => 1,
                'expires' => $now + $window
            ]), LOCK_EX);
            
            return 1;
        }

        /* CORE ROUTER FUNCTIONALITY */

        /**
         * Dispatch the router to handle current request
         */
        public function dispatch(): void {
            try {
                self::$currentMethod = $this->getRequestMethod();
                self::$currentPath = $this->getRequestUri();

                error_log(sprintf("Dispatching: %s %s", self::$currentMethod, self::$currentPath));

                if ($this->executeMiddlewares($this->middlewares) === false) {
                    return;
                }

                if ($this->rateLimitingEnabled) {
                    $this->applyRateLimiting();
                }

                error_log("Available POST routes: " . print_r(array_keys($this->routes['POST'] ?? []), true));
                $handler = $this->findRouteHandler(self::$currentMethod, self::$currentPath);
                
                if ($handler) {
                    self::$currentParams = $handler['params'];
                    $this->executeHandler($handler['callback'], $handler['params']);
                } elseif ($this->notFoundCallback) {
                    $this->executeHandler($this->notFoundCallback);
                } else {
                    error_log("No handler found for " . self::$currentMethod . " " . self::$currentPath);
                    $this->sendNotFoundResponse();
                }
            } catch (Throwable $e) {
                error_log("Dispatch error: " . $e->getMessage());
                $this->handleError($e);
            }
        }

        /* PRIVATE HELPER METHODS */

        private function addRoute(string $method, string $path, callable $callback): void {
            $path = $this->sanitizePath($path);
            $fullPath = $this->basePath === '/' ? $path : $this->sanitizePath($this->basePath . $path);
            $fullPath = preg_replace('#/+#', '/', $fullPath);
            
            if (!isset($this->routes[$method][$fullPath])) {
                $this->routes[$method][$fullPath] = $callback;
            }
        }

        private function executeMiddlewares(array $middlewares): bool {
            foreach ($middlewares as $middleware) {
                if ($middleware() === false) {
                    return false;
                }
            }
            return true;
        }

        private function getRequestUri(): string {
            $uri = parse_url(($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            return $this->sanitizePath($uri);
        }

        private function getRequestMethod( ): string {
            $method = ( $_SERVER['REQUEST_METHOD'] ) ?? 'GET';

            return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
                ? $method
                : 'GET';
        }

        private function findRouteHandler(string $method, string $uri): ?array {
            $uri = strtok($uri, '?');
            $uri = rtrim($uri, '/') ?: '/';
            
            error_log("Trying to match: $method $uri");

            if (isset($this->routes[$method][$uri])) {
                error_log("Exact match found");
                return [
                    'callback' => $this->routes[$method][$uri],
                    'params' => []
                ];
            }

            foreach ($this->routes[$method] ?? [] as $routePath => $callback) {
                $pattern = $this->convertRouteToPattern($routePath);
                if (preg_match($pattern, $uri, $matches)) {
                    error_log("Pattern matched: $routePath");
                    return [
                        'callback' => $callback,
                        'params' => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)
                    ];
                }
            }

            if ($method === 'POST' && isset($_POST['_method'])) {
                $overrideMethod = strtoupper($_POST['_method']);
                if (isset($this->routes[$overrideMethod][$uri])) {
                    return [
                        'callback' => $this->routes[$overrideMethod][$uri],
                        'params' => []
                    ];
                }
            }

            return null;
        }

        private function convertRouteToPattern(string $routePath): string {
            if (!isset($this->routeCache[$routePath])) {
                $this->routeCache[$routePath] = '#^' . 
                    preg_replace('/\{([a-z][a-z0-9_]*)\}/i', '(?P<$1>[^/]+)', $routePath) . 
                    '$#i';
            }
            return $this->routeCache[$routePath];
        }

        private function executeHandler(callable $handler, array $params = []): void {
            try {
                $currentRoute = self::get_current_route();
                
                $result = call_user_func_array($handler, $params);
                
                if (is_string($result)) {
                    echo $result;
                } elseif ($result !== null) {
                    error_log("Unexpected return type from handler: " . gettype($result));
                }
            } catch (Throwable $e) {
                error_log("Handler execution failed: " . $e->getMessage());
                $this->handleError($e);
            }
        }

        private function sanitizePath(?string $path): string {
            if (empty($path)) return '/';
            $path = parse_url($path, PHP_URL_PATH) ?? '';
            $path = trim(str_replace(['../', './'], '', $path), '/');
            return $path === '' ? '/' : '/' . $path;
        }

        private function getClientIp(): string {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
        }

        private function sendNotFoundResponse(): void {
            http_response_code(404);
            header('Content-Type: text/html');
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        private function handleError(Throwable $e): void {
            error_log('Router error: ' . $e->getMessage());
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            http_response_code($code);
            
            if (ini_get('display_errors')) {
                echo "Error {$code}: " . $e->getMessage();
            } else {
                echo "An error occurred. Please try again later.";
            }
            
            exit;
        }

        public function __destruct() {
            $this->routes = [];
            $this->middlewares = [];
            $this->routeCache = [];
            try {
                if ($this->redis) {
                    $this->redis->close();
                }
            } catch (Throwable $e) {
                error_log('Router destructor error: ' . $e->getMessage());
            }
        }
    }

}
