<?php
/**
 * menu.php
 * 
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;

// get ther user id for the export
$user_for_export = KPT::encrypt( ( KPT_User::get_current_user( ) -> id ) ?? 0 );

// get the user role
$user_role = ( KPT_User::get_current_user( ) -> role ) ?? 0;

?>
<nav class="dark-or-light uk-navbar-container uk-padding-small" data-uk-sticky="show-on-up: false; top: 80; animation: uk-animation-slide-top;">
    <div class="uk-container mynavbar" data-uk-navbar>
        <div class="uk-navbar-left uk-width-auto">
            <div class="uk-navbar-item uk-logo">
                <a class="header-logo" href="/">
                    <h1 class="uk-margin-remove-vertical">Stream Manager</h1>
                </a>
            </div>
        </div>
        <div class="uk-navbar-right uk-width-expand uk-flex uk-flex-right">
            <ul class="uk-navbar-nav uk-visible@m">
                <li><a href="/">Home</a></li>
                <li>
                    <a href="#" class="uk-parent">INFO <i uk-icon="icon: chevron-down;"></i></a>
                    <div class="dark-or-light uk-navbar-dropdown">
                        <ul role="menu" class="uk-nav uk-navbar-dropdown-nav">
                            <li><a href="/users/faq">Account Management</a></li>
                            <li><a href="/streams/faq">Stream Management</a></li>
                            <li class="uk-nav-divider"></li>
                            <li><a href="/terms-of-use">Terms of Use</a></li>
                        </ul>
                    </div>
                </li>
                <?php
                    // check if there is a user object, and a user id
                    if( KPT_User::is_user_logged_in( ) ) {
                        ?>
                        <li>
                            <a href="#" class="uk-parent">Your Streams <i uk-icon="icon: chevron-down;"></i></a>
                            <div class="dark-or-light uk-navbar-dropdown">
                                <ul role="menu" class="uk-nav uk-navbar-dropdown-nav">
                                    <li><a href="/providers"><i uk-icon="icon: server"></i> Your Providers</a></li>
                                    <li><a href="/filters"><i uk-icon="icon: settings"></i> Your Filters</a></li>
                                    <li class="uk-nav-divider"></li>
                                    <li>
                                        <a href="/stream/live/all"><i uk-icon="icon: tv"></i> Live Streams</a>
                                        <ul class="uk-list inner-links uk-padding-small uk-padding-remove-vertical uk-margin-remove-top">
                                            <li><a href="/streams/live/active">Active Streams</a></li>
                                            <li><a href="/streams/live/inactive">In-Active Streams</a></li>
                                            <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/live" class="copy-link">Export the Playlist</a></li>
                                        </ul>
                                    </li>
                                    <li class="uk-nav-divider"></li>
                                    <li>
                                        <a href="/streams/series/all"><i uk-icon="icon: album"></i> Series Streams</a>
                                        <ul class="uk-list inner-links uk-padding-small uk-padding-remove-vertical uk-margin-remove-top">
                                            <li><a href="/streams/series/active">Active Streams</a></li>
                                            <li><a href="/streams/series/inactive">In-Active Streams</a></li>
                                            <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/series" class="copy-link">Export the Playlist</a></li>
                                        </ul>
                                    </li>
                                    <!--<li class="uk-nav-divider"></li>
                                    <li>
                                        <a href="/streams/vod/all"><i uk-icon="icon: video-camera"></i> VOD Streams</a>
                                        <ul class="uk-list inner-links uk-padding-small uk-padding-remove-vertical uk-margin-remove-top">
                                            <li><a href="/streams/vod/active">Active Streams</a></li>
                                            <li><a href="/streams/vod/iactive">In-Active Streams</a></li>
                                            <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/vod" class="copy-link">Export the Playlist</a></li>
                                        </ul>
                                    </li>-->
                                    <li class="uk-nav-divider"></li>
                                    <li>
                                        <a href="/streams/other"><i uk-icon="icon: nut"></i> Other Streams</a>
                                    </li>
                                    <li>
                                        <a href="/missing"><i uk-icon="icon: eye-slash"></i> Missing Streams</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <?php
                    }
                ?>
                <li>
                    <a href="#" class="uk-parent">Your Account <i uk-icon="icon: chevron-down;"></i></a>
                    <div class="dark-or-light uk-navbar-dropdown">
                        <ul role="menu" class="uk-nav uk-navbar-dropdown-nav">
                            <?php

                            // check if there is a user object, and a user id
                            if( KPT_User::is_user_logged_in( ) ) {
                            ?>
                                <li><a href="/users/changepass">Change Your Password</a></li>
                                <li class="uk-nav-divider"></li>
                                <li><a href="/users/logout">Logout of Your Account</a></li>
                            <?php
                            // there isn't so replace this with the login stuff
                            } else {
                            ?>
                                <li><a href="/users/login">Login to Your Account</a></li>
                                <li class="uk-nav-divider"></li>
                                <li><a href="/users/register">Register an Account</a></li>
                                <li><a href="/users/forgot">Forgot Your Password?</a></li>
                            <?php 
                            }
                            ?>
                        </ul>
                    </div>
                </li>
                <?php
                    if( $user_role == 99 ) {
                        ?>
                        <li>
                            <a href="#" class="uk-parent">Admin <i uk-icon="icon: chevron-down;"></i></a>
                            <div class="dark-or-light uk-navbar-dropdown">
                                <ul role="menu" class="uk-nav uk-navbar-dropdown-nav">
                                    <li><a href="/admin/users">User Management</a></li>
                                </ul>
                            </div>
                        </li>
                        <?php
                    }
                ?>
            </ul>

            <div class="uk-navbar-item in-mobile-nav uk-hidden@m">
                <a class="uk-button" href="#modal-full" data-uk-toggle><i uk-icon="icon: menu;"></i></a>
            </div>
            <div id="modal-full" class="uk-modal-full" data-uk-modal>
                <div class="dark-or-light uk-modal-dialog uk-flex uk-flex-center" data-uk-height-viewport>
                    <a class="dark-or-light uk-modal-close-full uk-button uk-icon-link" uk-icon="close"></a>
                    <div class="uk-width-large uk-padding-small uk-padding-remove-horizontal">
                        <div class="uk-logo">
                            <a class="header-logo mobile" href="/">
                                <h1 class="uk-margin-remove-vertical">Stream Manager</h1>
                            </a>
                        </div>
                        <ul class="uk-navbar kp-mobile-menu">
                            <li><a href="/">Home</a></li>
                            <li>
                                <a href="#" class="uk-parent">INFO <i uk-icon="icon: chevron-down;"></i></a>
                                <div class="dark-or-light uk-navbar-dropdown">
                                    <ul role="menu" class="uk-nav uk-navbar-dropdown-nav">
                                        <li><a href="/users/faq">Account Management</a></li>
                                        <li><a href="/streams/faq">Stream Management</a></li>
                                        <li class="uk-nav-divider"></li>
                                        <li><a href="/terms-of-use">Terms of Use</a></li>
                                    </ul>
                                </div>
                            </li>
                            <?php
                                // check if there is a user object, and a user id
                                if( KPT_User::is_user_logged_in( ) ) {
                                    ?>
                                    <li>
                                        <a href="#" class="uk-parent">Your Streams <i uk-icon="icon: chevron-down;"></i></a>
                                        <ul role="menu" class="uk-nav uk-padding-small uk-padding-remove-vertical uk-padding-remove-right">
                                            <li><a href="/providers"><i uk-icon="icon: server"></i> Your Providers</a></li>
                                            <li><a href="/filters"><i uk-icon="icon: settings"></i> Your Filters</a></li>
                                            <li class="uk-nav-divider"></li>
                                            <li>
                                                <a href="/streams/live/all"><i uk-icon="icon: tv"></i> Live Streams</a>
                                                <ul class="uk-list inner-links uk-padding-small uk-padding-remove-vertical uk-margin-remove-top">
                                                    <li><a href="/streams/live/active">Active Streams</a></li>
                                                    <li><a href="/streams/live/inactive">In-Active Streams</a></li>
                                                    <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/live" class="copy-link">Export the Playlist</a></li>
                                                </ul>
                                            </li>
                                            <li class="uk-nav-divider"></li>
                                            <li>
                                                <a href="/streams/series/all"><i uk-icon="icon: album"></i> Series Streams</a>
                                                <ul class="uk-list inner-links uk-padding-small uk-padding-remove-vertical uk-margin-remove-top">
                                                    <li><a href="/streams/series/active">Active Streams</a></li>
                                                    <li><a href="/streams/series/inactive">In-Active Streams</a></li>
                                                    <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/series" class="copy-link">Export the Playlist</a></li>
                                                </ul>
                                            </li>
                                            <!--<li class="uk-nav-divider"></li>
                                            <li>
                                                <a href="/streams/vod/all"><i uk-icon="icon: video-camera"></i> VOD Streams</a>
                                                <ul class="uk-list inner-links uk-padding-small uk-padding-remove-vertical uk-margin-remove-top">
                                                    <li><a href="/streams/vod/active">Active Streams</a></li>
                                                    <li><a href="/streams/vod/iactive">In-Active Streams</a></li>
                                                </ul>
                                            </li>-->
                                            <li class="uk-nav-divider"></li>
                                            <li>
                                                <a href="/streams/other"><i uk-icon="icon: nut"></i> Other Streams</a>
                                            </li>
                                            <li>
                                                <a href="/missing"><i uk-icon="icon: eye-slash"></i> Missing Streams</a>
                                            </li>
                                        </ul>
                                    </li>
                                    <?php
                                }
                            ?>
                            <li>
                                <a href="#" class="uk-parent">Your Account <i uk-icon="icon: chevron-down;"></i></a>
                                <ul role="menu" class="uk-nav uk-padding-small uk-padding-remove-vertical uk-padding-remove-right">
                                    <?php

                                    // check if there is a user object, and a user id
                                    if( KPT_User::is_user_logged_in( ) ) {
                                    ?>
                                        <li><a href="/users/changepass">Change Your Password</a></li>
                                        <li class="uk-nav-divider"></li>
                                        <li><a href="/users/logout">Logout of Your Account</a></li>
                                    <?php
                                    // there isn't so replace this with the login stuff
                                    } else {
                                    ?>
                                        <li><a href="/users/login">Login to Your Account</a></li>
                                        <li class="uk-nav-divider"></li>
                                        <li><a href="/users/register">Register an Account</a></li>
                                        <li><a href="/users/forgot">Forgot Your Password?</a></li>
                                    <?php 
                                    }
                                    ?>
                                    <?php if( $user_role == 99 ) { ?>
                                        <li class="uk-nav-divider"></li>
                                        <li><a href="/admin/users">Admin Users</a></li>
                                    <?php } ?>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
