<?php
/**
 * KPT Router - View Rendering Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

trait KPT_Router_ViewRenderingTrait {
    
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
}
