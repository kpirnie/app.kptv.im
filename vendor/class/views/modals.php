<?php
/**
 * Modal Renderer Component
 * 
 * Separates modal rendering logic for better modularity
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class ModalRenderer {
    
    /**
     * Render a create modal
     */
    public static function renderCreateModal(array $config, array $data = []): string {
        ob_start();
        
        echo '<div id="create-modal" uk-modal>';
        echo '<div class="uk-modal-dialog uk-modal-body">';
        echo '<button class="uk-modal-close-default" type="button" uk-close></button>';
        echo '<div class="uk-modal-header">';
        echo '<h2 class="uk-modal-title">' . htmlspecialchars($config['title'] ?? 'Create New') . '</h2>';
        echo '</div>';
        echo '<form method="POST" action="">';
        echo '<div class="uk-modal-body">';
        echo '<input type="hidden" name="form_action" value="create">';
        
        self::renderFields($config['fields'] ?? [], null, $data);
        
        echo '</div>';
        echo '<div class="uk-modal-footer uk-text-right">';
        echo '<button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>';
        echo '<button class="uk-button uk-button-primary" type="submit">Create</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Render edit modals for each record
     */
    public static function renderEditModals(array $config, array $records = []): string {
        ob_start();
        
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
            
            self::renderFields($config['fields'] ?? [], $record, []);
            
            echo '</div>';
            echo '<div class="uk-modal-footer uk-text-right">';
            echo '<button class="uk-button uk-button-secondary uk-modal-close" type="button">Cancel</button>';
            echo '<button class="uk-button uk-button-primary" type="submit">Save</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render delete modals for each record
     */
    public static function renderDeleteModals(array $config, array $records = []): string {
        ob_start();
        
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
        
        return ob_get_clean();
    }
    
    /**
     * Render form field
     */
    private static function renderFormField(array $field, ?object $record, array $data): void {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $required = $field['required'] ?? false;
        $value = $record ? ($record->{$name} ?? '') : ($field['default'] ?? '');
        $wrapper_class = $field['wrapper_class'] ?? '';
        
        $field_id = $record ? $name . '_' . $record->id : $name;
        
        // For fields that should be in a grid, just render the field content
        // The grid container will be handled by grouping consecutive grid fields
        if ($wrapper_class) {
            echo '<div>';
        } else {
            echo '<div class="uk-margin-small">';
        }
        
        echo '<label class="uk-form-label" for="' . $field_id . '">' . htmlspecialchars($label) . '</label>';
        echo '<div class="uk-form-controls">';
        
        switch ($type) {
            case 'select':
                self::renderSelectField($field, $field_id, $value, $data);
                break;
            case 'textarea':
                self::renderTextareaField($field, $field_id, $value, $required);
                break;
            default:
                self::renderInputField($field, $field_id, $value, $required);
                break;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Group and render fields with proper grid handling using 'group' configuration
     */
    private static function renderFields(array $fields, ?object $record, array $data): void {
        $grouped_fields = [];
        $ungrouped_fields = [];
        
        // Separate grouped and ungrouped fields
        foreach ($fields as $field) {
            if (isset($field['group'])) {
                $grouped_fields[$field['group']][] = $field;
            } else {
                $ungrouped_fields[] = $field;
            }
        }
        
        // Render ungrouped fields first
        foreach ($ungrouped_fields as $field) {
            echo '<div class="uk-margin-small">';
            self::renderFieldContent($field, $record, $data);
            echo '</div>';
        }
        
        // Render grouped fields
        foreach ($grouped_fields as $group_name => $group_fields) {
            echo '<div class="uk-child-width-1-2 uk-grid-small" uk-grid>';
            foreach ($group_fields as $field) {
                echo '<div>';
                self::renderFieldContent($field, $record, $data);
                echo '</div>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Render field content (label + control)
     */
    private static function renderFieldContent(array $field, ?object $record, array $data): void {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $required = $field['required'] ?? false;
        $value = $record ? ($record->{$name} ?? '') : ($field['default'] ?? '');
        
        $field_id = $record ? $name . '_' . $record->id : $name;
        
        echo '<label class="uk-form-label" for="' . $field_id . '">' . htmlspecialchars($label) . '</label>';
        echo '<div class="uk-form-controls">';
        
        switch ($type) {
            case 'select':
                self::renderSelectField($field, $field_id, $value, $data);
                break;
            case 'textarea':
                self::renderTextareaField($field, $field_id, $value, $required);
                break;
            default:
                self::renderInputField($field, $field_id, $value, $required);
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render select field
     */
    private static function renderSelectField(array $field, string $field_id, mixed $value, array $data): void {
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
    private static function renderTextareaField(array $field, string $field_id, mixed $value, bool $required): void {
        echo '<textarea class="uk-textarea" id="' . $field_id . '" name="' . $field['name'] . '"';
        if ($required) echo ' required';
        if (isset($field['rows'])) echo ' rows="' . $field['rows'] . '"';
        echo '>' . htmlspecialchars($value) . '</textarea>';
    }
    
    /**
     * Render input field
     */
    private static function renderInputField(array $field, string $field_id, mixed $value, bool $required): void {
        $type = $field['input_type'] ?? 'text';
        echo '<input class="uk-input" id="' . $field_id . '" name="' . $field['name'] . '" type="' . $type . '"';
        echo ' value="' . htmlspecialchars($value) . '"';
        if ($required) echo ' required';
        if (isset($field['min'])) echo ' min="' . $field['min'] . '"';
        if (isset($field['max'])) echo ' max="' . $field['max'] . '"';
        echo '>';
    }
}
