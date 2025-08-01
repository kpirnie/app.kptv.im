<?php
/**
 * KPTV Streams CRUD Class
 * 
 * Handles all database operations for the kptv_streams table
 * 
 * @since 8.4
 * @package KP TV
 */
class KPTV_Streams extends KPTV_Base_CRUD {
    
    protected string $table_name = 'kptv_streams';
    protected string $default_sort_column = 's_name';
    protected array $searchable_fields = ['s_name', 's_orig_name', 's_stream_uri', 's_tvg_id', 's_tvg_group'];
    protected array $required_fields = ['s_name', 's_stream_uri'];

    /**
     * Get total count with type and active filters
     */
    public function getTotalCount(array $filters = [], ?string $search = null): int {
        $query = "SELECT COUNT(s.id) as total 
                 FROM {$this->table_name} s
                 LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                 WHERE s.u_id = ?";
        $params = [$this->current_user_id];

        // Add filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query .= " AND s.{$field} = ?";
                $params[] = $value;
            }
        }

        // Add search
        if ($search && !empty($this->searchable_fields)) {
            $searchTerm = "%{$search}%";
            $conditions = [];
            foreach ($this->searchable_fields as $field) {
                if (strpos($field, '.') === false) {
                    $field = "s.{$field}";
                }
                $conditions[] = "{$field} LIKE ?";
                $params[] = $searchTerm;
            }
            $query .= " AND (" . implode(" OR ", $conditions) . ")";
        }

        $result = $this->select_single($query, $params);
        return (int)($result->total ?? 0);
    }
    
    /**
     * Get streams with provider information
     */
    public function getWithProviders(
        int $per_page,
        int $offset,
        array $filters = [],
        string $sort_column = 's_name',
        string $sort_direction = 'ASC'
    ): array|bool {
        $query = "SELECT s.*, p.sp_name as provider_name 
                 FROM {$this->table_name} s
                 LEFT JOIN kptv_stream_providers p ON s.p_id = p.id AND p.u_id = s.u_id
                 WHERE s.u_id = ?";
        
        $params = [$this->current_user_id];
        
        foreach ($filters as $field => $value) {
            $query .= " AND s.{$field} = ?";
            $params[] = $value;
        }
        
        $query .= " ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        return $this->select_many($query, $params);
    }
    
    /**
     * Toggle stream active status
     */
    public function toggleActive(int $id): bool {
        $query = "UPDATE {$this->table_name} 
                 SET s_active = NOT s_active 
                 WHERE id = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }
    
    /**
     * Move multiple streams to another category (transactional)
     */
    public function moveToCategory(array $ids, int $new_type_id): bool {
        try {
            $this->beginTransaction();
            
            foreach ($ids as $id) {
                $this->update($id, ['s_type_id' => $new_type_id]);
            }
            
            return $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }
    
    /**
     * Move stream to "other" table (transactional)
     */
    public function moveToOther(int $id): bool {
        try {
            $this->beginTransaction();
            
            // Get stream data
            $stream = $this->getById($id);
            if (!$stream) {
                throw new Exception("Stream not found");
            }
            
            // Insert into other table
            $other = new KPTV_Stream_Other();
            $other->create([
                'p_id' => $stream->p_id,
                's_orig_name' => $stream->s_name,
                's_stream_uri' => $stream->s_stream_uri,
                's_tvg_id' => $stream->s_tvg_id,
                's_tvg_logo' => $stream->s_tvg_logo
            ]);
            
            // Delete from streams
            $this->delete($id);
            
            return $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }
}
