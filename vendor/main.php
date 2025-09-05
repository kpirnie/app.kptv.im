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

// try to manage the session as early as possible
session_set_cookie_params( [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset( $_SERVER['HTTPS'] ),
    'httponly' => true,
    'samesite' => 'Lax'
] );

// hold the app path
$appPath = dirname( __FILE__, 2 ) . '/';

// include our vendor autoloader
include_once $appPath . 'vendor/autoload.php';

// use our namespace
use KPT\KPT;
use KPT\Cache;
use KPT\Logger;
use KPT\Router;

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || define( 'KPT_PATH', $appPath );

// setup the database config definitions
$_db = KPT::get_setting( 'database' );

// configure the cache
Cache::configure( [
    'path' => KPT_PATH . '.cache/',
    'prefix' => KPT::get_cache_prefix( ),
    'allowed_backends' => [ 'array', 'redis', 'memcached', 'opcache', 'shmop', 'file' ], // also: apcu, yac, mysql, sqlite
] );

// define the app URI
defined( 'KPT_URI' ) || define( 'KPT_URI', KPT::get_setting( 'mainuri' ) . '/' );

// define our app name
defined( 'APP_NAME' ) || define( 'APP_NAME', KPT::get_setting( 'appname' ) );

// try to manage the session as early as possible
KPT::manage_the_session( );

// setup our environment
$_debug = KPT::get_setting( 'debug_app' ) ?? false;
defined( 'KPT_DEBUG' ) || define( 'KPT_DEBUG', $_debug );

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

// initialize the logger
new Logger( KPT_DEBUG );

// hold our constant definitions
defined( 'DB_SERVER' ) || define( 'DB_SERVER', $_db -> server );
defined( 'DB_SCHEMA' ) || define( 'DB_SCHEMA', $_db -> schema );
defined( 'DB_USER' ) || define( 'DB_USER', $_db -> username );
defined( 'DB_PASS' ) || define( 'DB_PASS', $_db -> password );
defined( 'TBL_PREFIX' ) || define( 'TBL_PREFIX', $_db -> tbl_prefix );

// hold the global cli args
global $argv;

// make sure this only runs if called from a web browser
if ( php_sapi_name( ) !== 'cli' && 
    ( ! isset( $argv ) || 
    ! is_array( $argv ) || 
    empty( $argv ) || 
    realpath( $argv[0] ) !== 
    realpath( __FILE__ ) ) ) {

    // make sure the routes file exists
    if( file_exists( KPT_PATH . 'views/routes.php' ) ) {

        // hold the routes path
        $routes_path = KPT_PATH . 'views/routes.php';

        // Initialize the router with explicit base path
        $router = new Router( '' );

        // enable the redis rate limiter
        $router -> enableRateLimiter( );

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
            Logger::error( "Router error: " . $e -> getMessage( ) );
            header( 'Content-Type: application/json');
            http_response_code( $e -> getCode( ) >= 400 ? $e -> getCode( ) : 500 );
            echo json_encode( [
                'status' => 'error',
                'message' => $e -> getMessage( ),
                'code' => $e -> getCode( )
            ] );
            
        }

    }

}
