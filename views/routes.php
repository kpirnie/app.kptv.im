<?php
/**
 * KPT_Routes
 * 
 * This class provides a comprehensive routing solution for the KPTV Manager application.
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// =============================================================
// ==================== MIDDLEWARE DEFINITIONS ===============
// =============================================================

$middlewareDefinitions = [
    // Guest-only middleware (user must NOT be logged in)
    'guest_only' => function() {
        if (KPT_User::is_user_logged_in()) {
            KPT::message_with_redirect('/', 'danger', 'You are already logged in.');
            return false;
        }
        return true;
    },
    
    // Authentication required middleware
    'auth_required' => function() {
        if (!KPT_User::is_user_logged_in()) {
            KPT::message_with_redirect('/users/login', 'danger', 'You must be logged in to access this page.');
            return false;
        }
        return true;
    },
    
    // Admin-only middleware
    'admin_required' => function() {
        if (!KPT_User::is_user_logged_in()) {
            KPT::message_with_redirect('/users/login', 'danger', 'You must be logged in to access this page.');
            return false;
        }
        
        $user = KPT_User::get_current_user();
        if ($user->role != 99) {
            KPT::message_with_redirect('/', 'danger', 'You do not have permission to access this page.');
            return false;
        }
        
        return true;
    },
    
    // API authentication middleware (bonus example)
    'api_auth' => function() {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        
        if (empty($apiKey)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'API key required']);
            return false;
        }
        
        // In a real app, validate against database
        $validKeys = ['kptv_api_key_123', 'demo_key_456'];
        if (!in_array($apiKey, $validKeys)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid API key']);
            return false;
        }
        
        return true;
    },
    
    // JSON-only middleware (for API endpoints)
    'json_only' => function() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Content-Type must be application/json']);
            return false;
        }
        return true;
    }
];

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
    
    // Logout action (using controller)
    [
        'method' => 'GET',
        'path' => '/users/logout',
        'middleware' => ['auth_required'],
        'handler' => 'KPT_User@logout'
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
    
    // Account validation (using controller)
    [
        'method' => 'GET',
        'path' => '/validate',
        'handler' => 'KPT_User@validate_user'
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
    
    // Login form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/login',
        'middleware' => ['guest_only'],
        'handler' => 'KPT_User@login'
    ],
    
    // Registration form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/register',
        'middleware' => ['guest_only'],
        'handler' => 'KPT_User@register'
    ],
    
    // Change password form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/changepass',
        'middleware' => ['auth_required'],
        'handler' => 'KPT_User@change_pass'
    ],
    
    // Forgot password form submission (using controller)
    [
        'method' => 'POST',
        'path' => '/users/forgot',
        'middleware' => ['guest_only'],
        'handler' => 'KPT_User@forgot'
    ],
    
    // Admin user management form submission
    [
        'method' => 'POST',
        'path' => '/admin/users',
        'middleware' => ['admin_required'],
        'handler' => 'KPT_User@handle_posts'
    ],
    
    // --------------------- Stream Routes -------------------------
    
    // Filters form submission
    [
        'method' => 'POST',
        'path' => '/filters',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_Stream_Filters@handleFormSubmission'
    ],
    
    // Providers form submission
    [
        'method' => 'POST',
        'path' => '/providers',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_Stream_Providers@handleFormSubmission'
    ],
    
    // Streams form submission with parameters
    [
        'method' => 'POST',
        'path' => '/streams/{which}/{type}',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_Streams@handleFormSubmission',
        'data' => ['currentRoute' => true]
    ],
    
    // Other streams form submission
    [
        'method' => 'POST',
        'path' => '/other',
        'middleware' => ['auth_required'],
        'handler' => 'KPTV_Stream_Other@handleFormSubmission'
    ],
    
];

// =============================================================
// ==================== ROUTE CACHING ========================
// =============================================================

// Now we can cache everything since no closures are involved!
$routesFile = __FILE__;
$cacheKey = 'compiled_routes_' . md5( $routesFile . filemtime( $routesFile ) );
$cacheTTL = KPT::DAY_IN_SECONDS; // Cache for 1 day

// Try to get cached routes (NOTE: We can't cache middleware definitions with closures)
$cachedData = KPT_Cache::get( $cacheKey );

if ( $cachedData !== false && is_array( $cachedData ) && isset( $cachedData['routes'] ) ) {
    
    // Use cached routes (but always define middleware fresh since they contain closures)
    $routes = $cachedData['routes'];
    
    // Log cache hit for debugging (optional)
    error_log( "Route cache HIT for key: {$cacheKey}" );
    
} else {
    
    // Cache miss - store routes for next time (but not middleware definitions)
    $cacheData = [
        'routes' => $routes,
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

// Register middleware definitions (always fresh since they contain closures)
$router->registerMiddlewareDefinitions( $middlewareDefinitions );

// Register all routes
$router->registerRoutes( $routes );

// =============================================================
// ==================== GLOBAL MIDDLEWARE ====================
// =============================================================

// Request logging middleware
$router->addMiddleware( function() {
    // Log all requests with timestamp
    $timestamp = date('Y-m-d H:i:s');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $ip = KPT::get_user_ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    error_log("[$timestamp] $ip - $method $uri - $userAgent");
    return true;
} );

// Maintenance mode middleware
$router->addMiddleware( function() {
    // Check for maintenance mode configuration
    $configFile = $_SERVER['DOCUMENT_ROOT'] . '/.maintenance.json';
    
    // Skip if no maintenance config exists
    if ( ! file_exists( $configFile ) ) return true;
    
    // Load maintenance configuration
    $config = json_decode( file_get_contents( $configFile ), true );
    $enabled = $config['enabled'] ?? false;
    $allowedIPs = $config['allowed_ips'] ?? ['127.0.0.1/32'];
    $message = $config['message'] ?? 'Down for maintenance';
    
    // Skip if maintenance not enabled
    if ( ! $enabled ) return true;
    
    // Check if client IP is in any allowed CIDR range
    $clientIp = KPT::get_user_ip();
    foreach ( $allowedIPs as $allowed ) {
        if ( KPT::cidrMatch( $clientIp, $allowed ) ) {
            return true;
        }
    }

    // Return maintenance mode response
    http_response_code( 503 );
    header( 'Content-Type: application/json' );
    header( 'Retry-After: 3600' ); // Retry after 1 hour
    die( json_encode( [
        'error' => 'maintenance',
        'message' => $message,
        'until' => $config['until'] ?? null,
        'status' => 503
    ] ) );
    
} );

// CORS middleware for API routes
$router->addMiddleware( function() {
    
    // Only apply CORS to API routes
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $requestUri, '/api/' ) === false ) {
        return true;
    }
    
    // Set CORS headers
    header( 'Access-Control-Allow-Origin: *' );
    header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
    header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key' );
    header( 'Access-Control-Max-Age: 86400' ); // Cache preflight for 24 hours
    
    // Handle preflight OPTIONS request
    if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
        http_response_code( 200 );
        exit;
    }
    
    return true;
} );

// =============================================================
// ==================== ERROR HANDLING =========================
// =============================================================

// 404 Not Found handler
$router->notFound( function() {
    
    // Log the 404 error
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $ip = KPT::get_user_ip();
    error_log( "404 Error: $method $uri from $ip" );
    
    // Check if it's an API request
    if ( strpos( $uri, '/api/' ) !== false ) {
        // Return JSON 404 response for API
        header( 'Content-Type: application/json' );
        http_response_code( 404 );
        echo json_encode( [
            'status' => 'error',
            'message' => 'API endpoint not found',
            'request_uri' => $uri,
            'method' => $method,
            'timestamp' => date( 'c' )
        ] );
    } else {
        // Return HTML 404 response for web
        http_response_code( 404 );
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo 'Page Not Found';
    }
    
} );

/**
 * =============================================================
 * EXAMPLE CONTROLLER CLASSES (for reference)
 * =============================================================
 * 
 * Here are examples of what your controller classes should look like:
 */

