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

?>
<div class="uk-width-1-1">
    <div class="uk-flex uk-flex-between uk-margin-bottom">
        <!-- search -->
        <?php
            KPT::include_view( 'common/search', [
                'page' => $page,
                'base_url' => $base_url,
                'search_term' => $search_term,
            ] );
        ?>
        <!-- Links -->
        <?php
            KPT::include_view( 'common/links', [
                'page' => $page,
                'base_url' => $base_url,
                'search_term' => $search_term
            ] );
        ?>
    </div>
    <div>
        <!-- Pagination -->
        <?php
            KPT::include_view( 'common/pagination', [
                'page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'base_url' => $base_url,
                'max_visible_links' => $max_visible_links ?? 5,
                'show_first_last' => $show_first_last ?? false,
                'search_term' => $search_term
            ] );
        ?>
    </div>
</div>
