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

class KPTV_Stream_Filters extends KPTV_Base {
    
    protected string $table_name = 'kptv_stream_filters';
    protected array $searchable_fields = ['sf_filter'];
    protected string $default_sort_column = 'sf_type_id';

    /**
     * Creates a new filter record
     */
    protected function create(array $data): int|bool {
        $query = "INSERT INTO {$this->table_name} (u_id, sf_active, sf_type_id, sf_filter) VALUES (?, ?, ?, ?)";
        
        $params = [
            $this->current_user_id,
            $data['sf_active'] ?? 1,
            $data['sf_type_id'] ?? 1,
            $data['sf_filter'] ?? '',
        ];
        
        return $this->execute($query, $params);
    }

    /**
     * Updates an existing filter record
     */
    protected function update(int $id, array $data): bool {
        $query = "UPDATE {$this->table_name} SET sf_active = ?, sf_type_id = ?, sf_filter = ?, sf_updated = CURRENT_TIMESTAMP WHERE id = ? AND u_id = ?";
        
        $params = [
            $data['sf_active'] ?? 1,
            $data['sf_type_id'] ?? 1,
            $data['sf_filter'] ?? '',
            $id,
            $this->current_user_id
        ];
        
        return (bool)$this->execute($query, $params);
    }

    public function post_action(array $params): void {
        $theid = isset($params['id']) ? (int)$params['id'] : 0;
        
        switch ($params['form_action']) {
            case 'create':
                $this->create($params);
                break;
                
            case 'update':
                $this->update($theid, $params);
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
                $success = $this->toggleActive($theid, 'sf_active');
                $this->handleAjaxResponse($success, 'In/Activated successfully', 'Failed to activate');
                break;
        }
    }
}