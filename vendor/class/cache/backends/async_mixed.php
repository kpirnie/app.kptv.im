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
// MIXED ASYNC OPERATIONS TRAIT
// =====================================================================

if ( ! trait_exists( 'Cache_Mixed_Async' ) ) {

    trait Cache_Mixed_Async {
        
        /**
         * Async multi-tier operation with different backends
         */
        public static function multiTierOperationAsync(array $operations): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($operations) {
                if (self::$_async_enabled && self::$_event_loop) {
                    $promises = [];
                    
                    foreach ($operations as $op) {
                        $tier = $op['tier'];
                        $method = $op['method'];
                        $key = $op['key'];
                        
                        $promise = match([$tier, $method]) {
                            [self::TIER_MEMCACHED, 'get'] => self::getFromMemcachedAsync($key),
                            [self::TIER_MEMCACHED, 'set'] => self::setToMemcachedAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            [self::TIER_FILE, 'get'] => self::getFromFileAsync($key),
                            [self::TIER_FILE, 'set'] => self::setToFileAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            [self::TIER_MMAP, 'get'] => self::getFromMmapAsync($key),
                            [self::TIER_MMAP, 'set'] => self::setToMmapAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            [self::TIER_OPCACHE, 'get'] => self::getFromOPcacheAsync($key),
                            [self::TIER_OPCACHE, 'set'] => self::setToOPcacheAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            default => Cache_Promise::reject(new Exception("Unsupported async operation: {$tier}:{$method}"))
                        };
                        
                        $promises[] = $promise;
                    }
                    
                    Cache_Promise::all($promises)
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
                            $results[] = self::executeNonAsyncOperation($op);
                        }
                        $resolve($results);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Execute non-async operation fallback
         */
        private static function executeNonAsyncOperation(array $op): mixed {
            $tier = $op['tier'];
            $method = $op['method'];
            $key = $op['key'];
            
            return match([$tier, $method]) {
                [self::TIER_MEMCACHED, 'get'] => self::getFromMemcached($key),
                [self::TIER_MEMCACHED, 'set'] => self::setToMemcached($key, $op['data'], $op['ttl'] ?? 3600),
                [self::TIER_FILE, 'get'] => self::getFromFile($key),
                [self::TIER_FILE, 'set'] => self::setToFile($key, $op['data'], $op['ttl'] ?? 3600),
                [self::TIER_MMAP, 'get'] => self::getFromMmap($key),
                [self::TIER_MMAP, 'set'] => self::setToMmap($key, $op['data'], $op['ttl'] ?? 3600),
                [self::TIER_OPCACHE, 'get'] => self::getFromOPcache($key),
                [self::TIER_OPCACHE, 'set'] => self::setToOPcache($key, $op['data'], $op['ttl'] ?? 3600),
                default => false
            };
        }
        
        /**
         * Parallel cache warming for I/O intensive tiers
         */
        public static function parallelWarmCacheAsync(array $warm_data): Cache_Promise {
            return new Cache_Promise(function($resolve, $reject) use ($warm_data) {
                if (self::$_async_enabled && self::$_event_loop) {
                    $promises = [];
                    
                    foreach ($warm_data as $item) {
                        $key = $item['key'];
                        $data = $item['data'];
                        $ttl = $item['ttl'] ?? 3600;
                        $tiers = $item['tiers'] ?? [self::TIER_FILE, self::TIER_MEMCACHED];
                        
                        foreach ($tiers as $tier) {
                            $promise = match($tier) {
                                self::TIER_MEMCACHED => self::setToMemcachedAsync($key, $data, $ttl),
                                self::TIER_FILE => self::setToFileAsync($key, $data, $ttl),
                                self::TIER_MMAP => self::setToMmapAsync($key, $data, $ttl),
                                self::TIER_OPCACHE => self::setToOPcacheAsync($key, $data, $ttl),
                                default => Cache_Promise::resolve(false)
                            };
                            $promises[] = $promise;
                        }
                    }
                    
                    Cache_Promise::all($promises)
                        ->then(function($results) use ($resolve) {
                            $resolve(['warmed' => count($results), 'results' => $results]);
                        })
                        ->catch(function($error) use ($reject) {
                            $reject($error);
                        });
                } else {
                    try {
                        $warmed = 0;
                        foreach ($warm_data as $item) {
                            $key = $item['key'];
                            $data = $item['data'];
                            $ttl = $item['ttl'] ?? 3600;
                            $tiers = $item['tiers'] ?? [self::TIER_FILE, self::TIER_MEMCACHED];
                            
                            foreach ($tiers as $tier) {
                                $success = self::setToTierInternal($key, $data, $ttl, $tier);
                                if ($success) $warmed++;
                            }
                        }
                        $resolve(['warmed' => $warmed]);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}