<?php
/**
 * KPTV XtreamCodes API Emulation
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;
use KPT\Database;
use KPT\Logger;

if( ! class_exists( 'KPTV_XtreamAPI' ) ) {

    class KPTV_XtreamAPI extends Database {
        
        private const TYPE_LIVE = 0;
        private const TYPE_VOD = 4;
        private const TYPE_SERIES = 5;
        
        public function __construct( ) {
            parent::__construct( KPT::get_setting( 'database' ) );
        }

        public function handleRequest( ): void {
            
            try {
                $action = $_GET['action'] ?? '';
                
                if ( empty( $action ) ) {
                    $this->sendError( 'No action specified', 400 );
                    return;
                }
                
                switch ( $action ) {
                    case 'get_live_streams':
                        $this->getLiveStreams( );
                        break;
                        
                    case 'get_vod_streams':
                        $this->getVodStreams( );
                        break;
                        
                    case 'get_series':
                        $this->getSeries( );
                        break;
                        
                    default:
                        $this->sendError( 'Unknown action', 400 );
                }
                
            } catch ( \Throwable $e ) {
                Logger::error( "XtreamAPI error", [
                    'action' => $_GET['action'] ?? '',
                    'error' => $e->getMessage( )
                ] );
                
                $this->sendError( "API error occurred", 500 );
            }
        }

        private function getLiveStreams( ): void {
            
            $user = $_GET['user'] ?? '';
            $provider = $_GET['provider'] ?? null;
            
            if ( empty( $user ) ) {
                $this->sendError( 'User parameter required', 400 );
                return;
            }
            
            $userId = KPT::decrypt( $user );
            
            $sql = 'SELECT
                    a.`id` as stream_id,
                    a.`s_channel` as num,
                    a.`s_name` as name,
                    a.`s_stream_uri` as stream_url,
                    a.`s_tvg_id` as epg_channel_id,
                    a.`s_tvg_logo` as stream_icon,
                    a.`s_tvg_group` as category_id,
                    "live" as category_name,
                    1 as tv_archive,
                    0 as direct_source,
                    0 as tv_archive_duration
                    FROM `kptv_streams` a
                    LEFT OUTER JOIN `kptv_stream_providers` b ON b.`id` = a.`p_id`
                    WHERE a.`u_id` = ? AND a.`s_active` = 1 AND a.`s_type_id` = ?';
            
            $params = [$userId, self::TYPE_LIVE];
            
            if ( $provider !== null ) {
                $sql .= ' AND a.`p_id` = ?';
                $params[] = (int)$provider;
            }
            
            $sql .= ' ORDER BY b.`sp_priority`, a.`s_name` ASC';
            
            $streams = $this->query( $sql )->bind( $params )->fetch( );
            
            if ( ! $streams ) {
                $streams = [];
            }
            
            $this->sendSuccess( $streams );
        }

        private function getVodStreams( ): void {
            
            $user = $_GET['user'] ?? '';
            $provider = $_GET['provider'] ?? null;
            
            if ( empty( $user ) ) {
                $this->sendError( 'User parameter required', 400 );
                return;
            }
            
            $userId = KPT::decrypt( $user );
            
            $sql = 'SELECT
                    a.`id` as stream_id,
                    a.`s_channel` as num,
                    a.`s_name` as name,
                    a.`s_stream_uri` as stream_url,
                    a.`s_tvg_logo` as stream_icon,
                    a.`s_tvg_group` as category_id,
                    "vod" as category_name,
                    0 as direct_source,
                    "" as container_extension
                    FROM `kptv_streams` a
                    LEFT OUTER JOIN `kptv_stream_providers` b ON b.`id` = a.`p_id`
                    WHERE a.`u_id` = ? AND a.`s_active` = 1 AND a.`s_type_id` = ?';
            
            $params = [$userId, self::TYPE_VOD];
            
            if ( $provider !== null ) {
                $sql .= ' AND a.`p_id` = ?';
                $params[] = (int)$provider;
            }
            
            $sql .= ' ORDER BY b.`sp_priority`, a.`s_name` ASC';
            
            $streams = $this->query( $sql )->bind( $params )->fetch( );
            
            if ( ! $streams ) {
                $streams = [];
            }
            
            $this->sendSuccess( $streams );
        }

        private function getSeries( ): void {
            
            $user = $_GET['user'] ?? '';
            $provider = $_GET['provider'] ?? null;
            
            if ( empty( $user ) ) {
                $this->sendError( 'User parameter required', 400 );
                return;
            }
            
            $userId = KPT::decrypt( $user );
            
            $sql = 'SELECT
                    a.`id` as series_id,
                    a.`s_channel` as num,
                    a.`s_name` as name,
                    a.`s_stream_uri` as stream_url,
                    a.`s_tvg_logo` as cover,
                    a.`s_tvg_group` as category_id,
                    "series" as category_name,
                    0 as direct_source
                    FROM `kptv_streams` a
                    LEFT OUTER JOIN `kptv_stream_providers` b ON b.`id` = a.`p_id`
                    WHERE a.`u_id` = ? AND a.`s_active` = 1 AND a.`s_type_id` = ?';
            
            $params = [$userId, self::TYPE_SERIES];
            
            if ( $provider !== null ) {
                $sql .= ' AND a.`p_id` = ?';
                $params[] = (int)$provider;
            }
            
            $sql .= ' ORDER BY b.`sp_priority`, a.`s_name` ASC';
            
            $streams = $this->query( $sql )->bind( $params )->fetch( );
            
            if ( ! $streams ) {
                $streams = [];
            }
            
            $this->sendSuccess( $streams );
        }

        private function sendSuccess( $data ): void {
            header( 'Content-Type: application/json' );
            header( 'Cache-Control: no-cache, must-revalidate' );
            http_response_code( 200 );
            echo json_encode( $data, JSON_PRETTY_PRINT );
            exit;
        }

        private function sendError( string $message, int $code = 400 ): void {
            header( 'Content-Type: application/json' );
            http_response_code( $code );
            echo json_encode( [
                'error' => true,
                'message' => $message
            ], JSON_PRETTY_PRINT );
            exit;
        }
    }
}
