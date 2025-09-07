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
                return true; //return move( $database, $selectedIds, 0 );

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
                return true; //move( $database, $selectedIds, 5 );

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
    <h2 class="me uk-heading-divider">Other Stream Management</h2>
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

// move function
function move($database, $selectedIds, $from, $to) : bool {

    // Use transaction for multiple operations
    $database -> transaction( );
    try {
        
        // loop the IDs
        foreach($selectedIds as $id) {
            
            // Call stored procedure for each ID
            $result = $database
                -> query( 'CALL Streams_Move_From_Other(?, ?)' )
                -> bind( [$id, $which] )
                -> execute( );
            
            // Check if sproc failed
            if ( $result === false ) {
                $database -> rollback( );
                return false;
            }
        }
        
        // Commit if all successful
        $database -> commit( );
        return true;
        
    } catch ( \Exception $e ) {
        // Rollback on error
        $database -> rollback( );
        return false;
    }

}

// pull in the footer
KPT::pull_footer( );

// clean up
unset( $dt, $formFields, $dbconf );


/*

use KPT\KPT;

// Extract route parameters from current URL
$current_path = parse_url(KPT::get_user_uri( ), PHP_URL_PATH);
$path_parts = explode('/', trim($current_path, '/'));

// Extract which and type from URL path: /streams/{which}/{type}
$which = $path_parts[1] ?? 'live';   // Default to 'live'
$type = $path_parts[2] ?? 'active';  // Default to 'active'

// Initialize Streams class
$streams = new KPTV_Streams( );

// Handle pagination
$per_page = $_GET['per_page'] ?? 25;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// Get sort parameters from URL
$sort_column = $_GET['sort'] ?? 's_name';
$sort_direction = $_GET['dir'] ?? 'asc';

// Validate sort parameters
$valid_columns = ['s_name', 's_orig_name', 's_stream_uri', 's_tvg_id', 's_tvg_group', 's_active', 'p_id', 's_channel'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 's_name';
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// Handle stream type filter (passed from router)
$type_filter = $which ?? 'live';
$valid_types = ['live' => 0, 'vod' => 4, 'series' => 5];
$type_value = $valid_types[$type_filter] ?? null;

// Handle the stream active filter (passed from router)
$active_filter = $type ?? 'active';
$valid_active = ['active' => 1, 'inactive' => 0];
$active_value = $valid_active[$active_filter] ?? null;

// Get search term
$search_term = htmlspecialchars(($_GET['s']) ?? '');

// Get all providers for dropdowns
$providers = $streams->getAllProviders();

// Get records based on search
$filters = [
    'type_id' => $type_value,
    'active' => $active_value
];

if (!empty($search_term)) {
    $records = $streams->searchPaginated(
        $search_term,
        $per_page,
        $offset,
        $sort_column,
        $sort_direction,
        $filters,
    );
} else {
    $records = $streams->getPaginated(
        $per_page,
        $offset,
        $sort_column,
        $sort_direction,
        $filters,
    );
}

$total_records = $streams->getTotalCount($search_term,$filters);
$total_pages = $per_page !== 'all' ? ceil($total_records / $per_page) : 1;

// Create and configure view with dynamic configuration
$config = StreamsViewConfig::getConfig($type_filter);

// Add providers to modal field options dynamically
foreach ($config['modals'] as $modal_type => &$modal_config) {
    if (isset($modal_config['fields'])) {
        foreach ($modal_config['fields'] as &$field) {
            if ($field['name'] === 'p_id') {
                $field['options'] = [0 => 'No Provider'];
                if ($providers && count($providers) > 0) {
                    foreach ($providers as $provider) {
                        $field['options'][$provider->id] = $provider->sp_name;
                    }
                }
            }
        }
    }
}

$title = ucfirst($active_filter) . ' ' . ucfirst($type_filter) . ' Streams Management';
$base_url = sprintf('/streams/%s/%s/', $type_filter, ($active_filter) ?? 'all');

$view = new EnhancedBaseTableView($title, $base_url, $config);

// Render the view using modular system
$view->display([
    'records' => $records ?: [],
    'page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'search_term' => $search_term,
    'sort_column' => $sort_column,
    'sort_direction' => $sort_direction,
    'error' => null,
    'type_filter' => $type_filter,
    'active_filter' => $active_filter,
    'providers' => $providers
]);
*/