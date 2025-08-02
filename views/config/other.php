<?php
/**
 * Other View Configuration
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure the class doesn't already exit
if( ! class_exists( 'OtherViewConfig' ) ) {

    /**
     * Other View Configuration
     * 
     * @since 8.4
     * @package KPTV Stream Manager
     */
    class OtherViewConfig {
        
        public static function getConfig(): array {
            return [
                'table' => [
                    'show_checkbox' => true,
                    'show_actions' => true,
                    'empty_message' => 'No streams found',
                    'columns' => [
                        [
                            'key' => 's_orig_name',
                            'label' => 'Original Name',
                            'sortable' => true
                        ],
                        [
                            'key' => 's_stream_uri',
                            'label' => 'Stream URI',
                            'sortable' => true,
                            'truncate' => true
                        ],
                        [
                            'key' => 'provider_name',
                            'label' => 'Provider',
                            'sortable' => true,
                            'renderer' => function($record) {
                                return htmlspecialchars($record->provider_name ?? 'N/A');
                            }
                        ]
                    ],
                    'actions' => [
                        [
                            'href' => function($record) { return htmlspecialchars($record->s_stream_uri); },
                            'icon' => 'play',
                            'tooltip' => 'Try to Play This Stream'
                        ],
                        [
                            'href' => function($record) { return htmlspecialchars($record->s_stream_uri); },
                            'icon' => 'link',
                            'tooltip' => 'Copy the Stream URL',
                            'class' => 'uk-link-icon copy-link'
                        ],
                        [
                            'href' => '#delete-modal-{id}',
                            'icon' => 'trash',
                            'tooltip' => 'Delete this Stream',
                            'attributes' => 'uk-toggle'
                        ]
                    ]
                ],
                'modals' => [
                    'create' => [
                        'title' => 'Add New Stream',
                        'fields' => [
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
                                'name' => 'p_id',
                                'label' => 'Provider',
                                'type' => 'select',
                                'required' => true,
                                'options' => [] // Will be populated dynamically
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
