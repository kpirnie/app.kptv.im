<?php
/**
 * KPT Router - HTTP Methods Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure it doesn't already exist
if( ! trait_exists( 'KPT_Router_Route_Handler' ) ) {

    trait KPT_Router_Route_Handler {

        private array $routes = [];
        private array $middlewareDefinitions = [];

        /**
         * Register middleware definitions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $definitions Array of middleware name => callable pairs
         * @return self
         */
        public function registerMiddlewareDefinitions(array $definitions): self {
            $this->middlewareDefinitions = array_merge($this->middlewareDefinitions, $definitions);
            return $this;
        }

        /**
         * Register routes from array definition
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $routes Array of route definitions
         * @return self
         */
        public function registerRoutes(array $routes): self {
            foreach ($routes as $route) {
                $this->registerSingleRoute($route);
            }
            return $this;
        }

        /**
         * Register a single route from array definition
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $route Route definition array
         * @return self
         * @throws InvalidArgumentException If route definition is invalid
         */
        private function registerSingleRoute(array $route): self {
            if (!isset($route['method']) || !isset($route['path']) || !isset($route['handler'])) {
                throw new InvalidArgumentException('Route must have method, path, and handler defined');
            }

            $method = strtoupper($route['method']);
            $path = $route['path'];
            $handler = $route['handler'];
            $middlewares = $route['middleware'] ?? [];
            $data = $route['data'] ?? [];

            if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'TRACE', 'CONNECT'])) {
                throw new InvalidArgumentException("Invalid HTTP method: {$method}");
            }

            $callableHandler = $this->resolveHandler($handler, $data);
            $wrappedHandler = $this->createWrappedHandler($callableHandler, $middlewares);
            $this->addRoute($method, $path, $wrappedHandler);

            return $this;
        }

        /**
         * Add a route to the router
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $method HTTP method
         * @param string $path Route path
         * @param callable $callback Route handler
         */
        private function addRoute(string $method, string $path, callable $callback): void {
            $path = $this->sanitizePath($path);
            $fullPath = $this->basePath === '/' ? $path : $this->sanitizePath($this->basePath . $path);
            $fullPath = preg_replace('#/+#', '/', $fullPath);
            
            if (!isset($this->routes[$method][$fullPath])) {
                $this->routes[$method][$fullPath] = $callback;
            }
        }

        /**
         * Register a single middleware definition
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $name Middleware name
         * @param callable $middleware Middleware callable
         * @return self
         */
        public function registerMiddleware(string $name, callable $middleware): self {
            $this->middlewareDefinitions[$name] = $middleware;
            return $this;
        }

        /**
         * Get registered middleware definitions
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Array of middleware definitions
         */
        public function getMiddlewareDefinitions(): array {
            return $this->middlewareDefinitions;
        }

        /**
         * Get all registered routes
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return array Array of routes grouped by HTTP method
         */
        public function getRoutes(): array {
            return [
                'GET' => array_keys($this->routes['GET'] ?? []),
                'POST' => array_keys($this->routes['POST'] ?? []),
                'PUT' => array_keys($this->routes['PUT'] ?? []),
                'PATCH' => array_keys($this->routes['PATCH'] ?? []),
                'DELETE' => array_keys($this->routes['DELETE'] ?? []),
                'HEAD' => array_keys($this->routes['HEAD'] ?? []),
                'TRACE' => array_keys($this->routes['TRACE'] ?? []),
                'CONNECT' => array_keys($this->routes['CONNECT'] ?? []),
            ];
        }

        /**
         * Register a GET route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function get(string $path, callable $callback): self {
            $this->addRoute('GET', $path, $callback);
            return $this;
        }

        /**
         * Register a POST route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function post(string $path, callable $callback): self {
            $this->addRoute('POST', $path, $callback);
            return $this;
        }

        /**
         * Register a PUT route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function put(string $path, callable $callback): self {
            $this->addRoute('PUT', $path, $callback);
            return $this;
        }

        /**
         * Register a PATCH route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function patch(string $path, callable $callback): self {
            $this->addRoute('PATCH', $path, $callback);
            return $this;
        }

        /**
         * Register a DELETE route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function delete(string $path, callable $callback): self {
            $this->addRoute('DELETE', $path, $callback);
            return $this;
        }

        /**
         * Register a HEAD route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function head(string $path, callable $callback): self {
            $this->addRoute('HEAD', $path, $callback);
            return $this;
        }

        /**
         * Register a TRACE route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function trace(string $path, callable $callback): self {
            $this->addRoute('TRACE', $path, $callback);
            return $this;
        }

        /**
         * Register a CONNECT route
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self
         */
        public function connect(string $path, callable $callback): self {
            $this->addRoute('CONNECT', $path, $callback);
            return $this;
        }
        
    }

}
