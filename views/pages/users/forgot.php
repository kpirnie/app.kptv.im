<?php
/**
 * user/forgot.php
 * 
 * No direct access allowed!
 * 
 * @since 8.3
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package Pirnie Media IPTV
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// pull in the header
KPT::pull_header( );

// check if we're already logged in
if( KPT_User::is_user_logged_in( ) ) {

    // message with redirect
    KPT::message_with_redirect( '/', 'danger', 'You don\'t belong there.  Don\'t worry, our support team has been notified.' );

} else {

    ?>
    <h2 class="me">Forgot Your Password?</h2>
    <form action="/users/forgot" method="POST" class="uk-form-stacked" id="t-forgot">
        <div class="uk-margin">
            <?php KPT::show_message(
                'info',
                '<ul class="uk-list uk-list-disc uk-margin-remove-top">
                    <li>All fields are required.</li>
                    <li>You will be sent a new password by email.  We recommend you login with it as soon as you receive it and change it to something you will remember.</li>
                </ul>
                '
            ); ?>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: user"></span>
                <input class="uk-input" id="frmUsername" type="text" placeholder="Your Username" name="frmUsername" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: lock"></span>
                <input class="uk-input" id="frmEmail" type="email" placeholder="Your Email" name="frmEmail" />
            </div>
        </div>
        <div class="uk-margin uk-grid uk-grid-small">
            <div class="uk-width-1-1">
                <button class="uk-button uk-button-primary uk-border-rounded contact-button uk-align-right g-recaptcha" data-badge="inline" data-sitekey="<?php echo KPT::get_setting( 'recaptcha' ) -> sitekey; ?>" data-callback='onSubmit' data-action='submit'>
                    Reset Your Password <i uk-icon="icon: sign-in"></i>
                </button>
            </div>
        </div>
    </form>
    <script src="//www.google.com/recaptcha/api.js"></script>
    <script>
        function onSubmit( token ) {
            document.getElementById( "t-forgot" ).submit( );
        }
    </script>
    <?php

}

// pull in the footer
KPT::pull_footer( );
