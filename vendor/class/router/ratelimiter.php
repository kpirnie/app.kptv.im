<?php
/**
 * KPT Router - Rate Limiting Trait
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the trait doesn't exist first
if( ! trait_exists( 'KPT_Router_RateLimitingTrait' ) ) {

    trait KPT_Router_RateLimitingTrait {
        
        private array $rateLimits = [
            'global' => [
                'limit' => 100,
                'window' => 60,
                'storage' => 'file'
            ]
        ];
        private ?Redis $redis = null;
        private string $rateLimitPath = '/tmp/kpt_rate_limits';
        private bool $rateLimitingEnabled = false;

        /**
         * Initialize Redis-based rate limiting
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param array $config Configuration array
         * @return bool True if initialization succeeded
         */
        public function initRedisRateLimiting(array $config = ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.0, 'password' => null]): bool {
            try {
                $this->redis = new Redis();
                $connected = $this->redis->connect(
                    $config['host'],
                    $config['port'],
                    $config['timeout']
                );

                $this->redis->select(1);
                $this->redis->setOption(Redis::OPT_PREFIX, 'KPTV_RL:');

                if (!$connected) {
                    throw new RuntimeException('Failed to connect to Redis');
                }

                if (!empty($config['password'])) {
                    $this->redis->auth($config['password']);
                }

                $this->redis->ping();
                $this->rateLimits['global']['storage'] = 'redis';
                $this->rateLimitingEnabled = true;
                return true;
            } catch (Throwable $e) {
                error_log('Redis connection failed: ' . $e->getMessage());
                $this->rateLimitingEnabled = false;
                return false;
            }
        }

        /**
         * Enable file-based rate limiting
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function enableFileRateLimiting(): void {
            $this->rateLimits['global']['storage'] = 'file';
            $this->rateLimitingEnabled = true;
        }

        /**
         * Disable rate limiting
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function disableRateLimiting(): void {
            $this->rateLimitingEnabled = false;
        }

        /**
         * Apply rate limiting to the current request
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @throws RuntimeException When rate limit is exceeded
         */
        private function applyRateLimiting(): void {
            $clientIp = KPT::get_user_ip();
            $cacheKey = 'rate_limit_' . md5($clientIp);

            $limit = $this->rateLimits['global']['limit'];
            $window = $this->rateLimits['global']['window'];
            $storageType = $this->rateLimits['global']['storage'];

            try {
                if ($storageType === 'redis' && $this->redis !== null) {
                    $current = $this->handleRedisRateLimit($cacheKey, $limit, $window);
                } else {
                    $current = $this->handleFileRateLimit($cacheKey, $limit, $window);
                }

                if ($current >= $limit) {
                    header('Retry-After: ' . $window);
                    throw new RuntimeException('Rate limit exceeded', 429);
                }

                header('X-RateLimit-Limit: ' . $limit);
                header('X-RateLimit-Remaining: ' . max(0, $limit - $current - 1));
                header('X-RateLimit-Reset: ' . (time() + $window));

            } catch (Exception $e) {
                error_log('Rate limiting error: ' . $e->getMessage());
                if ($this->rateLimits['global']['strict_mode'] ?? false) {
                    throw new RuntimeException('Rate limit service unavailable', 503);
                }
            }
        }

        /**
         * Handle Redis-based rate limiting
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The rate limit key
         * @param int $limit Maximum allowed requests
         * @param int $window Time window in seconds
         * @return int Current request count
         */
        private function handleRedisRateLimit(string $key, int $limit, int $window): int {
            $current = $this->redis->get($key);
            
            if ($current !== false) {
                if ((int)$current >= $limit) {
                    return (int)$current;
                }

                $this->redis->incr($key);
                return (int)$current + 1;
            }

            $this->redis->setex($key, $window, 1);
            return 1;
        }

        /**
         * Handle file-based rate limiting
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string $key The rate limit key
         * @param int $limit Maximum allowed requests
         * @param int $window Time window in seconds
         * @return int Current request count
         */
        private function handleFileRateLimit(string $key, int $limit, int $window): int {
            $file = $this->rateLimitPath . '/' . $key;
            $now = time();
            
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);

                if ($data['expires'] > $now) {
                    $current = $data['count'] + 1;
                    file_put_contents($file, json_encode([
                        'count' => $current,
                        'expires' => $data['expires']
                    ]), LOCK_EX);

                    return $current;
                }
            }
            
            file_put_contents($file, json_encode([
                'count' => 1,
                'expires' => $now + $window
            ]), LOCK_EX);
            
            return 1;
        }
    }

}
