<?php
/**
 * KPTV Stream Other CRUD Class
 * 
 * Handles all database operations for the kptv_stream_other table
 * Manages additional/alternative IPTV stream entries
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class KPTV_Stream_Other extends KPTV_Base {
    
    protected string $table_name = 'kptv_stream_other';
    protected array $searchable_fields = ['s_orig_name', 's_stream_uri', 's_tvg_id', 's_tvg_logo', 's_extras'];
    protected string $default_sort_column = 's_orig_name';

    protected function buildSelectQuery( array $fields = []): string {
        return "SELECT so.*, sp.sp_name as provider_name 
                FROM {$this->table_name} so
                LEFT JOIN kptv_stream_providers sp ON so.p_id = sp.id
                WHERE so.u_id = ?";
    }

    public function getAllProviders(): array|bool {
        $query = "SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ? ORDER BY sp_name";
        return $this->query($query)->bind([$this->current_user_id])->many()->fetch();
    }

    public function getTotalCount(?string $search = null, array $filters = []): int {
        // Override parent to maintain original method signature
        return parent::getTotalCount($search, $filters);
    }

    public function searchPaginated(string $search, int $per_page, int $offset, string $sort_column = 's_orig_name', string $sort_direction = 'ASC', array $filters = [], array $fields = []): array|bool {
        $query = "SELECT s.*, p.sp_name as provider_name 
                FROM {$this->table_name} s
                LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                WHERE s.u_id = ?";

        $params = [$this->current_user_id];

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query .= " AND (p.sp_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
            $params = [...$params, ...array_fill(0, 6, $searchTerm)];
        }

        $query .= " ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
                
        return $this->query($query)->bind($params)->many()->fetch();
    }

    public function getTotalCountWithProvider(?string $search = null, array $filters = []): int {
        $query = "SELECT COUNT(a.id) as total FROM {$this->table_name} a
                  LEFT JOIN kptv_stream_providers p ON a.p_id = p.id AND p.u_id = a.u_id
                  WHERE a.u_id = ?";
        $params = [$this->current_user_id];

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query .= " AND (p.sp_name LIKE ? OR s_orig_name LIKE ? OR s_stream_uri LIKE ? OR s_tvg_id LIKE ? OR s_tvg_logo LIKE ? OR s_extras LIKE ?)";
            $params = [...$params, ...array_fill(0, 6, $searchTerm)];
        }

        $result = $this->query($query)->bind($params)->single()->fetch();
        return (int)$result->total ?? 0;
    }

    protected function create(array $data): int|bool {
        return false;
    }

    protected function update(int $id, array $data): bool {
        return false;
    }

    private function move_to(int $id, int $type): bool {
        $query = "CALL Streams_Move_From_Other(?, ?)";
        return (bool)$this->query($query)->bind([$id, $type])->execute();
    }

    public function post_action(array $params): void {
        $theid = isset($params['id']) ? (int)$params['id'] : 0;

        $uri = parse_url( ( KPT::get_user_uri( ) ), PHP_URL_PATH ) ?? '/';
        
        switch ($params['form_action']) {
            case 'move-to-live':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->move_to($id, 0);
                    }
                    KPT::message_with_redirect($uri, 'success', 'Other Stream moved successfully.');
                }
                break;
                
            case 'move-to-series':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->move_to($id, 5);
                    }
                    KPT::message_with_redirect($uri, 'success', 'Other Stream moved successfully.');
                }
                break;
                
            case 'delete':
                $this->delete($theid);
                KPT::message_with_redirect($uri, 'success', 'Other Stream deleted successfully.');
                break;
                
            case 'delete-multiple':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->delete($id);
                    }
                    KPT::message_with_redirect($uri, 'success', 'Other Streams deleted successfully.');
                }
                break;
            default:
                KPT::message_with_redirect($uri, 'danger', 'Invalid action.');
                break;
        }
    }
}
