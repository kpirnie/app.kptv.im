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
 * Main Cache Warming Manager
 */
class KPT_Cache_Warmer {
    
    private array $warmers = [];
    private array $stats = [];
    private bool $async_enabled = false;
    
    /**
     * Add a warmer to the collection
     */
    public function addWarmer(KPT_Cache_Warmer_Interface $warmer): self {
        $this->warmers[$warmer->getName()] = $warmer;
        return $this;
    }
    
    /**
     * Remove a warmer
     */
    public function removeWarmer(string $name): self {
        unset($this->warmers[$name]);
        return $this;
    }
    
    /**
     * Warm cache using all applicable warmers
     */
    public function warmAll(bool $parallel = false): array {
        $results = [];
        
        if ($parallel && $this->async_enabled) {
            return $this->warmAllAsync();
        }
        
        foreach ($this->warmers as $name => $warmer) {
            if ($warmer->isApplicable()) {
                $start_time = microtime(true);
                $count = $warmer->warm();
                $duration = microtime(true) - $start_time;
                
                $results[$name] = [
                    'warmed_count' => $count,
                    'duration' => $duration,
                    'success' => $count > 0
                ];
                
                $this->updateStats($name, $count, $duration);
            }
        }
        
        return $results;
    }
    
    /**
     * Warm cache using specific warmer
     */
    public function warmWith(string $name): array {
        if (!isset($this->warmers[$name])) {
            return ['error' => "Warmer '{$name}' not found"];
        }
        
        $warmer = $this->warmers[$name];
        
        if (!$warmer->isApplicable()) {
            return ['error' => "Warmer '{$name}' is not applicable"];
        }
        
        $start_time = microtime(true);
        $count = $warmer->warm();
        $duration = microtime(true) - $start_time;
        
        $result = [
            'warmed_count' => $count,
            'duration' => $duration,
            'success' => $count > 0
        ];
        
        $this->updateStats($name, $count, $duration);
        
        return $result;
    }
    
    /**
     * Async warming (requires async support enabled)
     */
    public function warmAllAsync(): KPT_Cache_Promise {
        if (!$this->async_enabled) {
            return KPT_Cache_Promise::reject(new Exception('Async not enabled'));
        }
        
        $promises = [];
        
        foreach ($this->warmers as $name => $warmer) {
            if ($warmer->isApplicable()) {
                $promises[$name] = new KPT_Cache_Promise(function($resolve, $reject) use ($warmer, $name) {
                    try {
                        $start_time = microtime(true);
                        $count = $warmer->warm();
                        $duration = microtime(true) - $start_time;
                        
                        $result = [
                            'warmed_count' => $count,
                            'duration' => $duration,
                            'success' => $count > 0
                        ];
                        
                        $this->updateStats($name, $count, $duration);
                        $resolve($result);
                        
                    } catch (Exception $e) {
                        $reject($e);
                    }
                });
            }
        }
        
        return KPT_Cache_Promise::all($promises);
    }
    
    /**
     * Enable async warming
     */
    public function enableAsync(): self {
        $this->async_enabled = true;
        return $this;
    }
    
    /**
     * Get warming statistics
     */
    public function getStats(): array {
        return $this->stats;
    }
    
    /**
     * Reset statistics
     */
    public function resetStats(): self {
        $this->stats = [];
        return $this;
    }
    
    /**
     * Get list of warmers
     */
    public function getWarmers(): array {
        return array_keys($this->warmers);
    }
    
    /**
     * Create warmers from configuration array
     */
    public static function fromConfig(array $config): self {
        $warmer = new self();
        
        foreach ($config as $type => $settings) {
            switch ($type) {
                case 'database':
                    if (isset($settings['pdo'])) {
                        $warmer->addWarmer(new KPT_Cache_Warmer_Database(
                            $settings['queries'] ?? [],
                            $settings['pdo'],
                            $settings['name'] ?? 'database',
                            $settings['ttl'] ?? 3600
                        ));
                    }
                    break;
                    
                case 'file':
                    $warmer->addWarmer(new KPT_Cache_Warmer_File(
                        $settings['files'] ?? [],
                        $settings['name'] ?? 'file',
                        $settings['ttl'] ?? 3600
                    ));
                    break;
                    
            }
        }
        
        return $warmer;
    }
    
    // Private methods
    
    private function updateStats(string $name, int $count, float $duration): void {
        if (!isset($this->stats[$name])) {
            $this->stats[$name] = [
                'total_warmed' => 0,
                'total_runs' => 0,
                'total_duration' => 0,
                'average_duration' => 0,
                'last_run' => null
            ];
        }
        
        $this->stats[$name]['total_warmed'] += $count;
        $this->stats[$name]['total_runs']++;
        $this->stats[$name]['total_duration'] += $duration;
        $this->stats[$name]['average_duration'] = $this->stats[$name]['total_duration'] / $this->stats[$name]['total_runs'];
        $this->stats[$name]['last_run'] = time();
    }
}