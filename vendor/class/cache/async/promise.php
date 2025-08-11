<?php
/**
 * Simple Promise Implementation for Cache Operations
 * Compatible with ReactPHP and other async libraries
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! class_exists( 'KPT_Cache_Promise' ) ) {

    class KPT_Cache_Promise {
        
        private string $state = 'pending'; // pending, fulfilled, rejected
        private mixed $value = null;
        private mixed $reason = null;
        private array $onFulfilled = [];
        private array $onRejected = [];
        
        public function __construct(?callable $executor = null) {
            if ($executor) {
                try {
                    $executor(
                        [$this, 'fulfill'],
                        [$this, 'fail']
                    );
                } catch (Exception $e) {
                    $this->fail($e);
                }
            }
        }
        
        public function fulfill(mixed $value): void {
            if ($this->state !== 'pending') return;
            
            $this->state = 'fulfilled';
            $this->value = $value;
            
            foreach ($this->onFulfilled as $callback) {
                try {
                    $callback($value);
                } catch (Exception $e) {
                    // Handle callback errors
                }
            }
            
            $this->onFulfilled = [];
            $this->onRejected = [];
        }
        
        public function fail(mixed $reason): void {
            if ($this->state !== 'pending') return;
            
            $this->state = 'rejected';
            $this->reason = $reason;
            
            foreach ($this->onRejected as $callback) {
                try {
                    $callback($reason);
                } catch (Exception $e) {
                    // Handle callback errors
                }
            }
            
            $this->onFulfilled = [];
            $this->onRejected = [];
        }
        
        public function then(?callable $onFulfilled = null, ?callable $onRejected = null): self {
            $promise = new self();
            
            $wrappedOnFulfilled = function($value) use ($onFulfilled, $promise) {
                if ($onFulfilled) {
                    try {
                        $result = $onFulfilled($value);
                        $promise->fulfill($result);
                    } catch (Exception $e) {
                        $promise->fail($e);
                    }
                } else {
                    $promise->fulfill($value);
                }
            };
            
            $wrappedOnRejected = function($reason) use ($onRejected, $promise) {
                if ($onRejected) {
                    try {
                        $result = $onRejected($reason);
                        $promise->fulfill($result);
                    } catch (Exception $e) {
                        $promise->fail($e);
                    }
                } else {
                    $promise->fail($reason);
                }
            };
            
            if ($this->state === 'fulfilled') {
                $wrappedOnFulfilled($this->value);
            } elseif ($this->state === 'rejected') {
                $wrappedOnRejected($this->reason);
            } else {
                $this->onFulfilled[] = $wrappedOnFulfilled;
                $this->onRejected[] = $wrappedOnRejected;
            }
            
            return $promise;
        }
        
        public function catch(callable $onRejected): self {
            return $this->then(null, $onRejected);
        }
        
        public static function resolve(mixed $value): self {
            $promise = new self();
            $promise->fulfill($value);
            return $promise;
        }
        
        public static function reject(mixed $reason): self {
            $promise = new self();
            $promise->fail($reason);
            return $promise;
        }
        
        public static function all(array $promises): self {
            $promise = new self();
            $results = [];
            $remaining = count($promises);
            
            if ($remaining === 0) {
                $promise->fulfill([]);
                return $promise;
            }
            
            foreach ($promises as $index => $p) {
                $p->then(
                    function($value) use (&$results, &$remaining, $index, $promise) {
                        $results[$index] = $value;
                        $remaining--;
                        
                        if ($remaining === 0) {
                            $promise->fulfill($results);
                        }
                    },
                    function($reason) use ($promise) {
                        $promise->fail($reason);
                    }
                );
            }
            
            return $promise;
        }
        
        public static function race(array $promises): self {
            $promise = new self();
            
            foreach ($promises as $p) {
                $p->then(
                    function($value) use ($promise) {
                        $promise->fulfill($value);
                    },
                    function($reason) use ($promise) {
                        $promise->fail($reason);
                    }
                );
            }
            
            return $promise;
        }
        
        public static function allSettled(array $promises): self {
            $promise = new self();
            $results = [];
            $remaining = count($promises);
            
            if ($remaining === 0) {
                $promise->fulfill([]);
                return $promise;
            }
            
            foreach ($promises as $index => $p) {
                $p->then(
                    function($value) use (&$results, &$remaining, $index, $promise) {
                        $results[$index] = ['status' => 'fulfilled', 'value' => $value];
                        $remaining--;
                        
                        if ($remaining === 0) {
                            $promise->fulfill($results);
                        }
                    },
                    function($reason) use (&$results, &$remaining, $index, $promise) {
                        $results[$index] = ['status' => 'rejected', 'reason' => $reason];
                        $remaining--;
                        
                        if ($remaining === 0) {
                            $promise->fulfill($results);
                        }
                    }
                );
            }
            
            return $promise;
        }
        
        public function finally(callable $onFinally): self {
            return $this->then(
                function($value) use ($onFinally) {
                    $onFinally();
                    return $value;
                },
                function($reason) use ($onFinally) {
                    $onFinally();
                    throw $reason;
                }
            );
        }
        
        public function getState(): string {
            return $this->state;
        }
        
        public function getValue(): mixed {
            return $this->value;
        }
        
        public function getReason(): mixed {
            return $this->reason;
        }
        
        public function isPending(): bool {
            return $this->state === 'pending';
        }
        
        public function isFulfilled(): bool {
            return $this->state === 'fulfilled';
        }
        
        public function isRejected(): bool {
            return $this->state === 'rejected';
        }
        
        public function isSettled(): bool {
            return $this->state !== 'pending';
        }
    }
}