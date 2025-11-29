<?php
/**
 * sidebar.php
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
use KPT\Router;

// get the route we're in
$_route = Router::getCurrentRoute( );

// title and image string
$title = ( KPT_User::is_user_logged_in( ) ) ? '<br /><br />' : "KPTV Stream Manager";
$img = '/assets/images/iptv-inner.jpg';

// switch on path se we can set the title and image
switch( $_route -> path ) {
    case '/users/login':
    case '/users/register':
    case '/users/forgot':
    case '/users/changepass':
    case '/admin/users':
        $title = "Account<br />Manager";
        $img = '/assets/images/security-inner.jpg';
        break;
}

// get ther user id for the export
$user_for_export = KPT::encrypt( ( KPT_User::get_current_user( ) -> id ) ?? 0 );

?>

<div class="in-pricing-1">
    <div class="uk-card uk-card-default uk-box-shadow-medium">
        <div class="uk-card-media-top">
            <img class="uk-width-1-1 uk-align-center" data-src="<?php echo $img; ?>" height="800" width="534" data-width data-height alt="<?php echo ( $_data -> page_title ) ?? 'Kevin Pirnie\'s Support'; ?>" data-uk-img />
            <span></span>
        </div>
        <div class="uk-card-body uk-margin-small uk-padding-small">
            <div class="in-heading-extra in-card-decor-1">
                <h2 class="no-border">
                    <?php echo $title; ?>
                </h2>
            </div>
            <?php
                // if the type is empty or home
                if( ! KPT_User::is_user_logged_in( ) ) {
                    ?>
                    <a href="/users/register" class="uk-button uk-button-primary uk-border-rounded contact-button uk-margin-auto uk-margin-medium-top" target="">
                        Register
                    </a>
                    <a href="/users/forgot" class="uk-button uk-button-primary uk-border-rounded contact-button uk-margin-auto uk-margin-top" target="">
                        Forgot Password?
                    </a>
                    <a href="/users/login" class="uk-button uk-button-primary uk-border-rounded contact-button uk-margin-auto uk-margin-top uk-margin-bottom" target="">
                        Login
                    </a>
                    <?php
                } else {

                    ?>
                    <h4 class="me uk-heading-bullet uk-margin-remove-top">Account Manager</h4>
                    <ul class="uk-list uk-padding-small uk-padding-remove-vertical">
                        <li><a href="/users/changepass"><i uk-icon="icon: cog"></i> Change Your Password</a></li>
                        <li><a href="/users/logout"><i uk-icon="icon: sign-out"></i> Logout of Your Account</a></li>
                        <?php if( KPT_User::get_current_user( ) -> role == 99 ) { ?>
                            <li class="uk-li-divider"></li>
                            <li><a href="/admin/users"><i uk-icon="icon: users"></i> User Management</a></li>
                        <?php } ?>
                    </ul>
                    <h4 class="me uk-heading-bullet uk-margin-remove-top">Stream Manager</h4>
                    <ul class="uk-list uk-padding-small uk-padding-remove-vertical">
                        <li><a href="/providers"><i uk-icon="icon: server"></i> Your Providers</a></li>
                        <li><a href="/filters"><i uk-icon="icon: settings"></i> Your Filters</a></li>
                        <li class="uk-li-divider"></li>    
                        <li>
                            <a href="/streams/live/all" class="" target=""><i uk-icon="icon: tv"></i> Live Streams</a>
                            <ul class="inner-links uk-margin-remove-top uk-list uk-padding-small uk-padding-remove-vertical">
                                <li><a href="/streams/live/active">Active Streams</a></li>
                                <li><a href="/streams/live/inactive">In-Active Streams</a></li>
                                <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/live" class="copy-link">Export the Playlist</a></li>
                            </ul>
                        </li>
                        <li>
                            <a href="/streams/series/all" class="" target=""><i uk-icon="icon: album"></i>  Series Stream</a>
                            <ul class="inner-links uk-margin-remove-top uk-list uk-padding-small uk-padding-remove-vertical">
                                <li><a href="/streams/series/active">Active Streams</a></li>
                                <li><a href="/streams/series/inactive">In-Active Streams</a></li>
                                <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/series" class="copy-link">Export the Playlist</a></li>
                            </ul>
                        </li>
                        <!--<li>
                            <a href="/streams/vod/all" class="" target=""><i uk-icon="icon: video-camera"></i>  VOD Stream</a>
                            <ul class="inner-links uk-margin-remove-top uk-list uk-padding-small uk-padding-remove-vertical">
                                <li><a href="/streams/vod/active">Active Streams</a></li>
                                <li><a href="/streams/vod/inactive">In-Active Streams</a></li>
                                <li><a uk-tooltip="Click to Copy the Playlist URL" href="<?php echo KPT_URI; ?>playlist/<?php echo $user_for_export; ?>/vod" class="copy-link">Export the Playlist</a></li>
                            </ul>
                        </li>-->
                        <li><a href="/streams/other" class="" target=""><i uk-icon="icon: nut"></i> Other Streams</a></li>
                        <li><a href="/missing" class="" target=""><i uk-icon="icon: eye-slash"></i> Missing Streams</a></li>
                    </ul>                    
                    <?php

                }
                ?>
        </div>
    </div>
</div>
