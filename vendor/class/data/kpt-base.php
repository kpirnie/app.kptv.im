<?php
/**
 * KPTV Base CRUD Class
 * 
 * Base class providing common CRUD functionality for all KPTV classes
 * 
 * @since 8.4
 * @package KP TV
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

abstract class KPTV_Base extends KPT_DB {
    
    /** @var int $current_user_id The ID of the currently authenticated user */
    protected int $current_user_id;
    
    /** @var string $table_name The name of the database table */
    protected string $table_name;
    
    /** @var string $primary_key The primary key column name */
    protected string $primary_key = 'id';
    
    /** @var array $searchable_fields Fields that can be searched */
    protected array $searchable_fields = [];
    
    /** @var string $default_sort_column Default column for sorting */
    protected string $default_sort_column = 'id';
    
    public function __construct() {
        parent::__construct();
        $this->current_user_id = (KPT_User::get_current_user()->id) ?? 0;
    }

    /**
     * Get total count of records for the current user
     * 
     * @param string|null $search Optional search term
     * @param array $filters Additional filters
     * @return int Total number of records
     */
    public function getTotalCount(?string $search = null, array $filters = []): int {
        $query = "SELECT COUNT({$this->primary_key}) as total FROM {$this->table_name} WHERE u_id = ?";
        $params = [$this->current_user_id];

        // Add search conditions
        if (!empty($search) && !empty($this->searchable_fields)) {
            $searchConditions = [];
            $searchTerm = "%{$search}%";
            
            foreach ($this->searchable_fields as $field) {
                $searchConditions[] = "{$field} LIKE ?";
                $params[] = $searchTerm;
            }
            
            $query .= " AND (" . implode(" OR ", $searchConditions) . ")";
        }

        // Add custom filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query .= " AND {$field} = ?";
                $params[] = $value;
            }
        }

        $result = $this->select_single($query, $params);
        return (int)$result->total ?? 0;
    }

    /**
     * Get paginated records
     * 
     * @param int $per_page Number of records per page
     * @param int $offset The offset for pagination
     * @param string $sort_column Column to sort by
     * @param string $sort_direction Sort direction (ASC/DESC)
     * @param array $filters Additional filters
     * @return array|bool Returns paginated records or false on failure
     */
    public function getPaginated(int $per_page, int $offset, string $sort_column = '', string $sort_direction = 'ASC', array $filters = []): array|bool {
        $sort_column = $sort_column ?: $this->default_sort_column;
        
        $query = $this->buildSelectQuery($filters);
        
        // Add filter conditions
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query .= " AND {$field} = ?";
            }
        }
        
        $query .= " ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
        
        $params = $this->buildSelectParams($filters);
        
        // Add filter parameters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $params[] = $value;
            }
        }
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $this->select_many($query, $params);
    }

    /**
     * Search paginated records
     * 
     * @param string $search The search term
     * @param int $per_page Number of records per page
     * @param int $offset The offset for pagination
     * @param string $sort_column Column to sort by
     * @param string $sort_direction Sort direction (ASC/DESC)
     * @param array $filters Additional filters
     * @return array|bool Returns matching records or false if none found
     */
    public function searchPaginated(string $search, int $per_page, int $offset, string $sort_column = '', string $sort_direction = 'ASC', array $filters = []): array|bool {
        $sort_column = $sort_column ?: $this->default_sort_column;
        
        $query = $this->buildSelectQuery($filters);
        
        // Add search conditions
        if (!empty($this->searchable_fields)) {
            $searchConditions = [];
            foreach ($this->searchable_fields as $field) {
                $searchConditions[] = "{$field} LIKE ?";
            }
            $query .= " AND (" . implode(" OR ", $searchConditions) . ")";
        }
        
        // Add filter conditions
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query .= " AND {$field} = ?";
            }
        }
        
        $query .= " ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
        
        $params = $this->buildSelectParams($filters);
        
        // Add search parameters
        if (!empty($this->searchable_fields)) {
            $searchTerm = "%{$search}%";
            foreach ($this->searchable_fields as $field) {
                $params[] = $searchTerm;
            }
        }
        
        // Add filter parameters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $params[] = $value;
            }
        }
        
        $params[] = $per_page;
        $params[] = $offset;
        
        return $this->select_many($query, $params);
    }

    /**
     * Toggle active status
     * 
     * @param int $id The ID of the record to toggle
     * @param string $active_field The name of the active field
     * @return bool Returns true on success, false on failure
     */
    protected function toggleActive(int $id, string $active_field = 'active'): bool {
        $query = "UPDATE {$this->table_name} SET {$active_field} = NOT {$active_field} WHERE {$this->primary_key} = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }

    /**
     * Delete a record
     * 
     * @param int $id The ID of the record to delete
     * @return bool Returns true on success, false on failure
     */
    protected function delete(int $id): bool {
        $query = "DELETE FROM {$this->table_name} WHERE {$this->primary_key} = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }

    /**
     * Handle AJAX response
     * 
     * @param bool $success Success status
     * @param string $success_message Success message
     * @param string $error_message Error message
     */
    protected function handleAjaxResponse(bool $success, string $success_message, string $error_message): void {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? $success_message : $error_message
            ]);
            exit;
        }
    }

    /**
     * Build select query - can be overridden by child classes
     */
    protected function buildSelectQuery(array $filters = [], bool $includeSearch = false): string {
        return "SELECT * FROM {$this->table_name} WHERE u_id = ?";
    }

    /**
     * Build select parameters - can be overridden by child classes
     */
    protected function buildSelectParams(array $filters = []): array {
        return [$this->current_user_id];
    }

    /**
     * Abstract methods that must be implemented by child classes
     */
    abstract protected function create(array $data): int|bool;
    abstract protected function update(int $id, array $data): bool;
    abstract public function post_action(array $params): void;
}