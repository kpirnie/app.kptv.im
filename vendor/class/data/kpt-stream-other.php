<?php
/**
 * KPTV Stream Other CRUD Class
 * 
 * Handles all database operations for the kptv_stream_other table
 * Manages additional/alternative IPTV stream entries
 * 
 * @since 8.4
 * @package KP TV
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

if( ! class_exists( 'KPTV_Stream_Other' ) ) {

    class KPTV_Stream_Other extends KPT_DB {
        
        /** @var int $current_user_id The ID of the currently authenticated user */
        private int $current_user_id;
        
        public function __construct() {
            parent::__construct();
            $this->current_user_id = (KPT_User::get_current_user()->id) ?? 0;
        }

        /**
         * Get total count of records for the current user
         * 
         * @return int Total number of records
         */
        public function getTotalCount(?string $search = null): int {
            $query = "SELECT COUNT(a.id) as total FROM kptv_stream_other a
            LEFT JOIN kptv_stream_providers p ON a.p_id = p.id AND p.u_id = a.u_id
            WHERE a.u_id = ?";

            $params = [$this->current_user_id];

            if(!empty($search)) {
                $searchTerm = "%{$search}%";
                $query .= " AND (p.sp_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
                $params = [...$params, ...array_fill(0, 6, $searchTerm)];
            }

            $result = $this->select_single($query, $params);
            return (int)$result->total ?? 0;
        }

    /**
     * Searches streams by name or original name
     * 
     * @param string $search The search term to match against stream names
     * @param int $per_page Number of records per page
     * @param int $offset The offset for pagination
     * @param string $sort_column Column to sort by
     * @param string $sort_direction Sort direction (ASC/DESC)
     * @param int|null $type_id Optional stream type to filter by
     * @return array|bool Returns matching streams or false if none found
     */
    public function searchPaginated(string $search, int $per_page, int $offset, string $sort_column = 's_name', string $sort_direction = 'ASC'): array|bool {
        $query = "SELECT s.*, p.sp_name as provider_name 
                FROM kptv_stream_other s
                LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                WHERE s.u_id = ?";

        $params = [$this->current_user_id];

        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $query .= " AND (p.sp_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
            $params = [...$params, ...array_fill(0, 6, $searchTerm)];
        }

        $query .= " ORDER BY $sort_column $sort_direction
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
                
        return $this->select_many($query, $params); // Changed this line to use $params instead of hardcoded values
    }

        /**
         * Get paginated and sorted records with provider names
         * 
         * @param int $per_page Number of records per page
         * @param int $offset The offset for pagination
         * @param string $sort_column Column to sort by
         * @param string $sort_direction Sort direction (ASC/DESC)
         * @return array|bool Returns paginated records with provider names or false on failure
         */
        public function getPaginated(int $per_page, int $offset, string $sort_column = 's_orig_name', string $sort_direction = 'ASC'): array|bool {
            // First get the streams
            $query = "SELECT so.*, sp.sp_name as provider_name 
                    FROM kptv_stream_other so
                    LEFT JOIN kptv_stream_providers sp ON so.p_id = sp.id
                    WHERE so.u_id = ? 
                    ORDER BY $sort_column $sort_direction, s_orig_name ASC
                    LIMIT ? OFFSET ?";
            return $this->select_many($query, [$this->current_user_id, $per_page, $offset]);
        }

        /**
         * Get all providers for the current user
         * 
         * @return array|bool Array of providers or false on failure
         */
        public function getProviders(): array|bool {
            $query = "SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ? ORDER BY sp_name";
            return $this->select_many($query, [$this->current_user_id]);
        }

        /**
         * Handles the post actions from the listing
         * 
         * @param array $params The post parameters
         * @return void Returns nothing
         */
        public function post_action(array $params): void {
            $theid = isset($params['id']) ? (int)$params['id'] : 0;
            
            switch ($params['form_action']) {
                case 'create':
                    $this->create($params);
                    break;
                    
                case 'update':
                    $this->update($theid, $params);
                    break;
                    
                case 'move-to-live':
                    if (isset($params['ids']) && is_array($params['ids'])) {
                        foreach ($params['ids'] as $id) {
                            $this->move_to($id, 0);
                        }
                    }                    
                    break;
                    
                case 'move-to-series':
                    if (isset($params['ids']) && is_array($params['ids'])) {
                        foreach ($params['ids'] as $id) {
                            $this->move_to($id, 5);
                        }
                    }                    
                    break;
                    
                case 'delete':
                    $this->delete($theid);
                    break;
                    
                case 'delete-multiple':
                    if (isset($params['ids']) && is_array($params['ids'])) {
                        foreach ($params['ids'] as $id) {
                            $this->delete($id);
                        }
                    }
                    break;
            }
        }

        /**
         * Creates a new stream_other entry
         * 
         * @param array $data Associative array of stream data to insert
         * @return int|bool Returns the new entry ID on success, false on failure
         */
        public function create(array $data): int|bool {
            $query = "INSERT INTO kptv_stream_other (
                u_id, p_id, s_orig_name, s_stream_uri, 
                s_tvg_id, s_tvg_logo, s_extras
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $this->current_user_id,
                $data['p_id'] ?? 0,
                $data['s_orig_name'] ?? '',
                $data['s_stream_uri'] ?? '',
                $data['s_tvg_id'] ?? null,
                $data['s_tvg_logo'] ?? null,
                $data['s_extras'] ?? null
            ];
            
            return $this->execute($query, $params);
        }

        /**
         * Updates an existing stream_other entry
         * 
         * @param int $id The ID of the entry to update
         * @param array $data Associative array of fields to update
         * @return bool Returns true on success, false on failure
         */
        public function update(int $id, array $data): bool {
            $query = "UPDATE kptv_stream_other SET 
                p_id = ?, s_orig_name = ?, s_stream_uri = ?, 
                s_tvg_id = ?, s_tvg_logo = ?, s_extras = ?
                WHERE id = ? AND u_id = ?";
            
            $params = [
                $data['p_id'] ?? 0,
                $data['s_orig_name'] ?? '',
                $data['s_stream_uri'] ?? '',
                $data['s_tvg_id'] ?? null,
                $data['s_tvg_logo'] ?? null,
                $data['s_extras'] ?? null,
                $id,
                $this->current_user_id
            ];
            
            return (bool)$this->execute($query, $params);
        }

        /**
         * Deletes a stream_other entry
         * 
         * @param int $id The ID of the entry to delete
         * @return bool Returns true on success, false on failure
         */
        public function delete(int $id): bool {
            $query = "DELETE FROM kptv_stream_other WHERE id = ? AND u_id = ?";
            return (bool)$this->execute($query, [$id, $this->current_user_id]);
        }

        /**
         * Move a stream to another type entry
         * 
         * @param int $id The ID of the entry to delete
         * @return bool Returns true on success, false on failure
         */
        public function move_to( int $id, int $type ) : bool {
            $query = "Call Streams_Move_From_Other( ?, ? );";
            return ( bool ) $this -> execute( $query, [$id, $type] );
        }

    }

}