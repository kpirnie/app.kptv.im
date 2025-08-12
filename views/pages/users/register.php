<?php
/**
 * user/register.php
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

use KPT\KPT;

// pull in the header
KPT::pull_header( );

// if the user is logged in
if( KPT_User::is_user_logged_in( ) ) {

    // message with redirect
    KPT::message_with_redirect( '/', 'danger', 'You don\'t belong there.  Don\'t worry, our support team has been notified.' );

} else {

    ?>
    <h2 class="me">Register For an Account</h2>
    <form action="/users/register" method="POST" class="uk-form-stacked" id="t-register">
        <div class="uk-width-1-1">
            <?php KPT::show_message(
                'info',
                '<ul class="uk-list uk-list-disc uk-margin-remove-top">
                    <li>* - Fields are required.</li>
                    <li>You will be sent a confirmation email.</li>
                    <li>You must use a complicated password.  It must be between 8 and 64 characters long consisting of upper and lowercase letters, numbers, and the following special characters: <code>!@#$%*</code></li>
                </ul>
                '
            ); ?>
        </div>
        <div uk-grid>
            <div class="uk-width-1-2@s">
                <div class="uk-margin">
                    <label for="frmFirstName">First Name *</label>
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="icon: info"></span>
                        <input value="<?php echo ( isset( $_POST['frmFirstName'] ) ) ? KPT::sanitize_string( $_POST['frmFirstName'] ) : ''; ?>" tabindex="1" class="uk-input uk-form-danger" id="frmFirstName" type="text" placeholder="* First Name" name="frmFirstName" />
                    </div>
                </div>
                <div class="uk-margin">
                    <label for="frmUsername">Username *</label>
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="icon: user"></span>
                        <input value="<?php echo ( isset( $_POST['frmUsername'] ) ) ? KPT::sanitize_string( $_POST['frmUsername'] ) : ''; ?>" tabindex="3" class="uk-input uk-form-danger" id="frmUsername" type="text" placeholder="* Username" name="frmUsername" />
                    </div>
                </div>
                <div class="uk-margin">
                <label for="frmPassword1">Password *</label>
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="icon: lock"></span>
                        <input tabindex="5" class="uk-input uk-form-danger" id="frmPassword1" type="password" placeholder="* Password" name="frmPassword1" />
                    </div>
                </div>
            </div>
            <div class="uk-width-1-2@s">
                <div class="uk-margin">
                <label for="frmLastName">Last Name *</label>
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="icon: info"></span>
                        <input value="<?php echo ( isset( $_POST['frmLastName'] ) ) ? KPT::sanitize_string( $_POST['frmLastName'] ) : ''; ?>" tabindex="2" class="uk-input uk-form-danger" id="frmLastName" type="text" placeholder="* Last Name" name="frmLastName" />
                    </div>
                </div>
                <div class="uk-margin">
                <label for="frmMainEmail">Main Email *</label>
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="icon: mail"></span>
                        <input value="<?php echo ( isset( $_POST['frmMainEmail'] ) ) ? KPT::sanitize_the_email( $_POST['frmMainEmail'] ) : ''; ?>" tabindex="3" class="uk-input uk-form-danger" id="frmMainEmail" type="text" placeholder="* Main Email" name="frmMainEmail" />
                    </div>
                </div>
                <div class="uk-margin">
                    <label for="frmPassword2">Password Again *</label>
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="icon: lock"></span>
                        <input tabindex="6" class="uk-input uk-form-danger" id="frmPassword2" type="password" placeholder="* Password Again" name="frmPassword2" />
                    </div>
                </div>
            </div>
            <div class="uk-width-1-1">
                <div class="uk-width-1-1">
                    <button class="uk-button uk-button-primary uk-border-rounded contact-button uk-align-right g-recaptcha" data-badge="inline" data-sitekey="<?php echo KPT::get_setting( 'recaptcha' ) -> sitekey; ?>" data-callback='onSubmit' data-action='submit'>
                        Register Now <i uk-icon="icon: cog"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>
    <script src="//www.google.com/recaptcha/api.js"></script>
    <script>
        function onSubmit( token ) {
            document.getElementById( "t-register" ).submit( );
        }
    </script>
<?php

}

// pull in the footer
KPT::pull_footer( );
