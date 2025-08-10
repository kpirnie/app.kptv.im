<?php
/**
 * Base Table View Component
 * 
 * @since 8.4
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

class BaseTableView {
    
    // setup the needed properties
    protected string $title = '';
    protected string $base_url = '';
    protected array $table_config = [];
    protected array $modal_config = [];
    protected ?object $crud_instance = null;
    
    // fire up the class and set the properties values
    public function __construct( string $title, string $base_url, array $config = [] ) {
        $this -> title = $title;
        $this -> base_url = $base_url;
        $this -> table_config = $config['table'] ?? [];
        $this -> modal_config = $config['modals'] ?? [];
        $this -> crud_instance = $config['crud_instance'] ?? null;
    }
    
    /**
     * Render the complete view
     */
    public function render( array $data = [] ) : void {
        $this -> renderHeader( $data );
        $this -> renderNavigation( $data );
        $this -> renderTable( $data );
        $this -> renderNavigation( $data );
        $this -> renderModals( $data );
        $this -> renderFooter( );
    }
    
    /**
     * Render page header
     */
    protected function renderHeader( array $data ) : void {
        KPT::pull_header( );
        echo '<div class="uk-container">';
        echo '<h2 class="me uk-heading-divider">' . htmlspecialchars( $this -> title ) . '</h2>';
        
        if ( isset( $data['error'] ) ) {
            echo '<div class="uk-alert-danger" uk-alert>';
            echo '<a class="uk-alert-close" uk-close></a>';
            echo '<p>' . htmlspecialchars( $data['error'] ) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render navigation
     */
    protected function renderNavigation( array $data ) : void {
        KPT::include_view('common/navigation', [
            'page' => $data['page'] ?? 1,
            'total_pages' => $data['total_pages'] ?? 1,
            'per_page' => $data['per_page'] ?? 25,
            'base_url' => $this->base_url,
            'max_visible_links' => 5,
            'show_first_last' => true,
            'search_term' => $data['search_term'] ?? '',
        ] );
    }
    
    /**
     * Render table
     */
    protected function renderTable( array $data ): void {
        echo '<div class="uk-overflow-auto">';
        echo '<table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">';
        
        $this->renderTableHeader($data);
        $this->renderTableBody($data);
        $this->renderTableFooter($data);
        
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Render table header
     */
    protected function renderTableHeader(array $data): void {
        echo '<thead><tr>';
        
        if ($this->table_config['show_checkbox'] ?? true) {
            echo '<th style="width:5px !important;">';
            echo '<input type="checkbox" id="select-all" class="uk-checkbox select-all">';
            echo '</th>';
        }
        
        foreach ($this->table_config['columns'] ?? [] as $column) {
            $this->renderColumnHeader($column, $data);
        }
        
        if ($this->table_config['show_actions'] ?? true) {
            echo '<th>Actions</th>';
        }
        
        echo '</tr></thead>';
    }
    
    /**
     * Render column header
     */
    protected function renderColumnHeader(array $column, array $data): void {
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
    protected function renderTableBody(array $data): void {
        echo '<tbody>';
        
        $records = $data['records'] ?? [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $this->renderTableRow($record, $data);
            }
        } else {
            $colspan = count($this->table_config['columns'] ?? []) + 1;
            if ($this->table_config['show_checkbox'] ?? true) $colspan++;
            
            echo '<tr>';
            echo '<td colspan="' . $colspan . '" class="uk-text-center">';
            echo htmlspecialchars($this->table_config['empty_message'] ?? 'No records found');
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
    }
    
    /**
     * Render table row
     */
    protected function renderTableRow(object $record, array $data): void {
        echo '<tr>';
        
        if ($this->table_config['show_checkbox'] ?? true) {
            echo '<td>';
            echo '<input type="checkbox" name="ids[]" value="' . $record->id . '" class="uk-checkbox record-checkbox">';
            echo '</td>';
        }
        
        foreach ($this->table_config['columns'] ?? [] as $column) {
            $this->renderTableCell($record, $column, $data);
        }
        
        if ($this->table_config['show_actions'] ?? true) {
            $this->renderActionCell($record, $data);
        }
        
        echo '</tr>';
    }
    
    /**
     * Render table cell
     */
    protected function renderTableCell(object $record, array $column, array $data): void {
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
    protected function renderActionCell(object $record, array $data): void {
        echo '<td>';
        echo '<div class="uk-button-group">';
        
        foreach ($this->table_config['actions'] ?? [] as $action) {
            $this->renderAction($record, $action, $data);
        }
        
        echo '</div>';
        echo '</td>';
    }
    
    /**
     * Render action button
     */
    protected function renderAction(object $record, array $action, array $data): void {
        $href = $action['href'] ?? '#';
        $icon = $action['icon'] ?? 'info';
        $tooltip = $action['tooltip'] ?? '';
        $class = $action['class'] ?? 'uk-icon-link';
        $attributes = $action['attributes'] ?? '';

        // Replace placeholders in href and tooltip
        $href = str_replace('{id}', $record->id, $href);
        $tooltip = str_replace('{id}', $record->id, $tooltip);
        
        if (isset($action['condition']) && is_callable($action['condition'])) {
            if (!$action['condition']($record, $data)) {
                return;
            }
        }
        
        echo '<a href="' . htmlspecialchars($href) . '" ';
        echo 'class="' . $class . '" ';
        echo 'uk-icon="' . $icon . '" ';
        if ($tooltip) {
            echo 'uk-tooltip="' . htmlspecialchars($tooltip) . '" ';
        }
        echo $attributes;
        echo '></a>';
    }
    
    /**
     * Render table footer
     */
    protected function renderTableFooter(array $data): void {
        // Same as header for consistency
        echo '<tfoot>';
        $this->renderTableHeader($data);
        echo '</tfoot>';
    }
    
    /**
     * Render modals
     */
    protected function renderModals(array $data): void {
        foreach ($this->modal_config as $modal_type => $config) {
            switch ($modal_type) {
                case 'create':
                    $this->renderCreateModal($config, $data);
                    break;
                case 'edit':
                    $this->renderEditModals($config, $data);
                    break;
                case 'delete':
                    $this->renderDeleteModals($config, $data);
                    break;
            }
        }
    }
    
    /**
     * Render create modal
     */
    protected function renderCreateModal(array $config, array $data): void {
        echo '<div id="create-modal" uk-modal>';
        echo '<div class="uk-modal-dialog uk-modal-body">';
        echo '<button class="uk-modal-close-default" type="button" uk-close></button>';
        echo '<div class="uk-modal-header">';
        echo '<h2 class="uk-modal-title">' . htmlspecialchars($config['title'] ?? 'Create New') . '</h2>';
        echo '</div>';
        echo '<form method="POST" action="">';
        echo '<div class="uk-modal-body">';
        echo '<input type="hidden" name="form_action" value="create">';
        
        foreach ($config['fields'] ?? [] as $field) {
            $this->renderFormField($field, null, $data);
        }
        
        echo '</div>';
        echo '<div class="uk-modal-footer uk-text-right">';
        echo '<button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>';
        echo '<button class="uk-button uk-button-primary" type="submit">Create</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render edit modals for each record
     */
    protected function renderEditModals(array $config, array $data): void {
        $records = $data['records'] ?? [];
        foreach ($records as $record) {
            echo '<div id="edit-modal-' . $record->id . '" uk-modal>';
            echo '<div class="uk-modal-dialog">';
            echo '<button class="uk-modal-close-default" type="button" uk-close></button>';
            echo '<div class="uk-modal-header">';
            echo '<h2 class="uk-modal-title">' . htmlspecialchars($config['title'] ?? 'Edit') . '</h2>';
            echo '</div>';
            echo '<form method="POST" action="">';
            echo '<div class="uk-modal-body">';
            echo '<input type="hidden" name="form_action" value="update">';
            echo '<input type="hidden" name="id" value="' . $record->id . '">';
            
            foreach ($config['fields'] ?? [] as $field) {
                $this->renderFormField($field, $record, $data);
            }
            
            echo '</div>';
            echo '<div class="uk-modal-footer uk-text-right">';
            echo '<button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>';
            echo '<button class="uk-button uk-button-primary" type="submit">Save</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Render delete modals for each record
     */
    protected function renderDeleteModals(array $config, array $data): void {
        $records = $data['records'] ?? [];
        foreach ($records as $record) {
            echo '<div id="delete-modal-' . $record->id . '" uk-modal>';
            echo '<div class="uk-modal-dialog uk-modal-body">';
            echo '<button class="uk-modal-close-default" type="button" uk-close></button>';
            echo '<h2 class="uk-modal-title">' . htmlspecialchars($config['title'] ?? 'Delete') . '</h2>';
            echo '<p>' . htmlspecialchars($config['message'] ?? 'Are you sure you want to delete this item?') . '</p>';
            echo '<p class="uk-text-danger">This action cannot be undone.</p>';
            echo '<form method="POST" action="">';
            echo '<input type="hidden" name="form_action" value="delete">';
            echo '<input type="hidden" name="id" value="' . $record->id . '">';
            echo '<div class="uk-modal-footer uk-text-right">';
            echo '<button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>';
            echo '<button class="uk-button uk-button-danger" type="submit">Delete</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Render form field
     */
    protected function renderFormField(array $field, ?object $record, array $data): void {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $required = $field['required'] ?? false;
        $value = $record ? ($record->{$name} ?? '') : ($field['default'] ?? '');
        $wrapper_class = $field['wrapper_class'] ?? '';
        
        $field_id = $record ? $name . '_' . $record->id : $name;
        
        if ($wrapper_class) {
            echo '<div class="' . $wrapper_class . '">';
        } else {
            echo '<div class="uk-margin-small">';
        }
        
        echo '<label class="uk-form-label" for="' . $field_id . '">' . htmlspecialchars($label) . '</label>';
        echo '<div class="uk-form-controls">';
        
        switch ($type) {
            case 'select':
                $this->renderSelectField($field, $field_id, $value, $data);
                break;
            case 'textarea':
                $this->renderTextareaField($field, $field_id, $value, $required);
                break;
            default:
                $this->renderInputField($field, $field_id, $value, $required);
                break;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render select field
     */
    protected function renderSelectField(array $field, string $field_id, mixed $value, array $data): void {
        $required = $field['required'] ?? false;
        echo '<select class="uk-select" id="' . $field_id . '" name="' . $field['name'] . '"' . ($required ? ' required' : '') . '>';
        
        foreach ($field['options'] ?? [] as $option_value => $option_label) {
            $selected = ($value == $option_value) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($option_value) . '"' . $selected . '>';
            echo htmlspecialchars($option_label);
            echo '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * Render textarea field
     */
    protected function renderTextareaField(array $field, string $field_id, mixed $value, bool $required): void {
        echo '<textarea class="uk-textarea" id="' . $field_id . '" name="' . $field['name'] . '"';
        if ($required) echo ' required';
        if (isset($field['rows'])) echo ' rows="' . $field['rows'] . '"';
        echo '>' . htmlspecialchars($value) . '</textarea>';
    }
    
    /**
     * Render input field
     */
    protected function renderInputField(array $field, string $field_id, mixed $value, bool $required): void {
        $type = $field['input_type'] ?? 'text';
        echo '<input class="uk-input" id="' . $field_id . '" name="' . $field['name'] . '" type="' . $type . '"';
        echo ' value="' . htmlspecialchars($value) . '"';
        if ($required) echo ' required';
        if (isset($field['min'])) echo ' min="' . $field['min'] . '"';
        if (isset($field['max'])) echo ' max="' . $field['max'] . '"';
        echo '>';
    }
    
    /**
     * Render footer
     */
    protected function renderFooter(): void {
        echo '</div>';
        KPT::pull_footer();
    }
}