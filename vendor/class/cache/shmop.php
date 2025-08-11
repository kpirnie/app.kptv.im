<?php
/**
 * KPT Cache - SHMOP Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_SHMOP' ) ) {

    trait KPT_Cache_SHMOP {

        // shmop settings
        private static $_shmop_settings = [
            'prefix' => 'KPTV_APP:',
            'segment_size' => 1048576, // 1MB default segment size
            'base_key' => 0x12345000, // Base key for shared memory segments
        ];
        
    }

}
        