<?php
/**
 * footer.php
 * 
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */
use KPT\Router;
// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );
?>
                            <?php if( '/terms-of-use' != Router::getCurrentRoute( ) -> path ) { ?>
                                <div class="uk-margin-large">
                                    <div class="uk-alert-primary dark-or-light uk-padding" uk-alert>
                                        <hr class="uk-divider-icon dark-version" />
                                        <h3 class="me uk-heading-bullet uk-margin-remove-top">Important Legal Notice</h3>
                                        <p>This platform is intended for legitimate IPTV management purposes only. Users are responsible for ensuring they have proper legal authorization for any content, streams, or media they manage through this service. We do not host, store, or distribute any media content - this is strictly an organizational tool for legally obtained IPTV subscriptions.</p>
                                        <p>By using this service, you agree to use it responsibly and in accordance with all applicable local, national, and international laws. Any content that violates copyright or licensing agreements is strictly prohibited. Account privileges may be revoked immediately for misuse, unauthorized content management, or violation of these terms.</p>
                                        <p class="uk-text-small uk-margin-remove-bottom uk-text-right">For support or legal concerns, visit our <a href="https://github.com/kpirnie/app.kptv.im/issues" target="_blank" class="uk-link">GitHub Issues page</a>.</p>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="uk-width-1-4@m uk-visible@m">
                            <?php

                                // include our sidebar
                                include KPT_PATH . 'views/wrapper/sidebar.php';
                            ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        <footer>
            <div class="uk-section uk-section-primary uk-padding uk-padding-remove-horizontal uk-margin-medium-top kp-footer">
                <div class="uk-container">
                    <div class="copyright-bar uk-text-center">
                        <p class="in-copyright-text uk-text-small">
                            Copyright &copy; <a href="https://kevinpirnie.com/" target="_blank">Kevin C. Pirnie</a> <?php echo date( 'Y' ); ?>, All Rights Reserved.<br />
                        </p>
                    </div>
                </div>
            </div>
            <div class="">
                <a href="#" class="in-totop" data-uk-scroll><i class="dark-or-light" uk-icon="icon: chevron-up;"></i></a>
            </div>
        </footer>

        <div id="vid_modal" class="uk-flex-top vid-modal" uk-modal>
            <div class="uk-modal-dialog uk-modal-body uk-margin-auto-vertical uk-width-auto">
                <button class="uk-modal-close-outside vid-closer" type="button" uk-close></button>
                <video id="the_streamer" class="video-js vjs-default-skin uk-border-rounded" controls preload="auto" width="800" height="450" data-setup="{}">
                    <p class="vjs-no-js">
                        To view this video please enable JavaScript, and consider upgrading to a web browser that
                        <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                    </p>
                </video>
            </div>
        </div>
        
        <script type="text/javascript" src="//vjs.zencdn.net/8.6.1/video.min.js"></script>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/hls.js@latest"></script>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/mpegts.js@latest"></script>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.min.js"></script>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.min.js"></script>
        <script type="text/javascript" src="/assets/js/video.js?_=<?php echo time( ); ?>"></script>
        <script type="text/javascript" src="/assets/js/custom.js?_=<?php echo time( ); ?>"></script>
        <script src="//instant.page/5.2.0" type="module" integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z"></script>
    </body>
</html>
