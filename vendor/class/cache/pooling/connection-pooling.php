<?php
/**
 * Connection Pool Manager for Database Backends
 * Manages reusable connections for Redis and Memcached
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! class_exists( 'KPT_Cache_ConnectionPool' ) ) {

    class KPT_Cache_ConnectionPool {
        
        private static array $pools = [];
        private static array $pool_configs = [
            'redis' => [
                'min_connections' => 2,
                'max_connections' => 10,
                'idle_timeout' => 300, // 5 minutes
                'connection_timeout' => 5,
                'retry_attempts' => 3
            ],
            'memcached' => [
                'min_connections' => 1,
                'max_connections' => 5,
                'idle_timeout' => 300,
                'connection_timeout' => 5,
                'retry_attempts' => 3
            ]
        ];
        
        /**
         * Get connection from pool
         */
        public static function getConnection(string $backend): mixed {
            if (!isset(self::$pools[$backend])) {
                self::initializePool($backend);
            }
            
            $pool = &self::$pools[$backend];
            
            // Try to get an active connection first
            foreach ($pool['active'] as $id => $conn_data) {
                if (self::isConnectionHealthy($backend, $conn_data['connection'])) {
                    $conn_data['last_used'] = time();
                    return $conn_data['connection'];
                } else {
                    // Remove dead connection
                    self::closeConnection($backend, $conn_data['connection']);
                    unset($pool['active'][$id]);
                }
            }
            
            // Try to get from idle pool
            if (!empty($pool['idle'])) {
                $conn_data = array_pop($pool['idle']);
                
                if (self::isConnectionHealthy($backend, $conn_data['connection'])) {
                    $id = uniqid();
                    $pool['active'][$id] = [
                        'connection' => $conn_data['connection'],
                        'created' => $conn_data['created'],
                        'last_used' => time()
                    ];
                    return $conn_data['connection'];
                } else {
                    self::closeConnection($backend, $conn_data['connection']);
                }
            }
            
            // Create new connection if under max limit
            if (count($pool['active']) < $pool['config']['max_connections']) {
                $connection = self::createConnection($backend);
                if ($connection) {
                    $id = uniqid();
                    $pool['active'][$id] = [
                        'connection' => $connection,
                        'created' => time(),
                        'last_used' => time()
                    ];
                    return $connection;
                }
            }
            
            return null;
        }
        
        /**
         * Return connection to pool
         */
        public static function returnConnection(string $backend, mixed $connection): void {
            if (!isset(self::$pools[$backend])) return;
            
            $pool = &self::$pools[$backend];
            
            // Find and move from active to idle
            foreach ($pool['active'] as $id => $conn_data) {
                if ($conn_data['connection'] === $connection) {
                    unset($pool['active'][$id]);
                    
                    // Only return to idle pool if under max idle connections
                    if (count($pool['idle']) < floor($pool['config']['max_connections'] / 2)) {
                        $pool['idle'][] = $conn_data;
                    } else {
                        self::closeConnection($backend, $connection);
                    }
                    break;
                }
            }
        }
        
        /**
         * Clean up idle connections
         */
        public static function cleanup(): void {
            foreach (self::$pools as $backend => &$pool) {
                $now = time();
                $timeout = $pool['config']['idle_timeout'];
                
                // Clean up idle connections
                $pool['idle'] = array_filter($pool['idle'], function($conn_data) use ($now, $timeout, $backend) {
                    if (($now - $conn_data['created']) > $timeout) {
                        self::closeConnection($backend, $conn_data['connection']);
                        return false;
                    }
                    return true;
                });
                
                // Check active connections
                foreach ($pool['active'] as $id => $conn_data) {
                    if (!self::isConnectionHealthy($backend, $conn_data['connection'])) {
                        self::closeConnection($backend, $conn_data['connection']);
                        unset($pool['active'][$id]);
                    }
                }
            }
        }
        
        /**
         * Close all connections and clear pools
         */
        public static function closeAll(): void {
            foreach (self::$pools as $backend => $pool) {
                foreach ($pool['active'] as $conn_data) {
                    self::closeConnection($backend, $conn_data['connection']);
                }
                foreach ($pool['idle'] as $conn_data) {
                    self::closeConnection($backend, $conn_data['connection']);
                }
            }
            self::$pools = [];
        }
        
        /**
         * Configure pool settings
         */
        public static function configurePool(string $backend, array $config): void {
            self::$pool_configs[$backend] = array_merge(
                self::$pool_configs[$backend] ?? [],
                $config
            );
        }
        
        /**
         * Get pool statistics
         */
        public static function getPoolStats(): array {
            $stats = [];
            
            foreach (self::$pools as $backend => $pool) {
                $stats[$backend] = [
                    'active_connections' => count($pool['active']),
                    'idle_connections' => count($pool['idle']),
                    'max_connections' => $pool['config']['max_connections'],
                    'total_created' => $pool['stats']['total_created'] ?? 0,
                    'total_reused' => $pool['stats']['total_reused'] ?? 0
                ];
            }
            
            return $stats;
        }
        
        // Private methods
        
        private static function initializePool(string $backend): void {
            self::$pools[$backend] = [
                'active' => [],
                'idle' => [],
                'config' => self::$pool_configs[$backend] ?? [],
                'stats' => ['total_created' => 0, 'total_reused' => 0]
            ];
            
            // Pre-create minimum connections
            $min_connections = self::$pools[$backend]['config']['min_connections'] ?? 1;
            for ($i = 0; $i < $min_connections; $i++) {
                $connection = self::createConnection($backend);
                if ($connection) {
                    self::$pools[$backend]['idle'][] = [
                        'connection' => $connection,
                        'created' => time(),
                        'last_used' => time()
                    ];
                }
            }
        }
        
        private static function createConnection(string $backend): mixed {
            $config = KPT_Cache_Config::get($backend);
            
            try {
                switch ($backend) {
                    case 'redis':
                        $redis = new Redis();
                        
                        $connected = $redis->pconnect(
                            $config['host'],
                            $config['port'],
                            $config['connect_timeout']
                        );
                        
                        if (!$connected) return null;
                        
                        $redis->select($config['database']);
                        
                        if (!empty($config['prefix'])) {
                            $redis->setOption(Redis::OPT_PREFIX, $config['prefix']);
                        }
                        
                        self::$pools[$backend]['stats']['total_created']++;
                        return $redis;
                        
                    case 'memcached':
                        $memcached = new Memcached($config['persistent'] ? 'kpt_pool' : null);
                        
                        // Only add servers if not using persistent connections or if no servers exist
                        if (!$config['persistent'] || count($memcached->getServerList()) === 0) {
                            $memcached->addServer($config['host'], $config['port']);
                        }
                        
                        $memcached->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
                        $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
                        
                        // Test connection
                        $stats = $memcached->getStats();
                        if (empty($stats)) return null;
                        
                        self::$pools[$backend]['stats']['total_created']++;
                        return $memcached;
                }
            } catch (Exception $e) {
                return null;
            }
            
            return null;
        }
        
        private static function isConnectionHealthy(string $backend, mixed $connection): bool {
            try {
                switch ($backend) {
                    case 'redis':
                        if (!$connection instanceof Redis) return false;
                        $result = $connection->ping();
                        return $result === true || $result === '+PONG';
                        
                    case 'memcached':
                        if (!$connection instanceof Memcached) return false;
                        $stats = $connection->getStats();
                        return !empty($stats);
                }
            } catch (Exception $e) {
                return false;
            }
            
            return false;
        }
        
        private static function closeConnection(string $backend, mixed $connection): void {
            try {
                switch ($backend) {
                    case 'redis':
                        if ($connection instanceof Redis) {
                            $connection->close();
                        }
                        break;
                        
                    case 'memcached':
                        if ($connection instanceof Memcached) {
                            $connection->quit();
                        }
                        break;
                }
            } catch (Exception $e) {
                // Ignore close errors
            }
        }
    }
}
