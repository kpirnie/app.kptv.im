<?php

/**
 * KPT Cache Cleaner - Comprehensive Cache Management Utility
 *
 * A utility class for clearing and managing cache across all tiers with support
 * for CLI usage, selective clearing, and detailed reporting.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// Prevent multiple executions of this script
if (defined('KPT_CACHECLEANER_LOADED')) {
    return;
}
define('KPT_CACHECLEANER_LOADED', true);

// no direct access via web, but allow CLI
if (php_sapi_name() !== 'cli') {
    die('Direct Access is not allowed!');
}

// make sure the class doesn't exist
if (! class_exists('CacheCleaner')) {

    /**
     * Cache Cleaner - Comprehensive Cache Management Utility
     *
     * Provides methods for clearing cache data across all tiers with support
     * for CLI operations, selective clearing, and detailed reporting.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class CacheCleaner
    {
        /**
         * CLI entry point
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $args Command line arguments (without script name)
         * @return int Exit code
         */
        public static function cli(array $args = []): int
        {

            // hold our CLI arguments
            $args = self::parseArguments();

            // try to run out cleaning
            try {
                // if we have the clear_all
                if (isset($args['clear_all']) && $args['clear_all']) {
                    // clear all caches
                    Cache::clear();

                    // close the cache connections
                    Cache::close();

                    // debug logging
                    Logger::debug("Cleared All Available Caches", ['tiers' => CacheTierManager::getAvailableTiers()]);
                }

                // if we have the cleanup
                if (isset($args['cleanup']) && $args['cleanup']) {
                    // clear all caches
                    Cache::cleanup();

                    // debug logging
                    Logger::debug("Cleanup Expired Caches", ['tiers' => CacheTierManager::getAvailableTiers()]);
                }

                // if the clear tier argument is set
                if (isset($args['clear_tier'])) {
                    // hold our tiers, and the chosen one
                    $validTiers = CacheTierManager::getValidTiers();
                    $tier = $args['clear_tier'];

                    // if the argument is in the list of tiers
                    if (in_array($tier, $validTiers)) {
                        // clear the tiers cache
                        Cache::clearTier($tier);

                        // debug logging
                        Logger::debug("Cleared Cache Tier", [$tier]);
                    }
                }

            // whoopsie...
            } catch (\Exception $e) {
                // log the error
                Logger::error("Cache Clear Error", ['error' => $e -> getMessage()]);
            }

            return 0;
        }

        /**
         * Parse the arguments passed to the script
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Array of arguments passed
         */
        private static function parseArguments(): array
        {

            // setup the argv global and hold the options return
            global $argv;
            $options = [];

            // if we only have 1 argument (which is the script name)
            if (count($argv) > 1) {
                // loop over the arguments, but skip the first one
                foreach (array_slice($argv, 1) as $arg) {
                    // set the arguments to the return options
                    if ($arg === '--clear_all') {
                        $options['clear_all'] = true;
                    } elseif (strpos($arg, '--clear_tier=') === 0) {
                        $tier = substr($arg, strlen('--clear_tier='));
                        $options['clear_tier'] = $tier;
                    }
                }
            }

            // return the options
            return $options;
        }
    }

}

// CLI execution if called directly
if (php_sapi_name() === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    // Define KPT_PATH for CLI access to other KPT classes
    defined('KPT_PATH') || define('KPT_PATH', dirname(__DIR__, 3) . '/');

    // Try to include Composer autoloader
    require_once KPT_PATH . 'vendor/main.php';

    // clean the cache
    CacheCleaner::cli();
}
