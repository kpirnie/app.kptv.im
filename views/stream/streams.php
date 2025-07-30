<?php
/**
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KPTV Stream Manager
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

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
$valid_columns = ['s_name', 's_orig_name', 's_stream_uri', 's_tvg_id', 's_tvg_group', 's_active', 'p_id'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 's_name';
$sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';

// Handle stream type filter
$type_filter = $data['which'] ?? 'live';
$valid_types = ['live' => 0, 'vod' => 4, 'series' => 5];
$type_value = $valid_types[$type_filter] ?? null;

// Handle the stream active filter
$active_filter = $data['type'] ?? 'active';
$valid_active = ['active' => 1, 'inactive' => 0];
$active_value = $valid_active[$active_filter] ?? null;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_action'])) {
        try {
            $streams->post_action($_POST);
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// get the search term
$search_term = htmlspecialchars( ( $_GET['s'] ) ?? '' );

// Get all providers for dropdowns
$providers = $streams -> getAllProviders( );

// if the search is not empty
if( ! empty( $search_term ) ) {

    // Search paginated and sorted records
    $records = $streams -> searchPaginated( $search_term, $per_page, $offset, $sort_column, $sort_direction, $type_value, $active_value );

// otherwise
} else {

    // Get paginated and sorted records
    $records = $streams -> getPaginated( $per_page, $offset, $sort_column, $sort_direction, $type_value, $active_value );

}

// get the total records
$total_records = $streams -> getTotalCount( $type_value, $active_value, $search_term );
$total_pages = $per_page !== 'all' ? ceil( $total_records / $per_page ) : 1;

// pull in the header
KPT::pull_header( );

?>

<div class="uk-container">
    <h2 class="me uk-heading-divider">Streams Management</h2>
    
    <?php if (isset($error)): ?>
        <div class="uk-alert-danger" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p><?php echo htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>
    
    <!-- List Navigation -->
    <?php
        KPT::include_view('common/navigation', [
            'page' => $page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'base_url' => sprintf( '/streams/%s/%s/', $type_filter, ( ( $active_filter ) ?? 'all' ) ),
            'max_visible_links' => 5,
            'show_first_last' => true,
            'search_term' => $search_term,
        ]);
    ?>
    
    <div class="uk-overflow-auto">
        <table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">
            <thead>
                <tr>
                    <th width="5px">
                        <input type="checkbox" id="select-all" class="uk-checkbox">
                    </th>
                    <th class="sortable" data-column="s_active">
                        Active
                        <?php if ($sort_column === 's_active'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="s_name">
                        Name
                        <?php if ($sort_column === 's_name'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="s_orig_name">
                        Original Name
                        <?php if ($sort_column === 's_orig_name'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="p_id">
                        Provider
                        <?php if ($sort_column === 'p_id'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records && count($records) > 0): ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="<?php echo $record->id ?>" class="uk-checkbox record-checkbox">
                            </td>
                            <td>
                                <span class="active-toggle" data-id="<?php echo $record->id ?>" uk-tooltip="<?php echo $record->s_active ? 'Deactivate' : 'Activate' ?> This Stream">
                                    <i uk-icon="<?php echo $record->s_active ? 'check' : 'close' ?>" class="me"></i>
                                </span>
                            </td>
                            <td class="stream-name name-cell"><?php echo htmlspecialchars($record->s_name) ?></td>
                            <td class="truncate"><?php echo htmlspecialchars($record->s_orig_name) ?></td>
                            <td class="truncate"><?php echo htmlspecialchars($record->provider_name ?? 'N/A') ?></td>
                            <td class="action-cell">
                                <div class="uk-button-group">
                                    <a href="#" uk-tooltip="Copy the Stream URL" uk-icon="link" class="uk-link-icon"></a>
                                    <?php
                                        // if we're live
                                        if( $type_filter == 'live' ) {
                                            echo '<a href="#" uk-tooltip="Move to Series List" uk-icon="album" class="uk-link-icon move-to-series single-move"></a>';
                                        // otherwise it's a series already
                                        } else {
                                            echo '<a href="#" uk-tooltip="Move to Live List" uk-icon="tv" class="uk-link-icon move-to-live single-move"></a>';
                                        }
                                    ?>
                                    <a href="#" uk-tooltip="Move to Other List" uk-icon="nut" class="uk-link-icon move-to-other single-move"></a>
                                    <a href="#edit-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Edit this Stream" uk-icon="icon: pencil"></a>
                                    <a href="#delete-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Delete this Stream" uk-icon="icon: trash"></a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div id="edit-modal-<?php echo $record->id ?>" uk-modal>
                            <div class="uk-modal-dialog">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <div class="uk-modal-header">
                                    <h2 class="uk-modal-title">Edit Stream</h2>
                                </div>
                                <form method="POST" action="">
                                    <div class="uk-modal-body">
                                        <input type="hidden" name="form_action" value="update">
                                        <input type="hidden" name="id" value="<?php echo $record->id ?>">
                                        
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div>
                                                <label class="uk-form-label" for="s_active_<?php echo $record->id ?>">Active</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="s_active_<?php echo $record->id ?>" name="s_active">
                                                        <option value="1" <?php echo $record->s_active == 1 ? 'selected' : '' ?>>Yes</option>
                                                        <option value="0" <?php echo $record->s_active == 0 ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label class="uk-form-label" for="s_type_id_<?php echo $record->id ?>">Stream Type</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="s_type_id_<?php echo $record->id ?>" name="s_type_id">
                                                        <option value="0" <?php echo $record->s_type_id == 0 ? 'selected' : '' ?>>Live</option>
                                                        <option value="4" <?php echo $record->s_type_id == 4 ? 'selected' : '' ?>>VOD</option>
                                                        <option value="5" <?php echo $record->s_type_id == 5 ? 'selected' : '' ?>>Series</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="uk-margin-small">
                                            <label class="uk-form-label" for="s_name_<?php echo $record->id ?>">Name</label>
                                            <div class="uk-form-controls">
                                                <input class="uk-input" id="s_name_<?php echo $record->id ?>" name="s_name" type="text" value="<?php echo htmlspecialchars($record->s_name) ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="uk-margin-small">
                                            <label class="uk-form-label" for="s_orig_name_<?php echo $record->id ?>">Original Name</label>
                                            <div class="uk-form-controls">
                                                <input class="uk-input" id="s_orig_name_<?php echo $record->id ?>" name="s_orig_name" type="text" value="<?php echo htmlspecialchars($record->s_orig_name) ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="uk-margin-small">
                                            <label class="uk-form-label" for="s_stream_uri_<?php echo $record->id ?>">Stream URI</label>
                                            <div class="uk-form-controls">
                                                <input class="uk-input" id="s_stream_uri_<?php echo $record->id ?>" name="s_stream_uri" type="text" value="<?php echo htmlspecialchars($record->s_stream_uri) ?>" required>
                                            </div>
                                        </div>

                                        <div class="uk-margin-small">
                                            <label class="uk-form-label" for="s_tvg_logo_<?php echo $record->id ?>">TVG Logo</label>
                                            <div class="uk-form-controls">
                                                <input class="uk-input" id="s_tvg_logo_<?php echo $record->id ?>" name="s_tvg_logo" type="text" value="<?php echo htmlspecialchars($record->s_tvg_logo) ?>">
                                            </div>
                                        </div>

                                        <div class="uk-margin-small">
                                            <label class="uk-form-label" for="p_id_<?php echo $record->id ?>">Provider</label>
                                            <div class="uk-form-controls">
                                                <select class="uk-select" id="p_id_<?php echo $record->id ?>" name="p_id">
                                                    <option value="0">No Provider</option>
                                                    <?php foreach ($providers as $provider): ?>
                                                        <option value="<?php echo $provider->id ?>" <?php echo $record->p_id == $provider->id ? 'selected' : '' ?>>
                                                            <?php echo htmlspecialchars($provider->sp_name) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div>
                                                <label class="uk-form-label" for="s_tvg_id_<?php echo $record->id ?>">TVG ID</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="s_tvg_id_<?php echo $record->id ?>" name="s_tvg_id" type="text" value="<?php echo htmlspecialchars($record->s_tvg_id) ?>">
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <label class="uk-form-label" for="s_tvg_group_<?php echo $record->id ?>">TVG Group</label>
                                                <div class="uk-form-controls">
                                                    <input class="uk-input" id="s_tvg_group_<?php echo $record->id ?>" name="s_tvg_group" type="text" value="<?php echo htmlspecialchars($record->s_tvg_group) ?>">
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
                        <div id="delete-modal-<?php echo $record->id ?>" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <h2 class="uk-modal-title">Delete Stream</h2>
                                <p>Are you sure you want to delete this stream?</p>
                                <p class="uk-text-danger">This action cannot be undone.</p>
                                <form method="POST" action="">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $record->id ?>">
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
                        <td colspan="6" class="uk-text-center">No streams found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- List Navigation -->
        <?php
            KPT::include_view('common/navigation', [
                'page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'base_url' => sprintf( '/streams/%s/%s/', $type_filter, ( ( $active_filter ) ?? 'all' ) ),
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
                <h2 class="uk-modal-title">Add New Stream</h2>
            </div>
            <form method="POST" action="">
                <div class="uk-modal-body">
                    <input type="hidden" name="form_action" value="create">
                    
                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div>
                            <label class="uk-form-label" for="s_active">Active</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="s_active" name="s_active">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="uk-form-label" for="s_type_id">Stream Type</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="s_type_id" name="s_type_id">
                                    <option value="0">Live</option>
                                    <option value="4">VOD</option>
                                    <option value="5">Series</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="uk-margin-small">
                        <label class="uk-form-label" for="s_name">Name</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="s_name" name="s_name" type="text" required>
                        </div>
                    </div>
                    
                    <div class="uk-margin-small">
                        <label class="uk-form-label" for="s_orig_name">Original Name</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="s_orig_name" name="s_orig_name" type="text" required>
                        </div>
                    </div>
                    
                    <div class="uk-margin-small">
                        <label class="uk-form-label" for="s_stream_uri">Stream URI</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="s_stream_uri" name="s_stream_uri" type="text" required>
                        </div>
                    </div>

                    <div class="uk-margin-small">
                        <label class="uk-form-label" for="s_tvg_logo">TVG Logo</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="s_tvg_logo" name="s_tvg_logo" type="text">
                        </div>
                    </div>

                    <div class="uk-margin-small">
                        <label class="uk-form-label" for="p_id">Provider</label>
                        <div class="uk-form-controls">
                            <select class="uk-select" id="p_id" name="p_id">
                                <option value="0">No Provider</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?php echo $provider->id ?>">
                                        <?php echo htmlspecialchars($provider->sp_name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>             

                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div>
                            <label class="uk-form-label" for="s_tvg_id">TVG ID</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="s_tvg_id" name="s_tvg_id" type="text">
                            </div>
                        </div>
                        
                        <div>
                            <label class="uk-form-label" for="s_tvg_group">TVG Group</label>
                            <div class="uk-form-controls">
                                <input class="uk-input" id="s_tvg_group" name="s_tvg_group" type="text">
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
KPT::pull_footer();
