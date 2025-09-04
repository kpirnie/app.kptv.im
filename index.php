<?php
require 'vendor/autoload.php';

use KPT\DataTables\DataTables;

// Configure database
$dbConfig = [
    'server' => 'localhost',
    'schema' => 'dev-db',
    'username' => 'dev-dbuser',
    'password' => 'fLjLQBsFqhm0inG2OMDX',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$dataTable = new DataTables( $dbConfig );

// Configure table FIRST
$dataTable
    -> table( 'kptv_streams' )
    -> columns( [
        'id' => 'ID',
        's_active' => 'Active',
        's_channel' => 'Channel',
        's_name' => 'Name',
        's_orig_name' => 'Original Name',
    ] )
    -> sortable( ['id', 's_active', 's_channel', 's_name'] )
    -> bulkActions( true )
    -> inlineEditable( ['s_active', 's_channel', 's_name'] )
    -> actionGroups( [
        ['edit', 'delete'],
        [
            'view_details' => [
                'icon' => 'info',
                'title' => 'View Details',
                'class' => 'btn-view-details',
                'onclick' => 'viewStreamDetails(this.closest(\'tr\').dataset.id)'
            ],
            'copy_stream' => [
                'icon' => 'copy', 
                'title' => 'Duplicate Stream',
                'class' => 'btn-copy',
                'onclick' => 'copyStream(this.closest(\'tr\').dataset.id)'
            ]
        ]
    ] )
    -> addForm( 'Add New Stream', [
        's_active' => [
            'type' => 'select',
            'label' => 'Active',
            'required' => true,
            'options' => [
                '1' => 'Active',
                '0' => 'Inactive'
            ],
            'default' => '1'
        ],
        's_channel' => [
            'type' => 'text',
            'label' => 'Channel',
            'required' => true,
            'placeholder' => 'Enter channel name'
        ],
        's_name' => [
            'type' => 'text',
            'label' => 'Stream Name',
            'required' => true,
            'placeholder' => 'Enter stream name'
        ],
        's_orig_name' => [
            'type' => 'text',
            'label' => 'Original Name',
            'required' => false,
            'placeholder' => 'Enter original name'
        ]
    ] )
    -> editForm( 'Edit Stream', [
        's_active' => [
            'type' => 'select',
            'label' => 'Active',
            'required' => true,
            'options' => [
                '1' => 'Active',
                '0' => 'Inactive'
            ],
        ],
        's_channel' => [
            'type' => 'text',
            'label' => 'Channel',
            'required' => true,
            'placeholder' => 'Enter channel name',
        ],
        's_name' => [
            'type' => 'text',
            'label' => 'Stream Name',
            'required' => true,
            'placeholder' => 'Enter stream name',
        ],
        's_orig_name' => [
            'type' => 'text',
            'label' => 'Original Name',
            'required' => false,
            'placeholder' => 'Enter original name',
            'disabled' => true,
        ]
    ] );

// Handle AJAX requests if present
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
    exit; // Stop execution after AJAX response
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataTables Example</title>
    <!-- UIKit3 CSS (required) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@latest/dist/css/uikit.min.css">
    <link rel="stylesheet" href="/assets/css/datatables-dark.css">
</head>
<body class="uk-light">
    <section class="uk-section uk-section-default">
        <div class="uk-container uk-container-expand uk-padding-top">
            <h1>My DataTable</h1>

            <?php

                // Render table
                echo $dataTable -> render( );

            ?>
        </div>
    </section>
    <!-- UIKit3 JavaScript (required) -->
    <script src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.min.js"></script>
    <?php echo KPT\DataTables\Renderer::getJsIncludes( ); ?>
    <script>
        function viewStreamDetails(id) {
            console.log('Viewing details for stream ID:', id);
            // Add your custom logic here
        }

        function copyStream(id) {
            console.log('Copying stream ID:', id);  
            // Add your custom logic here
        }
    </script>
</body>
</html>