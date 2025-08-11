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
// MMAP ASYNC TRAIT
// =====================================================================

if ( ! trait_exists( 'KPT_Cache_MMAP_Async' ) ) {

    trait KPT_Cache_MMAP_Async {
        
        /**
         * Async get from MMAP
         */
        public static function getFromMmapAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::getFromMmap($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::getFromMmap($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async set to MMAP
         */
        public static function setToMmapAsync(string $key, mixed $data, int $ttl): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $data, $ttl, $resolve, $reject) {
                        try {
                            $result = self::setToMmap($key, $data, $ttl);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::setToMmap($key, $data, $ttl);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async batch MMAP operations
         */
        public static function mmapBatchAsync(array $operations): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($operations) {
                if (self::$_async_enabled && self::$_event_loop) {
                    $promises = [];
                    
                    foreach ($operations as $op) {
                        $promise = match($op['type']) {
                            'get' => self::getFromMmapAsync($op['key']),
                            'set' => self::setToMmapAsync($op['key'], $op['data'], $op['ttl'] ?? 3600),
                            default => KPT_Cache_Promise::reject(new Exception("Unknown operation: {$op['type']}"))
                        };
                        $promises[] = $promise;
                    }
                    
                    KPT_Cache_Promise::all($promises)
                        ->then(function($results) use ($resolve) {
                            $resolve($results);
                        })
                        ->catch(function($error) use ($reject) {
                            $reject($error);
                        });
                } else {
                    try {
                        $results = [];
                        foreach ($operations as $op) {
                            $results[] = match($op['type']) {
                                'get' => self::getFromMmap($op['key']),
                                'set' => self::setToMmap($op['key'], $op['data'], $op['ttl'] ?? 3600),
                                default => false
                            };
                        }
                        $resolve($results);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}