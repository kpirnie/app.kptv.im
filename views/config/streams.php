<?php
/**
 * Streams View Configuration - Fixed
 * 
 * @since 8.4
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

// make sure it doesn't already exist
if (!class_exists('StreamsViewConfig')) {

    /**
     * Streams View Configuration
     * 
     * @since 8.4
     * @package KP Library
     */
    class StreamsViewConfig {
        
        /** 
         * getConfig
         * 
         * Get the config for the streams view
         * 
         * @param string The type filter
         * @return array Returns an array of the configuration options
         */
        public static function getConfig(string $type_filter = 'live'): array {
            
            // Define actions dynamically based on type_filter
            $actions = [
                [
                    'href' => '#',
                    'icon' => 'play',
                    'tooltip' => 'Try to Play This Stream',
                    'class' => 'uk-link-icon play-stream',
                    'attributes' => function($record) {
                        return 'data-stream-url="' . htmlspecialchars($record->s_stream_uri) . '"';
                    }
                ],
                [
                    'href' => fn($record) => htmlspecialchars($record->s_stream_uri),
                    'icon' => 'link',
                    'tooltip' => 'Copy the Stream URL',
                    'class' => 'uk-link-icon copy-link'
                ],
                [
                    'href' => '#',
                    'icon' => ($type_filter == 'live') ? 'album' : 'tv',
                    'tooltip' => ($type_filter == 'live') ? 'Move to Series List' : 'Move to Live List',
                    'class' => ($type_filter == 'live') ? 'uk-link-icon move-to-series single-move' : 'uk-link-icon move-to-live single-move'
                ],
                [
                    'href' => '#',
                    'icon' => 'nut',
                    'tooltip' => 'Move to Other List',
                    'class' => 'uk-link-icon move-to-other single-move'
                ],
                [
                    'href' => '#edit-modal-{id}',
                    'icon' => 'pencil',
                    'tooltip' => 'Edit this Stream',
                    'attributes' => 'uk-toggle'
                ],
                [
                    'href' => '#delete-modal-{id}',
                    'icon' => 'trash',
                    'tooltip' => 'Delete this Stream',
                    'attributes' => 'uk-toggle'
                ]
            ];
            
            // return the config array
            return [
                'table' => [
                    'show_checkbox' => true,
                    'show_actions' => true,
                    'empty_message' => 'No streams found',
                    'columns' => [
                        [
                            'key' => 's_active',
                            'label' => 'Active',
                            'sortable' => true,
                            'renderer' => fn($record) => '<span class="active-toggle" data-id="' . $record->id . '" uk-tooltip="' . 
                                        ($record->s_active ? 'Deactivate' : 'Activate') . ' This Stream">
                                        <i uk-icon="' . ($record->s_active ? 'check' : 'close') . '" class="me"></i>
                                    </span>',
                        ],
                        [
                            'key' => 's_channel',
                            'label' => 'Channel',
                            'sortable' => true,
                            'renderer' => fn($record) => '<span class="stream-channel channel-cell">' . htmlspecialchars($record->s_channel ?? '') . '</span>',
                        ],
                        [
                            'key' => 's_name',
                            'label' => 'Name',
                            'sortable' => true,
                            'renderer' => fn($record) => '<span class="stream-name name-cell">' . htmlspecialchars($record->s_name ?? '') . '</span>',
                        ],
                        [
                            'key' => 's_orig_name',
                            'label' => 'Original Name',
                            'sortable' => true,
                            'truncate' => true
                        ],
                        [
                            'key' => 'provider_name',
                            'label' => 'Provider',
                            'sortable' => true,
                            'truncate' => true,
                            'renderer' => fn($record) => htmlspecialchars($record->provider_name ?? 'N/A'),
                        ]
                    ],
                    'actions' => $actions
                ],
                'modals' => [
                    'create' => [
                        'title' => 'Add New Stream',
                        'fields' => [
                            [
                                'name' => 's_active',
                                'label' => 'Active',
                                'type' => 'select',
                                'group' => 'basic',
                                'options' => [1 => 'Yes', 0 => 'No'],
                                'default' => 1
                            ],
                            [
                                'name' => 's_type_id',
                                'label' => 'Stream Type',
                                'type' => 'select',
                                'group' => 'basic',
                                'options' => [0 => 'Live', 4 => 'VOD', 5 => 'Series']
                            ],
                            [
                                'name' => 's_channel',
                                'label' => 'Channel Number',
                                'type' => 'text',
                                'group' => 'info',
                                'default' => '0'
                            ],
                            [
                                'name' => 'p_id',
                                'label' => 'Provider',
                                'type' => 'select',
                                'group' => 'info',
                                'options' => [] // Will be populated dynamically
                            ],
                            [
                                'name' => 's_name',
                                'label' => 'Name',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 's_orig_name',
                                'label' => 'Original Name',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 's_stream_uri',
                                'label' => 'Stream URI',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 's_tvg_logo',
                                'label' => 'TVG Logo',
                                'type' => 'text'
                            ],
                            [
                                'name' => 's_tvg_id',
                                'label' => 'TVG ID',
                                'type' => 'text',
                                'group' => 'tvg'
                            ],
                            [
                                'name' => 's_tvg_group',
                                'label' => 'TVG Group',
                                'type' => 'text',
                                'group' => 'tvg'
                            ]
                        ]
                    ],
                    'edit' => [
                        'title' => 'Edit Stream',
                        'fields' => [
                            [
                                'name' => 's_active',
                                'label' => 'Active',
                                'type' => 'select',
                                'group' => 'basic',
                                'options' => [1 => 'Yes', 0 => 'No']
                            ],
                            [
                                'name' => 's_type_id',
                                'label' => 'Stream Type',
                                'type' => 'select',
                                'group' => 'basic',
                                'options' => [0 => 'Live', 4 => 'VOD', 5 => 'Series']
                            ],
                            [
                                'name' => 's_channel',
                                'label' => 'Channel Number',
                                'type' => 'text',
                                'group' => 'info'
                            ],
                            [
                                'name' => 'p_id',
                                'label' => 'Provider',
                                'type' => 'select',
                                'group' => 'info',
                                'options' => [] // Will be populated dynamically
                            ],
                            [
                                'name' => 's_name',
                                'label' => 'Name',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 's_orig_name',
                                'label' => 'Original Name',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 's_stream_uri',
                                'label' => 'Stream URI',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 's_tvg_logo',
                                'label' => 'TVG Logo',
                                'type' => 'text'
                            ],
                            [
                                'name' => 's_tvg_id',
                                'label' => 'TVG ID',
                                'type' => 'text',
                                'group' => 'tvg'
                            ],
                            [
                                'name' => 's_tvg_group',
                                'label' => 'TVG Group',
                                'type' => 'text',
                                'group' => 'tvg'
                            ]
                        ]
                    ],
                    'delete' => [
                        'title' => 'Delete Stream',
                        'message' => 'Are you sure you want to delete this stream?'
                    ]
                ]
            ];
        }
    }
}
