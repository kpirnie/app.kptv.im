<?php
/**
 * Filters View Configuration
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

if( ! class_exists( 'FiltersViewConfig' ) ) {

    /**
     * Filters View Configuration Class
     * 
     * @since 8.4
     * @package KPTV Stream Manager
     */
    class FiltersViewConfig {
        
        public static function getConfig(): array {
            return [
                'table' => [
                    'show_checkbox' => true,
                    'show_actions' => true,
                    'empty_message' => 'No filters found',
                    'columns' => [
                        [
                            'key' => 'sf_active',
                            'label' => 'Active',
                            'sortable' => true,
                            'renderer' => function($record) {
                                return '<span class="active-toggle" data-id="' . $record->id . '">' .
                                    '<i uk-icon="' . ($record->sf_active ? 'check' : 'close') . '" class="me"></i>' .
                                    '</span>';
                            }
                        ],
                        [
                            'key' => 'sf_type_id',
                            'label' => 'Type',
                            'sortable' => true,
                            'renderer' => function($record) {
                                $types = [
                                    0 => 'Include Name (regex)',
                                    1 => 'Exclude Name',
                                    2 => 'Exclude Name (regex)',
                                    3 => 'Exclude Stream (regex)',
                                    4 => 'Exclude Group (regex)'
                                ];
                                return htmlspecialchars($types[$record->sf_type_id] ?? 'Unknown');
                            }
                        ],
                        [
                            'key' => 'sf_filter',
                            'label' => 'Filter',
                            'sortable' => true
                        ]
                    ],
                    'actions' => [
                        [
                            'href' => '#edit-modal-{id}',
                            'icon' => 'pencil',
                            'tooltip' => 'Edit this Filter',
                            'attributes' => 'uk-toggle'
                        ],
                        [
                            'href' => '#delete-modal-{id}',
                            'icon' => 'trash',
                            'tooltip' => 'Delete this Filter',
                            'attributes' => 'uk-toggle'
                        ]
                    ]
                ],
                'modals' => [
                    'create' => [
                        'title' => 'Add New Filter',
                        'fields' => [
                            [
                                'name' => 'sf_active',
                                'label' => 'Active',
                                'type' => 'select',
                                'wrapper_class' => 'uk-child-width-1-2 uk-grid-small',
                                'options' => [1 => 'Yes', 0 => 'No'],
                                'default' => 1,
                                'group' => 'filt_config',
                            ],
                            [
                                'name' => 'sf_type_id',
                                'label' => 'Filter Type',
                                'type' => 'select',
                                'options' => [
                                    0 => 'Include Name (regex)',
                                    1 => 'Exclude Name',
                                    2 => 'Exclude Name (regex)',
                                    3 => 'Exclude Stream (regex)',
                                    4 => 'Exclude Group (regex)'
                                ],
                                'group' => 'filt_config',
                            ],
                            [
                                'name' => 'sf_filter',
                                'label' => 'Filter Value',
                                'type' => 'text',
                                'required' => true
                            ]
                        ]
                    ],
                    'edit' => [
                        'title' => 'Edit Filter',
                        'fields' => [
                            [
                                'name' => 'sf_active',
                                'label' => 'Active',
                                'type' => 'select',
                                'wrapper_class' => 'uk-child-width-1-2 uk-grid-small',
                                'options' => [1 => 'Yes', 0 => 'No'],
                                'group' => 'filt_config',
                            ],
                            [
                                'name' => 'sf_type_id',
                                'label' => 'Filter Type',
                                'type' => 'select',
                                'options' => [
                                    0 => 'Include Name (regex)',
                                    1 => 'Exclude Name',
                                    2 => 'Exclude Name (regex)',
                                    3 => 'Exclude Stream (regex)',
                                    4 => 'Exclude Group (regex)'
                                ],
                                'group' => 'filt_config',
                            ],
                            [
                                'name' => 'sf_filter',
                                'label' => 'Filter Value',
                                'type' => 'text',
                                'required' => true
                            ]
                        ]
                    ],
                    'delete' => [
                        'title' => 'Delete Filter',
                        'message' => 'Are you sure you want to delete this filter?'
                    ]
                ]
            ];
        }
    }


}
