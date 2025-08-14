<?php
/**
 * KPT Router - Core Routing Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'Router_Request_Processor' ) ) {

    trait Router_Request_Processor {
        
        private $notFoundCallback;
        private static string $currentMethod = '';
        private static string $currentPath = '';
        private static array $currentParams = [];

        /**
         * Set 404 Not Found handler
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable $callback Handler function
         * @return self
         */
        public function notFound(callable $callback): self {
            $this->notFoundCallback = $callback;
            return $this;
        }

        /**
         * Dispatch the router to handle current request
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function dispatch(): void {
            try {
                self::$currentMethod = $this->getRequestMethod();
                self::$currentPath = $this->getRequestUri();

                if ($this->executeMiddlewares($this->middlewares) === false) {
                    return;
                }

                if ($this->rateLimitingEnabled) {
                    $this->applyRateLimiting();
                }

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

            } catch (\Throwable $e) {
                LOG::error("Dispatch error: " . $e->getMessage());
                $this->handleError($e);
            }
        }

        /**
         * Get the request URI
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string Sanitized request URI
         */
        private function getRequestUri(): string {
            $uri = parse_url(( KPT::get_user_uri( ) ), PHP_URL_PATH);
            return KPT::sanitize_path( $uri );
        }

        /**
         * Get the request method
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string HTTP request method
         */
        private function getRequestMethod(): string {
            $method = ($_SERVER['REQUEST_METHOD']) ?? 'GET';
            return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'])
                ? $method
                : 'GET';
        }

        /**
         * Find route handler for current request
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $method HTTP method
         * @param string $uri Request URI
         * @return array|null Array containing handler and parameters or null if not found
         */
        private function findRouteHandler(string $method, string $uri): ?array {
            $uri = strtok($uri, '?');
            $uri = rtrim($uri, '/') ?: '/';

            LOG::debug("=== ROUTE MATCHING DEBUG ===", [
                'method' => $method,
                'uri' => $uri,
                'available_routes' => array_keys($this->routes[$method] ?? [])
            ]);
            
            if (isset($this->routes[$method][$uri])) {
                return [
                    'callback' => $this->routes[$method][$uri],
                    'params' => []
                ];
            }

            foreach ($this->routes[$method] ?? [] as $routePath => $callback) {
                $pattern = $this->convertRouteToPattern($routePath);

                LOG::debug("Testing route pattern", [
                    'pattern' => $pattern,
                    'route_path' => $routePath,
                    'testing_against' => $uri
                ]);

                if (preg_match($pattern, $uri, $matches)) {
                    LOG::debug("ROUTE MATCHED!", [
                        'route_path' => $routePath,
                        'matches' => $matches,
                        'callback_type' => gettype($callback)
                    ]);
                    
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

            LOG::debug("NO ROUTE MATCHED", ['uri' => $uri]);

            return null;
        }

        /**
         * Convert route path to regex pattern
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $routePath Route path to convert
         * @return string Regex pattern
         */
        private function convertRouteToPattern(string $routePath): string {
            return '#^' . preg_replace('/\{([a-z][a-z0-9_]*)\}/i', '(?P<$1>[^/]+)', $routePath) . '$#i';
        }

        /**
         * Execute route handler
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable $handler Handler to execute
         * @param array $params Parameters to pass to handler
         */
        private function executeHandler(callable $handler, array $params = []): void {
            try {
                $currentRoute = self::get_current_route();
                $result = call_user_func_array($handler, $params);
                
                if (is_string($result)) {
                    echo $result;
                } elseif ($result !== null) {
                    error_log("Unexpected return type from handler: " . gettype($result));
                }
            } catch (\Throwable $e) {
                LOG::error("Handler execution failed: " . $e->getMessage(), include_stack: true);
                $this->handleError($e);
            }
        }

        /**
         * Send 404 Not Found response
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        private function sendNotFoundResponse(): void {
            http_response_code(404);
            header('Content-Type: text/html');
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        /**
         * Handle errors
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param Throwable $e Exception to handle
         */
        private function handleError(\Throwable $e): void {
            LOG::error('Router error: ' . $e->getMessage(), include_stack: true);
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            http_response_code($code);
            
            if (ini_get('display_errors')) {
                echo "Error {$code}: " . $e->getMessage();
            } else {
                echo "An error occurred. Please try again later.";
            }
            
            exit;
        }

        /**
         * Get information about current matched route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return object Object containing route information
         */
        public static function get_current_route(): object {
            return (object)[
                'method' => self::$currentMethod,
                'path' => self::$currentPath,
                'params' => self::$currentParams,
                'matched' => !empty(self::$currentMethod) && self::$currentPath !== ''
            ];
        }
    }

}