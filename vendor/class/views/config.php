<?php
/**
 * Complete View Configuration Classes
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

/**
 * Base Configuration Helper
 */
class BaseViewConfig {
    
    /**
     * Get common field configurations
     */
    public static function getCommonFields(): array {
        return [
            'active_field' => [
                'name' => 'active',
                'label' => 'Active',
                'type' => 'select',
                'options' => [1 => 'Yes', 0 => 'No'],
                'default' => 1
            ],
            'priority_field' => [
                'name' => 'priority',
                'label' => 'Priority',
                'type' => 'number',
                'input_type' => 'number',
                'min' => 0,
                'max' => 99,
                'default' => 50
            ],
            'name_field' => [
                'name' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'required' => true
            ]
        ];
    }
    
    /**
     * Get common table actions
     */
    public static function getCommonActions(): array {
        return [
            'edit' => [
                'href' => '#edit-modal-{id}',
                'icon' => 'pencil',
                'tooltip' => 'Edit this item',
                'attributes' => 'uk-toggle'
            ],
            'delete' => [
                'href' => '#delete-modal-{id}',
                'icon' => 'trash',
                'tooltip' => 'Delete this item',
                'attributes' => 'uk-toggle'
            ]
        ];
    }
    
    /**
     * Get responsive column configuration
     */
    public static function getResponsiveConfig(): array {
        return [
            'mobile_hidden' => 'uk-visible@s',
            'tablet_hidden' => 'uk-visible@m',
            'desktop_hidden' => 'uk-visible@l'
        ];
    }
}
