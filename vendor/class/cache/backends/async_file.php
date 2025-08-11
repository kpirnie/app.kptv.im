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
// FILE CACHE ASYNC TRAIT  
// =====================================================================

if ( ! trait_exists( 'KPT_Cache_File_Async' ) ) {

    trait KPT_Cache_File_Async {
        
        /**
         * Async get from file cache
         */
        public static function getFromFileAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::getFromFile($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::getFromFile($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async set to file cache
         */
        public static function setToFileAsync(string $key, mixed $data, int $ttl): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $data, $ttl, $resolve, $reject) {
                        try {
                            $result = self::setToFile($key, $data, $ttl);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::setToFile($key, $data, $ttl);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async batch file operations
         */
        public static function fileCacheBatchAsync(array $operations): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($operations) {
                if (self::$_async_enabled && self::$_event_loop) {
                    $promises = [];
                    
                    foreach ($operations as $op) {
                        $promise = match($op['type']) {
                            'get' => self::getFromFileAsync($op['key']),
                            'set' => self::setToFileAsync($op['key'], $op['data'], $op['ttl'] ?? 3600),
                            'delete' => self::deleteFromFileAsync($op['key']),
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
                    // Fallback to synchronous batch processing
                    try {
                        $results = [];
                        foreach ($operations as $op) {
                            $results[] = match($op['type']) {
                                'get' => self::getFromFile($op['key']),
                                'set' => self::setToFile($op['key'], $op['data'], $op['ttl'] ?? 3600),
                                'delete' => self::deleteFromFile($op['key']),
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
        
        /**
         * Async delete from file cache
         */
        public static function deleteFromFileAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::deleteFromFile($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::deleteFromFile($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async cleanup expired files
         */
        public static function cleanupExpiredFilesAsync(): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) {
                if (self::$_async_enabled && self::$_event_loop) {
                    self::$_event_loop->futureTick(function() use ($resolve, $reject) {
                        try {
                            $result = self::cleanupExpiredFiles();
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::cleanupExpiredFiles();
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}
