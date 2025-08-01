<?php
/**
 * 
 * No direct access allowed!
 * 
 * @mince 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KPTV Stream Manager
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// Initialize Stream Providers class
$stream_providers = new KPTV_Stream_Providers( );

// Handle pagination
$per_page = $_GET['per_page'] ?? 25;
$page = $_GET['page'] ?? 1;
$offset = ( $page - 1 ) * $per_page;

// Get sort parameters from URL
$sort_column = $_GET['sort'] ?? 'sp_priority';
$sort_direction = $_GET['dir'] ?? 'asc';

// Validate sort parameters
$valid_columns = ['sp_priority', 'sp_name', 'sp_type', 'sp_stream_type', 'sp_last_synced'];
$sort_column = in_array( $sort_column, $valid_columns ) ? $sort_column : 'sp_priority';
$sort_direction = $sort_direction === 'desc' ? 'DESC' : 'ASC';

// get the search term
$search_term =  htmlspecialchars( ( $_GET['s'] ) ?? '' );

// Handle CRUD operations
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

    // if the form action is posted
    if ( isset( $_POST['form_action'] ) ) {
        try {
            $stream_providers -> post_actions( $_POST );    
        } catch ( Exception $e ) {
            $error = "Database error: " . $e -> getMessage( );
        }
    }
}

// Get total count of records for current user
$total_records = $stream_providers -> getTotalCount( $search_term );

// if we're searching
if( $search_term ) {
    // Get paginated records with sorting
    $records = $stream_providers -> searchPaginated( $search_term, $per_page, $offset, $sort_column, $sort_direction );
} else {
    // Get paginated records with sorting
    $records = $stream_providers -> getPaginated( $per_page, $offset, $sort_column, $sort_direction );
}

// Calculate total pages
$total_pages = ceil( $total_records / $per_page ) ?? 1;

// pull in the header
KPT::pull_header( );

?>

<div class="uk-container">
    <h2 class="me uk-heading-divider">Stream Providers</h2>
    
    <?php if ( isset( $error ) ): ?>
        <div class="uk-alert-danger" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p><?php echo htmlspecialchars( $error ) ?></p>
        </div>
    <?php endif; ?>
    
    <!-- List Navigation -->
    <?php
        KPT::include_view( 'common/navigation', [
            'page' => $page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'base_url' => '/providers',
            'max_visible_links' => 5,
            'show_first_last' => true,
            'search_term' => $search_term,
        ] );
    ?>
    
    <div class="uk-overflow-auto">
        <table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">
            <thead>
                <tr>
                    <th width="5px">
                        <input type="checkbox" id="select-all" class="uk-checkbox select-all">
                    </th>
                    <th>Export</th>
                    <th class="sortable uk-visible@s" data-column="sp_priority">
                        Priority
                        <?php if (($_GET['sort'] ?? 'sp_priority') === 'sp_priority'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sp_name">
                        Name
                        <?php if (($_GET['sort'] ?? '') === 'sp_name'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable uk-visible@m" data-column="sp_type">
                        Type
                        <?php if (($_GET['sort'] ?? '') === 'sp_type'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable uk-visible@m" data-column="sp_stream_type">
                        Stream
                        <?php if (($_GET['sort'] ?? '') === 'sp_stream_type'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sp_should_filter">
                        Filter
                        <?php if (($_GET['sort'] ?? '') === 'sp_should_filter'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>                        
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records && count($records) > 0): ?>
                    <?php foreach ($records as $record): 
                        $user_for_link = KPT::encrypt( $record -> u_id );
                        $prov_for_link = KPT::encrypt( $record -> id );
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="<?php echo $record->id ?>" class="uk-checkbox record-checkbox">
                            </td>
                            <td>
                                <div class="uk-button-group">
                                    <a href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_link; ?>/<?php echo $prov_for_link; ?>/live" class="uk-icon-link copy-link" uk-icon="tv" uk-tooltip="Copy This Providers Live Streams Playlist"></a>
                                    <a href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_link; ?>/<?php echo $prov_for_link; ?>/series" class="uk-icon-link copy-link" uk-icon="album" uk-tooltip="Copy This Providers Series Streams Playlist"></a>
                                </div>
                            </td>
                            <td class="uk-visible@s"><?php echo $record->sp_priority ?></td>
                            <td><?php echo htmlspecialchars($record->sp_name) ?></td>
                            <td class="uk-visible@m"><?php echo $record->sp_type == 0 ? 'XC API' : 'M3U' ?></td>
                            <td class="uk-visible@m"><?php echo $record->sp_stream_type == 0 ? 'MPEGTS' : 'HLS' ?></td>
                            <td class="active-toggle" data-id="<?php echo $record->id ?>">
                                <i uk-icon="<?php echo $record->sp_should_filter ? 'check' : 'close' ?>" class="me"></i>
                            </td>
                            <td>
                                <div class="uk-button-group">
                                    <a href="#edit-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Edit this Provider" uk-icon="icon: pencil"></a>
                                    <a href="#delete-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Delete this Provider" uk-icon="icon: trash"></a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div id="edit-modal-<?php echo $record -> id ?>" uk-modal>
                            <div class="uk-modal-dialog">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <div class="uk-modal-header">
                                    <h2 class="uk-modal-title">Edit Provider</h2>
                                </div>
                                <form method="POST" action="">

                                    <div class="uk-modal-body">
                                        <input type="hidden" name="form_action" value="update">
                                        <input type="hidden" name="id" value="<?php echo $record -> id; ?>">
                                        
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_name_<?php echo $record -> id; ?>">Name</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="sp_name_<?php echo $record -> id; ?>" name="sp_name" type="text" value="<?php echo htmlspecialchars( $record -> sp_name ); ?>" required>
                                                </div>
                                            </div>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_type_<?php echo $record->id ?>">Provider Type</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="sp_type_<?php echo $record -> id; ?>" name="sp_type">
                                                        <option value="0" <?php echo $record -> sp_type == 0 ? 'selected' : ''; ?>>XC API</option>
                                                        <option value="1" <?php echo $record -> sp_type == 1 ? 'selected' : ''; ?>>M3U</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="">
                                            <label class="uk-form-label" for="sp_domain_<?php echo $record -> id; ?>">Domain</label>
                                            <div class="uk-form-controls">
                                                <input class="uk-input" id="sp_domain_<?php echo $record -> id; ?>" name="sp_domain" type="text" value="<?php echo htmlspecialchars( $record -> sp_domain ); ?>" required>
                                            </div>
                                        </div>
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_username_<?php echo $record -> id; ?>">Username</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="sp_username_<?php echo $record -> id; ?>" name="sp_username" type="text" value="<?php echo htmlspecialchars( $record -> sp_username ?? '' ); ?>">
                                                </div>
                                            </div>                                           
                                            <div class="">
                                                <label class="uk-form-label" for="sp_password_<?php echo $record -> id; ?>">Password</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="sp_password_<?php echo $record -> id; ?>" name="sp_password" type="text" value="<?php echo htmlspecialchars( $record -> sp_password ?? '' ); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_stream_type_<?php echo $record->id ?>">Stream Type</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="sp_stream_type_<?php echo $record -> id; ?>" name="sp_stream_type">
                                                        <option value="0" <?php echo $record -> sp_stream_type == 0 ? 'selected' : ''; ?>>MPEGTS</option>
                                                        <option value="1" <?php echo $record -> sp_stream_type == 1 ? 'selected' : ''; ?>>HLS</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_should_filter_<?php echo $record -> id; ?>">Filter Content</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="sp_should_filter_<?php echo $record->id ?>" name="sp_should_filter">
                                                        <option value="1" <?php echo $record -> sp_should_filter == 1 ? 'selected' : ''; ?>>Yes</option>
                                                        <option value="0" <?php echo $record -> sp_should_filter == 0 ? 'selected' : ''; ?>>No</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_priority_<?php echo $record -> id; ?>">Priority</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="sp_priority_<?php echo $record -> id; ?>" name="sp_priority" type="number" value="<?php echo $record -> sp_priority; ?>" min="0" max="99">
                                                </div>
                                            </div>
                                            <div class="">
                                                <label class="uk-form-label" for="sp_refresh_period_<?php echo $record -> id  ?>">Refresh Period (days)</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="sp_refresh_period_<?php echo $record -> id; ?>" name="sp_refresh_period" type="number" value="<?php echo $record -> sp_refresh_period; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="uk-modal-footer uk-text-right">
                                        <button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>
                                        <button class="uk-button uk-button-primary" type="submit">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Delete Modal -->
                        <div id="delete-modal-<?php echo $record -> id ?>" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <h2 class="uk-modal-title">Delete Provider</h2>
                                <p>Are you sure you want to delete "<?php echo htmlspecialchars( $record -> sp_name ); ?>"?</p>
                                <p class="uk-text-danger">This action cannot be undone.</p>
                                <form method="POST" action="">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $record -> id; ?>">
                                    <div class="uk-modal-footer uk-text-right">
                                        <button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>
                                        <button class="uk-button uk-button-danger" type="submit">Delete</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="10" class="uk-text-center">No providers found</td>
                    </tr>
                <?php endif; ?>

            </tbody>
            <tfoot>
                <tr>
                    <th width="5px">
                        <input type="checkbox" id="select-all" class="uk-checkbox select-all">
                    </th>
                    <th>Export</th>
                    <th class="sortable uk-visible@s" data-column="sp_priority">
                        Priority
                        <?php if (($_GET['sort'] ?? 'sp_priority') === 'sp_priority'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sp_name">
                        Name
                        <?php if (($_GET['sort'] ?? '') === 'sp_name'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable uk-visible@m" data-column="sp_type">
                        Type
                        <?php if (($_GET['sort'] ?? '') === 'sp_type'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable uk-visible@m" data-column="sp_stream_type">
                        Stream
                        <?php if (($_GET['sort'] ?? '') === 'sp_stream_type'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sp_should_filter">
                        Filter
                        <?php if (($_GET['sort'] ?? '') === 'sp_should_filter'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= ($_GET['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>                        
                    <th>Actions</th>
                </tr>
            </tfoot>
        </table>

    <!-- List Navigation -->
    <?php
        KPT::include_view( 'common/navigation', [
            'page' => $page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'base_url' => '/providers',
            'max_visible_links' => 5,
            'show_first_last' => true,
            'search_term' => $search_term,
        ]);
    ?>
    </div>
     
    <!-- Create Modal -->
    <div id="create-modal" uk-modal>
        <div class="uk-modal-dialog uk-modal-body">
            <button class="uk-modal-close-default" type="button" uk-close></button>
            <div class="uk-modal-header">
                <h2 class="uk-modal-title">Add New Provider</h2>
            </div>
            <form method="POST" action="">
                <div class="uk-modal-body">
                    <input type="hidden" name="form_action" value="create">
                    
                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div class="">
                            <label class="uk-form-label" for="sp_name">Name</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="sp_name" name="sp_name" type="text" required>
                            </div>
                        </div>

                        <div class="">
                            <label class="uk-form-label" for="sp_type">Provider Type</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="sp_type" name="sp_type">
                                    <option value="0">XC API</option>
                                    <option value="1">M3U</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="">
                        <label class="uk-form-label" for="sp_domain">Domain</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="sp_domain" name="sp_domain" type="text" required>
                        </div>
                    </div>

                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div class="">
                            <label class="uk-form-label" for="sp_username">Username</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="sp_username" name="sp_username" type="text">
                            </div>
                        </div>
                        
                        <div class="">
                            <label class="uk-form-label" for="sp_password">Password</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="sp_password" name="sp_password" type="text">
                            </div>
                        </div>
                    </div>
                    
                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div class="">
                            <label class="uk-form-label" for="sp_stream_type">Stream Type</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="sp_stream_type" name="sp_stream_type">
                                    <option value="0">MPEGTS</option>
                                    <option value="1">HLS</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="">
                            <label class="uk-form-label" for="sp_should_filter">Filter Content</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="sp_should_filter" name="sp_should_filter">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div class="">
                            <label class="uk-form-label" for="sp_priority">Priority</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="sp_priority" name="sp_priority" type="number" value="99" min="0" max="99">
                            </div>
                        </div>
                                            
                        <div class="">
                            <label class="uk-form-label" for="sp_refresh_period">Refresh Period (days)</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="sp_refresh_period" name="sp_refresh_period" type="number" value="3">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="uk-modal-footer uk-text-right">
                    <button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>
                    <button class="uk-button uk-button-primary" type="submit">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php

// pull in the footer
KPT::pull_footer( );
