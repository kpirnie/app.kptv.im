<?php
/**
 * Other Streams View
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
$formFields = [];

// configure the datatable
$dt -> table( 'kptv_stream_other s' )
    -> primaryKey( 's.id' )  // Use qualified primary key
    -> join( 'LEFT', 'kptv_stream_providers p', 's.p_id = p.id' )
    -> where( [
        [ // unless specified as OR, it should always be AND
            'field' => 's.u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
    ] )
    -> tableClass( 'uk-table uk-table-divider uk-table-small uk-margin-bottom' )
    -> columns( [
        's.id' => 'ID',
        's_orig_name' => 'Original Name',
        's_stream_uri' => 'Stream URI',
        'p.sp_name' => 'Provider',
    ] )
    -> columnClasses( [
        's.id' => 'uk-min-width',
        's_stream_uri' => 'url-truncate',
        's_orig_name' => 'txt-truncate',
        'p.sp_name' => 'txt-truncate',
    ] )
    -> sortable( ['s_orig_name', 'p.sp_name'] )
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true )
    -> bulkActions( true, [
        'movetolive' => [
            'label' => 'Move to Live Streams',
            'icon' => 'tv',
            'confirm' => 'Move the selected records to live streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // use our local function to move the records
                return KPT::moveFromOther( $database, $selectedIds, 0 );

            },
            'success_message' => 'Records moved successfully',
            'error_message' => 'Failed to move records'
        ],
        'movetoseries' => [
            'label' => 'Move to Series Streams',
            'icon' => 'album',
            'confirm' => 'Move the selected records to series streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // use our local function to move the records
                return KPT::moveFromOther( $database, $selectedIds, 5 );

            },
            'success_message' => 'Records moved successfully',
            'error_message' => 'Failed to move records'
        ],
    ] )
    -> actionGroups( [
        [
            'playstream' => [
                'icon' => 'play',
                'title' => 'Try to Play Stream',
                'class' => 'play-stream',
                'href' => '#{s_orig_name}',
                'attributes' => [
                    'data-stream-url' => '{s_stream_uri}',
                    'data-stream-name' => '{s_orig_name}',
                ]
            ],
            'copystream' => [
                'icon' => 'link', 
                'title' => 'Copy Stream Link',
                'class' => 'copy-link',
                'href' => '{s_stream_uri}',
            ]
        ],
        ['delete'],
    ] );

    // Handle AJAX requests (before any HTML output)
if ( isset( $_POST['action'] ) || isset( $_GET['action'] ) ) {
    $dt -> handleAjax( );
}

// pull in the header
KPT::pull_header( );
?>
<div class="uk-container uk-container-full">
    <h2 class="me uk-heading-divider">Other Streams</h2>
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
