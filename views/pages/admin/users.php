<?php
/**
 * User Management View
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// Check if user is logged in and is an admin
$currentUser = KPT_User::get_current_user( );
if ( ! $currentUser || $currentUser -> role != 99 ) {
    // it's not, dump out and redirect with a message
    KPT::message_with_redirect( '/', 'danger', 'You do not have permission to access this page.' );
    return;
}

// pull in the header
KPT::pull_header( );

// Initialize user class
$userManager = new KPT_User( );

// Pagination settings
$perPageOptions = [10, 25, 50, 0]; // 0 means "ALL"
$defaultPerPage = 25;
$currentPage = isset( $_GET['page'] ) ? max( 1, ( int ) $_GET['page'] ) : 1;
$perPage = isset( $_GET['per_page'] ) ? ( int ) $_GET['per_page'] : $defaultPerPage;

// Validate per_page value
if ( ! in_array( $perPage, $perPageOptions ) ) {
    $perPage = $defaultPerPage;
}

// Get users data
$totalUsers = $userManager -> get_total_users_count( );
$users = $userManager -> get_users_paginated( $perPage, ( $currentPage - 1 ) * $perPage );
$totalPages = $perPage > 0 ? ceil( $totalUsers / $perPage ) : 1;

// Process any actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    // try to handle the management posts
    try {
        // handle the management posts
        $userManager -> handle_posts( );

    // whoopsie...
    } catch ( Exception $e ) {
        KPT::message_with_redirect( '/admin/users?page=' . $currentPage . '&per_page=' . $perPage, 'danger', 'Error: ' . $e -> getMessage( ) );
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
                    <select class="uk-select uk-form-width-small" name="per_page" onchange="this.form.submit( )">
                        <?php foreach ( $perPageOptions as $option ): ?>
                            <option value="<?php echo $option; ?>" <?php echo $perPage == $option ? 'selected' : ''; ?>>
                                <?php echo $option === 0 ? 'ALL' : $option; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="page" value="1">
            </form>
            <span class="uk-text-meta uk-margin-left">
                Showing <?php echo $perPage > 0 ? min( $perPage, count( $users ) ) : count( $users ); ?> of <?php echo $totalUsers; ?> users
            </span>
        </div>
        
        <!-- Page navigation -->
        <?php if ( $perPage > 0 && $totalPages > 1 ): ?>
        <ul class="uk-pagination">
            <!-- Previous Page -->
            <li class="<?php echo $currentPage == 1 ? 'uk-disabled' : ''; ?>">
                <a href="?page=<?php echo max( 1, $currentPage - 1 ); ?>&per_page=<?php echo $perPage; ?>">
                    <span uk-pagination-previous></span>
                </a>
            </li>
            
            <!-- Page Numbers -->
            <?php if ( $currentPage > 3 ): ?>
                <li><a href="?page=1&per_page=<?php echo $perPage; ?>">1</a></li>
                <?php if ( $currentPage > 4 ): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ( $i = max( 1, $currentPage - 2 ); $i <= min( $totalPages, $currentPage + 2 ); $i++ ): ?>
                <li class="<?php echo $i == $currentPage ? 'uk-active' : ''; ?>">
                    <a href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ( $currentPage < $totalPages - 2 ): ?>
                <?php if ( $currentPage < $totalPages - 3 ): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
                <li><a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>"><?php echo $totalPages; ?></a></li>
            <?php endif; ?>
            
            <!-- Next Page -->
            <li class="<?php echo $currentPage >= $totalPages ? 'uk-disabled' : ''; ?>">
                <a href="?page=<?php echo min( $totalPages, $currentPage + 1 ); ?>&per_page=<?php echo $perPage; ?>">
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
                <?php foreach ( $users as $user ): ?>
                <tr>
                    <td><?php echo htmlspecialchars( $user -> id ); ?></td>
                    <td><?php echo htmlspecialchars( $user -> u_fname . ' ' . $user -> u_lname ); ?></td>
                    <td><?php echo htmlspecialchars( $user -> u_name ); ?></td>
                    <td><?php echo htmlspecialchars( $user -> u_email ); ?></td>
                    <td>
                        <span class="uk-label <?php echo $user -> u_role == 99 ? 'uk-label-warning' : 'uk-label'; ?>">
                            <?php echo $user -> u_role == 99 ? 'Admin' : 'User'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ( $user -> locked_until && strtotime( $user -> locked_until ) > time( ) ): ?>
                            <span class="uk-label uk-label-danger">Locked</span>
                        <?php else: ?>
                            <span class="uk-label <?php echo $user -> u_active ? 'uk-label-success' : 'uk-label-danger'; ?>">
                                <?php echo $user -> u_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $user -> last_login ? date( 'M j, Y g:i a', strtotime( $user -> last_login ) ) : 'Never'; ?>
                    </td>
                    <td>
                        <div class="uk-button-group">
                            <!-- Edit Button -->
                            <a href="#edit-user-<?php echo $user -> id; ?>" uk-toggle class="uk-icon-link" uk-icon="icon: pencil" uk-tooltip="Edit"></a>
                            
                            <!-- Toggle Active/Inactive -->
                            <?php if ( $user -> id != $currentUser -> id ): ?>
                                <form method="post" class="uk-display-inline">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?php echo $user -> id; ?>">
                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                    <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                                    <button type="submit" class="uk-icon-link" uk-icon="icon: <?php echo $user -> u_active ? 'lock' : 'unlock'; ?>" uk-tooltip="<?php echo $user -> u_active ? 'Deactivate this User' : 'Activate this User'; ?>"></button>
                                </form>
                            <?php else: ?>
                                <span class="uk-icon-link uk-text-muted" uk-icon="icon: user" uk-tooltip="Current user"></span>
                            <?php endif; ?>
                            
                            <!-- Unlock Button (if locked) -->
                            <?php if ( $user -> locked_until && strtotime( $user -> locked_until ) > time( ) ): ?>
                                <form method="post" class="uk-display-inline">
                                    <input type="hidden" name="action" value="unlock">
                                    <input type="hidden" name="user_id" value="<?php echo $user -> id; ?>">
                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                    <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                                    <button type="submit" class="uk-icon-link" uk-icon="icon: unlock" uk-tooltip="Unlock this User"></button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Delete Button -->
                            <?php if ( $user -> id != $currentUser -> id): ?>
                                <a href="#delete-user-<?php echo $user -> id; ?>" uk-toggle class="uk-icon-link" uk-icon="icon: trash" uk-tooltip="Delete"></a>
                            <?php else: ?>
                                <span class="uk-icon-link uk-text-muted" uk-icon="icon: ban" uk-tooltip="Cannot delete current user"></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Edit Modal -->
                        <div id="edit-user-<?php echo $user -> id; ?>" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <h2 class="uk-modal-title">Edit User: <?php echo htmlspecialchars( $user -> u_name ); ?></h2>
                                
                                <form method="post">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?php echo $user -> id; ?>">
                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                    <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_fname">First Name</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" type="text" id="u_fname" name="u_fname" value="<?php echo htmlspecialchars( $user -> u_fname ); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_lname">Last Name</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" type="text" id="u_lname" name="u_lname" value="<?php echo htmlspecialchars( $user -> u_lname ); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_email">Email</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" type="email" id="u_email" name="u_email" value="<?php echo htmlspecialchars( $user -> u_email ); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="u_role">Role</label>
                                        <div class="uk-form-controls">
                                            <select class="uk-select" id="u_role" name="u_role" <?php echo $user -> id == $currentUser -> id ? 'disabled' : ''; ?>>
                                                <option value="0" <?php echo $user -> u_role == 0 ? 'selected' : ''; ?>>User</option>
                                                <option value="99" <?php echo $user -> u_role == 99 ? 'selected' : ''; ?>>Administrator</option>
                                            </select>
                                            <?php if ($user -> id == $currentUser -> id): ?>
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
                        <?php if ($user -> id != $currentUser->id): ?>
                        <div id="delete-user-<?php echo $user -> id; ?>" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body">
                                <button class="uk-modal-close-default" type="button" uk-close></button>
                                <h2 class="uk-modal-title">Confirm Deletion</h2>
                                <p>Are you sure you want to delete user <?php echo htmlspecialchars( $user -> u_name ); ?>? This action cannot be undone.</p>
                                <p class="uk-text-danger"><strong>Warning:</strong> All data associated with this user will be permanently removed.</p>
                                
                                <form method="post" class="uk-text-right">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user -> id; ?>">
                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                    <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                                    
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
    <?php if ( $perPage > 0 && $totalPages > 1 ): ?>
    <div class="uk-margin-top uk-flex uk-flex-center">
        <ul class="uk-pagination">
            <!-- Previous Page -->
            <li class="<?php echo $currentPage == 1 ? 'uk-disabled' : ''; ?>">
                <a href="?page=<?php echo max( 1, $currentPage - 1 ); ?>&per_page=<?php echo $perPage; ?>">
                    <span uk-pagination-previous></span>
                </a>
            </li>
            
            <!-- Page Numbers -->
            <?php if ($currentPage > 3): ?>
                <li><a href="?page=1&per_page=<?php echo $perPage; ?>">1</a></li>
                <?php if ( $currentPage > 4 ): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ( $i = max( 1, $currentPage - 2 ); $i <= min( $totalPages, $currentPage + 2 ); $i++ ): ?>
                <li class="<?php echo $i == $currentPage ? 'uk-active' : ''; ?>">
                    <a href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ( $currentPage < $totalPages - 2 ): ?>
                <?php if ( $currentPage < $totalPages - 3 ): ?>
                    <li class="uk-disabled"><span>...</span></li>
                <?php endif; ?>
                <li><a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>"><?php echo $totalPages; ?></a></li>
            <?php endif; ?>
            
            <!-- Next Page -->
            <li class="<?php echo $currentPage >= $totalPages ? 'uk-disabled' : ''; ?>">
                <a href="?page=<?php echo min( $totalPages, $currentPage + 1 ); ?>&per_page=<?php echo $perPage; ?>">
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
