<?php
/**
 * Filters View - Refactored to use modular system
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// make sure we've got our namespaces...
use KPT\KPT;
use KPT\DataTables\DataTables;

// Configure database via constructor
$dbconf = [
    'server' => DB_SERVER,
    'schema' => DB_SCHEMA,
    'username' => DB_USER,
    'password' => DB_PASS,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

// fire up the datatables class
$dt = new DataTables( $dbconf );

// configure the datatable
$dt -> table( 'kptv_stream_filters' )
    -> columns( [
        'id' => 'ID',
        'sf_active' => 'Active',
        'sf_type_id' => 'Type',
        'sf_filter' => 'Filter',
        'sf_created' => 'Added',
    ] )
    -> sortable( ['sf_active', 'sf_type_id', 'sf_filter', 'sf_created'] )
    
    ;


// Handle AJAX requests (before any HTML output)
if ( isset( $_POST['action'] ) || isset( $_GET['action'] ) ) {
    $dt -> handleAjax( );
}

// pull in the header
KPT::pull_header( );

// write out the datatable component
echo $dt -> renderDataTableComponent( );

// pull in the footer
KPT::pull_footer( );
