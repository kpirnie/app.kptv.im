<?php
/**
 * Async Cache Operations
 * Provides promise-based versions of cache methods
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'KPT_Cache_Async' ) ) {

    trait KPT_Cache_Async {
        
        private static ?object $event_loop = null;
        private static bool $async_enabled = false;
        
        /**
         * Initialize async support
         */
        public static function enableAsync(?object $eventLoop = null): void {
            self::$async_enabled = true;
            self::$event_loop = $eventLoop;
        }
        
        /**
         * Async get operation
         */
        public static function getAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$async_enabled && self::$event_loop) {
                    // Use event loop for true async
                    self::$event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::get($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    // Fallback to immediate execution
                    try {
                        $result = self::get($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async set operation
         */
        public static function setAsync(string $key, mixed $data, int $ttl = 3600): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $data, $ttl, $resolve, $reject) {
                        try {
                            $result = self::set($key, $data, $ttl);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::set($key, $data, $ttl);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async delete operation
         */
        public static function deleteAsync(string $key): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $resolve, $reject) {
                        try {
                            $result = self::delete($key);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::delete($key);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async batch get operation
         */
        public static function getBatchAsync(array $keys): KPT_Cache_Promise {
            $promises = array_map(function($key) {
                return self::getAsync($key);
            }, $keys);
            
            return KPT_Cache_Promise::all($promises)
                ->then(function($results) use ($keys) {
                    return array_combine($keys, $results);
                });
        }
        
        /**
         * Async batch set operation
         */
        public static function setBatchAsync(array $items, int $ttl = 3600): KPT_Cache_Promise {
            $promises = [];
            
            foreach ($items as $key => $data) {
                $promises[] = self::setAsync($key, $data, $ttl);
            }
            
            return KPT_Cache_Promise::all($promises);
        }
        
        /**
         * Async batch delete operation
         */
        public static function deleteBatchAsync(array $keys): KPT_Cache_Promise {
            $promises = array_map(function($key) {
                return self::deleteAsync($key);
            }, $keys);
            
            return KPT_Cache_Promise::all($promises);
        }
        
        /**
         * Async tier-specific get operation
         */
        public static function getFromTierAsync(string $key, string $tier): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $tier) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $tier, $resolve, $reject) {
                        try {
                            $result = self::getFromTier($key, $tier);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::getFromTier($key, $tier);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async tier-specific set operation
         */
        public static function setToTierAsync(string $key, mixed $data, int $ttl, string $tier): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl, $tier) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $data, $ttl, $tier, $resolve, $reject) {
                        try {
                            $result = self::setToTier($key, $data, $ttl, $tier);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::setToTier($key, $data, $ttl, $tier);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async tier-specific delete operation
         */
        public static function deleteFromTierAsync(string $key, string $tier): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $tier) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $tier, $resolve, $reject) {
                        try {
                            $result = self::deleteFromTier($key, $tier);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::deleteFromTier($key, $tier);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async multi-tier set operation
         */
        public static function setToTiersAsync(string $key, mixed $data, int $ttl, array $tiers): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $data, $ttl, $tiers) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $data, $ttl, $tiers, $resolve, $reject) {
                        try {
                            $result = self::setToTiers($key, $data, $ttl, $tiers);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::setToTiers($key, $data, $ttl, $tiers);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async multi-tier delete operation
         */
        public static function deleteFromTiersAsync(string $key, array $tiers): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $tiers) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $tiers, $resolve, $reject) {
                        try {
                            $result = self::deleteFromTiers($key, $tiers);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::deleteFromTiers($key, $tiers);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async tier preference operation
         */
        public static function getWithTierPreferenceAsync(string $key, string $preferred_tier, bool $fallback_on_failure = true): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($key, $preferred_tier, $fallback_on_failure) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($key, $preferred_tier, $fallback_on_failure, $resolve, $reject) {
                        try {
                            $result = self::getWithTierPreference($key, $preferred_tier, $fallback_on_failure);
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::getWithTierPreference($key, $preferred_tier, $fallback_on_failure);
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async pipeline operations for better performance
         */
        public static function pipelineAsync(array $operations): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) use ($operations) {
                $promises = [];
                
                foreach ($operations as $index => $operation) {
                    $method = $operation['method'];
                    $args = $operation['args'] ?? [];
                    
                    $promise = match($method) {
                        'get' => self::getAsync($args[0]),
                        'set' => self::setAsync($args[0], $args[1], $args[2] ?? 3600),
                        'delete' => self::deleteAsync($args[0]),
                        'getFromTier' => self::getFromTierAsync($args[0], $args[1]),
                        'setToTier' => self::setToTierAsync($args[0], $args[1], $args[2] ?? 3600, $args[3]),
                        'deleteFromTier' => self::deleteFromTierAsync($args[0], $args[1]),
                        default => KPT_Cache_Promise::reject(new Exception("Unknown method: {$method}"))
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
            });
        }
        
        /**
         * Async clear operation
         */
        public static function clearAsync(): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($resolve, $reject) {
                        try {
                            $result = self::clear();
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::clear();
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Async cleanup operation
         */
        public static function cleanupAsync(): KPT_Cache_Promise {
            return new KPT_Cache_Promise(function($resolve, $reject) {
                if (self::$async_enabled && self::$event_loop) {
                    self::$event_loop->futureTick(function() use ($resolve, $reject) {
                        try {
                            $result = self::cleanup();
                            $resolve($result);
                        } catch (Exception $e) {
                            $reject($e);
                        }
                    });
                } else {
                    try {
                        $result = self::cleanup();
                        $resolve($result);
                    } catch (Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
        
        /**
         * Check if async is enabled
         */
        public static function isAsyncEnabled(): bool {
            return self::$async_enabled;
        }
        
        /**
         * Get event loop
         */
        public static function getEventLoop(): ?object {
            return self::$event_loop;
        }
        
        /**
         * Disable async support
         */
        public static function disableAsync(): void {
            self::$async_enabled = false;
            self::$event_loop = null;
        }
    }
}