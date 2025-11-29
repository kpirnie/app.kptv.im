<?php

declare(strict_types=1);

// Ensure CLI-only execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Set up the application path
$appPath = dirname(__DIR__);

// Define KPT_PATH for the main application
if (!defined('KPT_PATH')) {
    define('KPT_PATH', $appPath . '/');
}

// Include the main application's autoloader
require_once $appPath . '/vendor/autoload.php';

// Disable caching for CLI operations
use KPT\Cache;
Cache::configure([
    'enabled' => false,
    'path' => KPT_PATH . '.cache/',
    'prefix' => 'cli_sync_',
    'allowed_backends' => ['array'],
]);

// Use the main app's KPT class for configuration
use KPT\KPT;
use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\ProviderManager;
use Kptv\IptvSync\SyncEngine;
use Kptv\IptvSync\MissingChecker;
use Kptv\IptvSync\FixupEngine;

class IptvSyncApp
{
    private KpDb $db;
    private ProviderManager $providerManager;
    private SyncEngine $syncEngine;
    private MissingChecker $missingChecker;
    private FixupEngine $fixupEngine;

    public function __construct(array $ignoreFields = [], bool $debug = false)
    {
        // Get database configuration from main app config file directly
        // to avoid any caching issues
        $configPath = KPT_PATH . 'assets/config.json';
        
        if (!file_exists($configPath)) {
            throw new RuntimeException('Configuration file not found: ' . $configPath);
        }

        $config = json_decode(file_get_contents($configPath));
        
        if (!$config || !isset($config->database)) {
            throw new RuntimeException('Database configuration not found in application config');
        }

        $dbConfig = $config->database;

        $this->db = new KpDb(
            host: $dbConfig->server ?? 'localhost',
            port: (int) ($dbConfig->port ?? 3306),
            database: $dbConfig->schema ?? '',
            user: $dbConfig->username ?? '',
            password: $dbConfig->password ?? '',
            table_prefix: $dbConfig->tbl_prefix ?? 'kptv_',
            pool_size: 10,
            chunk_size: 1000
        );

        $this->providerManager = new ProviderManager($this->db);
        $this->syncEngine = new SyncEngine($this->db, $ignoreFields);
        $this->missingChecker = new MissingChecker($this->db);
        $this->fixupEngine = new FixupEngine($this->db);
    }

    public function runSync(?int $userId = null, ?int $providerId = null): void
    {
        $providers = $this->providerManager->getProviders($userId, $providerId);

        if (empty($providers)) {
            echo "No providers found\n";
            return;
        }

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($providers as $provider) {
            try {
                echo "Syncing provider {$provider['id']} - {$provider['sp_name']}\n";
                $count = $this->syncEngine->syncProvider($provider);
                $totalSynced += $count;

                $this->providerManager->updateLastSynced($provider['id']);

                unset($count);
            } catch (\Exception $e) {
                $totalErrors++;
                echo "Error syncing provider {$provider['id']}: {$e->getMessage()}\n";
            }

            // Force garbage collection at the end of each iteration
            gc_collect_cycles();
        }

        echo str_repeat('=', 60) . "\n";
        echo "SYNC COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Providers processed: " . count($providers) . "\n";
        echo "Streams synced: {$totalSynced}\n";
        echo "Errors: {$totalErrors}\n";
        echo str_repeat('=', 60) . "\n\n";
    }

    public function runTestMissing(?int $userId = null, ?int $providerId = null): void
    {
        $providers = $this->providerManager->getProviders($userId, $providerId);

        if (empty($providers)) {
            echo "No providers found\n";
            return;
        }

        $totalMissing = 0;

        foreach ($providers as $provider) {
            try {
                echo "Checking provider {$provider['id']}\n";
                $missing = $this->missingChecker->checkProvider($provider);
                $totalMissing += count($missing);
            } catch (\Exception $e) {
                echo "Error checking provider {$provider['id']}: {$e->getMessage()}\n";
            }
        }

        echo str_repeat('=', 60) . "\n";
        echo "MISSING CHECK COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Providers checked: " . count($providers) . "\n";
        echo "Missing streams: {$totalMissing}\n";
        echo str_repeat('=', 60) . "\n\n";
    }

