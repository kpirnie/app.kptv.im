<?php
/**
 * Simple Promise Implementation for Cache Operations
 * Compatible with ReactPHP and other async libraries
 * 
 * Provides a complete Promise/A+ compatible implementation for asynchronous
 * cache operations with support for chaining, error handling, and concurrent
 * execution patterns.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class doesn't exist
if ( ! class_exists( 'Cache_Promise' ) ) {

    /**
     * KPT Cache Promise Class
     * 
     * A Promise/A+ compatible implementation for handling asynchronous cache
     * operations with support for chaining, error handling, and advanced
     * concurrency patterns like Promise.all() and Promise.race().
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Cache_Promise {
        
        // class properties
        private string $state = 'pending'; // pending, fulfilled, rejected
        private mixed $value = null;
        private mixed $reason = null;
        private array $onFulfilled = [];
        private array $onRejected = [];
        
        /**
         * Promise constructor
         * 
         * Creates a new promise with an optional executor function that receives
         * resolve and reject callbacks for immediate execution.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable|null $executor Optional executor function
         */
        public function __construct( ?callable $executor = null ) {

            // if we have an executor
            if ( $executor ) {

                // try to execute it
                try {

                    // call the executor with fulfill and fail callbacks
                    $executor(
                        [$this, 'fulfill'],
                        [$this, 'fail']
                    );

                // whoopsie...
                } catch ( \Exception $e ) {

                    // fail the promise with the exception
                    $this -> fail( $e );
                }
            }
        }
        
        /**
         * Fulfill the promise with a value
         * 
         * Transitions the promise from pending to fulfilled state and
         * executes all registered fulfillment callbacks.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $value The value to fulfill with
         * @return void Returns nothing
         */
        public function fulfill( mixed $value ): void {

            // if we're not pending, just return
            if ( $this -> state !== 'pending' ) return;
            
            // set the state and value
            $this -> state = 'fulfilled';
            $this -> value = $value;
            
            // execute all fulfillment callbacks
            foreach ( $this -> onFulfilled as $callback ) {

                // try to execute the callback
                try {

                    // call the callback with the value
                    $callback( $value );

                // whoopsie...
                } catch ( \Exception $e ) {

                    // Handle callback errors silently for now
                }
            }
            
            // clear the callback arrays
            $this -> onFulfilled = [];
            $this -> onRejected = [];
        }
        
        /**
         * Reject the promise with a reason
         * 
         * Transitions the promise from pending to rejected state and
         * executes all registered rejection callbacks.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $reason The reason for rejection
         * @return void Returns nothing
         */
        public function fail( mixed $reason ): void {

            // if we're not pending, just return
            if ( $this -> state !== 'pending' ) return;
            
            // set the state and reason
            $this -> state = 'rejected';
            $this -> reason = $reason;
            
            // execute all rejection callbacks
            foreach ( $this -> onRejected as $callback ) {

                // try to execute the callback
                try {

                    // call the callback with the reason
                    $callback( $reason );

                // whoopsie...
                } catch ( \Exception $e ) {

                    // Handle callback errors silently for now
                }
            }
            
            // clear the callback arrays
            $this -> onFulfilled = [];
            $this -> onRejected = [];
        }
        
        /**
         * Register fulfillment and rejection callbacks
         * 
         * Returns a new promise that will be resolved or rejected based on
         * the outcome of the registered callbacks.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable|null $onFulfilled Fulfillment callback
         * @param callable|null $onRejected Rejection callback
         * @return self Returns a new promise
         */
        public function then( ?callable $onFulfilled = null, ?callable $onRejected = null ): self {

            // create a new promise for chaining
            $promise = new self( );
            
            // create wrapped fulfillment callback
            $wrappedOnFulfilled = function( $value ) use ( $onFulfilled, $promise ) {

                // if we have a fulfillment callback
                if ( $onFulfilled ) {

                    // try to execute it
                    try {

                        // execute the callback and get the result
                        $result = $onFulfilled( $value );

                        // fulfill the new promise with the result
                        $promise -> fulfill( $result );

                    // whoopsie...
                    } catch ( \Exception $e ) {

                        // fail the new promise with the exception
                        $promise -> fail( $e );
                    }

                // otherwise
                } else {

                    // just fulfill with the original value
                    $promise -> fulfill( $value );
                }
            };
            
            // create wrapped rejection callback
            $wrappedOnRejected = function( $reason ) use ( $onRejected, $promise ) {

                // if we have a rejection callback
                if ( $onRejected ) {

                    // try to execute it
                    try {

                        // execute the callback and get the result
                        $result = $onRejected( $reason );

                        // fulfill the new promise with the result
                        $promise -> fulfill( $result );

                    // whoopsie...
                    } catch ( \Exception $e ) {

                        // fail the new promise with the exception
                        $promise -> fail( $e );
                    }

                // otherwise
                } else {

                    // just fail with the original reason
                    $promise -> fail( $reason );
                }
            };
            
            // handle based on current state
            if ( $this -> state === 'fulfilled' ) {

                // execute fulfillment callback immediately
                $wrappedOnFulfilled( $this -> value );

            } elseif ( $this -> state === 'rejected' ) {

                // execute rejection callback immediately
                $wrappedOnRejected( $this -> reason );

            } else {

                // add callbacks to arrays for later execution
                $this -> onFulfilled[] = $wrappedOnFulfilled;
                $this -> onRejected[] = $wrappedOnRejected;
            }
            
            // return the new promise
            return $promise;
        }
        
        /**
         * Register only a rejection callback
         * 
         * Shorthand for then(null, $onRejected) to handle only rejections.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable $onRejected Rejection callback
         * @return self Returns a new promise
         */
        public function catch( callable $onRejected ): self {
            return $this -> then( null, $onRejected );
        }
        
        /**
         * Register a callback that runs regardless of outcome
         * 
         * Executes the callback when the promise settles, regardless of
         * whether it was fulfilled or rejected.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param callable $onFinally Callback to execute on settlement
         * @return self Returns a new promise
         */
        public function finally( callable $onFinally ): self {

            // return a then with both callbacks
            return $this -> then(
                function( $value ) use ( $onFinally ) {

                    // execute the finally callback
                    $onFinally( );

                    // return the original value
                    return $value;
                },
                function( $reason ) use ( $onFinally ) {

                    // execute the finally callback
                    $onFinally( );

                    // re-throw the original reason
                    throw $reason;
                }
            );
        }
        
        /**
         * Create a pre-resolved promise
         * 
         * Returns a promise that is immediately fulfilled with the given value.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $value The value to resolve with
         * @return self Returns a fulfilled promise
         */
        public static function resolve( mixed $value ): self {

            // create a new promise
            $promise = new self( );

            // fulfill it with the value
            $promise -> fulfill( $value );

            // return the promise
            return $promise;
        }
        
        /**
         * Create a pre-rejected promise
         * 
         * Returns a promise that is immediately rejected with the given reason.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param mixed $reason The reason to reject with
         * @return self Returns a rejected promise
         */
        public static function reject( mixed $reason ): self {

            // create a new promise
            $promise = new self( );

            // fail it with the reason
            $promise -> fail( $reason );

            // return the promise
            return $promise;
        }
        
        /**
         * Wait for all promises to resolve
         * 
         * Returns a promise that resolves when all input promises resolve,
         * or rejects immediately when any input promise rejects.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $promises Array of promises to wait for
         * @return self Returns a promise that resolves with all results
         */
        public static function all( array $promises ): self {

            // create a new promise
            $promise = new self( );

            // setup results tracking
            $results = [];
            $remaining = count( $promises );
            
            // if no promises, resolve immediately
            if ( $remaining === 0 ) {

                // fulfill with empty array
                $promise -> fulfill( [] );

                // return the promise
                return $promise;
            }
            
            // setup each promise
            foreach ( $promises as $index => $p ) {

                // add then handlers
                $p -> then(
                    function( $value ) use ( &$results, &$remaining, $index, $promise ) {

                        // store the result
                        $results[$index] = $value;

                        // decrement remaining count
                        $remaining--;
                        
                        // if all are done
                        if ( $remaining === 0 ) {

                            // fulfill with all results
                            $promise -> fulfill( $results );
                        }
                    },
                    function( $reason ) use ( $promise ) {

                        // fail immediately on first rejection
                        $promise -> fail( $reason );
                    }
                );
            }
            
            // return the promise
            return $promise;
        }
        
        /**
         * Wait for first promise to settle
         * 
         * Returns a promise that settles with the same value/reason as
         * the first promise in the input array to settle.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $promises Array of promises to race
         * @return self Returns a promise that settles with the first result
         */
        public static function race( array $promises ): self {

            // create a new promise
            $promise = new self( );
            
            // setup each promise
            foreach ( $promises as $p ) {

                // add then handlers
                $p -> then(
                    function( $value ) use ( $promise ) {

                        // fulfill with the first result
                        $promise -> fulfill( $value );
                    },
                    function( $reason ) use ( $promise ) {

                        // fail with the first rejection
                        $promise -> fail( $reason );
                    }
                );
            }
            
            // return the promise
            return $promise;
        }
        
        /**
         * Wait for all promises to settle (fulfilled or rejected)
         * 
         * Returns a promise that resolves when all input promises settle,
         * regardless of whether they were fulfilled or rejected.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $promises Array of promises to wait for
         * @return self Returns a promise with settlement results
         */
        public static function allSettled( array $promises ): self {

            // create a new promise
            $promise = new self( );

            // setup results tracking
            $results = [];
            $remaining = count( $promises );
            
            // if no promises, resolve immediately
            if ( $remaining === 0 ) {

                // fulfill with empty array
                $promise -> fulfill( [] );

                // return the promise
                return $promise;
            }
            
            // setup each promise
            foreach ( $promises as $index => $p ) {

                // add then handlers
                $p -> then(
                    function( $value ) use ( &$results, &$remaining, $index, $promise ) {

                        // store the fulfilled result
                        $results[$index] = ['status' => 'fulfilled', 'value' => $value];

                        // decrement remaining count
                        $remaining--;
                        
                        // if all are done
                        if ( $remaining === 0 ) {

                            // fulfill with all results
                            $promise -> fulfill( $results );
                        }
                    },
                    function( $reason ) use ( &$results, &$remaining, $index, $promise ) {

                        // store the rejected result
                        $results[$index] = ['status' => 'rejected', 'reason' => $reason];

                        // decrement remaining count
                        $remaining--;
                        
                        // if all are done
                        if ( $remaining === 0 ) {

                            // fulfill with all results
                            $promise -> fulfill( $results );
                        }
                    }
                );
            }
            
            // return the promise
            return $promise;
        }
        
        /**
         * Get the current state of the promise
         * 
         * Returns one of 'pending', 'fulfilled', or 'rejected'.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return string Returns the current promise state
         */
        public function getState( ): string {
            return $this -> state;
        }
        
        /**
         * Get the resolved value
         * 
         * Returns the value the promise was fulfilled with, or null
         * if the promise is not fulfilled.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return mixed Returns the fulfilled value or null
         */
        public function getValue( ): mixed {
            return $this -> value;
        }
        
        /**
         * Get the rejection reason
         * 
         * Returns the reason the promise was rejected with, or null
         * if the promise is not rejected.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return mixed Returns the rejection reason or null
         */
        public function getReason( ): mixed {
            return $this -> reason;
        }
        
        /**
         * Check if the promise is pending
         * 
         * Returns true if the promise has not yet been fulfilled or rejected.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if pending, false otherwise
         */
        public function isPending( ): bool {
            return $this -> state === 'pending';
        }
        
        /**
         * Check if the promise is fulfilled
         * 
         * Returns true if the promise has been successfully resolved.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if fulfilled, false otherwise
         */
        public function isFulfilled( ): bool {
            return $this -> state === 'fulfilled';
        }
        
        /**
         * Check if the promise is rejected
         * 
         * Returns true if the promise has been rejected with an error.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if rejected, false otherwise
         */
        public function isRejected( ): bool {
            return $this -> state === 'rejected';
        }
        
        /**
         * Check if the promise is settled
         * 
         * Returns true if the promise has been either fulfilled or rejected.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @return bool Returns true if settled, false if pending
         */
        public function isSettled( ): bool {
            return $this -> state !== 'pending';
        }
    }
}