<?php
/**
 * Async Cache Traits for I/O-intensive cache backends
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// no direct access
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't already exist
if ( ! trait_exists( 'Cache_MMAP_Async' ) ) {

    /**
     * KPT Cache MMAP Async Trait
     * 
     * Provides asynchronous memory-mapped file caching operations for improved performance
     * in I/O-intensive applications using event loops and promises.
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait Cache_MMAP_Async {
        
        /**
         * Async get from MMAP
         * 
         * Asynchronously retrieves an item from memory-mapped cache using promises
         * and event loop integration for non-blocking operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to retrieve
         * @return Cache_Promise Returns a promise that resolves with the cached data
         */
        public static function getFromMmapAsync( string $key ): Cache_Promise {

            // return a new promise for the async operation
            return new Cache_Promise( function( $resolve, $reject ) use ( $key ) {

                // check if async is enabled and we have an event loop
                if ( self::$_async_enabled && self::$_event_loop ) {

                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick( function( ) use ( $key, $resolve, $reject ) {

                        // try to get the item from MMAP
                        try {

                            // get the result and resolve
                            $result = self::getFromMmap( $key );
                            $resolve( $result );

                        // whoopsie... reject the promise with the error
                        } catch ( Exception $e ) {
                            $reject( $e );
                        }
                    });

                // fallback to synchronous operation
                } else {

                    // try to get the item synchronously
                    try {

                        // get the result and resolve
                        $result = self::getFromMmap( $key );
                        $resolve( $result );

                    // whoopsie... reject the promise with the error
                    } catch ( Exception $e ) {
                        $reject( $e );
                    }
                }
            });
        }
        
        /**
         * Async set to MMAP
         * 
         * Asynchronously stores an item in memory-mapped cache using promises
         * and event loop integration for non-blocking operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return Cache_Promise Returns a promise that resolves with success status
         */
        public static function setToMmapAsync( string $key, mixed $data, int $ttl ): Cache_Promise {

            // return a new promise for the async operation
            return new Cache_Promise( function( $resolve, $reject ) use ( $key, $data, $ttl ) {

                // check if async is enabled and we have an event loop
                if ( self::$_async_enabled && self::$_event_loop ) {

                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick( function( ) use ( $key, $data, $ttl, $resolve, $reject ) {

                        // try to set the item to MMAP
                        try {

                            // set the result and resolve
                            $result = self::setToMmap( $key, $data, $ttl );
                            $resolve( $result );

                        // whoopsie... reject the promise with the error
                        } catch ( Exception $e ) {
                            $reject( $e );
                        }
                    });

                // fallback to synchronous operation
                } else {

                    // try to set the item synchronously
                    try {

                        // set the result and resolve
                        $result = self::setToMmap( $key, $data, $ttl );
                        $resolve( $result );

                    // whoopsie... reject the promise with the error
                    } catch ( Exception $e ) {
                        $reject( $e );
                    }
                }
            });
        }
        
        /**
         * Async batch MMAP operations
         * 
         * Performs multiple MMAP cache operations asynchronously in batch
         * for improved performance when handling multiple cache operations.
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $operations Array of operations to perform
         * @return Cache_Promise Returns a promise that resolves with operation results
         */
        public static function mmapBatchAsync( array $operations ): Cache_Promise {

            // return a new promise for the batch operation
            return new Cache_Promise( function( $resolve, $reject ) use ( $operations ) {

                // check if async is enabled and we have an event loop
                if ( self::$_async_enabled && self::$_event_loop ) {

                    // setup promises array
                    $promises = [ ];
                    
                    // loop through each operation and create promises
                    foreach ( $operations as $op ) {

                        // match the operation type and create appropriate promise
                        $promise = match( $op['type'] ) {
                            'get' => self::getFromMmapAsync( $op['key'] ),
                            'set' => self::setToMmapAsync( $op['key'], $op['data'], $op['ttl'] ?? 3600 ),
                            default => Cache_Promise::reject( new Exception( "Unknown operation: {$op['type']}" ) )
                        };

                        // add to promises array
                        $promises[ ] = $promise;
                    }
                    
                    // wait for all promises to complete
                    Cache_Promise::all( $promises )
                        -> then( function( $results ) use ( $resolve ) {
                            $resolve( $results );
                        })
                        -> catch( function( $error ) use ( $reject ) {
                            $reject( $error );
                        });

                // fallback to synchronous batch processing
                } else {

                    // try to process batch synchronously
                    try {

                        // setup results array
                        $results = [ ];

                        // loop through each operation
                        foreach ( $operations as $op ) {

                            // match the operation type and execute
                            $results[ ] = match( $op['type'] ) {
                                'get' => self::getFromMmap( $op['key'] ),
                                'set' => self::setToMmap( $op['key'], $op['data'], $op['ttl'] ?? 3600 ),
                                default => false
                            };
                        }

                        // resolve with results
                        $resolve( $results );

                    // whoopsie... reject the promise with the error
                    } catch ( Exception $e ) {
                        $reject( $e );
                    }
                }
            });
        }

    }
}