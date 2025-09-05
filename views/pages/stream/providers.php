<?php
/**
 * Providers View - Refactored to use modular system
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// Initialize Stream Providers class
$stream_providers = new KPTV_Stream_Providers();

// Handle pagination
$per_page = $_GET['per_page'] ?? 25;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// Get sort parameters from URL
$sort_column = $_GET['sort'] ?? 'sp_priority';
$sort_direction = $_GET['dir'] ?? 'asc';

// Validate sort parameters
$valid_columns = ['sp_priority', 'sp_name', 'sp_cnx_limit', 'sp_should_filter'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'sp_priority';
$sort_direction = $sort_direction === 'desc' ? 'DESC' : 'ASC';

// Get search term
$search_term = htmlspecialchars(($_GET['s']) ?? '');

// Get records based on search
if (!empty($search_term)) {
    $records = $stream_providers->searchPaginated(
        $search_term,
        $per_page,
        $offset,
        $sort_column,
        $sort_direction
    );
} else {
    $records = $stream_providers->getPaginated(
        $per_page,
        $offset,
        $sort_column,
        $sort_direction
    );
}

$total_records = $stream_providers->getTotalCount($search_term);
$total_pages = ceil($total_records / $per_page) ?? 1;

// Create and render view using modular system
$view = new EnhancedBaseTableView('Stream Providers', '/providers', ProvidersViewConfig::getConfig());
$view->display([
    'records' => $records ?: [],
    'page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'search_term' => $search_term,
    'sort_column' => $sort_column,
    'sort_direction' => $sort_direction,
    'error' => null
]);