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
    -> table( 'dev_test' )
    -> columns([
        'id' => 'ID',
        'active' => 'Active', 
        'testttttt' => 'Test',
        'yeppers' => 'Test 2'
    ] )
    -> sortable( ['id', 'active', 'testttttt'] )
    -> bulkActions( true )
    -> inlineEditable( ['active', 'testttttt'] );

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataTables Example</title>
    <!-- UIKit3 CSS (required) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@latest/dist/css/uikit.min.css">
    <?php echo KPT\DataTables\Renderer::getCssIncludes( 'dark' ); ?>
</head>
<body>
    <div class="uk-container uk-container-large uk-padding-top">
        <h1>My DataTable</h1>

        <?php

            // Render table
            echo $dataTable -> render( );

        ?>
    </div>

    <!-- UIKit3 JavaScript (required) -->
    <script src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.min.js"></script>
    <?php echo KPT\DataTables\Renderer::getJsIncludes( ); ?>
</body>
</html>