<?php
/**
 * KPT Router - Handler Resolution Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// make sure it doesn't already exist
if( ! trait_exists( 'KPT_Router_Response_Handler' ) ) {

    trait KPT_Router_Response_Handler {

        private string $viewsPath = '';
        private array $viewData = [];

        /**
         * Set the views directory path
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $path Path to views directory
         * @return self
         */
        public function setViewsPath(string $path): self {
            $this->viewsPath = rtrim($path, '/');
            return $this;
        }

        /**
         * Render a view template with data
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $template View file path (relative to views directory)
         * @param array $data Data to pass to the view
         * @return string Rendered content
         * @throws RuntimeException If view file not found
         */
        public function view(string $template, array $data = []): string {
            $templatePath = $this->viewsPath . '/' . ltrim($template, '/');
            
            if (!file_exists($templatePath)) {
                $error = "View template not found: $templatePath";
                error_log($error);
                throw new RuntimeException($error);
            }

            extract(array_merge($this->viewData, $data), EXTR_SKIP);
            ob_start();

            try {
                include $templatePath;
                $content = ob_get_clean();
                return $content;
            } catch (Throwable $e) {
                ob_end_clean();
                error_log("View rendering failed: " . $e->getMessage());
                throw $e;
            }
        }

        /**
         * Share data with all views
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
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

        /**
         * Resolve handler to callable
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $handler Handler to resolve
         * @param array $data Additional handler data
         * @return callable Resolved handler
         * @throws InvalidArgumentException If handler cannot be resolved
         */
        private function resolveHandler($handler, array $data = []): callable {
            if (is_callable($handler)) {
                return $handler;
            }

            if (is_string($handler)) {
                if (strpos($handler, ':') !== false) {
                    list($type, $target) = explode(':', $handler, 2);
                    
                    switch ($type) {
                        case 'view':
                            return $this->createViewHandler($target, $data);
                        case 'controller':
                            return $this->createControllerHandler($target);
                        default:
                            throw new InvalidArgumentException("Unknown handler type: {$type}");
                    }
                }
                
                // Check if it's a controller format (Class@method)
                if (strpos($handler, '@') !== false) {
                    return $this->createControllerHandler($handler);
                }
                
                return $this->createViewHandler($handler, $data);
            }

            throw new InvalidArgumentException('Handler must be callable or string');
        }

        /**
         * Create view handler
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $viewPath Path to view file
         * @param array $data Additional view data
         * @return callable View handler
         */
        private function createViewHandler(string $viewPath, array $data = []): callable {
            return function(...$params) use ($viewPath, $data) {
                $viewData = [];
                
                $currentRoute = self::get_current_route();
                foreach ($currentRoute->params as $key => $value) {
                    $viewData[$key] = $value;
                }
                
                if (isset($data['currentRoute']) && $data['currentRoute']) {
                    $viewData['currentRoute'] = $currentRoute;
                }
                
                $viewData = array_merge($viewData, $data);
                return $this->view($viewPath, $viewData);
            };
        }

        /**
         * Create controller handler
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $controller Controller identifier (e.g., "UserController@show" or "controller:UserController@show")
         * @return callable Controller handler
         * @throws InvalidArgumentException If controller format is invalid
         * @throws RuntimeException If controller class doesn't exist or method is not callable
         */
        private function createControllerHandler(string $controller): callable {
            return function(...$params) use ($controller) {
                if (!strpos($controller, '@')) {
                    throw new InvalidArgumentException("Controller format must be 'ClassName@methodName', got: {$controller}");
                }

                list($class, $method) = explode('@', $controller, 2);
                
                // Trim any whitespace
                $class = trim($class);
                $method = trim($method);
                
                if (empty($class) || empty($method)) {
                    throw new InvalidArgumentException("Both controller class and method must be specified: {$controller}");
                }
                
                // Check if class exists
                if (!class_exists($class)) {
                    throw new RuntimeException("Controller class not found: {$class}");
                }
                
                // Instantiate the controller
                $controllerInstance = new $class();
                
                // Check if method exists and is callable
                if (!method_exists($controllerInstance, $method)) {
                    throw new RuntimeException("Method '{$method}' not found in controller '{$class}'");
                }
                
                if (!is_callable([$controllerInstance, $method])) {
                    throw new RuntimeException("Method '{$method}' is not callable in controller '{$class}'");
                }
                
                // Call the controller method with parameters
                $result = call_user_func_array([$controllerInstance, $method], $params);
                
                // Clean up
                unset($controllerInstance);
                
                return $result;
            };
        }
        
    }

}