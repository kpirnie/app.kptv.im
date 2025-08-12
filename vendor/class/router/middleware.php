<?php
/**
 * KPT Router - Middleware Handling Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// make sure it doesn't already exist
if( ! trait_exists( 'KPT_Router_MiddlewareHandler' ) ) {

    /**
     * KPT Router - Middleware Handling Trait
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait KPT_Router_MiddlewareHandler {
        
        private array $middlewares = [];

        /**
         * Add global middleware
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable $middleware Middleware function
         * @return self
         */
        public function addMiddleware(callable $middleware): self {
            $this->middlewares[] = $middleware;
            return $this;
        }

        /**
         * Execute middlewares
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $middlewares Array of middlewares to execute
         * @return bool True if all middlewares passed, false if one failed
         */
        private function executeMiddlewares(array $middlewares): bool {
            foreach ($middlewares as $middleware) {
                if ($middleware() === false) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Resolve middleware to callable
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $middleware Middleware to resolve
         * @return callable|null Resolved middleware or null if cannot be resolved
         */
        private function resolveMiddleware($middleware): ?callable {
            if (is_callable($middleware)) {
                return $middleware;
            }

            if (is_string($middleware)) {
                if (isset($this->middlewareDefinitions[$middleware])) {
                    $definition = $this->middlewareDefinitions[$middleware];
                    
                    if (is_string($definition)) {
                        return $this->resolveStringMiddleware($definition);
                    }
                    
                    return $definition;
                }
                
                return $this->resolveStringMiddleware($middleware);
            }

            error_log("Warning: Could not resolve middleware: " . (is_string($middleware) ? $middleware : 'non-string'));
            return null;
        }

        /**
         * Resolve string middleware
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $middleware Middleware string to resolve
         * @return callable|null Resolved middleware or null if unknown type
         */
        private function resolveStringMiddleware(string $middleware): ?callable {
            // Check if it's a registered middleware definition
            if (isset($this->middlewareDefinitions[$middleware])) {
                return $this->middlewareDefinitions[$middleware];
            }
            
            // If no middleware found, log warning and return null
            error_log("Warning: Middleware '{$middleware}' not found in registered definitions");
            return null;
        }

        /**
         * Create wrapped handler with middleware
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable $handler Original handler
         * @param array $middlewares Middlewares to wrap
         * @return callable Wrapped handler
         */
        private function createWrappedHandler(callable $handler, array $middlewares): callable {
            return function(...$params) use ($handler, $middlewares) {
                foreach ($middlewares as $middleware) {
                    $middlewareCallable = $this->resolveMiddleware($middleware);
                    
                    if ($middlewareCallable && $middlewareCallable() === false) {
                        return;
                    }
                }

                return call_user_func_array($handler, $params);
            };
        }
    }

}