<?php
/**
 * KPT_Router - Advanced PHP Router with Security Features
 * 
 * Features:
 * - Comprehensive routing for all HTTP methods
 * - Middleware support
 * - Rate limiting (file or Redis-based)
 * - Error handling
 * - View template support
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

    /**
     * Constructor - Initializes router with security features
     * @param string $basePath Base path for all routes
     */
    public function __construct(string $basePath = '') {
        $this->basePath = $this->sanitizePath($basePath);
        $this->viewsPath = defined('KPT_PATH') ? KPT_PATH . '/views' : '';
        
        if (!file_exists($this->rateLimitPath)) {
            mkdir($this->rateLimitPath, 0755, true);
        }
    }

    /* VIEW RENDERING METHODS */

    /**
     * Set the views directory path
     * @param string $path Path to views directory
     * @return self
     */
    public function setViewsPath(string $path): self {
        $this->viewsPath = rtrim($path, '/');
        return $this;
    }

    /**
     * Render a view template with data
     * @param string $template View file path (relative to views directory)
     * @param array $data Data to pass to the view
     * @return string Rendered content
     * @throws RuntimeException If view file not found
     */
    public function view(string $template, array $data = []): string {
        $templatePath = $this->viewsPath . '/' . ltrim($template, '/');
        
        error_log("Attempting to render view: " . $templatePath); // Debug
        
        if (!file_exists($templatePath)) {
            $error = "View template not found: $templatePath";
            error_log($error);
            throw new RuntimeException($error);
        }

        // Debug view data
        error_log("View data: " . print_r($data, true));
        
        extract(array_merge($this->viewData, $data), EXTR_SKIP);
        
        ob_start();
        try {
            include $templatePath;
            $content = ob_get_clean();
            error_log("View rendered successfully"); // Debug
            return $content;
        } catch (Throwable $e) {
            ob_end_clean();
            error_log("View rendering failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Share data with all views
     * @param string|array $key Data key or array of key-value pairs
     * @param mixed $value Value if key is string
     * @return self
     */
    public function share($key, $value = null): self {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }
        return $this;
    }

    /* ROUTE REGISTRATION METHODS */

    public function get(string $path, callable $callback): self {
        $this->addRoute('GET', $path, $callback);
        return $this;
    }

    public function post(string $path, callable $callback): self {
        $this->addRoute('POST', $path, $callback);
        return $this;
    }

    public function put(string $path, callable $callback): self {
        $this->addRoute('PUT', $path, $callback);
        return $this;
    }

    public function patch(string $path, callable $callback): self {
        $this->addRoute('PATCH', $path, $callback);
        return $this;
    }

    public function delete(string $path, callable $callback): self {
        $this->addRoute('DELETE', $path, $callback);
        return $this;
    }

    /**
     * Get all registered routes (for debugging)
     */
    public function getRoutes(): array {
        return [
            'GET' => array_keys($this->routes['GET'] ?? []),
            'POST' => array_keys($this->routes['POST'] ?? [])
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
    public static function get_current_route(): object {
        return (object)[
            'method' => self::$currentMethod,
            'path' => self::$currentPath,
            'params' => self::$currentParams,
            'matched' => !empty(self::$currentMethod) && self::$currentPath !== ''
        ];
    }

    /* MIDDLEWARE AND ERROR HANDLING */

    public function addMiddleware(callable $middleware): self {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function notFound(callable $callback): self {
        $this->notFoundCallback = $callback;
        return $this;
    }

    /* RATE LIMITING METHODS */

    public function initRedisRateLimiting(array $config = ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.0, 'password' => null]): bool {
        
        try {
            $this -> redis = new Redis( );
            $connected = $this -> redis -> connect(
                $config['host'],
                $config['port'],
                $config['timeout'],
            );

            // Select database
            $this -> redis -> select( 1 );
            
            // Set prefix if needed
            $this -> redis -> setOption( Redis::OPT_PREFIX, 'KPTV_RL:' );

            if (!$connected) {
                throw new RuntimeException('Failed to connect to Redis');
            }

            if (!empty($config['password'])) {
                $this->redis->auth($config['password']);
            }

            // Test the connection
            $this->redis->ping();
            
            $this->rateLimits['global']['storage'] = 'redis';
            $this->rateLimitingEnabled = true;
            return true;
        } catch (Throwable $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            $this->rateLimitingEnabled = false;
            return false;
        }
    }

    public function enableFileRateLimiting(): void {
        $this->rateLimits['global']['storage'] = 'file';
        $this->rateLimitingEnabled = true;
    }

    public function disableRateLimiting(): void {
        $this->rateLimitingEnabled = false;
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