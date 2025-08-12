<?php
/**
 * header.php
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

// get the route we're in
$_route = Router::get_current_route( );

// title and image string
$img = '/assets/images/iptv-header.jpg';

// switch on path se we can set the title and image
switch( $_route -> path ) {
    case '/users/login':
    case '/users/register':
    case '/users/forgot':
    case '/users/changepass':
    case '/admin/users':
        $img = '/assets/images/security-header.jpg';
        break;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="author" content="Kevin C. Pirnie" />
        <link rel="profile" href="http://gmpg.org/xfn/11" />
        <title>KPTV Stream Manager</title>
        <link rel="dns-prefetch" href="//app.kptv.im" />
        <link rel="dns-prefetch" href="//cdn.jsdelivr.net" />
        <link rel="dns-prefetch" href="//instant.page" />
        <link rel="stylesheet" media="all" href="//cdn.jsdelivr.net/npm/uikit@latest/dist/css/uikit.min.css" />    
        <link rel="stylesheet" media="all" href="/assets/css/fonts.css" />
        <link rel="stylesheet" media="all" href="/assets/css/style.css" />
        <link rel="stylesheet" media="all" href="/assets/css/darkmode.css" />
        <link rel="stylesheet" media="all" href="/assets/css/custom.css?_=<?php echo time( ); ?>" />
        <link rel="icon" type="image/png" href="/assets/images/kptv-icon.png" />
    </head>
    <body class="dark-or-light" uk-height-viewport="offset-top: true">
        <header>
            <div class="dark-or-light uk-section uk-padding-remove-vertical">
                <?php

                    // get the main menu template part
                    include KPT_PATH . 'views/wrapper/main-menu.php';
                ?>
            </div>
        </header>
        <main id="page-content">
            <div class="uk-section uk-padding-remove-vertical">
                <div class="uk-overlay uk-overlay-primary uk-height-mediumish uk-background-cover uk-background-blend-darken uk-light uk-padding-remove-bottom" style="background-image: url('<?php echo $img; ?>');">
                    <div class="uk-grid">
                        <div class="uk-width-1-1 in-breadcrumb">

                        </div>
                    </div>
                </div>
            </div>
            <section class="uk-section uk-padding uk-padding-remove-horizontal uk-padding-remove-bottom in-padding-large-vertical@s uk-background-contain in-profit-2" data-src="/assets/images/in-profit-decor-3.svg" data-uk-img>
                <div class="uk-container uk-align-center">
                    <div class="uk-grid uk-flex uk-flex-center">
                        <div class="uk-width-1-1@s uk-width-3-4@m">
                            <?php

                                // if there is a message to be shown
                                if( isset( $_SESSION ) && isset( $_SESSION['page_msg'] ) ) {

                                    // show the message
                                    KPT::show_message( $_SESSION['page_msg']['type'], $_SESSION['page_msg']['msg'] );

                                    // remove it from the session
                                    unset( $_SESSION['page_msg'] );

                                }
