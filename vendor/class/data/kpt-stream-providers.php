<?php
/**
 * KPTV Stream Providers CRUD Class
 * 
 * Handles all database operations for the kptv_stream_providers table
 * Manages IPTV stream provider sources and configurations
 * 
 * @since 8.4
 * @package KP TV
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure we don't have the class in userspace already
if( ! class_exists( 'KPTV_Stream_Providers' ) ) {

    /**
     * KPTV Stream Providers CRUD Class
     * 
     * Handles all database operations for the kptv_stream_providers table
     * 
     * @since 8.4
     * @package KP TV
     * @author Kevin Pirnie <me@kpirnie.com>
     */
    class KPTV_Stream_Providers extends KPT_DB {
        
        /** @var int $current_user_id The ID of the currently authenticated user */
        private int $current_user_id;
        
        // fire us up with the parent constructor also
        public function __construct( ) {

            // fire up the database class
            parent::__construct( );

            // hold the user id
            $this -> current_user_id = ( KPT_User::get_current_user( ) -> id ) ?? 0;
        }

        /**
         * Gets the total count of providers for the current user
         * 
         * @param string $search The search term to match against provider names
         * @return int The total number of providers
         */
        public function getTotalCount( ?string $search = null ) : int {

            // setup the query to run
            $query = "SELECT COUNT(id) as total FROM kptv_stream_providers WHERE u_id = ?";

            // setup the initial parameters
            $params = [$this -> current_user_id];

            // if the search isn't empty
            if( ! empty( $search ) ) {

                // setup the term, rest of the query, and the rest of the parameters
                $searchTerm = "%{$search}%";
                $query .= " AND sp_name LIKE ?";
                $params = [...$params, ...array_fill( 0, 1, $searchTerm )];
            }

            // hold the results
            $result = $this -> select_single( $query, $params );

            // return the results or 0
            return ( int ) $result -> total ?? 0;
        }
        

        /**
         * Retrieves paginated providers for the current user
         * 
         * @param int $per_page Number of records per page
         * @param int $offset The offset for pagination
         * @param string $sort_column Column to sort by
         * @param string $sort_direction Sort direction (ASC/DESC)
         * @return array|bool Returns paginated records or false on failure
         */
        public function getPaginated( int $per_page, int $offset, string $sort_column = 'sp_priority', string $sort_direction = 'ASC' ) : array|bool {
            
            // setup the query
            $query = "SELECT * FROM kptv_stream_providers 
                    WHERE u_id = ? 
                    ORDER BY $sort_column $sort_direction, sp_priority ASC
                    LIMIT ? OFFSET ?";

            // return all the records
            return $this -> select_many( $query, [$this -> current_user_id, $per_page, $offset] );
        }

        /**
         * Searches providers by name
         * 
         * @param string $search The search term to match against provider names
         * @param int $per_page Number of records per page
         * @param int $offset The offset for pagination
         * @param string $sort_column Column to sort by
         * @param string $sort_direction Sort direction (ASC/DESC)
         * @return array|bool Returns matching providers or false if none found
         */
        public function searchPaginated( string $search, int $per_page, int $offset, string $sort_column = 'sp_priority', string $sort_direction = 'ASC' ) : array|bool {

            // setup the query
            $query = "SELECT * FROM kptv_stream_providers 
                    WHERE u_id = ? AND sp_name LIKE ?
                    ORDER BY $sort_column $sort_direction, sp_priority ASC
                    LIMIT ? OFFSET ?";

            // setup the search term
            $searchTerm = "%{$search}%";

            // return the execution
            return $this -> select_many( $query, [$this -> current_user_id, $searchTerm, $per_page, $offset] );
        }

        /**
         * Creates a new provider record
         * 
         * @param array $data Associative array of provider data to insert
         * @return int|bool Returns the new provider ID on success, false on failure
         */
        private function create( array $data ): int|bool {

            // setup the query
            $query = "INSERT INTO kptv_stream_providers (
                u_id, sp_should_filter, sp_priority, sp_name, sp_type, 
                sp_domain, sp_username, sp_password, sp_stream_type, 
                sp_refresh_period
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // hold the parameters
            $params = [
                $this -> current_user_id,
                $data['sp_should_filter'] ?? 1,
                $data['sp_priority'] ?? 99,
                $data['sp_name'] ?? '',
                $data['sp_type'] ?? 0,
                $data['sp_domain'] ?? '',
                $data['sp_username'] ?? null,
                $data['sp_password'] ?? null,
                $data['sp_stream_type'] ?? 0,
                $data['sp_refresh_period'] ?? 3
            ];
            
            // return the execution
            return $this -> execute( $query, $params );
        }

        /**
         * Updates an existing provider record
         * 
         * @param int $id The ID of the provider to update
         * @param array $data Associative array of fields to update
         * @return bool Returns true on success, false on failure
         */
        private function update( int $id, array $data ): bool {

            // setup the query
            $query = "UPDATE kptv_stream_providers SET 
                sp_should_filter = ?, sp_priority = ?, sp_name = ?, sp_type = ?, 
                sp_domain = ?, sp_username = ?, sp_password = ?, sp_stream_type = ?, 
                sp_refresh_period = ?, sp_updated = CURRENT_TIMESTAMP
                WHERE id = ? AND u_id = ?";
            
            // setup the parameters
            $params = [
                $data['sp_should_filter'] ?? 1,
                $data['sp_priority'] ?? 99,
                $data['sp_name'] ?? '',
                $data['sp_type'] ?? 0,
                $data['sp_domain'] ?? '',
                $data['sp_username'] ?? null,
                $data['sp_password'] ?? null,
                $data['sp_stream_type'] ?? 0,
                $data['sp_refresh_period'] ?? 3,
                $id,
                $this -> current_user_id
            ];
            
            // return the execution
            return ( bool ) $this -> execute( $query, $params );
        }

        /**
         * Deletes a provider record
         * also deletes the associated streams
         * 
         * @param int $id The ID of the provider to delete
         * @return bool Returns true on success, false on failure
         */
        private function delete( int $id) : bool {

            // first delete from other
            $this -> execute( "DELETE FROM kptv_stream_other WHERE p_id = ? AND u_id = ?", [$id, $this -> current_user_id] );
            // then delete from streams
            $this -> execute( "DELETE FROM kptv_streams WHERE p_id = ? AND u_id = ?", [$id, $this -> current_user_id] );
            // now, delete the provider and return the execution
            return ( bool ) $this -> execute( "DELETE FROM kptv_stream_providers WHERE id = ? AND u_id = ?", [$id, $this -> current_user_id] );
        }

        /**
         * Toggles the active filtering
         * 
         * @param int $id The ID of the provider to delete
         * @return bool Returns true on success, false on failure
         */
        private function toggleActive( int $id ) : bool {

            // setup the query to be run
            $query = "UPDATE kptv_stream_providers SET sp_should_filter = NOT sp_should_filter WHERE id = ? AND u_id = ?";
            
            // return the execution
            return ( bool ) $this -> execute( $query, [$id, $this -> current_user_id] );
        }

        /**
         * Handles the post actions from the listing
         * 
         * @param array $params The post parameters
         * @return void Returns nothing
         */
        public function post_actions( array $params ) : void {

            // For AJAX requests
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

            // grab the ID if any
            $theid = isset( $_POST['id'] ) ? ( int ) $params['id'] : 0;
            
            // switch through the actions taken
            switch ( $params['form_action'] ) {

                // create a new proivider
                case 'create':
                    $this -> create( $params );
                    break;

                // update a provider
                case 'update':
                    // just make sure we have the id
                    if ( $theid > 0 ) {                    
                        $this -> update( $theid, $params );
                    }
                    break;

                // delete a provider
                case 'delete':
                    // just make sure we have the id
                    if ( $theid > 0 ) { 
                        $this -> delete( $theid );
                    }
                    break;

                // delete selected providers
                case 'delete-multiple':
                    // make sure the ids are set and are an array
                    if ( isset( $params['ids'] ) && is_array( $params['ids'] ) ) {
                        // loop the ids
                        foreach ( $params['ids'] as $id ) {
                            $this -> delete( $id );
                        }
                    }
                    break;

                // toggle the active and make sure we get a json response
                case 'toggle-active':
                    $success = $this -> toggleActive( $theid );
                    if ( $is_ajax ) {
                        header( 'Content-Type: application/json' );
                        echo json_encode( [
                            'success' => $success,
                            'message' => $success ? 'In/Activated successfully' : 'Failed to activate'
                        ] );
                        exit;
                    }
                    break;                
            }

        }

    }

}
