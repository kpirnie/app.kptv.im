<?php
/**
 * KPTV Stream Filters CRUD Class
 * 
 * @since 8.4
 * @package KP TV
 */
class KPTV_Stream_Filters extends KPTV_Base_CRUD {
    
    protected string $table_name = 'kptv_stream_filters';
    protected string $default_sort_column = 'sf_filter';
    protected array $searchable_fields = ['sf_filter'];
    protected array $required_fields = ['sf_filter', 'sf_type_id'];

    /**
     * Get total count with optional search
     */
    public function getTotalCount(array $filters = [], ?string $search = null): int {
        $query = "SELECT COUNT(id) as total FROM {$this->table_name} WHERE u_id = ?";
        $params = [$this->current_user_id];

        // Add search if provided
        if ($search && !empty($this->searchable_fields)) {
            $searchTerm = "%{$search}%";
            $query .= " AND {$this->searchable_fields[0]} LIKE ?";
            $params[] = $searchTerm;
        }

        $result = $this->select_single($query, $params);
        return (int)($result->total ?? 0);
    }
    
    /**
     * Toggle filter active status
     */
    public function toggleActive(int $id): bool {
        $query = "UPDATE {$this->table_name} 
                 SET sf_active = NOT sf_active 
                 WHERE id = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }
    
    /**
     * Bulk delete filters (transactional)
     */
    public function deleteMultiple(array $ids): bool {
        try {
            $this->beginTransaction();
            
            foreach ($ids as $id) {
                parent::delete($id);
            }
            
            return $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }
    
    /**
     * Get active filters by type
     */
    public function getActiveByType(int $type_id): array {
        $query = "SELECT * FROM {$this->table_name} 
                 WHERE u_id = ? AND sf_type_id = ? AND sf_active = 1";
        $result = $this->select_many($query, [$this->current_user_id, $type_id]);
        return is_array($result) ? $result : [];
    }
}