<?php
/**
 * Page Links Component
 * 
 * @param int $page Current page number
 * @param string $base_url Base URL for pagination links (optional)
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// Ensure required parameters are set
if ( ! isset( $page, $base_url ) ) {
    return;
}

// Split the URL and detect stream type
// Split the URL and detect stream type
$urlParts = explode('/', $base_url);
$streamTypeKeywords = ['other', 'live', 'series', 'providers', 'filters'];
$matchedKeywords = array_intersect($urlParts, $streamTypeKeywords);
$stream_type = reset($matchedKeywords) ?: null;

// Define UI text and actions for each stream type
$streamConfigs = [
    'providers' => [
        'add_text' => 'Add a Provider',
        'extra'    => ''
    ],
    'filters' => [
        'add_text' => 'Add a Filter',
        'extra'    => ''
    ],
    'series' => [
        'add_text' => 'Add a Series Stream',
        'extra'    => <<<HTML
            <a href="#" class="uk-icon-link uk-padding-tiny move-to-live" uk-icon="tv" uk-tooltip="Move the Selected to Live Streams"></a>
            <a href="#" class="uk-icon-link uk-padding-tiny move-to-other" uk-icon="nut" uk-tooltip="Move the Selected to Other Streams"></a> | 
            <a href="#" class="uk-icon-link uk-padding-tiny activate-streams" uk-icon="crosshairs" uk-tooltip="De/Activate the Selected Streams"></a> | 
        HTML
    ],
    'live' => [
        'add_text' => 'Add a Live Stream',
        'extra'    => <<<HTML
            <a href="#" class="uk-icon-link uk-padding-tiny move-to-series" uk-icon="album" uk-tooltip="Move the Selected to Series Streams"></a>
            <a href="#" class="uk-icon-link uk-padding-tiny move-to-other" uk-icon="nut" uk-tooltip="Move the Selected to Other Streams"></a> | 
            <a href="#" class="uk-icon-link uk-padding-tiny activate-streams" uk-icon="crosshairs" uk-tooltip="De/Activate the Selected Streams"></a> | 
        HTML
    ],
    'other' => [
        'add_text' => 'Add an Other Stream',
        'extra'    => <<<HTML
            <a href="#" class="uk-icon-link uk-padding-tiny move-to-series" uk-icon="album" uk-tooltip="Move the Selected to Series Streams"></a>
            <a href="#" class="uk-icon-link uk-padding-tiny move-to-live" uk-icon="tv" uk-tooltip="Move the Selected to Live Streams"></a> | 
        HTML
    ]
];

// Set defaults (avoids undefined variable warnings)
$add_text = '';
$extra = '';

// Apply configuration if stream type exists
if ($stream_type && isset($streamConfigs[$stream_type])) {
    $add_text = $streamConfigs[$stream_type]['add_text'];
    $extra = trim($streamConfigs[$stream_type]['extra']);
}
?>
<div>
    <!-- Add -->
    <?php
        // show the extra link if there is one
        echo $extra;
    ?>
    <a href="#create-modal" class="uk-icon-link uk-padding-tiny" uk-toggle uk-icon="plus" uk-tooltip="<?php echo $add_text; ?>"></a>
    <a class="uk-icon-link uk-padding-tiny delete-selected" uk-icon="trash" uk-tooltip="Delete the Selected Items"></a>
</div>