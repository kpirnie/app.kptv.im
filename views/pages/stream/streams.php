<?php
/**
 * Streams View
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// make sure we've got our namespaces...
use KPT\KPT;
use KPT\DataTables\DataTables;

// Handle stream type filter (passed from router)
$type_filter = $which ?? 'live';
$valid_types = ['live' => 0, 'vod' => 4, 'series' => 5];
$type_value = $valid_types[$type_filter] ?? null;

// Handle the stream active filter (passed from router)
$active_filter = $type ?? 'active';
$valid_active = ['active' => 1, 'inactive' => 0];
$active_value = $valid_active[$active_filter] ?? null;

// setup the actions
$actionGroups = [
    'live' => [
        'moveseries' => [
            'icon' => 'album',
            'title' => 'Move This Stream to Series Streams',
            'callback' => function($rowId, $rowData, $database, $tableName) {

                // move the stream
                return KPT::moveToType( $database, $rowId, 5, 'liveorseries' );
            },
            'confirm' => 'Are you sure you want to move this stream?',
            'success_message' => 'The stream has been moved.',
            'error_message' => 'Failed to move the stream.'
        ],
        'moveother' => [
            'icon' => 'nut',
            'title' => 'Move This Stream to Other Streams',
            'callback' => function($rowId, $rowData, $database, $tableName) {

                // move the stream
                return KPT::moveToType( $database, $rowId, 4, 'toother' );
            },
            'confirm' => 'Are you sure you want to move this stream?',
            'success_message' => 'The stream has been moved.',
            'error_message' => 'Failed to move the stream.'
        ],
    ],
    'series' => [
        'movelive' => [
            'icon' => 'tv',
            'title' => 'Move This Stream to Live Streams',
            'callback' => function($rowId, $rowData, $database, $tableName) {

                // move the stream
                KPT::moveToType( $database, $rowId, 0, 'liveorseries' );
            },
            'confirm' => 'Are you sure you want to move this stream?',
            'success_message' => 'The stream has been moved.',
            'error_message' => 'Failed to move the stream.'
        ],
        'moveother' => [
            'icon' => 'nut',
            'title' => 'Move This Stream to Other Streams',
            'callback' => function($rowId, $rowData, $database, $tableName) {

                // move the stream
                return KPT::moveToType( $database, $rowId, 4, 'toother' );
            },
            'confirm' => 'Are you sure you want to move this stream?',
            'success_message' => 'The stream has been moved.',
            'error_message' => 'Failed to move the stream.'
        ],
    ],
];

// the bulk actions
$bulkActions = [
    'live' => [
        'movetoseries' => [
            'label' => 'Move to Series Streams',
            'icon' => 'album',
            'confirm' => 'Move the selected records to series streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // Track success/failure
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                // Use transaction for all operations
                $database->transaction();
                
                try {
                    // Process all selected IDs
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 5, 'liveorseries' );
                        if ($result) {
                            $successCount++;
                        }
                    }
                    
                    // Commit if all successful, rollback if any failed
                    if ($successCount === $totalCount) {
                        $database->commit();
                        return true;
                    } else {
                        $database->rollback();
                        return false;
                    }
                    
                } catch (\Exception $e) {
                    $database->rollback();
                    return false;
                }
            },
            'success_message' => 'Records moved to series streams successfully',
            'error_message' => 'Failed to move some or all records to series streams'
        ],
        'movetoother' => [
            'label' => 'Move to Other Streams',
            'icon' => 'nut',
            'confirm' => 'Move the selected records to other streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                $database->transaction();
                
                try {
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 4, 'toother' );
                        if ($result) {
                            $successCount++;
                        }
                    }
                    
                    if ($successCount === $totalCount) {
                        $database->commit();
                        return true;
                    } else {
                        $database->rollback();
                        return false;
                    }
                    
                } catch (\Exception $e) {
                    $database->rollback();
                    return false;
                }
            },
            'success_message' => 'Records moved to other streams successfully',
            'error_message' => 'Failed to move some or all records to other streams'
        ],
    ],
    'series' => [
        'movetolive' => [
            'label' => 'Move to Live Streams',
            'icon' => 'tv',
            'confirm' => 'Move the selected records to live streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                $database->transaction();
                
                try {
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 0, 'liveorseries' );
                        if ($result) {
                            $successCount++;
                        }
                    }
                    
                    if ($successCount === $totalCount) {
                        $database->commit();
                        return true;
                    } else {
                        $database->rollback();
                        return false;
                    }
                    
                } catch (\Exception $e) {
                    $database->rollback();
                    return false;
                }
            },
            'success_message' => 'Records moved to live streams successfully',
            'error_message' => 'Failed to move some or all records to live streams'
        ],
        'movetoother' => [
            'label' => 'Move to Other Streams',
            'icon' => 'nut',
            'confirm' => 'Move the selected records to other streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                $database->transaction();
                
                try {
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 4, 'toother' );
                        if ($result) {
                            $successCount++;
                        }
                    }
                    
                    if ($successCount === $totalCount) {
                        $database->commit();
                        return true;
                    } else {
                        $database->rollback();
                        return false;
                    }
                    
                } catch (\Exception $e) {
                    $database->rollback();
                    return false;
                }
            },
            'success_message' => 'Records moved to other streams successfully',
            'error_message' => 'Failed to move some or all records to other streams'
        ],
    ],
];

// setup the edit/add forms
$formFields = [
    
];

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
$dt -> table( 'kptv_streams s' )
    -> primaryKey( 's.id' )  // Use qualified primary key
    -> join( 'LEFT', 'kptv_stream_providers p', 's.p_id = p.id' )
    -> where( [
        [ // unless specified as OR, it should always be AND
            'field' => 'u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
        [ // unless specified as OR, it should always be AND
            'field' => 's_type_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $type_value
        ],
        [ // unless specified as OR, it should always be AND
            'field' => 's_active',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $active_value
        ],
    ] )
    -> tableClass( 'uk-table uk-table-divider uk-table-small uk-margin-bottom' )
    -> columns( [
        's.id' => 'ID',
        's_active' => [ 'label' => 'Active', 'type' => 'boolean' ],
        's_name' => 'Name',
        's_orig_name' => 'Original Name',
        'p.sp_name' => 'Provider',
    ] )
    -> columnClasses( [
        's.id' => 'hide-col',
        's_orig_name' => 'txt-truncate',
        'p.sp_name' => 'txt-truncate',
    ] )
    -> sortable( ['s_name', 's_orig_name', 'p.sp_name'] )
    -> defaultSort( 's_name', 'ASC' )
    -> inlineEditable( ['s_active', 's_name', ] )
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true )
    -> bulkActions( true, $bulkActions[$type_filter] )
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
        $actionGroups[$type_filter],
        ['edit', 'delete'],
    ] );

// Handle AJAX requests (before any HTML output)
if ( isset( $_POST['action'] ) || isset( $_GET['action'] ) ) {
    $dt -> handleAjax( );
}

// pull in the header
KPT::pull_header( );
?>
<div class="uk-container uk-container-full">
    <h2 class="me uk-heading-divider"><?php echo ucfirst( $type ); ?> <?php echo ucfirst( $which ); ?> Streams</h2>
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
unset( $dt, $formFields, $actionGroups, $bulkActions, $dbconf );