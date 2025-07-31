<?php
/**
 * KPT_Fingerprint
 * 
 * This class generates a unique id for the user
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Tasks
 * 
 */
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// if the class does not exist in userspace yet
if( ! class_exists( 'KPT_Fingerprint' ) ) {

    /**
     * KPT_Fingerprint
     * 
     * This class generates a unique id for the user
     * 
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Tasks
     * 
     */
    class KPT_Fingerprint {

        // hold the local salt string
        private string $salt;
        
        // fire up the class with our application's salt
        public function __construct( string $applicationSecret ) {
            $this -> salt = $applicationSecret;
        }
        
        /**
         * Generate a consistent user fingerprint hash that won't change between pages
         * 
         * @return string SHA256 hash prefixed with 'user_' representing this visitor
         */
        public function getFingerprint( ) : string {
            $components = [
                'network' => $this -> getNetworkCharacteristics( ),
                'client' => $this -> getClientCharacteristics( ),
            ];
            
            // return the identifier
            return 'user_' . hash( 'sha256', $this -> salt . json_encode( $components ) );
        }
        
        /**
         * Extract network-level identification characteristics
         * 
         * @return array Returns an array of network characteristics
         */
        protected function getNetworkCharacteristics( ) : array {

            // get the users IP address
            $ip = KPT::get_user_ip( );
            
            // return the populated array
            return [
                'ip' => $ip,
                'ip_version' => strpos( $ip, ':' ) !== false ? 6 : 4,
                'network_segment' => $this -> getNetworkSegment( $ip )
            ];
        }
        
        /**
         * Extract client software and capability characteristics
         * 
         * @return array Returns an array of client characteristics
         */
        protected function getClientCharacteristics( ) : array {

            // get the users User Agent
            $ua = KPT::get_user_agent( ) ?? '';
            
            // return the populated array
            return [
                'device_type' => $this -> getDeviceType( $ua ),
                'languages' => explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' ),
                'encodings' => explode( ',', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '' ),
            ];
        }
        
        /**
         * Calculate network segment from IP address
         * 
         * @return string Returns the network segment from the IP address
         */
        protected function getNetworkSegment( string $ip ) : string {

            // filter out the segment, it it's v4
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                $parts = explode( '.', $ip );
                return $parts[0].'.'.$parts[1]; // Class B segment
            }
            
            // otherwise its v6, so clean it up
            $clean = str_replace( ':', '', $ip );
            return substr( $clean, 0, 16 );
        }
        
        /**
         * Categorize device type from User-Agent string
         * 
         * @return string Returns a string of the possible device
         */
        protected function getDeviceType( string $ua ): string {

            // if it's mobile
            if ( preg_match( '/(mobile|android|iphone|ipad)/i', $ua ) ) {
                return 'mobile';
            }

            // if it's a bot
            if ( preg_match( '/(bot|crawl|spider)/i', $ua ) ) {
                return 'bot';
            }

            // otherwise, its desktop
            return 'desktop';
        }

    }

}
