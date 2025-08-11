<?php
/**
 * KPT Cache - Redis Caching Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Cache_Redis' ) ) {

    trait KPT_Cache_Redis {

        // initial Redis settings
        private static $_redis_settings = [
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'KPTV_APP:',
            'read_timeout' => 0,
            'connect_timeout' => 2,
            'persistent' => true,
            'retry_attempts' => 2,
            'retry_delay' => 100,
        ];



    }

}
        