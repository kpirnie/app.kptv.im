<?php
/**
 * KPT Router - HTTP Methods Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

trait KPT_Router_HttpMethodsTrait {

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
