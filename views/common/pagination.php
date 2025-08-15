<?php
/**
 * Pagination View Component
 * 
 * @param int $page Current page number
 * @param int $total_pages Total number of pages
 * @param int $per_page Items per page
 * @param string $base_url Base URL for pagination links (optional)
 * @param int $max_visible_links Maximum number of visible page links (default: 5)
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// set the extra querystring
$eq = '';

// now, if there's a search term
if( $search_term ) {
    $eq .= '&s=' . $search_term;
}

// get the sort info if there is any
if( $sort_column && $sort_direction ) {
    $eq .= sprintf( '&sort=%s&dir=%s', $sort_column, $sort_direction );
}
?>

<div class="uk-flex uk-flex-between uk-margin-bottom">
    <div class="uk-form-controls">
        Per Page: 
        <div class="uk-button-group">
            <a href="?per_page=25<?php echo $eq; ?>" class="uk-button uk-button-secondary <?php echo $per_page == 25 ? 'uk-active' : ''; ?> pg-button" style="margin:0 !important;">25</a>
            <a href="?per_page=50<?php echo $eq; ?>" class="uk-button uk-button-secondary <?php echo $per_page == 50 ? 'uk-active' : ''; ?> pg-button" style="margin:0 !important;">50</a>
            <a href="?per_page=100<?php echo $eq; ?>" class="uk-button uk-button-secondary <?php echo $per_page == 100 ? 'uk-active' : ''; ?> pg-button" style="margin:0 !important;">100</a>
            <a href="?per_page=250<?php echo $eq; ?>" class="uk-button uk-button-secondary <?php echo $per_page == 250 ? 'uk-active' : ''; ?> pg-button" style="margin:0 !important;">250</a>
        </div>
    </div>
    <div>
        <?php if ( $per_page !== 'all' && $total_pages > 1 ): ?>
            <ul class="uk-pagination uk-margin-remove-bottom">

                <?php if ( $show_first_last && $page > 1 ): ?>
                    <li>
                        <a href="?page=1&per_page=<?php echo $per_page; ?><?php echo $eq; ?>" title="First Page" class="pagination-icon">
                            <span uk-icon="icon: chevron-double-left"></span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($page > 1): ?>
                    <li>
                        <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?><?php echo $eq; ?>" title="Previous" class="pagination-icon">
                            <span uk-pagination-previous></span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                // Calculate range of pages to show
                $half = floor( $max_visible_links / 2 );
                $start = max( 1, $page - $half );
                $end = min( $total_pages, $start + $max_visible_links - 1 );
                
                // Adjust if we're at the end
                if ( $end - $start + 1 < $max_visible_links ) {
                    $start = max( 1, $end - $max_visible_links + 1 );
                }
                
                // Show first page + ellipsis if needed
                if ( $start > 1 ) {
                    echo '<li><a href="' . $base_url . '?page=1&per_page=' . $per_page . $eq . '">1</a></li>';
                    if ( $start > 2 ) {
                        echo '<li class="uk-disabled"><span>...</span></li>';
                    }
                }
                
                // Show page numbers
                for ( $i = $start; $i <= $end; $i++ ): ?>
                    <li class="<?php echo $i == $page ? 'uk-active' : ''; ?>" style="<?php echo $i == $page ? 'color:#fff !important;text-decoration:underline !important;' : ''; ?>">
                        <a href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?><?php echo $eq; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor;
                
                // Show last page + ellipsis if needed
                if ( $end < $total_pages ) {
                    if ( $end < $total_pages - 1 ) {
                        echo '<li class="uk-disabled"><span>...</span></li>';
                    }
                    echo '<li><a href="' . $base_url . '?page=' . $total_pages . '&per_page=' . $per_page . $eq . '">' . $total_pages . '</a></li>';
                }
                ?>

                <?php if ( $page < $total_pages ): ?>
                    <li>
                        <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?><?php echo $eq; ?>" title="Next" class="pagination-icon">
                            <span uk-pagination-next></span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ( $show_first_last && $page < $total_pages ): ?>
                    <li>
                        <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?><?php echo $eq; ?>" title="Last Page" class="pagination-icon">
                            <span uk-icon="icon: chevron-double-right"></span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>