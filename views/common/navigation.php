<?php
/**
 * List Navigation Component
 * 
 * @param int $page Current page number
 * @param string $base_url Base URL for pagination links (optional)
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;

// Ensure required parameters are set
if ( ! isset( $page, $total_pages, $per_page, $base_url ) ) {
    return;
}

// setup the data for the views
$the_data = [
    'page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'base_url' => $base_url,
    'max_visible_links' => $max_visible_links ?? 5,
    'show_first_last' => $show_first_last ?? false,
    'search_term' => $search_term,
    'sort_column' => $sort_column,
    'sort_direction' => $sort_direction,
];

?>
<div class="uk-width-1-1">
    <div class="uk-margin-bottom" uk-grid>
        <!-- search -->
        <div class="uk-width-1-1 uk-width-1-2@s">
            <?php
                KPT::include_view( 'common/search', $the_data );
            ?>
        </div>
        <!-- Links -->
        <div class="uk-width-1-1 uk-width-1-2@s uk-flex uk-flex-right@s uk-flex-center uk-flex-left@s">
            <?php
                KPT::include_view( 'common/links', $the_data );
            ?>
        </div>
    </div>
    <div class="uk-width-1-1">
        <!-- Pagination -->
        <?php
            KPT::include_view( 'common/pagination', $the_data );
        ?>
    </div>
</div>