<?php
/**
 * KPT Cache - YAC Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_YAC' ) ) {

    trait KPT_Cache_YAC {
        
        // Yac settings
        private static $_yac_settings = [
            'prefix' => 'KPTV_APP:',
            'ttl_default' => 3600,
        ];

    }

}
        