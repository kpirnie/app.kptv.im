<?php
defined('KPT_PATH') || die('Direct Access is not allowed!');

use KPT\KPT;

if (!KPT_User::is_user_logged_in()) {
    http_response_code(403);
    exit('Unauthorized');
}

$stream_url = $_GET['url'] ?? '';
if (empty($stream_url) || !filter_var($stream_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Invalid URL');
}

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Range, Content-Type, User-Agent');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Determine content type
$ext = strtolower(pathinfo(parse_url($stream_url, PHP_URL_PATH), PATHINFO_EXTENSION));
switch ($ext) {
    case 'm3u8':
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        break;
    case 'ts':
        header('Content-Type: video/mp2t');
        header('Cache-Control: public, max-age=3600');
        break;
    case 'mp4':
        header('Content-Type: video/mp4');
        break;
    default:
        header('Content-Type: application/octet-stream');
}

// Enhanced headers for IPTV compatibility
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept: */*',
    'Connection: keep-alive',
    'Cache-Control: no-cache'
];

// Handle range requests
if (isset($_SERVER['HTTP_RANGE'])) {
    $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    header('Accept-Ranges: bytes');
}

// For M3U8 files, get content and rewrite URLs
if ($ext === 'm3u8') {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 10,  // Shorter timeout for playlists
            'ignore_errors' => true
        ]
    ]);

    $content = @file_get_contents($stream_url, false, $context);
    
    if ($content === false) {
        http_response_code(404);
        exit('Playlist not accessible');
    }
    
    // Rewrite relative URLs to go through proxy
    $base_url = dirname($stream_url) . '/';
    
    $content = preg_replace_callback('/^(?!#)([^\r\n]+)$/m', function($matches) use ($base_url) {
        $line = trim($matches[1]);
        
        if (empty($line)) return $line;
        
        // If it's already a full URL, proxy it
        if (filter_var($line, FILTER_VALIDATE_URL)) {
            return '/proxy/stream?url=' . urlencode($line);
        }
        
        // If it's a relative URL, make it absolute then proxy
        if (!empty($line)) {
            $full_url = $base_url . $line;
            return '/proxy/stream?url=' . urlencode($full_url);
        }
        
        return $line;
    }, $content);
    
    echo $content;
    exit();
}

// For streaming content (TS files, etc.), use streaming approach
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => 30,
        'ignore_errors' => true
    ]
]);

// Open stream
$stream = @fopen($stream_url, 'r', false, $context);

if (!$stream) {
    http_response_code(404);
    exit('Stream not accessible');
}

// Get response headers
$meta = stream_get_meta_data($stream);
$response_headers = $meta['wrapper_data'] ?? [];

// Forward relevant headers
foreach ($response_headers as $header) {
    if (stripos($header, 'content-length:') === 0 ||
        stripos($header, 'content-range:') === 0 ||
        stripos($header, 'accept-ranges:') === 0) {
        header($header);
    }
}

// Stream the content in chunks
$chunk_size = 8192; // 8KB chunks
while (!feof($stream) && connection_status() === CONNECTION_NORMAL) {
    $chunk = fread($stream, $chunk_size);
    if ($chunk !== false) {
        echo $chunk;
        flush();
    }
}

fclose($stream);
