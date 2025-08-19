<?php
/**
 * streams-faq.php
 * 
 * FAQ page for stream management
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;

// pull in the header
KPT::pull_header( );

?>
<div class="uk-container">
    <h2 class="me uk-heading-divider">Stream Management FAQ</h2>

    <!-- Stream Basics Section -->
    <div class="uk-margin-large">
        <h3 class="uk-heading-bullet">Stream Basics</h3>
        
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What are the different types of streams?</a>
                <div class="uk-accordion-content">
                    <p>The KPTV Stream Manager organizes your content into three main categories:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Live Streams:</strong> Real-time TV channels and broadcasts</li>
                        <li><strong>Series Streams:</strong> TV shows, series, and episodic content</li>
                        <li><strong>Other Streams:</strong> Uncategorized or miscellaneous content that needs to be organized</li>
                    </ul>
                    <p class="uk-text-meta dark-version">You can move streams between categories as needed to keep your content organized.</p>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">What is the difference between active and inactive streams?</a>
                <div class="uk-accordion-content">
                    <p><strong>Active Streams:</strong> These are streams that are currently available and will be included in your exported playlists.</p>
                    <p><strong>Inactive Streams:</strong> These are streams that are temporarily disabled or not working. They won't appear in your exported playlists but remain in your library for future use.</p>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Tip:</strong> Use the active/inactive toggle to quickly enable or disable streams without deleting them permanently.</p>
                    </div>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">How do I play a stream to test it?</a>
                <div class="uk-accordion-content">
                    <p>You can test streams directly in your browser using the built-in player:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Navigate to any stream list (Live, Series, or Other)</li>
                        <li>Click the <span uk-icon="play"></span> play button next to the stream you want to test</li>
                        <li>The stream will open in a modal player that supports HLS (.m3u8) and MPEG-TS (.ts) formats</li>
                    </ol>
                    <p class="uk-text-meta dark-version">If a stream doesn't play in the browser, try copying the stream URL and opening it in VLC or another media player.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Provider Management Section -->
    <div class="uk-margin-large">
        <h3 class="uk-heading-bullet">Provider Management</h3>
        
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What are providers and how do I add them?</a>
                <div class="uk-accordion-content">
                    <p>Providers are your IPTV sources - the services that supply your stream content. The system supports two types:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>XC API:</strong> Xtream Codes API providers (most common)</li>
                        <li><strong>M3U:</strong> Direct M3U playlist URLs</li>
                    </ul>
                    <p><strong>To add a provider:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Go to "Your Streams" → "Your Providers"</li>
                        <li>Click the <span uk-icon="plus"></span> add button</li>
                        <li>Fill in the provider details (name, domain, credentials)</li>
                        <li>Set the stream type (MPEGTS or HLS)</li>
                        <li>Configure priority and filtering options</li>
                    </ol>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">What does provider priority mean?</a>
                <div class="uk-accordion-content">
                    <p>Provider priority determines the order of preference when you have multiple providers offering similar content.</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Lower numbers = Higher priority</strong> (Priority 1 is highest)</li>
                        <li>Streams from higher priority providers appear first in your lists</li>
                        <li>Use this to prioritize your most reliable providers</li>
                    </ul>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Example:</strong> If Provider A has priority 1 and Provider B has priority 5, streams from Provider A will be listed first.</p>
                    </div>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">How do I export provider-specific playlists?</a>
                <div class="uk-accordion-content">
                    <p>You can export playlists for individual providers:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Go to "Your Streams" → "Your Providers"</li>
                        <li>Find the provider you want to export</li>
                        <li>Click the <span uk-icon="tv"></span> icon for Live streams or <span uk-icon="album"></span> icon for Series streams</li>
                        <li>The playlist URL will be copied to your clipboard</li>
                    </ol>
                    <p class="uk-text-meta dark-version">Provider-specific playlists only include streams from that particular provider.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Content Filtering Section -->
    <div class="uk-margin-large">
        <h3 class="uk-heading-bullet">Content Filtering</h3>
        
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">What are filters and how do they work?</a>
                <div class="uk-accordion-content">
                    <p>Filters help you automatically organize and exclude unwanted content from your streams. There are several filter types:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Include Name (regex):</strong> Only include streams matching a pattern</li>
                        <li><strong>Exclude Name:</strong> Remove streams with specific names</li>
                        <li><strong>Exclude Name (regex):</strong> Remove streams matching a pattern</li>
                        <li><strong>Exclude Stream (regex):</strong> Filter by stream URL patterns</li>
                        <li><strong>Exclude Group (regex):</strong> Filter by group/category patterns</li>
                    </ul>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">How do I create effective filters?</a>
                <div class="uk-accordion-content">
                    <p><strong>Best Practices for Filtering:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Start with broad filters and refine them based on results</li>
                        <li>Use "Exclude Name" for simple text matching (e.g., "XXX", "Adult")</li>
                        <li>Use regex filters for complex patterns (e.g., ".*\b(word1|word2)\b.*")</li>
                        <li>Test your filters on a small subset first</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Example:</strong> To exclude adult content, create an "Exclude Name" filter with terms like "XXX", "Adult", "18+"</p>
                    </div>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">Can I disable filtering for specific providers?</a>
                <div class="uk-accordion-content">
                    <p>Yes! When editing a provider, you can toggle the "Filter Content" option:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Yes:</strong> All active filters will be applied to this provider's content</li>
                        <li><strong>No:</strong> This provider's content will bypass all filters</li>
                    </ul>
                    <p class="uk-text-meta dark-version">This is useful for trusted providers where you want all content to be available.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Stream Organization Section -->
    <div class="uk-margin-large">
        <h3 class="uk-heading-bullet">Stream Organization</h3>
        
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I move streams between categories?</a>
                <div class="uk-accordion-content">
                    <p>You can move streams individually or in bulk:</p>
                    <p><strong>Individual Stream:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Click the category icon next to any stream (e.g., <span uk-icon="tv"></span> for Live or <span uk-icon="album"></span> for Series)</li>
                    </ul>
                    <p><strong>Bulk Move:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Select multiple streams using the checkboxes</li>
                        <li>Click the appropriate move button in the toolbar</li>
                        <li>All selected streams will be moved to the target category</li>
                    </ol>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">How do I edit stream names and channel numbers?</a>
                <div class="uk-accordion-content">
                    <p>You can edit stream information in two ways:</p>
                    <p><strong>Quick Edit:</strong> Click directly on the stream name or channel number in the list to edit it inline.</p>
                    <p><strong>Full Edit:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Click the <span uk-icon="pencil"></span> edit button next to the stream</li>
                        <li>Modify any field in the edit modal</li>
                        <li>Click "Save" to apply changes</li>
                    </ol>
                    <div class="uk-alert-success dark-version" uk-alert>
                        <p><strong>Tip:</strong> Use descriptive names and logical channel numbers to make your streams easier to find.</p>
                    </div>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">What should I do with streams in the "Other" category?</a>
                <div class="uk-accordion-content">
                    <p>The "Other" category contains uncategorized content that needs to be organized:</p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Review the streams in the Other category</li>
                        <li>Move TV channels to "Live Streams"</li>
                        <li>Move TV shows and series to "Series Streams"</li>
                        <li>Delete any unwanted or broken streams</li>
                    </ol>
                    <p class="uk-text-meta dark-version">Keeping the Other category clean helps maintain an organized library.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Playlist Export Section -->
    <div class="uk-margin-large">
        <h3 class="uk-heading-bullet">Playlist Export</h3>
        
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">How do I export my streams as M3U playlists?</a>
                <div class="uk-accordion-content">
                    <p>You can export playlists in several ways:</p>
                    <p><strong>Full Category Playlists:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Navigate to "Your Streams" in the main menu</li>
                        <li>Click "Export the Playlist" for Live Streams or Series Streams</li>
                    </ul>
                    <p><strong>Provider-Specific Playlists:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Go to "Your Providers"</li>
                        <li>Use the export buttons for each provider</li>
                    </ul>
                    <div class="uk-alert-primary dark-version" uk-alert>
                        <p><strong>Note:</strong> Only active streams are included in exported playlists.</p>
                    </div>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">What's the difference between exported playlist types?</a>
                <div class="uk-accordion-content">
                    <p><strong>Full Playlists:</strong> Include all active streams from all providers in a category</p>
                    <p><strong>Provider-Specific Playlists:</strong> Include only streams from a single provider</p>
                    <p><strong>Use Cases:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Use full playlists for comprehensive channel lineups</li>
                        <li>Use provider-specific playlists to test individual providers</li>
                        <li>Share provider-specific playlists with others without exposing all your sources</li>
                    </ul>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">How do I use exported playlists in media players?</a>
                <div class="uk-accordion-content">
                    <p>Your exported M3U playlists work with most IPTV-compatible media players:</p>
                    <p><strong>Popular Players:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>VLC Media Player</li>
                        <li>Kodi</li>
                        <li>Perfect Player (Android)</li>
                        <li>GSE Smart IPTV</li>
                        <li>TiviMate (Android TV)</li>
                    </ul>
                    <p><strong>To use:</strong> Simply copy the playlist URL and add it to your preferred media player's playlist manager.</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Troubleshooting Section -->
    <div class="uk-margin-large">
        <h3 class="uk-heading-bullet">Troubleshooting</h3>
        
        <ul uk-accordion="multiple: false">
            <li>
                <a class="uk-accordion-title" href="#">Why won't my stream play in the browser?</a>
                <div class="uk-accordion-content">
                    <p>Several factors can prevent streams from playing in the browser:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li><strong>Format Compatibility:</strong> Some .ts streams may not play well in browsers</li>
                        <li><strong>CORS Issues:</strong> Some providers block cross-origin requests</li>
                        <li><strong>Stream Availability:</strong> The stream may be temporarily offline</li>
                    </ul>
                    <p><strong>Solutions:</strong></p>
                    <ol class="uk-list uk-list-decimal">
                        <li>Try copying the stream URL and opening it in VLC</li>
                        <li>Check if the stream works in other media players</li>
                        <li>Contact your provider if the stream consistently fails</li>
                    </ol>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">My exported playlist isn't working. What should I check?</a>
                <div class="uk-accordion-content">
                    <p><strong>Common Playlist Issues:</strong></p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Check that you're using the correct playlist URL</li>
                        <li>Ensure your streams are marked as "active"</li>
                        <li>Verify your provider credentials are still valid</li>
                        <li>Test individual streams to isolate problems</li>
                    </ul>
                    <div class="uk-alert-warning dark-version" uk-alert>
                        <p><strong>Remember:</strong> Playlist URLs are unique to your account and should not be shared publicly.</p>
                    </div>
                </div>
            </li>
            
            <li>
                <a class="uk-accordion-title" href="#">How do I report bugs or request features?</a>
                <div class="uk-accordion-content">
                    <p>For technical issues, feature requests, or bug reports:</p>
                    <ul class="uk-list uk-list-bullet">
                        <li>Visit our <a href="https://github.com/kpirnie/app.kptv.im/issues" target="_blank" class="uk-link">GitHub Issues</a> page</li>
                        <li>Search existing issues before creating a new one</li>
                        <li>Provide detailed information about the problem</li>
                        <li>Include steps to reproduce the issue if possible</li>
                    </ul>
                    <p class="uk-text-meta dark-version">Please note that support is provided on a best-effort basis.</p>
                </div>
            </li>
        </ul>
    </div>

</div>

<?php
// pull in the footer
KPT::pull_footer( );
