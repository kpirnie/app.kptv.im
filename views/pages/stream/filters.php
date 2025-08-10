<?php
/**
 * Filters View - Refactored to use modular system
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// Initialize Stream Filters class
$stream_filters = new KPTV_Stream_Filters();

// Handle pagination
$per_page = $_GET['per_page'] ?? 25;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// Get sort parameters from URL
$sort_column = $_GET['sort'] ?? 'sf_type_id';
$sort_direction = $_GET['dir'] ?? 'desc';

// Validate sort parameters
$valid_columns = ['sf_active', 'sf_type_id', 'sf_filter', 'sf_updated'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'sf_type_id';
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// Get search term
$search_term = htmlspecialchars(($_GET['s']) ?? '');

// Get records based on search
if (!empty($search_term)) {
    $records = $stream_filters->searchPaginated(
        $search_term,
        $per_page,
        $offset,
        $sort_column,
        $sort_direction
    );
} else {
    $records = $stream_filters->getPaginated(
        $per_page,
        $offset,
        $sort_column,
        $sort_direction
    );
}

$total_records = $stream_filters->getTotalCount($search_term);
$total_pages = $per_page !== 'all' ? ceil($total_records / $per_page) : 1;

// Create and render view using modular system
$view = new EnhancedBaseTableView('Stream Filters', '/filters', FiltersViewConfig::getConfig());
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