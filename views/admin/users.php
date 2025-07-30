<?php
/**
 * User Management View
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// Check if user is logged in and is an admin
$currentUser = KPT_User::get_current_user();
if (!$currentUser || $currentUser->role != 99) {
    KPT::message_with_redirect('/', 'danger', 'You do not have permission to access this page.');
    return;
}

// pull in the header
KPT::pull_header( );

// Initialize database connection
$db = new KPT_DB();

// Pagination settings
$perPageOptions = [10, 25, 50, 0]; // 0 means "ALL"
$defaultPerPage = 25;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : $defaultPerPage;

// Validate per_page value
if (!in_array($perPage, $perPageOptions)) {
    $perPage = $defaultPerPage;
}

// Get total number of users
$totalUsers = $db->select_single('SELECT COUNT(*) as total FROM kptv_users')->total;

// Calculate pagination values
$totalPages = $perPage > 0 ? ceil($totalUsers / $perPage) : 1;
$offset = $perPage > 0 ? ($currentPage - 1) * $perPage : 0;

// Build the query with pagination
$query = 'SELECT * FROM kptv_users ORDER BY u_created DESC';
if ($perPage > 0) {
    $query .= " LIMIT $perPage OFFSET $offset";
}

// Get users for current page
$users = $db->select_many($query);

// Process any actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    try {
        switch ($action) {

            case 'toggle_active':
                // Prevent deactivating yourself
                if ($userId === $currentUser->id) {
                    throw new Exception('You cannot change your own status');
                }
                
                $current = $db->select_single('SELECT u_active FROM kptv_users WHERE id = ?', [$userId]);
                if ($current) {
                    $newStatus = $current->u_active ? 0 : 1;
                    $db->execute('UPDATE kptv_users SET u_active = ? WHERE id = ?', [$newStatus, $userId]);
                    KPT::message_with_redirect('/admin/users?page='.$currentPage.'&per_page='.$perPage, 'success', 'User status updated successfully.');
                }
                break;
                
            case 'unlock':
                $db->execute('UPDATE kptv_users SET login_attempts = 0, locked_until = NULL WHERE id = ?', [$userId]);
                KPT::message_with_redirect('/admin/users?page='.$currentPage.'&per_page='.$perPage, 'success', 'User account unlocked successfully.');
                break;
                
            case 'delete':
                // Prevent deleting yourself
                if ( $userId === $currentUser -> id ) {
                    throw new Exception('You cannot delete your own account');
                }
                
                // delete from the stream tables first
                $db -> execute( sprintf( 'DELETE FROM %sstreams WHERE id = ?', TBL_PREFIX ), [$userId] );
                $db -> execute( sprintf( 'DELETE FROM %sstream_filters WHERE id = ?', TBL_PREFIX ), [$userId] );
                $db -> execute( sprintf( 'DELETE FROM %sstream_other WHERE id = ?', TBL_PREFIX ), [$userId] );
                $db -> execute( sprintf( 'DELETE FROM %sstream_providers WHERE id = ?', TBL_PREFIX ), [$userId] );

                // now delete the user
                $db -> execute( sprintf( 'DELETE FROM %susers WHERE id = ?', TBL_PREFIX ), [$userId] );

                // redirect with a message
                KPT::message_with_redirect( '/admin/users?page=' . $currentPage . '&per_page=' . $perPage, 'success', 'User deleted successfully.' );
                break;
                
            case 'update':
            
                $data = [
                    'u_fname' => KPT::sanitize_string($_POST['u_fname'] ?? ''),
                    'u_lname' => KPT::sanitize_string($_POST['u_lname'] ?? ''),
                    'u_email' => KPT::sanitize_string($_POST['u_email'] ?? ''),
                    'u_role' => (int)($_POST['u_role'] ?? 0),
                    'id' => $userId
                ];
                
                // Validate email
                if (!KPT::validate_email($data['u_email'])) {
                    throw new Exception('Invalid email address');
                }
                
                // Prevent changing your own role to non-admin
                if ($userId === $currentUser->id && $data['u_role'] != 99) {
                    throw new Exception('You cannot remove your own admin privileges');
                }
                
                $db->execute(
                    'UPDATE kptv_users SET u_fname = ?, u_lname = ?, u_email = ?, u_role = ?, u_updated = NOW( ) WHERE id = ?',
                    [$data['u_fname'], $data['u_lname'], $data['u_email'], $data['u_role'], $data['id']]
                );
                
                KPT::message_with_redirect( '/admin/users?page='.$currentPage.'&per_page='.$perPage, 'success', 'User updated successfully.' );
                break;
            }
            
    // whoopsie...
    } catch ( Exception $e ) {
        KPT::message_with_redirect( '/admin/users?page='.$currentPage.'&per_page='.$perPage, 'danger', 'Error: ' . $e -> getMessage( ) );
    }
}
?>

<div class="uk-container">
    <h3 class="uk-heading-divider uk-margin-remove-top">User Management</h3>
    
    <!-- Pagination Controls (Top) -->
    <div class="uk-margin-bottom uk-flex uk-flex-between uk-flex-middle">
        <!-- Items per page selector -->
        <div>
            <form class="uk-display-inline" method="get" action="">
                <div class="uk-inline">
                    <select class="uk-select uk-form-width-small" name="per_page" onchange="this.form.submit()">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?= $option ?>" <?= $perPage == $option ? 'selected' : '' ?>>
                                <?= $option === 0 ? 'ALL' : $option ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="page" value="1">
            </form>
            <span class="uk-text-meta uk-margin-left">
                Showing <?= $perPage > 0 ? min($perPage, count($users)) : count($users) ?> of <?= $totalUsers ?> users
            </span>
        </div>
        
        <!-- Page navigation -->
        <?php if ($perPage > 0 && $totalPages > 1): ?>
        <ul class="uk-pagination">
            <!-- Previous Page -->
            <li class="<?= $currentPage == 1 ? 'uk-disabled' : '' ?>">
                <a href="?page=<?= max(1, $currentPage - 1) ?>&per_page=<?= $perPage ?>">
                    <span uk-pagination-previous></span>
                </a>
            </li>
            
            <!-- Page Numbers -->
            <?php 
            // Show first page + separator if needed
            if ($currentPage > 3): ?>
                <li><a href="?page=1&per_page=<?= $perPage ?>">1</a></li>
                <?php if ($currentPage > 4): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php 
            // Show pages around current page
            for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <li class="<?= $i == $currentPage ? 'uk-active' : '' ?>">
                    <a href="?page=<?= $i ?>&per_page=<?= $perPage ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            
            <!-- Show last page + separator if needed -->
            <?php if ($currentPage < $totalPages - 2): ?>
                <?php if ($currentPage < $totalPages - 3): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
                <li><a href="?page=<?= $totalPages ?>&per_page=<?= $perPage ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            
            <!-- Next Page -->
            <li class="<?= $currentPage >= $totalPages ? 'uk-disabled' : '' ?>">
                <a href="?page=<?= min($totalPages, $currentPage + 1) ?>&per_page=<?= $perPage ?>">
                    <span uk-pagination-next></span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
    
    <!-- User Table -->
    <div class="uk-overflow-auto">
        <table class="uk-table uk-table-hover uk-table-middle uk-table-divider">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user->id) ?></td>
                    <td><?= htmlspecialchars($user->u_fname . ' ' . $user->u_lname) ?></td>
                    <td><?= htmlspecialchars($user->u_name) ?></td>
                    <td><?= htmlspecialchars($user->u_email) ?></td>
                    <td>
                        <span class="uk-label <?= $user->u_role == 99 ? 'uk-label-warning' : 'uk-label' ?>">
                            <?= $user->u_role == 99 ? 'Admin' : 'User' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($user->locked_until && strtotime($user->locked_until) > time()): ?>
                            <span class="uk-label uk-label-danger">Locked</span>
                        <?php else: ?>
                            <span class="uk-label <?= $user->u_active ? 'uk-label-success' : 'uk-label-danger' ?>">
                                <?= $user->u_active ? 'Active' : 'Inactive' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $user->last_login ? date('M j, Y g:i a', strtotime($user->last_login)) : 'Never' ?>
                    </td>
                    <td>
                        <div class="uk-button-group">
                            <!-- Edit Button -->
                            <a href="#edit-user-<?= $user->id ?>" uk-toggle class="uk-icon-link" uk-icon="icon: pencil" uk-tooltip="Edit"></a>
                            
                            <!-- Toggle Active/Inactive -->
                            <?php if ($user->id != $currentUser->id): ?>
                                <form method="post" class="uk-display-inline">
                                    <input type="hidden" name="kpt_csrf" value="<?php //echo KPT_CSRF::getToken( ) ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= $user->id ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                                    <button type="submit" class="uk-icon-link" uk-icon="icon: <?= $user->u_active ? 'lock' : 'unlock' ?>" uk-tooltip="<?= $user->u_active ? 'Deactivate this User' : 'Activate this User' ?>"></button>
                                </form>
                            <?php else: ?>
                                <span class="uk-icon-link uk-text-muted" uk-icon="icon: user" uk-tooltip="Current user"></span>
                            <?php endif; ?>
                            
                            <!-- Unlock Button (if locked) -->
                            <?php if ($user->locked_until && strtotime($user->locked_until) > time()): ?>
                                <form method="post" class="uk-display-inline">
                                    <input type="hidden" name="kpt_csrf" value="<?php// echo KPT_CSRF::getToken( ) ?>">
                                    <input type="hidden" name="action" value="unlock">
                                    <input type="hidden" name="user_id" value="<?= $user->id ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                                    <button type="submit" class="uk-icon-link" uk-icon="icon: unlock" uk-tooltip="Unlock this User"></button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Delete Button -->
                            <?php if ($user->id != $currentUser->id): ?>
                                <a href="#delete-user-<?= $user->id ?>" uk-toggle class="uk-icon-link" uk-icon="icon: trash" uk-tooltip="Delete"></a>
                            <?php else: ?>
                                <span class="uk-icon-link uk-text-muted" uk-icon="icon: ban" uk-tooltip="Cannot delete current user"></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Edit Modal -->
                        <div id="edit-user-<?= $user->id ?>" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <h2 class="uk-modal-title">Edit User: <?= htmlspecialchars($user->u_name) ?></h2>
                                
                                <form method="post">
                                    <input type="hidden" name="kpt_csrf" value="<?php //echo KPT_CSRF::getToken( ) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?= $user->id ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_fname">First Name</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" type="text" id="u_fname" name="u_fname" value="<?= htmlspecialchars($user->u_fname) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_lname">Last Name</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" type="text" id="u_lname" name="u_lname" value="<?= htmlspecialchars($user->u_lname) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_email">Email</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" type="email" id="u_email" name="u_email" value="<?= htmlspecialchars($user->u_email) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_role">Role</label>
                                        <div class="uk-form-controls">
                                            <select class="uk-select" id="u_role" name="u_role" <?= $user->id == $currentUser->id ? 'disabled' : '' ?>>
                                                <option value="0" <?= $user->u_role == 0 ? 'selected' : '' ?>>User</option>
                                                <option value="99" <?= $user->u_role == 99 ? 'selected' : '' ?>>Administrator</option>
                                            </select>
                                            <?php if ($user->id == $currentUser->id): ?>
                                                <input type="hidden" name="u_role" value="99">
                                                <div class="uk-text-meta">You cannot change your own role</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin uk-text-right">
                                        <button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>
                                        <button class="uk-button uk-button-primary" type="submit">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Delete Confirmation Modal -->
                        <?php if ($user->id != $currentUser->id): ?>
                        <div id="delete-user-<?= $user->id ?>" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <h2 class="uk-modal-title">Confirm Deletion</h2>
                                <p>Are you sure you want to delete user <?= htmlspecialchars($user->u_name) ?>? This action cannot be undone.</p>
                                <p class="uk-text-danger"><strong>Warning:</strong> All data associated with this user will be permanently removed.</p>
                                
                                <form method="post" class="uk-text-right">
                                    <input type="hidden" name="kpt_csrf" value="<?php //echo KPT_CSRF::getToken( ) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user->id ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                                    
                                    <button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>
                                    <button class="uk-button uk-button-danger" type="submit">Delete User</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination Controls (Bottom) -->
    <?php if ($perPage > 0 && $totalPages > 1): ?>
    <div class="uk-margin-top uk-flex uk-flex-center">
        <ul class="uk-pagination">
            <!-- Previous Page -->
            <li class="<?= $currentPage == 1 ? 'uk-disabled' : '' ?>">
                <a href="?page=<?= max(1, $currentPage - 1) ?>&per_page=<?= $perPage ?>">
                    <span uk-pagination-previous></span>
                </a>
            </li>
            
            <!-- Page Numbers -->
            <?php 
            // Show first page + separator if needed
            if ($currentPage > 3): ?>
                <li><a href="?page=1&per_page=<?= $perPage ?>">1</a></li>
                <?php if ($currentPage > 4): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php 
            // Show pages around current page
            for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <li class="<?= $i == $currentPage ? 'uk-active' : '' ?>">
                    <a href="?page=<?= $i ?>&per_page=<?= $perPage ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            
            <!-- Show last page + separator if needed -->
            <?php if ($currentPage < $totalPages - 2): ?>
                <?php if ($currentPage < $totalPages - 3): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
                <li><a href="?page=<?= $totalPages ?>&per_page=<?= $perPage ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            
            <!-- Next Page -->
            <li class="<?= $currentPage >= $totalPages ? 'uk-disabled' : '' ?>">
                <a href="?page=<?= min($totalPages, $currentPage + 1) ?>&per_page=<?= $perPage ?>">
                    <span uk-pagination-next></span>
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php

// pull in the footer
KPT::pull_footer( );
