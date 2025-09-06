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
$formFields = [
    'u_id' => [
        'type' => 'hidden',
        'value' => KPT_User::get_current_user( ) -> id,
        'required' => true
    ],
    'sf_active' => [
        'label' => 'Active',
        'type' => 'boolean',
        'required' => true,
        'class' => 'uk-width-1-2 uk-margin-bottom',
    ],
    'sf_type_id' => [
        'label' => 'Filter Type',
        'type' => 'select',
        'required' => true,
        'options' => [
            0 => 'Include Name (regex)',
            1 => 'Exclude Name',
            2 => 'Exclude Name (regex)',
            3 => 'Exclude Stream (regex)',
            4 => 'Exclude Group (regex)', 
        ],
        'class' => 'uk-width-1-2 uk-margin-bottom',
    ],
    'sf_filter' => [
        'type' => 'text',
        'label' => 'Filter',
        'class' => 'uk-width-1-1',    
    ]
];

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
                '0' => 'Include Name (regex)',
                '1' => 'Exclude Name',
                '2' => 'Exclude Name (regex)',
                '3' => 'Exclude Stream (regex)',
                '4' => 'Exclude Group (regex)', 
            ]
        ] ,
        'sf_filter' => 'Filter',
    ] )
    -> sortable( ['sf_active', 'sf_type_id', ] )
    -> inlineEditable( ['sf_active', 'sf_type_id', 'sf_filter'] )
    -> perPage( 10 )
    -> pageSizeOptions( [10, 25, 50, 100], true ) // true includes "ALL" option
    -> bulkActions( true )
    -> actions( 'end', true, true, [
        /*[
            'icon' => 'mail',
            'title' => 'Send Email',
            'class' => 'btn-email'
        ],*/
    ] )
    -> addForm( 'Add a Filter', $formFields, class: 'uk-grid-small uk-grid' )
    -> editForm( 'Update a Filter', $formFields, class: 'uk-grid-small uk-grid' );


// Handle AJAX requests (before any HTML output)
if ( isset( $_POST['action'] ) || isset( $_GET['action'] ) ) {
    $dt -> handleAjax( );
}

// pull in the header
KPT::pull_header( );

echo '<h2 class="me">Stream Filters</h2>';

// pull in the control panel
KPT::include_view( 'common/control-panel', [ 'dt' => $dt ] );

// write out the datatable component
echo $dt -> renderDataTableComponent( );

// pull in the control panel
KPT::include_view( 'common/control-panel', [ 'dt' => $dt ] );

// pull in the footer
KPT::pull_footer( );

// clean up
unset( $dt, $formFields, $dbconf );
