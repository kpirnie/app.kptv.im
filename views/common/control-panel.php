<?php
/**
 * Control Panel Component
 * 
 * @param DataTables $dt The datatable class
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

?>
<div class="bordertop-borderbottom uk-margin-bottom">
    <div class="uk-width-1-1 uk-grid-collapse uk-margin-bottom" uk-grid>
        <div class="uk-width-1-1 uk-width-1-2@m uk-grid-collapse padleft uk-text-center" uk-grid>
            <?php echo $dt -> renderSearchFormComponent( ); ?>
        </div>
        <div class="uk-width-1-1 uk-width-1-2@m uk-flex uk-flex-center uk-flex-right@m uk-padding-remove-right uk-margin-top-tiny">
            <?php echo $dt -> renderBulkActionsComponent( ); ?>
        </div>
    </div>
    <div class="uk-width-1-1 uk-grid-collapse" uk-grid>
        <div class="uk-width-1-1 uk-width-1-2@m padleft uk-text-center uk-text-left@m">
            <div class="uk-margin-top"><?php echo $dt -> renderPageSizeSelectorComponent( true ); ?></div>
        </div>
        <div class="uk-width-1-1 uk-width-1-2@m uk-flex uk-flex-center uk-flex-right@m uk-text-center uk-padding-remove-right">
            <?php echo $dt -> renderPaginationComponent( ); ?>
        </div>
    </div>
</div>