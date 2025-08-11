<?php
/**
 * KPT Cache - YAC Caching Trait
 * Yet Another Cache (YAC) extension implementation
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! trait_exists( 'KPT_Cache_YAC' ) ) {

    trait KPT_Cache_YAC {

        /**
         * Test if YAC cache is actually working
         */
        private static function testYacConnection(): bool {
            
            try {
                $config = KPT_Cache_Config::get('yac');
                $prefix = $config['prefix'] ?? 'KPTV_APP:';
                
                // Test with a simple store/fetch operation
                $test_key = $prefix . 'test_' . uniqid();
                $test_value = 'test_value_' . time();
                
                // Try to store and retrieve
                if (yac_add($test_key, $test_value, 60)) {
                    $retrieved = yac_get($test_key);
                    yac_delete($test_key); // Clean up
                    return $retrieved === $test_value;
                }
                
                return false;
                
            } catch (Exception $e) {
                self::$_last_error = "YAC test failed: " . $e->getMessage();
                return false;
            }
        }

        /**
         * Get item from YAC cache
         */
        private static function getFromYac(string $key): mixed {
            
            // If YAC is not loaded, just return false
            if (!extension_loaded('yac')) {
                return false;
            }
            
            try {
                $config = KPT_Cache_Config::get('yac');
                $prefix = $config['prefix'] ?? 'KPTV_APP:';
                
                // Setup the prefixed key
                $prefixed_key = $prefix . $key;
                
                // Fetch the value
                $value = yac_get($prefixed_key);
                
                // YAC returns false for non-existent keys
                return $value !== false ? $value : false;

            } catch (Exception $e) {
                self::$_last_error = "YAC get error: " . $e->getMessage();
            }
            
            return false;
        }

        /**
         * Set item to YAC cache
         */
        private static function setToYac(string $key, mixed $data, int $ttl): bool {
            
            if (!extension_loaded('yac')) {
                return false;
            }
            
            try {
                $config = KPT_Cache_Config::get('yac');
                $prefix = $config['prefix'] ?? 'KPTV_APP:';
                
                $prefixed_key = $prefix . $key;
                return yac_set($prefixed_key, $data, $ttl);
                
            } catch (Exception $e) {
                self::$_last_error = "YAC set error: " . $e->getMessage();
                return false;
            }
        }
    }
}