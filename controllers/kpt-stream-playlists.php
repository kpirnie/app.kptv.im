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
    class KPTV_Stream_Playlists extends KPT_Database {
        
        public function __construct( ) {
            parent::__construct( );
        }

        /**
         * Pull streams for a specific provider
         * 
         * @param string $user The encrypted user ID we need to pull a playlist for
         * @param string $provider The encrypted provider ID we need to pull a playlist for
         * @param int $which Which streams are we actually pulling (0=live, 5=series, etc.)
         * @return array|bool Returns matching streams or false if none found
         */
        public function getGetProviderPlaylist( string $user, string $provider, int $which ) : array|bool {

            // setup the provider and user
            $user = KPT::decrypt( $user );
            $provider = KPT::decrypt( $provider );

            // setup the query
            $query = 'Call Streams_Get_Provider( ?, ?, ? );';

            // setup the parameters
            $params = [$provider, $user, $which];

            // return the query execution
            return $this->query($query)->bind($params)->many()->fetch();
        }

        /**
         * Pull streams for a user (all providers)
         * 
         * @param string $user The encrypted user ID we need to pull a playlist for
         * @param int $which Which streams are we actually pulling (0=live, 5=series, etc.)
         * @return array|bool Returns matching streams or false if none found
         */
        public function getUserPlaylist( string $user, int $which ) : array|bool {

            // setup the user
            $user = KPT::decrypt( $user );

            // setup the query
            $query = 'Call Streams_Get_User( ?, ? );';

            // setup the parameters
            $params = [$user, $which];

            // return the query execution
            return $this->query($query)->bind($params)->many()->fetch();
        }

    }

}