<?php
/**
 * KPTV Stream Providers CRUD Class
 * 
 * Handles all database operations for the kptv_stream_providers table
 * 
 * @since 8.4
 * @package KP TV
 */
class KPTV_Stream_Providers extends KPTV_Base_CRUD {
    
    protected string $table_name = 'kptv_stream_providers';
    protected string $default_sort_column = 'sp_name';
    protected array $searchable_fields = ['sp_name', 'sp_domain'];
    protected array $required_fields = ['sp_name', 'sp_type'];
    
    /**
     * Delete provider and all associated streams (transactional)
     */
    public function delete(int $id): bool {
        try {
            $this->beginTransaction();
            
            // Delete associated streams
            $streams = new KPTV_Streams();
            $streams->deleteWhere(['p_id' => $id]);
            
            // Delete provider
            parent::delete($id);
            
            return $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }
    
    /**
     * Toggle provider's active filtering status
     */
    public function toggleFiltering(int $id): bool {
        $query = "UPDATE {$this->table_name} 
                 SET sp_should_filter = NOT sp_should_filter 
                 WHERE id = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }
    
    /**
     * Get all providers for dropdowns
     */
    public function getAllForSelect(): array {
        $query = "SELECT id, sp_name FROM {$this->table_name} 
                 WHERE u_id = ? ORDER BY sp_name";
        $result = $this->select_many($query, [$this->current_user_id]);
        return is_array($result) ? $result : [];
    }
}
