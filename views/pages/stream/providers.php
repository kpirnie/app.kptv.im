<?php
/**
 * Providers View - Refactored to use modular system
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

// setup the user string
$userForExport = KPT::encrypt( $userId );

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
    'sp_name' => [
        'type' => 'text',
        'required' => true,
        'label' => 'Name',
        'class' => 'uk-width-1-1',
    ],
    'sp_type' => [
        'type' => 'select',
        'required' => true,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'Type',
        'options' => [
            0 => 'XC API',
            1 => 'M3U',
        ],
    ],
    'sp_cnx_limit' => [
        'type' => 'text',
        'required' => true,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'Connections',
        'default' => 1,
    ],
    'sp_domain' => [
        'type' => 'url',
        'required' => true,
        'label' => 'Domain / URL',
        'class' => 'uk-width-1-1',
    ],
    'sp_username' => [
        'type' => 'text',
        'required' => false,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'XC Username',
    ],
    'sp_password' => [
        'type' => 'text',
        'required' => false,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'XC Password',
    ],
    'sp_stream_type' => [
        'type' => 'select',
        'required' => false,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'Stream Type',
        'options' => [
            0 => 'MPEGTS',
            1 => 'HLS',
        ],
    ],
    'sp_should_filter' => [
        'label' => 'Should Filter?',
        'type' => 'boolean',
        'required' => true,
        'class' => 'uk-width-1-2 uk-margin-bottom',
    ],
    'sp_priority' => [
        'type' => 'number',
        'required' => false,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'Order Priority',
        'default' => 1,
    ],
    'sp_refresh_period' => [
        'type' => 'number',
        'required' => false,
        'class' => 'uk-width-1-2 uk-margin-bottom',
        'label' => 'Refresh Period',
        'default' => 1,
    ],
];

// configure the datatable
$dt -> table( 'kptv_stream_providers' )
    -> tableClass( 'uk-table uk-table-divider uk-table-small uk-margin-bottom' )
    -> where( [
        [ // unless specified as OR, it should always be AND
            'field' => 'u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
    ] )
    -> primaryKey( 'id' )
    -> columns( [
        'id' => 'ID',
        'sp_priority' => [
            'label' => 'Priority',
        ],
        'sp_name' => 'Name',
        'sp_cnx_limit' => 'Cnx.',
        'sp_should_filter' => ['type' => 'boolean', 'label' => 'Filter'],
    ] )
    -> columnClasses( [
        'sp_priority' => 'uk-min-width',
        'sp_cnx_limit' => 'uk-min-width',
        'sp_should_filter' => 'uk-min-width',
        'id' => 'hide-col'
    ] )
    -> sortable( ['sp_priority', 'sp_name', 'sp_cnx_limit', 'sp_should_filter'] )
    -> defaultSort( 'sp_priority', 'ASC' )
    -> inlineEditable( ['sp_priority', 'sp_name', 'sp_cnx_limit', 'sp_should_filter'] )
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true ) // true includes "ALL" option
    -> bulkActions( true )
    -> addForm( 'Add a Provider', $formFields, class: 'uk-grid-small uk-grid' )
    -> editForm( 'Update a Provider', $formFields, class: 'uk-grid-small uk-grid' )
    -> actionGroups( [
        [
            'exportlive' => [
                'icon' => 'tv',
                'title' => 'Export Live Streams',
                'class' => 'copy-link',
                'href' => '' . KPT_URI . 'playlist/' . $userForExport . '/{id}/live',

            ],
            'exportseries' => [
                'icon' => 'album', 
                'title' => 'Export Series Streams',
                'class' => 'copy-link',
                'href' => '' . KPT_URI . 'playlist/' . $userForExport . '/{id}/series',
            ]
        ],
        [
            'delprovider' => [
                'icon' => 'trash',
                'title' => 'Delete this Provider',
                'class' => '',
                'success_message' => 'Provider and all it\'s streams have been deleted.',
                'error_message' => 'Failed to delete the provider.',
                'confirm' => 'Are you want to remove this provider and all it\'s streams?',
                'callback' => function( $rowId, $rowData, $db, $tableName ) {
                    
                    return true;
                },
            ],
        ],
        ['edit'],
    ] );

// Handle AJAX requests (before any HTML output)
if ( isset( $_POST['action'] ) || isset( $_GET['action'] ) ) {
    $dt -> handleAjax( );
}

// pull in the header
KPT::pull_header( );
?>
<div class="uk-container uk-container-full">
    <h2 class="me uk-heading-divider">Stream Providers</h2>
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
