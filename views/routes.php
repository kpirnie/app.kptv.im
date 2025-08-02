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

// =============================================================
// ===================== ROUTE DEFINITIONS ====================
// =============================================================

$routes = [
    // =============================================================
    // ===================== GET ROUTES ============================
    // =============================================================
    
    // Home page route
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'view:pages/home.php'
    ],
    
    // --------------------- User Routes ----------------------------
    
    // Login page
    [
        'method' => 'GET',
        'path' => '/users/login',
        'middleware' => ['guest_only'],
        'handler' => 'view:pages/users/login.php'
    ],
    
    // Logout action
    [
        'method' => 'GET',
        'path' => '/users/logout',
        'middleware' => ['auth_required'],
        'handler' => 'action:user.logout'
    ],
    
    // Registration page
    [
        'method' => 'GET',
        'path' => '/users/register',
        'middleware' => ['guest_only'],
        'handler' => 'view:pages/users/register.php'
    ],
    
    // Forgot password page
    [
        'method' => 'GET',
        'path' => '/users/forgot',
        'middleware' => ['guest_only'],
        'handler' => 'view:pages/users/forgot.php'
    ],
    
    // Change password page
    [
        'method' => 'GET',
        'path' => '/users/changepass',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/users/changepass.php'
    ],
    
    // Account validation
    [
        'method' => 'GET',
        'path' => '/validate',
        'handler' => 'action:user.validate'
    ],
    
    // --------------------- Stream Routes -------------------------
    
    // Providers management
    [
        'method' => 'GET',
        'path' => '/providers',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/providers.php'
    ],
    
    // Filters management
    [
        'method' => 'GET',
        'path' => '/filters',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/filters.php'
    ],
    
    // Other streams management
    [
        'method' => 'GET',
        'path' => '/other',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/other.php'
    ],
    
    // Streams management with parameters
    [
        'method' => 'GET',
        'path' => '/streams/{which}/{type}',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/streams.php',
        'data' => ['currentRoute' => true] // Special flag to pass current route
    ],
    
    // Playlist export with parameters (2 params)
    [
        'method' => 'GET',
        'path' => '/playlist/{user}/{which}',
        'handler' => 'view:pages/stream/playlist.php'
    ],
    
    // Playlist export with parameters (3 params)
    [
        'method' => 'GET',
        'path' => '/playlist/{user}/{provider}/{which}',
        'handler' => 'view:pages/stream/playlist.php'
    ],
    
    // --------------------- Admin Routes ---------------------------
    
    // User management (admin only)
    [
        'method' => 'GET',
        'path' => '/admin/users',
        'middleware' => ['admin_required'],
        'handler' => 'view:pages/admin/users.php'
    ],
    
    // =============================================================
    // ===================== POST ROUTES ===========================
    // =============================================================
    
    // --------------------- User Routes ----------------------------
    
    // Login form submission
    [
        'method' => 'POST',
        'path' => '/users/login',
        'middleware' => ['guest_only'],
        'handler' => 'action:user.login'
    ],
    
    // Registration form submission
    [
        'method' => 'POST',
        'path' => '/users/register',
        'middleware' => ['guest_only'],
        'handler' => 'action:user.register'
    ],
    
    // Change password form submission
    [
        'method' => 'POST',
        'path' => '/users/changepass',
        'middleware' => ['auth_required'],
        'handler' => 'action:user.changepass'
    ],
    
    // Forgot password form submission
    [
        'method' => 'POST',
        'path' => '/users/forgot',
        'middleware' => ['guest_only'],
        'handler' => 'action:user.forgot'
    ],
    
    // Admin user management form submission
    [
        'method' => 'POST',
        'path' => '/admin/users',
        'middleware' => ['admin_required'],
        'handler' => 'view:pages/admin/users.php'
    ],
    
    // --------------------- Stream Routes -------------------------
    
    // Filters form submission
    [
        'method' => 'POST',
        'path' => '/filters',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/filters.php'
    ],
    
    // Providers form submission
    [
        'method' => 'POST',
        'path' => '/providers',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/providers.php'
    ],
    
    // Streams form submission with parameters
    [
        'method' => 'POST',
        'path' => '/streams/{which}/{type}',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/streams.php',
        'data' => ['currentRoute' => true]
    ],
    
    // Other streams form submission
    [
        'method' => 'POST',
        'path' => '/other',
        'middleware' => ['auth_required'],
        'handler' => 'view:pages/stream/other.php'
    ],
];

// =============================================================
// ==================== MIDDLEWARE DEFINITIONS ===============
// =============================================================

$middlewareDefinitions = [
    'guest_only' => 'middleware:guest_only',
    'auth_required' => 'middleware:auth_required', 
    'admin_required' => 'middleware:admin_required'
];

// =============================================================
// ==================== ROUTE CACHING ========================
// =============================================================

// Now we can cache everything since no closures are involved!
$routesFile = __FILE__;
$cacheKey = 'compiled_routes_' . md5( $routesFile . filemtime( $routesFile ) );
$cacheTTL = KPT::DAY_IN_SECONDS; // Cache for 1 day

// Try to get cached routes and middleware
$cachedData = KPT_Cache::get( $cacheKey );

if ( $cachedData !== false && is_array( $cachedData ) && 
     isset( $cachedData['routes'] ) && isset( $cachedData['middleware'] ) ) {
    
    // Use cached data
    $routes = $cachedData['routes'];
    $middlewareDefinitions = $cachedData['middleware'];
    
    // Log cache hit for debugging (optional)
    error_log( "Route cache HIT for key: {$cacheKey}" );
    
} else {
    
    // Cache miss - store routes and middleware for next time
    $cacheData = [
        'routes' => $routes,
        'middleware' => $middlewareDefinitions,
        'cached_at' => time(),
        'expires_at' => time() + $cacheTTL
    ];
    
    KPT_Cache::set( $cacheKey, $cacheData, $cacheTTL );
    
    // Log cache miss for debugging (optional)  
    error_log( "Route cache MISS for key: {$cacheKey} - Routes cached" );
}

// =============================================================
// ==================== REGISTER ROUTES ======================
// =============================================================

// Register middleware definitions
$router -> registerMiddlewareDefinitions( $middlewareDefinitions );

// Register all routes
$router -> registerRoutes( $routes );

// =============================================================
// ==================== GLOBAL MIDDLEWARE ====================
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
