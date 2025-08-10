<?php
/**
 * KPT Router Class (Main Class)
 * 
 * This class provides a comprehensive routing solution with middleware support, 
 * rate limiting, and view rendering capabilities.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if( ! class_exists( 'KPT_Router' ) ) {

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
    class KPT_Router {

        // inherit our traits
        use KPT_Router_RateLimitingTrait;
        use KPT_Router_RouteRegistrationTrait;
        use KPT_Router_ViewRenderingTrait;
        use KPT_Router_HttpMethodsTrait;
        use KPT_Router_MiddlewareHandlingTrait;
        use KPT_Router_HandlerResolutionTrait;
        use KPT_Router_CoreRoutingTrait;

        /**
         * Class properties
         * 
         * @since 8.4
         * @var string
         */
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
            $this->basePath = $this->sanitizePath($basePath);
            $this->viewsPath = defined('KPT_PATH') ? KPT_PATH . '/views' : '';
            
            if (!file_exists($this->rateLimitPath)) {
                mkdir($this->rateLimitPath, 0755, true);
            }
        }

        /**
         * Sanitize path
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string|null $path Path to sanitize
         * @return string Sanitized path
         */
        private function sanitizePath(?string $path): string {
            if (empty($path)) return '/';
            $path = parse_url($path, PHP_URL_PATH) ?? '';
            $path = trim(str_replace(['../', './'], '', $path), '/');
            return $path === '' ? '/' : '/' . $path;
        }

        /**
         * Destructor
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function __destruct() {
            $this->routes = [];
            $this->middlewares = [];
            $this->middlewareDefinitions = [];

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
