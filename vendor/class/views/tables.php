<?php
/**
 * Table Renderer Component
 * 
 * Separates table rendering logic for better modularity
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class TableRenderer {
    
    private array $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    /**
     * Render complete table
     */
    public function render(array $data): string {
        ob_start();
        
        echo '<div class="uk-overflow-auto">';
        echo '<table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">';
        
        $this->renderHeader($data);
        $this->renderBody($data);
        $this->renderFooter($data);
        
        echo '</table>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Render table header
     */
    private function renderHeader(array $data): void {
        echo '<thead><tr>';
        
        if ($this->config['show_checkbox'] ?? true) {
            echo '<th width="5px">';
            echo '<input type="checkbox" id="select-all" class="uk-checkbox select-all">';
            echo '</th>';
        }
        
        foreach ($this->config['columns'] ?? [] as $column) {
            $this->renderColumnHeader($column, $data);
        }
        
        if ($this->config['show_actions'] ?? true) {
            echo '<th>Actions</th>';
        }
        
        echo '</tr></thead>';
    }
    
    /**
     * Render column header
     */
    private function renderColumnHeader(array $column, array $data): void {
        $sort_column = $data['sort_column'] ?? '';
        $sort_direction = $data['sort_direction'] ?? 'ASC';
        
        $classes = [];
        if ($column['sortable'] ?? false) {
            $classes[] = 'sortable';
        }
        if ($column['responsive'] ?? false) {
            $classes[] = $column['responsive'];
        }
        
        $class_str = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        $data_attr = ($column['sortable'] ?? false) ? ' data-column="' . $column['key'] . '"' : '';
        
        echo '<th' . $class_str . $data_attr . '>';
        echo htmlspecialchars($column['label']);
        
        if (($column['sortable'] ?? false) && $sort_column === $column['key']) {
            $icon = strtolower($sort_direction) === 'asc' ? 'up' : 'down';
            echo '<span class="uk-align-right" uk-icon="icon: chevron-' . $icon . '"></span>';
        }
        
        echo '</th>';
    }
    
    /**
     * Render table body
     */
    private function renderBody(array $data): void {
        echo '<tbody>';
        
        $records = $data['records'] ?? [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $this->renderRow($record, $data);
            }
        } else {
            $this->renderEmptyRow();
        }
        
        echo '</tbody>';
    }
    
    /**
     * Render table row
     */
    private function renderRow(object $record, array $data): void {
        echo '<tr>';
        
        if ($this->config['show_checkbox'] ?? true) {
            echo '<td>';
            echo '<input type="checkbox" name="ids[]" value="' . $record->id . '" class="uk-checkbox record-checkbox">';
            echo '</td>';
        }
        
        foreach ($this->config['columns'] ?? [] as $column) {
            $this->renderCell($record, $column, $data);
        }
        
        if ($this->config['show_actions'] ?? true) {
            $this->renderActionCell($record, $data);
        }
        
        echo '</tr>';
    }
    
    /**
     * Render table cell
     */
    private function renderCell(object $record, array $column, array $data): void {
        $classes = [];
        if ($column['responsive'] ?? false) {
            $classes[] = $column['responsive'];
        }
        if ($column['truncate'] ?? false) {
            $classes[] = 'truncate';
        }
        
        $class_str = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        
        echo '<td' . $class_str . '>';
        
        if (isset($column['renderer']) && is_callable($column['renderer'])) {
            echo $column['renderer']($record, $data);
        } else {
            $value = $record->{$column['key']} ?? '';
            echo htmlspecialchars($value);
        }
        
        echo '</td>';
    }
    
    /**
     * Render action cell
     */
    private function renderActionCell(object $record, array $data): void {
        echo '<td class="action-cell">';
        echo '<div class="uk-button-group">';
        
        foreach ($this->config['actions'] ?? [] as $action) {
            $this->renderAction($record, $action, $data);
        }
        
        echo '</div>';
        echo '</td>';
    }
    
    /**
     * Render action button
     */
    private function renderAction(object $record, array $action, array $data): void {
        // Handle dynamic values
        $href = $this->resolveValue($action['href'] ?? '#', $record, $data);
        $icon = $this->resolveValue($action['icon'] ?? 'info', $record, $data);
        $tooltip = $this->resolveValue($action['tooltip'] ?? '', $record, $data);
        $class = $this->resolveValue($action['class'] ?? 'uk-icon-link', $record, $data);
        $attributes = $action['attributes'] ?? '';
        
        // Ensure href is a string
        if (!is_string($href)) {
            $href = '#';
        }
        
        // Ensure icon is a string
        if (!is_string($icon)) {
            $icon = 'info';
        }
        
        // Ensure tooltip is a string
        if (!is_string($tooltip)) {
            $tooltip = '';
        }
        
        // Ensure class is a string
        if (!is_string($class)) {
            $class = 'uk-icon-link';
        }
        
        // Replace placeholders in href and tooltip
        $href = str_replace('{id}', (string)$record->id, $href);
        $tooltip = str_replace('{id}', (string)$record->id, $tooltip);
        
        // Check condition if exists
        if (isset($action['condition']) && is_callable($action['condition'])) {
            if (!$action['condition']($record, $data)) {
                return;
            }
        }
        
        echo '<a href="' . htmlspecialchars($href) . '" ';
        echo 'class="' . htmlspecialchars($class) . '" ';
        echo 'uk-icon="' . htmlspecialchars($icon) . '" ';
        if ($tooltip) {
            echo 'uk-tooltip="' . htmlspecialchars($tooltip) . '" ';
        }
        echo $attributes;
        echo '></a>';
    }
    
    /**
     * Resolve dynamic values (callable or static)
     */
    private function resolveValue($value, object $record, array $data) {
        if (is_callable($value)) {
            try {
                $result = $value($record, $data);
                // Ensure we always return a string for href/icon/tooltip/class values
                return is_string($result) ? $result : (string)$result;
            } catch (Throwable $e) {
                error_log("Error resolving dynamic value: " . $e->getMessage());
                return '#'; // Safe fallback
            }
        }
        return $value;
    }
    
    /**
     * Render empty row
     */
    private function renderEmptyRow(): void {
        $colspan = count($this->config['columns'] ?? []);
        if ($this->config['show_checkbox'] ?? true) $colspan++;
        if ($this->config['show_actions'] ?? true) $colspan++;
        
        echo '<tr>';
        echo '<td colspan="' . $colspan . '" class="uk-text-center">';
        echo htmlspecialchars($this->config['empty_message'] ?? 'No records found');
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Render table footer
     */
    private function renderFooter(array $data): void {
        echo '<tfoot>';
        $this->renderHeader($data); // Reuse header for footer
        echo '</tfoot>';
    }
}