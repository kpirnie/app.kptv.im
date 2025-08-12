<?php
/**
 * user/login.php
 * 
 * No direct access allowed!
 * 
 * @since 8.3
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// use our namespace
use KPT\KPT;

// pull in the header
KPT::pull_header( );

// check if we're already logged in
if( \KPT_User::is_user_logged_in( ) ) {

    // do the message and redirect
    KPT::message_with_redirect( '/', 'danger', 'You don\'t belong there.  Don\'t worry, our support team has been notified.' );

} else {

    ?>
    <h2 class="me">Login to Your Account</h2>
    <form action="/users/login" method="POST" class="uk-form-stacked" id="t-login">
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: user"></span>
                <input class="uk-input" id="frmUsername" type="text" placeholder="Username..." name="frmUsername" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: lock"></span>
                <input class="uk-input" id="frmPassword" type="password" placeholder="Password..." name="frmPassword" />
            </div>
        </div>
        <div class="uk-margin uk-grid uk-grid-small">
            <div class="uk-width-1-1">
                <button class="uk-button uk-button-primary uk-border-rounded contact-button uk-align-right g-recaptcha" data-badge="inline" data-sitekey="<?php echo KPT::get_setting( 'recaptcha' ) -> sitekey; ?>" data-callback='onSubmit' data-action='submit'>
                    Login Now <i uk-icon="icon: sign-in"></i>
                </button>
            </div>
        </div>
        <div class="uk-margin">
        </div>
    </form>
    <script src="//www.google.com/recaptcha/api.js"></script>
    <script>
        function onSubmit( token ) {
            document.getElementById( "t-login" ).submit( );
        }
    </script>
<?php

}

// pull in the footer
KPT::pull_footer( );
