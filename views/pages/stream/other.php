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
$dt -> table( 'kptv_stream_other s' )
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
        's_stream_uri' => 'txt-truncate'
    ] )
    -> sortable( ['s_orig_name', 'p.sp_name'] )
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true )
    -> bulkActions( true )
    -> actionGroups( [
        [
            'email' => [
                'icon' => 'play',
                'title' => 'Export Live Streams',
                'class' => 'copy-link',
                'href' => '#-{s_orig_name}',
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

// pull in the footer
KPT::pull_footer( );

// clean up
unset( $dt, $formFields, $dbconf );
