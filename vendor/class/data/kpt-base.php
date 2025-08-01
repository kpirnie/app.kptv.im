<?php
/**
 * KPTV Base CRUD Class
 * 
 * Provides core CRUD functionality for all stream-related tables
 * 
 * @since 8.4
 * @package KP TV
 */
abstract class KPTV_Base_CRUD extends KPT_DB {
    
    /** @var int $current_user_id The authenticated user's ID */
    protected int $current_user_id;
    
    /** @var string $table_name Database table name */
    protected string $table_name;
    
    /** @var string $default_sort_column Default column for sorting */
    protected string $default_sort_column = 'id';
    
    /** @var string $default_sort_direction Default sort direction */
    protected string $default_sort_direction = 'ASC';
    
    /** @var array $searchable_fields Fields to include in search */
    protected array $searchable_fields = [];
    
    /** @var array $required_fields Fields required for create/update */
    protected array $required_fields = [];
    
    public function __construct() {
        parent::__construct();
        $this->current_user_id = (KPT_User::get_current_user()->id) ?? 0;
    }

    // ========================
    // Transaction Methods
    // ========================
    
    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool {
        return $this->execute("START TRANSACTION");
    }
    
    /**
     * Commit a transaction
     */
    public function commit(): bool {
        return $this->execute("COMMIT");
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback(): bool {
        return $this->execute("ROLLBACK");
    }

    // ========================
    // Core CRUD Methods
    // ========================

    /**
     * Get all providers for dropdowns
     */
    public function getAllProviders(): array {
        $query = "SELECT id, sp_name FROM kptv_stream_providers 
                 WHERE u_id = ? ORDER BY sp_name ASC";
        $result = $this->select_many($query, [$this->current_user_id]);
        return is_array($result) ? $result : [];
    }

    /**
     * Get total count of records (with optional filters)
     * 
     * @param array $filters [field => value]
     * @param string|null $search Search term
     * @return int
     */
    public function getTotalCount(array $filters = [], ?string $search = null): int {
        $query = "SELECT COUNT(id) as total FROM {$this->table_name} WHERE u_id = ?";
        $params = [$this->current_user_id];

        // Add filters
        foreach ($filters as $field => $value) {
            $query .= " AND {$field} = ?";
            $params[] = $value;
        }

        // Add search
        if ($search && !empty($this->searchable_fields)) {
            $searchTerm = "%{$search}%";
            $conditions = [];
            foreach ($this->searchable_fields as $field) {
                $conditions[] = "{$field} LIKE ?";
                $params[] = $searchTerm;
            }
            $query .= " AND (" . implode(" OR ", $conditions) . ")";
        }

        $result = $this->select_single($query, $params);
        return (int)($result->total ?? 0);
    }

    /**
     * Get paginated records
     * 
     * @param int $per_page
     * @param int $offset
     * @param array $filters [field => value]
     * @param string|null $sort_column
     * @param string|null $sort_direction
     * @return array|bool
     */
    public function getPaginated(
        int $per_page,
        int $offset,
        array $filters = [],
        ?string $sort_column = null,
        ?string $sort_direction = null
    ): array|bool {
        $sort_col = $sort_column ?: $this->default_sort_column;
        $sort_dir = $sort_direction ?: $this->default_sort_direction;

        $query = "SELECT * FROM {$this->table_name} WHERE u_id = ?";
        $params = [$this->current_user_id];

        // Add filters
        foreach ($filters as $field => $value) {
            $query .= " AND {$field} = ?";
            $params[] = $value;
        }

        $query .= " ORDER BY {$sort_col} {$sort_dir} LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;

        return $this->select_many($query, $params);
    }

    /**
     * Search with pagination
     */
    public function searchPaginated(
        string $search,
        int $per_page,
        int $offset,
        array $filters = [],
        ?string $sort_column = null,
        ?string $sort_direction = null
    ): array|bool {
        if (empty($this->searchable_fields)) {
            return false;
        }

        $query = "SELECT * FROM {$this->table_name} WHERE u_id = ?";
        $params = [$this->current_user_id];

        // Add filters
        foreach ($filters as $field => $value) {
            $query .= " AND {$field} = ?";
            $params[] = $value;
        }

        // Add search conditions
        $searchTerm = "%{$search}%";
        $conditions = [];
        foreach ($this->searchable_fields as $field) {
            $conditions[] = "{$field} LIKE ?";
            $params[] = $searchTerm;
        }
        $query .= " AND (" . implode(" OR ", $conditions) . ")";

        // Add sorting
        $sort_col = $sort_column ?: $this->default_sort_column;
        $sort_dir = $sort_direction ?: $this->default_sort_direction;
        $query .= " ORDER BY {$sort_col} {$sort_dir} LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;

        return $this->select_many($query, $params);
    }

    /**
     * Create a new record
     * 
     * @param array $data
     * @return int|bool
     */
    public function create(array $data): int|bool {
        // Validate required fields
        foreach ($this->required_fields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        $fields = array_keys($data);
        $placeholders = implode(", ", array_fill(0, count($fields), "?"));
        $query = "INSERT INTO {$this->table_name} (" . implode(", ", $fields) . ") 
                  VALUES ({$placeholders})";

        return $this->execute($query, array_values($data));
    }

    /**
     * Update a record
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $updates = [];
        $params = [];
        foreach ($data as $field => $value) {
            $updates[] = "{$field} = ?";
            $params[] = $value;
        }
        $params[] = $id;
        $params[] = $this->current_user_id;

        $query = "UPDATE {$this->table_name} 
                  SET " . implode(", ", $updates) . " 
                  WHERE id = ? AND u_id = ?";

        return (bool)$this->execute($query, $params);
    }

    /**
     * Delete a record
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        $query = "DELETE FROM {$this->table_name} WHERE id = ? AND u_id = ?";
        return (bool)$this->execute($query, [$id, $this->current_user_id]);
    }
}