/*

class UserController {
    
    public function login() {
        // Handle login logic
        $user = new KPT_User();
        return $user->login();
    }
    
    public function logout() {
        $user = new KPT_User();
        return $user->logout();
    }
    
    public function register() {
        $user = new KPT_User();
        return $user->register();
    }
    
    public function profile() {
        $user = KPT_User::get_current_user();
        return json_encode(['user' => $user]);
    }
    
    public function edit($id) {
        // Check if user can edit this profile
        $currentUser = KPT_User::get_current_user();
        if ($currentUser->id != $id && $currentUser->role != 99) {
            http_response_code(403);
            return json_encode(['error' => 'Not authorized']);
        }
        
        // Return edit form or user data
        $user = KPT_User::get_user_by_id($id);
        return json_encode(['user' => $user]);
    }
    
    public function validate() {
        $user = new KPT_User();
        return $user->validate_user();
    }
}

class ProviderController {
    
    public function index() {
        // Return all providers as JSON
        $providers = KPT_Stream_Provider::get_all();
        return json_encode(['providers' => $providers]);
    }
    
    public function show($id) {
        $provider = KPT_Stream_Provider::get_by_id($id);
        if (!$provider) {
            http_response_code(404);
            return json_encode(['error' => 'Provider not found']);
        }
        return json_encode(['provider' => $provider]);
    }
    
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        $provider = KPT_Stream_Provider::create($data);
        http_response_code(201);
        return json_encode(['provider' => $provider]);
    }
    
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $provider = KPT_Stream_Provider::update($id, $data);
        return json_encode(['provider' => $provider]);
    }
    
    public function delete($id) {
        KPT_Stream_Provider::delete($id);
        http_response_code(204);
        return '';
    }
}

class AdminController {
    
    public function dashboard() {
        $stats = [
            'total_users' => KPT_User::count(),
            'total_providers' => KPT_Stream_Provider::count(),
            'active_streams' => KPT_Stream::count_active()
        ];
        return json_encode(['dashboard' => $stats]);
    }
    
    public function getUsersList() {
        $users = KPT_User::get_all();
        return json_encode(['users' => $users]);
    }
    
    public function showUser($id) {
        $user = KPT_User::get_user_by_id($id);
        return json_encode(['user' => $user]);
    }
    
    public function deleteUser($id) {
        // Prevent self-deletion
        $currentUser = KPT_User::get_current_user();
        if ($currentUser->id == $id) {
            http_response_code(400);
            return json_encode(['error' => 'Cannot delete your own account']);
        }
        
        KPT_User::delete($id);
        http_response_code(204);
        return '';
    }
}

*/