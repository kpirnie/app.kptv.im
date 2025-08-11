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
// OPCACHE ASYNC TRAIT
// =====================================================================

if ( ! trait_exists( 'KPT_Cache_OPCache_Async' ) ) {

    trait KPT_Cache_OPCache_Async {
        
        /**
         * Async get from OPCache
         */
        public static function getFromOPcacheAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::getFromOPcache($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::getFromOPcache($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async set to OPCache
         */
        public static function setToOPcacheAsync(string $key, mixed $data, int $ttl): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $data, $ttl, $resolve, $reject) {
                        try {
                            $result = self::setToOPcache($key, $data, $ttl);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::setToOPcache($key, $data, $ttl);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async cleanup OPCache files
         */
        public static function cleanupOPcacheFilesAsync(): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($resolve, $reject) {
                        try {
                            $result = self::cleanupOPcacheFiles();
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::cleanupOPcacheFiles();
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}
