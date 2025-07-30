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

// Get paginated and sorted records
$total_records = $streams->getTotalCount( $search_term );
$total_pages = $per_page !== 'all' ? ceil($total_records / $per_page) : 1;

// Get providers for create form
$providers = $streams->getProviders();


// if the search is not empty
if( ! empty( $search_term ) ) {

    // Search paginated and sorted records
    $records = $streams -> searchPaginated( $search_term, $per_page, $offset, $sort_column, $sort_direction );

// otherwise
} else {

    // Get paginated and sorted records
    $records = $streams -> getPaginated( $per_page, $offset, $sort_column, $sort_direction );

}

// pull in the header
KPT::pull_header( );
?>

<div class="uk-container">
    <h2 class="me uk-heading-divider">Other Stream Management</h2>
    
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
            'base_url' => '/other',
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
                    <th class="sortable" data-column="s_orig_name">
                        Original Name
                        <?php if ($sort_column === 's_orig_name'): ?>
                            <span class="uk-align-right" uk-icon="icon: chevron-<?= $sort_direction === 'asc' ? 'up' : 'down' ?>"></span>
                        <?php endif; ?>
                    </th>
                    <th class="sortable" data-column="s_stream_uri">
                        Stream URI
                        <?php if ($sort_column === 's_stream_uri'): ?>
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
                            <td><?php echo htmlspecialchars($record->s_orig_name) ?></td>
                            <td><?php echo htmlspecialchars($record->s_stream_uri) ?></td>
                            <td><?php echo htmlspecialchars($record->provider_name ?? 'N/A') ?></td>
                            <td>
                                <div class="uk-button-group">
                                    <a href="#delete-modal-<?php echo $record->id ?>" class="uk-icon-link" uk-toggle uk-tooltip="Delete this Stream" uk-icon="icon: trash"></a>
                                </div>
                            </td>
                        </tr>
                        
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
                        <td colspan="5" class="uk-text-center">No streams found</td>
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
                'base_url' => '/other',
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
                    
                    <div class="uk-margin">
                        <label class="uk-form-label" for="s_orig_name">Original Name</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="s_orig_name" name="s_orig_name" type="text" required>
                        </div>
                    </div>
                    
                    <div class="uk-margin">
                        <label class="uk-form-label" for="s_stream_uri">Stream URI</label>
                        <div class="uk-form-controls">
                            <input class="uk-input" id="s_stream_uri" name="s_stream_uri" type="text" required>
                        </div>
                    </div>
                    
                    <div class="uk-margin">
                        <label class="uk-form-label" for="p_id">Provider</label>
                        <div class="uk-form-controls">
                            <select class="uk-select" id="p_id" name="p_id" required>
                                <option value="">Select Provider</option>
                                <?php if ($providers && count($providers) > 0): ?>
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?php echo $provider->id ?>"><?php echo htmlspecialchars($provider->sp_name) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
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