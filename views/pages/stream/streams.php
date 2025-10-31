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
$valid_types = ['live' => 0, 'vod' => 4, 'series' => 5, 'other' => 99];
$type_value = $valid_types[$type_filter] ?? null;

// Handle the stream active filter (passed from router)
$active_filter = $type ?? 'active';
$valid_active = ['active' => 1, 'inactive' => 0];
$active_value = $valid_active[$active_filter] ?? null;

// setup the user id
$userId = KPT_User::get_current_user( ) -> id;

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
        'movevod' => [
            'icon' => 'video-camera',
            'title' => 'Move This Stream to VOD Streams',
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
                return KPT::moveToType( $database, $rowId, 99, 'toother' );
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
        'movevod' => [
            'icon' => 'video-camera',
            'title' => 'Move This Stream to VOD Streams',
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
                return KPT::moveToType( $database, $rowId, 99, 'toother' );
            },
            'confirm' => 'Are you sure you want to move this stream?',
            'success_message' => 'The stream has been moved.',
            'error_message' => 'Failed to move the stream.'
        ],
    ],
    'vod' => [
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
        'movevod' => [
            'icon' => 'video-camera',
            'title' => 'Move This Stream to VOD Streams',
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
                return KPT::moveToType( $database, $rowId, 99, 'toother' );
            },
            'confirm' => 'Are you sure you want to move this stream?',
            'success_message' => 'The stream has been moved.',
            'error_message' => 'Failed to move the stream.'
        ],
    ],
    'other' => [
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
        'movevod' => [
            'icon' => 'video-camera',
            'title' => 'Move This Stream to VOD Streams',
            'callback' => function($rowId, $rowData, $database, $tableName) {

                // move the stream
                return KPT::moveToType( $database, $rowId, 5, 'liveorseries' );
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
        'livestreamact' => [
            'label' => '(De)Activate Streams',
            'icon' => 'crosshairs',
            'confirm' => 'Are you sure you want to (de)activate these streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have records selected
                if ( empty( $selectedIds ) ) return false;

                // setup the placeholders and the query
                $placeholders = implode( ',', array_fill( 0, count( $selectedIds), '?' ) );
                $sql = "UPDATE {$tableName} SET s_active = NOT s_active WHERE id IN ({$placeholders})";

                // return the execution
                return $database -> query( $sql )
                        -> bind( $selectedIds )
                        -> execute( ) !== false;

            },
            'success_message' => 'Records (de)activated',
            'error_message' => 'Failed to (de)activate'
        ],
        'movetoseries' => [
            'label' => 'Move to Series Streams',
            'icon' => 'album',
            'confirm' => 'Move the selected records to series streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
        'movetovod' => [
            'label' => 'Move to VOD Streams',
            'icon' => 'video-camera',
            'confirm' => 'Move the selected records to vod streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
            'success_message' => 'Records moved to vod streams successfully',
            'error_message' => 'Failed to move some or all records to vod streams'
        ],
        'movetoother' => [
            'label' => 'Move to Other Streams',
            'icon' => 'nut',
            'confirm' => 'Move the selected records to other streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {
                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                $database->transaction();
                
                try {
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 99, 'toother' );
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
        'seriesstreamact' => [
            'label' => '(De)Activate Streams',
            'icon' => 'crosshairs',
            'confirm' => 'Are you sure you want to (de)activate these streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have records selected
                if ( empty( $selectedIds ) ) return false;

                // setup the placeholders and the query
                $placeholders = implode( ',', array_fill( 0, count( $selectedIds), '?' ) );
                $sql = "UPDATE {$tableName} SET s_active = NOT s_active WHERE id IN ({$placeholders})";

                // return the execution
                return $database -> query( $sql )
                        -> bind( $selectedIds )
                        -> execute( ) !== false;

            },
            'success_message' => 'Records (de)activated',
            'error_message' => 'Failed to (de)activate'
        ],
        'movetolive' => [
            'label' => 'Move to Live Streams',
            'icon' => 'tv',
            'confirm' => 'Move the selected records to live streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;

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
        'movetovod' => [
            'label' => 'Move to VOD Streams',
            'icon' => 'video-camera',
            'confirm' => 'Move the selected records to vod streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
            'success_message' => 'Records moved to vod streams successfully',
            'error_message' => 'Failed to move some or all records to vod streams'
        ],
        'movetoother' => [
            'label' => 'Move to Other Streams',
            'icon' => 'nut',
            'confirm' => 'Move the selected records to other streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {
                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                $database->transaction();
                
                try {
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 99, 'toother' );
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
    'vod' => [
        'movetolive' => [
            'label' => 'Move to Live Streams',
            'icon' => 'tv',
            'confirm' => 'Move the selected records to live streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;

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
        'movetoseries' => [
            'label' => 'Move to Series Streams',
            'icon' => 'album',
            'confirm' => 'Move the selected records to series streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
        'movetovod' => [
            'label' => 'Move to VOD Streams',
            'icon' => 'video-camera',
            'confirm' => 'Move the selected records to vod streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
            'success_message' => 'Records moved to vod streams successfully',
            'error_message' => 'Failed to move some or all records to vod streams'
        ],
        'movetoother' => [
            'label' => 'Move to Other Streams',
            'icon' => 'nut',
            'confirm' => 'Move the selected records to other streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {
                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
                $successCount = 0;
                $totalCount = count($selectedIds);
                
                $database->transaction();
                
                try {
                    foreach($selectedIds as $id) {
                        $result = KPT::moveToType( $database, $id, 99, 'toother' );
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
    'other' => [
        'movetolive' => [
            'label' => 'Move to Live Streams',
            'icon' => 'tv',
            'confirm' => 'Move the selected records to live streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;

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
        'movetoseries' => [
            'label' => 'Move to Series Streams',
            'icon' => 'album',
            'confirm' => 'Move the selected records to series streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
        'movetovod' => [
            'label' => 'Move to VOD Streams',
            'icon' => 'video-camera',
            'confirm' => 'Move the selected records to vod streams?',
            'callback' => function( $selectedIds, $database, $tableName ) {

                // make sure we have selected items
                if ( empty( $selectedIds ) ) return false;
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
            'success_message' => 'Records moved to vod streams successfully',
            'error_message' => 'Failed to move some or all records to vod streams'
        ],
    ],
];

// setup the form fields
$formFields = [
    's.u_id' => [
        'type' => 'hidden',
        'value' => $userId,
        'required' => true
    ],
    's_name' => [
        'type' => 'text',
        'required' => true,
        'class' => 'uk-width-1-1 uk-margin-bottom',
        'label' => 'Name',
    ],
    's_orig_name' => [
        'type' => 'text',
        'required' => true,
        'class' => 'uk-width-1-1 uk-margin-bottom',
        'label' => 'Original Name',
    ],
    's_stream_uri' => [
        'type' => 'url',
        'required' => true,
        'class' => 'uk-width-1-1 uk-margin-bottom',
        'label' => 'Stream URL',
    ],
    'p_id' => [
        'type' => 'select',
        'required' => true,
        'class' => 'uk-width-1-1 uk-margin-bottom',
        'label' => 'Provider',
        'options' => KPT::getProviders( $userId ),
    ], 
    's_active' => [
        'type' => 'boolean',
        'label' => 'Stream Active?',
        'class' => 'uk-width-1-2',
    ],
    's_type_id' => [
        'type' => 'select',
        'label' => 'Stream Type',
        'options' => [
            0 => 'Live',
            5 => 'Series',
            4 => 'VOD',
        ],
        'class' => 'uk-width-1-2',
    ],
    's_channel' => [
        'type' => 'number',
        'class' => 'uk-width-1-2',
        'label' => 'Channel',
        'default' => '0',
    ],
    's_tvg_logo' => [
        'type' => 'image',
        'class' => 'uk-width-1-2',
        'label' => 'Channel Logo',
    ],
    's_tvg_id' => [
        'type' => 'text',
        'class' => 'uk-width-1-2',
        'label' => 'TVG ID',
    ],
    's_tvg_group' => [
        'type' => 'text',
        'class' => 'uk-width-1-2',
        'label' => 'TVG Group',
    ],
    's_extras' => [
        'type' => 'text',
        'class' => 'uk-width-1-1',
        'label' => 'Attributes',
    ],
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

// configure the datatable
$dt -> table( 'kptv_streams s' )
    -> primaryKey( 's.id' )  // Use qualified primary key
    -> join( 'LEFT', 'kptv_stream_providers p', 's.p_id = p.id' )
    -> where( [
        [ // unless specified as OR, it should always be AND
            'field' => 's.u_id',
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
            'value' => ( $type_value == 99 ) ? 0 : $active_value,
        ],
    ] )
    -> tableClass( 'uk-table uk-table-divider uk-table-small uk-margin-bottom' )
    -> columns( [
        's.id' => 'ID',
        's_active' => [ 'label' => 'Active', 'type' => 'boolean' ],
        's_channel' => 'Channel',
        's_name' => 'Name',
        's_orig_name' => 'Orig. Name',
        's_tvg_id' => 'TVG ID',
        'p.sp_name' => 'Provider',
        's_tvg_logo' => [ 'label' => 'Logo', 'type' => 'image' ],
    ] )
    -> columnClasses( [
        's.id' => 'hide-col',
        's_orig_name' => 'hide-col',
        's_tvg_id' => 'txt-truncate',
        'p.sp_name' => 'txt-truncate',
    ] )
    -> sortable( ['s_name', 's_channel', 's_tvg_id', 'p.sp_name'] )
    -> defaultSort( 's_name', 'ASC' )
    -> inlineEditable( ['s_active', 's_channel', 's_name', 's_tvg_logo', 's_tvg_id', ] )
    -> perPage( 25 )
    -> pageSizeOptions( [25, 50, 100, 250], true )
    -> bulkActions( true, $bulkActions[$type_filter] )
    -> addForm( 'Add a Stream', $formFields, class: 'uk-grid-small uk-grid' )
    -> editForm( 'Update a Stream', $formFields, class: 'uk-grid-small uk-grid' )
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
    <div class="">
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
    <div class="">
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