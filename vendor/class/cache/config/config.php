<?php
/**
 * Enhanced Cache Configuration Manager
 * Centralizes all cache configuration and settings management
 * Supports global path and prefix settings that act as defaults
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

if ( ! class_exists( 'KPT_Cache_Config' ) ) {

    class KPT_Cache_Config {
        
        private static array $global_config = [
            'path' => null,
            'prefix' => 'KPTV_APP:',
        ];
        
        private static array $default_configs = [
            'redis' => [
                'host' => 'localhost',
                'port' => 6379,
                'database' => 0,
                'prefix' => null,
                'read_timeout' => 0,
                'connect_timeout' => 2,
                'persistent' => true,
                'retry_attempts' => 2,
                'retry_delay' => 100,
            ],
            'memcached' => [
                'host' => 'localhost',
                'port' => 11211,
                'prefix' => null,
                'persistent' => true,
                'retry_attempts' => 2,
                'retry_delay' => 100,
            ],
            'apcu' => [
                'prefix' => null,
                'ttl_default' => 3600,
            ],
            'yac' => [
                'prefix' => null,
                'ttl_default' => 3600,
            ],
            'opcache' => [
                'prefix' => null,
                'cleanup_interval' => 3600, 
                'path' => null, 
            ],
            'shmop' => [
                'prefix' => null,
                'segment_size' => 1048576,
                'base_key' => 0x12345000,
            ],
            'mmap' => [
                'prefix' => null, 
                'base_path' => null, 
                'file_size' => 1048576,
                'max_files' => 1000,
            ],
            'file' => [
                'path' => null, 
                'permissions' => 0755,
                'prefix' => null,
            ]
        ];
        
        private static array $current_configs = [];
        private static bool $initialized = false;
        
        /**
         * Initialize configuration with defaults
         */
        public static function initialize(): void {
            if (self::$initialized) return;
            
            self::$current_configs = self::$default_configs;
            
            // Set default global path if not already set
            if (self::$global_config['path'] === null) {
                self::$global_config['path'] = sys_get_temp_dir() . '/kpt_cache/';
            }
            
            self::$initialized = true;
        }
        
        /**
         * Set global cache path used as default for all backends
         */
        public static function setGlobalPath(string $path): bool {
            // Normalize the path (ensure it ends with a slash)
            $normalized_path = rtrim($path, '/') . '/';
            
            // Validate path is accessible
            if (!is_dir(dirname($normalized_path)) && !mkdir(dirname($normalized_path), 0755, true)) {
                return false;
            }
            
            self::$global_config['path'] = $normalized_path;
            return true;
        }
        
        /**
         * Set global prefix used as default for all backends
         */
        public static function setGlobalPrefix(string $prefix): void {
            // Ensure prefix ends with appropriate separator if not empty
            if (!empty($prefix) && !str_ends_with($prefix, ':') && !str_ends_with($prefix, '_')) {
                $prefix .= ':';
            }
            
            self::$global_config['prefix'] = $prefix;
        }
        
        /**
         * Get global cache path
         */
        public static function getGlobalPath(): ?string {
            self::initialize();
            return self::$global_config['path'];
        }
        
        /**
         * Get global prefix
         */
        public static function getGlobalPrefix(): string {
            self::initialize();
            return self::$global_config['prefix'];
        }
        
        /**
         * Get configuration for specific backend with global defaults applied
         */
        public static function get(string $backend): array {
            self::initialize();
            
            if (!isset(self::$current_configs[$backend])) {
                return [];
            }
            
            $config = self::$current_configs[$backend];
            
            // Apply global defaults where backend-specific values are null
            $config = self::applyGlobalDefaults($config, $backend);
            
            return $config;
        }
        
        /**
         * Set configuration for specific backend
         */
        public static function set(string $backend, array $config): bool {
            self::initialize();
            
            if (!isset(self::$default_configs[$backend])) {
                return false;
            }
            
            // Validate required fields (but allow null values for global fallback)
            if (!self::validateConfig($backend, $config)) {
                return false;
            }
            
            // Merge with defaults but preserve null values for global fallback
            self::$current_configs[$backend] = array_merge(
                self::$default_configs[$backend], 
                $config
            );
            
            return true;
        }
        
        /**
         * Get configuration with global settings explicitly shown
         */
        public static function getWithGlobals(string $backend): array {
            $config = self::get($backend);
            
            return [
                'config' => $config,
                'global_path' => self::$global_config['path'],
                'global_prefix' => self::$global_config['prefix'],
                'effective_path' => $config['path'] ?? $config['base_path'] ?? self::$global_config['path'],
                'effective_prefix' => $config['prefix'] ?? self::$global_config['prefix']
            ];
        }
        
        /**
         * Get all configurations with global defaults applied
         */
        public static function getAll(): array {
            self::initialize();
            
            $all_configs = [];
            
            foreach (array_keys(self::$current_configs) as $backend) {
                $all_configs[$backend] = self::get($backend);
            }
            
            return [
                'global' => self::$global_config,
                'backends' => $all_configs
            ];
        }
        
        /**
         * Reset to defaults
         */
        public static function reset(): void {
            self::$current_configs = self::$default_configs;
            self::$global_config = [
                'path' => sys_get_temp_dir() . '/kpt_cache/',
                'prefix' => 'KPTV_APP:',
            ];
        }
        
        /**
         * Reset global settings only
         */
        public static function resetGlobal(): void {
            self::$global_config = [
                'path' => sys_get_temp_dir() . '/kpt_cache/',
                'prefix' => 'KPTV_APP:',
            ];
        }
        
        /**
         * Apply global defaults to backend configuration
         */
        private static function applyGlobalDefaults(array $config, string $backend): array {
            // Apply global prefix if backend prefix is null
            if (!isset($config['prefix']) || $config['prefix'] === null) {
                $config['prefix'] = self::$global_config['prefix'];
            }
            
            // Apply global path based on backend type
            if ($backend === 'file') {
                if (!isset($config['path']) || $config['path'] === null) {
                    $config['path'] = self::$global_config['path'];
                }
            } elseif ($backend === 'mmap') {
                if (!isset($config['base_path']) || $config['base_path'] === null) {
                    $config['base_path'] = self::$global_config['path'];
                }
            } elseif ($backend === 'opcache') {
                if (!isset($config['path']) || $config['path'] === null) {
                    $config['path'] = self::$global_config['path'];
                }
            }
            
            return $config;
        }
        
        /**
         * Validate backend configuration
         */
        private static function validateConfig(string $backend, array $config): bool {
            $required_fields = match($backend) {
                'redis' => ['host', 'port'],
                'memcached' => ['host', 'port'], 
                'apcu', 'yac', 'opcache', 'shmop', 'mmap', 'file' => [],
                default => []
            };
            
            foreach ($required_fields as $field) {
                if (!isset($config[$field]) && 
                    !isset(self::$default_configs[$backend][$field]) && 
                    self::$default_configs[$backend][$field] !== null) {
                    return false;
                }
            }
            
            // Validate path if provided
            if (isset($config['path']) && $config['path'] !== null) {
                $dir = dirname($config['path']);
                if (!is_dir($dir) && !is_writable(dirname($dir))) {
                    return false;
                }
            }
            
            if (isset($config['base_path']) && $config['base_path'] !== null) {
                $dir = dirname($config['base_path']);
                if (!is_dir($dir) && !is_writable(dirname($dir))) {
                    return false;
                }
            }
            
            return true;
        }
        
        /**
         * Get backend-specific path (considering different path field names)
         */
        public static function getBackendPath(string $backend): ?string {
            $config = self::get($backend);
            
            return match($backend) {
                'file', 'opcache' => $config['path'] ?? null,
                'mmap' => $config['base_path'] ?? null,
                default => null
            };
        }
        
        /**
         * Set backend-specific path
         */
        public static function setBackendPath(string $backend, string $path): bool {
            self::initialize();
            
            if (!isset(self::$current_configs[$backend])) {
                return false;
            }
            
            $normalized_path = rtrim($path, '/') . '/';
            
            // Update the appropriate path field based on backend
            $path_field = match($backend) {
                'file', 'opcache' => 'path',
                'mmap' => 'base_path',
                default => null
            };
            
            if ($path_field === null) {
                return false;
            }
            
            self::$current_configs[$backend][$path_field] = $normalized_path;
            return true;
        }
        
        /**
         * Export configuration (for debugging/backup)
         */
        public static function export(): array {
            return [
                'global' => self::$global_config,
                'defaults' => self::$default_configs,
                'current' => self::$current_configs,
                'initialized' => self::$initialized,
                'effective_configs' => self::getAll()
            ];
        }
        
        /**
         * Import configuration from backup
         */
        public static function import(array $config_data): bool {
            if (!isset($config_data['global']) || !isset($config_data['current'])) {
                return false;
            }
            
            try {
                self::$global_config = $config_data['global'];
                self::$current_configs = $config_data['current'];
                self::$initialized = $config_data['initialized'] ?? true;
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        
        /**
         * Get configuration summary for debugging
         */
        public static function getSummary(): array {
            self::initialize();
            
            $summary = [
                'global_settings' => self::$global_config,
                'backends_using_global_prefix' => [],
                'backends_using_global_path' => [],
                'backends_with_custom_prefix' => [],
                'backends_with_custom_path' => [],
            ];
            
            foreach (array_keys(self::$current_configs) as $backend) {
                $raw_config = self::$current_configs[$backend];
                
                // Check prefix usage
                if (!isset($raw_config['prefix']) || $raw_config['prefix'] === null) {
                    $summary['backends_using_global_prefix'][] = $backend;
                } else {
                    $summary['backends_with_custom_prefix'][] = $backend;
                }
                
                // Check path usage
                $path_field = match($backend) {
                    'file', 'opcache' => 'path',
                    'mmap' => 'base_path',
                    default => null
                };
                
                if ($path_field && (!isset($raw_config[$path_field]) || $raw_config[$path_field] === null)) {
                    $summary['backends_using_global_path'][] = $backend;
                } elseif ($path_field) {
                    $summary['backends_with_custom_path'][] = $backend;
                }
            }
            
            return $summary;
        }
        
        /**
         * Validate global configuration
         */
        public static function validateGlobal(): array {
            $issues = [];
            
            // Check global path
            if (self::$global_config['path']) {
                $path = self::$global_config['path'];
                $parent_dir = dirname(rtrim($path, '/'));
                
                if (!is_dir($parent_dir)) {
                    $issues[] = "Global path parent directory does not exist: {$parent_dir}";
                } elseif (!is_writable($parent_dir)) {
                    $issues[] = "Global path parent directory is not writable: {$parent_dir}";
                }
                
                if (is_dir($path) && !is_writable($path)) {
                    $issues[] = "Global path exists but is not writable: {$path}";
                }
            } else {
                $issues[] = "Global path is not set";
            }
            
            // Check global prefix
            if (empty(self::$global_config['prefix'])) {
                $issues[] = "Global prefix is empty (this may cause key collisions)";
            }
            
            return [
                'valid' => empty($issues),
                'issues' => $issues,
                'global_config' => self::$global_config
            ];
        }
    }
}
