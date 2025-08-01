<?php
/**
 * KPTV Stream Filters CRUD Class
 * 
 * Handles all database operations for the kptv_stream_filters table
 * Manages content filtering rules for IPTV streams
 * 
 * @since 8.4
 * @package KP TV
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class isn't already in userspace
if( ! class_exists( 'KPTV_Stream_Filters' ) ) {

    /**
     * KPTV Stream Filters CRUD Class
     * 
     * Handles all database operations for the kptv_stream_filters table
     * 
     * @since 8.4
     * @package KP TV
     * @author Kevin Pirnie <me@kpirnie.com>
     */
    class KPTV_Stream_Filters extends KPT_DB {
        
        /** @var int $current_user_id The ID of the currently authenticated user */
        private int $current_user_id;
        
        // fire us up
        public function __construct( ) {
            // fire up the database class
            parent::__construct( );

            // setup the user id
            $this -> current_user_id = ( KPT_User::get_current_user( ) -> id ) ?? 0;
        }

        /**
         * Get total count of records for the current user
         * 
         * @return int Total number of records
         */
        public function getTotalCount( string $search = '' ): int {

            // setup the query to run
            $query = "SELECT COUNT(id) as total FROM kptv_stream_filters WHERE u_id = ?";

            // setup the parameters
            $params = [$this -> current_user_id];

            // if search is not empty
            if( ! empty( $search ) ) {

                // hold the term
                $searchTerm = "%{$search}%";
                $query .= ' AND sf_filter LIKE ?';
                $params = [$this -> current_user_id, $searchTerm];
            }

            // hold the results
            $result = $this -> select_single( $query, $params );

            // return the results or 0
            return ( int ) $result -> total ?? 0;
        }

        /**
         * Get paginated and sorted records
         * 
         * @param int $per_page Number of records per page
         * @param int $offset The offset for pagination
         * @param string $sort_column Column to sort by
         * @param string $sort_direction Sort direction (ASC/DESC)
         * @return array|bool Returns paginated records or false on failure
         */
        public function getPaginated( int $per_page, int $offset, string $sort_column = 'sf_type_id', string $sort_direction = 'ASC' ) : array|bool {
            
            // setup the query
            $query = "SELECT * FROM kptv_stream_filters 
                    WHERE u_id = ? 
                    ORDER BY $sort_column $sort_direction, sf_type_id ASC
                    LIMIT ? OFFSET ?";

            // return the results
            return $this -> select_many( $query, [$this -> current_user_id, $per_page, $offset] );
        }

        /**
         * Searches filters by their filter text
         * 
         * @param string $search The search term to match against filter text
         * @param int $per_page Number of records per page
         * @param int $offset The offset for pagination
         * @param string $sort_column Column to sort by
         * @param string $sort_direction Sort direction (ASC/DESC)
         * @return array|bool Returns matching filters or false if none found
         */
        public function searchPaginated( string $search, int $per_page, int $offset, string $sort_column = 'sf_type_id', string $sort_direction = 'ASC' ) : array|bool {

            // setup the query
            $query = "SELECT * FROM kptv_stream_filters 
                    WHERE u_id = ? AND sf_filter LIKE ?
                    ORDER BY $sort_column $sort_direction, sf_type_id ASC
                    LIMIT ? OFFSET ?";
            
            // hold the term
            $searchTerm = "%{$search}%";

            // return the results
            return $this -> select_many( $query, [$this->current_user_id, $searchTerm, $per_page, $offset] );
        }

        /**
         * Creates a new filter record
         * 
         * @param array $data Associative array of filter data to insert
         * @return int|bool Returns the new filter ID on success, false on failure
         */
        private function create( array $data ) : int|bool {

            // setup the query
            $query = "INSERT INTO kptv_stream_filters (
                u_id, sf_active, sf_type_id, sf_filter
            ) VALUES (?, ?, ?, ?)";
            
            // setup the parameters
            $params = [
                $this->current_user_id,
                $data['sf_active'] ?? 1,
                $data['sf_type_id'] ?? 1,
                $data['sf_filter'] ?? '',
            ];
            
            // return the execution
            return $this -> execute( $query, $params );
        }

        /**
         * Updates an existing filter record
         * 
         * @param int $id The ID of the filter to update
         * @param array $data Associative array of fields to update
         * @return bool Returns true on success, false on failure
         */
        private function update( int $id, array $data ) : bool {

            // setup the query
            $query = "UPDATE kptv_stream_filters SET 
                sf_active = ?, sf_type_id = ?, sf_filter = ?, 
                sf_updated = CURRENT_TIMESTAMP
                WHERE id = ? AND u_id = ?";
            
            // setup the parameters
            $params = [
                $data['sf_active'] ?? 1,
                $data['sf_type_id'] ?? 1,
                $data['sf_filter'] ?? '',
                $id,
                $this -> current_user_id
            ];
            
            // return the execution
            return ( bool ) $this -> execute( $query, $params );
        }

        /**
         * Deletes a filter record
         * 
         * @param int $id The ID of the filter to delete
         * @return bool Returns true on success, false on failure
         */
        private function delete( int $id ) : bool {

            // setup the query
            $query = "DELETE FROM kptv_stream_filters WHERE id = ? AND u_id = ?";
            
            // return the execution
            return ( bool ) $this -> execute( $query, [$id, $this -> current_user_id] );
        }

        /**
         * Toggles the active status of a filter
         * 
         * @param int $id The ID of the filter to toggle
         * @return bool Returns true on success, false on failure
         */
        private function toggleActive(int $id): bool {
            
            // setup the query
            $query = "UPDATE kptv_stream_filters  
                    SET sf_active = NOT sf_active
                    WHERE id = ? AND u_id = ?";

            // return the execution
            return ( bool ) $this -> execute( $query, [$id, $this -> current_user_id] );
        }

        /**
         * Handles the post actions from the listing
         * 
         * @param array $params The post parameters
         * @return void Returns nothing
         */
        public function post_action( array $params ) : void {

            // For AJAX requests
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

            // grab the ID if any
            $theid = isset( $_POST['id'] ) ? ( int ) $params['id'] : 0;
            
            // switch through the actions taken
            switch ($params['form_action']) {

                // create the new filter
                case 'create':
                    $this -> create( $params );
                    break;

                // update the filter
                case 'update':
                    $this -> update( $theid, $params );
                    break;

                // delete the filter
                case 'delete':
                    $this -> delete( $theid );
                    break;

                // delete selected filters
                case 'delete-multiple':
                    // make sure the ids are set and are an array
                    if ( isset( $params['ids'] ) && is_array( $params['ids'] ) ) {
                        // loop the ids
                        foreach ( $params['ids'] as $id ) {
                            $this -> delete( $id );
                        }
                    }
                    break;

                // toggle the active
                case 'toggle-active':
                    $success = $this->toggleActive($theid);
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => $success,
                            'message' => $success ? 'In/Activated successfully' : 'Failed to activate'
                        ]);
                        exit;
                    }
                    break;
            }

        }

    }

}
