<?php
/**
 * KPT Cache - MMAP Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_MMAP' ) ) {

    trait KPT_Cache_MMAP {

        // mmap settings
        private static $_mmap_settings = [
            'prefix' => 'KPTV_APP:',
            'base_path' => null, // Will use temp dir if null
            'file_size' => 1048576, // 1MB default file size
            'max_files' => 1000, // Maximum number of mmap files
        ];

    }

}
        