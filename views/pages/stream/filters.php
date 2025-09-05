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

// setup the form fields
$formFields = [];

// configure the datatable
$dt -> table( 'kptv_stream_filters' )
    -> tableClass( 'uk-table uk-table-divider uk-table-small uk-margin-bottom' )
    -> columns( [
        'id' => 'ID',
        'sf_active' => ['type' => 'boolean', 'label' => 'Active'],
        'sf_type_id' => [
            'label' => 'Type',
            'type' => 'select',
            'options' => [
                0 => 'Include Name (regex)',
                1 => 'Exclude Name',
                2 => 'Exclude Name (regex)',
                3 => 'Exclude Stream (regex)',
                4 => 'Exclude Group (regex)', 
            ]
        ] ,
        'sf_filter' => 'Filter',
    ] )
    -> sortable( ['sf_active', 'sf_type_id', 'sf_filter'] )
    -> inlineEditable( ['sf_active', 'sf_type_id', 'sf_filter'] )
    -> perPage( 25 )
    -> pageSizeOptions( [10, 25, 50, 100], true ) // true includes "ALL" option
    -> bulkActions( true )
    -> actions( 'end', true, true, [
        /*[
            'icon' => 'mail',
            'title' => 'Send Email',
            'class' => 'btn-email'
        ],*/
    ] )
    -> addForm( 'Add a Filter', $formFields )
    -> editForm( 'Update a Filter', $formFields )
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
