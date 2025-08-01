<?php
/**
 * KPTV Stream Other CRUD Class
 * 
 * Handles all database operations for the kptv_stream_other table
 * 
 * @since 8.4
 * @package KP TV
 */
class KPTV_Stream_Other extends KPTV_Base_CRUD {
    
    protected string $table_name = 'kptv_stream_other';
    protected string $default_sort_column = 's_orig_name';
    protected array $searchable_fields = ['s_orig_name', 's_stream_uri', 's_tvg_id'];
    
    /**
     * Move entries to main streams table (transactional)
     */
    public function moveToStreams(array $ids, int $type_id): bool {
        try {
            $this->beginTransaction();
            $streams = new KPTV_Streams();
            
            foreach ($ids as $id) {
                $entry = $this->getById($id);
                if ($entry) {
                    $streams->create([
                        'p_id' => $entry->p_id,
                        's_type_id' => $type_id,
                        's_name' => $entry->s_orig_name,
                        's_orig_name' => $entry->s_orig_name,
                        's_stream_uri' => $entry->s_stream_uri,
                        's_tvg_id' => $entry->s_tvg_id,
                        's_tvg_logo' => $entry->s_tvg_logo
                    ]);
                    $this->delete($id);
                }
            }
            
            return $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }
    
    /**
     * Get entries with provider names
     */
    public function getWithProviders(
        int $per_page,
        int $offset,
        array $filters = []
    ): array|bool {
        $query = "SELECT o.*, p.sp_name as provider_name 
                 FROM {$this->table_name} o
                 LEFT JOIN kptv_stream_providers p ON o.p_id = p.id AND p.u_id = o.u_id
                 WHERE o.u_id = ?";
        
        $params = [$this->current_user_id];
        
        foreach ($filters as $field => $value) {
            $query .= " AND o.{$field} = ?";
            $params[] = $value;
        }
        
        $query .= " ORDER BY {$this->default_sort_column} 
                   {$this->default_sort_direction}
                   LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $this->select_many($query, $params);
    }
}