    public function runFixup(?int $userId = null, ?int $providerId = null): void
    {
        $providers = $this->providerManager->getProviders($userId, $providerId);

        if (empty($providers)) {
            echo "No providers found\n";
            return;
        }

        // Call stored procedure
        try {
            $this->db->call_proc('UpdateStreamMetadata', fetch: false);
        } catch (\Exception $e) {
            echo "Error calling UpdateStreamMetadata: {$e->getMessage()}\n";
        }

        echo str_repeat('=', 60) . "\n";
        echo "FIXUP COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Providers processed: " . count($providers) . "\n";
        echo str_repeat('=', 60) . "\n\n";
    }
}


function printHelp(): void
{
    echo <<<HELP
IPTV Provider Sync - PHP 8.4

Usage: php kptv-sync.php <action> [options]

Actions:
  sync          Sync streams from providers
  testmissing   Check for missing streams
  fixup         Run metadata fixup

Options:
  --user-id <id>        Filter by user ID
  --provider-id <id>    Filter by provider ID
  --debug               Enable debug logging
  --ignore <fields>     Fields to ignore during sync (comma-separated)
                        Available: tvg_id, logo, tvg_group
  --help                Show this help

Examples:
  php kptv-sync.php sync
  php kptv-sync.php sync --user-id 1
  php kptv-sync.php sync --provider-id 32
  php kptv-sync.php sync --debug
  php kptv-sync.php sync --ignore tvg_id,logo

HELP;
}

// Main execution
try {
    // Manually parse arguments to support options anywhere in the command line
    $action = null;
    $options = [];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help') {
            $options['help'] = true;
        } elseif ($arg === '--debug') {
            $options['debug'] = true;
        } elseif ($arg === '--user-id' && isset($argv[$i + 1])) {
            $options['user-id'] = $argv[++$i];
        } elseif ($arg === '--provider-id' && isset($argv[$i + 1])) {
            $options['provider-id'] = $argv[++$i];
        } elseif ($arg === '--ignore' && isset($argv[$i + 1])) {
            $options['ignore'] = $argv[++$i];
        } elseif ($arg[0] !== '-' && $action === null) {
            $action = $arg;
        }
    }

    if (isset($options['help']) || $action === null) {
        printHelp();
        exit(0);
    }

    $validActions = ['sync', 'testmissing', 'fixup'];
    if (!in_array($action, $validActions, true)) {
        echo "Error: Invalid action '{$action}'\n\n";
        printHelp();
        exit(1);
    }

    $debug = isset($options['debug']);
    $userId = isset($options['user-id']) ? (int) $options['user-id'] : null;
    $providerId = isset($options['provider-id']) ? (int) $options['provider-id'] : null;

    // Process ignore fields
    $ignoreFields = [];
    if (isset($options['ignore'])) {
        $ignoreFields = array_map('trim', explode(',', $options['ignore']));
        $validIgnoreFields = ['tvg_id', 'logo', 'tvg_group'];
        $invalidFields = array_diff($ignoreFields, $validIgnoreFields);
        if (!empty($invalidFields)) {
            echo "Error: Invalid ignore fields: " . implode(', ', $invalidFields) . "\n";
            exit(1);
        }
    }

    if (!empty($ignoreFields)) {
        echo "Ignoring fields during sync: " . implode(', ', $ignoreFields) . "\n";
    }

    $app = new IptvSyncApp($ignoreFields, $debug);

    match ($action) {
        'sync' => $app->runSync($userId, $providerId),
        'testmissing' => $app->runTestMissing($userId, $providerId),
        'fixup' => $app->runFixup($userId, $providerId),
    };

} catch (\Exception $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    if (isset($options['debug']) && $options['debug']) {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}