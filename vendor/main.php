<?php
/**
 * main.php
 * 
 * This is the main include for the app
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || define( 'KPT_PATH', dirname( __FILE__, 2 ) . '/' );

// include our vendor autoloader
include_once KPT_PATH . 'vendor/autoload.php';

// define the app URI
defined( 'KPT_URI' ) || define( 'KPT_URI', KPT::get_setting( 'mainuri' ) );

// try to manage the session as early as possible
KPT::manage_the_session( );

// setup our environment
$_debug = KPT::get_setting( 'debug_app' );

// if we are debugging
if( $_debug ) {

    // force PHP to render our errors
    @ini_set( 'display_errors', 1 );
    @ini_set( 'display_startup_errors', 1 );
    error_reporting( E_ALL );
    
} else {

    // force php to NOT render our errors
    @ini_set( 'display_errors', 0 );
    error_reporting( 0 );

}

// setup the database config definitions
$_db = KPT::get_setting( 'database' );

// hold our constant definitions
defined( 'DB_SERVER' ) || define( 'DB_SERVER', $_db -> server );
defined( 'DB_SCHEMA' ) || define( 'DB_SCHEMA', $_db -> schema );
defined( 'DB_USER' ) || define( 'DB_USER', $_db -> username );
defined( 'DB_PASS' ) || define( 'DB_PASS', $_db -> password );
defined( 'TBL_PREFIX' ) || define( 'TBL_PREFIX', $_db -> tbl_prefix );

// configre the caches
KPT_Caching::setCachePath( KPT_PATH . '.cache' );
KPT_Caching::setRedisSettings( ['host' => '127.0.0.1', 'port' => 6379] );
KPT_Caching::setMemcachedSettings( ['host' => '127.0.0.1', 'port' => 11211] );
KPT_Caching::setAPCuSettings( ['prefix' => 'MYAPP:'] );
KPT_Caching::setShmopSettings( ['prefix' => 'MYAPP:', 'segment_size' => 2097152, 'base_key' => 0x99999000,] );
KPT_Caching::setYacSettings( [ 'prefix' => 'MYAPP:', 'ttl_default' => 7200, ] );
KPT_Caching::setMmapSettings( [ 'prefix' => 'MYAPP:', 'base_path' => KPT_PATH . '.cache/mmap', 'file_size' => 2097152, 'max_files' => 5000,  ] );

// shmop is now second priority (moved up from 5th place)

// hold the routes path
$routes_path = KPT_PATH . 'views/routes.php';

// Initialize the router with explicit base path
$router = new KPT_Router( '' );

// enable the redis rate limiter
$router -> initRedisRateLimiting( );

// if the routes file exists... load it in to add the routes
if ( file_exists( $routes_path ) ) {
    include_once $routes_path;
}

// Dispatch the router
try {
    $router -> dispatch( );

// whoopsie...
} catch ( Throwable $e ) {
    
    // log the error then throw a json response
    error_log( "Router error: " . $e -> getMessage( ) );
    header( 'Content-Type: application/json');
    http_response_code( $e -> getCode( ) >= 400 ? $e -> getCode( ) : 500 );
    echo json_encode( [
        'status' => 'error',
        'message' => $e -> getMessage( ),
        'code' => $e -> getCode( )
    ] );
    
}
