<?php
/**
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KPTV Stream Manager
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// Initialize Stream Filters class
$stream_filters = new KPTV_Stream_Filters( );

// Handle pagination
$per_page = $_GET['per_page'] ?? 25;
$page = $_GET['page'] ?? 1;
$offset = ( $page - 1 ) * $per_page;

// Get sort parameters from URL
$sort_column = $_GET['sort'] ?? 'sf_type_id';
$sort_direction = $_GET['dir'] ?? 'desc';

// Validate sort parameters
$valid_columns = ['sf_active', 'sf_type_id', 'sf_filter', 'sf_updated'];
$sort_column = in_array( $sort_column, $valid_columns ) ? $sort_column : 'sf_type_id';
$sort_direction = strtoupper( $sort_direction ) === 'DESC' ? 'DESC' : 'ASC';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['form_action'])) {
        try {
            $stream_filters->post_action( $_POST );
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get paginated and sorted records
$records = $stream_filters->getPaginated( $per_page, $page, $sort_column, $sort_direction );
$total_records = $stream_filters->getTotalCount();
$total_pages = $per_page !== 'all' ? ceil($total_records / $per_page) : 1;

// pull in the header
KPT::pull_header( );

// get the search term
$search_term =  htmlspecialchars( ( $_GET['s'] ) ?? '' );
?>

<div class="uk-container">
    <h2 class="me uk-heading-divider">Stream Filters</h2>
    
    <?php if (isset($error)): ?>
        <div class="uk-alert-danger" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p><?php echo htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>
    
    <!-- List Navigation -->
    <?php
        KPT::include_view( 'common/navigation', [
            'page' => $page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'base_url' => '/filters',
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
                        <input type="checkbox" id="select-all" class="uk-checkbox select-all">
                    </th>
                    <th class="sortable" data-column="sf_active">
                        Active
                        <?php if ($sort_column === 'sf_active'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sf_type_id">
                        Type
                        <?php if ($sort_column === 'sf_type_id'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sf_filter">
                        Filter
                        <?php if ($sort_column === 'sf_filter'): ?>
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
                                <span class="active-toggle" data-id="<?php echo $record->id ?>">
                                    <i uk-icon="<?php echo $record->sf_active ? 'check' : 'close' ?>" class="me"></i>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $types = [
                                    0 => 'Include Name (regex)',
                                    1 => 'Exclude Name',
                                    2 => 'Exclude Name (regex)',
                                    3 => 'Exclude Stream (regex)',
                                    4 => 'Exclude Group (regex)'
                                ];
                                echo $types[$record->sf_type_id] ?? 'Unknown';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($record->sf_filter) ?></td>
                            <td>
                                <div class="uk-button-group">
                                    <a href="#edit-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Edit this Filter" uk-icon="icon: pencil"></a>
                                    <a href="#delete-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Delete this Filter" uk-icon="icon: trash"></a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div id="edit-modal-<?php echo $record->id ?>" uk-modal>
                            <div class="uk-modal-dialog">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <div class="uk-modal-header">
                                    <h2 class="uk-modal-title">Edit Filter</h2>
                                </div>
                                <form method="POST" action="">
                                    <div class="uk-modal-body">
                                        <input type="hidden" name="form_action" value="update">
                                        <input type="hidden" name="id" value="<?php echo $record->id ?>">
                                        
                                        <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                                            <div class="">
                                                <label class="uk-form-label" for="sf_active_<?php echo $record->id ?>">Active</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="sf_active_<?php echo $record->id ?>" name="sf_active">
                                                        <option value="1" <?php echo $record->sf_active == 1 ? 'selected' : '' ?>>Yes</option>
                                                        <option value="0" <?php echo $record->sf_active == 0 ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="">
                                                <label class="uk-form-label" for="sf_type_id_<?php echo $record->id ?>">Filter Type</label>
                                                <div class="uk-form-controls">
                                                    <select class="uk-select" id="sf_type_id_<?php echo $record->id ?>" name="sf_type_id">
                                                        <option value="0" <?php echo $record->sf_type_id == 0 ? 'selected' : '' ?>>Include Name (regex)</option>
                                                        <option value="1" <?php echo $record->sf_type_id == 1 ? 'selected' : '' ?>>Exclude Name</option>
                                                        <option value="2" <?php echo $record->sf_type_id == 2 ? 'selected' : '' ?>>Exclude Name (regex)</option>
                                                        <option value="3" <?php echo $record->sf_type_id == 3 ? 'selected' : '' ?>>Exclude Stream (regex)</option>
                                                        <option value="4" <?php echo $record->sf_type_id == 4 ? 'selected' : '' ?>>Exclude Group (regex)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="">
                                            <label class="uk-form-label" for="sf_filter_<?php echo $record->id ?>">Filter Value</label>
                                            <div class="uk-form-controls">
                                                <input class="uk-input" id="sf_filter_<?php echo $record->id ?>" name="sf_filter" type="text" value="<?php echo htmlspecialchars($record->sf_filter) ?>" required>
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
                                <h2 class="uk-modal-title">Delete Filter</h2>
                                <p>Are you sure you want to delete this filter?</p>
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
                        <td colspan="6" class="uk-text-center">No filters found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th width="5px">
                        <input type="checkbox" id="select-all" class="uk-checkbox select-all">
                    </th>
                    <th class="sortable" data-column="sf_active">
                        Active
                        <?php if ($sort_column === 'sf_active'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sf_type_id">
                        Type
                        <?php if ($sort_column === 'sf_type_id'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="sf_filter">
                        Filter
                        <?php if ($sort_column === 'sf_filter'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
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
                'base_url' => '/filters',
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
                <h2 class="uk-modal-title">Add New Filter</h2>
            </div>
            <form method="POST" action="">
                <div class="uk-modal-body">
                    <input type="hidden" name="form_action" value="create">
                    
                    <div class="uk-child-width-1-2 uk-grid-small" uk-grid>
                        <div class="">
                            <label class="uk-form-label" for="sf_active">Active</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="sf_active" name="sf_active">
                                    <option value="1" selected>Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="">
                            <label class="uk-form-label" for="sf_type_id">Filter Type</label>
                            <div class="uk-form-controls">
                                <select class="uk-select" id="sf_type_id" name="sf_type_id">
                                    <option value="0">Include Name (regex)</option>
                                    <option value="1">Exclude Name</option>
                                    <option value="2">Exclude Name (regex)</option>
                                    <option value="3">Exclude Stream (regex)</option>
                                    <option value="4">Exclude Group (regex)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="">
                        <label class="uk-form-label" for="sf_filter">Filter Value</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="sf_filter" name="sf_filter" type="text" required>
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