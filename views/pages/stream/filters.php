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

// setup the user id
$userId = KPT_User::get_current_user( ) -> id;

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
        'value' => $userId,
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
    -> where( [
        '' => [ // unless specified as OR, it should always be AND
            'field' => 'u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
    ] )
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
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true ) // true includes "ALL" option
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
?>
<div class="uk-container uk-container-full">
    <h2 class="me uk-heading-divider">Stream Filters</h2>
    <div class="uk-border-bottom">
        <?php

        // pull in the control panel
        KPT::include_view( 'common/control-panel', [ 'dt' => $dt ] );
        ?>
    </div>
    <div class="">
        <?php

        // write out the datatable component
        echo $dt -> renderDataTableComponent( );
        ?>
    </div>
    <div class="uk-border-top">
        <?php

        // pull in the control panel
        KPT::include_view( 'common/control-panel', [ 'dt' => $dt ] );
        ?>
    </div>
</div>
<?php

// pull in the footer
KPT::pull_footer( );

// clean up
unset( $dt, $formFields, $dbconf );
