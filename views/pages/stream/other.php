<?php
/**
 * Other Streams View - Refactored to use modular system
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
$dt -> table( 'kptv_stream_other' )
    -> tableClass( 'uk-table uk-table-divider uk-table-small uk-margin-bottom' )
    -> columns( [
        'id' => 'ID',
        's_orig_name' => 'Original Name',
        's_stream_uri' => 'Stream URI',
        'p_id' => 'Provider',
    ] )
    -> columnClasses( [
        'id' => 'uk-min-width',
        's_stream_uri' => 'txt-truncate'
    ] )
    -> sortable( ['s_orig_name', 'p_id', ] )
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true ) // true includes "ALL" option
    -> bulkActions( true )
    -> actionGroups( [
        [
            'email' => [
                'icon' => 'play',
                'title' => 'Export Live Streams',
                'class' => 'copy-link',
                'href' => '' . KPT_URI . 'playlist/' . $userForExport . '/{id}/live',

            ],
            'export' => [
                'icon' => 'link', 
                'title' => 'Export Series Streams',
                'class' => 'copy-link',
                'href' => '' . KPT_URI . 'playlist/' . $userForExport . '/{id}/series',
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


/*
// Initialize Streams class
$streams = new KPTV_Stream_Other();

// Handle pagination
$per_page = $_GET['per_page'] ?? 25;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// Get sort parameters from URL
$sort_column = $_GET['sort'] ?? 's_orig_name';
$sort_direction = $_GET['dir'] ?? 'asc';

// Validate sort parameters
$valid_columns = ['s_orig_name', 's_stream_uri', 'p_id'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 's_orig_name';
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// Get search term
$search_term = htmlspecialchars(($_GET['s']) ?? '');

// Get providers for create form
$providers = $streams->getAllProviders();

// Get records based on search
if (!empty($search_term)) {
    $records = $streams->searchPaginated(
        $search_term,
        $per_page,
        $offset,
        $sort_column,
        $sort_direction
    );
} else {
    $records = $streams->getPaginated(
        $per_page,
        $offset,
        $sort_column,
        $sort_direction
    );
}

$total_records = $streams->getTotalCount($search_term);
$total_pages = $per_page !== 'all' ? ceil($total_records / $per_page) : 1;

// Create and configure view
$config = OtherViewConfig::getConfig();

// Add providers to modal field options dynamically
foreach ($config['modals'] as $modal_type => &$modal_config) {
    if (isset($modal_config['fields'])) {
        foreach ($modal_config['fields'] as &$field) {
            if ($field['name'] === 'p_id') {
                $field['options'] = ['' => 'Select Provider'];
                if ($providers && count($providers) > 0) {
                    foreach ($providers as $provider) {
                        $field['options'][$provider->id] = $provider->sp_name;
                    }
                }
            }
        }
    }
}

$view = new EnhancedBaseTableView('Other Stream Management', '/other', $config);

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
    'providers' => $providers
]);
*/