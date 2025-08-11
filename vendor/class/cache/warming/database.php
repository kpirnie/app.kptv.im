<?php
/**
 * Cache Warming System
 * Provides various strategies for pre-loading cache data
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

/**
 * Database Query Cache Warmer
 */
class KPT_Cache_Warmer_Database implements KPT_Cache_Warmer_Interface {
    
    private array $queries;
    private ?PDO $pdo;
    private string $name;
    private int $default_ttl;
    
    public function __construct(array $queries, ?PDO $pdo = null, string $name = 'database', int $default_ttl = 3600) {
        $this->queries = $queries;
        $this->pdo = $pdo;
        $this->name = $name;
        $this->default_ttl = $default_ttl;
    }
    
    public function warm(): int {
        if (!$this->pdo) return 0;
        
        $warmed = 0;
        
        foreach ($this->queries as $config) {
            try {
                $sql = $config['sql'];
                $cache_key = $config['cache_key'];
                $ttl = $config['ttl'] ?? $this->default_ttl;
                $params = $config['params'] ?? [];
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (KPT_Cache::set($cache_key, $result, $ttl)) {
                    $warmed++;
                }
                
            } catch (PDOException $e) {
                // Log error but continue with other queries
                error_log("Cache warming query failed: " . $e->getMessage());
            }
        }
        
        return $warmed;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function isApplicable(): bool {
        return $this->pdo instanceof PDO && !empty($this->queries);
    }
}
