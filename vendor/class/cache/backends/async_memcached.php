<?php
/**
 * Async Cache Traits for I/O-intensive cache backends
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// =====================================================================
// MEMCACHED ASYNC TRAIT
// =====================================================================

if ( ! trait_exists( 'KPT_Cache_Memcached_Async' ) ) {

    trait KPT_Cache_Memcached_Async {
        
        /**
         * Async get from Memcached
         */
        public static function getFromMemcachedAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::getFromMemcached($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::getFromMemcached($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async set to Memcached
         */
        public static function setToMemcachedAsync(string $key, mixed $data, int $ttl): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $data, $ttl, $resolve, $reject) {
                        try {
                            $result = self::setToMemcached($key, $data, $ttl);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::setToMemcached($key, $data, $ttl);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async multi-get from Memcached
         */
        public static function memcachedMultiGetAsync(array $keys): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($keys) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($keys, $resolve, $reject) {
                        try {
                            $result = self::memcachedMultiGet($keys);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::memcachedMultiGet($keys);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async multi-set to Memcached
         */
        public static function memcachedMultiSetAsync(array $items, int $ttl = 3600): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($items, $ttl) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($items, $ttl, $resolve, $reject) {
                        try {
                            $result = self::memcachedMultiSet($items, $ttl);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::memcachedMultiSet($items, $ttl);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}
