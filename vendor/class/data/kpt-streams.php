<?php
/**
 * KPTV Streams CRUD Class
 * 
 * Handles all database operations for the kptv_streams table
 * Provides complete CRUD functionality for IPTV streams
 * 
 * @since 8.4
 * @package KP TV
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class KPTV_Streams extends KPT_DB {
    
    /** @var int $current_user_id The ID of the currently authenticated user */
    private int $current_user_id;
    
    public function __construct() {
        parent::__construct();
        $this->current_user_id = (KPT_User::get_current_user()->id) ?? 0;
    }

    /**
     * Get total count of records for the current user
     * 
     * @param int|null $type_id Optional stream type to filter by
     * @return int Total number of records
     */
    public function getTotalCount(?int $type_id = null, ?int $active = null, ?string $search = null): int {
        $query = "SELECT COUNT(s.id) as total 
              FROM kptv_streams s
              LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
              WHERE s.u_id = ?";
        $params = [$this->current_user_id];

        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $query .= " AND (p.sp_name LIKE ? OR s_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_group LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
            $params = [...$params, ...array_fill(0, 8, $searchTerm)];
        }
        
        if ($type_id !== null) {
            $query .= " AND s_type_id = ?";
            $params[] = $type_id;
        }

        if ($active !== null) {
            $query .= " AND s_active = ?";
            $params[] = $active;
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
    public function searchPaginated(string $search, int $per_page, int $offset, string $sort_column = 's_name', string $sort_direction = 'ASC', ?int $type_id = null, ?int $active = null): array|bool {
        $query = "SELECT s.*, p.sp_name as provider_name 
                FROM kptv_streams s
                LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                WHERE s.u_id = ?";

        $params = [$this->current_user_id];

        if(!empty($search)) {
            $searchTerm = "%{$search}%";
            $query .= " AND (p.sp_name LIKE ? OR s_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_group LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
            $params = [...$params, ...array_fill(0, 8, $searchTerm)];
        }
        
        if ($type_id !== null) {
            $query .= " AND s.s_type_id = ?";
            $params[] = $type_id;
        }
        
        if ($active !== null) {
            $query .= " AND s.s_active = ?";
            $params[] = $active;
        }

        $query .= " ORDER BY $sort_column $sort_direction
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
                
        return $this->select_many($query, $params); // Changed this line to use $params instead of hardcoded values
    }


    /**
     * Get paginated and sorted records
     * 
     * @param int $per_page Number of records per page
     * @param int $offset The offset for pagination
     * @param string $sort_column Column to sort by
     * @param string $sort_direction Sort direction (ASC/DESC)
     * @param int|null $type_id Optional stream type to filter by
     * @return array|bool Returns paginated records or false on failure
     */
    public function getPaginated(int $per_page, int $offset, string $sort_column = 's_name', string $sort_direction = 'ASC', ?int $type_id = null, ?int $active = null): array|bool {
        $query = "SELECT s.*, p.sp_name as provider_name 
                FROM kptv_streams s
                LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                WHERE s.u_id = ?";
        
        $params = [$this->current_user_id];
        
        if ($type_id !== null) {
            $query .= " AND s.s_type_id = ?";
            $params[] = $type_id;
        }
        
        if ($active !== null) {
            $query .= " AND s.s_active = ?";
            $params[] = $active;
        }

        $query .= " ORDER BY $sort_column $sort_direction
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $this->select_many($query, $params);
    }

    /**
     * Gets all providers for the current user
     * 
     * @return array Array of provider objects
     */
    public function getAllProviders() : array {
        $query = "SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ? ORDER BY sp_name ASC";
        $result = $this->select_many($query, [$this->current_user_id]);
        return is_array($result) ? $result : [];
    }

    /**
     * Handles the post actions from the listing
     * 
     * @param array $params The post parameters
     * @return void Returns nothing
     */
    public function post_action(array $params): void {
        $theid = isset($params['id']) ? (int)$params['id'] : 0;

        // For AJAX requests
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        switch ($params['form_action']) {
            case 'create':
                $this->create($params);
                break;
                
            case 'update':
                $this->update($theid, $params);
                break;

            case 'move-to-other':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->move_to_other($id);
                    }
                }
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

            case 'update-name':
                $success = $this->update_name($theid, $params);
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => $success,
                        'message' => $success ? 'Name updated successfully' : 'Failed to update name'
                    ]);
                    exit;
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

            case 'activate-streams':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->toggleActive($id);
                    }
                }
                break;
                
        }
    }

    /**
     * Toggles the active status of a stream
     * 
     * @param int $id The ID of the stream to toggle
     * @return bool Returns true on success, false on failure
     */
    private function toggleActive(int $id): bool {
        $query = "UPDATE kptv_streams SET 
                s_active = NOT s_active
                WHERE id = ? AND u_id = ?";
        
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }

    /**
     * Creates a new stream record
     * 
     * @param array $data Associative array of stream data to insert
     * @return int|bool Returns the new stream ID on success, false on failure
     */
    public function create(array $data): int|bool {
        $query = "INSERT INTO kptv_streams (
            u_id, p_id, s_type_id, s_active, s_channel, s_name, 
            s_orig_name, s_stream_uri, s_tvg_id, s_tvg_group, 
            s_tvg_logo, s_extras
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $this->current_user_id,
            $data['p_id'] ?? 0,
            $data['s_type_id'] ?? 0,
            $data['s_active'] ?? 1,
            $data['s_channel'] ?? '0',
            $data['s_name'] ?? '',
            $data['s_orig_name'] ?? '',
            $data['s_stream_uri'] ?? '',
            $data['s_tvg_id'] ?? null,
            $data['s_tvg_group'] ?? null,
            $data['s_tvg_logo'] ?? null,
            $data['s_extras'] ?? null
        ];
        
        return $this->execute($query, $params);
    }

    /**
     * Updates an existing stream record
     * 
     * @param int $id The ID of the stream to update
     * @param array $data Associative array of fields to update
     * @return bool Returns true on success, false on failure
     */
    public function update(int $id, array $data): bool {
        $query = "UPDATE kptv_streams SET 
            p_id = ?, s_type_id = ?, s_active = ?, s_channel = ?, 
            s_name = ?, s_orig_name = ?, s_stream_uri = ?, 
            s_tvg_id = ?, s_tvg_group = ?, s_tvg_logo = ?, s_extras = ?, 
            s_updated = CURRENT_TIMESTAMP
            WHERE id = ? AND u_id = ?";
        
        $params = [
            $data['p_id'] ?? 0,
            $data['s_type_id'] ?? 0,
            $data['s_active'] ?? 1,
            $data['s_channel'] ?? '0',
            $data['s_name'] ?? '',
            $data['s_orig_name'] ?? '',
            $data['s_stream_uri'] ?? '',
            $data['s_tvg_id'] ?? null,
            $data['s_tvg_group'] ?? null,
            $data['s_tvg_logo'] ?? null,
            $data['s_extras'] ?? null,
            $id,
            $this->current_user_id
        ];
        
        return (bool)$this->execute($query, $params);
    }

    /**
     * Updates an existing stream record's Name field
     * 
     * @param int $id The ID of the stream to update
     * @param array $data Associative array of fields to update
     * @return bool Returns true on success, false on failure
     */
    public function update_name(int $id, array $data): bool {
        $query = "UPDATE kptv_streams SET 
            s_name = ? 
            WHERE id = ? AND u_id = ?";
        
        $params = [
            $data['s_name'] ?? '',
            $id,
            $this->current_user_id
        ];
        
        return (bool)$this->execute($query, $params);
    }


    /**
     * Deletes a stream record
     * 
     * @param int $id The ID of the stream to delete
     * @return bool Returns true on success, false on failure
     */
    public function delete(int $id): bool {
        $query = "DELETE FROM kptv_streams WHERE id = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }

    /**
     * Move a stream to the other type entry
     * 
     * @param int $id The ID of the entry to delete
     * @return bool Returns true on success, false on failure
     */
    public function move_to_other( int $id ) : bool {
        $query = "Call Streams_Move_To_Other( ? );";
        return ( bool ) $this -> execute( $query, [$id] );
    }

    /**
     * Move a stream to another type entry
     * 
     * @param int $id The ID of the entry to delete
     * @return bool Returns true on success, false on failure
     */
    public function move_to( int $id, int $type ) : bool {
        $query = "UPDATE kptv_streams SET 
            s_type_id = ? 
            WHERE id = ? AND u_id = ?";
        
        $params = [
            $type,
            $id,
            $this->current_user_id
        ];

        return ( bool ) $this -> execute( $query, $params );
    }

}
