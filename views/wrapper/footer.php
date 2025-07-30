<?php
/**
 * footer.php
 * 
 * No direct access allowed!
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

?>
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
                <video id="the_streamer" class="uk-border-rounded" controls></video>
            </div>
        </div>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.min.js"></script>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.min.js"></script>
        <script type="text/javascript" src="/assets/js/custom.js?_=<?php echo time( ); ?>"></script>
        <script src="//instant.page/5.2.0" type="module" integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z"></script>
    </body>
</html>
