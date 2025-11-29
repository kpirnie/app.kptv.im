<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use RuntimeException;

class Config
{
    private static ?self $instance = null;

    private function __construct(
        public readonly string $dbserver,
        public readonly int $dbport,
        public readonly string $dbuser,
        public readonly string $dbpassword,
        public readonly string $dbschema,
        public readonly string $dbTblprefix
    ) {
    }

    public static function load(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $configPath = self::findConfig();

        try {
            $rawConfig = json_decode(file_get_contents($configPath), true, flags: JSON_THROW_ON_ERROR);

            $requiredKeys = ['dbserver', 'dbport', 'dbuser', 'dbpassword', 'dbschema', 'db_tblprefix'];
            $missing = array_diff($requiredKeys, array_keys($rawConfig));

            if (!empty($missing)) {
                throw new RuntimeException('Missing config keys: ' . implode(', ', $missing));
            }

            self::$instance = new self(
                dbserver: $rawConfig['dbserver'],
                dbport: (int) $rawConfig['dbport'],
                dbuser: $rawConfig['dbuser'],
                dbpassword: $rawConfig['dbpassword'],
                dbschema: $rawConfig['dbschema'],
                dbTblprefix: $rawConfig['db_tblprefix']
            );

            return self::$instance;
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON in config: {$e->getMessage()}", previous: $e);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to load config: {$e->getMessage()}", previous: $e);
        }
    }

    private static function findConfig(): string
    {
        // Search paths
        $searchPaths = [
            getcwd(),
            getcwd() . '/src',
            $_SERVER['HOME'] ?? '/root',
        ];

        // Also check parent directories from current working directory
        $current = getcwd();
        while ($current !== '/') {
            $searchPaths[] = $current;
            $current = dirname($current);
        }

        foreach ($searchPaths as $path) {
            $configPath = $path . '/.kptvconf';
            if (file_exists($configPath)) {
                return $configPath;
            }
        }

        throw new RuntimeException('Could not find .kptvconf in any parent directory or common locations');
    }
}
