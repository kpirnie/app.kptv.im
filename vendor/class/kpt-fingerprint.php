<?php

/**
 * Generates a consistent unique fingerprint for users across all application pages
 * 
 * Creates a persistent identifier based solely on user characteristics (not request-specific data)
 * that remains the same throughout the user's browsing session.
 */
class KPT_Fingerprint
{
    /**
     * Application-specific salt for hashing fingerprint components
     * 
     * @var string
     */
    private string $salt;
    
    /**
     * @param string $applicationSecret Unique secret key for your application to salt hashes
     */
    public function __construct( string $applicationSecret )
    {
        $this -> salt = $applicationSecret;
    }
    
    /**
     * Generate a consistent user fingerprint hash that won't change between pages
     * 
     * @return string SHA256 hash prefixed with 'user_' representing this visitor
     */
    public function getFingerprint( ): string
    {
        $components = [
            'network' => $this -> getNetworkCharacteristics( ),
            'client' => $this -> getClientCharacteristics( ),
        ];
        
        return 'user_' . hash( 'sha256', $this -> salt . json_encode( $components ) );
    }
    
    /**
     * Extract network-level identification characteristics
     */
    protected function getNetworkCharacteristics(): array
    {
        $ip = KPT::get_user_ip( );
        
        return [
            'ip' => $ip,
            'ip_version' => strpos($ip, ':') !== false ? 6 : 4,
            'network_segment' => $this->getNetworkSegment($ip)
        ];
    }
    
    /**
     * Extract client software and capability characteristics
     */
    protected function getClientCharacteristics(): array
    {
        $ua = KPT::get_user_agent( ) ?? '';
        
        return [
            'device_type' => $this->getDeviceType($ua),
            'languages' => explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
            'encodings' => explode(',', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''),
        ];
    }
    
    /**
     * Calculate network segment from IP address
     */
    protected function getNetworkSegment(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0].'.'.$parts[1]; // Class B segment
        }
        
        $clean = str_replace(':', '', $ip);
        return substr($clean, 0, 16);
    }
    
    /**
     * Categorize device type from User-Agent string
     */
    protected function getDeviceType(string $ua): string
    {
        if (preg_match('/(mobile|android|iphone|ipad)/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/(bot|crawl|spider)/i', $ua)) {
            return 'bot';
        }
        return 'desktop';
    }

}