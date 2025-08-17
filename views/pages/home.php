<?php
/**
 * views/home.php
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
use KPT\Cache;

// pull in the header
KPT::pull_header( );

?>
<h3 class="me">Welcome to my stream manager.</h3>
<p>Please understand that I built this primarily for me as practice to keep my PHP and MySQL coding skills up to snuff. I decided to make it publicly available in case anyone else feels they could use something similar.</p>
<p>Now, that that is out of the way, please understand that I can only minimally support this app, if you decide to use it, you agree that it is at your own discretion and that I am under no obligation to help you, fix your items, or fix this website if it seems broken to you.</p>
<p>You also understand that I do not host, nor have I ever hosted any kind of media for public consumption or use. Thus said, do not ask me to provide you with anything related to that.</p>
<p>I also make this statement that this tool is to be used to legitimate IPTV purposes, and data stored that violates this is beyond my control.</p>
<p>You can send suggestions through this <a href="https://kevp.us/contact" target="_blank">Contact Us</a> form, but please understand that I may not answer you.</p>
<?php

// pull in the footer
KPT::pull_footer( );
