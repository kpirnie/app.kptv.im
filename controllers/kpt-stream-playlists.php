<?php
/**
 * KPTV Stream Playlists class
 * 
 * Handles the playlist rendering
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;
use KPT\Database;
use KPT\Logger;
use KPT\Cache;

// make sure the class isn't already in userspace
if( ! class_exists( 'KPTV_Stream_Playlists' ) ) {

    /**
     * KPTV Stream Playlists class
     * 
     * Handles the playlist rendering
     * 
     * @since 8.4
     * @package KP Library
     * @author Kevin Pirnie <me@kpirnie.com>
     */
    class KPTV_Stream_Playlists extends Database {
        
        public function __construct( ) {
            parent::__construct( KPT::get_setting( 'database' ) );
        }

        /**
         * Handle user playlist route (user + which)
         * 
         * @param string $user Encrypted user ID
         * @param string $which Which streams to pull
         * @return void Outputs M3U playlist directly
         */
        public function handleUserPlaylist( string $user, string $which ): void {
            
            try {
                // setup the type of stream to pull...
                $stream_type = ['live' => 0, 'series' => 5, 'vod' => 4];
                
                // Get playlist data
                $records = $this->getUserPlaylist( $user, $stream_type[$which] );
                
                // Generate and output M3U
                $this->outputM3UPlaylist( $records, $which );
                
            } catch ( \Throwable $e ) {
                Logger::error( "User playlist generation failed", [
                    'user' => $user,
                    'which' => $which,
                    'error' => $e->getMessage()
                ] );
                
                $this->outputErrorResponse( "Failed to generate playlist" );
            }
        }

        /**
         * Handle provider playlist route (user + provider + which)
         * 
         * @param string $user Encrypted user ID
         * @param string $provider Encrypted provider ID
         * @param string $which Which streams to pull
         * @return void Outputs M3U playlist directly
         */
        public function handleProviderPlaylist( string $user, string $provider, string $which ): void {
            
            try {
                // setup the type of stream to pull...
                $stream_type = ['live' => 0, 'series' => 5, 'vod' => 4];
                
                // Get playlist data
                $records = $this->getGetProviderPlaylist( $user, $provider, $stream_type[$which] );
                
                // Generate and output M3U
                $this->outputM3UPlaylist( $records, $which );
                
            } catch ( \Throwable $e ) {
                Logger::error( "Provider playlist generation failed", [
                    'user' => $user,
                    'provider' => $provider,
                    'which' => $which,
                    'error' => $e->getMessage()
                ] );
                
                $this->outputErrorResponse( "Failed to generate playlist" );
            }
        }

                /**
         * Pull streams for a specific provider
         * 
         * @param string $user The encrypted user ID we need to pull a playlist for
         * @param string $provider The encrypted provider ID we need to pull a playlist for
         * @param int $which Which streams are we actually pulling (0=live, 5=series, etc.)
         * @return array|bool Returns matching streams or false if none found
         */
        private function getGetProviderPlaylist( string $user, string $provider, int $which ) : array|bool {

            // setup the provider and user
            $user = KPT::decrypt( $user );
            $provider = KPT::decrypt( $provider );

            // setup the cache key
            $ck = sprintf( 'pl_%s_%s_%d', $user, $provider, $which );

            // Try to get cached content
            $cached = Cache::get( $ck );
            if ( $cached !== false ) {
                Logger::debug( "Provider Playlist Cache Hit" );
                return $cached;
            }

            // setup the query
            $query = 'Call Streams_Get_Provider( ?, ?, ? );';

            // setup the parameters
            $params = [$provider, $user, $which];

            // setup the recordset
            $rs = $this->query($query)->bind($params)->many()->fetch();

            // cache the recordset
            Cache::set( $ck, $rs, KPT::DAY_IN_SECONDS );
        
            // return the records
            return $rs;
        }

        /**
         * Pull streams for a user (all providers)
         * 
         * @param string $user The encrypted user ID we need to pull a playlist for
         * @param int $which Which streams are we actually pulling (0=live, 5=series, etc.)
         * @return array|bool Returns matching streams or false if none found
         */
        private function getUserPlaylist( string $user, int $which ) : array|bool {

            // setup the user
            $user = KPT::decrypt( $user );

            // setup the cache key
            $ck = sprintf( 'pl_%s_%d', $user, $which );

            // Try to get cached content
            $cached = Cache::get( $ck );
            if ( $cached !== false ) {
                Logger::debug( "User Playlist Cache Hit" );
                return $cached;
            }

            // setup the query
            $query = 'Call Streams_Get_User( ?, ? );';

            // setup the parameters
            $params = [$user, $which];

            // setup the recordset
            $rs = $this->query($query)->bind($params)->many()->fetch();

            // cache the recordset
            Cache::set( $ck, $rs, KPT::DAY_IN_SECONDS );
        
            // return the records
            return $rs;
        }

        /**
         * Generate and output M3U playlist (using original working logic)
         * 
         * @param array|bool $records Stream data from database
         * @param string $which Stream type name
         * @return void Outputs M3U content directly
         */
        private function outputM3UPlaylist( $records, string $which ): void {
            
            // make sure there's records
            if( $records ) {

                // set the mimetype and filename to download
                header( 'Content-Type: application/mpegurl' );
                header( 'Content-Disposition: attachment; filename="' . $which . '.m3u8"' );

                // set the expires and caching headers
                header( 'Expires: ' . gmdate( "D, d M Y H:i:s", time( ) + KPT::DAY_IN_SECONDS ) . " GMT", true );
                header( 'Cache-Control: public, max-age=' . KPT::DAY_IN_SECONDS, true );
                header_remove( 'set-cookie' );

                // start the M3U no matter what
                echo "#EXTM3U" . PHP_EOL;

                // loop over the records
                foreach( $records as $rec ) {

                    // start creating the EXTINF line
                    $extinf = sprintf( '#EXTINF:-1 tvg-name="%s" tvg-chno="%s" tvg-type="%s"', $rec -> TvgName, $rec -> TvgChNo, $rec -> TvgType );

                    // if there's a tvg-group
                    if( ! empty( $rec -> TvgGroup ) ) {
                        $extinf .= sprintf( ' tvg-group="%s"', $which );
                        $extinf .= sprintf( ' group-title="%s"', $which );
                    }

                    // if there's a tvg-id
                    if( ! empty( $rec -> TvgID ) ) {
                        $extinf .= sprintf( ' tvg-id="%s"', $rec -> TvgID );
                    }

                    // if there's a tvg-logo
                    if( ! empty( $rec -> TvgLogo ) ) {
                        $extinf .= sprintf( ' tvg-logo="%s"', $rec -> TvgLogo );
                    }

                    // finish up the extinf line and write it out
                    $extinf .= sprintf( ', %s', $rec -> TvgName );
                    echo $extinf . PHP_EOL;

                    // write out the stream url line
                    echo $rec -> Stream . PHP_EOL;

                }

            }
        }

        /**
         * Output error response for playlist requests
         * 
         * @param string $message Error message
         * @return void Outputs error response
         */
        private function outputErrorResponse( string $message ): void {
            
            // Set error headers
            http_response_code( 500 );
            header( 'Content-Type: application/mpegurl' );
            
            // Output minimal M3U with error
            echo "#EXTM3U" . PHP_EOL;
            echo "# Error: {$message}" . PHP_EOL;
        }

    }

}