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
 * Cache Warmer Interface
 */
interface KPT_Cache_Warmer_Interface {
    
    /**
     * Warm cache with data
     */
    public function warm(): int;
    
    /**
     * Get warmer name/identifier
     */
    public function getName(): string;
    
    /**
     * Check if warmer is applicable
     */
    public function isApplicable(): bool;
}