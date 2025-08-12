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

use KPT\KPT;

// pull in the header
KPT::pull_header( );

// check if we're already logged in
if( ! KPT_User::is_user_logged_in( ) ) {

    // message with redirect
    KPT::message_with_redirect( '/', 'danger', 'You don\'t belong there.' );

} else {

    ?>
    <h2 class="me">Change Your Password</h2>
    <form action="/users/changepass" method="POST" class="uk-form-stacked">
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: unlock"></span>
                <input class="uk-input" id="frmExistPassword" type="password" placeholder="Your Current Password" name="frmExistPassword" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: lock"></span>
                <input class="uk-input" id="frmNewPassword1" type="password" placeholder="New Password" name="frmNewPassword1" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-inline uk-width-1-1">
                <span class="uk-form-icon" uk-icon="icon: lock"></span>
                <input class="uk-input" id="frmNewPassword2" type="password" placeholder="New Password Again" name="frmNewPassword2" />
            </div>
        </div>
        <div class="uk-margin">
            <div class="uk-width-1-1">
                <button class="uk-button uk-button-primary uk-border-rounded contact-button uk-align-right" type="submit">
                    Change Your Password <i uk-icon="icon: cog"></i>
                </button>
            </div>
        </div>
    </form>
<?php

}

// pull in the footer
KPT::pull_footer( );
