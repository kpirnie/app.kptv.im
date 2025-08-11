<?php
/**
 * KPTV Streams CRUD Class
 * 
 * Handles all database operations for the kptv_streams table
 * Provides complete CRUD functionality for IPTV streams
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class KPTV_Streams extends KPTV_Base {
    
    protected string $table_name = 'kptv_streams';
    protected array $searchable_fields = ['s_name', 's_orig_name', 's_stream_uri', 's_tvg_id', 's_tvg_group', 's_tvg_logo', 's_extras'];
    protected string $default_sort_column = 's_name';

    protected function buildSelectQuery( array $fields = []): string {
        return "SELECT s.*, p.sp_name as provider_name 
                FROM {$this->table_name} s
                LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                WHERE s.u_id = ?";
    }

    public function searchPaginated(string $search, int $per_page, int $offset, string $sort_column = 's_name', string $sort_direction = 'ASC', array $filters = [], array $fields = []): array|bool {
        
        $query = $this -> buildSelectQuery( fields: $fields);

        $params = [$this->current_user_id];

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query .= " AND (p.sp_name LIKE ? OR s_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_group LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
            $params = [...$params, ...array_fill(0, 8, $searchTerm)];
        }
        
        // Handle type_id filter
        if (isset($filters['type_id']) && $filters['type_id'] !== null) {
            $query .= " AND s.s_type_id = ?";
            $params[] = $filters['type_id'];
        }
        
        // Handle active filter
        if (isset($filters['active']) && $filters['active'] !== null) {
            $query .= " AND s.s_active = ?";
            $params[] = $filters['active'];
        }

        $query .= " ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
                
        return $this->query($query)->bind($params)->many()->fetch();
    }

    public function getPaginated(int $per_page, int $offset, string $sort_column = 's_name', string $sort_direction = 'ASC', array $filters = [], array $fields = []): array|bool {
        $query = $this->buildSelectQuery( fields: $fields);
        
        $params = [$this->current_user_id];

        // Handle type_id filter
        if (isset($filters['type_id'])) {
            $query .= " AND s.s_type_id = ?";
            $params[] = $filters['type_id'];
            
        }
        
        // Handle active filter
        if (isset($filters['active']) && $filters['active'] !== null) {
            $query .= " AND s.s_active = ?";
            $params[] = $filters['active'];
        }

        $query .= " ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        return $this->query($query)->bind($params)->many()->fetch();
    }

    public function getTotalCount(?string $search = null, array $filters = []): int {
        // Handle the custom parameters for streams - convert to filters array
        $type_id = $filters['type_id'] ?? null;
        $active = $filters['active'] ?? null;
        
        $query = "SELECT COUNT(s.id) as total 
                  FROM {$this->table_name} s
                  LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                  WHERE s.u_id = ?";
        $params = [$this->current_user_id];

        if (!empty($search)) {
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
        
        $result = $this->query($query)->bind($params)->single()->fetch();
        return (int)$result->total ?? 0;
    }

    // Convenience method to maintain backward compatibility
    public function getTotalCountByType(?int $type_id = null, ?int $active = null, ?string $search = null): int {
        $filters = [];
        if ($type_id !== null) $filters['type_id'] = $type_id;
        if ($active !== null) $filters['active'] = $active;
        
        return $this->getTotalCount($search, $filters);
    }

    public function getAllProviders(): array {
        $query = "SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ? ORDER BY sp_name ASC";
        $result = $this->query($query)->bind([$this->current_user_id])->many()->fetch();
        return is_array($result) ? $result : [];
    }

    protected function create(array $data): int|bool {
        $query = "INSERT INTO {$this->table_name} (
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
        
        return $this->query($query)->bind($params)->execute();
    }

    protected function update(int $id, array $data): bool {
        $query = "UPDATE {$this->table_name} SET 
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
        
        return (bool)$this->query($query)->bind($params)->execute();
    }

    public function update_name(int $id, array $data): bool {
        $query = "UPDATE {$this->table_name} SET s_name = ? WHERE id = ? AND u_id = ?";
        $params = [$data['s_name'] ?? '', $id, $this->current_user_id];
        return (bool)$this->query($query)->bind($params)->execute();
    }

    public function update_channel(int $id, array $data): bool {
        $query = "UPDATE {$this->table_name} SET s_channel = ? WHERE id = ? AND u_id = ?";
        $params = [$data['s_channel'] ?? '', $id, $this->current_user_id];
        return (bool)$this->query($query)->bind($params)->execute();
    }

    private function move_to_other(int $id): bool {
        $query = "CALL Streams_Move_To_Other(?)";
        return (bool)$this->query($query)->bind([$id])->execute();
    }

    private function move_to(int $id, int $type): bool {
        $query = "UPDATE {$this->table_name} SET s_type_id = ? WHERE id = ? AND u_id = ?";
        return (bool)$this->query($query)->bind([$type, $id, $this->current_user_id])->execute();
    }

    public function post_action(array $params): void {

        $uri = parse_url( ( KPT::get_user_uri( ) ), PHP_URL_PATH ) ?? '/';

        $theid = isset($params['id']) ? (int)$params['id'] : 0;
        
        switch ($params['form_action']) {
            case 'create':
                $this->create($params);
                KPT::message_with_redirect( $uri, 'success', 'Stream created successfully.');
                break;
                
            case 'update':
                $this->update($theid, $params);
                KPT::message_with_redirect( $uri, 'success', 'Stream updated successfully.');
                break;

            case 'move-to-other':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->move_to_other($id);
                    }
                    KPT::message_with_redirect( $uri, 'success', 'Streams moved successfully.');
                }
                break;

            case 'move-to-live':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->move_to($id, 0);
                    }
                    KPT::message_with_redirect( $uri, 'success', 'Streams moved successfully.');
                }
                break;

            case 'move-to-series':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->move_to($id, 5);
                    }
                    KPT::message_with_redirect( $uri, 'success', 'Streams moved successfully.');
                }
                break;

            case 'update-name':
                $success = $this->update_name($theid, $params);
                $this->handleResponse($success, 'Name updated successfully', 'Failed to update name');
                break;

            case 'update-channel':
                $success = $this->update_channel($theid, $params);
                $this->handleResponse($success, 'Channel updated successfully', 'Failed to update channel');
                break;
                
            case 'delete':
                $this->delete($theid);
                KPT::message_with_redirect( $uri, 'success', 'Stream deleted successfully.');
                break;
                
            case 'delete-multiple':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->delete($id);
                    }
                    KPT::message_with_redirect( $uri, 'success', 'Streams deleted successfully.');
                }
                break;
                
            case 'toggle-active':
                $success = $this->toggleActive($theid, 's_active');
                $this->handleResponse($success, 'In/Activated successfully', 'Failed to activate');
                break;

            case 'activate-streams':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->toggleActive($id, 's_active');
                    }
                    KPT::message_with_redirect( $uri, 'success', 'Streams activation updated successfully.');
                }
                break;
            default:
                KPT::message_with_redirect( $uri, 'danger', 'Invalid action.' );
                break;
        }
    }
}