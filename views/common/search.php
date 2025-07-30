<?php
/**
 * Search Form Component
 * 
 * @param int $page Current page number
 * @param string $base_url Base URL for pagination links (optional)
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

?>
<div>
    <form class="uk-search uk-search-default">
        <input class="uk-search-input" type="search" placeholder="Search" aria-label="Search" name="s" value="<?php echo $search_term; ?>">
        <span class="uk-search-icon-flip" uk-search-icon></span>
    </form>
    <a href="<?php echo parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>" class="uk-button uk-button-default uk-reset-link" uk-tooltip="Reset All">
        <span uk-icon="refresh" class="uk-reset-icon"></span>
    </a>
</div>