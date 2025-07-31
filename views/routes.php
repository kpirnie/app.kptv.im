<?php
/**
 * KPT_Routes
 * 
 * This class provides a comprehensive routing solution for the KPTV Manager application.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KPTV Manager
 */
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// enable caching
$router -> enableCaching( KPT::DAY_IN_SECONDS );

// =============================================================
// ===================== GET ROUTES ============================
// =============================================================

// Home page route
$router -> get( '/', function( ) {
    include_once KPT_PATH . '/views/home.php';    
} );

// --------------------- User Routes ----------------------------

// Login page
$router -> get( '/users/login', function( ) use( $router ) {
    // Redirect if user is already logged in
    if ( KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You are already logged in, there is no need to do it again.' );
        return;
    }
    return $router -> view( 'users/login.php' );
} );

// Logout action
$router -> get( '/users/logout', function( ) {
    // Prevent logout if not logged in
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You can only logout if you are currently logged in.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    // process the logout
    $user = new KPT_User( );
    $user -> logout( );
    unset( $user );
} );

// Registration page
$router -> get( '/users/register', function( ) use( $router ) {
    // Redirect if already logged in
    if ( KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You are already logged in, you do not need to register for an account.' );
        return;
    }
    return $router -> view( 'users/register.php' );
} );

// Forgot password page
$router -> get( '/users/forgot', function( ) use( $router ) {
    // Redirect if already logged in
    if ( KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'If you forgot your password, please logout first.' );
        return;
    }
    return $router -> view( 'users/forgot.php' );
} );

// Change password page
$router -> get( '/users/changepass', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You are not logged in so you cannot change your password.' );
        return;
    }
    return $router -> view( 'users/changepass.php' );
} );

// Account validation
$router -> get( '/validate', function( ) {
    // disable caching for this view
    $router -> disableCaching( );
    $user = new KPT_User( );
    $user -> validate_user( );
    unset( $user );
} );

// --------------------- Stream Routes -------------------------

// Providers management
$router -> get( '/providers', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your providers.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/providers.php' );
} );

// Filters management
$router -> get( '/filters', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your filters.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/filters.php' );
} );

// Other streams management
$router -> get( '/other', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your other streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/other.php' );
} );

// Streams management with parameters
$router -> get( '/streams/{which}/{type}', function( string $which, string $type ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( '/stream/streams.php', [
        'which' => $which,
        'type' => $type,
        'currentRoute' => KPT_Router::get_current_route( )
    ] );
} );

// Playlist export with parameters
$router -> get( '/playlist/{user}/{which}', function( string $user, string $which ) use( $router ) {
    return $router -> view( '/stream/playlist.php', [
        'user' => $user,
        'which' => $which,
    ] );
} ) -> get( '/playlist/{user}/{provider}/{which}', function( string $user, string $provider, string $which ) use( $router ) {
    return $router -> view( '/stream/playlist.php', [
        'user' => $user,
        'provider' => $provider,
        'which' => $which,
    ] );
} );

// --------------------- Admin Routes ---------------------------

// User management (admin only)
$router -> get( '/admin/users', function( ) use( $router ) {
    // Require admin privileges
    if ( ! KPT_User::is_user_logged_in( ) || KPT_User::get_current_user( ) -> role != 99 ) {
        KPT::message_with_redirect( '/', 'danger', 'You do not have permission to access this page.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'admin/users.php' );
} );

// =============================================================
// ===================== POST ROUTES ===========================
// =============================================================

// --------------------- User Routes ----------------------------

// Login form submission
$router -> post( '/users/login', function( ) {
    // Check authentication
    if ( KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'There\s no need to do that...' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    $user = new KPT_User( );
    $user -> login( );
    unset( $user );
} );

// Registration form submission
$router -> post( '/users/register', function( ) {
    // Check authentication
    if ( KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'There\s no need to do that...' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    $user = new KPT_User( );
    $user -> register( );
    unset( $user );
} );

// Change password form submission
$router -> post( '/users/changepass', function( ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your other streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    $user = new KPT_User( );
    $user -> change_pass( );
    unset( $user );
} );

// Forgot password form submission
$router -> post( '/users/forgot', function( ) {
    // Check authentication
    if ( KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'There\s no need to do that...' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    $user = new KPT_User( );
    $user -> forgot( );
    unset( $user );
} );

// Admin user management form submission
$router -> post( '/admin/users', function( ) use( $router ) {
    // Require admin privileges
    if ( ! KPT_User::is_user_logged_in( ) || KPT_User::get_current_user( ) -> role != 99 ) {
        KPT::message_with_redirect( '/', 'danger', 'You do not have permission to access this page.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'admin/users.php' );
} );

// --------------------- Stream Routes -------------------------

// Filters form submission
$router -> post( '/filters', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your other streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/filters.php' );
} );

// Providers form submission
$router -> post( '/providers', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your other streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/providers.php' );
} );

// Streams form submission with parameters
$router -> post( '/streams/{which}/{type}', function( string $which, string $type ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your other streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/streams.php', [
        'which' => $which,
        'type' => $type,
        'currentRoute' => KPT_Router::get_current_route( )
    ] );
} );

// Other streams form submission
$router -> post( '/other', function( ) use( $router ) {
    // Require authentication
    if ( ! KPT_User::is_user_logged_in( ) ) {
        KPT::message_with_redirect( '/', 'danger', 'You must be logged in to manage your other streams.' );
        return;
    }
    // disable caching for this view
    $router -> disableCaching( );
    return $router -> view( 'stream/other.php' );
} );

// =============================================================
// ==================== MIDDLEWARE =============================
// =============================================================

// Maintenance mode middleware
$router -> addMiddleware( function( ) {
    // Check for maintenance mode configuration
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/.maintenance.json';
    
    // Skip if no maintenance config exists
    if ( ! file_exists( $configFile ) ) return true;
    
    // Load maintenance configuration
    $config = json_decode( file_get_contents( $configFile ), true );
    $enabled = $config['enabled'] ?? false;
    $allowedIPs = $config['allowed_ips'] ?? ['127.0.0.1/32'];
    $message = $config['message'] ?? 'Down for maintenance';
    
    // Skip if maintenance not enabled or IP is allowed
    if ( ! $enabled || in_array( $_SERVER['REMOTE_ADDR'], $allowedIPs ) ) {
        return true;
    }
    
    // Check if client IP is in any allowed CIDR range
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ( $allowedIPs as $allowed ) {
        if ( KPT::cidrMatch( $clientIp, $allowed ) ) {
            return true;
        }
    }

    // Return maintenance mode response
    http_response_code( 503 );
    header( 'Content-Type: application/json' );
    die( json_encode( [
        'error' => 'maintenance',
        'message' => $message,
        'until' => $config['until'] ?? null
    ] ) );
    
} );

// =============================================================
// ==================== ERROR HANDLING =========================
// =============================================================

// 404 Not Found handler
$router -> notFound( function( ) {    

    // Log the 404 error
    error_log( "404 triggered for: " . $_SERVER['REQUEST_URI'] );
    
    // Return JSON 404 response
    header( 'Content-Type: application/json' );
    http_response_code( 404 );
    echo json_encode( [
        'status' => 'error',
        'message' => 'Endpoint not found',
        'request_uri' => $_SERVER['REQUEST_URI']
    ] );

} );
