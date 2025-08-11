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
 * File-based Cache Warmer
 */
class KPT_Cache_Warmer_File implements KPT_Cache_Warmer_Interface {
    
    private array $files;
    private string $name;
    private int $default_ttl;
    
    public function __construct(array $files, string $name = 'file', int $default_ttl = 3600) {
        $this->files = $files;
        $this->name = $name;
        $this->default_ttl = $default_ttl;
    }
    
    public function warm(): int {
        $warmed = 0;
        
        foreach ($this->files as $config) {
            try {
                $file_path = $config['file'];
                $cache_key = $config['cache_key'];
                $ttl = $config['ttl'] ?? $this->default_ttl;
                $parser = $config['parser'] ?? 'raw'; // raw, json, yaml, csv
                
                if (!file_exists($file_path) || !is_readable($file_path)) {
                    continue;
                }
                
                $content = file_get_contents($file_path);
                
                $data = match($parser) {
                    'json' => json_decode($content, true),
                    'csv' => str_getcsv($content),
                    'yaml' => function_exists('yaml_parse') ? yaml_parse($content) : $content,
                    'serialize' => unserialize($content),
                    default => $content
                };
                
                if (KPT_Cache::set($cache_key, $data, $ttl)) {
                    $warmed++;
                }
                
            } catch (Exception $e) {
                error_log("Cache warming file failed: " . $e->getMessage());
            }
        }
        
        return $warmed;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function isApplicable(): bool {
        return !empty($this->files);
    }
}
