<?php
/**
 * Streams View Configuration
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

// make sure it doesn't already exist
if( ! class_exists( 'StreamsViewConfig' ) ) {

    /**
     * Streams View Configuration
     * 
     * @since 8.4
     * @package KPTV Stream Manager
     */
    class StreamsViewConfig {
        
        /** 
         * getConfig
         * 
         * Get the config for the other streams view
         * 
         * @param string The type filter
         * @return array Returns an array of the configuraiton options
         */
        public static function getConfig( string $type_filter = 'live' ) : array {
            
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
                            'renderer' => fn( $record ) => '<span class="active-toggle" data-id="' . $record -> id . '" uk-tooltip="' . 
                                        ( $record -> s_active ? 'Deactivate' : 'Activate' ) . ' This Stream">
                                        <i uk-icon="' . ( $record -> s_active ? 'check' : 'close' ) . '" class="me"></i>
                                    </span>',
                        ],
                        [
                            'key' => 's_name',
                            'label' => 'Name',
                            'sortable' => true,
                            'renderer' => fn( $record ) => '<span class="stream-name name-cell">' . htmlspecialchars( $record -> s_name ) . '</span>',
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
                            'renderer' => fn( $record ) => htmlspecialchars( $record -> provider_name ?? 'N/A' ),
                        ]
                    ],
                    'actions' => [
                        [
                            'href' => fn( $record ) => htmlspecialchars( $record -> s_stream_uri ),
                            'icon' => 'play',
                            'tooltip' => 'Try to Play This Stream'
                        ],
                        [
                            'href' => fn( $record ) => htmlspecialchars( $record -> s_stream_uri ),
                            'icon' => 'link',
                            'tooltip' => 'Copy the Stream URL',
                            'class' => 'uk-link-icon copy-link'
                        ],
                        [
                            'href' => '#',
                            'icon' => fn( $record, $data ) => $type_filter == 'live' ? 'album' : 'tv',
                            'tooltip' => fn( $record, $data ) => $type_filter == 'live' ? 'Move to Series List' : 'Move to Live List',
                            'class' => fn( $record, $data ) => $type_filter == 'live' ? 'uk-link-icon move-to-series single-move' : 'uk-link-icon move-to-live single-move'
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
                    ]
                ],
                'modals' => [
                    'create' => [
                        'title' => 'Add New Stream',
                        'fields' => [
                            [
                                'name' => 's_active',
                                'label' => 'Active',
                                'type' => 'select',
                                'wrapper_class' => 'uk-child-width-1-2 uk-grid-small',
                                'options' => [1 => 'Yes', 0 => 'No'],
                                'default' => 1
                            ],
                            [
                                'name' => 's_type_id',
                                'label' => 'Stream Type',
                                'type' => 'select',
                                'options' => [0 => 'Live', 4 => 'VOD', 5 => 'Series']
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
                                'name' => 'p_id',
                                'label' => 'Provider',
                                'type' => 'select',
                                'options' => [] // Will be populated dynamically
                            ],
                            [
                                'name' => 's_tvg_id',
                                'label' => 'TVG ID',
                                'type' => 'text',
                                'wrapper_class' => 'uk-child-width-1-2 uk-grid-small'
                            ],
                            [
                                'name' => 's_tvg_group',
                                'label' => 'TVG Group',
                                'type' => 'text'
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
                                'wrapper_class' => 'uk-child-width-1-2 uk-grid-small',
                                'options' => [1 => 'Yes', 0 => 'No']
                            ],
                            [
                                'name' => 's_type_id',
                                'label' => 'Stream Type',
                                'type' => 'select',
                                'options' => [0 => 'Live', 4 => 'VOD', 5 => 'Series']
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
                                'name' => 'p_id',
                                'label' => 'Provider',
                                'type' => 'select',
                                'options' => [] // Will be populated dynamically
                            ],
                            [
                                'name' => 's_tvg_id',
                                'label' => 'TVG ID',
                                'type' => 'text',
                                'wrapper_class' => 'uk-child-width-1-2 uk-grid-small'
                            ],
                            [
                                'name' => 's_tvg_group',
                                'label' => 'TVG Group',
                                'type' => 'text'
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
